<?php
session_start();
require_once 'config.php';
require_once 'hotel_handlers/handler_factory.php';
// Single source of truth for pricing -- same function
// get_hotel_room_price.php uses for the preview, so a booking can never
// silently charge a different (or lower) total than what the customer
// was shown, and a missing pricing row now always fails the booking
// instead of quietly charging SAR 0 for that night.
require_once 'hotel_handlers/price_calculator.php';

// CSRF VERIFY — PEHLE HI HONA CHAHIYE
csrf_verify();

$room_id = $_POST['room_id'] ?? 0;
$hotel_id = $_POST['hotel_id'] ?? 0;
$hotel_name = $_POST['hotel_name'] ?? '';
$room_type_code = $_POST['room_type_code'] ?? '';
$bed_type = $_POST['bed_type'] ?? '';
$check_in = $_POST['check_in'] ?? '';
$check_out = $_POST['check_out'] ?? '';
$meal_type = $_POST['meal_type'] ?? 'breakfast';
$extra_bed = isset($_POST['extra_bed']) ? 1 : 0;
$guests = $_POST['guests'] ?? 2;
$supplements = $_POST['supplements'] ?? [];
$supplement = $_POST['supplement'] ?? null; // Al Safwah / Conrad: single-select radio
$meals = $_POST['meals'] ?? [];

if (!$room_id || !$hotel_id || !$check_in || !$check_out) {
    header('Location: services.php?type=hotels');
    exit();
}

// Guest booking flow
if (!isset($_SESSION['user_id'])) {
    $_SESSION['pending_hotel_booking'] = [
        'room_id' => $room_id,
        'hotel_id' => $hotel_id,
        'hotel_name' => $hotel_name,
        'room_type_code' => $room_type_code,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'meal_type' => $meal_type,
        'extra_bed' => $extra_bed,
        'guests' => $guests,
        'supplements' => $supplements,
        'meals' => $meals,
    ];
    $_SESSION['redirect_after_login'] = 'hotel_rooms.php?hotel_id=' . urlencode($hotel_id) . '&resume=1';
    header('Location: login.php');
    exit();
}

// Get user email if not in session
if (!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $_SESSION['user_email'] = $user['email'];
}

// ============================================================
// HAR HOTEL KA APNA HANDLER ROOM DETAILS DEGA
// ============================================================
$handler = HotelHandlerFactory::getHandler($hotel_id);
$room = $handler->getRoomDetails($room_id);

if (!$room) {
    header('Location: services.php?type=hotels');
    exit();
}

// Server-side nights calculation
$date_in = new DateTime($check_in);
$date_out = new DateTime($check_out);
$nights = $date_in->diff($date_out)->days;

if ($nights < 1) {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}

// ============================================================
// PRICE CALCULATION -- delegated entirely to price_calculator.php.
// If ANY date in the stay has no matching pricing row, this now fails
// the booking outright (same as the preview endpoint always did)
// instead of silently treating that night as free.
// ============================================================
$price = calculateHotelStayPrice($pdo, [
    'hotel_id'    => $hotel_id,
    'room_type'   => $room_type_code,
    'bed_type'    => $bed_type,
    'meal_type'   => $meal_type,
    'extra_bed'   => $extra_bed,
    'supplement'  => $supplement,
    'supplements' => $supplements,
    'meals'       => $meals,
    'guests'      => $guests,
    'check_in'    => $check_in,
    'check_out'   => $check_out,
]);

if (!$price['success']) {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}

$grand_total = $price['grand_total'];
$extra_bed_total = $price['extra_bed_total'] ?? 0;
$meal_total = $price['meal_total'] ?? 0;

$booking_no = 'HOTEL-' . date('Ymd') . '-' . rand(1000, 9999);
$travel_date = $check_in;
$room_display = $room['room_type'] ?? $room['display_name'] ?? 'Room';
$capacity = $room['capacity'] ?? 2;
// from_location is VARCHAR(100) -- mb_substr guarantees it never
// exceeds the column limit no matter how long hotel/room names are.
$from_location = mb_substr($hotel_name . ' - ' . $room_display, 0, 100);

// price_breakdown -- structured detail the agent panel reads to show
// "Details" on every booking, instead of just "Hotel".
$price_breakdown = json_encode([
    'hotel_name' => $hotel_name,
    'room_type' => $room_display,
    'room_type_code' => $room_type_code,
    'bed_type' => $bed_type ?: null,
    'meal_type' => $meal_type ?: null,
    'check_in' => $check_in,
    'check_out' => $check_out,
    'nights' => $nights,
    'guests' => $guests,
    'extra_bed' => (bool)$extra_bed,
    'extra_bed_total' => $extra_bed_total,
    'meal_total' => $meal_total,
    'supplement' => $supplement ?: null,
    'supplements' => $supplements ?: null,
]);

// BOOKING INSERT
$stmt = $pdo->prepare("
    INSERT INTO bookings (
        booking_no, user_id, service_type, service_id, booking_date, 
        travel_date, from_location, guests, extra_bed, extra_bed_price, total_amount, 
        meal_total, price_breakdown, status, payment_status, can_cancel_until
    ) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))
");

if ($stmt->execute([
    $booking_no, 
    $_SESSION['user_id'], 
    $hotel_id, 
    $travel_date, 
    $from_location, 
    $capacity, 
    $extra_bed,
    $extra_bed_total,
    $grand_total,
    $meal_total,
    $price_breakdown
])) {
    // REMOVED: the "Booking Received" email used to fire right here,
    // the moment a room was picked -- before the customer had even
    // entered their details or confirmed anything. That email now
    // fires once, at the Confirm step (booking_review.php), which is
    // the point a booking actually becomes "real" (see
    // customer_confirmed_at). Nothing else in this file changed --
    // pricing, the INSERT, and the redirect below are untouched.

    // Send the customer into the new booking flow (personal
    // details -> confirm -> payment) instead of straight to the old
    // success page. Everything above this line -- pricing, the INSERT --
    // is completely unchanged.
    header('Location: booking_details.php?booking_id=' . $pdo->lastInsertId());
    exit();
} else {
    header('Location: hotel_rooms.php?hotel_id=' . $hotel_id . '&error=1');
    exit();
}