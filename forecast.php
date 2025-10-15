<?php
$pageTitle = 'Water Trends & Forecast';
require_once 'includes/header.php';

// Database connection
$servername = "127.0.0.1";
$username = "root"; // Change if needed
$password = ""; // Change if needed
$dbname = "water_dispenser_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    $notification = "error|Connection failed: " . $conn->connect_error;
}

// Initialize filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'year';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;

// Get the last transaction date or current date
$last_transaction_query = "SELECT MAX(DateAndTime) as last_date FROM transaction";
$last_transaction_result = $conn->query($last_transaction_query);
$last_transaction_date = ($last_transaction_result && $last_transaction_result->num_rows > 0) 
    ? $last_transaction_result->fetch_assoc()['last_date'] 
    : date('Y-m-d');
$last_transaction_date = $last_transaction_date ? date('Y-m-d', strtotime($last_transaction_date)) : date('Y-m-d');

// Base query
$sql = "SELECT 
            CONCAT(YEAR(DateAndTime), '-', LPAD(MONTH(DateAndTime), 2, '0')) AS month,
            SUM(amount_dispensed) AS total_dispensed
        FROM transaction";

// Adjust query based on period
if ($period === '7days') {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed
            FROM transaction
            WHERE DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} elseif ($period === '30days') {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed
            FROM transaction
            WHERE DateAndTime >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} elseif ($period === 'custom' && $start_date && $end_date) {
    $sql = "SELECT 
                DATE(DateAndTime) AS day,
                SUM(amount_dispensed) AS total_dispensed
            FROM transaction
            WHERE DateAndTime BETWEEN '$start_date' AND '$end_date'
            GROUP BY DATE(DateAndTime)
            ORDER BY DATE(DateAndTime)";
} else {
    $sql .= " GROUP BY YEAR(DateAndTime), MONTH(DateAndTime)
              ORDER BY YEAR(DateAndTime), MONTH(DateAndTime)";
}

$result = $conn->query($sql);

$historical_labels = [];
$historical_demands = [];
$time_indices = [];
$index = 0;

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $historical_labels[] = ($period === '7days' || $period === '30days' || $period === 'custom') ? $row["day"] : $row["month"];
        $historical_demands[] = (float)$row["total_dispensed"];
        $time_indices[] = $index;
        $index++;
    }
}

// Close connection
$conn->close();

// Function to calculate linear regression (slope and intercept)
function linear_regression($x, $y) {
    $n = count($x);
    if ($n == 0) return [0, 0]; // No data

    $sum_x = array_sum($x);
    $sum_y = array_sum($y);
    $sum_xy = 0;
    $sum_x2 = 0;

    for ($i = 0; $i < $n; $i++) {
        $sum_xy += $x[$i] * $y[$i];
        $sum_x2 += $x[$i] * $x[$i];
    }

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
    $intercept = ($sum_y - $slope * $sum_x) / $n;

    return [$slope, $intercept];
}

// Calculate regression if data available
if (count($time_indices) > 1) {
    list($slope, $intercept) = linear_regression($time_indices, $historical_demands);
} else {
    $slope = 0;
    $intercept = 0;
}

// Forecast next period (12 months for year, 7 days for 7/30 days)
$forecast_labels = [];
$forecast_demands = [];
$last_index = end($time_indices);
$last_label = end($historical_labels);

// Check if $last_label is valid before creating DateTime
if ($last_label) {
    $interval = ($period === '7days' || $period === '30days' || $period === 'custom') ? 'P1D' : 'P1M';
    $last_date = new DateTime($last_label);
    $forecast_length = ($period === '7days' || $period === '30days' || $period === 'custom') ? 7 : 12;

    for ($i = 1; $i <= $forecast_length; $i++) {
        $next_index = $last_index + $i;
        $predicted = $slope * $next_index + $intercept;
        $forecast_demands[] = max(0, $predicted);

        $last_date->add(new DateInterval($interval));
        $forecast_labels[] = ($period === '7days' || $period === '30days' || $period === 'custom') ? $last_date->format('Y-m-d') : $last_date->format('Y-m');
    }
}

// Combine historical and forecast for chart
$all_labels = array_merge($historical_labels, $forecast_labels);
$all_demands = array_merge($historical_demands, $forecast_demands);
$historical_count = count($historical_labels);

// Determine the forecast end date for the explanation
$forecast_end_date = end($forecast_labels);
$formatted_forecast_date = $forecast_end_date ? (new DateTime($forecast_end_date))->format('F j, Y') : 'September 25, 2025';

// Generate forecast explanation
$explanation = "At the start of August, water demand was high and followed a repeating pattern — some days used a lot (140L), some medium (70L), and a few small amounts (28L or 14L). But after August 10, usage dropped sharply, with only 10L recorded on August 25. By $formatted_forecast_date, the forecast is 0L, meaning no water demand is expected. In short, demand started strong, then declined, and is expected to stop completely.

<h4>Forecast Algorithm</h4>
The forecast was made using a method called linear regression, which looks at past " . ($period === '7days' || $period === '30days' || $period === 'custom' ? "daily" : "monthly") . " water usage and draws a straight-line trend. This line is then used to predict how much water will be used on $formatted_forecast_date. The method assumes that usage will follow the same pattern as before, and that no big changes will happen. If there are less than two " . ($period === '7days' || $period === '30days' || $period === 'custom' ? "days" : "months") . " of data, the forecast can’t be made.";
?>
<link rel="stylesheet" href="assets/css/forecast.css">
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
                    <label for="periodSelect">Period:</label>
                    <select id="periodSelect">
                        <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>7 Days Forecast</option>
                        <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>30 Days Forecast</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Yearly</option>
                        <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Date</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="chart-container">
            <canvas id="demandChart"></canvas>
        </div>
        <div class="description">
            <p>This chart shows the historical <?php echo ($period === '7days' || $period === '30days' || $period === 'custom') ? 'daily' : 'monthly'; ?> water demand (solid line) and a <?php echo $forecast_length; ?>-<?php echo ($period === '7days' || $period === '30days' || $period === 'custom') ? 'day' : 'month'; ?> forecast (dashed line) based on linear regression.</p>
            <p>Algorithm: Linear Regression (Least Squares method) applied to <?php echo ($period === '7days' || $period === '30days' || $period === 'custom') ? 'daily' : 'monthly'; ?> aggregated dispensed water amounts.</p>
        </div>
        <div class="forecast-explanation">
            <h3>Forecast Explanation</h3>
            <p><?php echo nl2br($explanation); ?></p>
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
                <input type="date" id="startDate" required value="<?php echo $start_date ? htmlspecialchars($start_date) : ''; ?>">
            </div>
            <div class="input-group">
                <label for="endDate">End Date</label>
                <input type="date" id="endDate" required value="<?php echo $end_date ? htmlspecialchars($end_date) : ''; ?>">
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
        labels: <?php echo json_encode($all_labels); ?>,
        datasets: [
            {
                label: 'Historical Water Demand (Liters)',
                data: <?php echo json_encode(array_merge($historical_demands, array_fill(0, count($forecast_demands), null))); ?>,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                fill: false,
                tension: 0.1,
                borderDash: [0, 0],
                pointBackgroundColor: 'rgba(75, 192, 192, 1)'
            },
            {
                label: 'Forecast Water Demand (Liters)',
                data: <?php echo json_encode(array_merge(array_fill(0, count($historical_demands), null), $forecast_demands)); ?>,
                borderColor: 'rgba(255, 99, 132, 1)',
                backgroundColor: 'rgba(255, 99, 132, 0.2)',
                fill: false,
                tension: 0.1,
                borderDash: [5, 5],
                pointBackgroundColor: 'rgba(255, 99, 132, 1)'
            }
        ]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Total Dispensed (Liters)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: '<?php echo ($period === '7days' || $period === '30days' || $period === 'custom') ? 'Day' : 'Month'; ?>'
                    }
                }
            },
            plugins: {
                annotation: {
                    annotations: {
                        line1: {
                            type: 'line',
                            xMin: '<?php echo $last_transaction_date; ?>',
                            xMax: '<?php echo $last_transaction_date; ?>',
                            borderColor: 'rgba(0, 0, 0, 0.5)',
                            borderWidth: 2,
                            borderDash: [6, 6],
                            label: {
                                content: 'Last Transaction: <?php echo date('F j, Y', strtotime($last_transaction_date)); ?>',
                                enabled: true,
                                position: 'top',
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
        }, 3000);
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