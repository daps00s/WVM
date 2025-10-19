<?php
date_default_timezone_set('Asia/Manila');
$pageTitle = 'Accounting and Calibration Dashboard';
require_once 'includes/header.php';
require_once 'includes/db_connect.php';

if (!isset($conn)) {
    if (isset($db)) $conn = $db;
    elseif (isset($database)) $conn = $database;
    elseif (isset($pdo)) $conn = $pdo;
    else {
        die('<div class="bg-red-100 text-red-700 p-4 m-4 rounded-lg">Database connection not available. Please check your database configuration.</div>');
    }
}

// Fetch dispensers for dropdown
$dispensers = $conn->query("SELECT dispenser_id, Description FROM dispenser")->fetchAll(PDO::FETCH_ASSOC);
if (empty($dispensers)) {
    die('<div class="bg-red-100 text-red-700 p-4 m-4 rounded-lg">No dispensers found. Please populate the dispenser table in the database.</div>');
}

// Fetch transactions for selected dispenser and date range
$dispenser_id = isset($_GET['dispenser_id']) ? (int)$_GET['dispenser_id'] : 27;
$start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
$end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// CORRECTED: Updated expected amounts to match Arduino requirements (in liters)
$expected_amounts = ['1 Peso' => 0.25, '5 Peso' => 0.5, '10 Peso' => 1.0]; // 250ml, 500ml, 1000ml

if ($start_date > $end_date) {
    $calibration_message = '<div class="bg-red-100 text-red-700 p-4 rounded-lg">Error: Start date cannot be after end date. Please select a valid date range.</div>';
    $transactions = [];
    $calibration_transactions = [];
    $total_revenue = 0;
    $total_dispensed = 0;
    $discrepancies = [];
    $chart_labels = ['1 Peso', '5 Peso', '10 Peso'];
    $chart_values = [0, 0, 0];
    $problem_details = '';
    $action_plan = '';
    $adjustment_details = '';
} else {
    $stmt = $conn->prepare("SELECT transaction_id, amount_dispensed, DateAndTime, coin_type, water_type 
                           FROM transaction 
                           WHERE dispenser_id = ? 
                           AND DateAndTime BETWEEN ? AND ? 
                           ORDER BY DateAndTime DESC");
    $stmt->execute([$dispenser_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total revenue and dispensed amount
    $total_revenue = 0;
    $total_dispensed = 0;
    $discrepancies = [];
    $coin_values = ['1 Peso' => 1, '5 Peso' => 5, '10 Peso' => 10];

    foreach ($transactions as &$transaction) {
        $total_dispensed += $transaction['amount_dispensed'];
        $coin_value = $coin_values[$transaction['coin_type']] ?? 0;
        $total_revenue += $coin_value;
        $expected = $expected_amounts[$transaction['coin_type']] ?? 0;
        if (abs($transaction['amount_dispensed'] - $expected) > 0.01) {
            $discrepancies[] = $transaction['transaction_id'];
        }
        // Format date with full month name
        $date = new DateTime($transaction['DateAndTime']);
        $transaction['formatted_date'] = $date->format('F j, Y H:i:s');
    }
    unset($transaction);

    // Fetch dispenser description
    $stmt = $conn->prepare("SELECT Description FROM dispenser WHERE dispenser_id = ?");
    $stmt->execute([$dispenser_id]);
    $dispenser_desc = $stmt->fetch(PDO::FETCH_ASSOC)['Description'] ?? 'Unknown';

    // CALCULATE CASH RECONCILIATION (NEW ACCOUNTING MODULE)
    $expected_cash = 0;
    $coin_counts = ['1 Peso' => 0, '5 Peso' => 0, '10 Peso' => 0];
    
    foreach ($transactions as $transaction) {
        $coin_type = $transaction['coin_type'];
        $coin_value = $coin_values[$coin_type] ?? 0;
        $expected_cash += $coin_value;
        
        if (isset($coin_counts[$coin_type])) {
            $coin_counts[$coin_type]++;
        }
    }
    
    // In real system, this would come from physical coin count
    $actual_cash = $expected_cash; // Default to perfect match
    $cash_difference = $actual_cash - $expected_cash;
    
    // Process cash reconciliation form
    if (isset($_POST['reconcile_cash'])) {
        $count_1 = (int)$_POST['coins_1'];
        $count_5 = (int)$_POST['coins_5'];
        $count_10 = (int)$_POST['coins_10'];
        
        $actual_cash = ($count_1 * 1) + ($count_5 * 5) + ($count_10 * 10);
        $cash_difference = $actual_cash - $expected_cash;
        
        // Save reconciliation to database (you would implement this)
        $reconciliation_message = '<div class="mt-3 p-3 rounded-md ' . 
             ($cash_difference == 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700') . '">';
        $reconciliation_message .= '<strong>Reconciliation Result:</strong><br>';
        $reconciliation_message .= 'Expected: ₱' . number_format($expected_cash, 2) . '<br>';
        $reconciliation_message .= 'Counted: ₱' . number_format($actual_cash, 2) . '<br>';
        $reconciliation_message .= 'Difference: ₱' . number_format($cash_difference, 2) . ' ';
        $reconciliation_message .= $cash_difference > 0 ? '(Surplus)' : ($cash_difference < 0 ? '(Shortage)' : '(Perfect Match)');
        $reconciliation_message .= '</div>';
    }

    // Calibration analysis (existing code)
    $calibration_message = '<div class="text-gray-600 p-2">No transactions found for calibration analysis. Try a different date range or dispenser.</div>';
    $chart_labels = ['1 Peso', '5 Peso', '10 Peso'];
    $chart_values = [0, 0, 0];
    $problem_details = '';
    $action_plan = '';
    $adjustment_details = '';

    if (!empty($transactions)) {
        $stmt = $conn->prepare("SELECT transaction_id, amount_dispensed, coin_type 
                               FROM transaction 
                               WHERE dispenser_id = ? 
                               AND DateAndTime BETWEEN ? AND ?");
        $stmt->execute([$dispenser_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $calibration_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total_actual = 0;
        $total_expected = 0;
        $total_transactions = count($calibration_transactions);
        $correct_transactions = 0;
        $discrepancy_details = [];
        $coin_stats = [
            '1 Peso' => ['total' => 0, 'correct' => 0, 'deviation_sum' => 0],
            '5 Peso' => ['total' => 0, 'correct' => 0, 'deviation_sum' => 0],
            '10 Peso' => ['total' => 0, 'correct' => 0, 'deviation_sum' => 0]
        ];

        foreach ($calibration_transactions as $transaction) {
            $coin_type = $transaction['coin_type'];
            $actual = $transaction['amount_dispensed'];
            $expected = $expected_amounts[$coin_type] ?? 0;
            $total_actual += $actual;
            $total_expected += $expected;
            $deviation = $actual - $expected;

            $coin_stats[$coin_type]['total']++;
            $coin_stats[$coin_type]['deviation_sum'] += $deviation;
            if (abs($deviation) <= 0.01) {
                $correct_transactions++;
                $coin_stats[$coin_type]['correct']++;
            } else {
                $discrepancy_details[] = [
                    'transaction_id' => $transaction['transaction_id'],
                    'coin_type' => $coin_type,
                    'actual' => number_format($actual, 2),
                    'expected' => number_format($expected, 2),
                    'deviation' => number_format($deviation, 2)
                ];
            }
        }

        // Calculate overall accuracy and chart data
        $chart_data = [
            '1 Peso' => ['correct' => $coin_stats['1 Peso']['correct'], 'total' => $coin_stats['1 Peso']['total']],
            '5 Peso' => ['correct' => $coin_stats['5 Peso']['correct'], 'total' => $coin_stats['5 Peso']['total']],
            '10 Peso' => ['correct' => $coin_stats['10 Peso']['correct'], 'total' => $coin_stats['10 Peso']['total']]
        ];
        $chart_values = array_map(function($data) {
            return $data['total'] > 0 ? ($data['correct'] / $data['total']) * 100 : 0;
        }, $chart_data);

        if ($total_transactions > 0) {
            $accuracy = ($correct_transactions / $total_transactions) * 100;
            $status = $accuracy < 90 ? 'text-red-600' : 'text-green-600';
            $calibration_message = '<div class="' . $status . ' p-2 font-medium">Calibration Accuracy: ' . number_format($accuracy, 2) . '% (' . $correct_transactions . '/' . $total_transactions . ' transactions correct)</div>';

            // Problem details
            if ($accuracy < 90) {
                $problem_details = '<h3 class="text-lg font-semibold mt-4">Identified Problems</h3>';
                $problem_details .= '<ul class="list-disc pl-5 text-gray-700">';
                foreach ($coin_stats as $coin_type => $stats) {
                    if ($stats['total'] > 0) {
                        $coin_accuracy = ($stats['correct'] / $stats['total']) * 100;
                        if ($coin_accuracy < 90) {
                            $avg_deviation = $stats['deviation_sum'] / $stats['total'];
                            $problem_details .= '<li>' . htmlspecialchars($coin_type) . ': ' . number_format($coin_accuracy, 2) . '% accuracy, average deviation ' . number_format($avg_deviation, 2) . ' liters</li>';
                        }
                    }
                }
                $problem_details .= '</ul>';

                // Action plan
                $action_plan = '<h3 class="text-lg font-semibold mt-4">Action Plan</h3>';
                $action_plan .= '<ol class="list-decimal pl-5 text-gray-700">';
                $action_plan .= '<li>Check if Arduino is sending correct amounts: 1 Peso=0.25L, 5 Peso=0.5L, 10 Peso=1.0L</li>';
                $action_plan .= '<li>Calibrate servo timing for ' . htmlspecialchars($dispenser_desc) . ' (ID: ' . $dispenser_id . ')</li>';
                $action_plan .= '<li>Measure actual water output with measuring cup for each coin type</li>';
                $action_plan .= '<li>Adjust HOT_1_PESO_TIME, HOT_5_PESO_TIME, etc. in Arduino code</li>';
                $action_plan .= '<li>Run 5 test transactions per coin type and re-check calibration</li>';
                $action_plan .= '</ol>';

                // Adjustment details
                $adjustment_details = '<h3 class="text-lg font-semibold mt-4">Servo Timing Adjustments Needed</h3>';
                $adjustment_details .= '<ul class="list-disc pl-5 text-gray-700">';
                foreach ($coin_stats as $coin_type => $stats) {
                    if ($stats['total'] > 0) {
                        $avg_deviation = $stats['deviation_sum'] / $stats['total'];
                        if (abs($avg_deviation) > 0.01) {
                            $percentage_error = ($avg_deviation / $expected_amounts[$coin_type]) * 100;
                            $adjustment_details .= '<li>' . htmlspecialchars($coin_type) . ': ' . 
                                ($avg_deviation > 0 ? 'Decrease' : 'Increase') . ' servo time by ' . 
                                number_format(abs($percentage_error), 1) . '%</li>';
                        }
                    }
                }
                $adjustment_details .= '</ul>';

                // Discrepancy details table
                if (!empty($discrepancy_details)) {
                    $problem_details .= '<h3 class="text-lg font-semibold mt-4">Discrepant Transactions</h3>';
                    $problem_details .= '<div class="overflow-x-auto"><table class="w-full border-collapse mt-2 text-sm">';
                    $problem_details .= '<thead><tr class="bg-gray-200"><th class="p-2 border">Transaction ID</th><th class="p-2 border">Coin Type</th><th class="p-2 border">Actual (liters)</th><th class="p-2 border">Expected (liters)</th><th class="p-2 border">Deviation (liters)</th></tr></thead>';
                    $problem_details .= '<tbody>';
                    foreach ($discrepancy_details as $detail) {
                        $problem_details .= '<tr class="discrepancy"><td class="p-2 border">' . $detail['transaction_id'] . '</td>';
                        $problem_details .= '<td class="p-2 border">' . htmlspecialchars($detail['coin_type']) . '</td>';
                        $problem_details .= '<td class="p-2 border">' . $detail['actual'] . '</td>';
                        $problem_details .= '<td class="p-2 border">' . $detail['expected'] . '</td>';
                        $problem_details .= '<td class="p-2 border">' . $detail['deviation'] . '</td></tr>';
                    }
                    $problem_details .= '</tbody></table></div>';
                }
            } else {
                $calibration_message .= '<div class="text-green-600 p-2 font-medium">No significant calibration issues detected. Continue regular monitoring.</div>';
            }
        }
    }
}
?>

<link rel="stylesheet" href="assets/css/accounting.css">
<style>
/* Accounting and Calibration Specific Styles */
.accounting-container {
    padding: 20px;
    margin-left: 0;
    width: 100%;
    min-height: calc(100vh - 70px);
    background-color: #f8f9fa;
}

.content-area {
    width: 100%;
    min-height: 100vh;
    background-color: #f8f9fa;
}

.content-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.content-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 25px; /* Reduced from 30px to 25px */
    flex-wrap: wrap;
    gap: 20px;
}

.content-title {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.content-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

.search-group {
    display: flex;
    align-items: center;
    gap: 10px;
}

.search-label {
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
}

#searchInput {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    min-width: 250px;
}

.rows-per-page {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rows-label {
    font-weight: 500;
    color: #64748b;
    white-space: nowrap;
}

#rowsPerPage {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    background: #4b5563;
}

.btn-danger {
    background: #ef4444;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s ease;
}

.btn-danger:hover {
    background: #dc2626;
}

/* Stat Cards Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px; /* Reduced from 30px to 25px */
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 16px;
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-content {
    flex: 1;
}

.stat-title {
    font-size: 14px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    line-height: 1;
}

.stat-change {
    font-size: 12px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
}

.stat-change.success {
    color: #059669;
}

.stat-change.warning {
    color: #d97706;
}

.stat-change.danger {
    color: #dc2626;
}

/* Stat card colors */
.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card:nth-child(4) .stat-icon {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
}

.table-wrapper {
    overflow-x: auto;
    max-height: 400px; /* Fixed height for scrollable area */
    overflow-y: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    background: #f8fafc;
    padding: 12px 16px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #e5e7eb;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #f1f5f9;
}

.data-table tbody tr:hover {
    background: #f8fafc;
}

.discrepancy { 
    background-color: #fee2e2 !important; 
}

.status-badge {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}

.status-success {
    background: #dcfce7;
    color: #166534;
}

.status-error {
    background: #fee2e2;
    color: #dc2626;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    padding: 6px 10px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-action.edit {
    background: #dbeafe;
    color: #1d4ed8;
}

.btn-action.edit:hover {
    background: #bfdbfe;
}

.btn-action.delete {
    background: #fee2e2;
    color: #dc2626;
}

.btn-action.delete:hover {
    background: #fecaca;
}

.btn-action.view {
    background: #dcfce7;
    color: #16a34a;
}

.btn-action.view:hover {
    background: #bbf7d0;
}

/* Filter Section - Moved 5px higher */
.filter-section {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 25px; /* Reduced from 30px to 25px */
    margin-top: -5px; /* Move section 5px higher */
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    align-items: end;
}

.filter-buttons {
    display: flex;
    gap: 10px;
    align-items: end;
}

.filter-buttons .btn-primary,
.filter-buttons .btn-secondary {
    flex: 1;
    min-height: 42px; /* Ensure consistent button height */
}

/* Tab Navigation */
.tab-navigation {
    display: flex;
    background-color: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    overflow-x: auto;
}
.tab-button {
    padding: 12px 24px;
    background: none;
    border: none;
    font-size: 16px;
    font-weight: 500;
    color: #6b7280;
    cursor: pointer;
    transition: all 0.3s;
    white-space: nowrap;
    border-bottom: 3px solid transparent;
}
.tab-button:hover {
    color: #3b82f6;
    background-color: #f8fafc;
}
.tab-button.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
    background-color: #f0f7ff;
}
.tab-content {
    display: none;
}
.tab-content.active {
    display: block;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1050;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.4);
}

.modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 90%;
    max-width: 500px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.modal-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e5e7eb;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5rem;
    color: #1e293b;
}

.close-modal {
    color: #6b7280;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1;
}

.close-modal:hover {
    color: #374151;
}

.modal-body {
    margin-bottom: 20px;
}

.input-group {
    margin-bottom: 15px;
}

.input-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #374151;
}

.input-group input,
.input-group select,
.input-group textarea {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 14px;
}

.input-group textarea {
    resize: vertical;
    min-height: 80px;
}

.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    padding-top: 15px;
    border-top: 1px solid #e5e7eb;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 1060;
    animation: slideIn 0.3s ease;
}

.notification-toast.success {
    background: #10b981;
}

.notification-toast.error {
    background: #ef4444;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Chart Containers */
.chart-container {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

/* Responsive design */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .content-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .content-actions {
        justify-content: stretch;
    }
    
    .search-group,
    .rows-per-page {
        flex: 1;
    }
    
    #searchInput {
        min-width: auto;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .accounting-container {
        padding: 15px;
    }
    
    .modal-content {
        margin: 20% auto;
        width: 95%;
    }
    
    .table-wrapper {
        max-height: 300px;
    }
    
    .tab-button {
        padding: 10px 16px;
        font-size: 14px;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-buttons {
        flex-direction: column;
    }
}

@media (max-width: 640px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .btn-action {
        width: 100%;
    }
    
    .table-wrapper {
        max-height: 250px;
    }
}
</style>

<div class="content-area">
    <div class="content-wrapper">
        <!-- Notification Toast -->
        <?php if (isset($reconciliation_message)): ?>
        <div class="notification-toast success">
            <?= $reconciliation_message ?>
        </div>
        <?php endif; ?>
        
        <div class="content-header">
            <h1 class="content-title">Accounting and Calibration Dashboard</h1>
            <div class="content-actions">
                <div class="search-group">
                    <label for="searchInput" class="search-label">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search transactions...">
                </div>
                <div class="rows-per-page">
                    <label for="rowsPerPage" class="rows-label">Rows per page:</label>
                    <select id="rowsPerPage">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                    </select>
                </div>
                <div>
                    <a href="transactions.php" class="btn-primary">
                        <i class="fas fa-exchange-alt"></i> <span class="btn-text">Switch to Transactions</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card" onclick="filterTable('revenue')">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value">₱<?= number_format($total_revenue, 2) ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-money-bill-wave"></i> Collected
                    </div>
                </div>
            </div>
            <div class="stat-card" onclick="filterTable('water')">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Water Dispensed</div>
                    <div class="stat-value"><?= number_format($total_dispensed, 2) ?>L</div>
                    <div class="stat-change success">
                        <i class="fas fa-water"></i> Total Volume
                    </div>
                </div>
            </div>
            <div class="stat-card" onclick="filterTable('discrepancies')">
                <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Calibration Issues</div>
                    <div class="stat-value"><?= count($discrepancies) ?></div>
                    <div class="stat-change <?= count($discrepancies) > 0 ? 'danger' : 'success' ?>">
                        <i class="fas fa-chart-line"></i> <?= count($discrepancies) > 0 ? 'Needs Attention' : 'All Good' ?>
                    </div>
                </div>
            </div>
            <div class="stat-card" onclick="filterTable('cash')">
                <div class="stat-icon"><i class="fas fa-balance-scale"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Cash Accuracy</div>
                    <div class="stat-value"><?= $cash_difference == 0 ? 'Balanced' : '₱' . number_format(abs($cash_difference), 2) ?></div>
                    <div class="stat-change <?= $cash_difference == 0 ? 'success' : ($cash_difference > 0 ? 'warning' : 'danger') ?>">
                        <i class="fas fa-calculator"></i> <?= $cash_difference == 0 ? 'Perfect Match' : ($cash_difference > 0 ? 'Surplus' : 'Shortage') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Section - Moved 5px higher -->
        <div class="filter-section">
            <h2 style="margin-bottom: 20px; color: #1e293b;">Filter Data</h2>
            <form action="" method="GET" class="filter-grid">
                <div class="input-group">
                    <label for="dispenser_id">Dispenser</label>
                    <select name="dispenser_id" id="dispenser_id">
                        <?php foreach ($dispensers as $dispenser): ?>
                            <option value="<?= $dispenser['dispenser_id'] ?>"
                                    <?= $dispenser['dispenser_id'] == $dispenser_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dispenser['Description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>">
                </div>
                <div class="input-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <button type="button" onclick="window.location.href='?dispenser_id=27&start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>'" 
                            class="btn-secondary">Reset</button>
                </div>
            </form>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button active" data-tab="overview">Overview</button>
            <button class="tab-button" data-tab="accounting">Cash Accounting</button>
            <button class="tab-button" data-tab="calibration">Machine Calibration</button>
            <button class="tab-button" data-tab="analytics">Analytics</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <!-- Recent Transactions - Limited to 5 visible rows, scrollable for more -->
            <div class="table-container">
                <h2 style="padding: 20px 20px 0; margin: 0; color: #1e293b;">Recent Transactions</h2>
                <p style="padding: 0 20px; margin: 5px 0 15px; color: #6b7280; font-size: 14px;">
                    Showing <?= min(5, count($transactions)) ?> of <?= count($transactions) ?> transactions. Scroll to see more.
                </p>
                <div class="table-wrapper">
                    <table class="data-table" id="transactionsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Date & Time</th>
                                <th>Coin Type</th>
                                <th>Amount (₱)</th>
                                <th>Dispensed (L)</th>
                                <th>Expected (L)</th>
                                <th>Water Type</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $display_count = 0;
                            foreach ($transactions as $transaction): 
                                $coin_value = $coin_values[$transaction['coin_type']] ?? 0;
                                $expected = $expected_amounts[$transaction['coin_type']] ?? 0;
                                $is_discrepancy = in_array($transaction['transaction_id'], $discrepancies);
                                $display_count++;
                            ?>
                                <tr class="<?= $is_discrepancy ? 'discrepancy' : '' ?>" 
                                    style="<?= $display_count > 5 ? '' : '' ?>">
                                    <td><?= $transaction['transaction_id'] ?></td>
                                    <td><?= htmlspecialchars($transaction['formatted_date']) ?></td>
                                    <td><?= htmlspecialchars($transaction['coin_type']) ?></td>
                                    <td>₱<?= number_format($coin_value, 2) ?></td>
                                    <td><?= number_format($transaction['amount_dispensed'], 2) ?></td>
                                    <td><?= number_format($expected, 2) ?></td>
                                    <td><?= htmlspecialchars($transaction['water_type']) ?></td>
                                    <td>
                                        <span class="status-badge <?= $is_discrepancy ? 'status-error' : 'status-success' ?>">
                                            <?= $is_discrepancy ? 'Calibration Issue' : 'Normal' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-action view" onclick="showTransactionDetails(<?= $transaction['transaction_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 20px; color: #6b7280;">
                                        No transactions found for the selected criteria.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Calibration Overview -->
            <div class="chart-container">
                <h2 style="margin-bottom: 20px; color: #1e293b;">Calibration Overview</h2>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <?= $calibration_message ?>
                        <?= $problem_details ?>
                    </div>
                    <div>
                        <canvas id="calibrationChart" style="width: 100%; height: 300px;"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounting Tab -->
        <div id="accounting" class="tab-content">
            <div class="chart-container">
                <h2 style="margin-bottom: 20px; color: #1e293b;">Cash Accounting Module</h2>
                
                <!-- Cash Reconciliation -->
                <div style="margin-bottom: 30px;">
                    <h3 style="margin-bottom: 15px; color: #374151;">Cash Reconciliation</h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                        <div style="background: #dbeafe; padding: 20px; border-radius: 8px;">
                            <h4 style="font-size: 14px; color: #1e40af; margin-bottom: 10px;">Expected Cash (Transactions)</h4>
                            <p style="font-size: 24px; font-weight: bold; color: #1e40af;">₱<?= number_format($expected_cash, 2) ?></p>
                            <p style="font-size: 12px; color: #4b5563; margin-top: 10px;">
                                1 Peso: <?= $coin_counts['1 Peso'] ?> coins<br>
                                5 Peso: <?= $coin_counts['5 Peso'] ?> coins<br>
                                10 Peso: <?= $coin_counts['10 Peso'] ?> coins
                            </p>
                        </div>
                        <div style="background: #dcfce7; padding: 20px; border-radius: 8px;">
                            <h4 style="font-size: 14px; color: #166534; margin-bottom: 10px;">Actual Cash Collected</h4>
                            <p style="font-size: 24px; font-weight: bold; color: #166534;">₱<?= number_format($actual_cash, 2) ?></p>
                        </div>
                        <div style="background: <?= $cash_difference == 0 ? '#dcfce7' : '#fef2f2' ?>; padding: 20px; border-radius: 8px;">
                            <h4 style="font-size: 14px; color: <?= $cash_difference == 0 ? '#166534' : '#dc2626' ?>; margin-bottom: 10px;">Cash Difference</h4>
                            <p style="font-size: 24px; font-weight: bold; color: <?= $cash_difference == 0 ? '#166534' : '#dc2626' ?>;">
                                ₱<?= number_format(abs($cash_difference), 2) ?>
                            </p>
                            <p style="font-size: 12px; color: <?= $cash_difference == 0 ? '#166534' : '#dc2626' ?>; margin-top: 10px;">
                                <?= $cash_difference > 0 ? 'Surplus' : ($cash_difference < 0 ? 'Shortage' : 'Balanced') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Cash Collection Form -->
                    <div style="background: #f8fafc; padding: 20px; border-radius: 8px;">
                        <h4 style="font-size: 14px; color: #374151; margin-bottom: 15px;">Record Physical Cash Count</h4>
                        <form method="POST" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                            <div class="input-group">
                                <label style="font-size: 12px;">1 Peso Coins</label>
                                <input type="number" name="coins_1" value="<?= $coin_counts['1 Peso'] ?>" style="padding: 8px;">
                            </div>
                            <div class="input-group">
                                <label style="font-size: 12px;">5 Peso Coins</label>
                                <input type="number" name="coins_5" value="<?= $coin_counts['5 Peso'] ?>" style="padding: 8px;">
                            </div>
                            <div class="input-group">
                                <label style="font-size: 12px;">10 Peso Coins</label>
                                <input type="number" name="coins_10" value="<?= $coin_counts['10 Peso'] ?>" style="padding: 8px;">
                            </div>
                            <div style="display: flex; align-items: end;">
                                <button type="submit" name="reconcile_cash" class="btn-primary" style="width: 100%;">
                                    Reconcile Cash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Revenue Analytics -->
                <div>
                    <h3 style="margin-bottom: 15px; color: #374151;">Revenue Analytics</h3>
                    <canvas id="revenueChart" style="width: 100%; height: 300px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Calibration Tab -->
        <div id="calibration" class="tab-content">
            <div class="chart-container">
                <h2 style="margin-bottom: 20px; color: #1e293b;">Machine Calibration Module</h2>
                
                <?= $action_plan ?>
                <?= $adjustment_details ?>
                
                <!-- Expected vs Actual -->
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px; color: #374151;">Expected vs Actual Dispensing</h3>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <h4 style="font-size: 14px; color: #374151; margin-bottom: 10px;">1 Peso Coin</h4>
                            <p style="font-size: 16px; font-weight: bold; color: #3b82f6;">Target: 0.25L (250ml)</p>
                            <p style="font-size: 12px; color: #6b7280;">Check Arduino: amountDispensed = 0.25</p>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <h4 style="font-size: 14px; color: #374151; margin-bottom: 10px;">5 Peso Coin</h4>
                            <p style="font-size: 16px; font-weight: bold; color: #3b82f6;">Target: 0.5L (500ml)</p>
                            <p style="font-size: 12px; color: #6b7280;">Check Arduino: amountDispensed = 0.50</p>
                        </div>
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e5e7eb;">
                            <h4 style="font-size: 14px; color: #374151; margin-bottom: 10px;">10 Peso Coin</h4>
                            <p style="font-size: 16px; font-weight: bold; color: #3b82f6;">Target: 1.0L (1000ml)</p>
                            <p style="font-size: 12px; color: #6b7280;">Check Arduino: amountDispensed = 1.00</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <div class="chart-container">
                <h2 style="margin-bottom: 20px; color: #1e293b;">Analytics & Reporting</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h3 style="margin-bottom: 15px; color: #374151;">Coin Distribution</h3>
                        <canvas id="coinDistributionChart" style="width: 100%; height: 300px;"></canvas>
                    </div>
                    <div>
                        <h3 style="margin-bottom: 15px; color: #374151;">Performance Metrics</h3>
                        <div style="display: grid; gap: 15px;">
                            <div style="background: #f0f9ff; padding: 15px; border-radius: 8px;">
                                <h4 style="font-size: 14px; color: #0369a1; margin-bottom: 5px;">Average Transaction Value</h4>
                                <p style="font-size: 20px; font-weight: bold; color: #0369a1;">
                                    ₱<?= !empty($transactions) ? number_format($total_revenue / count($transactions), 2) : '0.00' ?>
                                </p>
                            </div>
                            <div style="background: #f0fdf4; padding: 15px; border-radius: 8px;">
                                <h4 style="font-size: 14px; color: #166534; margin-bottom: 5px;">Total Transactions</h4>
                                <p style="font-size: 20px; font-weight: bold; color: #166534;"><?= count($transactions) ?></p>
                            </div>
                            <div style="background: #faf5ff; padding: 15px; border-radius: 8px;">
                                <h4 style="font-size: 14px; color: #7c3aed; margin-bottom: 5px;">Accuracy Rate</h4>
                                <p style="font-size: 20px; font-weight: bold; color: #7c3aed;">
                                    <?= isset($accuracy) ? number_format($accuracy, 1) . '%' : 'N/A' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal" id="transactionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Transaction Details</h2>
            <span class="close-modal">×</span>
        </div>
        <div class="modal-body">
            <div id="transactionDetails"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('transactionModal')">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Tab Navigation
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons and contents
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Add active class to clicked button and corresponding content
        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        document.getElementById(tabId).classList.add('active');
    });
});

// State management for table
let currentPage = 1;
let rowsPerPage = 25;
let searchTerm = '';

function filterAndPaginate() {
    const rows = document.querySelectorAll('#transactionsTable tbody tr');
    const filteredRows = [];
    
    rows.forEach(row => {
        const text = Array.from(row.cells).map(cell => cell.textContent.toLowerCase()).join(' ');
        if (text.includes(searchTerm.toLowerCase())) {
            filteredRows.push(row);
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    const totalRows = filteredRows.length;
    const totalPages = Math.ceil(totalRows / rowsPerPage);
    currentPage = Math.min(currentPage, Math.max(1, totalPages));
    
    filteredRows.forEach((row, index) => {
        const start = (currentPage - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        row.style.display = (index >= start && index < end) ? '' : 'none';
    });
    
    updatePagination(totalPages);
}

function updatePagination(totalPages) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    const prevButton = document.createElement('button');
    prevButton.textContent = 'Previous';
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            filterAndPaginate();
        }
    });
    pagination.appendChild(prevButton);
    
    for (let i = 1; i <= totalPages; i++) {
        const pageButton = document.createElement('button');
        pageButton.textContent = i;
        pageButton.className = i === currentPage ? 'active' : '';
        pageButton.addEventListener('click', () => {
            currentPage = i;
            filterAndPaginate();
        });
        pagination.appendChild(pageButton);
    }
    
    const nextButton = document.createElement('button');
    nextButton.textContent = 'Next';
    nextButton.disabled = currentPage === totalPages || totalPages === 0;
    nextButton.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            filterAndPaginate();
        }
    });
    pagination.appendChild(nextButton);
}

function filterTable(filterType) {
    const rows = document.querySelectorAll('#transactionsTable tbody tr');
    
    switch(filterType) {
        case 'discrepancies':
            rows.forEach(row => {
                const isDiscrepancy = row.classList.contains('discrepancy');
                row.style.display = isDiscrepancy ? '' : 'none';
            });
            break;
        case 'revenue':
            // Show all revenue-related transactions
            rows.forEach(row => row.style.display = '');
            break;
        case 'water':
            // Show all water dispensing transactions
            rows.forEach(row => row.style.display = '');
            break;
        case 'cash':
            // Show cash-related transactions
            rows.forEach(row => row.style.display = '');
            break;
        default:
            rows.forEach(row => row.style.display = '');
    }
    
    document.getElementById('searchInput').value = '';
    searchTerm = '';
    currentPage = 1;
    filterAndPaginate();
}

function showTransactionDetails(transactionId) {
    // In a real implementation, you would fetch transaction details via AJAX
    const modal = document.getElementById('transactionModal');
    const details = document.getElementById('transactionDetails');
    
    details.innerHTML = `
        <p><strong>Transaction ID:</strong> ${transactionId}</p>
        <p><strong>Details would be loaded via AJAX in a real implementation</strong></p>
        <p>This would show complete transaction information including timestamps, amounts, and any calibration issues.</p>
    `;
    
    modal.style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    filterAndPaginate();
    
    document.getElementById('searchInput').addEventListener('input', function() {
        searchTerm = this.value;
        currentPage = 1;
        filterAndPaginate();
    });
    
    document.getElementById('rowsPerPage').addEventListener('change', function() {
        rowsPerPage = parseInt(this.value);
        currentPage = 1;
        filterAndPaginate();
    });
    
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.modal').style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    });

    // Initialize charts
    initCharts();
});

function initCharts() {
    // Calibration Chart
    const calibrationCtx = document.getElementById('calibrationChart').getContext('2d');
    new Chart(calibrationCtx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: 'Calibration Accuracy (%)',
                data: <?= json_encode($chart_values) ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#ef4444'],
                borderColor: ['#1d4ed8', '#059669', '#dc2626'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Accuracy (%)' }
                }
            }
        }
    });

    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Revenue (₱)',
                data: [1200, 1900, 1500, 2000, 1800, 2200, 1700],
                borderColor: '#3b82f6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Coin Distribution Chart
    const coinCtx = document.getElementById('coinDistributionChart').getContext('2d');
    <?php
    $coin_counts = ['1 Peso' => 0, '5 Peso' => 0, '10 Peso' => 0];
    foreach ($transactions as $transaction) {
        $coin_type = $transaction['coin_type'];
        if (isset($coin_counts[$coin_type])) {
            $coin_counts[$coin_type]++;
        }
    }
    ?>
    new Chart(coinCtx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($coin_counts)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($coin_counts)) ?>,
                backgroundColor: ['#3b82f6', '#10b981', '#ef4444'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>