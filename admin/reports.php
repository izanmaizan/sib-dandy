<!-- admin/repots.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

// Filter periode
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

// Generate laporan berdasarkan periode
switch ($period) {
    case 'week':
        $start_date = date('Y-m-d', strtotime('monday this week'));
        $end_date = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $start_date = "$year-$month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        break;
    case 'year':
        $start_date = "$year-01-01";
        $end_date = "$year-12-31";
        break;
    default:
        $start_date = date('Y-m-01');
        $end_date = date('Y-m-t');
}

// Statistik umum
$stats = [];

// Total booking dalam periode
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Revenue dalam periode
$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM bookings WHERE status IN ('paid', 'completed') AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Customer baru dalam periode - DIPERBAIKI: menggunakan users dengan role customer
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'customer' AND DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$stats['new_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Booking status breakdown
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM bookings WHERE DATE(created_at) BETWEEN ? AND ? GROUP BY status");
$stmt->execute([$start_date, $end_date]);
$status_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top paket dalam periode
$stmt = $db->prepare("SELECT p.name, p.price, COUNT(b.id) as booking_count, SUM(b.total_amount) as total_revenue 
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id 
                     WHERE DATE(b.created_at) BETWEEN ? AND ? 
                     GROUP BY p.id 
                     ORDER BY booking_count DESC 
                     LIMIT 5");
$stmt->execute([$start_date, $end_date]);
$top_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly trend (untuk chart)
$stmt = $db->prepare("SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as booking_count,
                        SUM(total_amount) as revenue
                     FROM bookings 
                     WHERE YEAR(created_at) = ? 
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month");
$stmt->execute([$year]);
$monthly_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Payment method statistics
$stmt = $db->prepare("SELECT payment_method, COUNT(*) as count, SUM(amount) as total_amount 
                     FROM payments p
                     JOIN bookings b ON p.booking_id = b.id
                     WHERE DATE(p.payment_date) BETWEEN ? AND ? AND p.status = 'verified'
                     GROUP BY payment_method
                     ORDER BY total_amount DESC");
$stmt->execute([$start_date, $end_date]);
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Service type statistics - TAMBAHAN: statistik per jenis layanan
$stmt = $db->prepare("SELECT p.service_type as service_name, COUNT(b.id) as booking_count, SUM(b.total_amount) as revenue
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id
                     WHERE DATE(b.created_at) BETWEEN ? AND ?
                     GROUP BY p.service_type
                     ORDER BY booking_count DESC");
$stmt->execute([$start_date, $end_date]);
$service_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tambahan: Booking trend per hari untuk periode minggu
if ($period === 'week') {
    $stmt = $db->prepare("SELECT 
                            DATE(created_at) as booking_date,
                            COUNT(*) as booking_count,
                            SUM(total_amount) as daily_revenue
                         FROM bookings 
                         WHERE DATE(created_at) BETWEEN ? AND ?
                         GROUP BY DATE(created_at)
                         ORDER BY booking_date");
    $stmt->execute([$start_date, $end_date]);
    $daily_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Dandy Gallery Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 70px;
            width: 250px;
            height: calc(100vh - 70px);
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            padding: 2rem 0;
        }

        .sidebar ul {
            list-style: none;
        }

        .sidebar a {
            display: flex;
            align-items: center;
            padding: 1rem 2rem;
            color: #333;
            text-decoration: none;
            transition: background 0.3s;
            gap: 0.5rem;
        }

        .sidebar a:hover,
        .sidebar a.active {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
        }

        .main-content {
            margin-left: 250px;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            color: #333;
        }

        .filter-section {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: auto auto auto auto auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group select {
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }

        .form-group select:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .btn {
            padding: 10px 15px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: white;
            margin: 0 auto 1rem;
        }

        .stat-icon.bookings {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .stat-icon.revenue {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }

        .stat-icon.customers {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
        }

        .report-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
        }

        .card-body {
            padding: 2rem;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-label {
            font-weight: 500;
            text-transform: capitalize;
        }

        .status-count {
            background: #f8f9fa;
            color: #666;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .period-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
            color: #666;
        }

        .method-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .method-name {
            font-weight: 500;
            text-transform: capitalize;
        }

        .method-stats {
            text-align: right;
        }

        .method-count {
            color: #666;
            font-size: 0.9rem;
        }

        .method-amount {
            color: #ff6b6b;
            font-weight: bold;
        }

        .service-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .service-name {
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .service-stats {
            text-align: right;
        }

        .service-count {
            color: #666;
            font-size: 0.9rem;
        }

        .service-revenue {
            color: #ff6b6b;
            font-weight: bold;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media print {
            .sidebar,
            .filter-section,
            .page-header .btn {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-ring"></i> Dandy Gallery Admin
            </div>
            <div class="user-info">
                <span>Selamat datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <a href="../logout.php" style="color: white; text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <!-- Sidebar -->
    <nav class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Kelola Paket</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Laporan Bisnis</h1>
            <button onclick="window.print()" class="btn">
                <i class="fas fa-print"></i> Cetak Laporan
            </button>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="period">Periode</label>
                    <select id="period" name="period" onchange="this.form.submit()">
                        <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Minggu Ini</option>
                        <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Bulan</option>
                        <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Tahun</option>
                    </select>
                </div>
                
                <?php if ($period === 'month' || $period === 'year'): ?>
                    <div class="form-group">
                        <label for="year">Tahun</label>
                        <select id="year" name="year" onchange="this.form.submit()">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <?php if ($period === 'month'): ?>
                    <div class="form-group">
                        <label for="month">Bulan</label>
                        <select id="month" name="month" onchange="this.form.submit()">
                            <?php 
                            $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                                      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            for ($m = 1; $m <= 12; $m++):
                            ?>
                                <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                                    <?php echo $months[$m-1]; ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                <?php endif; ?>
                
                <button type="submit" class="btn">
                    <i class="fas fa-sync"></i> Update
                </button>
                
                <a href="reports.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Period Info -->
        <div class="period-info">
            <strong>Periode Laporan: <?php echo date('d/m/Y', strtotime($start_date)); ?> - <?php echo date('d/m/Y', strtotime($end_date)); ?></strong>
        </div>

        <!-- Statistics Cards -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bookings">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                <div class="stat-label">Total Booking</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo formatRupiah($stats['revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number"><?php echo $stats['new_customers']; ?></div>
                <div class="stat-label">Customer Baru</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-number">
                    <?php echo $stats['total_bookings'] > 0 ? formatRupiah($stats['revenue'] / $stats['total_bookings']) : 'Rp 0'; ?>
                </div>
                <div class="stat-label">Rata-rata per Booking</div>
            </div>
        </div> -->

        <!-- Charts and Tables -->
        <div class="report-grid">
            <!-- Revenue Trend Chart -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Trend Revenue Bulanan <?php echo $year; ?></h3>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Service Type Statistics -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> Jenis Layanan</h3>
    </div>
    <div class="card-body">
        <?php if (empty($service_stats)): ?>
            <p style="text-align: center; color: #666; padding: 2rem;">
                Tidak ada data layanan dalam periode ini
            </p>
        <?php else: ?>
            <?php foreach ($service_stats as $service): ?>
                <div class="service-item">
                    <div class="service-name">
                        <?php 
                        // Perbaikan: gunakan fungsi getServiceIcon dengan pengecekan
                        $service_icon = getServiceIcon($service['service_name']); 
                        ?>
                        <i class="<?php echo $service_icon; ?>"></i>
                        <?php echo htmlspecialchars($service['service_name']); ?>
                    </div>
                    <div class="service-stats">
                        <div class="service-count"><?php echo $service['booking_count']; ?> booking</div>
                        <div class="service-revenue"><?php echo formatRupiah($service['revenue']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
        </div>

        <div class="report-grid">
            <!-- Booking Status Breakdown -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Status Booking</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($status_breakdown as $status): ?>
                        <div class="status-item">
                            <span class="status-label"><?php echo ucfirst($status['status']); ?></span>
                            <span class="status-count"><?php echo $status['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($status_breakdown)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">
                            Tidak ada data booking dalam periode ini
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-credit-card"></i> Metode Pembayaran</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($payment_methods)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">
                            Tidak ada data pembayaran dalam periode ini
                        </p>
                    <?php else: ?>
                        <?php foreach ($payment_methods as $method): ?>
                            <div class="method-item">
                                <div class="method-name"><?php echo ucfirst($method['payment_method']); ?></div>
                                <div class="method-stats">
                                    <div class="method-count"><?php echo $method['count']; ?> transaksi</div>
                                    <div class="method-amount"><?php echo formatRupiah($method['total_amount']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Packages -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-trophy"></i> Paket Terpopuler</h3>
            </div>
            <div class="card-body">
                <?php if (empty($top_packages)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">
                        Tidak ada data paket dalam periode ini
                    </p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Paket</th>
                                <th>Harga</th>
                                <th>Booking</th>
                                <th>Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_packages as $package): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($package['name']); ?></strong></td>
                                <td><?php echo formatRupiah($package['price']); ?></td>
                                <td><?php echo $package['booking_count']; ?>x</td>
                                <td><strong><?php echo formatRupiah($package['total_revenue']); ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_trend); ?>;
        
        const months = monthlyData.map(item => {
            const date = new Date(item.month + '-01');
            return date.toLocaleDateString('id-ID', { month: 'short' });
        });
        
        const bookingCounts = monthlyData.map(item => parseInt(item.booking_count));
        const revenues = monthlyData.map(item => parseFloat(item.revenue));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Revenue (Juta Rupiah)',
                    data: revenues.map(r => r / 1000000),
                    borderColor: '#ff6b6b',
                    backgroundColor: 'rgba(255, 107, 107, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Jumlah Booking',
                    data: bookingCounts,
                    borderColor: '#ffa500',
                    backgroundColor: 'rgba(255, 165, 0, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (Juta Rupiah)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Jumlah Booking'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Auto-refresh data setiap 5 menit
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>