<?php
require_once 'TestCase.php';

class AuthenticationTest extends TestCase
{
    public function testAuthCheckFileExists()
    {
        $this->assertFileExists(BASE_PATH . '/includes/auth_check.php');
    }
    
    public function testAuthCheckHasSessionStart()
    {
        $content = file_get_contents(BASE_PATH . '/includes/auth_check.php');
        $this->assertStringContainsString('session_start()', $content);
    }
    
    public function testAuthCheckRedirectsWhenNotLoggedIn()
    {
        // Clear session and close any existing session
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        // Capture headers
        $this->expectOutputRegex('/Location:.*index\.php/');
        
        // Include the file - it should redirect
        include BASE_PATH . '/includes/auth_check.php';
    }
    
    public function testAuthCheckAllowsAccessWhenLoggedIn()
    {
        // Mock logged in session - close any existing session first
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        
        $_SESSION['admin_logged_in'] = true;
        
        // This should not redirect when logged in
        ob_start();
        include BASE_PATH . '/includes/auth_check.php';
        $output = ob_get_clean();
        
        $this->assertEmpty($output, 'Should not output anything when authenticated');
    }
}