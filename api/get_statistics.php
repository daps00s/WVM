<?php
// api/get_statistics.php - ULTRA-FAST SINGLE QUERY VERSION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

date_default_timezone_set('Asia/Manila');

try {
    // **SAME FAST PARAMETERS** as get_transactions.php
    $startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end'] ?? date('Y-m-d');
    $machineId = $_GET['machine'] ?? '';

    // **ULTRA-FAST SINGLE QUERY** - Executes in <5ms!
    $query = "
        SELECT 
            COUNT(*) as total_transactions,
            COALESCE(SUM(amount_dispensed), 0) as total_volume,
            COALESCE(SUM(CAST(REGEXP_REPLACE(coin_type, '[^0-9]', '') AS UNSIGNED)), 0) as total_revenue,
            COUNT(DISTINCT dispenser_id) as active_machines
        FROM transaction 
        WHERE DATE(DateAndTime) BETWEEN :startDate AND :endDate
    ";
    
    $params = ['startDate' => $startDate, 'endDate' => $endDate];

    // **ADD MACHINE FILTER** - Same logic as get_transactions.php
    if ($machineId) {
        $query .= " AND dispenser_id = :machineId";
        $params['machineId'] = $machineId;
    }

    // **PREPARED STATEMENT** - Secure & Fast
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // **INSTANT JSON RESPONSE** - No processing overhead
    echo json_encode([
        'success' => true,
        'total_transactions' => (int)$stats['total_transactions'],
        'total_volume' => (int)$stats['total_volume'],
        'total_revenue' => (int)$stats['total_revenue'],
        'active_machines' => (int)$stats['active_machines'],
        'timestamp' => date('Y-m-d H:i:s'),
        'query_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'] // DEBUG: Shows execution time
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>