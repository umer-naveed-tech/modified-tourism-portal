<?php
// agent_theme_settings.php
//
// Complete customer-panel theme control: background photos for every
// page (dashboard hero, 3 service cards, and now My Bookings/History/
// Payments too), a color theme (Elegant / Classic Elegant), and an
// entrance-animation style (10 options). Everything here is read by
// dynamic_theme.php on every customer page, so a save here updates
// the WHOLE customer panel immediately -- nothing else to touch.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'image_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // ---- Images (all optional -- only the ones actually chosen get saved) ----
    $slots = ['dashboard_hero', 'service_hotel', 'service_taxi', 'service_visa'];
    $upload_dir = __DIR__ . '/uploads/theme_images/';
    $img_stmt = $pdo->prepare("
        INSERT INTO site_theme_images (setting_key, image_path) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
    ");
    foreach ($slots as $slot) {
        if (!empty($_FILES[$slot]) && $_FILES[$slot]['error'] === UPLOAD_ERR_OK) {
            // Higher quality/resolution than the default (these are
            // large background photos, not small thumbnails) -- still
            // automatically compressed, just with a higher ceiling so
            // they stay sharp at full width.
            $filename = handleImageUpload($_FILES[$slot], $upload_dir, $slot, 2400, 88);
            if ($filename) {
                $img_stmt->execute([$slot, 'uploads/theme_images/' . $filename]);
            }
        }
    }

    // ---- Theme style + animation style ----
    $allowed_themes = ['elegant', 'classic_elegant'];
    $allowed_animations = ['fade_up', 'fade_in', 'slide_left', 'slide_right', 'zoom_in', 'flip_up', 'bounce_in', 'blur_in', 'rotate_in', 'cascade'];
    $theme_style = in_array($_POST['theme_style'] ?? '', $allowed_themes) ? $_POST['theme_style'] : 'elegant';
    $animation_style = in_array($_POST['animation_style'] ?? '', $allowed_animations) ? $_POST['animation_style'] : 'fade_up';

    $set_stmt = $pdo->prepare("
        INSERT INTO site_theme_settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $set_stmt->execute(['theme_style', $theme_style]);
    $set_stmt->execute(['animation_style', $animation_style]);

    header('Location: agent_theme_settings.php?saved=1');
    exit();
}

$stmt = $pdo->query("SELECT setting_key, image_path FROM site_theme_images");
$images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_theme_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$current_theme = $settings['theme_style'] ?? 'elegant';
$current_animation = $settings['animation_style'] ?? 'fade_up';

$slots_info = [
    'dashboard_hero' => ['label' => 'Dashboard Hero Background', 'hint' => 'The big full-screen photo behind "Welcome back" on the customer dashboard. A wide, atmospheric travel/city/skyline shot works best.'],
    'service_hotel' => ['label' => 'Hotel Service Card', 'hint' => 'Background for the "Hotel" card on the Book a Service page.'],
    'service_taxi' => ['label' => 'Taxi Service Card', 'hint' => 'Background for the "Taxi" card.'],
    'service_visa' => ['label' => 'Visa/Tour Service Card', 'hint' => 'Background for the "Visa & Tours" card.'],
];

$theme_options = [
    'elegant' => ['label' => 'Elegant', 'desc' => 'Warm cream & gold -- the current look', 'bg' => '#faf7f1', 'accent' => '#c9a24b', 'text' => '#2b2620'],
    'classic_elegant' => ['label' => 'Classic Elegant', 'desc' => 'Ivory & deep navy, formal traditional feel', 'bg' => '#fbfaf6', 'accent' => '#a9812c', 'text' => '#1b2536'],
];

$animation_options = [
    'fade_up' => 'Fade Up', 'fade_in' => 'Fade In', 'slide_left' => 'Slide from Right', 'slide_right' => 'Slide from Left',
    'zoom_in' => 'Zoom In', 'flip_up' => 'Flip Up', 'bounce_in' => 'Bounce In', 'blur_in' => 'Blur In',
    'rotate_in' => 'Rotate In', 'cascade' => 'Cascade (staggered)',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Settings | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 860px; margin: 0 auto; padding: 30px 24px 60px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 6px; }
        .sub { color: rgba(255,255,255,0.45); font-size: 13.5px; margin-bottom: 26px; }
        .flash { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: #34d399; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .section-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 24px; margin-bottom: 20px; }
        .section-card h2 { font-size: 15px; color: #d4af37; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .section-card .sec-hint { font-size: 12px; color: rgba(255,255,255,0.4); margin-bottom: 18px; }

        .slot-card { background: rgba(255,255,255,0.015); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; padding: 18px; margin-bottom: 14px; }
        .slot-card h3 { font-size: 13.5px; margin-bottom: 4px; }
        .slot-card .hint { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-bottom: 12px; line-height: 1.5; }
        .slot-preview { width: 100%; height: 120px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.08); }
        .slot-empty { width: 100%; height: 120px; border-radius: 10px; margin-bottom: 10px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.25); font-size: 12px; text-align: center; padding: 10px; }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 12px; text-align: center; cursor: pointer; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 12px; color: rgba(255,255,255,0.5); }

        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .theme-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s ease; }
        .theme-opt:hover { border-color: rgba(212,175,55,0.3); }
        .theme-opt.selected { border-color: #d4af37; }
        .theme-opt input { display: none; }
        .theme-swatch-row { display: flex; gap: 6px; margin-bottom: 10px; height: 36px; border-radius: 8px; overflow: hidden; }
        .theme-swatch-row span { flex: 1; }
        .theme-opt-label { font-size: 13.5px; font-weight: 700; color: white; }
        .theme-opt-desc { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-top: 2px; }

        .anim-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .anim-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 8px; text-align: center; cursor: pointer; transition: all 0.2s ease; font-size: 11.5px; color: rgba(255,255,255,0.6); }
        .anim-opt:hover { border-color: rgba(212,175,55,0.3); color: white; }
        .anim-opt.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); color: white; }
        .anim-opt input { display: none; }
        .anim-opt i { display: block; font-size: 16px; margin-bottom: 8px; color: #d4af37; }
        @media (max-width: 700px) { .anim-grid { grid-template-columns: repeat(3, 1fr); } .theme-grid { grid-template-columns: 1fr; } }

        .btn-save { background: #d4af37; color: #0a0f1e; padding: 14px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; margin-top: 6px; }
        .btn-save:hover { background: #b8922e; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <h1>Customer Panel Theme</h1>
    <div class="sub">Controls the look of the entire customer-facing panel -- Dashboard, My Bookings, History, and Payments all update together.</div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="flash"><i class="fas fa-circle-check"></i> Saved. The whole customer panel updates immediately.</div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div class="section-card">
            <h2>Color Theme</h2>
            <div class="sec-hint">Pick one -- applies site-wide.</div>
            <div class="theme-grid">
                <?php foreach ($theme_options as $key => $t): ?>
                <label class="theme-opt <?php echo $current_theme === $key ? 'selected' : ''; ?>">
                    <input type="radio" name="theme_style" value="<?php echo $key; ?>" <?php echo $current_theme === $key ? 'checked' : ''; ?> onchange="document.querySelectorAll('.theme-opt').forEach(e=>e.classList.remove('selected')); this.closest('.theme-opt').classList.add('selected');">
                    <div class="theme-swatch-row">
                        <span style="background:<?php echo $t['bg']; ?>;"></span>
                        <span style="background:<?php echo $t['accent']; ?>;"></span>
                        <span style="background:<?php echo $t['text']; ?>;"></span>
                    </div>
                    <div class="theme-opt-label"><?php echo $t['label']; ?></div>
                    <div class="theme-opt-desc"><?php echo $t['desc']; ?></div>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-card">
            <h2>Entrance Animation</h2>
            <div class="sec-hint">How page elements animate in when a customer opens a page.</div>
            <div class="anim-grid">
                <?php foreach ($animation_options as $key => $label): ?>
                <label class="anim-opt <?php echo $current_animation === $key ? 'selected' : ''; ?>">
                    <input type="radio" name="animation_style" value="<?php echo $key; ?>" <?php echo $current_animation === $key ? 'checked' : ''; ?> onchange="document.querySelectorAll('.anim-opt').forEach(e=>e.classList.remove('selected')); this.closest('.anim-opt').classList.add('selected');">
                    <i class="fas fa-sparkles"></i>
                    <?php echo $label; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="section-card">
            <h2>Background Photos</h2>
            <div class="sec-hint">Upload your own for each spot -- auto-compressed, no speed impact.</div>
            <?php foreach ($slots_info as $key => $info): ?>
            <div class="slot-card">
                <h3><?php echo htmlspecialchars($info['label']); ?></h3>
                <div class="hint"><?php echo htmlspecialchars($info['hint']); ?></div>
                <?php if (!empty($images[$key])): ?>
                    <img class="slot-preview" src="<?php echo htmlspecialchars($images[$key]); ?>" alt="" id="preview_<?php echo $key; ?>">
                <?php else: ?>
                    <img class="slot-preview" src="" alt="" id="preview_<?php echo $key; ?>" style="display:none;">
                    <div class="slot-empty" id="empty_<?php echo $key; ?>">No image uploaded yet</div>
                <?php endif; ?>
                <label class="file-drop">
                    <input type="file" name="<?php echo $key; ?>" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this, '<?php echo $key; ?>')">
                    <div class="file-drop-text" id="filetext_<?php echo $key; ?>"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
                </label>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn-save">Save All Theme Settings</button>
    </form>
</div>
<script>
function previewImage(input, key) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('preview_' + key);
        const emptyBox = document.getElementById('empty_' + key);
        img.src = e.target.result;
        img.style.display = 'block';
        if (emptyBox) emptyBox.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
    document.getElementById('filetext_' + key).innerHTML = '<i class="fas fa-check" style="color:#34d399;"></i> ' + input.files[0].name;
}
</script>
</body>
</html>