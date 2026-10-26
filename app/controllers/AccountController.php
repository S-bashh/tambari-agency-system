<?php
declare(strict_types=1);

final class AccountController extends Controller
{
    public function actionProfile(): void
    {
        $u = Auth::user();
        $errors = [];
        $vals = $u;
        if (is_post()) {
            Csrf::verify();
            $v = new Validator($_POST);
            $v->text('full_name', 'Full name', true, 120);
            $v->email('email', 'Email', true);
            $v->phone('phone', 'Phone');
            $email = $v->d['email'] ?? null;
            if ($email && db_val('SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?', [mb_strtolower($email), (int)$u['id']])) {
                $v->fail('email', 'Another user already uses this email address.');
            }
            if ($v->ok()) {
                $old = $u;
                $d = $v->d;
                $d['email'] = mb_strtolower((string)$d['email']);
                db_exec('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?', [$d['full_name'], $d['email'], $d['phone'], (int)$u['id']]);
                Audit::log('update', 'users', $u['id'], 'Updated own profile', Audit::diff($old, $d, ['full_name', 'email', 'phone']));
                flash('success', 'Profile updated.');
                redirect('account/profile');
            }
            $errors = $v->errors;
            $vals = $_POST + $u;
        }
        form_set($vals, $errors);
        $this->view('account/profile', ['pageTitle' => 'My profile', 'active' => '', 'u' => $u]);
    }

    public function actionPassword(): void
    {
        $u = Auth::user();
        $forced = (int)$u['must_change_password'] === 1;
        $errors = [];
        if (is_post()) {
            Csrf::verify();
            $cur = (string)($_POST['current_password'] ?? '');
            $new = (string)($_POST['new_password'] ?? '');
            $conf = (string)($_POST['confirm_password'] ?? '');
            $row = db_row('SELECT password_hash FROM users WHERE id = ?', [(int)$u['id']]);
            if (!$row || !password_verify($cur, $row['password_hash'])) {
                $errors['current_password'] = 'Your current password is incorrect.';
            }
            if ($e = Auth::validPassword($new)) {
                $errors['new_password'] = $e;
            } elseif (hash_equals($cur, $new)) {
                $errors['new_password'] = 'Choose a password different from the current one.';
            }
            if ($new !== $conf) {
                $errors['confirm_password'] = 'The two passwords do not match.';
            }
            if (!$errors) {
                db_exec('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$u['id']]);
                session_regenerate_id(true);
                Audit::log('password_change', 'users', $u['id'], 'Changed own password');
                flash('success', 'Password changed successfully.');
                redirect('dashboard');
            }
        }
        form_set([], $errors);
        $this->view('account/password', ['pageTitle' => 'Change password', 'active' => '', 'forced' => $forced]);
    }
}
