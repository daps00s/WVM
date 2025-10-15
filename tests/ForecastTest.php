<?php
require_once 'TestCase.php';

class ForecastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up server variables for forecast
        $_SERVER['PHP_SELF'] = '/forecast.php';
        
        // Create fresh database connection
        $host = 'localhost';
        $dbname = 'water_dispenser_system';
        $username = 'root';
        $password = '';
        
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        
        // Mock global $pdo for forecast.php
        global $pdo;
        $GLOBALS['pdo'] = $pdo;
        
        // Set fresh session data
        $_SESSION = [
            'admin_logged_in' => true,
            'admin_username' => 'testuser',
            'admin_role' => 'admin'
        ];
        
        // Set default GET parameters
        $_GET = ['period' => 'year'];
    }
    
    public function testForecastFileExists()
    {
        $this->assertFileExists(BASE_PATH . '/forecast.php');
    }
    
    public function testForecastDependenciesExist()
    {
        $this->assertFileExists(BASE_PATH . '/includes/header.php');
        $this->assertFileExists(BASE_PATH . '/includes/footer.php');
    }
    
    public function testForecastHasRequiredVariables()
    {
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Water Trends & Forecast', $output);
        $this->assertStringContainsString('Period', $output);
        $this->assertStringContainsString('Forecast Explanation', $output);
    }
    
    public function testForecastPeriodSelectorExists()
    {
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('periodSelect', $output);
        $this->assertStringContainsString('7 Days Forecast', $output);
        $this->assertStringContainsString('30 Days Forecast', $output);
        $this->assertStringContainsString('Yearly', $output);
        $this->assertStringContainsString('Custom Date', $output);
    }
    
    public function testForecastChartContainerExists()
    {
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('demandChart', $output);
        $this->assertStringContainsString('chart-container', $output);
    }
    
    public function testForecastModalExists()
    {
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('dateModal', $output);
        $this->assertStringContainsString('startDate', $output);
        $this->assertStringContainsString('endDate', $output);
    }
    
    public function testForecastJavaScriptLibrariesLoaded()
    {
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Chart.js', $output);
        $this->assertStringContainsString('chartjs-plugin-annotation', $output);
    }
    
    public function testForecastLinearRegressionFunction()
    {
        // Test the linear_regression function
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        // Function should be defined and work correctly
        $x = [1, 2, 3, 4, 5];
        $y = [2, 4, 6, 8, 10];
        
        list($slope, $intercept) = linear_regression($x, $y);
        
        $this->assertEquals(2, $slope, 'Slope should be 2 for perfect linear relationship');
        $this->assertEquals(0, $intercept, 'Intercept should be 0 for perfect linear relationship');
    }
    
    public function testForecastWithDifferentPeriods()
    {
        $periods = ['7days', '30days', 'year', 'custom'];
        
        foreach ($periods as $period) {
            $_GET['period'] = $period;
            
            ob_start();
            include BASE_PATH . '/forecast.php';
            $output = ob_get_clean();
            
            $this->assertStringContainsString('demandChart', $output);
            
            if ($period === 'custom') {
                $this->assertStringContainsString('custom', $output);
            }
        }
    }
    
    public function testForecastBuildTimeWhereClause()
    {
        // Test time where clause building
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        // Test different time filters
        $dayClause = buildTimeWhereClause('day');
        $weekClause = buildTimeWhereClause('week');
        $monthClause = buildTimeWhereClause('month');
        $yearClause = buildTimeWhereClause('year');
        $customClause = buildTimeWhereClause('custom', '2024-01-01', '2024-12-31');
        
        $this->assertStringContainsString('CURDATE()', $dayClause);
        $this->assertStringContainsString('INTERVAL 7 DAY', $weekClause);
        $this->assertStringContainsString('INTERVAL 30 DAY', $monthClause);
        $this->assertStringContainsString('INTERVAL 1 YEAR', $yearClause);
        $this->assertStringContainsString('BETWEEN', $customClause);
    }
    
    public function testForecastDatabaseQueries()
    {
        // Test that database queries execute without errors
        ob_start();
        include BASE_PATH . '/forecast.php';
        $output = ob_get_clean();
        
        $this->assertStringNotContainsString('Connection failed', $output);
        $this->assertStringNotContainsString('SQL error', $output);
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($GLOBALS['pdo']);
        $_GET = [];
    }
}