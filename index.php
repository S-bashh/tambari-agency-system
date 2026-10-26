<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (!is_file(__DIR__ . '/config/config.php')) {
    header('Location: ' . base_path() . '/install/');
    exit;
}

set_exception_handler(function (Throwable $e): void {
    app_log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $isDb = $e instanceof PDOException && in_array((int)($e->errorInfo[1] ?? 0), [1045, 1049, 2002, 2006], true) || ($e instanceof PDOException && str_contains($e->getMessage(), 'SQLSTATE[HY000] [20'));
    $msg = $isDb
        ? 'The system could not connect to its database. Make sure MySQL is running in the XAMPP Control Panel, then refresh this page.'
        : 'An unexpected error occurred and has been logged. Please try again, or contact your administrator if it keeps happening.';
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error</title>'
        . '<body style="font-family:system-ui,sans-serif;background:#f4f6f9;display:grid;place-items:center;min-height:100vh;margin:0">'
        . '<div style="background:#fff;border-radius:14px;padding:2rem 2.5rem;max-width:480px;box-shadow:0 8px 30px rgba(0,0,0,.08)">'
        . '<h2 style="margin:0 0 .5rem;color:#12263f">Something went wrong</h2><p style="color:#5b6b7e;line-height:1.55">' . htmlspecialchars($msg) . '</p></div></body>';
    exit;
});

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
header('Cache-Control: no-store');

Auth::startSession();

$routes = [
    'auth' => 'AuthController', 'account' => 'AccountController', 'dashboard' => 'DashboardController',
    'customers' => 'CustomersController', 'properties' => 'PropertiesController', 'documents' => 'DocumentsController',
    'media' => 'MediaController', 'search' => 'SearchController', 'users' => 'UsersController', 'roles' => 'RolesController',
    'audit' => 'AuditController', 'settings' => 'SettingsController', 'tenancies' => 'TenanciesController', 'payments' => 'PaymentsController',
    'receipts' => 'ReceiptsController', 'agreements' => 'AgreementsController', 'sales' => 'SalesController', 'land' => 'LandController',
    'reports' => 'ReportsController', 'backups' => 'BackupsController', 'demo' => 'DemoController',
    'tenants' => ['RoleListController', 'tenant'], 'landlords' => ['RoleListController', 'landlord'], 'guarantors' => ['RoleListController', 'guarantor'],
];

$route = (string)($_GET['r'] ?? 'dashboard');
if (!preg_match('#^[a-z]+(/[a-z][a-z0-9-]*)?$#', $route)) {
    http_error(404, 'That page does not exist.');
}
[$module, $action] = array_pad(explode('/', $route, 2), 2, 'index');

// New program files copied over an old database: guide the administrator to the upgrade page instead of crashing.
if (Migrator::pending(db())) {
    $isLoginRoute = ($_GET['r'] ?? '') === 'auth/login' || (($_GET['r'] ?? '') === 'auth/logout');
    if (Auth::check()) {
        if (Auth::user()['role_slug'] === 'admin') {
            redirect_url(base_path() . '/install/upgrade.php');
        }
        http_error(503, 'The system has just been updated and an administrator must finish the upgrade before it can be used. Please try again shortly.');
    } elseif (!$isLoginRoute && ($_GET['r'] ?? '') !== 'media/logo') {
        redirect('auth/login');
    }
}

$public = ['auth/login', 'media/logo'];
if (!in_array("$module/$action", $public, true)) {
    if (!Auth::check()) {
        redirect('auth/login');
    }
    if ((int)Auth::user()['must_change_password'] === 1 && !in_array("$module/$action", ['account/password', 'auth/logout'], true)) {
        redirect('account/password');
    }
}

if (!isset($routes[$module])) {
    http_error(404, 'That page does not exist.');
}
[$class, $arg] = is_array($routes[$module]) ? $routes[$module] : [$routes[$module], null];

spl_autoload_register(function (string $c): void {
    $f = TEAMS_ROOT . '/app/controllers/' . $c . '.php';
    if (is_file($f)) require $f;
});

$method = 'action' . str_replace(' ', '', ucwords(str_replace('-', ' ', $action)));
if (!class_exists($class) || !method_exists($class, $method)) {
    http_error(404, 'That page does not exist.');
}
$ctl = $arg === null ? new $class() : new $class($arg);
$ctl->$method();
