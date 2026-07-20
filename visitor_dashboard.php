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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f8fafc; }
        
        .navbar { background: #0f172a; padding: 16px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 600; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: #cbd5e1; text-decoration: none; margin-left: 24px; font-size: 14px; transition: color 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: #ef4444; color: white; padding: 8px 20px; border-radius: 8px; margin-left: 24px; }
        
        .dashboard-header { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 48px 0; }
        .dashboard-header h1 { font-size: 32px; font-weight: 600; margin-bottom: 8px; }
        .dashboard-header p { color: #94a3b8; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-top: -30px; margin-bottom: 48px; }
        .stat-card { background: white; padding: 24px; border-radius: 16px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: transform 0.3s ease; }
        .stat-card:hover { transform: translateY(-5px); border-color: #d4af37; }
        .stat-number { font-size: 32px; font-weight: 700; color: #0f172a; }
        .stat-label { font-size: 13px; color: #64748b; margin-top: 8px; }
        
        .section-title { font-size: 20px; font-weight: 600; color: #0f172a; margin: 32px 0 20px; }
        
        .services-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 48px; }
        .service-card { background: white; padding: 24px; border-radius: 16px; text-align: center; cursor: pointer; transition: all 0.3s ease; border: 1px solid #e2e8f0; }
        .service-card:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -12px rgba(0,0,0,0.1); border-color: #d4af37; }
        .service-card h3 { font-size: 18px; font-weight: 600; color: #0f172a; margin: 12px 0 4px; }
        .service-card p { font-size: 13px; color: #64748b; margin-bottom: 16px; }
        .service-card button { background: #0f172a; color: white; border: none; padding: 8px 20px; border-radius: 8px; font-size: 13px; font-weight: 500; cursor: pointer; transition: all 0.3s ease; }
        .service-card button:hover { background: #d4af37; color: #0f172a; }
        
        .bookings-list { display: flex; flex-direction: column; gap: 16px; margin-top: 20px; }
        .booking-card { background: white; padding: 20px; border-radius: 16px; border-left: 4px solid #d4af37; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .booking-info h4 { font-size: 16px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .booking-info p { font-size: 13px; color: #64748b; }
        .booking-status { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-confirmed { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .booking-actions { display: flex; gap: 12px; }
        .btn-cancel, .btn-support { padding: 6px 16px; border-radius: 8px; font-size: 12px; font-weight: 500; text-decoration: none; cursor: pointer; border: none; }
        .btn-cancel { background: #fee2e2; color: #dc2626; }
        .btn-cancel:hover { background: #fecaca; }
        .btn-support { background: #0f172a; color: white; }
        .btn-support:hover { background: #1e293b; }
        
        @media (max-width: 768px) { .stats-grid, .services-grid { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>

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
        <h1>Hello, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
        <p>Manage your bookings and explore new destinations</p>
    </div>
</div>

<div class="container">
    <!-- Stats -->
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
            <div class="stat-number">WhatsApp</div>
            <div class="stat-label">Chat Now</div>
        </div>
    </div>
    
    <!-- Quick Booking Services -->
    <div class="section-title">Quick Booking</div>
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
        <div class="service-card" onclick="location.href='services.php?type=groups'">
            <h3>Group Tours</h3>
            <p>Explore with groups</p>
            <button>View Tours</button>
        </div>
    </div>
    
    <!-- My Bookings (SAR Currency) -->
    <div class="section-title">My Bookings</div>
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
                    <p>Amount: <strong>SAR <?php echo number_format($b['total_amount']); ?></strong></p>
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
        <div style="background: white; padding: 40px; text-align: center; border-radius: 16px;">
            <p style="color: #64748b;">No bookings yet. Book a service to get started!</p>
        </div>
    <?php endif; ?>
</div>

</body>
</html>