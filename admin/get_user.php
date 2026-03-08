<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if (isset($_POST['user_id'])) {
    $conn = getDB();
    $user_id = intval($_POST['user_id']);
    
    $stmt = $conn->prepare("SELECT id, username, email, full_name, phone, address, status FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        header('Content-Type: application/json');
        echo json_encode($row);
    }
}
?>