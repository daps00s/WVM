<?php
//get_transactions.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth_check.php';

$startDate = $_GET['start'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate = $_GET['end'] ?? date('Y-m-d');

$transactions = $pdo->prepare("SELECT t.*, d.Description as machine_name, l.location_name
                             FROM transaction t
                             JOIN dispenser d ON t.dispenser_id = d.dispenser_id
                             JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
                             JOIN location l ON dl.location_id = l.location_id
                             WHERE DATE(t.DateAndTime) BETWEEN :startDate AND :endDate
                             ORDER BY t.DateAndTime DESC LIMIT 100");
$transactions->execute(['startDate' => $startDate, 'endDate' => $endDate]);

echo json_encode($transactions->fetchAll());