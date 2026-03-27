<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['space_id'])) {
    $space_id = intval($_POST['space_id']);

    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM parking_spaces WHERE id = ?");
    $stmt->bind_param("i", $space_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $space = $result->fetch_assoc();

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'id' => $space['id'],
            'space_number' => $space['space_number'],
            'category_id' => $space['category_id'],
            'location_name' => $space['location_name'],
            'latitude' => $space['latitude'],
            'longitude' => $space['longitude'],
            'address' => $space['address'],
            'price_per_hour' => $space['price_per_hour'],
            'distance_from_entry' => $space['distance_from_entry'],
            'status' => $space['status'],
            'is_available' => $space['is_available']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Space not found']);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>