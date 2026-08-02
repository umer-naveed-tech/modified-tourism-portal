<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['reset_email'])) {
    header('Location: forgot_password.php');
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'];
    
    // Get the latest OTP from database
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$email]);
    $db_record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($db_record) {
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | Ahmed Travels</title>
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
        .form-box .subtitle { color: rgba(255,255,255,0.4); font-size: 13.5px; margin-bottom: 28px; line-height: 1.6; }

        /* OTP input -- kept as a single text field (same name="otp",
           same maxlength/required/autofocus) but styled larger with
           wide letter-spacing so it reads like a code. */
        .otp-field input {
            width: 100%; padding: 18px; font-size: 26px; text-align: center; letter-spacing: 14px;
            border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03);
            border-radius: 12px; color: #d4af37; font-weight: 700; font-family: inherit;
            transition: all 0.25s ease;
        }
        .otp-field input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.08); background: rgba(255,255,255,0.05); }
        .otp-field input::placeholder { color: rgba(212,175,55,0.2); letter-spacing: 14px; }

        .btn-primary {
            position: relative; width: 100%; padding: 15px; margin-top: 22px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer;
            transition: transform 0.15s ease-out, box-shadow 0.3s ease, background 0.3s ease; overflow: hidden;
        }
        .btn-primary:hover { background: #b8922e; box-shadow: 0 12px 32px rgba(212, 175, 55, 0.25); }
        .btn-primary:active { transform: scale(0.97) !important; }
        .btn-primary .btn-shine { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: skewX(-20deg); transition: left 0.6s ease; }
        .btn-primary:hover .btn-shine { left: 130%; }

        .resend-row { text-align: center; margin-top: 22px; font-size: 13px; }
        .btn-link { background: none; border: none; color: #d4af37; text-decoration: underline; cursor: pointer; font-size: 13px; font-family: inherit; padding: 0; }
        .btn-link:hover { color: #b8922e; }
        .request-new { display: block; margin-top: 10px; color: rgba(255,255,255,0.35); text-decoration: none; font-size: 12.5px; transition: color 0.2s ease; }
        .request-new:hover { color: #d4af37; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); display: flex; align-items: center; gap: 10px; animation: shake 0.4s ease; }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }
        .success-message { background: rgba(16,185,129,0.07); color: #34d399; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(16,185,129,0.1); display: flex; align-items: center; gap: 10px; }

        .back-home { position: absolute; top: 28px; right: 32px; z-index: 2; }
        .back-home a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: color 0.2s ease; }
        .back-home a:hover { color: #d4af37; }

        @media (max-width: 900px) {
            .auth-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 40px 32px; min-height: 200px; justify-content: flex-end; }
            .brand-content h1 { font-size: 28px; }
            .brand-content > p { display: none; }
            .form-panel { padding: 32px 20px 60px; }
            .otp-field input { font-size: 22px; letter-spacing: 10px; }
        }
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
                <h1>Check your<br><span>inbox</span></h1>
                <p>We've sent a 6-digit verification code to your email. Enter it below to continue resetting your password.</p>
            </div>
        </div>

        <div class="form-panel">
            <div class="back-home"><a href="index.php"><i class="fas fa-arrow-left"></i> Back to home</a></div>
            <div class="form-box">
                <div class="icon-badge"><i class="fas fa-shield-halved"></i></div>
                <h2>Verify OTP</h2>
                <p class="subtitle">Enter the 6-digit code sent to your email</p>

                <?php if(isset($_SESSION['reset_success'])): ?>
                    <div class="success-message"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['reset_success']); unset($_SESSION['reset_success']); ?></div>
                <?php endif; ?>
                <?php if($error): ?>
                    <div class="error-message"><i class="fas fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="otp-field">
                        <input type="text" name="otp" placeholder="000000" maxlength="6" required autofocus>
                    </div>
                    <button type="submit" class="btn-primary magnetic"><span class="btn-shine"></span>Verify OTP</button>
                </form>

                <div class="resend-row">
                    <form method="POST" action="resend_otp.php" style="display:inline;">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn-link">Resend OTP</button>
                    </form>
                    <a href="forgot_password.php" class="request-new">Request New OTP</a>
                </div>
            </div>
        </div>
    </div>

    <script>
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
</body>
</html>