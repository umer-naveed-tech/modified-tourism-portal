<?php
// get_payment_details.php
//
// Agent-only AJAX endpoint for agent_payments.php's detail modal --
// given a payment_id, returns everything about that payment AND the
// booking/customer it belongs to, in one call.

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

$payment_id = (int)($_GET['id'] ?? 0);
if (!$payment_id) {
    echo json_encode(['success' => false, 'error' => 'Missing payment id']);
    exit();
}

$stmt = $pdo->prepare("
    SELECT p.*, b.booking_no, b.service_type, b.service_id, b.total_amount, b.travel_date,
           b.from_location, b.to_location, b.price_breakdown,
           u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
           b.customer_name, b.customer_email, b.customer_phone
    FROM payments p
    JOIN bookings b ON p.booking_id = b.id
    JOIN users u ON b.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['success' => false, 'error' => 'Payment not found']);
    exit();
}

// Reuse the same reconstruction approach as get_booking_details.php --
// prefer the structured price_breakdown JSON, fall back to a live
// lookup for older bookings that don't have it.
$details = [];
if (!empty($row['price_breakdown'])) {
    $decoded = json_decode($row['price_breakdown'], true);
    if (is_array($decoded)) $details = $decoded;
}
if (empty($details)) {
    if ($row['service_type'] === 'hotel') {
        $stmt2 = $pdo->prepare("SELECT hotel_name FROM hotels_saudi WHERE id = ?");
        $stmt2->execute([$row['service_id']]);
        $h = $stmt2->fetch(PDO::FETCH_ASSOC);
        $details['hotel_name'] = $h['hotel_name'] ?? null;
    } elseif ($row['service_type'] === 'taxi') {
        $stmt2 = $pdo->prepare("SELECT car_name, car_model FROM cars WHERE id = ?");
        $stmt2->execute([$row['service_id']]);
        $c = $stmt2->fetch(PDO::FETCH_ASSOC);
        if ($c) {
            $details['car_name'] = trim($c['car_name'] . ' ' . $c['car_model']);
            $details['from_city'] = $row['from_location'];
            $details['to_city'] = $row['to_location'];
        }
    }
}

echo json_encode([
    'success' => true,
    'payment' => [
        'id' => $row['id'],
        'payer_name' => $row['payer_name'],
        'payment_reference' => $row['payment_reference'],
        'screenshot_url' => $row['screenshot_path'],
        'status' => $row['status'],
        'rejection_reason' => $row['rejection_reason'],
        'submitted_at' => $row['submitted_at'],
        'verified_at' => $row['verified_at'],
    ],
    'booking' => [
        'booking_no' => $row['booking_no'],
        'service_type' => $row['service_type'],
        'total_amount' => $row['total_amount'],
        'travel_date' => $row['travel_date'],
        'customer_name' => $row['customer_name'] ?: $row['user_name'],
        'customer_email' => $row['customer_email'] ?: $row['user_email'],
        'customer_phone' => $row['customer_phone'] ?: $row['user_phone'],
    ],
    'details' => $details,
]);