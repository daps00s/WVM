<?php
require_once 'TestCase.php';

class ReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up server variables for reports
        $_SERVER['PHP_SELF'] = '/reports.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        // Create fresh database connection
        $host = 'localhost';
        $dbname = 'water_dispenser_system';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        
        // Mock global $pdo for reports.php
        global $pdo;
        $GLOBALS['pdo'] = $pdo;
        
        // Set fresh session data
        $_SESSION = [
            'admin_logged_in' => true,
            'admin_username' => 'testuser',
            'admin_role' => 'admin'
        ];
        
        // Set default GET parameters
        $_GET = [
            'report' => 'transactions',
            'machine' => 'all',
            'time' => 'month',
            'visual' => 'table'
        ];
    }
    
    public function testReportsFileExists()
    {
        $this->assertFileExists(BASE_PATH . '/reports.php');
    }
    
    public function testReportsDependenciesExist()
    {
        $this->assertFileExists(BASE_PATH . '/includes/header.php');
        $this->assertFileExists(BASE_PATH . '/includes/footer.php');
    }
    
    public function testReportsHasRequiredVariables()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Reports', $output);
        $this->assertStringContainsString('Report Type', $output);
        $this->assertStringContainsString('Machine', $output);
        $this->assertStringContainsString('Time Period', $output);
    }
    
    public function testReportsFilterControlsExist()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('reportType', $output);
        $this->assertStringContainsString('machineFilter', $output);
        $this->assertStringContainsString('timeFilter', $output);
        $this->assertStringContainsString('visualType', $output);
    }
    
    public function testReportsDownloadButtonsExist()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('downloadPdf', $output);
        $this->assertStringContainsString('downloadCsv', $output);
        $this->assertStringContainsString('fa-file-pdf', $output);
        $this->assertStringContainsString('fa-file-csv', $output);
    }
    
    public function testReportsReportTypesExist()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Transaction Report', $output);
        $this->assertStringContainsString('Machine Status Report', $output);
        $this->assertStringContainsString('Sales Summary Report', $output);
        $this->assertStringContainsString('Water Consumption Report', $output);
    }
    
    public function testReportsTableStructureExists()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('report-table', $output);
        $this->assertStringContainsString('table-responsive', $output);
    }
    
    public function testReportsChartContainerExists()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('chart-container', $output);
        $this->assertStringContainsString('Chart.js', $output);
    }
    
    public function testReportsCustomDateModalExists()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('customDateModal', $output);
        $this->assertStringContainsString('modalStartDate', $output);
        $this->assertStringContainsString('modalEndDate', $output);
    }
    
    public function testReportsJavaScriptLibrariesLoaded()
    {
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('html2canvas', $output);
        $this->assertStringContainsString('jspdf', $output);
        $this->assertStringContainsString('chart.js', $output);
    }
    
    public function testReportsBuildTimeWhereClauseFunction()
    {
        // Test the buildTimeWhereClause function
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        // Function should be defined
        $this->assertTrue(function_exists('buildTimeWhereClause'));
    }
    
    public function testReportsGenerateReportFunction()
    {
        // Test the generateReport function
        ob_start();
        include BASE_PATH . '/reports.php';
        $output = ob_get_clean();
        
        // Function should be defined
        $this->assertTrue(function_exists('generateReport'));
    }
    
    public function testReportsWithDifferentParameters()
    {
        // Test with different report types
        $reportTypes = ['transactions', 'machines', 'sales', 'water'];
        
        foreach ($reportTypes as $reportType) {
            $_GET['report'] = $reportType;
            
            ob_start();
            include BASE_PATH . '/reports.php';
            $output = ob_get_clean();
            
            $this->assertStringContainsString($reportType . 'Report', $output);
        }
    }
    
    public function testReportsTimeFilters()
    {
        $timeFilters = ['day', 'week', 'month', 'year', 'custom'];
        
        foreach ($timeFilters as $timeFilter) {
            $_GET['time'] = $timeFilter;
            
            ob_start();
            include BASE_PATH . '/reports.php';
            $output = ob_get_clean();
            
            $this->assertStringContainsString('timeFilter', $output);
        }
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['pdo']);
        $_GET = [];
    }
}