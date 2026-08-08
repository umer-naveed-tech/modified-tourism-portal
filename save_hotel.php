<?php
// save_hotel.php
//
// Backend for agent_hotel_form.php. Agent enters the FINAL customer-
// facing price -- this file subtracts the standard 70 SAR (room) / 25
// SAR (extra bed) hidden margin before writing to the database, then
// stores markup_sar=70 / extra_bed_markup=25 so calculatePrice() (in
// generic_hotel_handler.php) adds it straight back on when a customer
// views the hotel -- exactly the same convention used everywhere else
// on this site (update_seasonal_price.php etc).

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agent_manage_hotels.php');
    exit();
}

csrf_verify();

$hotel_id = (int)($_POST['hotel_id'] ?? 0);
$hotel_name = trim($_POST['hotel_name'] ?? '');
$city = ($_POST['city'] ?? '') === 'Madinah' ? 'Madinah' : 'Mecca';
$rating = max(1, min(5, (int)($_POST['rating'] ?? 3)));
$distance_meters = $_POST['distance_meters'] !== '' ? (int)$_POST['distance_meters'] : null;
$shuttle_service = ($_POST['shuttle_service'] ?? 'No') === 'Yes' ? 'Yes' : 'No';

$room_types = json_decode($_POST['room_types_json'] ?? '[]', true) ?: [];
$pricing_periods = json_decode($_POST['pricing_json'] ?? '[]', true) ?: [];

if ($hotel_name === '' || empty($room_types) || empty($pricing_periods)) {
    header('Location: agent_hotel_form.php' . ($hotel_id ? '?id=' . $hotel_id : '') . '&error=1');
    exit();
}

// ---- Image upload (optional -- keeps the existing photo if none given) ----
$image_url = null;
if (!empty($_FILES['hotel_image']) && $_FILES['hotel_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['hotel_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
        $upload_dir = __DIR__ . '/uploads/hotel_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = 'hotel-' . preg_replace('/[^a-z0-9]/i', '', strtolower($hotel_name)) . '-' . time() . '.' . $allowed[$mime];
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $image_url = 'uploads/hotel_images/' . $filename;
        }
    }
}

// ---- Room photo (ONE shared photo shown above the room list) ----
$rooms_image_url = null;
if (!empty($_FILES['rooms_image']) && $_FILES['rooms_image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['rooms_image'];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (isset($allowed[$mime]) && $file['size'] <= 5 * 1024 * 1024) {
        $upload_dir = __DIR__ . '/uploads/hotel_images/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $filename = 'rooms-' . preg_replace('/[^a-z0-9]/i', '', strtolower($hotel_name)) . '-' . time() . '.' . $allowed[$mime];
        if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            $rooms_image_url = 'uploads/hotel_images/' . $filename;
        }
    }
}

$pdo->beginTransaction();
try {
    if ($hotel_id) {
        // ---- Editing an existing hotel ----
        $stmt = $pdo->prepare("SELECT image_url, rooms_image_url FROM hotels_saudi WHERE id = ?");
        $stmt->execute([$hotel_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) throw new Exception('Hotel not found');
        if ($image_url === null) $image_url = $existing['image_url']; // keep old photo if none uploaded
        if ($rooms_image_url === null) $rooms_image_url = $existing['rooms_image_url'];

        $stmt = $pdo->prepare("UPDATE hotels_saudi SET hotel_name = ?, city = ?, rating = ?, distance_meters = ?, shuttle_service = ?, image_url = ?, rooms_image_url = ? WHERE id = ?");
        $stmt->execute([$hotel_name, $city, $rating, $distance_meters, $shuttle_service, $image_url, $rooms_image_url, $hotel_id]);

        // Simplest, safest re-sync: wipe this hotel's rooms/pricing and
        // rebuild fresh from the form -- avoids complex row-by-row
        // diffing, and hotel_room_types/hotel_seasonal_pricing only
        // ever belong to one hotel each so this can't affect anyone else.
        $pdo->prepare("DELETE FROM hotel_room_types WHERE hotel_id = ?")->execute([$hotel_id]);
        $pdo->prepare("DELETE FROM hotel_seasonal_pricing WHERE hotel_id = ?")->execute([$hotel_id]);
    } else {
        // ---- Creating a new hotel ----
        $stmt = $pdo->prepare("INSERT INTO hotels_saudi (hotel_name, city, rating, distance_meters, shuttle_service, image_url, rooms_image_url, min_price) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
        $stmt->execute([$hotel_name, $city, $rating, $distance_meters, $shuttle_service, $image_url, $rooms_image_url]);
        $hotel_id = (int)$pdo->lastInsertId();
    }

    // ---- Room types ----
    $room_type_ids = [];
    $stmt = $pdo->prepare("INSERT INTO hotel_room_types (hotel_id, room_type, display_name, capacity, description) VALUES (?, ?, ?, ?, ?)");
    foreach ($room_types as $rt) {
        $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($rt['code'] ?? '')));
        if ($code === '') continue;
        $stmt->execute([$hotel_id, $code, trim($rt['display_name'] ?? ''), max(1, (int)($rt['capacity'] ?? 2)), trim($rt['description'] ?? '')]);
    }

    // ---- Seasonal pricing (subtract the hidden margin before storing) ----
    // Each room can now optionally have bed-type variants -- prices come
    // in as pricingPeriods[i].prices[roomCode][bedCode], where bedCode is
    // either a real bed-type code or '_default' for a room with no
    // variants (room_type_code = the room's own code either way; room_type
    // = the bed variant's code, or the room's own code again when there
    // are no variants -- same convention used by every hand-written
    // "Swissotel-style" handler already in this codebase).
    $stmt = $pdo->prepare("
        INSERT INTO hotel_seasonal_pricing
            (hotel_id, room_type, room_type_code, is_weekend, start_date, end_date, base_price_sar, markup_sar, extra_bed_base, extra_bed_markup)
        VALUES (?, ?, ?, ?, ?, ?, ?, 70.00, ?, ?)
    ");

    $lowest_price = null;

    foreach ($pricing_periods as $period) {
        $start = $period['start_date'] ?? '';
        $end = $period['end_date'] ?? '';
        if (!$start || !$end) continue;

        $extra_bed_final = (float)($period['extra_bed'] ?? 0);
        $extra_bed_base = $extra_bed_final > 0 ? max(0, $extra_bed_final - 25) : 0;
        $extra_bed_markup = $extra_bed_final > 0 ? 25.00 : 0;

        foreach ($room_types as $rt) {
            $code = preg_replace('/[^a-z0-9_]/', '', strtolower(trim($rt['code'] ?? '')));
            if ($code === '') continue;

            $bed_variants = !empty($rt['bed_types']) ? $rt['bed_types'] : [['code' => '_default', 'name' => '']];

            foreach ($bed_variants as $bt) {
                $bed_code_raw = $bt['code'] ?? '_default';
                $room_type_for_row = ($bed_code_raw === '_default')
                    ? $code
                    : preg_replace('/[^a-z0-9_]/', '', strtolower(trim($bed_code_raw)));
                if ($room_type_for_row === '') continue;

                $prices = $period['prices'][$code][$bed_code_raw] ?? null;
                if (!$prices) continue;

                $weekday_final = (float)($prices['weekday'] ?? 0);
                $weekend_final = $period['has_weekend_split'] ? (float)($prices['weekend'] ?? 0) : $weekday_final;
                if ($weekday_final <= 0) continue;

                $weekday_base = max(0, $weekday_final - 70);
                $weekend_base = max(0, $weekend_final - 70);

                $stmt->execute([$hotel_id, $room_type_for_row, $code, 0, $start, $end, $weekday_base, $extra_bed_base, $extra_bed_markup]);
                $stmt->execute([$hotel_id, $room_type_for_row, $code, 1, $start, $end, $weekend_base, $extra_bed_base, $extra_bed_markup]);

                if ($lowest_price === null || $weekday_final < $lowest_price) $lowest_price = $weekday_final;
            }
        }
    }

    if ($lowest_price !== null) {
        $pdo->prepare("UPDATE hotels_saudi SET min_price = ? WHERE id = ?")->execute([$lowest_price, $hotel_id]);
    }

    $pdo->commit();
    header('Location: agent_manage_hotels.php?saved=1');
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('save_hotel.php error: ' . $e->getMessage());
    header('Location: agent_hotel_form.php' . ($hotel_id ? '?id=' . $hotel_id : '') . '&error=1');
    exit();
}