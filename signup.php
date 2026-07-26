<?php
require_once 'config.php';
require_once 'auth_helpers.php';

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted   = isset($_POST['terms']);

    if ($e = validate_name($old['name']))              $errors[] = $e;
    if ($e = validate_email_address($old['email']))    $errors[] = $e;
    if ($e = validate_phone_optional($old['phone']))   $errors[] = $e;
    if ($e = validate_password_strength($password))    $errors[] = $e;
    if ($password !== $confirm_password)               $errors[] = 'Passwords do not match.';
    if (!$terms_accepted)                               $errors[] = 'You must accept the Terms and Conditions.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'visitor';

        $stmt = $pdo->prepare(
            "INSERT INTO users (name, email, password, phone, role) VALUES (?,?,?,?,?)"
        );
        if ($stmt->execute([$old['name'], $old['email'], $hashed_password, $old['phone'], $role])) {
            header('Location: login.php?registered=1');
            exit();
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0f1e;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        /* ===== PAGE FADE-IN ===== */
        .page-content {
            animation: fadeIn 0.5s ease forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ===== BUTTON HOVER ===== */
        .btn-signup {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-signup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.2);
        }
        .btn-signup:active { transform: scale(0.97); }
        
        /* ===== INPUT FOCUS GLOW ===== */
        input:focus {
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
            outline: none;
        }
        
        .signup-card {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 24px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
        }
        .signup-header { 
            background: rgba(10, 15, 30, 0.5);
            padding: 32px; 
            text-align: center;
            border-bottom: 1px solid rgba(212, 175, 55, 0.05);
        }
        .signup-header h1 { 
            color: white; 
            font-size: 28px; 
            font-weight: 800; 
            letter-spacing: -0.5px; 
        }
        .signup-header h1 span { color: #d4af37; }
        .signup-header p { 
            color: rgba(255,255,255,0.5); 
            font-size: 14px; 
            margin-top: 8px; 
        }
        .signup-body { padding: 32px; }
        .form-group { margin-bottom: 18px; }
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
            border-color: #d4af37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.08);
            background: rgba(255,255,255,0.05);
        }
        .form-group input.invalid { border-color: rgba(239,68,68,0.3); }
        .field-hint { 
            font-size: 12px; 
            color: rgba(255,255,255,0.3); 
            margin-top: 5px; 
        }
        .field-error { 
            font-size: 12px; 
            color: #f87171; 
            margin-top: 5px; 
            display: none; 
        }
        .terms-row { 
            display: flex; 
            align-items: flex-start; 
            gap: 8px; 
            margin-bottom: 20px; 
        }
        .terms-row input { 
            width: auto; 
            margin-top: 3px; 
            accent-color: #d4af37;
        }
        .terms-row label { 
            font-size: 13px; 
            color: rgba(255,255,255,0.5); 
            font-weight: 400; 
        }
        .terms-row a { 
            color: #d4af37; 
            text-decoration: none; 
            font-weight: 500; 
            transition: all 0.3s ease;
        }
        .terms-row a:hover { color: #b8922e; }
        .btn-signup {
            width: 100%; padding: 12px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 600; font-size: 15px; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-signup:hover { 
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
            transform: translateY(-2px);
        }
        .login-link { 
            text-align: center; 
            margin-top: 24px; 
            font-size: 13px; 
            color: rgba(255,255,255,0.4); 
        }
        .login-link a { 
            color: #d4af37; 
            text-decoration: none; 
            font-weight: 500; 
            transition: all 0.3s ease;
        }
        .login-link a:hover { color: #b8922e; }
        .error-message { 
            background: rgba(239,68,68,0.06); 
            color: #f87171; 
            padding: 12px; 
            border-radius: 12px; 
            font-size: 13px; 
            margin-bottom: 20px; 
            border: 1px solid rgba(239,68,68,0.06);
        }
        .error-message ul { margin: 0; padding-left: 18px; }
    </style>
</head>
<body>

<div class="page-content">
    <div class="signup-card">
        <div class="signup-header">
            <h1>Ahmed<span>Travels</span></h1>
            <p>Join us for unforgettable journeys</p>
        </div>
        <div class="signup-body">
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" id="signupForm" novalidate>
                <?php echo csrf_field(); ?>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="name" name="name" placeholder="Enter your full name"
                           value="<?php echo htmlspecialchars($old['name']); ?>" required>
                    <div class="field-error" id="err-name">Full name is required.</div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email"
                           value="<?php echo htmlspecialchars($old['email']); ?>" required>
                    <div class="field-error" id="err-email">Please enter a valid email address.</div>
                </div>

                <div class="form-group">
                    <label>Phone Number <span style="color:rgba(255,255,255,0.3);font-weight:400;">(optional)</span></label>
                    <input type="text" id="phone" name="phone" placeholder="Enter your phone number"
                           value="<?php echo htmlspecialchars($old['phone']); ?>">
                    <div class="field-error" id="err-phone">Please enter a valid phone number.</div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" name="password" placeholder="Create a password" required>
                    <div class="field-hint">8-20 characters, with uppercase, lowercase &amp; a number.</div>
                    <div class="field-error" id="err-password">Password doesn't meet the requirements.</div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter your password" required>
                    <div class="field-error" id="err-confirm">Passwords do not match.</div>
                </div>

                <div class="terms-row">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.</label>
                </div>

                <button type="submit" class="btn-signup" id="submitBtn">Create Account</button>
            </form>

            <div class="divider">OR</div>
            <a href="google_login.php" class="btn-google">
                <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.36 0-4.35-1.59-5.06-3.73H.9v2.34A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.94 10.69A5.4 5.4 0 0 1 3.66 9c0-.59.1-1.16.28-1.69V4.97H.9A9 9 0 0 0 0 9c0 1.45.35 2.83.9 4.03z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .9 4.97l3.04 2.34C4.65 5.17 6.64 3.58 9 3.58z"/></svg>
                Continue with Google
            </a>

            <div class="login-link">
                Already have an account? <a href="login.php">Sign in</a>
            </div>
        </div>
    </div>
</div>

<script>
    const form = document.getElementById('signupForm');
    const passwordRe = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,20}$/;

    function showError(id, show) {
        document.getElementById('err-' + id).style.display = show ? 'block' : 'none';
    }

    function validateField(id) {
        const val = document.getElementById(id).value.trim();
        let ok = true;
        if (id === 'name') ok = val.length > 0;
        if (id === 'email') ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
        if (id === 'phone') ok = val === '' || /^[0-9+\-\s()]{7,20}$/.test(val);
        if (id === 'password') ok = passwordRe.test(document.getElementById('password').value);
        if (id === 'confirm') {
            ok = document.getElementById('password').value === document.getElementById('confirm_password').value
                 && document.getElementById('confirm_password').value !== '';
        }
        showError(id === 'confirm_password' ? 'confirm' : id, !ok);
        return ok;
    }

    ['name','email','phone','password'].forEach(id => {
        document.getElementById(id).addEventListener('blur', () => validateField(id));
    });
    document.getElementById('confirm_password').addEventListener('input', () => validateField('confirm'));

    form.addEventListener('submit', function (e) {
        const checks = ['name','email','phone','password'].map(validateField);
        checks.push(validateField('confirm'));
        if (!document.getElementById('terms').checked) {
            e.preventDefault();
            alert('Please accept the Terms and Conditions to continue.');
            return;
        }
        if (checks.includes(false)) {
            e.preventDefault();
        }
    });
</script>
</body>
</html>