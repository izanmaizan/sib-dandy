<!-- customer/dashboard.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

// Ambil data customer
// $stmt = $db->prepare("SELECT * FROM customers WHERE user_id = ?");
// $stmt->execute([$_SESSION['user_id']]);
// $customer = $stmt->fetch(PDO::FETCH_ASSOC);

// Jika belum ada data customer, redirect ke form
// if (!$customer) {
//     header('Location: profile.php');
//     exit();
// }

// Ambil booking customer
$stmt = $db->prepare("SELECT b.*, 
                     p.name as package_name, p.price as package_price,
                     COALESCE(SUM(pay.amount), 0) as total_paid
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id 
                     LEFT JOIN payments pay ON b.id = pay.booking_id AND pay.status = 'verified'
                     WHERE b.user_id = ? 
                     GROUP BY b.id
                     ORDER BY b.created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik customer
$stats = [];
$stats['total_bookings'] = count($bookings);
$stats['completed_bookings'] = count(array_filter($bookings, function ($b) {
    return $b['status'] === 'completed';
}));
$stats['pending_bookings'] = count(array_filter($bookings, function ($b) {
    return $b['status'] === 'pending';
}));
$stats['total_spent'] = array_sum(array_column($bookings, 'total_paid'));
$stats['dp_paid_bookings'] = count(array_filter($bookings, function ($b) {
    return $b['total_paid'] >= $b['down_payment'] && $b['total_paid'] < $b['total_amount'];
}));
$stats['fully_paid_bookings'] = count(array_filter($bookings, function ($b) {
    return $b['total_paid'] >= $b['total_amount'];
}));


// Function status untuk dashboard
function getDashboardStatus($booking)
{
    if ($booking['total_paid'] >= $booking['total_amount']) {
        return 'Lunas';
    } elseif ($booking['total_paid'] >= $booking['down_payment']) {
        return 'DP Dibayar';
    } else {
        $status_map = [
            'pending' => 'Menunggu Konfirmasi',
            'confirmed' => 'Dikonfirmasi',
            'paid' => 'Dibayar (DP)',
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
    <title>Dashboard Customer - Dandy Gallery Gown</title>
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
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
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

        .welcome-card {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .welcome-card h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .welcome-card p {
            opacity: 0.9;
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
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

        .stat-icon.completed {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }

        .stat-icon.pending {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .stat-icon.spent {
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .card-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .status.cancelled {
            background: #f8d7da;
            color: #721c24;
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
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            opacity: 0.8;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                <i class="fas fa-ring"></i> Dandy Gallery
            </div>
            <div class="user-info">
                <span>Halo, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
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
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Booking Saya</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
            <li><a href="../packages.php"><i class="fas fa-box"></i> Lihat Paket</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p>Kelola semua booking dan persiapan pernikahan Anda dengan mudah melalui dashboard ini.</p>
        </div>

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
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['fully_paid_bookings']; ?></div>
                <div class="stat-label">Booking Lunas</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-number"><?php echo $stats['dp_paid_bookings']; ?></div>
                <div class="stat-label">DP Dibayar</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon spent">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo formatRupiah($stats['total_spent']); ?></div>
                <div class="stat-label">Total Pengeluaran</div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Booking Terbaru</h3>
                <a href="booking_new.php" class="btn">
                    <i class="fas fa-plus"></i> Booking Baru
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Belum Ada Booking</h3>
                        <p>Anda belum memiliki booking apapun. Mulai booking paket impian Anda sekarang!</p>
                        <a href="booking_new.php" class="btn" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Buat Booking Pertama
                        </a>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Paket</th>
                                <th>Tanggal Event</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($bookings, 0, 5) as $booking): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($booking['package_name']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?></td>
                                    <td>
                                        <?php echo formatRupiah($booking['total_amount']); ?>
                                        <?php if ($booking['total_paid'] > 0): ?>
                                            <br><small style="color: #28a745;">Dibayar:
                                                <?php echo formatRupiah($booking['total_paid']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span
                                            class="status <?php echo getDashboardStatus($booking) === 'Lunas' ? 'completed' : ($booking['total_paid'] >= $booking['down_payment'] ? 'paid' : $booking['status']); ?>">
                                            <?php echo getDashboardStatus($booking); ?>
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

                    <?php if (count($bookings) > 5): ?>
                        <div style="text-align: center; margin-top: 1rem;">
                            <a href="bookings.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i> Lihat Semua Booking
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

    </main>
</body>

</html>