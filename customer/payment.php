<?php
// customer/payment.php - Improved version supporting flexible payment

session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

$booking_id = isset($_GET['booking']) ? (int)$_GET['booking'] : 0;
$is_return_payment = isset($_GET['return']) ? true : false;
$auto_redirect = isset($_GET['auto']) ? true : false;

if (!$booking_id) {
    header('Location: bookings.php');
    exit();
}

// Ambil detail booking
$stmt = $db->prepare("SELECT b.*, p.name as package_name, p.price as package_price, u.full_name as customer_name
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id 
                     JOIN users u ON b.user_id = u.id
                     WHERE b.id = ? AND b.user_id = ? AND b.status IN ('pending', 'confirmed', 'paid')");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: bookings.php');
    exit();
}

// Ambil history pembayaran
$stmt = $db->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC");
$stmt->execute([$booking_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total yang sudah dibayar
$total_paid = array_sum(array_column(array_filter($payments, function ($p) {
    return $p['status'] === 'verified';
}), 'amount'));

// Hitung jumlah minimal dan maksimal yang bisa dibayar
$minimum_payment = max(0, $booking['down_payment'] - $total_paid); // DP minimal
$remaining_payment = $booking['total_amount'] - $total_paid;
$fifty_percent_payment = $booking['total_amount'] * 0.5;

// Validasi apakah masih bisa melakukan pembayaran
$can_pay = false;
$error_message = '';

if ($remaining_payment <= 0) {
    $error_message = 'Booking ini sudah lunas.';
} elseif ($booking['status'] === 'cancelled') {
    $error_message = 'Booking ini sudah dibatalkan.';
} elseif ($total_paid > 0 && $total_paid < $booking['down_payment']) {
    $error_message = 'Silakan lunasi DP terlebih dahulu sebelum melakukan pembayaran tambahan.';
} else {
    // Cek batas waktu pelunasan jika sudah bayar DP
    if ($total_paid >= $booking['down_payment']) {
        $event_date = strtotime($booking['usage_date']);
        $today = time();
        $max_payment_date = $event_date + (7 * 24 * 60 * 60);

        if ($today > $event_date && $today > $max_payment_date) {
            $error_message = 'Batas waktu pelunasan sudah habis (maksimal 7 hari setelah acara).';
        } else {
            $can_pay = true;
        }
    } else {
        $can_pay = true;
    }
}

// Handle form submission
$error = '';
$success = '';

if ($_POST) {
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $payment_date = $_POST['payment_date'];
    $notes = trim($_POST['notes']);

    // Validasi
    if (empty($amount) || empty($payment_method) || empty($payment_date)) {
        $error = 'Mohon lengkapi semua field yang wajib!';
    } elseif ($amount <= 0) {
        $error = 'Jumlah pembayaran harus lebih dari 0!';
    } elseif ($amount > $remaining_payment) {
        $error = 'Jumlah pembayaran tidak boleh melebihi sisa tagihan!';
    } elseif ($total_paid == 0 && $amount < $minimum_payment) {
        $error = 'Pembayaran pertama minimal DP 30% (' . formatRupiah($minimum_payment) . ')!';
    } else {
        // Handle file upload
        $payment_proof = '';
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/payments/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];

            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $payment_proof = 'payment_' . time() . '_' . $booking_id . '.' . $file_extension;
                $upload_path = $upload_dir . $payment_proof;

                if (move_uploaded_file($_FILES['payment_proof']['tmp_name'], $upload_path)) {
                    // Insert payment record
                    $stmt = $db->prepare("INSERT INTO payments (booking_id, payment_date, amount, payment_method, payment_proof, notes, status) 
                                        VALUES (?, ?, ?, ?, ?, ?, 'pending')");

                    if ($stmt->execute([$booking_id, $payment_date, $amount, $payment_method, $payment_proof, $notes])) {
                        // Determine payment type for success message
                        $new_total = $total_paid + $amount;
                        if ($new_total >= $booking['total_amount']) {
                            $success = "Pembayaran lunas berhasil disubmit! Admin akan memverifikasi dalam 1x24 jam.";
                        } elseif ($total_paid == 0 && $amount >= $booking['down_payment']) {
                            $success = "Pembayaran DP berhasil disubmit dan menunggu verifikasi admin.";
                        } else {
                            $success = "Pembayaran berhasil disubmit dan menunggu verifikasi admin.";
                        }

                        // TAMBAHAN: Jika ini pembayaran saat return dan baju pengantin
                        if ($is_return_payment && $booking['service_type'] === 'Baju Pengantin') {
                            // Cek apakah pembayaran sudah lunas
                            if ($new_total >= $booking['total_amount']) {
                                // Update booking notes untuk menandai baju dikembalikan dan lunas
                                $current_notes = $booking['notes'] ?? '';
                                $return_date = date('d/m/Y H:i');
                                $return_note = "\n[DRESS_RETURNED] Baju pengantin dikembalikan bersamaan dengan pelunasan pada $return_date.";

                                $stmt = $db->prepare("UPDATE bookings SET notes = CONCAT(COALESCE(notes, ''), ?), status = 'completed', updated_at = NOW() WHERE id = ?");
                                $stmt->execute([$return_note, $booking_id]);

                                $success = "Pembayaran lunas dan pengembalian baju berhasil dicatat! Terima kasih.";
                            } else {
                                $success = "Pembayaran berhasil disubmit. Anda masih bisa melunasi sisa saat mengembalikan baju.";
                            }
                        } else {
                            // Kode success message yang sudah ada
                            if ($new_total >= $booking['total_amount']) {
                                $success = "Pembayaran lunas berhasil disubmit! Admin akan memverifikasi dalam 1x24 jam.";
                            } elseif ($total_paid == 0 && $amount >= $booking['down_payment']) {
                                $success = "Pembayaran DP berhasil disubmit dan menunggu verifikasi admin.";
                            } else {
                                $success = "Pembayaran berhasil disubmit dan menunggu verifikasi admin.";
                            }
                        }

                        // Refresh payment data - PERBAIKAN: Query yang benar
                        $stmt = $db->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY created_at DESC");
                        $stmt->execute([$booking_id]);
                        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // Hitung ulang total yang sudah dibayar
                        $total_paid = array_sum(array_column(array_filter($payments, function ($p) {
                            return $p['status'] === 'verified';
                        }), 'amount'));
                        $remaining_payment = $booking['total_amount'] - $total_paid;
                    } else {
                        $error = 'Terjadi kesalahan saat menyimpan data pembayaran!';
                        unlink($upload_path);
                    }
                } else {
                    $error = 'Gagal mengupload bukti pembayaran!';
                }
            } else {
                $error = 'Format file tidak didukung! Gunakan JPG, PNG, atau PDF.';
            }
        } else {
            $error = 'Mohon upload bukti pembayaran!';
        }
    }
}

// Function untuk menentukan jenis pembayaran
function getPaymentTypeLabel($amount, $booking, $total_paid_before)
{
    $total_after = $total_paid_before + $amount;

    if ($total_after >= $booking['total_amount']) {
        return 'Lunas';
    } elseif ($total_paid_before == 0 && $amount >= $booking['down_payment']) {
        if ($amount >= $booking['total_amount'] * 0.5) {
            return 'Pembayaran 50%+';
        } else {
            return 'DP (Down Payment)';
        }
    } elseif ($total_paid_before >= $booking['down_payment'] && $total_after < $booking['total_amount']) {
        return 'Pelunasan Sebagian';
    } else {
        return 'Cicilan';
    }
}

// Update logic untuk admin - pembayaran lebih fleksibel
$payment_suggestions = [];

if ($total_paid == 0) {
    // Belum ada pembayaran - admin bisa terima pembayaran apapun
    $payment_suggestions[] = [
        'amount' => $fifty_percent_payment,
        'label' => '50%',
        'description' => 'Bayar setengah'
    ];

    $payment_suggestions[] = [
        'amount' => $booking['total_amount'],
        'label' => 'Lunas',
        'description' => 'Bayar semua'
    ];

    // Tambahkan DP jika berbeda dengan 50%
    if ($booking['down_payment'] != $fifty_percent_payment) {
        array_unshift($payment_suggestions, [
            'amount' => $booking['down_payment'],
            'label' => 'DP 30%',
            'description' => 'Down payment'
        ]);
    }
} else {
    // Sudah ada pembayaran - sisa apapun bisa dibayar
    if ($remaining_payment > 0) {
        $payment_suggestions[] = [
            'amount' => $remaining_payment,
            'label' => 'Lunas',
            'description' => 'Bayar sisa'
        ];

        // Tambahkan opsi pembayaran sebagian jika sisa > 500k
        if ($remaining_payment > 500000) {
            $payment_suggestions[] = [
                'amount' => 500000,
                'label' => '500K',
                'description' => 'Cicilan'
            ];

            if ($remaining_payment > 1000000) {
                $payment_suggestions[] = [
                    'amount' => 1000000,
                    'label' => '1 Juta',
                    'description' => 'Cicilan'
                ];
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Booking - Dandy Gallery</title>
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

        .main-content {
            max-width: 1000px;
            margin: 2rem auto;
            padding: 0 2rem;
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

        .payment-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .booking-code {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .booking-package {
            opacity: 0.9;
            margin-bottom: 1rem;
        }

        .payment-amount {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .amount-item {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .amount-value {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }

        .amount-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .payment-grid {
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .quick-amount-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            flex-wrap: wrap;
        }

        .quick-btn {
            padding: 0.5rem 0.75rem;
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.3s;
            text-align: center;
            min-width: 70px;
            font-weight: 500;
        }

        .quick-btn:hover {
            background: #ff6b6b;
            color: white;
            border-color: #ff6b6b;
            transform: translateY(-1px);
        }

        .quick-btn.primary {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }

        .quick-btn.primary:hover {
            background: #0056b3;
            border-color: #0056b3;
        }

        .quick-btn.success {
            background: #28a745;
            color: white;
            border-color: #28a745;
        }

        .quick-btn.success:hover {
            background: #1e7e34;
            border-color: #1e7e34;
        }

        .payment-info {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            color: #1565c0;
        }

        .payment-info.warning {
            background: #fff3cd;
            border-color: #ffecb5;
            color: #856404;
        }

        .payment-info.success {
            background: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .bank-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .bank-info h4 {
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bank-list {
            display: grid;
            gap: 1rem;
        }

        .bank-item {
            background: white;
            padding: 1rem;
            border-radius: 8px;
            border-left: 4px solid #ff6b6b;
        }

        .bank-name {
            font-weight: bold;
            color: #333;
        }

        .bank-account {
            color: #666;
            font-family: 'Courier New', monospace;
            margin: 0.25rem 0;
        }

        .bank-holder {
            color: #666;
            font-size: 0.9rem;
        }

        .instructions {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            padding: 1.5rem;
            border-radius: 10px;
            color: #1565c0;
        }

        .instructions h4 {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .instructions ol {
            margin-left: 1.5rem;
        }

        .instructions li {
            margin-bottom: 0.5rem;
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

        .alert.info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px dashed #e1e5e9;
            border-radius: 8px;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            color: #666;
        }

        .file-input:hover {
            border-color: #ff6b6b;
            background: #fff;
        }

        .file-input input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .payment-history {
            margin-top: 2rem;
        }

        .payment-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #ff6b6b;
        }

        .payment-card h5 {
            color: #333;
            margin-bottom: 0.5rem;
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

        @media (max-width: 768px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }

            .payment-amount {
                grid-template-columns: 1fr;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .quick-amount-buttons {
                justify-content: center;
            }

            .quick-btn {
                min-width: 100px;
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Pembayaran Booking</h1>
            <a href="booking_detail.php?id=<?php echo $booking_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Kembali ke Detail
            </a>
        </div>

        <?php if ($auto_redirect): ?>
            <div class="alert info">
                <i class="fas fa-info-circle"></i>
                Silakan lakukan pembayaran untuk booking Anda. Anda bisa memilih DP 30%, 50%, atau lunas sekaligus.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Payment Header -->
        <div class="payment-header">
            <div class="booking-code">
                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($booking['booking_code']); ?>
            </div>
            <div class="booking-package"><?php echo htmlspecialchars($booking['package_name']); ?></div>

            <?php if ($is_return_payment): ?>
                <div
                    style="background: rgba(255, 255, 255, 0.2); padding: 0.5rem 1rem; border-radius: 20px; margin-top: 1rem; font-size: 0.9rem;">
                    <i class="fas fa-undo"></i> Pembayaran saat Pengembalian Baju
                </div>
            <?php endif; ?>

            <div class="payment-amount">
                <div class="amount-item">
                    <div class="amount-value"><?php echo formatRupiah($booking['total_amount']); ?></div>
                    <div class="amount-label">Total Tagihan</div>
                </div>
                <div class="amount-item">
                    <div class="amount-value"><?php echo formatRupiah($total_paid); ?></div>
                    <div class="amount-label">Sudah Dibayar</div>
                </div>
                <div class="amount-item">
                    <div class="amount-value"><?php echo formatRupiah($remaining_payment); ?></div>
                    <div class="amount-label">Sisa Tagihan</div>
                </div>
            </div>
        </div>

        <?php if (!$can_pay): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-exclamation-triangle"
                        style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                    <h3>Tidak Dapat Melakukan Pembayaran</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        <?php echo $error_message; ?>
                    </p>
                    <a href="booking_detail.php?id=<?php echo $booking_id; ?>" class="btn">
                        <i class="fas fa-arrow-left"></i> Kembali ke Detail Booking
                    </a>
                </div>
            </div>
        <?php elseif ($remaining_payment <= 0): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745; margin-bottom: 1rem;"></i>
                    <h3>Pembayaran Sudah Lunas</h3>
                    <p style="color: #666; margin-bottom: 2rem;">
                        Semua pembayaran untuk booking ini telah lunas.
                    </p>
                    <a href="booking_detail.php?id=<?php echo $booking_id; ?>" class="btn">
                        <i class="fas fa-eye"></i> Lihat Detail Booking
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Payment Form -->
            <div class="payment-grid">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-credit-card"></i>
                        <h3>Form Pembayaran</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($total_paid == 0): ?>
                            <div class="payment-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Pembayaran Pertama:</strong> Pilih jumlah pembayaran yang sesuai dengan kemampuan Anda.
                                Bisa 50% atau langsung lunas sekaligus.
                            </div>
                        <?php elseif ($total_paid < $booking['down_payment']): ?>
                            <div class="payment-info warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Perhatian:</strong> Anda perlu melunasi DP terlebih dahulu sebelum melakukan pembayaran
                                lainnya.
                            </div>
                        <?php else: ?>
                            <div class="payment-info success">
                                <i class="fas fa-check-circle"></i>
                                <strong>DP Sudah Dibayar:</strong> Anda bisa melakukan pelunasan kapan saja sebelum atau sesudah
                                acara
                                (maksimal 7 hari setelah acara).
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="amount">Jumlah Pembayaran <span style="color: #ff6b6b;">*</span></label>
                                    <input type="number" id="amount" name="amount" step="0.01" required
                                        min="<?php echo $total_paid == 0 ? $minimum_payment : 1; ?>"
                                        max="<?php echo $remaining_payment; ?>" placeholder="Masukkan jumlah pembayaran">

                                    <div class="quick-amount-buttons">
                                        <?php foreach ($payment_suggestions as $suggestion): ?>
                                            <button type="button"
                                                class="quick-btn <?php echo $suggestion['amount'] == $booking['total_amount'] ? 'success' : ($suggestion['amount'] == $fifty_percent_payment ? 'primary' : ''); ?>"
                                                onclick="setAmount(<?php echo $suggestion['amount']; ?>)"
                                                title="<?php echo $suggestion['description'] . ' - ' . formatRupiah($suggestion['amount']); ?>">
                                                <?php echo $suggestion['label']; ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>

                                    <div style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                                        <?php if ($total_paid == 0): ?>
                                            Minimal: <?php echo formatRupiah($minimum_payment); ?> | Maksimal:
                                            <?php echo formatRupiah($remaining_payment); ?>
                                        <?php else: ?>
                                            Maksimal: <?php echo formatRupiah($remaining_payment); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="payment_date">Tanggal Pembayaran <span
                                            style="color: #ff6b6b;">*</span></label>
                                    <input type="date" id="payment_date" name="payment_date" required
                                        value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">Metode Pembayaran <span style="color: #ff6b6b;">*</span></label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">Pilih Metode Pembayaran</option>
                                    <option value="transfer">Transfer Bank</option>
                                    <option value="cash">Cash/Tunai</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="payment_proof">Bukti Pembayaran <span style="color: #ff6b6b;">*</span></label>
                                <div class="file-upload">
                                    <div class="file-input">
                                        <input type="file" id="payment_proof" name="payment_proof"
                                            accept=".jpg,.jpeg,.png,.pdf" required>
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <span>Klik untuk upload bukti pembayaran</span>
                                        <small style="display: block; margin-top: 0.5rem;">
                                            Format: JPG, PNG, PDF (Max: 5MB)
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="notes">Catatan</label>
                                <textarea id="notes" name="notes" placeholder="Catatan tambahan (opsional)"></textarea>
                            </div>

                            <button type="submit" class="btn" style="width: 100%;">
                                <i class="fas fa-paper-plane"></i> Submit Pembayaran
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Payment Info -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i>
                        <h3>Informasi Pembayaran</h3>
                    </div>
                    <div class="card-body">
                        <div class="bank-info">
                            <h4><i class="fas fa-university"></i> Rekening Pembayaran</h4>
                            <div class="bank-list">
                                <div class="bank-item">
                                    <div class="bank-name">Bank BCA</div>
                                    <div class="bank-account">1234567890</div>
                                    <div class="bank-holder">PT Dandy Gallery Gown</div>
                                </div>
                                <div class="bank-item">
                                    <div class="bank-name">Bank Mandiri</div>
                                    <div class="bank-account">9876543210</div>
                                    <div class="bank-holder">PT Dandy Gallery Gown</div>
                                </div>
                                <div class="bank-item">
                                    <div class="bank-name">Bank BRI</div>
                                    <div class="bank-account">5555666677</div>
                                    <div class="bank-holder">PT Dandy Gallery Gown</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment History -->
        <?php if (!empty($payments)): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3>Riwayat Pembayaran</h3>
                </div>
                <div class="card-body">
                    <?php
                    $running_total = 0;
                    foreach ($payments as $payment):
                        if ($payment['status'] === 'verified') {
                            $running_total += $payment['amount'];
                        }
                        $payment_type_label = getPaymentTypeLabel($payment['amount'], $booking, $running_total - $payment['amount']);
                    ?>
                        <div class="payment-card">
                            <h5>
                                Pembayaran <?php echo formatRupiah($payment['amount']); ?>
                                <small style="color: #666; font-weight: normal;"> - <?php echo $payment_type_label; ?></small>
                            </h5>
                            <div class="payment-details">
                                <div>
                                    <strong>Tanggal:</strong><br>
                                    <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                                </div>
                                <div>
                                    <strong>Metode:</strong><br>
                                    <?php echo ucfirst($payment['payment_method']); ?>
                                </div>
                                <div>
                                    <strong>Status:</strong><br>
                                    <span class="status <?php echo $payment['status']; ?>">
                                        <?php echo getPaymentStatusText($payment['status']); ?>
                                    </span>
                                </div>
                                <div>
                                    <strong>Dibuat:</strong><br>
                                    <?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?>
                                </div>
                                <?php if ($payment['notes']): ?>
                                    <div style="grid-column: 1/-1;">
                                        <strong>Catatan:</strong><br>
                                        <?php echo htmlspecialchars($payment['notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function setAmount(amount) {
            document.getElementById('amount').value = amount;

            // Update button appearance
            const buttons = document.querySelectorAll('.quick-btn');
            buttons.forEach(btn => btn.style.transform = 'scale(1)');

            // Highlight selected button
            event.target.style.transform = 'scale(1.05)';
            setTimeout(() => {
                event.target.style.transform = 'scale(1)';
            }, 200);
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

        // File upload preview
        document.getElementById('payment_proof').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const fileInput = document.querySelector('.file-input span');

            if (file) {
                fileInput.textContent = `File terpilih: ${file.name}`;
            } else {
                fileInput.textContent = 'Klik untuk upload bukti pembayaran';
            }
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            const maxAmount = <?php echo $remaining_payment; ?>;
            const minAmount = <?php echo $total_paid == 0 ? $minimum_payment : 1; ?>;
            const isReturnPayment = <?php echo $is_return_payment ? 'true' : 'false'; ?>;

            if (amount <= 0) {
                e.preventDefault();
                alert('Jumlah pembayaran harus lebih dari 0!');
                return;
            }

            if (amount > maxAmount) {
                e.preventDefault();
                alert('Jumlah pembayaran tidak boleh melebihi sisa tagihan!');
                return;
            }

            if (amount < minAmount) {
                e.preventDefault();
                alert('Jumlah pembayaran minimal ' + formatRupiah(minAmount) + '!');
                return;
            }

            // Determine payment type for confirmation
            let confirmMessage = '';
            const totalAmount = <?php echo $booking['total_amount']; ?>;
            const totalPaid = <?php echo $total_paid; ?>;
            const newTotal = totalPaid + amount;

            if (isReturnPayment) {
                if (newTotal >= totalAmount) {
                    confirmMessage =
                        `Anda akan melakukan pelunasan sebesar ${formatRupiah(amount)} sekaligus mengembalikan baju pengantin. Apakah Anda yakin?`;
                } else {
                    confirmMessage =
                        `Anda akan membayar ${formatRupiah(amount)} saat pengembalian baju. Apakah Anda yakin?`;
                }
            } else {
                // Konfirmasi message yang sudah ada
                let paymentTypeText = '';
                if (newTotal >= totalAmount) {
                    paymentTypeText = 'pembayaran lunas';
                } else if (totalPaid == 0 && amount >= <?php echo $booking['down_payment']; ?>) {
                    paymentTypeText = 'pembayaran DP';
                } else {
                    paymentTypeText = 'pembayaran cicilan';
                }
                confirmMessage =
                    `Anda akan melakukan ${paymentTypeText} sebesar ${formatRupiah(amount)}. Apakah Anda yakin?`;
            }

            if (!confirm(confirmMessage)) {
                e.preventDefault();
            }
        });

        function formatRupiah(angka) {
            return 'Rp ' + angka.toLocaleString('id-ID');
        }

        // Set maximum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('payment_date').setAttribute('max', today);
        });

        // Input amount validation
        document.getElementById('amount').addEventListener('input', function(e) {
            const amount = parseFloat(e.target.value);
            const maxAmount = <?php echo $remaining_payment; ?>;
            const minAmount = <?php echo $total_paid == 0 ? $minimum_payment : 1; ?>;

            if (amount > maxAmount) {
                e.target.value = maxAmount;
            }

            if (amount < 0) {
                e.target.value = 0;
            }
        });
    </script>
</body>

</html>