<?php
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear session and server variables
        $_SESSION = [];
        $_SERVER = [
            'SERVER_NAME' => 'localhost',
            'HTTP_HOST' => 'localhost',
            'REQUEST_METHOD' => 'GET'
        ];
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up
        $_SESSION = [];
        $_SERVER = [];
    }
    
    protected function mockLoggedInUser()
    {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'testuser';
        $_SESSION['admin_role'] = 'admin';
    }
    
    protected function mockPostRequest($data = [])
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        file_put_contents('php://input', json_encode($data));
    }
    
    protected function mockGetRequest($params = [])
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = $params;
    }
    
    protected function cleanOutputBuffer()
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
    
    protected function assertFileContains($file, $expectedString)
    {
        $content = file_get_contents($file);
        $this->assertStringContainsString($expectedString, $content);
    }
    
    protected function assertValidJson($string)
    {
        json_decode($string);
        $this->assertEquals(JSON_ERROR_NONE, json_last_error());
    }
}