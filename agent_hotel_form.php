<?php
// agent_hotel_form.php
//
// Add or Edit a hotel. Room types and seasonal pricing periods are
// fully dynamic (agent can add/remove as many as needed) -- built as
// JSON in JS, parsed and written to hotel_room_types /
// hotel_seasonal_pricing by save_hotel.php on submit.
//
// IMPORTANT: the agent types the FINAL price the customer should see
// (matching the "Room Price" convention already used everywhere else
// on this site, e.g. update_seasonal_price.php's hidden-markup UI) --
// save_hotel.php subtracts the standard 70/25 SAR margin automatically
// before writing to the database, exactly like the rest of the site.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'gallery_fonts.php';
require_once 'gallery_renderer.php';

$hotel_id = (int)($_GET['id'] ?? 0);
$hotel = null;
$room_types = [];
$pricing_periods = [];
$using_legacy_data = false;

if ($hotel_id) {
    $stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE id = ?");
    $stmt->execute([$hotel_id]);
    $hotel = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$hotel) {
        header('Location: agent_manage_hotels.php');
        exit();
    }

    $stmt = $pdo->prepare("SELECT room_type, display_name, capacity, description FROM hotel_room_types WHERE hotel_id = ? ORDER BY id");
    $stmt->execute([$hotel_id]);
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($room_types) > 0) {
        // Reconstruct pricing periods from the raw rows -- group by
        // (start_date, end_date), each holding every room's weekday/
        // weekend + extra bed price for that period, converted back to
        // the FINAL (agent-facing) price by adding the stored markup
        // back on. Prices are keyed as [room_type_code][bed_variant] --
        // bed_variant is '_default' for a plain room (room_type equals
        // room_type_code, no real variant), or the actual bed-type code
        // when the room has real variants (e.g. City View / Haram View).
        $stmt = $pdo->prepare("SELECT * FROM hotel_seasonal_pricing WHERE hotel_id = ? ORDER BY start_date, room_type_code, room_type, is_weekend");
        $stmt->execute([$hotel_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $r) {
            $key = $r['start_date'] . '|' . $r['end_date'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['start_date' => $r['start_date'], 'end_date' => $r['end_date'], 'prices' => [], 'extra_bed' => 0, 'has_weekend_split' => false];
            }
            $code = $r['room_type_code'];
            $bed_key = ($r['room_type'] === $code) ? '_default' : $r['room_type'];
            if (!isset($grouped[$key]['prices'][$code])) $grouped[$key]['prices'][$code] = [];
            if (!isset($grouped[$key]['prices'][$code][$bed_key])) {
                $grouped[$key]['prices'][$code][$bed_key] = ['weekday' => 0, 'weekend' => 0];
            }
            $final_price = $r['base_price_sar'] + $r['markup_sar'];
            if ($r['is_weekend'] == 1) {
                $grouped[$key]['prices'][$code][$bed_key]['weekend'] = $final_price;
            } else {
                $grouped[$key]['prices'][$code][$bed_key]['weekday'] = $final_price;
            }
            if ($r['extra_bed_base'] > 0) {
                $grouped[$key]['extra_bed'] = $r['extra_bed_base'] + $r['extra_bed_markup'];
            }
        }
        foreach ($grouped as &$g) {
            foreach ($g['prices'] as $variants) {
                foreach ($variants as $p) {
                    if ($p['weekday'] != $p['weekend']) { $g['has_weekend_split'] = true; break 2; }
                }
            }
        }
        $pricing_periods = array_values($grouped);
    } else {
        // FIX: some hotels (e.g. Marriot Jabal Omer, Shaza Al Wasam) were
        // set up before this form existed, using the older `hotel_rooms`
        // table (a simple room_type + flat price_per_night_sar, no
        // seasonal periods). Their data was never missing -- this form
        // just never looked at that table, so it appeared empty here
        // even though the hotel works fine for customers. Bridge it in:
        // pre-fill the form from hotel_rooms as ONE wide "always" period,
        // so the agent can see and edit their real existing prices
        // instead of starting from a blank form.
        $stmt = $pdo->prepare("SELECT * FROM hotel_rooms WHERE hotel_id = ?");
        $stmt->execute([$hotel_id]);
        $legacy_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($legacy_rooms) > 0) {
            $using_legacy_data = true;
            $prices = [];
            foreach ($legacy_rooms as $lr) {
                $room_type_raw = $lr['room_type'] ?? ('Room');
                $code = preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(' ', '_', $room_type_raw)));
                if ($code === '') continue;
                $price = (float)($lr['price_per_night_sar'] ?? 0);

                $room_types[] = [
                    'room_type' => $code,
                    'display_name' => $room_type_raw,
                    'capacity' => (int)($lr['capacity'] ?? 2),
                    'description' => $lr['description'] ?? '',
                ];
                $prices[$code] = ['_default' => ['weekday' => $price, 'weekend' => $price]];
            }
            if (!empty($prices)) {
                $pricing_periods = [[
                    'start_date' => date('Y-m-d'),
                    'end_date' => date('Y-m-d', strtotime('+1 year')),
                    'has_weekend_split' => false,
                    'extra_bed' => 0,
                    'prices' => $prices,
                ]];
            }
        }
    }
}

$is_edit = $hotel_id > 0;

// ---- Gallery (images + layout/theme settings, if any exist yet) ----
$gallery_settings = ['layout' => 'grid2', 'bg_color' => '#0a0f1e', 'theme' => 'custom', 'font_family' => 'Inter'];
$gallery_images = [];
if ($hotel_id) {
    $stmt = $pdo->prepare("SELECT layout, bg_color, theme, font_family FROM hotel_galleries WHERE hotel_id = ?");
    $stmt->execute([$hotel_id]);
    $g = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($g) $gallery_settings = $g;

    $stmt = $pdo->prepare("SELECT id, image_path, caption FROM hotel_gallery_images WHERE hotel_id = ? ORDER BY sort_order, id");
    $stmt->execute([$hotel_id]);
    $gallery_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$gallery_layouts = [
    'grid2' => '2-Column Grid', 'grid3' => '3-Column Grid', 'grid4' => '4-Column Grid',
    'masonry' => 'Masonry', 'carousel' => 'Scrolling Carousel', 'hero' => 'Hero + Thumbnails',
    'mosaic' => 'Mosaic', 'stack' => 'Full-Width Stack', 'polaroid' => 'Polaroid Style', 'split' => 'Split Rows',
    'signature5' => '5-Photo Showcase', 'cascade' => 'Cascade',
];
$theme_presets = galleryThemePresets();
$theme_labels = galleryThemeLabels();
$font_choices = array_keys(galleryFontChoices());
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Hotel' : 'Add New Hotel'; ?> | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        /* 🔴 FIX: native <select> dropdown options were showing white
           background with unreadable text -- browsers render the
           OPEN dropdown list of an <option> separately from the closed
           select box, and mostly ignore the select's own background/
           color for it unless the <option> elements are styled
           directly. This fixes it everywhere on this page. */
        select { background-color: rgba(255,255,255,0.03); color: white; }
        select option { background-color: #10182c; color: white; }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; padding-bottom: 60px; }
        .container { max-width: 900px; margin: 0 auto; padding: 30px 24px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 24px; }
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 26px; margin-bottom: 20px; }
        .card h3 { font-size: 15px; color: #d4af37; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        .row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
        .field { flex: 1; min-width: 160px; }
        .field label { display: block; font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
        .field input, .field select { width: 100%; padding: 11px 13px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; font-family: inherit; font-size: 13.5px; }
        .field input:focus, .field select:focus { outline: none; border-color: #d4af37; }

        .room-row, .period-block { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 10px; position: relative; overflow: hidden; }
        .period-block.expanded { border-color: rgba(212,175,55,0.15); }

        /* NEW: condensed, spreadsheet-style pricing period -- collapsed
           header row by default, expands to a compact table instead of
           a full field-block repeated per room. */
        .period-header { display: flex; justify-content: space-between; align-items: center; padding: 13px 16px; cursor: pointer; transition: background 0.15s ease; }
        .period-header:hover { background: rgba(255,255,255,0.02); }
        .period-header-title { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: white; font-weight: 500; }
        .period-badge { font-size: 10.5px; color: #d4af37; background: rgba(212,175,55,0.1); padding: 2px 8px; border-radius: 20px; font-weight: 600; }
        .period-body { padding: 4px 16px 16px; border-top: 1px solid rgba(255,255,255,0.05); }
        .period-header .btn-remove { position: static; }
        .weekend-toggle-inline { display: flex; align-items: center; gap: 7px; font-size: 12px; color: rgba(255,255,255,0.55); white-space: nowrap; cursor: pointer; font-weight: 400; }
        .weekend-toggle-inline input { accent-color: #d4af37; }
        .price-table { width: 100%; border-collapse: collapse; margin-top: 12px; font-size: 13px; }
        .price-table th { text-align: left; padding: 6px 8px; font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.3px; color: rgba(255,255,255,0.35); border-bottom: 1px solid rgba(255,255,255,0.06); }
        .price-table td { padding: 5px 8px; border-bottom: 1px solid rgba(255,255,255,0.03); }
        .price-table tr:last-child td { border-bottom: none; }
        .pt-room-name { color: rgba(255,255,255,0.75); font-size: 12.5px; }
        .price-table input { width: 100px; padding: 7px 9px; font-size: 13px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 6px; color: white; font-family: inherit; }
        .price-table input:focus { outline: none; border-color: #d4af37; }
        .period-block { padding: 0; }
        .room-row-header { display: flex; justify-content: space-between; align-items: center; padding: 13px 16px; cursor: pointer; transition: background 0.15s ease; }
        .room-row-header:hover { background: rgba(255,255,255,0.02); }
        .room-row-title { display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: white; font-weight: 500; }
        .room-row-summary { font-size: 11.5px; color: rgba(255,255,255,0.35); font-weight: 400; }
        .room-row-body { padding: 4px 16px 16px; border-top: 1px solid rgba(255,255,255,0.05); }
        .room-row.expanded { border-color: rgba(212,175,55,0.15); }
        .room-row .btn-remove { position: static; }

        /* NEW: Hotel Gallery -- layout picker + existing image thumbnails */
        .layout-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-top: 6px; }
        .layout-option { border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px; cursor: pointer; text-align: center; transition: all 0.2s ease; }
        .layout-option:hover { border-color: rgba(212,175,55,0.25); }
        .layout-option.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); }
        .layout-option input { display: none; }
        .layout-preview { height: 40px; display: grid; gap: 3px; margin-bottom: 6px; }
        .layout-preview span { background: rgba(212,175,55,0.4); border-radius: 2px; }
        .layout-preview-grid2 { grid-template-columns: 1fr 1fr; }
        .layout-preview-grid3 { grid-template-columns: 1fr 1fr 1fr; }
        .layout-preview-grid4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
        .layout-preview-masonry { grid-template-columns: 1fr 1fr; }
        .layout-preview-masonry span:first-child { grid-row: span 2; }
        .layout-preview-carousel { grid-template-columns: 1fr 1fr 1fr; grid-auto-flow: column; }
        .layout-preview-hero { grid-template-rows: 2fr 1fr; }
        .layout-preview-hero span:first-child { grid-column: span 3; }
        .layout-preview-mosaic { grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; }
        .layout-preview-mosaic span:first-child { grid-row: span 2; }
        .layout-preview-stack { grid-template-rows: 1fr 1fr 1fr; }
        .layout-preview-polaroid { grid-template-columns: 1fr 1fr 1fr; }
        .layout-preview-polaroid span { transform: rotate(-3deg); }
        .layout-preview-split { grid-template-columns: 1fr 1fr; }
        .layout-preview-signature5 { grid-template-rows: 2fr 1fr; }
        .layout-preview-signature5 span:first-child { grid-column: span 3; }
        .layout-preview-cascade { grid-template-columns: 1fr 1fr; }
        .layout-preview-cascade span:nth-child(2) { margin-top: 8px; margin-left: 8px; }
        .layout-name { font-size: 10.5px; color: rgba(255,255,255,0.5); }
        .gallery-existing { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
        .gallery-existing-item { display: block; text-align: center; }
        .gallery-existing-item img { width: 80px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,0.08); }
        .gallery-existing-item span { display: block; font-size: 10.5px; color: #f87171; margin-top: 4px; cursor: pointer; }
        @media (max-width: 700px) { .layout-grid { grid-template-columns: repeat(3, 1fr); } }

        /* NEW: gallery file-picker feedback + layout checkmark + color swatch */
        .gallery-card { border-color: rgba(212,175,55,0.1); }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 20px; text-align: center; cursor: pointer; transition: all 0.2s ease; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); background: rgba(212,175,55,0.03); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 13px; color: rgba(255,255,255,0.5); }
        .file-drop-text i { margin-right: 6px; color: #d4af37; }
        .layout-option { position: relative; }
        .layout-check { position: absolute; top: 6px; right: 6px; color: #d4af37; font-size: 15px; opacity: 0; transition: opacity 0.15s ease; }
        .layout-option.selected .layout-check { opacity: 1; }
        .color-field { display: flex; align-items: center; gap: 10px; }
        .color-field input[type="color"] { width: 44px; height: 44px; padding: 3px; border-radius: 8px; cursor: pointer; border: 1px solid rgba(255,255,255,0.08); background: transparent; }
        .color-field span { font-size: 12.5px; color: rgba(255,255,255,0.5); font-family: monospace; }
        .theme-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 6px; }
        .theme-option { border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; padding: 10px; cursor: pointer; text-align: center; transition: all 0.2s ease; }
        .theme-option:hover { border-color: rgba(212,175,55,0.25); }
        .theme-option.selected { border-color: #d4af37; background: rgba(212,175,55,0.06); }
        .theme-option input { display: none; }
        .theme-swatch { height: 40px; border-radius: 8px; margin-bottom: 6px; border: 1px solid rgba(255,255,255,0.1); }
        @media (max-width: 700px) { .theme-grid { grid-template-columns: repeat(3, 1fr); } }
        .gallery-flash { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; display: flex; align-items: center; gap: 8px; }
        .gallery-flash-success { background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); color: #34d399; }
        .gallery-flash-error { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #f87171; }
        .btn-remove { position: absolute; top: 12px; right: 12px; background: rgba(239,68,68,0.1); color: #f87171; border: none; width: 26px; height: 26px; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .btn-remove:hover { background: #dc2626; color: white; }
        .btn-add-row { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px dashed rgba(212,175,55,0.3); padding: 10px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; font-family: inherit; width: 100%; margin-top: 6px; }
        .btn-add-row:hover { background: rgba(212,175,55,0.18); }

        .price-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px; }
        .price-grid .field-sm label { font-size: 11px; color: rgba(255,255,255,0.4); }
        .price-grid .field-sm input { padding: 8px 10px; font-size: 13px; }
        .weekend-toggle { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: rgba(255,255,255,0.6); margin: 10px 0; }
        .weekend-toggle input { accent-color: #d4af37; }
        .room-price-label { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 12px; margin-bottom: 4px; font-weight: 600; }

        .btn-save { background: #d4af37; color: #0a0f1e; padding: 14px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; }
        .btn-save:hover { background: #b8922e; }
        .error-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #f87171; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; }
        .img-preview { width: 100px; height: 70px; object-fit: cover; border-radius: 8px; margin-top: 8px; border: 1px solid rgba(255,255,255,0.08); }
        .hint { font-size: 11.5px; color: rgba(255,255,255,0.35); margin-top: 4px; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_manage_hotels.php" class="btn-back">← Back to Manage Hotels</a>
    <h1><?php echo $is_edit ? 'Edit Hotel' : 'Add New Hotel'; ?></h1>

    <div id="errorBox" class="error-box" style="display:none;"></div>

    <form method="POST" action="save_hotel.php" enctype="multipart/form-data" id="hotelForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="hotel_id" value="<?php echo $hotel_id; ?>">
        <input type="hidden" name="room_types_json" id="roomTypesJson">
        <input type="hidden" name="pricing_json" id="pricingJson">

        <div class="card">
            <h3>Hotel Details</h3>
            <div class="row">
                <div class="field" style="flex:2;">
                    <label>Hotel Name</label>
                    <input type="text" name="hotel_name" required value="<?php echo htmlspecialchars($hotel['hotel_name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>City</label>
                    <select name="city" required>
                        <option value="Mecca" <?php echo ($hotel['city'] ?? '') === 'Mecca' ? 'selected' : ''; ?>>Mecca</option>
                        <option value="Madinah" <?php echo ($hotel['city'] ?? '') === 'Madinah' ? 'selected' : ''; ?>>Madinah</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="field">
                    <label>Star Rating</label>
                    <select name="rating">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <option value="<?php echo $s; ?>" <?php echo (int)($hotel['rating'] ?? 0) === $s ? 'selected' : ''; ?>><?php echo $s; ?> Star</option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Distance from Haram (meters)</label>
                    <input type="number" name="distance_meters" value="<?php echo htmlspecialchars($hotel['distance_meters'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Shuttle Service</label>
                    <select name="shuttle_service">
                        <option value="No" <?php echo ($hotel['shuttle_service'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                        <option value="Yes" <?php echo ($hotel['shuttle_service'] ?? '') === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                    </select>
                </div>
            </div>
            <div class="field">
                <label>Hotel Photo</label>
                <label class="file-drop" for="hotelImageInput">
                    <input type="file" name="hotel_image" id="hotelImageInput" accept="image/jpeg,image/png,image/webp">
                    <div class="file-drop-text" id="hotelImageText"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
                </label>
                <div class="hint">JPG, PNG, or WEBP -- max 5 MB. Leave empty to keep the current photo.</div>
                <?php if (!empty($hotel['image_url'])): ?>
                    <img class="img-preview" src="<?php echo htmlspecialchars($hotel['image_url']); ?>" alt="Current photo">
                <?php endif; ?>
            </div>
            <div class="field">
                <label>Room Photo <span style="color:rgba(255,255,255,0.35); font-weight:400;">(shown above the room list -- one photo for all room types)</span></label>
                <label class="file-drop" for="roomsImageInput">
                    <input type="file" name="rooms_image" id="roomsImageInput" accept="image/jpeg,image/png,image/webp">
                    <div class="file-drop-text" id="roomsImageText"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
                </label>
                <div class="hint">JPG, PNG, or WEBP -- max 5 MB. Leave empty to keep the current photo.</div>
                <?php if (!empty($hotel['rooms_image_url'])): ?>
                    <img class="img-preview" src="<?php echo htmlspecialchars($hotel['rooms_image_url']); ?>" alt="Current room photo">
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Room Types</h3>
            <div id="roomTypesContainer"></div>
            <button type="button" class="btn-add-row" onclick="addRoomType()"><i class="fas fa-plus"></i> Add Room Type</button>
        </div>

        <div class="card">
            <h3>Seasonal Pricing</h3>
            <div id="pricingContainer"></div>
            <button type="button" class="btn-add-row" onclick="addPricingPeriod()"><i class="fas fa-plus"></i> Add Pricing Period</button>
        </div>

        <button type="submit" class="btn-save"><?php echo $is_edit ? 'Save Changes' : 'Create Hotel'; ?></button>
    </form>

    <?php if ($is_edit): ?>
    <!-- NEW: Hotel Gallery is now its OWN form, completely separate
         from the hotel-details form above. It has its own Save button
         and its own backend (save_gallery.php) -- so it always saves
         correctly on its own, even if something else on the page has
         a validation issue. Only shown once the hotel actually exists
         (a brand-new hotel needs to be created first). -->
    <div class="card gallery-card">
        <h3>Hotel Gallery <span style="color:rgba(255,255,255,0.35); font-weight:400; text-transform:none; letter-spacing:0;">(optional -- saves independently of the details above)</span></h3>

        <?php if (isset($_GET['gallery_saved'])): ?>
            <div class="gallery-flash gallery-flash-success"><i class="fas fa-circle-check"></i> Gallery saved.</div>
        <?php elseif (isset($_GET['gallery_deleted'])): ?>
            <div class="gallery-flash gallery-flash-success"><i class="fas fa-circle-check"></i> Gallery deleted. You can start a fresh one below.</div>
        <?php elseif (isset($_GET['gallery_error'])): ?>
            <div class="gallery-flash gallery-flash-error"><i class="fas fa-circle-exclamation"></i> Something went wrong saving the gallery. Please try again.</div>
        <?php endif; ?>

        <form method="POST" action="save_gallery.php" enctype="multipart/form-data" id="galleryForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="hotel_id" value="<?php echo $hotel_id; ?>">

            <?php if (!empty($gallery_images)): ?>
            <div class="field">
                <label>Current Photos</label>
                <div class="gallery-existing">
                    <?php foreach ($gallery_images as $gi): ?>
                    <label class="gallery-existing-item">
                        <img src="<?php echo htmlspecialchars($gi['image_path']); ?>" alt="">
                        <span><input type="checkbox" name="remove_gallery_images[]" value="<?php echo (int)$gi['id']; ?>"> Remove</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="field">
                <label>Add Photos <span style="color:rgba(255,255,255,0.35); font-weight:400;">(you can select more than one)</span></label>
                <label class="file-drop" for="galleryFileInput">
                    <input type="file" name="gallery_images[]" id="galleryFileInput" accept="image/jpeg,image/png,image/webp" multiple>
                    <div class="file-drop-text" id="galleryFileText"><i class="fas fa-cloud-arrow-up"></i> Click to choose photos, or drag them here</div>
                </label>
                <div class="hint">JPG, PNG, or WEBP -- max 5 MB each.</div>
            </div>

            <div class="field">
                <label>Gallery Layout</label>
                <div class="layout-grid">
                    <?php foreach ($gallery_layouts as $lkey => $lname): ?>
                    <label class="layout-option <?php echo $gallery_settings['layout'] === $lkey ? 'selected' : ''; ?>">
                        <input type="radio" name="gallery_layout" value="<?php echo $lkey; ?>" <?php echo $gallery_settings['layout'] === $lkey ? 'checked' : ''; ?> onchange="selectLayout(this)">
                        <div class="layout-preview layout-preview-<?php echo $lkey; ?>"><span></span><span></span><span></span></div>
                        <div class="layout-name"><?php echo $lname; ?></div>
                        <div class="layout-check"><i class="fas fa-circle-check"></i></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field">
                <label>Background Theme</label>
                <div class="theme-grid">
                    <?php foreach ($theme_labels as $tkey => $tlabel): ?>
                    <label class="theme-option <?php echo $gallery_settings['theme'] === $tkey ? 'selected' : ''; ?>">
                        <input type="radio" name="gallery_theme" value="<?php echo $tkey; ?>" <?php echo $gallery_settings['theme'] === $tkey ? 'checked' : ''; ?> onchange="selectTheme(this)">
                        <div class="theme-swatch" style="background:<?php echo $tkey === 'custom' ? htmlspecialchars($gallery_settings['bg_color']) : htmlspecialchars($theme_presets[$tkey]); ?>;"></div>
                        <div class="layout-name"><?php echo $tlabel; ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="row">
                <div class="field" id="customColorField" style="<?php echo $gallery_settings['theme'] !== 'custom' ? 'display:none;' : ''; ?>">
                    <label>Custom Color <span style="color:rgba(255,255,255,0.35); font-weight:400;">(used when "Custom Color" theme is selected)</span></label>
                    <div class="color-field">
                        <input type="color" name="gallery_bg_color" id="galleryBgColor" value="<?php echo htmlspecialchars($gallery_settings['bg_color']); ?>" onchange="document.getElementById('galleryBgSwatch').textContent = this.value; document.querySelector('.theme-option input[value=custom]').closest('.theme-option').querySelector('.theme-swatch').style.background = this.value;">
                        <span id="galleryBgSwatch"><?php echo htmlspecialchars($gallery_settings['bg_color']); ?></span>
                    </div>
                </div>
                <div class="field">
                    <label>Font</label>
                    <select name="gallery_font">
                        <?php foreach ($font_choices as $f): ?>
                        <option value="<?php echo $f; ?>" <?php echo $gallery_settings['font_family'] === $f ? 'selected' : ''; ?>><?php echo $f; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn-save" style="margin-top:6px;">Save Gallery</button>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                <a href="hotel_gallery.php?hotel_id=<?php echo $hotel_id; ?>" target="_blank" class="hint" style="color:#d4af37; text-decoration:none;">Preview gallery →</a>
                <?php if (!empty($gallery_images)): ?>
                <button type="button" onclick="deleteEntireGallery()" style="background:none; border:none; color:#f87171; font-size:12.5px; cursor:pointer; font-family:inherit;">Delete Entire Gallery</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="card gallery-card">
        <h3>Hotel Gallery</h3>
        <div class="hint">Create the hotel first (button above) -- you'll be able to add gallery photos once it's saved.</div>
    </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('hotelImageInput')?.addEventListener('change', function() {
    const text = document.getElementById('hotelImageText');
    text.innerHTML = this.files.length ? '<i class="fas fa-check" style="color:#34d399;"></i> ' + this.files[0].name : '<i class="fas fa-cloud-arrow-up"></i> Click to choose a photo';
});
document.getElementById('roomsImageInput')?.addEventListener('change', function() {
    const text = document.getElementById('roomsImageText');
    text.innerHTML = this.files.length ? '<i class="fas fa-check" style="color:#34d399;"></i> ' + this.files[0].name : '<i class="fas fa-cloud-arrow-up"></i> Click to choose a photo';
});
document.getElementById('galleryFileInput')?.addEventListener('change', function() {
    const text = document.getElementById('galleryFileText');
    if (this.files.length === 0) {
        text.innerHTML = '<i class="fas fa-cloud-arrow-up"></i> Click to choose photos, or drag them here';
    } else if (this.files.length === 1) {
        text.innerHTML = '<i class="fas fa-check" style="color:#34d399;"></i> 1 photo selected: ' + this.files[0].name;
    } else {
        text.innerHTML = '<i class="fas fa-check" style="color:#34d399;"></i> ' + this.files.length + ' photos selected';
    }
});

function selectLayout(input) {
    document.querySelectorAll('.layout-option').forEach(el => el.classList.remove('selected'));
    input.closest('.layout-option').classList.add('selected');
}

function selectTheme(input) {
    document.querySelectorAll('.theme-option').forEach(el => el.classList.remove('selected'));
    input.closest('.theme-option').classList.add('selected');
    document.getElementById('customColorField').style.display = (input.value === 'custom') ? '' : 'none';
}

function deleteEntireGallery() {
    if (!confirm('Delete ALL photos and settings for this gallery? This cannot be undone -- you can start a fresh gallery afterward.')) return;

    fetch('delete_gallery.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'hotel_id=<?php echo $hotel_id; ?>&csrf_token=<?php echo urlencode(csrf_token()); ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'agent_hotel_form.php?id=<?php echo $hotel_id; ?>&gallery_deleted=1';
        } else {
            alert('Could not delete gallery: ' + (data.error || 'unknown error'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<script>
let roomTypes = <?php echo json_encode(array_map(function($r) use ($pdo, $hotel_id) {
    // Pull any bed-type variants already saved for this room category
    // (room_type_code = the room's code, room_type = each bed variant's
    // code) -- if there's exactly one and it matches the room's own
    // code, that's the "no variants" simple case, so bed_types stays empty.
    $stmt = $pdo->prepare("SELECT DISTINCT room_type FROM hotel_seasonal_pricing WHERE hotel_id = ? AND room_type_code = ? ORDER BY room_type");
    $stmt->execute([$hotel_id, $r['room_type']]);
    $variants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $bed_types = [];
    if (count($variants) > 1 || (count($variants) === 1 && $variants[0] !== $r['room_type'])) {
        foreach ($variants as $v) {
            $bed_types[] = ['code' => $v, 'name' => $v];
        }
    }
    return ['code' => $r['room_type'], 'display_name' => $r['display_name'], 'capacity' => (int)$r['capacity'], 'description' => $r['description'], 'bed_types' => $bed_types];
}, $room_types)); ?>;

let pricingPeriods = <?php echo json_encode($pricing_periods); ?>;
// Existing (already-saved) periods start collapsed -- this is the main
// space-saver for hotels with many pricing periods/room types. Newly
// added periods (addPricingPeriod()) start expanded so the agent can
// fill them in right away.
pricingPeriods.forEach(p => { if (p._expanded === undefined) p._expanded = false; });

if (roomTypes.length === 0) {
    roomTypes.push({ code: '', display_name: '', capacity: 2, description: 'Breakfast included', bed_types: [], _expanded: true });
}

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

function renderRoomTypes() {
    const container = document.getElementById('roomTypesContainer');
    container.innerHTML = '';
    roomTypes.forEach((rt, i) => {
        const div = document.createElement('div');
        div.className = 'room-row' + (rt._expanded ? ' expanded' : '');
        const bedTypesText = (rt.bed_types || []).map(b => b.name).join(', ');
        const summaryBits = [];
        if (rt.capacity) summaryBits.push(rt.capacity + ' guests');
        if (rt.bed_types && rt.bed_types.length) summaryBits.push(rt.bed_types.length + ' bed types');

        // NEW: condensed by default -- shows just the room name +
        // a quick summary in one line, click to expand and edit the
        // full details. Nothing was removed, every field below still
        // exists exactly as before -- this only changes how much is
        // visible at once, so a hotel with many room types doesn't
        // turn into one long overwhelming page.
        div.innerHTML = `
            <div class="room-row-header" onclick="toggleRoomExpand(${i})">
                <div class="room-row-title">
                    <i class="fas fa-chevron-${rt._expanded ? 'down' : 'right'}" style="font-size:11px; color:rgba(255,255,255,0.35); width:12px;"></i>
                    <span>${escAttr(rt.display_name) || 'New Room Type'}</span>
                    ${summaryBits.length ? '<span class="room-row-summary">' + summaryBits.join(' · ') + '</span>' : ''}
                </div>
                ${roomTypes.length > 1 ? '<button type="button" class="btn-remove" onclick="event.stopPropagation(); removeRoomType(' + i + ')">&times;</button>' : ''}
            </div>
            <div class="room-row-body" style="${rt._expanded ? '' : 'display:none;'}">
                <div class="row">
                    <div class="field">
                        <label>Room Type Name (shown to customer)</label>
                        <input type="text" value="${escAttr(rt.display_name)}" placeholder="e.g. Double Room" oninput="updateRoomType(${i}, 'display_name', this.value)">
                    </div>
                    <div class="field">
                        <label>Capacity (guests)</label>
                        <input type="number" min="1" value="${rt.capacity}" oninput="updateRoomType(${i}, 'capacity', this.value)">
                    </div>
                </div>
                <div class="field">
                    <label>Meal Plan (shown to customer, e.g. "Breakfast Included" or "Room Only")</label>
                    <input type="text" value="${escAttr(rt.description)}" oninput="updateRoomType(${i}, 'description', this.value)">
                </div>
                <div class="field">
                    <label>Bed Type <span style="color:rgba(255,255,255,0.35); font-weight:400;">(optional -- only if this room comes in variants, e.g. "City View, Haram View". Leave empty if not.)</span></label>
                    <input type="text" value="${escAttr(bedTypesText)}" placeholder="e.g. City View, Haram View" oninput="updateBedTypes(${i}, this.value)">
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function toggleRoomExpand(i) {
    roomTypes[i]._expanded = !roomTypes[i]._expanded;
    renderRoomTypes();
}

function escAttr(s) {
    const d = document.createElement('div');
    d.textContent = s ?? '';
    return d.innerHTML.replace(/"/g, '&quot;');
}

function updateRoomType(i, field, value) {
    roomTypes[i][field] = field === 'capacity' ? parseInt(value) || 1 : value;
    if (field === 'display_name') {
        roomTypes[i].code = slugify(value) || ('room' + i);
    }
    renderPricingPeriods(); // room-type columns in pricing table need to stay in sync
}

function updateBedTypes(i, value) {
    const names = value.split(',').map(s => s.trim()).filter(Boolean);
    roomTypes[i].bed_types = names.map(n => ({ code: slugify(n), name: n }));
    renderPricingPeriods();
}

function addRoomType() {
    roomTypes.push({ code: '', display_name: '', capacity: 2, description: 'Breakfast included', bed_types: [], _expanded: true });
    renderRoomTypes();
    renderPricingPeriods();
}

function removeRoomType(i) {
    const name = roomTypes[i].display_name || 'this room type';
    if (!confirm('Remove "' + name + '"? Its pricing will also be removed when you save.')) return;
    roomTypes.splice(i, 1);
    renderRoomTypes();
    renderPricingPeriods();
}

// Every price is stored as pricingPeriods[i].prices[roomCode][bedKey], where
// bedKey is either a real bed-type code, or '_default' when the room has
// no bed-type variants -- keeping ONE consistent shape whether or not a
// room uses bed types, instead of two different data shapes to juggle.
// NEW: condensed, spreadsheet-style layout -- one compact table per
// period (room names as rows, price as columns) instead of a full
// field-block repeated for every room. Same data, same behavior --
// just takes far less vertical space for hotels with many room types.
function renderPricingPeriods() {
    const container = document.getElementById('pricingContainer');
    container.innerHTML = '';
    pricingPeriods.forEach((p, i) => {
        const div = document.createElement('div');
        div.className = 'period-block' + (p._expanded !== false ? ' expanded' : '');

        let rowsHtml = '';
        roomTypes.forEach((rt) => {
            if (!p.prices) p.prices = {};
            if (!p.prices[rt.code]) p.prices[rt.code] = {};

            const variants = (rt.bed_types && rt.bed_types.length > 0) ? rt.bed_types : [{ code: '_default', name: '' }];
            variants.forEach(bt => {
                if (!p.prices[rt.code][bt.code]) p.prices[rt.code][bt.code] = { weekday: 0, weekend: 0 };
                const label = bt.code === '_default' ? (escAttr(rt.display_name) || '(unnamed room)') : (escAttr(rt.display_name) || '(unnamed room)') + ' — ' + escAttr(bt.name);
                rowsHtml += `
                    <tr>
                        <td class="pt-room-name">${label}</td>
                        <td><input type="number" min="0" placeholder="0" value="${p.prices[rt.code][bt.code].weekday || ''}" oninput="updatePeriodPrice(${i}, '${rt.code}', '${bt.code}', 'weekday', this.value)"></td>
                        ${p.has_weekend_split ? `<td><input type="number" min="0" placeholder="0" value="${p.prices[rt.code][bt.code].weekend || ''}" oninput="updatePeriodPrice(${i}, '${rt.code}', '${bt.code}', 'weekend', this.value)"></td>` : ''}
                    </tr>
                `;
            });
        });

        const dateLabel = (p.start_date && p.end_date) ? formatDateShort(p.start_date) + ' – ' + formatDateShort(p.end_date) : 'New period';

        div.innerHTML = `
            <div class="period-header" onclick="togglePeriodExpand(${i})">
                <div class="period-header-title">
                    <i class="fas fa-chevron-${p._expanded !== false ? 'down' : 'right'}" style="font-size:11px; color:rgba(255,255,255,0.35); width:12px;"></i>
                    <span>${dateLabel}</span>
                    ${p.has_weekend_split ? '<span class="period-badge">Weekday/Weekend</span>' : ''}
                </div>
                <button type="button" class="btn-remove" onclick="event.stopPropagation(); removePeriod(${i})">&times;</button>
            </div>
            <div class="period-body" style="${p._expanded !== false ? '' : 'display:none;'}">
                <div class="row">
                    <div class="field">
                        <label>From</label>
                        <input type="date" value="${p.start_date || ''}" oninput="updatePeriod(${i}, 'start_date', this.value)">
                    </div>
                    <div class="field">
                        <label>To</label>
                        <input type="date" value="${p.end_date || ''}" oninput="updatePeriod(${i}, 'end_date', this.value)">
                    </div>
                    <div class="field" style="flex:0 0 auto; align-self:flex-end; padding-bottom:11px;">
                        <label class="weekend-toggle-inline">
                            <input type="checkbox" ${p.has_weekend_split ? 'checked' : ''} onchange="toggleWeekend(${i}, this.checked)">
                            Different weekend price
                        </label>
                    </div>
                </div>
                <table class="price-table">
                    <thead>
                        <tr>
                            <th>Room</th>
                            <th>${p.has_weekend_split ? 'Weekday (SAR)' : 'Price (SAR)'}</th>
                            ${p.has_weekend_split ? '<th>Weekend (SAR)</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <div class="row" style="margin-top:12px;">
                    <div class="field" style="max-width:220px;">
                        <label>Extra Bed (SAR/night) <span style="color:rgba(255,255,255,0.35); font-weight:400;">-- 0 if not offered</span></label>
                        <input type="number" min="0" value="${p.extra_bed || 0}" oninput="updatePeriod(${i}, 'extra_bed', this.value)">
                    </div>
                </div>
            </div>
        `;
        container.appendChild(div);
    });
}

function togglePeriodExpand(i) {
    pricingPeriods[i]._expanded = pricingPeriods[i]._expanded === false ? true : false;
    renderPricingPeriods();
}

function formatDateShort(dateStr) {
    const d = new Date(dateStr + 'T00:00:00');
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function updatePeriod(i, field, value) {
    pricingPeriods[i][field] = field === 'extra_bed' ? (parseFloat(value) || 0) : value;
}

function updatePeriodPrice(i, roomCode, bedCode, which, value) {
    if (!pricingPeriods[i].prices[roomCode]) pricingPeriods[i].prices[roomCode] = {};
    if (!pricingPeriods[i].prices[roomCode][bedCode]) pricingPeriods[i].prices[roomCode][bedCode] = { weekday: 0, weekend: 0 };
    pricingPeriods[i].prices[roomCode][bedCode][which] = parseFloat(value) || 0;
}

function toggleWeekend(i, checked) {
    pricingPeriods[i].has_weekend_split = checked;
    renderPricingPeriods();
}

function addPricingPeriod() {
    pricingPeriods.push({ start_date: '', end_date: '', has_weekend_split: false, extra_bed: 0, prices: {}, _expanded: true });
    renderPricingPeriods();
}

function removePeriod(i) {
    if (!confirm('Remove this pricing period? This cannot be undone once you save.')) return;
    pricingPeriods.splice(i, 1);
    renderPricingPeriods();
}

document.getElementById('hotelForm').addEventListener('submit', function(e) {
    // Auto-generate room codes for any room whose name was typed but
    // code wasn't set yet (covers the very first render before any
    // oninput fired).
    roomTypes.forEach((rt, i) => { if (!rt.code && rt.display_name) rt.code = slugify(rt.display_name) || ('room' + i); });

    const errors = [];
    if (roomTypes.some(rt => !rt.display_name)) errors.push('Every room type needs a name.');
    if (pricingPeriods.length === 0) errors.push('Add at least one pricing period.');
    pricingPeriods.forEach((p, i) => {
        if (!p.start_date || !p.end_date) errors.push('Pricing period ' + (i + 1) + ' needs both dates.');
    });

    if (errors.length) {
        e.preventDefault();
        const box = document.getElementById('errorBox');
        box.innerHTML = errors.map(e => '• ' + e).join('<br>');
        box.style.display = 'block';
        window.scrollTo(0, 0);
        return;
    }

    document.getElementById('roomTypesJson').value = JSON.stringify(roomTypes);
    document.getElementById('pricingJson').value = JSON.stringify(pricingPeriods);
});

renderRoomTypes();
renderPricingPeriods();
</script>
</body>
</html>