<?php
// tests/bootstrap.php

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('TEST_PATH', __DIR__);

// Include the Composer autoloader
require_once BASE_PATH . '/vendor/autoload.php';

// Clean up any existing session
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// Set up complete testing environment
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['DOCUMENT_ROOT'] = BASE_PATH;
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/';

// Mock authenticated user for tests
$_SESSION = [
    'admin_logged_in' => true,
    'admin_username' => 'testuser',
    'admin_role' => 'admin'
];

// Include db_connect.php to set up database connection
require_once BASE_PATH . '/includes/db_connect.php';

// Define ROOT_PATH for API files
define('ROOT_PATH', BASE_PATH . '/');

// Error reporting for testing
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Prevent any output during tests
ob_start();