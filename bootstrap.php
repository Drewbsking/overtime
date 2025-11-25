<?php

define('BASE_PATH', __DIR__);

// Load Composer autoload if present
$autoload = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
} else {
    // Keep going so the app can show a friendly message about installing deps
}

// Load environment variables
if (class_exists(Dotenv\Dotenv::class)) {
    $dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
} elseif (file_exists(BASE_PATH . '/.env')) {
    // Minimal fallback parser
    $lines = file(BASE_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

// Core helpers
require_once BASE_PATH . '/src/helpers.php';

// Error handling
$displayErrors = filter_var(env('DISPLAY_ERRORS', false), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $displayErrors ? '1' : '0');
ini_set('log_errors', '1');
if (!file_exists(BASE_PATH . '/storage/logs')) {
    mkdir(BASE_PATH . '/storage/logs', 0775, true);
}
ini_set('error_log', BASE_PATH . '/storage/logs/app.log');
error_reporting(E_ALL);

date_default_timezone_set('UTC');

// Secure session cookie params
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once BASE_PATH . '/src/db.php';
require_once BASE_PATH . '/src/auth.php';
require_once BASE_PATH . '/src/mailer.php';
require_once BASE_PATH . '/src/overtime.php';

// Simple dependency check to surface missing vendor
if (!class_exists(PHPMailer\PHPMailer\PHPMailer::class)) {
    $_SESSION['flash_missing_vendor'] = 'Install Composer dependencies before using email features.';
}

// Optional equalization loader (Excel)
if (!class_exists(EqualizationSheet::class) && file_exists(BASE_PATH . '/src/equalization_sheet.php')) {
    require_once BASE_PATH . '/src/equalization_sheet.php';
}
