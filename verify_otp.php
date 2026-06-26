<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';
$debug_info = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];
    
    // Debug: Get the latest OTP from database
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $db_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($db_record) {
        $debug_info = "📧 Email: $email<br>";
        $debug_info .= "🔢 OTP You Entered: <strong>$otp</strong><br>";
        $debug_info .= "💾 OTP in Database: <strong>" . $db_record['otp'] . "</strong><br>";
        $debug_info .= "⏰ Expires At: " . $db_record['expires_at'] . "<br>";
        $debug_info .= "⏰ Current Time: " . date('Y-m-d H:i:s') . "<br>";
        $debug_info .= "✅ Is Used: " . ($db_record['is_used'] == 0 ? 'No (Good)' : 'Yes (Already used)') . "<br>";
        
        // Check if OTP matches
        if($db_record['otp'] == $otp) {
            // Check if expired
            if(strtotime($db_record['expires_at']) > time()) {
                // Check if not used
                if($db_record['is_used'] == 0) {
                    // Success - Update OTP as used
                    $stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
                    $stmt->execute([$db_record['id']]);
                    $_SESSION['reset_verified'] = true;
                    header('Location: reset_password.php');
                    exit();
                } else {
                    $error = "❌ This OTP has already been used. Please request a new one.";
                }
            } else {
                $error = "❌ OTP has expired. Please request a new one.";
            }
        } else {
            $error = "❌ OTP does not match!";
        }
    } else {
        $error = "❌ No OTP record found. Please request a new OTP.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP - Debug</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #0a1a2f; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
<div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; padding: 30px;">
    <h3 class="text-center">Verify OTP</h3>
    <p class="text-center text-muted">Enter 6-digit code sent to your email</p>
    
    <?php if(isset($_SESSION['reset_success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['reset_success']; unset($_SESSION['reset_success']); ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($debug_info): ?>
        <div class="alert alert-info" style="font-size: 12px; word-break: break-all;">
            <strong>🔍 Debug Info:</strong><br>
            <?php echo $debug_info; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <input type="text" name="otp" class="form-control mb-3 text-center" placeholder="000000" maxlength="6" required autofocus>
        <button type="submit" class="btn btn-dark w-100">Verify OTP</button>
    </form>
    <div class="text-center mt-3">
        <a href="resend_otp.php">Resend OTP</a>
        <br>
        <a href="forgot_password.php">Request New OTP</a>
    </div>
</div>
</body>
</html>