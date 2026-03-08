<?php
require_once '../includes/session.php';
Session::requireUser();

require_once '../includes/functions.php';

if (isset($_POST['booking_id'])) {
    $conn = getDB();
    $booking_id = intval($_POST['booking_id']);
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("
        SELECT pb.*, ps.space_number, ps.location_name, ps.address, ps.price_per_hour,
               vc.category_name
        FROM parking_bookings pb
        JOIN parking_spaces ps ON pb.space_id = ps.id
        JOIN vehicle_categories vc ON ps.category_id = vc.id
        WHERE pb.id = ? AND pb.user_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    
    if ($booking) {
        ?>
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2">Booking Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>Booking Number:</th>
                            <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <span class="badge bg-<?php 
                                    echo $booking['booking_status'] == 'active' ? 'success' : 
                                        ($booking['booking_status'] == 'completed' ? 'info' : 'danger'); 
                                ?>">
                                    <?php echo ucfirst($booking['booking_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Vehicle Number:</th>
                            <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Check In:</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($booking['check_in'])); ?></td>
                        </tr>
                        <tr>
                            <th>Expected Check Out:</th>
                            <td><?php echo $booking['expected_check_out'] ? date('d M Y, h:i A', strtotime($booking['expected_check_out'])) : 'N/A'; ?></td>
                        </tr>
                        <?php if ($booking['check_out']): ?>
                        <tr>
                            <th>Actual Check Out:</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($booking['check_out'])); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="col-md-6">
                    <h6 class="border-bottom pb-2">Parking Space Details</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>Space Number:</th>
                            <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Location:</th>
                            <td><?php echo htmlspecialchars($booking['location_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo htmlspecialchars($booking['address']); ?></td>
                        </tr>
                        <tr>
                            <th>Category:</th>
                            <td><?php echo htmlspecialchars($booking['category_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Rate:</th>
                            <td>रू <?php echo number_format($booking['price_per_hour'], 2); ?> per hour</td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <h6 class="border-bottom pb-2">Payment Information</h6>
                    <table class="table table-sm">
                        <tr>
                            <th>Total Amount:</th>
                            <td><strong>रू <?php echo number_format($booking['total_amount'] ?? 0, 2); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Payment Status:</th>
                            <td>
                                <span class="badge bg-<?php echo $booking['payment_status'] == 'paid' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($booking['payment_status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Booked On:</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($booking['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    } else {
        echo '<div class="alert alert-danger">Booking not found.</div>';
    }
}
?>