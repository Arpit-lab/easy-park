<?php
require_once '../includes/session.php';
Session::requireAdmin();

require_once '../includes/functions.php';

$page_title = 'Analytics';
$page_icon = 'chart-bar';

$conn = getDB();

// Get date range
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

if (isset($_GET['date_range'])) {
    $dates = explode(' - ', $_GET['date_range']);
    if (count($dates) == 2) {
        $start_date = date('Y-m-d', strtotime($dates[0]));
        $end_date = date('Y-m-d', strtotime($dates[1]));
    }
}

// Revenue by day
$revenue_by_day = $conn->prepare("
    SELECT DATE(created_at) as date, COUNT(*) as transactions, SUM(amount) as total
    FROM parking_transactions
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$revenue_by_day->bind_param("ss", $start_date, $end_date);
$revenue_by_day->execute();
$daily_revenue = $revenue_by_day->get_result();

// Revenue by category
$revenue_by_category = $conn->query("
    SELECT vc.category_name, COUNT(pt.id) as bookings, SUM(pt.amount) as revenue
    FROM parking_transactions pt
    JOIN parking_bookings pb ON pt.booking_id = pb.id
    JOIN vehicles v ON pb.vehicle_id = v.id
    JOIN vehicle_categories vc ON v.category_id = vc.id
    GROUP BY vc.id
    ORDER BY revenue DESC
");

// Peak hours analysis
$peak_hours = $conn->query("
    SELECT HOUR(check_in) as hour, COUNT(*) as bookings
    FROM parking_bookings
    WHERE check_in >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY HOUR(check_in)
    ORDER BY hour
");

// Summary statistics
$summary = $conn->query("
    SELECT 
        COUNT(DISTINCT pb.id) as total_bookings,
        COUNT(DISTINCT v.id) as unique_vehicles,
        COALESCE(SUM(pt.amount), 0) as total_revenue,
        COALESCE(AVG(pt.duration_hours), 0) as avg_duration,
        COALESCE(AVG(pt.amount), 0) as avg_amount
    FROM parking_bookings pb
    LEFT JOIN parking_transactions pt ON pb.id = pt.booking_id
    LEFT JOIN vehicles v ON pb.vehicle_id = v.id
    WHERE pb.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
")->fetch_assoc();

include 'includes/header.php';
?>

<div class="container-fluid px-0">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-calendar-check stat-icon"></i>
                <div class="stat-value"><?php echo $summary['total_bookings'] ?? 0; ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-dollar-sign stat-icon"></i>
                <div class="stat-value">रू <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?php echo round($summary['avg_duration'] ?? 0, 1); ?> hrs</div>
                <div class="stat-label">Avg Duration</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card primary">
                <i class="fas fa-car stat-icon"></i>
                <div class="stat-value"><?php echo $summary['unique_vehicles'] ?? 0; ?></div>
                <div class="stat-label">Unique Vehicles</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-line me-2"></i>Revenue Overview
                </div>
                <div class="card-body">
                    <canvas id="revenueChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie me-2"></i>Revenue by Category
                </div>
                <div class="card-body">
                    <canvas id="categoryChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-clock me-2"></i>Peak Hours Analysis
                </div>
                <div class="card-body">
                    <canvas id="peakHoursChart" height="300"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-table me-2"></i>Daily Summary
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transactions</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $daily_revenue->data_seek(0);
                                while ($row = $daily_revenue->fetch_assoc()): 
                                ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo $row['transactions']; ?></td>
                                        <td><strong>रू <?php echo number_format($row['total'], 2); ?></strong></td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Revenue Chart
const ctx1 = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx1, {
    type: 'line',
    data: {
        labels: [<?php 
            $dates = [];
            $revenues = [];
            $daily_revenue->data_seek(0);
            while ($row = $daily_revenue->fetch_assoc()) {
                $dates[] = "'" . date('d M', strtotime($row['date'])) . "'";
                $revenues[] = $row['total'];
            }
            echo implode(',', $dates);
        ?>],
        datasets: [{
            label: 'Revenue (रू)',
            data: [<?php echo implode(',', $revenues); ?>],
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
        }
    }
});

// Category Chart
const ctx2 = document.getElementById('categoryChart').getContext('2d');
new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            $categories = [];
            $revenues = [];
            $revenue_by_category->data_seek(0);
            while ($row = $revenue_by_category->fetch_assoc()) {
                $categories[] = "'" . $row['category_name'] . "'";
                $revenues[] = $row['revenue'];
            }
            echo implode(',', $categories);
        ?>],
        datasets: [{
            data: [<?php echo implode(',', $revenues); ?>],
            backgroundColor: ['#667eea', '#764ba2', '#f687b3', '#f6ad55', '#68d391'],
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

// Peak Hours Chart
const ctx3 = document.getElementById('peakHoursChart').getContext('2d');
new Chart(ctx3, {
    type: 'bar',
    data: {
        labels: [<?php 
            $hours = [];
            $bookings = [];
            $peak_hours->data_seek(0);
            while ($row = $peak_hours->fetch_assoc()) {
                $hours[] = "'" . $row['hour'] . ":00'";
                $bookings[] = $row['bookings'];
            }
            echo implode(',', $hours);
        ?>],
        datasets: [{
            label: 'Number of Bookings',
            data: [<?php echo implode(',', $bookings); ?>],
            backgroundColor: '#667eea',
            borderRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>