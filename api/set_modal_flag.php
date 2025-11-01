<?php
// api/set_modal_flag.php
require_once '../includes/header.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['low_water_modal_shown'])) {
        $_SESSION['low_water_modal_shown'] = (bool)$input['low_water_modal_shown'];
    }
    
    if (isset($input['start_recurring_alerts'])) {
        $_SESSION['recurring_alerts_active'] = true;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Modal flags updated successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

echo json_encode([
    'success' => false,
    'message' => 'Invalid request method',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>