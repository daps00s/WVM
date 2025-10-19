<?php
//get_transactions.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');
$machineId = $_GET['machine'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build query
$query = "SELECT t.*, d.Description as machine_name, l.location_name, t.water_type
          FROM transaction t
          JOIN dispenser d ON t.dispenser_id = d.dispenser_id
          JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
          JOIN location l ON dl.location_id = l.location_id
          WHERE DATE(t.DateAndTime) BETWEEN :startDate AND :endDate";
$params = ['startDate' => $startDate, 'endDate' => $endDate];

if ($machineId) {
    $query .= " AND t.dispenser_id = :machineId";
    $params['machineId'] = $machineId;
}

if ($searchTerm) {
    $query .= " AND (t.transaction_id LIKE :searchTerm1 OR l.location_name LIKE :searchTerm2 OR d.Description LIKE :searchTerm3 OR CAST(t.amount_dispensed AS CHAR) LIKE :searchTerm4 OR t.coin_type LIKE :searchTerm5 OR t.water_type LIKE :searchTerm6)";
    $params['searchTerm1'] = "%$searchTerm%";
    $params['searchTerm2'] = "%$searchTerm%";
    $params['searchTerm3'] = "%$searchTerm%";
    $params['searchTerm4'] = "%$searchTerm%";
    $params['searchTerm5'] = "%$searchTerm%";
    $params['searchTerm6'] = "%$searchTerm%";
}

$query .= " ORDER BY t.DateAndTime DESC LIMIT 100";

$transactions = $pdo->prepare($query);
$transactions->execute($params);

echo json_encode($transactions->fetchAll());
?>