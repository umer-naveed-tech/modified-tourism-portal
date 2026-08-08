<?php
// save_taxi.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agent_manage_taxis.php');
    exit();
}
csrf_verify();

$car_id = (int)($_POST['car_id'] ?? 0);
$car_name = trim($_POST['car_name'] ?? '');
$car_model = trim($_POST['car_model'] ?? '');
$capacity = max(1, (int)($_POST['capacity'] ?? 4));
$routes = json_decode($_POST['routes_json'] ?? '[]', true) ?: [];

if ($car_name === '' || $car_model === '' || empty($routes)) {
    header('Location: agent_taxi_form.php' . ($car_id ? '?id=' . $car_id : '') . '&error=1');
    exit();
}

$image_url = null;
if (!empty($_FILES['car_image']) && $_FILES['car_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['car_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
        $upload_dir = __DIR__ . '/uploads/car_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = 'car-' . preg_replace('/[^a-z0-9]/i', '', strtolower($car_name . $car_model)) . '-' . time() . '.' . $allowed[$mime];
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $image_url = 'uploads/car_images/' . $filename;
        }
    }
}

$pdo->beginTransaction();
try {
    if ($car_id) {
        $stmt = $pdo->prepare("SELECT image_url FROM cars WHERE id = ?");
        $stmt->execute([$car_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new Exception('Vehicle not found');
        if ($image_url === null) $image_url = $existing['image_url'];

        $stmt = $pdo->prepare("UPDATE cars SET car_name = ?, car_model = ?, capacity = ?, image_url = ? WHERE id = ?");
        $stmt->execute([$car_name, $car_model, $capacity, $image_url, $car_id]);

        $pdo->prepare("DELETE FROM car_fares WHERE car_id = ?")->execute([$car_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cars (car_name, car_model, capacity, image_url) VALUES (?, ?, ?, ?)");
        $stmt->execute([$car_name, $car_model, $capacity, $image_url]);
        $car_id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("INSERT INTO car_fares (car_id, from_city, to_city, price_sar) VALUES (?, ?, ?, ?)");
    foreach ($routes as $r) {
        // FIX: always store city names in UPPERCASE, so "makkah" and
        // "MAKKAH" (or any other casing an agent types) always end up
        // as the exact same value -- this is what was causing routes
        // to silently fail to match on the customer-facing pages.
        $from = strtoupper(trim($r['from_city'] ?? ''));
        $to = strtoupper(trim($r['to_city'] ?? ''));
        $price = (float)($r['price_sar'] ?? 0);
        if ($from === '' || $to === '' || $price <= 0) continue;
        $stmt->execute([$car_id, $from, $to, $price]);
    }

    $pdo->commit();
    header('Location: agent_manage_taxis.php?saved=1');
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('save_taxi.php error: ' . $e->getMessage());
    header('Location: agent_taxi_form.php' . ($car_id ? '?id=' . $car_id : '') . '&error=1');
    exit();
}