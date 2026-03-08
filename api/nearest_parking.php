<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $latitude = floatval($_POST['latitude'] ?? 0);
    $longitude = floatval($_POST['longitude'] ?? 0);
    $radius = floatval($_POST['radius'] ?? 5);
    
    if ($latitude && $longitude) {
        $conn = getDB();
        
        // Haversine formula to find nearest parking spaces
        $query = "
            SELECT 
                ps.*,
                vc.category_name,
                (6371 * acos(cos(radians(?)) * cos(radians(ps.latitude)) * 
                cos(radians(ps.longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(ps.latitude)))) AS distance,
                (SELECT COUNT(*) FROM parking_bookings WHERE space_id = ps.id AND booking_status = 'active') as current_bookings
            FROM parking_spaces ps
            JOIN vehicle_categories vc ON ps.category_id = vc.id
            WHERE ps.status = 'active' AND ps.is_available = 1
            HAVING distance < ?
            ORDER BY distance
            LIMIT 20
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("dddi", $latitude, $longitude, $latitude, $radius);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $spaces = [];
        while ($row = $result->fetch_assoc()) {
            $spaces[] = [
                'id' => $row['id'],
                'space_number' => $row['space_number'],
                'location_name' => $row['location_name'],
                'address' => $row['address'],
                'category' => $row['category_name'],
                'price_per_hour' => $row['price_per_hour'],
                'distance' => round($row['distance'], 2) . ' km',
                'available' => ($row['current_bookings'] < 10), // Simple availability check
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'spaces' => $spaces,
            'count' => count($spaces)
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid coordinates'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>