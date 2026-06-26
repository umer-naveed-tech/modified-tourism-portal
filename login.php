<?php
session_start();
require_once 'config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        if($user['role'] == 'agent') {
            header('Location: agent_dashboard.php');
        } else {
            header('Location: visitor_dashboard.php');
        }
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            max-width: 440px;
            width: 90%;
        }
        .login-header {
            background: #0f172a;
            padding: 40px 32px;
            text-align: center;
        }
        .login-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .login-header h1 span {
            color: #d4af37;
        }
        .login-header p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 8px;
        }
        .login-body {
            padding: 32px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .form-group input:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.1);
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }
        .signup-link {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
        }
        .signup-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 500;
        }
        .signup-link a:hover {
            text-decoration: underline;
        }
        .forgot-link {
            text-align: center;
            margin-top: 16px;
            font-size: 12px;
        }
        .forgot-link a {
            color: #94a3b8;
            text-decoration: none;
        }
        .forgot-link a:hover {
            color: #d4af37;
        }
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-header">
            <h1>Ahmed<span>Travels</span></h1>
            <p>Welcome back to your travel partner</p>
        </div>
        <div class="login-body">
            <?php if(isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>
            <div class="signup-link">
                Don't have an account? <a href="signup.php">Create an account</a>
            </div>
            <div class="forgot-link">
                <a href="forgot_password.php">Forgot password?</a>
            </div>
        </div>
    </div>
</body>
</html>