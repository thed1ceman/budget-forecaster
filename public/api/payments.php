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
        if (isset($pathParts[2])) {
            // Get single payment
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ? AND user_id = ?");
            $stmt->execute([$pathParts[2], $_SESSION['user_id']]);
            $payment = $stmt->fetch();
            
            if ($payment) {
                echo json_encode($payment);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Payment not found']);
            }
        } else {
            // Get all payments
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE user_id = ? ORDER BY due_day");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll());
        }
        break;

    case 'POST':
        // Add new payment
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || !isset($data['amount']) || !isset($data['due_day'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO payments (user_id, name, amount, due_day) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $data['name'],
                $data['amount'],
                $data['due_day']
            ]);
            
            echo json_encode(['id' => $pdo->lastInsertId()]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create payment']);
        }
        break;

    case 'PUT':
        if (!isset($pathParts[2])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID required']);
            exit;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $stmt = $pdo->prepare("UPDATE payments SET name = ?, amount = ?, due_day = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([
                $data['name'],
                $data['amount'],
                $data['due_day'],
                $pathParts[2],
                $_SESSION['user_id']
            ]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Payment not found']);
            } else {
                echo json_encode(['success' => true]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update payment']);
        }
        break;

    case 'DELETE':
        if (!isset($pathParts[2])) {
            http_response_code(400);
            echo json_encode(['error' => 'Payment ID required']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("UPDATE payments SET is_active = 0 WHERE id = ? AND user_id = ?");
            $stmt->execute([$pathParts[2], $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Payment not found']);
            } else {
                echo json_encode(['success' => true]);
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete payment']);
        }
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
} 