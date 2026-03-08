<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Outgoing Vehicle';
$page_icon = 'arrow-left';

$conn = getDB();
$message = '';
$error = '';

// Handle vehicle exit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_id'])) {
    $booking_id = intval($_POST['booking_id']);
    $payment_method = $_POST['payment_method'];
    
    // Get booking details
    $booking_query = $conn->prepare("
        SELECT pb.*, ps.price_per_hour, vc.hourly_rate, vc.daily_rate, ps.space_number
        FROM parking_bookings pb
        JOIN parking_spaces ps ON pb.space_id = ps.id
        JOIN vehicle_categories vc ON ps.category_id = vc.id
        WHERE pb.id = ? AND pb.booking_status = 'active'
    ");
    $booking_query->bind_param("i", $booking_id);
    $booking_query->execute();
    $booking = $booking_query->get_result()->fetch_assoc();
    
    if ($booking) {
        $check_out = date('Y-m-d H:i:s');
        $hours = calculateDuration($booking['check_in'], $check_out);
        $amount = $hours * $booking['price_per_hour'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update booking
            $update_booking = $conn->prepare("
                UPDATE parking_bookings 
                SET check_out = ?, total_amount = ?, booking_status = 'completed' 
                WHERE id = ?
            ");
            $update_booking->bind_param("sdi", $check_out, $amount, $booking_id);
            $update_booking->execute();
            
            // Create transaction
            $receipt_number = generateReceiptNumber();
            $transaction = $conn->prepare("
                INSERT INTO parking_transactions 
                (booking_id, vehicle_number, check_in, check_out, duration_hours, amount, payment_method, receipt_number, operator_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $transaction->bind_param("issssdssi", 
                $booking_id, 
                $booking['vehicle_number'], 
                $booking['check_in'], 
                $check_out, 
                $hours, 
                $amount, 
                $payment_method, 
                $receipt_number,
                $_SESSION['user_id']
            );
            $transaction->execute();
            
            // Update vehicle status
            $conn->query("UPDATE vehicles SET status = 'out' WHERE id = " . $booking['vehicle_id']);
            
            // Free up parking space
            $conn->query("UPDATE parking_spaces SET is_available = 1 WHERE id = " . $booking['space_id']);
            
            $conn->commit();
            
            $message = "Vehicle checked out successfully!";
            logActivity($_SESSION['user_id'], 'vehicle_exit', "Vehicle {$booking['vehicle_number']} checked out");
            
            // Redirect to print receipt
            header("Location: print_receipt.php?transaction_id=" . $conn->insert_id);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error processing checkout: " . $e->getMessage();
        }
    }
}

// Get active bookings
$active_bookings = $conn->query("
    SELECT pb.*, ps.space_number, ps.price_per_hour, v.owner_name, v.owner_phone
    FROM parking_bookings pb
    JOIN parking_spaces ps ON pb.space_id = ps.id
    LEFT JOIN vehicles v ON pb.vehicle_id = v.id
    WHERE pb.booking_status = 'active'
    ORDER BY pb.check_in DESC
");

include 'includes/header.php';
?>

<div class="container-fluid px-0">
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <?php
        $active_count = $conn->query("SELECT COUNT(*) as count FROM parking_bookings WHERE booking_status = 'active'")->fetch_assoc()['count'];
        $today_checkouts = $conn->query("SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total FROM parking_bookings WHERE DATE(check_out) = CURDATE() AND booking_status = 'completed'")->fetch_assoc();
        ?>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo $active_count; ?></div>
                <div class="stat-label">Active Parked</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon"></i>
                <div class="stat-value"><?php echo $today_checkouts['count']; ?></div>
                <div class="stat-label">Today's Checkouts</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card primary">
                <i class="fas fa-dollar-sign stat-icon"></i>
                <div class="stat-value">रू <?php echo number_format($today_checkouts['total'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <i class="fas fa-list me-2"></i>Active Parked Vehicles
        </div>
        <div class="card-body">
            <?php if ($active_bookings->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table data-table">
                        <thead>
                            <tr>
                                <th>Space</th>
                                <th>Vehicle Number</th>
                                <th>Owner</th>
                                <th>Check In</th>
                                <th>Duration</th>
                                <th>Estimated Cost</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($booking = $active_bookings->fetch_assoc()): 
                                $hours = calculateDuration($booking['check_in']);
                                $cost = $hours * $booking['price_per_hour'];
                            ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($booking['space_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($booking['vehicle_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($booking['owner_name'] ?: 'N/A'); ?>
                                        <?php if ($booking['owner_phone']): ?>
                                            <br><small><?php echo htmlspecialchars($booking['owner_phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y, h:i A', strtotime($booking['check_in'])); ?></td>
                                    <td><?php echo round($hours, 1); ?> hours</td>
                                    <td><strong class="text-success">रू <?php echo number_format($cost, 2); ?></strong></td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="checkout(<?php echo $booking['id']; ?>)">
                                            <i class="fas fa-sign-out-alt me-1"></i>Checkout
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-parking fa-3x text-muted mb-3"></i>
                    <h5>No Active Vehicles</h5>
                    <p class="text-muted">There are no vehicles currently parked.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Checkout Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vehicle Checkout</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="checkout_booking_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Payment Method</label>
                        <select class="form-control" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="card">Credit/Debit Card</option>
                            <option value="online">Online Payment</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info" id="cost_display"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Process Checkout</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function checkout(bookingId) {
    $('#checkout_booking_id').val(bookingId);
    
    // Calculate and display cost
    $.ajax({
        url: 'calculate_cost.php',
        method: 'POST',
        data: {booking_id: bookingId},
        success: function(response) {
            $('#cost_display').html(response);
        }
    });
    
    $('#checkoutModal').modal('show');
}
</script>

<?php include 'includes/footer.php'; ?>