<?php
// gallery_renderer.php
//
// Shared gallery HTML/CSS -- one function used both by hotel_rooms.php
// (embedded directly on the hotel page, which is the whole point of
// this feature -- customers should SEE it without an extra click) and
// hotel_gallery.php (a dedicated full-page view, useful for hotels
// with a lot of photos or when linked from elsewhere).
//
// Call renderHotelGalleryCSS() once per page (outputs the CSS for all
// 10 layouts, scoped so it can't leak into the rest of the page), and
// renderHotelGalleryHTML($pdo, $hotel_id) wherever the gallery itself
// should appear.

function renderHotelGalleryCSS() {
    ?>
    <style>
        .hgal { --hgal-radius: 10px; }
        .hgal figure { margin: 0; position: relative; border-radius: var(--hgal-radius); overflow: hidden; }
        .hgal figure img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .hgal figcaption { position: absolute; bottom: 0; left: 0; right: 0; padding: 10px 14px; background: linear-gradient(transparent, rgba(0,0,0,0.7)); font-size: 12.5px; color: white; }

        .hgal.gallery-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .hgal.gallery-grid2 figure { height: 220px; }

        .hgal.gallery-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        .hgal.gallery-grid3 figure { height: 170px; }

        .hgal.gallery-grid4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .hgal.gallery-grid4 figure { height: 130px; }

        .hgal.gallery-masonry { columns: 3 200px; column-gap: 12px; }
        .hgal.gallery-masonry figure { margin-bottom: 12px; break-inside: avoid; height: auto; }
        .hgal.gallery-masonry figure img { height: auto; }

        .hgal.gallery-carousel { display: flex; gap: 14px; overflow-x: auto; scroll-snap-type: x mandatory; padding-bottom: 10px; }
        .hgal.gallery-carousel figure { flex: 0 0 280px; height: 200px; scroll-snap-align: start; }

        .hgal.gallery-hero figure:first-child { height: 340px; margin-bottom: 12px; }
        .hgal.gallery-hero .hgal-thumbs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .hgal.gallery-hero .hgal-thumbs figure { height: 90px; }

        .hgal.gallery-mosaic { display: grid; grid-template-columns: 2fr 1fr 1fr; grid-template-rows: 1fr 1fr; gap: 10px; height: 380px; }
        .hgal.gallery-mosaic figure:first-child { grid-row: span 2; height: auto; }
        .hgal.gallery-mosaic figure { height: auto; }

        .hgal.gallery-stack figure { height: 280px; margin-bottom: 14px; }

        .hgal.gallery-polaroid { display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; padding-top: 6px; }
        .hgal.gallery-polaroid figure { width: 190px; height: 190px; background: white; padding: 8px 8px 26px; border-radius: 4px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); transform: rotate(-2deg); }
        .hgal.gallery-polaroid figure:nth-child(even) { transform: rotate(2deg); }
        .hgal.gallery-polaroid figure img { height: 100%; border-radius: 2px; }
        .hgal.gallery-polaroid figcaption { position: static; background: none; color: #333; text-align: center; padding-top: 6px; }

        .hgal.gallery-split figure { height: 260px; margin-bottom: 12px; width: 60%; }
        .hgal.gallery-split figure:nth-child(even) { margin-left: 40%; }

        @media (max-width: 700px) {
            .hgal.gallery-grid2, .hgal.gallery-grid3, .hgal.gallery-grid4, .hgal.gallery-mosaic { grid-template-columns: 1fr 1fr; height: auto; }
            .hgal.gallery-hero .hgal-thumbs { grid-template-columns: repeat(2, 1fr); }
            .hgal.gallery-split figure, .hgal.gallery-split figure:nth-child(even) { width: 100%; margin-left: 0; }
        }
    </style>
    <?php
}

/**
 * Outputs the gallery for one hotel. Returns true if it actually drew
 * something (has photos), false if there was nothing to show -- so
 * the caller can decide whether to print a section heading around it.
 *
 * $apply_container_style: when true (the default, used when embedding
 * on hotel_rooms.php), the gallery gets its own colored/padded box so
 * it stands out from the surrounding page. hotel_gallery.php passes
 * false since its whole page background is already the gallery's
 * chosen color -- a second colored box around it would look boxed-in-
 * a-box instead of one clean full-page background.
 */
function renderHotelGalleryHTML($pdo, $hotel_id, $apply_container_style = true) {
    $stmt = $pdo->prepare("SELECT layout, bg_color, font_family FROM hotel_galleries WHERE hotel_id = ?");
    $stmt->execute([$hotel_id]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['layout' => 'grid2', 'bg_color' => null, 'font_family' => 'Inter'];

    $stmt = $pdo->prepare("SELECT image_path, caption FROM hotel_gallery_images WHERE hotel_id = ? ORDER BY sort_order, id");
    $stmt->execute([$hotel_id]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($images)) return false;

    $layout = $settings['layout'];
    $style = 'font-family:\'' . htmlspecialchars($settings['font_family']) . '\', Georgia, sans-serif;';
    if ($apply_container_style && !empty($settings['bg_color'])) {
        $style .= 'background:' . htmlspecialchars($settings['bg_color']) . '; padding:20px; border-radius:16px;';
    }

    echo '<div class="hgal gallery-' . htmlspecialchars($layout) . '" style="' . $style . '">';
    if ($layout === 'hero') {
        $first = $images[0];
        echo '<figure><img src="' . htmlspecialchars($first['image_path']) . '" alt="">';
        if ($first['caption']) echo '<figcaption>' . htmlspecialchars($first['caption']) . '</figcaption>';
        echo '</figure><div class="hgal-thumbs">';
        foreach (array_slice($images, 1) as $img) {
            echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt=""></figure>';
        }
        echo '</div>';
    } else {
        foreach ($images as $img) {
            echo '<figure><img src="' . htmlspecialchars($img['image_path']) . '" alt="">';
            if ($img['caption']) echo '<figcaption>' . htmlspecialchars($img['caption']) . '</figcaption>';
            echo '</figure>';
        }
    }
    echo '</div>';

    return true;
}