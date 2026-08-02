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
        .brand-content > p { color: rgba(255,255,255,0.5); font-size: 15px; line-height: 1.7; max-width: 380px; margin-bottom: 44px; opacity: 0; animation: fadeSlideIn 0.7s ease forwards; animation-delay: 0.28s; }
        @keyframes fadeSlideIn { to { opacity: 1; transform: translateY(0); } from { transform: translateY(14px); } }

        .feature-list { display: flex; flex-direction: column; gap: 20px; }
        .feature-list li { list-style: none; display: flex; align-items: center; gap: 14px; color: rgba(255,255,255,0.75); font-size: 14px; opacity: 0; animation: fadeSlideIn 0.6s ease forwards; }
        .feature-list li:nth-child(1) { animation-delay: 0.4s; }
        .feature-list li:nth-child(2) { animation-delay: 0.5s; }
        .feature-list li:nth-child(3) { animation-delay: 0.6s; }
        .feature-list .f-icon { flex-shrink: 0; width: 36px; height: 36px; border-radius: 10px; background: rgba(212,175,55,0.1); border: 1px solid rgba(212,175,55,0.15); display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 15px; }

        .form-panel { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px 24px; position: relative; }
        .form-box { width: 100%; max-width: 440px; opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards; animation-delay: 0.15s; }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .form-box h2 { font-family: 'Playfair Display', serif; font-size: 26px; color: white; margin-bottom: 6px; }
        .form-box .subtitle { color: rgba(255,255,255,0.4); font-size: 13.5px; margin-bottom: 28px; }

        .field { position: relative; margin-bottom: 18px; }
        .field .f-icon-input { position: absolute; left: 16px; top: 27px; color: rgba(255,255,255,0.25); font-size: 14px; transition: color 0.25s ease; pointer-events: none; }
        .field input {
            width: 100%; padding: 18px 16px 8px 42px; font-size: 14.5px;
            border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03);
            border-radius: 12px; color: white; transition: all 0.25s ease; font-family: inherit; height: 54px;
        }
        .field input::placeholder { color: transparent; }
        .field label { position: absolute; left: 42px; top: 50%; transform: translateY(-50%); font-size: 14px; color: rgba(255,255,255,0.35); pointer-events: none; transition: all 0.2s ease; }
        .field input:focus, .field input:not(:placeholder-shown) { border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }
        .field input.invalid { border-color: rgba(239,68,68,0.4); }
        .field input:focus + label, .field input:not(:placeholder-shown) + label { top: 12px; font-size: 10.5px; color: #d4af37; letter-spacing: 0.3px; text-transform: uppercase; font-weight: 600; }
        .field input:focus ~ .f-icon-input, .field input:not(:placeholder-shown) ~ .f-icon-input { color: #d4af37; }
        .field.has-error input { border-color: rgba(239,68,68,0.4); }

        .field-hint { font-size: 11.5px; color: rgba(255,255,255,0.3); margin-top: 6px; padding-left: 2px; }
        .field-error { font-size: 11.5px; color: #f87171; margin-top: 6px; padding-left: 2px; display: none; }

        /* NEW: password strength meter -- purely visual, additive, does
           not affect the existing validateField('password') logic or
           the server-side validate_password_strength() rule at all. */
        .pw-meter { display: flex; gap: 4px; margin-top: 8px; height: 4px; }
        .pw-meter span { flex: 1; border-radius: 2px; background: rgba(255,255,255,0.08); transition: background 0.3s ease; }
        .pw-meter.s1 span:nth-child(1) { background: #ef4444; }
        .pw-meter.s2 span:nth-child(-n+2) { background: #f59e0b; }
        .pw-meter.s3 span:nth-child(-n+3) { background: #eab308; }
        .pw-meter.s4 span { background: #22c55e; }
        .pw-label { font-size: 11px; margin-top: 5px; color: rgba(255,255,255,0.3); padding-left: 2px; }

        .terms-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 22px; margin-top: 6px; }
        .terms-row input { width: auto; margin-top: 3px; accent-color: #d4af37; }
        .terms-row label { font-size: 12.5px; color: rgba(255,255,255,0.5); font-weight: 400; }
        .terms-row a { color: #d4af37; text-decoration: none; font-weight: 500; transition: all 0.3s ease; }
        .terms-row a:hover { color: #b8922e; }

        .btn-signup {
            position: relative;
            width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer;
            transition: transform 0.15s ease-out, box-shadow 0.3s ease, background 0.3s ease;
            overflow: hidden;
        }
        .btn-signup:hover { background: #b8922e; box-shadow: 0 12px 32px rgba(212, 175, 55, 0.25); }
        .btn-signup:active { transform: scale(0.97) !important; }
        .btn-signup .btn-shine { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: skewX(-20deg); transition: left 0.6s ease; }
        .btn-signup:hover .btn-shine { left: 130%; }

        .divider { display: flex; align-items: center; gap: 12px; margin: 24px 0; color: rgba(255,255,255,0.2); font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255,255,255,0.05); }

        .btn-google {
            width: 100%; padding: 13px; background: rgba(255,255,255,0.03);
            color: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px; font-weight: 500; font-size: 14px; cursor: pointer; display: flex;
            align-items: center; justify-content: center; gap: 10px; text-decoration: none; transition: all 0.25s ease;
        }
        .btn-google:hover { border-color: rgba(212,175,55,0.3); background: rgba(255,255,255,0.06); transform: translateY(-2px); }

        .login-link { text-align: center; margin-top: 26px; font-size: 13.5px; color: rgba(255,255,255,0.4); }
        .login-link a { color: #d4af37; text-decoration: none; font-weight: 600; transition: all 0.3s ease; }
        .login-link a:hover { text-decoration: underline; }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); animation: shake 0.4s ease; }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }
        .error-message ul { margin: 0; padding-left: 18px; }

        .back-home { position: absolute; top: 28px; right: 32px; z-index: 2; }
        .back-home a { color: rgba(255,255,255,0.35); text-decoration: none; font-size: 13px; display: flex; align-items: center; gap: 6px; transition: color 0.2s ease; }
        .back-home a:hover { color: #d4af37; }

        @media (max-width: 900px) {
            .auth-wrap { flex-direction: column; }
            .brand-panel { flex: none; padding: 40px 32px; min-height: 220px; justify-content: flex-end; }
            .brand-content h1 { font-size: 28px; }
            .brand-content > p { display: none; }
            .feature-list { display: none; }
            .form-panel { padding: 32px 20px 60px; }
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
                <h1>Start your next<br><span>journey</span> with us</h1>
                <p>Create your account to book hotels, taxis, and visas in a few clicks -- and keep every trip in one place.</p>
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
                <h2>Create Account</h2>
                <p class="subtitle">Join us for unforgettable journeys</p>

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

                    <div class="field">
                        <i class="fas fa-user f-icon-input"></i>
                        <input type="text" id="name" name="name" placeholder=" "
                               value="<?php echo htmlspecialchars($old['name']); ?>" required>
                        <label for="name">Full Name</label>
                        <div class="field-error" id="err-name">Full name is required.</div>
                    </div>

                    <div class="field">
                        <i class="fas fa-envelope f-icon-input"></i>
                        <input type="email" id="email" name="email" placeholder=" "
                               value="<?php echo htmlspecialchars($old['email']); ?>" required>
                        <label for="email">Email Address</label>
                        <div class="field-error" id="err-email">Please enter a valid email address.</div>
                    </div>

                    <div class="field">
                        <i class="fas fa-phone f-icon-input"></i>
                        <input type="text" id="phone" name="phone" placeholder=" "
                               value="<?php echo htmlspecialchars($old['phone']); ?>">
                        <label for="phone">Phone Number (optional)</label>
                        <div class="field-error" id="err-phone">Please enter a valid phone number.</div>
                    </div>

                    <div class="field">
                        <i class="fas fa-lock f-icon-input"></i>
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password">Password</label>
                        <div class="pw-meter" id="pwMeter"><span></span><span></span><span></span><span></span></div>
                        <div class="pw-label" id="pwLabel">8-20 characters, with uppercase, lowercase &amp; a number</div>
                        <div class="field-error" id="err-password">Password doesn't meet the requirements.</div>
                    </div>

                    <div class="field">
                        <i class="fas fa-lock f-icon-input"></i>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder=" " required>
                        <label for="confirm_password">Confirm Password</label>
                        <div class="field-error" id="err-confirm">Passwords do not match.</div>
                    </div>

                    <div class="terms-row">
                        <input type="checkbox" id="terms" name="terms" required>
                        <label for="terms">I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>.</label>
                    </div>

                    <button type="submit" class="btn-signup magnetic" id="submitBtn"><span class="btn-shine"></span>Create Account</button>
                </form>

                <div class="divider">OR</div>
                <a href="google_login.php" class="btn-google">
                    <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.9c1.7-1.57 2.7-3.88 2.7-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.9-2.26c-.8.54-1.84.86-3.06.86-2.36 0-4.35-1.59-5.06-3.73H.9v2.34A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.94 10.69A5.4 5.4 0 0 1 3.66 9c0-.59.1-1.16.28-1.69V4.97H.9A9 9 0 0 0 0 9c0 1.45.35 2.83.9 4.03z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .9 4.97l3.04 2.34C4.65 5.17 6.64 3.58 9 3.58z"/></svg>
                    Continue with Google
                </a>

                <div class="login-link">Already have an account? <a href="login.php">Sign in</a></div>
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

    /* ---------- NEW: visual-only password strength meter. Reads the
       same field but does not alter validateField/showError/submit
       logic above in any way -- purely additive UI feedback. ---------- */
    const pwInput = document.getElementById('password');
    const pwMeter = document.getElementById('pwMeter');
    const pwLabel = document.getElementById('pwLabel');
    const pwLabels = ['Too short', 'Weak', 'Okay', 'Good', 'Strong'];
    pwInput.addEventListener('input', () => {
        const v = pwInput.value;
        let score = 0;
        if (v.length >= 8) score++;
        if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (v.length >= 12 || /[^A-Za-z0-9]/.test(v)) score++;
        pwMeter.className = 'pw-meter' + (v.length ? ' s' + Math.max(1, score) : '');
        pwLabel.textContent = v.length ? pwLabels[score] : '8-20 characters, with uppercase, lowercase & a number';
    });

    /* ---------- NEW: magnetic submit button (fine-pointer only) ---------- */
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