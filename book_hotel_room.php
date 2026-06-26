<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$room_id = $_POST['room_id'] ?? 0;
$hotel_id = $_POST['hotel_id'] ?? 0;
$hotel_name = $_POST['hotel_name'] ?? '';
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';
$total_amount = $_POST['total_amount'] ?? 0;

if(!$room_id || !$hotel_id || !$check_in || !$check_out) {
    header('Location: services.php?type=hotels');
    exit();
}

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

// Store only check-in date in travel_date (not range)
$travel_date = $check_in;

// Store room type and dates in from_location for reference
$from_location = $hotel_name . ' - ' . $room['room_type'] . ' Room (Check-in: ' . $check_in . ', Check-out: ' . $check_out . ')';

$room_display = $room['room_type'] . ' Room';

// Insert booking - travel_date sirf check-in date store karega
$stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, guests, total_amount, status, payment_status, can_cancel_until) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))");

if($stmt->execute([$booking_no, $_SESSION['user_id'], $hotel_id, $travel_date, $from_location, $room['capacity'], $total_amount])) {
    // Send email
    if(file_exists('send_booking_email.php')) {
        require_once 'send_booking_email.php';
        sendBookingEmail(
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $booking_no,
            'Hotel - ' . $hotel_name . ' (' . $room_display . ')',
            $check_in . ' to ' . $check_out,
            $total_amount,
            $hotel_name,
            $room_display,
            $room['capacity'],
            $total_amount
        );
    }
    
    header('Location: booking_success.php?booking_no=' . $booking_no . '&type=hotel&amount=' . $total_amount);
    exit();
} else {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}
?>