<?php
require_once 'includes/functions.php';
$conn = getDB();
$r = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'distance_from_entry'");
if ($r) {
    echo 'distance_from_entry exists: ' . ($r->num_rows > 0 ? 'yes' : 'no') . PHP_EOL;
} else {
    echo 'query failed: ' . $conn->error . PHP_EOL;
}
?>