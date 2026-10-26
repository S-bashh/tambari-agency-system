<?php
declare(strict_types=1);

final class CustomersController extends Controller
{
    private const COLS = ['full_name', 'gender', 'date_of_birth', 'phone', 'phone_alt', 'email', 'address', 'town', 'lga', 'state_of_origin',
        'nationality', 'marital_status', 'religion', 'occupation', 'employer_name', 'employer_address', 'id_type', 'id_number',
        'emergency_name', 'emergency_phone', 'emergency_relationship', 'notes'];

    /* ---------- list ---------- */
    public function actionIndex(): void
    {
        Auth::require('customers.view');
        $q = $this->str('q');
        $role = $this->str('role');
        $archived = $this->str('status') === 'archived' && can('customers.delete');
        $where = [$archived ? 'c.deleted_at IS NOT NULL' : 'c.deleted_at IS NULL'];
        $p = [];
        if ($q !== '') {
            $like = like_escape($q);
            $digits = preg_replace('/\D+/', '', $q) ?? '';
            $w = '(c.full_name LIKE :q OR c.ref LIKE :q OR c.email LIKE :q OR c.phone LIKE :q';
            $p['q'] = $like;
            if (strlen($digits) >= 4) {
                $w .= ' OR c.phone_norm LIKE :pn';
                $p['pn'] = '%' . $digits . '%';
            }
            $where[] = $w . ')';
        }
        if (isset(list_roles()[$role])) {
            $where[] = 'EXISTS (SELECT 1 FROM customer_roles r WHERE r.customer_id = c.id AND r.role = :role)';
            $p['role'] = $role;
        }
        $W = implode(' AND ', $where);
        $pg = paginate((int)db_val("SELECT COUNT(*) FROM customers c WHERE $W", $p));
        $rows = db_all("SELECT c.id, c.ref, c.full_name, c.phone, c.email, c.occupation, c.created_at,
                (SELECT GROUP_CONCAT(role) FROM customer_roles WHERE customer_id = c.id) roles
            FROM customers c WHERE $W ORDER BY c.full_name ASC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $p);
        $this->view('customers/index', compact('rows', 'pg', 'q', 'role', 'archived') + ['pageTitle' => 'Customers', 'active' => 'customers']);
    }

    /* ---------- profile ---------- */
    public function actionView(): void
    {
        Auth::require('customers.view');
        $c = $this->find($this->int('id'), true);
        $id = (int)$c['id'];
        $roles = array_column(db_all('SELECT role FROM customer_roles WHERE customer_id = ?', [$id]), 'role');
        $tenant = in_array('tenant', $roles, true) && can('tenants.view') ? db_row('SELECT * FROM tenants WHERE customer_id = ?', [$id]) : null;
        $landlord = in_array('landlord', $roles, true) && can('landlords.view') ? db_row('SELECT * FROM landlords WHERE customer_id = ?', [$id]) : null;
        $guarantor = in_array('guarantor', $roles, true) && can('guarantors.view') ? db_row('SELECT * FROM guarantors WHERE customer_id = ?', [$id]) : null;

        $guarantors = $tenant ? db_all('SELECT tg.*, c.id cid, c.full_name, c.phone, c.ref, c.occupation FROM tenant_guarantors tg
            JOIN guarantors g ON g.id = tg.guarantor_id JOIN customers c ON c.id = g.customer_id WHERE tg.tenant_id = ? ORDER BY tg.id', [(int)$tenant['id']]) : [];
        $guaranteed = $guarantor ? db_all('SELECT tg.*, c.id cid, c.full_name, c.phone, c.ref FROM tenant_guarantors tg
            JOIN tenants t ON t.id = tg.tenant_id JOIN customers c ON c.id = t.customer_id WHERE tg.guarantor_id = ? ORDER BY tg.id', [(int)$guarantor['id']]) : [];
        $properties = $landlord && can('properties.view') ? db_all('SELECT id, ref, title, area, status, listing_for FROM properties WHERE landlord_id = ? AND deleted_at IS NULL ORDER BY id DESC', [$id]) : [];
        $docs = can('documents.view') ? db_all("SELECT d.*, u.full_name uploader FROM documents d LEFT JOIN users u ON u.id = d.uploaded_by
            WHERE d.owner_type = 'customer' AND d.owner_id = ? AND d.archived_at IS NULL ORDER BY d.id DESC", [$id]) : [];
        $tenancies = $payments = $landlordPayments = [];
        if (can('tenancies.view') && in_array('tenant', $roles, true)) {
            $tenancies = db_all('SELECT t.id, t.ref, t.status, t.start_date, t.end_date, t.total_payable, t.amount_paid, p.title property_title, ' . Tenancy::effectiveSql('t') . ' eff
                FROM tenancies t JOIN properties p ON p.id = t.property_id WHERE t.tenant_id = ? AND t.deleted_at IS NULL ORDER BY t.start_date DESC', [$id]);
        }
        if (can('payments.view')) {
            $payments = db_all('SELECT p.id, p.txn_id, p.amount, p.payment_date, p.payment_type, p.status, r.id receipt_id, r.receipt_no FROM payments p LEFT JOIN receipts r ON r.payment_id = p.id
                WHERE p.customer_id = ? ORDER BY p.payment_date DESC, p.id DESC LIMIT 15', [$id]);
            if (in_array('landlord', $roles, true)) {
                $landlordPayments = db_all("SELECT p.id, p.txn_id, p.amount, p.payment_date, p.payment_type, p.status, r.receipt_no, c.full_name payer FROM payments p JOIN tenancies t ON t.id = p.tenancy_id
                    JOIN customers c ON c.id = p.customer_id LEFT JOIN receipts r ON r.payment_id = p.id WHERE t.landlord_id = ? ORDER BY p.payment_date DESC, p.id DESC LIMIT 10", [$id]);
            }
        }
        $sales = can('sales.view') ? db_all("SELECT s.id, s.ref, s.status, s.sale_price, s.amount_paid, s.buyer_id, COALESCE(pr.title, CONCAT('Plot ', lp.plot_number)) asset FROM sales s LEFT JOIN properties pr ON pr.id = s.property_id LEFT JOIN land_plots lp ON lp.id = s.plot_id
            WHERE s.buyer_id = ? OR s.seller_id = ? ORDER BY s.id DESC LIMIT 20", [$id, $id]) : [];
        $activity = can('audit.view') ? db_all("SELECT * FROM audit_logs WHERE module = 'customers' AND record_id = ? ORDER BY id DESC LIMIT 10", [(string)$id]) : [];
        $this->view('customers/view', compact('c', 'roles', 'tenant', 'landlord', 'guarantor', 'guarantors', 'guaranteed', 'properties', 'docs', 'activity', 'tenancies', 'payments', 'landlordPayments', 'sales') +
            ['pageTitle' => $c['full_name'], 'active' => 'customers']);
    }

    /* ---------- create / edit ---------- */
    public function actionCreate(): void
    {
        Auth::require('customers.create');
        $role = $this->str('role');
        $vals = ['nationality' => 'Nigerian', 'roles' => isset(list_roles()[$role]) ? [$role] : []];
        $forTenant = $this->int('for_tenant');
        $this->renderForm(null, $vals, [], [], $forTenant);
    }

    public function actionEdit(): void
    {
        Auth::require('customers.edit');
        $c = $this->find($this->int('id'));
        $vals = $c + ['roles' => $this->rolesOf((int)$c['id'])] + $this->extrasOf((int)$c['id']);
        $this->renderForm($c, $vals, [], []);
    }

    private function renderForm(?array $c, array $vals, array $errors, array $dups, int $forTenant = 0): void
    {
        form_set($vals, $errors);
        $tenantName = null;
        if ($forTenant) {
            $tenantName = db_val("SELECT c.full_name FROM tenants t JOIN customers c ON c.id = t.customer_id WHERE c.id = ? AND c.deleted_at IS NULL", [$forTenant]);
        }
        $this->view('customers/form', [
            'c' => $c, 'dups' => $dups, 'forTenant' => $tenantName ? $forTenant : 0, 'forTenantName' => $tenantName,
            'selRoles' => (array)($vals['roles'] ?? []), 'manage' => $this->manageable(),
            'pageTitle' => $c ? 'Edit ' . $c['full_name'] : 'Add person', 'active' => 'customers',
        ]);
    }

    private function manageable(): array
    {
        return ['tenant' => can('tenants.manage'), 'landlord' => can('landlords.manage'), 'guarantor' => can('guarantors.manage'),
                'buyer' => can('customers.edit'), 'seller' => can('customers.edit')];
    }
    private function rolesOf(int $id): array { return array_column(db_all('SELECT role FROM customer_roles WHERE customer_id = ?', [$id]), 'role'); }
    private function extrasOf(int $id): array
    {
        $o = [];
        if ($t = db_row('SELECT * FROM tenants WHERE customer_id = ?', [$id])) {
            foreach (['spouse_name', 'spouse_employer', 'number_of_kids', 'office_phone', 'wedding_anniversary', 'notes'] as $k) $o['t_' . $k] = $t[$k];
        }
        if ($l = db_row('SELECT * FROM landlords WHERE customer_id = ?', [$id])) {
            foreach (['bank_name', 'account_name', 'account_number', 'notes'] as $k) $o['l_' . $k] = $l[$k];
        }
        if ($g = db_row('SELECT * FROM guarantors WHERE customer_id = ?', [$id])) $o['g_notes'] = $g['notes'];
        return $o;
    }

    /* ---------- save ---------- */
    public function actionSave(): void
    {
        $this->post();
        $id = $this->int('id', 'POST');
        $isNew = $id === 0;
        Auth::require($isNew ? 'customers.create' : 'customers.edit');
        $existing = $isNew ? null : $this->find($id);
        $manage = $this->manageable();

        $v = new Validator($_POST);
        $v->text('full_name', 'Full name', true, 150, 3);
        $v->enum('gender', 'Gender', array_keys(list_genders()));
        $v->date('date_of_birth', 'Date of birth', false, today(), '1900-01-01');
        $v->phone('phone', 'Phone number', true);
        $v->phone('phone_alt', 'Alternative phone');
        $v->email('email', 'Email');
        $v->area('address', 'Address', false, 1000);
        $v->text('town', 'Town', false, 80);
        $v->text('lga', 'Local government area', false, 80);
        $v->text('state_of_origin', 'State of origin', false, 60);
        $v->text('nationality', 'Nationality', false, 60);
        $v->enum('marital_status', 'Marital status', array_keys(list_marital()));
        $v->text('religion', 'Religion', false, 40);
        $v->text('occupation', 'Occupation', false, 100);
        $v->text('employer_name', 'Employer', false, 150);
        $v->text('employer_address', 'Employer address', false, 255);
        $v->enum('id_type', 'ID type', array_keys(list_id_types()));
        $v->text('id_number', 'ID number', false, 60);
        $v->text('emergency_name', 'Emergency contact name', false, 150);
        $v->phone('emergency_phone', 'Emergency contact phone');
        $v->text('emergency_relationship', 'Emergency contact relationship', false, 60);
        $v->area('notes', 'Notes', false, 3000);
        if ($v->d['id_number'] !== null && empty($v->d['id_type'])) $v->fail('id_type', 'Choose the ID type for this ID number.');

        // roles: only change the roles this user is allowed to manage
        $submitted = array_values(array_intersect((array)($_POST['roles'] ?? []), array_keys(list_roles())));
        $current = $existing ? $this->rolesOf($id) : [];
        $final = [];
        foreach (array_keys(list_roles()) as $r) {
            $keep = $manage[$r] ? in_array($r, $submitted, true) : in_array($r, $current, true);
            if ($keep) $final[] = $r;
        }
        if ($existing) {
            if (in_array('landlord', $current, true) && !in_array('landlord', $final, true)
                && ($n = (int)db_val('SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND deleted_at IS NULL', [$id]))) {
                $v->fail('roles', "This person owns $n propert" . ($n > 1 ? 'ies' : 'y') . ' and must stay a landlord.');
            }
            if (in_array('guarantor', $current, true) && !in_array('guarantor', $final, true)
                && (int)db_val('SELECT COUNT(*) FROM tenant_guarantors tg JOIN guarantors g ON g.id = tg.guarantor_id WHERE g.customer_id = ?', [$id])) {
                $v->fail('roles', 'This person guarantees at least one tenant. Remove those links before removing the guarantor role.');
            }
        }

        // role-specific details
        $x = new Validator($_POST);
        $x->text('t_spouse_name', 'Spouse name', false, 150);
        $x->text('t_spouse_employer', 'Spouse employer', false, 150);
        $x->int('t_number_of_kids', 'Number of children', false, 0, 40);
        $x->phone('t_office_phone', 'Office phone');
        $x->date('t_wedding_anniversary', 'Wedding anniversary', false, today(), '1900-01-01');
        $x->area('t_notes', 'Tenant notes', false, 2000);
        $x->text('l_bank_name', 'Bank name', false, 100);
        $x->text('l_account_name', 'Account name', false, 150);
        $x->text('l_account_number', 'Account number', false, 20);
        if ($x->d['l_account_number'] !== null && !preg_match('/^\d{10}$/', (string)$x->d['l_account_number'])) $x->fail('l_account_number', 'A Nigerian bank account number has 10 digits.');
        $x->area('l_notes', 'Landlord notes', false, 2000);
        $x->area('g_notes', 'Guarantor notes', false, 2000);
        $errors = $v->errors;
        foreach ($x->errors as $k => $m) {
            $role = ['t' => 'tenant', 'l' => 'landlord', 'g' => 'guarantor'][$k[0]];
            if (in_array($role, $final, true) && $manage[$role]) $errors[$k] = $m;
        }

        $forTenant = $this->int('for_tenant', 'POST');
        $link = new Validator($_POST);
        $link->text('link_relationship', 'Relationship to tenant', false, 80);
        $link->text('link_years_known', 'How long known', false, 40);
        $errors += $link->errors;

        // duplicate detection (new people, or when identifying details changed)
        $dups = [];
        if (!$errors && empty($_POST['confirm_dup'])) {
            $pn = norm_phone($v->d['phone']);
            $changed = $isNew || $pn !== (string)$existing['phone_norm'] || mb_strtolower((string)$existing['email']) !== mb_strtolower((string)($v->d['email'] ?? '')) || mb_strtolower($existing['full_name']) !== mb_strtolower((string)$v->d['full_name']);
            if ($changed) $dups = $this->findDuplicates($v->d, $id);
        }
        if ($errors || $dups) {
            $vals = $_POST + ['roles' => $final];
            $vals['roles'] = $final;
            $this->renderForm($existing, $vals, $errors, $dups, $forTenant);
            return;
        }

        $data = [];
        foreach (self::COLS as $k) $data[$k] = $v->d[$k] ?? null;
        $data['email'] = $data['email'] ? mb_strtolower($data['email']) : null;
        $data['phone_norm'] = norm_phone($data['phone']);

        db()->beginTransaction();
        try {
            if ($isNew) {
                $data['ref'] = format_ref(setting('prefix_customer', 'TEA-C-'), next_sequence('customer'));
                $data['created_by'] = Auth::id();
                $cols = array_keys($data);
                db_run('INSERT INTO customers (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')', $data);
                $id = db_id();
            } else {
                $set = implode(',', array_map(fn($k) => "$k = :$k", array_keys($data)));
                db_run("UPDATE customers SET $set WHERE id = :id", $data + ['id' => $id]);
            }
            // roles
            foreach (array_diff($current, $final) as $r) db_exec('DELETE FROM customer_roles WHERE customer_id = ? AND role = ?', [$id, $r]);
            foreach (array_diff($final, $current) as $r) db_exec('INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, ?)', [$id, $r]);
            // role detail rows
            if (in_array('tenant', $final, true) && $manage['tenant']) {
                db_run('INSERT INTO tenants (customer_id, spouse_name, spouse_employer, number_of_kids, office_phone, wedding_anniversary, notes) VALUES (?,?,?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE spouse_name = VALUES(spouse_name), spouse_employer = VALUES(spouse_employer), number_of_kids = VALUES(number_of_kids),
                        office_phone = VALUES(office_phone), wedding_anniversary = VALUES(wedding_anniversary), notes = VALUES(notes)',
                    [$id, $x->d['t_spouse_name'], $x->d['t_spouse_employer'], $x->d['t_number_of_kids'], $x->d['t_office_phone'], $x->d['t_wedding_anniversary'], $x->d['t_notes']]);
            }
            if (in_array('landlord', $final, true) && $manage['landlord']) {
                db_run('INSERT INTO landlords (customer_id, bank_name, account_name, account_number, notes) VALUES (?,?,?,?,?)
                        ON DUPLICATE KEY UPDATE bank_name = VALUES(bank_name), account_name = VALUES(account_name), account_number = VALUES(account_number), notes = VALUES(notes)',
                    [$id, $x->d['l_bank_name'], $x->d['l_account_name'], $x->d['l_account_number'], $x->d['l_notes']]);
            }
            if (in_array('guarantor', $final, true) && $manage['guarantor']) {
                db_run('INSERT INTO guarantors (customer_id, notes) VALUES (?,?) ON DUPLICATE KEY UPDATE notes = VALUES(notes)', [$id, $x->d['g_notes']]);
            }
            // optional: attach new guarantor to a tenant straight away
            if ($isNew && $forTenant && in_array('guarantor', $final, true) && can('tenants.manage')) {
                $tid = db_val('SELECT t.id FROM tenants t JOIN customers c ON c.id = t.customer_id WHERE c.id = ? AND c.deleted_at IS NULL', [$forTenant]);
                $gid = db_val('SELECT id FROM guarantors WHERE customer_id = ?', [$id]);
                if ($tid && $gid) {
                    db_run('INSERT IGNORE INTO tenant_guarantors (tenant_id, guarantor_id, relationship, years_known, consented) VALUES (?,?,?,?,?)',
                        [$tid, $gid, $link->d['link_relationship'], $link->d['link_years_known'], empty($_POST['link_consented']) ? 0 : 1]);
                    Audit::log('link', 'customers', $forTenant, 'Linked guarantor ' . $data['full_name'] . ' to tenant');
                }
            }
            if ($isNew) {
                Audit::log('create', 'customers', $id, 'Added ' . $data['full_name'] . ' (' . $data['ref'] . ')' . ($final ? ' as ' . implode(', ', $final) : ''));
            } else {
                $changes = Audit::diff($existing, $data, self::COLS);
                if ($current !== $final) $changes['roles'] = [implode(',', $current), implode(',', $final)];
                Audit::log('update', 'customers', $id, 'Edited ' . $data['full_name'] . ($changes ? ' (changed: ' . implode(', ', array_keys($changes)) . ')' : ''), $changes ?: null);
            }
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash('success', $isNew ? 'Person added successfully.' : 'Changes saved.');
        redirect('customers/view', ['id' => $forTenant && $isNew && in_array('guarantor', $final, true) ? $forTenant : $id]);
    }

    private function findDuplicates(array $d, int $excludeId): array
    {
        $pn = norm_phone($d['phone'] ?? null);
        $pn2 = norm_phone($d['phone_alt'] ?? null);
        return db_all("SELECT id, ref, full_name, phone, email, date_of_birth FROM customers
            WHERE deleted_at IS NULL AND id <> ?
              AND ( (phone_norm <> '' AND phone_norm IN (?, ?)) OR (? <> '' AND email = ?) OR (LOWER(full_name) = ? AND date_of_birth <=> ?) )
            LIMIT 5", [$excludeId, $pn, $pn2, (string)($d['email'] ?? ''), (string)($d['email'] ?? ''), mb_strtolower((string)$d['full_name']), $d['date_of_birth'] ?? null]);
    }

    /* ---------- archive / restore ---------- */
    public function actionArchive(): void
    {
        $this->post();
        Auth::require('customers.delete');
        $c = $this->find($this->int('id', 'POST'));
        if ((int)db_val('SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND deleted_at IS NULL', [(int)$c['id']])) {
            flash('danger', 'Cannot archive: this landlord still owns active properties. Reassign or archive those properties first.');
            redirect('customers/view', ['id' => $c['id']]);
        }
        db_exec('UPDATE customers SET deleted_at = NOW() WHERE id = ?', [(int)$c['id']]);
        Audit::log('archive', 'customers', $c['id'], 'Archived ' . $c['full_name'] . ' (' . $c['ref'] . ')');
        flash('success', $c['full_name'] . ' was archived. You can restore them from the archived list.');
        redirect('customers');
    }

    public function actionRestore(): void
    {
        $this->post();
        Auth::require('customers.delete');
        $c = $this->find($this->int('id', 'POST'), true);
        db_exec('UPDATE customers SET deleted_at = NULL WHERE id = ?', [(int)$c['id']]);
        Audit::log('restore', 'customers', $c['id'], 'Restored ' . $c['full_name'] . ' (' . $c['ref'] . ')');
        flash('success', $c['full_name'] . ' was restored.');
        redirect('customers/view', ['id' => $c['id']]);
    }

    /* ---------- guarantor links ---------- */
    public function actionLinkGuarantor(): void
    {
        $this->post();
        Auth::require('tenants.manage');
        Auth::require('guarantors.manage');
        $tc = $this->find($this->int('tenant_customer_id', 'POST'));
        $gc = db_row('SELECT * FROM customers WHERE id = ? AND deleted_at IS NULL', [$this->int('guarantor_customer_id', 'POST')]);
        $tid = db_val('SELECT t.id FROM tenants t JOIN customer_roles r ON r.customer_id = t.customer_id AND r.role = \'tenant\' WHERE t.customer_id = ?', [(int)$tc['id']]);
        if (!$gc || !$tid) {
            flash('danger', 'Pick an existing person from the search results to link as guarantor.');
            redirect('customers/view', ['id' => $tc['id']]);
        }
        if ((int)$gc['id'] === (int)$tc['id']) {
            flash('danger', 'A tenant cannot be their own guarantor.');
            redirect('customers/view', ['id' => $tc['id']]);
        }
        $v = new Validator($_POST);
        $v->text('relationship', 'Relationship', false, 80);
        $v->text('years_known', 'How long known', false, 40);
        db()->beginTransaction();
        try {
            db_exec('INSERT IGNORE INTO customer_roles (customer_id, role) VALUES (?, \'guarantor\')', [(int)$gc['id']]);
            db_exec('INSERT IGNORE INTO guarantors (customer_id) VALUES (?)', [(int)$gc['id']]);
            $gid = (int)db_val('SELECT id FROM guarantors WHERE customer_id = ?', [(int)$gc['id']]);
            $n = db_exec('INSERT IGNORE INTO tenant_guarantors (tenant_id, guarantor_id, relationship, years_known, consented) VALUES (?,?,?,?,?)',
                [(int)$tid, $gid, $v->d['relationship'], $v->d['years_known'], empty($_POST['consented']) ? 0 : 1]);
            if ($n) Audit::log('link', 'customers', $tc['id'], 'Linked guarantor ' . $gc['full_name'] . ' to tenant ' . $tc['full_name']);
            db()->commit();
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            throw $e;
        }
        flash($n ? 'success' : 'warning', $n ? $gc['full_name'] . ' linked as guarantor.' : 'That person is already a guarantor for this tenant.');
        redirect('customers/view', ['id' => $tc['id']]);
    }

    public function actionUnlinkGuarantor(): void
    {
        $this->post();
        Auth::require('tenants.manage');
        $l = db_row('SELECT tg.id, t.customer_id tcid, gc.full_name gname, tc.full_name tname FROM tenant_guarantors tg
            JOIN tenants t ON t.id = tg.tenant_id JOIN customers tc ON tc.id = t.customer_id
            JOIN guarantors g ON g.id = tg.guarantor_id JOIN customers gc ON gc.id = g.customer_id WHERE tg.id = ?', [$this->int('id', 'POST')]);
        if (!$l) http_error(404, 'That guarantor link no longer exists.');
        db_exec('DELETE FROM tenant_guarantors WHERE id = ?', [(int)$l['id']]);
        Audit::log('unlink', 'customers', $l['tcid'], 'Removed guarantor ' . $l['gname'] . ' from tenant ' . $l['tname']);
        flash('success', 'Guarantor link removed.');
        redirect('customers/view', ['id' => $l['tcid']]);
    }

    /* ---------- JSON lookup for autocomplete ---------- */
    public function actionLookup(): void
    {
        Auth::require('customers.view');
        $q = $this->str('q');
        $role = $this->str('role');
        if (mb_strlen($q) < 2) $this->json([]);
        $p = ['q' => like_escape($q)];
        $w = 'c.deleted_at IS NULL AND (c.full_name LIKE :q OR c.ref LIKE :q OR c.phone LIKE :q';
        $digits = preg_replace('/\D+/', '', $q) ?? '';
        if (strlen($digits) >= 4) { $w .= ' OR c.phone_norm LIKE :pn'; $p['pn'] = '%' . $digits . '%'; }
        $w .= ')';
        if (isset(list_roles()[$role])) { $w .= ' AND EXISTS (SELECT 1 FROM customer_roles r WHERE r.customer_id = c.id AND r.role = :role)'; $p['role'] = $role; }
        $rows = db_all("SELECT c.id, c.full_name, c.ref, c.phone FROM customers c WHERE $w ORDER BY c.full_name LIMIT 10", $p);
        $this->json(array_map(fn($r) => ['id' => (int)$r['id'], 'name' => $r['full_name'], 'meta' => trim($r['ref'] . ' · ' . ($r['phone'] ?: 'no phone'), ' ·')], $rows));
    }

    private function find(int $id, bool $includeArchived = false): array
    {
        $c = db_row('SELECT * FROM customers WHERE id = ?' . ($includeArchived ? '' : ' AND deleted_at IS NULL'), [$id]);
        if (!$c) http_error(404, 'That person could not be found. They may have been archived.');
        return $c;
    }
}
