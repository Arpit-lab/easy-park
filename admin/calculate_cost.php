<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if (isset($_POST['booking_id'])) {
    $conn = getDB();
    $booking_id = intval($_POST['booking_id']);
    
    $query = $conn->prepare("
        SELECT pb.*, ps.price_per_hour
        FROM parking_bookings pb
        JOIN parking_spaces ps ON pb.space_id = ps.id
        WHERE pb.id = ?
    ");
    $query->bind_param("i", $booking_id);
    $query->execute();
    $booking = $query->get_result()->fetch_assoc();
    
    if ($booking) {
        $hours = calculateDuration($booking['check_in']);
        $cost = $hours * $booking['price_per_hour'];
        
        echo "
            <strong>Duration:</strong> " . round($hours, 1) . " hours<br>
            <strong>Rate:</strong> रू " . number_format($booking['price_per_hour'], 2) . "/hour<br>
            <strong>Total Amount:</strong> रू " . number_format($cost, 2) . "
        ";
    }
}
?>