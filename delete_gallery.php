<?php
// delete_gallery.php
//
// Agent-only. Deletes every photo (files + DB rows) and the layout/
// theme settings for one hotel's gallery, so the agent can start
// completely fresh. Confirmation happens on the frontend
// (agent_hotel_form.php's confirm dialog) before this is ever called.

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

$stmt = $pdo->prepare("SELECT image_path FROM hotel_gallery_images WHERE hotel_id = ?");
$stmt->execute([$hotel_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($images as $img) {
    if (!empty($img['image_path']) && file_exists(__DIR__ . '/' . $img['image_path'])) {
        @unlink(__DIR__ . '/' . $img['image_path']);
    }
}

$pdo->prepare("DELETE FROM hotel_gallery_images WHERE hotel_id = ?")->execute([$hotel_id]);
$pdo->prepare("DELETE FROM hotel_galleries WHERE hotel_id = ?")->execute([$hotel_id]);

echo json_encode(['success' => true]);