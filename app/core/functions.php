<?php
declare(strict_types=1);

/* ---------- configuration ---------- */
function cfg(string $key, mixed $default = null): mixed
{
    static $c = null;
    if ($c === null) {
        $f = TEAMS_ROOT . '/config/config.php';
        $c = is_file($f) ? (require $f) : [];
    }
    return $c[$key] ?? $default;
}

/* ---------- database ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $d = cfg('db');
    if (!is_array($d)) {
        throw new RuntimeException('Database is not configured.');
    }
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $d['host'], (int)($d['port'] ?? 3306), $d['name']);
    $pdo = new PDO($dsn, $d['user'], $d['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET time_zone = '+01:00'"); // Nigeria (WAT) has no daylight saving
    return $pdo;
}

function db_run(string $sql, array $params = []): PDOStatement
{
    // Real (non-emulated) prepared statements forbid repeating a named placeholder,
    // so repeated names are transparently given unique aliases.
    if ($params && !array_is_list($params)) {
        $seen = [];
        $sql = preg_replace_callback('/(?<![:\w]):([A-Za-z_][A-Za-z0-9_]*)/', function ($m) use (&$seen, &$params) {
            $n = $m[1];
            $seen[$n] = ($seen[$n] ?? 0) + 1;
            if ($seen[$n] === 1) return $m[0];
            $alias = $n . '__' . $seen[$n];
            $params[$alias] = $params[$n] ?? $params[':' . $n] ?? null;
            return ':' . $alias;
        }, $sql);
    }
    $st = db()->prepare($sql);
    foreach ($params as $k => $v) {
        $type = match (true) {
            is_int($v)  => PDO::PARAM_INT,
            is_bool($v) => PDO::PARAM_BOOL,
            $v === null => PDO::PARAM_NULL,
            default     => PDO::PARAM_STR,
        };
        $st->bindValue(is_int($k) ? $k + 1 : ':' . ltrim((string)$k, ':'), $v, $type);
    }
    $st->execute();
    return $st;
}
function db_all(string $sql, array $p = []): array { return db_run($sql, $p)->fetchAll(); }
function db_row(string $sql, array $p = []): ?array { $r = db_run($sql, $p)->fetch(); return $r === false ? null : $r; }
function db_val(string $sql, array $p = []): mixed { $r = db_run($sql, $p)->fetchColumn(); return $r === false ? null : $r; }
function db_exec(string $sql, array $p = []): int { return db_run($sql, $p)->rowCount(); }
function db_id(): int { return (int)db()->lastInsertId(); }

/** Atomic, gap-tolerant counter (safe under concurrent requests). */
function next_sequence(string $name): int
{
    db_run('INSERT INTO sequences (name, last_value) VALUES (?, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)', [$name]);
    return (int)db()->lastInsertId();
}

/* ---------- settings ---------- */
function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db_all('SELECT skey, svalue FROM settings') as $r) {
                $cache[$r['skey']] = (string)$r['svalue'];
            }
        } catch (Throwable) {
        }
    }
    return $cache[$key] ?? $default;
}
function setting_set(string $key, string $value): void
{
    db_run('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, $value]);
}

/* ---------- output / URLs ---------- */
function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    static $b = null;
    if ($b === null) {
        $s = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (str_ends_with($s, '/install')) {
            $s = substr($s, 0, -8);
        }
        $b = $s;
    }
    return $b;
}
function url(string $route = '', array $q = []): string
{
    $params = $route !== '' ? ['r' => $route] + $q : $q;
    return base_path() . '/index.php' . ($params ? '?' . http_build_query($params) : '');
}
function asset(string $path): string
{
    $f = TEAMS_ROOT . '/assets/' . $path;
    return base_path() . '/assets/' . $path . (is_file($f) ? '?v=' . filemtime($f) : '');
}
function redirect(string $route, array $q = []): never
{
    header('Location: ' . url($route, $q));
    exit;
}
function redirect_url(string $url): never
{
    header('Location: ' . $url);
    exit;
}
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45); }
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') === '443');
}

/* ---------- flash messages ---------- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- formatting ---------- */
function money(mixed $n, ?string $symbol = null): string
{
    if ($n === null || $n === '') {
        return '—';
    }
    return ($symbol ?? setting('currency_symbol', '₦')) . number_format((float)$n, 2);
}
function fmt_date(?string $d): string
{
    if (!$d || str_starts_with($d, '0000')) {
        return '—';
    }
    try {
        return (new DateTime($d))->format(setting('date_format', 'd/m/Y'));
    } catch (Throwable) {
        return '—';
    }
}
function fmt_datetime(?string $d): string
{
    if (!$d) {
        return '—';
    }
    try {
        return (new DateTime($d))->format(setting('date_format', 'd/m/Y') . ' H:i');
    } catch (Throwable) {
        return '—';
    }
}
function fmt_bytes(int $b): string
{
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    if ($b >= 1024) return round($b / 1024) . ' KB';
    return $b . ' B';
}
function time_ago(?string $d): string
{
    if (!$d) return '';
    $diff = time() - strtotime($d);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';
    return fmt_date($d);
}
function str_limit(?string $s, int $n = 60): string
{
    $s = (string)$s;
    return mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
}
function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $i = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        $i .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $i ?: '?';
}
function norm_phone(?string $p): string
{
    $d = preg_replace('/\D+/', '', (string)$p) ?? '';
    return strlen($d) >= 10 ? substr($d, -10) : $d;
}
function format_ref(string $prefix, int $n, int $pad = 5): string
{
    return $prefix . str_pad((string)$n, $pad, '0', STR_PAD_LEFT);
}
function can(string $perm): bool { return Auth::can($perm); }
function selected(mixed $a, mixed $b): string { return (string)$a === (string)$b ? ' selected' : ''; }
function checked(bool $c): string { return $c ? ' checked' : ''; }

/* ---------- pagination ---------- */
function paginate(int $total, ?int $perPage = null): array
{
    $per = $perPage ?? max(5, min(100, (int)setting('page_size', '15')));
    $pages = max(1, (int)ceil($total / $per));
    $page = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    return ['page' => $page, 'per' => $per, 'pages' => $pages, 'total' => $total, 'offset' => ($page - 1) * $per];
}
function page_url(int $page): string
{
    $q = $_GET;
    $q['page'] = $page;
    return base_path() . '/index.php?' . http_build_query($q);
}
function query_without(array $drop = ['page']): array
{
    $q = $_GET;
    foreach ($drop as $d) unset($q[$d]);
    return $q;
}

/* ---------- logging & error pages ---------- */
function app_log(string $msg): void
{
    $f = TEAMS_ROOT . '/storage/logs/app-' . date('Y-m') . '.log';
    @file_put_contents($f, '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
}
function http_error(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => 'Access denied', 404 => 'Not found', 405 => 'Method not allowed', 413 => 'Upload too large', 419 => 'Session expired', 500 => 'Something went wrong', 503 => 'Upgrade in progress'];
    $title = $titles[$code] ?? 'Error';
    $layout = Auth::check() ? 'main' : 'auth';
    View::render('errors/error', ['code' => $code, 'title' => $title, 'message' => $message, 'pageTitle' => $title, 'active' => ''], $layout);
    exit;
}

function logo_url(): string
{
    $f = setting('logo_file');
    return $f !== '' ? url('media/logo', ['v' => substr(md5($f), 0, 8)]) : '';
}
function brand_mark(): string
{
    $l = logo_url();
    return '<span class="brand-mark">' . ($l ? '<img src="' . e($l) . '" alt="">' : 'TEA') . '</span>';
}

function like_escape(string $s): string { return '%' . addcslashes($s, '%_\\') . '%'; }
function today(): string { return date('Y-m-d'); }
