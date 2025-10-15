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
?>
<link rel="stylesheet" href="assets/css/transactions.css">
<div class="content-area">
    <div class="content-wrapper">
        <div class="content-header">
            <div class="content-title-group">
                <h1 class="content-title">Transaction History (Last 30 Days)</h1>
                <a href="accounting_and_calibration.php" class="btn-primary switch-mode-btn" title="Switch to Accounting and Calibration Mode">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 12h-4l2 2-2 2m-2-2h-4"></path>
                        <path d="M2 12h4l-2-2 2-2m2 2h4"></path>
                    </svg>
                    Switch to Accounting and Calibration
                </a>
            </div>
            <div class="content-actions">
                <div class="search-group">
                    <label for="searchInput">Search:</label>
                    <input type="text" id="searchInput" placeholder="Search transactions..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="rows-per-page">
                    <label for="rowsPerPage">Rows per page:</label>
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

        <div class="filter-container">
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
        </div>

        <div class="table-container">
            <table class="data-table" id="transactionsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date & Time</th>
                        <th>Machine</th>
                        <th>Location</th>
                        <th>Amount (ml)</th>
                        <th>Water Type</th>
                        <th>Coin Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr data-machine-id="<?php echo $transaction['dispenser_id']; ?>">
                        <td><?php echo $transaction['transaction_id']; ?></td>
                        <td class="transaction-time"><?php echo date('M j, Y h:i A', strtotime($transaction['DateAndTime'])); ?></td>
                        <td><?php echo htmlspecialchars($transaction['machine_name']); ?></td>
                        <td><?php echo htmlspecialchars($transaction['location_name']); ?></td>
                        <td><?php echo $transaction['amount_dispensed']; ?>ml</td>
                        <td><?php echo htmlspecialchars($transaction['water_type']); ?></td>
                        <td><?php echo $transaction['coin_type']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div class="pagination" id="pagination"></div>
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
    
    // Filter rows based on search term and machine filter
    rows.forEach(row => {
        const transactionId = row.cells[0].textContent.toLowerCase();
        const dateTime = row.cells[1].textContent.toLowerCase();
        const machineName = row.cells[2].textContent.toLowerCase();
        const locationName = row.cells[3].textContent.toLowerCase();
        const amount = row.cells[4].textContent.toLowerCase();
        const waterType = row.cells[5].textContent.toLowerCase();
        const coinType = row.cells[6].textContent.toLowerCase();
        
        // Check if row matches search term
        const matchesSearch = searchTerm === '' || 
            transactionId.includes(searchTerm.toLowerCase()) ||
            dateTime.includes(searchTerm.toLowerCase()) ||
            machineName.includes(searchTerm.toLowerCase()) ||
            locationName.includes(searchTerm.toLowerCase()) ||
            amount.includes(searchTerm.toLowerCase()) ||
            waterType.includes(searchTerm.toLowerCase()) ||
            coinType.includes(searchTerm.toLowerCase());
        
        // Check if row matches machine filter
        const matchesMachine = currentMachineId === '' || 
            row.getAttribute('data-machine-id') === currentMachineId;
        
        if (matchesSearch && matchesMachine) {
            filteredRows.push(row);
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Calculate pagination
    const totalRows = filteredRows.length;
    const totalPages = rowsPerPage === 'all' ? 1 : Math.ceil(totalRows / parseInt(rowsPerPage));
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
    updatePagination(totalPages);
}

// Update pagination controls
function updatePagination(totalPages) {
    const pagination = document.getElementById('pagination');
    pagination.innerHTML = '';
    
    if (rowsPerPage === 'all') {
        return; // No pagination controls when showing all rows
    }
    
    // Previous button
    const prevButton = document.createElement('button');
    prevButton.textContent = 'Previous';
    prevButton.disabled = currentPage === 1;
    prevButton.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            filterAndPaginate();
        }
    });
    pagination.appendChild(prevButton);
    
    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const pageButton = document.createElement('button');
        pageButton.textContent = i;
        pageButton.className = i === currentPage ? 'active' : '';
        pageButton.addEventListener('click', () => {
            currentPage = i;
            filterAndPaginate();
        });
        pagination.appendChild(pageButton);
    }
    
    // Next button
    const nextButton = document.createElement('button');
    nextButton.textContent = 'Next';
    nextButton.disabled = currentPage === totalPages || totalPages === 0;
    nextButton.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            filterAndPaginate();
        }
    });
    pagination.appendChild(nextButton);
}

// Refresh transactions
function refreshTransactions() {
    const machineId = document.getElementById('machineFilter').value;
    const searchTerm = document.getElementById('searchInput').value;
    
    // Update current machine ID
    currentMachineId = machineId;
    
    const params = new URLSearchParams();
    params.set('start', '<?php echo $startDate; ?>');
    params.set('end', '<?php echo $endDate; ?>');
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
            
            // Sort data to ensure newest transactions are first
            data.sort((a, b) => new Date(b.DateAndTime) - new Date(a.DateAndTime));
            
            // Update table content
            tbody.innerHTML = '';
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
                
                // Only highlight if this is the first time we've seen this transaction
                const isNew = !knownTransactionIds.has(transaction.transaction_id);
                
                const row = `
                    <tr data-machine-id="${transaction.dispenser_id}" class="${isNew ? 'new-transaction' : ''}">
                        <td>${transaction.transaction_id}</td>
                        <td>${formattedDate}</td>
                        <td>${transaction.machine_name}</td>
                        <td>${transaction.location_name}</td>
                        <td>${transaction.amount_dispensed}ml</td>
                        <td>${transaction.water_type}</td>
                        <td>${transaction.coin_type}</td>
                    </tr>
                `;
                tbody.innerHTML += row;
                
                // Add to known transactions to prevent future highlighting
                if (isNew) {
                    knownTransactionIds.add(transaction.transaction_id);
                }
            });
            
            // Ensure new transactions are visible by resetting to first page if new transactions are present
            if (data.some(t => !knownTransactionIds.has(t.transaction_id))) {
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
    
    // Initialize current machine ID from URL
    currentMachineId = '<?php echo $machineId; ?>';
    
    filterAndPaginate();
    
    // Debounced search input event listener
    const debouncedSearch = debounce(function() {
        searchTerm = document.getElementById('searchInput').value;
        currentPage = 1;
        updateURL();
    }, 500);
    
    document.getElementById('rowsPerPage').addEventListener('change', function() {
        rowsPerPage = this.value;
        currentPage = 1;
        filterAndPaginate();
    });
    
    // Machine filter event listener
    document.getElementById('machineFilter').addEventListener('change', function() {
        currentMachineId = this.value;
        currentPage = 1;
        filterAndPaginate();
        updateURL();
    });
    
    // Auto-refresh every 2 seconds
    setInterval(refreshTransactions, 2000);
});
</script>

<?php require_once 'includes/footer.php'; ?>