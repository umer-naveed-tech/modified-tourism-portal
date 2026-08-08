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
                    'end_date' => date('Y-m-d', strtotime('+2 years')),
                    'has_weekend_split' => false,
                    'extra_bed' => 0,
                    'prices' => $prices,
                ]];
            }
        }
    }
}

$is_edit = $hotel_id > 0;
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

        .room-row, .period-block { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 16px; margin-bottom: 12px; position: relative; }
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

    <?php if ($using_legacy_data): ?>
    <div style="background:rgba(212,175,55,0.06); border:1px solid rgba(212,175,55,0.15); color:#d4af37; padding:14px 18px; border-radius:10px; margin-bottom:20px; font-size:13px; line-height:1.6;">
        This hotel's rooms were set up in an older format (no seasonal pricing). Your existing prices are shown below, filled in as one period covering the next 2 years. Nothing has changed for customers yet -- review the prices below and click Save to switch this hotel over to the newer format.
    </div>
    <?php endif; ?>

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
                <input type="file" name="hotel_image" accept="image/jpeg,image/png,image/webp">
                <div class="hint">JPG, PNG, or WEBP -- max 5 MB. Leave empty to keep the current photo.</div>
                <?php if (!empty($hotel['image_url'])): ?>
                    <img class="img-preview" src="<?php echo htmlspecialchars($hotel['image_url']); ?>" alt="Current photo">
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
            <div class="hint" style="margin-bottom:14px;">Enter the FINAL price the customer should see (your normal markup is applied automatically, same as everywhere else on the site).</div>
            <div id="pricingContainer"></div>
            <button type="button" class="btn-add-row" onclick="addPricingPeriod()"><i class="fas fa-plus"></i> Add Pricing Period</button>
        </div>

        <button type="submit" class="btn-save"><?php echo $is_edit ? 'Save Changes' : 'Create Hotel'; ?></button>
    </form>
</div>

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

if (roomTypes.length === 0) {
    roomTypes.push({ code: '', display_name: '', capacity: 2, description: 'Breakfast included', bed_types: [] });
}

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
}

function renderRoomTypes() {
    const container = document.getElementById('roomTypesContainer');
    container.innerHTML = '';
    roomTypes.forEach((rt, i) => {
        const div = document.createElement('div');
        div.className = 'room-row';
        const bedTypesText = (rt.bed_types || []).map(b => b.name).join(', ');
        div.innerHTML = `
            ${roomTypes.length > 1 ? '<button type="button" class="btn-remove" onclick="removeRoomType(' + i + ')">&times;</button>' : ''}
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
        `;
        container.appendChild(div);
    });
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
    roomTypes.push({ code: '', display_name: '', capacity: 2, description: 'Breakfast included', bed_types: [] });
    renderRoomTypes();
    renderPricingPeriods();
}

function removeRoomType(i) {
    roomTypes.splice(i, 1);
    renderRoomTypes();
    renderPricingPeriods();
}

// Every price is stored as pricingPeriods[i].prices[roomCode][bedKey], where
// bedKey is either a real bed-type code, or '_default' when the room has
// no bed-type variants -- keeping ONE consistent shape whether or not a
// room uses bed types, instead of two different data shapes to juggle.
function renderPricingPeriods() {
    const container = document.getElementById('pricingContainer');
    container.innerHTML = '';
    pricingPeriods.forEach((p, i) => {
        const div = document.createElement('div');
        div.className = 'period-block';
        let pricesHtml = '';
        roomTypes.forEach((rt) => {
            if (!p.prices) p.prices = {};
            if (!p.prices[rt.code]) p.prices[rt.code] = {};

            const variants = (rt.bed_types && rt.bed_types.length > 0) ? rt.bed_types : [{ code: '_default', name: '' }];
            variants.forEach(bt => {
                if (!p.prices[rt.code][bt.code]) p.prices[rt.code][bt.code] = { weekday: 0, weekend: 0 };
                const label = bt.code === '_default' ? (escAttr(rt.display_name) || '(unnamed room)') : (escAttr(rt.display_name) || '(unnamed room)') + ' — ' + escAttr(bt.name);
                pricesHtml += `
                    <div class="room-price-label">${label}</div>
                    <div class="price-grid">
                        <div class="field-sm">
                            <label>${p.has_weekend_split ? 'Weekday Price (SAR)' : 'Price (SAR)'}</label>
                            <input type="number" min="0" value="${p.prices[rt.code][bt.code].weekday || ''}" oninput="updatePeriodPrice(${i}, '${rt.code}', '${bt.code}', 'weekday', this.value)">
                        </div>
                        ${p.has_weekend_split ? `
                        <div class="field-sm">
                            <label>Weekend Price (SAR)</label>
                            <input type="number" min="0" value="${p.prices[rt.code][bt.code].weekend || ''}" oninput="updatePeriodPrice(${i}, '${rt.code}', '${bt.code}', 'weekend', this.value)">
                        </div>` : ''}
                    </div>
                `;
            });
        });
        div.innerHTML = `
            <button type="button" class="btn-remove" onclick="removePeriod(${i})">&times;</button>
            <div class="row">
                <div class="field">
                    <label>From</label>
                    <input type="date" value="${p.start_date || ''}" oninput="updatePeriod(${i}, 'start_date', this.value)">
                </div>
                <div class="field">
                    <label>To</label>
                    <input type="date" value="${p.end_date || ''}" oninput="updatePeriod(${i}, 'end_date', this.value)">
                </div>
            </div>
            <div class="weekend-toggle">
                <input type="checkbox" id="wk${i}" ${p.has_weekend_split ? 'checked' : ''} onchange="toggleWeekend(${i}, this.checked)">
                <label for="wk${i}">This period has a different weekend price</label>
            </div>
            ${pricesHtml}
            <div class="room-price-label">Extra Bed (optional -- leave 0 if not offered)</div>
            <div class="price-grid" style="grid-template-columns:1fr;">
                <div class="field-sm">
                    <label>Extra Bed Price (SAR per night)</label>
                    <input type="number" min="0" value="${p.extra_bed || 0}" oninput="updatePeriod(${i}, 'extra_bed', this.value)">
                </div>
            </div>
        `;
        container.appendChild(div);
    });
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
    pricingPeriods.push({ start_date: '', end_date: '', has_weekend_split: false, extra_bed: 0, prices: {} });
    renderPricingPeriods();
}

function removePeriod(i) {
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