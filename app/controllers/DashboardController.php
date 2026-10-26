<?php
declare(strict_types=1);

final class DashboardController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('dashboard.view');

        $byStatus = array_column(db_all('SELECT status, COUNT(*) n FROM properties WHERE deleted_at IS NULL GROUP BY status'), 'n', 'status');
        $total = array_sum($byStatus);
        $roleCount = array_column(db_all(
            'SELECT cr.role, COUNT(*) n FROM customer_roles cr JOIN customers c ON c.id = cr.customer_id WHERE c.deleted_at IS NULL GROUP BY cr.role'
        ), 'n', 'role');
        $customers = (int)db_val('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
        $forSale = (int)db_val("SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL AND status = 'available' AND listing_for IN ('sale','both')");
        $forRent = (int)db_val("SELECT COUNT(*) FROM properties WHERE deleted_at IS NULL AND status = 'available' AND listing_for IN ('rent','both')");

        $types = db_all('SELECT property_type, COUNT(*) n FROM properties WHERE deleted_at IS NULL GROUP BY property_type ORDER BY n DESC');
        $typeLabels = list_property_types();

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
        }
        foreach (db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) n FROM properties WHERE deleted_at IS NULL AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym") as $r) {
            if (isset($months[$r['ym']])) $months[$r['ym']] = (int)$r['n'];
        }

        // Real, actionable alerts
        $alerts = [];
        if (can('tenants.view')) {
            $n = (int)db_val("SELECT COUNT(*) FROM customers c JOIN customer_roles cr ON cr.customer_id = c.id AND cr.role = 'tenant'
                              JOIN tenants t ON t.customer_id = c.id LEFT JOIN tenant_guarantors tg ON tg.tenant_id = t.id
                              WHERE c.deleted_at IS NULL AND tg.id IS NULL");
            if ($n) $alerts[] = ['bi-shield-exclamation', "$n tenant" . ($n > 1 ? 's have' : ' has') . ' no guarantor on file', url('tenants', ['guarantor' => 'none'])];
        }
        if (can('properties.view')) {
            $n = (int)db_val('SELECT COUNT(*) FROM properties p WHERE p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM property_images i WHERE i.property_id = p.id)');
            if ($n) $alerts[] = ['bi-image', "$n propert" . ($n > 1 ? 'ies have' : 'y has') . ' no photos', url('properties', ['media' => 'none'])];
            if (can('documents.view')) {
                $n = (int)db_val("SELECT COUNT(*) FROM properties p WHERE p.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM documents d WHERE d.owner_type = 'property' AND d.owner_id = p.id AND d.archived_at IS NULL)");
                if ($n) $alerts[] = ['bi-file-earmark-x', "$n propert" . ($n > 1 ? 'ies have' : 'y has') . ' no documents (title, deed, etc.)', url('properties', ['docs' => 'none'])];
            }
        }
        if (can('landlords.view')) {
            $n = (int)db_val("SELECT COUNT(*) FROM customers c JOIN customer_roles cr ON cr.customer_id = c.id AND cr.role = 'landlord'
                              WHERE c.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM properties p WHERE p.landlord_id = c.id AND p.deleted_at IS NULL)");
            if ($n) $alerts[] = ['bi-person-exclamation', "$n landlord" . ($n > 1 ? 's have' : ' has') . ' no properties yet', url('landlords')];
        }

        // ---- rentals & money (real figures, only for roles allowed to see them) ----
        $rental = null; $money = null; $revMonthChart = null; $revTypeChart = null;
        if (can('tenancies.view')) {
            $eff = Tenancy::effectiveSql('t');
            $tc = array_column(db_all("SELECT ($eff) s, COUNT(*) n FROM tenancies t WHERE t.deleted_at IS NULL GROUP BY s"), 'n', 's');
            $bk = db_row("SELECT COALESCE(SUM(end_date < CURDATE()),0) expired, COALESCE(SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 0 AND 7),0) d7,
                    COALESCE(SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 8 AND 30),0) d30, COALESCE(SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 31 AND 60),0) d60,
                    COALESCE(SUM(DATEDIFF(end_date, CURDATE()) BETWEEN 61 AND 90),0) d90 FROM tenancies WHERE status = 'active' AND deleted_at IS NULL");
            $od = Tenancy::overdueList();
            $odSum = 0; foreach ($od as $o) $odSum += Money::kobo($o['overdue']);
            $rental = ['counts' => $tc, 'buckets' => $bk, 'overdue_n' => count($od), 'overdue_sum' => Money::fromKobo($odSum),
                'pending' => db_val("SELECT COALESCE(SUM(total_payable - amount_paid),0) FROM tenancies WHERE status IN ('draft','active') AND deleted_at IS NULL")];
            if ($bk['expired']) $alerts[] = ['bi-calendar-x', $bk['expired'] . ' tenanc' . ($bk['expired'] > 1 ? 'ies have' : 'y has') . ' expired and still need renewal or closing', url('tenancies/expiry')];
            if ($bk['d7']) $alerts[] = ['bi-alarm', $bk['d7'] . ' tenanc' . ($bk['d7'] > 1 ? 'ies expire' : 'y expires') . ' within 7 days', url('tenancies/expiry')];
            if ($od) $alerts[] = ['bi-exclamation-octagon', count($od) . ' tenanc' . (count($od) > 1 ? 'ies are' : 'y is') . ' behind on rent (' . money(Money::fromKobo($odSum)) . ' overdue)', url('tenancies/expiry') . '#overdue'];
            if (!empty($tc['draft'])) $alerts[] = ['bi-hourglass-split', $tc['draft'] . ' draft tenanc' . ($tc['draft'] > 1 ? 'ies are' : 'y is') . ' waiting to be activated', url('tenancies', ['status' => 'draft'])];
            if (can('agreements.view')) {
                $n = (int)db_val("SELECT COUNT(*) FROM tenancies t WHERE t.status = 'active' AND t.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM agreements a WHERE a.tenancy_id = t.id AND a.status = 'current')");
                if ($n) $alerts[] = ['bi-file-earmark-x', $n . ' active tenanc' . ($n > 1 ? 'ies have' : 'y has') . ' no tenancy agreement generated', url('tenancies', ['status' => 'active'])];
            }
        }
        if (can('payments.view')) {
            $money = db_row("SELECT COALESCE(SUM(CASE WHEN payment_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') THEN amount END),0) month_all,
                    COALESCE(SUM(CASE WHEN payment_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND payment_type = 'rent' THEN amount END),0) month_rent,
                    COALESCE(SUM(CASE WHEN payment_type = 'rent' THEN amount END),0) total_rent, COALESCE(SUM(amount),0) total_all
                FROM payments WHERE status = 'valid'");
            $rm = array_fill_keys(array_keys($months), 0.0);
            foreach (db_all("SELECT DATE_FORMAT(payment_date,'%Y-%m') ym, SUM(amount) s FROM payments WHERE status = 'valid' AND payment_date >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01') GROUP BY ym") as $r) {
                if (isset($rm[$r['ym']])) $rm[$r['ym']] = (float)$r['s'];
            }
            $revMonthChart = ['type' => 'bar', 'label' => 'Collected (₦)', 'labels' => array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($rm)), 'values' => array_values($rm)];
            $byType = db_all("SELECT payment_type, SUM(amount) s FROM payments WHERE status = 'valid' AND YEAR(payment_date) = YEAR(CURDATE()) GROUP BY payment_type ORDER BY s DESC");
            $revTypeChart = ['type' => 'doughnut', 'labels' => array_map(fn($r) => PaymentsController::types()[$r['payment_type']] ?? ucfirst($r['payment_type']), $byType), 'values' => array_map(fn($r) => (float)$r['s'], $byType)];
        }

        // ---- sales & land ----
        $salesStat = null; $activityChart = null;
        if (can('sales.view')) {
            $sc = array_column(db_all('SELECT status, COUNT(*) n FROM sales GROUP BY status'), 'n', 'status');
            $sv = db_row("SELECT COALESCE(SUM(sale_price),0) value, COALESCE(SUM(amount_paid),0) paid FROM sales WHERE status <> 'cancelled'");
            $sold = array_column(db_all("SELECT sale_type, COUNT(*) n FROM sales WHERE status = 'completed' GROUP BY sale_type"), 'n', 'sale_type');
            $od = Sale::overdueList(); $odSum = 0; foreach ($od as $o) $odSum += Money::kobo($o['overdue']);
            $open = (int)db_val("SELECT COUNT(*) FROM sales WHERE status IN ('negotiation','reserved','deposit_paid','partially_paid','fully_paid')");
            $salesStat = ['counts' => $sc, 'value' => $sv['value'], 'paid' => $sv['paid'], 'open' => $open, 'houses' => (int)($sold['property'] ?? 0), 'plots' => (int)($sold['plot'] ?? 0), 'overdue_n' => count($od), 'overdue_sum' => Money::fromKobo($odSum),
                'plots_available' => can('land.view') ? (int)db_val("SELECT COUNT(*) FROM land_plots WHERE deleted_at IS NULL AND status = 'available'") : null];
            if ($od) $alerts[] = ['bi-exclamation-octagon', count($od) . ' sale' . (count($od) > 1 ? 's have' : ' has') . ' overdue instalments (' . money(Money::fromKobo($odSum)) . ')', url('reports/run', ['report' => 'sales_outstanding', 'run' => 1])];
            $tm = array_fill_keys(array_keys($months), 0); $sm = $tm;
            foreach (db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) n FROM sales WHERE status <> 'cancelled' AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01') GROUP BY ym") as $r) if (isset($sm[$r['ym']])) $sm[$r['ym']] = (int)$r['n'];
            if (can('tenancies.view')) foreach (db_all("SELECT DATE_FORMAT(created_at,'%Y-%m') ym, COUNT(*) n FROM tenancies WHERE deleted_at IS NULL AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m-01') GROUP BY ym") as $r) if (isset($tm[$r['ym']])) $tm[$r['ym']] = (int)$r['n'];
            $activityChart = ['type' => 'bar', 'label' => 'Activity', 'labels' => array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($months)), 'values' => array_map(fn($a, $b) => $a + $b, array_values($tm), array_values($sm)),
                'tenancies' => array_values($tm), 'sales' => array_values($sm)];
        }
        if (can('backups.manage')) {
            $last = db_val('SELECT MAX(created_at) FROM backups WHERE deleted_at IS NULL');
            $days = $last ? (int)floor((time() - strtotime($last)) / 86400) : null;
            if ($last === null || $days >= (int)setting('backup_reminder_days', '7')) $alerts[] = ['bi-cloud-arrow-down', $last === null ? 'No backup has been made yet. Create one now.' : "The last backup was $days days ago. Create a new one.", url('backups')];
        }

        $recentProps = can('properties.view') ? db_all('SELECT id, ref, title, area, status, listing_for FROM properties WHERE deleted_at IS NULL ORDER BY id DESC LIMIT 5') : [];
        $recentPeople = can('customers.view') ? db_all("SELECT c.id, c.ref, c.full_name, c.phone, GROUP_CONCAT(cr.role) roles FROM customers c LEFT JOIN customer_roles cr ON cr.customer_id = c.id WHERE c.deleted_at IS NULL GROUP BY c.id ORDER BY c.id DESC LIMIT 5") : [];
        $activity = can('audit.view') ? db_all("SELECT * FROM audit_logs WHERE action NOT IN ('login','logout','login_failed') ORDER BY id DESC LIMIT 8") : [];

        $this->view('dashboard/index', [
            'pageTitle' => 'Dashboard', 'active' => 'dashboard', 'charts' => true,
            'byStatus' => $byStatus, 'total' => $total, 'roleCount' => $roleCount, 'customers' => $customers,
            'forSale' => $forSale, 'forRent' => $forRent,
            'statusChart' => ['type' => 'doughnut', 'labels' => array_map(fn($k) => list_property_statuses()[$k][0] ?? $k, array_keys($byStatus)), 'values' => array_values($byStatus)],
            'typeChart' => ['type' => 'bar', 'label' => 'Properties', 'labels' => array_map(fn($r) => $typeLabels[$r['property_type']] ?? ucfirst($r['property_type']), $types), 'values' => array_map(fn($r) => (int)$r['n'], $types)],
            'monthChart' => ['type' => 'bar', 'label' => 'Listings added', 'labels' => array_map(fn($m) => date('M', strtotime($m . '-01')), array_keys($months)), 'values' => array_values($months)],
            'salesStat' => $salesStat, 'activityChart' => $activityChart, 'rental' => $rental, 'money' => $money, 'revMonthChart' => $revMonthChart, 'revTypeChart' => $revTypeChart,
            'alerts' => $alerts, 'recentProps' => $recentProps, 'recentPeople' => $recentPeople, 'activity' => $activity,
        ]);
    }
}
