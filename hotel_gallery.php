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

$stmt = $pdo->prepare("SELECT layout, bg_color, font_family FROM hotel_galleries WHERE hotel_id = ?");
$stmt->execute([$hotel_id]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['layout' => 'grid2', 'bg_color' => '#0a0f1e', 'font_family' => 'Inter'];

$stmt = $pdo->prepare("SELECT image_path, caption FROM hotel_gallery_images WHERE hotel_id = ? ORDER BY sort_order, id");
$stmt->execute([$hotel_id]);
$images = $stmt->fetchAll(PDO::FETCH_ASSOC);

$layout = $settings['layout'];
$font_choices = galleryFontChoices();
$font_url = $font_choices[$settings['font_family']] ?? $font_choices['Inter'];
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
            background: <?php echo htmlspecialchars($settings['bg_color']); ?>;
            color: white;
            min-height: 100vh;
            padding: 40px 24px 80px;
        }
        .wrap { max-width: 1100px; margin: 0 auto; }
        .back-link { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 20px; }
        h1 { font-size: 28px; font-weight: 700; margin-bottom: 6px; }
        .sub { color: rgba(255,255,255,0.5); font-size: 14px; margin-bottom: 32px; }
        .empty { text-align: center; padding: 80px 20px; color: rgba(255,255,255,0.4); }
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
        <?php renderHotelGalleryHTML($pdo, $hotel_id, false); ?>
    <?php endif; ?>
</div>
</body>
</html>