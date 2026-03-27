<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Debug: Log all inputs
error_log("=== DEBUG smart_slot_recommendation.php ===");
error_log("POST data: " . json_encode($_POST));
error_log("GET data: " . json_encode($_GET));

try {
    $conn = getDB();
    
    // Get the location
    $location = isset($_POST['location_id']) ? $_POST['location_id'] : (isset($_GET['location_id']) ? $_GET['location_id'] : null);
    error_log("Location received: " . $location);
    
    if (!$location) {
        throw new Exception('Location ID is required - received empty value');
    }
    
    // First, let's check what spaces exist for this location
    $check_query = "SELECT COUNT(*) as count FROM parking_spaces WHERE location_name = ? AND status = 'active'";
    $check_stmt = $conn->prepare($check_query);
    if (!$check_stmt) {
        throw new Exception("Prepare failed for check query: " . $conn->error);
    }
    
    $check_stmt->bind_param("s", $location);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $count_row = $check_result->fetch_assoc();
    error_log("Spaces found for location '$location': " . $count_row['count']);
    
    if ($count_row['count'] == 0) {
        throw new Exception("No active parking spaces found for location: $location");
    }
    
    // Now get all spaces
    $query = "
        SELECT 
            id,
            space_number,
            location_name,
            distance_from_entry,
            priority_level,
            category_id,
            price_per_hour,
            latitude,
            longitude,
            address,
            is_available,
            (SELECT COUNT(*) FROM parking_bookings 
             WHERE space_id = parking_spaces.id AND booking_status = 'active') as current_occupancy
        FROM parking_spaces
        WHERE status = 'active' 
        AND is_available = 1
        AND location_name = ?
        ORDER BY id ASC
    ";
    
    error_log("Query: $query");
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("s", $location);
    error_log("Binding location: $location");
    
    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    error_log("Rows returned: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        throw new Exception("No available parking slots found in location: $location");
    }
    
    // Process slots
    $slots = [];
    while ($slot = $result->fetch_assoc()) {
        $distance = max(0, floatval($slot['distance_from_entry'] ?? 0));
        $priority = max(0, intval($slot['priority_level'] ?? 0));
        $score = $distance + ($priority * 5);
        
        error_log("Slot {$slot['space_number']}: distance={$distance}, priority={$priority}, score={$score}");
        
        $slots[] = array_merge($slot, [
            'score' => round($score, 2),
            'distance' => $distance,
            'priority' => $priority
        ]);
    }
    
    // Sort by score
    usort($slots, function($a, $b) {
        return $a['score'] <=> $b['score'];
    });
    
    $best_slot = $slots[0];
    $explanation = generateSlotExplanation($best_slot, $slots);
    
    error_log("Best slot: " . $best_slot['space_number'] . " with score " . $best_slot['score']);
    
    $response = [
        'success' => true,
        'recommended_slot' => [
            'id' => intval($best_slot['id']),
            'space_number' => $best_slot['space_number'],
            'location_name' => $best_slot['location_name'],
            'score' => $best_slot['score'],
            'distance_from_entry' => round($best_slot['distance'], 2),
            'priority_level' => intval($best_slot['priority']),
            'price_per_hour' => floatval($best_slot['price_per_hour']),
            'address' => $best_slot['address']
        ],
        'explanation' => $explanation,
        'scoring_details' => [
            'formula' => 'distance_from_entry + (priority_level × 5)',
            'best_score' => $best_slot['score'],
            'average_score' => round(array_sum(array_column($slots, 'score')) / count($slots), 2),
            'total_available_slots' => count($slots)
        ],
        'alternative_options' => getAlternativeRecommendations($slots, 2),
        'debug' => [
            'location_received' => $location,
            'total_slots_processed' => count($slots),
            'all_slots' => $slots
        ]
    ];
    
    error_log("Response: " . json_encode($response));
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => [
            'location_received' => $location ?? 'null',
            'post_data' => $_POST,
            'get_data' => $_GET
        ]
    ]);
}
?>
