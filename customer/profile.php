<!-- customer/profile.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

// Ambil data user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_profile':
                $full_name = trim($_POST['full_name']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                
                if (empty($full_name) || empty($email)) {
                    $error = 'Nama lengkap dan email wajib diisi!';
                } else {
                    // Cek email sudah digunakan user lain atau belum
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $_SESSION['user_id']]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Email sudah digunakan oleh user lain!';
                    } else {
                        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
                        if ($stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']])) {
                            $_SESSION['full_name'] = $full_name;
                            $user['full_name'] = $full_name;
                            $user['email'] = $email;
                            $user['phone'] = $phone;
                            $success = 'Profil berhasil diupdate!';
                        } else {
                            $error = 'Gagal mengupdate profil!';
                        }
                    }
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    $error = 'Semua field password wajib diisi!';
                } elseif ($new_password !== $confirm_password) {
                    $error = 'Konfirmasi password baru tidak cocok!';
                } elseif (strlen($new_password) < 6) {
                    $error = 'Password baru minimal 6 karakter!';
                } elseif (!password_verify($current_password, $user['password'])) {
                    $error = 'Password saat ini salah!';
                } else {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                    if ($stmt->execute([$hashed_password, $_SESSION['user_id']])) {
                        $success = 'Password berhasil diubah!';
                    } else {
                        $error = 'Gagal mengubah password!';
                    }
                }
                break;
        }
    }
}

// Handle redirect parameter
$redirect_to = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// Hitung total booking user
$stmt = $db->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Hitung total pengeluaran
$stmt = $db->prepare("SELECT SUM(total_amount) as total FROM bookings WHERE user_id = ? AND status IN ('paid', 'completed')");
$stmt->execute([$_SESSION['user_id']]);
$total_spent = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya - Dandy Gallery</title>
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

    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
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

    .required {
        color: #ff6b6b;
    }

    .form-group input,
    .form-group textarea {
        width: 100%;
        padding: 12px 15px;
        border: 2px solid #e1e5e9;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #ff6b6b;
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

    .profile-summary {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        padding: 2rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        text-align: center;
    }

    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin: 0 auto 1rem;
    }

    .profile-name {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    .profile-info {
        opacity: 0.9;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .info-item {
        text-align: center;
        padding: 1rem;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 8px;
    }

    .info-value {
        font-weight: bold;
        font-size: 1.1rem;
    }

    .info-label {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-top: 0.25rem;
    }

    .redirect-notice {
        background: #fff3cd;
        color: #856404;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 2rem;
        border: 1px solid #ffeaa7;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .profile-grid {
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
                <i class="fas fa-gem"></i> Dandy Gallery
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
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Booking Saya</a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profil Saya</a></li>
            <li><a href="../packages.php"><i class="fas fa-box"></i> Lihat Paket</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Profil Saya</h1>
            <?php if ($redirect_to): ?>
            <a href="<?php echo htmlspecialchars($redirect_to); ?>.php" class="btn">
                <i class="fas fa-arrow-right"></i> Lanjutkan ke <?php echo ucfirst($redirect_to); ?>
            </a>
            <?php endif; ?>
        </div>

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

        <?php if ($redirect_to): ?>
        <div class="redirect-notice">
            <i class="fas fa-info-circle"></i>
            <strong>Lengkapi profil terlebih dahulu</strong> untuk melanjutkan ke halaman
            <?php echo ucfirst($redirect_to); ?>.
        </div>
        <?php endif; ?>

        <!-- Profile Summary -->
        <div class="profile-summary">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?>
            </div>
            <div class="profile-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
            <div class="profile-info">
                Member sejak <?php echo date('d F Y', strtotime($user['created_at'])); ?>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <div class="info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div class="info-label">Email</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?: '-'); ?></div>
                    <div class="info-label">Telepon</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo $total_bookings; ?></div>
                    <div class="info-label">Total Booking</div>
                </div>
                <div class="info-item">
                    <div class="info-value"><?php echo formatRupiah($total_spent); ?></div>
                    <div class="info-label">Total Pengeluaran</div>
                </div>
            </div>
        </div>

        <!-- Profile Forms -->
        <div class="profile-grid">
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user-cog"></i>
                    <h3>Informasi Profil</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-group">
                            <label for="full_name">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" id="full_name" name="full_name" required
                                value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email <span class="required">*</span></label>
                            <input type="email" id="email" name="email" required
                                value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Nomor Telepon</label>
                            <input type="tel" id="phone" name="phone"
                                value="<?php echo htmlspecialchars($user['phone'] ?: ''); ?>">
                        </div>

                        <button type="submit" class="btn" style="width: 100%;">
                            <i class="fas fa-save"></i> Update Profil
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-lock"></i>
                    <h3>Ubah Password</h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="form-group">
                            <label for="current_password">Password Saat Ini <span class="required">*</span></label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>

                        <div class="form-group">
                            <label for="new_password">Password Baru <span class="required">*</span></label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Konfirmasi Password Baru <span
                                    class="required">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>

                        <button type="submit" class="btn" style="width: 100%;">
                            <i class="fas fa-key"></i> Ubah Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
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

    // Password confirmation validation
    document.getElementById('confirm_password').addEventListener('input', function() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = this.value;

        if (confirmPassword && newPassword !== confirmPassword) {
            this.setCustomValidity('Password tidak cocok');
        } else {
            this.setCustomValidity('');
        }
    });
    </script>
</body>

</html>