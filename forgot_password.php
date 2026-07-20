<?php
session_start();

require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/csrf.php';

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        otp VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        is_used BOOLEAN DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS reset_attempts (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $email = trim($_POST['email']);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM reset_attempts WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $stmt->execute([$email]);
        $attempts = $stmt->fetchColumn();
        
        if($attempts > 3) {
            $error = "Too many attempts. Please try after 15 minutes.";
        } else {
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $otp, $expires_at]);
            
            $stmt = $pdo->prepare("INSERT INTO reset_attempts (email) VALUES (?)");
            $stmt->execute([$email]);
            
            $mail = new PHPMailer(true);
            
            try {
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_APP_PASSWORD;
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                
                $mail->setFrom(SMTP_USERNAME, SMTP_FROM_NAME);
                $mail->addAddress($email, $user['name']);
                
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset OTP - Ahmed Travels';
                $mail->Body = "<h2>Password Reset OTP</h2><p>Your OTP is: <strong>$otp</strong></p><p>Valid for 10 minutes.</p>";
                
                $mail->send();
                
                $_SESSION['reset_success'] = "✅ OTP sent successfully to $email! Check your inbox.";
                $_SESSION['reset_email'] = $email;
                header('Location: verify_otp.php');
                exit();
                
            } catch (Exception $e) {
                $error = "OTP could not be sent. Error: {$mail->ErrorInfo}";
            }
        }
    } else {
        $error = "Email address not found.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #0a1a2f; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
<div style="background: white; border-radius: 16px; max-width: 400px; width: 90%; padding: 30px;">
    <h3 class="text-center">Forgot Password?</h3>
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="POST">
        <?php echo csrf_field(); ?>
        <input type="email" name="email" class="form-control mb-3" placeholder="Enter your email" required>
        <button type="submit" class="btn btn-dark w-100">Send OTP</button>
    </form>
    <div class="text-center mt-3">
        <a href="login.php">Back to Login</a>
    </div>
</div>
</body>
</html>