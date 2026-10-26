<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $user = null;
    private static ?array $perms = null;

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = TEAMS_ROOT . '/storage/tmp';
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        session_name('TEAMS_SESSION');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', '7200');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() === '' ? '/' : base_path() . '/',
            'secure'   => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function check(): bool { return self::user() !== null; }
    public static function id(): ?int { return self::user()['id'] ?? null; }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['uid'])) {
            return null;
        }
        $u = db_row('SELECT u.*, r.slug AS role_slug, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', [(int)$_SESSION['uid']]);
        $timeout = max(5, (int)setting('session_timeout', '30')) * 60;
        if (!$u || !$u['is_active']) {
            self::destroy();
            return null;
        }
        if (time() - (int)($_SESSION['last_activity'] ?? 0) > $timeout) {
            self::destroy();
            self::startSession();
            flash('warning', 'You were signed out because of inactivity. Please sign in again.');
            return null;
        }
        $_SESSION['last_activity'] = time();
        return self::$user = $u;
    }

    private static function loadPerms(): array
    {
        if (self::$perms === null) {
            $u = self::user();
            self::$perms = $u ? array_column(db_all('SELECT p.perm_key FROM role_permissions rp JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?', [(int)$u['role_id']]), 'perm_key') : [];
        }
        return self::$perms;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) {
            return false;
        }
        return $u['role_slug'] === 'admin' || in_array($perm, self::loadPerms(), true);
    }

    public static function require(string $perm): void
    {
        if (!self::check()) {
            redirect('auth/login');
        }
        if (!self::can($perm)) {
            http_error(403, 'Your role does not have permission to do this. Ask an administrator if you need access.');
        }
    }

    /** @return array{ok:bool,error:?string} */
    public static function attempt(string $login, string $password): array
    {
        $login = trim($login);
        $u = db_row('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$login, mb_strtolower($login)]);
        $generic = 'Incorrect username or password.';

        if (!$u) {
            password_verify($password, '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG'); // equalise timing
            Audit::log('login_failed', 'auth', null, 'Failed sign-in for unknown user "' . mb_substr($login, 0, 40) . '"', null, null, mb_substr($login, 0, 40));
            return ['ok' => false, 'error' => $generic];
        }
        if ($u['locked_until'] && strtotime((string)$u['locked_until']) > time()) {
            return ['ok' => false, 'error' => 'This account is temporarily locked after too many failed attempts. Try again in a few minutes.'];
        }
        if (!password_verify($password, $u['password_hash'])) {
            $n = (int)$u['failed_attempts'] + 1;
            if ($n >= 5) {
                db_exec('UPDATE users SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?', [(int)$u['id']]);
            } else {
                db_exec('UPDATE users SET failed_attempts = ? WHERE id = ?', [$n, (int)$u['id']]);
            }
            Audit::log('login_failed', 'auth', $u['id'], 'Failed sign-in (wrong password)', null, (int)$u['id'], $u['username']);
            return ['ok' => false, 'error' => $generic];
        }
        if (!$u['is_active']) {
            return ['ok' => false, 'error' => 'This account has been disabled. Contact an administrator.'];
        }
        if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), (int)$u['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        $_SESSION['last_activity'] = time();
        self::$user = null;
        self::$perms = null;
        db_exec('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW(), last_login_ip = ? WHERE id = ?', [client_ip(), (int)$u['id']]);
        Audit::log('login', 'auth', $u['id'], 'Signed in', null, (int)$u['id'], $u['username']);
        return ['ok' => true, 'error' => null];
    }

    public static function logout(): void
    {
        if (self::check()) {
            Audit::log('logout', 'auth', self::id(), 'Signed out');
        }
        self::destroy();
    }

    private static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], true);
            }
            session_destroy();
        }
        self::$user = null;
        self::$perms = null;
    }

    public static function validPassword(string $pw): ?string
    {
        if (mb_strlen($pw) < 8) return 'Password must be at least 8 characters.';
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) return 'Password must contain at least one letter and one number.';
        if (mb_strlen($pw) > 200) return 'Password is too long.';
        return null;
    }
}
