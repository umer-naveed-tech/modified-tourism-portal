<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$user_id = $_SESSION['user_id'];

if(!isset($_SESSION['user_email'])) {
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    $_SESSION['user_email'] = $user['email'];
}

$stmt = $pdo->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Inter', sans-serif; 
            background: #0a0f1e; 
            min-height: 100vh;
        }
        
        /* ===== PAGE FADE-IN ===== */
        .page-content {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ===== BUTTON HOVER ===== */
        .btn, button {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn:hover, button:hover { transform: translateY(-2px); }
        .btn:active, button:active { transform: scale(0.97); }
        
        /* ===== CARD HOVER LIFT ===== */
        .stat-card, .service-card, .booking-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover, .service-card:hover, .booking-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.3);
        }
        
        /* ===== INPUT FOCUS GLOW ===== */
        input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
            outline: none;
        }
        
        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        
        .dashboard-header { 
            background: linear-gradient(180deg, #0a0f1e 0%, #0d1a2d 50%, #0a0f1e 100%);
            color: white; 
            padding: 48px 0;
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .dashboard-header .gold-line {
            width: 60px;
            height: 3px;
            background: #d4af37;
            margin-bottom: 12px;
            border-radius: 2px;
        }
        .dashboard-header h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
        .dashboard-header p { color: rgba(255,255,255,0.5); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-top: -30px; margin-bottom: 48px; }
        .stat-card { 
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 24px; 
            border-radius: 16px; 
            text-align: center; 
            cursor: default;
        }
        .stat-card:hover { 
            border-color: rgba(212, 175, 55, 0.1);
        }
        .stat-number { font-size: 32px; font-weight: 700; color: #d4af37; }
        .stat-label { font-size: 13px; color: rgba(255,255,255,0.4); margin-top: 8px; }
        .stat-card[onclick] { cursor: pointer; }
        
        .section-title { 
            font-size: 20px; 
            font-weight: 700; 
            color: white; 
            margin: 32px 0 20px; 
        }
        .section-title .gold-line {
            width: 40px;
            height: 2px;
            background: #d4af37;
            margin-top: 6px;
            border-radius: 2px;
        }
        
        .services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }
        .service-card { 
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 24px; 
            border-radius: 16px; 
            text-align: center; 
            cursor: pointer; 
        }
        .service-card:hover { 
            border-color: rgba(212, 175, 55, 0.1);
        }
        .service-card h3 { font-size: 18px; font-weight: 600; color: white; margin: 12px 0 4px; }
        .service-card p { font-size: 13px; color: rgba(255,255,255,0.4); margin-bottom: 16px; }
        .service-card button { 
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37; 
            border: 1px solid rgba(212, 175, 55, 0.05);
            padding: 8px 20px; 
            border-radius: 8px; 
            font-size: 13px; 
            font-weight: 500; 
            cursor: pointer; 
            transition: all 0.3s ease; 
        }
        .service-card button:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }
        
        .bookings-list { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; }
        .booking-card { 
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.04);
            padding: 20px; 
            border-radius: 16px; 
            border-left: 4px solid #d4af37; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            flex-wrap: wrap; 
        }
        .booking-card:hover { border-color: rgba(212, 175, 55, 0.1); }
        .booking-info h4 { font-size: 16px; font-weight: 600; color: white; margin-bottom: 4px; }
        .booking-info p { font-size: 13px; color: rgba(255,255,255,0.4); }
        .booking-status { 
            display: inline-block; 
            padding: 4px 12px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: 500; 
        }
        .status-pending { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .status-confirmed { background: rgba(16,185,129,0.1); color: #34d399; }
        .status-cancelled { background: rgba(239,68,68,0.1); color: #f87171; }
        .booking-actions { display: flex; gap: 12px; }
        .btn-cancel, .btn-support { 
            padding: 6px 16px; 
            border-radius: 8px; 
            font-size: 12px; 
            font-weight: 500; 
            text-decoration: none; 
            cursor: pointer; 
            border: none; 
            transition: all 0.3s ease;
        }
        .btn-cancel { background: rgba(239,68,68,0.1); color: #f87171; }
        .btn-cancel:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        .btn-support { background: rgba(255,255,255,0.03); color: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.04); }
        .btn-support:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }
        
        .empty-state { 
            background: rgba(255,255,255,0.02);
            padding: 40px; 
            text-align: center; 
            border-radius: 16px; 
            border: 1px solid rgba(255,255,255,0.02);
        }
        .empty-state p { color: rgba(255,255,255,0.3); }
        
        @media (max-width: 768px) { 
            .stats-grid, .services-grid { grid-template-columns: repeat(2, 1fr); } 
        }
    </style>
</head>
<body>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <div class="nav-links">
                <a href="services.php">Services</a>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard-header">
        <div class="container">
            <div class="gold-line"></div>
            <h1>Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p>Manage your bookings and explore new destinations</p>
        </div>
    </div>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($bookings); ?></div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">10+</div>
                <div class="stat-label">Destinations</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">24/7</div>
                <div class="stat-label">Support</div>
            </div>
            <div class="stat-card" onclick="window.open('https://wa.me/923001234567', '_blank')" style="cursor:pointer;">
                <div class="stat-number" style="color:#25D366;">WhatsApp</div>
                <div class="stat-label">Chat Now</div>
            </div>
        </div>
        
        <div class="section-title">
            Quick Booking
            <div class="gold-line"></div>
        </div>
        <div class="services-grid">
            <div class="service-card" onclick="location.href='services.php?type=hotels&city=Mecca'">
                <h3>Hotels</h3>
                <p>Luxury stays in Mecca</p>
                <button>Book Hotel</button>
            </div>
            <div class="service-card" onclick="location.href='services.php?type=taxi'">
                <h3>Airport Taxi</h3>
                <p>Rent a car with driver</p>
                <button>Book Taxi</button>
            </div>
            <div class="service-card" onclick="location.href='services.php?type=visa'">
                <h3>Visa Services</h3>
                <p>Fast processing</p>
                <button>Apply Now</button>
            </div>
        </div>
        
        <div class="section-title">
            My Bookings
            <div class="gold-line"></div>
        </div>
        <?php if(count($bookings) > 0): ?>
            <div class="bookings-list">
                <?php foreach($bookings as $b): 
                    $created_at = new DateTime($b['created_at']);
                    $cancel_deadline = clone $created_at;
                    $cancel_deadline->modify('+60 minutes');
                    $now = new DateTime();
                    $can_cancel = ($now <= $cancel_deadline) && ($b['status'] == 'pending');
                ?>
                <div class="booking-card">
                    <div class="booking-info">
                        <h4><?php echo htmlspecialchars($b['booking_no']); ?></h4>
                        <p><?php echo htmlspecialchars(ucfirst($b['service_type'])); ?> | Travel Date: <?php echo htmlspecialchars($b['travel_date']); ?></p>
                    </div>
                    <div>
                        <span class="booking-status status-<?php echo htmlspecialchars($b['status']); ?>"><?php echo htmlspecialchars(ucfirst($b['status'])); ?></span>
                    </div>
                    <div class="booking-info">
                        <p>Amount: <strong style="color:#d4af37;">SAR <?php echo number_format($b['total_amount']); ?></strong></p>
                    </div>
                    <div class="booking-actions">
                        <?php if($can_cancel): ?>
                            <a href="cancel_booking.php?id=<?php echo (int)$b['id']; ?>" class="btn-cancel" onclick="return confirm('Cancel this booking?')">Cancel</a>
                        <?php endif; ?>
                        <a href="https://wa.me/923001234567?text=<?php echo urlencode('Help with booking ' . $b['booking_no']); ?>" class="btn-support" target="_blank">Support</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>No bookings yet. Book a service to get started!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'chatbot_widget.php'; ?>
</body>
</html>