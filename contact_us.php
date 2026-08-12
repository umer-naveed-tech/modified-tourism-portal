<?php
// contact_us.php
//
// Simple contact-info page -- 3 Saudi phone numbers, the support
// email, and a WhatsApp button. Linked from the "Support" item in
// the customer sidebar (previously that link went straight to
// WhatsApp with nothing else shown).

session_start();
require_once 'config.php';

$contact_numbers = ['+966510361841', '+966511538108', '+966553382876'];
$contact_email = 'ahmedtvl606@gmail.com';
$whatsapp_number = '923134830023';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Ahmed Travels</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: var(--font-body, 'Inter', sans-serif); background: var(--c-bg); color: var(--c-text); min-height: 100vh; }
        .wrap { max-width: 560px; margin: 0 auto; padding: 60px 24px 80px; }
        .back-link { color: var(--c-muted); text-decoration: none; font-size: 13px; display: inline-block; margin-bottom: 28px; }
        .back-link:hover { color: var(--c-accent); }
        .header { text-align: center; margin-bottom: 36px; }
        .gold-line { width: 50px; height: 3px; background: #d4af37; margin: 0 auto 16px; border-radius: 2px; }
        .header h1 { font-family: var(--font-heading); font-size: 30px; font-weight: 800; }
        .header p { color: var(--c-muted); font-size: 13.5px; margin-top: 8px; }

        .contact-card { background: var(--c-card-bg); border: 1px solid var(--c-border); border-radius: 18px; padding: 28px; margin-bottom: 16px; box-shadow: 0 12px 34px rgba(120,95,40,0.08); }
        .contact-card h3 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.6px; color: var(--c-accent); margin-bottom: 14px; }
        .contact-row { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid var(--c-border); }
        .contact-row:last-child { border-bottom: none; }
        .contact-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(212,175,55,0.1); color: var(--c-accent); display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .contact-text a { color: var(--c-text); text-decoration: none; font-size: 14.5px; font-weight: 600; }
        .contact-text a:hover { color: var(--c-accent); }
        .contact-text .label { font-size: 11px; color: var(--c-muted); margin-bottom: 2px; }

        .btn-whatsapp { display: flex; align-items: center; justify-content: center; gap: 10px; background: #25D366; color: white; padding: 15px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 14.5px; margin-top: 8px; box-shadow: 0 10px 28px rgba(37,211,102,0.25); }
        .btn-whatsapp:hover { background: #1ebe5a; }
    </style>
    <?php include 'dynamic_theme.php'; ?>
</head>
<body>
    <div class="wrap">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>
        <div class="header">
            <div class="gold-line"></div>
            <h1>Contact Us</h1>
            <p>We're happy to help with any booking, big or small.</p>
        </div>

        <div class="contact-card">
            <h3>Phone</h3>
            <?php foreach ($contact_numbers as $num): ?>
            <div class="contact-row">
                <div class="contact-icon"><i class="fas fa-phone" aria-hidden="true"></i></div>
                <div class="contact-text"><a href="tel:<?php echo htmlspecialchars($num); ?>"><?php echo htmlspecialchars($num); ?></a></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="contact-card">
            <h3>Email</h3>
            <div class="contact-row">
                <div class="contact-icon"><i class="fas fa-envelope" aria-hidden="true"></i></div>
                <div class="contact-text"><a href="mailto:<?php echo htmlspecialchars($contact_email); ?>"><?php echo htmlspecialchars($contact_email); ?></a></div>
            </div>
        </div>

        <a href="https://wa.me/<?php echo htmlspecialchars($whatsapp_number); ?>" target="_blank" class="btn-whatsapp">
            <i class="fab fa-whatsapp" aria-hidden="true"></i> Chat on WhatsApp
        </a>
    </div>
</body>
</html>