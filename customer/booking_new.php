<!-- customer/booking_new.php  -->
<?php
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

// Ambil paket yang dipilih (jika ada)
$selected_package = null;
if (isset($_GET['package'])) {
    $stmt = $db->prepare("SELECT p.*, p.service_type as service_name FROM packages p 
                         WHERE p.id = ? AND p.is_active = 1");
    $stmt->execute([$_GET['package']]);
    $selected_package = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Ambil semua paket untuk dropdown
$stmt = $db->query("SELECT p.*, p.service_type as service_name FROM packages p 
                   WHERE p.is_active = 1 ORDER BY p.service_type, p.name");
$all_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$error = '';
$success = '';

if ($_POST) {
    $package_id = (int)$_POST['package_id'];
    $usage_date = $_POST['usage_date'];
    $venue_address = trim($_POST['venue_address']);
    $special_request = trim($_POST['special_request']);

    // Hapus field dress dan makeup terpisah - masukkan ke special_request
    $dress_size = isset($_POST['dress_size']) ? trim($_POST['dress_size']) : null;
    $dress_color = isset($_POST['dress_color']) ? trim($_POST['dress_color']) : null;
    $makeup_style = isset($_POST['makeup_style']) ? trim($_POST['makeup_style']) : null;

    // Gabungkan request khusus
    $combined_request = [];
    if ($dress_size) $combined_request[] = "Ukuran dress: " . $dress_size;
    if ($dress_color) $combined_request[] = "Warna dress: " . $dress_color;
    if ($makeup_style) $combined_request[] = "Style makeup: " . $makeup_style;
    if ($special_request) $combined_request[] = $special_request;

    $final_special_request = implode("\n", $combined_request);

    // Validasi
    if (empty($package_id) || empty($usage_date)) {
        $error = 'Mohon isi semua field yang wajib!';
    } elseif (strtotime($usage_date) <= time()) {
        $error = 'Tanggal penggunaan harus lebih dari hari ini!';
    } else {
        // Ambil data paket
        $stmt = $db->prepare("SELECT p.*, p.service_type as service_name FROM packages p 
                     WHERE p.id = ?");
        $stmt->execute([$package_id]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$package) {
            $error = 'Paket tidak ditemukan!';
        } else {
            // Generate booking code
            $booking_code = generateBookingCode();

            // Hitung pembayaran
            $total_amount = $package['price'];
            $down_payment = $total_amount * 0.3; // DP 30%
            $remaining_payment = $total_amount - $down_payment;

            try {
                $db->beginTransaction();

                // Insert booking - DIPERBAIKI: tambahkan service_type
                $stmt = $db->prepare("INSERT INTO bookings 
        (booking_code, user_id, package_id, service_type, booking_date, usage_date, 
         venue_address, special_request, total_amount, down_payment, remaining_payment, status) 
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, 'pending')");

                $stmt->execute([
                    $booking_code,
                    $_SESSION['user_id'],
                    $package_id,
                    $package['service_name'], // TAMBAHAN: service_type dari package
                    $usage_date,
                    $venue_address,
                    $final_special_request,
                    $total_amount,
                    $down_payment,
                    $remaining_payment
                ]);

                $booking_id = $db->lastInsertId();

                $db->commit();

                // Redirect ke halaman detail booking
                header("Location: booking_detail.php?id=$booking_id&success=1");
                exit();
            } catch (Exception $e) {
                $db->rollBack();
                $error = 'Terjadi kesalahan saat membuat booking: ' . $e->getMessage();
            }
        }
    }
}


// Perbaikan function generateBookingCode jika belum ada
if (!function_exists('generateBookingCode')) {
    function generateBookingCode()
    {
        return 'BK' . date('Ymd') . rand(1000, 9999);
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Baru - Dandy Gallery Gown</title>
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

        .back-btn {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: opacity 0.3s;
        }

        .back-btn:hover {
            opacity: 0.8;
        }

        .main-content {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            margin-bottom: 2rem;
        }

        .form-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .form-body {
            padding: 2rem;
        }

        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid #eee;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.3rem;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 600;
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
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ff6b6b;
            box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.1);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .package-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .package-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .package-detail-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
        }

        .package-detail-label {
            color: #666;
        }

        .package-detail-value {
            font-weight: 600;
            color: #333;
        }

        .price-breakdown {
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 1rem;
        }

        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .price-item:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 1.2rem;
            font-weight: bold;
        }

        .alert {
            padding: 12px 15px;
            margin-bottom: 1rem;
            border-radius: 10px;
        }

        .alert.error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert.success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .btn {
            padding: 12px 30px;
            background: linear-gradient(45deg, #ff6b6b, #ffa500);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1rem;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-secondary {
            background: #6c757d;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            flex-wrap: wrap;
        }

        .size-options,
        .color-options {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .option-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #e1e5e9;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .option-btn:hover,
        .option-btn.selected {
            border-color: #ff6b6b;
            background: #ff6b6b;
            color: white;
        }

        .help-text {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .package-details {
                grid-template-columns: 1fr;
            }

            .main-content {
                padding: 0 1rem;
            }

            .form-actions {
                flex-direction: column;
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
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Kembali ke Dashboard
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <h1 class="page-title">Booking Baru</h1>
        <p class="page-subtitle">Isi form di bawah untuk membuat booking paket baju pengantin atau makeup</p>

        <div class="form-container">
            <div class="form-header">
                <h2><i class="fas fa-calendar-plus"></i> Form Booking</h2>
                <p>Pastikan semua data yang Anda masukkan sudah benar</p>
            </div>

            <div class="form-body">
                <?php if ($error): ?>
                    <div class="alert error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="bookingForm">
                    <!-- Pilih Paket -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-box"></i> Pilih Paket
                        </h3>

                        <div class="form-group">
                            <label for="package_id">Paket Layanan <span class="required">*</span></label>
                            <select id="package_id" name="package_id" required onchange="updatePackageInfo()">
                                <option value="">-- Pilih Paket --</option>
                                <?php
                                $current_service = '';
                                foreach ($all_packages as $package):
                                    if ($package['service_name'] !== $current_service):
                                        if ($current_service !== '') echo '</optgroup>';
                                        echo '<optgroup label="' . htmlspecialchars($package['service_name']) . '">';
                                        $current_service = $package['service_name'];
                                    endif;
                                ?>
                                    <option value="<?php echo $package['id']; ?>"
                                        data-price="<?php echo $package['price']; ?>"
                                        data-service="<?php echo htmlspecialchars($package['service_name']); ?>"
                                        data-includes="<?php echo htmlspecialchars($package['includes']); ?>"
                                        data-sizes="<?php echo htmlspecialchars($package['size_available'] ?? ''); ?>"
                                        data-colors="<?php echo htmlspecialchars($package['color_available'] ?? ''); ?>"
                                        <?php echo ($selected_package && $selected_package['id'] == $package['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($package['name']); ?> -
                                        <?php echo formatRupiah($package['price']); ?>
                                    </option>
                                <?php endforeach; ?>
                                <?php if ($current_service !== '') echo '</optgroup>'; ?>
                            </select>
                        </div>

                        <div id="packageInfo" style="display: <?php echo $selected_package ? 'block' : 'none'; ?>">
                            <!-- Package info akan diisi via JavaScript -->
                        </div>
                    </div>

                    <!-- Detail Booking -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Detail Booking
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="usage_date">Tanggal Penggunaan <span class="required">*</span></label>
                                <input type="date" id="usage_date" name="usage_date" required
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                    value="<?php echo isset($_POST['usage_date']) ? $_POST['usage_date'] : ''; ?>">
                                <div class="help-text">Tanggal minimal H+1 dari hari ini</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="venue_address">Alamat Lokasi Acara</label>
                            <textarea id="venue_address" name="venue_address"
                                placeholder="Masukkan alamat lengkap venue acara (untuk makeup bisa ke lokasi)"><?php echo isset($_POST['venue_address']) ? htmlspecialchars($_POST['venue_address']) : ''; ?></textarea>
                            <div class="help-text">Opsional - khusus untuk makeup, bisa dikerjakan di lokasi acara</div>
                        </div>
                    </div>

                    <!-- Detail Khusus Dress -->
                    <div class="form-section" id="dressDetails" style="display: none;">
                        <h3 class="section-title">
                            <i class="fas fa-tshirt"></i> Detail Baju Pengantin
                        </h3>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="dress_size">Ukuran Dress</label>
                                <input type="hidden" id="dress_size" name="dress_size">
                                <div id="sizeOptions" class="size-options">
                                    <!-- Size options akan diisi via JavaScript -->
                                </div>
                                <div class="help-text">Pilih ukuran yang sesuai dengan tubuh pengantin</div>
                            </div>

                            <div class="form-group">
                                <label for="dress_color">Warna Dress</label>
                                <input type="hidden" id="dress_color" name="dress_color">
                                <div id="colorOptions" class="color-options">
                                    <!-- Color options akan diisi via JavaScript -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detail Khusus Makeup -->
                    <div class="form-section" id="makeupDetails" style="display: none;">
                        <h3 class="section-title">
                            <i class="fas fa-palette"></i> Detail Makeup
                        </h3>

                        <div class="form-group">
                            <label for="makeup_style">Gaya Makeup yang Diinginkan</label>
                            <select id="makeup_style" name="makeup_style">
                                <option value="">-- Pilih Gaya Makeup --</option>
                                <option value="Natural">Natural</option>
                                <option value="Glamour">Glamour</option>
                                <option value="Korean Style">Korean Style</option>
                                <option value="Vintage">Vintage</option>
                                <option value="Bold">Bold</option>
                                <option value="Tradisional">Tradisional</option>
                                <option value="Custom">Custom (sesuai request)</option>
                            </select>
                            <div class="help-text">Pilih gaya makeup yang sesuai dengan tema pernikahan</div>
                        </div>
                    </div>

                    <!-- Permintaan Khusus -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-comment"></i> Permintaan Khusus
                        </h3>

                        <div class="form-group">
                            <label for="special_request">Catatan Tambahan</label>
                            <textarea id="special_request" name="special_request"
                                placeholder="Tuliskan permintaan khusus, ukuran dress, warna, style makeup, alergi, atau catatan penting lainnya..."><?php echo isset($_POST['special_request']) ? htmlspecialchars($_POST['special_request']) : ''; ?></textarea>
                            <div class="help-text">Opsional - berikan informasi tambahan termasuk preferensi ukuran,
                                warna, dan style</div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Batal
                        </a>
                        <button type="submit" class="btn">
                            <i class="fas fa-check"></i> Buat Booking
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Data paket untuk JavaScript
        const packages = <?php echo json_encode($all_packages); ?>;

        function updatePackageInfo() {
            const select = document.getElementById('package_id');
            const packageInfo = document.getElementById('packageInfo');
            const dressDetails = document.getElementById('dressDetails');
            const makeupDetails = document.getElementById('makeupDetails');

            if (select.value === '') {
                packageInfo.style.display = 'none';
                dressDetails.style.display = 'none';
                makeupDetails.style.display = 'none';
                return;
            }

            const selectedOption = select.selectedOptions[0];
            const price = parseFloat(selectedOption.dataset.price);
            const service = selectedOption.dataset.service;
            const includes = selectedOption.dataset.includes;
            const sizes = selectedOption.dataset.sizes;
            const colors = selectedOption.dataset.colors;

            // Update package info
            const downPayment = price * 0.3;
            const remaining = price - downPayment;

            packageInfo.innerHTML = `
        <div class="package-info">
            <h4 style="margin-bottom: 1rem; color: #333;">
                <i class="${service === 'Baju Pengantin' ? 'fas fa-tshirt' : 'fas fa-palette'}"></i> 
                ${selectedOption.text.split(' - ')[0]}
            </h4>
            <div class="package-details">
                <div class="package-detail-item">
                    <span class="package-detail-label">Jenis Layanan:</span>
                    <span class="package-detail-value">${service}</span>
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <strong>Termasuk dalam paket:</strong>
                <p style="color: #666; margin-top: 0.5rem;">${includes}</p>
            </div>
            <div class="price-breakdown">
                <div class="price-item">
                    <span>Harga Paket:</span>
                    <span>${formatRupiah(price)}</span>
                </div>
                <div class="price-item">
                    <span>DP (30%):</span>
                    <span>${formatRupiah(downPayment)}</span>
                </div>
                <div class="price-item">
                    <span>Sisa Pembayaran:</span>
                    <span>${formatRupiah(remaining)}</span>
                </div>
                <div class="price-item">
                    <span>Total:</span>
                    <span>${formatRupiah(price)}</span>
                </div>
            </div>
        </div>
    `;

            packageInfo.style.display = 'block';

            // Show/hide specific details
            if (service === 'Baju Pengantin') {
                dressDetails.style.display = 'block';
                makeupDetails.style.display = 'none';

                // Update size options
                if (sizes) {
                    const sizeArray = sizes.split(',');
                    document.getElementById('sizeOptions').innerHTML = sizeArray.map(size =>
                        `<button type="button" class="option-btn" onclick="selectSize('${size.trim()}')">${size.trim()}</button>`
                    ).join('');
                }

                // Update color options
                if (colors) {
                    const colorArray = colors.split(',');
                    document.getElementById('colorOptions').innerHTML = colorArray.map(color =>
                        `<button type="button" class="option-btn" onclick="selectColor('${color.trim()}')">${color.trim()}</button>`
                    ).join('');
                }
            } else {
                dressDetails.style.display = 'none';
                makeupDetails.style.display = 'block';
            }
        }

        function selectSize(size) {
            document.getElementById('dress_size').value = size;
            document.querySelectorAll('#sizeOptions .option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
        }

        function selectColor(color) {
            document.getElementById('dress_color').value = color;
            document.querySelectorAll('#colorOptions .option-btn').forEach(btn => {
                btn.classList.remove('selected');
            });
            event.target.classList.add('selected');
        }

        function formatRupiah(angka) {
            return 'Rp ' + angka.toLocaleString('id-ID');
        }

        // Initialize if package is pre-selected
        <?php if ($selected_package): ?>
            document.addEventListener('DOMContentLoaded', function() {
                updatePackageInfo();
            });
        <?php endif; ?>

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const packageId = document.getElementById('package_id').value;
            if (!packageId) {
                e.preventDefault();
                alert('Mohon pilih paket terlebih dahulu!');
                return;
            }

            const selectedOption = document.getElementById('package_id').selectedOptions[0];
            const service = selectedOption.dataset.service;

            if (service === 'Baju Pengantin') {
                const size = document.getElementById('dress_size').value;
                const color = document.getElementById('dress_color').value;

                if (!size || !color) {
                    e.preventDefault();
                    alert('Mohon pilih ukuran dan warna dress!');
                    return;
                }
            }
        });

        // Set minimum date to tomorrow
        document.addEventListener('DOMContentLoaded', function() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const minDate = tomorrow.toISOString().split('T')[0];
            document.getElementById('usage_date').setAttribute('min', minDate);
        });
    </script>
</body>

</html>