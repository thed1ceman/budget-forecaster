<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Set error reporting based on environment
if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Initialize database connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get the request URI and remove the base path
$request = $_SERVER['REQUEST_URI'];
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if (strpos($request, $basePath) === 0) {
    $request = substr($request, strlen($basePath));
}

// Remove query string from request
$request = parse_url($request, PHP_URL_PATH);

// Ensure request starts with a slash
if (strpos($request, '/') !== 0) {
    $request = '/' . $request;
}

// Debug logging
if ($config['app']['debug']) {
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Script Name: " . $_SERVER['SCRIPT_NAME']);
    error_log("Base Path: " . $basePath);
    error_log("Document Root: " . $_SERVER['DOCUMENT_ROOT']);
    error_log("Processed Request: " . $request);
}

// Route the request
switch ($request) {
    case '/':
    case '':
        require __DIR__ . '/../src/views/home.php';
        break;
    case '/login':
        require __DIR__ . '/../src/views/login.php';
        break;
    case '/register':
        require __DIR__ . '/../src/views/register.php';
        break;
    case '/dashboard':
        require __DIR__ . '/../src/views/dashboard.php';
        break;
    case '/logout':
        require __DIR__ . '/api/auth/logout.php';
        break;
    default:
        if ($config['app']['debug']) {
            error_log("404 Not Found: " . $request);
        }
        http_response_code(404);
        require __DIR__ . '/../src/views/404.php';
        break;
} 