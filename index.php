<?php session_start(); require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ahmed Travels - Your Trusted Travel Partner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; overflow-x: hidden; background: #0a0f1e; }
        
        .page-content {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .btn, button, .btn-book, .nav-btn, .action-btn {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover, button:hover, .btn-book:hover, .nav-btn:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }
        
        .service-card, .stat-card {
            transition: all 0.3s ease;
        }
        .service-card:hover, .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
            outline: none;
        }
        
        .navbar { 
            background: rgba(10, 15, 30, 0.9);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.08);
            padding: 14px 0;
            position: fixed; 
            width: 100%; 
            top: 0; 
            z-index: 1000;
            transition: all 0.3s ease;
        }
        .navbar.scrolled { 
            background: rgba(10, 15, 30, 0.95);
            padding: 10px 0; 
            box-shadow: 0 2px 30px rgba(0,0,0,0.3);
        }
        .navbar-brand { 
            font-size: 24px; 
            font-weight: 800; 
            color: white; 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .logo-icon { 
            background: #d4af37; 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 18px; 
            color: #0a0f1e; 
        }
        .navbar-brand span { color: #d4af37; }
        .nav-menu { display: flex; align-items: center; gap: 5px; }
        .nav-link-custom { 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            padding: 8px 18px; 
            font-weight: 500; 
            font-size: 14px; 
            transition: all 0.3s ease; 
            border-radius: 50px; 
        }
        .nav-link-custom:hover { color: #d4af37; background: rgba(212, 175, 55, 0.05); transform: translateY(-1px); }
        .nav-btn { 
            background: #d4af37; 
            color: #0a0f1e !important; 
            padding: 8px 24px; 
            border-radius: 50px; 
            font-weight: 600; 
        }
        .nav-btn:hover { 
            background: #b8922e; 
            color: white !important; 
        }
        .nav-outline { 
            border: 1px solid rgba(212, 175, 55, 0.2); 
            color: rgba(255,255,255,0.8) !important; 
            background: transparent; 
        }
        .nav-outline:hover { 
            border-color: #d4af37; 
            color: #d4af37 !important; 
        }
        .hamburger { 
            display: none; 
            cursor: pointer; 
            background: #d4af37; 
            padding: 8px 12px; 
            border-radius: 8px; 
            transition: all 0.3s ease;
        }
        .hamburger:hover { transform: scale(1.05); }
        .hamburger i { font-size: 20px; color: #0a0f1e; }
        
        .mobile-menu { 
            position: fixed; 
            top: 0; 
            left: -300px; 
            width: 280px; 
            height: 100%; 
            background: #0a0f1e; 
            z-index: 10001; 
            transition: 0.3s; 
            padding: 80px 20px 20px; 
            box-shadow: 2px 0 30px rgba(0,0,0,0.5);
            border-right: 1px solid rgba(212, 175, 55, 0.05);
        }
        .mobile-menu.active { left: 0; }
        .mobile-menu .close-btn { 
            position: absolute; 
            top: 15px; 
            right: 15px; 
            font-size: 28px; 
            color: rgba(255,255,255,0.6); 
            cursor: pointer; 
            transition: all 0.3s ease;
        }
        .mobile-menu .close-btn:hover { color: white; transform: rotate(90deg); }
        .mobile-menu a { 
            display: block; 
            color: rgba(255,255,255,0.7); 
            text-decoration: none; 
            padding: 12px 0; 
            font-size: 15px; 
            border-bottom: 1px solid rgba(255,255,255,0.04); 
            transition: all 0.3s ease;
        }
        .mobile-menu a:hover { color: #d4af37; padding-left: 10px; }
        .overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.7); 
            z-index: 10000; 
            display: none; 
        }
        .overlay.active { display: block; }
        
        .hero-slider { height: 100vh; position: relative; overflow: hidden; margin-top: 0; }
        .slide { 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            opacity: 0; 
            transition: opacity 0.6s ease; 
            background-size: cover; 
            background-position: center; 
        }
        .slide.active { opacity: 1; }
        .slide::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(10, 15, 30, 0.6); 
        }
        .slide-content { 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%); 
            text-align: center; 
            color: white; 
            width: 100%; 
            padding: 0 20px; 
            z-index: 2; 
        }
        .slide-content h1 { 
            font-size: 52px; 
            font-weight: 800; 
            margin-bottom: 15px; 
            letter-spacing: -0.5px; 
        }
        .slide-content p { 
            font-size: 18px; 
            margin-bottom: 30px; 
            opacity: 0.8; 
            max-width: 600px; 
            margin-left: auto; 
            margin-right: auto; 
        }
        .btn-book { 
            background: #d4af37; 
            color: #0a0f1e; 
            border: none; 
            padding: 12px 40px; 
            font-size: 16px; 
            font-weight: 600; 
            border-radius: 50px; 
            text-decoration: none; 
            display: inline-block; 
        }
        .btn-book:hover { 
            background: #b8922e; 
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
        }
        .slider-controls { 
            position: absolute; 
            bottom: 30px; 
            left: 50%; 
            transform: translateX(-50%); 
            z-index: 10; 
            display: flex; 
            gap: 15px; 
        }
        .slider-controls button { 
            background: rgba(255,255,255,0.05); 
            backdrop-filter: blur(10px); 
            border: 1px solid rgba(255,255,255,0.05); 
            color: rgba(255,255,255,0.7); 
            padding: 8px 22px; 
            cursor: pointer; 
            border-radius: 50px; 
            font-weight: 500; 
            transition: all 0.3s ease; 
        }
        .slider-controls button:hover { 
            background: #d4af37; 
            border-color: #d4af37; 
            color: #0a0f1e;
            transform: translateY(-2px);
        }
        
        .services-section { padding: 80px 0; background: #0a0f1e; }
        .section-title { text-align: center; margin-bottom: 50px; }
        .section-title .gold-line {
            width: 60px;
            height: 3px;
            background: #d4af37;
            margin: 0 auto 12px;
            border-radius: 2px;
        }
        .section-title h2 { 
            font-size: 32px; 
            font-weight: 800; 
            color: white; 
            margin-bottom: 10px; 
        }
        .section-title p { 
            color: rgba(255,255,255,0.5); 
            font-size: 15px; 
        }
        .service-card { 
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 16px; 
            overflow: hidden; 
            cursor: pointer; 
            margin-bottom: 25px; 
        }
        .service-card:hover { 
            border-color: rgba(212, 175, 55, 0.1);
            box-shadow: 0 15px 40px rgba(0,0,0,0.3);
        }
        .service-card img { 
            width: 100%; 
            height: 180px; 
            object-fit: cover; 
        }
        .service-card .card-body { padding: 20px; text-align: center; }
        .service-card h5 { 
            font-size: 17px; 
            font-weight: 600; 
            margin-bottom: 5px; 
            color: white;
        }
        
        .footer { 
            background: #0a0f1e; 
            color: rgba(255,255,255,0.6); 
            padding: 50px 0 25px; 
            border-top: 1px solid rgba(255,255,255,0.03);
        }
        .footer h4, .footer h5 { color: white; }
        .footer a { color: rgba(255,255,255,0.5); text-decoration: none; transition: all 0.3s ease; }
        .footer a:hover { color: #d4af37; transform: translateX(3px); }
        
        .whatsapp-float { 
            position: fixed; 
            bottom: 30px; 
            right: 30px; 
            background: #25D366; 
            color: white; 
            border-radius: 50px; 
            padding: 10px 24px; 
            text-decoration: none; 
            font-weight: 600; 
            z-index: 1000; 
            box-shadow: 0 5px 20px rgba(0,0,0,0.3); 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-size: 14px; 
            transition: all 0.3s ease;
        }
        .whatsapp-float:hover { background: #128C7E; color: white; transform: scale(1.05); }
        
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
        <div class="slide active" style="background-image: url('https://images.unsplash.com/photo-1555215695-3004980ad54e?w=1600');">
            <div class="slide-content">
                <h1>Book a Taxi</h1>
                <p>With professional driver</p>
                <a href="services.php?type=taxi" class="btn-book">Book Now</a>
            </div>
        </div>
        <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1566073771259-6a8506099945?w=1600');">
            <div class="slide-content">
                <h1>5 Star Hotels</h1>
                <p>Luxury stays at best rates</p>
                <a href="services.php?type=hotels" class="btn-book">Book Hotel</a>
            </div>
        </div>
        <div class="slide" style="background-image: url('https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=1600');">
            <div class="slide-content">
                <h1>Visa Services</h1>
                <p>Fast processing for multiple countries</p>
                <a href="services.php?type=visa" class="btn-book">Apply Now</a>
            </div>
        </div>
        <div class="slider-controls">
            <button onclick="prevSlide()">Previous</button>
            <button onclick="nextSlide()">Next</button>
        </div>
    </div>

    <section id="services" class="services-section">
        <div class="container">
            <div class="section-title">
                <div class="gold-line"></div>
                <h2>Our Services</h2>
                <p>Explore the best travel services for your journey</p>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="service-card" onclick="location.href='services.php?type=hotels'">
                        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400" alt="Hotels">
                        <div class="card-body"><h5>Hotels</h5></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card" onclick="location.href='services.php?type=taxi'">
                        <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?w=400" alt="Premium Car">
                        <div class="card-body"><h5>Book a Taxi</h5></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="service-card" onclick="location.href='services.php?type=visa'">
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
    let slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    function showSlide(n) { slides.forEach(s => s.classList.remove('active')); currentSlide = (n + slides.length) % slides.length; slides[currentSlide].classList.add('active'); }
    function nextSlide() { showSlide(currentSlide + 1); }
    function prevSlide() { showSlide(currentSlide - 1); }
    setInterval(nextSlide, 6000);
    function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); document.getElementById('overlay').classList.toggle('active'); }
    window.addEventListener('scroll', function() { const navbar = document.getElementById('navbar'); if(window.scrollY > 50) { navbar.classList.add('scrolled'); } else { navbar.classList.remove('scrolled'); } });
</script>
<?php include 'chatbot_widget.php'; ?>
</body>
</html>