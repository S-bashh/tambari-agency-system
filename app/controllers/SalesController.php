<?php
declare(strict_types=1);

final class SalesController extends Controller
{
    private const SELECT = "SELECT s.*, bc.full_name buyer_name, bc.phone buyer_phone, sc.full_name seller_name, u.full_name agent_name,
            pr.title property_title, pr.ref property_ref, pr.address property_address,
            lp.plot_number, lp.block_number, lp.area, lp.ref plot_ref, lp.title_status, lp.size_text, e.name estate_name
        FROM sales s JOIN customers bc ON bc.id = s.buyer_id JOIN customers sc ON sc.id = s.seller_id LEFT JOIN users u ON u.id = s.agent_id
        LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id LEFT JOIN land_estates e ON e.id = lp.estate_id";

    private function find(int $id, bool $lock = false): array
    {
        $s = db_row(self::SELECT . ' WHERE s.id = ?' . ($lock ? ' FOR UPDATE' : ''), [$id]);
        if (!$s) http_error(404, 'That sale could not be found.');
        $s['plot_label'] = $s['plot_id'] ? Sale::plotLabel($s + ['estate_name' => $s['estate_name']]) : null;
        $s['asset_label'] = $s['sale_type'] === 'property' ? $s['property_title'] : $s['plot_label'];
        return $s;
    }

    /* ---------- list ---------- */
    public function actionIndex(): void
    {
        Auth::require('sales.view');
        $f = ['q' => $this->str('q'), 'type' => $this->str('type'), 'status' => $this->str('status'), 'agent' => $this->int('agent'), 'from' => $this->str('from'), 'to' => $this->str('to')];
        $where = ['1=1'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(s.ref LIKE :q OR bc.full_name LIKE :q OR sc.full_name LIKE :q OR pr.title LIKE :q OR pr.ref LIKE :q OR lp.plot_number LIKE :q OR lp.ref LIKE :q OR e.name LIKE :q)'; $p['q'] = like_escape($f['q']); }
        if (in_array($f['type'], ['property', 'plot'], true)) { $where[] = 's.sale_type = :ty'; $p['ty'] = $f['type']; }
        if (isset(Sale::STATUS[$f['status']])) { $where[] = 's.status = :st'; $p['st'] = $f['status']; }
        if ($f['agent']) { $where[] = 's.agent_id = :ag'; $p['ag'] = $f['agent']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) { $where[] = 'DATE(s.created_at) >= :fr'; $p['fr'] = $f['from']; }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) { $where[] = 'DATE(s.created_at) <= :to'; $p['to'] = $f['to']; }
        $W = implode(' AND ', $where);
        $from = "FROM sales s JOIN customers bc ON bc.id = s.buyer_id JOIN customers sc ON sc.id = s.seller_id LEFT JOIN properties pr ON pr.id = s.property_id
                 LEFT JOIN land_plots lp ON lp.id = s.plot_id LEFT JOIN land_estates e ON e.id = lp.estate_id WHERE $W";
        $tot = db_row("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.sale_price END),0) value, COALESCE(SUM(CASE WHEN s.status <> 'cancelled' THEN s.amount_paid END),0) paid $from", $p);
        $pg = paginate((int)$tot['n']);
        $rows = db_all("SELECT s.*, bc.full_name buyer_name, pr.title property_title, lp.plot_number, lp.block_number, e.name estate_name, lp.area $from ORDER BY s.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $agents = db_all("SELECT DISTINCT u.id, u.full_name FROM users u JOIN sales s ON s.agent_id = u.id ORDER BY u.full_name");
        $this->view('sales/index', compact('rows', 'pg', 'f', 'tot', 'agents') + ['pageTitle' => 'Sales', 'active' => 'sales']);
    }

    /* ---------- detail ---------- */
    public function actionView(): void
    {
        Auth::require('sales.view');
        $s = $this->find($this->int('id'));
        $sched = Sale::schedule($s);
        $payments = can('payments.view') ? db_all("SELECT p.*, r.id receipt_id, r.receipt_no FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id WHERE p.sale_id = ? ORDER BY p.payment_date DESC, p.id DESC", [(int)$s['id']]) : [];
        $docs = can('documents.view') ? db_all("SELECT d.*, u.full_name uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.owner_type = 'sale' AND d.owner_id = ? AND d.archived_at IS NULL ORDER BY d.id DESC", [(int)$s['id']]) : [];
        $activity = can('audit.view') ? db_all("SELECT * FROM audit_logs WHERE module = 'sales' AND record_id = ? ORDER BY id DESC LIMIT 10", [(string)$s['id']]) : [];
        $this->view('sales/view', compact('s', 'sched', 'payments', 'docs', 'activity') + ['outstanding' => Sale::outstanding($s), 'pageTitle' => $s['ref'], 'active' => 'sales']);
    }

    /* ---------- form ---------- */
    public function actionCreate(): void
    {
        Auth::require('sales.create');
        $type = $this->str('type') === 'plot' ? 'plot' : 'property';
        $vals = ['sale_type' => $type, 'status' => 'negotiation', 'agent_id' => Auth::id(), 'deposit' => '0'];
        if ($type === 'property' && ($pid = $this->int('property'))) {
            $pr = db_row('SELECT * FROM properties WHERE id = ? AND deleted_at IS NULL', [$pid]);
            if ($pr) $vals += ['property_id' => $pid, 'sale_price' => $pr['sale_price']];
        }
        if ($type === 'plot' && ($lid = $this->int('plot'))) {
            $lp = db_row('SELECT * FROM land_plots WHERE id = ? AND deleted_at IS NULL', [$lid]);
            if ($lp) $vals += ['plot_id' => $lid, 'sale_price' => $lp['list_price']];
        }
        if ($b = $this->int('buyer')) $vals['buyer_id'] = $b;
        $this->form(null, $vals, [], []);
    }

    public function actionEdit(): void
    {
        Auth::require('sales.create');
        $s = $this->find($this->int('id'));
        if (!in_array($s['status'], Sale::OPEN, true)) http_error(403, 'Completed and cancelled sales can no longer be edited.');
        $inst = db_all('SELECT due_date, amount, label FROM sale_installments WHERE sale_id = ? ORDER BY due_date, id', [(int)$s['id']]);
        $this->form($s, $s, [], $inst);
    }

    private function form(?array $s, array $vals, array $errors, array $inst): void
    {
        if (!empty($vals['buyer_id'])) $vals['buyer_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$vals['buyer_id']]);
        if (!empty($vals['property_id'])) { $pr = db_row('SELECT ref, title FROM properties WHERE id = ?', [(int)$vals['property_id']]); if ($pr) $vals['asset_name'] = $pr['ref'] . ' · ' . $pr['title']; }
        if (!empty($vals['plot_id'])) { $lp = db_row('SELECT lp.*, e.name estate_name FROM land_plots lp LEFT JOIN land_estates e ON e.id = lp.estate_id WHERE lp.id = ?', [(int)$vals['plot_id']]); if ($lp) $vals['asset_name'] = $lp['ref'] . ' · ' . Sale::plotLabel($lp); }
        form_set($vals, $errors);
        $agents = db_all("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.slug IN ('admin','manager','agent') ORDER BY u.full_name");
        $this->view('sales/form', ['s' => $s, 'agents' => $agents, 'inst' => $inst, 'scripts' => ['js/sale-form.js'], 'pageTitle' => $s ? 'Edit sale' : 'New sale', 'active' => 'sales']);
    }

    public function actionSave(): void
    {
        $this->post();
        Auth::require('sales.create');
        $id = $this->int('id', 'POST');
        $isNew = $id === 0;
        $existing = $isNew ? null : $this->find($id);
        if ($existing && !in_array($existing['status'], Sale::OPEN, true)) http_error(403, 'Completed and cancelled sales can no longer be edited.');
        $locked = $existing && Money::kobo($existing['amount_paid']) > 0;   // parties and asset are fixed once money has been received

        $v = new Validator($_POST);
        $type = $existing['sale_type'] ?? ($this->str('sale_type', 'POST') === 'plot' ? 'plot' : 'property');
        $assetId = (int)($existing ? ($existing['property_id'] ?? $existing['plot_id']) : ($_POST['asset_id'] ?? 0));
        $buyerId = (int)($locked ? $existing['buyer_id'] : ($_POST['buyer_id'] ?? 0));
        if ($existing && !$locked && isset($_POST['asset_id'])) $assetId = (int)$_POST['asset_id'];
        $asset = null; $sellerId = 0; $listPrice = null;
        if ($type === 'property') {
            $asset = $assetId ? db_row('SELECT * FROM properties WHERE id = ? AND deleted_at IS NULL', [$assetId]) : null;
            if (!$asset) $v->fail('asset_id', 'Choose the property from the search results.');
            else {
                if (!in_array($asset['listing_for'], ['sale', 'both'], true)) $v->fail('asset_id', 'This property is listed for rent only. Change its listing type first.');
                $heldBy = $existing && (int)$existing['property_id'] === $assetId;
                if (!$heldBy && $asset['status'] !== 'available') $v->fail('asset_id', 'This property is ' . (list_property_statuses()[$asset['status']][0] ?? $asset['status']) . ' and cannot be sold right now.');
                $sellerId = (int)$asset['landlord_id']; $listPrice = $asset['sale_price'];
            }
        } else {
            $asset = $assetId ? db_row('SELECT * FROM land_plots WHERE id = ? AND deleted_at IS NULL', [$assetId]) : null;
            if (!$asset) $v->fail('asset_id', 'Choose the plot from the search results.');
            else {
                $heldBy = $existing && (int)$existing['plot_id'] === $assetId;
                if (!$heldBy && $asset['status'] !== 'available') $v->fail('asset_id', 'This plot is ' . ($asset['status']) . ' and cannot be sold right now.');
                $sellerId = (int)$asset['seller_id']; $listPrice = $asset['list_price'];
            }
        }
        if ($asset && ($o = Sale::openFor($type, $assetId, $id))) $v->fail('asset_id', "Sale {$o['ref']} is already open for this " . ($type === 'plot' ? 'plot' : 'property') . '.');
        if (!$buyerId || !db_val('SELECT COUNT(*) FROM customers WHERE id = ? AND deleted_at IS NULL', [$buyerId])) $v->fail('buyer_id', 'Choose the buyer from the search results.');
        elseif ($buyerId === $sellerId) $v->fail('buyer_id', 'The buyer cannot be the same person as the seller.');

        $price = $v->decimal('sale_price', 'Sale price', true);
        if ($price !== null && (float)$price <= 0) $v->fail('sale_price', 'Sale price must be greater than zero.');
        $deposit = $v->decimal('deposit', 'Agreed deposit') ?? '0.00';
        if ($price !== null && Money::kobo($deposit) > Money::kobo($price)) $v->fail('deposit', 'The deposit cannot be more than the sale price.');
        if ($existing && $price !== null && Money::kobo($price) < Money::kobo($existing['amount_paid'])) $v->fail('sale_price', 'The price cannot be lower than the ' . money($existing['amount_paid']) . ' already paid.');
        $saleDate = $v->date('sale_date', 'Sale date', false, null, '2000-01-01');
        $rateRaw = trim((string)($_POST['commission_rate'] ?? ''));
        $rate = null; $commission = '0.00';
        if ($rateRaw !== '') {
            if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $rateRaw) || (float)$rateRaw > 100) $v->fail('commission_rate', 'Commission rate must be between 0 and 100 percent.');
            else { $rate = number_format((float)$rateRaw, 2, '.', ''); if ($price !== null) $commission = Money::fromKobo((int)round(Money::kobo($price) * (float)$rate / 100)); }
        } else {
            $commission = $v->decimal('commission_amount', 'Commission amount') ?? '0.00';
            if ($price !== null && Money::kobo($commission) > Money::kobo($price)) $v->fail('commission_amount', 'Commission cannot be more than the sale price.');
        }
        $agentId = (int)($_POST['agent_id'] ?? 0) ?: null;
        if ($agentId && !db_val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1 AND r.slug IN ('admin','manager','agent')", [$agentId])) $v->fail('agent_id', 'Choose a valid agent.');
        $status = $existing['status'] ?? ($this->str('status', 'POST') === 'reserved' ? 'reserved' : 'negotiation');
        $notes = $v->area('notes', 'Notes', false, 3000);

        // instalments: all-or-nothing, and they must add up to the price
        $insts = []; $sum = 0;
        $dates = (array)($_POST['inst_date'] ?? []); $amts = (array)($_POST['inst_amount'] ?? []); $labels = (array)($_POST['inst_label'] ?? []);
        foreach ($dates as $i => $dt) {
            $dt = trim((string)$dt); $am = trim(str_replace(',', '', (string)($amts[$i] ?? ''))); $lb = mb_substr(trim((string)($labels[$i] ?? '')), 0, 80);
            if ($dt === '' && $am === '') continue;
            $d = DateTime::createFromFormat('!Y-m-d', $dt);
            if (!$d || $d->format('Y-m-d') !== $dt || !preg_match('/^\d+(\.\d{1,2})?$/', $am) || (float)$am <= 0) { $v->fail('installments', 'Each instalment needs a valid date and an amount greater than zero.'); break; }
            $insts[] = [$dt, number_format((float)$am, 2, '.', ''), $lb ?: null]; $sum += Money::kobo($am);
        }
        if ($insts && $price !== null && $sum !== Money::kobo($price)) $v->fail('installments', 'The instalments add up to ' . money(Money::fromKobo($sum)) . ' but the sale price is ' . money($price) . '. They must match exactly, or remove the schedule.');

        if ($v->errors) {
            $vals = $_POST + ['sale_type' => $type, 'property_id' => $type === 'property' ? $assetId : null, 'plot_id' => $type === 'plot' ? $assetId : null, 'buyer_id' => $buyerId];
            $keep = []; foreach ($dates as $i => $dt) $keep[] = ['due_date' => $dt, 'amount' => $amts[$i] ?? '', 'label' => $labels[$i] ?? ''];
            $this->form($existing, $vals, $v->errors, $keep);
            return;
        }

        db()->beginTransaction();
        try {
            $d = ['seller_id' => $sellerId, 'buyer_id' => $buyerId, 'agent_id' => $agentId, 'sale_price' => $price, 'deposit' => $deposit, 'sale_date' => $saleDate,
                  'commission_rate' => $rate, 'commission_amount' => $commission, 'notes' => $notes];
            if ($isNew) {
                $d += ['ref' => format_ref(setting('prefix_sale', 'TEA-S-'), next_sequence('sale')), 'sale_type' => $type, 'property_id' => $type === 'property' ? $assetId : null,
                       'plot_id' => $type === 'plot' ? $assetId : null, 'status' => $status, 'created_by' => Auth::id()];
                $cols = array_keys($d);
                db_run('INSERT INTO sales (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $d);
                $id = db_id();
                $row = db_row('SELECT * FROM sales WHERE id = ?', [$id]);
                if ($status === 'reserved') Sale::setAsset($row, 'reserved');
                Audit::log('create', 'sales', $id, "Created sale {$d['ref']} for " . money($price) . " ($status)");
            } else {
                if (!$locked) { $d += ['property_id' => $type === 'property' ? $assetId : null, 'plot_id' => $type === 'plot' ? $assetId : null]; }
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($d)));
                db_run("UPDATE sales SET $set WHERE id = :id", $d + ['id' => $id]);
                // moving an unpaid sale to a different asset: release the old one, hold the new one
                if (!$locked && (int)($existing['property_id'] ?? $existing['plot_id']) !== $assetId && $existing['status'] !== 'negotiation') {
                    Sale::setAsset($existing, 'available');
                    Sale::setAsset(db_row('SELECT * FROM sales WHERE id = ?', [$id]), 'reserved');
                }
                Sale::recalc($id);   // price/deposit may have changed which status the payments imply
                Audit::log('update', 'sales', $id, "Edited sale {$existing['ref']}", Audit::diff($existing, $d, array_keys($d)) ?: null);
            }
            db_exec('DELETE FROM sale_installments WHERE sale_id = ?', [$id]);
            foreach ($insts as [$dt, $am, $lb]) db_exec('INSERT INTO sale_installments (sale_id, due_date, amount, label) VALUES (?,?,?,?)', [$id, $dt, $am, $lb]);
            db_exec("INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, 'buyer')", [$buyerId]);
            db_exec("INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, 'seller')", [$sellerId]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', $isNew ? 'Sale created. Record the deposit from the sale page when it is paid.' : 'Sale updated.');
        redirect('sales/view', ['id' => $id]);
    }

    /* ---------- workflow ---------- */
    public function actionReserve(): void
    {
        $this->post();
        Auth::require('sales.create');
        db()->beginTransaction();
        try {
            $s = $this->find($this->int('id', 'POST'), true);
            if ($s['status'] !== 'negotiation') { db()->rollBack(); flash('warning', 'Only a sale in Negotiation can be reserved.'); redirect('sales/view', ['id' => $s['id']]); }
            db_exec("UPDATE sales SET status = 'reserved' WHERE id = ?", [(int)$s['id']]);
            Sale::setAsset($s, 'reserved');
            Audit::log('status', 'sales', $s['id'], "Sale {$s['ref']} reserved");
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Reserved. The ' . ($s['sale_type'] === 'plot' ? 'plot' : 'property') . ' is now held for this buyer.');
        redirect('sales/view', ['id' => $s['id']]);
    }

    public function actionComplete(): void
    {
        $this->post();
        Auth::require('sales.complete');
        $date = $this->str('sale_date', 'POST');
        db()->beginTransaction();
        try {
            $s = $this->find($this->int('id', 'POST'), true);
            $bad = null;
            $dt = DateTime::createFromFormat('!Y-m-d', $date);
            if ($s['status'] !== 'fully_paid') $bad = 'A sale can only be completed when it is fully paid (balance ' . money(Sale::outstanding($s)) . ').';
            elseif (Money::kobo($s['amount_paid']) !== Money::kobo($s['sale_price'])) $bad = 'The amount paid does not equal the sale price. Check the payments first.';
            elseif (!$dt || $dt->format('Y-m-d') !== $date || $date > today()) $bad = 'Enter a valid completion date that is not in the future.';
            elseif (Money::kobo($s['commission_amount']) > Money::kobo($s['sale_price'])) $bad = 'Commission is larger than the sale price. Edit the sale first.';
            if ($bad) { db()->rollBack(); flash('danger', $bad); redirect('sales/view', ['id' => $s['id']]); }
            db_exec("UPDATE sales SET status = 'completed', completed_at = NOW(), sale_date = ? WHERE id = ?", [$date, (int)$s['id']]);
            Sale::setAsset($s, 'sold');
            Audit::log('complete', 'sales', $s['id'], "Completed sale {$s['ref']} (" . money($s['sale_price']) . ") on " . fmt_date($date));
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Sale completed and the ' . ($s['sale_type'] === 'plot' ? 'plot' : 'property') . ' is marked Sold.');
        redirect('sales/view', ['id' => $s['id']]);
    }

    public function actionCancel(): void
    {
        $this->post();
        Auth::require('sales.cancel');
        $reason = $this->str('reason', 'POST');
        $id = $this->int('id', 'POST');
        if (mb_strlen($reason) < 5) { flash('danger', 'Give a reason for cancelling (at least 5 characters).'); redirect('sales/view', ['id' => $id]); }
        db()->beginTransaction();
        try {
            $s = $this->find($id, true);
            if (!in_array($s['status'], Sale::OPEN, true)) { db()->rollBack(); flash('warning', 'This sale is already closed.'); redirect('sales/view', ['id' => $id]); }
            if ((int)db_val("SELECT COUNT(*) FROM payments WHERE sale_id = ? AND status = 'valid'", [$id])) {
                db()->rollBack();
                flash('danger', 'This sale has payments. Void them first (after refunding the buyer outside the system), then cancel.');
                redirect('sales/view', ['id' => $id]);
            }
            db_exec("UPDATE sales SET status = 'cancelled', cancelled_at = NOW(), cancel_reason = ? WHERE id = ?", [mb_substr($reason, 0, 255), $id]);
            Sale::setAsset($s, 'available');
            Audit::log('cancel', 'sales', $id, "Cancelled sale {$s['ref']}: " . mb_substr($reason, 0, 200));
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Sale cancelled and the asset is available again.');
        redirect('sales/view', ['id' => $id]);
    }
}
