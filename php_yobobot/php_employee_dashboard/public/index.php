<?php

declare(strict_types=1);

use PhpEmployeeDashboard\DashboardApp;

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('html_errors', '0');
ini_set('log_errors', '1');

if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

session_start();

require_once dirname(__DIR__) . '/src/DashboardApp.php';

try {
    $app = new DashboardApp(dirname(__DIR__));
    $app->handle();
} catch (\Throwable $exception) {
    error_log('[php_employee_dashboard] ' . $exception->getMessage() . "\n" . $exception->getTraceAsString());

    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $isApiRequest = str_starts_with($requestPath, '/api/');

    if ($isApiRequest) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'error' => 'Dashboard request failed: ' . $exception->getMessage(),
        ], JSON_UNESCAPED_SLASHES);
        return;
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Dashboard Error</title></head><body>';
    echo '<h1>Dashboard Error</h1>';
    echo '<p>The employee dashboard hit an unexpected error.</p>';
    echo '<pre>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '</body></html>';
}
