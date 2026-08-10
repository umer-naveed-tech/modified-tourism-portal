<?php
// agent_theme_settings.php
//
// "User Panel View" -- walks the agent through the customer's actual
// journey, page by page (Dashboard -> Services Cards -> each Main
// Page -> each Booking Page), letting them set a background photo (or
// just a color theme with no photo) for exactly where they are in
// that journey. One combined form/save underneath (same mechanism as
// before) -- this only reorganizes how the agent navigates it.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'image_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $slots = [
        'dashboard_hero', 'service_hotel', 'service_taxi', 'service_visa',
        'page_hotel', 'page_taxi', 'page_visa', 'page_hotel_room', 'page_taxi_booking',
    ];
    $upload_dir = __DIR__ . '/uploads/theme_images/';
    $img_stmt = $pdo->prepare("
        INSERT INTO site_theme_images (setting_key, image_path) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)
    ");
    foreach ($slots as $slot) {
        if (!empty($_FILES[$slot]) && $_FILES[$slot]['error'] === UPLOAD_ERR_OK) {
            $filename = handleImageUpload($_FILES[$slot], $upload_dir, $slot, 2400, 88);
            if ($filename) {
                $img_stmt->execute([$slot, 'uploads/theme_images/' . $filename]);
            }
        }
    }

    // Removing a photo (agent checked "Remove this photo")
    if (!empty($_POST['remove_slot']) && is_array($_POST['remove_slot'])) {
        $stmt = $pdo->prepare("SELECT image_path FROM site_theme_images WHERE setting_key = ?");
        $del_stmt = $pdo->prepare("UPDATE site_theme_images SET image_path = NULL WHERE setting_key = ?");
        foreach ($_POST['remove_slot'] as $slot) {
            if (!in_array($slot, $slots)) continue;
            $stmt->execute([$slot]);
            $path = $stmt->fetchColumn();
            if ($path && file_exists(__DIR__ . '/' . $path)) @unlink(__DIR__ . '/' . $path);
            $del_stmt->execute([$slot]);
        }
    }

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

// The customer's actual click-through journey, in order -- this IS the
// slide list.
$journey = [
    ['key' => 'dashboard_hero', 'title' => 'User Dashboard', 'subtitle' => 'The very first page after a customer logs in.', 'multi' => false],
    ['key' => 'cards', 'title' => 'Services Cards', 'subtitle' => 'Shown when the customer clicks "Book a Service" -- 3 cards: Hotel, Taxi, Visa.', 'multi' => true, 'slots' => ['service_hotel' => 'Hotel Card', 'service_taxi' => 'Taxi Card', 'service_visa' => 'Visa Card']],
    ['key' => 'page_hotel', 'title' => 'Hotel Main Page', 'subtitle' => 'Opens when the customer picks "Hotels" -- the full hotel-listing page.', 'multi' => false],
    ['key' => 'page_hotel_room', 'title' => 'Hotel Room Page', 'subtitle' => 'Opens when the customer clicks into one specific hotel.', 'multi' => false],
    ['key' => 'page_taxi', 'title' => 'Taxi Main Page', 'subtitle' => 'Opens when the customer picks "Taxi".', 'multi' => false],
    ['key' => 'page_taxi_booking', 'title' => 'Taxi Booking Page', 'subtitle' => 'Opens after the customer picks a car/route -- the final booking form.', 'multi' => false],
    ['key' => 'page_visa', 'title' => 'Visa Main Page', 'subtitle' => 'Opens when the customer picks "Visa Services".', 'multi' => false],
    ['key' => 'theme', 'title' => 'Colors & Effects', 'subtitle' => 'No photo needed for this one -- just the overall color theme and animation style, used everywhere.', 'multi' => false],
];

$theme_options = [
    'elegant' => ['label' => 'Elegant', 'desc' => 'Warm cream & gold', 'bg' => '#faf7f1', 'accent' => '#c9a24b', 'text' => '#2b2620'],
    'classic_elegant' => ['label' => 'Classic Elegant', 'desc' => 'Ivory & deep navy, formal', 'bg' => '#fbfaf6', 'accent' => '#a9812c', 'text' => '#1b2536'],
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
    <title>User Panel View | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        select { background-color: rgba(255,255,255,0.03); color: white; }
        select option { background-color: #10182c; color: white; }

        .top-bar { display: flex; align-items: center; justify-content: space-between; padding: 18px 28px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .top-bar a { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        .top-bar h1 { font-family: 'Playfair Display', serif; font-size: 18px; }
        .flash { background: rgba(52,211,153,0.1); color: #34d399; padding: 10px 28px; font-size: 13px; }

        .wizard-shell { display: flex; min-height: calc(100vh - 60px); }

        /* Left "slide bar" -- click any step to jump straight to it */
        .slide-bar { width: 250px; flex-shrink: 0; border-right: 1px solid rgba(255,255,255,0.06); padding: 20px 0; overflow-y: auto; }
        .slide-item { display: flex; align-items: center; gap: 12px; padding: 13px 22px; cursor: pointer; color: rgba(255,255,255,0.5); transition: all 0.2s ease; border-left: 3px solid transparent; }
        .slide-item:hover { background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.8); }
        .slide-item.active { background: rgba(212,175,55,0.06); color: white; border-left-color: #d4af37; }
        .slide-num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .slide-item.active .slide-num { background: #d4af37; color: #0a0f1e; font-weight: 700; }
        .slide-item.has-photo .slide-num::after { content: ''; }
        .slide-title { font-size: 13px; }
        .photo-dot { width: 6px; height: 6px; border-radius: 50%; background: #34d399; margin-left: auto; flex-shrink: 0; }

        .wizard-main { flex: 1; padding: 40px 50px; max-width: 760px; }
        .slide-panel { display: none; }
        .slide-panel.active { display: block; animation: slideFade 0.4s ease; }
        @keyframes slideFade { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }

        .slide-panel h2 { font-family: 'Playfair Display', serif; font-size: 24px; margin-bottom: 6px; }
        .slide-panel .subtitle { color: rgba(255,255,255,0.45); font-size: 13.5px; margin-bottom: 28px; line-height: 1.5; }

        .upload-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 22px; margin-bottom: 18px; }
        .upload-box h3 { font-size: 13.5px; margin-bottom: 14px; color: #d4af37; }
        .slot-preview { width: 100%; height: 220px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.08); }
        .slot-empty { width: 100%; height: 220px; border-radius: 10px; margin-bottom: 12px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.25); font-size: 13px; flex-direction: column; gap: 8px; }
        .slot-empty i { font-size: 26px; opacity: 0.5; }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 14px; text-align: center; cursor: pointer; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 12.5px; color: rgba(255,255,255,0.5); }
        .remove-check { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 12px; color: #f87171; cursor: pointer; }
        .remove-check input { accent-color: #f87171; }

        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 24px; }
        .theme-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s ease; }
        .theme-opt:hover { border-color: rgba(212,175,55,0.3); }
        .theme-opt.selected { border-color: #d4af37; }
        .theme-opt input { display: none; }
        .theme-swatch-row { display: flex; gap: 6px; margin-bottom: 10px; height: 36px; border-radius: 8px; overflow: hidden; }
        .theme-swatch-row span { flex: 1; }
        .theme-opt-label { font-size: 13.5px; font-weight: 700; }
        .theme-opt-desc { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-top: 2px; }

        .anim-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .anim-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 8px; text-align: center; cursor: pointer; transition: all 0.2s ease; font-size: 11.5px; color: rgba(255,255,255,0.6); }
        .anim-opt:hover { border-color: rgba(212,175,55,0.3); color: white; }
        .anim-opt.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); color: white; }
        .anim-opt input { display: none; }
        .anim-opt i { display: block; font-size: 16px; margin-bottom: 8px; color: #d4af37; }

        .wizard-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.06); }
        .nav-btn { background: rgba(255,255,255,0.04); color: white; border: 1px solid rgba(255,255,255,0.08); padding: 12px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .nav-btn:hover { background: rgba(255,255,255,0.08); }
        .nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }
        .btn-save { background: #d4af37; color: #0a0f1e; padding: 12px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 14px; cursor: pointer; }
        .btn-save:hover { background: #b8922e; }

        @media (max-width: 800px) {
            .wizard-shell { flex-direction: column; }
            .slide-bar { width: 100%; display: flex; overflow-x: auto; padding: 10px; }
            .slide-item { flex-shrink: 0; border-left: none; border-bottom: 3px solid transparent; }
            .slide-item.active { border-left: none; border-bottom-color: #d4af37; }
            .wizard-main { padding: 24px 20px; }
            .anim-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div>
            <h1>User Panel View</h1>
        </div>
        <a href="agent_dashboard.php">← Back to Dashboard</a>
    </div>
    <?php if (isset($_GET['saved'])): ?><div class="flash"><i class="fas fa-circle-check"></i> Saved. The whole customer panel updates immediately.</div><?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="wizardForm">
        <?php echo csrf_field(); ?>
        <div class="wizard-shell">
            <div class="slide-bar" id="slideBar">
                <?php foreach ($journey as $i => $step):
                    $hasPhoto = false;
                    if ($step['multi']) {
                        foreach ($step['slots'] as $k => $l) if (!empty($images[$k])) $hasPhoto = true;
                    } elseif ($step['key'] !== 'theme') {
                        $hasPhoto = !empty($images[$step['key']]);
                    }
                ?>
                <div class="slide-item <?php echo $i === 0 ? 'active' : ''; ?>" data-slide="<?php echo $i; ?>" onclick="goToSlide(<?php echo $i; ?>)">
                    <div class="slide-num"><?php echo $i + 1; ?></div>
                    <div class="slide-title"><?php echo htmlspecialchars($step['title']); ?></div>
                    <?php if ($hasPhoto): ?><div class="photo-dot" title="Photo set"></div><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="wizard-main">
                <?php foreach ($journey as $i => $step): ?>
                <div class="slide-panel <?php echo $i === 0 ? 'active' : ''; ?>" data-panel="<?php echo $i; ?>">
                    <h2><?php echo htmlspecialchars($step['title']); ?></h2>
                    <div class="subtitle"><?php echo htmlspecialchars($step['subtitle']); ?></div>

                    <?php if ($step['key'] === 'theme'): ?>
                        <div class="upload-box">
                            <h3>Color Theme</h3>
                            <div class="theme-grid">
                                <?php foreach ($theme_options as $key => $t): ?>
                                <label class="theme-opt <?php echo $current_theme === $key ? 'selected' : ''; ?>">
                                    <input type="radio" name="theme_style" value="<?php echo $key; ?>" <?php echo $current_theme === $key ? 'checked' : ''; ?> onchange="document.querySelectorAll('.theme-opt').forEach(e=>e.classList.remove('selected')); this.closest('.theme-opt').classList.add('selected');">
                                    <div class="theme-swatch-row"><span style="background:<?php echo $t['bg']; ?>;"></span><span style="background:<?php echo $t['accent']; ?>;"></span><span style="background:<?php echo $t['text']; ?>;"></span></div>
                                    <div class="theme-opt-label"><?php echo $t['label']; ?></div>
                                    <div class="theme-opt-desc"><?php echo $t['desc']; ?></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="upload-box">
                            <h3>Entrance Animation</h3>
                            <div class="anim-grid">
                                <?php foreach ($animation_options as $key => $label): ?>
                                <label class="anim-opt <?php echo $current_animation === $key ? 'selected' : ''; ?>">
                                    <input type="radio" name="animation_style" value="<?php echo $key; ?>" <?php echo $current_animation === $key ? 'checked' : ''; ?> onchange="document.querySelectorAll('.anim-opt').forEach(e=>e.classList.remove('selected')); this.closest('.anim-opt').classList.add('selected');">
                                    <i class="fas fa-sparkles"></i><?php echo $label; ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($step['multi']): ?>
                        <?php foreach ($step['slots'] as $slotKey => $slotLabel): ?>
                        <div class="upload-box">
                            <h3><?php echo htmlspecialchars($slotLabel); ?></h3>
                            <?php if (!empty($images[$slotKey])): ?>
                                <img class="slot-preview" src="<?php echo htmlspecialchars($images[$slotKey]); ?>" id="preview_<?php echo $slotKey; ?>">
                                <label class="remove-check"><input type="checkbox" name="remove_slot[]" value="<?php echo $slotKey; ?>"> Remove this photo</label>
                            <?php else: ?>
                                <img class="slot-preview" src="" style="display:none;" id="preview_<?php echo $slotKey; ?>">
                                <div class="slot-empty" id="empty_<?php echo $slotKey; ?>"><i class="fas fa-image"></i>No photo yet -- default look shown until you add one</div>
                            <?php endif; ?>
                            <label class="file-drop">
                                <input type="file" name="<?php echo $slotKey; ?>" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this, '<?php echo $slotKey; ?>')">
                                <div class="file-drop-text" id="filetext_<?php echo $slotKey; ?>"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="upload-box">
                            <?php if (!empty($images[$step['key']])): ?>
                                <img class="slot-preview" src="<?php echo htmlspecialchars($images[$step['key']]); ?>" id="preview_<?php echo $step['key']; ?>">
                                <label class="remove-check"><input type="checkbox" name="remove_slot[]" value="<?php echo $step['key']; ?>"> Remove this photo</label>
                            <?php else: ?>
                                <img class="slot-preview" src="" style="display:none;" id="preview_<?php echo $step['key']; ?>">
                                <div class="slot-empty" id="empty_<?php echo $step['key']; ?>"><i class="fas fa-image"></i>No photo yet -- default look shown until you add one</div>
                            <?php endif; ?>
                            <label class="file-drop">
                                <input type="file" name="<?php echo $step['key']; ?>" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this, '<?php echo $step['key']; ?>')">
                                <div class="file-drop-text" id="filetext_<?php echo $step['key']; ?>"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
                            </label>
                        </div>
                        <div class="subtitle" style="margin-top:-8px; margin-bottom:0;">Don't want a photo here? No problem -- leave this empty and the color theme (last step) is used instead.</div>
                    <?php endif; ?>

                    <div class="wizard-nav">
                        <button type="button" class="nav-btn" onclick="goToSlide(<?php echo $i - 1; ?>)" <?php echo $i === 0 ? 'disabled' : ''; ?>>← Previous</button>
                        <?php if ($i === count($journey) - 1): ?>
                            <button type="submit" class="btn-save">Save All Changes</button>
                        <?php else: ?>
                            <button type="button" class="nav-btn" onclick="goToSlide(<?php echo $i + 1; ?>)" style="background:rgba(212,175,55,0.1); color:#d4af37; border-color:rgba(212,175,55,0.2);">Next →</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </form>

<script>
function goToSlide(i) {
    if (i < 0 || i >= <?php echo count($journey); ?>) return;
    document.querySelectorAll('.slide-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.slide-panel').forEach(el => el.classList.remove('active'));
    document.querySelector('.slide-item[data-slide="' + i + '"]').classList.add('active');
    document.querySelector('.slide-panel[data-panel="' + i + '"]').classList.add('active');
    document.querySelector('.wizard-main').scrollTo(0, 0);
}

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