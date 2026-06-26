<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=booking');
    exit();
}
require_once 'config.php';

$service_id = $_GET['id'] ?? 0;
$service_type = $_GET['type'] ?? 'hotel';

$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if(!$service) {
    header('Location: services.php?type=' . $service_type);
    exit();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $travel_date = $_POST['travel_date'];
    $from_location = $_POST['from_location'] ?? '';
    $to_location = $_POST['to_location'] ?? '';
    $guests = $_POST['guests'] ?? 1;
    
    $booking_no = 'TRV-' . date('Ymd') . '-' . rand(1000, 9999);
    $total_amount = $service['price'] * $guests;
    
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, to_location, guests, total_amount, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, 'pending')");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $service_type, $service_id, $travel_date, $from_location, $to_location, $guests, $total_amount])) {
        $success = true;
        $wa_msg = "Hi! I have booked {$service['title']} for $guests person(s) on $travel_date. Booking ID: $booking_no. Total: Rs. $total_amount";
        $wa_link = "https://wa.me/923001234567?text=" . urlencode($wa_msg);
    } else {
        $error = "Booking failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book <?php echo $service['title']; ?> - Ahmed Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; font-family: 'Inter', sans-serif; }
        .booking-card { background: white; border-radius: 15px; padding: 30px; max-width: 600px; margin: 50px auto; }
        .price-large { font-size: 36px; font-weight: bold; color: #c9a03d; }
        input, select { border-radius: 8px; padding: 10px; width: 100%; margin-bottom: 15px; border: 1px solid #ddd; }
        .btn-confirm { background: #0a1a2f; color: white; padding: 12px; border-radius: 50px; width: 100%; font-weight: bold; }
        .btn-confirm:hover { background: #c9a03d; color: #0a1a2f; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-plane"></i> Ahmed Travels</a>
        <a href="dashboard.php" class="btn btn-light btn-sm">My Dashboard</a>
    </div>
</nav>

<div class="container">
    <div class="booking-card">
        <h2 class="text-center">Book <?php echo $service['title']; ?></h2>
        
        <?php if($success): ?>
            <div class="alert alert-success text-center">
                <h4>✅ Booking Confirmed!</h4>
                <p><strong>Booking ID:</strong> <?php echo $booking_no; ?></p>
                <p><strong>Service:</strong> <?php echo $service['title']; ?></p>
                <p><strong>Total Amount:</strong> Rs. <?php echo number_format($service['price'] * ($_POST['guests'] ?? 1)); ?></p>
                <a href="<?php echo $wa_link; ?>" class="btn btn-success" target="_blank">
                    <i class="fab fa-whatsapp"></i> Send WhatsApp Confirmation
                </a>
                <br><br>
                <a href="dashboard.php" class="btn btn-primary">View My Bookings</a>
                <a href="services.php?type=<?php echo $service_type; ?>" class="btn btn-secondary">Book More</a>
            </div>
        <?php else: ?>
            <?php if($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row">
                    <div class="col-md-6">
                        <label><i class="fas fa-calendar"></i> Travel Date *</label>
                        <input type="date" name="travel_date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label><i class="fas fa-users"></i> Number of Guests/Persons</label>
                        <input type="number" name="guests" value="1" min="1" max="10">
                    </div>
                </div>
                
                <?php if($service_type == 'taxi'): ?>
                <div class="row">
                    <div class="col-md-6">
                        <label><i class="fas fa-map-marker-alt"></i> Pickup Location *</label>
                        <input type="text" name="from_location" placeholder="e.g., Lahore Airport" required>
                    </div>
                    <div class="col-md-6">
                        <label><i class="fas fa-flag-checkered"></i> Drop Location *</label>
                        <input type="text" name="to_location" placeholder="e.g., Islamabad" required>
                    </div>
                </div>
                <?php else: ?>
                <div class="row">
                    <div class="col-md-12">
                        <label><i class="fas fa-map-marker-alt"></i> Location</label>
                        <input type="text" name="from_location" value="<?php echo $service['location']; ?>" readonly>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <h4>Total Amount: <span class="price-large">Rs. <?php echo number_format($service['price']); ?></span></h4>
                    <p class="text-muted">*Per <?php echo $service_type == 'hotel' ? 'night' : ($service_type == 'taxi' ? 'trip' : 'person'); ?></p>
                    <button type="submit" class="btn-confirm">Confirm Booking</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>