<?php


define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/database.php';
load_env(APP_ROOT . '/.env');

// ---- Error handling: log technical details, never show them to users ----
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
date_default_timezone_set('Asia/Manila');

// ---- Base URL (works whether the app is in /agri-system/ or the web root) ----
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$appRoot = realpath(APP_ROOT);
$base = '';
if ($docRoot && $appRoot && strpos($appRoot, $docRoot) === 0) {
    $base = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
}
define('BASE_URL', rtrim($base, '/'));

// ---- Secure session cookie ----
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_name('AGRISESSID');
session_start();

require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/validator.php';
require_once APP_ROOT . '/includes/forms.php';
require_once APP_ROOT . '/includes/layout.php';

// ---- Any uncaught error -> friendly page (details go to the PHP error log) ----
set_exception_handler(function (Throwable $ex) {
    error_log('[AgriSystem] ' . get_class($ex) . ': ' . $ex->getMessage()
        . ' in ' . $ex->getFile() . ':' . $ex->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    $debug = env('APP_DEBUG', '0') === '1';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>System Error</title>'
       . '<link rel="stylesheet" href="' . htmlspecialchars(BASE_URL) . '/assets/vendor/bootstrap/bootstrap.min.css">'
       . '</head><body class="bg-light"><div class="container py-5"><div class="card shadow-sm mx-auto" style="max-width:560px">'
       . '<div class="card-body text-center p-5"><h1 class="h4 mb-3">Something went wrong</h1>'
       . '<p class="text-muted">The system could not complete your request. '
       . 'Please try again, or contact the system administrator if the problem continues.</p>'
       . ($debug ? '<pre class="text-start small text-danger">' . htmlspecialchars($ex->getMessage()) . '</pre>' : '')
       . '<a class="btn btn-success" href="' . htmlspecialchars(BASE_URL . '/dashboard.php') . '">Back to Dashboard</a>'
       . '</div></div></div></body></html>';
    exit;
});
