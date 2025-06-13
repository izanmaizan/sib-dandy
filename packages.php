<!-- /packages.php  -->
<?php
session_start();
require_once 'config/database.php';

$db = getDB();

// Filter jenis layanan (dress atau makeup)
$service_filter = isset($_GET['type']) ? $_GET['type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Query paket dengan filter
$sql = "SELECT p.*, p.service_type as service_name FROM packages p 
        WHERE p.is_active = 1";

$params = [];

if ($service_filter === 'dress') {
    $sql .= " AND p.service_type = 'Baju Pengantin'";
} elseif ($service_filter === 'makeup') {
    $sql .= " AND p.service_type = 'Makeup Pengantin'";
}

if (!empty($search)) {
    $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.service_type, p.created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil service types untuk statistik
// $stmt = $db->query("SELECT * FROM service_types ORDER BY id");
// $service_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik paket
$dress_count = count(array_filter($packages, function($p) { return $p['service_name'] === 'Baju Pengantin'; }));
$makeup_count = count(array_filter($packages, function($p) { return $p['service_name'] === 'Makeup Pengantin'; }));
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Layanan - Dandy Gallery Gown</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        line-height: 1.6;
        color: #333;
        background: #f8f9fa;
    }

    /* Header */
    .header {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        padding: 1rem 0;
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .nav-container {
        max-width: 1200px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0 2rem;
    }

    .logo {
        font-size: 1.8rem;
        font-weight: bold;
        text-decoration: none;
        color: white;
    }

    .nav-menu {
        display: flex;
        list-style: none;
        gap: 2rem;
    }

    .nav-menu a {
        color: white;
        text-decoration: none;
        transition: opacity 0.3s;
    }

    .nav-menu a:hover {
        opacity: 0.8;
    }

    /* Main Content */
    .main-content {
        margin-top: 80px;
        padding: 3rem 0;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    .page-header {
        text-align: center;
        margin-bottom: 3rem;
    }

    .page-title {
        font-size: 3rem;
        color: #333;
        margin-bottom: 1rem;
    }

    .page-subtitle {
        font-size: 1.2rem;
        color: #666;
        max-width: 600px;
        margin: 0 auto;
    }

    /* Service Filter Tabs */
    .service-tabs {
        display: flex;
        justify-content: center;
        gap: 1rem;
        margin-bottom: 3rem;
        flex-wrap: wrap;
    }

    .service-tab {
        padding: 1rem 2rem;
        background: white;
        border: 2px solid #e1e5e9;
        border-radius: 50px;
        text-decoration: none;
        color: #333;
        font-weight: 600;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .service-tab:hover,
    .service-tab.active {
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        border-color: transparent;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    /* Stats Cards */
    .stats-section {
        margin-bottom: 3rem;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
    }

    .stat-card {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        transition: transform 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin: 0 auto 1rem;
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.5rem;
    }

    .stat-label {
        color: #666;
        font-size: 0.9rem;
    }

    /* Search Section */
    .search-section {
        background: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        margin-bottom: 3rem;
    }

    .search-form {
        display: flex;
        gap: 1rem;
        align-items: end;
        flex-wrap: wrap;
    }

    .search-input {
        flex: 1;
        min-width: 300px;
        padding: 12px 15px;
        border: 2px solid #e1e5e9;
        border-radius: 10px;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .search-input:focus {
        outline: none;
        border-color: #ff6b6b;
    }

    .btn {
        padding: 14px 20px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        border: none;
        border-radius: 10px;
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

    /* Packages Grid */
    .packages-section h3 {
        font-size: 2rem;
        color: #333;
        margin-bottom: 2rem;
        text-align: center;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
    }

    .packages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 2rem;
        margin-bottom: 4rem;
    }

    .package-card {
        background: white;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s, box-shadow 0.3s;
        position: relative;
    }

    .package-card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .package-image {
        height: 250px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 4rem;
        position: relative;
    }

    .package-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: rgba(255, 255, 255, 0.9);
        color: #333;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .package-content {
        padding: 2rem;
    }

    .package-header {
        margin-bottom: 1.5rem;
    }

    .package-name {
        font-size: 1.5rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.5rem;
    }

    .package-price {
        font-size: 2rem;
        font-weight: bold;
        color: #ff6b6b;
        margin-bottom: 1rem;
    }

    .package-description {
        color: #666;
        margin-bottom: 1.5rem;
        line-height: 1.8;
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
        font-size: 1.1rem;
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
        color: #2ecc71;
        font-weight: bold;
    }

    .package-meta {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .package-meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #666;
        font-size: 0.9rem;
    }

    .package-actions {
        display: flex;
        gap: 1rem;
    }

    .btn-book {
        flex: 1;
        text-align: center;
        padding: 12px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        text-decoration: none;
        border-radius: 10px;
        font-weight: 600;
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .btn-book:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-detail {
        padding: 12px 20px;
        background: transparent;
        color: #ff6b6b;
        text-decoration: none;
        border: 2px solid #ff6b6b;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s;
    }

    .btn-detail:hover {
        background: #ff6b6b;
        color: white;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        color: #666;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .nav-menu {
            display: none;
        }

        .search-form {
            flex-direction: column;
        }

        .search-input {
            min-width: auto;
        }

        .packages-grid {
            grid-template-columns: 1fr;
        }

        .page-title {
            font-size: 2rem;
        }

        .service-tabs {
            justify-content: center;
        }

        .package-actions {
            flex-direction: column;
        }
    }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-gem"></i> Dandy Gallery Gown
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="packages.php">Paket</a></li>
                    <li><a href="index.php#gallery">Gallery</a></li>
                    <li><a href="index.php#contact">Kontak</a></li>
                    <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                    <li><a href="admin/dashboard.php">Dashboard</a></li>
                    <?php else: ?>
                    <li><a href="customer/dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Daftar</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">Paket Layanan Kami</h1>
                <p class="page-subtitle">
                    Pilih layanan terbaik untuk hari spesial Anda - sewa baju pengantin premium atau
                    jasa makeup profesional dengan berbagai pilihan paket
                </p>
            </div>

            <!-- Service Filter Tabs -->
            <div class="service-tabs">
                <a href="packages.php" class="service-tab <?php echo empty($service_filter) ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> Semua Layanan
                </a>
                <a href="packages.php?type=dress"
                    class="service-tab <?php echo $service_filter === 'dress' ? 'active' : ''; ?>">
                    <i class="fas fa-tshirt"></i> Sewa Baju Pengantin
                </a>
                <a href="packages.php?type=makeup"
                    class="service-tab <?php echo $service_filter === 'makeup' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> Jasa Makeup
                </a>
            </div>

            <!-- Stats Section -->
            <!-- <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <div class="stat-number"><?php echo $dress_count; ?></div>
                        <div class="stat-label">Paket Baju Pengantin</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-palette"></i>
                        </div>
                        <div class="stat-number"><?php echo $makeup_count; ?></div>
                        <div class="stat-label">Paket Makeup</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="stat-number"><?php echo count($packages); ?></div>
                        <div class="stat-label">Total Paket Tersedia</div>
                    </div>
                </div>
            </div> -->

            <!-- Search Section -->
            <div class="search-section">
                <form method="GET" class="search-form">
                    <?php if (!empty($service_filter)): ?>
                    <input type="hidden" name="type" value="<?php echo htmlspecialchars($service_filter); ?>">
                    <?php endif; ?>

                    <input type="text" name="search" class="search-input"
                        placeholder="Cari paket berdasarkan nama atau deskripsi..."
                        value="<?php echo htmlspecialchars($search); ?>">

                    <button type="submit" class="btn">
                        <i class="fas fa-search"></i> Cari
                    </button>

                    <?php if (!empty($search) || !empty($service_filter)): ?>
                    <a href="packages.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Reset
                    </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Packages Section -->
            <?php if (empty($packages)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h3>Tidak Ada Paket Ditemukan</h3>
                <p>Maaf, tidak ada paket yang sesuai dengan pencarian Anda. Silakan coba dengan kata kunci lain.</p>
                <a href="packages.php" class="btn" style="margin-top: 1rem;">
                    <i class="fas fa-refresh"></i> Lihat Semua Paket
                </a>
            </div>
            <?php else: ?>

            <?php 
                // Kelompokkan paket berdasarkan jenis layanan
                $dress_packages = array_filter($packages, function($p) { return $p['service_name'] === 'Baju Pengantin'; });
$makeup_packages = array_filter($packages, function($p) { return $p['service_name'] === 'Makeup Pengantin'; });
                ?>

            <?php if (!empty($dress_packages) && (empty($service_filter) || $service_filter === 'dress')): ?>
            <div class="packages-section">
                <h3><i class="fas fa-tshirt"></i> Paket Sewa Baju Pengantin</h3>
                <div class="packages-grid">
                    <?php foreach ($dress_packages as $package): ?>
                    <div class="package-card">
                        <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])): ?>
                        <div class="package-image"
                            style="background-image: url('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>'); background-size: cover; background-position: center; position: relative;">
                            <div class="package-badge">
                                <?php $service_icon = getServiceIcon($package['service_name']); ?>
                                <i class="<?php echo $service_icon; ?>"></i>
                                <?php echo htmlspecialchars($package['service_name']); ?>
                            </div>
                            <!-- Overlay untuk readability -->
                            <div
                                style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3);">
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="package-image">
                            <div class="package-badge"><?php echo htmlspecialchars($package['service_name']); ?></div>
                            <i
                                class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
                        </div>
                        <?php endif; ?>

                        <div class="package-content">
                            <div class="package-header">
                                <h4 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h4>
                                <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>
                            </div>

                            <?php if ($package['description']): ?>
                            <p class="package-description"><?php echo htmlspecialchars($package['description']); ?></p>
                            <?php endif; ?>

                            <?php if ($package['includes']): ?>
                            <div class="package-includes">
                                <h4><i class="fas fa-check-circle"></i> Termasuk dalam paket:</h4>
                                <ul>
                                    <?php 
                                                $includes = explode(',', $package['includes']);
                                                foreach (array_slice($includes, 0, 4) as $include): 
                                                ?>
                                    <li><?php echo trim(htmlspecialchars($include)); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($includes) > 4): ?>
                                    <li>Dan <?php echo count($includes) - 4; ?> item lainnya...</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <div class="package-meta">
                                <?php if ($package['size_available']): ?>
                                <div class="package-meta-item">
                                    <i class="fas fa-ruler"></i>
                                    <span>Size: <?php echo htmlspecialchars($package['size_available']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($package['color_available']): ?>
                                <div class="package-meta-item">
                                    <i class="fas fa-palette"></i>
                                    <span><?php echo htmlspecialchars($package['color_available']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="package-actions">
                                <?php if (isLoggedIn()): ?>
                                <a href="customer/booking_new.php?package=<?php echo $package['id']; ?>"
                                    class="btn-book">
                                    <i class="fas fa-calendar-plus"></i> Book Sekarang
                                </a>
                                <?php else: ?>
                                <a href="login.php" class="btn-book">
                                    <i class="fas fa-sign-in-alt"></i> Login untuk Book
                                </a>
                                <?php endif; ?>

                                <a href="package_detail.php?id=<?php echo $package['id']; ?>" class="btn-detail">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($makeup_packages) && (empty($service_filter) || $service_filter === 'makeup')): ?>
            <div class="packages-section">
                <h3><i class="fas fa-palette"></i> Paket Jasa Makeup Pengantin</h3>
                <div class="packages-grid">
                    <?php foreach ($makeup_packages as $package): ?>
                    <div class="package-card">
                        <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])): ?>
                        <div class="package-image"
                            style="background-image: url('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>'); background-size: cover; background-position: center; position: relative;">
                            <div class="package-badge">
                                <?php $service_icon = getServiceIcon($package['service_name']); ?>
                                <i class="<?php echo $service_icon; ?>"></i>
                                <?php echo htmlspecialchars($package['service_name']); ?>
                            </div>
                            <div
                                style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.3);">
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="package-image">
                            <div class="package-badge"><?php echo htmlspecialchars($package['service_name']); ?></div>
                            <i
                                class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-palette'; ?>"></i>
                        </div>
                        <?php endif; ?>

                        <div class="package-content">
                            <div class="package-header">
                                <h4 class="package-name"><?php echo htmlspecialchars($package['name']); ?></h4>
                                <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>
                            </div>

                            <?php if ($package['description']): ?>
                            <p class="package-description"><?php echo htmlspecialchars($package['description']); ?></p>
                            <?php endif; ?>

                            <?php if ($package['includes']): ?>
                            <div class="package-includes">
                                <h4><i class="fas fa-check-circle"></i> Termasuk dalam paket:</h4>
                                <ul>
                                    <?php 
                                                $includes = explode(',', $package['includes']);
                                                foreach (array_slice($includes, 0, 4) as $include): 
                                                ?>
                                    <li><?php echo trim(htmlspecialchars($include)); ?></li>
                                    <?php endforeach; ?>
                                    <?php if (count($includes) > 4): ?>
                                    <li>Dan <?php echo count($includes) - 4; ?> item lainnya...</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                            <?php endif; ?>

                            <div class="package-meta">
                                <div class="package-meta-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Bisa ke lokasi</span>
                                </div>
                            </div>

                            <div class="package-actions">
                                <?php if (isLoggedIn()): ?>
                                <a href="customer/booking_new.php?package=<?php echo $package['id']; ?>"
                                    class="btn-book">
                                    <i class="fas fa-calendar-plus"></i> Book Sekarang
                                </a>
                                <?php else: ?>
                                <a href="login.php" class="btn-book">
                                    <i class="fas fa-sign-in-alt"></i> Login untuk Book
                                </a>
                                <?php endif; ?>

                                <a href="package_detail.php?id=<?php echo $package['id']; ?>" class="btn-detail">
                                    <i class="fas fa-info-circle"></i> Detail
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php endif; ?>

            <!-- Call to Action -->
            <div
                style="text-align: center; margin-top: 4rem; padding: 3rem; background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <h2 style="color: #333; margin-bottom: 1rem;">Butuh Konsultasi?</h2>
                <p style="color: #666; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Tim kami siap membantu Anda memilih paket yang tepat sesuai dengan kebutuhan dan budget.
                    Jangan ragu untuk menghubungi kami untuk konsultasi gratis!
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="index.php#contact" class="btn">
                        <i class="fas fa-phone"></i> Hubungi Kami
                    </a>
                    <a href="index.php#gallery" class="btn btn-secondary">
                        <i class="fas fa-images"></i> Lihat Portfolio
                    </a>
                    <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-secondary">
                        <i class="fas fa-user-plus"></i> Daftar Sekarang
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer style="background: #333; color: white; text-align: center; padding: 3rem 0; margin-top: 4rem;">
        <div class="container">
            <p>&copy; 2025 Dandy Gallery Gown. All rights reserved.</p>
            <p style="margin-top: 0.5rem; opacity: 0.8;">Sistem Informasi Booking Sewa Baju Pengantin & Jasa Makeup
                Profesional</p>
        </div>
    </footer>

    <script>
    // Smooth scrolling untuk anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Auto focus search input jika ada parameter search
    <?php if (!empty($search)): ?>
    document.querySelector('.search-input').focus();
    <?php endif; ?>
    </script>
</body>

</html>