<?php
declare(strict_types=1);

final class PaymentsController extends Controller
{
    public static function types(): array
    {
        return ['rent' => 'Rent', 'deposit' => 'Security / caution deposit', 'agency_fee' => 'Agency fee', 'legal_fee' => 'Legal fee',
                'service_charge' => 'Service charge', 'other' => 'Other', 'sale' => 'House sale payment', 'land' => 'Land / plot payment'];
    }
    public static function methods(): array
    {
        return ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'pos' => 'POS', 'cheque' => 'Cheque', 'other' => 'Other'];
    }

    public function actionIndex(): void
    {
        Auth::require('payments.view');
        $f = ['q' => $this->str('q'), 'type' => $this->str('type'), 'method' => $this->str('method'), 'status' => $this->str('status'), 'from' => $this->str('from'), 'to' => $this->str('to')];
        $where = ['1=1'];
        $p = [];
        if ($f['q'] !== '') {
            $where[] = '(p.txn_id LIKE :q OR r.receipt_no LIKE :q OR c.full_name LIKE :q OR p.reference LIKE :q OR t.ref LIKE :q OR sl.ref LIKE :q OR pr.title LIKE :q OR c.phone LIKE :q)';
            $p['q'] = like_escape($f['q']);
        }
        if (isset(self::types()[$f['type']])) { $where[] = 'p.payment_type = :type'; $p['type'] = $f['type']; }
        if (isset(self::methods()[$f['method']])) { $where[] = 'p.method = :method'; $p['method'] = $f['method']; }
        if (in_array($f['status'], ['valid', 'void'], true)) { $where[] = 'p.status = :st'; $p['st'] = $f['status']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'p.payment_date >= :from'; $p['from'] = $f['from']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'p.payment_date <= :to'; $p['to'] = $f['to']; }
        $W = implode(' AND ', $where);
        $from = "FROM payments p JOIN customers c ON c.id = p.customer_id LEFT JOIN receipts r ON r.payment_id = p.id LEFT JOIN tenancies t ON t.id = p.tenancy_id LEFT JOIN sales sl ON sl.id = p.sale_id LEFT JOIN properties pr ON pr.id = p.property_id WHERE $W";
        $tot = db_row("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN p.status = 'valid' THEN p.amount END), 0) valid_sum $from", $p);
        $pg = paginate((int)$tot['n']);
        $rows = db_all("SELECT p.*, c.full_name customer_name, r.id receipt_id, r.receipt_no, t.ref tenancy_ref, sl.ref sale_ref, pr.title property_title $from ORDER BY p.payment_date DESC, p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $this->view('payments/index', compact('rows', 'pg', 'f') + ['validSum' => $tot['valid_sum'], 'pageTitle' => 'Payments', 'active' => 'payments']);
    }

    public function actionCreate(): void
    {
        Auth::require('payments.create');
        $vals = ['payment_date' => today(), 'method' => 'cash', 'payment_type' => 'rent', 'received_by' => Auth::user()['full_name']];
        $t = null;
        if ($sid = $this->int('sale')) {
            $s = $this->sale($sid);
            $vals['sale_id'] = $sid;
            $vals['payment_type'] = $s['sale_type'] === 'plot' ? 'land' : 'sale';
            $sc = Sale::schedule($s);
            $vals['amount'] = Money::kobo($sc['next_amount']) > 0 ? $sc['next_amount'] : (Money::kobo($s['amount_paid']) === 0 && Money::kobo($s['deposit']) > 0 ? $s['deposit'] : Sale::outstanding($s));
            $this->form($vals, [], null, $s);
            return;
        }
        if ($tid = $this->int('tenancy')) {
            $t = $this->tenancy($tid);
            $vals['tenancy_id'] = $tid;
            $vals['amount'] = Tenancy::schedule($t)['next_amount'];
            if (Money::kobo($vals['amount']) <= 0) $vals['amount'] = Tenancy::outstanding($t);
        }
        $this->form($vals, [], $t);
    }

    private function sale(int $id): array
    {
        $s = db_row("SELECT s.*, bc.full_name buyer_name, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)) asset_title FROM sales s JOIN customers bc ON bc.id = s.buyer_id
            LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id WHERE s.id = ?", [$id]);
        if (!$s) http_error(404, 'That sale could not be found.');
        return $s;
    }

    private function tenancy(int $id): array
    {
        $t = db_row("SELECT t.*, tc.full_name tenant_name, p.title property_title, p.ref property_ref FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id JOIN properties p ON p.id = t.property_id WHERE t.id = ? AND t.deleted_at IS NULL", [$id]);
        if (!$t) http_error(404, 'That tenancy could not be found.');
        return $t;
    }

    private function form(array $vals, array $errors, ?array $t, ?array $sale = null): void
    {
        if (!empty($vals['customer_id']) && $t === null) $vals['customer_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$vals['customer_id']]);
        if (!empty($vals['property_id']) && $t === null) {
            $pr = db_row('SELECT ref, title FROM properties WHERE id = ?', [(int)$vals['property_id']]);
            if ($pr) $vals['property_name'] = $pr['ref'] . ' · ' . $pr['title'];
        }
        form_set($vals, $errors);
        $this->view('payments/form', ['t' => $t, 'sale' => $sale, 'sched' => $t ? Tenancy::schedule($t) : ($sale ? Sale::schedule($sale) : null), 'pageTitle' => 'Record payment', 'active' => 'payments']);
    }

    public function actionSave(): void
    {
        $this->post();
        Auth::require('payments.create');
        $tid = $this->int('tenancy_id', 'POST');
        $sid = $this->int('sale_id', 'POST');
        $v = new Validator($_POST);
        $type = $v->enum('payment_type', 'Payment type', array_keys(self::types()), true);
        $amount = $v->decimal('amount', 'Amount', true);
        if ($amount !== null && (float)$amount <= 0) $v->fail('amount', 'Amount must be greater than zero.');
        $date = $v->date('payment_date', 'Payment date', true, today(), '2000-01-01');
        $method = $v->enum('method', 'Payment method', array_keys(self::methods()), true);
        $ref = $v->text('reference', 'Reference', false, 80);
        if ($method && in_array($method, ['bank_transfer', 'pos', 'cheque'], true) && $ref === null) $v->fail('reference', 'Enter the transaction, POS or cheque reference for this payment method.');
        $purpose = $v->text('purpose', 'Payment description', false, 255);
        $recv = $v->text('received_by', 'Received by', true, 120);
        $notes = $v->text('notes', 'Notes', false, 500);

        $t = null; $sale = null; $customerId = (int)($_POST['customer_id'] ?? 0); $propertyId = (int)($_POST['property_id'] ?? 0) ?: null;
        if ($sid) {
            $sale = $this->sale($sid);
            if (!in_array($sale['status'], ['negotiation', 'reserved', 'deposit_paid', 'partially_paid'], true)) $v->fail('amount', 'Payments can only be recorded against an open sale that is not yet fully paid.');
            $customerId = (int)$sale['buyer_id'];
            $propertyId = $sale['property_id'] ? (int)$sale['property_id'] : null;
            $type = $sale['sale_type'] === 'plot' ? 'land' : 'sale';
            unset($v->errors['payment_type']);
        } elseif ($tid) {
            $t = $this->tenancy($tid);
            if (!in_array($t['status'], ['draft', 'active'], true)) $v->fail('amount', 'Payments can only be recorded against draft or active tenancies.');
            $customerId = (int)$t['tenant_id'];
            $propertyId = (int)$t['property_id'];
        } else {
            if (!$customerId || !db_val('SELECT COUNT(*) FROM customers WHERE id = ? AND deleted_at IS NULL', [$customerId])) $v->fail('customer_id', 'Choose the payer from the search results.');
            if ($propertyId && !db_val('SELECT COUNT(*) FROM properties WHERE id = ? AND deleted_at IS NULL', [$propertyId])) $v->fail('property_id', 'Choose a valid property or leave it empty.');
            if (in_array($type, ['rent', 'sale', 'land'], true)) $v->fail('payment_type', 'Rent and sale payments must be recorded from the tenancy or sale page so the balance updates.');
        }
        if ($sale && $type === null) $type = $sale['sale_type'] === 'plot' ? 'land' : 'sale';
        if ($v->errors) {
            $this->form($_POST + ['customer_id' => $customerId], $v->errors, $t, $sale);
            return;
        }

        $newId = 0;
        db()->beginTransaction();
        try {
            if ($tid) {
                $locked = db_row('SELECT * FROM tenancies WHERE id = ? FOR UPDATE', [$tid]);
                $out = Money::kobo(Tenancy::outstanding($locked));
                if (Money::kobo($amount) > $out) {
                    db()->rollBack();
                    $v->fail('amount', 'This is more than the outstanding balance of ' . money(Money::fromKobo($out)) . '. Reduce the amount.');
                    $this->form($_POST, $v->errors, $t);
                    return;
                }
            }
            if ($sid) {
                $ls = db_row('SELECT * FROM sales WHERE id = ? FOR UPDATE', [$sid]);
                $out = Money::kobo(Sale::outstanding($ls));
                if (!in_array($ls['status'], ['negotiation', 'reserved', 'deposit_paid', 'partially_paid'], true) || Money::kobo($amount) > $out) {
                    db()->rollBack();
                    $v->fail('amount', $out <= 0 ? 'This sale is already fully paid.' : 'This is more than the outstanding balance of ' . money(Money::fromKobo($out)) . '. Reduce the amount.');
                    $this->form($_POST, $v->errors, null, $sale);
                    return;
                }
            }
            $year = date('Y');
            $txn = 'TXN-' . $year . '-' . str_pad((string)next_sequence('txn_' . $year), 6, '0', STR_PAD_LEFT);
            $rno = setting('receipt_prefix', 'TEA-') . $year . '-' . str_pad((string)next_sequence('receipt_' . $year), 6, '0', STR_PAD_LEFT);
            if ($purpose === null) {
                $purpose = self::types()[$type] . ($t ? ' for ' . $t['property_title'] . ' (' . $t['ref'] . ')' : ($sale ? ' for ' . $sale['asset_title'] . ' (' . $sale['ref'] . ')' : ''));
            }
            db_run('INSERT INTO payments (txn_id, customer_id, tenancy_id, sale_id, property_id, payment_type, amount, payment_date, method, reference, purpose, received_by, notes, recorded_by)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$txn, $customerId, $tid ?: null, $sid ?: null, $propertyId, $type, $amount, $date, $method, $ref, $purpose, $recv, $notes, Auth::id()]);
            $newId = db_id();
            db_exec('INSERT INTO receipts (receipt_no, payment_id, issued_by) VALUES (?,?,?)', [$rno, $newId, Auth::id()]);
            $rid = db_id();
            if ($tid) Tenancy::recalc($tid);
            if ($sid) {
                // a payment on a sale in Negotiation reserves the asset first
                if ($sale['status'] === 'negotiation') { Sale::setAsset($sale, 'reserved'); db_exec("UPDATE sales SET status = 'reserved' WHERE id = ?", [$sid]); }
                Sale::recalc($sid);
            }
            Audit::log('create', 'payments', $newId, "Recorded payment $txn of " . money($amount) . ' (' . self::types()[$type] . ', ' . self::methods()[$method] . ')' . ($t ? " for tenancy {$t['ref']}" : '') . ($sale ? " for sale {$sale['ref']}" : ''));
            Audit::log('receipt', 'receipts', $rid, "Issued receipt $rno for payment $txn");
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', "Payment recorded. Receipt $rno issued.");
        redirect('receipts/view', ['id' => $rid]);
    }

    public function actionView(): void
    {
        Auth::require('payments.view');
        $p = db_row("SELECT p.*, c.full_name customer_name, c.ref customer_ref, r.id receipt_id, r.receipt_no, r.print_count, t.ref tenancy_ref, sl.ref sale_ref, pr.title property_title, pr.ref property_ref,
                u.full_name recorder, v.full_name voider
            FROM payments p JOIN customers c ON c.id = p.customer_id LEFT JOIN receipts r ON r.payment_id = p.id LEFT JOIN tenancies t ON t.id = p.tenancy_id LEFT JOIN sales sl ON sl.id = p.sale_id
            LEFT JOIN properties pr ON pr.id = p.property_id LEFT JOIN users u ON u.id = p.recorded_by LEFT JOIN users v ON v.id = p.voided_by WHERE p.id = ?", [$this->int('id')]);
        if (!$p) http_error(404, 'That payment could not be found.');
        $activity = can('audit.view') ? db_all("SELECT * FROM audit_logs WHERE module IN ('payments','receipts') AND (record_id = ? OR description LIKE ?) ORDER BY id DESC LIMIT 10", [(string)$p['id'], '%' . $p['txn_id'] . '%']) : [];
        $this->view('payments/view', ['p' => $p, 'activity' => $activity, 'types' => self::types(), 'methods' => self::methods(), 'pageTitle' => $p['txn_id'], 'active' => 'payments']);
    }

    public function actionVoid(): void
    {
        $this->post();
        Auth::require('payments.void');
        $id = $this->int('id', 'POST');
        $reason = $this->str('reason', 'POST');
        if (mb_strlen($reason) < 5) { flash('danger', 'Give a reason for voiding (at least 5 characters).'); redirect('payments/view', ['id' => $id]); }
        db()->beginTransaction();
        try {
            $p = db_row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$id]);
            if (!$p) { db()->rollBack(); http_error(404, 'That payment could not be found.'); }
            if ($p['status'] !== 'valid') { db()->rollBack(); flash('warning', 'This payment is already void.'); redirect('payments/view', ['id' => $id]); }
            db_exec("UPDATE payments SET status = 'void', void_reason = ?, voided_at = NOW(), voided_by = ? WHERE id = ?", [mb_substr($reason, 0, 255), Auth::id(), $id]);
            if ($p['tenancy_id']) Tenancy::recalc((int)$p['tenancy_id']);
            if ($p['sale_id']) {
                $sl = db_row('SELECT status FROM sales WHERE id = ?', [(int)$p['sale_id']]);
                if ($sl && $sl['status'] === 'completed') { db()->rollBack(); flash('danger', 'This payment belongs to a completed sale and cannot be voided.'); redirect('payments/view', ['id' => $id]); }
                Sale::recalc((int)$p['sale_id']);
            }
            $rno = (string)db_val('SELECT receipt_no FROM receipts WHERE payment_id = ?', [$id]);
            Audit::log('void', 'payments', $id, "Voided payment {$p['txn_id']} (" . money($p['amount']) . ", receipt $rno): " . mb_substr($reason, 0, 200), ['amount' => $p['amount'], 'reason' => $reason]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Payment and receipt voided. The records are kept and the tenancy balance has been recalculated.');
        redirect('payments/view', ['id' => $id]);
    }
}
