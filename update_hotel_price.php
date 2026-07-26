<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if(!csrf_valid()) {
    echo json_encode(['success' => false, 'error' => 'Security check failed']);
    exit();
}

$id = $_POST['id'] ?? 0;
$price = $_POST['price'] ?? 0;

if(!$id || $price < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$stmt = $pdo->prepare("UPDATE hotels_saudi SET price_per_night_sar = ? WHERE id = ?");
$success = $stmt->execute([$price, $id]);

echo json_encode(['success' => $success]);
?>