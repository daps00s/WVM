<?php
require_once 'TestCase.php';

class WaterLevelsTest extends TestCase
{
    public function testWaterLevelsPageExists()
    {
        $this->assertFileExists(BASE_PATH . '/water_levels.php');
    }
    
    public function testWaterLevelsFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/water_levels.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    public function testWaterLevelsPageHasRequiredElements()
    {
        $content = file_get_contents(BASE_PATH . '/water_levels.php');
        $this->assertStringContainsString('Water Level Monitoring', $content);
        $this->assertStringContainsString('water-level-bar', $content);
        $this->assertStringContainsString('refillModal', $content);
    }
}