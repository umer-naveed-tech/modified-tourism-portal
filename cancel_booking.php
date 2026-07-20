<?php
session_start();
if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'send_cancel_email.php';
require_once 'send_admin_email.php';  // Add this

$booking_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.email as user_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $user_id]);
$booking = $stmt->fetch();

if(!$booking) {
    header('Location: visitor_dashboard.php');
    exit();
}

$error = '';
$success = '';

$created_at = new DateTime($booking['created_at']);
$cancel_deadline = clone $created_at;
$cancel_deadline->modify('+60 minutes');
$now = new DateTime();

$can_cancel = ($now <= $cancel_deadline) && ($booking['status'] == 'pending');
$remaining_seconds = max(0, $cancel_deadline->getTimestamp() - $now->getTimestamp());

if($_SERVER['REQUEST_METHOD'] == 'POST' && $can_cancel) {
    csrf_verify();
    $reason = $_POST['reason'] ?? 'Customer requested cancellation';
    
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancelled_at = NOW(), cancellation_reason = ? WHERE id = ?");
    if($stmt->execute([$reason, $booking_id])) {
        // Send email to customer
        $email_sent = sendCancellationEmail(
            $_SESSION['user_email'],
            $_SESSION['user_name'],
            $booking['booking_no'],
            $booking['service_type'],
            $booking['travel_date'],
            $booking['total_amount'],
            $reason
        );
        
        // Send email to admin
        sendAdminEmail(
            'cancellation',
            $booking['booking_no'],
            $_SESSION['user_name'],
            $_SESSION['user_email'],
            $booking['service_type'],
            $booking['travel_date'],
            $booking['total_amount'],
            'cancelled'
        );
        
        $success = "Booking cancelled successfully!";
        header("refresh:2;url=visitor_dashboard.php");
    } else {
        $error = "Failed to cancel booking.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Cancel Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #f8fafc;">
<div class="container">
    <div class="card" style="max-width: 500px; margin: 80px auto; border-radius: 20px;">
        <div class="card-body p-4">
            <h3 class="text-center">Cancel Booking</h3>
            
            <?php if($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?> Redirecting...</div>
            <?php elseif($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php elseif(!$can_cancel && $booking['status'] == 'pending'): ?>
                <div class="alert alert-danger">
                    Cancellation window closed. You can only cancel within 60 minutes of booking.
                </div>
                <a href="visitor_dashboard.php" class="btn btn-secondary w-100">Back to Dashboard</a>
            <?php elseif($booking['status'] == 'cancelled'): ?>
                <div class="alert alert-warning">Booking already cancelled.</div>
                <a href="visitor_dashboard.php" class="btn btn-secondary w-100">Back to Dashboard</a>
            <?php else: ?>
                <p><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['booking_no']); ?></p>
                <p><strong>Service:</strong> <?php echo htmlspecialchars(ucfirst($booking['service_type'])); ?></p>
                <p><strong>Amount:</strong> SAR <?php echo number_format($booking['total_amount']); ?></p>
                <p class="text-danger">⏰ You have <?php echo floor($remaining_seconds / 60); ?> minutes left to cancel.</p>
                
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <textarea name="reason" class="form-control mb-3" rows="2" placeholder="Reason for cancellation (optional)"></textarea>
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('Confirm cancellation?')">Confirm Cancellation</button>
                    <a href="visitor_dashboard.php" class="btn btn-secondary w-100 mt-2">Go Back</a>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>