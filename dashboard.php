<?php
//dashboard.php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Initialize session flag for low water modal
if (!isset($_SESSION['low_water_modal_shown'])) {
    $_SESSION['low_water_modal_shown'] = false;
}

// Get statistics
$dispensers = $pdo->query("SELECT COUNT(*) as total FROM dispenser")->fetch();
$active_locations = $pdo->query("SELECT COUNT(DISTINCT location_id) as total FROM dispenserlocation WHERE Status = 1")->fetch();
$recent_transactions = $pdo->query("SELECT COUNT(*) as total FROM transaction WHERE DateAndTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();
$alerts = $pdo->query("
    SELECT 
        d.dispenser_id,
        d.Description as machine_name,
        COALESCE(ds.water_level, 0) as water_level,
        d.Capacity,
        l.location_name
    FROM dispenser d
    JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
    JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
    LEFT JOIN location l ON dl.location_id = l.location_id
    WHERE ds.water_level < 2 AND dl.Status = 1
")->fetchAll();
$total_coins = $pdo->query("SELECT SUM(CAST(REGEXP_REPLACE(coin_type, '[^0-9]', '') AS UNSIGNED)) as total FROM transaction WHERE DateAndTime >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();

// Get recent transactions
$transactions = $pdo->query("SELECT t.transaction_id, t.amount_dispensed, t.DateAndTime, d.Description 
                            FROM transaction t
                            JOIN dispenser d ON t.dispenser_id = d.dispenser_id
                            ORDER BY t.DateAndTime DESC LIMIT 10")->fetchAll();

// Store alert count in session for cross-page access
$_SESSION['current_alert_count'] = count($alerts);
?>
<link rel="stylesheet" href="assets/css/maindashboard.css">
<style>
.sticky-alert-success {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    z-index: 10000;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideInRight 0.5s ease-out;
    max-width: 300px;
    border-left: 4px solid #2E7D32;
}

.sticky-alert-success .alert-icon {
    font-size: 1.5em;
}

.sticky-alert-success .alert-content {
    flex: 1;
}

.sticky-alert-success .alert-title {
    font-weight: bold;
    margin-bottom: 5px;
}

.sticky-alert-success .alert-message {
    font-size: 0.9em;
    opacity: 0.9;
}

.sticky-alert-success .close-alert {
    background: none;
    border: none;
    color: white;
    font-size: 1.2em;
    cursor: pointer;
    padding: 0;
    width: 25px;
    height: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.3s;
}

.sticky-alert-success .close-alert:hover {
    background-color: rgba(255,255,255,0.2);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOutUp {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(-20px);
        opacity: 0;
    }
}
</style>

<div class="content-area">
    <div class="content-wrapper">
        <div class="content-header">
            <h1 class="content-title">Dashboard Overview</h1>
            <div class="content-actions">
                <!-- Add Machine moved to stat card -->
            </div>
        </div>
        
        <div class="stats-grid">
            <a href="machines.php" class="stat-card machines">
                <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Machines</div>
                    <div class="stat-value"><?php echo $dispensers['total']; ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-arrow-up"></i> 12% from last month
                    </div>
                </div>
            </a>
            <a href="locations.php" class="stat-card locations">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Active Locations</div>
                    <div class="stat-value"><?php echo $active_locations['total']; ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-arrow-up"></i> 5% from last month
                    </div>
                </div>
            </a>
            <a href="transactions.php" class="stat-card transactions" id="showTransactions">
                <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Recent Transactions</div>
                    <div class="stat-value"><?php echo $recent_transactions['total']; ?></div>
                    <div class="stat-change danger">
                        <i class="fas fa-arrow-down"></i> 8% from last week
                    </div>
                </div>
            </a>
            <a href="coin_collections.php" class="stat-card coins">
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Coin Collections</div>
                    <div class="stat-value"><?php echo number_format($total_coins['total'] ?? 0); ?> PHP</div>
                    <div class="stat-change success">
                        <i class="fas fa-arrow-up"></i> Weekly Total
                    </div>
                </div>
            </a>
            <a href="water_levels.php" class="stat-card water-level">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Active Alerts</div>
                    <div class="water-level-bar">
                        <div class="water-level-fill <?php echo count($alerts) < 1 ? 'success' : (count($alerts) < 2 ? 'warning' : 'danger'); ?>" 
                             style="width: <?php echo min(count($alerts) * 20, 100); ?>%">
                            <?php echo count($alerts); ?> Low
                        </div>
                    </div>
                    <div class="stat-change">
                        <i class="fas fa-bell"></i> Needs attention
                    </div>
                </div>
            </a>
            <a href="machines.php?showAddModal=true" class="stat-card add-machine">
                <div class="stat-icon"><i class="fas fa-plus-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Add New Machine</div>
                    <div class="stat-value">Create</div>
                    <div class="stat-change"><i class="fas fa-arrow-right"></i> Go to Form</div>
                </div>
            </a>
        </div>
    </div>
</div>

<!-- Low Water Level Alert Modal (Floating Popup) -->
<?php if (!empty($alerts) && !$_SESSION['low_water_modal_shown']): ?>
<div class="modal floating-alert-modal" id="lowWaterModal" style="display: block;">
    <div class="modal-content floating-modal-content">
        <h2>🚨 Low Water Level Alert</h2>
        <div class="alert-list">
            <p><strong>Urgent Attention Required!</strong> The following machines have low water levels:</p>
            <div class="alert-scroll-container">
                <ul>
                    <?php foreach ($alerts as $alert): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($alert['machine_name']); ?></strong>
                            <br>Location: <?php echo htmlspecialchars($alert['location_name'] ?? 'Not Deployed'); ?>
                            <br>Water Level: <?php echo $alert['water_level']; ?>L / <?php echo $alert['Capacity']; ?>L
                            <br>Status: <span class="status-badge <?php echo $alert['water_level'] < 1 ? 'inactive' : 'warning'; ?>">
                                <?php echo $alert['water_level'] < 1 ? 'Critical' : 'Low'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        
        <div class="modal-actions">
            <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'admin'): ?>
                <h3>Quick Actions</h3>
                <form id="refillForm">
                    <div class="input-group">
                        <label for="refillDispenserId">Select Machine</label>
                        <select name="dispenser_id" id="refillDispenserId" required>
                            <?php foreach ($alerts as $alert): ?>
                                <option value="<?php echo $alert['dispenser_id']; ?>">
                                    <?php echo htmlspecialchars($alert['machine_name']) . ' (' . htmlspecialchars($alert['location_name'] ?? 'Not Deployed') . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <label for="refillAmount">Amount Added (Liters)</label>
                        <input type="number" id="refillAmount" name="amount" step="0.1" min="0.1" required>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-fill-drip"></i> Refill Now
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="water_levels.php" class="btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-tint"></i> View All Water Levels
                </a>
                
                <button type="button" class="btn-primary acknowledge-btn">
                    <i class="fas fa-check"></i> Acknowledge Alert
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Transactions Modal -->
<div class="modal" id="recentTransactionsModal">
    <div class="modal-content">
        <span class="close-modal">×</span>
        <h2>Recent Transactions</h2>
        <div class="transactions-scroll-container">
            <div class="transactions-list">
                <?php foreach($transactions as $transaction): ?>
                    <div class="transaction-item">
                        <div class="transaction-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <div class="transaction-details">
                            <div class="transaction-amount"><?php echo $transaction['amount_dispensed']; ?>L</div>
                            <div class="transaction-desc"><?php echo htmlspecialchars($transaction['Description']); ?></div>
                            <div class="transaction-time"><?php echo date('M j, h:i A', strtotime($transaction['DateAndTime'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Audio element for alert sound -->
<audio id="alertSound" preload="auto" loop>
    <source src="assets/sounds/alert.mp3" type="audio/mpeg">
    <source src="assets/sounds/alert.wav" type="audio/wav">
</audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Audio element for alert sound
    const alertSound = document.getElementById('alertSound');
    let soundInterval;
    
    // Auto start sound if alerts exist and modal is shown
    <?php if (!empty($alerts) && !$_SESSION['low_water_modal_shown']): ?>
    setTimeout(() => {
        startAlertSound();
    }, 1000);
    <?php endif; ?>

    // Function to start looping alert sound
    function startAlertSound() {
        if (alertSound) {
            alertSound.volume = 0.5; // Set volume to 50% for looping
            alertSound.loop = true; // Enable looping
            
            const playSound = () => {
                alertSound.play().catch(error => {
                    console.log('Audio play failed:', error);
                    // Retry every 2 seconds if failed
                    setTimeout(playSound, 2000);
                });
            };
            
            playSound(); // Initial play
            
            // Keep trying to play if it stops
            soundInterval = setInterval(() => {
                if (alertSound.paused) {
                    playSound();
                }
            }, 3000);
        }
    }

    // Function to stop alert sound
    function stopAlertSound() {
        if (alertSound) {
            alertSound.pause();
            alertSound.currentTime = 0;
            alertSound.loop = false;
            if (soundInterval) {
                clearInterval(soundInterval);
            }
        }
    }

    // Recent Transactions Modal
    const transactionsModal = document.getElementById('recentTransactionsModal');
    const showTransactions = document.querySelector('.stat-card.transactions');

    if (showTransactions) {
        showTransactions.addEventListener('click', function(e) {
            e.preventDefault();
            transactionsModal.style.display = 'block';
        });
    }

    // Close modals
    document.querySelectorAll('.close-modal').forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.style.display = 'none';
            if (modal.id === 'lowWaterModal') {
                stopAlertSound();
                setModalFlag();
            }
        });
    });

    // Acknowledge button for low water modal
    const acknowledgeBtn = document.querySelector('.acknowledge-btn');
    if (acknowledgeBtn) {
        acknowledgeBtn.addEventListener('click', function() {
            const lowWaterModal = document.getElementById('lowWaterModal');
            if (lowWaterModal) {
                lowWaterModal.style.display = 'none';
                stopAlertSound();
                setModalFlag();
                showStickySuccessAlert();
                // Start the recurring alert
                startRecurringAlert();
            }
        });
    }

    // Function to show sticky success alert
    function showStickySuccessAlert() {
        // Remove any existing sticky alerts first
        const existingAlerts = document.querySelectorAll('.sticky-alert-success');
        existingAlerts.forEach(alert => {
            removeStickyAlert(alert);
        });

        const stickyAlert = document.createElement('div');
        stickyAlert.className = 'sticky-alert-success';
        stickyAlert.innerHTML = `
            <div class="alert-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="alert-content">
                <div class="alert-title">Alert Acknowledged</div>
                <div class="alert-message">Low water level alert has been acknowledged and will be monitored</div>
            </div>
            <button class="close-alert" aria-label="Close alert">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        document.body.appendChild(stickyAlert);
        
        // Auto-remove after 8 seconds
        const autoRemove = setTimeout(() => {
            removeStickyAlert(stickyAlert);
        }, 8000);
        
        // Close button event
        const closeBtn = stickyAlert.querySelector('.close-alert');
        closeBtn.addEventListener('click', function() {
            clearTimeout(autoRemove);
            removeStickyAlert(stickyAlert);
        });
    }
    
    // Function to remove sticky alert with animation
    function removeStickyAlert(alertElement) {
        alertElement.style.animation = 'fadeOutUp 0.5s forwards';
        setTimeout(() => {
            if (alertElement.parentNode) {
                alertElement.parentNode.removeChild(alertElement);
            }
        }, 500);
    }

    // Function to start recurring alert every 10 seconds
    function startRecurringAlert() {
        // Show first alert immediately
        showStickySuccessAlert();
        
        // Then show every 10 seconds
        setInterval(showStickySuccessAlert, 10000);
    }

    // Check if we should start recurring alerts (if modal was previously acknowledged)
    function checkRecurringAlerts() {
        fetch('api/check_alert_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.recurring_alerts_active) {
                    startRecurringAlert();
                }
            })
            .catch(error => console.error('Error checking alert status:', error));
    }

    // Initialize recurring alerts check
    checkRecurringAlerts();

    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            const modal = event.target;
            modal.style.display = 'none';
            if (modal.id === 'lowWaterModal') {
                stopAlertSound();
                setModalFlag();
            }
        }
    });

    // Function to set modal flag
    function setModalFlag() {
        fetch('api/set_modal_flag.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                low_water_modal_shown: true,
                start_recurring_alerts: true 
            })
        }).catch(error => {
            console.error('Error setting modal flag:', error);
        });
    }

    // Refill form submission
    const refillForm = document.getElementById('refillForm');
    if (refillForm) {
        refillForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = parseFloat(document.getElementById('refillAmount').value);
            
            if (isNaN(amount) || amount <= 0) {
                showNotification('error', 'Please enter a valid amount greater than 0.');
                return;
            }

            const formData = new FormData(this);
            
            fetch('update_water_level.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message);
                    stopAlertSound();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotification('error', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('error', 'An error occurred while updating the water level');
            });
        });
    }

    // Show notification function
    function showNotification(type, message) {
        const toast = document.createElement('div');
        toast.className = `notification-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'fadeOut 0.5s forwards';
            setTimeout(() => toast.remove(), 500);
        }, 2500);
    }

    // Auto-check for alerts and show modal if new alerts appear
    function checkForNewAlerts() {
        fetch('api/check_alerts.php')
            .then(response => response.json())
            .then(data => {
                const lowWaterModal = document.getElementById('lowWaterModal');
                // Check if there are alerts and no modal is currently shown
                if (data.alerts_count > 0 && (!lowWaterModal || lowWaterModal.style.display === 'none')) {
                    // Check if we haven't already shown the modal in this session
                    fetch('api/check_alert_status.php')
                        .then(response => response.json())
                        .then(statusData => {
                            if (!statusData.low_water_modal_shown) {
                                window.location.reload();
                            }
                        });
                }
            })
            .catch(error => console.error('Error checking alerts:', error));
    }

    // Check for alerts every 30 seconds
    setInterval(checkForNewAlerts, 30000);
});
</script>

<?php require_once 'includes/footer.php'; ?>