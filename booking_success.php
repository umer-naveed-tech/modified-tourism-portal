<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$booking_no = $_GET['booking_no'] ?? '';
$type = $_GET['type'] ?? '';
$amount = $_GET['amount'] ?? 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Booking Confirmed - Ahmed Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Inter', sans-serif; }
        .success-card { background: white; border-radius: 20px; padding: 40px; max-width: 500px; margin: 80px auto; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .check-icon { font-size: 80px; color: #10b981; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <div class="success-card">
        <div class="check-icon">✓</div>
        <h2>Booking Confirmed!</h2>
        <p>Your booking has been successfully confirmed.</p>
        <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking_no); ?></p>
        <p><strong>Total Amount:</strong> SAR <?php echo number_format($amount); ?></p>
        <p>A confirmation email has been sent to your inbox.</p>
        <a href="dashboard.php" class="btn btn-primary mt-3">View My Bookings</a>
        <a href="services.php" class="btn btn-secondary mt-3 ms-2">Book Another</a>
    </div>
</div>
</body>
</html>