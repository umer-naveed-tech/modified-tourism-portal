<?php
// agent_theme_settings.php
//
// "User Panel View" -- walks the agent through the customer's actual
// journey, page by page. Each slide now saves INDEPENDENTLY (its own
// "Save This Section" button, AJAX -- see save_theme_slot.php and
// save_global_theme.php) instead of one combined form, and each photo
// slide can also pick its own background color instead of a photo.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'card_frames.php';

$stmt = $pdo->query("SELECT setting_key, image_path FROM site_theme_images");
$images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$stmt = $pdo->query("SELECT setting_key, setting_value FROM site_theme_settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$current_theme = $settings['theme_style'] ?? 'elegant';
$current_animation = $settings['animation_style'] ?? 'fade_up';
$current_frame = $settings['card_frame_style'] ?? 'none';
$stmt = $pdo->query("SELECT setting_key, bg_color FROM site_theme_slot_colors");
$slot_colors = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$journey = [
    ['key' => 'dashboard_hero', 'title' => 'User Dashboard', 'subtitle' => 'The very first page after a customer logs in.', 'multi' => false],
    ['key' => 'cards', 'title' => 'Services Cards', 'subtitle' => 'Shown when the customer clicks "Book a Service" -- 3 cards: Hotel, Taxi, Visa.', 'multi' => true, 'slots' => ['service_hotel' => 'Hotel Card', 'service_taxi' => 'Taxi Card', 'service_visa' => 'Visa Card']],
    ['key' => 'page_hotel', 'title' => 'Hotel Main Page', 'subtitle' => 'Opens when the customer picks "Hotels" -- the full hotel-listing page.', 'multi' => false],
    ['key' => 'page_hotel_room', 'title' => 'Hotel Room Page', 'subtitle' => 'Opens when the customer clicks into one specific hotel.', 'multi' => false],
    ['key' => 'page_taxi', 'title' => 'Taxi Main Page', 'subtitle' => 'Opens when the customer picks "Taxi".', 'multi' => false],
    ['key' => 'page_taxi_booking', 'title' => 'Taxi Booking Page', 'subtitle' => 'Opens after the customer picks a car/route.', 'multi' => false],
    ['key' => 'page_visa', 'title' => 'Visa Main Page', 'subtitle' => 'Opens when the customer picks "Visa Services".', 'multi' => false],
    ['key' => 'theme', 'title' => 'Colors, Frames & Effects', 'subtitle' => 'Applies everywhere -- the overall color theme, card frame style, and animation.', 'multi' => false],
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
$frame_options = cardFrameOptions();

// Curated palette offered on every photo-slide as a quick alternative
// to uploading a photo.
$quick_colors = ['#faf7f1', '#fbfaf6', '#0a0f1e', '#1b2536', '#2b2416', '#3d2416', '#1e2a3d', '#241a30'];
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

        .wizard-shell { display: flex; min-height: calc(100vh - 60px); }
        .slide-bar { width: 250px; flex-shrink: 0; border-right: 1px solid rgba(255,255,255,0.06); padding: 20px 0; overflow-y: auto; }
        .slide-item { display: flex; align-items: center; gap: 12px; padding: 13px 22px; cursor: pointer; color: rgba(255,255,255,0.5); transition: all 0.2s ease; border-left: 3px solid transparent; }
        .slide-item:hover { background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.8); }
        .slide-item.active { background: rgba(212,175,55,0.06); color: white; border-left-color: #d4af37; }
        .slide-num { width: 24px; height: 24px; border-radius: 50%; background: rgba(255,255,255,0.06); display: flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }
        .slide-item.active .slide-num { background: #d4af37; color: #0a0f1e; font-weight: 700; }
        .slide-title { font-size: 13px; }
        .photo-dot { width: 6px; height: 6px; border-radius: 50%; background: #34d399; margin-left: auto; flex-shrink: 0; }

        .wizard-main { flex: 1; padding: 40px 50px; max-width: 760px; }
        .slide-panel { display: none; }
        .slide-panel.active { display: block; animation: slideFade 0.4s ease; }
        @keyframes slideFade { from { opacity: 0; transform: translateX(10px); } to { opacity: 1; transform: translateX(0); } }

        .slide-panel h2 { font-family: 'Playfair Display', serif; font-size: 24px; margin-bottom: 6px; }
        .slide-panel .subtitle { color: rgba(255,255,255,0.45); font-size: 13.5px; margin-bottom: 24px; line-height: 1.5; }

        .upload-box { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 22px; margin-bottom: 18px; }
        .upload-box h3 { font-size: 13.5px; margin-bottom: 14px; color: #d4af37; }
        .slot-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.08); }
        .slot-empty { width: 100%; height: 200px; border-radius: 10px; margin-bottom: 12px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.25); font-size: 13px; flex-direction: column; gap: 8px; }
        .slot-empty i { font-size: 26px; opacity: 0.5; }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 14px; text-align: center; cursor: pointer; margin-bottom: 14px; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 12.5px; color: rgba(255,255,255,0.5); }
        .remove-check { display: flex; align-items: center; gap: 8px; margin-bottom: 14px; font-size: 12px; color: #f87171; cursor: pointer; }
        .remove-check input { accent-color: #f87171; }

        .or-divider { text-align: center; color: rgba(255,255,255,0.3); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin: 16px 0; position: relative; }
        .or-divider::before, .or-divider::after { content: ''; position: absolute; top: 50%; width: 40%; height: 1px; background: rgba(255,255,255,0.06); }
        .or-divider::before { left: 0; } .or-divider::after { right: 0; }

        .color-swatches { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .color-swatch { width: 34px; height: 34px; border-radius: 8px; cursor: pointer; border: 2px solid transparent; transition: all 0.15s ease; position: relative; }
        .color-swatch:hover { transform: scale(1.1); }
        .color-swatch.selected { border-color: #d4af37; box-shadow: 0 0 0 2px rgba(212,175,55,0.3); }
        .color-swatch.selected::after { content: '\f00c'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 11px; color: white; text-shadow: 0 1px 3px rgba(0,0,0,0.6); }
        .color-swatch-default { display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); font-size: 9px; text-align: center; line-height: 1.2; }
        .custom-color-input { width: 34px; height: 34px; border-radius: 8px; border: none; cursor: pointer; padding: 0; }

        .save-section-btn { background: rgba(212,175,55,0.12); color: #d4af37; border: 1px solid rgba(212,175,55,0.25); padding: 11px 22px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; font-family: inherit; transition: all 0.2s ease; }
        .save-section-btn:hover { background: #d4af37; color: #0a0f1e; }
        .save-section-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .save-feedback { display: inline-flex; align-items: center; gap: 7px; font-size: 12.5px; margin-left: 14px; opacity: 0; transition: opacity 0.3s ease; }
        .save-feedback.show { opacity: 1; }
        .save-feedback.ok { color: #34d399; }
        .save-feedback.err { color: #f87171; }

        .theme-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
        .theme-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 14px; padding: 16px; cursor: pointer; transition: all 0.2s ease; }
        .theme-opt:hover { border-color: rgba(212,175,55,0.3); }
        .theme-opt.selected { border-color: #d4af37; }
        .theme-opt input { display: none; }
        .theme-swatch-row { display: flex; gap: 6px; margin-bottom: 10px; height: 36px; border-radius: 8px; overflow: hidden; }
        .theme-swatch-row span { flex: 1; }
        .theme-opt-label { font-size: 13.5px; font-weight: 700; }
        .theme-opt-desc { font-size: 11.5px; color: rgba(255,255,255,0.4); margin-top: 2px; }

        .anim-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; }
        .anim-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 14px 8px; text-align: center; cursor: pointer; transition: all 0.2s ease; font-size: 11.5px; color: rgba(255,255,255,0.6); }
        .anim-opt:hover { border-color: rgba(212,175,55,0.3); color: white; }
        .anim-opt.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); color: white; }
        .anim-opt input { display: none; }
        .anim-opt i { display: block; font-size: 16px; margin-bottom: 8px; color: #d4af37; }

        .frame-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .frame-opt { border: 2px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px; text-align: center; cursor: pointer; transition: all 0.2s ease; font-size: 10.5px; color: rgba(255,255,255,0.6); }
        .frame-opt:hover { border-color: rgba(212,175,55,0.3); color: white; }
        .frame-opt.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); color: white; }
        .frame-opt input { display: none; }
        .frame-preview { height: 40px; background: linear-gradient(135deg,#3a5170,#1e2a3d); border-radius: 6px; margin-bottom: 8px; }

        .wizard-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 30px; padding-top: 24px; border-top: 1px solid rgba(255,255,255,0.06); }
        .nav-btn { background: rgba(255,255,255,0.04); color: white; border: 1px solid rgba(255,255,255,0.08); padding: 12px 22px; border-radius: 10px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: inherit; }
        .nav-btn:hover { background: rgba(255,255,255,0.08); }
        .nav-btn:disabled { opacity: 0.3; cursor: not-allowed; }

        @media (max-width: 800px) {
            .wizard-shell { flex-direction: column; }
            .slide-bar { width: 100%; display: flex; overflow-x: auto; padding: 10px; }
            .slide-item { flex-shrink: 0; border-left: none; border-bottom: 3px solid transparent; }
            .slide-item.active { border-left: none; border-bottom-color: #d4af37; }
            .wizard-main { padding: 24px 20px; }
            .anim-grid, .frame-grid { grid-template-columns: repeat(3, 1fr); }
        }
    </style>
    <?php cardFrameCSS(); ?>
</head>
<body>
    <div class="top-bar">
        <h1>User Panel View</h1>
        <a href="agent_dashboard.php">← Back to Dashboard</a>
    </div>

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
                                <input type="radio" name="theme_style" value="<?php echo $key; ?>" <?php echo $current_theme === $key ? 'checked' : ''; ?> onchange="selectRadioCard(this, '.theme-opt')">
                                <div class="theme-swatch-row"><span style="background:<?php echo $t['bg']; ?>;"></span><span style="background:<?php echo $t['accent']; ?>;"></span><span style="background:<?php echo $t['text']; ?>;"></span></div>
                                <div class="theme-opt-label"><?php echo $t['label']; ?></div>
                                <div class="theme-opt-desc"><?php echo $t['desc']; ?></div>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <h3>Card Frame Style <span style="color:rgba(255,255,255,0.35); font-weight:400; text-transform:none;">— applies to hotel & visa listing cards</span></h3>
                        <div class="frame-grid">
                            <?php foreach ($frame_options as $key => $label): ?>
                            <label class="frame-opt <?php echo $current_frame === $key ? 'selected' : ''; ?>">
                                <input type="radio" name="card_frame_style" value="<?php echo $key; ?>" <?php echo $current_frame === $key ? 'checked' : ''; ?> onchange="selectRadioCard(this, '.frame-opt')">
                                <div class="frame-preview card-frame-<?php echo $key; ?>"></div>
                                <?php echo $label; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <h3 style="margin-top:20px;">Entrance Animation</h3>
                        <div class="anim-grid">
                            <?php foreach ($animation_options as $key => $label): ?>
                            <label class="anim-opt <?php echo $current_animation === $key ? 'selected' : ''; ?>">
                                <input type="radio" name="animation_style" value="<?php echo $key; ?>" <?php echo $current_animation === $key ? 'checked' : ''; ?> onchange="selectRadioCard(this, '.anim-opt')">
                                <i class="fas fa-sparkles"></i><?php echo $label; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="save-section-btn" onclick="saveGlobalTheme(this)">Save This Section</button>
                        <span class="save-feedback" id="feedback-theme"></span>
                    </div>
                <?php elseif ($step['multi']): ?>
                    <?php foreach ($step['slots'] as $slotKey => $slotLabel): ?>
                    <div class="upload-box">
                        <h3><?php echo htmlspecialchars($slotLabel); ?></h3>
                        <?php renderSlotUploader($slotKey, $images, $slot_colors, $quick_colors); ?>
                        <button type="button" class="save-section-btn" onclick="saveSlot('<?php echo $slotKey; ?>', this)">Save This Section</button>
                        <span class="save-feedback" id="feedback-<?php echo $slotKey; ?>"></span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="upload-box">
                        <?php renderSlotUploader($step['key'], $images, $slot_colors, $quick_colors); ?>
                        <button type="button" class="save-section-btn" onclick="saveSlot('<?php echo $step['key']; ?>', this)">Save This Section</button>
                        <span class="save-feedback" id="feedback-<?php echo $step['key']; ?>"></span>
                    </div>
                <?php endif; ?>

                <div class="wizard-nav">
                    <button type="button" class="nav-btn" onclick="goToSlide(<?php echo $i - 1; ?>)" <?php echo $i === 0 ? 'disabled' : ''; ?>>← Previous</button>
                    <?php if ($i < count($journey) - 1): ?>
                        <button type="button" class="nav-btn" onclick="goToSlide(<?php echo $i + 1; ?>)" style="background:rgba(212,175,55,0.1); color:#d4af37; border-color:rgba(212,175,55,0.2);">Next →</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

<?php
// Renders the photo-upload + color-swatch UI shared by every simple
// (non-"theme") slide -- kept as one function so every slide behaves
// identically.
function renderSlotUploader($key, $images, $slot_colors, $quick_colors) {
    ?>
    <?php if (!empty($images[$key])): ?>
        <img class="slot-preview" src="<?php echo htmlspecialchars($images[$key]); ?>" id="preview_<?php echo $key; ?>">
        <label class="remove-check"><input type="checkbox" id="remove_<?php echo $key; ?>"> Remove this photo</label>
    <?php else: ?>
        <img class="slot-preview" src="" style="display:none;" id="preview_<?php echo $key; ?>">
        <div class="slot-empty" id="empty_<?php echo $key; ?>"><i class="fas fa-image"></i>No photo yet — default look shown until you add one</div>
    <?php endif; ?>
    <label class="file-drop">
        <input type="file" id="file_<?php echo $key; ?>" accept="image/jpeg,image/png,image/webp" onchange="previewImage(this, '<?php echo $key; ?>')">
        <div class="file-drop-text" id="filetext_<?php echo $key; ?>"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
    </label>
    <div class="or-divider">or use a color instead</div>
    <div class="color-swatches" id="colors_<?php echo $key; ?>">
        <?php $current_color = $slot_colors[$key] ?? null; ?>
        <div class="color-swatch color-swatch-default <?php echo !$current_color ? 'selected' : ''; ?>" data-color="default" onclick="selectColor('<?php echo $key; ?>', 'default', this)">Default</div>
        <?php foreach ($quick_colors as $c): ?>
        <div class="color-swatch <?php echo $current_color === $c ? 'selected' : ''; ?>" style="background:<?php echo $c; ?>;" data-color="<?php echo $c; ?>" onclick="selectColor('<?php echo $key; ?>', '<?php echo $c; ?>', this)"></div>
        <?php endforeach; ?>
        <input type="color" class="custom-color-input" id="customcolor_<?php echo $key; ?>" value="<?php echo $current_color ?: '#0a0f1e'; ?>" onchange="selectColor('<?php echo $key; ?>', this.value, this)">
    </div>
    <?php
}
?>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';
const slotColorState = {};

function goToSlide(i) {
    if (i < 0 || i >= <?php echo count($journey); ?>) return;
    document.querySelectorAll('.slide-item').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.slide-panel').forEach(el => el.classList.remove('active'));
    document.querySelector('.slide-item[data-slide="' + i + '"]').classList.add('active');
    document.querySelector('.slide-panel[data-panel="' + i + '"]').classList.add('active');
    document.querySelector('.wizard-main').scrollTo(0, 0);
}

function selectRadioCard(input, groupSelector) {
    document.querySelectorAll(groupSelector).forEach(e => e.classList.remove('selected'));
    input.closest(groupSelector).classList.add('selected');
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

function selectColor(key, color, el) {
    slotColorState[key] = color;
    document.querySelectorAll('#colors_' + key + ' .color-swatch').forEach(e => e.classList.remove('selected'));
    if (el.classList.contains('color-swatch')) el.classList.add('selected');
}

function showFeedback(key, ok, msg) {
    const el = document.getElementById('feedback-' + key);
    el.className = 'save-feedback show ' + (ok ? 'ok' : 'err');
    el.innerHTML = (ok ? '<i class="fas fa-circle-check"></i> ' : '<i class="fas fa-circle-exclamation"></i> ') + msg;
    setTimeout(() => { el.classList.remove('show'); }, 4000);
}

function saveSlot(key, btn) {
    if (!confirm('Save changes to this section? This will update it for every customer immediately.')) return;

    const fileInput = document.getElementById('file_' + key);
    const removeBox = document.getElementById('remove_' + key);
    const fd = new FormData();
    fd.append('slot', key);
    fd.append('csrf_token', csrfToken);
    if (fileInput && fileInput.files[0]) fd.append('photo', fileInput.files[0]);
    if (removeBox && removeBox.checked) fd.append('remove_photo', '1');
    if (slotColorState[key] !== undefined) fd.append('bg_color', slotColorState[key]);

    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';

    fetch('save_theme_slot.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = originalText;
            if (data.success) {
                showFeedback(key, true, 'Saved — now live for customers.');
                const dot = document.querySelector('.slide-item[onclick*="' + findSlideIndexForKey(key) + '"] .photo-dot');
            } else {
                showFeedback(key, false, data.error || 'Something went wrong.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.textContent = originalText;
            showFeedback(key, false, 'Network error — please try again.');
        });
}

function findSlideIndexForKey(key) { return ''; } // sidebar dot refresh is cosmetic only; a full reload also updates it

function saveGlobalTheme(btn) {
    if (!confirm('Save these color, frame, and animation settings? This applies across the whole customer panel immediately.')) return;

    const theme = document.querySelector('input[name="theme_style"]:checked').value;
    const frame = document.querySelector('input[name="card_frame_style"]:checked').value;
    const anim = document.querySelector('input[name="animation_style"]:checked').value;

    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = 'Saving...';

    fetch('save_global_theme.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'theme_style=' + encodeURIComponent(theme) + '&card_frame_style=' + encodeURIComponent(frame) + '&animation_style=' + encodeURIComponent(anim) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = originalText;
        showFeedback('theme', data.success, data.success ? 'Saved — now live for customers.' : (data.error || 'Something went wrong.'));
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = originalText;
        showFeedback('theme', false, 'Network error — please try again.');
    });
}
</script>
</body>
</html>