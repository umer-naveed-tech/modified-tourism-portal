<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['reset_verified'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if(strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $email = $_SESSION['reset_email'];
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        if($stmt->execute([$hashed, $email])) {
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_verified']);
            header('Location: login.php?reset=success');
            exit();
        } else {
            $error = "Something went wrong.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background: #0a1a2f; display: flex; align-items: center; justify-content: center; min-height: 100vh;">
<div style="background: white; border-radius: 16px; max-width: 400px; width: 90%; padding: 30px;">
    <h3 class="text-center">Reset Password</h3>
    <?php if($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <input type="password" name="password" class="form-control mb-3" placeholder="New Password (min 6)" required>
        <input type="password" name="confirm_password" class="form-control mb-3" placeholder="Confirm Password" required>
        <button type="submit" class="btn btn-dark w-100">Reset Password</button>
    </form>
</div>
</body>
</html>