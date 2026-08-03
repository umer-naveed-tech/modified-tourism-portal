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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0a0f1e;
            min-height: 100vh;
            overflow-x: hidden;
        }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ============================================================
           Split layout: branded panel (left) + form panel (right).
           Stacks vertically on small screens.
           ============================================================ */
        .auth-wrap { display: flex; min-height: 100vh; }

        .brand-panel {
            flex: 0 0 42%;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 60px;
            overflow: hidden;
            background: radial-gradient(circle at 30% 20%, #12192c 0%, #0a0f1e 60%);
        }
        .brand-panel::before {
            content: '';
            position: absolute; inset: -20%;
            background:
                radial-gradient(circle at 25% 25%, rgba(212,175,55,0.16), transparent 42%),
                radial-gradient(circle at 75% 75%, rgba(212,175,55,0.08), transparent 45%);
            animation: driftGlow 20s ease-in-out infinite alternate;
        }
        .brand-panel::after {
            content: '';
            position: absolute; inset: 0;
            opacity: 0.55;
            background-image: radial-gradient(rgba(212,175,55,0.10) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        @keyframes driftGlow {
            0% { transform: translate(0,0) scale(1); }
            50% { transform: translate(-3%,2%) scale(1.06); }
            100% { transform: translate(2%,-2%) scale(1.02); }
        }
        .brand-panel .bg-shape {
            position: absolute; opacity: 0.06; color: #d4af37;
            animation: floatShape 24s ease-in-out infinite;
            pointer-events: none;
        }
        .brand-panel .bg-shape.s1 { font-size: 160px; top: -30px; right: -30px; }
        .brand-panel .bg-shape.s2 { font-size: 90px; bottom: 40px; left: -10px; animation-delay: -10s; }
        @keyframes floatShape { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-24px) rotate(10deg); } }

        .brand-content { position: relative; z-index: 1; color: white; }
        .brand-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 56px; opacity: 0; animation: fadeSlideIn 0.7s ease forwards; }
        .brand-logo .logo-icon {
            background: #d4af37; width: 42px; height: 42px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 19px; color: #0a0f1e;
        }
        .brand-logo span { font-size: 22px; font-weight: 800; }
        .brand-logo span b { color: #d4af37; font-weight: 800; }

        .brand-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 42px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 18px;
            opacity: 0;
            animation: fadeSlideIn 0.7s ease forwards;
            animation-delay: 0.15s;
        }
        .brand-content h1 span { color: #d4af37; }
        .brand-content > p {
            color: rgba(255,255,255,0.5);
            font-size: 15px;
            line-height: 1.7;
            max-width: 380px;
            margin-bottom: 44px;
            opacity: 0;
            animation: fadeSlideIn 0.7s ease forwards;
            animation-delay: 0.28s;
        }
        @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } from { transform: translateY(14px); } }

        .feature-list { display: flex; flex-direction: column; gap: 20px; }
        .feature-list li {
            list-style: none;
            display: flex; align-items: center; gap: 14px;
            color: rgba(255,255,255,0.75);
            font-size: 14px;
            opacity: 0;
            animation: fadeSlideIn 0.6s ease forwards;
        }
        .feature-list li:nth-child(1) { animation-delay: 0.4s; }
        .feature-list li:nth-child(2) { animation-delay: 0.5s; }
        .feature-list li:nth-child(3) { animation-delay: 0.6s; }
        .feature-list .f-icon {
            flex-shrink: 0;
            width: 36px; height: 36px; border-radius: 10px;
            background: rgba(212,175,55,0.1);
            border: 1px solid rgba(212,175,55,0.15);
            display: flex; align-items: center; justify-content: center;
            color: #d4af37; font-size: 15px;
        }

        /* ---------- Form panel ---------- */
        .form-panel {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 24px;
            position: relative;
        }
        .form-box {
            width: 100%;
            max-width: 400px;
            opacity: 0;
            transform: translateY(16px);
            animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
            animation-delay: 0.15s;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .form-box h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: white; margin-bottom: 6px; }
        .form-box .subtitle { color: rgba(255,255,255,0.4); font-size: 13.5px; margin-bottom: 32px; }

        /* ---------- Floating-label fields ---------- */
        .field { position: relative; margin-bottom: 20px; }
        .field .f-icon-input {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: rgba(255,255,255,0.25); font-size: 14px; transition: color 0.25s ease;
            pointer-events: none;
        }
        .field input {
            width: 100%;
            padding: 18px 16px 8px 42px;
            font-size: 14.5px;
            border: 1px solid rgba(255,255,255,0.08);
            background: rgba(255,255,255,0.03);
            border-radius: 12px;
            color: white;
            transition: all 0.25s ease;
            font-family: inherit;
            height: 54px;
        }
        .field input::placeholder { color: transparent; }
        .field label {
            position: absolute;
            left: 42px; top: 50%; transform: translateY(-50%);
            font-size: 14px;
            color: rgba(255,255,255,0.35);
            pointer-events: none;
            transition: all 0.2s ease;
            background: transparent;
        }
        .field input:focus,
        .field input:not(:placeholder-shown) {
            border-color: #d4af37;
            box-shadow: 0 0 0 4px rgba(212,175,55,0.07);
            background: rgba(255,255,255,0.05);
        }
        .field input:focus + label,
        .field input:not(:placeholder-shown) + label {
            top: 12px;
            font-size: 10.5px;
            color: #d4af37;
            letter-spacing: 0.3px;
            text-transform: uppercase;
            font-weight: 600;
        }
        .field input:focus ~ .f-icon-input,
        .field input:not(:placeholder-shown) ~ .f-icon-input { color: #d4af37; }

        .remember-row { display: flex; align-items: center; justify-content: space-between; margin-bottom: 22px; }
        .remember-row label { display: flex; align-items: center; gap: 7px; font-size: 13px; color: rgba(255,255,255,0.5); font-weight: 400; cursor: pointer; }
        .remember-row input { width: auto; accent-color: #d4af37; }
        .remember-row a { font-size: 12.5px; color: rgba(255,255,255,0.35); text-decoration: none; transition: color 0.2s ease; }
        .remember-row a:hover { color: #d4af37; }

        .btn-login {
            position: relative;
            width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer;
            transition: transform 0.15s ease-out, box-shadow 0.3s ease, background 0.3s ease;
            overflow: hidden;
        }
        .btn-login:hover { background: #b8922e; box-shadow: 0 12px 32px rgba(212, 175, 55, 0.25); }
        .btn-login:active { transform: scale(0.97) !important; }
        .btn-login .btn-shine {
            position: absolute; top: 0; left: -60%; width: 40%; height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent);
            transform: skewX(-20deg);
            transition: left 0.6s ease;
        }
        .btn-login:hover .btn-shine { left: 130%; }

        .divider { display: flex; align-items: center; gap: 12px; margin: 26px 0; color: rgba(255,255,255,0.2); font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.05); }

        .btn-google {
            width: 100%; padding: 13px; background: rgba(255,255,255,0.03);
            color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; font-weight: 500; font-size: 14px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: all 0.25s ease;
        }
        .btn-google:hover { border-color: rgba(212,175,55,0.3); background: rgba(255,255,255,0.06); transform: translateY(-2px); }

        .signup-link { text-align: center; margin-top: 28px; font-size: 13.5px; color: rgba(255,255,255,0.4); }
        .signup-link a { color: #d4af37; text-decoration: none; font-weight: 600; transition: all 0.2s ease; }
        .signup-link a:hover { text-decoration: underline; }

        .error-message {
            background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px;
            font-size: 13px; margin-bottom: 22px; text-align: left; border: 1px solid rgba(239,68,68,0.1);
            display: flex; align-items: center; gap: 10px;
            animation: shake 0.4s ease;
        }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }
        .success-message {
            background: rgba(16,185,129,0.07); color: #34d399; padding: 13px 16px; border-radius: 12px;
            font-size: 13px; margin-bottom: 22px; text-align: left; border: 1px solid rgba(16,185,129,0.1);
            display: flex; align-items: center; gap: 10px;
        }

        .back-home { position: absolute; top: 28px; right: 32px; z-index: 2; }
        .back-home a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: color 0.2s ease; }
        .back-home a:hover { color: #d4af37; }

        @media (max-width: 900px) {
            .auth-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 44px 32px; min-height: 260px; justify-content: flex-end; }
            .brand-content h1 { font-size: 30px; }
            .brand-content > p { display: none; }
            .feature-list { display: none; }
            .form-panel { padding: 36px 20px 60px; }
        }
    
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
    <div class="grain-overlay" aria-hidden="true"></div>

    <div class="auth-wrap">
        <div class="brand-panel">
            <i class="fas fa-star-and-crescent bg-shape s1"></i>
            <i class="fas fa-star-and-crescent bg-shape s2"></i>
            <div class="brand-content">
                <div class="brand-logo">
                    <div class="logo-icon"><i class="fas fa-plane"></i></div>
                    <span>Ahmed<b>Travels</b></span>
                </div>
                <h1>Welcome back to<br>your <span>journey</span></h1>
                <p>Sign in to manage your bookings, track your trips, and pick up right where you left off.</p>
                <ul class="feature-list">
                    <li><span class="f-icon"><i class="fas fa-hotel"></i></span> Handpicked hotels across Saudi Arabia</li>
                    <li><span class="f-icon"><i class="fas fa-car"></i></span> Reliable, on-time taxi booking</li>
                    <li><span class="f-icon"><i class="fas fa-passport"></i></span> Fast, guided visa processing</li>
                </ul>
            </div>
        </div>

        <div class="form-panel">
            <div class="back-home"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></div>
            <div class="form-box">
                <h2>Sign In</h2>
                <p class="subtitle">Enter your details to access your account</p>

                <?php if ($registered): ?>
                    <div class="success-message"><i class="fas fa-check-circle"></i> Account created! Please sign in.</div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <i class="fas fa-envelope f-icon-input"></i>
                        <input type="email" name="email" id="email" placeholder=" " required autofocus>
                        <label for="email">Email Address</label>
                    </div>
                    <div class="field">
                        <i class="fas fa-lock f-icon-input"></i>
                        <input type="password" name="password" id="password" placeholder=" " required>
                        <label for="password">Password</label>
                    </div>
                    <div class="remember-row">
                        <label><input type="checkbox" name="remember_me"> Remember me</label>
                        <a href="forgot_password.php">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn-login magnetic"><span class="btn-shine"></span>Sign In</button>
                </form>

                <div class="divider">OR</div>
                <a href="google_login.php" class="btn-google">
                    <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.36 0-4.35-1.59-5.06-3.73H.9v2.34A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.94 10.69A5.4 5.4 0 0 1 3.66 9c0-.59.1-1.16.28-1.69V4.97H.9A9 9 0 0 0 0 9c0 1.45.35 2.83.9 4.03z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .9 4.97l3.04 2.34C4.65 5.17 6.64 3.58 9 3.58z"/></svg>
                    Continue with Google
                </a>

                <div class="signup-link">Don't have an account? <a href="signup.php">Create an account</a></div>
            </div>
        </div>
    </div>

    <script>
        /* NEW: magnetic pull on the submit button (fine-pointer only) */
        if (window.matchMedia('(pointer: fine)').matches) {
            document.querySelectorAll('.magnetic').forEach(btn => {
                btn.addEventListener('mousemove', (e) => {
                    const r = btn.getBoundingClientRect();
                    const relX = e.clientX - r.left - r.width / 2;
                    const relY = e.clientY - r.top - r.height / 2;
                    btn.style.transform = `translate(${relX * 0.08}px, ${relY * 0.25}px)`;
                });
                btn.addEventListener('mouseleave', () => { btn.style.transform = 'translate(0,0)'; });
            });
        }
    </script>

<script>
    /* NEW: disable the submit button and show a spinner while the form
       is submitting, so double-clicking never fires a second (duplicate)
       request. Skips entirely if an earlier listener already cancelled
       the submit (e.g. client-side validation failing) -- never leaves
       a valid form stuck showing "Processing...". Runs independently of
       any other <script> block on this page -- doesn't touch them. */
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