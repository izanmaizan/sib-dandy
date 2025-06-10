<!-- index.php  -->
<?php
session_start();
require_once 'config/database.php';

$db = getDB();

// Ambil jenis layanan
// $stmt = $db->query("SELECT * FROM service_types ORDER BY id");
// $service_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil paket dress terbaru
$stmt = $db->prepare("SELECT p.*, p.service_type as service_name FROM packages p 
                     WHERE p.is_active = 1 AND p.service_type = 'Baju Pengantin' 
                     ORDER BY p.created_at DESC LIMIT 3");
$stmt->execute();
$dress_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil paket makeup terbaru
$stmt = $db->prepare("SELECT p.*, p.service_type as service_name FROM packages p 
                     WHERE p.is_active = 1 AND p.service_type = 'Makeup Pengantin' 
                     ORDER BY p.created_at DESC LIMIT 3");
$stmt->execute();
$makeup_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil gallery featured
$stmt = $db->prepare("SELECT g.*, g.service_type as service_name FROM gallery g 
                     WHERE g.is_featured = 1 ORDER BY g.created_at DESC LIMIT 6");
$stmt->execute();
$gallery = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dandy Gallery Gown - Rental Baju Pengantin & Jasa Makeup</title>
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Hero Section */
        .hero {
            background-image: url('./uploads/hero.jpeg');
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            background-size: cover;
            background-position: center;
        }

        .hero-content h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }

        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            transition: transform 0.3s, box-shadow 0.3s;
            font-weight: 600;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-outline:hover {
            background: white;
            color: #ff6b6b;
        }

        /* Services Section */
        .services-section {
            padding: 5rem 0;
            background: #f8f9fa;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #333;
        }

        .section-subtitle {
            text-align: center;
            font-size: 1.1rem;
            color: #666;
            margin-bottom: 3rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 3rem;
            margin-top: 3rem;
        }

        .service-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .service-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .service-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .service-title {
            font-size: 1.8rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .service-body {
            padding: 2rem;
        }

        /* Packages Grid */
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .package-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .package-card:hover {
            transform: translateY(-5px);
        }

        .package-image {
            height: 200px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
        }

        .package-content {
            padding: 1.5rem;
        }

        .package-content h4 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .package-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }

        .package-includes {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        /* Gallery Grid */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .gallery-item {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .gallery-item:hover {
            transform: scale(1.05);
        }

        .gallery-image {
            height: 250px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            position: relative;
        }

        .gallery-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .gallery-content {
            padding: 1.5rem;
        }

        /* Section */
        .section {
            padding: 5rem 0;
        }

        /* Footer */
        .footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 3rem 0;
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .contact-item {
            text-align: center;
        }

        .contact-item i {
            font-size: 2rem;
            color: #ff6b6b;
            margin-bottom: 1rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2.5rem;
            }
            
            .nav-menu {
                display: none;
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
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
                    <li><a href="#home">Home</a></li>
                    <li><a href="#services">Layanan</a></li>
                    <li><a href="#gallery">Gallery</a></li>
                    <li><a href="#contact">Kontak</a></li>
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

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="hero-content">
            <h1>Dandy Gallery Gown</h1>
            <p>Sewa Baju Pengantin Premium & Jasa Makeup Profesional untuk Hari Spesial Anda</p>
            <div class="hero-buttons">
                <a href="#services" class="btn">Lihat Layanan Kami</a>
                <a href="packages.php" class="btn btn-outline">Jelajahi Paket</a>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="services-section">
        <div class="container">
            <h2 class="section-title">Layanan Kami</h2>
            <p class="section-subtitle">
                Kami menyediakan dua layanan utama yang dapat Anda pilih sesuai kebutuhan - 
                sewa baju pengantin eksklusif dan jasa makeup profesional
            </p>
            
            <div class="services-grid">
                <!-- Layanan Baju Pengantin -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-icon">
                            <i class="fas fa-tshirt"></i>
                        </div>
                        <h3 class="service-title">Sewa Baju Pengantin</h3>
                        <p>Koleksi gaun pengantin premium dengan berbagai model dan ukuran</p>
                    </div>
                    <div class="service-body">
                        <div class="packages-grid">
                            <?php foreach ($dress_packages as $package): ?>
                            <div class="package-card">
    <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])): ?>
        <div class="package-image" style="background-image: url('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>'); background-size: cover; background-position: center; position: relative;">
            <!-- Icon overlay untuk branding -->
            <!-- <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); color: #ff6b6b; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
            </div> -->
        </div>
    <?php else: ?>
        <div class="package-image">
            <i class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
        </div>
    <?php endif; ?>
                                <div class="package-content">
                                    <h4><?php echo htmlspecialchars($package['name']); ?></h4>
                                    <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>
                                    <p class="package-includes"><?php echo htmlspecialchars(substr($package['includes'], 0, 80)) . '...'; ?></p>
                                    <a href="package_detail.php?id=<?php echo $package['id']; ?>" class="btn">Lihat Detail</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="text-align: center; margin-top: 2rem;">
                            <a href="packages.php?type=dress" class="btn">Lihat Semua Gaun Pengantin</a>
                        </div>
                    </div>
                </div>

                <!-- Layanan Makeup -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-icon">
                            <i class="fas fa-palette"></i>
                        </div>
                        <h3 class="service-title">Jasa Makeup Pengantin</h3>
                        <p>Makeup profesional dengan berbagai style sesuai tema pernikahan</p>
                    </div>
                    <div class="service-body">
                        <div class="packages-grid">
                            <?php foreach ($makeup_packages as $package): ?>
                            <div class="package-card">
    <?php if ($package['image'] && file_exists('uploads/packages/' . $package['image'])): ?>
        <div class="package-image" style="background-image: url('uploads/packages/<?php echo htmlspecialchars($package['image']); ?>'); background-size: cover; background-position: center; position: relative;">
            <!-- Icon overlay untuk branding -->
            <!-- <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(255,255,255,0.9); color: #ff6b6b; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem;">
                <i class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-palette'; ?>"></i>
            </div> -->
        </div>
    <?php else: ?>
        <div class="package-image">
            <i class="<?php echo $package['service_name'] === 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-palette'; ?>"></i>
        </div>
    <?php endif; ?>

                                <div class="package-content">
                                    <h4><?php echo htmlspecialchars($package['name']); ?></h4>
                                    <div class="package-price"><?php echo formatRupiah($package['price']); ?></div>
                                    <p class="package-includes"><?php echo htmlspecialchars(substr($package['includes'], 0, 80)) . '...'; ?></p>
                                    <a href="package_detail.php?id=<?php echo $package['id']; ?>" class="btn">Lihat Detail</a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div style="text-align: center; margin-top: 2rem;">
                            <a href="packages.php?type=makeup" class="btn">Lihat Semua Paket Makeup</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
<section id="gallery" class="section" style="background: #f8f9fa;">
    <div class="container">
        <h2 class="section-title">Gallery Portfolio</h2>
        <p class="section-subtitle">Lihat hasil karya terbaik kami dalam koleksi baju pengantin dan makeup profesional</p>
        <div class="gallery-grid">
            <?php foreach ($gallery as $item): ?>
            <div class="gallery-item">
                <?php if ($item['image'] && file_exists('uploads/gallery/' . $item['image'])): ?>
                    <div class="gallery-image" style="background-image: url('uploads/gallery/<?php echo htmlspecialchars($item['image']); ?>'); background-size: cover; background-position: center; position: relative;">
                        <div class="gallery-badge"><?php echo htmlspecialchars($item['service_name']); ?></div>
                        <!-- Optional overlay icon -->
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.5); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; opacity: 0; transition: opacity 0.3s;">
                            <i class="<?php echo $item['service_name'] == 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="gallery-image">
                        <div class="gallery-badge"><?php echo htmlspecialchars($item['service_name']); ?></div>
                        <i class="<?php echo $item['service_name'] == 'Baju Pengantin' ? 'fas fa-wedding-dress' : 'fas fa-brush'; ?>"></i>
                    </div>
                <?php endif; ?>
                <div class="gallery-content">
                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                    <p><?php echo htmlspecialchars($item['description']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Tampilkan pesan jika gallery kosong -->
        <?php if (empty($gallery)): ?>
        <div style="text-align: center; padding: 3rem; color: #666;">
            <i class="fas fa-images" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3>Portfolio Coming Soon</h3>
            <p>Kami sedang mempersiapkan galeri portfolio terbaik untuk Anda.</p>
        </div>
        <?php endif; ?>
        
        <!-- Link untuk melihat gallery lengkap -->
        <?php if (!empty($gallery)): ?>
        <div style="text-align: center; margin-top: 3rem;">
            <a href="gallery.php" class="btn">
                <i class="fas fa-images"></i> Lihat Semua Portfolio
            </a>
        </div>
        <?php endif; ?>
    </div>
</section>

    <!-- Why Choose Us Section -->
    <!-- <section class="section">
        <div class="container">
            <h2 class="section-title">Mengapa Memilih Dandy Gallery?</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-top: 3rem;">
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(45deg, #ff6b6b, #ffa500); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                        <i class="fas fa-award"></i>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: #333;">Kualitas Premium</h3>
                    <p style="color: #666;">Koleksi gaun pengantin berkualitas tinggi dan makeup artist berpengalaman</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(45deg, #ff6b6b, #ffa500); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: #333;">Layanan Fleksibel</h3>
                    <p style="color: #666;">Booking mudah dengan waktu yang fleksibel sesuai jadwal acara Anda</p>
                </div>
                <div style="text-align: center; padding: 2rem;">
                    <div style="width: 80px; height: 80px; background: linear-gradient(45deg, #ff6b6b, #ffa500); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; color: white; font-size: 2rem;">
                        <i class="fas fa-heart"></i>
                    </div>
                    <h3 style="margin-bottom: 1rem; color: #333;">Pelayanan Terbaik</h3>
                    <p style="color: #666;">Tim profesional yang siap membantu mewujudkan penampilan impian Anda</p>
                </div>
            </div>
        </div>
    </section> -->

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <h2 class="section-title">Hubungi Kami</h2>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Alamat</h3>
                    <p>Jl. Pengantin Bahagia No. 123<br>Jakarta Selatan, Indonesia</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <h3>Telepon</h3>
                    <p>+62 812-3456-7890<br>+62 21-7654-3210</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p>info@dandygallery.com<br>booking@dandygallery.com</p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <h3>Jam Buka</h3>
                    <p>Senin - Sabtu: 09:00 - 21:00<br>Minggu: 10:00 - 18:00</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; 2025 Dandy Gallery Gown. All rights reserved.</p>
            <p>Sistem Informasi Booking Sewa Baju Pengantin & Jasa Makeup Profesional</p>
        </div>
    </footer>

    <script>
        // Smooth scrolling untuk navigation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
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
    </script>
</body>
</html>