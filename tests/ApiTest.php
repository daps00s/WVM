<?php
require_once 'TestCase.php';

class ApiTest extends TestCase
{    
    public function testGetTransactionsEndpointExists()
    {
        $this->assertFileExists(BASE_PATH . '/api/get_transactions.php');
    }
    
    public function testTestCoinEndpointExists()
    {
        $this->assertFileExists(BASE_PATH . '/api/test_coin.php');
    }
    
    public function testGetTransactionsFileStructure()
    {
        $content = file_get_contents(BASE_PATH . '/api/get_transactions.php');
        
        // Check if file has required components
        $this->assertStringContainsString('header(\'Content-Type: application/json\')', $content);
        $this->assertStringContainsString('require_once', $content);
        $this->assertStringContainsString('db_connect.php', $content);
        $this->assertStringContainsString('auth_check.php', $content);
        $this->assertStringContainsString('json_encode', $content);
    }
    
    public function testTestCoinFileStructure()
    {
        $content = file_get_contents(BASE_PATH . '/api/test_coin.php');
        
        // Check if file has required components
        $this->assertStringContainsString('header(\'Content-Type: application/json\')', $content);
        $this->assertStringContainsString('require_once', $content);
        $this->assertStringContainsString('db_connect.php', $content);
        $this->assertStringContainsString('sendError', $content);
        $this->assertStringContainsString('json_encode', $content);
    }
    
    public function testApiFilesHaveValidSyntax()
    {
        // Test that API files don't have syntax errors
        $output1 = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/api/get_transactions.php'));
        $output2 = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/api/test_coin.php'));
        
        $this->assertStringContainsString('No syntax errors', $output1, "Syntax error in get_transactions.php");
        $this->assertStringContainsString('No syntax errors', $output2, "Syntax error in test_coin.php");
    }
}