<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'visitor') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'auth_helpers.php';

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors = [];
$success = false;
$old = ['name' => $user['name'], 'phone' => $user['phone']];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();

    $old['name']  = trim($_POST['name'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    // Email is intentionally never read from $_POST here -- it cannot be
    // changed from this form, for account-security reasons.

    if ($e = validate_name($old['name']))            $errors[] = $e;
    if ($e = validate_phone_optional($old['phone']))  $errors[] = $e;

    if (empty($errors)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$old['name'], $old['phone'], $user_id])) {
            $_SESSION['user_name'] = $old['name'];
            $success = true;
            $user['name'] = $old['name'];
            $user['phone'] = $old['phone'];
        } else {
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}

$name_parts = preg_split('/\s+/', trim($old['name']));
$initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .bg-ambient { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; background: #0a0f1e; }
        .bg-ambient::before {
            content: ''; position: absolute; inset: -20%;
            background: radial-gradient(circle at 20% 15%, rgba(212,175,55,0.09), transparent 40%),
                        radial-gradient(circle at 85% 80%, rgba(212,175,55,0.06), transparent 40%);
            animation: driftGlow 24s ease-in-out infinite alternate;
        }
        @keyframes driftGlow { 0% { transform: translate(0,0) scale(1); } 100% { transform: translate(2%,-2%) scale(1.05); } }
        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .page-content { position: relative; z-index: 1; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { font-family: 'Playfair Display', serif; color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .back-link { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 13.5px; display: flex; align-items: center; gap: 6px; transition: all 0.2s ease; }
        .back-link:hover { color: #d4af37; }

        .profile-form-card {
            background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05);
            border-radius: 20px; padding: 40px; max-width: 480px; margin: 50px auto;
            opacity: 0; transform: translateY(16px); animation: cardIn 0.6s cubic-bezier(.2,.8,.3,1) forwards;
        }
        @keyframes cardIn { to { opacity: 1; transform: translateY(0); } }

        .form-avatar-row { display: flex; align-items: center; gap: 16px; margin-bottom: 30px; }
        .profile-avatar {
            width: 60px; height: 60px; border-radius: 50%; flex-shrink: 0;
            background: linear-gradient(135deg, #d4af37, #b8922e); color: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 800;
        }
        .profile-form-card h2 { font-family: 'Playfair Display', serif; color: white; font-size: 22px; }
        .profile-form-card .subtitle { color: rgba(255,255,255,0.4); font-size: 13px; margin-top: 3px; }

        .field { position: relative; margin-bottom: 20px; }
        .field .f-icon-input { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.25); font-size: 14px; transition: color 0.25s ease; pointer-events: none; }
        .field input {
            width: 100%; padding: 18px 16px 8px 42px; font-size: 14.5px;
            border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.03);
            border-radius: 12px; color: white; transition: all 0.25s ease; font-family: inherit; height: 54px;
        }
        .field input::placeholder { color: transparent; }
        .field label { position: absolute; left: 42px; top: 50%; transform: translateY(-50%); font-size: 14px; color: rgba(255,255,255,0.35); pointer-events: none; transition: all 0.2s ease; }
        .field input:focus, .field input:not(:placeholder-shown) { border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.07); background: rgba(255,255,255,0.05); }
        .field input:focus + label, .field input:not(:placeholder-shown) + label { top: 12px; font-size: 10.5px; color: #d4af37; letter-spacing: 0.3px; text-transform: uppercase; font-weight: 600; }
        .field input:focus ~ .f-icon-input, .field input:not(:placeholder-shown) ~ .f-icon-input { color: #d4af37; }
        .field input[readonly] { color: rgba(255,255,255,0.35); cursor: not-allowed; }
        .field input[readonly]:focus, .field input[readonly]:not(:placeholder-shown) { border-color: rgba(255,255,255,0.08); box-shadow: none; }
        .field-hint { font-size: 11.5px; color: rgba(255,255,255,0.3); margin-top: 6px; padding-left: 2px; }

        .btn-save {
            position: relative; overflow: hidden;
            width: 100%; padding: 15px; background: #d4af37; color: #0a0f1e; border: none;
            border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; transition: all 0.3s ease;
        }
        .btn-save:hover { background: #b8922e; box-shadow: 0 12px 32px rgba(212, 175, 55, 0.25); }
        .btn-save .btn-shine { position: absolute; top: 0; left: -60%; width: 40%; height: 100%; background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: skewX(-20deg); transition: left 0.6s ease; }
        .btn-save:hover .btn-shine { left: 130%; }

        .btn-spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.35); border-top-color: currentColor; border-radius: 50%; animation: btnSpin 0.6s linear infinite; margin-right: 8px; vertical-align: -2px; }
        @keyframes btnSpin { to { transform: rotate(360deg); } }

        .error-message { background: rgba(239,68,68,0.07); color: #f87171; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(239,68,68,0.1); animation: shake 0.4s ease; }
        @keyframes shake { 20%, 60% { transform: translateX(-4px); } 40%, 80% { transform: translateX(4px); } }
        .error-message ul { margin: 0; padding-left: 18px; }
        .success-message { background: rgba(16,185,129,0.07); color: #34d399; padding: 13px 16px; border-radius: 12px; font-size: 13px; margin-bottom: 20px; border: 1px solid rgba(16,185,129,0.1); display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body>
    <div class="bg-ambient" aria-hidden="true"></div>
    <div class="grain-overlay" aria-hidden="true"></div>

<div class="page-content">
    <nav class="navbar">
        <div class="container">
            <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
            <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </nav>

    <div class="container">
        <div class="profile-form-card">
            <div class="form-avatar-row">
                <div class="profile-avatar"><?php echo htmlspecialchars($initials); ?></div>
                <div>
                    <h2>Edit Profile</h2>
                    <p class="subtitle">Update your personal details</p>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="success-message"><i class="fas fa-check-circle"></i> Profile updated successfully.</div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="error-message">
                    <ul>
                        <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <div class="field">
                    <i class="fas fa-user f-icon-input"></i>
                    <input type="text" id="name" name="name" placeholder=" " value="<?php echo htmlspecialchars($old['name']); ?>" required>
                    <label for="name">Full Name</label>
                </div>
                <div class="field">
                    <i class="fas fa-phone f-icon-input"></i>
                    <input type="text" id="phone" name="phone" placeholder=" " value="<?php echo htmlspecialchars($old['phone']); ?>">
                    <label for="phone">Phone Number</label>
                </div>
                <div class="field">
                    <i class="fas fa-envelope f-icon-input"></i>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    <label>Email Address</label>
                </div>
                <p class="field-hint" style="margin-top:-12px; margin-bottom:20px;"><i class="fas fa-lock" style="font-size:10px;"></i> Email can't be changed here for account security. Contact support if you need to update it.</p>

                <button type="submit" class="btn-save"><span class="btn-shine"></span>Save Changes</button>
            </form>
        </div>
    </div>
</div>

<script>
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