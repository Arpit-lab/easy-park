<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'easypark_db');

// Application configuration 
define('SITE_NAME', 'EasyPark');
define('SITE_URL', 'http://localhost/easypark'); // Make sure this matches your folder name
define('TIMEZONE', 'Asia/Kathmandu');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>