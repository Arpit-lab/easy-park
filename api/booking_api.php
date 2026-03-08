<?php
require_once '../includes/session.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!Session::isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Please login to continue'
    ]);
    exit();
}

$conn = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'check_availability':
            $space_id = intval($_POST['space_id']);
            $check_in = $_POST['check_in'];
            $check_out = $_POST['check_out'];
            
            $available = checkSpaceAvailability($space_id, $check_in, $check_out);
            
            echo json_encode([
                'success' => true,
                'available' => $available
            ]);
            break;
            
        case 'calculate_cost':
            $space_id = intval($_POST['space_id']);
            $hours = floatval($_POST['hours']);
            
            $space = getParkingSpaceById($space_id);
            
            if ($space) {
                $cost = $hours * $space['price_per_hour'];
                
                // Apply any discounts
                if ($hours >= 24) {
                    $days = floor($hours / 24);
                    $cost = ($days * $space['daily_rate']) + (($hours % 24) * $space['price_per_hour']);
                }
                
                echo json_encode([
                    'success' => true,
                    'cost' => $cost,
                    'formatted' => 'रू ' . number_format($cost, 2)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Space not found'
                ]);
            }
            break;
            
        case 'cancel_booking':
            $booking_id = intval($_POST['booking_id']);
            $user_id = $_SESSION['user_id'];
            
            // Check if booking belongs to user
            $check = $conn->prepare("
                SELECT id FROM parking_bookings 
                WHERE id = ? AND user_id = ? AND booking_status = 'active'
            ");
            $check->bind_param("ii", $booking_id, $user_id);
            $check->execute();
            $result = $check->get_result();
            
            if ($result->num_rows > 0) {
                $stmt = $conn->prepare("
                    UPDATE parking_bookings 
                    SET booking_status = 'cancelled' 
                    WHERE id = ?
                ");
                $stmt->bind_param("i", $booking_id);
                
                if ($stmt->execute()) {
                    // Free up the parking space
                    $space_update = $conn->prepare("
                        UPDATE parking_spaces ps
                        JOIN parking_bookings pb ON pb.space_id = ps.id
                        SET ps.is_available = 1
                        WHERE pb.id = ?
                    ");
                    $space_update->bind_param("i", $booking_id);
                    $space_update->execute();
                    
                    logActivity($user_id, 'cancel_booking', "Cancelled booking #$booking_id");
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Booking cancelled successfully'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false,
                        'message' => 'Failed to cancel booking'
                    ]);
                }
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Booking not found or unauthorized'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>