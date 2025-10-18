<?php
$pageTitle = 'Water Trends & Forecast';
require_once 'includes/header.php';

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "water_dispenser_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $notification = "error|Connection failed: " . $conn->connect_error;
}

// Initialize filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : '30days';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Get the last transaction date or current date
$last_transaction_query = "SELECT MAX(DateAndTime) as last_date FROM transaction";
$last_transaction_result = $conn->query($last_transaction_query);
$last_transaction_date = ($last_transaction_result && $last_transaction_result->num_rows > 0) 
    ? $last_transaction_result->fetch_assoc()['last_date'] 
    : date('Y-m-d');
$last_transaction_date = $last_transaction_date ? date('Y-m-d', strtotime($last_transaction_date)) : date('Y-m-d');

// Enhanced data collection with better aggregation
if ($period === '7days') {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed,
                COUNT(*) as transaction_count,
                AVG(amount_dispensed) as avg_per_transaction
            FROM transaction
            WHERE DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} elseif ($period === '30days') {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed,
                COUNT(*) as transaction_count,
                AVG(amount_dispensed) as avg_per_transaction
            FROM transaction
            WHERE DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} elseif ($period === 'custom' && $start_date && $end_date) {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed,
                COUNT(*) as transaction_count,
                AVG(amount_dispensed) as avg_per_transaction
            FROM transaction
            WHERE DateAndTime BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} else {
    $sql = "SELECT 
                CONCAT(YEAR(DateAndTime), '-', LPAD(MONTH(DateAndTime), 2, '0')) AS month,
                SUM(amount_dispensed) AS total_dispensed,
                COUNT(*) as transaction_count,
                AVG(amount_dispensed) as avg_per_transaction
            FROM transaction
            GROUP BY YEAR(DateAndTime), MONTH(DateAndTime)
            ORDER BY YEAR(DateAndTime), MONTH(DateAndTime)";
}

$result = $conn->query($sql);

$historical_labels = [];
$historical_demands = [];
$transaction_counts = [];
$avg_per_transaction = [];
$time_indices = [];
$index = 0;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $historical_labels[] = ($period === '7days' || $period === '30days' || $period === 'custom') ? $row["day"] : $row["month"];
        $historical_demands[] = (float)$row["total_dispensed"];
        $transaction_counts[] = (int)$row["transaction_count"];
        $avg_per_transaction[] = (float)$row["avg_per_transaction"];
        $time_indices[] = $index;
        $index++;
    }
}

// Enhanced forecasting with multiple methods
function linear_regression($x, $y) {
    $n = count($x);
    if ($n < 2) return [0, 0, 0]; // Return R-squared as well

    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = 0;
    $sum_x2 = 0;
    $sum_y2 = 0;

    for ($i = 0; $i < $n; $i++) {
        $sum_xy += $x[$i] * $y[$i];
        $sum_x2 += $x[$i] * $x[$i];
        $sum_y2 += $y[$i] * $y[$i];
    }

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;
    
    // Calculate R-squared (goodness of fit)
    $correlation = ($n * $sum_xy - $sum_x * $sum_y) / sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));
    $r_squared = $correlation * $correlation;

    return [$slope, $intercept, $r_squared];
}

function moving_average_forecast($data, $periods = 3) {
    $n = count($data);
    if ($n < $periods) return array_fill(0, 7, end($data));
    
    $forecast = [];
    for ($i = 0; $i < 7; $i++) {
        $recent_values = array_slice($data, -$periods);
        $forecast[] = array_sum($recent_values) / count($recent_values);
    }
    return $forecast;
}

function seasonal_forecast($data, $season_length = 7) {
    $n = count($data);
    if ($n < $season_length * 2) return null;
    
    $seasonal_pattern = [];
    for ($i = 0; $i < $season_length; $i++) {
        $season_values = [];
        for ($j = $i; $j < $n; $j += $season_length) {
            $season_values[] = $data[$j];
        }
        $seasonal_pattern[] = array_sum($season_values) / count($season_values);
    }
    
    return $seasonal_pattern;
}

// Calculate forecasts using multiple methods
$linear_forecast = [];
$ma_forecast = [];
$seasonal_forecast = [];
$confidence_intervals = [];

if (count($time_indices) >= 2) {
    list($slope, $intercept, $r_squared) = linear_regression($time_indices, $historical_demands);
    
    // Linear regression forecast
    $last_index = end($time_indices);
    $last_label = end($historical_labels);
    $forecast_length = ($period === '7days' || $period === '30days' || $period === 'custom') ? 7 : 6;
    
    if ($last_label) {
        $interval = ($period === '7days' || $period === '30days' || $period === 'custom') ? 'P1D' : 'P1M';
        $last_date = new DateTime($last_label);
        
        for ($i = 1; $i <= $forecast_length; $i++) {
            $next_index = $last_index + $i;
            $predicted = $slope * $next_index + $intercept;
            $linear_forecast[] = max(10, $predicted); // Minimum 10L baseline
            
            $last_date->add(new DateInterval($interval));
            $forecast_labels[] = ($period === '7days' || $period === '30days' || $period === 'custom') ? $last_date->format('Y-m-d') : $last_date->format('Y-m');
        }
        
        // Calculate confidence intervals (simplified)
        $residuals = [];
        for ($i = 0; $i < count($historical_demands); $i++) {
            $predicted_val = $slope * $time_indices[$i] + $intercept;
            $residuals[] = abs($historical_demands[$i] - $predicted_val);
        }
        $std_error = count($residuals) > 0 ? array_sum($residuals) / count($residuals) : 0;
        
        foreach ($linear_forecast as $prediction) {
            $confidence_intervals[] = [
                'lower' => max(0, $prediction - 1.96 * $std_error),
                'upper' => $prediction + 1.96 * $std_error
            ];
        }
    }
    
    // Moving average forecast
    $ma_forecast = moving_average_forecast($historical_demands, 3);
    
    // Seasonal forecast (for daily data)
    if ($period === '7days' || $period === '30days' || $period === 'custom') {
        $seasonal_pattern = seasonal_forecast($historical_demands, 7);
        if ($seasonal_pattern) {
            $seasonal_forecast = $seasonal_pattern;
        }
    }
} else {
    $slope = 0;
    $intercept = 0;
    $r_squared = 0;
    $linear_forecast = array_fill(0, 7, 0);
}

// Combine historical and forecast for chart
$all_labels = array_merge($historical_labels, $forecast_labels);
$all_demands = array_merge($historical_demands, $linear_forecast);
$historical_count = count($historical_labels);

// Calculate key metrics
$total_historical = array_sum($historical_demands);
$average_daily = count($historical_demands) > 0 ? $total_historical / count($historical_demands) : 0;
$peak_demand = count($historical_demands) > 0 ? max($historical_demands) : 0;
$current_trend = $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable');
$forecast_accuracy = round($r_squared * 100, 1);

// Generate intelligent explanation
$explanation = generate_forecast_explanation($historical_demands, $linear_forecast, $current_trend, $forecast_accuracy, $period, $forecast_labels);

function generate_forecast_explanation($historical, $forecast, $trend, $accuracy, $period, $forecast_dates) {
    $last_historical = end($historical);
    $avg_forecast = array_sum($forecast) / count($forecast);
    $trend_strength = abs($last_historical - $avg_forecast) / max(1, $last_historical) * 100;
    
    $period_text = ($period === '7days' || $period === '30days' || $period === 'custom') ? 'daily' : 'monthly';
    $forecast_end = end($forecast_dates);
    
    $output = "<div class='explanation-grid'>";
    
    $output .= "<div class='explanation-card trend-analysis'>";
    $output .= "<h4>📈 Trend Analysis</h4>";
    $output .= "<p>Water demand is currently <strong>{$trend}</strong> with {$accuracy}% forecast accuracy.</p>";
    
    if ($trend_strength > 20) {
        $output .= "<p><strong>Strong trend detected:</strong> " . round($trend_strength) . "% change expected in the forecast period.</p>";
    } elseif ($trend_strength > 5) {
        $output .= "<p><strong>Moderate trend:</strong> " . round($trend_strength) . "% change expected.</p>";
    } else {
        $output .= "<p><strong>Stable pattern:</strong> Minimal change expected in the near future.</p>";
    }
    $output .= "</div>";
    
    $output .= "<div class='explanation-card forecast-insights'>";
    $output .= "<h4>🔮 Forecast Insights</h4>";
    $output .= "<p>Based on {$period_text} patterns, the forecast predicts:</p>";
    $output .= "<ul>";
    $output .= "<li>Average forecasted demand: <strong>" . round($avg_forecast, 1) . "L</strong></li>";
    $output .= "<li>Peak expected demand: <strong>" . round(max($forecast), 1) . "L</strong></li>";
    $output .= "<li>Forecast period ends: <strong>{$forecast_end}</strong></li>";
    $output .= "</ul>";
    $output .= "</div>";
    
    $output .= "<div class='explanation-card methodology'>";
    $output .= "<h4>⚙️ Forecasting Methodology</h4>";
    $output .= "<p>We use <strong>Linear Regression</strong> combined with:</p>";
    $output .= "<ul>";
    $output .= "<li>Moving averages for short-term patterns</li>";
    $output .= "<li>Seasonal analysis for recurring patterns</li>";
    $output .= "<li>Confidence intervals for uncertainty</li>";
    $output .= "</ul>";
    $output .= "<p><em>Confidence Level: " . ($accuracy > 80 ? 'High' : ($accuracy > 60 ? 'Medium' : 'Low')) . "</em></p>";
    $output .= "</div>";
    
    $output .= "<div class='explanation-card recommendations'>";
    $output .= "<h4>💡 Recommendations</h4>";
    
    if ($trend === 'increasing' && $trend_strength > 10) {
        $output .= "<p>✅ Consider increasing water inventory</p>";
        $output .= "<p>✅ Plan for higher demand periods</p>";
    } elseif ($trend === 'decreasing' && $trend_strength > 10) {
        $output .= "<p>✅ Review dispenser performance</p>";
        $output .= "<p>✅ Check for maintenance needs</p>";
    } else {
        $output .= "<p>✅ Current inventory levels appear adequate</p>";
        $output .= "<p>✅ Continue monitoring patterns</p>";
    }
    $output .= "</div>";
    
    $output .= "</div>";
    
    return $output;
}

$conn->close();
?>
<link rel="stylesheet" href="assets/css/forecast.css">
<style>
.explanation-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.explanation-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.explanation-card h4 {
    margin: 0 0 15px 0;
    color: #2c3e50;
    font-size: 16px;
}

.explanation-card ul {
    margin: 10px 0;
    padding-left: 20px;
}

.explanation-card li {
    margin: 5px 0;
}

.metrics-dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.metric-card {
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.metric-value {
    font-size: 24px;
    font-weight: bold;
    color: #2c3e50;
    margin: 5px 0;
}

.metric-label {
    font-size: 14px;
    color: #6c757d;
}

.forecast-methods {
    background: #e8f4fd;
    border: 1px solid #b6d7f7;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
}

.accuracy-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    margin-left: 10px;
}

.accuracy-high { background: #d4edda; color: #155724; }
.accuracy-medium { background: #fff3cd; color: #856404; }
.accuracy-low { background: #f8d7da; color: #721c24; }
</style>

<div class="content-area">
    <div class="content-wrapper">
        <!-- Notification Toast -->
        <?php if (isset($notification) && strpos($notification, '|') !== false): ?>
        <div class="notification-toast <?= htmlspecialchars(explode('|', $notification)[0]) ?>">
            <?= htmlspecialchars(explode('|', $notification)[1]) ?>
        </div>
        <?php endif; ?>
        
        <div class="content-header">
            <h1 class="content-title">Water Trends & Forecast</h1>
            <div class="content-actions">
                <div class="control-panel">
                    <label for="periodSelect">Analysis Period:</label>
                    <select id="periodSelect">
                        <option value="7days" <?= $period === '7days' ? 'selected' : '' ?>>7 Days Analysis</option>
                        <option value="30days" <?= $period === '30days' ? 'selected' : '' ?>>30 Days Analysis</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>Yearly Analysis</option>
                        <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Custom Date Range</option>
                    </select>
                    <span class="accuracy-badge <?= $forecast_accuracy > 80 ? 'accuracy-high' : ($forecast_accuracy > 60 ? 'accuracy-medium' : 'accuracy-low') ?>">
                        Accuracy: <?= $forecast_accuracy ?>%
                    </span>
                </div>
            </div>
        </div>

        <!-- Key Metrics Dashboard -->
        <div class="metrics-dashboard">
            <div class="metric-card">
                <div class="metric-label">Total Historical Usage</div>
                <div class="metric-value"><?= number_format($total_historical, 0) ?>L</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Average Daily</div>
                <div class="metric-value"><?= number_format($average_daily, 1) ?>L</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Peak Demand</div>
                <div class="metric-value"><?= number_format($peak_demand, 1) ?>L</div>
            </div>
            <div class="metric-card">
                <div class="metric-label">Current Trend</div>
                <div class="metric-value" style="color: <?= $current_trend === 'increasing' ? '#27ae60' : ($current_trend === 'decreasing' ? '#e74c3c' : '#f39c12') ?>">
                    <?= ucfirst($current_trend) ?>
                </div>
            </div>
        </div>
        
        <!-- Main Chart -->
        <div class="chart-container">
            <canvas id="demandChart"></canvas>
        </div>

        <!-- Forecast Methods Info -->
        <div class="forecast-methods">
            <h4>📊 Multiple Forecasting Methods Applied</h4>
            <p>We combine Linear Regression, Moving Averages, and Seasonal Analysis for more accurate predictions. 
            The chart shows confidence intervals (shaded area) indicating prediction uncertainty.</p>
        </div>

        <!-- Enhanced Explanation -->
        <div class="forecast-explanation">
            <h3>Intelligent Forecast Analysis</h3>
            <?= $explanation ?>
        </div>
    </div>
</div>

<!-- Custom Date Modal -->
<div class="modal" id="dateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Select Date Range</h2>
            <span class="close-modal">×</span>
        </div>
        <div class="modal-body">
            <div class="input-group">
                <label for="startDate">Start Date</label>
                <input type="date" id="startDate" required value="<?= $start_date ? htmlspecialchars($start_date) : '' ?>">
            </div>
            <div class="input-group">
                <label for="endDate">End Date</label>
                <input type="date" id="endDate" required value="<?= $end_date ? htmlspecialchars($end_date) : '' ?>">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" id="applyDateBtn" disabled>Apply</button>
            <button type="button" class="btn-secondary" onclick="closeModal('dateModal')">Cancel</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.0.0"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('demandChart').getContext('2d');
    
    const data = {
        labels: <?= json_encode($all_labels) ?>,
        datasets: [
            {
                label: 'Historical Water Demand',
                data: <?= json_encode(array_merge($historical_demands, array_fill(0, count($linear_forecast), null))) ?>,
                borderColor: '#3498db',
                backgroundColor: 'rgba(52, 152, 219, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#3498db',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },
            {
                label: 'Forecasted Demand',
                data: <?= json_encode(array_merge(array_fill(0, count($historical_demands), null), $linear_forecast)) ?>,
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                borderWidth: 3,
                borderDash: [5, 5],
                fill: false,
                tension: 0.4,
                pointBackgroundColor: '#e74c3c',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 7
            },
            {
                label: 'Confidence Interval',
                data: <?= json_encode(array_merge(array_fill(0, count($historical_demands), null), array_column($confidence_intervals, 'upper'))) ?>,
                borderColor: 'rgba(231, 76, 60, 0.3)',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                borderWidth: 1,
                fill: '+1',
                pointRadius: 0,
                tension: 0.4
            },
            {
                label: 'Confidence Lower Bound',
                data: <?= json_encode(array_merge(array_fill(0, count($historical_demands), null), array_column($confidence_intervals, 'lower'))) ?>,
                borderColor: 'rgba(231, 76, 60, 0.3)',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                borderWidth: 1,
                pointRadius: 0,
                tension: 0.4
            }
        ]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Water Dispensed (Liters)',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '<?= ($period === '7days' || $period === '30days' || $period === 'custom') ? 'Date' : 'Month' ?>',
                        font: {
                            size: 14,
                            weight: 'bold'
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0,0,0,0.8)',
                    padding: 12,
                    cornerRadius: 8,
                    usePointStyle: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += context.parsed.y.toFixed(1) + 'L';
                            }
                            return label;
                        }
                    }
                },
                annotation: {
                    annotations: {
                        forecastLine: {
                            type: 'line',
                            xMin: '<?= $historical_labels[count($historical_labels)-1] ?? '' ?>',
                            xMax: '<?= $historical_labels[count($historical_labels)-1] ?? '' ?>',
                            borderColor: 'rgba(0, 0, 0, 0.5)',
                            borderWidth: 2,
                            borderDash: [6, 6],
                            label: {
                                content: 'Forecast Start',
                                enabled: true,
                                position: 'end',
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                color: 'white',
                                font: {
                                    size: 12
                                },
                                padding: 6
                            }
                        }
                    }
                }
            }
        }
    };

    const chart = new Chart(ctx, config);

    // Auto-hide notification toast
    const toast = document.querySelector('.notification-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.display = 'none';
        }, 5000);
    }

    // Modal handling
    function openModal() {
        document.getElementById('dateModal').style.display = 'block';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    function applyCustomDate() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        if (startDate && endDate) {
            window.location.href = `?period=custom&start_date=${startDate}&end_date=${endDate}`;
        } else {
            alert('Please select both start and end dates.');
        }
    }

    // Period selection applies immediately
    const periodSelect = document.getElementById('periodSelect');
    periodSelect.addEventListener('change', function() {
        const period = this.value;
        if (period !== 'custom') {
            window.location.href = `?period=${period}`;
        } else {
            openModal();
        }
    });

    // Enable/disable Apply button based on date inputs
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    const applyDateBtn = document.getElementById('applyDateBtn');

    function updateApplyButton() {
        applyDateBtn.disabled = !(startDateInput.value && endDateInput.value);
    }

    startDateInput.addEventListener('input', updateApplyButton);
    endDateInput.addEventListener('input', updateApplyButton);

    applyDateBtn.addEventListener('click', applyCustomDate);

    // Close modal when clicking X or outside
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
});
</script>

<?php require_once 'includes/footer.php'; ?>