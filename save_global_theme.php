<?php
// save_global_theme.php
//
// Saves the "Colors & Effects" slide -- the overall color theme,
// animation style, and card frame style. Separate from
// save_theme_slot.php since these aren't tied to one photo slot.

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

require_once 'card_frames.php';

$allowed_themes = ['elegant', 'classic_elegant'];
$allowed_animations = ['fade_up', 'fade_in', 'slide_left', 'slide_right', 'zoom_in', 'flip_up', 'bounce_in', 'blur_in', 'rotate_in', 'cascade'];
$allowed_frames = array_keys(cardFrameOptions());

$theme_style = in_array($_POST['theme_style'] ?? '', $allowed_themes, true) ? $_POST['theme_style'] : 'elegant';
$animation_style = in_array($_POST['animation_style'] ?? '', $allowed_animations, true) ? $_POST['animation_style'] : 'fade_up';
$card_frame_style = in_array($_POST['card_frame_style'] ?? '', $allowed_frames, true) ? $_POST['card_frame_style'] : 'none';

$stmt = $pdo->prepare("
    INSERT INTO site_theme_settings (setting_key, setting_value) VALUES (?, ?)
    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
");
$stmt->execute(['theme_style', $theme_style]);
$stmt->execute(['animation_style', $animation_style]);
$stmt->execute(['card_frame_style', $card_frame_style]);

echo json_encode(['success' => true]);