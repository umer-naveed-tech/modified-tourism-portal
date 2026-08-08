<?php
// save_gallery.php
//
// Backend for the Hotel Gallery form in agent_hotel_form.php. This is
// completely independent of save_hotel.php -- saving the gallery never
// depends on (or can be blocked by) the hotel-details/room-types/
// pricing form, and vice versa.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'image_helper.php';
require_once 'gallery_fonts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agent_manage_hotels.php');
    exit();
}
csrf_verify();

$hotel_id = (int)($_POST['hotel_id'] ?? 0);
if (!$hotel_id) {
    header('Location: agent_manage_hotels.php');
    exit();
}

// Confirm the hotel actually exists before touching anything.
$stmt = $pdo->prepare("SELECT id FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
if (!$stmt->fetch()) {
    header('Location: agent_manage_hotels.php');
    exit();
}

$pdo->beginTransaction();
try {
    // ---- Layout/theme settings ----
    $gallery_layout = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['gallery_layout'] ?? 'grid2'));
    if ($gallery_layout === '') $gallery_layout = 'grid2';
    $gallery_bg_color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['gallery_bg_color'] ?? '') ? $_POST['gallery_bg_color'] : '#0a0f1e';
    $gallery_font = isValidGalleryFont($_POST['gallery_font'] ?? '') ? $_POST['gallery_font'] : 'Inter';

    $stmt = $pdo->prepare("
        INSERT INTO hotel_galleries (hotel_id, layout, bg_color, font_family)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE layout = VALUES(layout), bg_color = VALUES(bg_color), font_family = VALUES(font_family)
    ");
    $stmt->execute([$hotel_id, $gallery_layout, $gallery_bg_color, $gallery_font]);

    // ---- Remove any images the agent checked "Remove" ----
    if (!empty($_POST['remove_gallery_images']) && is_array($_POST['remove_gallery_images'])) {
        $remove_ids = array_map('intval', $_POST['remove_gallery_images']);
        if (!empty($remove_ids)) {
            $stmt = $pdo->prepare("SELECT image_path FROM hotel_gallery_images WHERE id = ? AND hotel_id = ?");
            $del_stmt = $pdo->prepare("DELETE FROM hotel_gallery_images WHERE id = ? AND hotel_id = ?");
            foreach ($remove_ids as $rid) {
                $stmt->execute([$rid, $hotel_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['image_path']) && file_exists(__DIR__ . '/' . $row['image_path'])) {
                    @unlink(__DIR__ . '/' . $row['image_path']);
                }
                $del_stmt->execute([$rid, $hotel_id]);
            }
        }
    }

    // ---- Upload any newly added photos -- automatically resized/
    // compressed by image_helper.php so page speed stays good no
    // matter how large the agent's original photos are. ----
    $uploaded_count = 0;
    if (!empty($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
        $upload_dir = __DIR__ . '/uploads/gallery_images/';

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM hotel_gallery_images WHERE hotel_id = ?");
        $stmt->execute([$hotel_id]);
        $next_sort = (int)$stmt->fetchColumn() + 1;

        $insert_stmt = $pdo->prepare("INSERT INTO hotel_gallery_images (hotel_id, image_path, sort_order) VALUES (?, ?, ?)");

        $count = count($_FILES['gallery_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['gallery_images']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $single_file = [
                'tmp_name' => $_FILES['gallery_images']['tmp_name'][$i],
                'error' => $_FILES['gallery_images']['error'][$i],
                'size' => $_FILES['gallery_images']['size'][$i],
            ];
            $filename = handleImageUpload($single_file, $upload_dir, 'gallery-' . $hotel_id);
            if ($filename) {
                $insert_stmt->execute([$hotel_id, 'uploads/gallery_images/' . $filename, $next_sort]);
                $next_sort++;
                $uploaded_count++;
            }
        }
    }

    $pdo->commit();
    header('Location: agent_hotel_form.php?id=' . $hotel_id . '&gallery_saved=1');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('save_gallery.php error: ' . $e->getMessage());
    header('Location: agent_hotel_form.php?id=' . $hotel_id . '&gallery_error=1');
    exit();
}