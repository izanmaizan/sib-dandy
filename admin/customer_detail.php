<!-- admin/customer_detail.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$customer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$customer_id) {
    echo '<div style="text-align: center; padding: 2rem; color: #dc3545;">ID customer tidak valid</div>';
    exit;
}

$db = getDB();

// Get customer detail with user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    echo '<div style="text-align: center; padding: 2rem; color: #dc3545;">Customer tidak ditemukan</div>';
    exit;
}

// Get customer bookings with package info
$stmt = $db->prepare("SELECT b.*, p.name as package_name, p.price as package_price, p.service_type as service_name 
                     FROM bookings b 
                     JOIN packages p ON b.package_id = p.id 
                     WHERE b.user_id = ? 
                     ORDER BY b.created_at DESC");
$stmt->execute([$customer_id]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment history
$stmt = $db->prepare("SELECT pay.*, b.booking_code 
                     FROM payments pay 
                     JOIN bookings b ON pay.booking_id = b.id 
                     WHERE b.user_id = ? 
                     ORDER BY pay.created_at DESC");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_bookings = count($bookings);
$completed_bookings = count(array_filter($bookings, function($b) { return $b['status'] === 'completed'; }));
$total_spent = array_sum(array_column(array_filter($bookings, function($b) { return in_array($b['status'], ['paid', 'completed']); }), 'total_amount'));
$pending_payments = array_sum(array_column(array_filter($payments, function($p) { return $p['status'] === 'pending'; }), 'amount'));
?>

<style>
    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .detail-section {
        background: #f8f9fa;
        padding: 1.5rem;
        border-radius: 10px;
    }

    .detail-section h4 {
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
        padding: 0.5rem 0;
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
    }

    .stats-mini {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .stat-mini {
        text-align: center;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .stat-mini-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: #ff6b6b;
    }

    .stat-mini-label {
        font-size: 0.8rem;
        color: #666;
        margin-top: 0.25rem;
    }

    .table-responsive {
        overflow-x: auto;
        margin-top: 1rem;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
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

    .table tbody tr:hover {
        background: #f8f9fa;
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

    .status.verified {
        background: #d4edda;
        color: #155724;
    }

    .status.rejected {
        background: #f8d7da;
        color: #721c24;
    }

    .wedding-highlight {
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        padding: 1rem;
        border-radius: 10px;
        text-align: center;
        margin-bottom: 1.5rem;
    }

    .wedding-date {
        font-size: 1.2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .tab-buttons {
        display: flex;
        margin-bottom: 1rem;
        border-bottom: 1px solid #eee;
    }

    .tab-button {
        padding: 0.75rem 1.5rem;
        background: none;
        border: none;
        cursor: pointer;
        color: #666;
        font-weight: 500;
        border-bottom: 2px solid transparent;
        transition: all 0.3s;
    }

    .tab-button.active {
        color: #ff6b6b;
        border-bottom-color: #ff6b6b;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    @media (max-width: 768px) {
        .detail-grid {
            grid-template-columns: 1fr;
        }
        
        .stats-mini {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="customer-detail-content">
    <!-- Customer Header -->
    <div class="wedding-highlight">
        <div class="wedding-date">
            <i class="fas fa-user"></i> 
            <?php echo htmlspecialchars($customer['full_name']); ?>
        </div>
        <div>Customer ID: #<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></div>
    </div>

    <!-- Statistics Mini -->
    <!-- <div class="stats-mini">
        <div class="stat-mini">
            <div class="stat-mini-number"><?php echo $total_bookings; ?></div>
            <div class="stat-mini-label">Total Booking</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-number"><?php echo $completed_bookings; ?></div>
            <div class="stat-mini-label">Completed</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-number"><?php echo formatRupiah($total_spent); ?></div>
            <div class="stat-mini-label">Total Spent</div>
        </div>
        <div class="stat-mini">
            <div class="stat-mini-number"><?php echo formatRupiah($pending_payments); ?></div>
            <div class="stat-mini-label">Pending Payment</div>
        </div>
    </div> -->

    <!-- Customer Information -->
     
    <div class="detail-grid">
        <div class="detail-section">
            <h4><i class="fas fa-user"></i> Informasi Personal</h4>
            <div class="info-item">
                <span class="info-label">Nama Lengkap:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['full_name']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Username:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['username']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Email:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['email']); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Telepon:</span>
                <span class="info-value"><?php echo htmlspecialchars($customer['phone'] ?: '-'); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Role:</span>
                <span class="info-value"><?php echo ucfirst($customer['role']); ?></span>
            </div>
        </div>

        <div class="detail-section">
            <h4><i class="fas fa-info-circle"></i> Informasi Akun</h4>
            <div class="info-item">
                <span class="info-label">User ID:</span>
                <span class="info-value">#<?php echo str_pad($customer['id'], 4, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Bergabung:</span>
                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($customer['created_at'])); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Terakhir Update:</span>
                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($customer['updated_at'])); ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Booking:</span>
                <span class="info-value"><?php echo $total_bookings; ?> booking</span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-buttons">
        <button class="tab-button active" onclick="showTab('bookings')">
            <i class="fas fa-calendar-check"></i> Riwayat Booking (<?php echo count($bookings); ?>)
        </button>
        <button class="tab-button" onclick="showTab('payments')">
            <i class="fas fa-money-bill"></i> Riwayat Pembayaran (<?php echo count($payments); ?>)
        </button>
    </div>

    <!-- Bookings Tab -->
     <div id="bookings-tab" class="tab-content active">
        <?php if (empty($bookings)): ?>
            <div style="text-align: center; padding: 2rem; color: #666;">
                <i class="fas fa-calendar-times" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Belum ada booking</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Paket</th>
                            <th>Layanan</th>
                            <th>Tanggal Acara</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                            <td>
                                <?php echo htmlspecialchars($booking['package_name']); ?><br>
                                <small style="color: #666;"><?php echo formatRupiah($booking['package_price']); ?></small>
                            </td>
<?php $service_icon = getServiceIcon($booking['service_name']); ?>
<td>
    <span class="service-badge <?php echo strtolower($booking['service_name']) === 'baju pengantin' ? 'dress' : 'makeup'; ?>">
        <i class="<?php echo $service_icon; ?>"></i>
        <?php echo htmlspecialchars($booking['service_name']); ?>
    </span>
</td>
                            <td>
                                <?php echo date('d/m/Y', strtotime($booking['usage_date'])); ?><br>
                                <small><?php echo date('H:i', strtotime($booking['usage_time'])); ?></small>
                            </td>
                            <td><strong><?php echo formatRupiah($booking['total_amount']); ?></strong></td>
                            <td>
                                <span class="status <?php echo $booking['status']; ?>">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Payments Tab -->
    <div id="payments-tab" class="tab-content">
        <?php if (empty($payments)): ?>
            <div style="text-align: center; padding: 2rem; color: #666;">
                <i class="fas fa-money-bill-wave" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                <p>Belum ada pembayaran</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Booking</th>
                            <th>Tanggal Bayar</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($payment['booking_code']); ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                            <td><strong><?php echo formatRupiah($payment['amount']); ?></strong></td>
                            <td><?php echo ucfirst($payment['payment_method']); ?></td>
                            <td>
                                <span class="status <?php echo $payment['status']; ?>">
                                    <?php echo ucfirst($payment['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    document.querySelectorAll('.tab-button').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked button
    event.target.classList.add('active');
}
</script>