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
    header('Location: /login?error=Database connection failed');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /login');
    exit;
}

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: /login?error=Invalid email or password');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        header('Location: /login?error=Invalid email or password');
        exit;
    }

    // Generate CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['user_id'] = $user['id'];

    // Log successful login
    $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address) VALUES (?, 'login', ?)");
    $stmt->execute([$user['id'], $_SERVER['REMOTE_ADDR']]);

    header('Location: /dashboard');
    exit;
} catch (PDOException $e) {
    header('Location: /login?error=An error occurred during login');
    exit;
} 