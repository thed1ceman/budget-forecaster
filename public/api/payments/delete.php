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

// Get payment ID from POST data
$paymentId = filter_input(INPUT_POST, 'payment_id', FILTER_VALIDATE_INT);

if (!$paymentId) {
    header('Location: /dashboard?error=Invalid payment ID');
    exit;
}

try {
    // Delete the payment
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ? AND user_id = ?");
    $stmt->execute([$paymentId, $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        header('Location: /dashboard?error=Payment not found');
        exit;
    }

    // Log the deletion
    $stmt = $pdo->prepare("
        INSERT INTO audit_log (user_id, action, ip_address) 
        VALUES (?, 'delete_payment', ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

    header('Location: /dashboard?success=Payment deleted successfully');
    exit;
} catch (PDOException $e) {
    header('Location: /dashboard?error=An error occurred while deleting the payment');
    exit;
} 