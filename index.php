<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahmed Travels - Your Trusted Travel Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; } /* NEW: silky anchor-link scrolling */
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; background: #0a0f1e; cursor: default; }

        /* NEW: display serif for headings only -- the single biggest
           "this looks expensive" lever. Body copy stays on Inter. */
        h1, h2, .navbar-brand, .slide-content h1, .section-title h2, .footer h4 {
            font-family: 'Playfair Display', serif;
        }

        /* NEW: gold-themed scrollbar (WebKit + Firefox) */
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: #d4af37; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        /* ============================================================
           Ambient animated background (from previous pass, kept)
           ============================================================ */
        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: '';
            position: absolute; inset: -20%;
            background:
                radial-gradient(circle at 20% 20%, rgba(212,175,55,0.10), transparent 40%),
                radial-gradient(circle at 80% 30%, rgba(212,175,55,0.06), transparent 35%),
                radial-gradient(circle at 50% 80%, rgba(212,175,55,0.08), transparent 45%);
            animation: driftGlow 22s ease-in-out infinite alternate;
        }
        .bg-ambient::after {
            content: '';
            position: absolute; inset: 0;
            opacity: 0.5;
            background-image: radial-gradient(rgba(212,175,55,0.08) 1px, transparent 1px);
            background-size: 34px 34px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 40%, transparent 100%);
        }
        @keyframes driftGlow {
            0%   { transform: translate(0, 0) scale(1); }
            50%  { transform: translate(-3%, 2%) scale(1.06); }
            100% { transform: translate(2%, -2%) scale(1.02); }
        }

        /* NEW: slow-drifting geometric star accents (pure decoration) */
        .bg-shape {
            position: absolute;
            opacity: 0.05;
            color: #d4af37;
            font-size: 120px;
            animation: floatShape 26s ease-in-out infinite;
            pointer-events: none;
        }
        .bg-shape.s1 { top: 8%; left: 6%; animation-delay: 0s; }
        .bg-shape.s2 { top: 60%; right: 8%; font-size: 90px; animation-delay: -8s; }
        .bg-shape.s3 { bottom: 6%; left: 15%; font-size: 70px; animation-delay: -16s; }
        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(12deg); }
        }

        /* ============================================================
           NEW: Preloader -- brief branded reveal before first paint.
           Pure CSS/JS, doesn't gate or alter any page logic; just fades
           itself out once the window has loaded.
           ============================================================ */
        .preloader {
            position: fixed; inset: 0; z-index: 99999;
            background: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            transition: opacity 0.6s ease, visibility 0.6s ease;
        }
        .preloader.done { opacity: 0; visibility: hidden; }
        .preloader-ring {
            width: 56px; height: 56px;
            border: 2px solid rgba(212,175,55,0.15);
            border-top-color: #d4af37;
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
            position: relative;
        }
        .preloader-ring::after {
            content: '\2708';
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px; color: #d4af37;
            animation: spin 0.9s linear infinite reverse;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* ============================================================
           NEW: Instant hero spotlight -- unlike a custom cursor, this
           has NO smoothing/lerp at all. It's bound 1:1 to the mouse
           position via a CSS custom property set directly on
           mousemove, so it never lags behind the real cursor (the
           native OS cursor stays visible throughout, exactly as
           before -- nothing hijacks or replaces it).
           ============================================================ */
        .hero-spotlight {
            position: absolute; inset: 0;
            z-index: 1;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.4s ease;
            background: radial-gradient(circle 260px at var(--sx,50%) var(--sy,50%), rgba(212,175,55,0.16), transparent 70%);
        }
        .hero-slider:hover .hero-spotlight { opacity: 1; }
        @media (pointer: coarse) { .hero-spotlight { display: none; } }

        /* NEW: fine cinematic grain overlay for extra depth/texture --
           static (no animation cost), sits above everything else at a
           very low opacity so it reads as "film" rather than noise. */
        .grain-overlay {
            position: fixed; inset: 0;
            z-index: 9997;
            pointer-events: none;
            opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .page-content { animation: fadeIn 0.5s ease forwards; position: relative; z-index: 1; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 0.7s cubic-bezier(.2,.7,.3,1), transform 0.7s cubic-bezier(.2,.7,.3,1); }
        .reveal.in-view { opacity: 1; transform: translateY(0); }
        .reveal-delay-1.in-view { transition-delay: 0.08s; }
        .reveal-delay-2.in-view { transition-delay: 0.16s; }
        .reveal-delay-3.in-view { transition-delay: 0.24s; }

        .btn, button, .btn-book, .nav-btn, .action-btn { transition: all 0.3s ease; cursor: pointer; }
        .btn:hover, button:hover, .btn-book:hover, .nav-btn:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }

        .service-card, .stat-card { transition: transform 0.15s ease, box-shadow 0.3s ease; }
        .service-card:hover, .stat-card:hover { box-shadow: 0 12px 40px rgba(0,0,0,0.3); }

        input:focus, select:focus, textarea:focus { border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08); outline: none; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: fixed; width: 100%; top: 0; z-index: 1000; transition: all 0.3s ease; }
        .navbar.scrolled { background: rgba(10, 15, 30, 0.95); padding: 10px 0; box-shadow: 0 2px 30px rgba(0,0,0,0.3); }
        .navbar-brand { font-size: 25px; font-weight: 800; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; letter-spacing: 0.3px; }
        .logo-icon { background: #d4af37; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; color: #0a0f1e; transition: transform 0.4s ease; }
        .navbar-brand:hover .logo-icon { transform: rotate(18deg) scale(1.06); }
        .navbar-brand span { color: #d4af37; }
        .nav-menu { display: flex; align-items: center; gap: 5px; }
        .nav-link-custom { position: relative; color: rgba(255,255,255,0.7); text-decoration: none; padding: 8px 18px; font-weight: 500; font-size: 14px; transition: all 0.3s ease; border-radius: 50px; }
        .nav-link-custom:not(.nav-btn):not(.nav-outline)::after { content: ''; position: absolute; left: 18px; right: 18px; bottom: 4px; height: 1px; background: #d4af37; transform: scaleX(0); transition: transform 0.25s ease; }
        .nav-link-custom:not(.nav-btn):not(.nav-outline):hover::after { transform: scaleX(1); }
        .nav-link-custom:hover { color: #d4af37; background: rgba(212, 175, 55, 0.05); transform: translateY(-1px); }
        .nav-btn { background: #d4af37; color: #0a0f1e !important; padding: 8px 24px; border-radius: 50px; font-weight: 600; }
        .nav-btn:hover { background: #b8922e; color: white !important; box-shadow: 0 6px 20px rgba(212,175,55,0.25); }
        .nav-outline { border: 1px solid rgba(212, 175, 55, 0.2); color: rgba(255,255,255,0.8) !important; background: transparent; }
        .nav-outline:hover { border-color: #d4af37; color: #d4af37 !important; }
        .hamburger { display: none; cursor: pointer; background: #d4af37; padding: 8px 12px; border-radius: 8px; transition: all 0.3s ease; }
        .hamburger:hover { transform: scale(1.05) rotate(-4deg); }
        .hamburger i { font-size: 20px; color: #0a0f1e; }

        .mobile-menu { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: #0a0f1e; z-index: 10001; transition: left 0.35s cubic-bezier(.2,.8,.3,1); padding: 80px 20px 20px; box-shadow: 2px 0 30px rgba(0,0,0,0.5); border-right: 1px solid rgba(212, 175, 55, 0.05); }
        .mobile-menu.active { left: 0; }
        .mobile-menu .close-btn { position: absolute; top: 15px; right: 15px; font-size: 28px; color: rgba(255,255,255,0.6); cursor: pointer; transition: all 0.3s ease; }
        .mobile-menu .close-btn:hover { color: white; transform: rotate(90deg); }
        .mobile-menu a { display: block; color: rgba(255,255,255,0.7); text-decoration: none; padding: 12px 0; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.04); transition: all 0.3s ease; }
        .mobile-menu a:hover { color: #d4af37; padding-left: 10px; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(2px); z-index: 10000; display: none; opacity: 0; transition: opacity 0.3s ease; }
        .overlay.active { display: block; opacity: 1; }

        .hero-slider { height: 100vh; position: relative; overflow: hidden; margin-top: 0; }
        .slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transform: scale(1.04); transition: opacity 1.2s ease, transform 6s ease; background-size: cover; background-position: center; }
        .slide.active { opacity: 1; transform: scale(1); animation: kenBurns 8s ease-out forwards; }
        @keyframes kenBurns { from { transform: scale(1.08); } to { transform: scale(1); } }
        .slide::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(180deg, rgba(10,15,30,0.55) 0%, rgba(10,15,30,0.35) 45%, rgba(10,15,30,0.75) 100%); }
        .slide-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white; width: 100%; padding: 0 20px; z-index: 2; }

        /* NEW: character-cascade reveal for the hero heading. Each
           .char span is animated with an incrementing --i custom
           property used as the stagger delay multiplier. */
        .slide-content h1 { font-size: 56px; font-weight: 800; margin-bottom: 15px; letter-spacing: -0.5px; }
        .slide-content h1 .char {
            display: inline-block;
            opacity: 0;
            transform: translateY(24px) rotate(4deg);
        }
        .slide.active .slide-content h1 .char {
            animation: charIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
            animation-delay: calc(var(--i) * 0.035s + 0.1s);
        }
        @keyframes charIn { to { opacity: 1; transform: translateY(0) rotate(0deg); } }

        .slide-content p { font-size: 18px; margin-bottom: 30px; opacity: 0; max-width: 600px; margin-left: auto; margin-right: auto; transform: translateY(16px); }
        .slide.active .slide-content p { animation: fadeUpIn 0.7s ease forwards; animation-delay: 0.5s; }
        .slide.active .slide-content .btn-book { opacity: 0; transform: translateY(16px); animation: fadeUpIn 0.7s ease forwards; animation-delay: 0.68s; }
        @keyframes fadeUpIn { to { opacity: 0.9; transform: translateY(0); } }
        .slide.active .slide-content .btn-book { animation-name: fadeUpInSolid; }
        @keyframes fadeUpInSolid { to { opacity: 1; transform: translateY(0); } }

        @media (prefers-reduced-motion: reduce) {
            .slide.active, .slide-content h1 .char, .slide.active .slide-content p, .slide.active .slide-content .btn-book { animation: none !important; opacity: 1 !important; transform: none !important; }
        }

        .btn-book { background: #d4af37; color: #0a0f1e; border: none; padding: 12px 40px; font-size: 16px; font-weight: 600; border-radius: 50px; text-decoration: none; display: inline-block; position: relative; overflow: hidden; }
        .btn-book:hover { background: #b8922e; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(212, 175, 55, 0.35); }

        /* NEW: magnetic pull -- JS sets --mx/--my (offset within the
           button) on mousemove; this just renders that offset. Resets
           to 0,0 on mouseleave. */
        .magnetic { transform: translate(var(--mx, 0), var(--my, 0)); transition: transform 0.15s ease-out; }

        .slider-controls { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 10; display: flex; gap: 15px; }
        .slider-controls button { background: rgba(255,255,255,0.05); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); padding: 8px 22px; cursor: pointer; border-radius: 50px; font-weight: 500; transition: all 0.3s ease; }
        .slider-controls button:hover { background: #d4af37; border-color: #d4af37; color: #0a0f1e; transform: translateY(-2px); }

        /* NEW: subtle scroll-down cue in the corner of the hero */
        .scroll-cue {
            position: absolute;
            right: 34px; bottom: 34px;
            z-index: 10;
            display: flex; flex-direction: column; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.4);
            font-size: 11px; letter-spacing: 2px; text-transform: uppercase;
            writing-mode: vertical-rl;
        }
        .scroll-cue .line { width: 1px; height: 46px; background: linear-gradient(180deg, rgba(212,175,55,0.7), transparent); animation: scrollCue 1.8s ease-in-out infinite; }
        @keyframes scrollCue { 0%,100% { transform: scaleY(0.6); opacity: 0.5; } 50% { transform: scaleY(1); opacity: 1; } }
        @media (max-width: 992px) { .scroll-cue { display: none; } }
        .slider-dots { position: absolute; bottom: 84px; left: 50%; transform: translateX(-50%); z-index: 10; display: flex; gap: 9px; }
        .slider-dots span { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,0.25); cursor: pointer; transition: all 0.3s ease; }
        .slider-dots span.active { background: #d4af37; width: 22px; border-radius: 5px; }

        .services-section { padding: 90px 0; }

        /* NEW: trust badges strip */
        .trust-strip { padding: 10px 0 70px; }
        .trust-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .trust-item {
            display: flex; align-items: center; gap: 14px;
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 14px; padding: 18px 20px; transition: all 0.3s ease;
        }
        .trust-item:hover { border-color: rgba(212,175,55,0.2); transform: translateY(-3px); box-shadow: 0 12px 30px rgba(0,0,0,0.25); }
        .trust-icon {
            width: 42px; height: 42px; border-radius: 12px; flex-shrink: 0;
            background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15);
            display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 17px;
        }
        .trust-item h4 { font-size: 13.5px; color: white; font-weight: 600; margin-bottom: 2px; }
        .trust-item p { font-size: 11.5px; color: rgba(255,255,255,0.4); }
        @media (max-width: 992px) { .trust-grid { grid-template-columns: repeat(2, 1fr); } }

        /* ============================================================
           NEW: Hotels Gallery -- a carousel showcase of real, top-rated
           hotels pulled from the database, matching the site's existing
           dark/gold visual language.
           ============================================================ */
        .section-title { text-align: center; margin-bottom: 50px; }
        .section-title .gold-line { width: 60px; height: 3px; background: #d4af37; margin: 0 auto 12px; border-radius: 2px; }
        .section-title h2 { font-size: 34px; font-weight: 800; color: white; margin-bottom: 10px; }
        .section-title p { color: rgba(255,255,255,0.5); font-size: 15px; }

        /* NEW: 3D tilt wrapper -- JS updates --rx/--ry per card based on
           cursor position; CSS just applies the perspective transform. */
        .service-card {
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px;
            overflow: hidden;
            cursor: pointer;
            margin-bottom: 25px;
            position: relative;
            transform: perspective(800px) rotateX(var(--rx, 0deg)) rotateY(var(--ry, 0deg)) translateY(var(--ty, 0px));
            transition: transform 0.25s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .service-card:hover { border-color: rgba(212, 175, 55, 0.25); box-shadow: 0 20px 45px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,175,55,0.08); }
        /* NEW: glare that follows the cursor across the card */
        .service-card .glare {
            position: absolute; inset: 0;
            background: radial-gradient(circle at var(--gx,50%) var(--gy,50%), rgba(212,175,55,0.14), transparent 55%);
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            z-index: 2;
        }
        .service-card:hover .glare { opacity: 1; }
        .service-card .img-wrap { overflow: hidden; position: relative; }
        .service-card img { width: 100%; height: 180px; object-fit: cover; display: block; transition: transform 0.5s ease; }
        .service-card:hover img { transform: scale(1.08); }
        .service-card .img-wrap::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, transparent 40%, rgba(10,15,30,0.85) 100%); opacity: 0; transition: opacity 0.35s ease; }
        .service-card:hover .img-wrap::after { opacity: 1; }
        .service-card .icon-badge { position: absolute; top: 14px; right: 14px; width: 40px; height: 40px; border-radius: 50%; background: rgba(10,15,30,0.55); backdrop-filter: blur(6px); border: 1px solid rgba(212,175,55,0.2); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 16px; transform: translateY(-6px); opacity: 0; transition: all 0.35s ease; z-index: 3; }
        .service-card:hover .icon-badge { opacity: 1; transform: translateY(0); }
        .service-card .card-body { padding: 20px; text-align: center; position: relative; z-index: 3; }
        .service-card h5 { font-size: 17px; font-weight: 600; margin-bottom: 5px; color: white; }
        .service-card .card-arrow { display: inline-block; margin-top: 4px; font-size: 13px; color: rgba(212,175,55,0.7); transition: transform 0.3s ease; }
        .service-card:hover .card-arrow { transform: translateX(5px); color: #d4af37; }

        .footer { background: transparent; color: rgba(255,255,255,0.6); padding: 60px 0 25px; border-top: 1px solid rgba(212,175,55,0.06); position: relative; }
        .footer::before { content: ''; position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 140px; height: 1px; background: linear-gradient(90deg, transparent, #d4af37, transparent); }
        .footer h4, .footer h5 { color: white; }
        .footer h5 { position: relative; padding-bottom: 10px; margin-bottom: 14px; font-family: 'Inter', sans-serif; }
        .footer h5::after { content: ''; position: absolute; left: 0; bottom: 0; width: 28px; height: 2px; background: #d4af37; border-radius: 2px; }
        .footer a { color: rgba(255,255,255,0.5); text-decoration: none; transition: all 0.3s ease; }
        .footer a:hover { color: #d4af37; transform: translateX(3px); display: inline-block; }

        .whatsapp-float { position: fixed; bottom: 30px; right: 30px; background: #25D366; color: white; border-radius: 50px; padding: 10px 24px; text-decoration: none; font-weight: 600; z-index: 1000; box-shadow: 0 5px 20px rgba(0,0,0,0.3); display: flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.3s ease; }
        .whatsapp-float:hover { background: #128C7E; color: white; transform: scale(1.05); }
        .whatsapp-float::before { content: ''; position: absolute; inset: 0; border-radius: 50px; background: #25D366; opacity: 0.5; animation: waPulse 2.4s ease-out infinite; z-index: -1; }
        @keyframes waPulse { 0% { transform: scale(1); opacity: 0.45; } 100% { transform: scale(1.35); opacity: 0; } }

        @media (prefers-reduced-motion: reduce) {
            .bg-ambient::before, .bg-shape, .whatsapp-float::before { animation: none; }
        }

        @media (max-width: 992px) {
            .nav-menu { display: none; }
            .hamburger { display: block; }
            .slide-content h1 { font-size: 32px; }
            .slide-content p { font-size: 14px; }
            .hero-slider { height: 80vh; }
        }
    </style>
</head>
<body>

<div class="preloader" id="preloader"><div class="preloader-ring"></div></div>

<div class="grain-overlay" aria-hidden="true"></div>

<div class="bg-ambient" aria-hidden="true">
    <i class="fas fa-star-and-crescent bg-shape s1"></i>
    <i class="fas fa-star-and-crescent bg-shape s2"></i>
    <i class="fas fa-star-and-crescent bg-shape s3"></i>
</div>

<div class="page-content">
    <nav class="navbar" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="#">
                <div class="logo-icon"><i class="fas fa-plane"></i></div>
                Ahmed<span>Travels</span>
            </a>
            <div class="nav-menu">
                <a href="#home" class="nav-link-custom">Home</a>
                <a href="#services" class="nav-link-custom">Services</a>
                <a href="gallery.php" class="nav-link-custom">Gallery</a>
                <a href="#contact" class="nav-link-custom">Contact</a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="dashboard.php" class="nav-link-custom nav-outline">Dashboard</a>
                    <a href="logout.php" class="nav-link-custom nav-btn">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link-custom nav-outline">Login</a>
                    <a href="signup.php" class="nav-link-custom nav-btn">Sign Up</a>
                <?php endif; ?>
            </div>
            <div class="hamburger" onclick="toggleMenu()"><i class="fas fa-bars"></i></div>
        </div>
    </nav>

    <div class="mobile-menu" id="mobileMenu">
        <div class="close-btn" onclick="toggleMenu()">✕</div>
        <a href="#home" onclick="toggleMenu()">Home</a>
        <a href="#services" onclick="toggleMenu()">Services</a>
        <a href="gallery.php" onclick="toggleMenu()">Gallery</a>
        <a href="#contact" onclick="toggleMenu()">Contact</a>
        <div style="margin: 20px 0;">
            <strong style="color:#d4af37;">Our Services</strong>
            <a href="services.php?type=hotels" style="padding-left: 15px;">Hotel Booking</a>
            <a href="services.php?type=taxi" style="padding-left: 15px;">Book a Taxi</a>
            <a href="services.php?type=visa" style="padding-left: 15px;">Visa Services</a>
        </div>
        <?php if(isset($_SESSION['user_id'])): ?>
            <div style="margin-top: 20px;">
                <a href="dashboard.php" style="background:#d4af37; color:#0a0f1e; text-align:center; border-radius:8px; margin-bottom:10px; display:block; padding:10px;" onclick="toggleMenu()">Dashboard</a>
                <a href="logout.php" style="background:#dc2626; color:white; text-align:center; border-radius:8px; display:block; padding:10px;" onclick="toggleMenu()">Logout</a>
            </div>
        <?php else: ?>
            <div style="margin-top: 20px;">
                <a href="login.php" style="background:#d4af37; color:#0a0f1e; text-align:center; border-radius:8px; margin-bottom:10px; display:block; padding:10px;" onclick="toggleMenu()">Login</a>
                <a href="signup.php" style="background:rgba(255,255,255,0.05); color:white; text-align:center; border-radius:8px; display:block; padding:10px;" onclick="toggleMenu()">Sign Up</a>
            </div>
        <?php endif; ?>
    </div>
    <div class="overlay" id="overlay" onclick="toggleMenu()"></div>

    <div class="hero-slider" id="heroSlider">
        <div class="hero-spotlight" id="heroSpotlight"></div>
        <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1555215695-3004980ad54e?w=1600');">
            <div class="slide-content">
                <h1 class="split-text">Book a Taxi</h1>
                <p>With professional driver</p>
                <a href="services.php?type=taxi" class="btn-book magnetic">Book Now</a>
            </div>
        </div>
        <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1600');">
            <div class="slide-content">
                <h1 class="split-text">5 Star Hotels</h1>
                <p>Luxury stays at best rates</p>
                <a href="services.php?type=hotels" class="btn-book magnetic">Book Hotel</a>
            </div>
        </div>
        <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=1600');">
            <div class="slide-content">
                <h1 class="split-text">Visa Services</h1>
                <p>Fast processing for multiple countries</p>
                <a href="services.php?type=visa" class="btn-book magnetic">Apply Now</a>
            </div>
        </div>
        <div class="slider-dots" id="sliderDots">
            <span class="active" onclick="showSlide(0)"></span>
            <span onclick="showSlide(1)"></span>
            <span onclick="showSlide(2)"></span>
        </div>
        <div class="slider-controls">
            <button onclick="prevSlide()">Previous</button>
            <button onclick="nextSlide()">Next</button>
        </div>
        <a href="#services" class="scroll-cue"><span class="line"></span>Scroll</a>
    </div>

    <section id="services" class="services-section">
        <div class="container">
            <div class="section-title reveal">
                <div class="gold-line"></div>
                <h2>Our Services</h2>
                <p>Explore the best travel services for your journey</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="service-card reveal reveal-delay-1" onclick="location.href='services.php?type=hotels'">
                        <div class="glare"></div>
                        <div class="img-wrap">
                            <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400" alt="Hotels">
                            <div class="icon-badge"><i class="fas fa-hotel"></i></div>
                        </div>
                        <div class="card-body"><h5>Hotels</h5><span class="card-arrow">Explore &rarr;</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card reveal reveal-delay-2" onclick="location.href='services.php?type=taxi'">
                        <div class="glare"></div>
                        <div class="img-wrap">
                            <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?w=400" alt="Premium Car">
                            <div class="icon-badge"><i class="fas fa-car"></i></div>
                        </div>
                        <div class="card-body"><h5>Book a Taxi</h5><span class="card-arrow">Explore &rarr;</span></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card reveal reveal-delay-3" onclick="location.href='services.php?type=visa'">
                        <div class="glare"></div>
                        <div class="img-wrap">
                            <img src="https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=400" alt="Visas">
                            <div class="icon-badge"><i class="fas fa-passport"></i></div>
                        </div>
                        <div class="card-body"><h5>Visas</h5><span class="card-arrow">Explore &rarr;</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="trust-strip">
        <div class="container">
            <div class="trust-grid">
                <div class="trust-item reveal">
                    <div class="trust-icon"><i class="fas fa-shield-halved"></i></div>
                    <div><h4>Secure Payment</h4><p>Your transactions are protected</p></div>
                </div>
                <div class="trust-item reveal reveal-delay-1">
                    <div class="trust-icon"><i class="fas fa-headset"></i></div>
                    <div><h4>24/7 Support</h4><p>We're always here to help</p></div>
                </div>
                <div class="trust-item reveal reveal-delay-2">
                    <div class="trust-icon"><i class="fas fa-rotate-left"></i></div>
                    <div><h4>Free Cancellation</h4><p>Cancel within 60 minutes</p></div>
                </div>
                <div class="trust-item reveal reveal-delay-3">
                    <div class="trust-icon"><i class="fas fa-award"></i></div>
                    <div><h4>Trusted Service</h4><p>Handpicked hotels &amp; rides</p></div>
                </div>
            </div>
        </div>
    </section>

    <a href="https://wa.me/923001234567?text=Hi! I need travel assistance" class="whatsapp-float" target="_blank">
        <i class="fab fa-whatsapp"></i> Chat Now
    </a>

    <footer class="footer" id="contact">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h4>Ahmed Travels</h4>
                    <p class="mt-3">Your trusted travel partner since 2020. Best travel deals across Pakistan and worldwide.</p>
                </div>
                <div class="col-md-4">
                    <h5>Quick Links</h5>
                    <p><a href="services.php?type=hotels">Hotels</a></p>
                    <p><a href="services.php?type=taxi">Book a Taxi</a></p>
                    <p><a href="services.php?type=visa">Visa Services</a></p>
                </div>
                <div class="col-md-4">
                    <h5>Contact Us</h5>
                    <p><i class="fas fa-phone"></i> +92 300 1234567</p>
                    <p><i class="fab fa-whatsapp"></i> +92 321 7654321</p>
                    <p><i class="fas fa-envelope"></i> info@ahmedtravels.com</p>
                </div>
            </div>
            <hr class="mt-4" style="border-color:rgba(255,255,255,0.03);">
            <p class="text-center">&copy; 2026 Ahmed Travels. All rights reserved.</p>
        </div>
    </footer>
</div>

<script>
    /* ---------- Existing slider logic (unchanged behavior) ---------- */
    let slides = document.querySelectorAll('.slide');
    let dots = document.querySelectorAll('#sliderDots span');
    let currentSlide = 0;
    function showSlide(n) {
        slides.forEach(s => s.classList.remove('active'));
        dots.forEach(d => d.classList.remove('active'));
        currentSlide = (n + slides.length) % slides.length;
        slides[currentSlide].classList.add('active');
        if (dots[currentSlide]) dots[currentSlide].classList.add('active');
    }
    function nextSlide() { showSlide(currentSlide + 1); }
    function prevSlide() { showSlide(currentSlide - 1); }
    setInterval(nextSlide, 6000);
    function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
    window.addEventListener('scroll', function() { const navbar = document.getElementById('navbar'); if(window.scrollY > 50) { navbar.classList.add('scrolled'); } else { navbar.classList.remove('scrolled'); } });

    /* ---------- Scroll-reveal (from previous pass) ---------- */
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) { entry.target.classList.add('in-view'); revealObserver.unobserve(entry.target); }
            });
        }, { threshold: 0.15 });
        document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
    } else {
        document.querySelectorAll('.reveal').forEach(el => el.classList.add('in-view'));
    }

    /* ---------- NEW: preloader ---------- */
    window.addEventListener('load', function() {
        const pl = document.getElementById('preloader');
        setTimeout(() => pl.classList.add('done'), 250);
    });

    /* ---------- NEW: split hero headings into per-letter spans with an
       --i index, so the CSS char-cascade animation can stagger them.
       Runs once on load; doesn't touch showSlide()'s own logic. ---------- */
    document.querySelectorAll('.split-text').forEach(h => {
        const text = h.textContent;
        h.innerHTML = '';
        [...text].forEach((ch, i) => {
            const span = document.createElement('span');
            span.className = 'char';
            span.style.setProperty('--i', i);
            span.textContent = ch === ' ' ? '\u00A0' : ch;
            h.appendChild(span);
        });
    });

    /* ---------- NEW: instant hero spotlight -- direct 1:1 bind to the
       mouse position, no smoothing/interpolation, so there is zero lag.
       The native cursor is untouched throughout. ---------- */
    const isFinePointer = window.matchMedia('(pointer: fine)').matches;
    const heroSlider = document.getElementById('heroSlider');
    const heroSpotlight = document.getElementById('heroSpotlight');
    if (isFinePointer && heroSlider && heroSpotlight) {
        heroSlider.addEventListener('mousemove', (e) => {
            const r = heroSlider.getBoundingClientRect();
            const x = ((e.clientX - r.left) / r.width) * 100;
            const y = ((e.clientY - r.top) / r.height) * 100;
            heroSpotlight.style.setProperty('--sx', x + '%');
            heroSpotlight.style.setProperty('--sy', y + '%');
        });
    }

    /* ---------- NEW: magnetic buttons (fine-pointer only) ---------- */
    if (isFinePointer) {
        document.querySelectorAll('.magnetic').forEach(btn => {
            btn.addEventListener('mousemove', (e) => {
                const r = btn.getBoundingClientRect();
                const relX = e.clientX - r.left - r.width / 2;
                const relY = e.clientY - r.top - r.height / 2;
                btn.style.setProperty('--mx', (relX * 0.25) + 'px');
                btn.style.setProperty('--my', (relY * 0.25) + 'px');
            });
            btn.addEventListener('mouseleave', () => {
                btn.style.setProperty('--mx', '0px');
                btn.style.setProperty('--my', '0px');
            });
        });
    }

    /* ---------- NEW: 3D tilt + glare on service cards (fine-pointer only) ---------- */
    if (isFinePointer) {
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('mousemove', (e) => {
                const r = card.getBoundingClientRect();
                const px = (e.clientX - r.left) / r.width;   // 0..1
                const py = (e.clientY - r.top) / r.height;   // 0..1
                const ry = (px - 0.5) * 10;   // rotateY range
                const rx = (0.5 - py) * 10;   // rotateX range
                card.style.setProperty('--rx', rx.toFixed(2) + 'deg');
                card.style.setProperty('--ry', ry.toFixed(2) + 'deg');
                card.style.setProperty('--ty', '-4px');
                card.style.setProperty('--gx', (px * 100).toFixed(1) + '%');
                card.style.setProperty('--gy', (py * 100).toFixed(1) + '%');
            });
            card.addEventListener('mouseleave', () => {
                card.style.setProperty('--rx', '0deg');
                card.style.setProperty('--ry', '0deg');
                card.style.setProperty('--ty', '0px');
            });
        });
    }
</script>
<?php include 'chatbot_widget.php'; ?>
</body>
</html>