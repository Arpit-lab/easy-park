<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if (isset($_POST['category_id'])) {
    $conn = getDB();
    $category_id = intval($_POST['category_id']);
    
    $stmt = $conn->prepare("SELECT * FROM vehicle_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    }
}
?>