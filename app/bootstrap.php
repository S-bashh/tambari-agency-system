<?php
declare(strict_types=1);

define('TEAMS_ROOT', dirname(__DIR__));
define('TEAMS_VERSION', '1.0.0');

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', TEAMS_ROOT . '/storage/logs/php-error.log');
date_default_timezone_set('Africa/Lagos');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/core/functions.php';
require_once __DIR__ . '/core/lists.php';
require_once __DIR__ . '/core/form.php';
require_once __DIR__ . '/core/Validator.php';
require_once __DIR__ . '/core/Csrf.php';
require_once __DIR__ . '/core/Audit.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Upload.php';
require_once __DIR__ . '/core/View.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Migrator.php';
require_once __DIR__ . '/core/Money.php';
require_once __DIR__ . '/core/Tenancy.php';
require_once __DIR__ . '/core/Pdf.php';
require_once __DIR__ . '/core/Agreement.php';
require_once __DIR__ . '/core/Receipt.php';
require_once __DIR__ . '/core/Sale.php';
require_once __DIR__ . '/core/Reports.php';
require_once __DIR__ . '/core/Backup.php';
require_once __DIR__ . '/core/Demo.php';
