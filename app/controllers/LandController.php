<?php
declare(strict_types=1);

final class LandController extends Controller
{
    /* ================= plots ================= */
    public function actionIndex(): void
    {
        Auth::require('land.view');
        $f = ['q' => $this->str('q'), 'estate' => $this->int('estate'), 'status' => $this->str('status'), 'title' => $this->str('title')];
        $archived = $this->str('archived') === '1' && can('land.delete');
        $where = [$archived ? 'lp.deleted_at IS NOT NULL' : 'lp.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') { $where[] = '(lp.ref LIKE :q OR lp.plot_number LIKE :q OR lp.block_number LIKE :q OR lp.area LIKE :q OR lp.location LIKE :q OR e.name LIKE :q OR sc.full_name LIKE :q)'; $p['q'] = like_escape($f['q']); }
        if ($f['estate']) { $where[] = 'lp.estate_id = :est'; $p['est'] = $f['estate']; }
        if (isset(list_plot_statuses()[$f['status']])) { $where[] = 'lp.status = :st'; $p['st'] = $f['status']; }
        if (isset(list_title_status()[$f['title']])) { $where[] = 'lp.title_status = :ti'; $p['ti'] = $f['title']; }
        $W = implode(' AND ', $where);
        $from = "FROM land_plots lp LEFT JOIN land_estates e ON e.id = lp.estate_id JOIN customers sc ON sc.id = lp.seller_id WHERE $W";
        $pg = paginate((int)db_val("SELECT COUNT(*) $from", $p));
        $rows = db_all("SELECT lp.*, e.name estate_name, sc.full_name seller_name $from ORDER BY lp.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $estates = db_all('SELECT id, name FROM land_estates WHERE deleted_at IS NULL ORDER BY name');
        $this->view('land/index', compact('rows', 'pg', 'f', 'estates', 'archived') + ['pageTitle' => 'Land & plots', 'active' => 'land']);
    }

    public function actionView(): void
    {
        Auth::require('land.view');
        $p = $this->plot($this->int('id'), true);
        $p['estate_name'] = $p['estate_id'] ? db_val('SELECT name FROM land_estates WHERE id = ?', [(int)$p['estate_id']]) : null;
        $seller = db_row('SELECT id, full_name, phone, ref FROM customers WHERE id = ?', [(int)$p['seller_id']]);
        $sales = can('sales.view') ? db_all('SELECT s.id, s.ref, s.status, s.sale_price, s.amount_paid, bc.full_name buyer_name FROM sales s JOIN customers bc ON bc.id = s.buyer_id WHERE s.plot_id = ? ORDER BY s.id DESC', [(int)$p['id']]) : [];
        $docs = can('documents.view') ? db_all("SELECT d.*, u.full_name uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by WHERE d.owner_type = 'plot' AND d.owner_id = ? AND d.archived_at IS NULL ORDER BY d.id DESC", [(int)$p['id']]) : [];
        $this->view('land/view', compact('p', 'seller', 'sales', 'docs') + ['pageTitle' => 'Plot ' . $p['plot_number'], 'active' => 'land']);
    }

    public function actionCreate(): void
    {
        Auth::require('land.create');
        $vals = ['state' => 'Kano', 'title_status' => 'Not stated'];
        if ($e = $this->int('estate')) {
            $es = db_row('SELECT * FROM land_estates WHERE id = ? AND deleted_at IS NULL', [$e]);
            if ($es) $vals = ['estate_id' => $es['id'], 'area' => $es['location'], 'lga' => $es['lga'], 'state' => $es['state'], 'title_status' => $es['title_status'] ?: 'Not stated', 'seller_id' => $es['seller_id']];
        }
        $this->form(null, $vals, []);
    }

    public function actionEdit(): void
    {
        Auth::require('land.create');
        $p = $this->plot($this->int('id'));
        $this->form($p, $p, []);
    }

    private function form(?array $p, array $vals, array $errors): void
    {
        if (!empty($vals['seller_id'])) $vals['seller_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$vals['seller_id']]);
        form_set($vals, $errors);
        $estates = db_all('SELECT id, name FROM land_estates WHERE deleted_at IS NULL ORDER BY name');
        $this->view('land/form', ['p' => $p, 'estates' => $estates, 'pageTitle' => $p ? 'Edit plot' : 'Add plot', 'active' => 'land']);
    }

    public function actionSave(): void
    {
        $this->post();
        $id = $this->int('id', 'POST');
        $isNew = $id === 0;
        Auth::require('land.create');
        $existing = $isNew ? null : $this->plot($id);
        $v = new Validator($_POST);
        $estateId = (int)($_POST['estate_id'] ?? 0) ?: null;
        if ($estateId && !db_val('SELECT COUNT(*) FROM land_estates WHERE id = ? AND deleted_at IS NULL', [$estateId])) $v->fail('estate_id', 'Choose a valid estate or leave it empty.');
        $plotNo = $v->text('plot_number', 'Plot number', true, 30);
        $block = (string)($v->text('block_number', 'Block number', false, 30) ?? '');
        $v->text('location', 'Location / address', false, 255);
        $v->text('area', 'Area', true, 100);
        $v->text('lga', 'Local government area', false, 80);
        $v->text('state', 'State', true, 60);
        $v->text('size_text', 'Size', false, 60);
        $v->decimal('size_sqm', 'Size in square metres', false, 0.0, 99999999.0);
        $v->enum('title_status', 'Title / document status', array_keys(list_title_status()), true);
        $v->decimal('list_price', 'List price');
        $v->area('description', 'Description', false, 4000);
        $sellerId = (int)($_POST['seller_id'] ?? 0);
        if (!$sellerId || !db_val('SELECT COUNT(*) FROM customers WHERE id = ? AND deleted_at IS NULL', [$sellerId])) $v->fail('seller_id', 'Choose the seller from the search results.');
        if ($plotNo !== null && db_val('SELECT COUNT(*) FROM land_plots WHERE estate_id <=> ? AND block_number = ? AND plot_number = ? AND id <> ?', [$estateId, $block, $plotNo, $id])) {
            $v->fail('plot_number', 'This plot number already exists in that estate and block.');
        }
        if ($v->errors) { $this->form($existing, $_POST + ['seller_id' => $sellerId], $v->errors); return; }
        $d = ['estate_id' => $estateId, 'plot_number' => $plotNo, 'block_number' => $block, 'location' => $v->d['location'], 'area' => $v->d['area'], 'lga' => $v->d['lga'], 'state' => $v->d['state'],
              'size_text' => $v->d['size_text'], 'size_sqm' => $v->d['size_sqm'], 'title_status' => $v->d['title_status'], 'list_price' => $v->d['list_price'], 'seller_id' => $sellerId, 'description' => $v->d['description']];
        db()->beginTransaction();
        try {
            if ($isNew) {
                $d['ref'] = format_ref(setting('prefix_plot', 'TEA-L-'), next_sequence('plot'));
                $d['created_by'] = Auth::id();
                $cols = array_keys($d);
                db_run('INSERT INTO land_plots (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $d);
                $id = db_id();
                Audit::log('create', 'land', $id, "Added plot {$d['ref']} (Plot $plotNo" . ($block !== '' ? ", Block $block" : '') . ')');
            } else {
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($d)));
                db_run("UPDATE land_plots SET $set WHERE id = :id", $d + ['id' => $id]);
                $ch = Audit::diff($existing, $d, array_keys($d));
                Audit::log('update', 'land', $id, "Edited plot {$existing['ref']}" . ($ch ? ' (changed: ' . implode(', ', array_keys($ch)) . ')' : ''), $ch ?: null);
            }
            db_exec("INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, 'seller')", [$sellerId]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', $isNew ? 'Plot added.' : 'Plot updated.');
        redirect('land/view', ['id' => $id]);
    }

    public function actionStatus(): void
    {
        $this->post();
        Auth::require('land.create');
        $p = $this->plot($this->int('id', 'POST'));
        $to = $this->str('status', 'POST');
        $allowed = ['available' => ['inactive'], 'inactive' => ['available']][$p['status']] ?? [];
        if (!in_array($to, $allowed, true)) {
            flash('danger', 'Reserved and sold status is set by sales. Only Available and Inactive can be changed by hand.');
            redirect('land/view', ['id' => $p['id']]);
        }
        db_exec('UPDATE land_plots SET status = ? WHERE id = ?', [$to, (int)$p['id']]);
        Audit::log('status', 'land', $p['id'], "Plot {$p['ref']} status: {$p['status']} to $to");
        flash('success', 'Plot status updated.');
        redirect('land/view', ['id' => $p['id']]);
    }

    public function actionArchive(): void
    {
        $this->post();
        Auth::require('land.delete');
        $p = $this->plot($this->int('id', 'POST'));
        if ($p['status'] === 'reserved' || $p['status'] === 'sold' || Sale::openFor('plot', (int)$p['id'])) {
            flash('danger', 'Reserved or sold plots are part of the business record and cannot be archived.');
            redirect('land/view', ['id' => $p['id']]);
        }
        db_exec('UPDATE land_plots SET deleted_at = NOW() WHERE id = ?', [(int)$p['id']]);
        Audit::log('archive', 'land', $p['id'], "Archived plot {$p['ref']}");
        flash('success', 'Plot archived.');
        redirect('land');
    }

    public function actionRestore(): void
    {
        $this->post();
        Auth::require('land.delete');
        $p = $this->plot($this->int('id', 'POST'), true);
        db_exec('UPDATE land_plots SET deleted_at = NULL WHERE id = ?', [(int)$p['id']]);
        Audit::log('restore', 'land', $p['id'], "Restored plot {$p['ref']}");
        flash('success', 'Plot restored.');
        redirect('land/view', ['id' => $p['id']]);
    }

    /** JSON for the sale form: available plots. */
    public function actionLookup(): void
    {
        Auth::require('land.view');
        $q = $this->str('q');
        if (mb_strlen($q) < 2) $this->json([]);
        $rows = db_all("SELECT lp.*, e.name estate_name, sc.full_name seller_name FROM land_plots lp LEFT JOIN land_estates e ON e.id = lp.estate_id JOIN customers sc ON sc.id = lp.seller_id
            WHERE lp.deleted_at IS NULL AND lp.status = 'available' AND (lp.ref LIKE :q OR lp.plot_number LIKE :q OR lp.area LIKE :q OR e.name LIKE :q) ORDER BY e.name, lp.block_number, lp.plot_number LIMIT 10", ['q' => like_escape($q)]);
        $this->json(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['ref'] . ' · ' . Sale::plotLabel($r), 'meta' => ($r['list_price'] !== null ? money($r['list_price']) : 'no price set') . ' · Seller: ' . $r['seller_name'],
            'data' => ['price' => $r['list_price'], 'seller' => $r['seller_name']]], $rows));
    }

    private function plot(int $id, bool $archived = false): array
    {
        $p = db_row('SELECT * FROM land_plots WHERE id = ?' . ($archived ? '' : ' AND deleted_at IS NULL'), [$id]);
        if (!$p) http_error(404, 'That plot could not be found.');
        return $p;
    }

    /* ================= estates ================= */
    public function actionEstates(): void
    {
        Auth::require('land.view');
        $rows = db_all("SELECT e.*, sc.full_name seller_name, COUNT(lp.id) plots,
                COALESCE(SUM(lp.status = 'available'), 0) available, COALESCE(SUM(lp.status = 'reserved'), 0) reserved, COALESCE(SUM(lp.status = 'sold'), 0) sold
            FROM land_estates e LEFT JOIN customers sc ON sc.id = e.seller_id LEFT JOIN land_plots lp ON lp.estate_id = e.id AND lp.deleted_at IS NULL
            WHERE e.deleted_at IS NULL GROUP BY e.id ORDER BY e.name");
        $this->view('land/estates', ['rows' => $rows, 'pageTitle' => 'Estates & projects', 'active' => 'land']);
    }

    public function actionEstateView(): void
    {
        Auth::require('land.view');
        $e = $this->estate($this->int('id'));
        $seller = $e['seller_id'] ? db_row('SELECT id, full_name FROM customers WHERE id = ?', [(int)$e['seller_id']]) : null;
        $plots = db_all('SELECT * FROM land_plots WHERE estate_id = ? AND deleted_at IS NULL ORDER BY block_number, CAST(plot_number AS UNSIGNED), plot_number LIMIT 500', [(int)$e['id']]);
        $this->view('land/estate_view', compact('e', 'seller', 'plots') + ['pageTitle' => $e['name'], 'active' => 'land']);
    }

    public function actionEstateEdit(): void
    {
        Auth::require('land.create');
        $id = $this->int('id');
        $e = $id ? $this->estate($id) : ['id' => 0, 'state' => 'Kano'];
        $e['seller_name'] = !empty($e['seller_id']) ? db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$e['seller_id']]) : '';
        form_set($e, []);
        $this->view('land/estate_form', ['e' => $e, 'pageTitle' => $id ? 'Edit estate' : 'Add estate', 'active' => 'land']);
    }

    public function actionEstateSave(): void
    {
        $this->post();
        Auth::require('land.create');
        $id = $this->int('id', 'POST');
        $existing = $id ? $this->estate($id) : null;
        $v = new Validator($_POST);
        $name = $v->text('name', 'Estate / project name', true, 150, 3);
        $v->text('location', 'Location', false, 255);
        $v->text('lga', 'Local government area', false, 80);
        $v->text('state', 'State', true, 60);
        $v->enum('title_status', 'Title / document status', array_keys(list_title_status()));
        $v->area('description', 'Description', false, 4000);
        $sellerId = (int)($_POST['seller_id'] ?? 0) ?: null;
        if ($sellerId && !db_val('SELECT COUNT(*) FROM customers WHERE id = ? AND deleted_at IS NULL', [$sellerId])) $v->fail('seller_id', 'Choose the seller from the search results.');
        if ($name !== null && db_val('SELECT COUNT(*) FROM land_estates WHERE name = ? AND id <> ?', [$name, $id])) $v->fail('name', 'An estate with this name already exists.');
        if ($v->errors) {
            $e = $_POST + ['id' => $id, 'seller_id' => $sellerId];
            form_set($e, $v->errors);
            $this->view('land/estate_form', ['e' => $e, 'pageTitle' => $id ? 'Edit estate' : 'Add estate', 'active' => 'land']);
            return;
        }
        $d = ['name' => $name, 'location' => $v->d['location'], 'lga' => $v->d['lga'], 'state' => $v->d['state'], 'title_status' => $v->d['title_status'], 'description' => $v->d['description'], 'seller_id' => $sellerId];
        db()->beginTransaction();
        try {
            if ($existing) {
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($d)));
                db_run("UPDATE land_estates SET $set WHERE id = :id", $d + ['id' => $id]);
                Audit::log('update', 'land', $id, "Edited estate \"$name\"", Audit::diff($existing, $d, array_keys($d)) ?: null);
            } else {
                $d['created_by'] = Auth::id();
                $cols = array_keys($d);
                db_run('INSERT INTO land_estates (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $d);
                $id = db_id();
                Audit::log('create', 'land', $id, "Added estate \"$name\"");
            }
            if ($sellerId) db_exec("INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, 'seller')", [$sellerId]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Estate saved.');
        redirect('land/estate-view', ['id' => $id]);
    }

    /** Create a run of plots (e.g. plots 1 to 40 in Block B) in one go. */
    public function actionBulkPlots(): void
    {
        $this->post();
        Auth::require('land.create');
        $e = $this->estate($this->int('estate_id', 'POST'));
        $v = new Validator($_POST);
        $block = (string)($v->text('block_number', 'Block', false, 30) ?? '');
        $from = $v->int('from', 'First plot number', true, 1, 99999);
        $to = $v->int('to', 'Last plot number', true, 1, 99999);
        $price = $v->decimal('list_price', 'List price');
        $size = $v->text('size_text', 'Size', false, 60);
        if ($from && $to && ($to < $from || $to - $from >= 200)) $v->fail('to', 'Enter a range of at most 200 plots, with the last number not below the first.');
        if (!$e['seller_id']) $v->fail('from', 'Set a seller on the estate first (Edit estate). Plots need a seller.');
        if ($v->errors) { flash('danger', implode(' ', $v->errors)); redirect('land/estate-view', ['id' => $e['id']]); }
        $made = 0; $skipped = 0;
        db()->beginTransaction();
        try {
            for ($n = $from; $n <= $to; $n++) {
                if (db_val('SELECT COUNT(*) FROM land_plots WHERE estate_id = ? AND block_number = ? AND plot_number = ?', [(int)$e['id'], $block, (string)$n])) { $skipped++; continue; }
                $ref = format_ref(setting('prefix_plot', 'TEA-L-'), next_sequence('plot'));
                db_run('INSERT INTO land_plots (ref, estate_id, plot_number, block_number, location, area, lga, state, size_text, title_status, list_price, seller_id, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    [$ref, (int)$e['id'], (string)$n, $block, $e['location'], $e['location'] ?: $e['name'], $e['lga'], $e['state'], $size, $e['title_status'] ?: 'Not stated', $price, (int)$e['seller_id'], Auth::id()]);
                $made++;
            }
            Audit::log('create', 'land', $e['id'], "Bulk-created $made plot(s) in \"{$e['name']}\"" . ($block !== '' ? " Block $block" : '') . " (numbers $from to $to, $skipped already existed)");
            db()->commit();
        } catch (Throwable $ex) {
            if (db()->inTransaction()) db()->rollBack();
            throw $ex;
        }
        flash('success', "$made plot" . ($made === 1 ? '' : 's') . ' created' . ($skipped ? ", $skipped skipped because they already exist" : '') . '.');
        redirect('land/estate-view', ['id' => $e['id']]);
    }

    public function actionEstateArchive(): void
    {
        $this->post();
        Auth::require('land.delete');
        $e = $this->estate($this->int('id', 'POST'));
        if ((int)db_val('SELECT COUNT(*) FROM land_plots WHERE estate_id = ? AND deleted_at IS NULL', [(int)$e['id']])) {
            flash('danger', 'This estate still has plots. Archive or move the plots first.');
            redirect('land/estate-view', ['id' => $e['id']]);
        }
        db_exec('UPDATE land_estates SET deleted_at = NOW() WHERE id = ?', [(int)$e['id']]);
        Audit::log('archive', 'land', $e['id'], "Archived estate \"{$e['name']}\"");
        flash('success', 'Estate archived.');
        redirect('land/estates');
    }

    private function estate(int $id): array
    {
        $e = db_row('SELECT * FROM land_estates WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$e) http_error(404, 'That estate could not be found.');
        return $e;
    }
}
