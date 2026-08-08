<?php
// hide_booking_agent.php
//
// Agent-only. Removes a booking from the agent's own dashboard view --
// same non-destructive idea as hide_booking.php on the customer side,
// but completely independent (its own hidden_by_agent column). The
// booking and any payment records stay in the database untouched.

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

$booking_id = (int)($_POST['id'] ?? 0);
if (!$booking_id) {
    echo json_encode(['success' => false, 'error' => 'Missing booking id']);
    exit();
}

$stmt = $pdo->prepare("UPDATE bookings SET hidden_by_agent = 1 WHERE id = ?");
$stmt->execute([$booking_id]);

echo json_encode(['success' => true]);