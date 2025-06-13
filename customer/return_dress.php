<?php
// customer/return_dress.php - Handler untuk pengembalian baju pengantin
session_start();
require_once '../config/database.php';

requireLogin();

$db = getDB();

if ($_POST && isset($_POST['action']) && $_POST['action'] === 'return_dress') {
    $booking_id = (int)$_POST['booking_id'];

    // Verify booking belongs to user and it's for dress rental
    $stmt = $db->prepare("SELECT b.*, p.service_type as service_name FROM bookings b 
                         JOIN packages p ON b.package_id = p.id 
                         WHERE b.id = ? AND b.user_id = ? AND p.service_type = 'Baju Pengantin'");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        // Check if dress was actually taken
        $dress_taken = strpos($booking['notes'], '[DRESS_TAKEN]') !== false;
        $dress_returned = strpos($booking['notes'], '[DRESS_RETURNED]') !== false;

        if ($dress_returned) {
            header("Location: booking_detail.php?id=$booking_id&error=already_returned");
            exit();
        }

        if (!$dress_taken) {
            header("Location: booking_detail.php?id=$booking_id&error=not_taken_yet");
            exit();
        }

        // Check if event has passed
        $event_date = strtotime($booking['usage_date']);
        $today = time();

        if ($today < $event_date) {
            header("Location: booking_detail.php?id=$booking_id&error=event_not_finished");
            exit();
        }

        // Update booking notes to mark dress as returned
        $current_notes = $booking['notes'] ?? '';
        $return_date = date('d/m/Y H:i');
        $new_notes = $current_notes . "\n[DRESS_RETURNED] Baju pengantin dikembalikan pada $return_date oleh customer.";

        // Update booking status to completed if fully paid
        $stmt = $db->prepare("SELECT SUM(amount) as total_paid FROM payments WHERE booking_id = ? AND status = 'verified'");
        $stmt->execute([$booking_id]);
        $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total_paid'] ?? 0;

        // Set status to completed if fully paid, otherwise keep current status
        $new_status = ($total_paid >= $booking['total_amount']) ? 'completed' : $booking['status'];

        $stmt = $db->prepare("UPDATE bookings SET notes = ?, status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$new_notes, $new_status, $booking_id])) {
            header("Location: booking_detail.php?id=$booking_id&return_success=1");
            exit();
        } else {
            header("Location: booking_detail.php?id=$booking_id&error=database_error");
            exit();
        }
    } else {
        header("Location: booking_detail.php?id=$booking_id&error=invalid_booking");
        exit();
    }
}


// Redirect back if invalid request
header('Location: bookings.php');
exit();
