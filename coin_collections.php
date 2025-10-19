<?php
$pageTitle = 'Coin Collections';
require_once 'includes/header.php';
require_once 'includes/db_connect.php';

// Get selected dispenser and period
$selected_dispenser = isset($_GET['dispenser_id']) ? (int)$_GET['dispenser_id'] : 0;
$selected_period = isset($_GET['period']) ? $_GET['period'] : 'Day';
$valid_periods = ['Day', '7 Days', 'Month'];
if (!in_array($selected_period, $valid_periods)) {
    $selected_period = 'Day';
}

// Fetch coin collections by coin type (used for both Coin Type Distribution and Dispenser Collections)
$coin_query = "
    SELECT d.dispenser_id, d.Description, l.location_name, l.address, 
           CASE 
               WHEN LOWER(t.coin_type) = 'peso' THEN '1 Peso'
               WHEN LOWER(t.coin_type) = '1 peso' THEN '1 Peso'
               WHEN LOWER(t.coin_type) = '5 peso' THEN '5 Peso'
               WHEN LOWER(t.coin_type) = '10 peso' THEN '10 Peso'
               ELSE t.coin_type 
           END as coin_type, 
           COUNT(*) as transaction_count, 
           SUM(CASE 
               WHEN t.coin_type = '1 Peso' THEN 1 
               WHEN t.coin_type = '5 Peso' THEN 5 
               WHEN t.coin_type = '10 Peso' THEN 10 
               ELSE 0 END) as total_value 
    FROM transaction t 
    JOIN dispenser d ON t.dispenser_id = d.dispenser_id 
    LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id 
    LEFT JOIN location l ON dl.location_id = l.location_id 
";
if ($selected_dispenser) {
    $coin_query .= " WHERE t.dispenser_id = :dispenser_id";
}
$coin_query .= " GROUP BY d.dispenser_id, d.Description, l.location_name, l.address, coin_type";
$stmt = $pdo->prepare($coin_query);
if ($selected_dispenser) {
    $stmt->execute(['dispenser_id' => $selected_dispenser]);
} else {
    $stmt->execute();
}
$coin_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Aggregate for Coin Type Distribution (all dispensers or selected)
$coin_summary = [];
$coin_types = ['1 Peso', '5 Peso', '10 Peso'];
foreach ($coin_types as $type) {
    $coin_summary[$type] = ['transaction_count' => 0, 'total_value' => 0];
}
if (empty($coin_data)) {
    error_log("No coin data retrieved from the database.");
} else {
    foreach ($coin_data as $row) {
        if (isset($coin_summary[$row['coin_type']])) {
            $coin_summary[$row['coin_type']]['transaction_count'] += $row['transaction_count'];
            $coin_summary[$row['coin_type']]['total_value'] += $row['total_value'];
        } else {
            error_log("Unexpected coin_type: " . $row['coin_type']);
        }
    }
}
$total_revenue = array_sum(array_column($coin_summary, 'total_value'));

// Group by dispenser for Dispenser Collections
$dispenser_collections_grouped = [];
$total_collected_all = 0;
foreach ($coin_data as $row) {
    $dispenser_id = $row['dispenser_id'];
    if (!isset($dispenser_collections_grouped[$dispenser_id])) {
        $dispenser_collections_grouped[$dispenser_id] = [
            'Description' => $row['Description'],
            'location_name' => $row['location_name'],
            'address' => $row['address'],
            'coin_types' => array_fill_keys($coin_types, ['transaction_count' => 0, 'total_value' => 0]),
            'total_collected' => 0
        ];
    }
    if (isset($row['coin_type']) && in_array($row['coin_type'], $coin_types)) {
        $dispenser_collections_grouped[$dispenser_id]['coin_types'][$row['coin_type']] = [
            'transaction_count' => $row['transaction_count'],
            'total_value' => $row['total_value']
        ];
        $dispenser_collections_grouped[$dispenser_id]['total_collected'] += $row['total_value'];
        $total_collected_all += $row['total_value'];
    } else {
        error_log("Unexpected coin_type in dispenser collections: " . ($row['coin_type'] ?? 'NULL'));
    }
}

// Fetch daily sales
$daily_query = "
    SELECT DATE(DateAndTime) as date, 
           SUM(CASE 
               WHEN coin_type = '1 Peso' THEN 1 
               WHEN coin_type = '5 Peso' THEN 5 
               WHEN coin_type = '10 Peso' THEN 10 
               ELSE 0 END) as total_collected 
    FROM transaction 
    WHERE DATE(DateAndTime) = CURDATE()
";
if ($selected_dispenser) {
    $daily_query .= " AND dispenser_id = :dispenser_id";
}
$daily_query .= " GROUP BY DATE(DateAndTime)";
$stmt = $pdo->prepare($daily_query);
if ($selected_dispenser) {
    $stmt->execute(['dispenser_id' => $selected_dispenser]);
} else {
    $stmt->execute();
}
$daily_sales = $stmt->fetch(PDO::FETCH_ASSOC);
$daily_sales_value = $daily_sales['total_collected'] ?? 0;

// Fetch hourly sales for today
$hourly_query = "
    SELECT HOUR(DateAndTime) as hour, 
           SUM(CASE 
               WHEN coin_type = '1 Peso' THEN 1 
               WHEN coin_type = '5 Peso' THEN 5 
               WHEN coin_type = '10 Peso' THEN 10 
               ELSE 0 END) as total_collected 
    FROM transaction 
    WHERE DATE(DateAndTime) = CURDATE()
";
if ($selected_dispenser) {
    $hourly_query .= " AND dispenser_id = :dispenser_id";
}
$hourly_query .= " GROUP BY HOUR(DateAndTime)";
$stmt = $pdo->prepare($hourly_query);
if ($selected_dispenser) {
    $stmt->execute(['dispenser_id' => $selected_dispenser]);
} else {
    $stmt->execute();
}
$hourly_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
$hourly_sales_value = array_sum(array_column($hourly_sales, 'total_collected'));

// Fetch recent transactions
$transaction_query = "
    SELECT t.transaction_id, t.DateAndTime, 
           CASE 
               WHEN LOWER(t.coin_type) = 'peso' THEN '1 Peso'
               WHEN LOWER(t.coin_type) = '1 peso' THEN '1 Peso'
               WHEN LOWER(t.coin_type) = '5 peso' THEN '5 Peso'
               WHEN LOWER(t.coin_type) = '10 peso' THEN '10 Peso'
               ELSE t.coin_type 
           END as coin_type, 
           (CASE 
               WHEN t.coin_type = '1 Peso' THEN 1 
               WHEN t.coin_type = '5 Peso' THEN 5 
               WHEN t.coin_type = '10 Peso' THEN 10 
               ELSE 0 END) as coin_value,
           t.amount_dispensed, t.water_type, d.Description 
    FROM transaction t 
    JOIN dispenser d ON t.dispenser_id = d.dispenser_id 
";
if ($selected_dispenser) {
    $transaction_query .= " WHERE t.dispenser_id = :dispenser_id";
}
$transaction_query .= " ORDER BY t.DateAndTime DESC LIMIT 5";
$stmt = $pdo->prepare($transaction_query);
if ($selected_dispenser) {
    $stmt->execute(['dispenser_id' => $selected_dispenser]);
} else {
    $stmt->execute();
}
$recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch collection trends based on period
$trends_query = "
    SELECT ";
if ($selected_period == 'Day') {
    $trends_query .= "HOUR(DateAndTime) as time_unit, 
                     CONCAT(LPAD(HOUR(DateAndTime), 2, '0'), ':00') as label";
} else {
    $trends_query .= "DATE(DateAndTime) as time_unit, 
                     DATE_FORMAT(DateAndTime, '%Y-%m-%d') as label";
}
$trends_query .= ",
           SUM(CASE 
               WHEN coin_type = '1 Peso' THEN 1 
               WHEN coin_type = '5 Peso' THEN 5 
               WHEN coin_type = '10 Peso' THEN 10 
               ELSE 0 END) as total_collected 
    FROM transaction 
";
$where_clauses = [];
if ($selected_dispenser) {
    $where_clauses[] = "dispenser_id = :dispenser_id";
}
if ($selected_period == 'Day') {
    $where_clauses[] = "DATE(DateAndTime) = CURDATE()";
} elseif ($selected_period == '7 Days') {
    $where_clauses[] = "DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
} elseif ($selected_period == 'Month') {
    $where_clauses[] = "DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
}
if (!empty($where_clauses)) {
    $trends_query .= " WHERE " . implode(' AND ', $where_clauses);
}
$trends_query .= $selected_period == 'Day' ? " GROUP BY HOUR(DateAndTime) ORDER BY time_unit" : " GROUP BY DATE(DateAndTime) ORDER BY time_unit";
$stmt = $pdo->prepare($trends_query);
if ($selected_dispenser) {
    $stmt->execute(['dispenser_id' => $selected_dispenser]);
} else {
    $stmt->execute();
}
$collection_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all dispensers for dropdown
$stmt = $pdo->query("SELECT dispenser_id, Description FROM dispenser WHERE dispenser_id IN (27, 28, 29, 30, 31, 32, 33) ORDER BY Description");
$dispensers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<link rel="stylesheet" href="assets/css/coin_collections.css">
<style>
/* Stat Cards Styles */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 30px;
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
    color: #f59e0b;
}

.stat-change.danger {
    color: #ef4444;
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
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 24px;
    }
}
</style>

<div class="content-area">
    <div class="content-wrapper">
        <div class="content-header">
            <h1 class="content-title">Coin Collection Monitoring</h1>
            <div class="content-actions">
                <select id="dispenserSelect" class="btn-primary" onchange="updatePage()">
                    <option value="0" <?php echo $selected_dispenser == 0 ? 'selected' : ''; ?>>All Dispensers</option>
                    <?php foreach ($dispensers as $dispenser): ?>
                    <option value="<?php echo $dispenser['dispenser_id']; ?>" <?php echo $selected_dispenser == $dispenser['dispenser_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dispenser['Description']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <select id="periodSelect" class="btn-primary" onchange="updatePage()">
                    <option value="Day" <?php echo $selected_period == 'Day' ? 'selected' : ''; ?>>Day</option>
                    <option value="7 Days" <?php echo $selected_period == '7 Days' ? 'selected' : ''; ?>>7 Days</option>
                    <option value="Month" <?php echo $selected_period == 'Month' ? 'selected' : ''; ?>>Month</option>
                </select>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value"><?php echo number_format($total_revenue, 2); ?> PHP</div>
                    <div class="stat-change success">
                        <i class="fas fa-chart-line"></i> Across <?php echo $selected_dispenser ? 'Selected Dispenser' : 'All Machines'; ?>
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Daily Sales</div>
                    <div class="stat-value"><?php echo number_format($daily_sales_value, 2); ?> PHP</div>
                    <div class="stat-change success">
                        <i class="fas fa-sun"></i> Today's Revenue
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Hourly Sales</div>
                    <div class="stat-value"><?php echo number_format($hourly_sales_value, 2); ?> PHP</div>
                    <div class="stat-change success">
                        <i class="fas fa-chart-bar"></i> Today's Hourly Total
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Transactions</div>
                    <div class="stat-value"><?php echo number_format(array_sum(array_column($coin_summary, 'transaction_count'))); ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-exchange-alt"></i> All Coin Types
                    </div>
                </div>
            </div>
        </div>

        <!-- Coin Type Distribution Graph -->
        <div class="table-container">
            <h2 class="text-lg font-semibold mb-4">Coin Type Distribution</h2>
            <canvas id="coinTypeChart" class="w-full" style="max-height: 300px;"></canvas>
        </div>

        <!-- Dispenser Collections Table -->
        <div class="table-container" id="dispenserCollections">
            <h2 class="text-lg font-semibold mb-4">Dispenser Collections Breakdown</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dispenser</th>
                        <th>Location</th>
                        <th>Address</th>
                        <th>1 Peso (Count/Value)</th>
                        <th>5 Peso (Count/Value)</th>
                        <th>10 Peso (Count/Value)</th>
                        <th>Total Collected (PHP)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($selected_dispenser): ?>
                        <?php foreach ($dispenser_collections_grouped as $dispenser): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dispenser['Description']); ?></td>
                            <td><?php echo htmlspecialchars($dispenser['location_name'] ?? 'Not Deployed'); ?></td>
                            <td><?php echo htmlspecialchars($dispenser['address'] ?? '-'); ?></td>
                            <td>
                                <?php
                                $one_peso = $dispenser['coin_types']['1 Peso'];
                                echo $one_peso['transaction_count'] . ' / ' . number_format($one_peso['total_value'], 2);
                                ?>
                            </td>
                            <td>
                                <?php
                                $five_peso = $dispenser['coin_types']['5 Peso'];
                                echo $five_peso['transaction_count'] . ' / ' . number_format($five_peso['total_value'], 2);
                                ?>
                            </td>
                            <td>
                                <?php
                                $ten_peso = $dispenser['coin_types']['10 Peso'];
                                echo $ten_peso['transaction_count'] . ' / ' . number_format($ten_peso['total_value'], 2);
                                ?>
                            </td>
                            <td class="text-right"><?php echo number_format($dispenser['total_collected'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3">All Dispensers</td>
                            <td>
                                <?php
                                echo $coin_summary['1 Peso']['transaction_count'] . ' / ' . number_format($coin_summary['1 Peso']['total_value'], 2);
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $coin_summary['5 Peso']['transaction_count'] . ' / ' . number_format($coin_summary['5 Peso']['total_value'], 2);
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $coin_summary['10 Peso']['transaction_count'] . ' / ' . number_format($coin_summary['10 Peso']['total_value'], 2);
                                ?>
                            </td>
                            <td class="text-right"><?php echo number_format($total_collected_all, 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Recent Transactions -->
        <div class="table-container">
            <h2 class="text-lg font-semibold mb-4">Recent Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Date & Time</th>
                        <th>Coin Type</th>
                        <th>Coin Value (PHP)</th>
                        <th>Amount Dispensed</th>
                        <th>Water Type</th>
                        <th>Dispenser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_transactions as $transaction): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['DateAndTime']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['coin_type']); ?></td>
                        <td class="text-right"><?php echo number_format($transaction['coin_value'], 2); ?></td>
                        <td><?php echo number_format($transaction['amount_dispensed'], 2); ?> L</td>
                        <td><?php echo htmlspecialchars($transaction['water_type']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['Description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Collection Trends Chart -->
        <div class="table-container">
            <h2 class="text-lg font-semibold mb-4">Collection Trends</h2>
            <canvas id="collectionTrendsChart" class="w-full" style="max-height: 300px;"></canvas>
            <p class="mt-4 text-sm text-gray-600">
                This chart shows the total coin value (in PHP) collected over time for 
                <?php echo $selected_dispenser ? 'the selected dispenser' : 'all dispensers'; ?>.
                Use the period filter to view trends for today (by hour), the last 7 days (by day), 
                or the last 30 days (by day). A rising trend indicates higher collections, while a 
                flat or declining trend may suggest lower activity. Select a dispenser or period to 
                update the chart instantly.
            </p>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Coin Type Chart
const coinTypeCtx = document.getElementById('coinTypeChart').getContext('2d');
new Chart(coinTypeCtx, {
    type: 'bar',
    data: {
        labels: ['1 Peso', '5 Peso', '10 Peso'],
        datasets: [
            {
                label: 'Total Value (PHP)',
                data: [
                    <?php echo $coin_summary['1 Peso']['total_value']; ?>,
                    <?php echo $coin_summary['5 Peso']['total_value']; ?>,
                    <?php echo $coin_summary['10 Peso']['total_value']; ?>
                ],
                backgroundColor: 'rgba(52, 152, 219, 0.6)',
                borderColor: '#3498db',
                borderWidth: 1
            },
            {
                label: 'Transaction Count',
                data: [
                    <?php echo $coin_summary['1 Peso']['transaction_count']; ?>,
                    <?php echo $coin_summary['5 Peso']['transaction_count']; ?>,
                    <?php echo $coin_summary['10 Peso']['transaction_count']; ?>
                ],
                backgroundColor: 'rgba(46, 204, 113, 0.6)',
                borderColor: '#2ecc71',
                borderWidth: 1
            }
        ]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Value / Count', font: { size: 14 } },
                grid: { color: '#e9ecef' }
            },
            x: {
                title: { display: true, text: 'Coin Type', font: { size: 14 } }
            }
        },
        plugins: {
            title: { display: true, text: 'Coin Type Distribution', font: { size: 16, weight: 'bold' } },
            legend: { display: true, position: 'top' },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += context.parsed.y;
                        if (context.dataset.label === 'Total Value (PHP)') {
                            label += ' PHP';
                        } else {
                            label += ' transactions';
                        }
                        return label;
                    }
                }
            }
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        }
    }
});

// Collection Trends Chart
const collectionTrendsCtx = document.getElementById('collectionTrendsChart').getContext('2d');
new Chart(collectionTrendsCtx, {
    type: 'line',
    data: {
        labels: [<?php echo "'" . implode("','", array_column($collection_trends, 'label')) . "'"; ?>],
        datasets: [{
            label: 'Total Coin Value (PHP)',
            data: [<?php echo implode(',', array_column($collection_trends, 'total_collected')); ?>],
            borderColor: '#3498db',
            backgroundColor: 'rgba(52, 152, 219, 0.2)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#3498db',
            pointRadius: 4,
            pointHoverRadius: 6
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Total Coin Value (PHP)', font: { size: 14 } },
                grid: { color: '#e9ecef' }
            },
            x: {
                title: { 
                    display: true, 
                    text: '<?php echo $selected_period == 'Day' ? 'Hour' : 'Date'; ?>', 
                    font: { size: 14 } 
                }
            }
        },
        plugins: {
            title: { 
                display: true, 
                text: 'Collection Trends (<?php echo $selected_period; ?>)', 
                font: { size: 16, weight: 'bold' } 
            },
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return `Value: ${context.parsed.y} PHP`;
                    }
                }
            }
        },
        animation: {
            duration: 1000,
            easing: 'easeOutQuart'
        }
    }
});

// Update page on dispenser or period change
function updatePage() {
    const dispenserId = document.getElementById('dispenserSelect').value;
    const period = document.getElementById('periodSelect').value;
    window.location.href = 'coin_collections.php?dispenser_id=' + dispenserId + '&period=' + encodeURIComponent(period);
}
</script>
<?php require_once 'includes/footer.php'; ?>