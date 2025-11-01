<?php
// api/check_alert_status.php
require_once '../includes/header.php';

header('Content-Type: application/json');

$response = [
    'recurring_alerts_active' => isset($_SESSION['recurring_alerts_active']) && $_SESSION['recurring_alerts_active'],
    'low_water_modal_shown' => isset($_SESSION['low_water_modal_shown']) && $_SESSION['low_water_modal_shown'],
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($response);
?>