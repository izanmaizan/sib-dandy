<!-- customer/bookings.php -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Enhanced query dengan informasi pembayaran
$sql = "SELECT b.*, p.name as package_name, p.price as package_price,
        p.service_type as service_name,
        COALESCE(SUM(CASE WHEN pay.status = 'verified' THEN pay.amount ELSE 0 END), 0) as total_paid,
        COALESCE(SUM(CASE WHEN pay.status = 'pending' THEN pay.amount ELSE 0 END), 0) as pending_amount,
        COUNT(CASE WHEN pay.status = 'pending' THEN 1 END) as pending_payments,
        COUNT(CASE WHEN pay.status = 'verified' THEN 1 END) as verified_payments
        FROM bookings b 
        JOIN packages p ON b.package_id = p.id 
        LEFT JOIN payments pay ON b.id = pay.booking_id
        WHERE b.user_id = ?";

$params = [$_SESSION['user_id']];

if (!empty($status_filter)) {
    $sql .= " AND b.status = ?";
    $params[] = $status_filter;
}

$sql .= " GROUP BY b.id ORDER BY b.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Enhanced statistik dengan informasi pembayaran
$stats = [];
$stats['total'] = count($bookings);
$stats['pending'] = count(array_filter($bookings, function($b) { return $b['status'] === 'pending'; }));
$stats['confirmed'] = count(array_filter($bookings, function($b) { return $b['status'] === 'confirmed'; }));
$stats['completed'] = count(array_filter($bookings, function($b) { return $b['status'] === 'completed'; }));
$stats['pending_payments'] = array_sum(array_column($bookings, 'pending_payments'));
$stats['total_spent'] = array_sum(array_column($bookings, 'total_paid'));

// Enhanced function untuk status Indonesia dengan logika pembayaran
function getBookingStatusIndonesia($booking) {
    $status = $booking['status'];
    $total_paid = $booking['total_paid'] ?? 0;
    $down_payment = $booking['down_payment'];
    $total_amount = $booking['total_amount'];
    
    // Jika sudah lunas
    if ($total_paid >= $total_amount) {
        return [
            'status' => 'lunas', 
            'text' => 'Lunas', 
            'class' => 'completed',
            'color' => '#28a745',
            'description' => 'Pembayaran lunas, siap untuk event'
        ];
    }
    
    // Jika sudah bayar DP tapi belum lunas
    if ($total_paid >= $down_payment && $total_paid < $total_amount) {
        return [
            'status' => 'dp_paid', 
            'text' => 'DP Dibayar', 
            'class' => 'paid',
            'color' => '#17a2b8',
            'description' => 'DP dibayar, dapat melunasi kapan saja'
        ];
    }
    
    // Jika ada pembayaran parsial (kurang dari DP)
    if ($total_paid > 0 && $total_paid < $down_payment) {
        return [
            'status' => 'partial_paid', 
            'text' => 'Dibayar Sebagian', 
            'class' => 'partial',
            'color' => '#ffc107',
            'description' => 'Pembayaran parsial, dapat melanjutkan'
        ];
    }
    
    $status_map = [
        'pending' => [
            'status' => 'pending', 
            'text' => 'Menunggu Konfirmasi', 
            'class' => 'pending',
            'color' => '#ffc107',
            'description' => 'Menunggu konfirmasi dari admin'
        ],
        'confirmed' => [
            'status' => 'confirmed', 
            'text' => 'Dikonfirmasi', 
            'class' => 'confirmed',
            'color' => '#28a745',
            'description' => 'Dikonfirmasi, silakan lakukan pembayaran'
        ],
        'paid' => [
            'status' => 'paid', 
            'text' => 'Dibayar', 
            'class' => 'paid',
            'color' => '#17a2b8',
            'description' => 'Pembayaran diterima'
        ],
        'in_progress' => [
            'status' => 'in_progress', 
            'text' => 'Sedang Berlangsung', 
            'class' => 'in_progress',
            'color' => '#6f42c1',
            'description' => 'Event sedang berlangsung'
        ],
        'completed' => [
            'status' => 'completed', 
            'text' => 'Selesai', 
            'class' => 'completed',
            'color' => '#28a745',
            'description' => 'Event selesai, terima kasih!'
        ],
        'cancelled' => [
            'status' => 'cancelled', 
            'text' => 'Dibatalkan', 
            'class' => 'cancelled',
            'color' => '#dc3545',
            'description' => 'Booking dibatalkan'
        ]
    ];
    
    return $status_map[$status] ?? [
        'status' => $status, 
        'text' => ucfirst($status), 
        'class' => $status,
        'color' => '#6c757d',
        'description' => ucfirst($status)
    ];
}

// Function untuk menentukan aksi pembayaran yang tersedia
function getAvailablePaymentActions($booking) {
    $total_paid = $booking['total_paid'] ?? 0;
    $remaining = $booking['total_amount'] - $total_paid;
    $actions = [];
    
    if ($booking['status'] === 'confirmed' && $remaining > 0) {
        if ($total_paid == 0) {
            // Belum ada pembayaran
            $actions[] = [
                'type' => 'dp',
                'text' => 'Bayar DP',
                'amount' => $booking['down_payment'],
                'class' => 'btn-success',
                'icon' => 'credit-card'
            ];
            $actions[] = [
                'type' => 'full',
                'text' => 'Bayar Lunas',
                'amount' => $booking['total_amount'],
                'class' => 'btn-warning',
                'icon' => 'star'
            ];
        } elseif ($total_paid >= $booking['down_payment']) {
            // DP sudah dibayar
            $actions[] = [
                'type' => 'remaining',
                'text' => 'Lunasi',
                'amount' => $remaining,
                'class' => 'btn-warning',
                'icon' => 'check-circle'
            ];
        } else {
            // Pembayaran parsial
            $actions[] = [
                'type' => 'dp',
                'text' => 'Lanjut DP',
                'amount' => $booking['down_payment'] - $total_paid,
                'class' => 'btn-success',
                'icon' => 'credit-card'
            ];
            $actions[] = [
                'type' => 'full',
                'text' => 'Bayar Lunas',
                'amount' => $remaining,
                'class' => 'btn-warning',
                'icon' => 'star'
            ];
        }
    }
    
    return $actions;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Saya - Dandy Gallery</title>
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

    .btn {
        padding: 10px 20px;
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

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-3px);
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

    .stat-icon.total {
        background: linear-gradient(45deg, #3498db, #2980b9);
    }

    .stat-icon.pending {
        background: linear-gradient(45deg, #f39c12, #e67e22);
    }

    .stat-icon.confirmed {
        background: linear-gradient(45deg, #2ecc71, #27ae60);
    }

    .stat-icon.completed {
        background: linear-gradient(45deg, #9b59b6, #8e44ad);
    }

    .stat-icon.payments {
        background: linear-gradient(45deg, #e74c3c, #c0392b);
    }

    .stat-icon.revenue {
        background: linear-gradient(45deg, #34495e, #2c3e50);
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #666;
        font-size: 0.85rem;
    }

    .filter-section {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: end;
    }

    .form-group {
        flex: 1;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 600;
        color: #333;
    }

    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .form-group select:focus {
        outline: none;
        border-color: #ff6b6b;
    }

    .bookings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 2rem;
    }

    .booking-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .booking-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
    }

    .booking-header {
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        padding: 1.5rem;
        position: relative;
    }

    .booking-code {
        font-size: 1.1rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .booking-package {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .booking-status {
        position: absolute;
        top: 1rem;
        right: 1rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
        background: rgba(255, 255, 255, 0.2);
    }

    .booking-body {
        padding: 1.5rem;
    }

    .booking-price {
        font-size: 1.3rem;
        font-weight: bold;
        color: #ff6b6b;
        margin-bottom: 1rem;
    }

    .payment-summary {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .payment-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
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

    .booking-details {
        margin-bottom: 1.5rem;
    }

    .detail-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 0.5rem;
        color: #666;
        font-size: 0.9rem;
    }

    .detail-item i {
        width: 16px;
        color: #ff6b6b;
    }

    .status-item {
        background: #f8f9fa;
        padding: 0.75rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 4px solid;
    }

    .status-item.pending {
        border-left-color: #ffc107;
    }

    .status-item.confirmed {
        border-left-color: #28a745;
    }

    .status-item.paid {
        border-left-color: #17a2b8;
    }

    .status-item.completed {
        border-left-color: #28a745;
    }

    .status-item.cancelled {
        border-left-color: #dc3545;
    }

    .status-item.partial {
        border-left-color: #ffc107;
    }

    .booking-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .payment-indicators {
        display: flex;
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

    .status.partial {
        background: #fff3cd;
        color: #856404;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .empty-state i {
        font-size: 4rem;
        color: #ccc;
        margin-bottom: 1rem;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .bookings-grid {
            grid-template-columns: 1fr;
        }

        .filter-form {
            flex-direction: column;
        }

        .stats-grid {
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
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bookings.php" class="active"><i class="fas fa-calendar-check"></i> Booking Saya</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
            <li><a href="../packages.php"><i class="fas fa-box"></i> Lihat Paket</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Booking Saya</h1>
            <a href="booking_new.php" class="btn">
                <i class="fas fa-plus"></i> Booking Baru
            </a>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Filter Status</label>
                    <select id="status" name="status" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Menunggu
                            Konfirmasi</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>
                            Dikonfirmasi</option>
                        <option value="paid" <?php echo $status_filter === 'paid' ? 'selected' : ''; ?>>Sudah Dibayar
                        </option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>
                            Sedang Berlangsung</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>
                            Selesai</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>
                            Dibatalkan</option>
                    </select>
                </div>

                <a href="bookings.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Bookings Grid -->
        <?php if (empty($bookings)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>Belum Ada Booking</h3>
            <p style="color: #666; margin-bottom: 2rem;">
                <?php if (!empty($status_filter)): ?>
                Tidak ada booking dengan status "<?php echo ucfirst($status_filter); ?>".
                <?php else: ?>
                Anda belum memiliki booking apapun. Mulai booking paket impian Anda sekarang!
                <?php endif; ?>
            </p>
            <a href="booking_new.php" class="btn">
                <i class="fas fa-plus"></i> Buat Booking Baru
            </a>
        </div>
        <?php else: ?>
        <div class="bookings-grid">
            <?php foreach ($bookings as $booking): ?>
            <?php 
    $service_icon = getServiceIcon($booking['service_name']); 
    $status_info = getBookingStatusIndonesia($booking);
    $remaining_payment = $booking['total_amount'] - $booking['total_paid'];
    $payment_actions = getAvailablePaymentActions($booking);
    ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-code">
                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($booking['booking_code']); ?>
                    </div>
                    <div class="booking-package"><?php echo htmlspecialchars($booking['package_name']); ?></div>
                    <div class="booking-status">
                        <i class="<?php echo $service_icon; ?>"></i>
                        <?php echo $status_info['text']; ?>
                    </div>
                </div>

                <div class="booking-body">
                    <div class="booking-price">
                        <?php echo formatRupiah($booking['total_amount']); ?>
                    </div>

                    <!-- Enhanced Payment Summary -->
                    <div class="payment-summary">
                        <div class="payment-item">
                            <span>Sudah Dibayar:</span>
                            <span class="payment-paid"><?php echo formatRupiah($booking['total_paid']); ?></span>
                        </div>
                        <?php if ($booking['pending_amount'] > 0): ?>
                        <div class="payment-item">
                            <span>Pending Verifikasi:</span>
                            <span class="payment-pending"><?php echo formatRupiah($booking['pending_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($remaining_payment > 0): ?>
                        <div class="payment-item">
                            <span>Sisa Pembayaran:</span>
                            <span class="payment-remaining"><?php echo formatRupiah($remaining_payment); ?></span>
                        </div>
                        <?php endif; ?>

                        <!-- Payment Indicators -->
                        <?php if ($booking['verified_payments'] > 0 || $booking['pending_payments'] > 0): ?>
                        <div class="payment-indicators">
                            <?php if ($booking['verified_payments'] > 0): ?>
                            <span class="payment-indicator verified">
                                <i class="fas fa-check"></i> <?php echo $booking['verified_payments']; ?> verified
                            </span>
                            <?php endif; ?>
                            <?php if ($booking['pending_payments'] > 0): ?>
                            <span class="payment-indicator pending">
                                <i class="fas fa-clock"></i> <?php echo $booking['pending_payments']; ?> pending
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="booking-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span>Event: <?php echo date('d F Y', strtotime($booking['usage_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="<?php echo $service_icon; ?>"></i>
                            <span>Layanan: <?php echo htmlspecialchars($booking['service_name']); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Dibuat: <?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></span>
                        </div>
                        <?php if ($booking['venue_address']): ?>
                        <div class="detail-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars(substr($booking['venue_address'], 0, 50)) . (strlen($booking['venue_address']) > 50 ? '...' : ''); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Enhanced Status Display -->
                    <div class="status-item <?php echo $status_info['class']; ?>">
                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <i class="fas fa-info-circle"></i>
                            <strong><?php echo $status_info['text']; ?></strong>
                        </div>
                        <div style="font-size: 0.9rem; color: #666;">
                            <?php echo $status_info['description']; ?>
                        </div>
                    </div>

                    <div class="booking-actions">
                        <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-sm">
                            <i class="fas fa-eye"></i> Detail
                        </a>

                        <!-- Dynamic Payment Actions -->
                        <?php foreach ($payment_actions as $action): ?>
                        <a href="payment.php?booking=<?php echo $booking['id']; ?>&type=<?php echo $action['type']; ?>"
                            class="btn btn-sm <?php echo $action['class']; ?>">
                            <i class="fas fa-<?php echo $action['icon']; ?>"></i>
                            <?php echo $action['text']; ?>
                            <small>(<?php echo formatRupiah($action['amount']); ?>)</small>
                        </a>
                        <?php endforeach; ?>

                        <?php if ($booking['status'] === 'pending'): ?>
                        <button type="button" class="btn btn-sm btn-danger"
                            onclick="cancelBooking(<?php echo $booking['id']; ?>)">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>

    <script>
    function cancelBooking(bookingId) {
        if (confirm('Apakah Anda yakin ingin membatalkan booking ini?')) {
            // Create form to submit cancellation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'cancel_booking.php';

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'booking_id';
            input.value = bookingId;

            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // Auto refresh status dengan interval yang lebih bijak
    setInterval(function() {
        // Hanya refresh jika ada pembayaran pending atau status yang bisa berubah
        if (document.querySelectorAll('.payment-indicator.pending').length > 0) {
            location.reload();
        }
    }, 120000); // 2 minutes untuk pembayaran pending

    // Highlight cards dengan pending payments
    document.addEventListener('DOMContentLoaded', function() {
        const pendingCards = document.querySelectorAll('.payment-indicator.pending');
        pendingCards.forEach(function(indicator) {
            const card = indicator.closest('.booking-card');
            if (card) {
                card.style.borderLeft = '4px solid #ffc107';
                card.style.background = 'linear-gradient(to right, #fffbf0, white)';
            }
        });

        // Animate statistics cards
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(function(card, index) {
            card.style.animationDelay = (index * 0.1) + 's';
            card.style.animation = 'fadeInUp 0.6s ease forwards';
        });
    });

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
    document.head.appendChild(style);
    </script>
</body>

</html>