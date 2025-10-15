<?php
require_once 'TestCase.php';

class BackupTest extends TestCase
{
    public function testBackupFileExists()
    {
        $this->assertFileExists(BASE_PATH . '/backup.php');
        $this->assertFileExists(BASE_PATH . '/cron_backup.php');
    }
    
    public function testBackupSettingsFileIsValidJson()
    {
        $settingsFile = BASE_PATH . '/config/backup_settings.json';
        $this->assertFileExists($settingsFile);
        
        $jsonContent = file_get_contents($settingsFile);
        $settings = json_decode($jsonContent, true);
        
        $this->assertNotNull($settings, "Backup settings file contains invalid JSON");
        $this->assertIsArray($settings);
        $this->assertArrayHasKey('frequency', $settings);
    }
    
    public function testBackupDirectoryExists()
    {
        $this->assertDirectoryExists(BASE_PATH . '/backups');
        $this->assertTrue(is_writable(BASE_PATH . '/backups'), 'Backups directory must be writable');
    }
    
    public function testBackupSettingsHasRequiredKeys()
    {
        $settingsFile = BASE_PATH . '/config/backup_settings.json';
        $settings = json_decode(file_get_contents($settingsFile), true);
        
        $requiredKeys = ['frequency', 'backup_hour', 'backup_minute'];
        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $settings, "Missing required key: $key");
        }
    }
    
    public function testBackupFrequencyIsValid()
    {
        $settingsFile = BASE_PATH . '/config/backup_settings.json';
        $settings = json_decode(file_get_contents($settingsFile), true);
        
        $validFrequencies = ['daily', 'every_other_day', 'every_month'];
        $this->assertContains($settings['frequency'], $validFrequencies, 
            "Invalid backup frequency: " . $settings['frequency']);
    }
}