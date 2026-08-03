<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['reset_verified'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .auth-wrap { display: flex; min-height: 100vh; }

        .brand-panel {
            flex: 0 0 42%; position: relative; display: flex; flex-direction: column; justify-content: center;
            padding: 60px; overflow: hidden;
            background: radial-gradient(circle at 30% 20%, #12192c 0%, #0a0f1e 60%);
        }
        .brand-panel::before {
            content: ''; position: absolute; inset: -20%;
            background:
                radial-gradient(circle at 25% 25%, rgba(212,175,55,0.16), transparent 42%),
                radial-gradient(circle at 75% 75%, rgba(212,175,55,0.08), transparent 45%);
            animation: driftGlow 20s ease-in-out infinite alternate;
        }
        .brand-panel::after {
            content: ''; position: absolute; inset: 0; opacity: 0.55;
            background-image: radial-gradient(rgba(212,175,55,0.10) 1px, transparent 1px);
            background-size: 30px 30px;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 50% { transform: translate(-3%,2%) scale(1.06); } 100% { transform: translate(2%,-2%) scale(1.02); } }
        .brand-panel .bg-shape { position: absolute; opacity: 0.06; color: #d4af37; animation: floatShape 24s ease-in-out infinite; pointer-events: none; }
        .brand-panel .bg-shape.s1 { font-size: 160px; top: -30px; right: -30px; }
        .brand-panel .bg-shape.s2 { font-size: 90px; bottom: 40px; left: -10px; animation-delay: -10s; }
        @keyframes floatShape { 0%,100% { transform: translateY(0) rotate(0); } 50% { transform: translateY(-24px) rotate(10deg); } }

        .brand-content { position: relative; z-index: 1; color: white; }
        .brand-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 56px; opacity: 0; animation: fadeSlideIn 0.7s ease forwards; }
        .brand-logo .logo-icon { background: #d4af37; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 19px; color: #0a0f1e; }
        .brand-logo span { font-size: 22px; font-weight: 800; }
        .brand-logo span b { color: #d4af37; font-weight: 800; }
        .brand-content h1 { font-family: 'Playfair Display', serif; font-size: 40px; font-weight: 800; line-height: 1.2; margin-bottom: 18px; opacity: 0; animation: fadeSlideIn 0.7s ease forwards; animation-delay: 0.15s; }
        .brand-content h1 span { color: #d4af37; }
        .brand-content > p { color: rgba(255,255,255,0.5); font-size: 15px; line-height: 1.7; max-width: 360px; opacity: 0; animation: fadeSlideIn 0.7s ease forwards; animation-delay: 0.28s; }
        @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } from { transform: translateY(14px); } }

        .form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; position: relative; }
        .form-box { width: 100%; max-width: 420px; opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards; animation-delay: 0.15s; }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .form-box .icon-badge { width: 56px; height: 56px; border-radius: 16px; background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 22px; margin-bottom: 20px; }
        .form-box h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: white; margin-bottom: 6px; }
        .form-box .subtitle { color: rgba(255,255,255,0.4); font-size: 13.5px; margin-bottom: 28px; }

        .field { position: relative; margin-bottom: 20px; }
        .field .f-icon-input { position: absolute; left: 16px; top: 27px; color: rgba(255,255,255,0.25); font-size: 14px; transition: color 0.25s ease; pointer-events: none; }
        .field input { width: 100%; padding: 18px 16px 8px 42px; font-size: 14.5px; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03); border-radius: 12px; color: white; transition: all 0.25s ease; font-family: inherit; height: 54px; }
        .field input::placeholder { color: transparent; }
        .field label { position: absolute; left: 42px; top: 50%; transform: translateY(-50%); font-size: 14px; color: rgba(255,255,255,0.35); pointer-events: none; transition: all 0.2s ease; }
        .field input:focus, .field input:not(:placeholder-shown) { border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }
        .field input:focus + label, .field input:not(:placeholder-shown) + label { top: 12px; font-size: 10.5px; color: #d4af37; letter-spacing: 0.3px; text-transform: uppercase; font-weight: 600; }
        .field input:focus ~ .f-icon-input, .field input:not(:placeholder-shown) ~ .f-icon-input { color: #d4af37; }

        /* NEW: password strength meter -- purely visual, additive */
        .pw-meter { display: flex; gap: 4px; margin-top: 8px; height: 4px; }
        .pw-meter span { flex: 1; border-radius: 2px; background: rgba(255,255,255,0.08); transition: background 0.3s ease; }
        .pw-meter.s1 span:nth-child(1) { background: #ef4444; }
        .pw-meter.s2 span:nth-child(-n+2) { background: #f59e0b; }
        .pw-meter.s3 span:nth-child(-n+3) { background: #eab308; }
        .pw-meter.s4 span { background: #22c55e; }
        .pw-label { font-size: 11px; margin-top: 5px; color: rgba(255,255,255,0.3); padding-left: 2px; }

        .btn-primary {
            position: relative; width: 100%; padding: 15px; margin-top: 10px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer;
            transition: transform 0.15s ease-out, box-shadow 0.3s ease, background 0.3s ease; overflow: hidden;
        }
        .btn-primary:hover { background: #b8922e; box-shadow: 0 12px 32px rgba(212, 175, 55, 0.25); }
        .btn-primary:active { transform: scale(0.97) !important; }
        .btn-primary .btn-shine { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: skewX(-20deg); transition: left 0.6s ease; }
        .btn-primary:hover .btn-shine { left: 130%; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 22px; border: 1px solid rgba(239,68,68,0.1); display: flex; align-items: center; gap: 10px; animation: shake 0.4s ease; }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }

        .back-home { position: absolute; top: 28px; right: 32px; z-index: 2; }
        .back-home a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: color 0.2s ease; }
        .back-home a:hover { color: #d4af37; }

        @media (max-width: 900px) {
            .auth-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 40px 32px; min-height: 200px; justify-content: flex-end; }
            .brand-content h1 { font-size: 28px; }
            .brand-content > p { display: none; }
            .form-panel { padding: 32px 20px 60px; }
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
                <h1>Set a new<br><span>password</span></h1>
                <p>Almost done -- choose a strong new password to secure your account.</p>
            </div>
        </div>

        <div class="form-panel">
            <div class="back-home"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></div>
            <div class="form-box">
                <div class="icon-badge"><i class="fas fa-lock-open"></i></div>
                <h2>Reset Password</h2>
                <p class="subtitle">Enter and confirm your new password</p>

                <?php if($error): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="field">
                        <i class="fas fa-lock f-icon-input"></i>
                        <input type="password" name="password" id="new_password" placeholder=" " required>
                        <label for="new_password">New Password (min 6)</label>
                        <div class="pw-meter" id="pwMeter"><span></span><span></span><span></span><span></span></div>
                        <div class="pw-label" id="pwLabel">Minimum 6 characters</div>
                    </div>
                    <div class="field">
                        <i class="fas fa-lock f-icon-input"></i>
                        <input type="password" name="confirm_password" id="confirm_password" placeholder=" " required>
                        <label for="confirm_password">Confirm Password</label>
                    </div>
                    <button type="submit" class="btn-primary magnetic"><span class="btn-shine"></span>Reset Password</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        /* NEW: visual-only password strength meter */
        const pwInput = document.getElementById('new_password');
        const pwMeter = document.getElementById('pwMeter');
        const pwLabel = document.getElementById('pwLabel');
        const pwLabels = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'];
        pwInput.addEventListener('input', () => {
            const v = pwInput.value;
            let score = 0;
            if (v.length >= 6) score++;
            if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
            if (/\d/.test(v)) score++;
            if (v.length >= 12 || /[^A-Za-z0-9]/.test(v)) score++;
            pwMeter.className = 'pw-meter' + (v.length ? ' s' + Math.max(1, score) : '');
            pwLabel.textContent = v.length ? pwLabels[score] : 'Minimum 6 characters';
        });

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