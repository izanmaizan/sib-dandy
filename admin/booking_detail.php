<!-- admin/booking_detail.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$success = '';
$error = '';

if (!$booking_id) {
    header('Location: bookings.php');
    exit();
}

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $new_status = $_POST['status'];
                $notes = trim($_POST['notes']);
                
                $stmt = $db->prepare("UPDATE bookings SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$new_status, $notes, $booking_id])) {
                    $success = "Status booking berhasil diupdate!";
                } else {
                    $error = "Gagal mengupdate status booking!";
                }
                break;
                
            case 'add_notes':
                $notes = trim($_POST['notes']);
                
                $stmt = $db->prepare("UPDATE bookings SET notes = ?, updated_at = NOW() WHERE id = ?");
                if ($stmt->execute([$notes, $booking_id])) {
                    $success = "Catatan berhasil ditambahkan!";
                } else {
                    $error = "Gagal menambahkan catatan!";
                }
                break;

            case 'update_payment_status':
                $payment_id = (int)$_POST['payment_id'];
                $payment_status = $_POST['payment_status'];
                $payment_notes = trim($_POST['payment_notes']);
                
                $stmt = $db->prepare("UPDATE payments SET status = ?, notes = ? WHERE id = ?");
                if ($stmt->execute([$payment_status, $payment_notes, $payment_id])) {
                    $success = "Status pembayaran berhasil diupdate!";
                    
                    // Auto-update booking status if payment verified
                    if ($payment_status === 'verified') {
                        // Recalculate total paid
                        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
                        $stmt->execute([$booking_id]);
                        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;
                        
                        // Get booking data
                        $stmt = $db->prepare("SELECT total_amount, down_payment, status FROM bookings WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($booking_data) {
                            $new_booking_status = $booking_data['status'];
                            
                            // Ubah logika: jika sudah lunas langsung set ke 'paid'
                            if ($total_paid >= $booking_data['total_amount']) {
                                $new_booking_status = 'paid'; // Fully paid
                            } elseif ($total_paid >= $booking_data['down_payment'] && $booking_data['status'] === 'confirmed') {
                                $new_booking_status = 'paid'; // DP paid
                            }
                            
                            if ($new_booking_status !== $booking_data['status']) {
                                $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                                $stmt->execute([$new_booking_status, $booking_id]);
                            }
                        }
                    }
                } else {
                    $error = "Gagal mengupdate status pembayaran!";
                }
                break;

            case 'add_manual_payment':
                $amount = (float)$_POST['payment_amount'];
                $payment_method = $_POST['payment_method'];
                $payment_date = $_POST['payment_date'];
                $payment_notes = trim($_POST['payment_notes']);
                
                if ($amount <= 0) {
                    $error = "Jumlah pembayaran harus lebih dari 0!";
                } else {
                    // Insert manual payment record
                    $stmt = $db->prepare("INSERT INTO payments (booking_id, payment_date, amount, payment_method, status, notes, created_at) VALUES (?, ?, ?, ?, 'verified', ?, NOW())");
                    if ($stmt->execute([$booking_id, $payment_date, $amount, $payment_method, $payment_notes])) {
                        $success = "Pembayaran manual berhasil ditambahkan!";
                        
                        // Auto-update booking status
                        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
                        $stmt->execute([$booking_id]);
                        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;
                        
                        $stmt = $db->prepare("SELECT total_amount, down_payment, status FROM bookings WHERE id = ?");
                        $stmt->execute([$booking_id]);
                        $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($booking_data && $total_paid >= $booking_data['down_payment']) {
                            $new_status = $total_paid >= $booking_data['total_amount'] ? 'paid' : 'paid';
                            $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                            $stmt->execute([$new_status, $booking_id]);
                        }
                    } else {
                        $error = "Gagal menambahkan pembayaran manual!";
                    }
                }
                break;
        }
    }
}

// Get comprehensive booking details
$stmt = $db->prepare("SELECT b.*, 
                     u.full_name as customer_name, u.email, u.phone, u.username,
                     p.name as package_name, p.description as package_description, 
                     p.includes as package_includes, p.price as package_price,
                     p.service_type as service_name
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     JOIN packages p ON b.package_id = p.id 
                     WHERE b.id = ?");
$stmt->execute([$booking_id]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: bookings.php');
    exit();
}

// Get comprehensive payment history with additional details
$stmt = $db->prepare("SELECT p.*, 
                     DATE(p.created_at) as submission_date,
                     TIME(p.created_at) as submission_time,
                     DATEDIFF(CURDATE(), p.payment_date) as days_since_payment,
                     CASE 
                         WHEN p.status = 'pending' THEN DATEDIFF(CURDATE(), p.created_at)
                         ELSE NULL 
                     END as pending_days
                     FROM payments p 
                     WHERE p.booking_id = ? 
                     ORDER BY p.created_at DESC");
$stmt->execute([$booking_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate comprehensive payment statistics
$payment_stats = [
    'total_transactions' => count($payments),
    'verified_amount' => 0,
    'pending_amount' => 0,
    'rejected_amount' => 0,
    'verified_count' => 0,
    'pending_count' => 0,
    'rejected_count' => 0,
    'first_payment_date' => null,
    'last_payment_date' => null,
    'avg_payment_amount' => 0,
    'payment_methods' => []
];

if (!empty($payments)) {
    // Calculate amounts and counts by status
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
        
        // Track payment methods
        $method = $payment['payment_method'];
        if (!isset($payment_stats['payment_methods'][$method])) {
            $payment_stats['payment_methods'][$method] = ['count' => 0, 'amount' => 0];
        }
        $payment_stats['payment_methods'][$method]['count']++;
        if ($payment['status'] === 'verified') {
            $payment_stats['payment_methods'][$method]['amount'] += $payment['amount'];
        }
    }
    
    // Get first and last payment dates
    $payment_dates = array_column($payments, 'payment_date');
    $payment_stats['first_payment_date'] = min($payment_dates);
    $payment_stats['last_payment_date'] = max($payment_dates);
    
    // Calculate average payment amount (verified only)
    if ($payment_stats['verified_count'] > 0) {
        $payment_stats['avg_payment_amount'] = $payment_stats['verified_amount'] / $payment_stats['verified_count'];
    }
}

// Calculate using verified amount for accurate calculation
$total_paid = $payment_stats['verified_amount'];
$remaining_payment = $booking['total_amount'] - $total_paid;
$payment_progress = $booking['total_amount'] > 0 ? ($total_paid / $booking['total_amount']) * 100 : 0;
$dp_progress = $booking['down_payment'] > 0 ? min(($total_paid / $booking['down_payment']) * 100, 100) : 0;

// Determine payment status and next steps
function getPaymentStatus($booking, $total_paid) {
    if ($total_paid >= $booking['total_amount']) {
        return [
            'status' => 'fully_paid',
            'text' => 'Lunas',
            'color' => '#28a745',
            'icon' => 'fas fa-check-circle',
            'next_step' => 'Pembayaran selesai'
        ];
    } elseif ($total_paid >= $booking['down_payment'] && $booking['down_payment'] > 0) {
        return [
            'status' => 'dp_paid',
            'text' => 'DP Dibayar',
            'color' => '#17a2b8',
            'icon' => 'fas fa-clock',
            'next_step' => 'Menunggu pelunasan'
        ];
    } elseif ($total_paid > 0) {
        return [
            'status' => 'partial_paid',
            'text' => 'Dibayar Sebagian',
            'color' => '#ffc107',
            'icon' => 'fas fa-exclamation-triangle',
            'next_step' => 'Perlu pembayaran tambahan'
        ];
    } else {
        return [
            'status' => 'not_paid',
            'text' => 'Belum Dibayar',
            'color' => '#dc3545',
            'icon' => 'fas fa-times-circle',
            'next_step' => 'Menunggu pembayaran'
        ];
    }
}

$payment_status_info = getPaymentStatus($booking, $total_paid);

// Function to get payment type
function getPaymentType($amount, $booking, $previous_total = 0) {
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

function getAdminBookingStatusIndonesia($status, $total_paid, $down_payment, $total_amount) {
    if ($total_paid >= $total_amount) {
        return 'Lunas';
    }
    
    if ($total_paid >= $down_payment && $total_paid < $total_amount && $status === 'paid') {
        return 'Dibayar (DP)';
    }
    
    $status_map = [
        'pending' => 'Menunggu Konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'paid' => 'Dibayar',
        'in_progress' => 'Sedang Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];
    
    return $status_map[$status] ?? ucfirst($status);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Booking - Dandy Gallery Admin</title>
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
            background: rgba(255,255,255,0.2);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        /* Enhanced Payment Summary Styles */
        .payment-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .payment-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payment-status-badge {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255,255,255,0.2);
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
        }

        .payment-body {
            padding: 2rem;
        }

        .payment-breakdown {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .breakdown-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #333;
        }

        .breakdown-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }

        .breakdown-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .payment-methods {
            margin-top: 2rem;
        }

        .method-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .method-name {
            font-weight: 500;
            text-transform: capitalize;
            color: #333;
        }

        .method-stats {
            text-align: right;
        }

        .method-count {
            font-size: 0.9rem;
            color: #666;
        }

        .method-amount {
            font-weight: bold;
            color: #ff6b6b;
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

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            position: relative;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .quick-amount-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }

        .quick-amount-btn {
            padding: 0.5rem 1rem;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .quick-amount-btn:hover {
            background: #ff6b6b;
            color: white;
            border-color: #ff6b6b;
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
            <h1 class="page-title">Detail Booking & Pembayaran</h1>
            <a href="bookings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Daftar
            </a>
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

        <!-- Booking Header -->
        <div class="booking-header">
            <div class="booking-code">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($booking['booking_code']); ?>
            </div>
            <div><?php echo htmlspecialchars($booking['package_name']); ?></div>
            <div class="booking-status">
<i class="<?php echo $service_icon; ?>"></i>
                Status: <?php echo getAdminBookingStatusIndonesia($booking['status'], $total_paid, $booking['down_payment'], $booking['total_amount']); ?>
            </div>
        </div>

        <!-- Action Buttons -->
        <div style="display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 2rem;">
            <button type="button" class="btn" onclick="openStatusModal()">
                <i class="fas fa-edit"></i> Update Status
            </button>
            <button type="button" class="btn btn-warning" onclick="openNotesModal()">
                <i class="fas fa-sticky-note"></i> Tambah Catatan
            </button>
            <button type="button" class="btn btn-success" onclick="openManualPaymentModal()">
                <i class="fas fa-plus-circle"></i> Tambah Pembayaran Manual
            </button>
            <?php if ($booking['status'] === 'pending'): ?>
            <button type="button" class="btn btn-success" onclick="quickConfirm()">
                <i class="fas fa-check"></i> Konfirmasi Cepat
            </button>
            <?php endif; ?>
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
                                <span class="info-value"><?php echo date('d F Y', strtotime($booking['usage_date'])); ?></span>
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
                                <span class="info-label">Lokasi Acara:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['venue_address']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="info-section">
                            <h4><i class="fas fa-user"></i> Informasi Customer</h4>
                            <div class="info-item">
                                <span class="info-label">Nama Lengkap:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Username:</span>
                                <span class="info-value">@<?php echo htmlspecialchars($booking['username']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Email:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Telepon:</span>
                                <span class="info-value"><?php echo htmlspecialchars($booking['phone'] ?: '-'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Booking Dibuat:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Package Includes -->
                    <?php if ($booking['package_includes']): ?>
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem;">
                        <h4><i class="fas fa-check-circle"></i> Yang Termasuk dalam Paket</h4>
                        <ul style="list-style: none; color: #666;">
                            <?php 
                            $includes = explode(',', $booking['package_includes']);
                            foreach ($includes as $include): 
                            ?>
                                <li style="padding: 0.25rem 0; position: relative; padding-left: 1.5rem;">
                                    <span style="position: absolute; left: 0; color: #28a745; font-weight: bold;">✓</span>
                                    <?php echo trim(htmlspecialchars($include)); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Special Request -->
                    <?php if ($booking['special_request']): ?>
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <h5><i class="fas fa-comment"></i> Permintaan Khusus Customer</h5>
                        <p style="margin: 0.5rem 0 0 0; color: #856404;">
                            <?php echo nl2br(htmlspecialchars($booking['special_request'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>

                    <!-- Admin Notes -->
                    <?php if ($booking['notes']): ?>
                    <div style="background: #e3f2fd; padding: 1rem; border-radius: 8px;">
                        <h5><i class="fas fa-sticky-note"></i> Catatan Admin</h5>
                        <p style="margin: 0.5rem 0 0 0; color: #1565c0;">
                            <?php echo nl2br(htmlspecialchars($booking['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Rincian Pembayaran Lengkap -->
            <div class="payment-summary">
                <div class="payment-header">
                    <h3><i class="fas fa-money-bill-wave"></i> Rincian Pembayaran</h3>
                    <div class="payment-status-badge">
                        <i class="<?php echo $payment_status_info['icon']; ?>"></i>
                        <span><?php echo $payment_status_info['text']; ?></span>
                    </div>
                </div>
                
                <div class="payment-body">
                    <!-- Payment Breakdown -->
                    <div class="payment-breakdown">
                        <div class="breakdown-item">
                            <span>Harga Paket:</span>
                            <span><?php echo formatRupiah($booking['package_price']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span>Total Tagihan:</span>
                            <span><?php echo formatRupiah($booking['total_amount']); ?></span>
                        </div>
                        <?php if ($booking['down_payment'] > 0): ?>
                        <div class="breakdown-item">
                            <span>DP yang Diperlukan:</span>
                            <span><?php echo formatRupiah($booking['down_payment']); ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span>Sisa Setelah DP:</span>
                            <span><?php echo formatRupiah($booking['remaining_payment']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="breakdown-item">
                            <span>Sudah Dibayar (Verified):</span>
                            <span style="color: #28a745;"><?php echo formatRupiah($payment_stats['verified_amount']); ?></span>
                        </div>
                        <?php if ($payment_stats['pending_amount'] > 0): ?>
                        <div class="breakdown-item">
                            <span>Pending Verifikasi:</span>
                            <span style="color: #ffc107;"><?php echo formatRupiah($payment_stats['pending_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($payment_stats['rejected_amount'] > 0): ?>
                        <div class="breakdown-item">
                            <span>Pembayaran Ditolak:</span>
                            <span style="color: #dc3545;"><?php echo formatRupiah($payment_stats['rejected_amount']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="breakdown-item">
                            <span>Sisa yang Harus Dibayar:</span>
                            <span style="color: <?php echo $remaining_payment > 0 ? '#dc3545' : '#28a745'; ?>;">
                                <?php echo formatRupiah($remaining_payment); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Payment Methods Summary -->
                    <?php if (!empty($payment_stats['payment_methods'])): ?>
                    <div class="payment-methods">
                        <div class="breakdown-title">
                            <i class="fas fa-credit-card"></i> Metode Pembayaran
                        </div>
                        <?php foreach ($payment_stats['payment_methods'] as $method => $data): ?>
                        <div class="method-item">
                            <div class="method-name">
                                <i class="fas fa-<?php echo $method === 'transfer' ? 'university' : ($method === 'cash' ? 'money-bill' : 'credit-card'); ?>"></i>
                                <?php echo ucfirst($method); ?>
                            </div>
                            <div class="method-stats">
                                <div class="method-count"><?php echo $data['count']; ?> transaksi</div>
                                <div class="method-amount"><?php echo formatRupiah($data['amount']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Payment Status Alert -->
                    <?php if ($remaining_payment <= 0): ?>
                    <div style="background: #d4edda; border: 2px solid #c3e6cb; padding: 1.5rem; border-radius: 10px; margin-top: 2rem; text-align: center;">
                        <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 0.5rem; color: #28a745;"></i>
                        <h4 style="color: #155724;">Pembayaran Lunas!</h4>
                        <p style="margin: 0.5rem 0 0 0; color: #155724;">Semua pembayaran telah diterima dan diverifikasi</p>
                    </div>
                    <?php elseif ($payment_stats['pending_count'] > 0): ?>
                    <div style="background: #fff3cd; border: 2px solid #ffeaa7; padding: 1.5rem; border-radius: 10px; margin-top: 2rem; text-align: center;">
                        <i class="fas fa-clock" style="font-size: 2rem; margin-bottom: 0.5rem; color: #856404;"></i>
                        <h4 style="color: #856404;">Ada Pembayaran Pending!</h4>
                        <p style="margin: 0.5rem 0 0 0; color: #856404;"><?php echo $payment_stats['pending_count']; ?> pembayaran menunggu verifikasi</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Detailed Payment History -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <h3>Riwayat Pembayaran (<?php echo count($payments); ?> transaksi)</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($payments)): ?>
                    <?php 
                    $running_total = 0;
                    foreach ($payments as $index => $payment): 
                        if ($payment['status'] === 'verified') {
                            $running_total += $payment['amount'];
                        }
                        $payment_type = getPaymentType($payment['amount'], $booking, $running_total - $payment['amount']);
                    ?>
                    <div style="background: #f8f9fa; border-radius: 8px; padding: 1rem; margin-bottom: 0.5rem; border-left: 4px solid <?php echo $payment['status'] === 'verified' ? '#28a745' : ($payment['status'] === 'pending' ? '#ffc107' : '#dc3545'); ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                            <div>
                                <strong style="color: #ff6b6b; font-size: 1.1rem;"><?php echo formatRupiah($payment['amount']); ?></strong>
                                <small style="color: #666; margin-left: 0.5rem;">(<?php echo $payment_type; ?>)</small>
                            </div>
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
                        </div>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 0.5rem; font-size: 0.9rem; color: #666;">
                            <div>
                                <strong>Tanggal:</strong><br>
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
                            <?php if ($payment['pending_days'] !== null): ?>
                            <div>
                                <strong>Pending:</strong><br>
                                <span style="color: <?php echo $payment['pending_days'] > 2 ? '#dc3545' : '#ffc107'; ?>">
                                    <?php echo $payment['pending_days']; ?> hari
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if ($payment['notes']): ?>
                        <div style="margin-top: 0.5rem; padding: 0.5rem; background: #fff; border-radius: 4px; font-size: 0.9rem;">
                            <strong>Catatan:</strong> <?php echo htmlspecialchars($payment['notes']); ?>
                        </div>
                        <?php endif; ?>

                        <div style="margin-top: 0.5rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <?php if ($payment['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-success" 
                                        onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'verified')">
                                    <i class="fas fa-check"></i> Verifikasi
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" 
                                        onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, 'rejected')">
                                    <i class="fas fa-times"></i> Tolak
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($payment['payment_proof']): ?>
                                <button type="button" class="btn btn-sm btn-secondary" 
                                        onclick="viewPaymentProof('<?php echo htmlspecialchars($payment['payment_proof']); ?>')">
                                    <i class="fas fa-image"></i> Bukti
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                <div style="background: #f8f9fa; padding: 2rem; border-radius: 8px; text-align: center;">
                    <i class="fas fa-receipt" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                    <p>Belum ada pembayaran untuk booking ini</p>
                    <button type="button" class="btn" onclick="openManualPaymentModal()" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Tambah Pembayaran Pertama
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Booking</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    
                    <div class="form-group">
                        <label for="status">Status Baru</label>
                        <select id="status" name="status" required>
                            <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Menunggu Konfirmasi</option>
                            <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                            <option value="paid" <?php echo $booking['status'] === 'paid' ? 'selected' : ''; ?>>Dibayar</option>
                            <option value="in_progress" <?php echo $booking['status'] === 'in_progress' ? 'selected' : ''; ?>>Sedang Berlangsung</option>
                            <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Selesai</option>
                            <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_notes">Catatan (opsional)</label>
                        <textarea id="status_notes" name="notes" rows="3" 
                                  placeholder="Berikan catatan tambahan untuk customer..."><?php echo htmlspecialchars($booking['notes']); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Notes Modal -->
    <div id="notesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Catatan</h3>
                <span class="close" onclick="closeNotesModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_notes">
                    
                    <div class="form-group">
                        <label for="notes">Catatan untuk Customer</label>
                        <textarea id="notes" name="notes" rows="5" required
                                  placeholder="Tulis catatan penting yang perlu diketahui customer..."><?php echo htmlspecialchars($booking['notes']); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeNotesModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Simpan Catatan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Manual Payment Modal -->
    <div id="manualPaymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Tambah Pembayaran Manual</h3>
                <span class="close" onclick="closeManualPaymentModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="add_manual_payment">
                    
                    <div class="form-group">
                        <label for="payment_amount">Jumlah Pembayaran</label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="1" required>
                        
                        <!-- Quick Amount Buttons -->
                        <div class="quick-amount-buttons">
                            <?php if ($booking['down_payment'] > 0 && $total_paid < $booking['down_payment']): ?>
                                <button type="button" class="quick-amount-btn" onclick="setAmount(<?php echo $booking['down_payment']; ?>)">
                                    DP: <?php echo formatRupiah($booking['down_payment']); ?>
                                </button>
                            <?php endif; ?>
                            
                            <?php if ($remaining_payment > 0): ?>
                                <button type="button" class="quick-amount-btn" onclick="setAmount(<?php echo $remaining_payment; ?>)">
                                    Sisa: <?php echo formatRupiah($remaining_payment); ?>
                                </button>
                            <?php endif; ?>
                            
                            <button type="button" class="quick-amount-btn" onclick="setAmount(<?php echo $booking['total_amount']; ?>)">
                                Lunas: <?php echo formatRupiah($booking['total_amount']); ?>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Metode Pembayaran</label>
                        <select id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="transfer">Transfer Bank</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Tanggal Pembayaran</label>
                        <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_notes">Catatan (opsional)</label>
                        <textarea id="payment_notes" name="payment_notes" rows="3" 
                                  placeholder="Catatan pembayaran, referensi transaksi, dll..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeManualPaymentModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Simpan Pembayaran
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Status Modal -->
    <div id="paymentStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Pembayaran</h3>
                <span class="close" onclick="closePaymentStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="payment_id" id="paymentId">
                    <input type="hidden" name="payment_status" id="paymentStatusValue">
                    
                    <div class="form-group">
                        <label for="payment_notes">Catatan (opsional)</label>
                        <textarea id="payment_notes" name="payment_notes" rows="3" 
                                  placeholder="Berikan catatan untuk pembayaran ini..."></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closePaymentStatusModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn" id="paymentSubmitBtn">
                            <i class="fas fa-save"></i> Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Proof Modal -->
    <div id="proofModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Bukti Pembayaran</h3>
                <span class="close" onclick="closeProofModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="proofModalContent" style="text-align: center;">
                    <!-- Bukti pembayaran akan ditampilkan di sini -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function openStatusModal() {
            document.getElementById('statusModal').style.display = 'block';
        }

        function closeStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function openNotesModal() {
            document.getElementById('notesModal').style.display = 'block';
        }

        function closeNotesModal() {
            document.getElementById('notesModal').style.display = 'none';
        }

        function openManualPaymentModal() {
            document.getElementById('manualPaymentModal').style.display = 'block';
        }

        function closeManualPaymentModal() {
            document.getElementById('manualPaymentModal').style.display = 'none';
            document.getElementById('payment_amount').value = '';
            document.getElementById('payment_notes').value = '';
        }

        function setAmount(amount) {
            document.getElementById('payment_amount').value = amount;
        }

        function updatePaymentStatus(paymentId, status) {
            document.getElementById('paymentId').value = paymentId;
            document.getElementById('paymentStatusValue').value = status;
            
            const submitBtn = document.getElementById('paymentSubmitBtn');
            if (status === 'verified') {
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Verifikasi';
                submitBtn.className = 'btn btn-success';
            } else {
                submitBtn.innerHTML = '<i class="fas fa-times"></i> Tolak';
                submitBtn.className = 'btn btn-danger';
            }
            
            document.getElementById('paymentStatusModal').style.display = 'block';
        }

        function closePaymentStatusModal() {
            document.getElementById('paymentStatusModal').style.display = 'none';
            document.getElementById('payment_notes').value = '';
        }

        function viewPaymentProof(imagePath) {
            document.getElementById('proofModal').style.display = 'block';
            document.getElementById('proofModalContent').innerHTML = `
                <img src="../uploads/payments/${imagePath}" 
                     style="max-width: 100%; height: auto; border-radius: 8px;" 
                     alt="Bukti Pembayaran"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=\\"http://www.w3.org/2000/svg\\" viewBox=\\"0 0 400 300\\"><rect fill=\\"%23f8f9fa\\" width=\\"400\\" height=\\"300\\"/><text x=\\"200\\" y=\\"150\\" text-anchor=\\"middle\\" fill=\\"%23666\\">Gambar tidak tersedia</text></svg>'">
            `;
        }

        function closeProofModal() {
            document.getElementById('proofModal').style.display = 'none';
        }

        function quickConfirm() {
            if (confirm('Apakah Anda yakin ingin mengkonfirmasi booking ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'update_status';
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status';
                statusInput.value = 'confirmed';
                
                const notesInput = document.createElement('input');
                notesInput.type = 'hidden';
                notesInput.name = 'notes';
                notesInput.value = 'Booking dikonfirmasi oleh admin. Silakan lakukan pembayaran.';
                
                form.appendChild(actionInput);
                form.appendChild(statusInput);
                form.appendChild(notesInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['statusModal', 'notesModal', 'paymentStatusModal', 'proofModal', 'manualPaymentModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
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
            if (document.querySelectorAll('.status.pending').length > 0) {
                location.reload();
            }
        }, 300000); // 5 minutes

        // Format currency input
        document.getElementById('payment_amount').addEventListener('input', function() {
            let value = this.value.replace(/[^\d]/g, '');
            this.value = value;
        });

        // Auto-calculate payment type when amount changes
        document.getElementById('payment_amount').addEventListener('change', function() {
            const amount = parseFloat(this.value) || 0;
            const totalAmount = <?php echo $booking['total_amount']; ?>;
            const downPayment = <?php echo $booking['down_payment']; ?>;
            const totalPaid = <?php echo $total_paid; ?>;
            
            let paymentType = '';
            const totalAfter = totalPaid + amount;
            
            if (totalAfter >= totalAmount) {
                if (totalPaid == 0) {
                    paymentType = 'Lunas Langsung';
                } else {
                    paymentType = 'Pelunasan';
                }
            } else if (totalPaid < downPayment && totalAfter >= downPayment) {
                paymentType = 'DP (Down Payment)';
            } else {
                paymentType = 'Cicilan';
            }
            
            // Update placeholder or show payment type info
            const notesField = document.getElementById('payment_notes');
            if (paymentType) {
                notesField.placeholder = `Jenis pembayaran: ${paymentType}. Tambahkan catatan lain jika diperlukan...`;
            }
        });

        // Highlight running total for verified payments
        document.addEventListener('DOMContentLoaded', function() {
            const verifiedPayments = document.querySelectorAll('.status.verified');
            let runningTotal = 0;
            
            verifiedPayments.forEach(function(payment) {
                const paymentCard = payment.closest('div[style*="border-left"]');
                if (paymentCard) {
                    // Add running total indicator
                    const amountElement = paymentCard.querySelector('strong[style*="color: #ff6b6b"]');
                    if (amountElement) {
                        const amount = parseFloat(amountElement.textContent.replace(/[^\d]/g, ''));
                        runningTotal += amount;
                        
                        // Add running total display
                        const runningTotalDiv = document.createElement('div');
                        runningTotalDiv.style.cssText = 'margin-top: 0.5rem; padding: 0.5rem; background: #e8f5e8; border-radius: 4px; font-size: 0.9rem; text-align: center;';
                        runningTotalDiv.innerHTML = `<strong>Total Diterima: ${formatRupiahJS(runningTotal)}</strong>`;
                        paymentCard.appendChild(runningTotalDiv);
                    }
                }
            });
        });

        // Helper function for JavaScript formatting
        function formatRupiahJS(amount) {
            return 'Rp ' + amount.toLocaleString('id-ID');
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 's':
                        e.preventDefault();
                        openStatusModal();
                        break;
                    case 'n':
                        e.preventDefault();
                        openNotesModal();
                        break;
                    case 'p':
                        e.preventDefault();
                        openManualPaymentModal();
                        break;
                }
            }
            
            // ESC to close modals
            if (e.key === 'Escape') {
                const modals = ['statusModal', 'notesModal', 'paymentStatusModal', 'proofModal', 'manualPaymentModal'];
                modals.forEach(modalId => {
                    document.getElementById(modalId).style.display = 'none';
                });
            }
        });

        // Add tooltip for keyboard shortcuts
        const tooltip = document.createElement('div');
        tooltip.style.cssText = 'position: fixed; bottom: 20px; right: 20px; background: rgba(0,0,0,0.8); color: white; padding: 10px; border-radius: 5px; font-size: 0.8rem; z-index: 999;';
        tooltip.innerHTML = 'Shortcuts: Ctrl+S (Status), Ctrl+N (Notes), Ctrl+P (Payment), ESC (Close)';
        document.body.appendChild(tooltip);
        
        // Hide tooltip after 5 seconds
        setTimeout(() => {
            tooltip.style.opacity = '0';
            setTimeout(() => tooltip.remove(), 300);
        }, 5000);
    </script>
</body>
</html>