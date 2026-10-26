<?php
declare(strict_types=1);

/** Report catalogue. Every report is a plain query function returning columns and rows; one engine renders HTML, CSV and PDF. */
final class Reports
{
    public static function catalogue(): array
    {
        $dateAll = ['date'];
        return [
            // ---- properties ----
            'properties' => ['Properties', 'All properties', 'Every property with owner, agent and status. Filter by status to get available, occupied (rented) or sold lists.', ['properties.view'], ['date', 'status', 'type', 'area', 'agent', 'property'], 'Added between', 'rpProperties'],
            'properties_by_area' => ['Properties', 'Properties by location', 'Count of properties per area, split by status.', ['properties.view'], [], null, 'rpByArea'],
            'properties_by_type' => ['Properties', 'Properties by type', 'Count of properties per type, split by status.', ['properties.view'], [], null, 'rpByType'],
            // ---- tenants ----
            'tenancies' => ['Tenants & tenancies', 'Tenancies (active, expiring, expired, history)', 'Choose a status to see active tenants, expiring or expired tenancies, or leave blank for full history.', ['tenancies.view'], ['date', 'tstatus', 'property', 'customer', 'agent'], 'Tenancy ends between', 'rpTenancies'],
            'tenant_history' => ['Tenants & tenancies', 'Tenant history', 'One line per tenant: how many tenancies, totals charged, paid and still owed.', ['tenancies.view'], ['date', 'customer'], 'Tenancy starts between', 'rpTenantHistory'],
            // ---- financial ----
            'collections_daily' => ['Financial', 'Daily collections', 'Money received per day (valid payments only).', ['payments.view'], ['date', 'ptype', 'method', 'agent'], 'Payment date between', 'rpCollectionsDaily'],
            'collections_monthly' => ['Financial', 'Monthly collections', 'Money received per month, with voided payments shown separately.', ['payments.view'], ['date', 'ptype', 'method', 'agent'], 'Payment date between', 'rpCollectionsMonthly'],
            'rent_collections' => ['Financial', 'Rent collections', 'Every rent payment with tenant and property.', ['payments.view'], ['date', 'customer', 'property', 'pstatus'], 'Payment date between', 'rpRent'],
            'sales_income' => ['Financial', 'Sales income', 'Every payment received towards house and land sales.', ['payments.view', 'sales.view'], ['date', 'customer', 'property', 'pstatus'], 'Payment date between', 'rpSalesIncome'],
            'outstanding' => ['Financial', 'Outstanding balances', 'Unpaid balances on tenancies and sales.', ['payments.view', 'tenancies.view', 'sales.view'], ['customer', 'property'], null, 'rpOutstanding'],
            'payment_history' => ['Financial', 'Payment history', 'Every payment with receipt number, method and who recorded it.', ['payments.view'], ['date', 'ptype', 'method', 'pstatus', 'customer', 'property', 'agent'], 'Payment date between', 'rpPayments'],
            'revenue_by_period' => ['Financial', 'Revenue by period', 'Income per month broken down by payment type (valid payments only).', ['payments.view'], ['date'], 'Payment date between', 'rpRevenue'],
            // ---- sales ----
            'houses_sold' => ['Sales', 'House sales', 'House sales with price, paid and outstanding. Choose "Completed" for houses sold.', ['sales.view'], ['date', 'sstatus', 'agent', 'customer', 'property'], 'Sale created between', 'rpHouseSales'],
            'land_sold' => ['Sales', 'Land / plot sales', 'Plot sales with price, paid and outstanding. Choose "Completed" for land sold.', ['sales.view'], ['date', 'sstatus', 'agent', 'customer', 'property'], 'Sale created between', 'rpLandSales'],
            'sales_by_agent' => ['Sales', 'Sales by agent', 'Number, value, collections and commission per agent.', ['sales.view'], ['date', 'sstatus'], 'Sale created between', 'rpSalesByAgent'],
            'sales_by_date' => ['Sales', 'Sales by month', 'Sales created per month with value and collections.', ['sales.view'], ['date', 'sstatus'], 'Sale created between', 'rpSalesByDate'],
            'sales_outstanding' => ['Sales', 'Outstanding sales balances', 'Open sales that still owe money, with the next instalment due.', ['sales.view'], ['customer', 'property'], null, 'rpSalesOutstanding'],
            // ---- management ----
            'agent_activity' => ['Management', 'Staff activity', 'What each staff member added or recorded in the period.', ['customers.view'], ['date'], 'Between', 'rpAgentActivity'],
            'customer_activity' => ['Management', 'Customer activity', 'New people and new roles added per month.', ['customers.view'], ['date'], 'Added between', 'rpCustomerActivity'],
            'audit_activity' => ['Management', 'Audit activity', 'Counts of recorded actions per user in the period.', ['audit.view'], ['date'], 'Between', 'rpAuditActivity'],
        ];
    }

    public static function allowed(array $def): bool
    {
        if (!can('reports.view')) return false;
        foreach ($def[3] as $p) if (!can($p)) return false;
        return true;
    }

    public static function filterValues(): array
    {
        $g = fn($k) => is_array($_GET[$k] ?? null) ? '' : trim((string)($_GET[$k] ?? ''));
        $d = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $g($k)) ? $g($k) : '';
        return ['from' => $d('from'), 'to' => $d('to'), 'status' => $g('status'), 'type' => $g('type'), 'area' => $g('area'), 'agent' => (int)$g('agent'), 'property' => $g('property'),
                'customer' => $g('customer'), 'tstatus' => $g('tstatus'), 'ptype' => $g('ptype'), 'method' => $g('method'), 'pstatus' => $g('pstatus'), 'sstatus' => $g('sstatus')];
    }

    /** @return array{0:string,1:array} */
    private static function dateRange(string $col, array $f, array &$p, string $tag = 'd'): string
    {
        $w = '';
        if ($f['from'] !== '') { $w .= " AND $col >= :{$tag}f"; $p["{$tag}f"] = $f['from']; }
        if ($f['to'] !== '') { $w .= " AND $col <= :{$tag}t"; $p["{$tag}t"] = $f['to']; }
        return $w;
    }
    private static function like(string $fv, array $cols, array &$p, string $tag): string
    {
        if ($fv === '') return '';
        $p[$tag] = like_escape($fv);
        return ' AND (' . implode(' OR ', array_map(fn($c) => "$c LIKE :$tag", $cols)) . ')';
    }
    private static function col(string $label, string $key, string $kind = 'text'): array { return ['label' => $label, 'key' => $key, 'kind' => $kind]; }

    /* ---------------- properties ---------------- */
    public static function rpProperties(array $f): array
    {
        $p = []; $w = 'p.deleted_at IS NULL' . self::dateRange('DATE(p.created_at)', $f, $p);
        if (isset(list_property_statuses()[$f['status']])) { $w .= ' AND p.status = :st'; $p['st'] = $f['status']; }
        if (isset(list_property_types()[$f['type']])) { $w .= ' AND p.property_type = :ty'; $p['ty'] = $f['type']; }
        if ($f['area'] !== '') { $w .= ' AND p.area LIKE :ar'; $p['ar'] = like_escape($f['area']); }
        if ($f['agent']) { $w .= ' AND p.agent_id = :ag'; $p['ag'] = $f['agent']; }
        $w .= self::like($f['property'], ['p.ref', 'p.title', 'p.address'], $p, 'qp');
        $rows = db_all("SELECT p.ref, p.title, p.property_type, p.area, p.listing_for, p.rent_price, p.sale_price, p.status, lc.full_name owner, u.full_name agent, DATE(p.created_at) added
            FROM properties p JOIN customers lc ON lc.id = p.landlord_id LEFT JOIN users u ON u.id = p.agent_id WHERE $w ORDER BY p.id DESC LIMIT 50000", $p);
        foreach ($rows as &$r) { $r['property_type'] = list_property_types()[$r['property_type']] ?? $r['property_type']; $r['status'] = list_property_statuses()[$r['status']][0] ?? $r['status']; $r['listing_for'] = list_listing_for()[$r['listing_for']] ?? $r['listing_for']; }
        return ['cols' => [self::col('Ref', 'ref'), self::col('Title', 'title'), self::col('Type', 'property_type'), self::col('Area', 'area'), self::col('Listed for', 'listing_for'), self::col('Rent', 'rent_price', 'money'),
            self::col('Sale price', 'sale_price', 'money'), self::col('Status', 'status'), self::col('Owner', 'owner'), self::col('Agent', 'agent'), self::col('Added', 'added', 'date')], 'rows' => $rows, 'totals' => ['title' => count($rows) . ' properties']];
    }
    private static function propGroup(string $field, string $label, callable $fmt): array
    {
        $rows = db_all("SELECT $field grp, COUNT(*) total, SUM(status='available') available, SUM(status='reserved') reserved, SUM(status='rented') rented, SUM(status='sold') sold,
            SUM(status IN ('under_maintenance','inactive')) other FROM properties WHERE deleted_at IS NULL GROUP BY $field ORDER BY total DESC");
        foreach ($rows as &$r) $r['grp'] = $fmt($r['grp']);
        $t = ['grp' => 'Total']; foreach (['total', 'available', 'reserved', 'rented', 'sold', 'other'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col($label, 'grp'), self::col('Total', 'total', 'int'), self::col('Available', 'available', 'int'), self::col('Reserved', 'reserved', 'int'), self::col('Rented', 'rented', 'int'), self::col('Sold', 'sold', 'int'), self::col('Other', 'other', 'int')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpByArea(array $f): array { return self::propGroup('area', 'Area', fn($v) => (string)$v); }
    public static function rpByType(array $f): array { return self::propGroup('property_type', 'Type', fn($v) => list_property_types()[$v] ?? ucfirst((string)$v)); }

    /* ---------------- tenants ---------------- */
    public static function rpTenancies(array $f): array
    {
        $p = []; $eff = Tenancy::effectiveSql('t');
        $w = 't.deleted_at IS NULL' . self::dateRange('t.end_date', $f, $p);
        if (isset(Tenancy::statuses()[$f['tstatus']])) { $w .= " AND ($eff) = :ts"; $p['ts'] = $f['tstatus']; }
        $w .= self::like($f['property'], ['p.ref', 'p.title', 'p.address'], $p, 'qp') . self::like($f['customer'], ['tc.full_name', 'tc.phone'], $p, 'qc');
        if ($f['agent']) { $w .= ' AND p.agent_id = :ag'; $p['ag'] = $f['agent']; }
        $rows = db_all("SELECT t.ref, tc.full_name tenant, tc.phone, p.title property, t.start_date, t.end_date, DATEDIFF(t.end_date, CURDATE()) days_left, t.rent_amount, t.rent_frequency,
                t.total_payable, t.amount_paid, (t.total_payable - t.amount_paid) outstanding, ($eff) eff
            FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id WHERE $w ORDER BY t.end_date LIMIT 50000", $p);
        foreach ($rows as &$r) { $r['eff'] = Tenancy::statuses()[$r['eff']] ?? $r['eff']; $r['rent_frequency'] = Tenancy::FREQ_LABEL[$r['rent_frequency']] ?? $r['rent_frequency']; }
        $t = ['ref' => count($rows) . ' tenancies', 'total_payable' => array_sum(array_column($rows, 'total_payable')), 'amount_paid' => array_sum(array_column($rows, 'amount_paid')), 'outstanding' => array_sum(array_column($rows, 'outstanding'))];
        return ['cols' => [self::col('Tenancy', 'ref'), self::col('Tenant', 'tenant'), self::col('Phone', 'phone'), self::col('Property', 'property'), self::col('Start', 'start_date', 'date'), self::col('End', 'end_date', 'date'),
            self::col('Days left', 'days_left', 'int'), self::col('Rent', 'rent_amount', 'money'), self::col('Rent paid', 'rent_frequency'), self::col('Total payable', 'total_payable', 'money'), self::col('Amount paid', 'amount_paid', 'money'),
            self::col('Outstanding', 'outstanding', 'money'), self::col('Status', 'eff')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpTenantHistory(array $f): array
    {
        $p = []; $w = 't.deleted_at IS NULL' . self::dateRange('t.start_date', $f, $p) . self::like($f['customer'], ['tc.full_name', 'tc.phone'], $p, 'qc');
        $rows = db_all("SELECT tc.full_name tenant, tc.phone, COUNT(*) n, MIN(t.start_date) first_start, MAX(t.end_date) last_end, SUM(t.total_payable) total_payable, SUM(t.amount_paid) amount_paid,
                SUM(CASE WHEN t.status IN ('draft','active') THEN t.total_payable - t.amount_paid ELSE 0 END) outstanding
            FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id WHERE $w GROUP BY tc.id ORDER BY tc.full_name LIMIT 50000", $p);
        $t = ['tenant' => count($rows) . ' tenants']; foreach (['n', 'total_payable', 'amount_paid', 'outstanding'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Tenant', 'tenant'), self::col('Phone', 'phone'), self::col('Tenancies', 'n', 'int'), self::col('First start', 'first_start', 'date'), self::col('Latest end', 'last_end', 'date'),
            self::col('Total charged', 'total_payable', 'money'), self::col('Total paid', 'amount_paid', 'money'), self::col('Outstanding (open)', 'outstanding', 'money')], 'rows' => $rows, 'totals' => $t];
    }

    /* ---------------- financial ---------------- */
    private static function payFilters(array $f, array &$p, bool $validOnly): string
    {
        $w = ($validOnly ? "p.status = 'valid'" : '1=1') . self::dateRange('p.payment_date', $f, $p);
        if (isset(PaymentsController::types()[$f['ptype']])) { $w .= ' AND p.payment_type = :pt'; $p['pt'] = $f['ptype']; }
        if (isset(PaymentsController::methods()[$f['method']])) { $w .= ' AND p.method = :pm'; $p['pm'] = $f['method']; }
        if ($f['agent']) { $w .= ' AND p.recorded_by = :ag'; $p['ag'] = $f['agent']; }
        if (!$validOnly && in_array($f['pstatus'], ['valid', 'void'], true)) { $w .= ' AND p.status = :ps'; $p['ps'] = $f['pstatus']; }
        return $w;
    }
    public static function rpCollectionsDaily(array $f): array
    {
        $p = []; $w = self::payFilters($f, $p, true);
        $rows = db_all("SELECT p.payment_date d, COUNT(*) n, SUM(p.amount) total FROM payments p WHERE $w GROUP BY p.payment_date ORDER BY p.payment_date DESC LIMIT 5000", $p);
        return ['cols' => [self::col('Date', 'd', 'date'), self::col('Payments', 'n', 'int'), self::col('Total collected', 'total', 'money')], 'rows' => $rows, 'totals' => ['d' => 'Total', 'n' => array_sum(array_column($rows, 'n')), 'total' => array_sum(array_column($rows, 'total'))]];
    }
    public static function rpCollectionsMonthly(array $f): array
    {
        $p = []; $w = self::payFilters($f, $p, false);
        $rows = db_all("SELECT DATE_FORMAT(p.payment_date, '%Y-%m') m, SUM(p.status = 'valid') n, COALESCE(SUM(CASE WHEN p.status = 'valid' THEN p.amount END), 0) total,
                SUM(p.status = 'void') voids, COALESCE(SUM(CASE WHEN p.status = 'void' THEN p.amount END), 0) void_total FROM payments p WHERE $w GROUP BY m ORDER BY m DESC LIMIT 600", $p);
        foreach ($rows as &$r) $r['m'] = date('F Y', strtotime($r['m'] . '-01'));
        $t = ['m' => 'Total']; foreach (['n', 'total', 'voids', 'void_total'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Month', 'm'), self::col('Payments', 'n', 'int'), self::col('Collected', 'total', 'money'), self::col('Voided', 'voids', 'int'), self::col('Voided amount', 'void_total', 'money')], 'rows' => $rows, 'totals' => $t];
    }
    private static function payList(array $f, string $extraWhere, array $cols): array
    {
        $p = []; $w = self::payFilters($f, $p, false) . $extraWhere;
        if (in_array($f['pstatus'], ['valid', 'void'], true) === false && $f['pstatus'] !== '') $w .= '';
        $w .= self::like($f['customer'], ['c.full_name', 'c.phone'], $p, 'qc') . self::like($f['property'], ['pr.title', 'pr.ref', 'lp.plot_number', 'sl.ref', 't.ref'], $p, 'qp');
        $rows = db_all("SELECT p.payment_date, r.receipt_no, p.txn_id, c.full_name payer, COALESCE(t.ref, sl.ref, '') linked, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number), '') asset, p.payment_type, p.method, p.reference, p.amount, p.status, u.full_name recorder
            FROM payments p JOIN customers c ON c.id = p.customer_id LEFT JOIN receipts r ON r.payment_id = p.id LEFT JOIN tenancies t ON t.id = p.tenancy_id LEFT JOIN sales sl ON sl.id = p.sale_id
            LEFT JOIN properties pr ON pr.id = p.property_id LEFT JOIN land_plots lp ON lp.id = sl.plot_id LEFT JOIN users u ON u.id = p.recorded_by WHERE $w ORDER BY p.payment_date DESC, p.id DESC LIMIT 50000", $p);
        $valid = 0;
        foreach ($rows as &$r) { if ($r['status'] === 'valid') $valid += Money::kobo($r['amount']); $r['payment_type'] = PaymentsController::types()[$r['payment_type']] ?? $r['payment_type']; $r['method'] = PaymentsController::methods()[$r['method']] ?? $r['method']; $r['status'] = ucfirst($r['status']); }
        return ['cols' => $cols, 'rows' => $rows, 'totals' => ['payer' => count($rows) . ' payments', 'amount' => Money::fromKobo($valid)], 'note' => 'The amount total counts valid payments only.'];
    }
    public static function rpRent(array $f): array
    {
        return self::payList($f, " AND p.payment_type = 'rent'", [self::col('Date', 'payment_date', 'date'), self::col('Receipt', 'receipt_no'), self::col('Tenant', 'payer'), self::col('Tenancy', 'linked'), self::col('Property', 'asset'), self::col('Method', 'method'), self::col('Reference', 'reference'), self::col('Amount', 'amount', 'money'), self::col('Status', 'status')]);
    }
    public static function rpSalesIncome(array $f): array
    {
        return self::payList($f, ' AND p.sale_id IS NOT NULL', [self::col('Date', 'payment_date', 'date'), self::col('Receipt', 'receipt_no'), self::col('Buyer', 'payer'), self::col('Sale', 'linked'), self::col('Property / plot', 'asset'), self::col('Method', 'method'), self::col('Reference', 'reference'), self::col('Amount', 'amount', 'money'), self::col('Status', 'status')]);
    }
    public static function rpPayments(array $f): array
    {
        return self::payList($f, '', [self::col('Date', 'payment_date', 'date'), self::col('Receipt', 'receipt_no'), self::col('Transaction', 'txn_id'), self::col('Payer', 'payer'), self::col('Linked to', 'linked'), self::col('Type', 'payment_type'), self::col('Method', 'method'),
            self::col('Reference', 'reference'), self::col('Amount', 'amount', 'money'), self::col('Status', 'status'), self::col('Recorded by', 'recorder')]);
    }
    public static function rpRevenue(array $f): array
    {
        $p = []; $w = "p.status = 'valid'" . self::dateRange('p.payment_date', $f, $p);
        $rows = db_all("SELECT DATE_FORMAT(p.payment_date, '%Y-%m') m,
                SUM(CASE WHEN p.payment_type = 'rent' THEN p.amount ELSE 0 END) rent, SUM(CASE WHEN p.payment_type = 'deposit' THEN p.amount ELSE 0 END) deposit,
                SUM(CASE WHEN p.payment_type = 'agency_fee' THEN p.amount ELSE 0 END) agency, SUM(CASE WHEN p.payment_type = 'legal_fee' THEN p.amount ELSE 0 END) legal,
                SUM(CASE WHEN p.payment_type = 'service_charge' THEN p.amount ELSE 0 END) service, SUM(CASE WHEN p.payment_type IN ('sale','land') THEN p.amount ELSE 0 END) sales,
                SUM(CASE WHEN p.payment_type = 'other' THEN p.amount ELSE 0 END) other, SUM(p.amount) total FROM payments p WHERE $w GROUP BY m ORDER BY m DESC LIMIT 600", $p);
        foreach ($rows as &$r) $r['m'] = date('F Y', strtotime($r['m'] . '-01'));
        $t = ['m' => 'Total']; foreach (['rent', 'deposit', 'agency', 'legal', 'service', 'sales', 'other', 'total'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Month', 'm'), self::col('Rent', 'rent', 'money'), self::col('Deposits', 'deposit', 'money'), self::col('Agency fees', 'agency', 'money'), self::col('Legal fees', 'legal', 'money'), self::col('Service charges', 'service', 'money'),
            self::col('House / land sales', 'sales', 'money'), self::col('Other', 'other', 'money'), self::col('Total', 'total', 'money')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpOutstanding(array $f): array
    {
        $rows = []; $p = [];
        $wt = "t.deleted_at IS NULL AND t.status IN ('draft','active') AND t.amount_paid < t.total_payable" . self::like($f['customer'], ['tc.full_name'], $p, 'qc') . self::like($f['property'], ['pr.title', 'pr.ref'], $p, 'qp');
        foreach (db_all("SELECT t.ref, tc.full_name party, pr.title asset, t.total_payable total, t.amount_paid paid FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties pr ON pr.id = t.property_id WHERE $wt ORDER BY t.id", $p) as $r) $rows[] = ['kind' => 'Tenancy'] + $r;
        $p2 = [];
        $ws = "s.status IN ('negotiation','reserved','deposit_paid','partially_paid','fully_paid') AND s.amount_paid < s.sale_price" . self::like($f['customer'], ['bc.full_name'], $p2, 'qc') . self::like($f['property'], ['pr.title', 'pr.ref', 'lp.plot_number'], $p2, 'qp');
        foreach (db_all("SELECT s.ref, bc.full_name party, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)) asset, s.sale_price total, s.amount_paid paid FROM sales s JOIN customers bc ON bc.id = s.buyer_id LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id WHERE $ws ORDER BY s.id", $p2) as $r) $rows[] = ['kind' => 'Sale'] + $r;
        foreach ($rows as &$r) $r['outstanding'] = Money::fromKobo(Money::kobo($r['total']) - Money::kobo($r['paid']));
        $t = ['kind' => count($rows) . ' items']; foreach (['total', 'paid', 'outstanding'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Type', 'kind'), self::col('Reference', 'ref'), self::col('Customer', 'party'), self::col('Property / plot', 'asset'), self::col('Total', 'total', 'money'), self::col('Paid', 'paid', 'money'), self::col('Outstanding', 'outstanding', 'money')], 'rows' => $rows, 'totals' => $t];
    }

    /* ---------------- sales ---------------- */
    private static function salesList(array $f, string $type): array
    {
        $p = []; $w = "s.sale_type = :ty" . self::dateRange('DATE(s.created_at)', $f, $p); $p['ty'] = $type;
        if (isset(Sale::STATUS[$f['sstatus']])) { $w .= ' AND s.status = :ss'; $p['ss'] = $f['sstatus']; }
        if ($f['agent']) { $w .= ' AND s.agent_id = :ag'; $p['ag'] = $f['agent']; }
        $w .= self::like($f['customer'], ['bc.full_name', 'sc.full_name'], $p, 'qc') . self::like($f['property'], ['pr.title', 'pr.ref', 'lp.plot_number', 'lp.ref', 'e.name'], $p, 'qp');
        $rows = db_all("SELECT s.ref, DATE(s.created_at) created, s.sale_date, sc.full_name seller, bc.full_name buyer, COALESCE(pr.title, CONCAT_WS(', ', NULLIF(CONCAT('Block ', NULLIF(lp.block_number, '')), 'Block '), CONCAT('Plot ', lp.plot_number), e.name)) asset,
                s.sale_price, s.amount_paid, (s.sale_price - s.amount_paid) outstanding, s.commission_amount, u.full_name agent, s.status
            FROM sales s JOIN customers bc ON bc.id = s.buyer_id JOIN customers sc ON sc.id = s.seller_id LEFT JOIN users u ON u.id = s.agent_id LEFT JOIN properties pr ON pr.id = s.property_id
            LEFT JOIN land_plots lp ON lp.id = s.plot_id LEFT JOIN land_estates e ON e.id = lp.estate_id WHERE $w ORDER BY s.id DESC LIMIT 50000", $p);
        foreach ($rows as &$r) $r['status'] = Sale::STATUS[$r['status']][0] ?? $r['status'];
        $t = ['ref' => count($rows) . ' sales']; foreach (['sale_price', 'amount_paid', 'outstanding', 'commission_amount'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Sale', 'ref'), self::col('Created', 'created', 'date'), self::col('Completed / sale date', 'sale_date', 'date'), self::col('Seller', 'seller'), self::col('Buyer', 'buyer'), self::col($type === 'plot' ? 'Plot' : 'Property', 'asset'),
            self::col('Price', 'sale_price', 'money'), self::col('Paid', 'amount_paid', 'money'), self::col('Outstanding', 'outstanding', 'money'), self::col('Commission', 'commission_amount', 'money'), self::col('Agent', 'agent'), self::col('Status', 'status')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpHouseSales(array $f): array { return self::salesList($f, 'property'); }
    public static function rpLandSales(array $f): array { return self::salesList($f, 'plot'); }
    public static function rpSalesByAgent(array $f): array
    {
        $p = []; $w = "s.status <> 'cancelled'" . self::dateRange('DATE(s.created_at)', $f, $p);
        if (isset(Sale::STATUS[$f['sstatus']])) { $w .= ' AND s.status = :ss'; $p['ss'] = $f['sstatus']; }
        $rows = db_all("SELECT COALESCE(u.full_name, 'Unassigned') agent, COUNT(*) n, SUM(s.status = 'completed') done, SUM(s.sale_price) value, SUM(s.amount_paid) paid, SUM(s.sale_price - s.amount_paid) outstanding, SUM(s.commission_amount) commission
            FROM sales s LEFT JOIN users u ON u.id = s.agent_id WHERE $w GROUP BY s.agent_id ORDER BY value DESC", $p);
        $t = ['agent' => 'Total']; foreach (['n', 'done', 'value', 'paid', 'outstanding', 'commission'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Agent', 'agent'), self::col('Sales', 'n', 'int'), self::col('Completed', 'done', 'int'), self::col('Value', 'value', 'money'), self::col('Collected', 'paid', 'money'), self::col('Outstanding', 'outstanding', 'money'), self::col('Commission', 'commission', 'money')], 'rows' => $rows, 'totals' => $t,
            'note' => 'Cancelled sales are excluded. Commission is the agreed amount, not a record of payout.'];
    }
    public static function rpSalesByDate(array $f): array
    {
        $p = []; $w = "s.status <> 'cancelled'" . self::dateRange('DATE(s.created_at)', $f, $p);
        if (isset(Sale::STATUS[$f['sstatus']])) { $w .= ' AND s.status = :ss'; $p['ss'] = $f['sstatus']; }
        $rows = db_all("SELECT DATE_FORMAT(s.created_at, '%Y-%m') m, COUNT(*) n, SUM(s.sale_type = 'property') houses, SUM(s.sale_type = 'plot') plots, SUM(s.sale_price) value, SUM(s.amount_paid) paid FROM sales s WHERE $w GROUP BY m ORDER BY m DESC LIMIT 600", $p);
        foreach ($rows as &$r) $r['m'] = date('F Y', strtotime($r['m'] . '-01'));
        $t = ['m' => 'Total']; foreach (['n', 'houses', 'plots', 'value', 'paid'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Month', 'm'), self::col('Sales', 'n', 'int'), self::col('Houses', 'houses', 'int'), self::col('Plots', 'plots', 'int'), self::col('Value', 'value', 'money'), self::col('Collected', 'paid', 'money')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpSalesOutstanding(array $f): array
    {
        $p = []; $w = "s.status IN ('negotiation','reserved','deposit_paid','partially_paid','fully_paid') AND s.amount_paid < s.sale_price"
            . self::like($f['customer'], ['bc.full_name'], $p, 'qc') . self::like($f['property'], ['pr.title', 'pr.ref', 'lp.plot_number', 'lp.ref'], $p, 'qp');
        $rows = db_all("SELECT s.id, s.ref, bc.full_name buyer, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)) asset, s.sale_price, s.amount_paid, (s.sale_price - s.amount_paid) outstanding, s.status
            FROM sales s JOIN customers bc ON bc.id = s.buyer_id LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id WHERE $w ORDER BY s.id", $p);
        foreach ($rows as &$r) {
            $sc = Sale::schedule(['id' => $r['id'], 'amount_paid' => $r['amount_paid']]);
            $r['next_due'] = $sc['next_due']; $r['overdue'] = $sc['overdue']; $r['status'] = Sale::STATUS[$r['status']][0] ?? $r['status'];
        }
        $t = ['ref' => count($rows) . ' sales']; foreach (['sale_price', 'amount_paid', 'outstanding', 'overdue'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Sale', 'ref'), self::col('Buyer', 'buyer'), self::col('Property / plot', 'asset'), self::col('Price', 'sale_price', 'money'), self::col('Paid', 'amount_paid', 'money'), self::col('Outstanding', 'outstanding', 'money'),
            self::col('Next instalment due', 'next_due', 'date'), self::col('Overdue now', 'overdue', 'money'), self::col('Status', 'status')], 'rows' => $rows, 'totals' => $t];
    }

    /* ---------------- management ---------------- */
    public static function rpAgentActivity(array $f): array
    {
        $d = function (string $col) use ($f, &$p) { return self::dateRange($col, $f, $p, 'x' . substr(md5($col), 0, 4)); };
        $p = [];
        $sub = fn(string $table, string $col, string $dateCol) => "(SELECT COUNT(*) FROM $table x WHERE x.$col = u.id" . $d("DATE(x.$dateCol)") . ')';
        $pay = "(SELECT COUNT(*) FROM payments x WHERE x.recorded_by = u.id AND x.status = 'valid'" . $d('x.payment_date') . ')';
        $paySum = "(SELECT COALESCE(SUM(x.amount), 0) FROM payments x WHERE x.recorded_by = u.id AND x.status = 'valid'" . $d('x.payment_date') . ')';
        $rows = db_all("SELECT u.full_name staff, r.name role, " . $sub('customers', 'created_by', 'created_at') . " customers, " . $sub('properties', 'created_by', 'created_at') . " properties, "
            . $sub('tenancies', 'created_by', 'created_at') . " tenancies, " . $sub('sales', 'created_by', 'created_at') . " sales, $pay payments, $paySum collected FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 ORDER BY u.full_name", $p);
        $t = ['staff' => 'Total']; foreach (['customers', 'properties', 'tenancies', 'sales', 'payments', 'collected'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Staff', 'staff'), self::col('Role', 'role'), self::col('People added', 'customers', 'int'), self::col('Properties added', 'properties', 'int'), self::col('Tenancies created', 'tenancies', 'int'), self::col('Sales created', 'sales', 'int'),
            self::col('Payments recorded', 'payments', 'int'), self::col('Amount recorded', 'collected', 'money')], 'rows' => $rows, 'totals' => $t];
    }
    public static function rpCustomerActivity(array $f): array
    {
        $p = []; $w = '1=1' . self::dateRange('DATE(c.created_at)', $f, $p);
        $rows = db_all("SELECT DATE_FORMAT(c.created_at, '%Y-%m') m, COUNT(*) n,
                (SELECT COUNT(*) FROM customer_roles r JOIN customers c2 ON c2.id = r.customer_id WHERE r.role = 'tenant' AND DATE_FORMAT(c2.created_at, '%Y-%m') = DATE_FORMAT(c.created_at, '%Y-%m')) tenants,
                (SELECT COUNT(*) FROM customer_roles r JOIN customers c2 ON c2.id = r.customer_id WHERE r.role = 'landlord' AND DATE_FORMAT(c2.created_at, '%Y-%m') = DATE_FORMAT(c.created_at, '%Y-%m')) landlords,
                (SELECT COUNT(*) FROM customer_roles r JOIN customers c2 ON c2.id = r.customer_id WHERE r.role = 'buyer' AND DATE_FORMAT(c2.created_at, '%Y-%m') = DATE_FORMAT(c.created_at, '%Y-%m')) buyers,
                (SELECT COUNT(*) FROM customer_roles r JOIN customers c2 ON c2.id = r.customer_id WHERE r.role = 'guarantor' AND DATE_FORMAT(c2.created_at, '%Y-%m') = DATE_FORMAT(c.created_at, '%Y-%m')) guarantors
            FROM customers c WHERE $w GROUP BY m ORDER BY m DESC LIMIT 600", $p);
        foreach ($rows as &$r) $r['m'] = date('F Y', strtotime($r['m'] . '-01'));
        $t = ['m' => 'Total']; foreach (['n', 'tenants', 'landlords', 'buyers', 'guarantors'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('Month', 'm'), self::col('New people', 'n', 'int'), self::col('Of whom tenants', 'tenants', 'int'), self::col('Landlords', 'landlords', 'int'), self::col('Buyers', 'buyers', 'int'), self::col('Guarantors', 'guarantors', 'int')], 'rows' => $rows, 'totals' => $t,
            'note' => 'Role columns count people who currently hold the role, grouped by when the person was first added.'];
    }
    public static function rpAuditActivity(array $f): array
    {
        $p = []; $w = '1=1' . self::dateRange('DATE(a.created_at)', $f, $p);
        $rows = db_all("SELECT a.username, COUNT(*) total, SUM(a.action = 'login') logins, SUM(a.action = 'login_failed') failed, SUM(a.action = 'create') creates, SUM(a.action = 'update') edits,
                SUM(a.module = 'payments' OR a.module = 'receipts') money, SUM(a.action IN ('void','cancel','archive','delete')) removals FROM audit_logs a WHERE $w GROUP BY a.username ORDER BY total DESC LIMIT 500", $p);
        $t = ['username' => 'Total']; foreach (['total', 'logins', 'failed', 'creates', 'edits', 'money', 'removals'] as $k) $t[$k] = array_sum(array_column($rows, $k));
        return ['cols' => [self::col('User', 'username'), self::col('All actions', 'total', 'int'), self::col('Sign-ins', 'logins', 'int'), self::col('Failed sign-ins', 'failed', 'int'), self::col('Created', 'creates', 'int'), self::col('Edited', 'edits', 'int'),
            self::col('Payment / receipt actions', 'money', 'int'), self::col('Voids, cancels, archives', 'removals', 'int')], 'rows' => $rows, 'totals' => $t];
    }

    /* ---------------- rendering helpers ---------------- */
    public static function cell(mixed $v, string $kind, bool $csv = false): string
    {
        if ($v === null || $v === '') return '';
        return match ($kind) {
            'money' => $csv ? number_format((float)$v, 2, '.', '') : money($v),
            'int'   => (string)(int)$v,
            'date'  => $csv ? (string)$v : fmt_date((string)$v),
            default => (string)$v,
        };
    }
    public static function csvSafe(string $s): string
    {
        return ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) ? "'" . $s : $s;
    }
}
