<?php
require_once 'config.php';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone = $_POST['phone'];
    $role = 'visitor'; // Security: public signup can NEVER create an agent/admin account.
                        // Agent accounts must be added directly in the database by the site owner.
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role) VALUES (?,?,?,?,?)");
    if($stmt->execute([$name, $email, $password, $phone, $role])) {
        header('Location: login.php');
        exit();
    } else {
        $error = "Email already exists";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Ahmed Travels</title>
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
            padding: 20px;
        }
        .signup-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .signup-header {
            background: #0f172a;
            padding: 32px;
            text-align: center;
        }
        .signup-header h1 {
            color: white;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }
        .signup-header h1 span {
            color: #d4af37;
        }
        .signup-header p {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 8px;
        }
        .signup-body {
            padding: 32px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #334155;
            margin-bottom: 6px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: white;
        }
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.1);
        }
        .btn-signup {
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
        .btn-signup:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }
        .login-link {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: #64748b;
        }
        .login-link a {
            color: #d4af37;
            text-decoration: none;
            font-weight: 500;
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
    <div class="signup-card">
        <div class="signup-header">
            <h1>Ahmed<span>Travels</span></h1>
            <p>Join us for unforgettable journeys</p>
        </div>
        <div class="signup-body">
            <?php if(isset($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Enter your full name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="Enter your phone number" required>
                </div>
                <button type="submit" class="btn-signup">Create Account</button>
            </form>
            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</body>
</html>