<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../../config/config.php';

// Initialize database connection
try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathParts = explode('/', trim($path, '/'));

// Handle CSRF protection
if ($method !== 'GET') {
    $headers = getallheaders();
    if (!isset($headers['X-CSRF-Token']) || $headers['X-CSRF-Token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

// Route the request
switch ($method) {
    case 'GET':
        // Get user settings
        $stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $settings = $stmt->fetch();
        
        if ($settings) {
            echo json_encode($settings);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Settings not found']);
        }
        break;

    case 'PUT':
        if ($pathParts[2] === 'balance') {
            // Update balance
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['balance'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Balance is required']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE user_settings SET current_balance = ? WHERE user_id = ?");
                $stmt->execute([$data['balance'], $_SESSION['user_id']]);
                
                if ($stmt->rowCount() === 0) {
                    // If no settings exist, create them
                    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, current_balance, payday) VALUES (?, ?, 1)");
                    $stmt->execute([$_SESSION['user_id'], $data['balance']]);
                }
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update balance']);
            }
        } elseif ($pathParts[2] === 'payday') {
            // Update payday
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['payday']) || $data['payday'] < 1 || $data['payday'] > 31) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid payday']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE user_settings SET payday = ? WHERE user_id = ?");
                $stmt->execute([$data['payday'], $_SESSION['user_id']]);
                
                if ($stmt->rowCount() === 0) {
                    // If no settings exist, create them
                    $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, current_balance, payday) VALUES (?, 0, ?)");
                    $stmt->execute([$_SESSION['user_id'], $data['payday']]);
                }
                
                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to update payday']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid setting']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}