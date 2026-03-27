<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'root');
define('DB_NAME', 'easypark_db');
define('DB_SOCKET', '/Applications/MAMP/tmp/mysql/mysql.sock');

// Application configuration
define('SITE_NAME', 'EasyPark');
define('SITE_URL', 'http://localhost:8888');// Make sure this matches your folder name
define('TIMEZONE', 'Asia/Kathmandu');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>