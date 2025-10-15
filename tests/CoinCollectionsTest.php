<?php
require_once 'TestCase.php';

class CoinCollectionsTest extends TestCase
{
    public function testCoinCollectionsPageExists()
    {
        $this->assertFileExists(BASE_PATH . '/coin_collections.php');
    }
    
    public function testCoinCollectionsFileHasValidSyntax()
    {
        $output = shell_exec("php -l " . escapeshellarg(BASE_PATH . '/coin_collections.php'));
        $this->assertStringContainsString('No syntax errors', $output);
    }
    
    public function testCoinCollectionsPageHasRequiredElements()
    {
        $content = file_get_contents(BASE_PATH . '/coin_collections.php');
        $this->assertStringContainsString('Coin Collection Monitoring', $content);
        $this->assertStringContainsString('coinTypeChart', $content);
        $this->assertStringContainsString('collectionTrendsChart', $content);
    }
}