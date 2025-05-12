<?php
session_start();
require_once __DIR__ . '/../../../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../../../config/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('Location: /dashboard?error=Invalid request');
    exit;
}

// Initialize database connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    header('Location: /dashboard?error=Database connection failed');
    exit;
}

// Validate input
$currency = filter_input(INPUT_POST, 'currency', FILTER_SANITIZE_STRING);
$currentBalance = filter_input(INPUT_POST, 'current_balance', FILTER_VALIDATE_FLOAT);
$payday = filter_input(INPUT_POST, 'payday', FILTER_VALIDATE_INT);

if (!$currency || !in_array($currency, ['GBP', 'USD', 'EUR'])) {
    header('Location: /dashboard?error=Invalid currency');
    exit;
}

if ($currentBalance === false) {
    header('Location: /dashboard?error=Invalid balance amount');
    exit;
}

if (!$payday || $payday < 1 || $payday > 31) {
    header('Location: /dashboard?error=Invalid pay day');
    exit;
}

try {
    // Update user settings
    $stmt = $pdo->prepare("
        UPDATE user_settings 
        SET currency = ?, current_balance = ?, payday = ? 
        WHERE user_id = ?
    ");
    $stmt->execute([$currency, $currentBalance, $payday, $_SESSION['user_id']]);

    // Log the settings update
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, action, ip_address) 
        VALUES (?, 'update_settings', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

    header('Location: /dashboard?success=Settings updated successfully');
    exit;
} catch (PDOException $e) {
    header('Location: /dashboard?error=An error occurred while updating settings');
    exit;
} 