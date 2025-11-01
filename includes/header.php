<?php
// header.php
require_once 'includes/auth_check.php';
require_once 'includes/db_connect.php';

function getActiveClass($currentPage) {
    return basename($_SERVER['PHP_SELF']) === $currentPage ? 'active' : '';
}

$pageTitle = $pageTitle ?? 'Dashboard';

// Determine which transaction-related page is active
$current_page = basename($_SERVER['PHP_SELF']);
$is_transaction_page = ($current_page === 'transactions.php' || $current_page === 'accounting_and_calibration.php');
$transaction_page_title = ($current_page === 'accounting_and_calibration.php') ? 'Accounting and Calibration' : 'Transactions';
$transaction_page_url = ($current_page === 'accounting_and_calibration.php') ? 'accounting_and_calibration.php' : 'transactions.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Water Vending Admin</title>
    <link rel="stylesheet" href="assets/css/navigations.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
</head>
<body>
    <div class="dashboard-container">
        <header class="dashboard-header">
            <div class="header-left">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <img src="assets/images/logo-small.png" alt="Logo" class="logo-small">
                <div class="header-title">Smart Water Dashboard</div>
            </div>
            <div class="user-info">
                <div class="user-avatar" id="userAvatar"><?php echo strtoupper(substr($_SESSION['admin_username'], 0, 1)); ?></div>
                <span class="user-name" id="userName"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></span>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </header>
        
        <nav class="sidebar" id="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="dashboard.php" class="menu-link <?php echo getActiveClass('dashboard.php'); ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="locations.php" class="menu-link <?php echo getActiveClass('locations.php'); ?>">
                        <i class="fas fa-map-marker-alt"></i> Locations
                    </a>
                </li>
                <li class="menu-item">
                    <a href="machines.php" class="menu-link <?php echo getActiveClass('machines.php'); ?>">
                        <i class="fas fa-water"></i> Machines
                    </a>
                </li>
                <li class="menu-item">
                    <?php if ($current_page === 'accounting_and_calibration.php'): ?>
                        <!-- Show Accounting and Calibration as active when on that page -->
                        <a href="accounting_and_calibration.php" class="menu-link active">
                            <i class="fas fa-exchange-alt"></i> Accounting and Calibration
                        </a>
                    <?php else: ?>
                        <!-- Show Transactions for all other cases -->
                        <a href="transactions.php" class="menu-link <?php echo getActiveClass('transactions.php'); ?>">
                            <i class="fas fa-exchange-alt"></i> Transactions
                        </a>
                    <?php endif; ?>
                </li>
                <li class="menu-item">
                    <a href="coin_collections.php" class="menu-link <?php echo getActiveClass('coin_collections.php'); ?>">
                        <i class="fas fa-coins"></i> Coin Collections
                    </a>
                </li>
                <li class="menu-item">
                    <a href="water_levels.php" class="menu-link <?php echo getActiveClass('water_levels.php'); ?>">
                        <i class="fas fa-tint"></i> Water Levels
                    </a>
                </li>
                <li class="menu-item">
                    <a href="reports.php" class="menu-link <?php echo getActiveClass('reports.php'); ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </li>
                <li class="menu-item">
                    <a href="forecast.php" class="menu-link <?php echo getActiveClass('forecast.php'); ?>">
                        <i class="fas fa-chart-line"></i>Forecasts & Trends
                    </a>
                </li>
                <li class="menu-item">
                    <a href="backup.php" class="menu-link <?php echo getActiveClass('backup.php'); ?>">
                        <i class="fas fa-database"></i> System Backup
                    </a>
                </li>
            </ul>
        </nav>
        
        <main class="content-area">
        
        <?php require_once 'includes/user_profile_slide.php'; ?>
        
        <script>
document.addEventListener('DOMContentLoaded', function() {
    const userName = document.getElementById('userName');
    const userAvatar = document.getElementById('userAvatar');
    const profileSlide = document.getElementById('userProfileSlide');
    const closeBtn = document.getElementById('closeProfileSlide');
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    // Menu toggle for sidebar - SIMPLIFIED VERSION
    menuToggle.addEventListener('click', function() {
        sidebar.classList.toggle('active');
    });
    
    // Open slide panel when username OR avatar is clicked
    function openProfileSlide() {
        profileSlide.classList.add('open');
    }
    
    userName.addEventListener('click', function(event) {
        event.stopPropagation();
        openProfileSlide();
    });
    
    // Make user avatar clickable
    userAvatar.addEventListener('click', function(event) {
        event.stopPropagation();
        openProfileSlide();
    });
    
    // Close slide panel when close button is clicked
    closeBtn.addEventListener('click', function() {
        profileSlide.classList.remove('open');
    });
    
    // Close when clicking outside the slide panel
    document.addEventListener('click', function(event) {
        if (!profileSlide.contains(event.target) && event.target !== userName && event.target !== userAvatar) {
            profileSlide.classList.remove('open');
        }
    });
    
    // Auto-hide notification toast
    const toast = document.querySelector('.notification-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.style.display = 'none';
            }, 300);
        }, 3000);
    }

    // Force menu toggle functionality
    document.getElementById('menuToggle').addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('active');
    });

    // Add hover effect to user avatar
    userAvatar.style.cursor = 'pointer';
    userAvatar.style.transition = 'all 0.2s ease';
    
    userAvatar.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.1)';
        this.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    });
    
    userAvatar.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
        this.style.boxShadow = 'none';
    });

    // Add click effect to user avatar
    userAvatar.addEventListener('mousedown', function() {
        this.style.transform = 'scale(0.95)';
    });
    
    userAvatar.addEventListener('mouseup', function() {
        this.style.transform = 'scale(1.1)';
    });

});

// Global alert system for all pages
document.addEventListener('DOMContentLoaded', function() {
    let recurringAlertInterval = null;

    function initializeRecurringAlerts() {
        // Check if recurring alerts should be active
        fetch('api/check_alert_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.recurring_alerts_active) {
                    startRecurringAlert();
                }
            })
            .catch(error => console.error('Error checking alert status:', error));
    }

    function startRecurringAlert() {
        // Clear any existing interval first
        if (recurringAlertInterval) {
            clearInterval(recurringAlertInterval);
        }
        
        // Show first alert immediately
        showStickySuccessAlert();
        
        // Then show every 10 seconds
        recurringAlertInterval = setInterval(showStickySuccessAlert, 10000);
    }

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
    
    function removeStickyAlert(alertElement) {
        alertElement.style.animation = 'fadeOutUp 0.5s forwards';
        setTimeout(() => {
            if (alertElement.parentNode) {
                alertElement.parentNode.removeChild(alertElement);
            }
        }, 500);
    }

    // Stop recurring alerts function
    function stopRecurringAlerts() {
        if (recurringAlertInterval) {
            clearInterval(recurringAlertInterval);
            recurringAlertInterval = null;
        }
        
        // Remove all existing alerts
        const existingAlerts = document.querySelectorAll('.sticky-alert-success');
        existingAlerts.forEach(alert => {
            removeStickyAlert(alert);
        });
        
        // Update server status
        fetch('api/stop_recurring_alerts.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }).catch(error => console.error('Error stopping alerts:', error));
    }

    // Add global function to stop alerts (can be called from other pages)
    window.stopRecurringAlerts = stopRecurringAlerts;

    // Initialize on page load
    initializeRecurringAlerts();

    // Check for new alerts every 30 seconds
    setInterval(function() {
        fetch('api/check_alerts.php')
            .then(response => response.json())
            .then(data => {
                if (data.alerts_count > 0) {
                    // Check if modal should be shown
                    fetch('api/check_alert_status.php')
                        .then(response => response.json())
                        .then(statusData => {
                            if (!statusData.low_water_modal_shown) {
                                // Reload to show modal if not already shown
                                window.location.reload();
                            }
                        });
                }
            })
            .catch(error => console.error('Error checking alerts:', error));
    }, 30000);
});

</script>
</body>
</html>