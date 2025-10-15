<?php
require_once 'TestCase.php';

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up server variables for dashboard
        $_SERVER['PHP_SELF'] = '/dashboard.php';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        
        // Mock session
        $_SESSION = [
            'admin_logged_in' => true,
            'admin_username' => 'testuser',
            'admin_role' => 'admin'
        ];
    }
    
    public function testDashboardFileExists()
    {
        $this->assertFileExists(BASE_PATH . '/dashboard.php');
    }
    
    public function testDashboardDependenciesExist()
    {
        $this->assertFileExists(BASE_PATH . '/includes/header.php');
        $this->assertFileExists(BASE_PATH . '/includes/footer.php');
        $this->assertFileExists(BASE_PATH . '/includes/db_connect.php');
        $this->assertFileExists(BASE_PATH . '/includes/auth_check.php');
    }
    
    public function testDashboardAssetsExist()
    {
        $this->assertFileExists(BASE_PATH . '/assets/css/dashboard.css');
        $this->assertFileExists(BASE_PATH . '/assets/js/dashboard.js');
    }
    
    public function testDashboardHasRequiredVariables()
    {
        ob_start();
        include BASE_PATH . '/dashboard.php';
        $output = ob_get_clean();
        
        $this->assertStringContainsString('Dashboard', $output);
    }
    
    public function testDashboardFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/dashboard.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }
}