<!-- customer/booking_detail.php -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = isset($_GET['success']) ? true : false;
$return_success = isset($_GET['return_success']) ? true : false;

if (!$booking_id) {
    header('Location: bookings.php');
    exit();
}

// Ambil detail booking dengan join yang sesuai struktur database
$stmt = $db->prepare("SELECT b.*, p.name as package_name, p.description as package_description, 
                     p.includes as package_includes, p.price as package_price,
                     p.service_type as service_name,
                     u.full_name as customer_name, u.email, u.phone
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id 
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);
// TAMBAHKAN setelah query $booking:
$service_icon = getServiceIcon($booking['service_name']);

if (!$booking) {
    header('Location: bookings.php');
    exit();
}

// Ambil history pembayaran untuk booking ini dengan detail lengkap
$stmt = $db->prepare("SELECT p.*, 
                     DATE(p.created_at) as submission_date,
                     TIME(p.created_at) as submission_time,
                     DATEDIFF(CURDATE(), p.payment_date) as days_since_payment
                     FROM payments p 
                     WHERE p.booking_id = ? 
                     ORDER BY p.created_at DESC");
$stmt->execute([$booking_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik pembayaran seperti di admin
$payment_stats = [
    'total_transactions' => count($payments),
    'verified_amount' => 0,
    'pending_amount' => 0,
    'rejected_amount' => 0,
    'verified_count' => 0,
    'pending_count' => 0,
    'rejected_count' => 0
];

if (!empty($payments)) {
    foreach ($payments as $payment) {
        switch ($payment['status']) {
            case 'verified':
                $payment_stats['verified_amount'] += $payment['amount'];
                $payment_stats['verified_count']++;
                break;
            case 'pending':
                $payment_stats['pending_amount'] += $payment['amount'];
                $payment_stats['pending_count']++;
                break;
            case 'rejected':
                $payment_stats['rejected_amount'] += $payment['amount'];
                $payment_stats['rejected_count']++;
                break;
        }
    }
}

// Hitung dengan verified amount saja untuk akurasi
$total_paid = $payment_stats['verified_amount'];
$remaining_payment = $booking['total_amount'] - $total_paid;

// Tentukan jenis pembayaran yang bisa dilakukan
$can_pay_dp = false;
$can_pay_remaining = false;
$can_pay_full = false;

// Logika pembayaran yang disederhanakan dan fleksibel
if ($booking['status'] === 'confirmed') {
    if ($total_paid == 0) {
        // Belum ada pembayaran - bisa bayar DP atau langsung lunas
        $can_pay_dp = true;
        $can_pay_full = true;
    } elseif ($total_paid >= $booking['down_payment'] && $remaining_payment > 0) {
        // DP sudah dibayar, bisa melunasi
        $can_pay_remaining = true;
    } elseif ($total_paid > 0 && $total_paid < $booking['down_payment']) {
        // Ada pembayaran parsial, bisa melanjutkan DP atau langsung lunas
        $can_pay_dp = true;
        $can_pay_full = true;
    }
}

// Function untuk menentukan jenis pembayaran
function getPaymentType($amount, $booking, $previous_total = 0)
{
    $total_after = $previous_total + $amount;

    if ($total_after >= $booking['total_amount']) {
        if ($previous_total == 0) {
            return 'Lunas Langsung';
        } else {
            return 'Pelunasan';
        }
    } elseif ($previous_total < $booking['down_payment'] && $total_after >= $booking['down_payment']) {
        return 'DP (Down Payment)';
    } else {
        return 'Cicilan';
    }
}

// Function untuk status dalam bahasa Indonesia dengan logika pembayaran baru
function formatStatusIndonesia($status, $total_paid, $down_payment, $total_amount)
{
    // Jika sudah lunas
    if ($total_paid >= $total_amount) {
        return 'Lunas';
    }

    // Jika sudah bayar DP tapi belum lunas
    if ($total_paid >= $down_payment && $total_paid < $total_amount && $status === 'paid') {
        return 'Dibayar (DP)';
    }

    $status_indonesia = [
        'pending' => 'Menunggu Konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'paid' => 'Dibayar',
        'in_progress' => 'Sedang Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];

    return $status_indonesia[$status] ?? ucfirst($status);
}

// Determine payment status untuk UI
function getPaymentStatus($booking, $total_paid)
{
    if ($total_paid >= $booking['total_amount']) {
        return [
            'status' => 'fully_paid',
            'text' => 'Lunas',
            'color' => '#28a745',
            'icon' => 'fas fa-check-circle'
        ];
    } elseif ($total_paid >= $booking['down_payment'] && $booking['down_payment'] > 0) {
        return [
            'status' => 'dp_paid',
            'text' => 'DP Dibayar',
            'color' => '#17a2b8',
            'icon' => 'fas fa-clock'
        ];
    } elseif ($total_paid > 0) {
        return [
            'status' => 'partial_paid',
            'text' => 'Dibayar Sebagian',
            'color' => '#ffc107',
            'icon' => 'fas fa-exclamation-triangle'
        ];
    } else {
        return [
            'status' => 'not_paid',
            'text' => 'Belum Dibayar',
            'color' => '#dc3545',
            'icon' => 'fas fa-times-circle'
        ];
    }
}

$payment_status_info = getPaymentStatus($booking, $total_paid);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking - Dandy Gallery</title>
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

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.8rem;
    }

    .booking-header {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
    }

    .booking-code {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .booking-status {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 25px;
        font-size: 1rem;
        margin-top: 1rem;
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .card-header {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        padding: 1.5rem 2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .card-body {
        padding: 2rem;
    }

    .info-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .info-section h4 {
        color: #333;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #ff6b6b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 500;
        color: #666;
    }

    .info-value {
        color: #333;
        font-weight: 500;
    }

    .package-includes {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
    }

    .package-includes h4 {
        color: #333;
        margin-bottom: 1rem;
    }

    .package-includes ul {
        list-style: none;
        color: #666;
    }

    .package-includes li {
        padding: 0.25rem 0;
        position: relative;
        padding-left: 1.5rem;
    }

    .package-includes li:before {
        content: "✓";
        position: absolute;
        left: 0;
        color: #28a745;
        font-weight: bold;
    }

    .payment-summary {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
    }

    .payment-status-badge {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-size: 0.9rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }

    .payment-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #dee2e6;
    }

    .payment-item:last-child {
        border-bottom: none;
        font-weight: bold;
        font-size: 1.1rem;
    }

    .payment-label {
        color: #666;
    }

    .payment-value {
        color: #333;
        font-weight: 500;
    }

    .payment-actions {
        display: flex;
        flex-direction: column;
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .payment-option {
        background: white;
        border: 2px solid #e1e5e9;
        border-radius: 10px;
        padding: 1rem;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s;
    }

    .payment-option:hover {
        border-color: #ff6b6b;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .payment-option-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .payment-option-title {
        font-weight: 600;
        color: #333;
    }

    .payment-option-amount {
        font-weight: bold;
        color: #ff6b6b;
    }

    .payment-option-desc {
        color: #666;
        font-size: 0.9rem;
    }

    .payment-history {
        margin-top: 2rem;
    }

    .payment-card {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        margin-bottom: 1rem;
        border-left: 4px solid;
    }

    .payment-card.verified {
        border-left-color: #28a745;
    }

    .payment-card.pending {
        border-left-color: #ffc107;
    }

    .payment-card.rejected {
        border-left-color: #dc3545;
    }

    .payment-card h5 {
        color: #333;
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .payment-details {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        font-size: 0.9rem;
        color: #666;
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

    .status.verified {
        background: #d4edda;
        color: #155724;
    }

    .status.rejected {
        background: #f8d7da;
        color: #721c24;
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

    .alert.info {
        background: #d1ecf1;
        color: #0c5460;
        border: 1px solid #bee5eb;
    }

    .alert.warning {
        background: #fff3cd;
        color: #856404;
        border: 1px solid #ffeaa7;
    }

    .timeline {
        position: relative;
        padding-left: 2rem;
    }

    .timeline-item {
        position: relative;
        padding-bottom: 2rem;
    }

    .timeline-item:before {
        content: '';
        position: absolute;
        left: -2rem;
        top: 0.5rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #dee2e6;
    }

    .timeline-item:after {
        content: '';
        position: absolute;
        left: -1.75rem;
        top: 1.5rem;
        width: 2px;
        height: calc(100% - 1rem);
        background: #dee2e6;
    }

    .timeline-item:last-child:after {
        display: none;
    }

    .timeline-item.completed:before {
        background: #28a745;
    }

    .timeline-item.current:before {
        background: #ff6b6b;
    }

    .timeline-content h5 {
        color: #333;
        margin-bottom: 0.25rem;
    }

    .timeline-content p {
        color: #666;
        font-size: 0.9rem;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .detail-grid {
            grid-template-columns: 1fr;
        }

        .info-grid {
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
            <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="bookings.php" class="active"><i class="fas fa-calendar-check"></i> Booking Saya</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profil Saya</a></li>
            <li><a href="../packages.php"><i class="fas fa-box"></i> Lihat Paket</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Detail Booking</h1>
            <a href="bookings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali
            </a>
        </div>

        <?php if ($success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i> Booking berhasil dibuat! Tim kami akan segera menghubungi Anda untuk
            konfirmasi.
        </div>
        <?php endif; ?>

        <!-- TAMBAHAN BARU: Alert Return Success -->
        <?php if ($return_success): ?>
        <div class="alert success">
            <i class="fas fa-check-circle"></i>
            <strong>Pengembalian Baju Berhasil Dicatat!</strong><br>
            Terima kasih telah mengembalikan baju pengantin tepat waktu.
            <?php if ($remaining_payment <= 0): ?>
            Semua proses booking telah selesai.
            <?php else: ?>
            Anda masih bisa melunasi sisa pembayaran jika diperlukan.
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Booking Header -->
        <div class="booking-header">
            <div class="booking-code">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($booking['booking_code']); ?>
            </div>
            <div>Booking untuk <?php echo htmlspecialchars($booking['package_name']); ?></div>
            <div class="booking-status">
                <i class="<?php echo $service_icon; ?>"></i>
                Status:
                <?php echo formatStatusIndonesia($booking['status'], $total_paid, $booking['down_payment'], $booking['total_amount']); ?>
            </div>
        </div>

        <!-- Detail Grid -->
        <div class="detail-grid">
            <!-- Detail Booking -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Detail Booking</h3>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-section">
                            <h4><i class="fas fa-calendar"></i> Informasi Event</h4>
                            <div class="info-item">
                                <span class="info-label">Tanggal Event:</span>
                                <span
                                    class="info-value"><?php echo date('d F Y', strtotime($booking['usage_date'])); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jenis Layanan:</span>
                                <span class="info-value">
                                    <i class="<?php echo $service_icon; ?>"></i>
                                    <?php echo htmlspecialchars($booking['service_name']); ?>
                                </span>
                            </div>
                            <?php if ($booking['venue_address']): ?>
                            <div class="info-item">
                                <span class="info-label">Alamat Acara:</span>
                                <span
                                    class="info-value"><?php echo htmlspecialchars($booking['venue_address']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="info-section">
                            <h4><i class="fas fa-user"></i> Informasi Customer</h4>
                            <div class="info-item">
                                <span class="info-label">Nama Lengkap:</span>
                                <span
                                    class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telepon:</span>
                                <span
                                    class="info-value"><?php echo htmlspecialchars($booking['phone'] ?: '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Dibuat:</span>
                                <span
                                    class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Special Request -->
                    <?php if ($booking['special_request']): ?>
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                        <h5><i class="fas fa-comment"></i> Permintaan Khusus</h5>
                        <p style="margin: 0.5rem 0 0 0; color: #856404;">
                            <?php echo nl2br(htmlspecialchars($booking['special_request'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Package Includes -->
                    <?php if ($booking['package_includes']): ?>
                    <div class="package-includes">
                        <h4><i class="fas fa-check-circle"></i> Yang Termasuk dalam Paket</h4>
                        <ul>
                            <?php
                                $includes = explode(',', $booking['package_includes']);
                                foreach ($includes as $include):
                                ?>
                            <li><?php echo trim(htmlspecialchars($include)); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Admin Notes -->
                    <?php if ($booking['notes']): ?>
                    <div style="background: #d1ecf1; padding: 1rem; border-radius: 8px;">
                        <h5><i class="fas fa-sticky-note"></i> Catatan dari Admin</h5>
                        <p style="margin: 0.5rem 0 0 0; color: #0c5460;">
                            <?php echo nl2br(htmlspecialchars($booking['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Summary & Actions -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-money-bill"></i>
                    <h3>Pembayaran</h3>
                </div>
                <div class="card-body">
                    <!-- Payment Status Badge -->
                    <div class="payment-status-badge"
                        style="background-color: <?php echo $payment_status_info['color']; ?>; color: white;">
                        <i class="<?php echo $payment_status_info['icon']; ?>"></i>
                        <span><?php echo $payment_status_info['text']; ?></span>
                    </div>

                    <div class="payment-summary">
                        <div class="payment-item">
                            <span class="payment-label">Total Biaya:</span>
                            <span class="payment-value"><?php echo formatRupiah($booking['total_amount']); ?></span>
                        </div>
                        <?php if ($booking['down_payment'] > 0): ?>
                        <div class="payment-item">
                            <span class="payment-label">DP (Minimum):</span>
                            <span class="payment-value"><?php echo formatRupiah($booking['down_payment']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="payment-item">
                            <span class="payment-label">Sudah Dibayar:</span>
                            <span class="payment-value"
                                style="color: #28a745;"><?php echo formatRupiah($total_paid); ?></span>
                        </div>
                        <?php if ($payment_stats['pending_amount'] > 0): ?>
                        <div class="payment-item">
                            <span class="payment-label">Menunggu Verifikasi:</span>
                            <span class="payment-value"
                                style="color: #ffc107;"><?php echo formatRupiah($payment_stats['pending_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="payment-item">
                            <span class="payment-label">Sisa Pembayaran:</span>
                        </div>
                    </div>

                    <!-- Payment Options -->
                    <?php if ($booking['status'] === 'confirmed' && $remaining_payment > 0): ?>
                    <div class="payment-actions">
                        <a href="payment.php?booking=<?php echo $booking['id']; ?>" class="payment-option">
                            <div class="payment-option-header">
                                <span class="payment-option-title">
                                    <i class="fas fa-credit-card"></i> Bayar Sekarang
                                </span>
                                <span
                                    class="payment-option-amount"><?php echo formatRupiah($remaining_payment); ?></span>
                            </div>
                            <div class="payment-option-desc">
                                <?php if ($total_paid >= $booking['down_payment']): ?>
                                Lunasi sisa pembayaran untuk menyelesaikan transaksi booking Anda.
                                <?php else: ?>
                                Pilih nominal pembayaran yang ingin Anda bayar (minimal DP
                                <?php echo formatRupiah($booking['down_payment']); ?> atau langsung lunas).
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endif; ?>

                    <!-- Return Dress Section - TAMBAHAN BARU -->
                    <?php
                    // Cek apakah ini booking baju pengantin dan sudah selesai event
                    $show_return_section = false;
                    $can_return_dress = false;
                    $return_status = '';

                    if ($booking['service_name'] === 'Baju Pengantin') {
                        $show_return_section = true;
                        $event_date = strtotime($booking['usage_date']);
                        $today = time();

                        // Cek status pengembalian dari notes
                        $dress_taken = strpos($booking['notes'], '[DRESS_TAKEN]') !== false;
                        $dress_returned = strpos($booking['notes'], '[DRESS_RETURNED]') !== false;

                        if ($dress_returned) {
                            $return_status = 'returned';
                        } elseif ($dress_taken) {
                            // Baju sudah diambil, cek apakah sudah lewat hari acara
                            if ($today >= $event_date) {
                                $return_status = 'ready_to_return'; // Siap dikembalikan
                                $can_return_dress = true;
                            } else {
                                $return_status = 'taken'; // Baju sudah diambil, belum waktunya return
                            }
                        } else {
                            // Baju belum diambil, cek apakah DP sudah dibayar
                            if ($total_paid >= $booking['down_payment']) {
                                $return_status = 'ready_to_take'; // Siap diambil
                            } else {
                                $return_status = 'waiting_dp'; // Menunggu DP
                            }
                        }
                    }

                    ?>

                    <?php if ($show_return_section): ?>
                    <div style="margin-top: 2rem;">
                        <div
                            style="background: white; border-radius: 15px; padding: 2rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                            <h3
                                style="color: #333; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-tshirt"></i> Status Baju Pengantin
                            </h3>

                            <?php if ($return_status === 'returned'): ?>
                            <div class="alert success">
                                <i class="fas fa-check-circle"></i>
                                <strong>Baju Pengantin Sudah Dikembalikan</strong><br>
                                Terima kasih telah mengembalikan baju pengantin tepat waktu. Semua proses booking telah
                                selesai.
                            </div>

                            <?php elseif ($return_status === 'ready_to_return'): ?>
                            <div class="alert warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Saatnya Mengembalikan Baju Pengantin</strong><br>
                                Event sudah selesai (<?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?>).
                                Silakan kembalikan baju pengantin maksimal 3 hari setelah acara.
                                <?php if ($remaining_payment > 0): ?>
                                <br><small style="color: #856404;"><strong>Sisa pembayaran:
                                        <?php echo formatRupiah($remaining_payment); ?></strong> - bisa dibayar saat
                                    pengembalian.</small>
                                <?php endif; ?>
                            </div>

                            <div style="display: flex; gap: 1rem; margin-top: 1rem; flex-wrap: wrap;">
                                <!-- <button onclick="confirmReturn()" class="btn" style="background: #28a745;">
                                            <i class="fas fa-check"></i> Konfirmasi Pengembalian Baju
                                        </button> -->

                                <?php if ($remaining_payment > 0): ?>
                                <a href="payment.php?booking=<?php echo $booking['id']; ?>&return=1" class="btn"
                                    style="background: #ff9800; color: white;">
                                    <i class="fas fa-money-bill"></i> Lunasi & Kembalikan
                                    <small>(<?php echo formatRupiah($remaining_payment); ?>)</small>
                                </a>
                                <?php endif; ?>
                            </div>

                            <?php elseif ($return_status === 'taken'): ?>
                            <div class="alert info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Baju Sudah Diambil</strong><br>
                                Baju pengantin sudah diambil. Event pada tanggal
                                <?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?>.
                                <br><small>Setelah event selesai, Anda bisa mengembalikan baju di sini.</small>
                            </div>

                            <?php elseif ($return_status === 'ready_to_take'): ?>
                            <div class="alert success">
                                <i class="fas fa-hand-holding"></i>
                                <strong>Baju Siap Diambil</strong><br>
                                DP sudah dibayar dan dikonfirmasi. Anda bisa mengambil baju pengantin sekarang.
                                <br><small>Hubungi admin untuk koordinasi pengambilan baju.</small>
                            </div>

                            <?php else: ?>
                            <div class="alert warning">
                                <i class="fas fa-clock"></i>
                                <strong>Menunggu Pembayaran DP</strong><br>
                                Setelah DP dibayar dan dikonfirmasi, Anda bisa mengambil baju pengantin.
                            </div>
                            <?php endif; ?>

                            <!-- Timeline Pengembalian yang Diperbaiki -->
                            <div style="margin-top: 2rem;">
                                <h5 style="margin-bottom: 1rem; color: #333;">
                                    <i class="fas fa-timeline"></i> Timeline Baju Pengantin
                                </h5>
                                <div class="timeline">
                                    <div
                                        class="timeline-item <?php echo ($total_paid >= $booking['down_payment']) ? 'completed' : 'current'; ?>">
                                        <div class="timeline-content">
                                            <h5>1. DP Dibayar</h5>
                                            <p><?php echo ($total_paid >= $booking['down_payment']) ? 'DP sudah dibayar' : 'Menunggu pembayaran DP'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="timeline-item <?php echo ($dress_taken) ? 'completed' : (($total_paid >= $booking['down_payment']) ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h5>2. Baju Diambil</h5>
                                            <p><?php echo ($dress_taken) ? 'Baju sudah diambil' : 'Menunggu pengambilan baju'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        class="timeline-item <?php echo ($today >= $event_date) ? 'completed' : (($dress_taken) ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h5>3. Event Selesai</h5>
                                            <p><?php echo date('d F Y', strtotime($booking['usage_date'])); ?></p>
                                        </div>
                                    </div>
                                    <div
                                        class="timeline-item <?php echo ($dress_returned) ? 'completed' : (($today >= $event_date && $dress_taken) ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h5>4. Pengembalian Baju</h5>
                                            <p><?php echo ($dress_returned) ? 'Baju sudah dikembalikan' : 'Maksimal 3 hari setelah acara'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if ($remaining_payment > 0): ?>
                                    <div
                                        class="timeline-item <?php echo ($remaining_payment <= 0) ? 'completed' : (($dress_returned || ($today >= $event_date && $dress_taken)) ? 'current' : ''); ?>">
                                        <div class="timeline-content">
                                            <h5>5. Pelunasan</h5>
                                            <p><?php echo ($remaining_payment <= 0) ? 'Sudah lunas' : 'Bisa dibayar saat return baju'; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Info Tambahan -->
                            <!-- <div
                                    style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 10px; border-left: 4px solid #17a2b8;">
                                    <h6 style="color: #0c5460; margin-bottom: 0.5rem;">
                                        <i class="fas fa-info-circle"></i> Informasi Penting:
                                    </h6>
                                    <ul style="margin: 0; padding-left: 1.5rem; color: #0c5460; font-size: 0.9rem;">
                                        <li>Baju pengantin harus dikembalikan maksimal 3 hari setelah acara</li>
                                        <li>Sisa pembayaran bisa dilunasi kapan saja, termasuk saat pengembalian baju</li>
                                        <li>Pastikan baju dalam kondisi bersih saat dikembalikan</li>
                                        <li>Hubungi admin jika ada kendala atau pertanyaan</li>
                                    </ul>
                                </div> -->
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Complete Message -->
                    <?php if ($remaining_payment <= 0): ?>
                    <div class="alert success">
                        <i class="fas fa-check-circle"></i>
                        <strong>Pembayaran Lunas!</strong><br>
                        Terima kasih! Pembayaran Anda telah lunas. Tim kami akan menghubungi Anda untuk persiapan acara.
                    </div>
                    <?php endif; ?>

                    <!-- Pending Payment Alert -->
                    <?php if ($payment_stats['pending_count'] > 0): ?>
                    <div class="alert warning">
                        <i class="fas fa-clock"></i>
                        <strong>Pembayaran Sedang Diverifikasi</strong><br>
                        <?php echo $payment_stats['pending_count']; ?> pembayaran senilai
                        <?php echo formatRupiah($payment_stats['pending_amount']); ?> sedang menunggu verifikasi admin.
                    </div>
                    <?php endif; ?>

                    <!-- Booking Status Message -->
                    <?php if ($booking['status'] === 'pending'): ?>
                    <div class="alert info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Menunggu Konfirmasi</strong><br>
                        Booking Anda sedang menunggu konfirmasi dari admin. Pembayaran akan tersedia setelah booking
                        dikonfirmasi.
                    </div>
                    <?php endif; ?>

                    <!-- Cancel Booking Option -->
                    <?php if ($booking['status'] === 'pending'): ?>
                    <div style="margin-top: 1rem;">
                        <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn btn-secondary"
                            style="width: 100%;">
                            <i class="fas fa-times"></i> Batalkan Booking
                        </button>
                    </div>
                    <?php endif; ?>

                    <!-- Status Timeline -->
                    <div style="margin-top: 2rem;">
                        <h5 style="margin-bottom: 1rem; color: #333;">
                            <i class="fas fa-list-check"></i> Progress Booking
                        </h5>
                        <div class="timeline">
                            <!-- 1. Booking Dibuat -->
                            <div class="timeline-item completed">
                                <div class="timeline-content">
                                    <h5>Booking Dibuat</h5>
                                    <p><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></p>
                                </div>
                            </div>

                            <!-- 2. Konfirmasi Admin -->
                            <div
                                class="timeline-item <?php echo in_array($booking['status'], ['confirmed', 'paid', 'in_progress', 'completed']) ? 'completed' : 'current'; ?>">
                                <div class="timeline-content">
                                    <h5>Konfirmasi Admin</h5>
                                    <p><?php echo $booking['status'] === 'pending' ? 'Menunggu konfirmasi admin' : 'Dikonfirmasi oleh admin'; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- 3. Pembayaran DP -->
                            <div
                                class="timeline-item <?php echo ($total_paid >= $booking['down_payment']) ? 'completed' : ($booking['status'] === 'confirmed' ? 'current' : ''); ?>">
                                <div class="timeline-content">
                                    <h5>Pembayaran DP</h5>
                                    <p><?php echo ($total_paid >= $booking['down_payment']) ? 'DP telah dibayar (' . formatRupiah($booking['down_payment']) . ')' : 'Menunggu pembayaran DP'; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- 4. Pengambilan Baju (khusus baju pengantin) -->
                            <?php if ($booking['service_name'] === 'Baju Pengantin'): ?>
                            <?php $dress_taken = strpos($booking['notes'], '[DRESS_TAKEN]') !== false; ?>
                            <div
                                class="timeline-item <?php echo $dress_taken ? 'completed' : (($total_paid >= $booking['down_payment']) ? 'current' : ''); ?>">
                                <div class="timeline-content">
                                    <h5>Pengambilan Baju Pengantin</h5>
                                    <p><?php echo $dress_taken ? 'Baju sudah diambil' : 'Menunggu pengambilan baju'; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 5. Persiapan Event -->
                            <?php
                            $event_date = strtotime($booking['usage_date']);
                            $today = time();
                            $event_passed = $today > $event_date;
                            $event_preparation_completed = ($booking['service_name'] === 'Baju Pengantin') ? $dress_taken : ($total_paid >= $booking['down_payment']);
                            ?>
                            <div
                                class="timeline-item <?php echo $event_passed ? 'completed' : ($event_preparation_completed ? 'current' : ''); ?>">
                                <div class="timeline-content">
                                    <h5>Persiapan Event</h5>
                                    <p>
                                        <?php if ($event_passed): ?>
                                        Event telah dilaksanakan (<?php echo date('d/m/Y', $event_date); ?>)
                                        <?php else: ?>
                                        Menuju hari H (<?php echo date('d/m/Y', $event_date); ?>)
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- 6. Pelunasan -->
                            <div
                                class="timeline-item <?php echo ($remaining_payment <= 0) ? 'completed' : ($event_passed ? 'current' : ''); ?>">
                                <div class="timeline-content">
                                    <h5>Pelunasan</h5>
                                    <p>
                                        <?php if ($remaining_payment <= 0): ?>
                                        Pembayaran lunas
                                        <?php else: ?>
                                        Sisa pembayaran: <?php echo formatRupiah($remaining_payment); ?>
                                        <?php if ($booking['service_name'] === 'Baju Pengantin' && $event_passed): ?>
                                        <br><small>Bisa dilunasi saat pengembalian baju</small>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- 7. Pengembalian Baju (khusus baju pengantin) -->
                            <?php if ($booking['service_name'] === 'Baju Pengantin'): ?>
                            <?php $dress_returned = strpos($booking['notes'], '[DRESS_RETURNED]') !== false; ?>
                            <div
                                class="timeline-item <?php echo $dress_returned ? 'completed' : ($event_passed ? 'current' : ''); ?>">
                                <div class="timeline-content">
                                    <h5>Pengembalian Baju Pengantin</h5>
                                    <p>
                                        <?php if ($dress_returned): ?>
                                        Baju sudah dikembalikan
                                        <?php elseif ($event_passed): ?>
                                        Menunggu pengembalian baju (maks. 3 hari setelah acara)
                                        <?php else: ?>
                                        Setelah event selesai
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- 8. Event Selesai -->
                            <div
                                class="timeline-item <?php echo $booking['status'] === 'completed' ? 'completed' : ''; ?>">
                                <div class="timeline-content">
                                    <h5>Booking Selesai</h5>
                                    <p>
                                        <?php if ($booking['status'] === 'completed'): ?>
                                        Semua proses telah selesai
                                        <?php else: ?>
                                        <?php if ($booking['service_name'] === 'Baju Pengantin'): ?>
                                        Menunggu pengembalian baju dan pelunasan
                                        <?php else: ?>
                                        Menunggu pelunasan pembayaran
                                        <?php endif; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Summary -->
                        <div style="margin-top: 2rem; padding: 1rem; background: #f8f9fa; border-radius: 8px;">
                            <h6 style="color: #333; margin-bottom: 1rem;">Ringkasan Progress:</h6>
                            <div
                                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; font-size: 0.9rem;">
                                <div>
                                    <strong>Status Booking:</strong><br>
                                    <span style="color: <?php echo $payment_status_info['color']; ?>;">
                                        <?php echo formatStatusIndonesia($booking['status'], $total_paid, $booking['down_payment'], $booking['total_amount']); ?>
                                    </span>
                                </div>
                                <div>
                                    <strong>Pembayaran:</strong><br>
                                    <span
                                        style="color: <?php echo ($remaining_payment <= 0) ? '#28a745' : '#dc3545'; ?>;">
                                        <?php echo ($remaining_payment <= 0) ? 'Lunas' : 'Sisa ' . formatRupiah($remaining_payment); ?>
                                    </span>
                                </div>
                                <?php if ($booking['service_name'] === 'Baju Pengantin'): ?>
                                <div>
                                    <strong>Status Baju:</strong><br>
                                    <span style="color: <?php
                                                            if ($dress_returned) echo '#28a745';
                                                            elseif ($dress_taken && $event_passed) echo '#ffc107';
                                                            elseif ($dress_taken) echo '#17a2b8';
                                                            elseif ($total_paid >= $booking['down_payment']) echo '#ffc107';
                                                            else echo '#666';
                                                            ?>;">
                                        <?php
                                            if ($dress_returned) echo 'Dikembalikan';
                                            elseif ($dress_taken && $event_passed) echo 'Perlu Dikembalikan';
                                            elseif ($dress_taken) echo 'Sudah Diambil';
                                            elseif ($total_paid >= $booking['down_payment']) echo 'Siap Diambil';
                                            else echo 'Belum Bisa Diambil';
                                            ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <div>
                                    <strong>Tanggal Event:</strong><br>
                                    <span style="color: <?php echo ($event_passed) ? '#28a745' : '#666'; ?>;">
                                        <?php echo date('d/m/Y', $event_date) . ' (' . ($event_passed ? 'Selesai' : 'Akan Datang') . ')'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Next Action Recommendations -->
                        <?php
                        $next_actions = [];

                        if ($booking['status'] === 'pending') {
                            $next_actions[] = ['text' => 'Menunggu konfirmasi admin', 'color' => '#ffc107', 'icon' => 'clock'];
                        } elseif ($booking['status'] === 'confirmed' && $total_paid < $booking['down_payment']) {
                            $next_actions[] = ['text' => 'Bayar DP untuk mengamankan booking', 'color' => '#dc3545', 'icon' => 'credit-card'];
                        } elseif ($booking['service_name'] === 'Baju Pengantin' && !$dress_taken && $total_paid >= $booking['down_payment']) {
                            $next_actions[] = ['text' => 'Ambil baju pengantin (DP sudah dibayar)', 'color' => '#17a2b8', 'icon' => 'hand-holding'];
                        } elseif (!$event_passed && $total_paid >= $booking['down_payment']) {
                            $next_actions[] = ['text' => 'Persiapan untuk hari acara', 'color' => '#28a745', 'icon' => 'calendar-check'];
                        } elseif ($event_passed && $booking['service_name'] === 'Baju Pengantin' && !$dress_returned) {
                            $next_actions[] = ['text' => 'Kembalikan baju pengantin (maks. 3 hari)', 'color' => '#dc3545', 'icon' => 'undo'];
                        } elseif ($remaining_payment > 0) {
                            $next_actions[] = ['text' => 'Lunasi sisa pembayaran', 'color' => '#ffc107', 'icon' => 'money-bill'];
                        }
                        ?>

                        <?php if (!empty($next_actions)): ?>
                        <div
                            style="margin-top: 1.5rem; padding: 1rem; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 0 8px 8px 0;">
                            <h6 style="color: #856404; margin-bottom: 0.5rem;">
                                <i class="fas fa-info-circle"></i> Langkah Selanjutnya:
                            </h6>
                            <?php foreach ($next_actions as $action): ?>
                            <div style="color: <?php echo $action['color']; ?>; margin-bottom: 0.25rem;">
                                <i class="fas fa-<?php echo $action['icon']; ?>"></i> <?php echo $action['text']; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <h3>Riwayat Pembayaran (<?php echo count($payments); ?> transaksi)</h3>
            </div>
            <div class="card-body">
                <div class="payment-history">
                    <?php
                        $running_total = 0;
                        foreach ($payments as $index => $payment):
                            if ($payment['status'] === 'verified') {
                                $running_total += $payment['amount'];
                            }
                            $payment_type = getPaymentType($payment['amount'], $booking, $running_total - $payment['amount']);
                        ?>
                    <div class="payment-card <?php echo $payment['status']; ?>">
                        <h5>
                            <span>
                                <?php echo formatRupiah($payment['amount']); ?>
                                <small style="color: #666; font-weight: normal;">(<?php echo $payment_type; ?>)</small>
                            </span>
                            <span class="status <?php echo $payment['status']; ?>">
                                <?php
                                        $status_text = [
                                            'pending' => 'Menunggu Verifikasi',
                                            'verified' => 'Terverifikasi',
                                            'rejected' => 'Ditolak'
                                        ];
                                        echo $status_text[$payment['status']] ?? ucfirst($payment['status']);
                                        ?>
                            </span>
                        </h5>
                        <div class="payment-details">
                            <div>
                                <strong>Tanggal Bayar:</strong><br>
                                <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                            </div>
                            <div>
                                <strong>Metode:</strong><br>
                                <?php echo ucfirst($payment['payment_method']); ?>
                            </div>
                            <div>
                                <strong>Disubmit:</strong><br>
                                <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                            </div>
                            <?php if ($payment['status'] === 'pending'): ?>
                            <div>
                                <strong>Status:</strong><br>
                                <span style="color: #856404;">Menunggu verifikasi admin</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($payment['notes']): ?>
                        <div
                            style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(255,255,255,0.7); border-radius: 4px; font-size: 0.9rem;">
                            <strong>Catatan:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <script>
    function cancelBooking(bookingId) {
        if (confirm('Apakah Anda yakin ingin membatalkan booking ini? Tindakan ini tidak dapat dibatalkan.')) {
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

    // Auto refresh untuk update status pembayaran
    setInterval(function() {
        // Hanya refresh jika ada pembayaran pending
        if (document.querySelectorAll('.status.pending').length > 0) {
            location.reload();
        }
    }, 60000); // 1 minute

    // Smooth scroll untuk anchor links
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight payment timeline based on current status
        const timelineItems = document.querySelectorAll('.timeline-item');
        timelineItems.forEach(function(item, index) {
            if (item.classList.contains('current')) {
                item.style.borderLeft = '3px solid #ff6b6b';
                item.style.paddingLeft = '1rem';
                item.style.background = 'rgba(255, 107, 107, 0.05)';
                item.style.borderRadius = '5px';
            }
        });
    });

    // Tambahkan fungsi ini ke script yang sudah ada
    function confirmReturn() {
        const remainingPayment = <?php echo $remaining_payment; ?>;
        let message = 'Apakah Anda yakin sudah mengembalikan baju pengantin?';

        if (remainingPayment > 0) {
            message += '\n\nPerhatian: Anda masih memiliki sisa pembayaran sebesar ' + formatRupiahJS(
                    remainingPayment) +
                '.\nAnda bisa melunasi sekarang dengan tombol "Bayar Sisa & Return Baju" atau nanti secara terpisah.';
        }

        message += '\n\nTindakan ini tidak dapat dibatalkan.';

        if (confirm(message)) {
            // Create form to submit return confirmation
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'return_dress.php';

            const bookingInput = document.createElement('input');
            bookingInput.type = 'hidden';
            bookingInput.name = 'booking_id';
            bookingInput.value = <?php echo $booking['id']; ?>;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'return_dress';

            form.appendChild(bookingInput);
            form.appendChild(actionInput);
            document.body.appendChild(form);
            form.submit();
        }
    }

    function formatRupiahJS(amount) {
        return 'Rp ' + amount.toLocaleString('id-ID');
    }

    // Test function - hapus setelah testing
    function testReturnStatus() {
        console.log('Return Status Debug:');
        console.log('Service Name: <?php echo $booking['service_name']; ?>');
        console.log('Event Date: <?php echo $booking['usage_date']; ?>');
        console.log('Today: <?php echo date('Y-m-d'); ?>');
        console.log('Total Paid: <?php echo $total_paid; ?>');
        console.log('DP Required: <?php echo $booking['down_payment']; ?>');
        console.log('Notes: <?php echo addslashes($booking['notes'] ?? ''); ?>');
        console.log('Return Status: <?php echo $return_status; ?>');
        console.log('Can Return: <?php echo $can_return_dress ? 'true' : 'false'; ?>');
    }

    // Panggil test function untuk debugging
    testReturnStatus();
    </script>
</body>

</html>