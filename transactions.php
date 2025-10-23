<?php
//transactions.php - Display and manage transaction history
date_default_timezone_set('Asia/Manila');
$pageTitle = 'Transactions';
require_once 'includes/header.php';

// Set date range to last 30 days
$startDate = date('Y-m-d', strtotime('-30 days'));
$endDate = date('Y-m-d');

// Get filters
$machineId = $_GET['machine'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build query
$query = "SELECT t.*, d.Description as machine_name, l.location_name, t.water_type
          FROM transaction t
          JOIN dispenser d ON t.dispenser_id = d.dispenser_id
          JOIN dispenserlocation dl ON d.dispenser_id = dl.dispenser_id
          JOIN location l ON dl.location_id = l.location_id
          WHERE DATE(t.DateAndTime) BETWEEN :startDate AND :endDate";
$params = ['startDate' => $startDate, 'endDate' => $endDate];

if ($machineId) {
    $query .= " AND t.dispenser_id = :machineId";
    $params['machineId'] = $machineId;
}

if ($searchTerm) {
    $query .= " AND (t.transaction_id LIKE :searchTerm1 OR l.location_name LIKE :searchTerm2 OR d.Description LIKE :searchTerm3 OR CAST(t.amount_dispensed AS CHAR) LIKE :searchTerm4 OR t.coin_type LIKE :searchTerm5 OR t.water_type LIKE :searchTerm6)";
    $params['searchTerm1'] = "%$searchTerm%";
    $params['searchTerm2'] = "%$searchTerm%";
    $params['searchTerm3'] = "%$searchTerm%";
    $params['searchTerm4'] = "%$searchTerm%";
    $params['searchTerm5'] = "%$searchTerm%";
    $params['searchTerm6'] = "%$searchTerm%";
}

$query .= " ORDER BY t.DateAndTime DESC";

$transactions = $pdo->prepare($query);
$transactions->execute($params);
$transactions = $transactions->fetchAll();

// Get all machines for filter
$machines = $pdo->query("SELECT dispenser_id, Description FROM dispenser ORDER BY Description")->fetchAll();

// Get statistics for the stat cards
$total_transactions = $pdo->query("SELECT COUNT(*) as total FROM transaction WHERE DATE(DateAndTime) BETWEEN '$startDate' AND '$endDate'")->fetch();
$total_volume = $pdo->query("SELECT COALESCE(SUM(amount_dispensed), 0) as total FROM transaction WHERE DATE(DateAndTime) BETWEEN '$startDate' AND '$endDate'")->fetch();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(CAST(REGEXP_REPLACE(coin_type, '[^0-9]', '') AS UNSIGNED)), 0) as total FROM transaction WHERE DATE(DateAndTime) BETWEEN '$startDate' AND '$endDate'")->fetch();
$active_machines = $pdo->query("SELECT COUNT(DISTINCT dispenser_id) as total FROM transaction WHERE DATE(DateAndTime) BETWEEN '$startDate' AND '$endDate'")->fetch();
?>
<link rel="stylesheet" href="assets/css/transactions.css">
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
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.stat-card.updating {
    animation: pulse 0.6s ease-in-out;
}

@keyframes pulse {
    0% { 
        transform: scale(1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    50% { 
        transform: scale(1.02);
        box-shadow: 0 8px 20px rgba(74, 144, 226, 0.3);
    }
    100% { 
        transform: scale(1);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
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
    position: relative;
    z-index: 2;
}

.stat-content {
    flex: 1;
    position: relative;
    z-index: 2;
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
    transition: all 0.3s ease;
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

/* Update indicator */
.update-indicator {
    position: absolute;
    top: 10px;
    right: 10px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10b981;
    opacity: 0;
    transition: all 0.3s ease;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    animation: none;
}

.update-indicator.active {
    opacity: 1;
    animation: pulse-ring 1.5s infinite;
}

@keyframes pulse-ring {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        transform: scale(1);
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }
    100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

/* Stat card colors */
.stat-card:nth-child(1) .stat-icon {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card:nth-child(2) .stat-icon {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card:nth-child(3) .stat-icon {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-card:nth-child(4) .stat-icon {
    background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
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
            <div class="content-title-group">
                <div>
                    <div>
                        <a href="accounting_and_calibration.php" class="btn-primary switch-mode-btn" title="Switch to Accounting and Calibration Mode">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 12h-4l2 2-2 2m-2-2h-4"></path>
                                <path d="M2 12h4l-2-2 2-2m2 2h4"></path>
                            </svg>
                            <span class="btn-text">Switch to Accounting and Calibration</span>
                        </a>
                    </div>
                    <h1 class="content-title">Transaction History (Last 30 Days)</h1>
                </div>  
            </div>
            <div class="content-actions">
                <div class="search-group">
                    <label for="searchInput" class="search-label">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search transactions..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="date-filter">
                    <select id="machineFilter">
                        <option value="">All Machines</option>
                        <?php foreach ($machines as $machine): ?>
                        <option value="<?php echo $machine['dispenser_id']; ?>" data-name="<?php echo htmlspecialchars($machine['Description']); ?>" <?php echo $machineId == $machine['dispenser_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($machine['Description']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
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
            </div>
        </div>

        <!-- Stat Cards Section -->
        <div class="stats-grid">
            <div class="stat-card" id="stat-total-transactions">
                <div class="update-indicator" id="indicator-transactions"></div>
                <div class="stat-icon"><i class="fas fa-exchange-alt"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Transactions</div>
                    <div class="stat-value" id="value-total-transactions"><?php echo number_format($total_transactions['total']); ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-chart-line"></i> Last 30 Days
                    </div>
                </div>
            </div>
            <div class="stat-card" id="stat-total-volume">
                <div class="update-indicator" id="indicator-volume"></div>
                <div class="stat-icon"><i class="fas fa-tint"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Volume</div>
                    <div class="stat-value" id="value-total-volume"><?php echo number_format($total_volume['total']); ?>ml</div>
                    <div class="stat-change success">
                        <i class="fas fa-water"></i> Water Dispensed
                    </div>
                </div>
            </div>
            <div class="stat-card" id="stat-total-revenue">
                <div class="update-indicator" id="indicator-revenue"></div>
                <div class="stat-icon"><i class="fas fa-coins"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value" id="value-total-revenue">₱<?php echo number_format($total_revenue['total']); ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-money-bill-wave"></i> Collected
                    </div>
                </div>
            </div>
            <div class="stat-card" id="stat-active-machines">
                <div class="update-indicator" id="indicator-machines"></div>
                <div class="stat-icon"><i class="fas fa-cogs"></i></div>
                <div class="stat-content">
                    <div class="stat-title">Active Machines</div>
                    <div class="stat-value" id="value-active-machines"><?php echo $active_machines['total']; ?></div>
                    <div class="stat-change success">
                        <i class="fas fa-microchip"></i> Processing
                    </div>
                </div>
            </div>
        </div>

        <div class="table-container">
            <div class="table-wrapper">
                <table class="data-table" id="transactionsTable">
                    <thead>
                        <tr>
                            <th class="id-col">ID</th>
                            <th class="datetime-col">Date & Time</th>
                            <th class="machine-col">Machine</th>
                            <th class="location-col">Location</th>
                            <th class="amount-col">Amount</th>
                            <th class="water-col">Water Type</th>
                            <th class="coin-col">Coin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                        <tr data-machine-id="<?php echo $transaction['dispenser_id']; ?>">
                            <td class="id-col"><?php echo $transaction['transaction_id']; ?></td>
                            <td class="transaction-time datetime-col"><?php echo date('M j, Y h:i A', strtotime($transaction['DateAndTime'])); ?></td>
                            <td class="machine-col"><?php echo htmlspecialchars($transaction['machine_name']); ?></td>
                            <td class="location-col"><?php echo htmlspecialchars($transaction['location_name']); ?></td>
                            <td class="amount-col"><?php echo $transaction['amount_dispensed']; ?>ml</td>
                            <td class="water-col"><?php echo htmlspecialchars($transaction['water_type']); ?></td>
                            <td class="coin-col"><?php echo $transaction['coin_type']; ?></td>
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

<script>
// State management
let currentPage = 1;
let rowsPerPage = 10;
let searchTerm = '<?php echo $searchTerm; ?>';
let currentMachineId = '<?php echo $machineId; ?>';
let knownTransactionIds = new Set();
let totalPages = 1;
let previousStats = {
    total_transactions: <?php echo $total_transactions['total']; ?>,
    total_volume: <?php echo $total_volume['total']; ?>,
    total_revenue: <?php echo $total_revenue['total']; ?>,
    active_machines: <?php echo $active_machines['total']; ?>
};

// Initialize known transactions
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('#transactionsTable tbody tr').forEach(row => {
        knownTransactionIds.add(row.cells[0].textContent);
    });
});

// Debounce function to delay search
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function updateURL() {
    const machineId = document.getElementById('machineFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    
    const params = new URLSearchParams();
    if (machineId) params.set('machine', machineId);
    if (searchTerm) params.set('search', searchTerm);
    
    window.location.href = `transactions.php?${params.toString()}`;
}

// Filter and paginate table
function filterAndPaginate() {
    const rows = document.querySelectorAll('#transactionsTable tbody tr');
    const filteredRows = [];
    
    rows.forEach(row => {
        const transactionId = row.cells[0].textContent.toLowerCase();
        const dateTime = row.cells[1].textContent.toLowerCase();
        const machineName = row.cells[2].textContent.toLowerCase();
        const locationName = row.cells[3].textContent.toLowerCase();
        const amount = row.cells[4].textContent.toLowerCase();
        const waterType = row.cells[5].textContent.toLowerCase();
        const coinType = row.cells[6].textContent.toLowerCase();
        
        const matchesSearch = searchTerm === '' || 
            transactionId.includes(searchTerm.toLowerCase()) ||
            dateTime.includes(searchTerm.toLowerCase()) ||
            machineName.includes(searchTerm.toLowerCase()) ||
            locationName.includes(searchTerm.toLowerCase()) ||
            amount.includes(searchTerm.toLowerCase()) ||
            waterType.includes(searchTerm.toLowerCase()) ||
            coinType.includes(searchTerm.toLowerCase());
        
        const matchesMachine = currentMachineId === '' || 
            row.getAttribute('data-machine-id') === currentMachineId;
        
        if (matchesSearch && matchesMachine) {
            filteredRows.push(row);
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    const totalRows = filteredRows.length;
    totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / parseInt(rowsPerPage));
    currentPage = Math.min(currentPage, Math.max(1, totalPages));
    
    filteredRows.forEach((row, index) => {
        if (rowsPerPage === 'all') {
            row.style.display = '';
        } else {
            const start = (currentPage - 1) * parseInt(rowsPerPage);
            const end = start + parseInt(rowsPerPage);
            row.style.display = (index >= start && index < end) ? '' : 'none';
        }
    });
    
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
    
    pageIndicator.textContent = `${currentPage}/${totalPages}`;
    
    prevBtn.disabled = currentPage === 1;
    nextBtn.disabled = currentPage === totalPages || totalPages === 0;
    
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

// **FIXED: INSTANT STATISTICS UPDATE FUNCTION**
function updateStatistics(data) {
    const transactionsElem = document.getElementById('value-total-transactions');
    const volumeElem = document.getElementById('value-total-volume');
    const revenueElem = document.getElementById('value-total-revenue');
    const machinesElem = document.getElementById('value-active-machines');
    
    let hasUpdates = false;
    
    // Check and update transactions
    const currentTransactions = parseInt(transactionsElem.textContent.replace(/,/g, ''));
    if (currentTransactions !== data.total_transactions) {
        transactionsElem.textContent = data.total_transactions.toLocaleString();
        document.getElementById('stat-total-transactions').classList.add('updating');
        document.getElementById('indicator-transactions').classList.add('active');
        hasUpdates = true;
    }
    
    // Check and update volume
    const currentVolume = parseInt(volumeElem.textContent.replace(/,/g, '').replace('ml', ''));
    if (currentVolume !== data.total_volume) {
        volumeElem.textContent = data.total_volume.toLocaleString() + 'ml';
        document.getElementById('stat-total-volume').classList.add('updating');
        document.getElementById('indicator-volume').classList.add('active');
        hasUpdates = true;
    }
    
    // Check and update revenue
    const currentRevenue = parseInt(revenueElem.textContent.replace(/[₱,]/g, ''));
    if (currentRevenue !== data.total_revenue) {
        revenueElem.textContent = '₱' + data.total_revenue.toLocaleString();
        document.getElementById('stat-total-revenue').classList.add('updating');
        document.getElementById('indicator-revenue').classList.add('active');
        hasUpdates = true;
    }
    
    // Check and update machines
    const currentMachines = parseInt(machinesElem.textContent);
    if (currentMachines !== data.active_machines) {
        machinesElem.textContent = data.active_machines;
        document.getElementById('stat-active-machines').classList.add('updating');
        document.getElementById('indicator-machines').classList.add('active');
        hasUpdates = true;
    }
    
    // Clear animations after 600ms
    if (hasUpdates) {
        setTimeout(() => {
            document.querySelectorAll('.stat-card').forEach(card => {
                card.classList.remove('updating');
            });
            document.querySelectorAll('.update-indicator').forEach(indicator => {
                indicator.classList.remove('active');
            });
        }, 600);
    }
}

// **FIXED: OPTIMIZED STATISTICS REFRESH**
function refreshStatistics() {
    const machineId = document.getElementById('machineFilter').value;
    
    const params = new URLSearchParams({
        start: '<?php echo $startDate; ?>',
        end: '<?php echo $endDate; ?>'
    });
    if (machineId) params.set('machine', machineId);
    
    fetch(`api/get_statistics.php?${params.toString()}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Update statistics instantly
        updateStatistics(data);
        
        // Store new values for comparison
        previousStats = {
            total_transactions: data.total_transactions,
            total_volume: data.total_volume,
            total_revenue: data.total_revenue,
            active_machines: data.active_machines
        };
    })
    .catch(error => {
        console.error('Error refreshing statistics:', error);
    });
}

// Refresh transactions
function refreshTransactions() {
    const machineId = document.getElementById('machineFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    
    currentMachineId = machineId;
    
    const params = new URLSearchParams({
        start: '<?php echo $startDate; ?>',
        end: '<?php echo $endDate; ?>'
    });
    if (machineId) params.set('machine', machineId);
    if (searchTerm) params.set('search', searchTerm);
    
    const url = `api/get_transactions.php?${params.toString()}`;
    
    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.querySelector('.data-table tbody');
            
            data.sort((a, b) => new Date(b.DateAndTime) - new Date(a.DateAndTime));
            
            tbody.innerHTML = '';
            let newTransactionsFound = false;
            
            data.forEach(transaction => {
                const date = new Date(transaction.DateAndTime);
                const formattedDate = date.toLocaleString('en-US', {
                    timeZone: 'Asia/Manila',
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                
                const isNew = !knownTransactionIds.has(transaction.transaction_id);
                
                const row = `
                    <tr data-machine-id="${transaction.dispenser_id}" class="${isNew ? 'new-transaction' : ''}">
                        <td>${transaction.transaction_id}</td>
                        <td class="datetime-col">${formattedDate}</td>
                        <td class="machine-col">${transaction.machine_name}</td>
                        <td class="location-col">${transaction.location_name}</td>
                        <td class="amount-col">${transaction.amount_dispensed}ml</td>
                        <td class="water-col">${transaction.water_type}</td>
                        <td class="coin-col">${transaction.coin_type}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
                
                if (isNew) {
                    knownTransactionIds.add(transaction.transaction_id);
                    newTransactionsFound = true;
                }
            });
            
            if (newTransactionsFound) {
                currentPage = 1;
            }
            
            filterAndPaginate();
        })
        .catch(error => console.error('Error refreshing transactions:', error));
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Set initial values from URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('rows')) {
        document.getElementById('rowsPerPage').value = urlParams.get('rows');
        rowsPerPage = urlParams.get('rows');
    }
    
    currentMachineId = '<?php echo $machineId; ?>';
    
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
    
    filterAndPaginate();
    
    // Debounced search input event listener
    const debouncedSearch = debounce(function() {
        searchTerm = document.getElementById('searchInput').value;
        currentPage = 1;
        updateURL();
    }, 500);
    
    document.getElementById('searchInput').addEventListener('input', debouncedSearch);
    
    document.getElementById('rowsPerPage').addEventListener('change', function() {
        rowsPerPage = this.value;
        currentPage = 1;
        filterAndPaginate();
    });
    
    document.getElementById('machineFilter').addEventListener('change', function() {
        currentMachineId = this.value;
        currentPage = 1;
        filterAndPaginate();
        updateURL();
    });
    
    // **CRITICAL: START AUTO-REFRESH EVERY 1 SECOND**
    const refreshInterval = setInterval(() => {
        refreshTransactions();
        refreshStatistics();  // This will now work INSTANTLY
    }, 1000);
    
    // Clean up interval on page unload
    window.addEventListener('beforeunload', () => {
        clearInterval(refreshInterval);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>