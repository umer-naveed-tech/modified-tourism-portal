<?php
// hide_booking.php
//
// Lets a customer remove a booking from their own view. This is NOT a
// real delete -- it only sets hidden_by_user = 1, so the booking (and
// its payment records, which the agent still needs for their own
// history) stay in the database untouched. Ownership-checked, same
// pattern as cancel_booking.php.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$booking_id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if (!$booking_id) {
    header('Location: dashboard.php');
    exit();
}

$stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $stmt = $pdo->prepare("UPDATE bookings SET hidden_by_user = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    header('Location: my_bookings.php?removed=1');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remove Booking | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        select { background-color: rgba(255,255,255,0.03); color: white; }
        select option { background-color: #10182c; color: white; }
        .card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 18px; padding: 34px; max-width: 420px; width: 100%; text-align: center; }
        .icon-wrap { width: 60px; height: 60px; margin: 0 auto 18px; border-radius: 50%; background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.2); display: flex; align-items: center; justify-content: center; }
        .icon-wrap i { font-size: 24px; color: #f87171; }
        h2 { font-family: 'Playfair Display', serif; font-size: 20px; margin-bottom: 10px; }
        p { color: rgba(255,255,255,0.5); font-size: 13.5px; line-height: 1.6; margin-bottom: 26px; }
        form { display: flex; gap: 10px; }
        button, a.btn-cancel { flex: 1; padding: 13px; border-radius: 10px; font-weight: 700; font-size: 13.5px; text-decoration: none; border: none; cursor: pointer; font-family: inherit; }
        .btn-confirm { background: #dc2626; color: white; }
        .btn-confirm:hover { background: #b91c1c; }
        a.btn-cancel { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon-wrap"><i class="fas fa-trash-can"></i></div>
        <h2>Remove This Booking?</h2>
        <p>This will remove it from your bookings list. It won't be deleted from our records, and this doesn't cancel it if it's still active -- it just won't show up for you anymore.</p>
        <form method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $booking_id; ?>">
            <a href="my_bookings.php" class="btn-cancel">Keep It</a>
            <button type="submit" class="btn-confirm">Yes, Remove It</button>
        </form>
    </div>
</body>
</html>