<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$email = $_SESSION['reset_email'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

$otp = sprintf("%06d", mt_rand(1, 999999));
$expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

$stmt = $pdo->prepare("UPDATE password_resets SET is_used = 1 WHERE email = ?");
$stmt->execute([$email]);

$stmt = $pdo->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$email, $otp, $expires_at]);

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'umernaveed2580@gmail.com';
    $mail->Password   = 'ebds gaci apij ssgv';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    
    $mail->setFrom('umernaveed2580@gmail.com', 'Ahmed Travels');
    $mail->addAddress($email, $user['name']);
    
    $mail->isHTML(true);
    $mail->Subject = 'New OTP - Password Reset';
    $mail->Body = "<h2>Your New OTP</h2><p>Your OTP is: <strong>$otp</strong></p><p>Valid for 10 minutes.</p>";
    
    $mail->send();
    $_SESSION['reset_success'] = "✅ New OTP sent to $email!";
    header('Location: verify_otp.php');
    exit();
    
} catch (Exception $e) {
    $_SESSION['reset_success'] = "Failed to send OTP. Error: {$mail->ErrorInfo}";
    header('Location: verify_otp.php');
    exit();
}
?>