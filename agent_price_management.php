<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'hotel_handlers/handler_factory.php';

$section = $_GET['section'] ?? 'hotels';
$city = $_GET['city'] ?? '';
$hotel_id = $_GET['hotel_id'] ?? 0;
$room_id = $_GET['room_id'] ?? 0;
$car_id = $_GET['car_id'] ?? 0;
$meal_type = $_GET['meal_type'] ?? '';

// ============================================================
// 🔴 HAR HOTEL KA APNA HANDLER
// ============================================================
$handler = null;
if ($hotel_id > 0) {
    $handler = HotelHandlerFactory::getHandler($hotel_id);
    $handlerClass = HotelHandlerFactory::getHandlerClass($hotel_id);
}

// Get all cities with hotels
$stmt = $pdo->query("SELECT DISTINCT city FROM hotels_saudi WHERE city IS NOT NULL ORDER BY city");
$cities = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get hotels by city
$hotels = [];
if($city) {
    $stmt = $pdo->prepare("SELECT id, hotel_name, rating FROM hotels_saudi WHERE city = ? ORDER BY hotel_name");
    $stmt->execute([$city]);
    $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get rooms for a specific hotel - 🔴 HANDLER SE
$rooms = [];
$hotel_name = '';
if($hotel_id && $handler) {
    $rooms = $handler->getRooms($hotel_id);
    
    $stmt = $pdo->prepare("SELECT hotel_name FROM hotels_saudi WHERE id = ?");
    $stmt->execute([$hotel_id]);
    $h = $stmt->fetch();
    $hotel_name = $h['hotel_name'] ?? '';
}

// MOVENPICK: Get Meal Categories
$meal_categories = [];
if($hotel_id == 63) {
    $options = $handler->getBookingOptions($hotel_id);
    if (isset($options['meal_types'])) {
        $meal_categories = $options['meal_types'];
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT meal_type FROM hotel_seasonal_pricing WHERE hotel_id = ? ORDER BY meal_type");
        $stmt->execute([$hotel_id]);
        $meal_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// ============================================================
// 🔴 MAKKAH HOTEL (ID: 43) - Seasonal Pricing Fetch
// ============================================================
$seasonal_rules = [];
$seasonal_rules_room = [];
$room_type = '';
$room_capacity = 0;
$seasonal_room_label = '';

if ($room_id > 0) {
    if ($handler) {
        $room = $handler->getRoomDetails($room_id);
        if ($room) {
            $room_type = $room['room_type'] ?? '';
            $room_capacity = $room['capacity'] ?? 0;
            $seasonal_room_label = $room['display_name'] ?? $room['room_type'] ?? 'Room';
            
            // 🔴 MAKKAH HOTEL (43), FAIRMONT (145), SWISSOTEL (146) - room_type_code use karein
            if ($hotel_id == 43 || $hotel_id == FAIRMONT_HOTEL_ID || $hotel_id == SWISSOTEL_HOTEL_ID) {
                $stmt = $pdo->prepare("
                    SELECT * FROM hotel_seasonal_pricing 
                    WHERE hotel_id = ? AND room_type_code = ? 
                    ORDER BY start_date
                ");
                $stmt->execute([$hotel_id, $room_type]);
                $seasonal_rules_room = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                // Other hotels: room_type = separate, double, triple, quad
                $stmt = $pdo->prepare("
                    SELECT * FROM hotel_seasonal_pricing 
                    WHERE hotel_id = ? AND room_type = ? 
                    ORDER BY start_date
                ");
                $stmt->execute([$hotel_id, strtolower($room_type)]);
                $seasonal_rules_room = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    }
}

// MOVENPICK Seasonal Pricing (with meal type)
if ($hotel_id == 63 && $meal_type) {
    $stmt = $pdo->prepare("
        SELECT 
            room_type,
            start_date,
            end_date,
            MAX(CASE WHEN is_weekend = 0 THEN base_price_sar END) AS weekday_base,
            MAX(CASE WHEN is_weekend = 1 THEN base_price_sar END) AS weekend_base,
            MAX(CASE WHEN is_weekend = 0 THEN markup_sar END) AS weekday_markup,
            MAX(CASE WHEN is_weekend = 1 THEN markup_sar END) AS weekend_markup,
            MAX(extra_bed_base) AS extra_bed_base,
            MAX(extra_bed_markup) AS extra_bed_markup,
            MAX(is_full_board) AS is_full_board
        FROM hotel_seasonal_pricing 
        WHERE hotel_id = ? AND meal_type = ? 
        GROUP BY room_type, start_date, end_date
        ORDER BY room_type, start_date
    ");
    $stmt->execute([$hotel_id, $meal_type]);
    $seasonal_rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all cars for taxi fares
$stmt = $pdo->query("SELECT id, car_name, car_model FROM cars ORDER BY car_name");
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get fares for a specific car
$fares = [];
if($car_id) {
    $stmt = $pdo->prepare("SELECT id, from_city, to_city, price_sar FROM car_fares WHERE car_id = ? ORDER BY from_city, to_city");
    $stmt->execute([$car_id]);
    $fares = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Management | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; }
        
        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; position: sticky; top: 0; z-index: 100; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
        .navbar .container { display: flex; justify-content: space-between; align-items: center; }
        .logo { color: white; font-size: 22px; font-weight: 800; text-decoration: none; letter-spacing: -0.5px; }
        .logo span { color: #d4af37; }
        .nav-links a { color: rgba(255,255,255,0.7); text-decoration: none; margin-left: 24px; font-size: 14px; transition: all 0.3s ease; }
        .nav-links a:hover { color: #d4af37; }
        .nav-links .btn-logout { background: rgba(239,68,68,0.1); color: #f87171; padding: 8px 20px; border-radius: 8px; margin-left: 24px; transition: all 0.3s ease; }
        .nav-links .btn-logout:hover { background: #dc2626; color: white; transform: translateY(-2px); }
        
        .header { background: #0a0f1e; color: white; padding: 32px 0; border-bottom: 1px solid rgba(212, 175, 55, 0.05); }
        .header .gold-line { width: 60px; height: 3px; background: #d4af37; margin-bottom: 12px; border-radius: 2px; }
        .header h1 { font-size: 28px; font-weight: 800; }
        .header p { color: rgba(255,255,255,0.5); margin-top: 4px; font-size: 14px; }
        
        .section-tabs { display: flex; gap: 0; margin: 24px 0; background: rgba(255,255,255,0.02); border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.04); }
        .section-tab { padding: 14px 32px; font-size: 14px; font-weight: 500; color: rgba(255,255,255,0.5); text-decoration: none; transition: all 0.3s ease; border-right: 1px solid rgba(255,255,255,0.04); }
        .section-tab:hover { background: rgba(255,255,255,0.02); color: #d4af37; }
        .section-tab.active { background: rgba(212, 175, 55, 0.05); color: #d4af37; border-bottom: 2px solid #d4af37; }
        
        .management-layout { display: flex; gap: 24px; margin-top: 24px; }
        .sidebar { width: 220px; flex-shrink: 0; }
        .sidebar-card { background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.04); overflow: hidden; }
        .sidebar-title { padding: 14px 20px; background: rgba(255,255,255,0.02); font-weight: 600; font-size: 14px; color: rgba(255,255,255,0.7); border-bottom: 1px solid rgba(255,255,255,0.04); }
        .sidebar-item { display: block; padding: 12px 20px; color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; border-bottom: 1px solid rgba(255,255,255,0.02); transition: all 0.2s ease; cursor: pointer; }
        .sidebar-item:hover { background: rgba(255,255,255,0.02); color: #d4af37; }
        .sidebar-item.active { background: rgba(212, 175, 55, 0.05); color: #d4af37; font-weight: 500; border-left: 3px solid #d4af37; }
        .sidebar-item .badge { float: right; background: rgba(255,255,255,0.04); padding: 0 10px; border-radius: 12px; font-size: 11px; color: rgba(255,255,255,0.3); }
        
        .content { flex: 1; }
        .content-card { background: rgba(255,255,255,0.02); border-radius: 12px; border: 1px solid rgba(255,255,255,0.04); overflow: hidden; }
        .content-header { padding: 16px 24px; background: rgba(255,255,255,0.02); border-bottom: 1px solid rgba(255,255,255,0.04); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .content-header h3 { font-size: 16px; font-weight: 600; color: white; }
        .content-header h3 span { color: #d4af37; }
        .content-header .handler-badge { 
            background: rgba(212, 175, 55, 0.1); 
            color: #d4af37; 
            padding: 4px 14px; 
            border-radius: 50px; 
            font-size: 11px; 
            font-weight: 500;
            border: 1px solid rgba(212, 175, 55, 0.05);
        }
        .content-body { padding: 20px 24px; }
        
        .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
        .room-card { 
            display: block; 
            padding: 16px 20px; 
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 12px; 
            text-decoration: none; 
            color: rgba(255,255,255,0.8);
            font-weight: 500; 
            font-size: 14px;
            transition: all 0.3s ease;
            text-align: center;
        }
        .room-card:hover { background: rgba(212, 175, 55, 0.05); border-color: rgba(212, 175, 55, 0.1); color: #d4af37; transform: translateY(-2px); }
        .room-card .room-capacity { color: rgba(255,255,255,0.3); font-size: 12px; display: block; margin-top: 3px; }
        .room-card .seasonal-badge { display: inline-block; background: rgba(16,185,129,0.1); color: #34d399; font-size: 10px; padding: 2px 10px; border-radius: 12px; margin-top: 5px; }
        
        .meal-category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .meal-category-card {
            display: block;
            padding: 20px 24px;
            background: rgba(255,255,255,0.02);
            border: 2px solid rgba(255,255,255,0.04);
            border-radius: 12px;
            text-decoration: none;
            color: rgba(255,255,255,0.6);
            text-align: center;
            transition: all 0.3s ease;
        }
        .meal-category-card:hover {
            border-color: rgba(212, 175, 55, 0.15);
            transform: translateY(-2px);
        }
        .meal-category-card.active {
            border-color: #d4af37;
            background: rgba(212, 175, 55, 0.05);
            color: #d4af37;
        }
        .meal-category-card .meal-name {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 4px;
        }
        .meal-category-card .meal-desc {
            font-size: 12px;
            color: rgba(255,255,255,0.3);
        }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 10px 12px; font-size: 11px; font-weight: 600; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        td { padding: 10px 12px; font-size: 13px; color: rgba(255,255,255,0.7); border-bottom: 1px solid rgba(255,255,255,0.02); vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        
        .price-input { width: 100px; padding: 6px 10px; border: 1px solid rgba(255,255,255,0.06); border-radius: 6px; font-size: 13px; font-family: inherit; transition: all 0.2s ease; background: rgba(255,255,255,0.03); color: white; }
        .price-input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 3px rgba(212,175,55,0.05); }
        .price-input.updated { border-color: #10b981; background: rgba(16,185,129,0.05); }
        
        .btn-update { background: rgba(212, 175, 55, 0.1); color: #d4af37; border: 1px solid rgba(212, 175, 55, 0.05); padding: 6px 16px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.2s ease; }
        .btn-update:hover { background: #d4af37; color: #0a0f1e; transform: translateY(-2px); }
        .btn-update:disabled { opacity: 0.5; cursor: not-allowed; transform: none !important; }
        
        .btn-back { display: inline-block; padding: 8px 16px; background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.5); text-decoration: none; border-radius: 6px; font-size: 13px; transition: all 0.2s ease; }
        .btn-back:hover { background: rgba(255,255,255,0.06); color: white; }
        
        .empty-state { text-align: center; padding: 40px 20px; color: rgba(255,255,255,0.3); }
        .empty-state p { font-size: 14px; }
        
        .toast { position: fixed; bottom: 30px; right: 30px; padding: 14px 24px; border-radius: 10px; color: white; font-weight: 500; z-index: 9999; display: none; animation: slideUp 0.3s ease; font-size: 14px; }
        .toast.success { background: #10b981; }
        .toast.error { background: #ef4444; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .weekday-tag { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: 500; background: rgba(255,255,255,0.04); color: rgba(255,255,255,0.3); }
        .weekend-tag { display: inline-block; padding: 2px 10px; border-radius: 4px; font-size: 10px; font-weight: 500; background: rgba(212,175,55,0.1); color: #d4af37; }
        .no-extra { color: rgba(255,255,255,0.2); font-size: 11px; }
        
        /* ============================================================
           MAKKAH HOTEL PRICE MANAGEMENT - NO MARKUP COLUMN
           ============================================================ */
        .seasonal-rules-container {
            overflow-x: auto;
        }
        .seasonal-rules-container table {
            min-width: 600px;
        }
        /* Collapsible date-range groups -- taake lambi list default mein
           minimized rahe, agent jo period chahe wahi expand kare */
        .period-group {
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.04);
            border-radius: 12px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        .period-group summary {
            padding: 14px 18px;
            cursor: pointer;
            font-weight: 600;
            color: white;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            list-style: none;
        }
        .period-group summary::-webkit-details-marker { display: none; }
        .period-group summary::before {
            content: '▸';
            color: #d4af37;
            margin-right: 10px;
            font-size: 12px;
            display: inline-block;
            transition: transform 0.2s ease;
        }
        .period-group[open] summary::before { transform: rotate(90deg); }
        .period-group summary:hover { background: rgba(212, 175, 55, 0.04); }
        .period-group .row-count { color: rgba(255,255,255,0.3); font-size: 12px; font-weight: 400; }
        .period-group table { margin: 0; min-width: unset; width: 100%; }
        .period-group table th { background: rgba(255,255,255,0.02); }
        .room-type-code-badge {
            display: inline-block;
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            padding: 2px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
            margin-left: 8px;
        }
        .makkah-price-display {
            color: #34d399;
            font-weight: 600;
        }
        .makkah-label {
            color: rgba(255,255,255,0.3);
            font-size: 11px;
            font-weight: 400;
        }
        
        @media (max-width: 768px) {
            .management-layout { flex-direction: column; }
            .sidebar { width: 100%; }
            .section-tabs { flex-wrap: wrap; }
            .section-tab { flex: 1; text-align: center; border-right: none; border-bottom: 1px solid rgba(255,255,255,0.04); padding: 10px 14px; font-size: 12px; }
            .meal-category-grid { grid-template-columns: 1fr; }
            .price-input { width: 80px; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container">
        <a href="index.php" class="logo">Ahmed<span>Travels</span></a>
        <div class="nav-links">
            <a href="agent_dashboard.php">Dashboard</a>
            <a href="agent_price_management.php" style="color:#d4af37;">Price Management</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
</nav>

<div class="header">
    <div class="container">
        <div class="gold-line"></div>
        <h1>Price Management</h1>
        <p>Manage hotel room prices, seasonal rates, and taxi fares</p>
    </div>
</div>

<div class="container">
    <!-- Section Tabs -->
    <div class="section-tabs">
        <a href="?section=hotels" class="section-tab <?php echo $section == 'hotels' ? 'active' : ''; ?>">Hotels</a>
        <a href="?section=taxi" class="section-tab <?php echo $section == 'taxi' ? 'active' : ''; ?>">Taxi Fares</a>
    </div>

    <?php if($section == 'hotels'): ?>
    <div class="management-layout">
        <!-- Sidebar: Cities -->
        <div class="sidebar">
            <div class="sidebar-card">
                <div class="sidebar-title">Select City</div>
                <?php foreach($cities as $c): ?>
                    <a href="?section=hotels&city=<?php echo urlencode($c); ?>" class="sidebar-item <?php echo $city == $c ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($c); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Content -->
        <div class="content">
            <div class="content-card">
                <div class="content-header">
                    <h3>
                        <?php if($room_id > 0): ?>
                            <span><?php echo htmlspecialchars($hotel_name); ?></span> — <?php echo htmlspecialchars($seasonal_room_label); ?>
                            <?php if($hotel_id == 43 || $hotel_id == 44): ?>
                                <span class="room-type-code-badge"><?php echo htmlspecialchars($room_type); ?></span>
                            <?php endif; ?>
                        <?php elseif($hotel_id == 63 && $meal_type): ?>
                            <span><?php echo htmlspecialchars($hotel_name); ?></span> — <?php echo ucfirst(str_replace('board', ' Board', $meal_type)); ?>
                        <?php elseif($hotel_id == 63): ?>
                            <span><?php echo htmlspecialchars($hotel_name); ?></span> — Select Meal Plan
                        <?php elseif($hotel_id): ?>
                            <span><?php echo htmlspecialchars($hotel_name); ?></span> — Rooms
                        <?php elseif($city): ?>
                            <span><?php echo htmlspecialchars($city); ?></span> Hotels
                        <?php else: ?>
                            Select a city from the sidebar
                        <?php endif; ?>
                    </h3>
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <?php if($room_id > 0): ?>
                            <a href="?section=hotels&city=<?php echo urlencode($city); ?>&hotel_id=<?php echo $hotel_id; ?>" class="btn-back">← Back to Rooms</a>
                        <?php elseif($hotel_id == 63 && $meal_type): ?>
                            <a href="?section=hotels&city=<?php echo urlencode($city); ?>&hotel_id=<?php echo $hotel_id; ?>" class="btn-back">← Back to Meal Plans</a>
                        <?php elseif($hotel_id): ?>
                            <a href="?section=hotels&city=<?php echo urlencode($city); ?>" class="btn-back">← Back to Hotels</a>
                        <?php elseif($city): ?>
                            <a href="?section=hotels" class="btn-back">← Back to Cities</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="content-body">
                    <?php if($room_id > 0): ?>
                        <!-- ============================================================
                        🔴 SHOW SEASONAL PRICING FOR SELECTED ROOM
                        ============================================================ -->
                        <?php if(!empty($seasonal_rules_room)): ?>
                            <div class="seasonal-rules-container">
                                <?php
                                    // Rows ko date-range ke hisaab se group karo -- taake lambi
                                    // flat list ki jagah collapsible sections banein (minimize).
                                    // Har group mein us period ke saare bed-type/weekend rows hain.
                                    $periodGroups = [];
                                    foreach ($seasonal_rules_room as $rule) {
                                        $gkey = $rule['start_date'] . '_' . $rule['end_date'];
                                        if (!isset($periodGroups[$gkey])) {
                                            $periodGroups[$gkey] = [
                                                'label' => date('d M Y', strtotime($rule['start_date'])) . ' — ' . date('d M Y', strtotime($rule['end_date'])),
                                                'rows' => [],
                                            ];
                                        }
                                        $periodGroups[$gkey]['rows'][] = $rule;
                                    }
                                    $isFirstGroup = true;
                                ?>
                                <?php if($hotel_id == 43 || $hotel_id == 44): ?>
                                    <!-- ============================================================
                                    MAKKAH HOTEL (43) & MAKKAH TOWERS (44) - NO MARKUP COLUMN
                                    Booking/preview code for these two hotels only ever reads
                                    base_price_sar / extra_bed_base and NEVER adds markup_sar.
                                    So the agent must not be shown an editable Markup field here —
                                    it would silently do nothing to the price the customer pays.
                                    ============================================================ -->
                                    <?php foreach ($periodGroups as $group): ?>
                                    <details class="period-group" <?php echo $isFirstGroup ? 'open' : ''; ?>>
                                        <summary>
                                            <span><?php echo htmlspecialchars($group['label']); ?></span>
                                            <span class="row-count"><?php echo count($group['rows']); ?> rates</span>
                                        </summary>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Room Price (SAR)</th>
                                                    <th>Weekend</th>
                                                    <th>Extra Bed (SAR)</th>
                                                    <th>Meal Type</th>
                                                    <th style="width:80px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($group['rows'] as $rule): 
                                                    // Sirf base price (markup is not used by booking code for these hotels)
                                                    $room_final = $rule['base_price_sar'];
                                                    $extra_bed_final = $rule['extra_bed_base'] ?? 0;
                                                ?>
                                                <tr>
                                                    <td>
                                                        <input type="number" class="price-input" id="base-<?php echo $rule['id']; ?>" 
                                                               value="<?php echo $room_final; ?>" step="1" min="0">
                                                    </td>
                                                    <td>
                                                        <?php if($rule['is_weekend']): ?>
                                                            <span class="weekend-tag">Weekend</span>
                                                        <?php else: ?>
                                                            <span class="weekday-tag">Weekday</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <input type="number" class="price-input" id="eb-base-<?php echo $rule['id']; ?>" 
                                                               value="<?php echo $extra_bed_final; ?>" step="1" min="0" style="width:80px;">
                                                    </td>
                                                    <td>
                                                        <?php echo $rule['meal_type'] ?? 'N/A'; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn-update" onclick="updateMakkahPrice(<?php echo $rule['id']; ?>)">Update</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                    <?php $isFirstGroup = false; endforeach; ?>
                                    <div style="margin-top:12px; padding:12px 16px; background:rgba(16,185,129,0.04); border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
                                        💡 <strong>Note:</strong> This hotel has no separate markup — the Room Price above is exactly what the customer is charged per night. Changes will automatically reflect on the booking page.
                                    </div>
                                    
                                <?php else: ?>
                                    <!-- ============================================================
                                    ALL OTHER HOTELS (Marriot, Fairmont, Swissotel, future hotels) -
                                    HIDDEN MARKUP
                                    Standing rule: every hotel here gets a 70 SAR/night hidden markup
                                    that must NEVER be shown or editable on any frontend (customer OR
                                    agent). Agent edits the single "Room Price" field (= what the
                                    customer sees); the JS subtracts the fixed 70 SAR and reuses
                                    update_seasonal_price.php. Bed Type column is shown because each
                                    room category can have multiple bed configs (Double/Triple/Quad)
                                    sharing the same date range -- without it the rows look like
                                    duplicates.
                                    ============================================================ -->
                                    <?php foreach ($periodGroups as $group): ?>
                                    <details class="period-group" <?php echo $isFirstGroup ? 'open' : ''; ?>>
                                        <summary>
                                            <span><?php echo htmlspecialchars($group['label']); ?></span>
                                            <span class="row-count"><?php echo count($group['rows']); ?> rates</span>
                                        </summary>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>Bed Type</th>
                                                    <th>Room Price (SAR)</th>
                                                    <th>Weekend</th>
                                                    <th style="width:80px;">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach($group['rows'] as $rule): 
                                                    $room_final = $rule['base_price_sar'] + $rule['markup_sar'];
                                                ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars(ucfirst($rule['room_type'])); ?></strong></td>
                                                    <td>
                                                        <input type="number" class="price-input" id="fs-price-<?php echo $rule['id']; ?>" 
                                                               value="<?php echo $room_final; ?>" step="1" min="0">
                                                    </td>
                                                    <td>
                                                        <?php if($rule['is_weekend']): ?>
                                                            <span class="weekend-tag">Weekend</span>
                                                        <?php else: ?>
                                                            <span class="weekday-tag">Weekday</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button class="btn-update" onclick="updateHiddenMarkupPrice(<?php echo $rule['id']; ?>)">Update</button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </details>
                                    <?php $isFirstGroup = false; endforeach; ?>
                                    <div style="margin-top:12px; padding:12px 16px; background:rgba(16,185,129,0.04); border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
                                        💡 <strong>Note:</strong> Room Price is exactly what the customer is charged per night. Changes will automatically reflect on the booking page.
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>No seasonal pricing found for this room.</p>
                                <p style="font-size:12px; margin-top:8px; color:rgba(255,255,255,0.2);">
                                    Search value used: <strong style="color:#d4af37;"><?php echo htmlspecialchars($room_type); ?></strong>
                                    (hotel_id = <?php echo (int)$hotel_id; ?>)
                                </p>
                                <?php
                                    // Diagnostic: kitni rows total is hotel ke liye DB mein hain (kisi bhi
                                    // room_type_code ke sath) -- taake pata chale ke masla "koi data hi
                                    // nahi hai" hai ya "room_type_code mismatch" hai.
                                    $diag = $pdo->prepare("SELECT room_type_code, COUNT(*) as cnt FROM hotel_seasonal_pricing WHERE hotel_id = ? GROUP BY room_type_code");
                                    $diag->execute([$hotel_id]);
                                    $diagRows = $diag->fetchAll(PDO::FETCH_ASSOC);
                                ?>
                                <?php if(empty($diagRows)): ?>
                                    <p style="font-size:12px; margin-top:8px; color:#f87171;">
                                        Is hotel (id <?php echo (int)$hotel_id; ?>) ke liye database mein koi bhi seasonal pricing row nahi mili. Seed SQL check karo -- shayad run nahi hua ya beech mein rukh gaya tha.
                                    </p>
                                <?php else: ?>
                                    <p style="font-size:12px; margin-top:8px; color:rgba(255,255,255,0.3);">
                                        Is hotel ke liye database mein ye room_type_codes maujood hain:
                                        <?php foreach($diagRows as $d): ?>
                                            <span style="color:#34d399;"><?php echo htmlspecialchars($d['room_type_code'] ?? 'NULL'); ?> (<?php echo $d['cnt']; ?>)</span>&nbsp;&nbsp;
                                        <?php endforeach; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    <?php elseif($hotel_id == 63 && !$meal_type): ?>
                        <!-- ============================================================
                        MOVENPICK: Show 3 Meal Categories
                        ============================================================ -->
                        <div class="meal-category-grid">
                            <?php 
                            $meal_labels = [
                                'breakfast' => ['International Breakfast', 'Room + Breakfast'],
                                'halfboard' => ['International Half Board', 'Room + Breakfast + Dinner'],
                                'fullboard' => ['International Full Board', 'All Meals Included']
                            ];
                            foreach($meal_categories as $meal): 
                                $label = $meal_labels[$meal] ?? [ucfirst($meal), ''];
                            ?>
                            <a href="?section=hotels&city=<?php echo urlencode($city); ?>&hotel_id=63&meal_type=<?php echo $meal; ?>" class="meal-category-card <?php echo $meal_type == $meal ? 'active' : ''; ?>">
                                <div class="meal-name"><?php echo $label[0]; ?></div>
                                <div class="meal-desc"><?php echo $label[1]; ?></div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                        <div style="color:rgba(255,255,255,0.3); font-size:13px; text-align:center; padding:10px;">
                            Click on a meal plan to manage its seasonal rates
                        </div>

                    <?php elseif($hotel_id == 63 && $meal_type): ?>
                        <!-- ============================================================
                        MOVENPICK: Show Seasonal Rates for Selected Meal Category
                        ============================================================ -->
                        <?php if(!empty($seasonal_rules)): 
                            $is_full_board = $seasonal_rules[0]['is_full_board'] ?? 0;
                        ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Room</th>
                                        <th>Date Range</th>
                                        <th>Weekday (SAR)</th>
                                        <th>Weekend (SAR)</th>
                                        <th>Extra Bed</th>
                                        <th style="width:80px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($seasonal_rules as $rule): 
                                        $wd_final = ($rule['weekday_base'] ?? 0) + ($rule['weekday_markup'] ?? 0);
                                        $we_final = ($rule['weekend_base'] ?? 0) + ($rule['weekend_markup'] ?? 0);
                                        $eb_final = ($rule['extra_bed_base'] ?? 0) + ($rule['extra_bed_markup'] ?? 0);
                                        $has_extra = $eb_final > 0;
                                    ?>
                                    <tr id="seasonal-row-<?php echo $rule['room_type']; ?>"
                                        data-wd-markup="<?php echo (float)($rule['weekday_markup'] ?? 0); ?>"
                                        data-we-markup="<?php echo (float)($rule['weekend_markup'] ?? 0); ?>"
                                        data-eb-markup="<?php echo (float)($rule['extra_bed_markup'] ?? 0); ?>">
                                        <td><strong><?php echo ucfirst($rule['room_type']); ?></strong></td>
                                        <td>
                                            <?php echo date('d M Y', strtotime($rule['start_date'])); ?> 
                                            — <?php echo date('d M Y', strtotime($rule['end_date'])); ?>
                                        </td>
                                        <td>
                                            <input type="number" class="price-input" id="wd-<?php echo $rule['room_type']; ?>" 
                                                   value="<?php echo $wd_final; ?>" step="1" min="0">
                                        </td>
                                        <td>
                                            <input type="number" class="price-input" id="we-<?php echo $rule['room_type']; ?>" 
                                                   value="<?php echo $we_final; ?>" step="1" min="0">
                                        </td>
                                        <td>
                                            <?php if($has_extra && !$is_full_board): ?>
                                                <input type="number" class="price-input" id="eb-<?php echo $rule['room_type']; ?>" 
                                                       value="<?php echo $eb_final; ?>" step="1" min="0" style="width:80px;">
                                            <?php else: ?>
                                                <span class="no-extra">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn-update" onclick="updateMovenpickPrice('<?php echo $rule['room_type']; ?>', '<?php echo $meal_type; ?>', '<?php echo $rule['start_date']; ?>', '<?php echo $rule['end_date']; ?>', <?php echo $is_full_board ? 'true' : 'false'; ?>)">Update</button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div style="margin-top:12px; padding:12px 16px; background:rgba(16,185,129,0.04); border-radius:8px; border:1px solid rgba(16,185,129,0.06); font-size:13px; color:#34d399;">
                                💡 Changes will automatically reflect on the user booking page.
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>No seasonal rates found for <?php echo htmlspecialchars($meal_type); ?></p>
                            </div>
                        <?php endif; ?>

                    <?php elseif($hotel_id && !empty($rooms)): ?>
                        <!-- ============================================================
                        SHOW ROOMS (Handler se aayenge)
                        ============================================================ -->
                        <div class="room-grid">
                            <?php foreach($rooms as $r): ?>
                                <a href="?section=hotels&city=<?php echo urlencode($city); ?>&hotel_id=<?php echo $hotel_id; ?>&room_id=<?php echo $r['id']; ?>" class="room-card">
                                    <?php echo htmlspecialchars($r['display_name'] ?? $r['room_type']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:12px; color:rgba(255,255,255,0.3); font-size:12px; text-align:center;">
                            💡 Click on a room to manage its seasonal pricing
                        </div>

                    <?php elseif($city && !empty($hotels)): ?>
                        <!-- SHOW HOTELS -->
                        <div class="room-grid">
                            <?php foreach($hotels as $h): 
                                $hasCustomHandler = HotelHandlerFactory::hasCustomHandler($h['id']);
                            ?>
                                <a href="?section=hotels&city=<?php echo urlencode($city); ?>&hotel_id=<?php echo $h['id']; ?>" class="room-card" style="padding:18px 20px;">
                                    <?php echo htmlspecialchars($h['hotel_name']); ?>
                                    <span class="room-capacity" style="font-size:12px; color:#d4af37; margin-top:4px;"><?php echo str_repeat('★', (int)$h['rating']); ?></span>
                                    <?php if($hasCustomHandler): ?>
                                        <span class="seasonal-badge" style="background:rgba(212,175,55,0.1); color:#d4af37;">Custom</span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>

                    <?php elseif($city && empty($hotels)): ?>
                        <div class="empty-state"><p>No hotels found in <strong style="color:rgba(255,255,255,0.5);"><?php echo htmlspecialchars($city); ?></strong>.</p></div>
                    <?php else: ?>
                        <div class="empty-state"><p>Select a city from the sidebar to view hotels.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php elseif($section == 'taxi'): ?>
    <!-- ============================================================
    TAXI FARES SECTION
    ============================================================ -->
    <div class="management-layout">
        <div class="sidebar">
            <div class="sidebar-card">
                <div class="sidebar-title">Select Vehicle</div>
                <?php foreach($cars as $c): ?>
                    <a href="?section=taxi&car_id=<?php echo $c['id']; ?>" class="sidebar-item <?php echo $car_id == $c['id'] ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($c['car_name'] . ' ' . $c['car_model']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="content">
            <div class="content-card">
                <div class="content-header">
                    <h3>
                        <?php if($car_id): ?>
                            <?php 
                                $stmt = $pdo->prepare("SELECT car_name, car_model FROM cars WHERE id = ?");
                                $stmt->execute([$car_id]);
                                $c = $stmt->fetch();
                                echo htmlspecialchars($c['car_name'] . ' ' . $c['car_model']) . ' — Fares';
                            ?>
                        <?php else: ?>
                            Select a vehicle from the sidebar
                        <?php endif; ?>
                    </h3>
                    <?php if($car_id): ?>
                        <a href="?section=taxi" class="btn-back">← Back to Vehicles</a>
                    <?php endif; ?>
                </div>
                <div class="content-body">
                    <?php if($car_id && !empty($fares)): ?>
                        <table>
                            <thead>
                                <tr><th>Route</th><th>Fare (SAR)</th><th style="width:100px;">Action</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($fares as $f): ?>
                                <tr id="fare-row-<?php echo $f['id']; ?>">
                                    <td><strong><?php echo htmlspecialchars($f['from_city']); ?></strong> → <strong><?php echo htmlspecialchars($f['to_city']); ?></strong></td>
                                    <td>
                                        <input type="number" class="price-input" id="fare-price-<?php echo $f['id']; ?>" 
                                               value="<?php echo $f['price_sar']; ?>" step="1" min="0">
                                    </td>
                                    <td>
                                        <button class="btn-update" onclick="updateCarFare(<?php echo $f['id']; ?>)">Update</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php elseif($car_id && empty($fares)): ?>
                        <div class="empty-state"><p>No fares found for this vehicle.</p></div>
                    <?php else: ?>
                        <div class="empty-state"><p>Select a vehicle from the sidebar to view fares.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Toast Notification -->
<div id="toast" class="toast"></div>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.style.display = 'block';
    setTimeout(() => { toast.style.display = 'none'; }, 3000);
}

// ============================================================
// 🔴 MAKKAH HOTEL: Update Price (No Markup)
// ============================================================
function updateMakkahPrice(ruleId) {
    const base = document.getElementById('base-' + ruleId).value;
    const ebBase = document.getElementById('eb-base-' + ruleId)?.value || 0;
    const btn = event.target;
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    const body = 'id=' + encodeURIComponent(ruleId) + 
                 '&base=' + encodeURIComponent(base) + 
                 '&markup=0' +
                 '&extra_bed_base=' + encodeURIComponent(ebBase) +
                 '&extra_bed_markup=0' +
                 '&csrf_token=' + encodeURIComponent(csrfToken);
    
    fetch('update_seasonal_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showToast('Price updated successfully!', 'success');
            document.getElementById('base-' + ruleId).classList.add('updated');
            if (document.getElementById('eb-base-' + ruleId)) {
                document.getElementById('eb-base-' + ruleId).classList.add('updated');
            }
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Update';
    });
}

// ============================================================
// UPDATE FAIRMONT / SWISSOTEL PRICE (hidden 70 SAR markup)
// Agent sirf ek final price field dekhta/edit karta hai. 70 SAR ek
// fixed business rule hai (kabhi badalti nahi), isliye yahan client
// side subtract karna safe hai -- Movenpick jaisa fragile guess-work
// nahi hai (wahan markup DB se aa sakti thi, yahan hamesha 70 fixed).
// Reuses the existing update_seasonal_price.php endpoint -- koi nayi
// file nahi banayi.
// ============================================================
function updateHiddenMarkupPrice(ruleId) {
    const price = parseFloat(document.getElementById('fs-price-' + ruleId).value);
    const btn = event.target;

    btn.disabled = true;
    btn.textContent = 'Saving...';

    // 70 SAR fixed rule (kabhi badalti nahi) -- isliye yahan subtract
    // karna safe hai, Movenpick jaisa fragile guess-work nahi hai.
    // Existing update_seasonal_price.php hi reuse kar rahe hain, koi
    // nayi file nahi banayi.
    const base = price - 70;

    const body = 'id=' + encodeURIComponent(ruleId) +
                 '&base=' + encodeURIComponent(base) +
                 '&markup=70' +
                 '&csrf_token=' + encodeURIComponent(csrfToken);

    fetch('update_seasonal_price.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Price updated successfully!', 'success');
            document.getElementById('fs-price-' + ruleId).classList.add('updated');
        } else {
            showToast('Error: ' + (data.error || 'Unknown error'), 'error');
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Update';
    });
}

// ============================================================
// MOVENPICK: Update Prices
// ============================================================
function updateMovenpickPrice(roomType, mealType, startDate, endDate, isFullBoard) {
    const wdPrice = document.getElementById('wd-' + roomType).value;
    const wePrice = document.getElementById('we-' + roomType).value;
    const extraBed = document.getElementById('eb-' + roomType)?.value || 0;
    const row = document.getElementById('seasonal-row-' + roomType);
    const btn = event.target;
    
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    // Pehle hardcoded -70/-25 subtract hota tha, jo sirf coincidentally
    // sahi tha (kyunki DB mein markup abhi 70/25 hai). Ab asal markup
    // value row ke data-attribute se li jaati hai — chahe kabhi markup
    // koi aur value ho, ye hamesha sahi base price nikalega.
    const wdMarkup = parseFloat(row?.dataset.wdMarkup || 0);
    const weMarkup = parseFloat(row?.dataset.weMarkup || 0);
    const ebMarkup = parseFloat(row?.dataset.ebMarkup || 0);
    
    const wdBase = parseFloat(wdPrice) - wdMarkup;
    const weBase = parseFloat(wePrice) - weMarkup;
    const ebBase = parseFloat(extraBed) - ebMarkup;
    
    fetch('update_movenpick_prices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'hotel_id=63&room_type=' + encodeURIComponent(roomType) + 
              '&meal_type=' + encodeURIComponent(mealType) +
              '&start_date=' + encodeURIComponent(startDate) +
              '&end_date=' + encodeURIComponent(endDate) +
              '&wd_base=' + encodeURIComponent(wdBase) +
              '&we_base=' + encodeURIComponent(weBase) +
              '&eb_base=' + encodeURIComponent(ebBase) +
              '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showToast('Prices updated successfully!', 'success');
            document.getElementById('wd-' + roomType).classList.add('updated');
            document.getElementById('we-' + roomType).classList.add('updated');
            if(document.getElementById('eb-' + roomType)) {
                document.getElementById('eb-' + roomType).classList.add('updated');
            }
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Update';
    });
}

// ============================================================
// Update Car Fare
// ============================================================
function updateCarFare(id) {
    const price = document.getElementById('fare-price-' + id).value;
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Saving...';
    
    fetch('update_car_fare.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'id=' + encodeURIComponent(id) + '&price=' + encodeURIComponent(price) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showToast('Fare updated successfully!', 'success');
            document.getElementById('fare-price-' + id).classList.add('updated');
        } else {
            showToast('Error: ' + data.error, 'error');
        }
    })
    .catch(() => {
        showToast('Network error. Please try again.', 'error');
    })
    .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Update';
    });
}
</script>

</body>
</html>