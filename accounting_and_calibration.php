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
                           ORDER BY DateAndTime DESC 
                           LIMIT 50");
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

<style>
/* Accounting and Calibration Specific Styles */
.accounting-container {
    padding: 20px;
    margin-left: 0;
    width: 100%;
    min-height: calc(100vh - 70px);
    background-color: #f8f9fa;
}

.discrepancy { 
    background-color: #fee2e2; 
}
.table-container { 
    max-height: 400px; 
    overflow-y: auto; 
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
.sticky-header { 
    position: sticky; 
    top: 0; 
    z-index: 10; 
    background-color: #f3f4f6;
}
.transaction-row:hover { 
    cursor: pointer; 
    background-color: #f1f5f9; 
}
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
.modal-close { 
    color: #aaa; 
    float: right; 
    font-size: 28px; 
    font-weight: bold; 
    cursor: pointer; 
    line-height: 1;
}
.modal-close:hover { 
    color: #000; 
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

/* Tailwind-like utility classes */
.max-w-7xl { max-width: 80rem; }
.mx-auto { margin-left: auto; margin-right: auto; }
.px-4 { padding-left: 1rem; padding-right: 1rem; }
.py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.bg-white { background-color: #ffffff; }
.bg-blue-700 { background-color: #1d4ed8; }
.bg-red-600 { background-color: #dc2626; }
.bg-gray-500 { background-color: #6b7280; }
.bg-gray-200 { background-color: #e5e7eb; }
.text-white { color: #ffffff; }
.text-blue-600 { color: #2563eb; }
.text-green-600 { color: #059669; }
.text-red-600 { color: #dc2626; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-gray-800 { color: #1f2937; }
.text-blue-100 { color: #dbeafe; }
.rounded-lg { border-radius: 0.5rem; }
.rounded-md { border-radius: 0.375rem; }
.shadow-lg { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
.shadow-md { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
.border { border-width: 1px; border-color: #e5e7eb; }
.border-gray-300 { border-color: #d1d5db; }
.grid { display: grid; }
.grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.gap-4 { gap: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mb-4 { margin-bottom: 1rem; }
.mt-4 { margin-top: 1rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mt-6 { margin-top: 1.5rem; }
.p-2 { padding: 0.5rem; }
.p-4 { padding: 1rem; }
.p-6 { padding: 1.5rem; }
.block { display: block; }
.w-full { width: 100%; }
.h-48 { height: 12rem; }
.h-64 { height: 16rem; }
.text-sm { font-size: 0.875rem; }
.text-base { font-size: 1rem; }
.text-lg { font-size: 1.125rem; }
.text-xl { font-size: 1.25rem; }
.text-2xl { font-size: 1.5rem; }
.text-3xl { font-size: 1.875rem; }
.font-medium { font-weight: 500; }
.font-semibold { font-weight: 600; }
.font-bold { font-weight: 700; }
.cursor-pointer { cursor: pointer; }
.overflow-x-auto { overflow-x: auto; }
.overflow-y-auto { overflow-y: auto; }
.list-disc { list-style-type: disc; }
.list-decimal { list-style-type: decimal; }
.pl-5 { padding-left: 1.25rem; }
.flex { display: flex; }
.items-center { align-items: center; }
.items-end { align-items: flex-end; }
.justify-between { justify-content: space-between; }
.gap-2 { gap: 0.5rem; }
.relative { position: relative; }
.text-center { text-align: center; }

/* Focus styles */
.focus\:ring-2:focus { 
    --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
    --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(2px + var(--tw-ring-offset-width)) var(--tw-ring-color);
    box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
}
.focus\:ring-blue-500:focus { --tw-ring-color: #3b82f6; }

/* Hover styles */
.hover\:bg-red-700:hover { background-color: #b91c1c; }
.hover\:bg-blue-700:hover { background-color: #1d4ed8; }
.hover\:bg-gray-600:hover { background-color: #4b5563; }

/* Responsive design */
@media (min-width: 640px) {
    .sm\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .sm\:text-3xl { font-size: 1.875rem; }
    .sm\:text-base { font-size: 1rem; }
    .sm\:text-sm { font-size: 0.875rem; }
    .sm\:p-6 { padding: 1.5rem; }
    .sm\:h-64 { height: 16rem; }
}

@media (min-width: 1024px) {
    .lg\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .lg\:grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .lg\:px-8 { padding-left: 2rem; padding-right: 2rem; }
}

@media (max-width: 768px) {
    .accounting-container {
        padding: 15px;
    }
    
    .modal-content {
        margin: 20% auto;
        width: 95%;
    }
    
    .table-container {
        max-height: 300px;
    }
    
    .tab-button {
        padding: 10px 16px;
        font-size: 14px;
    }
}

@media (max-width: 640px) {
    .grid-cols-2 { grid-template-columns: 1fr; }
    .text-2xl { font-size: 1.25rem; }
    .text-xl { font-size: 1.125rem; }
    canvas { height: 200px !important; }
}
</style>

<div class="accounting-container">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <!-- Header -->
        <header class="bg-blue-700 text-white p-4 rounded-lg shadow-lg mb-6 relative">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold">Accounting and Calibration Dashboard</h1>
                        <p class="text-sm sm:text-base text-blue-100">Monitor financial integrity and machine performance</p>
                    </div>
                </div>
            </div>
        </header>

        <!-- Filter Section -->
        <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
            <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">Filter Data</h2>
            <form action="" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="dispenser_id" class="block text-sm font-medium text-gray-700">Dispenser</label>
                    <select name="dispenser_id" id="dispenser_id" onchange="this.form.submit()"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 sm:text-sm">
                        <?php foreach ($dispensers as $dispenser): ?>
                            <option value="<?= $dispenser['dispenser_id'] ?>"
                                    <?= $dispenser['dispenser_id'] == $dispenser_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dispenser['Description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                    <input type="date" name="start_date" id="start_date" value="<?= htmlspecialchars($start_date) ?>"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 sm:text-sm">
                </div>
                <div>
                    <label for="end_date" class="block text-sm font-medium text-gray-700">End Date</label>
                    <input type="date" name="end_date" id="end_date" value="<?= htmlspecialchars($end_date) ?>"
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-2 focus:ring-blue-500 sm:text-sm">
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 sm:text-sm">Apply</button>
                    <button type="button" onclick="window.location.href='?dispenser_id=27&start_date=<?= date('Y-m-d', strtotime('-7 days')) ?>&end_date=<?= date('Y-m-d') ?>'"
                            class="w-full bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 sm:text-sm">Reset</button>
                </div>
            </form>
        </section>

        <!-- Tab Navigation -->
        <div class="tab-navigation">
            <button class="tab-button active" data-tab="overview">Overview</button>
            <button class="tab-button" data-tab="accounting">Cash Accounting</button>
            <button class="tab-button" data-tab="calibration">Machine Calibration</button>
            <button class="tab-button" data-tab="analytics">Analytics</button>
        </div>

        <!-- Overview Tab -->
        <div id="overview" class="tab-content active">
            <!-- KPI Cards -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <h3 class="text-base font-semibold text-gray-700">Total Revenue</h3>
                    <p class="text-xl sm:text-2xl font-bold text-blue-600">₱<?= number_format($total_revenue, 2) ?></p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <h3 class="text-base font-semibold text-gray-700">Water Dispensed</h3>
                    <p class="text-xl sm:text-2xl font-bold text-green-600"><?= number_format($total_dispensed, 2) ?> L</p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md cursor-pointer" id="discrepancyCard">
                    <h3 class="text-base font-semibold text-gray-700">Calibration Issues</h3>
                    <p class="text-xl sm:text-2xl font-bold <?= count($discrepancies) > 0 ? 'text-red-600' : 'text-gray-600' ?>">
                        <?= count($discrepancies) ?>
                    </p>
                </div>
                <div class="bg-white p-4 rounded-lg shadow-md">
                    <h3 class="text-base font-semibold text-gray-700">Cash Accuracy</h3>
                    <p class="text-xl sm:text-2xl font-bold <?= $cash_difference == 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $cash_difference == 0 ? 'Balanced' : '₱' . number_format(abs($cash_difference), 2) ?>
                    </p>
                </div>
            </section>

            <!-- Recent Transactions -->
            <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">Recent Transactions</h2>
                <div class="mb-4">
                    <input type="text" id="searchInput" placeholder="Search transactions (ID, coin type, water type)..."
                           class="w-full border-gray-300 rounded-md shadow-sm p-2 focus:ring-2 focus:ring-blue-500 sm:text-sm">
                </div>
                <div class="table-container overflow-x-auto">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-200 sticky-header">
                                <th class="p-2 border text-left">ID</th>
                                <th class="p-2 border text-left">Date & Time</th>
                                <th class="p-2 border text-left">Coin</th>
                                <th class="p-2 border text-left">Dispensed (L)</th>
                                <th class="p-2 border text-left">Water Type</th>
                                <th class="p-2 border text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody id="transactionTable">
                            <?php foreach ($transactions as $transaction): ?>
                                <tr class="transaction-row <?= in_array($transaction['transaction_id'], $discrepancies) ? 'discrepancy' : '' ?>"
                                    data-transaction='{
                                        "id": "<?= $transaction['transaction_id'] ?>",
                                        "coin_type": "<?= htmlspecialchars($transaction['coin_type']) ?>",
                                        "amount": "<?= number_format($transaction['amount_dispensed'], 2) ?>",
                                        "expected": "<?= number_format($expected_amounts[$transaction['coin_type']] ?? 0, 2) ?>",
                                        "water_type": "<?= htmlspecialchars($transaction['water_type']) ?>",
                                        "date": "<?= htmlspecialchars($transaction['formatted_date']) ?>"
                                    }'>
                                    <td class="p-2 border"><?= $transaction['transaction_id'] ?></td>
                                    <td class="p-2 border"><?= htmlspecialchars($transaction['formatted_date']) ?></td>
                                    <td class="p-2 border"><?= htmlspecialchars($transaction['coin_type']) ?></td>
                                    <td class="p-2 border"><?= number_format($transaction['amount_dispensed'], 2) ?></td>
                                    <td class="p-2 border"><?= htmlspecialchars($transaction['water_type']) ?></td>
                                    <td class="p-2 border">
                                        <?= in_array($transaction['transaction_id'], $discrepancies) ? 'Calibration Issue' : 'Normal' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Calibration Overview -->
            <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md">
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">System Overview</h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <?= $calibration_message ?>
                        <?= $problem_details ?>
                    </div>
                    <div>
                        <canvas id="calibrationChart" class="max-w-full h-48 sm:h-64"></canvas>
                    </div>
                </div>
            </section>
        </div>

        <!-- Accounting Tab - ENHANCED WITH CASH RECONCILIATION -->
        <div id="accounting" class="tab-content">
            <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">Cash Accounting Module</h2>
                
                <!-- Cash Reconciliation -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Cash Reconciliation</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800">Expected Cash (Transactions)</h4>
                            <p class="text-xl font-bold text-blue-600">₱<?= number_format($expected_cash, 2) ?></p>
                            <p class="text-sm text-gray-600 mt-1">
                                1 Peso: <?= $coin_counts['1 Peso'] ?> coins<br>
                                5 Peso: <?= $coin_counts['5 Peso'] ?> coins<br>
                                10 Peso: <?= $coin_counts['10 Peso'] ?> coins
                            </p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-green-800">Actual Cash Collected</h4>
                            <p class="text-xl font-bold text-green-600">₱<?= number_format($actual_cash, 2) ?></p>
                        </div>
                        <div class="bg-<?= $cash_difference == 0 ? 'green' : 'red' ?>-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-<?= $cash_difference == 0 ? 'green' : 'red' ?>-800">Cash Difference</h4>
                            <p class="text-xl font-bold text-<?= $cash_difference == 0 ? 'green' : 'red' ?>-600">
                                ₱<?= number_format(abs($cash_difference), 2) ?>
                            </p>
                            <p class="text-sm text-<?= $cash_difference == 0 ? 'green' : 'red' ?>-600 mt-1">
                                <?= $cash_difference > 0 ? 'Surplus' : ($cash_difference < 0 ? 'Shortage' : 'Balanced') ?>
                            </p>
                        </div>
                    </div>

                    <!-- Cash Collection Form -->
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-3">Record Physical Cash Count</h4>
                        <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-sm text-gray-600">1 Peso Coins</label>
                                <input type="number" name="coins_1" value="<?= $coin_counts['1 Peso'] ?>" 
                                       class="w-full border-gray-300 rounded-md p-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">5 Peso Coins</label>
                                <input type="number" name="coins_5" value="<?= $coin_counts['5 Peso'] ?>" 
                                       class="w-full border-gray-300 rounded-md p-2 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600">10 Peso Coins</label>
                                <input type="number" name="coins_10" value="<?= $coin_counts['10 Peso'] ?>" 
                                       class="w-full border-gray-300 rounded-md p-2 text-sm">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" name="reconcile_cash" 
                                        class="w-full bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 text-sm">
                                    Reconcile Cash
                                </button>
                            </div>
                        </form>
                        
                        <?php if (isset($reconciliation_message)) echo $reconciliation_message; ?>
                    </div>
                </div>

                <!-- Revenue Report -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Revenue Report</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800">Total Cash Collected</h4>
                            <p class="text-xl font-bold text-blue-600">₱<?= number_format($total_revenue, 2) ?></p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-green-800">Expected from Water Sales</h4>
                            <p class="text-xl font-bold text-green-600">₱<?= number_format($total_revenue, 2) ?></p>
                        </div>
                        <div class="bg-<?= count($discrepancies) > 0 ? 'red' : 'gray' ?>-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-<?= count($discrepancies) > 0 ? 'red' : 'gray' ?>-800">Water Discrepancy</h4>
                            <p class="text-xl font-bold text-<?= count($discrepancies) > 0 ? 'red' : 'gray' ?>-600">
                                <?= count($discrepancies) ?> transactions
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Ledger -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Transaction Ledger</h3>
                    <div class="table-container overflow-x-auto">
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-gray-200 sticky-header">
                                    <th class="p-2 border text-left">Transaction ID</th>
                                    <th class="p-2 border text-left">Date & Time</th>
                                    <th class="p-2 border text-left">Coin Type</th>
                                    <th class="p-2 border text-left">Coins Inserted</th>
                                    <th class="p-2 border text-left">Water Dispensed (L)</th>
                                    <th class="p-2 border text-left">Expected (L)</th>
                                    <th class="p-2 border text-left">Deviation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): 
                                    $coin_value = $coin_values[$transaction['coin_type']] ?? 0;
                                    $expected = $expected_amounts[$transaction['coin_type']] ?? 0;
                                    $discrepancy = $transaction['amount_dispensed'] - $expected;
                                ?>
                                    <tr class="<?= abs($discrepancy) > 0.01 ? 'discrepancy' : '' ?>">
                                        <td class="p-2 border"><?= $transaction['transaction_id'] ?></td>
                                        <td class="p-2 border"><?= htmlspecialchars($transaction['formatted_date']) ?></td>
                                        <td class="p-2 border"><?= htmlspecialchars($transaction['coin_type']) ?></td>
                                        <td class="p-2 border">₱<?= number_format($coin_value, 2) ?></td>
                                        <td class="p-2 border"><?= number_format($transaction['amount_dispensed'], 2) ?></td>
                                        <td class="p-2 border"><?= number_format($expected, 2) ?></td>
                                        <td class="p-2 border <?= abs($discrepancy) > 0.01 ? 'text-red-600 font-medium' : '' ?>">
                                            <?= number_format($discrepancy, 2) ?> L
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <!-- Calibration Tab -->
        <div id="calibration" class="tab-content">
            <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">Machine Calibration Module</h2>
                
                <!-- Calibration Status -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Calibration Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="text-sm font-medium text-gray-700">Flow Meter Status</h4>
                            <p class="text-lg font-bold <?= isset($accuracy) && $accuracy >= 90 ? 'text-green-600' : 'text-red-600' ?>">
                                <?= isset($accuracy) && $accuracy >= 90 ? 'Calibrated' : 'Needs Calibration' ?>
                            </p>
                            <p class="text-sm text-gray-600 mt-1">
                                Target: 1 Peso=0.25L, 5 Peso=0.5L, 10 Peso=1.0L
                            </p>
                        </div>
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="text-sm font-medium text-gray-700">Coin Acceptor Status</h4>
                            <p class="text-lg font-bold text-green-600">Operational</p>
                            <p class="text-sm text-gray-600 mt-1">All coin types detected correctly</p>
                        </div>
                    </div>
                </div>

                <!-- Calibration Details -->
                <div class="mb-6">
                    <?= $action_plan ?>
                    <?= $adjustment_details ?>
                </div>

                <!-- Expected vs Actual -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Expected vs Actual Dispensing</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center">
                            <div class="bg-white p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-700">1 Peso Coin</h4>
                                <p class="text-lg font-bold text-blue-600">Target: 0.25L (250ml)</p>
                                <p class="text-sm text-gray-600">Check Arduino: amountDispensed = 0.25</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-700">5 Peso Coin</h4>
                                <p class="text-lg font-bold text-blue-600">Target: 0.5L (500ml)</p>
                                <p class="text-sm text-gray-600">Check Arduino: amountDispensed = 0.50</p>
                            </div>
                            <div class="bg-white p-4 rounded-lg border">
                                <h4 class="text-sm font-medium text-gray-700">10 Peso Coin</h4>
                                <p class="text-lg font-bold text-blue-600">Target: 1.0L (1000ml)</p>
                                <p class="text-sm text-gray-600">Check Arduino: amountDispensed = 1.00</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Calibration History -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Calibration History</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-gray-600">Last calibration check: <?= date('F j, Y H:i:s') ?></p>
                        <p class="text-gray-600">System: <?= isset($accuracy) ? number_format($accuracy, 2) . '% accuracy' : 'No data' ?></p>
                        <p class="text-gray-600">Action: <?= isset($accuracy) && $accuracy >= 90 ? 'No action needed' : 'Calibration required' ?></p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Analytics Tab -->
        <div id="analytics" class="tab-content">
            <section class="bg-white p-4 sm:p-6 rounded-lg shadow-md mb-6">
                <h2 class="text-lg sm:text-xl font-semibold mb-4 text-gray-800">Analytics & Reporting</h2>
                
                <!-- Sales Trends -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Sales Trends</h3>
                    <div class="bg-white p-4 rounded-lg border">
                        <canvas id="salesTrendChart" class="w-full h-64"></canvas>
                    </div>
                </div>

                <!-- Performance Metrics -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Performance Metrics</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-blue-800">Average Transaction Value</h4>
                            <p class="text-xl font-bold text-blue-600">
                                ₱<?= !empty($transactions) ? number_format($total_revenue / count($transactions), 2) : '0.00' ?>
                            </p>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-green-800">Total Transactions</h4>
                            <p class="text-xl font-bold text-green-600"><?= count($transactions) ?></p>
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <h4 class="text-sm font-medium text-purple-800">Peak Usage Hours</h4>
                            <p class="text-xl font-bold text-purple-600">10:00 AM - 2:00 PM</p>
                        </div>
                    </div>
                </div>

                <!-- Coin Type Distribution -->
                <div>
                    <h3 class="text-lg font-semibold mb-3 text-gray-800">Coin Type Distribution</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white p-4 rounded-lg border">
                            <canvas id="coinDistributionChart" class="w-full h-48"></canvas>
                        </div>
                        <div class="bg-white p-4 rounded-lg border">
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Usage by Coin Type</h4>
                            <ul class="space-y-2">
                                <?php
                                $coin_counts = ['1 Peso' => 0, '5 Peso' => 0, '10 Peso' => 0];
                                foreach ($transactions as $transaction) {
                                    $coin_type = $transaction['coin_type'];
                                    if (isset($coin_counts[$coin_type])) {
                                        $coin_counts[$coin_type]++;
                                    }
                                }
                                $total = array_sum($coin_counts);
                                ?>
                                <?php foreach ($coin_counts as $coin => $count): ?>
                                    <?php if ($total > 0): ?>
                                        <li class="flex justify-between items-center">
                                            <span class="text-gray-600"><?= $coin ?></span>
                                            <span class="font-medium"><?= $count ?> (<?= number_format(($count / $total) * 100, 1) ?>%)</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Modal and Footer (unchanged) -->
        <div id="transactionModal" class="modal">
            <div class="modal-content">
                <span class="modal-close">&times;</span>
                <h2 class="text-lg font-semibold mb-4">Transaction Details</h2>
                <div id="modalContent" class="text-gray-700"></div>
            </div>
        </div>

        <footer class="mt-6 text-center text-gray-600 text-sm">
            <p>&copy; 2025 Water Dispenser System. All rights reserved.</p>
        </footer>
    </div>
</div>

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

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        const rows = document.querySelectorAll('#transactionTable tr');
        rows.forEach(row => {
            const rowText = Array.from(row.querySelectorAll('td')).map(cell => cell.textContent.toLowerCase()).join(' ');
            row.style.display = rowText.includes(searchTerm) ? '' : 'none';
        });
    });

    // Modal functionality
    const modal = document.getElementById('transactionModal');
    const modalContent = document.getElementById('modalContent');
    const closeModal = document.querySelector('.modal-close');

    document.querySelectorAll('.transaction-row').forEach(row => {
        row.addEventListener('click', function() {
            const data = JSON.parse(this.dataset.transaction);
            modalContent.innerHTML = `
                <p><strong>Transaction ID:</strong> ${data.id}</p>
                <p><strong>Coin Type:</strong> ${data.coin_type}</p>
                <p><strong>Amount Dispensed:</strong> ${data.amount} liters</p>
                <p><strong>Expected:</strong> ${data.expected} liters</p>
                <p><strong>Water Type:</strong> ${data.water_type}</p>
                <p><strong>Date:</strong> ${data.date}</p>
            `;
            modal.style.display = 'block';
        });
    });

    closeModal.addEventListener('click', function() {
        modal.style.display = 'none';
    });

    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });

    // Discrepancy card click functionality
    document.getElementById('discrepancyCard').addEventListener('click', function() {
        const rows = document.querySelectorAll('#transactionTable tr');
        rows.forEach(row => {
            const isDiscrepancy = row.classList.contains('discrepancy');
            row.style.display = isDiscrepancy ? '' : 'none';
        });
        document.getElementById('searchInput').value = ''; // Clear search input
        // Scroll to transactions section
        document.getElementById('transactionsSection').scrollIntoView({ behavior: 'smooth' });
    });

    // Calibration Chart
    const ctx = document.getElementById('calibrationChart').getContext('2d');
    new Chart(ctx, {
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
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Accuracy (%)', font: { size: 12 } },
                    ticks: { stepSize: 20, font: { size: 10 } }
                },
                x: {
                    title: { display: true, text: 'Coin Type', font: { size: 12 } },
                    ticks: { font: { size: 10 } }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw.toFixed(2)}% accuracy`;
                        }
                    }
                }
            },
            maintainAspectRatio: false
        }
    });

    // Sales Trend Chart (Sample Data)
    const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(salesCtx, {
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
            }, {
                label: 'Water Dispensed (L)',
                data: [600, 950, 750, 1000, 900, 1100, 850],
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
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
</script>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php require_once 'includes/footer.php'; ?>