<?php
// tests/mocks/get_transactions_mock.php
header('Content-Type: application/json');

// Mock version for testing
if (!isset($_SESSION['admin_logged_in'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

// Return mock data for testing
echo json_encode([
    ['transaction_id' => 1, 'amount_dispensed' => 0.5, 'machine_name' => 'Test Machine']
]);