<?php
//water_levels.php - Monitor and display water levels of dispensers
$pageTitle = 'Water Levels';
require_once 'includes/header.php';

// Handle refill action
if (isset($_POST['refill_dispenser'])) {
    $dispenserId = $_POST['dispenser_id'];
    $refillAmount = $_POST['refill_amount'];
    
    try {
        $pdo->beginTransaction();
        
        // Get current water level
        $stmt = $pdo->prepare("SELECT water_level FROM dispenserstatus WHERE dispenser_id = ?");
        $stmt->execute([$dispenserId]);
        $currentLevel = $stmt->fetchColumn();
        
        // Get dispenser capacity
        $stmt = $pdo->prepare("SELECT Capacity FROM dispenser WHERE dispenser_id = ?");
        $stmt->execute([$dispenserId]);
        $capacity = $stmt->fetchColumn();
        
        // Calculate new water level (don't exceed capacity)
        $newLevel = min($currentLevel + $refillAmount, $capacity);
        
        // Update water level
        $stmt = $pdo->prepare("UPDATE dispenserstatus SET water_level = ? WHERE dispenser_id = ?");
        $stmt->execute([$newLevel, $dispenserId]);
        
        $pdo->commit();
        $notification = "success|Dispenser refilled successfully! Water level updated to " . $newLevel . "L";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $notification = "error|Failed to refill dispenser: " . $e->getMessage();
    }
}

// Handle notification from refill action
$notification = $_GET['notification'] ?? '';

// Update operational_status in dispenserstatus based on water level and machine status
try {
    $pdo->beginTransaction();
    $stmt = $pdo->query("
        SELECT 
            d.dispenser_id,
            COALESCE(ds.water_level, 0) as water_level,
            COALESCE(dl.Status, 0) as machine_status
        FROM dispenser d
        LEFT JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
        LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
    ");
    $machines = $stmt->fetchAll();

    foreach ($machines as $machine) {
        $status = 'Normal';
        $waterLevel = (float)$machine['water_level'];
        if ($machine['machine_status'] == 1) {
            if ($waterLevel < 1) {
                $status = 'Critical';
            } elseif ($waterLevel < 2) {
                $status = 'Low';
            }
        } elseif ($machine['machine_status'] == 0) {
            $status = 'Disabled';
        }
        $stmt = $pdo->prepare("UPDATE dispenserstatus SET operational_status = ? WHERE dispenser_id = ?");
        $stmt->execute([$status, $machine['dispenser_id']]);
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $notification = 'error|Failed to update machine statuses: ' . $e->getMessage();
}

// Get water level data with machine information, including status
$waterLevels = $pdo->query("
    SELECT 
        d.dispenser_id,
        d.Description as machine_name,
        d.Capacity,
        COALESCE(ds.water_level, 0) as water_level,
        COALESCE(ds.operational_status, 'Normal') as operational_status,
        l.location_name,
        COALESCE(dl.Status, 0) as machine_status
    FROM dispenser d
    LEFT JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
    LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
    LEFT JOIN location l ON dl.location_id = l.location_id
    ORDER BY COALESCE(ds.water_level, 0) ASC
")->fetchAll();

// Count alerts
$lowWaterCount = 0;
$issueCount = 0;
foreach ($waterLevels as $level) {
    if ($level['machine_status'] == 1 && $level['water_level'] < 2) $lowWaterCount++;
    if ($level['machine_status'] == 1 && $level['operational_status'] != 'Normal') $issueCount++;
}
?>
<link rel="stylesheet" href="assets/css/water_levels.css">
<div class="content-area">
    <div class="content-wrapper">
        <!-- Notification Toast -->
        <?php if ($notification): ?>
        <div class="notification-toast <?= explode('|', $notification)[0] ?>">
            <?= explode('|', $notification)[1] ?>
        </div>
        <?php endif; ?>
        
        <div class="content-header">
            <h1 class="content-title">Water Level Monitoring</h1>
            <div class="content-actions">
                <button class="btn-primary" id="refreshLevels">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-title">Low Water Alerts</div>
                <div class="stat-value"><?= $lowWaterCount ?></div>
                <div class="stat-change warning">
                    <i class="fas fa-exclamation-triangle"></i> Needs Refill
                </div>
            </div>
            <div class="stat-card danger">
                <div class="stat-title">Operational Issues</div>
                <div class="stat-value"><?= $issueCount ?></div>
                <div class="stat-change danger">
                    <i class="fas fa-tools"></i> Needs Maintenance
                </div>
            </div>
        </div>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Machine</th>
                        <th>Location</th>
                        <th>Water Level</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($waterLevels as $level): 
                        $waterPercent = ($level['water_level'] / $level['Capacity']) * 100;
                        $statusClass = '';
                        if ($level['machine_status'] == 0) {
                            $statusClass = 'disabled';
                        } elseif ($level['water_level'] < 1) {
                            $statusClass = 'danger';
                        } elseif ($level['water_level'] < 2) {
                            $statusClass = 'warning';
                        } else {
                            $statusClass = 'success';
                        }
                        $badgeClass = '';
                        switch ($level['operational_status']) {
                            case 'Normal':
                                $badgeClass = 'active';
                                break;
                            case 'Low':
                                $badgeClass = 'warning';
                                break;
                            case 'Critical':
                                $badgeClass = 'inactive';
                                break;
                            case 'Disabled':
                                $badgeClass = 'disabled';
                                break;
                            default:
                                $badgeClass = 'active';
                        }
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($level['machine_name']) ?></td>
                        <td><?= htmlspecialchars($level['location_name'] ?? 'Not Deployed') ?></td>
                        <td>
                            <div class="water-level-bar">
                                <div class="water-level-fill <?= $statusClass ?>" 
                                     style="width: <?= $statusClass == 'disabled' ? 100 : $waterPercent ?>%">
                                    <?= $statusClass == 'disabled' ? 'Disabled' : $level['water_level'] . 'L / ' . $level['Capacity'] . 'L' ?>
                                </div>
                            </div>
                        </td>
                        <td><?= $level['Capacity'] ?>L</td>
                        <td>
                            <span class="status-badge <?= $badgeClass ?>">
                                <?= htmlspecialchars($level['operational_status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($level['machine_status'] == 1): ?>
                            <button class="btn-primary btn-sm refill-btn" 
                                    data-dispenser-id="<?= $level['dispenser_id'] ?>" 
                                    data-machine-name="<?= htmlspecialchars($level['machine_name']) ?>"
                                    data-current-level="<?= $level['water_level'] ?>"
                                    data-capacity="<?= $level['Capacity'] ?>">
                                <i class="fas fa-fill-drip"></i> Refill
                            </button>
                            <?php else: ?>
                            <span class="text-muted">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Refill Modal -->
<div id="refillModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Refill Water Dispenser</h2>
            <span class="close-modal">&times;</span>
        </div>
        <form method="POST" action="water_levels.php">
            <div class="modal-body">
                <input type="hidden" name="dispenser_id" id="refillDispenserId">
                <div class="input-group">
                    <label for="machineName">Machine:</label>
                    <input type="text" id="machineName" readonly class="readonly-input">
                </div>
                <div class="input-group">
                    <label for="currentLevel">Current Water Level:</label>
                    <input type="text" id="currentLevel" readonly class="readonly-input">
                </div>
                <div class="input-group">
                    <label for="capacity">Capacity:</label>
                    <input type="text" id="capacity" readonly class="readonly-input">
                </div>
                <div class="input-group">
                    <label for="refillAmount">Refill Amount (Liters):</label>
                    <input type="number" id="refillAmount" name="refill_amount" min="1" max="20" required 
                           placeholder="Enter amount to refill">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary close-modal">Cancel</button>
                <button type="submit" name="refill_dispenser" class="btn-primary">Refill</button>
            </div>
        </form>
    </div>
</div>

<script>
// Refresh button
document.getElementById('refreshLevels').addEventListener('click', function() {
    window.location.reload();
});

// Refill modal functionality
const refillModal = document.getElementById('refillModal');
const refillBtns = document.querySelectorAll('.refill-btn');
const closeModalBtns = document.querySelectorAll('.close-modal');

refillBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        const dispenserId = this.getAttribute('data-dispenser-id');
        const machineName = this.getAttribute('data-machine-name');
        const currentLevel = this.getAttribute('data-current-level');
        const capacity = this.getAttribute('data-capacity');
        
        document.getElementById('refillDispenserId').value = dispenserId;
        document.getElementById('machineName').value = machineName;
        document.getElementById('currentLevel').value = currentLevel + 'L';
        document.getElementById('capacity').value = capacity + 'L';
        
        // Set max refill amount based on capacity and current level
        const maxRefill = capacity - currentLevel;
        document.getElementById('refillAmount').max = maxRefill;
        document.getElementById('refillAmount').placeholder = `Max: ${maxRefill}L`;
        
        refillModal.style.display = 'block';
    });
});

closeModalBtns.forEach(btn => {
    btn.addEventListener('click', function() {
        refillModal.style.display = 'none';
    });
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === refillModal) {
        refillModal.style.display = 'none';
    }
});

// Auto-hide notification toast
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.querySelector('.notification-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>