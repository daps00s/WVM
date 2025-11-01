<?php
// backup.php
$pageTitle = 'System Backup';
require_once 'includes/db_connect.php';
require_once 'includes/auth_check.php';

// Set timezone to Philippines (Tarlac)
date_default_timezone_set('Asia/Manila');

// Backup directory
$backupDir = 'backups/';
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

// Settings file
$settingsFile = 'config/backup_settings.json';
if (!is_dir('config')) {
    mkdir('config', 0777, true);
}

// Load or initialize settings
if (!file_exists($settingsFile)) {
    $defaultSettings = [
        'frequency' => 'daily',
        'last_backup' => null
    ];
    file_put_contents($settingsFile, json_encode($defaultSettings, JSON_PRETTY_PRINT));
}
$settings = json_decode(file_get_contents($settingsFile), true);
$frequency = $settings['frequency'] ?? 'daily';
$last_backup = $settings['last_backup'] ?? null;

// Fixed backup time at 2:00 AM
$backup_hour = 2;
$backup_minute = 0;

// Function to create database backup
function createBackup($pdo, $backupDir, $isAuto = false) {
    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        
        $output = "-- Database Backup for water_dispenser_system\n";
        $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $output .= "-- Type: " . ($isAuto ? 'Automatic' : 'Manual') . "\n\n";

        foreach ($tables as $table) {
            $output .= "-- Table structure for $table\n\n";
            $createTable = $pdo->query("SHOW CREATE TABLE $table")->fetch();
            $output .= $createTable['Create Table'] . ";\n\n";

            $rows = $pdo->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $output .= "-- Data for $table\n";
                foreach ($rows as $row) {
                    $values = array_map(function($value) use ($pdo) {
                        return $value === null ? 'NULL' : $pdo->quote($value);
                    }, array_values($row));
                    $output .= "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                }
                $output .= "\n";
            }
        }

        $prefix = $isAuto ? 'auto_backup_' : 'manual_backup_';
        $filename = $backupDir . $prefix . date('Y-m-d_H-i-s') . '.sql';
        
        if (!file_put_contents($filename, $output)) {
            throw new Exception("Failed to write backup file");
        }
        
        // Implement retention policy: keep last 7 days of backups
        $retentionPeriod = 7 * 24 * 60 * 60;
        $backupFiles = glob($backupDir . '*.sql');
        foreach ($backupFiles as $file) {
            if (filemtime($file) < time() - $retentionPeriod) {
                unlink($file);
            }
        }
        
        return $filename;
    } catch (Exception $e) {
        error_log("Backup creation failed: " . $e->getMessage());
        throw $e;
    }
}

// Handle automatic backup check
if (isset($_GET['check_auto_backup']) && $_GET['check_auto_backup'] == '1') {
    try {
        $now = new DateTime();
        $lastBackupTime = $last_backup ? new DateTime($last_backup) : null;
        
        // Check if backup should run based on frequency
        $shouldRun = false;
        $currentHourMin = $now->format('H:i');
        $scheduledHourMin = sprintf('%02d:%02d', $backup_hour, $backup_minute);
        
        if ($frequency === 'daily') {
            $shouldRun = ($currentHourMin >= $scheduledHourMin);
        } elseif ($frequency === 'every_other_day' && $lastBackupTime) {
            $daysDiff = $now->diff($lastBackupTime)->days;
            $shouldRun = ($daysDiff >= 2 && $currentHourMin >= $scheduledHourMin);
        } elseif ($frequency === 'every_other_day' && !$lastBackupTime) {
            $shouldRun = ($currentHourMin >= $scheduledHourMin);
        } elseif ($frequency === 'every_month') {
            $currentDay = $now->format('j');
            $shouldRun = ($currentDay == 1 && $currentHourMin >= $scheduledHourMin);
        }

        if ($shouldRun) {
            $backupFile = createBackup($pdo, $backupDir, true);
            $settings['last_backup'] = $now->format('Y-m-d H:i:s');
            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
            // Trigger download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($backupFile) . '"');
            readfile($backupFile);
            exit;
        } else {
            echo json_encode(['status' => 'skipped', 'message' => 'Not time for backup yet']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Automatic backup failed: ' . $e->getMessage()]);
        exit;
    }
}

// Handle manual backup creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['backup'])) {
    try {
        $backupFile = createBackup($pdo, $backupDir, false);
        // Return JSON response to trigger client-side handling
        echo json_encode(['status' => 'success', 'filename' => $backupFile]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Backup failed: ' . $e->getMessage()]);
        exit;
    }
}

// Handle update backup settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $frequency = $_POST['frequency'];

    // Validate inputs
    $valid = true;
    $errorMsg = '';
    if (!in_array($frequency, ['daily', 'every_other_day', 'every_month'])) {
        $valid = false;
        $errorMsg = 'Invalid frequency selection.';
    }

    if (!$valid) {
        $notification = 'error|' . $errorMsg;
    } else {
        $settings['frequency'] = $frequency;
        $settings['last_backup'] = null; // Reset last backup to allow new backup
        file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
        $notification = 'success|Backup settings successfully updated!';
    }
}

// Handle delete
$notification = isset($notification) ? $notification : '';
if (isset($_GET['delete'])) {
    $file = $backupDir . basename($_GET['delete']);
    if (file_exists($file)) {
        if (unlink($file)) {
            $notification = 'success|Backup successfully deleted!';
            // Return JSON response for AJAX handling
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Backup successfully deleted!', 'filename' => basename($file)]);
            exit;
        } else {
            $notification = 'error|Failed to delete backup: ' . basename($file);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete backup: ' . basename($file)]);
            exit;
        }
    } else {
        $notification = 'error|Backup file not found: ' . basename($file);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Backup file not found: ' . basename($file)]);
        exit;
    }
}

// Check for message in URL
if (isset($_GET['message'])) {
    $notification = urldecode($_GET['message']);
}

// Get list of backups (sorted newest first)
$backupFiles = glob($backupDir . '*.sql');
rsort($backupFiles);

// Calculate next backup time for display
$next_backup = new DateTime();
$next_backup->setTime($backup_hour, $backup_minute);
$now = new DateTime();
if ($frequency === 'daily') {
    if ($next_backup <= $now) {
        $next_backup->modify('+1 day');
    }
} elseif ($frequency === 'every_other_day') {
    if ($last_backup) {
        $lastBackupTime = new DateTime($last_backup);
        $next_backup = clone $lastBackupTime;
        $next_backup->modify('+2 days');
        $next_backup->setTime($backup_hour, $backup_minute);
        while ($next_backup <= $now) {
            $next_backup->modify('+2 days');
        }
    } else {
        if ($next_backup <= $now) {
            $next_backup->modify('+2 days');
        }
    }
} elseif ($frequency === 'every_month') {
    $year = $now->format('Y');
    $month = $now->format('m');
    $next_backup->setDate($year, $month, 1);
    $next_backup->setTime($backup_hour, $backup_minute);
    if ($next_backup <= $now) {
        $next_backup->modify('+1 month');
        $next_backup->setDate($next_backup->format('Y'), $next_backup->format('m'), 1);
        $next_backup->setTime($backup_hour, $backup_minute);
    }
}

// Calculate backup stats
$total_backups = count($backupFiles);
$auto_backups = 0;
$manual_backups = 0;
$total_size = 0;

foreach ($backupFiles as $file) {
    if (strpos(basename($file), 'auto_backup_') === 0) {
        $auto_backups++;
    } else {
        $manual_backups++;
    }
    $total_size += filesize($file);
}

$total_size_mb = round($total_size / (1024 * 1024), 2);

require_once 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/machines.css">
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

/* Backup Cards */
.backup-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.backup-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.backup-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.backup-card h3 {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.backup-card p {
    font-size: 14px;
    color: #64748b;
    margin: 8px 0;
    line-height: 1.5;
}

.backup-card .btn {
    width: 100%;
    width: 200px;
    margin-left:170px;
    margin-top: 16px;
}

/* Control Panel */
.control-panel {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.period-select {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    background-color: white;
    min-width: 160px;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-top: 20px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th, .data-table td {
    padding: 16px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.data-table th {
    background: #f8fafc;
    font-weight: 600;
    color: #1e293b;
    font-size: 14px;
}

.data-table tr:hover {
    background: #f8fafc;
}

.backup-type-auto {
    color: #059669;
    font-weight: 500;
    font-size: 13px;
}

.backup-type-manual {
    color: #3b82f6;
    font-weight: 500;
    font-size: 13px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 6px;
    margin-right: 6px;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.btn-action.download {
    background: rgba(34, 197, 94, 0.1);
    color: #059669;
}

.btn-action.download:hover {
    background: #059669;
    color: white;
}

.btn-action.delete {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.btn-action.delete:hover {
    background: #ef4444;
    color: white;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1100;
    animation: slideIn 0.3s, fadeOut 0.5s 2.5s forwards;
    max-width: 400px;
}

.notification-toast.success {
    background: #059669;
}

.notification-toast.error {
    background: #ef4444;
}

.notification-toast.warning {
    background: #f59e0b;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    overflow: auto;
}

.modal-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    animation: fadeIn 0.3s ease;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.modal-header h2 {
    margin: 0;
    font-size: 20px;
    color: #1e293b;
    font-weight: 600;
}

.close-modal {
    font-size: 24px;
    color: #64748b;
    cursor: pointer;
    transition: color 0.2s;
}

.close-modal:hover {
    color: #1e293b;
}

.modal-body {
    padding: 20px;
}

.modal-footer {
    display: flex;
    gap: 12px;
    padding: 20px;
    border-top: 1px solid #e2e8f0;
    justify-content: flex-end;
}

.input-group {
    margin-bottom: 20px;
}

.input-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #1e293b;
    font-size: 14px;
}

.input-group input {
    width: 100%;
    padding: 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
    box-sizing: border-box;
}

.input-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 20px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: none;
    text-decoration: none;
}

.btn-primary {
    background: #3b82f6;
    color: white;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #64748b;
    color: white;
}

.btn-secondary:hover {
    background: #475569;
    transform: translateY(-1px);
}

.btn-danger {
    background: #ef4444;
    color: white;
}

.btn-danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Animations */
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

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Responsive Design */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .backup-cards {
        grid-template-columns: 1fr;
    }
    
    .content-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .control-panel {
        width: 100%;
        justify-content: flex-start;
    }
    
    .period-select {
        width: 100%;
    }
    
    .notification-toast {
        right: 15px;
        left: 15px;
        max-width: none;
    }
    
    .modal-content {
        margin: 20% auto;
        width: 95%;
    }
}

@media (max-width: 576px) {
    .content-wrapper {
        padding: 0 15px;
    }
    
    .stat-card {
        padding: 20px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .data-table {
        font-size: 14px;
    }
    
    .data-table th, .data-table td {
        padding: 12px 8px;
    }
}

/* Override to match forecast layout */
.content-area {
    padding: 30px 0 0 0 !important;
    background-color: var(--light) !important;
    width: 100% !important;
    margin-left: 0 !important;
}

.content-wrapper {
    padding: 0 30px !important;
    max-width: 100% !important;
    margin: 0 auto !important;
    min-height: auto !important;
    position: relative !important;
}

.content-header {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 30px !important;
    flex-direction: row !important;
    padding: 0 !important;
}

.content-title {
    font-size: 24px !important;
    color: var(--secondary) !important;
    font-weight: 600 !important;
    text-align: left !important;
}

.content-actions {
    display: flex !important;
    justify-content: flex-end !important;
    width: auto !important;
}
</style>

<div class="content-area">
    <div class="content-wrapper">
        <!-- Notification Toast -->
        <?php if ($notification): ?>
        <div class="notification-toast <?= explode('|', $notification)[0] ?>">
            <?= explode('|', $notification)[1] ?>
        </div>
        <?php endif; ?>

        <!-- Refresh Loading Modal -->
        <div id="refreshLoadingModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-body" style="text-align: center;">
                    <div class="loading-spinner"></div>
                    <p id="refreshLoadingMessage">Refreshing backup list...</p>
                </div>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div id="confirmModal" class="modal" style="display: none;">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modalTitle"></h2>
                    <span class="close-modal">&times;</span>
                </div>
                <div class="modal-body">
                    <p id="modalMessage"></p>
                    <div class="input-group" id="confirmInputGroup" style="display: none;">
                        <label for="confirmInput">Type CONFIRM to verify:</label>
                        <input type="text" id="confirmInput" class="form-control" placeholder="CONFIRM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button id="modalConfirm" class="btn btn-primary">Confirm</button>
                    <button id="modalCancel" class="btn btn-secondary">Cancel</button>
                </div>
            </div>
        </div>

        <!-- Header Section -->
        <div class="content-header">
            <h1 class="content-title">System Backup Management</h1>
            <div class="content-actions">
                <div class="control-panel">
                    <span style="font-size: 14px; color: #64748b;">Philippines - Tarlac Time (Asia/Manila)</span>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-database"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Backups</div>
                    <div class="stat-value"><?= $total_backups ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-archive"></i> All Time
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-robot"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Automatic Backups</div>
                    <div class="stat-value"><?= $auto_backups ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-cog"></i> System Generated
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-cog"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Manual Backups</div>
                    <div class="stat-value"><?= $manual_backups ?></div>
                    <div class="stat-change warning">
                        <i class="fas fa-hands"></i> User Created
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-hdd"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Size</div>
                    <div class="stat-value"><?= $total_size_mb ?>MB</div>
                    <div class="stat-change success">
                        <i class="fas fa-weight"></i> Storage Used
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Time Display -->
        <div class="backup-card">
            <h3><i class="fas fa-clock"></i> Current Time & Next Backup</h3>
            <p><strong>Current Time:</strong> <span id="currentTime"><?php echo date('Y-m-d h:i:s A'); ?></span></p>
            <p><strong>Next Scheduled Backup:</strong> <span id="nextBackup"><?php echo $next_backup->format('Y-m-d h:i A'); ?></span></p>
            <p><strong>Backup Frequency:</strong> <span class="backup-type-<?= $frequency === 'daily' ? 'auto' : 'manual' ?>">
                <?= ucfirst(str_replace('_', ' ', $frequency)) ?>
            </span></p>
        </div>

        <!-- Backup Cards Section -->
        <div class="backup-cards">
            <!-- Manual Backup Card -->
            <div class="backup-card">
                <h3><i class="fas fa-plus-circle"></i> Manual Backup</h3>
                <p>Create an immediate database backup. The backup file will be downloaded automatically.</p>
                <form method="POST" id="backupForm">
                    <button type="button" onclick="showModal('Create Backup', 'Are you sure you want to create a new manual backup?', handleManualBackup, false)" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Manual Backup
                    </button>
                    <input type="hidden" name="backup" value="1">
                </form>
            </div>

            <!-- Automatic Backup Settings Card -->
            <div class="backup-card">
                <h3><i class="fas fa-cog"></i> Automatic Backup Settings</h3>
                <p>Configure automatic backup frequency and schedule.</p>
                <form method="POST" id="settingsForm">
                    <div class="input-group">
                        <label for="frequency"><i class="fas fa-calendar-alt"></i> Backup Frequency</label>
                        <select id="frequency" name="frequency" class="period-select" required>
                            <option value="daily" <?= $frequency == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            <option value="every_other_day" <?= $frequency == 'every_other_day' ? 'selected' : ''; ?>>Every Other Day</option>
                            <option value="every_month" <?= $frequency == 'every_month' ? 'selected' : ''; ?>>Every Month (1st)</option>
                        </select>
                    </div>
                    <button type="button" onclick="showModal('Update Settings', 'Are you sure you want to update the automatic backup settings?', () => document.getElementById('settingsForm').submit(), false)" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                    <input type="hidden" name="update_settings" value="1">
                </form>
            </div>
        </div>

        <!-- Existing Backups Section -->
        <div class="table-container">
            <div style="padding: 20px;">
                <h3 style="margin: 0 0 20px 0; font-size: 18px; color: #1e293b; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-file-archive"></i> Existing Backups
                </h3>
                <?php if (empty($backupFiles)): ?>
                    <p style="text-align: center; color: #64748b; padding: 40px;">No backups found.</p>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Filename</th>
                                <th>Type</th>
                                <th>Date</th>
                                <th>Size</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="backupsTableBody">
                            <?php foreach ($backupFiles as $file): ?>
                                <?php
                                    $filename = basename($file);
                                    $isAuto = strpos($filename, 'auto_backup_') === 0;
                                    $fileSize = round(filesize($file) / (1024 * 1024), 2);
                                ?>
                                <tr data-filename="<?php echo htmlspecialchars($filename); ?>">
                                    <td><?php echo htmlspecialchars($filename); ?></td>
                                    <td class="backup-type-<?php echo $isAuto ? 'auto' : 'manual'; ?>">
                                        <?php echo $isAuto ? 'Automatic' : 'Manual'; ?>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', filemtime($file)); ?></td>
                                    <td><?php echo $fileSize; ?> MB</td>
                                    <td>
                                        <button class="btn-action download" onclick="showModal('Download Backup', 'Do you want to download <?php echo htmlspecialchars($filename); ?>?', () => handleDownload('<?php echo htmlspecialchars($file); ?>'), false)" title="Download">
                                            <i class="fas fa-download"></i>
                                        </button>
                                        <button class="btn-action delete" onclick="showModal('Delete Backup', 'Are you sure you want to delete <?php echo htmlspecialchars($filename); ?>?', () => handleDelete('<?php echo urlencode($filename); ?>', <?php echo $isAuto ? "'Automatic'" : "'Manual'"; ?>), true)" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Update current time every second
function updateTime() {
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        const now = new Date();
        const options = {
            timeZone: 'Asia/Manila',
            year: 'numeric',
            month: 'numeric',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        timeElement.textContent = now.toLocaleString('en-PH', options);
    }
}

// Store scheduled time and frequency
let scheduledTime = null;
let frequency = '<?php echo $frequency; ?>';
let lastBackup = '<?php echo $last_backup ?: ''; ?>';

function setScheduledTime() {
    const hour = 2; // Fixed at 2 AM
    const minute = 0;
    frequency = document.getElementById('frequency').value;

    const now = new Date();
    let scheduledDateTime = new Date();
    
    if (frequency === 'daily') {
        scheduledDateTime.setHours(hour, minute, 0, 0);
        if (scheduledDateTime <= now) {
            scheduledDateTime.setDate(scheduledDateTime.getDate() + 1);
        }
    } else if (frequency === 'every_other_day') {
        if (lastBackup) {
            let lastBackupDate = new Date(lastBackup);
            scheduledDateTime = new Date(lastBackupDate);
            scheduledDateTime.setDate(lastBackupDate.getDate() + 2);
            scheduledDateTime.setHours(hour, minute, 0, 0);
            while (scheduledDateTime <= now) {
                scheduledDateTime.setDate(scheduledDateTime.getDate() + 2);
            }
        } else {
            scheduledDateTime.setHours(hour, minute, 0, 0);
            if (scheduledDateTime <= now) {
                scheduledDateTime.setDate(scheduledDateTime.getDate() + 2);
            }
        }
    } else if (frequency === 'every_month') {
        const year = now.getFullYear();
        const month = now.getMonth();
        scheduledDateTime.setDate(1);
        scheduledDateTime.setHours(hour, minute, 0, 0);
        if (scheduledDateTime <= now) {
            scheduledDateTime.setMonth(month + 1);
            scheduledDateTime.setDate(1);
            scheduledDateTime.setHours(hour, minute, 0, 0);
        }
    }
    
    scheduledTime = scheduledDateTime;
    
    // Update next backup display
    const nextBackup = document.getElementById('nextBackup');
    nextBackup.textContent = scheduledDateTime.toLocaleString('en-PH', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });
}

function checkSchedule() {
    if (scheduledTime) {
        const now = new Date();
        const nowInManila = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Manila' }));
        if (nowInManila.getTime() >= scheduledTime.getTime()) {
            const loadingIndicator = document.getElementById('loadingIndicator');
            loadingIndicator.style.display = 'flex';
            
            fetch('backup.php?check_auto_backup=1')
                .then(response => {
                    if (response.headers.get('content-type')?.includes('application/octet-stream')) {
                        return response.blob().then(blob => {
                            const url = window.URL.createObjectURL(blob);
                            const a = document.createElement('a');
                            a.href = url;
                            a.download = response.headers.get('content-disposition')?.split('filename=')[1]?.replace(/"/g, '') || 'backup.sql';
                            document.body.appendChild(a);
                            a.click();
                            document.body.removeChild(a);
                            window.URL.revokeObjectURL(url);
                            showRefreshLoading('Refreshing backup list...');
                            return { status: 'success', message: 'Automatic backup successfully created!' };
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    loadingIndicator.style.display = 'none';
                    if (data.status !== 'skipped') {
                        const toast = document.createElement('div');
                        toast.className = `notification-toast ${data.status}`;
                        toast.innerHTML = `<span>${data.message}</span>`;
                        document.body.appendChild(toast);
                        setTimeout(() => {
                            toast.remove();
                        }, 3000);
                        
                        if (data.status === 'success') {
                            // Refresh backup list
                            fetchBackupList();
                            // Update last backup and scheduled time
                            lastBackup = new Date().toISOString();
                            if (frequency === 'daily') {
                                scheduledTime.setDate(scheduledTime.getDate() + 1);
                            } else if (frequency === 'every_other_day') {
                                scheduledTime.setDate(scheduledTime.getDate() + 2);
                            } else if (frequency === 'every_month') {
                                scheduledTime.setMonth(scheduledTime.getMonth() + 1);
                                scheduledDateTime.setDate(1);
                            }
                            const nextBackup = document.getElementById('nextBackup');
                            nextBackup.textContent = scheduledTime.toLocaleString('en-PH', {
                                timeZone: 'Asia/Manila',
                                year: 'numeric',
                                month: 'numeric',
                                day: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                        }
                    }
                })
                .catch(error => {
                    loadingIndicator.style.display = 'none';
                    const toast = document.createElement('div');
                    toast.className = 'notification-toast error';
                    toast.innerHTML = `<span>Error checking automatic backup: ${error.message}</span>`;
                    document.body.appendChild(toast);
                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
        }
    }
}

function fetchBackupList() {
    fetch('backup.php')
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTableBody = doc.querySelector('#backupsTableBody');
            if (newTableBody) {
                const currentTableBody = document.querySelector('#backupsTableBody');
                currentTableBody.innerHTML = newTableBody.innerHTML;
            }
            const refreshModal = document.getElementById('refreshLoadingModal');
            refreshModal.style.display = 'none';
            const noBackups = document.querySelector('.no-backups');
            if (noBackups && newTableBody.children.length > 0) {
                noBackups.remove();
            } else if (!noBackups && newTableBody.children.length === 0) {
                const backupList = document.querySelector('.backup-list');
                const noBackupsMessage = document.createElement('p');
                noBackupsMessage.className = 'no-backups';
                noBackupsMessage.textContent = 'No backups found.';
                backupList.appendChild(noBackupsMessage);
            }
        })
        .catch(error => {
            const refreshModal = document.getElementById('refreshLoadingModal');
            refreshModal.style.display = 'none';
            const toast = document.createElement('div');
            toast.className = 'notification-toast error';
            toast.innerHTML = `<span>Error refreshing backup list: ${error.message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        });
}

function showModal(title, message, onConfirm, requireConfirmText = false, backupType = '') {
    const modal = document.getElementById('confirmModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalMessage = document.getElementById('modalMessage');
    const modalConfirm = document.getElementById('modalConfirm');
    const modalCancel = document.getElementById('modalCancel');
    const confirmInputGroup = document.getElementById('confirmInputGroup');
    const confirmInput = document.getElementById('confirmInput');

    modalTitle.textContent = title;
    modalMessage.textContent = message;
    confirmInputGroup.style.display = requireConfirmText ? 'block' : 'none';
    confirmInput.value = '';
    modal.style.display = 'block';

    modalConfirm.onclick = () => {
        if (requireConfirmText && confirmInput.value !== 'CONFIRM') {
            const toast = document.createElement('div');
            toast.className = 'notification-toast error';
            toast.innerHTML = `<span>Please type CONFIRM to verify deletion</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
            return;
        }
        onConfirm();
        modal.style.display = 'none';
    };

    modalCancel.onclick = () => {
        modal.style.display = 'none';
    };
}

function showRefreshLoading(message) {
    const refreshModal = document.getElementById('refreshLoadingModal');
    const refreshMessage = document.getElementById('refreshLoadingMessage');
    refreshMessage.textContent = message;
    refreshModal.style.display = 'block';
}

function handleDownload(url) {
    const a = document.createElement('a');
    a.href = url;
    a.download = url.split('/').pop();
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    // Show success notification
    const toast = document.createElement('div');
    toast.className = 'notification-toast success';
    toast.innerHTML = `<span>Backup successfully downloaded!</span>`;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 3000);
    
    showRefreshLoading('Refreshing backup list...');
    setTimeout(fetchBackupList, 1000); // Refresh table after download
}

function handleDelete(filename, backupType) {
    const refreshModal = document.getElementById('refreshLoadingModal');
    refreshModal.querySelector('p').textContent = `Deleting ${backupType} backup...`;
    refreshModal.style.display = 'block';
    
    fetch(`backup.php?delete=${filename}`)
        .then(response => response.json())
        .then(data => {
            refreshModal.style.display = 'none';
            const toast = document.createElement('div');
            toast.className = `notification-toast ${data.status}`;
            toast.innerHTML = `<span>${data.message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);

            if (data.status === 'success') {
                // Remove the deleted row from the table
                const row = document.querySelector(`tr[data-filename="${decodeURIComponent(filename)}"]`);
                if (row) {
                    row.remove();
                }
                // Check if table is empty and update UI accordingly
                const tableBody = document.querySelector('#backupsTableBody');
                if (tableBody.children.length === 0) {
                    const backupList = document.querySelector('.backup-list');
                    const noBackupsMessage = document.createElement('p');
                    noBackupsMessage.className = 'no-backups';
                    noBackupsMessage.textContent = 'No backups found.';
                    backupList.appendChild(noBackupsMessage);
                }
            }
        })
        .catch(error => {
            refreshModal.style.display = 'none';
            const toast = document.createElement('div');
            toast.className = 'notification-toast error';
            toast.innerHTML = `<span>Error deleting backup: ${error.message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        });
}

function handleManualBackup() {
    const form = document.getElementById('backupForm');
    const formData = new FormData(form);
    
    const refreshModal = document.getElementById('refreshLoadingModal');
    refreshModal.querySelector('p').textContent = 'Creating Manual backup...';
    refreshModal.style.display = 'block';
    
    fetch('backup.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const a = document.createElement('a');
                a.href = data.filename;
                a.download = data.filename.split('/').pop();
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                
                const toast = document.createElement('div');
                toast.className = 'notification-toast success';
                toast.innerHTML = `<span>Backup successfully created!</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.remove();
                }, 3000);
                
                showRefreshLoading('Refreshing backup list...');
                setTimeout(fetchBackupList, 1000); // Refresh table after creation
            } else {
                refreshModal.style.display = 'none';
                const toast = document.createElement('div');
                toast.className = 'notification-toast error';
                toast.innerHTML = `<span>${data.message}</span>`;
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }
        })
        .catch(error => {
            refreshModal.style.display = 'none';
            const toast = document.createElement('div');
            toast.className = 'notification-toast error';
            toast.innerHTML = `<span>Error creating backup: ${error.message}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.remove();
            }, 3000);
        });
}

// Close modal when clicking X or outside
function setupModalCloseHandlers() {
    // Close when clicking X button
    document.querySelectorAll('.close-modal').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display = 'none';
            }
        });
    });

    // Close when clicking outside modal content
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === this) {
                this.style.display = 'none';
            }
        });
    });
}

// Initialize modal close handlers when DOM is loaded
setupModalCloseHandlers();

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide notification toast
    const toast = document.querySelector('.notification-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
    
    // Initialize scheduled time
    setScheduledTime();
    
    // Update time every second
    updateTime();
    setInterval(updateTime, 1000);
    
    // Check schedule every second
    setInterval(checkSchedule, 1000);
});
</script>

<?php require_once 'includes/footer.php'; ?>