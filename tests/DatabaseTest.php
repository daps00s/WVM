<?php
require_once 'TestCase.php';

class DatabaseTest extends TestCase
{
    private $pdo;
    
    protected function setUp(): void
    {
        $host = 'localhost';
        $dbname = 'water_dispenser_system';
        $username = 'root';
        $password = '';
        
        $this->pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function testDatabaseConnection()
    {
        $this->assertInstanceOf(PDO::class, $this->pdo);
    }
    
    public function testDatabaseIsAccessible()
    {
        $result = $this->pdo->query("SELECT 1");
        $this->assertTrue($result !== false);
    }
    
    public function testDatabaseHasTables()
    {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertIsArray($tables);
    }
    
    protected function tearDown(): void
    {
        $this->pdo = null;
    }
}