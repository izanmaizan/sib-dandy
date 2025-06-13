<?php
// config/database.php - Fixed version
class Database
{
    private $host = 'localhost';
    private $db_name = 'dandy_gallery_gown';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection()
    {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }

        return $this->conn;
    }
}

// Fungsi helper untuk koneksi database
function getDB()
{
    $database = new Database();
    return $database->getConnection();
}

// Fungsi untuk format rupiah
function formatRupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Fungsi untuk generate booking code - FIXED: Proper implementation
function generateBookingCode()
{
    $db = getDB(); // Get database connection here

    do {
        // Format: BK + YYYYMMDD + 4 digit random
        $code = 'BK' . date('Ymd') . sprintf('%04d', rand(1, 9999));

        // Check if code already exists
        $stmt = $db->prepare("SELECT id FROM bookings WHERE booking_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetch();
    } while ($exists);

    return $code;
}

// Fungsi untuk cek login
function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

// Fungsi untuk cek role admin
function isAdmin()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

// Fungsi untuk redirect jika tidak login
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

// Fungsi untuk redirect jika bukan admin
function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: index.php');
        exit();
    }
}

// Additional helper functions for booking system

// Fungsi untuk menghitung DP
function calculateDownPayment($total_amount, $percentage = 30)
{
    return $total_amount * ($percentage / 100);
}

// Fungsi untuk validasi tanggal booking
function validateBookingDate($date)
{
    $booking_date = strtotime($date);
    $today = strtotime(date('Y-m-d'));

    // Booking harus minimal H+1
    return $booking_date > $today;
}

// Fungsi untuk format status booking dalam bahasa Indonesia
function getBookingStatusText($status)
{
    $status_map = [
        'pending' => 'Menunggu Konfirmasi',
        'confirmed' => 'Dikonfirmasi',
        'paid' => 'Dibayar',
        'in_progress' => 'Sedang Berlangsung',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan'
    ];

    return $status_map[$status] ?? ucfirst($status);
}

// Fungsi untuk format status pembayaran
function getPaymentStatusText($status)
{
    $status_map = [
        'pending' => 'Menunggu Verifikasi',
        'verified' => 'Terverifikasi',
        'rejected' => 'Ditolak'
    ];

    return $status_map[$status] ?? ucfirst($status);
}


// Fungsi untuk mendapatkan service types
function getServiceTypes()
{
    return [
        'Baju Pengantin' => [
            'name' => 'Baju Pengantin',
            'icon' => 'fas fa-tshirt',
            'description' => 'Layanan penyewaan baju pengantin dengan berbagai pilihan model dan ukuran'
        ],
        'Makeup Pengantin' => [
            'name' => 'Makeup Pengantin',
            'icon' => 'fas fa-palette',
            'description' => 'Layanan makeup profesional untuk pengantin dengan berbagai style'
        ]
    ];
}

// Fungsi untuk mendapatkan icon service type
function getServiceIcon($service_type)
{
    $service_types = getServiceTypes();
    return $service_types[$service_type]['icon'] ?? 'fas fa-question';
}

// Fungsi untuk mendapatkan deskripsi service type
function getServiceDescription($service_type)
{
    $service_types = getServiceTypes();
    return $service_types[$service_type]['description'] ?? '';
}
