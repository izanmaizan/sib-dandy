<!-- admin/gallery.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();
requireAdmin();

$db = getDB();

// Handle form submissions
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_gallery':
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $service_type = trim($_POST['service_type']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Handle file upload
    $image = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/gallery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = 'gallery_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $image;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $stmt = $db->prepare("INSERT INTO gallery (service_type, title, description, image, is_featured) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$service_type, $title, $description, $image, $is_featured])) {
                $success = "Item gallery berhasil ditambahkan!";
            } else {
                $error = "Gagal menyimpan ke database!";
                unlink($upload_path);
            }
        } else {
            $error = "Gagal mengupload gambar!";
        }
    } else {
        $error = "Silakan pilih gambar untuk diupload!";
    }
    break;
            case 'update_gallery':
    $id = (int)$_POST['gallery_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $service_type = trim($_POST['service_type']);
    $is_featured = isset($_POST['is_featured']) ? 1 : 0;
    
    // Handle new image upload if provided
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/gallery/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Get old image to delete
        $stmt = $db->prepare("SELECT image FROM gallery WHERE id = ?");
        $stmt->execute([$id]);
        $old_image = $stmt->fetch(PDO::FETCH_ASSOC)['image'];
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $image = 'gallery_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $image;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            $stmt = $db->prepare("UPDATE gallery SET service_type = ?, title = ?, description = ?, image = ?, is_featured = ? WHERE id = ?");
            if ($stmt->execute([$service_type, $title, $description, $image, $is_featured, $id])) {
                if ($old_image && file_exists($upload_dir . $old_image)) {
                    unlink($upload_dir . $old_image);
                }
                $success = "Item gallery berhasil diupdate!";
            } else {
                $error = "Gagal mengupdate ke database!";
                unlink($upload_path);
            }
        } else {
            $error = "Gagal mengupload gambar baru!";
        }
    } else {
        // Update without changing image - PERBAIKI INI:
        $stmt = $db->prepare("UPDATE gallery SET service_type = ?, title = ?, description = ?, is_featured = ? WHERE id = ?");
        if ($stmt->execute([$service_type, $title, $description, $is_featured, $id])) {
            $success = "Item gallery berhasil diupdate!";
        } else {
            $error = "Gagal mengupdate gallery!";
        }
    }
    break;
                
            case 'delete_gallery':
                $id = (int)$_POST['gallery_id'];
                
                // Get image filename to delete
                $stmt = $db->prepare("SELECT image FROM gallery WHERE id = ?");
                $stmt->execute([$id]);
                $gallery = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($gallery) {
                    $stmt = $db->prepare("DELETE FROM gallery WHERE id = ?");
                    if ($stmt->execute([$id])) {
                        // Delete image file
                        $image_path = '../uploads/gallery/' . $gallery['image'];
                        if (file_exists($image_path)) {
                            unlink($image_path);
                        }
                        $success = "Item gallery berhasil dihapus!";
                    } else {
                        $error = "Gagal menghapus gallery!";
                    }
                }
                break;
        }
    }
}

// Get gallery items
$stmt = $db->query("SELECT g.*, g.service_type as service_name 
                   FROM gallery g 
                   ORDER BY g.created_at DESC");
$gallery_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service types untuk form
// $stmt = $db->query("SELECT * FROM service_types ORDER BY name");
// $service_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Gallery - Dandy Gallery Admin</title>
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

        .btn-danger {
            background: #dc3545;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b6b;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
        }

        .gallery-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .gallery-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }

        .gallery-image {
            height: 200px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .gallery-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .gallery-image .placeholder {
            color: white;
            font-size: 3rem;
            opacity: 0.8;
        }

        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.9);
            color: #ff6b6b;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .gallery-content {
            padding: 1.5rem;
        }

        .gallery-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .gallery-category {
            display: inline-block;
            background: #f8f9fa;
            color: #666;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }

        .gallery-description {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .gallery-actions {
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

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .image-preview {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            margin-top: 1rem;
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
            
            .gallery-grid {
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
            <li><a href="packages.php"><i class="fas fa-box"></i> Kelola Paket</a></li>
            <li><a href="customers.php"><i class="fas fa-users"></i> Kelola User</a></li>
            <li><a href="gallery.php" class="active"><i class="fas fa-images"></i> Gallery</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Laporan</a></li>
        </ul>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1 class="page-title">Kelola Gallery</h1>
            <button type="button" class="btn" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Tambah Item Gallery
            </button>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <!-- <div class="stats-summary">
            <?php
            $total_items = count($gallery_items);
            $featured_items = count(array_filter($gallery_items, function($item) { return $item['is_featured']; }));
            $categories = array_count_values(array_column($gallery_items, 'category'));
            ?>
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_items; ?></div>
                <div class="stat-label">Total Item</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $featured_items; ?></div>
                <div class="stat-label">Featured</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $categories['wedding_dress'] ?? 0; ?></div>
                <div class="stat-label">Wedding Dress</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $categories['makeup'] ?? 0; ?></div>
                <div class="stat-label">Makeup</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $categories['couple'] ?? 0; ?></div>
                <div class="stat-label">Couple</div>
            </div>
        </div> -->

        <!-- Gallery Grid -->
        <?php if (empty($gallery_items)): ?>
            <div style="text-align: center; padding: 4rem; background: white; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
                <i class="fas fa-images" style="font-size: 4rem; color: #ccc; margin-bottom: 1rem;"></i>
                <h3>Gallery Kosong</h3>
                <p style="color: #666; margin-bottom: 2rem;">Mulai tambahkan portfolio foto untuk showcase karya Anda.</p>
                <button type="button" class="btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tambah Item Pertama
                </button>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($gallery_items as $item): ?>
                    <div class="gallery-card">
                        <div class="gallery-image">
                            <?php if ($item['image'] && file_exists('../uploads/gallery/' . $item['image'])): ?>
                                <img src="../uploads/gallery/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <?php else: ?>
                                <div class="placeholder">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($item['is_featured']): ?>
                                <div class="featured-badge">
                                    <i class="fas fa-star"></i> Featured
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="gallery-content">
                            <h3 class="gallery-title"><?php echo htmlspecialchars($item['title']); ?></h3>
                            
                            <div class="gallery-category">
    <?php $service_icon = getServiceIcon($item['service_name']); ?>
    <i class="<?php echo $service_icon; ?>"></i> <?php echo htmlspecialchars($item['service_name']); ?>
</div>
                            
                            <?php if ($item['description']): ?>
                                <p class="gallery-description">
                                    <?php echo htmlspecialchars(substr($item['description'], 0, 100)) . (strlen($item['description']) > 100 ? '...' : ''); ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="gallery-actions">
                                <button type="button" class="btn btn-sm" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')">
                                    <i class="fas fa-trash"></i> Hapus
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- Add/Edit Gallery Modal -->
    <div id="galleryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Item Gallery</h3>
                <span class="close" onclick="closeGalleryModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data" id="galleryForm">
                    <input type="hidden" name="action" id="formAction" value="add_gallery">
                    <input type="hidden" name="gallery_id" id="galleryId">
                    
                    <div class="form-group">
                        <label for="title">Judul *</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
    <label for="service_type">Jenis Layanan *</label>
    <select id="service_type" name="service_type" required>
        <option value="">Pilih Jenis Layanan</option>
        <option value="Baju Pengantin">Baju Pengantin</option>
        <option value="Makeup Pengantin">Makeup Pengantin</option>
    </select>
</div>
                    
                    <div class="form-group">
                        <label for="description">Deskripsi</label>
                        <textarea id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="image">Gambar *</label>
                        <input type="file" id="image" name="image" accept="image/*" onchange="previewImage(this)">
                        <img id="imagePreview" class="image-preview" style="display: none;">
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_featured" name="is_featured">
                            <label for="is_featured">Tampilkan di Featured</label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                        <button type="button" class="btn btn-secondary" onclick="closeGalleryModal()">
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
                <p>Apakah Anda yakin ingin menghapus item gallery "<span id="deleteItemTitle"></span>"?</p>
                <p style="color: #dc3545; margin-top: 1rem;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Gambar juga akan dihapus dari server!
                </p>
                
                <form method="POST" style="margin-top: 2rem;">
                    <input type="hidden" name="action" value="delete_gallery">
                    <input type="hidden" name="gallery_id" id="deleteItemId">
                    
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
            document.getElementById('modalTitle').textContent = 'Tambah Item Gallery';
            document.getElementById('formAction').value = 'add_gallery';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Simpan';
            document.getElementById('galleryForm').reset();
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('image').required = true;
            document.getElementById('galleryModal').style.display = 'block';
        }

        function openEditModal(itemData) {
    document.getElementById('modalTitle').textContent = 'Edit Item Gallery';
    document.getElementById('formAction').value = 'update_gallery';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update';
            
            // Fill form with item data
            document.getElementById('galleryId').value = itemData.id;
            document.getElementById('title').value = itemData.title;
    document.getElementById('service_type').value = itemData.service_name;
            document.getElementById('description').value = itemData.description || '';
            document.getElementById('is_featured').checked = itemData.is_featured == 1;
            
            // Show current image if exists
            if (itemData.image) {
        const preview = document.getElementById('imagePreview');
        preview.src = '../uploads/gallery/' + itemData.image;
        preview.style.display = 'block';
    }
            
    document.getElementById('image').required = false;
    document.getElementById('galleryModal').style.display = 'block';
        }

        function closeGalleryModal() {
            document.getElementById('galleryModal').style.display = 'none';
        }

        function confirmDelete(itemId, itemTitle) {
            document.getElementById('deleteItemId').value = itemId;
            document.getElementById('deleteItemTitle').textContent = itemTitle;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const galleryModal = document.getElementById('galleryModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === galleryModal) {
                closeGalleryModal();
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