<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

$car_id = $_GET['car_id'] ?? 0;
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

if($car_id && $from && $to) {
    // FIX: routes are the same fare either direction -- check both
    // orderings, same as the fix in services.php and booking_taxi.php.
    $stmt = $pdo->prepare("SELECT price_sar FROM car_fares WHERE car_id = ? AND ((from_city = ? AND to_city = ?) OR (from_city = ? AND to_city = ?))");
    $stmt->execute([$car_id, $from, $to, $to, $from]);
    $fare = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($fare) {
        echo json_encode(['fare' => $fare['price_sar']]);
    } else {
        echo json_encode(['fare' => null]);
    }
} else {
    echo json_encode(['fare' => null]);
}
?>