<?php
require_once 'TestCase.php';

class LocationsTest extends TestCase
{
    public function testLocationsPageExists()
    {
        $this->assertFileExists(BASE_PATH . '/locations.php');
    }
    
    public function testLocationsFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/locations.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    public function testLocationsPageHasRequiredElements()
    {
        $content = file_get_contents(BASE_PATH . '/locations.php');
        $this->assertStringContainsString('Location Management', $content);
        $this->assertStringContainsString('locationsTable', $content) || 
               $this->assertStringContainsString('addLocation', $content);
    }
}