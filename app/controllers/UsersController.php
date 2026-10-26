<?php
declare(strict_types=1);

final class UsersController extends Controller
{
    public function actionIndex(): void
    {
        Auth::require('users.manage');
        $rows = db_all('SELECT u.*, r.name role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.is_active DESC, u.full_name');
        $this->view('users/index', ['rows' => $rows, 'pageTitle' => 'Users', 'active' => 'users']);
    }

    public function actionCreate(): void
    {
        Auth::require('users.manage');
        form_set(['role_id' => db_val("SELECT id FROM roles WHERE slug = 'agent'"), 'must_change_password' => 1], []);
        $this->view('users/form', ['u' => null, 'roles' => $this->roles(), 'pageTitle' => 'Add user', 'active' => 'users']);
    }

    public function actionEdit(): void
    {
        Auth::require('users.manage');
        $u = $this->find($this->int('id'));
        form_set($u, []);
        $this->view('users/form', ['u' => $u, 'roles' => $this->roles(), 'pageTitle' => 'Edit user', 'active' => 'users']);
    }

    public function actionSave(): void
    {
        $this->post();
        Auth::require('users.manage');
        $id = $this->int('id', 'POST');
        $u = $id ? $this->find($id) : null;
        $v = new Validator($_POST);
        $v->text('full_name', 'Full name', true, 120, 3);
        $v->email('email', 'Email', true);
        $v->phone('phone', 'Phone');
        $roleId = $v->int('role_id', 'Role', true, 1, 255);
        if ($roleId && !db_val('SELECT COUNT(*) FROM roles WHERE id = ?', [$roleId])) $v->fail('role_id', 'Choose a valid role.');
        $username = $u['username'] ?? null;
        if (!$u) {
            $username = $v->text('username', 'Username', true, 40, 3);
            if ($username !== null && !preg_match('/^[A-Za-z0-9._-]+$/', $username)) $v->fail('username', 'Use letters, numbers, dots, dashes and underscores only.');
            if ($username !== null && db_val('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) $v->fail('username', 'That username is already taken.');
        }
        $email = $v->d['email'] ?? null;
        if ($email && db_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [mb_strtolower($email), $id])) $v->fail('email', 'Another user already uses this email address.');
        $pw = (string)($_POST['password'] ?? '');
        if (!$u) {
            if ($e = Auth::validPassword($pw)) $v->fail('password', $e);
        }
        $active = empty($_POST['is_active']) ? 0 : 1;
        $slug = $roleId ? (string)db_val('SELECT slug FROM roles WHERE id = ?', [$roleId]) : '';
        if ($u) {
            if ((int)$u['id'] === Auth::id() && (!$active || (int)$u['role_id'] !== $roleId)) $v->fail('role_id', 'You cannot change your own role or disable your own account.');
            $wasAdmin = db_val('SELECT slug FROM roles WHERE id = ?', [(int)$u['role_id']]) === 'admin';
            if ($wasAdmin && $u['is_active'] && ($slug !== 'admin' || !$active) && $this->activeAdmins() <= 1) $v->fail('role_id', 'This is the last active administrator. Create another administrator first.');
        }
        if ($v->errors) {
            form_set($_POST + ($u ?? []), $v->errors);
            $this->view('users/form', ['u' => $u, 'roles' => $this->roles(), 'pageTitle' => $u ? 'Edit user' : 'Add user', 'active' => 'users']);
            return;
        }
        $must = empty($_POST['must_change_password']) ? 0 : 1;
        if (!$u) {
            db_run('INSERT INTO users (username, full_name, email, phone, password_hash, role_id, is_active, must_change_password) VALUES (?,?,?,?,?,?,?,?)',
                [$username, $v->d['full_name'], mb_strtolower((string)$email), $v->d['phone'], password_hash($pw, PASSWORD_DEFAULT), $roleId, $active, 1]);
            Audit::log('create', 'users', db_id(), "Created user $username with role $slug");
            flash('success', 'User created. They must set a new password at first sign-in.');
        } else {
            db_run('UPDATE users SET full_name = ?, email = ?, phone = ?, role_id = ?, is_active = ? WHERE id = ?',
                [$v->d['full_name'], mb_strtolower((string)$email), $v->d['phone'], $roleId, $active, $id]);
            $ch = Audit::diff($u, ['full_name' => $v->d['full_name'], 'email' => mb_strtolower((string)$email), 'phone' => $v->d['phone'], 'role_id' => $roleId, 'is_active' => $active], ['full_name', 'email', 'phone', 'role_id', 'is_active']);
            Audit::log(isset($ch['role_id']) ? 'permission_change' : 'update', 'users', $id, "Edited user {$u['username']}" . ($ch ? ' (changed: ' . implode(', ', array_keys($ch)) . ')' : ''), $ch ?: null);
            flash('success', 'User updated.');
        }
        redirect('users');
    }

    public function actionResetPassword(): void
    {
        $this->post();
        Auth::require('users.manage');
        $u = $this->find($this->int('id', 'POST'));
        $pw = (string)($_POST['password'] ?? '');
        if ($e = Auth::validPassword($pw)) {
            flash('danger', $e);
            redirect('users/edit', ['id' => $u['id']]);
        }
        db_exec('UPDATE users SET password_hash = ?, must_change_password = 1, failed_attempts = 0, locked_until = NULL WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), (int)$u['id']]);
        Audit::log('password_reset', 'users', $u['id'], "Reset password for {$u['username']}");
        flash('success', 'Temporary password set. Give it to the user privately; they must change it at next sign-in.');
        redirect('users');
    }

    public function actionUnlock(): void
    {
        $this->post();
        Auth::require('users.manage');
        $u = $this->find($this->int('id', 'POST'));
        db_exec('UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?', [(int)$u['id']]);
        Audit::log('update', 'users', $u['id'], "Unlocked account {$u['username']}");
        flash('success', 'Account unlocked.');
        redirect('users');
    }

    private function roles(): array { return db_all('SELECT id, name, slug FROM roles ORDER BY id'); }
    private function activeAdmins(): int { return (int)db_val("SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE r.slug = 'admin' AND u.is_active = 1"); }
    private function find(int $id): array
    {
        $u = db_row('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) http_error(404, 'That user could not be found.');
        return $u;
    }
}
