<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $type = $_POST['ziyarat_type'] ?? '';
    $price = $_POST['ziyarat_price'] ?? 0;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $guests = $_POST['guests'] ?? 1;
    $pickup = $_POST['pickup_location'] ?? '';
    $requests = $_POST['special_requests'] ?? '';
    
    if(!$type || !$date || !$pickup) {
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit();
    }
    
    $booking_no = 'ZIYARAT-' . date('Ymd') . '-' . rand(1000, 9999);
    $travel_datetime = $date . ($time ? ' at ' . $time : '');
    $from_location = $type == 'makkah' ? 'Makkah' : 'Madinah';
    $to_location = 'Ziyarat Sites';
    
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, to_location, guests, total_amount, status, payment_status, can_cancel_until) VALUES (?, ?, 'ziyarat', 0, CURDATE(), ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $travel_datetime, $from_location, $to_location, $guests, $price])) {
        echo json_encode(['success' => true, 'booking_no' => $booking_no, 'fare' => $price]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>