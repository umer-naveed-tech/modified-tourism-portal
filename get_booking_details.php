<?php
// get_booking_details.php
//
// Agent-only AJAX endpoint used by the "Details" button on
// agent_dashboard.php. Returns everything known about one booking as
// JSON, so the agent can see which hotel/room/car/route was actually
// booked instead of just "Hotel" / "Taxi".
//
// Two data sources, tried in order:
//   1. bookings.price_breakdown (JSON column) -- populated by every
//      booking-creation script going forward. Rich, exact data.
//   2. Best-effort reconstruction from service_id + from_location/
//      to_location -- used ONLY when price_breakdown is empty, which
//      covers bookings made before this feature existed. Clearly
//      flagged to the agent as "reconstructed" so it's never mistaken
//      for guaranteed-accurate data.

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Missing booking id']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT b.*, u.name AS user_name, u.email AS user_email, u.phone AS user_phone
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    WHERE b.id = ?
");
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$b) {
    echo json_encode(['success' => false, 'error' => 'Booking not found']);
    exit();
}

$details = [];
$source = 'reconstructed';

if (!empty($b['price_breakdown'])) {
    $decoded = json_decode($b['price_breakdown'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $details = $decoded;
        $source = 'stored';
    }
}

if (empty($details)) {
    // ---- Best-effort reconstruction for pre-existing bookings ----
    if ($b['service_type'] === 'hotel') {
        $stmt2 = $pdo->prepare("SELECT hotel_name, city FROM hotels_saudi WHERE id = ?");
        $stmt2->execute([$b['service_id']]);
        $hotel = $stmt2->fetch(PDO::FETCH_ASSOC);
        $details['hotel_name'] = $hotel['hotel_name'] ?? null;
        $details['city'] = $hotel['city'] ?? null;

        // Every hotel-booking script (old and new) has always written
        // from_location as "HotelName - RoomType (Check-in: X, Check-out:
        // Y)" -- parse that back out into separate fields instead of
        // dumping it as one raw blob, so Room Type/Check-in/Check-out
        // show up as proper rows just like a fresh booking would.
        $loc = $b['from_location'] ?? '';
        if (preg_match('/-\s*(.+?)\s*\(Check-in:\s*([\d-]+),\s*Check-out:\s*([\d-]+)\)/i', $loc, $m)) {
            $details['room_type'] = trim($m[1]);
            $details['check_in'] = $m[2];
            $details['check_out'] = $m[3];
        } elseif ($loc !== '' && stripos($loc, (string)($hotel['hotel_name'] ?? '')) === false) {
            // Doesn't match the pattern above and isn't just the hotel
            // name repeated (very old bookings only stored the city) --
            // show it raw rather than silently dropping it.
            $details['raw_info'] = $loc;
        }
    } elseif ($b['service_type'] === 'taxi') {
        $stmt2 = $pdo->prepare("SELECT car_name, car_model, capacity FROM cars WHERE id = ?");
        $stmt2->execute([$b['service_id']]);
        $car = $stmt2->fetch(PDO::FETCH_ASSOC);
        $details['car_name'] = $car['car_name'] ?? null;
        $details['car_model'] = $car['car_model'] ?? null;
        $details['capacity'] = $car['capacity'] ?? null;
        $details['from_city'] = $b['from_location'];
        $details['to_city'] = $b['to_location'];
    } elseif ($b['service_type'] === 'ziyarat') {
        $details['ziyarat_route'] = trim(($b['from_location'] ?? '') . ' -> ' . ($b['to_location'] ?? ''), ' ->');
    } else {
        // visa / other `services`-table bookings
        if ($b['service_id']) {
            $stmt2 = $pdo->prepare("SELECT title, description FROM services WHERE id = ?");
            $stmt2->execute([$b['service_id']]);
            $svc = $stmt2->fetch(PDO::FETCH_ASSOC);
            $details['service_title'] = $svc['title'] ?? null;
            $details['service_description'] = $svc['description'] ?? null;
        }
        $details['raw_info'] = $b['from_location'];
    }
}

echo json_encode([
    'success' => true,
    'source' => $source, // 'stored' (accurate) or 'reconstructed' (best-effort, legacy booking)
    'booking' => [
        'id' => $b['id'],
        'booking_no' => $b['booking_no'],
        'service_type' => $b['service_type'],
        'status' => $b['status'],
        'total_amount' => $b['total_amount'],
        'meal_total' => $b['meal_total'],
        'extra_bed' => (bool)$b['extra_bed'],
        'extra_bed_price' => $b['extra_bed_price'],
        'guests' => $b['guests'],
        'travel_date' => $b['travel_date'],
        'booking_date' => $b['booking_date'],
        'created_at' => $b['created_at'],
        'from_location' => $b['from_location'],
        'to_location' => $b['to_location'],
        'customer_name' => $b['user_name'],
        'customer_email' => $b['user_email'],
        'customer_phone' => $b['user_phone'],
    ],
    'details' => $details,
]);