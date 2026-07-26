<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$hotel_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch();

if(!$hotel) {
    header('Location: services.php?type=hotels');
    exit();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $guests = $_POST['guests'] ?? 1;
    
    // Calculate nights
    $date1 = new DateTime($check_in);
    $date2 = new DateTime($check_out);
    $nights = $date1->diff($date2)->days;
    
    $total_amount = $hotel['price_per_night_sar'] * $nights;
    $booking_no = 'HOTEL-' . date('Ymd') . '-' . rand(1000, 9999);
    
    $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, guests, total_amount, status) VALUES (?, ?, 'hotel', ?, CURDATE(), ?, ?, ?, ?, 'pending')");
    
    if($stmt->execute([$booking_no, $_SESSION['user_id'], $hotel_id, $check_in, $hotel['city'], $guests, $total_amount])) {
        $success = true;
        $wa_msg = "Hi! I have booked {$hotel['hotel_name']} in {$hotel['city']} from $check_in to $check_out ($nights nights). Booking ID: $booking_no. Total: SAR $total_amount";
        $wa_link = "https://wa.me/923001234567?text=" . urlencode($wa_msg);
    } else {
        $error = "Booking failed. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book <?php echo $hotel['hotel_name']; ?> - Ahmed Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .booking-card { background: white; border-radius: 15px; padding: 30px; max-width: 500px; margin: 50px auto; }
        .price-large { font-size: 36px; font-weight: bold; color: #c9a03d; }
        input, select { border-radius: 8px; padding: 10px; width: 100%; margin-bottom: 15px; border: 1px solid #ddd; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><i class="fas fa-plane"></i> Ahmed Travels - Saudi</a>
    </div>
</nav>

<div class="container">
    <div class="booking-card">
        <h2 class="text-center">Book <?php echo $hotel['hotel_name']; ?></h2>
        
        <?php if($success): ?>
            <div class="alert alert-success text-center">
                <h4>✅ Booking Confirmed!</h4>
                <p><strong>Booking ID:</strong> <?php echo $booking_no; ?></p>
                <p><strong>Hotel:</strong> <?php echo $hotel['hotel_name']; ?></p>
                <p><strong>Total Amount:</strong> SAR <?php echo number_format($total_amount); ?></p>
                <a href="<?php echo $wa_link; ?>" class="btn btn-success" target="_blank">
                    <i class="fab fa-whatsapp"></i> Send WhatsApp Confirmation
                </a>
                <br><br>
                <a href="dashboard.php" class="btn btn-primary">View My Bookings</a>
                <a href="services.php?type=hotels" class="btn btn-secondary">Book Another</a>
            </div>
        <?php else: ?>
            <div class="text-center mb-3">
                <img src="<?php echo $hotel['image_url']; ?>" style="width: 100%; border-radius: 12px;">
                <h4 class="mt-2"><?php echo $hotel['hotel_name']; ?></h4>
                <p><i class="fas fa-star text-warning"></i> <?php echo str_repeat('★', $hotel['rating']); ?></p>
                <p><?php echo $hotel['amenities']; ?></p>
            </div>
            <form method="POST">
                <label><i class="fas fa-calendar-check"></i> Check-in Date *</label>
                <input type="date" name="check_in" required min="<?php echo date('Y-m-d'); ?>">
                
                <label><i class="fas fa-calendar-times"></i> Check-out Date *</label>
                <input type="date" name="check_out" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                
                <label><i class="fas fa-users"></i> Number of Guests</label>
                <input type="number" name="guests" value="2" min="1" max="10">
                
                <p class="mt-3"><strong>Price per night:</strong> SAR <?php echo number_format($hotel['price_per_night_sar']); ?></p>
                
                <button type="submit" class="btn btn-confirm" style="background: #c9a03d; color: #0a1a2f; padding: 12px; border-radius: 50px; width: 100%; font-weight: bold; border: none;">Confirm Booking</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>