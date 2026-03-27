<?php
require_once 'includes/config.php';

header('Content-Type: application/json');

try {
    // Test basic MySQL connection without database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', null, DB_SOCKET);

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Check if database exists
    $result = $conn->query("SHOW DATABASES LIKE 'easypark_db'");
    $db_exists = $result->num_rows > 0;

    if (!$db_exists) {
        echo json_encode([
            'success' => false,
            'error' => 'Database easypark_db does not exist. Please create it first.'
        ]);
        exit;
    }

    // Select the database
    $conn->select_db('easypark_db');

    // Check if parking_spaces table exists
    $result = $conn->query("SHOW TABLES LIKE 'parking_spaces'");
    $table_exists = $result->num_rows > 0;

    if (!$table_exists) {
        echo json_encode([
            'success' => false,
            'error' => 'parking_spaces table does not exist. Please run database setup.'
        ]);
        exit;
    }

    // Check parking spaces count
    $result = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'active'");
    $count = $result->fetch_assoc()['count'];

    echo json_encode([
        'success' => true,
        'message' => "Database connected successfully. Found $count active parking spaces.",
        'database_exists' => $db_exists,
        'table_exists' => $table_exists
    ]);

    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>