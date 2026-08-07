<?php
// delete_hotel_admin.php
//
// Agent-only. Confirmation happens on the frontend (agent_manage_hotels.php's
// modal) before this is ever called. If the hotel has any existing
// bookings, deletion is BLOCKED rather than silently orphaning those
// bookings' service_id -- the agent has to handle those bookings first
// (matches the same "never orphan a booking" concern raised earlier
// when hotels were bulk-deleted).

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if (!csrf_valid()) {
    echo json_encode(['success' => false, 'error' => 'Security check failed, please refresh the page']);
    exit();
}

$hotel_id = (int)($_POST['hotel_id'] ?? 0);
if (!$hotel_id) {
    echo json_encode(['success' => false, 'error' => 'Missing hotel id']);
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_type = 'hotel' AND service_id = ?");
$stmt->execute([$hotel_id]);
if ($stmt->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'This hotel has existing bookings and cannot be deleted. Please handle those bookings first.']);
    exit();
}

// hotel_room_types / hotel_seasonal_pricing / hotel_supplements all
// cascade-delete automatically via their FK to hotels_saudi.
$stmt = $pdo->prepare("DELETE FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);

echo json_encode(['success' => true]);