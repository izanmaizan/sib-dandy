<!-- admin/customer.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

$success = '';
$error = '';

// Handle user actions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'toggle_status':
            $user_id = (int)$_POST['user_id'];
            $current_role = $_POST['current_role'];
            
            // Toggle antara customer dan admin (atau bisa disable user)
            // Untuk keamanan, hanya bisa mengubah role customer
            if ($current_role === 'customer') {
                $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE id = ? AND role = 'customer'");
                if ($stmt->execute([$user_id])) {
                    $success = "User berhasil dipromosikan menjadi admin!";
                } else {
                    $error = "Gagal mengubah role user!";
                }
            }
            break;
            
        case 'delete_user':
            $user_id = (int)$_POST['user_id'];
            
            // Cek apakah user memiliki booking
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($booking_count > 0) {
                $error = "Tidak dapat menghapus user yang memiliki booking!";
            } elseif ($user_id == $_SESSION['user_id']) {
                $error = "Tidak dapat menghapus akun sendiri!";
            } else {
                $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
                if ($stmt->execute([$user_id])) {
                    $success = "User berhasil dihapus!";
                } else {
                    $error = "Gagal menghapus user!";
                }
            }
            break;
    }
}

// Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Query users dengan filter dan statistik booking
$sql = "SELECT u.*, 
        COUNT(b.id) as total_bookings,
        SUM(CASE WHEN b.status IN ('paid', 'completed') THEN b.total_amount ELSE 0 END) as total_spent,
        MAX(b.created_at) as last_booking
        FROM users u 
        LEFT JOIN bookings b ON u.id = b.user_id
        WHERE 1=1";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($role_filter)) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

$sql .= " GROUP BY u.id ORDER BY u.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$total_users = count($users);
$customer_count = count(array_filter($users, function($u) { return $u['role'] === 'customer'; }));
$admin_count = count(array_filter($users, function($u) { return $u['role'] === 'admin'; }));
$active_users = count(array_filter($users, function($u) { return $u['total_bookings'] > 0; }));
$total_revenue = array_sum(array_column($users, 'total_spent'));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Akun User - Dandy Gallery Admin</title>
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

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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

        .stat-icon.total {
            background: linear-gradient(45deg, #3498db, #2980b9);
        }

        .stat-icon.customers {
            background: linear-gradient(45deg, #2ecc71, #27ae60);
        }

        .stat-icon.admins {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }

        .stat-icon.active {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .stat-icon.revenue {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #ff6b6b;
        }

        .btn {
            padding: 12px 20px;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .user-card {
            background: white;
            border: 1px solid #eee;
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .user-info {
            flex: 1;
        }

        .user-info h3 {
            color: #333;
            margin-bottom: 0.25rem;
        }

        .user-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .role-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .role-badge.admin {
            background: #e7f3ff;
            color: #0066cc;
        }

        .role-badge.customer {
            background: #f0f9ff;
            color: #0891b2;
        }

        .user-details {
            margin-bottom: 1rem;
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

        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 0.5rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1rem;
            font-weight: bold;
            color: #ff6b6b;
        }

        .stat-text {
            font-size: 0.7rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            margin: 10% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .users-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .user-stats {
                grid-template-columns: 1fr 1fr;
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
            <li><a href="customers.php" class="active"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Kelola Akun User</h1>
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

        <!-- Statistics Summary -->
        <!-- <div class="stats-summary">
            <div class="stat-card">
                <div class="stat-icon total">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total User</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon customers">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-number"><?php echo $customer_count; ?></div>
                <div class="stat-label">Customer</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon admins">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-number"><?php echo $admin_count; ?></div>
                <div class="stat-label">Admin</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo $active_users; ?></div>
                <div class="stat-label">User Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-number"><?php echo formatRupiah($total_revenue); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div> -->

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="search">Cari User</label>
                    <input type="text" id="search" name="search" placeholder="Nama, email, username, telepon..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role">
                        <option value="">Semua Role</option>
                        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-search"></i> Cari
                </button>
                
                <?php if (!empty($search) || !empty($role_filter)): ?>
                    <a href="customers.php" class="btn btn-secondary">
                        <i class="fas fa-refresh"></i> Reset
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Users Grid -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-users"></i> Daftar User</h3>
                <span><?php echo count($users); ?> user ditemukan</span>
            </div>
            
            <?php if (empty($users)): ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3>Tidak Ada User</h3>
                    <p>Belum ada user yang sesuai dengan filter yang dipilih.</p>
                </div>
            <?php else: ?>
                <div class="users-grid">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card">
                            <div class="user-header">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                                </div>
                                <span class="role-badge <?php echo $user['role']; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </div>

                            <div class="user-details">
                                <div class="detail-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                                
                                <?php if ($user['phone']): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($user['phone']); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <i class="fas fa-calendar"></i>
                                    <span>Bergabung: <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                                </div>
                                
                                <?php if ($user['last_booking']): ?>
                                    <div class="detail-item">
                                        <i class="fas fa-clock"></i>
                                        <span>Booking terakhir: <?php echo date('d/m/Y', strtotime($user['last_booking'])); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="user-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo $user['total_bookings']; ?></div>
                                    <div class="stat-text">Booking</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo formatRupiah($user['total_spent']); ?></div>
                                    <div class="stat-text">Total Spent</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?php echo $user['total_bookings'] > 0 ? 'Aktif' : 'Pasif'; ?>
                                    </div>
                                    <div class="stat-text">Status</div>
                                </div>
                            </div>

                            <div class="user-actions">
                                <?php if ($user['total_bookings'] > 0): ?>
                                    <a href="bookings.php?search=<?php echo urlencode($user['full_name']); ?>" class="btn btn-sm">
                                        <i class="fas fa-calendar-check"></i> Booking
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] === 'customer'): ?>
                                    <button type="button" class="btn btn-sm btn-secondary" 
                                            onclick="confirmAction(<?php echo $user['id']; ?>, 'promote', '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                        <i class="fas fa-user-shield"></i> Jadikan Admin
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($user['id'] != $_SESSION['user_id'] && $user['role'] === 'customer'): ?>
                                    <button type="button" class="btn btn-sm btn-danger" 
                                            onclick="confirmAction(<?php echo $user['id']; ?>, 'delete', '<?php echo htmlspecialchars($user['full_name']); ?>')">
                                        <i class="fas fa-trash"></i> Hapus
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Confirmation Modal -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Konfirmasi Aksi</h3>
                <span class="close" onclick="closeActionModal()">&times;</span>
            </div>
            
            <div id="modalMessage"></div>
            
            <form method="POST" style="margin-top: 2rem;">
                <input type="hidden" name="action" id="modalAction">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="current_role" id="modalCurrentRole">
                
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeActionModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn" id="modalSubmitBtn">
                        <i class="fas fa-check"></i> Konfirmasi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmAction(userId, action, userName) {
            const modal = document.getElementById('actionModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalAction = document.getElementById('modalAction');
            const modalUserId = document.getElementById('modalUserId');
            const modalSubmitBtn = document.getElementById('modalSubmitBtn');
            
            modalUserId.value = userId;
            
            if (action === 'promote') {
                modalTitle.textContent = 'Promosikan ke Admin';
                modalMessage.innerHTML = `<p>Apakah Anda yakin ingin menjadikan <strong>${userName}</strong> sebagai admin?</p>
                                        <p style="color: #856404; margin-top: 1rem;">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            Admin akan memiliki akses penuh ke sistem!
                                        </p>`;
                modalAction.value = 'toggle_status';
                modalSubmitBtn.innerHTML = '<i class="fas fa-user-shield"></i> Jadikan Admin';
                modalSubmitBtn.className = 'btn';
            } else if (action === 'delete') {
                modalTitle.textContent = 'Hapus User';
                modalMessage.innerHTML = `<p>Apakah Anda yakin ingin menghapus user <strong>${userName}</strong>?</p>
                                        <p style="color: #dc3545; margin-top: 1rem;">
                                            <i class="fas fa-exclamation-triangle"></i> 
                                            Tindakan ini tidak dapat dibatalkan!
                                        </p>`;
                modalAction.value = 'delete_user';
                modalSubmitBtn.innerHTML = '<i class="fas fa-trash"></i> Hapus User';
                modalSubmitBtn.className = 'btn btn-danger';
            }
            
            modal.style.display = 'block';
        }

        function closeActionModal() {
            document.getElementById('actionModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('actionModal');
            if (event.target === modal) {
                closeActionModal();
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

        // Animated counter for stats
        document.addEventListener('DOMContentLoaded', function() {
            const statNumbers = document.querySelectorAll('.stat-number');
            statNumbers.forEach(function(stat) {
                const text = stat.textContent.trim();
                
                // Skip if it's a formatted rupiah value
                if (text.includes('Rp')) return;
                
                const finalValue = parseInt(text);
                if (!isNaN(finalValue) && finalValue > 0) {
                    stat.textContent = '0';
                    let current = 0;
                    const increment = Math.ceil(finalValue / 20);
                    const timer = setInterval(function() {
                        current += increment;
                        if (current >= finalValue) {
                            current = finalValue;
                            clearInterval(timer);
                        }
                        stat.textContent = current;
                    }, 100);
                }
            });
        });
    </script>
</body>
</html>