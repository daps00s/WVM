<?php
// api/check_alerts.php
require_once '../includes/header.php';

header('Content-Type: application/json');

try {
    // Get current alerts count
    $alerts = $pdo->query("
        SELECT COUNT(*) as alerts_count
        FROM dispenser d
        JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
        JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
        WHERE ds.water_level < 2 AND dl.Status = 1
    ")->fetch();
    
    echo json_encode([
        'alerts_count' => (int)$alerts['alerts_count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    echo json_encode([
        'alerts_count' => 0,
        'error' => 'Failed to check alerts',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>