<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$conn = getDB();
$transaction_id = isset($_GET['transaction_id']) ? intval($_GET['transaction_id']) : 0;
$receipt_number = isset($_GET['receipt']) ? $_GET['receipt'] : '';

if ($transaction_id > 0) {
    $stmt = $conn->prepare("
        SELECT pt.*, pb.vehicle_number, pb.check_in, pb.check_out, ps.space_number, ps.location_name,
               u.full_name as operator_name
        FROM parking_transactions pt
        JOIN parking_bookings pb ON pt.booking_id = pb.id
        JOIN parking_spaces ps ON pb.space_id = ps.id
        LEFT JOIN users u ON pt.operator_id = u.id
        WHERE pt.id = ?
    ");
    $stmt->bind_param("i", $transaction_id);
} else if (!empty($receipt_number)) {
    $stmt = $conn->prepare("
        SELECT pt.*, pb.vehicle_number, pb.check_in, pb.check_out, ps.space_number, ps.location_name,
               u.full_name as operator_name
        FROM parking_transactions pt
        JOIN parking_bookings pb ON pt.booking_id = pb.id
        JOIN parking_spaces ps ON pb.space_id = ps.id
        LEFT JOIN users u ON pt.operator_id = u.id
        WHERE pt.receipt_number = ?
    ");
    $stmt->bind_param("s", $receipt_number);
} else {
    die("Invalid request");
}

$stmt->execute();
$transaction = $stmt->get_result()->fetch_assoc();

if (!$transaction) {
    die("Transaction not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parking Receipt - EasyPark</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            margin: 0;
            padding: 20px;
        }
        .receipt {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }
        .header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            color: #333;
        }
        .header h3 {
            margin: 5px 0;
            color: #666;
            font-weight: normal;
        }
        .receipt-number {
            text-align: center;
            background: #f8f9fa;
            padding: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            border-radius: 5px;
        }
        .details {
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dotted #ccc;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .amount {
            background: #e8f4f8;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            border-radius: 5px;
        }
        .amount .label {
            font-size: 14px;
            color: #666;
        }
        .amount .value {
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px dashed #333;
            font-size: 12px;
            color: #666;
        }
        .print-button {
            text-align: center;
            margin-top: 20px;
        }
        .print-button button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .print-button button:hover {
            background: #0056b3;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .receipt {
                box-shadow: none;
                padding: 0;
            }
            .print-button {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <h1>EasyPark</h1>
            <h3>Smart Parking Management System</h3>
        </div>
        
        <div class="receipt-number">
            Receipt #: <?php echo htmlspecialchars($transaction['receipt_number']); ?>
        </div>
        
        <div class="details">
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span class="detail-value"><?php echo date('d/m/Y', strtotime($transaction['created_at'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span class="detail-value"><?php echo date('h:i A', strtotime($transaction['created_at'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Vehicle Number:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['vehicle_number']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Parking Space:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['space_number']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['location_name']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check In:</span>
                <span class="detail-value"><?php echo date('d/m/Y h:i A', strtotime($transaction['check_in'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Check Out:</span>
                <span class="detail-value"><?php echo date('d/m/Y h:i A', strtotime($transaction['check_out'])); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Duration:</span>
                <span class="detail-value"><?php echo round($transaction['duration_hours'], 1); ?> hours</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value"><?php echo ucfirst($transaction['payment_method']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Operator:</span>
                <span class="detail-value"><?php echo htmlspecialchars($transaction['operator_name'] ?: 'System'); ?></span>
            </div>
        </div>
        
        <div class="amount">
            <div class="label">TOTAL AMOUNT</div>
            <div class="value">रू <?php echo number_format($transaction['amount'], 2); ?></div>
        </div>
        
        <div class="footer">
            <p>Thank you for using EasyPark!</p>
            <p>This is a computer generated receipt.</p>
            <p>For any queries, please contact support@easypark.com</p>
        </div>
        
        <div class="print-button">
            <button onclick="window.print()"><i class="fas fa-print"></i> Print Receipt</button>
        </div>
    </div>
</body>
</html>