<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'send_admin_email.php';
require_once 'send_booking_email.php';  // ✅ ADDED

$car_id = $_GET['car_id'] ?? 0;
$car_name = $_GET['car_name'] ?? '';
$from_city = $_GET['from'] ?? '';
$to_city = $_GET['to'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->execute([$car_id]);
$car = $stmt->fetch();

if(!$car) {
    header('Location: services.php?type=taxi');
    exit();
}

$fare = null;
if($from_city && $to_city) {
    $stmt = $pdo->prepare("SELECT price_sar FROM car_fares WHERE car_id = ? AND from_city = ? AND to_city = ?");
    $stmt->execute([$car_id, $from_city, $to_city]);
    $fare = $stmt->fetch();
}

$error = '';
$success = '';
$booking_no = '';
$wa_link = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $from = $_POST['from'];
    $to = $_POST['to'];
    $date = $_POST['date'];
    $time = $_POST['time'];
    
    $fare_stmt = $pdo->prepare("SELECT price_sar FROM car_fares WHERE car_id = ? AND from_city = ? AND to_city = ?");
    $fare_stmt->execute([$car_id, $from, $to]);
    $verified_fare = $fare_stmt->fetch();

    if(!$verified_fare) {
        $error = "Invalid route selected. Please try again.";
    } else {
        $fare_amount = $verified_fare['price_sar'];
        
        $booking_no = 'TAXI-' . date('Ymd') . '-' . rand(1000, 9999);
        $travel_datetime = $date . ($time ? ' at ' . $time : '');
        
        $stmt = $pdo->prepare("INSERT INTO bookings (booking_no, user_id, service_type, service_id, booking_date, travel_date, from_location, to_location, guests, total_amount, status, payment_status, can_cancel_until) VALUES (?, ?, 'taxi', ?, CURDATE(), ?, ?, ?, ?, ?, 'pending', 'pending', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        
        if($stmt->execute([$booking_no, $_SESSION['user_id'], $car_id, $travel_datetime, $from, $to, $car['capacity'], $fare_amount])) {
            $success = true;
            
            // Send email to admin
            sendAdminEmail(
                'booking',
                $booking_no,
                $_SESSION['user_name'],
                $_SESSION['user_email'],
                'Taxi - ' . $car['car_name'] . ' (' . $from . ' to ' . $to . ')',
                $travel_datetime,
                $fare_amount,
                'pending'
            );
            
            // ✅ SEND EMAIL TO REGISTERED USER
            $to_email = $_SESSION['user_email'] ?? '';
            $customer_name = $_SESSION['user_name'] ?? 'Customer';
            
            if(!empty($to_email) && file_exists('send_booking_email.php')) {
                sendBookingEmail(
                    $to_email,
                    $customer_name,
                    $booking_no,
                    'Taxi - ' . $car['car_name'] . ' (' . $from . ' to ' . $to . ')',
                    $travel_datetime,
                    $fare_amount,
                    $car['car_name'],
                    $from . ' → ' . $to,
                    $car['capacity'],
                    $fare_amount
                );
            }
            // ============================================================
            
            $wa_msg = "New Booking: $car_name from $from to $to on $date at $time. Booking ID: $booking_no. Total: SAR $fare_amount";
            $wa_link = "https://wa.me/923001234567?text=" . urlencode($wa_msg);
        } else {
            $error = "Booking failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Book Taxi - Ahmed Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .booking-card { background: white; border-radius: 20px; padding: 30px; max-width: 500px; margin: 50px auto; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .price-large { font-size: 28px; font-weight: bold; color: #d4af37; }
        .btn-confirm { background: #d4af37; color: #0f172a; border: none; padding: 12px; border-radius: 10px; width: 100%; font-weight: 600; }
        .btn-confirm:hover { background: #c9a227; color: white; }
        input, select { border-radius: 10px; padding: 10px; width: 100%; margin-bottom: 15px; border: 1px solid #e2e8f0; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php">Ahmed Travels - Saudi</a>
        <a href="dashboard.php" class="btn btn-light btn-sm">My Dashboard</a>
    </div>
</nav>

<div class="container">
    <div class="booking-card">
        <h2 class="text-center">Book <?php echo htmlspecialchars($car['car_name']); ?> <?php echo htmlspecialchars($car['car_model']); ?></h2>
        
        <?php if($success): ?>
            <div class="alert alert-success text-center">
                <h4>Booking Confirmed!</h4>
                <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking_no); ?></p>
                <p><strong>Route:</strong> <?php echo htmlspecialchars($from_city); ?> → <?php echo htmlspecialchars($to_city); ?></p>
                <p><strong>Total Fare:</strong> SAR <?php echo number_format($fare['price_sar'] ?? 0); ?></p>
                <a href="<?php echo htmlspecialchars($wa_link); ?>" class="btn btn-success" target="_blank">Send WhatsApp</a>
                <br><br>
                <a href="dashboard.php" class="btn btn-primary">View My Bookings</a>
                <a href="services.php?type=taxi" class="btn btn-secondary">Book Another</a>
            </div>
        <?php else: ?>
            <div class="text-center mb-3">
                <img src="<?php echo htmlspecialchars($car['image_url']); ?>" style="width: 100%; border-radius: 12px;">
                <p class="mt-2">Capacity: <?php echo (int)$car['capacity']; ?> persons | Air Conditioning: Yes</p>
            </div>
            
            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-md-6">
                        <label>Pickup City</label>
                        <input type="text" name="from" class="form-control" value="<?php echo htmlspecialchars($from_city); ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label>Drop City</label>
                        <input type="text" name="to" class="form-control" value="<?php echo htmlspecialchars($to_city); ?>" readonly>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label>Travel Date</label>
                        <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6">
                        <label>Travel Time</label>
                        <input type="time" name="time">
                    </div>
                </div>
                
                <label>Total Fare</label>
                <input type="text" class="form-control price-large" value="SAR <?php echo number_format($fare['price_sar'] ?? 0); ?>" readonly>
                <input type="hidden" name="fare" value="<?php echo $fare['price_sar'] ?? 0; ?>">
                
                <button type="submit" class="btn-confirm mt-3">Confirm Booking</button>
            </form>
        <?php endif; ?>
    </div>
</div>

</body>
</html>