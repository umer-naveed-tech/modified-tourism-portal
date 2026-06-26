<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

// Get user email if not in session
if(!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $_SESSION['user_email'] = $user['email'];
}

$room_id = $_POST['room_id'] ?? 0;
$hotel_id = $_POST['hotel_id'] ?? 0;
$hotel_name = $_POST['hotel_name'] ?? '';
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';
$nights = $_POST['nights'] ?? 1;
$total_amount_str = $_POST['total_amount'] ?? '0';
$total_amount = floatval(str_replace('SAR ', '', $total_amount_str));

// Get room details
$stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE id = ?");
$stmt->execute([$room_id]);
$room = $stmt->fetch();

if(!$room) {
    header('Location: services.php?type=hotels');
    exit();
}

// Generate booking number
$booking_no = 'HOTEL-' . date('Ymd') . '-' . rand(1000, 9999);

// Insert booking
$stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, guests, total_amount, status, payment_status, can_cancel_until) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))");

$travel_date = $check_in . ' to ' . $check_out;
$room_name = ucfirst($room['room_type']) . ' ' . ucfirst($room['room_style']) . ' Room';

$success = $stmt->execute([$booking_no, $_SESSION['user_id'], $hotel_id, $travel_date, $hotel_name . ' - ' . $room_name, $room['capacity'], $total_amount]);

if($success) {
    // Send email notification
    require_once 'send_booking_email.php';
    sendBookingEmail(
        $_SESSION['user_email'],
        $_SESSION['user_name'],
        $booking_no,
        'Hotel: ' . $hotel_name . ' (' . $room_name . ')',
        $travel_date,
        $total_amount,
        $hotel_name,
        $room_name,
        $room['capacity'],
        $total_amount
    );
    
    header('Location: hotel_booking_success.php?booking_no=' . $booking_no . '&hotel=' . urlencode($hotel_name) . '&room=' . urlencode($room_name) . '&check_in=' . $check_in . '&check_out=' . $check_out . '&total=' . $total_amount);
    exit();
} else {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}
?>