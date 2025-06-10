<!-- admin/dashboard.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

// Statistik dashboard
$stats = [];

// Total booking
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings");
$stats['total_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total revenue
$stmt = $db->query("SELECT SUM(total_amount) as total FROM bookings WHERE status IN ('paid', 'completed')");
$stats['total_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Booking pending
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE status = 'pending'");
$stats['pending_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Customer aktif
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE role = 'customer'");
$stats['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];


// Tambahkan statistik pembayaran untuk admin
$stmt = $db->query("SELECT 
    COUNT(CASE WHEN pay.total_paid >= b.down_payment AND pay.total_paid < b.total_amount THEN 1 END) as dp_paid,
    COUNT(CASE WHEN pay.total_paid >= b.total_amount THEN 1 END) as fully_paid
    FROM bookings b 
    LEFT JOIN (
        SELECT booking_id, SUM(amount) as total_paid 
        FROM payments 
        WHERE status = 'verified' 
        GROUP BY booking_id
    ) pay ON b.id = pay.booking_id");
$payment_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Booking terbaru - UBAH QUERY MENJADI:
$stmt = $db->prepare("SELECT b.*, u.full_name as customer_name, p.name as package_name, p.service_type as service_name,
                     COALESCE(SUM(pay.amount), 0) as total_paid
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     JOIN packages p ON b.package_id = p.id 
                     LEFT JOIN payments pay ON b.id = pay.booking_id AND pay.status = 'verified'
                     GROUP BY b.id
                     ORDER BY b.created_at DESC LIMIT 5");
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Booking bulan ini
$stmt = $db->query("SELECT COUNT(*) as total FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())");
$stats['monthly_bookings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];


// Function untuk admin dashboard status
function getAdminDashboardStatus($booking) {
    if ($booking['total_paid'] >= $booking['total_amount']) {
        return 'Lunas';
    } elseif ($booking['total_paid'] >= $booking['down_payment']) {
        return 'DP Dibayar';
    } else {
        $status_map = [
            'pending' => 'Menunggu Konfirmasi',
            'confirmed' => 'Dikonfirmasi', 
            'paid' => 'Dibayar (DP)',
            'in_progress' => 'Sedang Berlangsung',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan'
        ];
        return $status_map[$booking['status']] ?? ucfirst($booking['status']);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Dandy Gallery Gown</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        .page-title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .stat-icon.bookings {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .stat-icon.revenue {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }

        .stat-icon.pending {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .stat-icon.customers {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
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

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .status.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status.paid {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status.completed {
            background: #d4edda;
            color: #155724;
        }

        .btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: opacity 0.3s;
        }

        .btn:hover {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
            <li><a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Kelola Paket</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <h1 class="page-title">Dashboard</h1>

        <!-- Statistics Cards -->
        <div class="stats-grid">
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
                <div class="stat-number"><?php echo formatRupiah($stats['total_revenue']); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                <div class="stat-label">Booking Pending</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                <div class="stat-label">Total Customer</div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Booking Terbaru</h3>
            </div>
            <div class="card-body">
                <?php if (empty($recent_bookings)): ?>
                    <p style="text-align: center; color: #666; padding: 2rem;">Belum ada booking</p>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Pengantin</th>
                                <th>Paket</th>
                                <th>Tanggal Event</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
    <?php foreach ($recent_bookings as $booking): ?>
    <tr>
        <td><?php echo htmlspecialchars($booking['booking_code']); ?></td>
        <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
        <td>
            <?php echo htmlspecialchars($booking['package_name']); ?><br>
<?php $service_icon = getServiceIcon($booking['service_name']); ?>
<i class="<?php echo $service_icon; ?>"></i>
            <small style="color: #666;"><?php echo htmlspecialchars($booking['service_name']); ?></small>
        </td>
        <td>
            <?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?><br>
        </td>
        <td>
            <?php echo formatRupiah($booking['total_amount']); ?>
            <?php if ($booking['total_paid'] > 0): ?>
                <br><small style="color: #28a745;">
                    Dibayar: <?php echo formatRupiah($booking['total_paid']); ?>
                </small>
            <?php endif; ?>
        </td>
        <td>
            <span class="status <?php echo getAdminDashboardStatus($booking) === 'Lunas' ? 'completed' : ($booking['total_paid'] >= $booking['down_payment'] ? 'paid' : $booking['status']); ?>">
                <?php echo getAdminDashboardStatus($booking); ?>
            </span>
        </td>
        <td>
            <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn">
                <i class="fas fa-eye"></i> Detail
            </a>
        </td>
    </tr>
    <?php endforeach; ?>
</tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>