<?php
// hide_booking.php
//
// Lets a customer remove a booking from THEIR OWN "My Bookings" view
// only. This never touches the booking's actual status, and never
// deletes the row -- agents still see it in agent_dashboard.php exactly
// as before, for record-keeping. It just sets hidden_by_user=1 so the
// visitor_dashboard.php query stops showing it to that customer.

session_start();
header('Content-Type: application/json');
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    echo json_encode(['success' => false, 'error' => 'Not authorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if (function_exists('csrf_valid')) {
    if (!csrf_valid($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'Invalid security token, please refresh the page']);
        exit();
    }
}

$booking_id = (int)($_POST['id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing booking id']);
    exit();
}

// Ownership check -- a customer can only hide their OWN booking, never
// anyone else's, regardless of what id is passed in.
$stmt = $pdo->prepare("UPDATE bookings SET hidden_by_user = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => true]);
} else {
    // Either it doesn't exist, or it doesn't belong to this user, or it
    // was already hidden -- any of those are a safe "no-op" from the
    // customer's point of view, but we report it plainly rather than
    // pretending it worked.
    $check = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
    $check->execute([$booking_id, $_SESSION['user_id']]);
    if ($check->fetch()) {
        echo json_encode(['success' => true]); // was already hidden, treat as success
    } else {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
    }
}