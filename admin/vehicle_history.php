<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

if (isset($_POST['vehicle_id'])) {
    $conn = getDB();
    $vehicle_id = intval($_POST['vehicle_id']);
    
    $history = $conn->prepare("
        SELECT pb.*, ps.space_number, ps.location_name,
               pt.receipt_number, pt.amount, pt.payment_method
        FROM parking_bookings pb
        JOIN parking_spaces ps ON pb.space_id = ps.id
        LEFT JOIN parking_transactions pt ON pb.id = pt.booking_id
        WHERE pb.vehicle_id = ?
        ORDER BY pb.created_at DESC
    ");
    $history->bind_param("i", $vehicle_id);
    $history->execute();
    $result = $history->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="table table-hover">';
        echo '<thead><tr>
                <th>Date</th>
                <th>Space</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Duration</th>
                <th>Amount</th>
                <th>Receipt</th>
              </tr></thead><tbody>';
        
        while ($row = $result->fetch_assoc()) {
            $duration = '';
            if ($row['check_in'] && $row['check_out']) {
                $hours = calculateDuration($row['check_in'], $row['check_out']);
                $duration = round($hours, 1) . ' hours';
            }
            
            echo '<tr>';
            echo '<td>' . date('d M Y', strtotime($row['check_in'])) . '</td>';
            echo '<td>' . htmlspecialchars($row['space_number']) . '<br><small>' . htmlspecialchars($row['location_name']) . '</small></td>';
            echo '<td>' . date('h:i A', strtotime($row['check_in'])) . '</td>';
            echo '<td>' . ($row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : 'Still Parked') . '</td>';
            echo '<td>' . $duration . '</td>';
            echo '<td><strong>रू ' . number_format($row['amount'] ?? 0, 2) . '</strong></td>';
            echo '<td>';
            if ($row['receipt_number']) {
                echo '<a href="print_receipt.php?receipt=' . $row['receipt_number'] . '" class="btn btn-sm btn-outline-primary" target="_blank">';
                echo '<i class="fas fa-print"></i> Print';
                echo '</a>';
            } else {
                echo 'Not available';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">No parking history found for this vehicle.</div>';
    }
}
?>