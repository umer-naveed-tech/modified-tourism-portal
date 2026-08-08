<?php
// hotel_gallery.php
//
// Renders a hotel's photo gallery using whichever layout/background
// color/font the agent picked in agent_hotel_form.php. Linked from
// the hotel's room-selection page.

session_start();
require_once 'config.php';

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
$google_font_map = [
    'Playfair Display' => 'Playfair+Display:wght@600;700',
    'Poppins' => 'Poppins:wght@400;600',
    'Montserrat' => 'Montserrat:wght@400;600',
    'Merriweather' => 'Merriweather:wght@400;700',
    'Roboto' => 'Roboto:wght@400;600',
    'Inter' => 'Inter:wght@400;600',
];
$font_url = $google_font_map[$settings['font_family']] ?? $google_font_map['Inter'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($hotel['hotel_name']); ?> — Gallery</title>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($font_url); ?>&family=Georgia&display=swap" rel="stylesheet">
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
        figure { margin: 0; position: relative; border-radius: 10px; overflow: hidden; }
        figure img { width: 100%; height: 100%; object-fit: cover; display: block; }
        figcaption { position: absolute; bottom: 0; left: 0; right: 0; padding: 10px 14px; background: linear-gradient(transparent, rgba(0,0,0,0.7)); font-size: 12.5px; }

        /* ---- 10 layouts ---- */
        .gallery-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .gallery-grid2 figure { height: 260px; }

        .gallery-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .gallery-grid3 figure { height: 200px; }

        .gallery-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .gallery-grid4 figure { height: 150px; }

        .gallery-masonry { columns: 3 220px; column-gap: 12px; }
        .gallery-masonry figure { margin-bottom: 12px; break-inside: avoid; height: auto; }
        .gallery-masonry figure img { height: auto; }

        .gallery-carousel { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 12px; }
        .gallery-carousel figure { flex: 0 0 320px; height: 240px; scroll-snap-align: start; }

        .gallery-hero figure:first-child { height: 420px; margin-bottom: 12px; }
        .gallery-hero .thumbs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .gallery-hero .thumbs figure { height: 110px; }

        .gallery-mosaic { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 10px; height: 480px; }
        .gallery-mosaic figure:first-child { grid-row: span 2; height: auto; }
        .gallery-mosaic figure { height: auto; }

        .gallery-stack figure { height: 360px; margin-bottom: 16px; }

        .gallery-polaroid { display: flex; flex-wrap: wrap; gap: 24px; justify-content: center; padding-top: 10px; }
        .gallery-polaroid figure { width: 220px; height: 220px; background: white; padding: 10px 10px 30px; border-radius: 4px; box-shadow: 0 8px 20px rgba(0,0,0,0.35); transform: rotate(-2deg); }
        .gallery-polaroid figure:nth-child(even) { transform: rotate(2deg); }
        .gallery-polaroid figure img { height: 100%; border-radius: 2px; }
        .gallery-polaroid figcaption { position: static; background: none; color: #333; text-align: center; padding-top: 8px; }

        .gallery-split figure { height: 320px; margin-bottom: 14px; }
        .gallery-split figure:nth-child(odd) { width: 60%; }
        .gallery-split figure:nth-child(even) { width: 60%; margin-left: 40%; }

        @media (max-width: 700px) {
            .gallery-grid2, .gallery-grid3, .gallery-grid4, .gallery-mosaic { grid-template-columns: 1fr 1fr; }
            .gallery-hero .thumbs { grid-template-columns: repeat(2, 1fr); }
            .gallery-split figure, .gallery-split figure:nth-child(even) { width: 100%; margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <a href="hotel_rooms.php?hotel_id=<?php echo $hotel_id; ?>" class="back-link">← Back to <?php echo htmlspecialchars($hotel['hotel_name']); ?></a>
    <h1><?php echo htmlspecialchars($hotel['hotel_name']); ?></h1>
    <div class="sub"><?php echo htmlspecialchars($hotel['city']); ?> — Photo Gallery</div>

    <?php if (empty($images)): ?>
        <div class="empty">No photos have been added to this gallery yet.</div>
    <?php elseif ($layout === 'hero'): ?>
        <div class="gallery-hero">
            <figure>
                <img src="<?php echo htmlspecialchars($images[0]['image_path']); ?>" alt="">
                <?php if ($images[0]['caption']): ?><figcaption><?php echo htmlspecialchars($images[0]['caption']); ?></figcaption><?php endif; ?>
            </figure>
            <div class="thumbs">
                <?php foreach (array_slice($images, 1) as $img): ?>
                <figure>
                    <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                </figure>
                <?php endforeach; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="gallery-<?php echo htmlspecialchars($layout); ?>">
            <?php foreach ($images as $img): ?>
            <figure>
                <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="">
                <?php if ($img['caption']): ?><figcaption><?php echo htmlspecialchars($img['caption']); ?></figcaption><?php endif; ?>
            </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>