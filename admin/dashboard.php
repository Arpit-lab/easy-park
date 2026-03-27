<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Dashboard';
$page_icon = 'tachometer-alt';

$stats = getDashboardStats('admin');

// Get recent activities
$conn = getDB();
$recent_activities = $conn->query("
    SELECT al.*, u.username 
    FROM activity_logs al 
    JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");

// Get active bookings
$active_bookings = $conn->query("
    SELECT pb.*, ps.space_number, u.username 
    FROM parking_bookings pb 
    JOIN parking_spaces ps ON pb.space_id = ps.id 
    JOIN users u ON pb.user_id = u.id 
    WHERE pb.booking_status = 'active' 
    ORDER BY pb.check_in DESC 
    LIMIT 5
");

// Get today's transactions
$today_transactions = $conn->query("
    SELECT * FROM parking_transactions 
    WHERE DATE(created_at) = CURDATE() 
    ORDER BY created_at DESC 
    LIMIT 10
");

include 'includes/header.php';
?>

<style>
    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        transition: transform 0.3s;
        border: none;
        overflow: hidden;
        position: relative;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card .stat-icon {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 48px;
        color: rgba(102, 126, 234, 0.2);
    }

    .stat-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
        color: #333;
        margin-bottom: 5px;
    }

    .stat-card .stat-label {
        color: #666;
        font-size: 1rem;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .stat-card.primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .stat-card.primary .stat-value,
    .stat-card.primary .stat-label,
    .stat-card.primary .stat-icon {
        color: white;
    }

    .welcome-message {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .welcome-message h2 {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    .chart-container {
        position: relative;
        height: 300px;
        margin: 20px 0;
    }
</style>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Page Content -->
<div class="container-fluid px-0">
    <!-- Welcome Message -->
    <div class="welcome-message">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
                <p class="mb-0">Here's what's happening with your parking system today.</p>
            </div>
            <div class="col-md-4 text-end">
                <p class="mb-0"><i class="fas fa-calendar me-2"></i><?php echo date('l, F j, Y'); ?></p>
                <p class="mb-0"><i class="fas fa-clock me-2"></i><?php echo date('h:i A'); ?></p>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                <div class="stat-label">Total Users</div>
                <small class="text-success"><i class="fas fa-arrow-up"></i> +12% from last month</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <i class="fas fa-car stat-icon"></i>
                <div class="stat-value"><?php echo $stats['total_vehicles']; ?></div>
                <div class="stat-label">Total Vehicles</div>
                <small class="text-success"><i class="fas fa-arrow-up"></i> +8% from last month</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card primary">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo $stats['active_bookings']; ?></div>
                <div class="stat-label">Active Bookings</div>
                <small><i class="fas fa-users"></i> Currently parked</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stat-card">
                <i class="fas fa-dollar-sign stat-icon"></i>
                <div class="stat-value">रू <?php echo number_format($stats['today_revenue'], 2); ?></div>
                <div class="stat-label">Today's Revenue</div>
                <small class="text-success"><i class="fas fa-arrow-up"></i> +5% from yesterday</small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Revenue Overview
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Vehicle Distribution
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="vehicleChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Bookings and Recent Transactions -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock me-2"></i>Active Bookings
                    <a href="manage_bookings.php" class="btn btn-sm btn-primary float-end">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Booking #</th>
                                    <th>User</th>
                                    <th>Space</th>
                                    <th>Check In</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($active_bookings->num_rows > 0): ?>
                                    <?php while ($booking = $active_bookings->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['booking_number']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['username']); ?></td>
                                            <td><?php echo htmlspecialchars($booking['space_number']); ?></td>
                                            <td><?php echo date('h:i A', strtotime($booking['check_in'])); ?></td>
                                            <td><span class="badge badge-success">Active</span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No active bookings</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i>Today's Transactions
                    <a href="transactions.php" class="btn btn-sm btn-primary float-end">View All</a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Receipt #</th>
                                    <th>Vehicle</th>
                                    <th>Amount</th>
                                    <th>Time</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($today_transactions->num_rows > 0): ?>
                                    <?php while ($trans = $today_transactions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($trans['receipt_number']); ?></td>
                                            <td><?php echo htmlspecialchars($trans['vehicle_number']); ?></td>
                                            <td>रू <?php echo number_format($trans['amount'], 2); ?></td>
                                            <td><?php echo date('h:i A', strtotime($trans['created_at'])); ?></td>
                                            <td><span class="badge badge-success"><?php echo $trans['payment_method']; ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No transactions today</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activities -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history me-2"></i>Recent Activities
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($activity = $recent_activities->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('h:i A', strtotime($activity['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['action']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Revenue Chart
    const ctx1 = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Revenue (रू)',
                data: [12000, 19000, 15000, 22000, 18000, 24000, 28000, 26000, 30000, 32000, 35000, 38000],
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'रू ' + value;
                        }
                    }
                }
            }
        }
    });

    // Vehicle Distribution Chart
    const ctx2 = document.getElementById('vehicleChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Cars', 'Motorcycles', 'SUVs', 'Trucks', 'Bicycles'],
            datasets: [{
                data: [45, 25, 15, 10, 5],
                backgroundColor: ['#667eea', '#764ba2', '#ff6b6b', '#4ecdc4', '#ffe66d'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
</script>

<?php include 'includes/footer.php'; ?>
