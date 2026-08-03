<?php
session_start();
require_once 'config.php';

// Pexels supports on-the-fly resizing/compression via query params --
// requesting an appropriately-sized image instead of the original
// (often several MB) is what actually keeps this page fast.
function pexels_optimize($url, $width) {
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'auto=compress&cs=tinysrgb&w=' . $width;
}

// Curated gallery data -- licensed, free-to-use Pexels photos, general
// representative imagery rather than verified photos of any specific
// named property (so no hotel brand name is attached to them).
$gallery_hotels = [
    [
        'title' => 'Tower Suite Living',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Wake to golden light spilling across marble floors, with panoramic views stretching toward the horizon. Every corner of this stay whispers quiet, unhurried luxury.',
        'main_image' => 'https://images.pexels.com/photos/36859188/pexels-photo-36859188.jpeg',
        'thumbs' => [
            'https://images.pexels.com/photos/34496715/pexels-photo-34496715.jpeg',
            'https://images.pexels.com/photos/13008559/pexels-photo-13008559.jpeg',
            'https://images.pexels.com/photos/8082195/pexels-photo-8082195.jpeg',
        ],
    ],
    [
        'title' => 'Timeless Comfort',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Where crisp linens meet warm hospitality, and each evening unwinds into soft, lantern-lit calm. A retreat designed for stillness after a long day of travel.',
        'main_image' => 'https://images.pexels.com/photos/34956811/pexels-photo-34956811.jpeg',
        'thumbs' => [
            'https://images.pexels.com/photos/18285942/pexels-photo-18285942.jpeg',
            'https://images.pexels.com/photos/34496702/pexels-photo-34496702.jpeg',
            'https://images.pexels.com/photos/7018822/pexels-photo-7018822.jpeg',
        ],
    ],
    [
        'title' => 'Modern Sanctuary',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Sunlit corners, deep soaking tubs, and interiors dressed in warm neutrals -- built for travelers who linger a while, rather than simply pass through.',
        'main_image' => 'https://images.pexels.com/photos/5659779/pexels-photo-5659779.jpeg',
        'thumbs' => [
            'https://images.pexels.com/photos/36852529/pexels-photo-36852529.jpeg',
            'https://images.pexels.com/photos/36852544/pexels-photo-36852544.jpeg',
            'https://images.pexels.com/photos/19988067/pexels-photo-19988067.jpeg',
        ],
    ],
    [
        'title' => 'Refined Retreat',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'A place where minimalist elegance meets heartfelt service, and every room feels like a quiet exhale at the end of a long journey.',
        'main_image' => 'https://images.pexels.com/photos/35231800/pexels-photo-35231800.jpeg',
        'thumbs' => [
            'https://images.pexels.com/photos/28347470/pexels-photo-28347470.jpeg',
            'https://images.pexels.com/photos/34354407/pexels-photo-34354407.jpeg',
            'https://images.pexels.com/photos/35103156/pexels-photo-35103156.jpeg',
        ],
    ],
    [
        'title' => 'Royal Serenity',
        'city' => 'Mecca',
        'rating' => 5,
        'paragraph' => 'Rich textures, soft golden light, and rooms that feel less like a place to sleep and more like a private, unhurried sanctuary.',
        'main_image' => 'https://images.pexels.com/photos/24284828/pexels-photo-24284828.jpeg',
        'thumbs' => [
            'https://images.pexels.com/photos/32764943/pexels-photo-32764943.jpeg',
            'https://images.pexels.com/photos/36852535/pexels-photo-36852535.jpeg',
            'https://images.pexels.com/photos/35027131/pexels-photo-35027131.jpeg',
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
        body { font-family: 'Inter', sans-serif; background: radial-gradient(ellipse 120% 80% at 50% 0%, #fdf8ec 0%, #f8f0dc 55%, #f2e6c8 100%); }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        /* NEW: elegant ambient background -- soft gold glows on navy,
           matching the rest of the site, now actually VISIBLE as
           breathing room around the framed photos instead of being
           fully covered edge-to-edge by them. The drift animation here
           is cheap (just a transform on one fixed pseudo-element) --
           it doesn't touch any photo, so it stays smooth. */
        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background:
                radial-gradient(circle at 18% 15%, rgba(184,146,46,0.12), transparent 40%),
                radial-gradient(circle at 85% 20%, rgba(184,146,46,0.08), transparent 35%),
                radial-gradient(circle at 50% 95%, rgba(184,146,46,0.09), transparent 45%);
            animation: driftGlow 24s ease-in-out infinite alternate;
            will-change: transform;
        }
        @keyframes driftGlow {
            0%   { transform: translate(0, 0) scale(1); }
            50%  { transform: translate(-2.5%, 2%) scale(1.05); }
            100% { transform: translate(2%, -2%) scale(1.02); }
        }
        .bg-ambient::after {
            content: ''; position: absolute; inset: 0; opacity: 0.5;
            background-image: radial-gradient(rgba(184,146,46,0.14) 1px, transparent 1px);
            background-size: 32px 32px;
        }
        @media (prefers-reduced-motion: reduce) {
            .bg-ambient::before { animation: none; }
        }

        /* NEW: a couple of very faint, slow-floating motifs for extra
           depth -- pure CSS opacity/transform on font-icons, negligible
           performance cost. */
        .bg-motif { position: fixed; z-index: 0; color: #b8922e; opacity: 0.06; pointer-events: none; animation: motifFloat 30s ease-in-out infinite; }
        .bg-motif.m1 { font-size: 220px; top: -50px; right: -40px; }
        .bg-motif.m2 { font-size: 130px; bottom: -20px; left: -30px; animation-delay: -12s; opacity: 0.05; }
        @keyframes motifFloat { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-22px) rotate(7deg); } }
        @media (prefers-reduced-motion: reduce) { .bg-motif { animation: none; } }

        .g-preloader {
            position: fixed; inset: 0; z-index: 999; background: #f8f0dc;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        .g-preloader.done { opacity: 0; visibility: hidden; pointer-events: none; }
        .pl-ring {
            width: 40px; height: 40px; border-radius: 50%;
            border: 2px solid rgba(184,146,46,0.18); border-top-color: #b8922e;
            animation: plSpin 0.8s linear infinite;
        }
        @keyframes plSpin { to { transform: rotate(360deg); } }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 40; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .gtop { position: fixed; top: 0; left: 0; right: 0; z-index: 100; padding: 22px 40px; display: flex; justify-content: space-between; align-items: center; pointer-events: none; }
        .gtop .glogo, .gtop .gback { pointer-events: auto; }
        .gtop .glogo { font-family: 'Playfair Display', serif; color: #2b2416; font-size: 19px; font-weight: 800; text-decoration: none; letter-spacing: -0.3px; }
        .gtop .glogo span { color: #b8922e; }
        .gtop .gback { color: rgba(43,36,22,0.6); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 7px; transition: color 0.25s ease; }
        .gtop .gback:hover { color: #b8922e; }

        /* ===== Stage: each slide is now a centered, padded column --
           main photo on top, 3 room photos in a row below it -- both
           framed (rounded corners, border, shadow) so the elegant dark
           background shows through as breathing room around them. ===== */
        .stage { position: relative; width: 100%; height: 100%; z-index: 1; }
        .hslide {
            position: absolute; inset: 0; opacity: 0; pointer-events: none;
            transition: opacity 0.6s ease;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            padding: 86px 60px 40px;
        }
        .hslide.active { opacity: 1; pointer-events: auto; }

        .main-photo {
            position: relative; width: 100%; max-width: 920px; height: 46vh; min-height: 300px;
            border-radius: 20px; overflow: hidden;
            border: 1px solid rgba(184,146,46,0.2);
            box-shadow: 0 30px 60px rgba(43,36,22,0.22);
            opacity: 0; transform: translateY(16px);
        }
        .hslide.active .main-photo { animation: mIn 0.6s ease forwards; }
        @keyframes mIn { to { opacity: 1; transform: translateY(0); } }
        .main-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .main-photo .scrim {
            position: absolute; inset: 0;
            background: linear-gradient(0deg, rgba(5,7,13,0.92) 0%, rgba(5,7,13,0.1) 45%, transparent 62%);
        }
        .main-photo .m-info { position: absolute; left: 0; right: 0; bottom: 0; padding: 30px 34px; z-index: 2; }
        .m-tag {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(212,175,55,0.14); border: 1px solid rgba(212,175,55,0.28);
            color: #d4af37; font-size: 10px; font-weight: 700; letter-spacing: 1.1px; text-transform: uppercase;
            padding: 5px 13px; border-radius: 20px; margin-bottom: 12px;
        }
        .m-info h1 {
            font-family: 'Playfair Display', serif; font-weight: 800; color: white;
            font-size: 32px; line-height: 1.12; margin-bottom: 8px; letter-spacing: -0.5px;
        }
        .m-stars { color: #d4af37; font-size: 13px; letter-spacing: 2px; margin-bottom: 10px; }
        .m-para {
            max-width: 560px; color: rgba(255,255,255,0.68); font-size: 13.5px; line-height: 1.65;
        }

        /* Room photos row -- no text labels on purpose, just clean
           framed thumbnails beneath the main photo. */
        .rooms-row {
            display: flex; gap: 18px; width: 100%; max-width: 920px; margin-top: 20px;
            height: 17vh; min-height: 110px;
        }
        .room-photo {
            flex: 1; border-radius: 14px; overflow: hidden;
            border: 1px solid rgba(43,36,22,0.08);
            box-shadow: 0 14px 30px rgba(43,36,22,0.16);
            opacity: 0; transform: translateY(14px);
        }
        .hslide.active .room-photo { animation: rIn 0.5s ease forwards; }
        .hslide.active .room-photo:nth-child(1) { animation-delay: 0.15s; }
        .hslide.active .room-photo:nth-child(2) { animation-delay: 0.24s; }
        .hslide.active .room-photo:nth-child(3) { animation-delay: 0.33s; }
        @keyframes rIn { to { opacity: 1; transform: translateY(0); } }
        .room-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }

        /* ===== Counter ===== */
        .g-counter {
            position: fixed; top: 80px; right: 40px; z-index: 50;
            color: rgba(43,36,22,0.5); font-size: 13px; display: flex; align-items: center; gap: 8px;
        }
        .g-counter .cur { color: #b8922e; font-weight: 700; font-size: 16px; }
        .g-counter .bar { width: 60px; height: 2px; background: rgba(43,36,22,0.12); border-radius: 2px; overflow: hidden; position: relative; }
        .g-counter .bar-fill { position: absolute; left: 0; top: 0; bottom: 0; background: #b8922e; transition: width 0.5s ease; }

        /* ===== Arrows ===== */
        .g-arrow {
            position: fixed; top: 50%; transform: translateY(-50%); z-index: 50;
            width: 48px; height: 48px; border-radius: 50%;
            background: rgba(255,255,255,0.5); backdrop-filter: blur(10px);
            border: 1px solid rgba(43,36,22,0.1); color: #2b2416;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
            cursor: pointer; transition: background 0.25s ease, border-color 0.25s ease, color 0.25s ease;
        }
        .g-arrow:hover { background: #b8922e; color: #fdf8ec; border-color: #b8922e; }
        .g-arrow.gprev { left: 22px; }
        .g-arrow.gnext { right: 22px; }

        /* ===== Bottom dots ===== */
        .g-dots { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 50; display: flex; gap: 9px; }
        .g-dots span { width: 8px; height: 8px; border-radius: 50%; background: rgba(43,36,22,0.18); cursor: pointer; transition: background 0.25s ease, width 0.25s ease; }
        .g-dots span.active { background: #b8922e; width: 26px; border-radius: 5px; }

        @media (max-width: 900px) {
            .hslide { padding: 76px 20px 60px; }
            .main-photo { height: 38vh; }
            .m-info h1 { font-size: 24px; }
            .rooms-row { height: 13vh; gap: 10px; }
            .gtop { padding: 16px 18px; }
            .g-arrow { width: 40px; height: 40px; font-size: 13px; }
            .g-arrow.gprev { left: 8px; } .g-arrow.gnext { right: 8px; }
            .g-counter { right: 18px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .main-photo, .room-photo { animation: none !important; opacity: 1 !important; transform: none !important; }
        }
    </style>
</head>
<body>
    <div class="g-preloader" id="gPreloader"><div class="pl-ring"></div></div>
    <div class="bg-ambient" aria-hidden="true">
        <i class="fas fa-star-and-crescent bg-motif m1"></i>
        <i class="fas fa-star-and-crescent bg-motif m2"></i>
    </div>
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
            <div class="main-photo">
                <img src="<?php echo htmlspecialchars(pexels_optimize($h['main_image'], 1200)); ?>" alt="<?php echo htmlspecialchars($h['title']); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" decoding="async">
                <div class="scrim"></div>
                <div class="m-info">
                    <span class="m-tag"><?php echo htmlspecialchars($h['city']); ?></span>
                    <h1><?php echo htmlspecialchars($h['title']); ?></h1>
                    <div class="m-stars"><?php echo str_repeat('★', (int)$h['rating']); ?></div>
                    <p class="m-para"><?php echo htmlspecialchars($h['paragraph']); ?></p>
                </div>
            </div>
            <div class="rooms-row">
                <?php foreach ($h['thumbs'] as $thumbUrl): ?>
                    <div class="room-photo">
                        <img src="<?php echo htmlspecialchars(pexels_optimize($thumbUrl, 500)); ?>" alt="" loading="lazy" decoding="async">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="g-arrow gprev" id="gPrev" aria-label="Previous"><i class="fas fa-chevron-left"></i></div>
    <div class="g-arrow gnext" id="gNext" aria-label="Next"><i class="fas fa-chevron-right"></i></div>

    <div class="g-dots" id="gDots">
        <?php foreach ($gallery_hotels as $i => $h): ?>
            <span class="<?php echo $i === 0 ? 'active' : ''; ?>" data-index="<?php echo $i; ?>"></span>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div style="position:relative; z-index:2; height:100%; display:flex; align-items:center; justify-content:center; color:rgba(43,36,22,0.4); font-family:'Playfair Display',serif; font-size:20px;">
            No hotels to show yet.
        </div>
    <?php endif; ?>

<script>
(function() {
    const preloader = document.getElementById('gPreloader');
    function hidePreloader() { preloader.classList.add('done'); }
    const firstImg = document.querySelector('.hslide.active .main-photo img');
    if (firstImg) {
        if (firstImg.complete) {
            hidePreloader();
        } else {
            firstImg.addEventListener('load', hidePreloader);
            firstImg.addEventListener('error', hidePreloader);
        }
    } else {
        hidePreloader();
    }
    setTimeout(hidePreloader, 2500);

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