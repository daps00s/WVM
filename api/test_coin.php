<?php
//test_coin.php
header('Content-Type: application/json');

// Debug mode
$debug = isset($_GET['debug']) && $_GET['debug'] === 'true';
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Absolute path to database config
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . '/WVM/');
require_once ROOT_PATH . 'includes/db_connect.php';

// Log file for debugging
$logFile = ROOT_PATH . 'logs/transactions.log';
$logMessage = date('Y-m-d H:i:s') . " - Request received: " . file_get_contents('php://input') . "\n";
$logMessage .= date('Y-m-d H:i:s') . " - Headers: " . print_r(getallheaders(), true) . "\n";
file_put_contents($logFile, $logMessage, FILE_APPEND);

// Simple ping endpoint
if (isset($_GET['ping'])) {
    echo json_encode([
        'status' => 'success',
        'message' => 'API is working',
        'database' => isset($pdo) ? 'Connected' : 'Not connected',
        'server_time' => date('Y-m-d H:i:s'),
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'client_ip' => $_SERVER['REMOTE_ADDR']
    ], JSON_PRETTY_PRINT);
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method', ['allowed_methods' => 'POST']);
}

// Validate content type
if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') === false) {
    sendError('Content-Type must be application/json');
}

try {
    // Verify database connection
    if (!isset($pdo)) {
        sendError('Database connection not initialized');
    }

    // Verify table existence
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'transaction'");
    if ($tableCheck->rowCount() === 0) {
        sendError('Transaction table does not exist in the database');
    }

    // Verify column existence
    $columns = $pdo->query("SHOW COLUMNS FROM transaction")->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['DateAndTime', 'dispenser_id', 'amount_dispensed', 'coin_type', 'water_type'];
    foreach ($requiredColumns as $col) {
        if (!in_array($col, $columns)) {
            sendError("Missing required column in transaction table: $col");
        }
    }

    // Process POST request
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Validate JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Invalid JSON data', [
            'received' => $json,
            'json_error' => json_last_error_msg()
        ]);
    }

    // Required fields
    $required = ['coin', 'coin_type', 'machine_id', 'water_type', 'amount_dispensed'];
    foreach ($required as $field) {
        if (!isset($data[$field])) {
            sendError("Missing required field: $field", ['received_data' => $data]);
        }
    }

    // Validate data
    $coin = (float)$data['coin'];
    $coin_type = trim($data['coin_type']);
    $machine_id = (int)$data['machine_id'];
    $water_type = trim($data['water_type']);
    $amount_dispensed = (float)$data['amount_dispensed'];

    if ($coin <= 0) {
        sendError('Coin value must be positive', ['value' => $coin]);
    }
    if (!in_array($water_type, ['HOT', 'COLD'])) {
        sendError('Invalid water type', ['value' => $water_type]);
    }
    if ($amount_dispensed <= 0) {
        sendError('Amount dispensed must be positive', ['value' => $amount_dispensed]);
    }

    // Insert transaction
    $stmt = $pdo->prepare("INSERT INTO transaction 
                         (DateAndTime, dispenser_id, amount_dispensed, coin_type, water_type) 
                         VALUES (NOW(), :machine_id, :amount, :coin_type, :water_type)");
    
    $success = $stmt->execute([
        'machine_id' => $machine_id,
        'amount' => $amount_dispensed,
        'coin_type' => $coin_type,
        'water_type' => $water_type
    ]);

    if ($success) {
        $transaction_id = $pdo->lastInsertId();
        // Fixed: Combine responses into one JSON object
        $response = [
            'status' => 'success',
            'message' => 'Database connected and transaction inserted successfully',
            'transaction_id' => (string)$transaction_id,
            'inserted_data' => [
                'coin' => $coin,
                'coin_type' => $coin_type,
                'machine_id' => $machine_id,
                'water_type' => $water_type,
                'amount_dispensed' => $amount_dispensed
            ],
            'server_time' => date('Y-m-d H:i:s')
        ];
        echo json_encode($response, JSON_PRETTY_PRINT);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transaction inserted, ID: $transaction_id\n", FILE_APPEND);
    } else {
        sendError('Database operation failed', ['error_info' => $stmt->errorInfo()]);
    }
} catch (PDOException $e) {
    sendError('Database error', [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}

// FIX: Check if function exists before declaring
if (!function_exists('sendError')) {
    function sendError($message, $details = []) {
        global $logFile;
        $response = [
            'status' => 'error',
            'message' => $message,
            'server_time' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($details)) {
            $response['error_details'] = $details;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error: $message - " . print_r($details, true) . "\n", FILE_APPEND);
        exit;
    }
}
?>