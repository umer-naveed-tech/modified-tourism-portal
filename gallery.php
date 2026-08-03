<?php
session_start();
require_once 'config.php';

// NEW: Pexels supports on-the-fly resizing/compression via query params.
// Requesting an appropriately-sized image (instead of the original,
// often several MB, resolution) is the real fix for slow loading --
// far more effective than removing animations alone.
function pexels_optimize($url, $width) {
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'auto=compress&cs=tinysrgb&w=' . $width;
}

// NEW: curated gallery data. These are licensed, free-to-use stock
// photos (Pexels) -- general representative imagery, not verified
// photos of any specific named property. Deliberately not labelled
// with hotel brand names (misleading to attach a real hotel's name to
// a generic photo); presented instead as an elegant "stay experience"
// showcase, matching how the reference design used city names rather
// than specific hotel branding.
$gallery_hotels = [
    [
        'title' => 'Tower Suite Living',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Wake to golden light spilling across marble floors, with panoramic views stretching toward the horizon. Every corner of this stay whispers quiet, unhurried luxury.',
        'main_image' => 'https://images.pexels.com/photos/36859188/pexels-photo-36859188.jpeg',
        'thumbs' => [
            ['url' => 'https://images.pexels.com/photos/34496715/pexels-photo-34496715.jpeg', 'label' => 'Premium Suite', 'sub' => 'Space to breathe, styled to impress'],
            ['url' => 'https://images.pexels.com/photos/13008559/pexels-photo-13008559.jpeg', 'label' => 'Guest Room', 'sub' => 'Comfort in every quiet detail'],
            ['url' => 'https://images.pexels.com/photos/8082195/pexels-photo-8082195.jpeg', 'label' => 'Hotel Amenities', 'sub' => 'Thoughtful touches throughout'],
        ],
    ],
    [
        'title' => 'Timeless Comfort',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Where crisp linens meet warm hospitality, and each evening unwinds into soft, lantern-lit calm. A retreat designed for stillness after a long day of travel.',
        'main_image' => 'https://images.pexels.com/photos/34956811/pexels-photo-34956811.jpeg',
        'thumbs' => [
            ['url' => 'https://images.pexels.com/photos/18285942/pexels-photo-18285942.jpeg', 'label' => 'Master Bedroom', 'sub' => 'A calm, cocooning retreat'],
            ['url' => 'https://images.pexels.com/photos/34496702/pexels-photo-34496702.jpeg', 'label' => 'Guest Room', 'sub' => 'Warm tones, restful nights'],
            ['url' => 'https://images.pexels.com/photos/7018822/pexels-photo-7018822.jpeg', 'label' => 'Hotel Amenities', 'sub' => 'Every comfort, close at hand'],
        ],
    ],
    [
        'title' => 'Modern Sanctuary',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Sunlit corners, deep soaking tubs, and interiors dressed in warm neutrals -- built for travelers who linger a while, rather than simply pass through.',
        'main_image' => 'https://images.pexels.com/photos/5659779/pexels-photo-5659779.jpeg',
        'thumbs' => [
            ['url' => 'https://images.pexels.com/photos/36852529/pexels-photo-36852529.jpeg', 'label' => 'Guest Room', 'sub' => 'Light-filled, softly furnished'],
            ['url' => 'https://images.pexels.com/photos/36852544/pexels-photo-36852544.jpeg', 'label' => 'Suite Interior', 'sub' => 'Designed for longer stays'],
            ['url' => 'https://images.pexels.com/photos/19988067/pexels-photo-19988067.jpeg', 'label' => 'Hotel Amenities', 'sub' => 'Small luxuries, well placed'],
        ],
    ],
    [
        'title' => 'Refined Retreat',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'A place where minimalist elegance meets heartfelt service, and every room feels like a quiet exhale at the end of a long journey.',
        'main_image' => 'https://images.pexels.com/photos/35231800/pexels-photo-35231800.jpeg',
        'thumbs' => [
            ['url' => 'https://images.pexels.com/photos/28347470/pexels-photo-28347470.jpeg', 'label' => 'Guest Room', 'sub' => 'Clean lines, calm palette'],
            ['url' => 'https://images.pexels.com/photos/34354407/pexels-photo-34354407.jpeg', 'label' => 'Suite Interior', 'sub' => 'Quiet, considered elegance'],
            ['url' => 'https://images.pexels.com/photos/35103156/pexels-photo-35103156.jpeg', 'label' => 'Hotel Amenities', 'sub' => 'Service that anticipates you'],
        ],
    ],
    [
        'title' => 'Royal Serenity',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Rich textures, soft golden light, and rooms that feel less like a place to sleep and more like a private, unhurried sanctuary.',
        'main_image' => 'https://images.pexels.com/photos/24284828/pexels-photo-24284828.jpeg',
        'thumbs' => [
            ['url' => 'https://images.pexels.com/photos/32764943/pexels-photo-32764943.jpeg', 'label' => 'Guest Room', 'sub' => 'Golden light, gentle textures'],
            ['url' => 'https://images.pexels.com/photos/36852535/pexels-photo-36852535.jpeg', 'label' => 'Suite Interior', 'sub' => 'A room built for stillness'],
            ['url' => 'https://images.pexels.com/photos/35027131/pexels-photo-35027131.jpeg', 'label' => 'Hotel Amenities', 'sub' => 'Refined, unhurried comfort'],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Gallery | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; overflow: hidden; }
        body { font-family: 'Inter', sans-serif; background: #05070d; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #05070d; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        /* NEW: small, elegant preloader -- shown until the first hero
           photo has actually finished loading, so the page never shows
           a blank/broken flash while images (even compressed ones)
           are still downloading. No airplane icon here on purpose --
           a plain gold ring fits the hotel-gallery mood better. */
        .g-preloader {
            position: fixed; inset: 0; z-index: 999; background: #05070d;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        .g-preloader.done { opacity: 0; visibility: hidden; }
        .pl-ring {
            width: 40px; height: 40px; border-radius: 50%;
            border: 2px solid rgba(212,175,55,0.15); border-top-color: #d4af37;
            animation: plSpin 0.8s linear infinite;
        }
        @keyframes plSpin { to { transform: rotate(360deg); } }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 40; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ===== Top bar ===== */
        .gtop { position: fixed; top: 0; left: 0; right: 0; z-index: 50; padding: 26px 44px; display: flex; justify-content: space-between; align-items: center; }
        .gtop .glogo { font-family: 'Playfair Display', serif; color: white; font-size: 20px; font-weight: 800; text-decoration: none; letter-spacing: -0.3px; }
        .gtop .glogo span { color: #d4af37; }
        .gtop .gback { color: rgba(255,255,255,0.65); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 7px; transition: color 0.25s ease; }
        .gtop .gback:hover { color: #d4af37; }

        /* ===== Stage ===== */
        .stage { position: relative; width: 100%; height: 100%; }
        .hslide {
            position: absolute; inset: 0; opacity: 0; pointer-events: none;
            transition: opacity 0.6s ease;
            display: flex;
        }
        .hslide.active { opacity: 1; pointer-events: auto; }

        /* Hero (main image) -- left ~62%. NEW: no Ken-Burns zoom and no
           mouse-parallax anymore -- both were continuous, GPU-heavy
           animations that made the page feel laggy with this many
           large photos in play. The image is simply shown, static. */
        .hero-pane { position: relative; flex: 0 0 62%; overflow: hidden; }
        .hero-pane .bg { position: absolute; inset: 0; background-size: cover; background-position: center; }
        .hero-pane .scrim {
            position: absolute; inset: 0;
            background: linear-gradient(0deg, rgba(5,7,13,0.95) 0%, rgba(5,7,13,0.15) 40%, rgba(5,7,13,0.05) 60%, rgba(5,7,13,0.55) 100%),
                        linear-gradient(90deg, rgba(5,7,13,0.1) 0%, transparent 30%);
        }
        .hero-info { position: absolute; left: 0; right: 0; bottom: 0; padding: 0 56px 60px; z-index: 2; max-width: 640px; }
        .hero-tag {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(212,175,55,0.12); border: 1px solid rgba(212,175,55,0.25);
            color: #d4af37; font-size: 10.5px; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase;
            padding: 6px 15px; border-radius: 20px; margin-bottom: 20px;
            opacity: 0; transform: translateY(14px);
        }
        .hslide.active .hero-tag { animation: sIn 0.6s ease forwards; animation-delay: 0.1s; }
        .hero-info h1 {
            font-family: 'Playfair Display', serif; font-weight: 800; color: white;
            font-size: 50px; line-height: 1.08; margin-bottom: 16px; letter-spacing: -0.8px;
            opacity: 0; transform: translateY(20px);
        }
        .hslide.active .hero-info h1 { animation: sIn 0.65s ease forwards; animation-delay: 0.2s; }
        .hero-divider {
            width: 56px; height: 3px; background: #d4af37; border-radius: 2px; margin-bottom: 16px;
            opacity: 0; transform: scaleX(0); transform-origin: left;
        }
        .hslide.active .hero-divider { animation: dIn 0.5s ease forwards; animation-delay: 0.28s; }
        @keyframes dIn { to { opacity: 1; transform: scaleX(1); } }
        .hero-stars { color: #d4af37; font-size: 15px; letter-spacing: 3px; opacity: 0; transform: translateY(14px); margin-bottom: 16px; }
        .hslide.active .hero-stars { animation: sIn 0.6s ease forwards; animation-delay: 0.34s; }

        /* NEW: elegant descriptive paragraph -- carries the atmosphere
           now that there's no hotel brand name to lean on. Reworked
           typography: no italic (reads cleaner at this size), a subtle
           decorative quote mark, and better line length/height. */
        .hero-para { max-width: 480px; opacity: 0; transform: translateY(14px); position: relative; padding-left: 22px; }
        .hslide.active .hero-para { animation: sIn 0.6s ease forwards; animation-delay: 0.42s; }
        .hero-para::before {
            content: '\201C'; position: absolute; left: -6px; top: -18px;
            font-family: 'Playfair Display', serif; font-size: 60px; color: rgba(212,175,55,0.3);
            line-height: 1;
        }
        .hero-para p { color: rgba(255,255,255,0.72); font-size: 15.5px; line-height: 1.8; font-weight: 400; letter-spacing: 0.1px; }
        @keyframes sIn { to { opacity: 1; transform: translateY(0); } }

        /* Room-photo pane -- right ~38%. NEW: a clean vertical stack of
           exactly 3 tiles (every hotel now has exactly 3 room photos),
           closer to the reference layout's stacked-card feel, and no
           more empty grid cells when a hotel had fewer than 4 photos. */
        .thumb-pane {
            flex: 0 0 38%; height: 100%; display: flex; flex-direction: column; gap: 3px; background: #05070d;
        }
        .thumb-tile { position: relative; overflow: hidden; flex: 1; opacity: 0; transform: translateY(10px); }
        .hslide.active .thumb-tile { animation: tIn 0.5s ease forwards; }
        .hslide.active .thumb-tile:nth-child(1) { animation-delay: 0.22s; }
        .hslide.active .thumb-tile:nth-child(2) { animation-delay: 0.32s; }
        .hslide.active .thumb-tile:nth-child(3) { animation-delay: 0.42s; }
        @keyframes tIn { to { opacity: 1; transform: translateY(0); } }
        .thumb-tile img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .thumb-tile::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(0deg, rgba(5,7,13,0.9) 0%, rgba(5,7,13,0.15) 50%, transparent 70%);
        }
        .thumb-tile .tlabel {
            position: absolute; left: 18px; bottom: 30px; z-index: 2;
            color: white; font-size: 15px; font-weight: 700; letter-spacing: 0.2px;
            font-family: 'Playfair Display', serif;
        }
        .thumb-tile .tsub {
            position: absolute; left: 18px; bottom: 12px; right: 18px; z-index: 2;
            color: rgba(255,255,255,0.55); font-size: 11.5px; font-weight: 400;
        }

        /* ===== Counter ===== */
        .g-counter {
            position: fixed; top: 30px; right: 44px; z-index: 50;
            color: rgba(255,255,255,0.5); font-size: 13px; display: flex; align-items: center; gap: 8px;
        }
        .g-counter .cur { color: #d4af37; font-weight: 700; font-size: 16px; }
        .g-counter .bar { width: 60px; height: 2px; background: rgba(255,255,255,0.12); border-radius: 2px; overflow: hidden; position: relative; }
        .g-counter .bar-fill { position: absolute; left: 0; top: 0; bottom: 0; background: #d4af37; transition: width 0.5s ease; }

        /* ===== Arrows ===== */
        .g-arrow {
            position: fixed; top: 50%; transform: translateY(-50%); z-index: 50;
            width: 52px; height: 52px; border-radius: 50%;
            background: rgba(255,255,255,0.05); backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1); color: white;
            display: flex; align-items: center; justify-content: center; font-size: 17px;
            cursor: pointer; transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease;
        }
        .g-arrow:hover { background: #d4af37; color: #0a0f1e; border-color: #d4af37; }
        .g-arrow.gprev { left: 26px; }
        .g-arrow.gnext { right: 26px; }

        /* ===== Bottom dots ===== */
        .g-dots { position: fixed; bottom: 24px; left: 44px; z-index: 50; display: flex; gap: 9px; }
        .g-dots span { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.2); cursor: pointer; transition: background 0.25s ease, width 0.25s ease; }
        .g-dots span.active { background: #d4af37; width: 26px; border-radius: 5px; }

        @media (max-width: 900px) {
            .hslide { flex-direction: column; }
            .hero-pane { flex: 0 0 58%; }
            .thumb-pane { flex: 0 0 42%; }
            .hero-info { padding: 0 24px 24px; }
            .hero-info h1 { font-size: 30px; }
            .gtop { padding: 18px 20px; }
            .g-arrow { width: 42px; height: 42px; font-size: 14px; }
            .g-arrow.gprev { left: 10px; } .g-arrow.gnext { right: 10px; }
            .g-dots { left: 50%; transform: translateX(-50%); bottom: 14px; }
            .g-counter { right: 20px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .hero-tag, .hero-info h1, .hero-stars, .hero-para, .thumb-tile, .hero-divider { animation: none !important; opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>
<body>
    <div class="g-preloader" id="gPreloader"><div class="pl-ring"></div></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <div class="gtop">
        <a href="index.php" class="glogo">Ahmed<span>Travels</span></a>
        <a href="index.php" class="gback"><i class="fas fa-xmark"></i> Close Gallery</a>
    </div>

    <?php if (!empty($gallery_hotels)): ?>
    <div class="g-counter">
        <span class="cur" id="gCur">01</span>
        <div class="bar"><div class="bar-fill" id="gBarFill" style="width: 0%;"></div></div>
        <span id="gTotal"><?php echo str_pad(count($gallery_hotels), 2, '0', STR_PAD_LEFT); ?></span>
    </div>

    <div class="stage" id="gStage">
        <?php foreach ($gallery_hotels as $i => $h): ?>
        <div class="hslide<?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo $i; ?>">
            <div class="hero-pane">
                <div class="bg" style="background-image:url('<?php echo htmlspecialchars(pexels_optimize($h['main_image'], 1400)); ?>');" data-full="<?php echo htmlspecialchars($h['main_image']); ?>"></div>
                <div class="scrim"></div>
                <div class="hero-info">
                    <span class="hero-tag"><?php echo htmlspecialchars($h['city']); ?></span>
                    <h1><?php echo htmlspecialchars($h['title']); ?></h1>
                    <div class="hero-divider"></div>
                    <div class="hero-stars"><?php echo str_repeat('★', (int)$h['rating']); ?></div>
                    <div class="hero-para"><p><?php echo htmlspecialchars($h['paragraph']); ?></p></div>
                </div>
            </div>
            <div class="thumb-pane">
                <?php foreach ($h['thumbs'] as $t): ?>
                    <div class="thumb-tile">
                        <img src="<?php echo htmlspecialchars(pexels_optimize($t['url'], 500)); ?>" alt="<?php echo htmlspecialchars($t['label']); ?>" loading="lazy" decoding="async">
                        <span class="tlabel"><?php echo htmlspecialchars($t['label']); ?></span>
                        <span class="tsub"><?php echo htmlspecialchars($t['sub']); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="g-arrow gprev" id="gPrev" aria-label="Previous hotel"><i class="fas fa-chevron-left"></i></div>
    <div class="g-arrow gnext" id="gNext" aria-label="Next hotel"><i class="fas fa-chevron-right"></i></div>

    <div class="g-dots" id="gDots">
        <?php foreach ($gallery_hotels as $i => $h): ?>
            <span class="<?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div style="position:relative; z-index:2; height:100%; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.4); font-family:'Playfair Display',serif; font-size:20px;">
            No hotels to show yet.
        </div>
    <?php endif; ?>

<script>
(function() {
    /* NEW: hide the preloader once the FIRST hero photo has actually
       finished downloading (not just "page HTML parsed"), with a safety
       timeout so it can never get stuck forever if a photo fails. */
    const preloader = document.getElementById('gPreloader');
    const firstBg = document.querySelector('.hslide.active .bg');
    function hidePreloader() { preloader.classList.add('done'); }
    if (firstBg) {
        const m = firstBg.style.backgroundImage.match(/url\("?'?(.*?)"?'?\)$/);
        const url = m ? m[1] : null;
        if (url) {
            const probe = new Image();
            probe.onload = hidePreloader;
            probe.onerror = hidePreloader;
            probe.src = url;
        } else {
            hidePreloader();
        }
    } else {
        hidePreloader();
    }
    setTimeout(hidePreloader, 2500); // safety net

    const slides = Array.from(document.querySelectorAll('.hslide'));
    if (slides.length === 0) return;

    const dots = Array.from(document.querySelectorAll('#gDots span'));
    const curEl = document.getElementById('gCur');
    const barFill = document.getElementById('gBarFill');
    const prevBtn = document.getElementById('gPrev');
    const nextBtn = document.getElementById('gNext');
    let current = 0;
    let autoTimer;

    function pad(n) { return String(n + 1).padStart(2, '0'); }

    function goTo(index) {
        current = (index + slides.length) % slides.length;
        slides.forEach((s, i) => s.classList.toggle('active', i === current));
        dots.forEach((d, i) => d.classList.toggle('active', i === current));
        curEl.textContent = pad(current);
        barFill.style.width = (((current + 1) / slides.length) * 100) + '%';
        resetAuto();
    }

    function next() { goTo(current + 1); }
    function prev() { goTo(current - 1); }

    function resetAuto() {
        clearTimeout(autoTimer);
        autoTimer = setTimeout(next, 7500);
    }

    nextBtn.addEventListener('click', next);
    prevBtn.addEventListener('click', prev);
    dots.forEach(d => d.addEventListener('click', () => goTo(parseInt(d.dataset.index, 10))));

    document.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') next();
        if (e.key === 'ArrowLeft') prev();
        if (e.key === 'Escape') window.location.href = 'index.php';
    });

    resetAuto();
})();
</script>
</body>
</html>