<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../../../config/config.php';

// Initialize database connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Continue with logout even if database connection fails
}

if (isset($_SESSION['user_id'])) {
    try {
        // Log logout
        $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'logout', ?)");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        // Continue with logout even if logging fails
    }
}

// Clear session data
session_unset();
session_destroy();

// Redirect to home page
header('Location: /');
exit; 