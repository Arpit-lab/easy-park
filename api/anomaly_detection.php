<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

class AnomalyDetector {
    private $conn;
    
    public function __construct() {
        $this->conn = getDB();
    }
    
    // Detect overstay vehicles
    public function detectOverstay() {
        $query = "
            SELECT 
                pb.*,
                ps.space_number,
                TIMESTAMPDIFF(HOUR, pb.check_in, NOW()) as hours_parked
            FROM parking_bookings pb
            JOIN parking_spaces ps ON pb.space_id = ps.id
            WHERE pb.booking_status = 'active'
            AND pb.expected_check_out IS NOT NULL
            AND NOW() > pb.expected_check_out
            AND TIMESTAMPDIFF(HOUR, pb.expected_check_out, NOW()) > 2
        ";
        
        $result = $this->conn->query($query);
        $overstays = [];
        
        while ($row = $result->fetch_assoc()) {
            $overstays[] = $row;
            
            // Create anomaly alert if not already created
            $this->createAlert($row['vehicle_number'], $row['space_id'], 'overstay', 
                "Vehicle overstayed by " . round($row['hours_parked'] - 2) . " hours", 'medium');
        }
        
        return $overstays;
    }
    
    // Detect unauthorized vehicles in restricted spaces
    public function detectUnauthorizedVehicles() {
        $query = "
            SELECT 
                pb.*,
                ps.space_number,
                vc.category_name as required_category,
                v.category_id as vehicle_category
            FROM parking_bookings pb
            JOIN parking_spaces ps ON pb.space_id = ps.id
            JOIN vehicles v ON pb.vehicle_id = v.id
            JOIN vehicle_categories vc ON ps.category_id = vc.id
            WHERE pb.booking_status = 'active'
            AND v.category_id != ps.category_id
        ";
        
        $result = $this->conn->query($query);
        $unauthorized = [];
        
        while ($row = $result->fetch_assoc()) {
            $unauthorized[] = $row;
            
            $this->createAlert($row['vehicle_number'], $row['space_id'], 'unauthorized',
                "Vehicle type doesn't match space category", 'high');
        }
        
        return $unauthorized;
    }
    
    // Detect suspicious patterns (multiple bookings, etc.)
    public function detectSuspiciousPatterns() {
        $query = "
            SELECT 
                vehicle_number,
                COUNT(*) as booking_count,
                MIN(created_at) as first_booking,
                MAX(created_at) as last_booking
            FROM parking_bookings
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY vehicle_number
            HAVING booking_count > 3
        ";
        
        $result = $this->conn->query($query);
        $suspicious = [];
        
        while ($row = $result->fetch_assoc()) {
            $suspicious[] = $row;
            
            $this->createAlert($row['vehicle_number'], null, 'suspicious',
                "Unusual booking pattern: " . $row['booking_count'] . " bookings in 24 hours", 'medium');
        }
        
        return $suspicious;
    }
    
    // Create anomaly alert
    private function createAlert($vehicle_number, $space_id, $type, $description, $severity) {
        // Check if alert already exists and is still new
        $check = $this->conn->prepare("
            SELECT id FROM anomaly_alerts 
            WHERE vehicle_number = ? AND alert_type = ? AND status = 'new'
        ");
        $check->bind_param("ss", $vehicle_number, $type);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows == 0) {
            $stmt = $this->conn->prepare("
                INSERT INTO anomaly_alerts (vehicle_number, space_id, alert_type, description, severity)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sisss", $vehicle_number, $space_id, $type, $description, $severity);
            $stmt->execute();
        }
    }
    
    // Run all detection methods
    public function runAllDetections() {
        $results = [
            'overstays' => $this->detectOverstay(),
            'unauthorized' => $this->detectUnauthorizedVehicles(),
            'suspicious' => $this->detectSuspiciousPatterns()
        ];
        
        return $results;
    }
}

// API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'all';
    
    $detector = new AnomalyDetector();
    
    switch ($action) {
        case 'overstay':
            $results = $detector->detectOverstay();
            break;
        case 'unauthorized':
            $results = $detector->detectUnauthorizedVehicles();
            break;
        case 'suspicious':
            $results = $detector->detectSuspiciousPatterns();
            break;
        default:
            $results = $detector->runAllDetections();
    }
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>