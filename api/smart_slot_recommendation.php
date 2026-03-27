<?php
/**
 * Smart Parking Slot Recommendation System
 * 
 * Calculates optimal parking slot based on:
 * - Distance from entry point
 * - Priority level (congestion indicator)
 * 
 * Score formula: distance_from_entry + (priority_level * 5)
 * Lower score = better recommendation
 */

require_once '../includes/functions.php';

header('Content-Type: application/json');

try {
    // Get location - it's actually a STRING (location_name), not an ID
    $location = isset($_POST['location_id']) ? trim($_POST['location_id']) : (isset($_GET['location_id']) ? trim($_GET['location_id']) : '');
    
    // Get optional category_id filter
    $category_id = isset($_POST['category_id']) && !empty($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    // Get optional user location for real distance calculation
    $user_lat = isset($_POST['user_lat']) && !empty($_POST['user_lat']) ? floatval($_POST['user_lat']) : null;
    $user_lng = isset($_POST['user_lng']) && !empty($_POST['user_lng']) ? floatval($_POST['user_lng']) : null;
    $use_user_location = $user_lat !== null && $user_lng !== null;
    
    $conn = getDB();

    // Build query with conditions
    $query = "
        SELECT
            ps.id,
            ps.space_number,
            ps.location_name,
            COALESCE(ps.distance_from_entry, 0) as distance_from_entry,
            COALESCE(ps.priority_level, 1) as priority_level,
            ps.category_id,
            COALESCE(vc.category_name, 'General') as category_name,
            ps.price_per_hour,
            ps.latitude,
            ps.longitude,
            ps.address,
            ps.is_available
        FROM parking_spaces ps
        LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
        WHERE ps.status = 'active'
        AND ps.is_available = 1
    ";

    $bindTypes = '';
    $bindValues = [];

    if (!empty($location)) {
        $query .= " AND location_name LIKE ?";
        $bindTypes .= 's';
        $bindValues[] = "%$location%";
    }

    // Add category filter if provided
    if ($category_id > 0) {
        $query .= " AND category_id = ?";
        $bindTypes .= 'i';
        $bindValues[] = $category_id;
    }

    $query .= " ORDER BY (COALESCE(distance_from_entry, 0) + (COALESCE(priority_level, 1) * 5)) ASC";

    $stmt = $conn->prepare($query);

    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }

    if (!empty($bindValues)) {
        $stmt->bind_param($bindTypes, ...$bindValues);
    }

    if (!$stmt->execute()) {
        throw new Exception('Query execution failed: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $original_result_count = $result->num_rows;
    $searched_location = $location; // Remember the originally requested location

    // If location search gave zero result, do non-location fallback to get any available slot
    if (!empty($location) && $result->num_rows === 0) {
        $query = "
            SELECT
                ps.id,
                ps.space_number,
                ps.location_name,
                COALESCE(ps.distance_from_entry, 0) as distance_from_entry,
                COALESCE(ps.priority_level, 1) as priority_level,
                ps.category_id,
                COALESCE(vc.category_name, 'General') as category_name,
                ps.price_per_hour,
                ps.latitude,
                ps.longitude,
                ps.address,
                ps.is_available
            FROM parking_spaces ps
            LEFT JOIN vehicle_categories vc ON ps.category_id = vc.id
            WHERE ps.status = 'active'
            AND ps.is_available = 1
        ";

        $bindTypes = '';
        $bindValues = [];

        if ($category_id > 0) {
            $query .= " AND category_id = ?";
            $bindTypes .= 'i';
            $bindValues[] = $category_id;
        }

        $query .= " ORDER BY (COALESCE(distance_from_entry, 0) + (COALESCE(priority_level, 1) * 5)) ASC";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception('Fallback query prepare failed: ' . $conn->error);
        }
        if (!empty($bindValues)) {
            $stmt->bind_param($bindTypes, ...$bindValues);
        }
        if (!$stmt->execute()) {
            throw new Exception('Fallback query execution failed: ' . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'message' => 'No available parking slots found for your criteria. Try removing filters.'
            ]);
            exit;
        }
    }



    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => "No available parking slots found for your criteria. Try removing filters."
        ]);
        exit;
    }
    
    // Fetch and score all available slots
    $slots = [];
    $slot_scores = [];
    
    while ($slot = $result->fetch_assoc()) {
        $distance_entry = max(0, floatval($slot['distance_from_entry'] ?? 0));
        $priority = max(0, intval($slot['priority_level'] ?? 0));

        $distance_user = null;
        if ($use_user_location && floatval($slot['latitude']) && floatval($slot['longitude'])) {
            $distance_user = calculateDistanceKm($user_lat, $user_lng, floatval($slot['latitude']), floatval($slot['longitude']));
            $distance_user = round($distance_user * 1000, 2); // meters
        }

        // Calculate slot recommendation score
        // If user location is available, prefer distance from user (meters) + priority*5
        // Else fallback to distance_from_entry + priority*5
        if ($distance_user !== null) {
            $score = $distance_user + ($priority * 5);
        } else {
            $score = $distance_entry + ($priority * 5);
        }

        $slots[] = array_merge($slot, [
            'score' => round($score, 2),
            'distance_from_entry' => round($distance_entry, 2),
            'distance_from_user' => $distance_user,
            'priority' => $priority
        ]);

        $slot_scores[$slot['id']] = $score;
    }
    
    // Sort slots by score ascending (lower = better)
    usort($slots, function($a, $b) {
        return $a['score'] <=> $b['score'];
    });
    
    // Get the best recommendation (first slot after sorting)
    $best_slot = $slots[0];
    $best_slot_id = $best_slot['id'];
    $best_score = $best_slot['score'];
    
    // Check if best slot is from the requested location
    $is_from_requested_location = strtolower(trim($best_slot['location_name'])) === strtolower(trim($searched_location));
    
    // Get category name for the recommended slot (already included in query via JOIN)
    $category_name = $best_slot['category_name'] ?? 'General';
    
    // Generate intelligent explanation
    $explanation = generateSlotExplanation($best_slot, $slots);
    
    // Prepare response
    $response = [
        'success' => true,
        'requested_location' => $searched_location,
        'is_from_requested_location' => $is_from_requested_location,
        'recommended_slot' => [
            'id' => intval($best_slot['id']),
            'space_number' => $best_slot['space_number'],
            'location_name' => $best_slot['location_name'],
            'category_name' => $category_name,
            'score' => $best_score,
            'distance_from_entry' => round($best_slot['distance_from_entry'], 2),
            'distance_from_user' => $best_slot['distance_from_user'] !== null ? round($best_slot['distance_from_user'], 2) : null,
            'priority_level' => intval($best_slot['priority']),
            'price_per_hour' => floatval($best_slot['price_per_hour']),
            'latitude' => floatval($best_slot['latitude']),
            'longitude' => floatval($best_slot['longitude']),
            'address' => $best_slot['address']
        ],
        'explanation' => $explanation,
        'scoring_details' => [
            'formula' => 'distance_from_entry + (priority_level × 5)',
            'best_score' => $best_score,
            'average_score' => round(array_sum(array_column($slots, 'score')) / count($slots), 2),
            'total_available_slots' => count($slots)
        ],
        'alternative_options' => getAlternativeRecommendations($slots, 2)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
