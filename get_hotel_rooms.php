<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}
require_once 'config.php';

$hotel_id = $_GET['hotel_id'] ?? 0;

$stmt = $pdo->prepare("SELECT id, room_type, price_per_night_sar, capacity, description, amenities FROM hotel_rooms WHERE hotel_id = ? ORDER BY FIELD(room_type, 'Separate', 'Double', 'Triple', 'Quad')");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'rooms' => $rooms]);
?>