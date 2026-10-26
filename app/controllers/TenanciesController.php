<?php
declare(strict_types=1);

final class TenanciesController extends Controller
{
    private const SELECT = "SELECT t.*, tc.full_name tenant_name, tc.ref tenant_ref, tc.phone tenant_phone,
            p.ref property_ref, p.title property_title, p.area property_area, p.status property_status,
            lc.full_name landlord_name, gc.full_name guarantor_name
        FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id
        JOIN customers lc ON lc.id = t.landlord_id LEFT JOIN customers gc ON gc.id = t.guarantor_id";

    private function find(int $id, bool $lock = false): array
    {
        $t = db_row(self::SELECT . ' WHERE t.id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$t || $t['deleted_at']) http_error(404, 'That tenancy could not be found.');
        return $t;
    }

    /* ---------- lists ---------- */
    public function actionIndex(): void
    {
        Auth::require('tenancies.view');
        $q = $this->str('q');
        $st = $this->str('status');
        $where = ['t.deleted_at IS NULL'];
        $p = [];
        $eff = Tenancy::effectiveSql('t');
        if ($q !== '') {
            $where[] = '(t.ref LIKE :q OR tc.full_name LIKE :q OR tc.phone LIKE :q OR p.ref LIKE :q OR p.title LIKE :q OR p.address LIKE :q)';
            $p['q'] = like_escape($q);
        }
        if (isset(Tenancy::statuses()[$st])) { $where[] = "($eff) = :st"; $p['st'] = $st; }
        $W = implode(' AND ', $where);
        $from = "FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id WHERE $W";
        $pg = paginate((int)db_val("SELECT COUNT(*) $from", $p));
        $rows = db_all("SELECT t.*, tc.full_name tenant_name, p.ref property_ref, p.title property_title, ($eff) eff,
                DATEDIFF(t.end_date, CURDATE()) days_left $from ORDER BY t.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $counts = array_column(db_all("SELECT ($eff) s, COUNT(*) n FROM tenancies t WHERE t.deleted_at IS NULL GROUP BY s"), 'n', 's');
        $this->view('tenancies/index', compact('rows', 'pg', 'q', 'st', 'counts') + ['pageTitle' => 'Tenancies', 'active' => 'tenancies']);
    }

    public function actionExpiry(): void
    {
        Auth::require('tenancies.view');
        $base = "SELECT t.id, t.ref, t.end_date, t.total_payable, t.amount_paid, tc.full_name tenant_name, tc.phone tenant_phone, p.title property_title, p.ref property_ref,
                    DATEDIFF(t.end_date, CURDATE()) days_left,
                    (SELECT COUNT(*) FROM tenancies r WHERE r.renewed_from_id = t.id AND r.deleted_at IS NULL) has_renewal
                 FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id
                 WHERE t.status = 'active' AND t.deleted_at IS NULL";
        $buckets = [
            'expired' => ['Already expired', 'danger', "$base AND t.end_date < CURDATE() ORDER BY t.end_date DESC"],
            'd7'      => ['Expiring within 7 days', 'danger', "$base AND DATEDIFF(t.end_date, CURDATE()) BETWEEN 0 AND 7 ORDER BY t.end_date"],
            'd30'     => ['Expiring in 8 to 30 days', 'warning', "$base AND DATEDIFF(t.end_date, CURDATE()) BETWEEN 8 AND 30 ORDER BY t.end_date"],
            'd60'     => ['Expiring in 31 to 60 days', 'warning', "$base AND DATEDIFF(t.end_date, CURDATE()) BETWEEN 31 AND 60 ORDER BY t.end_date"],
            'd90'     => ['Expiring in 61 to 90 days', 'primary', "$base AND DATEDIFF(t.end_date, CURDATE()) BETWEEN 61 AND 90 ORDER BY t.end_date"],
        ];
        $data = [];
        foreach ($buckets as $k => [$label, $color, $sql]) $data[$k] = ['label' => $label, 'color' => $color, 'rows' => db_all($sql . ' LIMIT 100')];
        $this->view('tenancies/expiry', ['data' => $data, 'overdue' => Tenancy::overdueList(), 'pageTitle' => 'Expiry & overdue tracker', 'active' => 'tenancies']);
    }

    /* ---------- detail ---------- */
    public function actionView(): void
    {
        Auth::require('tenancies.view');
        $t = $this->find($this->int('id'));
        $id = (int)$t['id'];
        $sched = Tenancy::schedule($t);
        $payments = can('payments.view') ? db_all("SELECT p.*, r.id receipt_id, r.receipt_no FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id WHERE p.tenancy_id = ? ORDER BY p.payment_date DESC, p.id DESC", [$id]) : [];
        $agreements = can('agreements.view') ? db_all('SELECT a.*, u.full_name gen_by FROM agreements a LEFT JOIN users u ON u.id = a.generated_by WHERE a.tenancy_id = ? ORDER BY a.id DESC', [$id]) : [];
        $docs = can('documents.view') ? db_all("SELECT d.*, u.full_name uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.owner_type = 'tenancy' AND d.owner_id = ? AND d.archived_at IS NULL ORDER BY d.id DESC", [$id]) : [];
        $from = $t['renewed_from_id'] ? db_row('SELECT id, ref FROM tenancies WHERE id = ?', [(int)$t['renewed_from_id']]) : null;
        $to = db_row('SELECT id, ref, status FROM tenancies WHERE renewed_from_id = ? AND deleted_at IS NULL ORDER BY id DESC LIMIT 1', [$id]);
        $activity = can('audit.view') ? db_all("SELECT * FROM audit_logs WHERE module = 'tenancies' AND record_id = ? ORDER BY id DESC LIMIT 10", [(string)$id]) : [];
        $this->view('tenancies/view', compact('t', 'sched', 'payments', 'agreements', 'docs', 'from', 'to', 'activity') + [
            'eff' => Tenancy::effective($t), 'days' => Tenancy::daysRemaining($t), 'outstanding' => Tenancy::outstanding($t),
            'pageTitle' => $t['ref'], 'active' => 'tenancies']);
    }

    /* ---------- forms ---------- */
    public function actionCreate(): void
    {
        Auth::require('tenancies.create');
        $vals = ['start_date' => today(), 'duration_months' => 12, 'rent_frequency' => 'annual'];
        if ($tid = $this->int('tenant')) $vals['tenant_id'] = $tid;
        if ($pid = $this->int('property')) {
            $pr = db_row('SELECT * FROM properties WHERE id = ? AND deleted_at IS NULL', [$pid]);
            if ($pr) $vals = $this->defaultsFromProperty($pr) + $vals;
        }
        $this->form(null, $vals, [], null);
    }

    public function actionRenew(): void
    {
        Auth::require('tenancies.create');
        $old = $this->find($this->int('id'));
        if ($old['status'] !== 'active') http_error(403, 'Only an active tenancy can be renewed.');
        if (db_val("SELECT COUNT(*) FROM tenancies WHERE renewed_from_id = ? AND deleted_at IS NULL AND status IN ('draft','active')", [(int)$old['id']])) {
            flash('warning', 'A renewal for this tenancy already exists.');
            redirect('tenancies/view', ['id' => $old['id']]);
        }
        $vals = ['tenant_id' => $old['tenant_id'], 'property_id' => $old['property_id'], 'guarantor_id' => $old['guarantor_id'],
                 'start_date' => (new DateTimeImmutable($old['end_date']))->modify('+1 day')->format('Y-m-d'), 'duration_months' => $old['duration_months'],
                 'rent_frequency' => $old['rent_frequency'], 'rent_amount' => $old['rent_amount'], 'renewed_from_id' => $old['id'],
                 'service_charge' => $old['service_charge'], 'agency_fee' => 0, 'legal_fee' => 0, 'security_deposit' => 0, 'other_fees' => 0];
        $this->form(null, $vals, [], $old);
    }

    public function actionEdit(): void
    {
        Auth::require('tenancies.edit');
        $t = $this->find($this->int('id'));
        if ($t['status'] !== 'draft') http_error(403, 'Only draft tenancies can be edited. Terms of an active tenancy are fixed; use Renew for a new term.');
        $this->form($t, $t, [], null);
    }

    private function defaultsFromProperty(array $pr): array
    {
        $freq = $pr['rent_period'] === 'monthly' ? 'monthly' : 'annual';
        return ['property_id' => $pr['id'], 'rent_amount' => $pr['rent_price'], 'rent_frequency' => $freq, 'duration_months' => 12, 'security_deposit' => $pr['deposit'],
                'agency_fee' => $pr['agency_fee'], 'legal_fee' => $pr['legal_fee'], 'service_charge' => $pr['service_charge'], 'other_fees' => $pr['other_fees'], 'other_fees_note' => $pr['other_fees_note']];
    }

    private function form(?array $t, array $vals, array $errors, ?array $renewOf): void
    {
        if (!empty($vals['tenant_id'])) $vals['tenant_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$vals['tenant_id']]);
        if (!empty($vals['property_id'])) {
            $pr = db_row('SELECT ref, title FROM properties WHERE id = ?', [(int)$vals['property_id']]);
            if ($pr) $vals['property_name'] = $pr['ref'] . ' · ' . $pr['title'];
        }
        form_set($vals, $errors);
        $guarantors = !empty($vals['tenant_id']) ? $this->guarantorsOf((int)$vals['tenant_id']) : [];
        $this->view('tenancies/form', ['t' => $t, 'renewOf' => $renewOf, 'guarantors' => $guarantors, 'scripts' => ['js/tenancy-form.js'],
            'pageTitle' => $renewOf ? 'Renew tenancy' : ($t ? 'Edit tenancy' : 'New tenancy'), 'active' => 'tenancies']);
    }

    private function guarantorsOf(int $tenantCustomerId): array
    {
        return db_all('SELECT c.id, c.full_name FROM tenant_guarantors tg JOIN tenants tn ON tn.id = tg.tenant_id JOIN guarantors g ON g.id = tg.guarantor_id
            JOIN customers c ON c.id = g.customer_id WHERE tn.customer_id = ? AND c.deleted_at IS NULL ORDER BY c.full_name', [$tenantCustomerId]);
    }

    public function actionGuarantors(): void
    {
        Auth::require('tenancies.view');
        $this->json(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['full_name']], $this->guarantorsOf($this->int('tenant'))));
    }

    /* ---------- save ---------- */
    public function actionSave(): void
    {
        $this->post();
        $id = $this->int('id', 'POST');
        $isNew = $id === 0;
        Auth::require($isNew ? 'tenancies.create' : 'tenancies.edit');
        $existing = null;
        if (!$isNew) {
            $existing = $this->find($id);
            if ($existing['status'] !== 'draft') http_error(403, 'Only draft tenancies can be edited.');
        }
        $renewId = $isNew ? $this->int('renewed_from_id', 'POST') : (int)($existing['renewed_from_id'] ?? 0);
        $old = null;
        if ($renewId) {
            $old = db_row("SELECT * FROM tenancies WHERE id = ? AND status = 'active' AND deleted_at IS NULL", [$renewId]);
            if (!$old) http_error(404, 'The tenancy being renewed could not be found or is no longer active.');
        }

        $v = new Validator($_POST);
        $tenantId = (int)($_POST['tenant_id'] ?? 0);
        $propertyId = (int)($_POST['property_id'] ?? 0);
        if ($old) { $tenantId = (int)$old['tenant_id']; $propertyId = (int)$old['property_id']; }

        if (!$tenantId || !db_val("SELECT COUNT(*) FROM customers c JOIN customer_roles r ON r.customer_id = c.id AND r.role = 'tenant' WHERE c.id = ? AND c.deleted_at IS NULL", [$tenantId])) {
            $v->fail('tenant_id', 'Choose the tenant from the search results. They must have the tenant role.');
        }
        $prop = $propertyId ? db_row('SELECT * FROM properties WHERE id = ? AND deleted_at IS NULL', [$propertyId]) : null;
        if (!$prop) {
            $v->fail('property_id', 'Choose the property from the search results.');
        } else {
            if ($prop['listing_for'] === 'sale') $v->fail('property_id', 'This property is listed for sale only, not for rent.');
            if (!$old && !in_array($prop['status'], ['available', 'reserved'], true)) {
                $v->fail('property_id', 'This property is ' . (list_property_statuses()[$prop['status']][0] ?? $prop['status']) . ' and cannot be let right now.');
            }
            if (!$old && ($sale = Sale::openFor('property', $propertyId))) $v->fail('property_id', "This property is held by sale {$sale['ref']} and cannot be let.");
            $clash = db_val("SELECT ref FROM tenancies WHERE property_id = ? AND deleted_at IS NULL AND id <> ? AND (status = 'draft' OR (status = 'active' AND id <> ?)) LIMIT 1", [$propertyId, $id, $renewId]);
            if ($clash) $v->fail('property_id', "This property already has tenancy $clash in progress. Finish or cancel it first.");
        }
        $gid = (int)($_POST['guarantor_id'] ?? 0) ?: null;
        if ($gid && !in_array($gid, array_map(fn($g) => (int)$g['id'], $this->guarantorsOf($tenantId)), true)) {
            $v->fail('guarantor_id', 'Pick one of this tenant\'s guarantors. Link the guarantor to the tenant first.');
        }
        $start = $v->date('start_date', 'Start date', true, null, '2000-01-01');
        $dur = $v->int('duration_months', 'Duration (months)', true, 1, 120);
        $freq = $v->enum('rent_frequency', 'Payment frequency', array_keys(Tenancy::PERIOD_MONTHS), true);
        $rent = $v->decimal('rent_amount', 'Rent per payment', true);
        if ($rent !== null && (float)$rent <= 0) $v->fail('rent_amount', 'Rent must be greater than zero.');
        foreach (['security_deposit' => 'Security deposit', 'agency_fee' => 'Agency fee', 'legal_fee' => 'Legal fee', 'service_charge' => 'Service charge', 'other_fees' => 'Other fees'] as $k => $l) $v->decimal($k, $l);
        $v->text('other_fees_note', 'Other fees description', false, 150);
        $v->area('notes', 'Notes', false, 3000);
        if ($dur && $freq && !Tenancy::periods($dur, $freq)) {
            $v->fail('duration_months', 'The duration must be a whole number of ' . Tenancy::FREQ_LABEL[$freq] . ' periods (a multiple of ' . Tenancy::PERIOD_MONTHS[$freq] . ' months).');
        }
        if ($v->errors) {
            $this->form($existing, $_POST + ['tenant_id' => $tenantId, 'property_id' => $propertyId], $v->errors, $old);
            return;
        }
        $d = $v->d;
        foreach (['security_deposit', 'agency_fee', 'legal_fee', 'service_charge', 'other_fees'] as $f) $d[$f] = $d[$f] ?? '0.00';
        $calc = Tenancy::totals($d);
        $d['end_date'] = Tenancy::endDate($d['start_date'], (int)$d['duration_months']);

        db()->beginTransaction();
        try {
            $oldPropId = $existing ? (int)$existing['property_id'] : null;
            $cols = ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'landlord_id' => (int)$prop['landlord_id'], 'guarantor_id' => $gid,
                'start_date' => $d['start_date'], 'duration_months' => (int)$d['duration_months'], 'end_date' => $d['end_date'], 'rent_amount' => $d['rent_amount'],
                'rent_frequency' => $d['rent_frequency'], 'security_deposit' => $d['security_deposit'], 'agency_fee' => $d['agency_fee'], 'legal_fee' => $d['legal_fee'],
                'service_charge' => $d['service_charge'], 'other_fees' => $d['other_fees'], 'other_fees_note' => $d['other_fees_note'], 'total_payable' => $calc['total_payable'], 'notes' => $d['notes']];
            if ($isNew) {
                $cols['ref'] = format_ref(setting('prefix_tenancy', 'TEA-T-'), next_sequence('tenancy'));
                $cols['renewed_from_id'] = $renewId ?: null;
                $cols['created_by'] = Auth::id();
                $names = array_keys($cols);
                db_run('INSERT INTO tenancies (' . implode(',', $names) . ') VALUES (:' . implode(',:', $names) . ')', $cols);
                $id = db_id();
                Audit::log('create', 'tenancies', $id, "Created tenancy {$cols['ref']} (total payable " . money($calc['total_payable']) . ')' . ($renewId ? ' as a renewal' : ''));
            } else {
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($cols)));
                db_run("UPDATE tenancies SET $set WHERE id = :id", $cols + ['id' => $id]);
                $ch = Audit::diff($existing, $cols, array_keys($cols));
                Audit::log('update', 'tenancies', $id, "Edited tenancy {$existing['ref']}" . ($ch ? ' (changed: ' . implode(', ', array_keys($ch)) . ')' : ''), $ch ?: null);
            }
            Tenancy::syncReserved($propertyId);
            if ($oldPropId && $oldPropId !== $propertyId) Tenancy::syncReserved($oldPropId);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', $isNew ? 'Tenancy saved as a draft. Record the first payment, then activate it.' : 'Draft tenancy updated.');
        redirect('tenancies/view', ['id' => $id]);
    }

    /* ---------- workflow ---------- */
    public function actionActivate(): void
    {
        $this->post();
        Auth::require('tenancies.activate');
        db()->beginTransaction();
        try {
            $t = $this->find($this->int('id', 'POST'), true);
            $fail = function (string $m) use ($t): never {
                db()->rollBack();
                flash('danger', $m);
                redirect('tenancies/view', ['id' => $t['id']]);
            };
            if ($t['status'] !== 'draft') $fail('Only a draft tenancy can be activated.');
            $prop = db_row('SELECT * FROM properties WHERE id = ? FOR UPDATE', [(int)$t['property_id']]);
            if (!$prop || $prop['deleted_at']) $fail('The property is archived.');
            $old = $t['renewed_from_id'] ? db_row("SELECT * FROM tenancies WHERE id = ? AND status = 'active'", [(int)$t['renewed_from_id']]) : null;
            if ($t['renewed_from_id'] && !$old) $fail('The tenancy being renewed is no longer active.');
            if (!$old && !in_array($prop['status'], ['available', 'reserved'], true)) $fail('The property is ' . (list_property_statuses()[$prop['status']][0] ?? $prop['status']) . ' and cannot be let.');
            $other = db_val("SELECT ref FROM tenancies WHERE property_id = ? AND status = 'active' AND deleted_at IS NULL AND id <> ? AND id <> ? LIMIT 1", [(int)$t['property_id'], (int)$t['id'], (int)($old['id'] ?? 0)]);
            if ($other) $fail("Property already has an active tenancy ($other).");
            if (!db_val("SELECT COUNT(*) FROM customer_roles WHERE customer_id = ? AND role = 'tenant'", [(int)$t['tenant_id']])) $fail('The tenant no longer has the tenant role.');
            $unpaid = Money::kobo($t['amount_paid']) <= 0;
            if ($unpaid && empty($_POST['allow_unpaid'])) $fail('No payment has been recorded yet. Record the first payment, or tick "activate without payment" if the agency agreed this.');
            db_exec("UPDATE tenancies SET status = 'active', activated_at = NOW() WHERE id = ?", [(int)$t['id']]);
            if ($prop['status'] !== 'rented') Tenancy::setPropertyStatus((int)$prop['id'], $prop['status'], 'rented', 'Tenancy ' . $t['ref'] . ' activated');
            if ($old) {
                db_exec("UPDATE tenancies SET status = 'renewed' WHERE id = ?", [(int)$old['id']]);
                Audit::log('renew', 'tenancies', $old['id'], "Tenancy {$old['ref']} renewed by {$t['ref']}");
            }
            Audit::log('activate', 'tenancies', $t['id'], "Activated tenancy {$t['ref']}" . ($unpaid ? ' without any payment recorded' : ''));
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Tenancy is now active and the property is marked Rented.');
        redirect('tenancies/view', ['id' => $t['id']]);
    }

    public function actionTerminate(): void
    {
        $this->post();
        Auth::require('tenancies.terminate');
        $reason = $this->str('reason', 'POST');
        $id = $this->int('id', 'POST');
        if (mb_strlen($reason) < 5) { flash('danger', 'Please give a reason (at least 5 characters).'); redirect('tenancies/view', ['id' => $id]); }
        db()->beginTransaction();
        try {
            $t = $this->find($id, true);
            if ($t['status'] !== 'active') { db()->rollBack(); flash('danger', 'Only an active tenancy can be terminated.'); redirect('tenancies/view', ['id' => $id]); }
            db_exec("UPDATE tenancies SET status = 'terminated', terminated_at = NOW(), termination_reason = ? WHERE id = ?", [mb_substr($reason, 0, 255), $id]);
            $prop = db_row('SELECT id, status FROM properties WHERE id = ? FOR UPDATE', [(int)$t['property_id']]);
            if ($prop && $prop['status'] === 'rented' && !db_val("SELECT COUNT(*) FROM tenancies WHERE property_id = ? AND status = 'active' AND deleted_at IS NULL AND id <> ?", [(int)$t['property_id'], $id])) {
                Tenancy::setPropertyStatus((int)$prop['id'], 'rented', 'available', 'Tenancy ' . $t['ref'] . ' ended');
            }
            Audit::log('terminate', 'tenancies', $id, "Terminated tenancy {$t['ref']}: " . mb_substr($reason, 0, 200), ['outstanding' => Tenancy::outstanding($t)]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Tenancy closed. The property is Available again.');
        redirect('tenancies/view', ['id' => $id]);
    }

    public function actionCancelDraft(): void
    {
        $this->post();
        Auth::require('tenancies.edit');
        db()->beginTransaction();
        try {
            $t = $this->find($this->int('id', 'POST'), true);
            if ($t['status'] !== 'draft') { db()->rollBack(); flash('danger', 'Only draft tenancies can be cancelled.'); redirect('tenancies/view', ['id' => $t['id']]); }
            if ((int)db_val("SELECT COUNT(*) FROM payments WHERE tenancy_id = ? AND status = 'valid'", [(int)$t['id']])) {
                db()->rollBack();
                flash('danger', 'This draft has payments recorded. Void those payments first, then cancel the draft.');
                redirect('tenancies/view', ['id' => $t['id']]);
            }
            db_exec('UPDATE tenancies SET deleted_at = NOW() WHERE id = ?', [(int)$t['id']]);
            Tenancy::syncReserved((int)$t['property_id']);
            Audit::log('archive', 'tenancies', $t['id'], "Cancelled draft tenancy {$t['ref']}");
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Draft tenancy cancelled.');
        redirect('tenancies');
    }
}
