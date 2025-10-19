<?php
$pageTitle = 'Machines';
require_once 'includes/header.php';

// Handle form submissions
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_machine'])) {
        // Add new machine
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO dispenser (Description, Capacity) VALUES (?, ?)");
            if ($stmt->execute([$_POST['description'], $_POST['capacity']])) {
                $machineId = $pdo->lastInsertId();
                // Insert initial status
                $stmt = $pdo->prepare("INSERT INTO dispenserstatus (water_level, operational_status, dispenser_id) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['capacity'], 'Normal', $machineId]);
                // Set location if provided
                if (!empty($_POST['location_id'])) {
                    $stmt = $pdo->prepare("INSERT INTO dispenserlocation (location_id, dispenser_id, Status, DateDeployed) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$_POST['location_id'], $machineId, $_POST['status']]);
                }
                $pdo->commit();
                $notification = 'success|Machine successfully added!';
            } else {
                $pdo->rollBack();
                $notification = 'error|Failed to add machine.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $notification = 'error|Failed to add machine: ' . $e->getMessage();
        }
    } elseif (isset($_POST['edit_machine'])) {
        // Edit existing machine
        try {
            // If location_id is empty, force status to Disabled
            $status = !empty($_POST['location_id']) ? $_POST['status'] : 0;

            // Check if status is being set to Enabled and no location is selected
            if ($status == '1' && empty($_POST['location_id'])) {
                $notification = 'error|Cannot enable machine: Deploy the machine to a location first.';
            } else {
                // Check if any changes were made
                $stmt = $pdo->prepare("
                    SELECT d.Description, d.Capacity, dl.location_id, dl.Status
                    FROM dispenser d
                    LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
                    WHERE d.dispenser_id = ?
                ");
                $stmt->execute([$_POST['machine_id']]);
                $current = $stmt->fetch();

                $noChanges = (
                    $current['Description'] === $_POST['description'] &&
                    $current['Capacity'] == $_POST['capacity'] &&
                    ($current['location_id'] == ($_POST['location_id'] ?: null)) &&
                    $current['Status'] == $status
                );

                if ($noChanges) {
                    $notification = 'success|Machine saved successfully (no changes made).';
                } else {
                    $pdo->beginTransaction();
                    // Update dispenser details
                    $stmt = $pdo->prepare("UPDATE dispenser SET Description = ?, Capacity = ? WHERE dispenser_id = ?");
                    if ($stmt->execute([$_POST['description'], $_POST['capacity'], $_POST['machine_id']])) {
                        // Handle location
                        $stmt = $pdo->prepare("SELECT * FROM dispenserlocation WHERE dispenser_id = ?");
                        $stmt->execute([$_POST['machine_id']]);
                        $hasLocation = $stmt->rowCount() > 0;

                        if (empty($_POST['location_id'])) {
                            // Remove location and set status to Disabled
                            if ($hasLocation) {
                                $stmt = $pdo->prepare("DELETE FROM dispenserlocation WHERE dispenser_id = ?");
                                $stmt->execute([$_POST['machine_id']]);
                            }
                        } else {
                            // Update or insert location
                            if ($hasLocation) {
                                $stmt = $pdo->prepare("UPDATE dispenserlocation SET location_id = ?, Status = ?, DateDeployed = NOW() WHERE dispenser_id = ?");
                                $stmt->execute([$_POST['location_id'], $status, $_POST['machine_id']]);
                            } else {
                                $stmt = $pdo->prepare("INSERT INTO dispenserlocation (location_id, dispenser_id, Status, DateDeployed) VALUES (?, ?, ?, NOW())");
                                $stmt->execute([$_POST['location_id'], $_POST['machine_id'], $status]);
                            }
                        }
                        // Adjust water_level if capacity is reduced
                        $stmt = $pdo->prepare("UPDATE dispenserstatus SET water_level = LEAST(water_level, ?) WHERE dispenser_id = ?");
                        $stmt->execute([$_POST['capacity'], $_POST['machine_id']]);
                        $pdo->commit();
                        $notification = 'success|Machine successfully updated!';
                    } else {
                        $pdo->rollBack();
                        $notification = 'error|Failed to update machine.';
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $notification = 'error|Failed to update machine: ' . $e->getMessage();
        }
    } elseif (isset($_POST['delete_machine'])) {
        // Delete machine
        try {
            $pdo->beginTransaction();
            // Delete dependent records
            $stmt = $pdo->prepare("DELETE FROM transaction WHERE dispenser_id = ?");
            $stmt->execute([$_POST['machine_id']]);
            $stmt = $pdo->prepare("DELETE FROM dispenserstatus WHERE dispenser_id = ?");
            $stmt->execute([$_POST['machine_id']]);
            $stmt = $pdo->prepare("DELETE FROM dispenserlocation WHERE dispenser_id = ?");
            $stmt->execute([$_POST['machine_id']]);
            // Delete from dispenser
            $stmt = $pdo->prepare("DELETE FROM dispenser WHERE dispenser_id = ?");
            if ($stmt->execute([$_POST['machine_id']])) {
                $pdo->commit();
                $notification = 'success|Machine successfully deleted!';
            } else {
                $pdo->rollBack();
                $notification = 'error|Failed to delete machine.';
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $notification = 'error|Cannot delete machine due to database error.';
        }
    } elseif (isset($_POST['update_status'])) {
        // Update machine status
        try {
            // Check if status is being set to Enabled and location is Not Deployed
            $stmt = $pdo->prepare("SELECT dl.location_id FROM dispenserlocation dl WHERE dl.dispenser_id = ?");
            $stmt->execute([$_POST['machine_id']]);
            $location = $stmt->fetch();
            if ($_POST['status'] == '1' && !$location) {
                $notification = 'error|Cannot enable machine: Deploy the machine to a location first.';
            } else {
                $stmt = $pdo->prepare("UPDATE dispenserlocation SET Status = ? WHERE dispenser_id = ?");
                if ($stmt->execute([$_POST['status'], $_POST['machine_id']])) {
                    $notification = 'success|Machine status successfully updated!';
                } else {
                    $notification = 'error|Failed to update machine status.';
                }
            }
        } catch (PDOException $e) {
            $notification = 'error|Failed to update machine status: ' . $e->getMessage();
        }
    }
}

// Get all machines with their locations and status
$machines = $pdo->query("
    SELECT d.*, dl.Status, dl.location_id, l.location_name, ds.water_level, ds.operational_status
    FROM dispenser d
    LEFT JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
    LEFT JOIN location l ON dl.location_id = l.location_id
    LEFT JOIN dispenserstatus ds ON d.dispenser_id = ds.dispenser_id
")->fetchAll();

// Get all locations for dropdowns
$locations = $pdo->query("SELECT * FROM location ORDER BY location_name")->fetchAll();

// Get statistics for stat cards
$total_machines = $pdo->query("SELECT COUNT(*) as total FROM dispenser")->fetch();
$active_machines = $pdo->query("SELECT COUNT(*) as total FROM dispenserlocation WHERE Status = 1")->fetch();
$deployed_machines = $pdo->query("SELECT COUNT(*) as total FROM dispenserlocation WHERE location_id IS NOT NULL")->fetch();
$total_capacity = $pdo->query("SELECT SUM(Capacity) as total FROM dispenser")->fetch();

// Check if we should show the add modal
$showAddModal = isset($_GET['showAddModal']) && $_GET['showAddModal'] == 'true';
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
        <!-- Notification Toast -->
        <?php if ($notification): ?>
        <div class="notification-toast <?= explode('|', $notification)[0] ?>">
            <?= explode('|', $notification)[1] ?>
        </div>
        <?php endif; ?>
        
        <div class="content-header">
            <h1 class="content-title">Machine Management</h1>
            <div class="content-actions">
                <div class="search-group">
                    <label for="searchInput" class="search-label">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search machines...">
                </div>
                <div class="rows-per-page">
                    <label for="rowsPerPage" class="rows-label">Rows per page:</label>
                    <select id="rowsPerPage">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="30">30</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div>
                    <button class="btn-primary" id="addMachineBtn">
                        <i class="fas fa-plus"></i> <span class="btn-text">Add New Machine</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Machines</div>
                    <div class="stat-value"><?= $total_machines['total'] ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-microchip"></i> All Dispensers
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-play-circle"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Active Machines</div>
                    <div class="stat-value"><?= $active_machines['total'] ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-check-circle"></i> Currently Running
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Deployed Machines</div>
                    <div class="stat-value"><?= $deployed_machines['total'] ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-location-arrow"></i> At Locations
                    </div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Capacity</div>
                    <div class="stat-value"><?= number_format($total_capacity['total'] ?? 0) ?>L</div>
                    <div class="stat-change success">
                        <i class="fas fa-water"></i> Combined Storage
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table" id="machinesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Description</th>
                            <th class="capacity-col">Capacity</th>
                            <th class="water-col">Water Level</th>
                            <th class="location-col">Location</th>
                            <th class="status-col">Status</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($machines as $machine): ?>
                        <tr>
                            <td><?= $machine['dispenser_id'] ?></td>
                            <td><?= htmlspecialchars($machine['Description']) ?></td>
                            <td class="capacity-col"><?= $machine['Capacity'] ?>L</td>
                            <td class="water-col"><?= number_format($machine['water_level'] ?? 0, 1) ?>L</td>
                            <td class="location-col"><?= htmlspecialchars($machine['location_name'] ?? 'Not Deployed') ?></td>
                            <td class="status-col">
                                <button class="status-btn <?= $machine['Status'] == 1 ? 'enabled' : 'disabled' ?>" 
                                        onclick="showStatusModal(<?= $machine['dispenser_id'] ?>, <?= $machine['Status'] ?? 0 ?>, '<?= addslashes($machine['location_name'] ?? 'Not Deployed') ?>')">
                                    <?= $machine['Status'] == 1 ? 'Enabled' : 'Disabled' ?>
                                </button>
                            </td>
                            <td class="actions-col">
                                <div class="action-buttons">
                                    <button class="btn-action edit" onclick="showEditModal(
                                        <?= $machine['dispenser_id'] ?>, 
                                        '<?= addslashes($machine['Description']) ?>', 
                                        <?= $machine['Capacity'] ?>, 
                                        <?= $machine['location_id'] ?? 'null' ?>, 
                                        <?= $machine['Status'] ?? 0 ?>,
                                        '<?= addslashes($machine['location_name'] ?? 'Not Deployed') ?>'
                                    )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-action delete" onclick="showDeleteModal(<?= $machine['dispenser_id'] ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="pagination-container">
            <div class="pagination" id="pagination">
                <button id="prevBtn" class="pagination-btn">Previous</button>
                <span id="pageIndicator" class="page-indicator">1/1</span>
                <button id="nextBtn" class="pagination-btn">Next</button>
            </div>
        </div>
    </div>
</div>

<!-- Add Machine Modal -->
<div class="modal" id="addMachineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Machine</h2>
            <span class="close-modal">×</span>
        </div>
        <form method="POST">
            <div class="modal-body">
                <div class="input-group">
                    <label for="add_description">Description</label>
                    <input type="text" id="add_description" name="description" required>
                </div>
                <div class="input-group">
                    <label for="add_capacity">Capacity (Liters)</label>
                    <input type="number" id="add_capacity" name="capacity" min="1" step="0.1" required>
                </div>
                <div class="input-group">
                    <label for="add_location">Location</label>
                    <select id="add_location" name="location_id">
                        <option value="">-- Select Location --</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?= $location['location_id'] ?>"><?= htmlspecialchars($location['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label for="add_status">Status</label>
                    <select id="add_status" name="status" required>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="add_machine" class="btn-primary">Save Machine</button>
                <button type="button" class="btn-secondary" onclick="closeModal('addMachineModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Machine Modal -->
<div class="modal" id="editMachineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Machine</h2>
            <span class="close-modal">×</span>
        </div>
        <form method="POST" id="editMachineForm">
            <div class="modal-body">
                <input type="hidden" name="machine_id" id="edit_machine_id">
                <div class="input-group">
                    <label for="edit_description">Description</label>
                    <input type="text" id="edit_description" name="description" required>
                </div>
                <div class="input-group">
                    <label for="edit_capacity">Capacity (Liters)</label>
                    <input type="number" id="edit_capacity" name="capacity" min="1" step="0.1" required>
                </div>
                <div class="input-group">
                    <label for="edit_location">Location</label>
                    <select id="edit_location" name="location_id">
                        <option value="">-- Select Location --</option>
                        <?php foreach ($locations as $location): ?>
                        <option value="<?= $location['location_id'] ?>"><?= htmlspecialchars($location['location_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required exaltation="mandatory" required>
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="edit_machine" class="btn-primary">Save Changes</button>
                <button type="button" class="btn-secondary" onclick="closeModal('editMachineModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Status Update Modal -->
<div class="modal" id="statusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Update Machine Status</h2>
            <span class="close-modal">×</span>
        </div>
        <form method="POST" id="statusForm">
            <div class="modal-body">
                <input type="hidden" name="machine_id" id="status_machine_id">
                <div class="input-group">
                    <label>Current Status</label>
                    <p id="current_status_text"></p>
                </div>
                <div class="input-group" id="status_message" style="display: none;">
                    <p style="color: #e74c3c;">Cannot enable machine: Deploy the machine to a location first.</p>
                </div>
                <div class="input-group">
                    <label for="new_status">New Status</label>
                    <select id="new_status" name="status" required>
                        <option value="0">Disabled</option>
                        <option value="1">Enabled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" name="update_status" class="btn-primary">Update Status</button>
                <button type="button" class="btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteMachineModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Confirm Deletion</h2>
            <span class="close-modal">×</span>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete this machine? This action will also remove all associated transactions and status records and cannot be undone.</p>
        </div>
        <form method="POST">
            <input type="hidden" name="machine_id" id="delete_machine_id">
            <div class="modal-footer">
                <button type="submit" name="delete_machine" class="btn-danger">Delete Machine</button>
                <button type="button" class="btn-secondary" onclick="closeModal('deleteMachineModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Deploy Alert Modal -->
<div class="modal" id="deployAlertModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Deployment Required</h2>
            <span class="close-modal">×</span>
        </div>
        <div class="modal-body">
            <p>Cannot enable machine: Deploy the machine to a location first.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-primary" onclick="closeModal('deployAlertModal')">OK</button>
        </div>
    </div>
</div>

<script>
// State management
let currentPage = 1;
let rowsPerPage = 10;
let searchTerm = '';
let totalPages = 1;

// Filter and paginate table
function filterAndPaginate() {
    const rows = document.querySelectorAll('#machinesTable tbody tr');
    const filteredRows = [];
    
    // Filter rows based on search term
    rows.forEach(row => {
        const id = row.cells[0].textContent.toLowerCase();
        const description = row.cells[1].textContent.toLowerCase();
        const capacity = row.cells[2].textContent.toLowerCase();
        const waterLevel = row.cells[3].textContent.toLowerCase();
        const location = row.cells[4].textContent.toLowerCase();
        const status = row.cells[5].textContent.toLowerCase();
        
        if (
            id.includes(searchTerm.toLowerCase()) ||
            description.includes(searchTerm.toLowerCase()) ||
            capacity.includes(searchTerm.toLowerCase()) ||
            waterLevel.includes(searchTerm.toLowerCase()) ||
            location.includes(searchTerm.toLowerCase()) ||
            status.includes(searchTerm.toLowerCase())
        ) {
            filteredRows.push(row);
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Calculate pagination
    const totalRows = filteredRows.length;
    totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / parseInt(rowsPerPage));
    currentPage = Math.min(currentPage, Math.max(1, totalPages));
    
    // Show/hide rows based on current page
    filteredRows.forEach((row, index) => {
        if (rowsPerPage === 'all') {
            row.style.display = '';
        } else {
            const start = (currentPage - 1) * parseInt(rowsPerPage);
            const end = start + parseInt(rowsPerPage);
            row.style.display = (index >= start && index < end) ? '' : 'none';
        }
    });
    
    // Update pagination controls
    updatePagination();
}

// Update pagination controls
function updatePagination() {
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const pageIndicator = document.getElementById('pageIndicator');
    
    if (rowsPerPage === 'all') {
        pageIndicator.textContent = 'Showing All';
        prevBtn.style.display = 'none';
        nextBtn.style.display = 'none';
        return;
    }
    
    prevBtn.style.display = 'inline-block';
    nextBtn.style.display = 'inline-block';
    
    // Update page indicator
    pageIndicator.textContent = `${currentPage}/${totalPages}`;
    
    // Update button states
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    
    // Update button styles based on state
    if (prevBtn.disabled) {
        prevBtn.style.opacity = '0.5';
        prevBtn.style.cursor = 'not-allowed';
    } else {
        prevBtn.style.opacity = '1';
        prevBtn.style.cursor = 'pointer';
    }
    
    if (nextBtn.disabled) {
        nextBtn.style.opacity = '0.5';
        nextBtn.style.cursor = 'not-allowed';
    } else {
        nextBtn.style.opacity = '1';
        nextBtn.style.cursor = 'pointer';
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set up pagination button event listeners
    document.getElementById('prevBtn').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            filterAndPaginate();
        }
    });
    
    document.getElementById('nextBtn').addEventListener('click', function() {
        if (currentPage < totalPages) {
            currentPage++;
            filterAndPaginate();
        }
    });
    
    // Initialize table
    filterAndPaginate();
    
    // Search input event listener
    document.getElementById('searchInput').addEventListener('input', function() {
        searchTerm = this.value;
        currentPage = 1;
        filterAndPaginate();
    });
    
    // Rows per page event listener
    document.getElementById('rowsPerPage').addEventListener('change', function() {
        rowsPerPage = this.value;
        currentPage = 1;
        filterAndPaginate();
    });
    
    // Show add machine modal on page load if parameter is present
    const urlParams = new URLSearchParams(window.location.search);
    const showAddModal = urlParams.get('showAddModal');
    
    if (showAddModal === 'true') {
        document.getElementById('addMachineModal').style.display = 'block';
        // Clean up the URL
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }
    
    // Show add machine modal
    document.getElementById('addMachineBtn').addEventListener('click', function() {
        document.getElementById('addMachineModal').style.display = 'block';
    });
    
    // Auto-hide notification toast
    const toast = document.querySelector('.notification-toast');
    if (toast) {
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
});

// Show edit machine modal
function showEditModal(id, description, capacity, locationId, status, locationName) {
    document.getElementById('edit_machine_id').value = id;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_location').value = locationId || '';
    document.getElementById('edit_status').value = status;
    document.getElementById('editMachineModal').dataset.locationName = locationName;
    document.getElementById('editMachineModal').style.display = 'block';
    
    // Update status dropdown based on location
    const statusSelect = document.getElementById('edit_status');
    statusSelect.disabled = !locationId;
    if (!locationId) {
        statusSelect.value = '0';
    }
}

// Show status modal
function showStatusModal(id, currentStatus, locationName) {
    const statusSelect = document.getElementById('new_status');
    const statusMessage = document.getElementById('status_message');
    
    document.getElementById('status_machine_id').value = id;
    document.getElementById('current_status_text').textContent = currentStatus == 1 ? 'Enabled' : 'Disabled';
    document.getElementById('statusModal').dataset.locationName = locationName;
    
    // Check if machine is deployed
    const isDeployed = locationName !== 'Not Deployed';
    
    // Reset dropdown options
    statusSelect.innerHTML = '';
    
    // Add Disabled option (always available)
    const disabledOption = document.createElement('option');
    disabledOption.value = '0';
    disabledOption.textContent = 'Disabled';
    statusSelect.appendChild(disabledOption);
    
    // Add Enabled option only if machine is deployed
    if (isDeployed) {
        const enabledOption = document.createElement('option');
        enabledOption.value = '1';
        enabledOption.textContent = 'Enabled';
        statusSelect.appendChild(enabledOption);
    }
    
    // Set current status
    statusSelect.value = currentStatus;
    
    // Show/hide deployment message
    statusMessage.style.display = isDeployed ? 'none' : 'block';
    
    // Disable submit button if not deployed and trying to enable
    const submitButton = document.querySelector('#statusForm button[name="update_status"]');
    submitButton.disabled = !isDeployed && currentStatus == 0;
    
    document.getElementById('statusModal').style.display = 'block';
}

// Show delete confirmation modal
function showDeleteModal(id) {
    document.getElementById('delete_machine_id').value = id;
    document.getElementById('deleteMachineModal').style.display = 'block';
}

// Close modal
function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modals when clicking X or outside
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

// Client-side validation and status adjustment for Edit Machine form
document.getElementById('editMachineForm').addEventListener('submit', function(event) {
    const locationId = document.getElementById('edit_location').value;
    const statusSelect = document.getElementById('edit_status');

    if (!locationId) {
        // If no location is selected, force status to Disabled
        statusSelect.value = '0';
    }
});

// Update status dropdown based on location selection
document.getElementById('edit_location').addEventListener('change', function() {
    const locationId = this.value;
    const statusSelect = document.getElementById('edit_status');
    
    if (!locationId) {
        statusSelect.value = '0';
        statusSelect.disabled = true; // Disable status dropdown to indicate it's forced to Disabled
    } else {
        statusSelect.disabled = false;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>