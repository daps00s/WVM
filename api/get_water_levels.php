<?php
//get_water_levels.php - API endpoint to get current water levels
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db_connect.php';

try {
    // Get water level data with machine information including last refill time
    $waterLevels = $pdo->query("
        SELECT 
            d.dispenser_id,
            d.Description as machine_name,
            d.Capacity,
            -- Ensure water level is between 0 and capacity
            GREATEST(0, LEAST(d.Capacity, COALESCE(ds.water_level, 0))) as water_level,
            COALESCE(ds.operational_status, 'Normal') as operational_status,
            l.location_name,
            COALESCE(dl.Status, 0) as machine_status,
            COALESCE(ds.last_refill_time, NOW()) as last_refill_time
        FROM dispenser d
        LEFT JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
        LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
        LEFT JOIN location l ON dl.location_id = l.location_id
        ORDER BY d.dispenser_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($waterLevels);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>