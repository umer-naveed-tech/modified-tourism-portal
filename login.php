<?php
require_once 'config.php';
require_once 'auth_helpers.php';

// config.php already ran the remember-me auto-login check for us.
if (isset($_SESSION['user_id'])) {
    redirect_after_login(['id' => $_SESSION['user_id'], 'role' => $_SESSION['user_role']]);
}

$error = null;
$registered = isset($_GET['registered']);
if (isset($_GET['google_error'])) {
    $error = "Google sign-in failed. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        log_user_into_session($user);
        if ($remember_me) {
            issue_remember_me($pdo, $user['id']);
        }
        redirect_after_login($user);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0f1e;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .login-card {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 420px;
            width: 90%;
        }
        .login-header { 
            background: rgba(10, 15, 30, 0.5);
            padding: 36px 32px; 
            text-align: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .login-header h1 { 
            color: white; 
            font-size: 28px; 
            font-weight: 800; 
            letter-spacing: -0.5px; 
        }
        .login-header h1 span { color: #d4af37; }
        .login-header p { 
            color: rgba(255,255,255,0.5); 
            font-size: 14px; 
            margin-top: 8px; 
        }
        .login-body { padding: 32px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { 
            display: block; 
            font-size: 13px; 
            font-weight: 500; 
            color: rgba(255,255,255,0.6); 
            margin-bottom: 6px; 
        }
        .form-group input {
            width: 100%; padding: 12px 16px; font-size: 14px;
            border: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            color: white;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        .form-group input::placeholder {
            color: rgba(255,255,255,0.2);
        }
        .form-group input:focus {
            outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.05);
            background: rgba(255,255,255,0.05);
        }
        .remember-row { 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
            margin-bottom: 20px; 
        }
        .remember-row label { 
            display: flex; 
            align-items: center; 
            gap: 7px; 
            font-size: 13px; 
            color: rgba(255,255,255,0.5); 
            font-weight: 400; 
            cursor: pointer; 
        }
        .remember-row input { 
            width: auto; 
            accent-color: #d4af37;
        }
        .btn-login {
            width: 100%; padding: 12px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-login:hover { 
            background: #b8922e; 
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.15);
        }
        .divider { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            margin: 22px 0; 
            color: rgba(255,255,255,0.2); 
            font-size: 12px; 
        }
        .divider::before, .divider::after { 
            content: ''; 
            flex: 1; 
            height: 1px; 
            background: rgba(255,255,255,0.04); 
        }
        .btn-google {
            width: 100%; padding: 11px; background: rgba(255,255,255,0.03); 
            color: rgba(255,255,255,0.8); border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px; font-weight: 500; font-size: 14px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: all 0.2s ease;
        }
        .btn-google:hover { 
            border-color: rgba(255,255,255,0.1); 
            background: rgba(255,255,255,0.05); 
        }
        .signup-link { 
            text-align: center; 
            margin-top: 24px; 
            font-size: 13px; 
            color: rgba(255,255,255,0.4); 
        }
        .signup-link a { 
            color: #d4af37; 
            text-decoration: none; 
            font-weight: 500; 
        }
        .signup-link a:hover { text-decoration: underline; }
        .forgot-link { text-align: center; margin-top: 16px; font-size: 12px; }
        .forgot-link a { color: rgba(255,255,255,0.3); text-decoration: none; }
        .forgot-link a:hover { color: #d4af37; }
        .error-message { 
            background: rgba(239,68,68,0.06); 
            color: #f87171; 
            padding: 12px; 
            border-radius: 12px; 
            font-size: 13px; 
            margin-bottom: 20px; 
            text-align: center; 
            border: 1px solid rgba(239,68,68,0.06);
        }
        .success-message { 
            background: rgba(16,185,129,0.06); 
            color: #34d399; 
            padding: 12px; 
            border-radius: 12px; 
            font-size: 13px; 
            margin-bottom: 20px; 
            text-align: center; 
            border: 1px solid rgba(16,185,129,0.06);
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
            <?php if ($registered): ?>
                <div class="success-message">Account created! Please sign in.</div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="remember-row">
                    <label><input type="checkbox" name="remember_me"> Remember me</label>
                    <a href="forgot_password.php" style="font-size:12px;color:rgba(255,255,255,0.3);text-decoration:none;">Forgot password?</a>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>

            <div class="divider">OR</div>
            <a href="google_login.php" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.36 0-4.35-1.59-5.06-3.73H.9v2.34A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.94 10.69A5.4 5.4 0 0 1 3.66 9c0-.59.1-1.16.28-1.69V4.97H.9A9 9 0 0 0 0 9c0 1.45.35 2.83.9 4.03z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .9 4.97l3.04 2.34C4.65 5.17 6.64 3.58 9 3.58z"/></svg>
                Continue with Google
            </a>

            <div class="signup-link">
                Don't have an account? <a href="signup.php">Create an account</a>
            </div>
        </div>
    </div>
</body>
</html>