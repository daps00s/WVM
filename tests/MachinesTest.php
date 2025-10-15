<?php
require_once 'TestCase.php';

class MachinesTest extends TestCase
{
    public function testMachinesPageExists()
    {
        $this->assertFileExists(BASE_PATH . '/machines.php');
    }
    
    public function testMachinesFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/machines.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    public function testMachinesPageHasRequiredElements()
    {
        $content = file_get_contents(BASE_PATH . '/machines.php');
        $this->assertStringContainsString('Machine Management', $content);
        $this->assertStringContainsString('machinesTable', $content);
        $this->assertStringContainsString('addMachineModal', $content);
    }
}