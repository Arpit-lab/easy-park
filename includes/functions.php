<?php
require_once 'db_connection.php';


// Add this function to calculate hours-based cost
function calculateHoursCost($hours, $rate_per_hour) {
    return $hours * $rate_per_hour;
}

// Add this function to check if user can book
function canUserBook($user_id) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM parking_bookings 
        WHERE user_id = ? AND booking_status = 'active'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    // Allow maximum 3 active bookings per user
    return $row['count'] < 3;
}

// Add this function to get vehicle by number
function getVehicleByNumber($vehicle_number) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM vehicles WHERE vehicle_number = ?");
    $stmt->bind_param("s", $vehicle_number);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Add this function to format duration
function formatDuration($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($hours > 0) {
        return $hours . ' hr ' . $minutes . ' min';
    } else {
        return $minutes . ' minutes';
    }
}

// Add this function to send email (placeholder)
function sendEmail($to, $subject, $message) {
    // In production, use PHPMailer or similar
    // For now, just log it
    error_log("Email to: $to, Subject: $subject, Message: $message");
    return true;
}

// Add this function to generate QR code (placeholder)
function generateQRCode($data) {
    // In production, use a QR code library
    // For now, return a placeholder
    return "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($data);
}

// Add this function to validate vehicle number (Nepal format)
function validateVehicleNumber($number) {
    // Nepal vehicle number format: BA 1 PA 1234
    $pattern = '/^[A-Z]{2}\s?\d{1,2}\s?[A-Z]{2}\s?\d{1,4}$/';
    return preg_match($pattern, strtoupper($number));
}

// Add this function to get nearby places using OpenStreetMap Nominatim
function geocodeAddress($address) {
    $url = "https://nominatim.openstreetmap.org/search?format=json&q=" . urlencode($address);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'EasyPark App');
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!empty($data)) {
        return [
            'lat' => $data[0]['lat'],
            'lon' => $data[0]['lon'],
            'display_name' => $data[0]['display_name']
        ];
    }
    
    return null;
}

// Add this function to get weather data for demand prediction
function getWeatherData($latitude, $longitude) {
    // In production, use a weather API
    // For now, return dummy data
    return [
        'temperature' => rand(15, 35),
        'condition' => ['sunny', 'cloudy', 'rainy'][rand(0, 2)],
        'precipitation' => rand(0, 100)
    ];
}

// Generate unique booking number
function generateBookingNumber() {
    return 'BK' . date('Ymd') . rand(1000, 9999);
}

// Generate receipt number
function generateReceiptNumber() {
    return 'RCT' . date('Ymd') . rand(10000, 99999);
}

// Calculate the Haversine distance between two GPS coordinates in kilometers
function calculateDistanceKm($latFrom, $lonFrom, $latTo, $lonTo) {
    $earthRadiusKm = 6371;

    $dLat = deg2rad($latTo - $latFrom);
    $dLon = deg2rad($lonTo - $lonFrom);

    $latFrom = deg2rad($latFrom);
    $latTo = deg2rad($latTo);

    $a = sin($dLat / 2) * sin($dLat / 2) +
         sin($dLon / 2) * sin($dLon / 2) * cos($latFrom) * cos($latTo);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

    return $earthRadiusKm * $c;
}

// Calculate parking duration in hours
function calculateDuration($check_in, $check_out = null) {
    $check_in_time = new DateTime($check_in);
    $check_out_time = $check_out ? new DateTime($check_out) : new DateTime();
    
    $interval = $check_in_time->diff($check_out_time);
    $hours = $interval->h + ($interval->days * 24);
    $minutes = $interval->i;
    
    // Round up to nearest hour if minutes > 0
    if ($minutes > 0) {
        $hours += 1;
    }
    
    return $hours;
}

// Calculate parking amount
function calculateAmount($category_id, $hours) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT hourly_rate, daily_rate FROM vehicle_categories WHERE id = ?");
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    
    if ($hours >= 24) {
        $days = floor($hours / 24);
        $remaining_hours = $hours % 24;
        $amount = ($days * $category['daily_rate']) + ($remaining_hours * $category['hourly_rate']);
    } else {
        $amount = $hours * $category['hourly_rate'];
    }
    
    return $amount;
}

// Check space availability
function checkSpaceAvailability($space_id, $check_in, $check_out) {
    $conn = getDB();
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM parking_bookings 
        WHERE space_id = ? 
        AND booking_status = 'active'
        AND (
            (check_in <= ? AND check_out >= ?) OR
            (check_in <= ? AND check_out >= ?) OR
            (check_in >= ? AND check_out <= ?)
        )
    ");
    $stmt->bind_param("issssss", $space_id, $check_in, $check_in, $check_out, $check_out, $check_in, $check_out);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] == 0;
}

// Find nearest parking spaces using Haversine formula
function findNearestParking($latitude, $longitude, $radius = 5) {
    $conn = getDB();
    $query = "
        SELECT *, 
        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
        cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
        sin(radians(latitude)))) AS distance 
        FROM parking_spaces 
        WHERE status = 'active' AND is_available = 1
        HAVING distance < ?
        ORDER BY distance
        LIMIT 10
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dddi", $latitude, $longitude, $latitude, $radius);
    $stmt->execute();
    return $stmt->get_result();
}

// Log user activity
function logActivity($user_id, $action, $description) {
    $conn = getDB();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
    $stmt->execute();
}

// Send notification (simplified - can be extended for email/SMS)
function sendNotification($user_id, $type, $message) {
    // Implementation for sending notifications
    // Can be integrated with email or SMS gateway
    return true;
}

// Validate date time
function validateDateTime($datetime) {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $datetime);
    return $d && $d->format('Y-m-d H:i:s') === $datetime;
}

// Format currency
function formatCurrency($amount) {
    return 'रू ' . number_format($amount, 2);
}

// Get user by ID
function getUserById($user_id) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get parking space by ID
function getParkingSpaceById($space_id) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT ps.*, vc.category_name FROM parking_spaces ps JOIN vehicle_categories vc ON ps.category_id = vc.id WHERE ps.id = ?");
    $stmt->bind_param("i", $space_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Check if user has active booking
function hasActiveBooking($user_id) {
    $conn = getDB();
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM parking_bookings WHERE user_id = ? AND booking_status = 'active'");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'] > 0;
}

// Get dashboard statistics
function getDashboardStats($user_type = 'admin') {
    $conn = getDB();
    $stats = [];
    
    if ($user_type == 'admin') {
        // Total users
        $result = $conn->query("SELECT COUNT(*) as count FROM users WHERE user_type = 'user'");
        $stats['total_users'] = $result->fetch_assoc()['count'];
        
        // Total vehicles
        $result = $conn->query("SELECT COUNT(*) as count FROM vehicles");
        $stats['total_vehicles'] = $result->fetch_assoc()['count'];
        
        // Active bookings
        $result = $conn->query("SELECT COUNT(*) as count FROM parking_bookings WHERE booking_status = 'active'");
        $stats['active_bookings'] = $result->fetch_assoc()['count'];
        
        // Today's revenue
        $result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM parking_transactions WHERE DATE(created_at) = CURDATE()");
        $stats['today_revenue'] = $result->fetch_assoc()['total'];
        
        // Available spaces
        $result = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE is_available = 1 AND status = 'active'");
        $stats['available_spaces'] = $result->fetch_assoc()['count'];
        
        // New anomalies
        $result = $conn->query("SELECT COUNT(*) as count FROM anomaly_alerts WHERE status = 'new'");
        $stats['new_anomalies'] = $result->fetch_assoc()['count'];
    }
    
    return $stats;
}

/**
 * Ensure smart slot columns exist in database
 * Auto-creates columns if they don't exist
 */
function ensureSmartSlotColumns($conn) {
    try {
        // Check if distance_from_entry column exists
        $check_distance = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'distance_from_entry'");
        
        if (!$check_distance || $check_distance->num_rows === 0) {
            $conn->query("ALTER TABLE parking_spaces ADD COLUMN distance_from_entry FLOAT DEFAULT 0");
            // Populate with default values
            $conn->query("UPDATE parking_spaces SET distance_from_entry = 25 + (id % 26) WHERE distance_from_entry = 0");
        }
        
        // Check if priority_level column exists
        $check_priority = $conn->query("SHOW COLUMNS FROM parking_spaces LIKE 'priority_level'");
        
        if (!$check_priority || $check_priority->num_rows === 0) {
            $conn->query("ALTER TABLE parking_spaces ADD COLUMN priority_level INT DEFAULT 1");
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Smart slot migration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate intelligent explanation for slot recommendation
 */
function generateSlotExplanation($best_slot, $all_slots) {
    $distance = $best_slot['distance_from_user'] !== null ? floatval($best_slot['distance_from_user']) : floatval($best_slot['distance_from_entry'] ?? 0);
    $priority = intval($best_slot['priority_level'] ?? 0);
    
    $priority_desc = ['Low congestion', 'Medium congestion', 'High congestion', 'Very high congestion'][$priority] ?? 'Standard congestion';
    
    $avg_distance = 0;
    foreach ($all_slots as $slot) {
        $avg_distance += $slot['distance_from_user'] !== null ? floatval($slot['distance_from_user']) : floatval($slot['distance_from_entry'] ?? 0);
    }
    $avg_distance = $avg_distance / (count($all_slots) ?: 1);
    
    $improvement = $avg_distance > 0 ? round((($avg_distance - $distance) / $avg_distance) * 100, 1) : 0;
    
    return "Recommended Slot {$best_slot['space_number']} because it is very close ({$distance}m), {$priority_desc}, {$improvement}% better than average";
}

/**
 * Get alternative recommendation options
 */
function getAlternativeRecommendations($slots, $limit = 2) {
    $alternatives = [];
    $locations_used = [];
    
    // Group by location and get best spot from each location
    // This ensures we show recommendations from DIFFERENT locations
    foreach ($slots as $index => $slot) {
        // Skip first slot (it's the best overall)
        if ($index === 0) continue;
        
        $location = strtolower(trim($slot['location_name']));
        
        // Only add if we haven't already used this location
        if (!in_array($location, $locations_used) && count($alternatives) < $limit) {
            $locations_used[] = $location;
            $alternatives[] = [
                'id' => intval($slot['id']),
                'space_number' => htmlspecialchars($slot['space_number']),
                'location_name' => htmlspecialchars($slot['location_name']),
                'category_name' => htmlspecialchars($slot['category_name'] ?? 'General'),
                'score' => round($slot['score'], 2),
                'distance_from_entry' => round($slot['distance_from_entry'] ?? 0, 2),
                'distance_from_user' => isset($slot['distance_from_user']) ? round($slot['distance_from_user'], 2) : null,
                'price_per_hour' => floatval($slot['price_per_hour']),
                'latitude' => floatval($slot['latitude']),
                'longitude' => floatval($slot['longitude'])
            ];
        }
        
        // Stop if we've collected enough from different locations
        if (count($alternatives) >= $limit) break;
    }
    
    return $alternatives;
}

/**
 * Get parking prediction based on historical data
 * Analyzes entry/exit patterns by hour
 */
function getParkingPrediction() {
    $conn = getDB();
    
    // Get hourly occupancy from last 30 days
    // Using TIMESTAMPDIFF for proper duration calculation
    $query = "
        SELECT 
            HOUR(check_in) as hour_of_day,
            COUNT(*) as vehicle_count,
            AVG(TIMESTAMPDIFF(HOUR, check_in, IFNULL(check_out, NOW()))) as avg_duration_hours
        FROM parking_bookings
        WHERE check_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND booking_status IN ('completed', 'active')
        GROUP BY HOUR(check_in)
        ORDER BY hour_of_day ASC
    ";
    
    $result = $conn->query($query);
    
    if (!$result) {
        error_log("Prediction query error: " . $conn->error);
        return null;
    }
    
    $hourly_data = [];
    $max_vehicles = 0;
    
    while ($row = $result->fetch_assoc()) {
        $hourly_data[$row['hour_of_day']] = intval($row['vehicle_count']);
        $max_vehicles = max($max_vehicles, intval($row['vehicle_count']));
    }
    
    // If no data, return null
    if (empty($hourly_data)) {
        return null;
    }
    
    // Get current hour
    $current_hour = intval(date('H'));
    $current_vehicles = $hourly_data[$current_hour] ?? 0;
    
    // Get average across all hours
    $avg_vehicles = array_sum($hourly_data) / (count($hourly_data) ?: 1);
    
    // Calculate occupancy percentage
    $total_spaces_result = $conn->query("SELECT COUNT(*) as count FROM parking_spaces WHERE status = 'active'");
    $total_spaces_row = $total_spaces_result ? $total_spaces_result->fetch_assoc() : ['count' => 0];
    $total_spaces = intval($total_spaces_row['count']);
    
    $occupancy_percent = $total_spaces > 0 ? ($current_vehicles / $total_spaces) * 100 : 0;
    
    // Determine status
    if ($occupancy_percent >= 80) {
        $status = "Parking likely full";
        $severity = "danger";
    } elseif ($occupancy_percent >= 50) {
        $status = "Parking moderately available";
        $severity = "warning";
    } else {
        $status = "Parking mostly empty";
        $severity = "success";
    }
    
    // Find best time to park (lowest occupancy hour)
    $best_hour = array_key_first($hourly_data);
    $best_hour_vehicles = $hourly_data[$best_hour];
    
    foreach ($hourly_data as $hour => $count) {
        if ($count < $best_hour_vehicles) {
            $best_hour = $hour;
            $best_hour_vehicles = $count;
        }
    }
    
    // Find worst hour
    $worst_hour = array_key_first($hourly_data);
    $worst_hour_vehicles = $hourly_data[$worst_hour];
    
    foreach ($hourly_data as $hour => $count) {
        if ($count > $worst_hour_vehicles) {
            $worst_hour = $hour;
            $worst_hour_vehicles = $count;
        }
    }
    
    $best_time_start = str_pad($best_hour, 2, '0', STR_PAD_LEFT) . ':00';
    $best_time_end = str_pad(($best_hour + 2) % 24, 2, '0', STR_PAD_LEFT) . ':00';
    
    return [
        'status' => $status,
        'severity' => $severity,
        'occupancy_percent' => round($occupancy_percent, 1),
        'current_hour' => $current_hour,
        'current_vehicles' => $current_vehicles,
        'total_spaces' => $total_spaces,
        'available_spaces' => max(0, $total_spaces - $current_vehicles),
        'best_time' => "Between {$best_time_start} and {$best_time_end}",
        'best_hour' => $best_hour,
        'worst_hour' => $worst_hour,
        'hourly_data' => $hourly_data
    ];
}

?>
