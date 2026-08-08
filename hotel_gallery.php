<?php
// hotel_gallery.php
//
// Renders a hotel's photo gallery using whichever layout/background
// color/font the agent picked in agent_hotel_form.php. Linked from
// the hotel's room-selection page.

session_start();
require_once 'config.php';
require_once 'gallery_fonts.php';
require_once 'gallery_renderer.php';

$hotel_id = (int)($_GET['hotel_id'] ?? 0);
$stmt = $pdo->prepare("SELECT hotel_name, city FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$hotel) {
    header('Location: services.php?type=hotels');
    exit();
}

$stmt = $pdo->prepare("SELECT layout, bg_color, theme, font_family FROM hotel_galleries WHERE hotel_id = ?");
$stmt->execute([$hotel_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['layout' => 'grid2', 'bg_color' => '#0a0f1e', 'theme' => 'custom', 'font_family' => 'Inter'];

$stmt = $pdo->prepare("SELECT image_path, caption FROM hotel_gallery_images WHERE hotel_id = ? ORDER BY sort_order, id");
$stmt->execute([$hotel_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$font_choices = galleryFontChoices();
$font_url = $font_choices[$settings['font_family']] ?? $font_choices['Inter'];

$theme_presets = galleryThemePresets();
$theme_key = $settings['theme'] ?: 'custom';
$page_bg = $theme_presets[$theme_key] ?? null;
if ($page_bg === null) $page_bg = $settings['bg_color'] ?: '#0a0f1e'; // 'custom' theme -> use the color wheel value
$text_color = ($theme_key === 'pure_white') ? '#0a0f1e' : 'white';
$muted_color = ($theme_key === 'pure_white') ? 'rgba(10,15,30,0.55)' : 'rgba(255,255,255,0.5)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hotel['hotel_name']); ?> — Gallery</title>
    <?php if ($font_url): ?>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($font_url); ?>&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: '<?php echo htmlspecialchars($settings['font_family']); ?>', Georgia, sans-serif;
            background: <?php echo htmlspecialchars($page_bg); ?>;
            color: <?php echo $text_color; ?>;
            min-height: 100vh;
            padding: 48px 28px 90px;
            animation: hgalPageIn 0.5s ease forwards;
        }
        @keyframes hgalPageIn { from { opacity: 0; } to { opacity: 1; } }
        .wrap { max-width: 1200px; margin: 0 auto; }
        .back-link { color: <?php echo $muted_color; ?>; text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 24px; transition: color 0.2s ease; }
        .back-link:hover { color: #d4af37; }
        h1 { font-size: 32px; font-weight: 700; margin-bottom: 6px; }
        .sub { color: <?php echo $muted_color; ?>; font-size: 14px; margin-bottom: 40px; }
        .empty { text-align: center; padding: 100px 20px; color: <?php echo $muted_color; ?>; }
    </style>
    <?php renderHotelGalleryCSS(); ?>
</head>
<body>
<div class="wrap">
    <a href="hotel_rooms.php?hotel_id=<?php echo $hotel_id; ?>" class="back-link">← Back to <?php echo htmlspecialchars($hotel['hotel_name']); ?></a>
    <h1><?php echo htmlspecialchars($hotel['hotel_name']); ?></h1>
    <div class="sub"><?php echo htmlspecialchars($hotel['city']); ?> — Photo Gallery</div>

    <?php if (empty($images)): ?>
        <div class="empty">No photos have been added to this gallery yet.</div>
    <?php else: ?>
        <?php renderHotelGalleryHTML($pdo, $hotel_id); ?>
    <?php endif; ?>
</div>
</body>
</html>