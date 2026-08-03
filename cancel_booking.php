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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 50% 30%, rgba(239,68,68,0.08), transparent 45%),
                        radial-gradient(circle at 20% 80%, rgba(212,175,55,0.06), transparent 40%);
            animation: driftGlow 22s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(2%,-2%) scale(1.05); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .cancel-card {
            position: relative; z-index: 1;
            background: rgba(255,255,255,0.04); backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.05); border-radius: 24px;
            padding: 40px; max-width: 460px; width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            opacity: 0; transform: translateY(20px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .cancel-card h3 { font-family: 'Playfair Display', serif; color: white; font-size: 24px; text-align: center; margin-bottom: 24px; }

        .alert { padding: 14px 16px; border-radius: 12px; font-size: 13.5px; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; }
        .alert-success { background: rgba(16,185,129,0.08); color: #34d399; border: 1px solid rgba(16,185,129,0.15); }
        .alert-danger { background: rgba(239,68,68,0.08); color: #f87171; border: 1px solid rgba(239,68,68,0.15); animation: shake 0.4s ease; }
        .alert-warning { background: rgba(251,191,36,0.08); color: #fbbf24; border: 1px solid rgba(251,191,36,0.15); }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }

        .detail-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px 20px; margin-bottom: 18px; }
        .detail-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 13.5px; }
        .detail-row span:first-child { color: rgba(255,255,255,0.4); }
        .detail-row span:last-child { color: white; font-weight: 600; }
        .detail-row .amt { color: #d4af37; }

        .countdown { display: flex; align-items: center; gap: 8px; background: rgba(239,68,68,0.06); border: 1px solid rgba(239,68,68,0.1); color: #f87171; padding: 12px 16px; border-radius: 12px; font-size: 13.5px; margin-bottom: 20px; }

        textarea { width: 100%; padding: 14px 16px; font-size: 14px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); border-radius: 12px; color: white; font-family: inherit; resize: vertical; margin-bottom: 16px; transition: all 0.25s ease; }
        textarea::placeholder { color: rgba(255,255,255,0.25); }
        textarea:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }

        .btn-danger, .btn-secondary { display: block; width: 100%; padding: 14px; border-radius: 12px; font-weight: 700; font-size: 14.5px; text-align: center; text-decoration: none; cursor: pointer; border: none; transition: all 0.3s ease; margin-bottom: 10px; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(239,68,68,0.25); }
        .btn-secondary { background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.06); }
        .btn-secondary:hover { background: rgba(255,255,255,0.08); transform: translateY(-2px); }
    
        .btn-spinner {
            display: inline-block; width: 14px; height: 14px;
            border: 2px solid rgba(255,255,255,0.35); border-top-color: currentColor;
            border-radius: 50%; animation: btnSpin 0.6s linear infinite;
            margin-right: 8px; vertical-align: -2px;
        }
        @keyframes btnSpin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

    <div class="cancel-card">
        <h3>Cancel Booking</h3>

        <?php if($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?> Redirecting...</div>
        <?php elseif($error): ?>
            <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php elseif(!$can_cancel && $booking['status'] == 'pending'): ?>
            <div class="alert alert-danger"><i class="fas fa-clock"></i> Cancellation window closed. You can only cancel within 60 minutes of booking.</div>
            <a href="visitor_dashboard.php" class="btn-secondary">Back to Dashboard</a>
        <?php elseif($booking['status'] == 'cancelled'): ?>
            <div class="alert alert-warning"><i class="fas fa-triangle-exclamation"></i> Booking already cancelled.</div>
            <a href="visitor_dashboard.php" class="btn-secondary">Back to Dashboard</a>
        <?php else: ?>
            <div class="detail-box">
                <div class="detail-row"><span>Booking ID</span><span><?php echo htmlspecialchars($booking['booking_no']); ?></span></div>
                <div class="detail-row"><span>Service</span><span><?php echo htmlspecialchars(ucfirst($booking['service_type'])); ?></span></div>
                <div class="detail-row"><span>Amount</span><span class="amt">SAR <?php echo number_format($booking['total_amount']); ?></span></div>
            </div>
            <div class="countdown"><i class="fas fa-clock"></i> You have <?php echo floor($remaining_seconds / 60); ?> minutes left to cancel.</div>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <textarea name="reason" rows="2" placeholder="Reason for cancellation (optional)"></textarea>
                <button type="submit" class="btn-danger" onclick="return confirm('Confirm cancellation?')">Confirm Cancellation</button>
                <a href="visitor_dashboard.php" class="btn-secondary">Go Back</a>
            </form>
        <?php endif; ?>
    </div>

<script>
    /* NEW: disable the submit button and show a spinner while the form
       is submitting, so double-clicking never fires a second (duplicate)
       booking request. Skips entirely if an earlier listener already
       cancelled the submit (e.g. client-side validation failing) --
       never leaves a valid form stuck showing "Processing...". */
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            if (e.defaultPrevented) return;
            const btn = this.querySelector('button[type="submit"]');
            if (btn && !btn.disabled) {
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = '<span class="btn-spinner"></span>Processing...';
                btn.disabled = true;
            }
        });
    });
</script>

</body>
</html>