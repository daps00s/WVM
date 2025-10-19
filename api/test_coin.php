<?php
//test_coin.php - UPDATED WITH PROPER TIME-BASED WATER CALCULATION
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

    // Begin transaction for both inserting transaction and updating water level
    $pdo->beginTransaction();

    // STEP 1: Get machine capacity and current status
    $machineStmt = $pdo->prepare("
        SELECT d.Capacity, COALESCE(ds.last_refill_time, NOW()) as last_refill_time
        FROM dispenser d
        LEFT JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
        WHERE d.dispenser_id = ?
    ");
    $machineStmt->execute([$machine_id]);
    $machineData = $machineStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$machineData) {
        throw new Exception("Machine not found");
    }
    
    $capacity = (float)$machineData['Capacity'];
    $last_refill_time = $machineData['last_refill_time'];
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Machine {$machine_id}: Capacity={$capacity}L, Last Refill={$last_refill_time}\n", FILE_APPEND);

    // STEP 2: Calculate total water dispensed SINCE last refill
    $totalDispensedStmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_dispensed) / 1000, 0) as total_dispensed_liters
        FROM transaction 
        WHERE dispenser_id = ? 
        AND DateAndTime > ?
    ");
    $totalDispensedStmt->execute([$machine_id, $last_refill_time]);
    $total_dispensed_since_refill = (float)$totalDispensedStmt->fetchColumn();
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Total dispensed since last refill: {$total_dispensed_since_refill}L\n", FILE_APPEND);

    // STEP 3: Insert the new transaction
    $water_dispensed_liters = $amount_dispensed / 1000;
    $stmt = $pdo->prepare("INSERT INTO transaction 
                         (DateAndTime, dispenser_id, amount_dispensed, coin_type, water_type) 
                         VALUES (NOW(), :machine_id, :amount, :coin_type, :water_type)");
    
    $success = $stmt->execute([
        'machine_id' => $machine_id,
        'amount' => $amount_dispensed,
        'coin_type' => $coin_type,
        'water_type' => $water_type
    ]);

    if (!$success) {
        throw new Exception('Failed to insert transaction');
    }

    $transaction_id = $pdo->lastInsertId();

    // STEP 4: Calculate new water level (Capacity - total dispensed including new transaction)
    $new_total_dispensed = $total_dispensed_since_refill + $water_dispensed_liters;
    $new_water_level = max(0, $capacity - $new_total_dispensed);
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - New calculation: {$capacity}L - {$new_total_dispensed}L = {$new_water_level}L\n", FILE_APPEND);

    // STEP 5: Update water level in dispenserstatus
    $updateStmt = $pdo->prepare("
        UPDATE dispenserstatus 
        SET water_level = ? 
        WHERE dispenser_id = ?
    ");
    $updateStmt->execute([$new_water_level, $machine_id]);
    
    $affectedRows = $updateStmt->rowCount();
    
    // If no rows affected, insert new record
    if ($affectedRows === 0) {
        $insertStmt = $pdo->prepare("
            INSERT INTO dispenserstatus (dispenser_id, water_level, operational_status, last_refill_time)
            VALUES (?, ?, 'Normal', NOW())
        ");
        $insertStmt->execute([$machine_id, $new_water_level]);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Created new dispenserstatus record\n", FILE_APPEND);
    }

    // STEP 6: Update operational status based on new water level
    $statusStmt = $pdo->prepare("
        UPDATE dispenserstatus 
        SET operational_status = CASE 
            WHEN water_level < 1 THEN 'Critical'
            WHEN water_level < 2 THEN 'Low' 
            ELSE 'Normal'
        END
        WHERE dispenser_id = ?
    ");
    $statusStmt->execute([$machine_id]);

    // Commit all operations
    $pdo->commit();

    // Return success response
    $response = [
        'status' => 'success',
        'message' => 'Transaction recorded and water level updated successfully',
        'transaction_id' => (string)$transaction_id,
        'water_calculation' => [
            'capacity' => $capacity,
            'last_refill_time' => $last_refill_time,
            'previous_dispensed_since_refill' => $total_dispensed_since_refill,
            'this_transaction_liters' => $water_dispensed_liters,
            'new_total_dispensed' => $new_total_dispensed,
            'new_water_level' => $new_water_level
        ],
        'machine_id' => $machine_id,
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
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - SUCCESS: Transaction {$transaction_id}, Water level: {$new_water_level}L\n", FILE_APPEND);

} catch (PDOException $e) {
    $pdo->rollBack();
    sendError('Database error', [
        'error_message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    sendError('Transaction error', [
        'error_message' => $e->getMessage()
    ]);
}

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
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - ERROR: $message - " . print_r($details, true) . "\n", FILE_APPEND);
    exit;
}
?>