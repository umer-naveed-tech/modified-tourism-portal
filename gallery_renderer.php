<?php
// gallery_renderer.php
//
// Shared gallery HTML/CSS for the dedicated hotel_gallery.php page --
// large photos, entrance animations, background themes, and 12
// layouts. (Previously also embedded on hotel_rooms.php -- per
// Umer's request that page now only shows the agent's own room photo
// with a "Photo Gallery" button in the corner; the actual gallery
// lives only on this dedicated page.)

/**
 * Named background themes -- a quick, good-looking choice beyond a
 * flat custom color. 'custom' falls through to whatever bg_color the
 * agent picked in the color wheel.
 */
function galleryThemePresets() {
    return [
        'custom'       => null, // uses bg_color directly
        'midnight_gold' => 'linear-gradient(160deg, #0a0f1e 0%, #1a1a2e 60%, #2b2410 100%)',
        'warm_sand'     => 'linear-gradient(160deg, #3d2f1f 0%, #6b4f2f 100%)',
        'deep_ocean'    => 'linear-gradient(160deg, #041426 0%, #0b3556 100%)',
        'classic_black' => '#0a0a0a',
        'pure_white'    => '#f4f4f2',
        'rose_dusk'     => 'linear-gradient(160deg, #2a1420 0%, #4a2035 100%)',
    ];
}

function galleryThemeLabels() {
    return [
        'custom' => 'Custom Color', 'midnight_gold' => 'Midnight Gold', 'warm_sand' => 'Warm Sand',
        'deep_ocean' => 'Deep Ocean', 'classic_black' => 'Classic Black', 'pure_white' => 'Pure White',
        'rose_dusk' => 'Rose Dusk',
    ];
}

function renderHotelGalleryCSS() {
    ?>
    <style>
        .hgal { --hgal-radius: 12px; }
        .hgal figure {
            margin: 0; position: relative; border-radius: var(--hgal-radius); overflow: hidden;
            opacity: 0; transform: translateY(24px);
            animation: hgalIn 0.7s cubic-bezier(.2,.7,.3,1) forwards;
            box-shadow: 0 10px 30px rgba(0,0,0,0.25);
        }
        .hgal figure:nth-child(1) { animation-delay: 0.05s; }
        .hgal figure:nth-child(2) { animation-delay: 0.12s; }
        .hgal figure:nth-child(3) { animation-delay: 0.19s; }
        .hgal figure:nth-child(4) { animation-delay: 0.26s; }
        .hgal figure:nth-child(5) { animation-delay: 0.33s; }
        .hgal figure:nth-child(6) { animation-delay: 0.40s; }
        .hgal figure:nth-child(n+7) { animation-delay: 0.46s; }
        @keyframes hgalIn { to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { .hgal figure { animation: none; opacity: 1; transform: none; } }

        .hgal figure img { width: 100%; height: 100%; object-fit: cover; display: block; transition: transform 0.6s ease; }
        .hgal figure:hover img { transform: scale(1.06); }
        .hgal figcaption { position: absolute; bottom: 0; left: 0; right: 0; padding: 14px 18px; background: linear-gradient(transparent, rgba(0,0,0,0.75)); font-size: 13.5px; color: white; }

        /* ---- Bigger, full-page-scale layouts ---- */
        .hgal.gallery-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .hgal.gallery-grid2 figure { height: 380px; }

        .hgal.gallery-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .hgal.gallery-grid3 figure { height: 280px; }

        .hgal.gallery-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .hgal.gallery-grid4 figure { height: 210px; }

        .hgal.gallery-masonry { columns: 3 260px; column-gap: 16px; }
        .hgal.gallery-masonry figure { margin-bottom: 16px; break-inside: avoid; height: auto; }
        .hgal.gallery-masonry figure img { height: auto; }

        .hgal.gallery-carousel { display: flex; gap: 18px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 14px; }
        .hgal.gallery-carousel figure { flex: 0 0 420px; height: 320px; scroll-snap-align: start; }

        .hgal.gallery-hero figure:first-child { height: 520px; margin-bottom: 16px; }
        .hgal.gallery-hero .hgal-thumbs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .hgal.gallery-hero .hgal-thumbs figure { height: 140px; }

        .hgal.gallery-mosaic { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 14px; height: 560px; }
        .hgal.gallery-mosaic figure:first-child { grid-row: span 2; height: auto; }
        .hgal.gallery-mosaic figure { height: auto; }

        .hgal.gallery-stack figure { height: 440px; margin-bottom: 20px; }

        .hgal.gallery-polaroid { display: flex; flex-wrap: wrap; gap: 28px; justify-content: center; padding-top: 10px; }
        .hgal.gallery-polaroid figure { width: 260px; height: 260px; background: white; padding: 12px 12px 34px; border-radius: 4px; box-shadow: 0 12px 26px rgba(0,0,0,0.35); transform: rotate(-2deg); }
        .hgal.gallery-polaroid figure:nth-child(even) { transform: rotate(2deg); }
        .hgal.gallery-polaroid figure img { height: 100%; border-radius: 2px; }
        .hgal.gallery-polaroid figcaption { position: static; background: none; color: #333; text-align: center; padding-top: 8px; }

        .hgal.gallery-split figure { height: 340px; margin-bottom: 16px; width: 62%; }
        .hgal.gallery-split figure:nth-child(even) { margin-left: 38%; }

        /* NEW: signature 5-photo showcase (1 large hero + 4 in a
           balanced row beneath -- a fixed, deliberately-composed look
           rather than a repeating grid). Extra photos beyond the
           first 5 fall into a simple grid underneath. */
        .hgal.gallery-signature5 figure:nth-child(1) { height: 460px; margin-bottom: 16px; }
        .hgal.gallery-signature5 .hgal-row5 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 16px; }
        .hgal.gallery-signature5 .hgal-row5 figure { height: 180px; }
        .hgal.gallery-signature5 .hgal-rest { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .hgal.gallery-signature5 .hgal-rest figure { height: 220px; }

        /* NEW: cascade -- overlapping diagonal photos, elegant/editorial feel */
        .hgal.gallery-cascade { position: relative; padding: 20px 0 60px; }
        .hgal.gallery-cascade figure { width: 46%; height: 320px; margin-bottom: -60px; box-shadow: 0 20px 45px rgba(0,0,0,0.4); }
        .hgal.gallery-cascade figure:nth-child(odd) { margin-left: 0; }
        .hgal.gallery-cascade figure:nth-child(even) { margin-left: 54%; margin-top: -180px; }

        @media (max-width: 800px) {
            .hgal.gallery-grid2, .hgal.gallery-grid3, .hgal.gallery-grid4, .hgal.gallery-mosaic { grid-template-columns: 1fr 1fr; height: auto; }
            .hgal.gallery-hero .hgal-thumbs, .hgal.gallery-signature5 .hgal-row5, .hgal.gallery-signature5 .hgal-rest { grid-template-columns: repeat(2, 1fr); }
            .hgal.gallery-split figure, .hgal.gallery-split figure:nth-child(even) { width: 100%; margin-left: 0; }
            .hgal.gallery-cascade figure, .hgal.gallery-cascade figure:nth-child(even) { width: 100%; margin: 0 0 16px !important; }
        }
    </style>
    <?php
}

/**
 * Outputs the gallery for one hotel. Returns true if it drew
 * something (has photos), false if there was nothing to show.
 */
function renderHotelGalleryHTML($pdo, $hotel_id) {
    $stmt = $pdo->prepare("SELECT layout, bg_color, theme, font_family FROM hotel_galleries WHERE hotel_id = ?");
    $stmt->execute([$hotel_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['layout' => 'grid2', 'bg_color' => null, 'theme' => 'custom', 'font_family' => 'Inter'];

    $stmt = $pdo->prepare("SELECT image_path, caption FROM hotel_gallery_images WHERE hotel_id = ? ORDER BY sort_order, id");
    $stmt->execute([$hotel_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($images)) return false;

    $layout = $settings['layout'];
    $style = 'font-family:\'' . htmlspecialchars($settings['font_family']) . '\', Georgia, sans-serif;';

    if ($layout === 'hero' || $layout === 'signature5') {
        echo '<div class="hgal gallery-' . htmlspecialchars($layout) . '" style="' . $style . '">';
        $first = $images[0];
        echo '<figure><img src="' . htmlspecialchars($first['image_path']) . '" alt="">';
        if ($first['caption']) echo '<figcaption>' . htmlspecialchars($first['caption']) . '</figcaption>';
        echo '</figure>';

        if ($layout === 'hero') {
            echo '<div class="hgal-thumbs">';
            foreach (array_slice($images, 1) as $img) {
                echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt=""></figure>';
            }
            echo '</div>';
        } else { // signature5
            $row5 = array_slice($images, 1, 4);
            $rest = array_slice($images, 5);
            if (!empty($row5)) {
                echo '<div class="hgal-row5">';
                foreach ($row5 as $img) echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt=""></figure>';
                echo '</div>';
            }
            if (!empty($rest)) {
                echo '<div class="hgal-rest">';
                foreach ($rest as $img) {
                    echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt="">';
                    if ($img['caption']) echo '<figcaption>' . htmlspecialchars($img['caption']) . '</figcaption>';
                    echo '</figure>';
                }
                echo '</div>';
            }
        }
        echo '</div>';
    } else {
        echo '<div class="hgal gallery-' . htmlspecialchars($layout) . '" style="' . $style . '">';
        foreach ($images as $img) {
            echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt="">';
            if ($img['caption']) echo '<figcaption>' . htmlspecialchars($img['caption']) . '</figcaption>';
            echo '</figure>';
        }
        echo '</div>';
    }

    return true;
}