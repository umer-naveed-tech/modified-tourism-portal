<?php
// save_theme_slot.php
//
// Saves ONE wizard slide at a time (photo and/or its own custom
// color) -- called via AJAX from agent_theme_settings.php's per-slide
// "Save This Section" buttons, so the agent never has to fill in
// every slide before anything takes effect.

session_start();
header('Content-Type: application/json');
require_once 'config.php';
require_once 'image_helper.php';

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

$allowed_slots = [
    'dashboard_hero', 'service_hotel', 'service_taxi', 'service_visa',
    'page_hotel', 'page_taxi', 'page_visa', 'page_hotel_room', 'page_taxi_booking',
];
$slot = $_POST['slot'] ?? '';
if (!in_array($slot, $allowed_slots, true)) {
    echo json_encode(['success' => false, 'error' => 'Unknown section']);
    exit();
}

$uploaded_image_url = null;

// Photo (optional)
if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = __DIR__ . '/uploads/theme_images/';
    $filename = handleImageUpload($_FILES['photo'], $upload_dir, $slot, 2400, 88);
    if ($filename) {
        $uploaded_image_url = 'uploads/theme_images/' . $filename;
        $stmt = $pdo->prepare("
            INSERT INTO site_theme_images (setting_key, image_path) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
        ");
        $stmt->execute([$slot, $uploaded_image_url]);
    } else {
        echo json_encode(['success' => false, 'error' => 'That photo could not be processed. Please try a JPG, PNG, or WEBP under 8MB.']);
        exit();
    }
}

// Remove photo (optional, only if no new photo was also sent)
if ($uploaded_image_url === null && !empty($_POST['remove_photo'])) {
    $stmt = $pdo->prepare("SELECT image_path FROM site_theme_images WHERE setting_key = ?");
    $stmt->execute([$slot]);
    $old_path = $stmt->fetchColumn();
    if ($old_path && file_exists(__DIR__ . '/' . $old_path)) @unlink(__DIR__ . '/' . $old_path);
    $pdo->prepare("UPDATE site_theme_images SET image_path = NULL WHERE setting_key = ?")->execute([$slot]);
}

// This section's own custom color (optional -- NULL/empty means "use
// the global theme color instead", handled by clearing the row).
if (isset($_POST['bg_color'])) {
    $color = $_POST['bg_color'];
    if ($color === '' || $color === 'default') {
        $pdo->prepare("DELETE FROM site_theme_slot_colors WHERE setting_key = ?")->execute([$slot]);
    } elseif (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $stmt = $pdo->prepare("
            INSERT INTO site_theme_slot_colors (setting_key, bg_color) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE bg_color = VALUES(bg_color)
        ");
        $stmt->execute([$slot, $color]);
    }
}

echo json_encode(['success' => true, 'image_url' => $uploaded_image_url]);