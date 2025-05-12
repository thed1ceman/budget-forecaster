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
    header('Location: /register?error=Database connection failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /register');
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$email || !$password || !$confirmPassword) {
    header('Location: /register?error=All fields are required');
    exit;
}

if ($password !== $confirmPassword) {
    header('Location: /register?error=Passwords do not match');
    exit;
}

if (strlen($password) < 8) {
    header('Location: /register?error=Password must be at least 8 characters long');
    exit;
}

try {
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        header('Location: /register?error=Email already registered');
        exit;
    }

    // Create new user
    $passwordHash = password_hash($password, $config['security']['password_algo'], $config['security']['password_options']);
    
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
    $stmt->execute([$email, $passwordHash]);
    $userId = $pdo->lastInsertId();

    // Create default user settings
    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, current_balance, payday) VALUES (?, 0, 1)");
    $stmt->execute([$userId]);

    // Log registration
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'register', ?)");
    $stmt->execute([$userId, $_SERVER['REMOTE_ADDR']]);

    $pdo->commit();

    // Generate CSRF token and set session
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_id'] = $userId;

    header('Location: /dashboard');
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: /register?error=An error occurred during registration');
    exit;
} 