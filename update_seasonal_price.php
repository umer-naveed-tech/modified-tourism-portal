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
$base = $_POST['base'] ?? 0;
$markup = $_POST['markup'] ?? 70;

if(!$id || $base < 0 || $markup < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

$stmt = $pdo->prepare("UPDATE hotel_seasonal_pricing SET base_price_sar = ?, markup_sar = ? WHERE id = ?");
$success = $stmt->execute([$base, $markup, $id]);

echo json_encode(['success' => $success]);
?>