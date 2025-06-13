<!-- /package_detail.php  -->
<?php
/** 
 * Sib-dandy
 * package_detail.php 
 * PHP Native
 */
session_start();
require_once 'config/database.php';

$db = getDB();

$package_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$package_id) {
    header('Location: packages.php');
    exit();
}

// Ambil detail paket dengan service type - DIPERBAIKI: menggunakan service_types
$stmt = $db->prepare(
    "SELECT p.*, p.service_type as service_name
                     FROM packages p 
                     WHERE p.id = ? AND p.is_active = 1"
);
$stmt->execute([$package_id]);
$package = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$package) {
    header('Location: packages.php');
    exit();
}

// Ambil paket terkait (kategori yang sama)
$stmt = $db->prepare(
    "SELECT p.*, p.service_type as service_name 
                     FROM packages p 
                     WHERE p.service_type = ? AND p.id != ? AND p.is_active = 1 
                     ORDER BY p.price ASC 
                     LIMIT 3"
);
$stmt->execute([$package['service_name'], $package_id]);
$related_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// TAMBAHKAN setelah query package:
$service_icon = getServiceIcon($package['service_name']);
$service_description = getServiceDescription($package['service_name']);

// Ambil testimoni/review jika ada (dari booking yang completed)
$stmt = $db->prepare(
    "SELECT u.full_name, b.special_request, b.created_at 
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     WHERE b.package_id = ? AND b.status = 'completed' 
                     AND b.special_request IS NOT NULL AND b.special_request != ''
                     ORDER BY b.created_at DESC 
                     LIMIT 5"
);
$stmt->execute([$package_id]);
$testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung statistik paket
$stmt = $db->prepare(
    "SELECT 
                        COUNT(*) as total_bookings,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings
                     FROM bookings WHERE package_id = ?"
);
$stmt->execute([$package_id]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($package['name']); ?> - Dandy Gallery Gown</title>
    <meta name="description" content="<?php echo htmlspecialchars($package['description']); ?>">
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
        padding: 2rem 0;
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
    }

    /* Breadcrumb */
    .breadcrumb {
        background: white;
        padding: 1rem 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .breadcrumb a {
        color: #666;
        text-decoration: none;
        margin-right: 0.5rem;
    }

    .breadcrumb a:hover {
        color: #ff6b6b;
    }

    .breadcrumb .current {
        color: #ff6b6b;
        font-weight: 600;
    }

    /* Package Hero */
    .package-hero {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        padding: 3rem 0;
        border-radius: 20px;
        margin-bottom: 3rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .package-hero::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 600"><circle fill="rgba(255,255,255,0.1)" cx="200" cy="100" r="80"/><circle fill="rgba(255,255,255,0.05)" cx="1000" cy="500" r="120"/><circle fill="rgba(255,255,255,0.03)" cx="600" cy="300" r="200"/></svg>');
        pointer-events: none;
    }

    .hero-content {
        position: relative;
        z-index: 1;
    }

    .package-category {
        display: inline-block;
        background: rgba(255, 255, 255, 0.2);
        padding: 0.5rem 1.5rem;
        border-radius: 25px;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .package-title {
        font-size: 3rem;
        font-weight: bold;
        margin-bottom: 1rem;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }

    .package-price {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 1rem;
    }

    .package-description {
        font-size: 1.2rem;
        opacity: 0.9;
        max-width: 800px;
        margin: 0 auto 2rem;
    }

    .package-image {
        position: relative;
        transition: transform 0.3s ease;
    }

    .package-card:hover .package-image {
        transform: scale(1.05);
    }

    .package-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        z-index: 2;
        background: rgba(255, 255, 255, 0.95);
        color: #333;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    /* Image Gallery Styles */
    .package-gallery-section img {
        cursor: pointer;
        transition: transform 0.3s ease;
    }

    .package-gallery-section img:hover {
        transform: scale(1.02);
    }

    /* Modal untuk Image Zoom */
    .image-modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.9);
        animation: fadeIn 0.3s ease;
    }

    .image-modal-content {
        position: relative;
        margin: auto;
        display: block;
        width: 90%;
        max-width: 900px;
        max-height: 90%;
        top: 50%;
        transform: translateY(-50%);
        border-radius: 10px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .image-modal-close {
        position: absolute;
        top: 20px;
        right: 35px;
        color: white;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        z-index: 2001;
        transition: opacity 0.3s;
    }

    .image-modal-close:hover {
        opacity: 0.7;
    }

    /* Related Package Image Enhancements */
    .related-image {
        transition: transform 0.3s ease;
    }

    .related-card:hover .related-image {
        transform: scale(1.05);
    }

    /* Responsive Image Adjustments */
    @media (max-width: 768px) {
        .package-gallery-section img {
            max-height: 300px;
        }

        .image-modal-content {
            width: 95%;
            margin-top: 10%;
            transform: none;
            top: auto;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }

        to {
            opacity: 1;
        }
    }

    .hero-stats {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-top: 2rem;
    }

    .stat-item {
        text-align: center;
        background: rgba(255, 255, 255, 0.1);
        padding: 1rem 1.5rem;
        border-radius: 10px;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.8;
    }

    /* Package Details */
    .package-details {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 3rem;
        margin-bottom: 3rem;
    }

    .details-main {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .details-sidebar {
        display: flex;
        flex-direction: column;
        gap: 2rem;
    }

    .section-title {
        font-size: 1.8rem;
        color: #333;
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 3px solid #ff6b6b;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Package Includes */
    .includes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1.5rem;
    }

    .include-item {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 10px;
        border-left: 4px solid #ff6b6b;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .include-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
    }

    /* Package Info Cards */
    .info-card {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .info-card h3 {
        color: #333;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0;
        border-bottom: 1px solid #eee;
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        color: #666;
        font-weight: 500;
    }

    .info-value {
        color: #333;
        font-weight: 600;
    }

    /* Action Card */
    .action-card {
        background: linear-gradient(135deg, #ff6b6b, #ffa500);
        color: white;
        border-radius: 15px;
        padding: 2rem;
        text-align: center;
        box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3);
    }

    .action-card h3 {
        margin-bottom: 1rem;
    }

    .action-card p {
        opacity: 0.9;
        margin-bottom: 1.5rem;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 12px 24px;
        background: white;
        color: #ff6b6b;
        text-decoration: none;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s;
        border: 2px solid white;
    }

    .btn:hover {
        background: transparent;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    }

    .btn-secondary {
        background: transparent;
        color: white;
        border: 2px solid white;
    }

    .btn-secondary:hover {
        background: white;
        color: #ff6b6b;
    }

    /* Gallery Section */
    .gallery-section {
        margin-bottom: 3rem;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .gallery-item {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }

    .gallery-item:hover {
        transform: translateY(-5px);
    }

    .gallery-image {
        height: 200px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 3rem;
    }

    .gallery-content {
        padding: 1rem;
    }

    /* Testimonials */
    .testimonials-section {
        margin-bottom: 3rem;
    }

    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1.5rem;
    }

    .testimonial-card {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        position: relative;
    }

    .testimonial-card::before {
        content: '"';
        position: absolute;
        top: -10px;
        left: 20px;
        font-size: 4rem;
        color: #ff6b6b;
        opacity: 0.3;
        font-family: serif;
    }

    .testimonial-text {
        font-style: italic;
        color: #666;
        margin-bottom: 1rem;
        position: relative;
        z-index: 1;
    }

    .testimonial-author {
        font-weight: 600;
        color: #333;
    }

    .testimonial-date {
        font-size: 0.8rem;
        color: #999;
    }

    /* Related Packages */
    .related-section {
        margin-bottom: 3rem;
    }

    .related-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin-top: 1.5rem;
    }

    .related-card {
        background: white;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s;
    }

    .related-card:hover {
        transform: translateY(-5px);
    }

    .related-image {
        height: 150px;
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2rem;
    }

    .related-content {
        padding: 1.5rem;
    }

    .related-title {
        font-size: 1.1rem;
        font-weight: bold;
        color: #333;
        margin-bottom: 0.5rem;
    }

    .related-price {
        font-size: 1.3rem;
        font-weight: bold;
        color: #ff6b6b;
        margin-bottom: 1rem;
    }

    .related-category {
        background: #f8f9fa;
        color: #666;
        padding: 0.25rem 0.75rem;
        border-radius: 15px;
        font-size: 0.8rem;
        display: inline-block;
        margin-bottom: 1rem;
    }

    /* FAQ Section */
    .faq-section {
        background: white;
        border-radius: 20px;
        padding: 2.5rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        margin-bottom: 3rem;
    }

    .faq-item {
        border-bottom: 1px solid #eee;
        padding: 1.5rem 0;
    }

    .faq-item:last-child {
        border-bottom: none;
    }

    .faq-question {
        font-weight: 600;
        color: #333;
        margin-bottom: 0.5rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .faq-answer {
        color: #666;
        line-height: 1.6;
    }

    /* Footer */
    .footer {
        background: #333;
        color: white;
        text-align: center;
        padding: 3rem 0;
        margin-top: 4rem;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .package-title {
            font-size: 2rem;
        }

        .package-price {
            font-size: 2rem;
        }

        .package-details {
            grid-template-columns: 1fr;
            gap: 2rem;
        }

        .hero-stats {
            flex-direction: column;
            gap: 1rem;
        }

        .includes-grid {
            grid-template-columns: 1fr;
        }

        .nav-menu {
            display: none;
        }
    }

    /* Floating Action */
    .floating-action {
        position: fixed;
        bottom: 2rem;
        right: 2rem;
        z-index: 1000;
    }

    .floating-btn {
        background: linear-gradient(45deg, #ff6b6b, #ffa500);
        color: white;
        border: none;
        border-radius: 50px;
        padding: 1rem 2rem;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        box-shadow: 0 5px 20px rgba(255, 107, 107, 0.4);
        transition: all 0.3s;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .floating-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(255, 107, 107, 0.5);
    }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-ring"></i> Dandy Gallery Gown
            </a>
            <nav>
                <ul class="nav-menu">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="packages.php">Paket</a></li>
                    <li><a href="index.php#gallery">Gallery</a></li>
                    <li><a href="index.php#contact">Kontak</a></li>
                    <?php if (isLoggedIn()) : ?>
                    <?php if (isAdmin()) : ?>
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
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php"><i class="fas fa-home"></i> Home</a> /
                <a href="packages.php">Paket</a> /
                <a
                    href="packages.php?type=<?php echo $package['service_name'] === 'Baju Pengantin' ? 'dress' : 'makeup'; ?>">
                    <?php echo htmlspecialchars($package['service_name']); ?>
                </a> /
                <span class="current"><?php echo htmlspecialchars($package['name']); ?></span>
            </div>

            <!-- Package Hero -->
            <div class="package-hero"
                <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])) : ?>
                style="background-image: linear-gradient(rgba(255, 107, 107, 0.8), rgba(255, 165, 0, 0.8)), url('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>'); background-size: cover; background-position: center;"
                <?php endif; ?>>
                <div class="hero-content">
                    <?php if ($package['service_name']) : ?>
                    <div class="package-category">
                        <i class="<?php echo $package['service_icon']; ?>"></i>
                        <?php echo htmlspecialchars($package['service_name']); ?>
                    </div>
                    <?php endif; ?>

                    <h1 class="package-title"><?php echo htmlspecialchars($package['name']); ?></h1>
                    <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>

                    <?php if ($package['description']) : ?>
                    <p class="package-description"><?php echo htmlspecialchars($package['description']); ?></p>
                    <?php endif; ?>

                    <div class="hero-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                            <div class="stat-label">Total Booking</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $stats['completed_bookings']; ?></div>
                            <div class="stat-label">Event Selesai</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Package Details -->
            <div class="package-details">
                <div class="details-main">
                    <!-- What's Included -->
                    <?php if ($package['includes']) : ?>
                    <section>
                        <h2 class="section-title">
                            <i class="fas fa-check-circle"></i> Yang Termasuk dalam Paket
                        </h2>
                        <div class="includes-grid">
                            <?php 
                            $includes = explode(',', $package['includes']);
                            $icons = ['fas fa-crown', 'fas fa-palette', 'fas fa-cut', 'fas fa-gem', 'fas fa-camera', 'fas fa-car'];
                            foreach ($includes as $index => $include): 
                                ?>
                            <div class="include-item">
                                <div class="include-icon">
                                    <i class="<?php echo $icons[$index % count($icons)]; ?>"></i>
                                </div>
                                <span><?php echo trim(htmlspecialchars($include)); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- Package Description -->
                    <section style="margin-top: 3rem;">
                        <h2 class="section-title">
                            <i class="fas fa-info-circle"></i> Detail Paket
                        </h2>
                        <div style="background: #f8f9fa; padding: 2rem; border-radius: 15px; line-height: 1.8;">
                            <?php if ($package['description']) : ?>
                            <p><?php echo nl2br(htmlspecialchars($package['description'])); ?></p>
                            <?php else: ?>
                            <p>Paket wedding yang dirancang khusus untuk membuat hari spesial Anda menjadi tak
                                terlupakan. Dengan layanan profesional dan berkualitas tinggi, kami berkomitmen
                                memberikan pengalaman terbaik untuk pernikahan impian Anda.</p>
                            <?php endif; ?>

                            <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #dee2e6;">
                                <h4 style="color: #333; margin-bottom: 1rem;">
                                    <i class="fas fa-heart"></i> Mengapa Memilih Paket Ini?
                                </h4>
                                <ul style="list-style: none; padding: 0;">
                                    <li style="margin-bottom: 0.5rem;"><i class="fas fa-star"
                                            style="color: #ffa500; margin-right: 0.5rem;"></i> Tim profesional
                                        berpengalaman</li>
                                    <li style="margin-bottom: 0.5rem;"><i class="fas fa-star"
                                            style="color: #ffa500; margin-right: 0.5rem;"></i> Kualitas makeup dan dress
                                        terbaik</li>
                                    <li style="margin-bottom: 0.5rem;"><i class="fas fa-star"
                                            style="color: #ffa500; margin-right: 0.5rem;"></i> Service excellent dari
                                        awal hingga akhir</li>
                                    <li style="margin-bottom: 0.5rem;"><i class="fas fa-star"
                                            style="color: #ffa500; margin-right: 0.5rem;"></i> Harga terjangkau dengan
                                        kualitas premium</li>
                                </ul>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="details-sidebar">
                    <!-- Package Info -->
                    <div class="info-card">
                        <h3><i class="fas fa-info"></i> Informasi Paket</h3>
                        <div class="info-item">
                            <span class="info-label">Harga:</span>
                            <span class="info-value"><?php echo formatRupiah($package['price']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Jenis Layanan:</span>
                            <span class="info-value"><?php echo htmlspecialchars($package['service_name']); ?></span>
                        </div>
                        <?php if ($package['size_available']) : ?>
                        <div class="info-item">
                            <span class="info-label">Ukuran Tersedia:</span>
                            <span class="info-value"><?php echo htmlspecialchars($package['size_available']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($package['color_available']) : ?>
                        <div class="info-item">
                            <span class="info-label">Warna Tersedia:</span>
                            <span class="info-value"><?php echo htmlspecialchars($package['color_available']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <span class="info-label">Total Booking:</span>
                            <span class="info-value"><?php echo $stats['total_bookings']; ?>x</span>
                        </div>
                    </div>

                    <!-- Action Card -->
                    <div class="action-card">
                        <h3><i class="fas fa-calendar-heart"></i> Siap Booking?</h3>
                        <p>Jangan sampai kehabisan slot di hari spesial Anda. Book sekarang dan dapatkan pelayanan
                            terbaik!</p>

                        <?php if (isLoggedIn()) : ?>
                        <a href="customer/booking_new.php?package=<?php echo $package['id']; ?>" class="btn">
                            <i class="fas fa-calendar-plus"></i> Book Sekarang
                        </a>
                        <br><br>
                        <a href="customer/dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-user"></i> Dashboard Saya
                        </a>
                        <?php else: ?>
                        <a href="login.php" class="btn">
                            <i class="fas fa-sign-in-alt"></i> Login untuk Book
                        </a>
                        <br><br>
                        <a href="register.php" class="btn btn-secondary">
                            <i class="fas fa-user-plus"></i> Daftar Gratis
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Contact Info -->
                    <div class="info-card">
                        <h3><i class="fas fa-phone"></i> Butuh Konsultasi?</h3>
                        <p style="color: #666; margin-bottom: 1rem;">Tim kami siap membantu Anda memilih paket yang
                            tepat.</p>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <a href="tel:+628123456789" style="color: #ff6b6b; text-decoration: none;">
                                <i class="fas fa-phone"></i> +62 812-3456-7890
                            </a>
                            <a href="mailto:info@dandygallery.com" style="color: #ff6b6b; text-decoration: none;">
                                <i class="fas fa-envelope"></i> info@dandygallery.com
                            </a>
                            <a href="https://wa.me/628123456789" style="color: #ff6b6b; text-decoration: none;"
                                target="_blank">
                                <i class="fab fa-whatsapp"></i> Chat WhatsApp
                            </a>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Package Image Gallery (jika ada gambar) -->
            <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])) : ?>
            <section class="package-gallery-section" style="margin-bottom: 3rem;">
                <div
                    style="background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h2 class="section-title">
                        <i class="fas fa-camera"></i> Gallery Paket
                    </h2>

                    <div style="display: grid; grid-template-columns: 1fr; gap: 2rem; margin-top: 1.5rem;">
                        <!-- Main Package Image -->
                        <div
                            style="position: relative; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 25px rgba(0,0,0,0.1);">
                            <img src="uploads/packages/<?php echo htmlspecialchars($package['image']); ?>"
                                alt="<?php echo htmlspecialchars($package['name']); ?>"
                                style="width: 100%; max-height: 500px; object-fit: cover; border-radius: 15px;"
                                onclick="openImageModal(this.src)">

                            <!-- Image Overlay Info -->
                            <div
                                style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 2rem;">
                                <h3 style="font-size: 1.5rem; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($package['name']); ?></h3>
                                <p style="opacity: 0.9;">Klik untuk memperbesar gambar</p>
                            </div>

                            <!-- Zoom Icon -->
                            <div style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.9); color: #333; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer;"
                                onclick="openImageModal('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>')">
                                <i class="fas fa-search-plus"></i>
                            </div>
                        </div>

                        <!-- Image Details -->
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                            <h4 style="color: #333; margin-bottom: 1rem;">
                                <i class="fas fa-info-circle"></i> Detail Gambar
                            </h4>
                            <div
                                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                <div>
                                    <strong>Nama Paket:</strong><br>
                                    <span style="color: #666;"><?php echo htmlspecialchars($package['name']); ?></span>
                                </div>
                                <div>
                                    <strong>Kategori:</strong><br>
                                    <span
                                        style="color: #666;"><?php echo htmlspecialchars($package['service_name']); ?></span>
                                </div>
                                <div>
                                    <strong>Harga:</strong><br>
                                    <span
                                        style="color: #ff6b6b; font-weight: bold;"><?php echo formatRupiah($package['price']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- Testimonials -->
            <!-- <?php if (!empty($testimonials)) : ?>
<section class="testimonials-section">
    <div style="background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
        <h2 class="section-title">
            <i class="fas fa-heart"></i> Testimoni Customer
        </h2>
        <div class="testimonials-grid">
                <?php foreach ($testimonials as $testimonial): ?>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        <?php echo nl2br(htmlspecialchars($testimonial['special_request'])); ?>
                    </div>
                    <div class="testimonial-author">
                        <?php echo htmlspecialchars($testimonial['full_name']); ?>
                    </div>
                    <div class="testimonial-date">
                        <?php echo date('d M Y', strtotime($testimonial['created_at'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
        </div>
    </div>
</section>
           <?php endif; ?> -->

            <!-- Related Packages -->
            <?php if (!empty($related_packages)) : ?>
            <section class="related-section">
                <div
                    style="background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h2 class="section-title">
                        <i class="fas fa-layer-group"></i> Paket Serupa
                    </h2>
                    <div class="related-grid">
                        <?php foreach ($related_packages as $related): ?>
                        <div class="related-card">
                            <!-- PERUBAHAN: Tambah gambar untuk related packages -->
                            <?php if ($related['image'] && file_exists('uploads/packages/' . $related['image'])) : ?>
                            <div class="related-image"
                                style="background-image: url('uploads/packages/<?php echo htmlspecialchars($related['image']); ?>'); background-size: cover; background-position: center; position: relative;">
                                <!-- Overlay icon -->
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); color: #ff6b6b; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                    <i
                                        class="<?php echo $related['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="related-image">
                                <i
                                    class="<?php echo $related['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
                            </div>
                            <?php endif; ?>

                            <div class="related-content">
                                <!-- Sisa konten related tetap sama -->
                                <div class="related-category"><?php echo htmlspecialchars($related['service_name']); ?>
                                </div>
                                <h3 class="related-title"><?php echo htmlspecialchars($related['name']); ?></h3>
                                <div class="related-price"><?php echo formatRupiah($related['price']); ?></div>
                                <p style="color: #666; margin-bottom: 1rem; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars(substr($related['description'], 0, 100)) . '...'; ?>
                                </p>
                                <a href="package_detail.php?id=<?php echo $related['id']; ?>" class="btn"
                                    style="background: #ff6b6b; color: white; font-size: 0.9rem; padding: 8px 16px;">
                                    <i class="fas fa-eye"></i> Lihat Detail
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- FAQ Section -->
            <!-- <section class="faq-section">
                <h2 class="section-title">
                    <i class="fas fa-question-circle"></i> Pertanyaan yang Sering Diajukan
                </h2>
                
                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Apakah harga sudah termasuk semua layanan yang disebutkan?
                    </div>
                    <div class="faq-answer">
                        Ya, semua layanan yang tercantum dalam paket sudah termasuk dalam harga. Tidak ada biaya tambahan tersembunyi. Namun, untuk permintaan khusus di luar paket standar mungkin dikenakan biaya tambahan.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Berapa lama waktu yang dibutuhkan untuk proses makeup dan dress?
                    </div>
                    <div class="faq-answer">
                        Proses makeup dan dress membutuhkan waktu sekitar 2-3 jam untuk pengantin. Kami menyarankan untuk memulai persiapan 4 jam sebelum acara dimulai agar tidak terburu-buru.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Apakah bisa trial makeup sebelum hari H?
                    </div>
                    <div class="faq-answer">
                        Tentu saja! Kami sangat menyarankan trial makeup 1-2 minggu sebelum hari pernikahan. Trial makeup dikenakan biaya terpisah, namun jika Anda booking paket dengan kami, biaya trial akan mendapat diskon khusus.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Bagaimana sistem pembayaran dan kapan harus melunasi?
                    </div>
                    <div class="faq-answer">
                        Sistem pembayaran bisa dilakukan dengan DP 50% saat booking, dan pelunasan maksimal H-7 sebelum acara. Kami menerima pembayaran via transfer bank, e-wallet, atau cash.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Apakah ada garansi jika tidak puas dengan hasil?
                    </div>
                    <div class="faq-answer">
                        Kami berkomitmen memberikan hasil terbaik. Jika ada ketidakpuasan, kami akan melakukan touch-up atau perbaikan tanpa biaya tambahan. Kepuasan Anda adalah prioritas utama kami.
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question">
                        <i class="fas fa-chevron-right"></i> Apakah melayani acara di luar kota?
                    </div>
                    <div class="faq-answer">
                        Ya, kami melayani acara di luar kota dengan biaya transportasi dan akomodasi tambahan sesuai jarak dan lokasi. Silakan konsultasi dengan tim kami untuk detail biaya dan ketentuan.
                    </div>
                </div>
            </section> -->

            <!-- Gallery Section -->
            <!-- <section class="gallery-section">
                <div style="background: white; border-radius: 20px; padding: 2.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                    <h2 class="section-title">
                        <i class="fas fa-images"></i> Gallery Portfolio
                    </h2>
                    <div class="gallery-grid">
                        <div class="gallery-item">
                            <div class="gallery-image">
                                <i class="fas fa-camera"></i>
                            </div>
                            <div class="gallery-content">
                                <h4>Wedding Akad Nikah</h4>
                                <p>Dokumentasi moment sakral akad nikah dengan makeup natural dan elegan</p>
                            </div>
                        </div>
                        <div class="gallery-item">
                            <div class="gallery-image">
                                <i class="fas fa-ring"></i>
                            </div>
                            <div class="gallery-content">
                                <h4>Resepsi Pernikahan</h4>
                                <p>Tampilan glamour untuk resepsi dengan dress mewah dan makeup flawless</p>
                            </div>
                        </div>
                        <div class="gallery-item">
                            <div class="gallery-image">
                                <i class="fas fa-crown"></i>
                            </div>
                            <div class="gallery-content">
                                <h4>Traditional Wedding</h4>
                                <p>Konsep pernikahan adat dengan sentuhan modern yang memukau</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section> -->

            <!-- Call to Action -->
            <section
                style="background: linear-gradient(135deg, #ff6b6b, #ffa500); color: white; padding: 3rem; border-radius: 20px; text-align: center; margin: 3rem 0;">
                <h2 style="font-size: 2.5rem; margin-bottom: 1rem;">Wujudkan Pernikahan Impian Anda</h2>
                <p
                    style="font-size: 1.2rem; opacity: 0.9; margin-bottom: 2rem; max-width: 600px; margin-left: auto; margin-right: auto;">
                    Jangan biarkan hari spesial Anda berlalu begitu saja. Percayakan kepada kami untuk memberikan
                    pengalaman terbaik yang akan selalu Anda kenang selamanya.
                </p>
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <?php if (isLoggedIn()) : ?>
                    <a href="customer/booking_new.php?package=<?php echo $package['id']; ?>" class="btn"
                        style="background: white; color: #ff6b6b; font-size: 1.1rem; padding: 15px 30px;">
                        <i class="fas fa-calendar-check"></i> Book Paket Ini
                    </a>
                    <?php else: ?>
                    <a href="login.php" class="btn"
                        style="background: white; color: #ff6b6b; font-size: 1.1rem; padding: 15px 30px;">
                        <i class="fas fa-sign-in-alt"></i> Login untuk Book
                    </a>
                    <?php endif; ?>
                    <a href="https://wa.me/628123456789" class="btn btn-secondary"
                        style="font-size: 1.1rem; padding: 15px 30px;" target="_blank">
                        <i class="fab fa-whatsapp"></i> Konsultasi Gratis
                    </a>
                </div>
            </section>
        </div>
    </main>

    <!-- Floating Action Button -->
    <div class="floating-action">
        <?php if (isLoggedIn()) : ?>
        <a href="customer/booking_new.php?package=<?php echo $package['id']; ?>" class="floating-btn">
            <i class="fas fa-calendar-plus"></i> Book Now
        </a>
        <?php else: ?>
        <a href="login.php" class="floating-btn">
            <i class="fas fa-sign-in-alt"></i> Login to Book
        </a>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem; text-align: left;">
                <div>
                    <h3 style="margin-bottom: 1rem; color: #ff6b6b;">
                        <i class="fas fa-ring"></i> Dandy Gallery Gown
                    </h3>
                    <p style="opacity: 0.8; line-height: 1.6;">
                        Spesialis wedding organizer dan bridal makeup terpercaya. Memberikan layanan terbaik untuk
                        mewujudkan pernikahan impian Anda.
                    </p>
                </div>
                <div>
                    <h4 style="margin-bottom: 1rem;">Layanan</h4>
                    <ul style="list-style: none; opacity: 0.8;">
                        <li style="margin-bottom: 0.5rem;">Bridal Makeup</li>
                        <li style="margin-bottom: 0.5rem;">Wedding Dress</li>
                        <li style="margin-bottom: 0.5rem;">Dekorasi</li>
                        <li style="margin-bottom: 0.5rem;">Dokumentasi</li>
                    </ul>
                </div>
                <div>
                    <h4 style="margin-bottom: 1rem;">Kontak</h4>
                    <ul style="list-style: none; opacity: 0.8;">
                        <li style="margin-bottom: 0.5rem;">
                            <i class="fas fa-phone"></i> +62 812-3456-7890
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <i class="fas fa-envelope"></i> info@dandygallery.com
                        </li>
                        <li style="margin-bottom: 0.5rem;">
                            <i class="fas fa-map-marker-alt"></i> Padang, Sumatera Barat
                        </li>
                    </ul>
                </div>
                <div>
                    <h4 style="margin-bottom: 1rem;">Follow Us</h4>
                    <div style="display: flex; gap: 1rem;">
                        <a href="#" style="color: white; font-size: 1.5rem; opacity: 0.8;">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" style="color: white; font-size: 1.5rem; opacity: 0.8;">
                            <i class="fab fa-facebook"></i>
                        </a>
                        <a href="#" style="color: white; font-size: 1.5rem; opacity: 0.8;">
                            <i class="fab fa-whatsapp"></i>
                        </a>
                        <a href="#" style="color: white; font-size: 1.5rem; opacity: 0.8;">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                </div>
            </div>
            <div style="border-top: 1px solid #555; padding-top: 2rem; opacity: 0.8;">
                <p>&copy; <?php echo date('Y'); ?> Dandy Gallery Gown. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // FAQ Toggle
    document.querySelectorAll('.faq-question').forEach(question => {
        question.addEventListener('click', () => {
            const answer = question.nextElementSibling;
            const icon = question.querySelector('i');

            if (answer.style.display === 'block') {
                answer.style.display = 'none';
                icon.style.transform = 'rotate(0deg)';
            } else {
                // Hide all other answers
                document.querySelectorAll('.faq-answer').forEach(ans => {
                    ans.style.display = 'none';
                });
                document.querySelectorAll('.faq-question i').forEach(ic => {
                    ic.style.transform = 'rotate(0deg)';
                });

                // Show current answer
                answer.style.display = 'block';
                icon.style.transform = 'rotate(90deg)';
            }
        });
    });

    // Smooth scroll for anchor links
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

    // Floating button hide on scroll down
    let lastScrollTop = 0;
    const floatingBtn = document.querySelector('.floating-action');

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset || document.documentElement.scrollTop;

        if (currentScroll > lastScrollTop && currentScroll > 100) {
            // Scrolling down
            floatingBtn.style.transform = 'translateY(100px)';
        } else {
            // Scrolling up
            floatingBtn.style.transform = 'translateY(0)';
        }

        lastScrollTop = currentScroll <= 0 ? 0 : currentScroll;
    });

    // Mobile menu toggle (if needed)
    const navToggle = document.querySelector('.nav-toggle');
    const navMenu = document.querySelector('.nav-menu');

    if (navToggle) {
        navToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // Loading animation for buttons
    document.querySelectorAll('.btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.href && !this.href.includes('tel:') && !this.href.includes('mailto:') && !this.href
                .includes('wa.me')) {
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
            }
        });
    });

    // Add entrance animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, observerOptions);

    // Observe elements for animation
    document.querySelectorAll('.testimonial-card, .related-card, .gallery-item, .info-card').forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
        observer.observe(el);
    });


    // Image Modal Functions
    function openImageModal(imageSrc) {
        // Create modal if not exists
        let modal = document.getElementById('imageModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'imageModal';
            modal.className = 'image-modal';
            modal.innerHTML = `
            <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
            <img class="image-modal-content" id="modalImage">
        `;
            document.body.appendChild(modal);

            // Close modal when clicking outside image
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    closeImageModal();
                }
            });

            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeImageModal();
                }
            });
        }

        // Set image source and show modal
        document.getElementById('modalImage').src = imageSrc;
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden'; // Prevent scrolling
    }


    function closeImageModal() {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Re-enable scrolling
        }
    }

    // Lazy loading untuk gambar paket (optional enhancement)
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('img[data-src]');
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        images.forEach(img => imageObserver.observe(img));
    });

    // Image error handling
    document.addEventListener('DOMContentLoaded', function() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            img.addEventListener('error', function() {
                // Replace broken image with placeholder
                this.style.display = 'none';
                const placeholder = document.createElement('div');
                placeholder.style.cssText = `
                width: 100%; 
                height: 250px; 
                background: linear-gradient(45deg, #ff6b6b, #ffa500); 
                display: flex; 
                align-items: center; 
                justify-content: center; 
                color: white; 
                font-size: 3rem;
                border-radius: 15px;
            `;
                placeholder.innerHTML = '<i class="fas fa-image"></i>';
                this.parentNode.insertBefore(placeholder, this);
            });
        });
    });
    </script>
</body>

</html>