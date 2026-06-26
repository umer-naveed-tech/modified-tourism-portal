<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahmed Travels - Your Trusted Travel Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
        .navbar { background: #0a1a2f; padding: 15px 0; transition: all 0.3s ease; position: fixed; width: 100%; top: 0; z-index: 1000; }
        .navbar.scrolled { background: #0a1a2f; padding: 10px 0; box-shadow: 0 2px 20px rgba(0,0,0,0.15); }
        .navbar-brand { font-size: 24px; font-weight: 700; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; }
        .logo-icon { background: #c9a03d; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #0a1a2f; }
        .navbar-brand span { color: #c9a03d; }
        .nav-menu { display: flex; align-items: center; gap: 5px; }
        .nav-link-custom { color: #e0e0e0; text-decoration: none; padding: 8px 18px; font-weight: 500; font-size: 14px; transition: all 0.3s ease; border-radius: 50px; }
        .nav-link-custom:hover { color: #c9a03d; background: rgba(201, 160, 61, 0.1); }
        .nav-btn { background: #c9a03d; color: #0a1a2f !important; padding: 8px 22px; border-radius: 50px; font-weight: 600; }
        .nav-btn:hover { background: #b8922e; color: white !important; }
        .nav-outline { border: 1.5px solid #c9a03d; color: #e0e0e0 !important; background: transparent; }
        .hamburger { display: none; cursor: pointer; background: #c9a03d; padding: 8px 12px; border-radius: 8px; }
        .hamburger i { font-size: 20px; color: #0a1a2f; }
        .mobile-menu { position: fixed; top: 0; left: -300px; width: 280px; height: 100%; background: #0a1a2f; z-index: 10001; transition: 0.3s; padding: 80px 20px 20px; box-shadow: 2px 0 20px rgba(0,0,0,0.3); }
        .mobile-menu.active { left: 0; }
        .mobile-menu .close-btn { position: absolute; top: 15px; right: 15px; font-size: 28px; color: white; cursor: pointer; }
        .mobile-menu a { display: block; color: #e0e0e0; text-decoration: none; padding: 12px 0; font-size: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .mobile-menu a:hover { color: #c9a03d; padding-left: 10px; }
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: none; }
        .overlay.active { display: block; }
        .hero-slider { height: 100vh; position: relative; overflow: hidden; margin-top: 0; }
        .slide { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; transition: opacity 0.6s ease; background-size: cover; background-position: center; }
        .slide.active { opacity: 1; }
        .slide::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .slide-content { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; color: white; width: 100%; padding: 0 20px; z-index: 2; }
        .slide-content h1 { font-size: 52px; font-weight: 700; margin-bottom: 15px; letter-spacing: -0.5px; }
        .slide-content p { font-size: 18px; margin-bottom: 30px; opacity: 0.9; max-width: 600px; margin-left: auto; margin-right: auto; }
        .btn-book { background: #c9a03d; color: #0a1a2f; border: none; padding: 12px 40px; font-size: 16px; font-weight: 600; border-radius: 50px; transition: all 0.3s ease; text-decoration: none; display: inline-block; }
        .btn-book:hover { background: #b8922e; transform: translateY(-2px); color: #0a1a2f; }
        .slider-controls { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 10; display: flex; gap: 15px; }
        .slider-controls button { background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 22px; cursor: pointer; border-radius: 50px; font-weight: 500; transition: 0.3s; }
        .slider-controls button:hover { background: #c9a03d; border-color: #c9a03d; }
        .services-section { padding: 80px 0; background: #f5f5f0; }
        .section-title { text-align: center; margin-bottom: 50px; }
        .section-title h2 { font-size: 32px; font-weight: 700; color: #0a1a2f; margin-bottom: 10px; }
        .section-title p { color: #666; font-size: 15px; }
        .service-card { background: white; border-radius: 12px; overflow: hidden; transition: all 0.3s ease; cursor: pointer; box-shadow: 0 5px 20px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
        .service-card img { width: 100%; height: 180px; object-fit: cover; }
        .service-card .card-body { padding: 20px; text-align: center; }
        .service-card h5 { font-size: 17px; font-weight: 600; margin-bottom: 5px; }
        .footer { background: #0a1a2f; color: #e0e0e0; padding: 50px 0 25px; }
        .footer h4, .footer h5 { color: white; }
        .footer a { color: #ccc; text-decoration: none; }
        .footer a:hover { color: #c9a03d; }
        .whatsapp-float { position: fixed; bottom: 30px; right: 30px; background: #25D366; color: white; border-radius: 50px; padding: 10px 24px; text-decoration: none; font-weight: 600; z-index: 1000; box-shadow: 0 5px 20px rgba(0,0,0,0.2); display: flex; align-items: center; gap: 8px; font-size: 14px; }
        .whatsapp-float:hover { background: #128C7E; color: white; }
        @media (max-width: 992px) { .nav-menu { display: none; } .hamburger { display: block; } .slide-content h1 { font-size: 32px; } .slide-content p { font-size: 14px; } .hero-slider { height: 80vh; } }
    </style>
</head>
<body>

<nav class="navbar" id="navbar">
    <div class="container">
        <a class="navbar-brand" href="#">
            <div class="logo-icon"><i class="fas fa-plane"></i></div>
            Ahmed<span>Travels</span>
        </a>
        <div class="nav-menu">
            <a href="#home" class="nav-link-custom">Home</a>
            <a href="#services" class="nav-link-custom">Services</a>
            <a href="#packages" class="nav-link-custom">Packages</a>
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
    <a href="#packages" onclick="toggleMenu()">Packages</a>
    <a href="#contact" onclick="toggleMenu()">Contact</a>
    <div style="margin: 20px 0;">
        <strong style="color:#c9a03d;">Our Services</strong>
        <a href="services.php?type=groups" style="padding-left: 15px;">Group Tours</a>
        <a href="services.php?type=hotels" style="padding-left: 15px;">Hotel Booking</a>
        <a href="services.php?type=taxis" style="padding-left: 15px;">Book a Taxi</a>
        <a href="services.php?type=visas" style="padding-left: 15px;">Visa Services</a>
    </div>
    <?php if(!isset($_SESSION['user_id'])): ?>
        <div style="margin-top: 20px;">
            <a href="login.php" style="background:#c9a03d; color:#0a1a2f; text-align:center; border-radius:8px; margin-bottom:10px;" onclick="toggleMenu()">Login</a>
            <a href="signup.php" style="background:#1a2a3f; text-align:center; border-radius:8px;" onclick="toggleMenu()">Sign Up</a>
        </div>
    <?php endif; ?>
</div>
<div class="overlay" id="overlay" onclick="toggleMenu()"></div>

<!-- Hero Slider - 4 Slides with PREMIUM CAR PICTURE -->
<div class="hero-slider" id="heroSlider">
    <!-- Slide 1: Book a Taxi (PREMIUM WHITE LUXURY CAR) -->
    <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1555215695-3004980ad54e?w=1600');">
        <div class="slide-content">
            <h1>Book a Taxi</h1>
            <p>With professional driver</p>
            <a href="services.php?type=taxis" class="btn-book">Book Now</a>
        </div>
    </div>
    
    <!-- Slide 2: 5 Star Hotels -->
    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1600');">
        <div class="slide-content">
            <h1>5 Star Hotels</h1>
            <p>Luxury stays at best rates</p>
            <a href="services.php?type=hotels" class="btn-book">Book Hotel</a>
        </div>
    </div>
    
    <!-- Slide 3: Explore the World -->
    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=1600');">
        <div class="slide-content">
            <h1>Explore the World</h1>
            <p>Dubai | Singapore | Malaysia</p>
            <a href="services.php?type=groups" class="btn-book">Explore Tours</a>
        </div>
    </div>
    
    <!-- Slide 4: Visa Services -->
    <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=1600');">
        <div class="slide-content">
            <h1>Visa Services</h1>
            <p>Fast processing for multiple countries</p>
            <a href="services.php?type=visas" class="btn-book">Apply Now</a>
        </div>
    </div>
    
    <div class="slider-controls">
        <button onclick="prevSlide()">← Previous</button>
        <button onclick="nextSlide()">Next →</button>
    </div>
</div>

<!-- Services Section - WITH PREMIUM CAR PICTURE -->
<section id="services" class="services-section">
    <div class="container">
        <div class="section-title">
            <h2>Our Services</h2>
            <p>Explore the best travel services for your journey</p>
        </div>
        <div class="row">
            <div class="col-md-6 col-lg-3">
                <div class="service-card" onclick="location.href='services.php?type=groups'">
                    <img src="https://images.unsplash.com/photo-1530521954074-e64f6810b32d?w=400" alt="Group Tours">
                    <div class="card-body"><h5>Group Tours</h5></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="service-card" onclick="location.href='services.php?type=hotels'">
                    <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400" alt="Hotels">
                    <div class="card-body"><h5>Hotels</h5></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="service-card" onclick="location.href='services.php?type=taxis'">
                    <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?w=400" alt="Premium Car">
                    <div class="card-body"><h5>Book a Taxi</h5></div>
                </div>
            </div>
            <div class="col-md-6 col-lg-3">
                <div class="service-card" onclick="location.href='services.php?type=visas'">
                    <img src="https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=400" alt="Visas">
                    <div class="card-body"><h5>Visas</h5></div>
                </div>
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
                <p><a href="services.php?type=groups">Group Tours</a></p>
                <p><a href="services.php?type=hotels">Hotels</a></p>
                <p><a href="services.php?type=taxis">Book a Taxi</a></p>
                <p><a href="services.php?type=visas">Visa Services</a></p>
            </div>
            <div class="col-md-4">
                <h5>Contact Us</h5>
                <p><i class="fas fa-phone"></i> +92 300 1234567</p>
                <p><i class="fab fa-whatsapp"></i> +92 321 7654321</p>
                <p><i class="fas fa-envelope"></i> info@ahmedtravels.com</p>
            </div>
        </div>
        <hr class="mt-4">
        <p class="text-center">&copy; 2026 Ahmed Travels. All rights reserved.</p>
    </div>
</footer>

<script>
    let slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    function showSlide(n) { slides.forEach(s => s.classList.remove('active')); currentSlide = (n + slides.length) % slides.length; slides[currentSlide].classList.add('active'); }
    function nextSlide() { showSlide(currentSlide + 1); }
    function prevSlide() { showSlide(currentSlide - 1); }
    setInterval(nextSlide, 6000);
    function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
    window.addEventListener('scroll', function() { const navbar = document.getElementById('navbar'); if(window.scrollY > 50) { navbar.classList.add('scrolled'); } else { navbar.classList.remove('scrolled'); } });
</script>
</body>
</html>