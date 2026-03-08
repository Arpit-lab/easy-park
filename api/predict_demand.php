<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Simple ML-based demand prediction algorithm
function predictDemand($space_id, $date, $hour) {
    $conn = getDB();
    
    // Get historical data for this space
    $stmt = $conn->prepare("
        SELECT 
            HOUR(check_in) as hour,
            DAYOFWEEK(check_in) as day,
            COUNT(*) as bookings
        FROM parking_bookings
        WHERE space_id = ? 
        AND check_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(check_in), DAYOFWEEK(check_in)
    ");
    $stmt->bind_param("i", $space_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historical_data = [];
    while ($row = $result->fetch_assoc()) {
        $historical_data[$row['day']][$row['hour']] = $row['bookings'];
    }
    
    // Get current factors (weather, events, etc. - simplified)
    $day_of_week = date('N', strtotime($date));
    $is_weekend = ($day_of_week >= 6);
    $is_holiday = false; // You can implement holiday detection
    
    // Calculate base demand from historical data
    $base_demand = isset($historical_data[$day_of_week][$hour]) 
                   ? $historical_data[$day_of_week][$hour] 
                   : 5; // Default value
    
    // Apply factors
    $demand = $base_demand;
    
    if ($is_weekend) {
        $demand *= 1.3; // 30% increase on weekends
    }
    
    if ($is_holiday) {
        $demand *= 1.5; // 50% increase on holidays
    }
    
    // Peak hours adjustment
    if ($hour >= 8 && $hour <= 10) { // Morning peak
        $demand *= 1.4;
    } elseif ($hour >= 17 && $hour <= 19) { // Evening peak
        $demand *= 1.3;
    }
    
    // Lunch hour
    if ($hour >= 12 && $hour <= 14) {
        $demand *= 1.2;
    }
    
    return min(100, round($demand, 2)); // Cap at 100%
}

// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $space_id = intval($_POST['space_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    $hour = intval($_POST['hour'] ?? date('H'));
    
    if ($space_id > 0) {
        $demand = predictDemand($space_id, $date, $hour);
        echo json_encode([
            'success' => true,
            'demand' => $demand,
            'message' => "Predicted demand: {$demand}%"
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid space ID'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>