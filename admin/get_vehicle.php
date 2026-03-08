<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if (isset($_POST['vehicle_id'])) {
    $conn = getDB();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE id = ?");
    $stmt->bind_param("i", $vehicle_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    }
}
?>