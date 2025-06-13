<!-- admin/bookings.php  -->

<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

$success = '';
$error = '';

// Handle actions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_booking_status':
            $booking_id = (int)$_POST['booking_id'];
            $new_status = $_POST['status'];
            $notes = trim($_POST['notes']);

            $stmt = $db->prepare("UPDATE bookings SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt->execute([$new_status, $notes, $booking_id])) {
                $success = "Status booking berhasil diupdate!";
            } else {
                $error = "Gagal mengupdate status booking!";
            }
            break;

        case 'update_payment_status':
            $payment_id = (int)$_POST['payment_id'];
            $new_status = $_POST['payment_status'];
            $payment_notes = trim($_POST['payment_notes']);

            $stmt = $db->prepare("UPDATE payments SET status = ?, notes = ? WHERE id = ?");
            if ($stmt->execute([$new_status, $payment_notes, $payment_id])) {
                $success = "Status pembayaran berhasil diupdate!";

                // Auto-update booking status
                if ($new_status === 'verified') {
                    $stmt = $db->prepare("SELECT booking_id FROM payments WHERE id = ?");
                    $stmt->execute([$payment_id]);
                    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($payment) {
                        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
                        $stmt->execute([$payment['booking_id']]);
                        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;

                        $stmt = $db->prepare("SELECT total_amount, down_payment, status FROM bookings WHERE id = ?");
                        $stmt->execute([$payment['booking_id']]);
                        $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($booking_data && $total_paid >= $booking_data['down_payment'] && $booking_data['status'] === 'confirmed') {
                            $stmt = $db->prepare("UPDATE bookings SET status = 'paid' WHERE id = ?");
                            $stmt->execute([$payment['booking_id']]);
                        }
                    }
                }
            } else {
                $error = "Gagal mengupdate status pembayaran!";
            }
            break;
    }
}

// Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$payment_status = isset($_GET['payment_status']) ? $_GET['payment_status'] : '';

// Enhanced query untuk overview dengan informasi pembayaran lebih detail
$sql = "SELECT b.*, 
        u.full_name as customer_name, u.phone,
        p.name as package_name, p.price as package_price,
        p.service_type as service_name,
        COALESCE(SUM(CASE WHEN pay.status = 'verified' THEN pay.amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN pay.status = 'pending' THEN pay.amount ELSE 0 END), 0) as pending_amount,
        COUNT(pay.id) as payment_count,
        COUNT(CASE WHEN pay.status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN pay.status = 'verified' THEN 1 END) as verified_payments,
        COUNT(CASE WHEN pay.status = 'rejected' THEN 1 END) as rejected_payments
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN packages p ON b.package_id = p.id 
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (b.booking_code LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($payment_status)) {
    switch ($payment_status) {
        case 'no_payment':
            $sql .= " AND (SELECT COUNT(*) FROM payments WHERE booking_id = b.id) = 0";
            break;
        case 'pending_payment':
            $sql .= " AND (SELECT COUNT(*) FROM payments WHERE booking_id = b.id AND status = 'pending') > 0";
            break;
        case 'fully_paid':
            $sql .= " AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND status = 'verified') >= b.total_amount";
            break;
        case 'partial_paid':
            $sql .= " AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND status = 'verified') > 0 
                     AND (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = b.id AND status = 'verified') < b.total_amount";
            break;
    }
}

$sql .= " GROUP BY b.id ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced status function dengan lebih banyak detail
function getBookingStatus($booking)
{
    $total_paid = $booking['total_paid'] ?? 0;
    $pending_amount = $booking['pending_amount'] ?? 0;

    if ($total_paid >= $booking['total_amount']) {
        return ['text' => 'Lunas', 'class' => 'fully-paid', 'color' => '#28a745'];
    }

    if ($total_paid >= $booking['down_payment'] && $total_paid > 0) {
        return ['text' => 'DP Dibayar', 'class' => 'dp-paid', 'color' => '#17a2b8'];
    }

    if ($total_paid > 0 && $total_paid < $booking['down_payment']) {
        return ['text' => 'Sebagian', 'class' => 'partial-paid', 'color' => '#ffc107'];
    }

    $status_map = [
        'pending' => ['text' => 'Pending', 'class' => 'pending', 'color' => '#ffc107'],
        'confirmed' => ['text' => 'Dikonfirmasi', 'class' => 'confirmed', 'color' => '#28a745'],
        'paid' => ['text' => 'Dibayar', 'class' => 'paid', 'color' => '#17a2b8'],
        'in_progress' => ['text' => 'Berlangsung', 'class' => 'in_progress', 'color' => '#6f42c1'],
        'completed' => ['text' => 'Selesai', 'class' => 'completed', 'color' => '#28a745'],
        'cancelled' => ['text' => 'Dibatalkan', 'class' => 'cancelled', 'color' => '#dc3545']
    ];

    return $status_map[$booking['status']] ?? ['text' => ucfirst($booking['status']), 'class' => $booking['status'], 'color' => '#6c757d'];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking & Pembayaran - Dandy Gallery Admin</title>
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
            overflow-y: auto;
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
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 0.9rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
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
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-success {
            background: #28a745;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .bookings-table {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .table-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            position: sticky;
            top: 0;
        }

        .table tbody tr:hover {
            background: #f8f9fa;
        }

        .booking-code {
            font-weight: bold;
            color: #333;
        }

        .customer-info {
            line-height: 1.4;
        }

        .customer-name {
            font-weight: 600;
            color: #333;
        }

        .customer-phone {
            color: #666;
            font-size: 0.9rem;
        }

        .package-info {
            line-height: 1.4;
        }

        .package-name {
            font-weight: 500;
            color: #333;
        }

        .service-badge {
            display: inline-block;
            background: #f8f9fa;
            color: #666;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.7rem;
            margin-top: 0.25rem;
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
            display: inline-block;
        }

        /* Enhanced payment summary styles */
        .payment-summary {
            text-align: center;
            line-height: 1.3;
        }

        .payment-total {
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .payment-breakdown {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.85rem;
        }

        .payment-paid {
            color: #28a745;
            font-weight: 600;
        }

        .payment-pending {
            color: #ffc107;
            font-weight: 600;
        }

        .payment-remaining {
            color: #dc3545;
            font-weight: 600;
        }

        .payment-indicators {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .payment-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
        }

        .payment-indicator.pending {
            background: #fff3cd;
            color: #856404;
        }

        .payment-indicator.verified {
            background: #d4edda;
            color: #155724;
        }

        .payment-indicator.rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-body {
            padding: 2rem;
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            opacity: 0.8;
        }

        .close:hover {
            opacity: 1;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            resize: vertical;
            min-height: 80px;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 1rem;
            border-radius: 8px;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .quick-stat {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .quick-stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b6b;
        }

        .quick-stat-label {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
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

            .table-container {
                overflow-x: auto;
            }

            .table {
                min-width: 800px;
            }

            .quick-stats {
                grid-template-columns: repeat(2, 1fr);
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
            <li><a href="bookings.php" class="active"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
            <li><a href="packages.php"><i class="fas fa-box"></i> Kelola Paket</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Kelola Booking & Pembayaran</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>


        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Cari Booking</label>
                    <input type="text" id="search" name="search" placeholder="Kode booking atau nama customer..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status Booking</label>
                    <select id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending
                        </option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>
                            Dikonfirmasi</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Dibayar</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>
                            Berlangsung</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>
                            Selesai</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                            Dibatalkan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="payment_status">Status Pembayaran</label>
                    <select id="payment_status" name="payment_status">
                        <option value="">Semua</option>
                        <option value="no_payment" <?php echo $payment_status === 'no_payment' ? 'selected' : ''; ?>>
                            Belum Bayar</option>
                        <option value="pending_payment"
                            <?php echo $payment_status === 'pending_payment' ? 'selected' : ''; ?>>Ada Pending</option>
                        <option value="partial_paid"
                            <?php echo $payment_status === 'partial_paid' ? 'selected' : ''; ?>>Sebagian</option>
                        <option value="fully_paid" <?php echo $payment_status === 'fully_paid' ? 'selected' : ''; ?>>
                            Lunas</option>
                    </select>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Filter
                </button>

                <a href="bookings.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="bookings-table">
            <div class="table-header">
                <h3><i class="fas fa-calendar-check"></i> Daftar Booking & Pembayaran</h3>
                <span><?php echo count($bookings); ?> booking ditemukan</span>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Tidak Ada Booking</h3>
                    <p>Belum ada booking yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Customer</th>
                                <th>Paket & Layanan</th>
                                <th>Tanggal Event</th>
                                <th>Status</th>
                                <th>Pembayaran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <?php
                                $status_info = getBookingStatus($booking);
                                $remaining_payment = $booking['total_amount'] - $booking['total_paid'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="booking-code"><?php echo htmlspecialchars($booking['booking_code']); ?>
                                        </div>
                                        <small
                                            style="color: #666;"><?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="customer-info">
                                            <div class="customer-name">
                                                <?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                            <div class="customer-phone">
                                                <?php echo htmlspecialchars($booking['phone'] ?: '-'); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="package-info">
                                            <div class="package-name"><?php echo htmlspecialchars($booking['package_name']); ?>
                                            </div>
                                            <div class="service-badge">
                                                <?php $service_icon = getServiceIcon($booking['service_name']); ?>
                                                <i class="<?php echo $service_icon; ?>"></i>
                                                <?php echo htmlspecialchars($booking['service_name']); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?></div>
                                    </td>
                                    <td>
                                        <span class="status-badge"
                                            style="background-color: <?php echo $status_info['color']; ?>; color: white;">
                                            <?php echo $status_info['text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="payment-summary">
                                            <div class="payment-total"><?php echo formatRupiah($booking['total_amount']); ?>
                                            </div>
                                            <div class="payment-breakdown">
                                                <?php if ($booking['total_paid'] > 0): ?>
                                                    <div class="payment-paid">✓ Dibayar:
                                                        <?php echo formatRupiah($booking['total_paid']); ?></div>
                                                <?php endif; ?>

                                                <?php if ($booking['pending_amount'] > 0): ?>
                                                    <div class="payment-pending">⏳ Pending:
                                                        <?php echo formatRupiah($booking['pending_amount']); ?></div>
                                                <?php endif; ?>

                                                <?php if ($remaining_payment > 0): ?>
                                                    <div class="payment-remaining">⚠ Sisa:
                                                        <?php echo formatRupiah($remaining_payment); ?></div>
                                                <?php endif; ?>
                                            </div>

                                            <?php if ($booking['payment_count'] > 0): ?>
                                                <div class="payment-indicators">
                                                    <?php if ($booking['verified_payments'] > 0): ?>
                                                        <span class="payment-indicator verified">
                                                            <i class="fas fa-check"></i> <?php echo $booking['verified_payments']; ?>
                                                            verified
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($booking['pending_payments'] > 0): ?>
                                                        <span class="payment-indicator pending">
                                                            <i class="fas fa-clock"></i> <?php echo $booking['pending_payments']; ?>
                                                            pending
                                                        </span>
                                                    <?php endif; ?>

                                                    <?php if ($booking['rejected_payments'] > 0): ?>
                                                        <span class="payment-indicator rejected">
                                                            <i class="fas fa-times"></i> <?php echo $booking['rejected_payments']; ?>
                                                            ditolak
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm">
                                                <i class="fas fa-eye"></i> Detail
                                            </a>

                                            <button type="button" class="btn btn-sm btn-warning"
                                                onclick="updateBookingStatus(<?php echo $booking['id']; ?>, '<?php echo $booking['status']; ?>')">
                                                <i class="fas fa-edit"></i>
                                            </button>

                                            <?php if ($booking['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-success"
                                                    onclick="quickConfirm(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_code']); ?>')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($booking['pending_payments'] > 0): ?>
                                                <button type="button" class="btn btn-sm" style="background: #17a2b8;"
                                                    onclick="window.location.href='booking_detail.php?id=<?php echo $booking['id']; ?>#payments'">
                                                    <i class="fas fa-money-bill"></i> Verifikasi
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Update Booking Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Booking</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="action" value="update_booking_status">
                    <input type="hidden" name="booking_id" id="bookingId">

                    <div class="form-group">
                        <label for="bookingStatus">Status Baru</label>
                        <select id="bookingStatus" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Dikonfirmasi</option>
                            <option value="paid">Dibayar</option>
                            <option value="in_progress">Sedang Berlangsung</option>
                            <option value="completed">Selesai</option>
                            <option value="cancelled">Dibatalkan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="bookingNotes">Catatan (opsional)</label>
                        <textarea id="bookingNotes" name="notes"
                            placeholder="Berikan catatan untuk customer..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateBookingStatus(bookingId, currentStatus) {
            document.getElementById('bookingId').value = bookingId;
            document.getElementById('bookingStatus').value = currentStatus;
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
            document.getElementById('bookingNotes').value = '';
        }

        function quickConfirm(bookingId, bookingCode) {
            if (confirm(`Konfirmasi booking ${bookingCode}?`)) {
                const form = document.createElement('form');
                form.method = 'POST';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'update_booking_status';

                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'booking_id';
                idInput.value = bookingId;

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = 'confirmed';

                const notesInput = document.createElement('input');
                notesInput.type = 'hidden';
                notesInput.name = 'notes';
                notesInput.value = 'Booking dikonfirmasi oleh admin. Silakan lakukan pembayaran.';

                form.appendChild(actionInput);
                form.appendChild(idInput);
                form.appendChild(statusInput);
                form.appendChild(notesInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target === modal) {
                closeStatusModal();
            }
        }

        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);

        // Auto-refresh if there are pending payments
        setInterval(function() {
            if (document.querySelectorAll('.payment-indicator.pending').length > 0) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Highlight rows with pending payments
        document.addEventListener('DOMContentLoaded', function() {
            const pendingRows = document.querySelectorAll('.payment-indicator.pending');
            pendingRows.forEach(function(indicator) {
                const row = indicator.closest('tr');
                if (row) {
                    row.style.borderLeft = '4px solid #ffc107';
                    row.style.backgroundColor = '#fffbf0';
                }
            });
        });
    </script>
</body>

</html>