<?php
declare(strict_types=1);

final class PropertiesController extends Controller
{
    private const COLS = ['title', 'property_type', 'category', 'listing_for', 'address', 'area', 'lga', 'state', 'description', 'rent_price', 'rent_period',
        'sale_price', 'deposit', 'agency_fee', 'legal_fee', 'service_charge', 'other_fees', 'other_fees_note', 'landlord_id', 'ownership_info', 'agent_id',
        'bedrooms', 'bathrooms', 'toilets', 'parking_spaces', 'furnishing', 'water_supply', 'electricity', 'security_features', 'amenities', 'land_size'];

    private function canEdit(array $p): bool
    {
        if (can('properties.edit_all')) return true;
        return can('properties.edit') && ((int)$p['agent_id'] === Auth::id() || (int)$p['created_by'] === Auth::id());
    }

    /* ---------- list ---------- */
    public function actionIndex(): void
    {
        Auth::require('properties.view');
        $f = ['q' => $this->str('q'), 'type' => $this->str('type'), 'status' => $this->str('status'), 'listing' => $this->str('listing'),
              'area' => $this->str('area'), 'min' => $this->str('min'), 'max' => $this->str('max'), 'media' => $this->str('media'), 'docs' => $this->str('docs')];
        $archived = $this->str('archived') === '1' && can('properties.delete');
        $where = [$archived ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
        $p = [];
        if ($f['q'] !== '') {
            $where[] = '(p.ref LIKE :q OR p.title LIKE :q OR p.address LIKE :q OR p.area LIKE :q OR p.lga LIKE :q OR lc.full_name LIKE :q)';
            $p['q'] = like_escape($f['q']);
        }
        if (isset(list_property_types()[$f['type']])) { $where[] = 'p.property_type = :type'; $p['type'] = $f['type']; }
        if (isset(list_property_statuses()[$f['status']])) { $where[] = 'p.status = :status'; $p['status'] = $f['status']; }
        if (in_array($f['listing'], ['rent', 'sale'], true)) { $where[] = "p.listing_for IN (:l1, 'both')"; $p['l1'] = $f['listing']; }
        if ($f['area'] !== '') { $where[] = 'p.area = :area'; $p['area'] = $f['area']; }
        $priceCol = $f['listing'] === 'sale' ? 'p.sale_price' : 'p.rent_price';
        if (preg_match('/^\d+(\.\d+)?$/', $f['min'])) { $where[] = "$priceCol >= :min"; $p['min'] = $f['min']; }
        if (preg_match('/^\d+(\.\d+)?$/', $f['max'])) { $where[] = "$priceCol <= :max"; $p['max'] = $f['max']; }
        if ($f['media'] === 'none') $where[] = 'NOT EXISTS (SELECT 1 FROM property_images i WHERE i.property_id = p.id)';
        if ($f['docs'] === 'none') $where[] = "NOT EXISTS (SELECT 1 FROM documents d WHERE d.owner_type = 'property' AND d.owner_id = p.id AND d.archived_at IS NULL)";
        $W = implode(' AND ', $where);
        $from = "FROM properties p JOIN customers lc ON lc.id = p.landlord_id WHERE $W";
        $pg = paginate((int)db_val("SELECT COUNT(*) $from", $p), 12);
        $rows = db_all("SELECT p.*, lc.full_name landlord_name,
              (SELECT stored_name FROM property_images i WHERE i.property_id = p.id ORDER BY i.is_featured DESC, i.sort_order, i.id LIMIT 1) thumb
              $from ORDER BY p.id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $areas = array_column(db_all('SELECT DISTINCT area FROM properties WHERE deleted_at IS NULL ORDER BY area'), 'area');
        $this->view('properties/index', compact('rows', 'pg', 'f', 'areas', 'archived') + ['pageTitle' => 'Properties', 'active' => 'properties']);
    }

    /* ---------- detail ---------- */
    public function actionView(): void
    {
        Auth::require('properties.view');
        $p = $this->find($this->int('id'), true);
        $id = (int)$p['id'];
        $landlord = db_row('SELECT id, ref, full_name, phone FROM customers WHERE id = ?', [(int)$p['landlord_id']]);
        $agent = $p['agent_id'] ? db_val('SELECT full_name FROM users WHERE id = ?', [(int)$p['agent_id']]) : null;
        $images = db_all('SELECT * FROM property_images WHERE property_id = ? ORDER BY is_featured DESC, sort_order, id', [$id]);
        $history = db_all('SELECT l.*, u.full_name FROM property_status_log l LEFT JOIN users u ON u.id = l.changed_by WHERE l.property_id = ? ORDER BY l.id DESC LIMIT 15', [$id]);
        $docs = can('documents.view') ? db_all("SELECT d.*, u.full_name uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.owner_type = 'property' AND d.owner_id = ? AND d.archived_at IS NULL ORDER BY d.id DESC", [$id]) : [];
        $next = array_values(array_diff(property_transitions()[$p['status']] ?? [], ['rented', 'sold']));
        $sales = can('sales.view') ? db_all("SELECT s.id, s.ref, s.status, s.sale_price, s.amount_paid, bc.full_name buyer_name FROM sales s JOIN customers bc ON bc.id = s.buyer_id WHERE s.property_id = ? ORDER BY s.id DESC", [$id]) : [];
        $tenancies = can('tenancies.view') ? db_all("SELECT t.id, t.ref, t.status, t.start_date, t.end_date, t.deleted_at, tc.full_name tenant_name, t.total_payable, t.amount_paid, " . Tenancy::effectiveSql('t') . " eff
            FROM tenancies t JOIN customers tc ON tc.id = t.tenant_id WHERE t.property_id = ? AND t.deleted_at IS NULL ORDER BY t.id DESC", [$id]) : [];
        $this->view('properties/view', compact('p', 'landlord', 'agent', 'images', 'history', 'docs', 'next', 'tenancies', 'sales') + [
            'canEdit' => $this->canEdit($p), 'pageTitle' => $p['title'], 'active' => 'properties']);
    }

    /* ---------- create / edit ---------- */
    public function actionCreate(): void
    {
        Auth::require('properties.create');
        $vals = ['state' => 'Kano', 'currency' => 'NGN', 'listing_for' => 'rent', 'category' => 'residential', 'rent_period' => 'annual', 'furnishing' => 'unfurnished', 'agent_id' => Auth::id()];
        if ($lid = $this->int('landlord')) {
            $l = db_row("SELECT c.id, c.full_name FROM customers c JOIN customer_roles r ON r.customer_id = c.id AND r.role = 'landlord' WHERE c.id = ? AND c.deleted_at IS NULL", [$lid]);
            if ($l) { $vals['landlord_id'] = $l['id']; $vals['landlord_name'] = $l['full_name']; }
        }
        $this->form(null, $vals, []);
    }

    public function actionEdit(): void
    {
        Auth::require('properties.edit');
        $p = $this->find($this->int('id'));
        if (!$this->canEdit($p)) http_error(403, 'You can only edit properties assigned to you or created by you.');
        $p['landlord_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$p['landlord_id']]);
        $this->form($p, $p, []);
    }

    private function form(?array $p, array $vals, array $errors): void
    {
        if (!empty($vals['landlord_id']) && empty($vals['landlord_name'])) {
            $vals['landlord_name'] = db_val('SELECT full_name FROM customers WHERE id = ?', [(int)$vals['landlord_id']]);
        }
        form_set($vals, $errors);
        $agents = db_all("SELECT u.id, u.full_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_active = 1 AND r.slug IN ('admin','manager','agent') ORDER BY u.full_name");
        $this->view('properties/form', ['p' => $p, 'agents' => $agents, 'canAssign' => can('properties.edit_all'),
            'pageTitle' => $p ? 'Edit property' : 'Add property', 'active' => 'properties']);
    }

    public function actionSave(): void
    {
        $this->post();
        $id = $this->int('id', 'POST');
        $isNew = $id === 0;
        Auth::require($isNew ? 'properties.create' : 'properties.edit');
        $existing = $isNew ? null : $this->find($id);
        if ($existing && !$this->canEdit($existing)) http_error(403, 'You can only edit properties assigned to you or created by you.');

        $v = new Validator($_POST);
        $v->text('title', 'Title', true, 180, 3);
        $v->enum('property_type', 'Property type', array_keys(list_property_types()), true);
        $v->enum('category', 'Category', array_keys(list_categories()), true);
        $listing = $v->enum('listing_for', 'Listing type', array_keys(list_listing_for()), true);
        $v->text('address', 'Address', true, 255);
        $v->text('area', 'Area / neighbourhood', true, 100);
        $v->text('lga', 'Local government area', false, 80);
        $v->text('state', 'State', true, 60);
        $v->area('description', 'Description', false, 5000);
        $v->decimal('rent_price', 'Rent', in_array($listing, ['rent', 'both'], true));
        $v->enum('rent_period', 'Rent period', array_keys(list_rent_periods()), true);
        $v->decimal('sale_price', 'Sale price', in_array($listing, ['sale', 'both'], true));
        foreach (['deposit' => 'Deposit', 'agency_fee' => 'Agency fee', 'legal_fee' => 'Legal fee', 'service_charge' => 'Service charge', 'other_fees' => 'Other fees'] as $k => $l) $v->decimal($k, $l);
        if (in_array($listing, ['rent', 'both'], true) && $v->d['rent_price'] !== null && (float)$v->d['rent_price'] <= 0) $v->fail('rent_price', 'Rent must be greater than zero.');
        if (in_array($listing, ['sale', 'both'], true) && $v->d['sale_price'] !== null && (float)$v->d['sale_price'] <= 0) $v->fail('sale_price', 'Sale price must be greater than zero.');
        $v->text('other_fees_note', 'Other fees description', false, 150);
        $v->text('ownership_info', 'Ownership information', false, 255);
        foreach (['bedrooms' => 'Bedrooms', 'bathrooms' => 'Bathrooms', 'toilets' => 'Toilets', 'parking_spaces' => 'Parking spaces'] as $k => $l) $v->int($k, $l, false, 0, 100);
        $v->enum('furnishing', 'Furnishing', array_keys(list_furnishing()), true);
        $v->enum('water_supply', 'Water supply', array_keys(list_water()));
        $v->enum('electricity', 'Electricity', array_keys(list_electricity()));
        $v->text('security_features', 'Security', false, 150);
        $v->area('amenities', 'Other amenities', false, 2000);
        $v->text('land_size', 'Land size', false, 60);

        $lid = (int)($_POST['landlord_id'] ?? 0);
        if (!$lid || !db_val("SELECT COUNT(*) FROM customers c JOIN customer_roles r ON r.customer_id = c.id AND r.role = 'landlord' WHERE c.id = ? AND c.deleted_at IS NULL", [$lid])) {
            $v->fail('landlord_id', 'Choose the property owner from the search results. They must have the landlord role.');
        }
        $agentId = $existing['agent_id'] ?? Auth::id();
        if (can('properties.edit_all')) {
            $aid = (int)($_POST['agent_id'] ?? 0);
            if ($aid && !db_val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.is_active = 1 AND r.slug IN ('admin','manager','agent')", [$aid])) $v->fail('agent_id', 'Choose a valid agent.');
            $agentId = $aid ?: null;
        }
        if ($v->errors) {
            $this->form($existing, $_POST + ['landlord_id' => $lid], $v->errors);
            return;
        }
        $data = [];
        foreach (self::COLS as $k) $data[$k] = $v->d[$k] ?? null;
        $data['landlord_id'] = $lid;
        $data['agent_id'] = $agentId;
        if (!in_array($listing, ['rent', 'both'], true)) $data['rent_price'] = null;
        if (!in_array($listing, ['sale', 'both'], true)) $data['sale_price'] = null;

        db()->beginTransaction();
        try {
            if ($isNew) {
                $data['ref'] = format_ref(setting('prefix_property', 'TEA-P-'), next_sequence('property'));
                $data['created_by'] = Auth::id();
                $data['status'] = 'available';
                $cols = array_keys($data);
                db_run('INSERT INTO properties (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $data);
                $id = db_id();
                db_exec('INSERT INTO property_status_log (property_id, from_status, to_status, note, changed_by) VALUES (?,NULL,?,?,?)', [$id, 'available', 'Property added', Auth::id()]);
                Audit::log('create', 'properties', $id, 'Added property ' . $data['ref'] . ' "' . $data['title'] . '"');
            } else {
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($data)));
                db_run("UPDATE properties SET $set WHERE id = :id", $data + ['id' => $id]);
                $ch = Audit::diff($existing, $data, self::COLS);
                Audit::log('update', 'properties', $id, 'Edited property ' . $existing['ref'] . ($ch ? ' (changed: ' . implode(', ', array_keys($ch)) . ')' : ''), $ch ?: null);
            }
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', $isNew ? 'Property added. Now upload photos and documents.' : 'Property updated.');
        redirect('properties/view', ['id' => $id]);
    }

    /* ---------- status workflow ---------- */
    public function actionStatus(): void
    {
        $this->post();
        Auth::require('properties.status');
        $p = $this->find($this->int('id', 'POST'));
        $to = $this->str('status', 'POST');
        $note = mb_substr($this->str('note', 'POST'), 0, 255);
        if (!in_array($to, property_transitions()[$p['status']] ?? [], true)) {
            flash('danger', 'That status change is not allowed from "' . (list_property_statuses()[$p['status']][0] ?? $p['status']) . '".');
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'sold') {
            flash('danger', 'A property becomes Sold when its sale is completed. Create a sale for it instead.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'available' && ($sl = db_val("SELECT ref FROM sales WHERE property_id = ? AND status IN ('reserved','deposit_paid','partially_paid','fully_paid') LIMIT 1", [(int)$p['id']]))) {
            flash('danger', "Sale $sl is holding this property. Cancel that sale first; the property then becomes Available automatically.");
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'rented') {
            flash('danger', 'A property becomes Rented automatically when a tenancy is activated. Create a tenancy for it instead.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'available' && ($n = db_val("SELECT ref FROM tenancies WHERE property_id = ? AND status = 'active' AND deleted_at IS NULL LIMIT 1", [(int)$p['id']]))) {
            flash('danger', "Tenancy $n is still active on this property. Terminate or let it be closed first; the property then becomes Available automatically.");
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'sold' && $p['listing_for'] === 'rent') {
            flash('danger', 'This property is listed for rent only. Change the listing type first if it is being sold.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        if ($to === 'rented' && $p['listing_for'] === 'sale') {
            flash('danger', 'This property is listed for sale only. Change the listing type first if it is being let.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        db()->beginTransaction();
        try {
            db_exec('UPDATE properties SET status = ? WHERE id = ?', [$to, (int)$p['id']]);
            db_exec('INSERT INTO property_status_log (property_id, from_status, to_status, note, changed_by) VALUES (?,?,?,?,?)', [(int)$p['id'], $p['status'], $to, $note ?: null, Auth::id()]);
            Audit::log('status', 'properties', $p['id'], "Property {$p['ref']} status: {$p['status']} to $to", ['status' => [$p['status'], $to], 'note' => $note]);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', 'Status changed to ' . (list_property_statuses()[$to][0] ?? $to) . '.');
        redirect('properties/view', ['id' => $p['id']]);
    }

    /* ---------- archive / restore ---------- */
    public function actionArchive(): void
    {
        $this->post();
        Auth::require('properties.delete');
        $p = $this->find($this->int('id', 'POST'));
        if (in_array($p['status'], ['reserved', 'rented', 'sold'], true)) {
            flash('danger', 'Reserved, rented and sold properties are part of the business record and cannot be archived. Change the status first if appropriate.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        db_exec('UPDATE properties SET deleted_at = NOW() WHERE id = ?', [(int)$p['id']]);
        Audit::log('archive', 'properties', $p['id'], 'Archived property ' . $p['ref']);
        flash('success', 'Property archived.');
        redirect('properties');
    }

    public function actionRestore(): void
    {
        $this->post();
        Auth::require('properties.delete');
        $p = $this->find($this->int('id', 'POST'), true);
        if (!(int)db_val("SELECT COUNT(*) FROM customers WHERE id = ? AND deleted_at IS NULL", [(int)$p['landlord_id']])) {
            flash('danger', 'The owner of this property is archived. Restore the owner first.');
            redirect('properties/view', ['id' => $p['id']]);
        }
        db_exec('UPDATE properties SET deleted_at = NULL WHERE id = ?', [(int)$p['id']]);
        Audit::log('restore', 'properties', $p['id'], 'Restored property ' . $p['ref']);
        flash('success', 'Property restored.');
        redirect('properties/view', ['id' => $p['id']]);
    }

    /* ---------- images ---------- */
    public function actionUploadImages(): void
    {
        $this->post();
        Auth::require('properties.edit');
        $p = $this->find($this->int('id', 'POST'));
        if (!$this->canEdit($p)) http_error(403, 'You cannot change photos on this property.');
        $files = Upload::files('images');
        if (!$files) { flash('danger', 'Choose at least one photo to upload.'); redirect('properties/view', ['id' => $p['id']]); }
        $have = (int)db_val('SELECT COUNT(*) FROM property_images WHERE property_id = ?', [(int)$p['id']]);
        if ($have + count($files) > 30) { flash('danger', 'A property can have at most 30 photos.'); redirect('properties/view', ['id' => $p['id']]); }
        $ok = 0; $errs = [];
        foreach ($files as $f) {
            try {
                $r = Upload::saveImage($f);
                $order = (int)db_val('SELECT COALESCE(MAX(sort_order),0)+1 FROM property_images WHERE property_id = ?', [(int)$p['id']]);
                $feat = ($have + $ok) === 0 ? 1 : 0;
                db_exec('INSERT INTO property_images (property_id, stored_name, original_name, is_featured, sort_order, uploaded_by) VALUES (?,?,?,?,?,?)',
                    [(int)$p['id'], $r['stored_name'], $r['original_name'], $feat, $order, Auth::id()]);
                $ok++;
            } catch (UploadException $e) {
                $errs[] = $e->getMessage();
            }
        }
        if ($ok) Audit::log('upload', 'properties', $p['id'], "Uploaded $ok photo(s) to {$p['ref']}");
        if ($ok) flash('success', "$ok photo" . ($ok > 1 ? 's' : '') . ' uploaded.');
        foreach (array_slice($errs, 0, 3) as $m) flash('danger', $m);
        redirect('properties/view', ['id' => $p['id']], );
    }

    private function image(): array
    {
        $img = db_row('SELECT i.*, p.agent_id, p.created_by, p.ref FROM property_images i JOIN properties p ON p.id = i.property_id WHERE i.id = ?', [$this->int('image_id', 'POST')]);
        if (!$img) http_error(404, 'Photo not found.');
        Auth::require('properties.edit');
        if (!$this->canEdit($img)) http_error(403, 'You cannot change photos on this property.');
        return $img;
    }

    public function actionFeatureImage(): void
    {
        $this->post();
        $i = $this->image();
        db()->beginTransaction();
        db_exec('UPDATE property_images SET is_featured = 0 WHERE property_id = ?', [(int)$i['property_id']]);
        db_exec('UPDATE property_images SET is_featured = 1 WHERE id = ?', [(int)$i['id']]);
        db()->commit();
        Audit::log('update', 'properties', $i['property_id'], "Changed featured photo on {$i['ref']}");
        flash('success', 'Featured photo updated.');
        redirect('properties/view', ['id' => $i['property_id']]);
    }

    public function actionMoveImage(): void
    {
        $this->post();
        $i = $this->image();
        $dir = $this->str('dir', 'POST') === 'up' ? -1 : 1;
        $list = db_all('SELECT id FROM property_images WHERE property_id = ? ORDER BY sort_order, id', [(int)$i['property_id']]);
        $ids = array_map(fn($r) => (int)$r['id'], $list);
        $pos = array_search((int)$i['id'], $ids, true);
        $swap = $pos + $dir;
        if ($pos !== false && isset($ids[$swap])) {
            [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
            db()->beginTransaction();
            foreach ($ids as $n => $iid) db_exec('UPDATE property_images SET sort_order = ? WHERE id = ?', [$n + 1, $iid]);
            db()->commit();
        }
        redirect('properties/view', ['id' => $i['property_id']]);
    }

    public function actionDeleteImage(): void
    {
        $this->post();
        $i = $this->image();
        db()->beginTransaction();
        db_exec('DELETE FROM property_images WHERE id = ?', [(int)$i['id']]);
        if ($i['is_featured']) {
            db_exec('UPDATE property_images SET is_featured = 1 WHERE property_id = ? ORDER BY sort_order, id LIMIT 1', [(int)$i['property_id']]);
        }
        Audit::log('delete', 'properties', $i['property_id'], "Deleted a photo from {$i['ref']} ({$i['original_name']})");
        db()->commit();
        foreach ([$i['stored_name'] . '.jpg', $i['stored_name'] . '_t.jpg'] as $f) @unlink(TEAMS_ROOT . '/storage/images/' . $f);
        flash('success', 'Photo deleted.');
        redirect('properties/view', ['id' => $i['property_id']]);
    }

    /** JSON for the tenancy form: properties that can be let, with their default fees. */
    public function actionLookup(): void
    {
        Auth::require('properties.view');
        $q = $this->str('q');
        if (mb_strlen($q) < 2) $this->json([]);
        $forSale = $this->str('for') === 'sale';
        $rows = db_all("SELECT p.*, lc.full_name landlord_name FROM properties p JOIN customers lc ON lc.id = p.landlord_id
            WHERE p.deleted_at IS NULL AND " . ($forSale ? "p.listing_for IN ('sale','both') AND p.status = 'available'" : "p.listing_for IN ('rent','both') AND p.status IN ('available','reserved')") . "
              AND (p.ref LIKE :q OR p.title LIKE :q OR p.address LIKE :q OR p.area LIKE :q) ORDER BY p.title LIMIT 10", ['q' => like_escape($q)]);
        $this->json(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['ref'] . ' · ' . $r['title'], 'meta' => $r['area'] . ' · ' . ($forSale ? ($r['sale_price'] !== null ? money($r['sale_price']) : 'no price set') : ($r['rent_price'] !== null ? money($r['rent_price']) : 'no rent set')) . ' · Owner: ' . $r['landlord_name'],
            'data' => ['price' => $r['sale_price'], 'seller' => $r['landlord_name'], 'rent' => $r['rent_price'], 'period' => $r['rent_period'], 'deposit' => $r['deposit'], 'agency' => $r['agency_fee'], 'legal' => $r['legal_fee'], 'service' => $r['service_charge'], 'other' => $r['other_fees'], 'other_note' => $r['other_fees_note']]], $rows));
    }

    private function find(int $id, bool $includeArchived = false): array
    {
        $p = db_row('SELECT * FROM properties WHERE id = ?' . ($includeArchived ? '' : ' AND deleted_at IS NULL'), [$id]);
        if (!$p) http_error(404, 'That property could not be found. It may have been archived.');
        return $p;
    }
}
