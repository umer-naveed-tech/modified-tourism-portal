<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_no = $_GET['booking_no'] ?? '';
$hotel_name = $_GET['hotel'] ?? '';
$room_name = $_GET['room'] ?? '';
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$total = $_GET['total'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmed - Ahmed Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body style="background: #f0f2f5;">
<div class="container">
    <div class="card text-center" style="max-width: 550px; margin: 80px auto; border-radius: 16px;">
        <div class="card-body p-5">
            <div style="font-size: 80px; color: #28a745;">✅</div>
            <h2>Booking Confirmed!</h2>
            <p>Your hotel booking has been successfully confirmed.</p>
            
            <div class="alert alert-info text-start mt-3">
                <strong>Booking ID:</strong> <?php echo htmlspecialchars($booking_no); ?><br>
                <strong>Hotel:</strong> <?php echo htmlspecialchars($hotel_name); ?><br>
                <strong>Room:</strong> <?php echo htmlspecialchars($room_name); ?><br>
                <strong>Check-in:</strong> <?php echo htmlspecialchars($check_in); ?><br>
                <strong>Check-out:</strong> <?php echo htmlspecialchars($check_out); ?><br>
                <strong>Total Amount:</strong> <span class="text-success fw-bold">SAR <?php echo number_format($total); ?></span>
            </div>
            
            <p>A confirmation email has been sent to your registered email address.</p>
            
            <a href="dashboard.php" class="btn btn-primary">Go to Dashboard</a>
            <a href="services.php?type=hotels" class="btn btn-outline-secondary">Book Another Hotel</a>
        </div>
    </div>
</div>
</body>
</html>