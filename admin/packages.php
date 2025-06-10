<!-- admin/packages.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();


// Function untuk upload gambar
function uploadPackageImage($file, $package_id) {
    $upload_dir = '../uploads/packages/';
    
    // Buat direktori jika belum ada
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau GIF.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB.'];
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'package_' . $package_id . '_' . time() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Gagal mengupload gambar.'];
    }
}

$success = '';
$error = '';

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_package':
    $name = trim($_POST['name']);
    $service_type = trim($_POST['service_type']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $includes = trim($_POST['includes']);
    $size_available = trim($_POST['size_available']) ?: null;
    $color_available = trim($_POST['color_available']) ?: null;
    
    if (empty($name) || empty($service_type) || empty($price)) {
        $error = "Mohon isi semua field yang wajib!";
    } else {
        // Insert paket dulu untuk mendapatkan ID
        $stmt = $db->prepare("INSERT INTO packages (service_type, name, description, price, includes, size_available, color_available) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$service_type, $name, $description, $price, $includes, $size_available, $color_available])) {
            $package_id = $db->lastInsertId();
            
            // Handle upload gambar
            if (isset($_FILES['package_image']) && $_FILES['package_image']['error'] == 0) {
                $upload_result = uploadPackageImage($_FILES['package_image'], $package_id);
                if ($upload_result['success']) {
                    // Update paket dengan nama file gambar
                    $stmt = $db->prepare("UPDATE packages SET image = ? WHERE id = ?");
                    $stmt->execute([$upload_result['filename'], $package_id]);
                }
            }
            
            $success = "Paket berhasil ditambahkan!";
        } else {
            $error = "Gagal menambahkan paket!";
        }
    }
    break;


                
            case 'update_package':
    $id = (int)$_POST['package_id'];
    $name = trim($_POST['name']);
    $service_type = trim($_POST['service_type']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $includes = trim($_POST['includes']);
    $size_available = trim($_POST['size_available']) ?: null;
    $color_available = trim($_POST['color_available']) ?: null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    if (empty($name) || empty($service_type) || empty($price)) {
        $error = "Mohon isi semua field yang wajib!";
    } else {
        // Ambil data paket lama untuk gambar
        $stmt = $db->prepare("SELECT image FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        $old_package = $stmt->fetch(PDO::FETCH_ASSOC);
        $current_image = $old_package['image'];
        
        // Handle upload gambar baru
        if (isset($_FILES['package_image']) && $_FILES['package_image']['error'] == 0) {
            $upload_result = uploadPackageImage($_FILES['package_image'], $id);
            if ($upload_result['success']) {
                // Hapus gambar lama jika ada
                if ($current_image && file_exists('../uploads/packages/' . $current_image)) {
                    unlink('../uploads/packages/' . $current_image);
                }
                $current_image = $upload_result['filename'];
            }
        }
    $stmt = $db->prepare("UPDATE packages SET service_type = ?, name = ?, description = ?, price = ?, includes = ?, size_available = ?, color_available = ?, image = ?, is_active = ? WHERE id = ?");
        if ($stmt->execute([$service_type, $name, $description, $price, $includes, $size_available, $color_available, $current_image, $is_active, $id])) {
            $success = "Paket berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate paket!";
        }
    }
    break;


                
            case 'delete_package':
    $id = (int)$_POST['package_id'];
    
    // Cek apakah paket sedang digunakan dalam booking
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM bookings WHERE package_id = ?");
    $stmt->execute([$id]);
    $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($booking_count > 0) {
        $error = "Tidak dapat menghapus paket yang sedang digunakan dalam booking!";
    } else {
        // Ambil data gambar untuk dihapus
        $stmt = $db->prepare("SELECT image FROM packages WHERE id = ?");
        $stmt->execute([$id]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
        if ($stmt->execute([$id])) {
            // Hapus file gambar jika ada
            if ($package['image'] && file_exists('../uploads/packages/' . $package['image'])) {
                unlink('../uploads/packages/' . $package['image']);
            }
            $success = "Paket berhasil dihapus!";
        } else {
            $error = "Gagal menghapus paket!";
        }
    }
    break;

        }
    }
}

// Get packages with service types
$stmt = $db->prepare("SELECT p.*, p.service_type as service_type_name FROM packages p 
                     ORDER BY p.service_type, p.created_at DESC");
$stmt->execute();
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service types for form
// $stmt = $db->query("SELECT * FROM service_types ORDER BY name");
// $service_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM packages WHERE is_active = 1");
$stats['active_packages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM packages WHERE service_type = 'Baju Pengantin' AND is_active = 1");
$stats['dress_packages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM packages WHERE service_type = 'Makeup Pengantin' AND is_active = 1");
$stats['makeup_packages'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Paket - Dandy Gallery Admin</title>
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
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        /* Stats Cards */
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
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin: 0 auto 1rem;
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

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
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

        .form-group input[type="file"] {
    padding: 8px 12px;
    border: 2px dashed #e1e5e9;
    border-radius: 8px;
    background: #f8f9fa;
    cursor: pointer;
    transition: border-color 0.3s;
}

.form-group input[type="file"]:hover {
    border-color: #ff6b6b;
}

.form-group input[type="file"]:focus {
    outline: none;
    border-color: #ff6b6b;
    border-style: solid;
}

#currentImagePreview img {
    border: 2px solid #e1e5e9;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .package-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .package-card:hover {
            transform: translateY(-5px);
        }

        .package-card img {
    transition: transform 0.3s ease;
}

.package-card:hover img {
    transform: scale(1.05);
}

        .package-header {
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1.5rem;
            position: relative;
        }

        .package-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .package-status {
            position: absolute;
            top: 1rem;
            left: 1rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .package-status.active {
            background: rgba(40,167,69,0.9);
            color: white;
        }

        .package-status.inactive {
            background: rgba(220,53,69,0.9);
            color: white;
        }

        .package-body {
            padding: 1.5rem;
        }

        .package-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }

        .package-meta {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
            flex-wrap: wrap;
        }

        .package-includes {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .package-actions {
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
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 2rem auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 900px;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
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

        .service-specific {
            display: none;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .packages-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
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
            <li><a href="bookings.php"><i class="fas fa-calendar-check"></i> Kelola Booking</a></li>
            <li><a href="packages.php" class="active"><i class="fas fa-box"></i> Kelola Paket</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Kelola Paket</h1>
            <button type="button" class="btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Paket
            </button>
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

        <!-- Stats Section -->
        <!-- <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="stat-number"><?php echo $stats['active_packages']; ?></div>
                <div class="stat-label">Total Paket Aktif</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-tshirt"></i>
                </div>
                <div class="stat-number"><?php echo $stats['dress_packages']; ?></div>
                <div class="stat-label">Paket Baju Pengantin</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-palette"></i>
                </div>
                <div class="stat-number"><?php echo $stats['makeup_packages']; ?></div>
                <div class="stat-label">Paket Makeup</div>
            </div>
        </div> -->

        <!-- Packages Grid -->
        <div class="packages-grid">
            <?php foreach ($packages as $package): ?>
                <div class="package-card">
    <div class="package-header">
        <div class="package-status <?php echo $package['is_active'] ? 'active' : 'inactive'; ?>">
            <?php echo $package['is_active'] ? 'Aktif' : 'Nonaktif'; ?>
        </div>
        <div class="package-badge">
    <?php $service_icon = getServiceIcon($package['service_type_name']); ?>
    <i class="<?php echo $service_icon; ?>"></i>
    <?php echo htmlspecialchars($package['service_type_name']); ?>
        </div>
        <h3 style="margin-top: 2rem;"><?php echo htmlspecialchars($package['name']); ?></h3>
    </div>
    <!-- Tambahan: Gambar paket -->
    <?php if ($package['image']): ?>
        <div style="height: 200px; overflow: hidden;">
            <img src="../uploads/packages/<?php echo htmlspecialchars($package['image']); ?>" 
                 alt="<?php echo htmlspecialchars($package['name']); ?>"
                 style="width: 100%; height: 100%; object-fit: cover;">
        </div>
    <?php else: ?>
        <div style="height: 200px; background: #f8f9fa; display: flex; align-items: center; justify-content: center; color: #666;">
            <div style="text-align: center;">
                <i class="fas fa-image" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                <div>Belum ada gambar</div>
            </div>
        </div>
    <?php endif; ?>
                    
                    <div class="package-body">
                        <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>
                        
                        <div class="package-meta">
                            <span><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($package['created_at'])); ?></span>
                            <?php if ($package['size_available']): ?>
                                <span><i class="fas fa-ruler"></i> <?php echo htmlspecialchars($package['size_available']); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ($package['description']): ?>
                            <p style="margin-bottom: 1rem; color: #666;">
                                <?php echo htmlspecialchars(substr($package['description'], 0, 100)) . (strlen($package['description']) > 100 ? '...' : ''); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($package['includes']): ?>
                            <div class="package-includes">
                                <strong>Termasuk:</strong><br>
                                <?php echo htmlspecialchars(substr($package['includes'], 0, 150)) . (strlen($package['includes']) > 150 ? '...' : ''); ?>
                            </div>
                        <?php endif; ?>

                        <div class="package-actions">
                            <button type="button" class="btn btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($package)); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $package['id']; ?>, '<?php echo htmlspecialchars($package['name']); ?>')">
                                <i class="fas fa-trash"></i> Hapus
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (empty($packages)): ?>
            <div class="card">
                <div class="card-body" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-box" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem;"></i>
                    <h3>Belum Ada Paket</h3>
                    <p style="color: #666; margin-bottom: 2rem;">Mulai tambahkan paket baju pengantin dan makeup untuk customer Anda.</p>
                    <button type="button" class="btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Tambah Paket Pertama
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add/Edit Package Modal -->
     
<div id="packageModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Tambah Paket</h3>
            <span class="close" onclick="closePackageModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" id="packageForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="formAction" value="add_package">
                <input type="hidden" name="package_id" id="packageId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Nama Paket <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
    <label for="service_type">Jenis Layanan <span class="required">*</span></label>
    <select id="service_type" name="service_type" required onchange="toggleServiceFields()">
        <option value="">Pilih Jenis Layanan</option>
        <option value="Baju Pengantin">Baju Pengantin</option>
        <option value="Makeup Pengantin">Makeup Pengantin</option>
    </select>
                    </div>
                </div>

                <!-- Tambahan: Upload gambar -->
                <div class="form-group">
                    <label for="package_image">Gambar Paket</label>
                    <input type="file" id="package_image" name="package_image" accept="image/*">
                    <small style="color: #666; display: block; margin-top: 0.25rem;">
                        Format yang didukung: JPG, PNG, GIF. Maksimal 5MB.
                    </small>
                    <div id="currentImagePreview" style="margin-top: 1rem; display: none;">
                        <label style="display: block; margin-bottom: 0.5rem;">Gambar Saat Ini:</label>
                        <img id="currentImage" style="max-width: 200px; border-radius: 8px;">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="price">Harga <span class="required">*</span></label>
                        <input type="number" id="price" name="price" step="0.01" required>
                    </div>
                </div>

                <!-- Fields khusus untuk Baju Pengantin -->
                <div id="dressFields" class="service-specific">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="size_available">Ukuran Tersedia</label>
                            <input type="text" id="size_available" name="size_available" placeholder="Contoh: S,M,L,XL,XXL">
                            <small style="color: #666;">Pisahkan dengan koma</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="color_available">Warna Tersedia</label>
                            <input type="text" id="color_available" name="color_available" placeholder="Contoh: White,Ivory,Champagne">
                            <small style="color: #666;">Pisahkan dengan koma</small>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Deskripsi</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="includes">Yang Termasuk dalam Paket</label>
                    <textarea id="includes" name="includes" rows="4" placeholder="Pisahkan dengan koma, contoh: Gaun pengantin, Makeup, Hairdo, Aksesoris"></textarea>
                </div>

                <div class="form-group" id="statusGroup" style="display: none;">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_active" name="is_active" checked>
                        <label for="is_active">Paket Aktif</label>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <button type="button" class="btn btn-secondary" onclick="closePackageModal()">
                        <i class="fas fa-times"></i> Batal
                    </button>
                    <button type="submit" class="btn" id="submitBtn">
                        <i class="fas fa-save"></i> Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Konfirmasi Hapus</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Apakah Anda yakin ingin menghapus paket "<span id="deletePackageName"></span>"?</p>
                <p style="color: #dc3545; margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Tindakan ini tidak dapat dibatalkan!
                </p>
                
                <form method="POST" style="margin-top: 2rem;">
                    <input type="hidden" name="action" value="delete_package">
                    <input type="hidden" name="package_id" id="deletePackageId">
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                            <i class="fas fa-times"></i> Batal
                        </button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Hapus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Tambah Paket';
            document.getElementById('formAction').value = 'add_package';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Simpan';
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('packageForm').reset();
            document.getElementById('dressFields').style.display = 'none';
            document.getElementById('packageModal').style.display = 'block';
        }

        function openEditModal(packageData) {
    document.getElementById('modalTitle').textContent = 'Edit Paket';
    document.getElementById('service_type').value = packageData.service_type_name || '';
    document.getElementById('formAction').value = 'update_package';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update';
    document.getElementById('statusGroup').style.display = 'block';
    
    // Fill form with package data
    document.getElementById('packageId').value = packageData.id;
    document.getElementById('name').value = packageData.name;
    document.getElementById('service_type').value = packageData.service_type_name || '';
    document.getElementById('description').value = packageData.description || '';
    document.getElementById('price').value = packageData.price;
    document.getElementById('includes').value = packageData.includes || '';
    document.getElementById('size_available').value = packageData.size_available || '';
    document.getElementById('color_available').value = packageData.color_available || '';
    document.getElementById('is_active').checked = packageData.is_active == 1;
    
    // Show current image if exists
    const currentImagePreview = document.getElementById('currentImagePreview');
    const currentImage = document.getElementById('currentImage');
    if (packageData.image) {
        currentImage.src = '../uploads/packages/' + packageData.image;
        currentImagePreview.style.display = 'block';
    } else {
        currentImagePreview.style.display = 'none';
    }
    
    // Show/hide dress fields based on service type
    toggleServiceFields();
    
    document.getElementById('packageModal').style.display = 'block';
}


        function closePackageModal() {
    document.getElementById('packageModal').style.display = 'none';
    document.getElementById('currentImagePreview').style.display = 'none';
    document.getElementById('package_image').value = '';
}


        function confirmDelete(packageId, packageName) {
            document.getElementById('deletePackageId').value = packageId;
            document.getElementById('deletePackageName').textContent = packageName;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }


// Tambahan: Preview gambar yang dipilih
document.getElementById('package_image').addEventListener('change', function(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const currentImage = document.getElementById('currentImage');
            const currentImagePreview = document.getElementById('currentImagePreview');
            currentImage.src = e.target.result;
            currentImagePreview.style.display = 'block';
            
            // Update label
            currentImagePreview.querySelector('label').textContent = 'Preview Gambar Baru:';
        };
        reader.readAsDataURL(file);
    }
});

        function toggleServiceFields() {
    const serviceType = document.getElementById('service_type').value;
    const dressFields = document.getElementById('dressFields');
    
    if (serviceType === 'Baju Pengantin') {
        dressFields.style.display = 'block';
    } else {
        dressFields.style.display = 'none';
        document.getElementById('size_available').value = '';
        document.getElementById('color_available').value = '';
    }
}

        // Close modals when clicking outside
        window.onclick = function(event) {
            const packageModal = document.getElementById('packageModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === packageModal) {
                closePackageModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
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