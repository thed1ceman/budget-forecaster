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
$name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
$dueDate = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_STRING);
$frequency = filter_input(INPUT_POST, 'frequency', FILTER_SANITIZE_STRING);

if (!$name || strlen($name) > 255) {
    header('Location: /dashboard?error=Invalid payment name');
    exit;
}

if ($amount === false || $amount <= 0) {
    header('Location: /dashboard?error=Invalid amount');
    exit;
}

if (!$dueDate || !strtotime($dueDate)) {
    header('Location: /dashboard?error=Invalid due date');
    exit;
}

if (!$frequency || !in_array($frequency, ['monthly', 'weekly', 'yearly'])) {
    header('Location: /dashboard?error=Invalid frequency');
    exit;
}

try {
    // Add new payment
    $stmt = $pdo->prepare("
        INSERT INTO payments (user_id, name, amount, due_date, frequency) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $name, $amount, $dueDate, $frequency]);

    // Log the payment addition
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, action, ip_address) 
        VALUES (?, 'add_payment', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

    header('Location: /dashboard?success=Payment added successfully');
    exit;
} catch (PDOException $e) {
    header('Location: /dashboard?error=An error occurred while adding the payment');
    exit;
} 