<!-- admin/payments.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

$success = '';
$error = '';

// Handle payment status update
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_payment_status') {
    $payment_id = (int)$_POST['payment_id'];
    $new_status = $_POST['status'];
    $notes = trim($_POST['notes']);
    
    $stmt = $db->prepare("UPDATE payments SET status = ?, notes = ? WHERE id = ?");
    if ($stmt->execute([$new_status, $notes, $payment_id])) {
        $success = "Status pembayaran berhasil diupdate!";
        
        // Update status booking berdasarkan total pembayaran
        if ($new_status === 'verified') {
            $stmt = $db->prepare("SELECT booking_id FROM payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($payment) {
                // Hitung total pembayaran yang sudah verified untuk booking ini
                $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
                $stmt->execute([$payment['booking_id']]);
                $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;
                
                // Ambil data booking
                $stmt = $db->prepare("SELECT total_amount, down_payment, status FROM bookings WHERE id = ?");
                $stmt->execute([$payment['booking_id']]);
                $booking_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($booking_data) {
                    $new_booking_status = $booking_data['status'];
                    
                    // Update status booking berdasarkan pembayaran
                    if ($total_paid >= $booking_data['total_amount']) {
                        // Jika sudah lunas
                        $new_booking_status = 'paid'; // Status paid untuk yang sudah lunas
                    } elseif ($total_paid >= $booking_data['down_payment'] && $booking_data['status'] === 'confirmed') {
                        // Jika DP sudah dibayar
                        $new_booking_status = 'paid'; // Status paid untuk yang sudah bayar DP
                    }
                    
                    if ($new_booking_status !== $booking_data['status']) {
                        $stmt = $db->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                        $stmt->execute([$new_booking_status, $payment['booking_id']]);
                    }
                }
            }
        }
    } else {
        $error = "Gagal mengupdate status pembayaran!";
    }
}

// Filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query payments dengan filter - DIPERBAIKI: menggunakan users alih-alih customers
$sql = "SELECT p.*, b.booking_code, b.total_amount as booking_total, b.usage_date,
        u.full_name as customer_name, pk.name as package_name
        FROM payments p 
        JOIN bookings b ON p.booking_id = b.id 
        JOIN users u ON b.user_id = u.id 
        JOIN packages pk ON b.package_id = pk.id
        WHERE 1=1";

$params = [];

if (!empty($status_filter)) {
    $sql .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $sql .= " AND (b.booking_code LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistik pembayaran
$stats = [
    'total_payments' => count($payments),
    'pending_payments' => count(array_filter($payments, function($p) { return $p['status'] === 'pending'; })),
    'verified_payments' => count(array_filter($payments, function($p) { return $p['status'] === 'verified'; })),
    'total_amount' => array_sum(array_column(array_filter($payments, function($p) { return $p['status'] === 'verified'; }), 'amount'))
];


// Function untuk menentukan jenis pembayaran
function getPaymentType($booking_id, $amount, $db) {
    $stmt = $db->prepare("SELECT b.down_payment, b.total_amount, 
                         COALESCE(SUM(p.amount), 0) as total_paid_before
                         FROM bookings b
                         LEFT JOIN payments p ON b.id = p.booking_id AND p.status = 'verified'
                         WHERE b.id = ?
                         GROUP BY b.id");
    $stmt->execute([$booking_id]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$data) return 'Unknown';
    
    $total_after = $data['total_paid_before'] + $amount;
    
    if ($data['total_paid_before'] < $data['down_payment'] && $total_after >= $data['down_payment']) {
        return 'DP';
    } elseif ($data['total_paid_before'] >= $data['down_payment'] && $total_after >= $data['total_amount']) {
        return 'Pelunasan';
    } elseif ($total_after >= $data['total_amount']) {
        return 'Lunas';
    } else {
        return 'Cicilan';
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pembayaran - Dandy Gallery Admin</title>
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
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
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

    .stat-icon.verified {
        background: linear-gradient(45deg, #2ecc71, #27ae60);
    }

    .stat-icon.amount {
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

    .filter-section {
        background: white;
        padding: 1.5rem 2rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
    }

    .filter-form {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
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

    .btn-danger {
        background: #dc3545;
    }

    .btn-sm {
        padding: 5px 10px;
        font-size: 0.8rem;
    }

    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        overflow: hidden;
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

    .payments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        padding: 2rem;
    }

    .payment-card {
        background: white;
        border: 1px solid #eee;
        border-radius: 12px;
        padding: 1.5rem;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .payment-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }

    .payment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .payment-code {
        font-weight: bold;
        color: #333;
    }

    .payment-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }

    .payment-status.pending {
        background: #fff3cd;
        color: #856404;
    }

    .payment-status.verified {
        background: #d4edda;
        color: #155724;
    }

    .payment-status.rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .payment-info {
        margin-bottom: 1rem;
    }

    .info-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .info-label {
        color: #666;
    }

    .info-value {
        color: #333;
        font-weight: 500;
    }

    .payment-amount {
        font-size: 1.3rem;
        font-weight: bold;
        color: #ff6b6b;
        margin-bottom: 1rem;
    }

    .payment-actions {
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

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .payments-grid {
            grid-template-columns: 1fr;
        }

        .filter-form {
            grid-template-columns: 1fr;
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
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Kelola Pembayaran</h1>
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

        <!-- Statistics Cards -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['total_payments']; ?></div>
                <div class="stat-label">Total Pembayaran</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number"><?php echo $stats['pending_payments']; ?></div>
                <div class="stat-label">Menunggu Verifikasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon verified">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo $stats['verified_payments']; ?></div>
                <div class="stat-label">Terverifikasi</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amount">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo formatRupiah($stats['total_amount']); ?></div>
                <div class="stat-label">Total Diterima</div>
            </div>
        </div> -->

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Cari Pembayaran</label>
                    <input type="text" id="search" name="search" placeholder="Kode booking, nama customer..."
                        value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">Semua Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Menunggu
                            Verifikasi</option>
                        <option value="verified" <?php echo $status_filter === 'verified' ? 'selected' : ''; ?>>
                            Terverifikasi</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Ditolak
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Filter
                </button>

                <a href="payments.php" class="btn btn-secondary">
                    <i class="fas fa-refresh"></i> Reset
                </a>
            </form>
        </div>

        <!-- Payments Grid -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-money-bill"></i> Daftar Pembayaran</h3>
                <span><?php echo count($payments); ?> pembayaran ditemukan</span>
            </div>

            <?php if (empty($payments)): ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <i class="fas fa-receipt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <h3>Tidak Ada Pembayaran</h3>
                <p>Belum ada pembayaran yang sesuai dengan filter yang dipilih.</p>
            </div>
            <?php else: ?>
            <div class="payments-grid">
                <?php foreach ($payments as $payment): ?>
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="payment-code">
                            <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($payment['booking_code']); ?>
                        </div>
                        <span class="payment-status <?php echo $payment['status']; ?>">
                            <?php 
            $status_indonesia = [
                'pending' => 'Menunggu Verifikasi',
                'verified' => 'Terverifikasi', 
                'rejected' => 'Ditolak'
            ];
            echo $status_indonesia[$payment['status']] ?? ucfirst($payment['status']);
            ?>
                        </span>
                    </div>


                    <div class="payment-amount">
                        <?php echo formatRupiah($payment['amount']); ?>
                        <small style="display: block; color: #666; margin-top: 0.25rem;">
                            (<?php echo getPaymentType($payment['booking_id'], $payment['amount'], $db); ?>)
                        </small>
                    </div>

                    <div class="payment-info">
                        <div class="info-row">
                            <span class="info-label">Customer:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['customer_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Paket:</span>
                            <span class="info-value"><?php echo htmlspecialchars($payment['package_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Metode:</span>
                            <span class="info-value"><?php echo ucfirst($payment['payment_method']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Bayar:</span>
                            <span
                                class="info-value"><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Tanggal Acara:</span>
                            <span
                                class="info-value"><?php echo date('d/m/Y', strtotime($payment['usage_date'])); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Total Booking:</span>
                            <span class="info-value"><?php echo formatRupiah($payment['booking_total']); ?></span>
                        </div>
                    </div>

                    <?php if ($payment['notes']): ?>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                        <strong>Catatan:</strong><br>
                        <small><?php echo htmlspecialchars($payment['notes']); ?></small>
                    </div>
                    <?php endif; ?>

                    <div class="payment-actions">
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
                        <button type="button" class="btn btn-sm"
                            onclick="viewPaymentProof('<?php echo htmlspecialchars($payment['payment_proof']); ?>')">
                            <i class="fas fa-image"></i> Bukti
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Update Payment Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Status Pembayaran</h3>
                <span class="close" onclick="closeStatusModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" id="statusForm">
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="payment_id" id="modalPaymentId">
                    <input type="hidden" name="status" id="modalStatus">

                    <div class="form-group">
                        <label for="modalNotes">Catatan (opsional)</label>
                        <textarea id="modalNotes" name="notes" rows="3"
                            placeholder="Berikan catatan untuk customer..."></textarea>
                    </div>

                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn" id="submitStatusBtn">
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
    function updatePaymentStatus(paymentId, status) {
        document.getElementById('modalPaymentId').value = paymentId;
        document.getElementById('modalStatus').value = status;

        const submitBtn = document.getElementById('submitStatusBtn');
        if (status === 'verified') {
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Verifikasi';
            submitBtn.className = 'btn btn-success';
        } else {
            submitBtn.innerHTML = '<i class="fas fa-times"></i> Tolak';
            submitBtn.className = 'btn btn-danger';
        }

        document.getElementById('statusModal').style.display = 'block';
    }

    function closeStatusModal() {
        document.getElementById('statusModal').style.display = 'none';
        document.getElementById('modalNotes').value = '';
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

    // Close modals when clicking outside
    window.onclick = function(event) {
        const statusModal = document.getElementById('statusModal');
        const proofModal = document.getElementById('proofModal');

        if (event.target === statusModal) {
            closeStatusModal();
        }
        if (event.target === proofModal) {
            closeProofModal();
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
    </script>
</body>

</html>