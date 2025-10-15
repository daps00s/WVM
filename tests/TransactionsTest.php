<?php
require_once 'TestCase.php';

class TransactionsTest extends TestCase
{
    public function testTransactionsPageExists()
    {
        $this->assertFileExists(BASE_PATH . '/transactions.php');
    }
    
    public function testTransactionsFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/transactions.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    public function testTransactionsPageHasRequiredElements()
    {
        $content = file_get_contents(BASE_PATH . '/transactions.php');
        $this->assertStringContainsString('Transaction History', $content);
        $this->assertStringContainsString('transactionsTable', $content) || 
               $this->assertStringContainsString('filter', $content);
    }
}