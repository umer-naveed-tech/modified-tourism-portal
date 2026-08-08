<?php
session_start();
require_once 'config.php';
require_once 'hotel_handlers/handler_factory.php';

$hotel_id = $_GET['hotel_id'] ?? 0;

// Get hotel details
$stmt = $pdo->prepare("SELECT * FROM hotels_saudi WHERE id = ?");
$stmt->execute([$hotel_id]);
$hotel = $stmt->fetch();

if(!$hotel) {
    header('Location: services.php?type=hotels');
    exit();
}

// ============================================================
// 🔴 HAR HOTEL KA APNA HANDLER — SARI COMPLEXITY YAHAAN SE GAYI
// ============================================================
$handler = HotelHandlerFactory::getHandler($hotel_id);
$rooms = $handler->getRooms($hotel_id);

$error = '';
$is_movenpick = ($hotel_id == 63);
$is_makkah = ($hotel_id == 43);
$is_marriot = ($hotel_id == 41);
$is_makkah_towers = ($hotel_id == 44);
$is_lemeridien = ($hotel_id == LEMERIDIEN_HOTEL_ID);
$is_simple_hidden_markup = HotelHandlerFactory::isSimpleHiddenMarkupHotel($hotel_id);
$is_single_room_supplement = HotelHandlerFactory::isSingleRoomSupplementHotel($hotel_id);
$hotel_has_extra_bed = false;
$hotel_has_weekend_split = true;
$hotel_requires_meal_type = false;
$hotel_meal_labels = [];
if ($is_single_room_supplement || $is_simple_hidden_markup || $is_lemeridien) {
    $opts_for_flags = HotelHandlerFactory::getHandler($hotel_id)->getBookingOptions($hotel_id);
    $hotel_has_extra_bed = $opts_for_flags['extra_bed_available'] ?? false;
    $hotel_has_weekend_split = $opts_for_flags['has_weekend_split'] ?? true;
    $hotel_requires_meal_type = $opts_for_flags['requires_meal_type'] ?? false;
    $hotel_meal_labels = $opts_for_flags['meal_labels'] ?? [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($hotel['hotel_name']); ?> - Room Selection</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }

        .grain-overlay {
            position: fixed; inset: 0; z-index: 9997; pointer-events: none; opacity: 0.035;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }
        body { 
            font-family: 'Inter', sans-serif; 
            min-height: 100vh;
            background: #0a0f1e;
            overflow-x: hidden;
        }

        .bg-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(180deg, #0a0f1e 0%, #0d1a2d 40%, #0f1f33 70%, #0a1525 100%);
            overflow: hidden;
        }
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.2;
            animation: floatOrb 20s ease-in-out infinite;
        }
        .orb-1 { width: 600px; height: 600px; top: -250px; right: -200px; background: radial-gradient(circle, #d4af37, transparent 70%); animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; bottom: -150px; left: -150px; background: radial-gradient(circle, #c9a03d, transparent 70%); animation-delay: -7s; }
        .orb-3 { width: 300px; height: 300px; top: 50%; left: 50%; transform: translate(-50%, -50%); background: radial-gradient(circle, rgba(212, 175, 55, 0.08), transparent 70%); animation-delay: -14s; }

        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            25% { transform: translate(30px, -40px) scale(1.1); }
            50% { transform: translate(-20px, 20px) scale(0.9); }
            75% { transform: translate(40px, 30px) scale(1.05); }
        }

        .grid-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(rgba(255,255,255,0.01) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.01) 1px, transparent 1px);
            background-size: 60px 60px;
            z-index: 0;
        }

        .content-wrapper {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            padding: 0 0 40px;
        }

        .navbar { 
            background: rgba(10, 15, 30, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(212, 175, 55, 0.08);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand { font-weight: 800; font-size: 22px; color: white !important; letter-spacing: -0.5px; }
        .navbar-brand span { color: #d4af37; }
        .navbar .btn-light {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.06);
            color: #cbd5e1;
            font-weight: 500;
            font-size: 13px;
            padding: 6px 18px;
            transition: all 0.3s ease;
        }
        .navbar .btn-light:hover { background: #d4af37; color: #0a0f1e; border-color: #d4af37; }
        .navbar .btn-outline-light {
            background: transparent;
            border: 1px solid rgba(255,255,255,0.08);
            color: #94a3b8;
        }
        .navbar .btn-outline-light:hover { background: #d4af37; color: #0a0f1e; border-color: #d4af37; }
        .navbar .text-muted { color: #94a3b8 !important; font-size: 13px; }

        .hotel-header { 
            padding: 40px 0 20px;
            text-align: center;
            animation: fadeDown 0.8s ease forwards;
        }
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hotel-header .gold-line {
            width: 60px;
            height: 3px;
            background: #d4af37;
            margin: 0 auto 12px;
            border-radius: 2px;
            animation: expandLine 1s ease forwards;
        }
        @keyframes expandLine {
            from { width: 0; opacity: 0; }
            to { width: 60px; opacity: 1; }
        }
        .hotel-header h2 { 
            font-family: 'Playfair Display', serif;
            font-weight: 800;
            font-size: 34px;
            color: #ffffff;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 30px rgba(0,0,0,0.3);
        }
        .hotel-header .stars {
            color: #d4af37;
            font-size: 18px;
            letter-spacing: 3px;
        }
        .hotel-header .city {
            color: rgba(255,255,255,0.5);
            font-size: 14px;
            font-weight: 400;
            letter-spacing: 0.5px;
        }

        .container { max-width: 900px; margin: 0 auto; padding: 0 24px; }

        .panel { 
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
            box-shadow: 0 4px 40px rgba(0,0,0,0.2);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            animation: fadeUp 0.6s ease forwards;
            opacity: 0;
        }
        .panel:nth-child(1) { animation-delay: 0.1s; }
        .panel:nth-child(2) { animation-delay: 0.2s; }
        .panel:nth-child(3) { animation-delay: 0.3s; }
        
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .panel:hover {
            border-color: rgba(212, 175, 55, 0.1);
            box-shadow: 0 8px 60px rgba(0,0,0,0.3);
            transform: translateY(-2px);
        }

        .selector-panel { 
            padding: 28px 32px; 
            margin-top: -20px; 
            position: relative; 
        }
        .selector-label { 
            font-weight: 600; 
            color: rgba(255,255,255,0.6);
            margin-bottom: 10px; 
            display: block; 
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .room-select-wrap { position: relative; }
        .room-select {
            width: 100%; 
            padding: 15px 20px; 
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.03);
            font-size: 15px; 
            font-weight: 500;
            color: #ffffff;
            cursor: pointer; 
            transition: all 0.3s ease;
            appearance: none;
            -webkit-appearance: none;
        }
        .room-select option { background: #0d1a2d; color: #ffffff; }
        .room-select:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.05); }
        .room-select-wrap::after {
            content: "▾";
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255,255,255,0.2);
            font-size: 14px;
            pointer-events: none;
            transition: transform 0.3s ease;
        }
        .room-select-wrap:hover::after {
            transform: translateY(-50%) rotate(180deg);
        }

        .room-detail { 
            margin-top: 24px; 
            overflow: hidden; 
            display: none; 
        }
        .room-detail.active { 
            display: block; 
            animation: slideUp 0.6s ease forwards;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .room-detail img { 
            width: 100%; 
            height: 300px; 
            object-fit: cover; 
            display: block; 
            background: rgba(255,255,255,0.02);
        }
        .room-detail-noimg {
            width: 100%; height: 240px; display: flex; flex-direction: column; align-items: center; justify-content: center;
            background: rgba(255,255,255,0.02);
            color: rgba(255,255,255,0.2);
            font-size: 14px;
        }
        .room-detail-body { padding: 28px 32px; }
        .room-type-badge { 
            display: inline-block; 
            padding: 4px 16px; 
            border-radius: 50px; 
            font-size: 11px; 
            font-weight: 600; 
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            margin-bottom: 10px; 
            border: 1px solid rgba(212, 175, 55, 0.05);
        }
        .room-detail-body h4 { 
            font-weight: 700; 
            margin-bottom: 6px; 
            font-size: 22px;
            color: #ffffff;
        }
        .room-desc { 
            color: rgba(255,255,255,0.5); 
            margin-bottom: 16px; 
            font-size: 14px;
            line-height: 1.6;
        }
        .amenities { 
            display: flex; 
            flex-wrap: wrap; 
            gap: 6px; 
            margin-bottom: 20px; 
        }
        .amenity { 
            background: rgba(255,255,255,0.04);
            padding: 4px 14px; 
            border-radius: 50px; 
            font-size: 12px; 
            color: rgba(255,255,255,0.6);
            border: 1px solid rgba(255,255,255,0.04);
            transition: all 0.3s ease;
        }
        .amenity:hover {
            background: rgba(212, 175, 55, 0.1);
            color: #d4af37;
            border-color: rgba(212, 175, 55, 0.1);
            transform: scale(1.05);
        }

        .booking-section { 
            padding: 28px 32px; 
            margin-top: 24px; 
            margin-bottom: 40px; 
            display: none; 
        }
        .booking-section.active { display: block; }
        .booking-section h4 { 
            font-weight: 700; 
            margin-bottom: 20px; 
            color: #ffffff;
            font-size: 20px;
        }
        
        label.form-label { 
            font-weight: 500; 
            color: rgba(255,255,255,0.5);
            font-size: 12px; 
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        input { 
            border-radius: 12px; 
            padding: 12px 16px; 
            border: 1px solid rgba(255,255,255,0.06);
            width: 100%; 
            font-family: inherit;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
            color: #ffffff;
            font-size: 14px;
        }
        input:focus { outline: none; border-color: #d4af37; box-shadow: 0 0 0 4px rgba(212,175,55,0.05); }
        input[readonly] { background: rgba(255,255,255,0.02); color: rgba(255,255,255,0.8); font-weight: 600; }
        input[type="date"] { color-scheme: dark; }

        .btn-book { 
            background: linear-gradient(135deg, #d4af37 0%, #b8922e 100%);
            color: #0a0f1e; 
            border: none; 
            padding: 15px; 
            border-radius: 12px; 
            width: 100%; 
            font-weight: 600; 
            font-size: 16px; 
            transition: all 0.3s ease; 
            margin-top: 12px;
            position: relative;
            overflow: hidden;
        }
        .btn-book::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent);
            transition: left 0.6s ease;
        }
        .btn-book:hover { 
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(212, 175, 55, 0.2);
        }
        .btn-book:hover::before { left: 100%; }
        .btn-book:disabled { opacity: 0.4; cursor: not-allowed; transform: none !important; }
        
        .total-price { 
            font-size: 26px; 
            font-weight: 700; 
            color: #34d399;
            padding: 12px 16px;
            background: rgba(16, 185, 129, 0.04);
            border-radius: 12px;
            border: 1px solid rgba(16, 185, 129, 0.08);
            transition: all 0.3s ease;
        }

        .price-breakdown { 
            background: rgba(255,255,255,0.02);
            padding: 20px 24px; 
            border-radius: 14px; 
            margin-top: 18px; 
            display: none;
            border: 1px solid rgba(255,255,255,0.04);
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }

        /* NEW: shown when the selected dates fall outside every priced
           period for this hotel -- a clear, professional message
           instead of a browser alert() or a silently wrong fallback
           price. */
        .hotel-unavailable-panel {
            background: rgba(255,255,255,0.02);
            padding: 24px 26px;
            border-radius: 14px;
            margin-top: 18px;
            display: none;
            border: 1px solid rgba(212,175,55,0.12);
            animation: fadeIn 0.4s ease;
        }
        .hotel-unavailable-panel h6 {
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            font-size: 15px;
            font-family: 'Playfair Display', serif;
        }
        .hotel-unavailable-panel p {
            color: rgba(255,255,255,0.55);
            font-size: 13.5px;
            line-height: 1.7;
            margin-bottom: 18px;
        }
        .hotel-unavailable-panel .btn-contact-us {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #d4af37;
            color: #0a0f1e;
            padding: 12px 26px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 14px;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .hotel-unavailable-panel .btn-contact-us:hover {
            background: #b8922e;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(212,175,55,0.2);
        }
        .price-breakdown h6 {
            font-weight: 600;
            color: rgba(255,255,255,0.6);
            margin-bottom: 14px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .breakdown-range {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
            background: rgba(255,255,255,0.02);
            border-radius: 10px;
            border-left: 3px solid #d4af37;
            margin-bottom: 6px;
            transition: all 0.3s ease;
        }
        .breakdown-range:hover {
            background: rgba(255,255,255,0.04);
            transform: translateX(4px);
        }
        .breakdown-range .range-label { font-size: 13px; font-weight: 500; color: rgba(255,255,255,0.8); }
        .breakdown-range .range-price { font-size: 15px; font-weight: 700; color: #d4af37; }
        .breakdown-range .range-nights { font-size: 11px; color: rgba(255,255,255,0.3); background: rgba(255,255,255,0.04); padding: 1px 10px; border-radius: 12px; margin-left: 8px; }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 14px;
            margin-top: 12px;
            border-top: 1px solid rgba(212, 175, 55, 0.1);
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
        }
        .total-row .grand-total { color: #d4af37; font-size: 22px; }

        /* ============================================================
           MAKKAH HOTEL SPECIFIC STYLES
           ============================================================ */
        .supplement-options {
            margin-top: 16px;
            display: none;
            background: rgba(255,255,255,0.02);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.04);
            animation: fadeIn 0.4s ease;
        }
        .supplement-options .form-check { padding: 4px 0; }
        .supplement-options .form-check-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .supplement-options .form-check-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .supplement-options .form-check-input:checked {
            background-color: #d4af37;
            border-color: #d4af37;
        }
        .supplement-options .supplement-price {
            color: #d4af37;
            font-weight: 500;
            float: right;
        }

        /* ============================================================
           MOVENPICK SPECIFIC STYLES
           ============================================================ */
        .meal-category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-top: 16px;
        }
        .meal-category-card {
            background: rgba(255,255,255,0.02);
            border: 2px solid rgba(255,255,255,0.04);
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255,255,255,0.6);
        }
        .meal-category-card:hover {
            border-color: rgba(212, 175, 55, 0.2);
            transform: translateY(-2px);
        }
        .meal-category-card.active {
            border-color: #d4af37;
            background: rgba(212, 175, 55, 0.05);
            color: #d4af37;
        }
        .meal-category-card .meal-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }
        .meal-category-card .meal-desc {
            font-size: 11px;
            color: rgba(255,255,255,0.3);
        }

        .extra-bed-option {
            margin-top: 16px;
            display: none;
            background: rgba(255,255,255,0.02);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.04);
            animation: fadeIn 0.4s ease;
        }
        .extra-bed-option .form-check { padding: 4px 0; }
        .extra-bed-option .form-check-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .extra-bed-option .form-check-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .extra-bed-option .form-check-input:checked {
            background-color: #d4af37;
            border-color: #d4af37;
        }

        .meal-plan-options {
            margin-top: 16px;
            display: none;
            background: rgba(255,255,255,0.02);
            padding: 16px 20px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,0.04);
            animation: fadeIn 0.4s ease;
        }
        .meal-plan-options .form-check { padding: 4px 0; }
        .meal-plan-options .form-check-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .meal-plan-options .form-check-input:checked {
            background-color: #d4af37;
            border-color: #d4af37;
        }
        
        .full-board-notice { 
            display: none; 
            margin-top: 16px; 
            background: rgba(16, 185, 129, 0.04);
            padding: 12px 18px; 
            border-radius: 12px; 
            color: #34d399;
            font-weight: 500;
            border: 1px solid rgba(16, 185, 129, 0.06);
            font-size: 14px;
            animation: fadeIn 0.4s ease;
        }

        .empty-state { 
            text-align: center; 
            padding: 50px 24px; 
            color: rgba(255,255,255,0.3);
        }
        .empty-state h4 { color: #ffffff; margin-bottom: 6px; }

        /* ============================================================
           MAKKAH TOWERS - Custom Options Styles
           ============================================================ */
        .makkah-towers-options .form-check {
            padding: 4px 0;
        }
        .makkah-towers-options .form-check-label {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
        }
        .makkah-towers-options .form-check-input {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .makkah-towers-options .form-check-input:checked {
            background-color: #d4af37;
            border-color: #d4af37;
        }
        .makkah-towers-options .meal-plan-options,
        .makkah-towers-options .extra-bed-option {
            display: block !important;
        }

        @media (max-width: 768px) {
            .hotel-header h2 { font-size: 24px; }
            .price { font-size: 22px; }
            .total-price { font-size: 22px; }
            .room-detail img { height: 200px; }
            .panel { padding: 0 !important; }
            .room-detail-body { padding: 20px !important; }
            .booking-section { padding: 20px !important; }
            .selector-panel { padding: 20px !important; }
            .price-breakdown { padding: 16px; }
            .meal-category-grid { grid-template-columns: 1fr; }
            .makkah-towers-options .form-check {
                padding: 6px 0;
            }
        }

        /* NEW: page-transition loading overlay for navbar/back links only */
        .page-transition {
            position: fixed; inset: 0; z-index: 99999; background: #0a0f1e;
            display: flex; align-items: center; justify-content: center;
            opacity: 0; visibility: hidden; transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .page-transition.active { opacity: 1; visibility: visible; }
        .pt-spinner { position: relative; width: 64px; height: 64px; }
        .pt-ring { position: absolute; inset: 0; border: 2px solid rgba(212,175,55,0.15); border-top-color: #d4af37; border-radius: 50%; animation: ptSpin 0.9s linear infinite; }
        .pt-icon { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; color: #d4af37; font-size: 20px; animation: ptSpin 0.9s linear infinite reverse; }
        @keyframes ptSpin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>

<div class="grain-overlay" aria-hidden="true"></div>
<div class="page-transition" id="pageTransition"><div class="pt-spinner"><div class="pt-ring"></div><i class="fas fa-plane pt-icon" style="font-style:normal;">✈</i></div></div>

<div class="bg-container">
    <div class="grid-pattern"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
</div>

<div class="content-wrapper">
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">Ahmed<span>Travels</span></a>
            <div>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <span class="text-muted me-2"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <a href="dashboard.php" class="btn btn-light btn-sm">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-light btn-sm me-2">Login</a>
                    <a href="signup.php" class="btn btn-light btn-sm">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="hotel-header">
        <div class="container">
            <div class="gold-line"></div>
            <div class="stars"><?php echo str_repeat('★', (int)$hotel['rating']); ?></div>
            <h2><?php echo htmlspecialchars($hotel['hotel_name']); ?></h2>
            <div class="city"><?php echo htmlspecialchars($hotel['city']); ?></div>
            <?php
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM hotel_gallery_images WHERE hotel_id = ?");
                $stmt->execute([$hotel_id]);
                if ($stmt->fetchColumn() > 0):
            ?>
            <a href="hotel_gallery.php?hotel_id=<?php echo $hotel_id; ?>" style="display:inline-block; margin-top:10px; color:#d4af37; font-size:13px; text-decoration:none; border-bottom:1px solid rgba(212,175,55,0.3);">View Photo Gallery →</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($hotel['rooms_image_url'])): ?>
    <div class="container" style="padding-top:20px;">
        <img src="<?php echo htmlspecialchars($hotel['rooms_image_url']); ?>" alt="Rooms" style="width:100%; max-height:320px; object-fit:cover; border-radius:16px; border:1px solid rgba(255,255,255,0.05);">
    </div>
    <?php endif; ?>

    <div class="container">
        <?php if(isset($_GET['error'])): ?>
            <div class="alert alert-danger" style="background:rgba(239,68,68,0.06); border-color:rgba(239,68,68,0.08); color:#f87171; border-radius:12px; padding:16px 20px; line-height:1.6;">
                We were unable to complete this booking, most likely because the selected dates fall outside our currently confirmed availability for this hotel. Please try different dates, or
                <a href="https://wa.me/923001234567?text=<?php echo urlencode('Hi! I tried to book ' . ($hotel['hotel_name'] ?? 'this hotel') . ' but the dates I wanted were unavailable. Could you please help me manually?'); ?>" target="_blank" style="color:#f87171; text-decoration:underline;">contact our customer service team</a>
                and we will be happy to arrange it for you manually.
            </div>
        <?php endif; ?>

        <?php if(count($rooms) > 0): ?>
            <div class="panel selector-panel">
                <label class="selector-label" for="roomTypeSelect">Select Room Type</label>
                <div class="room-select-wrap">
                    <select id="roomTypeSelect" class="room-select">
                        <option value="" style="color:rgba(255,255,255,0.3);">— Choose a room type —</option>
                        <?php foreach($rooms as $i => $r): ?>
                            <option value="<?php echo $i; ?>" 
                                    data-room-id="<?php echo $r['id']; ?>"
                                    data-room-type="<?php echo htmlspecialchars($r['room_type']); ?>"
                                    data-has-seasonal="<?php echo isset($r['has_seasonal']) && $r['has_seasonal'] ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($r['display_name'] ?? $r['room_type']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if($is_simple_hidden_markup || $is_lemeridien): ?>
            <!-- ============================================================
            BED TYPE SELECTOR (Fairmont, Swissotel Makkah, Swissotel Al
            Maqam, Al Marwa Rayhaan, Le Meridien Tower). Room Type dropdown
            above only picks the room CATEGORY (view/location) -- each
            category has multiple bed configs (Double/Triple/Quad/etc) at
            different prices, so this second selection is required before
            a price can be calculated. Hidden until a room type is chosen;
            options are filled in from that room's actual available bed
            types (some rooms don't offer every config, per the rate sheet).
            ============================================================ -->
            <div class="panel selector-panel" id="bedTypePanel" style="display:none;">
                <label class="selector-label" for="bedTypeSelect">Select Bed Type</label>
                <div class="room-select-wrap">
                    <select id="bedTypeSelect" class="room-select">
                        <option value="">— Choose a bed type —</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <?php if($hotel_requires_meal_type): ?>
            <!-- ============================================================
            MEAL PLAN SELECTOR (generic -- any hotel where the handler
            declares requires_meal_type=true, e.g. Emaar Al Khalil, where
            Room Only vs Breakfast are genuinely different prices, not
            just informational text). Shown after Bed Type is chosen.
            ============================================================ -->
            <div class="panel selector-panel" id="mealTypePanel" style="display:none;">
                <label class="selector-label" for="mealTypeSelect">Select Meal Plan</label>
                <div class="room-select-wrap">
                    <select id="mealTypeSelect" class="room-select">
                        <option value="">— Choose a meal plan —</option>
                        <?php foreach($hotel_meal_labels as $mcode => $mlabel): ?>
                            <option value="<?php echo htmlspecialchars($mcode); ?>"><?php echo htmlspecialchars($mlabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

            <div class="panel room-detail" id="roomDetail">
                <div id="rd_image_wrap"></div>
                <div class="room-detail-body">
                    <span class="room-type-badge" id="rd_badge"></span>
                    <h4 id="rd_title"></h4>
                    <p class="room-desc" id="rd_desc"></p>
                    <div class="amenities" id="rd_amenities"></div>
                </div>
            </div>

            <div class="panel booking-section" id="bookingSection">
                <h4>Complete Your Booking</h4>
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <div style="background:rgba(251,191,36,0.04); color:#fbbf24; padding:12px 16px; border-radius:12px; font-size:13px; margin-bottom:16px; border:1px solid rgba(251,191,36,0.06);">
                        You will be asked to log in when you confirm — your room and dates will be saved.
                    </div>
                <?php endif; ?>
                <form method="POST" action="book_hotel_room.php">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="room_id" id="selected_room_id">
                    <input type="hidden" name="hotel_id" value="<?php echo (int)$hotel_id; ?>">
                    <input type="hidden" name="hotel_name" value="<?php echo htmlspecialchars($hotel['hotel_name']); ?>">
                    <input type="hidden" name="room_type_code" id="selected_room_type_code" value="">
                    <input type="hidden" name="bed_type" id="selected_bed_type" value="">
                    <input type="hidden" name="meal_type" id="selected_meal_type_generic" value="">
                    
                    <?php if($is_makkah): ?>
                    <!-- ============================================================
                    MAKKAH HOTEL: Meal Options
                    ============================================================ -->
                    <div class="meal-plan-options" id="mealPlanOptions" style="display:block;">
                        <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7);">Select Meal Plan</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="meal_type" id="meal_breakfast_makkah" value="breakfast" checked onchange="calculateTotal()">
                            <label class="form-check-label" for="meal_breakfast_makkah">Breakfast Only</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="meal_type" id="meal_halfboard_makkah" value="halfboard" onchange="calculateTotal()">
                            <label class="form-check-label" for="meal_halfboard_makkah">Half Board</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="meal_type" id="meal_fullboard_makkah" value="fullboard" onchange="calculateTotal()">
                            <label class="form-check-label" for="meal_fullboard_makkah">Full Board</label>
                        </div>
                    </div>

                    <!-- ============================================================
                    MAKKAH HOTEL: Extra Bed
                    ============================================================ -->
                    <div class="extra-bed-option" id="extraBedOption" style="display:block;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="extra_bed" name="extra_bed" value="1" onchange="calculateTotal()">
                            <label class="form-check-label" for="extra_bed">Add Extra Bed</label>
                        </div>
                    </div>

                    <!-- ============================================================
                    MAKKAH HOTEL: Supplements (Add-ons)
                    ============================================================ -->
                    <div class="supplement-options" id="supplementOptions" style="display:block;">
                        <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7);">Room Supplements (Optional)</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="supp_renovated" value="renovated" onchange="calculateTotal()">
                            <label class="form-check-label" for="supp_renovated">Renovated Room</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="supp_junior_suite" value="junior_suite" onchange="calculateTotal()">
                            <label class="form-check-label" for="supp_junior_suite">Junior Suite</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="supp_kaaba_view" value="kaaba_view" onchange="calculateTotal()">
                            <label class="form-check-label" for="supp_kaaba_view">Kaaba View</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="supp_suite" value="suite" onchange="calculateTotal()">
                            <label class="form-check-label" for="supp_suite">One Bed Room Suite</label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if($is_movenpick): ?>
                    <!-- ============================================================
                    MOVENPICK: Meal Plan Selection
                    ============================================================ -->
                    <div id="mealCategoryContainer">
                        <label class="form-label" style="font-weight:600; text-transform:none; color:rgba(255,255,255,0.7); margin-top:16px;">Select Meal Plan</label>
                        <div class="meal-category-grid">
                            <div class="meal-category-card active" data-meal="breakfast" onclick="selectMealCategory('breakfast')">
                                <div class="meal-name">International Breakfast</div>
                                <div class="meal-desc">Room + Breakfast</div>
                            </div>
                            <div class="meal-category-card" data-meal="halfboard" onclick="selectMealCategory('halfboard')">
                                <div class="meal-name">International Half Board</div>
                                <div class="meal-desc">Room + Breakfast + Dinner</div>
                            </div>
                            <div class="meal-category-card" data-meal="fullboard" onclick="selectMealCategory('fullboard')">
                                <div class="meal-name">International Full Board</div>
                                <div class="meal-desc">All Meals Included</div>
                            </div>
                        </div>
                        <input type="hidden" name="meal_type" id="selected_meal_type" value="breakfast">

                        <div class="extra-bed-option" id="extraBedOptionMovenpick" style="display:block;">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="extra_bed_movenpick" name="extra_bed" value="1" onchange="calculateTotal()">
                                <label class="form-check-label" for="extra_bed_movenpick">Add Extra Bed</label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- ============================================================
                    🔴 HANDLER-SPECIFIC OPTIONS (Makkah Towers, etc.)
                    ============================================================ -->
                    <div id="handlerOptions">
                        <?php echo $handler->renderRoomSelection($hotel_id, $rooms); ?>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Check-in</label>
                            <input type="date" name="check_in" id="check_in" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Check-out</label>
                            <input type="date" name="check_out" id="check_out" class="form-control" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Nights</label>
                            <input type="text" id="nights" class="form-control" readonly value="0">
                        </div>
                    </div>

                    <!-- NEW: generic Number of Guests field -- applies to every
                         hotel (previously only Makkah Towers had its own guest
                         selector). Value is read by calculateTotal() below and
                         submitted as the form's "guests" field, same as before. -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-4">
                            <label class="form-label">Number of Guests</label>
                            <input type="number" name="guests" id="guests" class="form-control" value="2" min="1" max="10" onchange="calculateTotal()">
                        </div>
                    </div>

                    <div class="price-breakdown" id="priceBreakdown">
                        <h6>Price Breakdown</h6>
                        <div id="breakdownDetails"></div>
                        <div class="total-row">
                            <span>Grand Total</span>
                            <span class="grand-total" id="grandTotal">SAR 0</span>
                        </div>
                    </div>

                    <!-- NEW: professional "unavailable for these dates" panel -->
                    <div class="hotel-unavailable-panel" id="hotelUnavailablePanel">
                        <h6>This Hotel Is Currently Unavailable for These Dates</h6>
                        <p>We do not have pricing available for the hotel and dates you have selected. This may be outside our currently confirmed availability window. If you would still like to book this hotel, please contact our customer service team and we will be happy to arrange it for you manually.</p>
                        <a href="https://wa.me/923001234567?text=<?php echo urlencode('Hi! I would like to book ' . ($hotel['hotel_name'] ?? 'this hotel') . ' for dates outside the listed availability. Could you please help me manually?'); ?>" class="btn-contact-us" target="_blank">Contact Customer Service</a>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-12">
                            <label class="form-label">Total Amount</label>
                            <input type="text" id="total_amount" class="form-control total-price" readonly value="SAR 0">
                        </div>
                    </div>

                    <button type="submit" class="btn-book" id="btnBook" disabled>
                        Confirm Booking
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="panel empty-state" style="padding:60px 24px;">
                <div style="font-size:48px; margin-bottom:16px;">🏨</div>
                <h4 style="font-size:22px;">Coming Soon</h4>
                <p style="max-width:380px; margin:0 auto; line-height:1.6;">Online booking for <strong style="color:rgba(255,255,255,0.6);"><?php echo htmlspecialchars($hotel['hotel_name']); ?></strong> is being set up. In the meantime, our team can help you book this hotel directly.</p>
                <div style="display:flex; gap:12px; justify-content:center; margin-top:24px; flex-wrap:wrap;">
                    <a href="https://wa.me/923001234567?text=<?php echo urlencode('Hi! I want to book ' . $hotel['hotel_name']); ?>" target="_blank" class="btn" style="background:#25D366; color:white; padding:10px 24px; border-radius:8px; text-decoration:none; font-weight:500;">📱 Chat on WhatsApp</a>
                    <a href="services.php?type=hotels&city=<?php echo urlencode($hotel['city']); ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.05); color:rgba(255,255,255,0.5); padding:10px 24px; border-radius:8px; text-decoration:none;">← Back to Hotels</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Room data
const roomsData = <?php echo json_encode($rooms, JSON_UNESCAPED_SLASHES); ?>;
let selectedRoom = null;
const isMovenpick = <?php echo $is_movenpick ? 'true' : 'false'; ?>;
const isMakkah = <?php echo $is_makkah ? 'true' : 'false'; ?>;
const isMarriot = <?php echo $is_marriot ? 'true' : 'false'; ?>;
const isMakkahTowers = <?php echo $is_makkah_towers ? 'true' : 'false'; ?>;
const isSimpleHiddenMarkupHotel = <?php echo $is_simple_hidden_markup ? 'true' : 'false'; ?>;
const isSingleRoomSupplementHotel = <?php echo $is_single_room_supplement ? 'true' : 'false'; ?>;
const hotelHasExtraBed = <?php echo $hotel_has_extra_bed ? 'true' : 'false'; ?>;
const hotelHasWeekendSplit = <?php echo $hotel_has_weekend_split ? 'true' : 'false'; ?>;
const hotelRequiresMealType = <?php echo $hotel_requires_meal_type ? 'true' : 'false'; ?>;
const isLeMeridien = <?php echo $is_lemeridien ? 'true' : 'false'; ?>;

function calculateNights() {
    const checkIn = document.getElementById('check_in').value;
    const checkOut = document.getElementById('check_out').value;
    const nightsField = document.getElementById('nights');
    
    if(checkIn && checkOut) {
        const start = new Date(checkIn);
        const end = new Date(checkOut);
        // 🔴 FIX: Math.abs() used to hide a reversed date pair (checkout
        // picked earlier than check-in) by turning a negative difference
        // into a positive "nights" count. Now a reversed pair correctly
        // resolves to 0 nights instead of a nonsensical number.
        const diffTime = end - start;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays > 0) {
            nightsField.value = diffDays;
            return diffDays;
        }
    }
    nightsField.value = 0;
    return 0;
}

function isWeekend(dateStr) {
    const date = new Date(dateStr);
    const day = date.getDay();
    return (day === 4 || day === 5);
}

function selectMealCategory(mealType) {
    document.querySelectorAll('.meal-category-card').forEach(el => {
        el.classList.toggle('active', el.dataset.meal === mealType);
    });
    document.getElementById('selected_meal_type').value = mealType;
    
    const extraBedOption = document.getElementById('extraBedOptionMovenpick');
    if (mealType === 'fullboard') {
        extraBedOption.style.display = 'none';
        document.getElementById('extra_bed_movenpick').checked = false;
    } else {
        extraBedOption.style.display = 'block';
    }
    
    calculateTotal();
}

function showHotelUnavailable() {
    document.getElementById('hotelUnavailablePanel').style.display = 'block';
    document.getElementById('priceBreakdown').style.display = 'none';
    document.getElementById('total_amount').value = 'Unavailable for these dates';
    document.getElementById('btnBook').disabled = true;
}

function hideHotelUnavailable() {
    document.getElementById('hotelUnavailablePanel').style.display = 'none';
}

function calculateTotal() {
    const select = document.getElementById('roomTypeSelect');
    const roomIndex = select.value;
    
    const nights = calculateNights();
    
    if (roomIndex === '') {
        document.getElementById('total_amount').value = 'SAR 0';
        document.getElementById('btnBook').disabled = true;
        document.getElementById('priceBreakdown').style.display = 'none';
        return;
    }
    
    const room = roomsData[parseInt(roomIndex)];
    const checkIn = document.getElementById('check_in').value;
    const checkOut = document.getElementById('check_out').value;
    const hotelId = <?php echo (int)$hotel_id; ?>;
    
    if (!checkIn || !checkOut || nights < 1) {
        document.getElementById('total_amount').value = 'SAR 0';
        document.getElementById('btnBook').disabled = true;
        return;
    }
    
    // ============================================================
    // MAKKAH HOTEL (hotel_id = 43)
    // ============================================================
    if (isMakkah) {
        const mealType = document.querySelector('input[name="meal_type"]:checked')?.value || 'breakfast';
        const extraBed = document.getElementById('extra_bed')?.checked ? 1 : 0;
        
        const supplements = [];
        if (document.getElementById('supp_renovated')?.checked) supplements.push('renovated');
        if (document.getElementById('supp_junior_suite')?.checked) supplements.push('junior_suite');
        if (document.getElementById('supp_kaaba_view')?.checked) supplements.push('kaaba_view');
        if (document.getElementById('supp_suite')?.checked) supplements.push('suite');
        
        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;
        
        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                check_in: checkIn,
                check_out: checkOut,
                meal_type: mealType,
                extra_bed: extraBed,
                supplements: supplements,
                guests: parseInt(document.getElementById('guests')?.value || 2),
                hotel_type: 'makkah'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }
                
                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);
                
                let breakdownHtml = '';
                const ranges = [];
                
                data.breakdown.forEach(item => {
                    const key = item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)');
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({
                            rule_name: key,
                            count: 1,
                            total: parseFloat(item.price)
                        });
                    }
                });
                
                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });
                
                if (data.extra_bed_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #34d399;">
                            <div>
                                <span class="range-label">Extra Bed</span>
                                <span class="range-nights">${data.nights} nights</span>
                            </div>
                            <span class="range-price" style="color:#34d399;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                
                if (data.supplements_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div>
                                <span class="range-label">Room Supplements</span>
                                <span class="range-nights">1 stay</span>
                            </div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.supplements_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                
                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });
        
        return;
    }
    
    // ============================================================
    // MOVENPICK (hotel_id = 63)
    // ============================================================
    if (isMovenpick) {
        const mealType = document.getElementById('selected_meal_type').value || 'breakfast';
        const extraBed = document.getElementById('extra_bed_movenpick')?.checked ? 1 : 0;
        const finalExtraBed = (mealType === 'fullboard') ? 0 : extraBed;
        
        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;
        
        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                check_in: checkIn,
                check_out: checkOut,
                meal_type: mealType,
                extra_bed: finalExtraBed,
                hotel_type: 'movenpick'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }
                
                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);
                
                let breakdownHtml = '';
                const ranges = [];
                
                data.breakdown.forEach(item => {
                    const key = item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)');
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({
                            rule_name: key,
                            count: 1,
                            total: parseFloat(item.price)
                        });
                    }
                });
                
                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });
                
                if (data.extra_bed_total > 0 && mealType !== 'fullboard') {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #34d399;">
                            <div>
                                <span class="range-label">Extra Bed</span>
                                <span class="range-nights">${data.nights} nights</span>
                            </div>
                            <span class="range-price" style="color:#34d399;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                
                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });
        
        return;
    }
    
    // ============================================================
    // MAKKAH TOWERS (hotel_id = 44)
    // ============================================================
    if (isMakkahTowers) {
        const mealType = document.querySelector('input[name="meal_type"]:checked')?.value || 'breakfast';
        const extraBed = document.getElementById('extra_bed_makkah_towers')?.checked ? 1 : 0;
        const guests = document.getElementById('guests')?.value || document.getElementById('guests_makkah_towers')?.value || 2;
        
        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;
        
        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                check_in: checkIn,
                check_out: checkOut,
                meal_type: mealType,
                extra_bed: extraBed,
                guests: parseInt(guests),
                hotel_type: 'makkah_towers'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }
                
                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);
                
                let breakdownHtml = '';
                const ranges = [];
                
                data.breakdown.forEach(item => {
                    const key = item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)');
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({
                            rule_name: key,
                            count: 1,
                            total: parseFloat(item.price)
                        });
                    }
                });
                
                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });
                
                if (data.extra_bed_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #34d399;">
                            <div>
                                <span class="range-label">Extra Bed</span>
                                <span class="range-nights">${data.nights} nights</span>
                            </div>
                            <span class="range-price" style="color:#34d399;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                
                if (data.meal_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div>
                                <span class="range-label">Meal Plan (${data.meal_type})</span>
                                <span class="range-nights">${data.guest_count} persons × ${data.nights} nights</span>
                            </div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.meal_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                
                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });
        
        return;
    }
    
    // ============================================================
    // LE MERIDIEN TOWER HOTEL MAKKAH -- bespoke. Category (room_type),
    // Subtype (bed_type), Meal Plan (REQUIRED -- changes price), Extra
    // Bed (Royal Suite only).
    // ============================================================
    if (isLeMeridien) {
        const bedType = document.getElementById('selected_bed_type')?.value || '';
        const mealType = document.getElementById('lemeridienMealSelect')?.value || '';

        if (!bedType || !mealType) {
            document.getElementById('total_amount').value = mealType ? 'Select a bed type' : 'Select a meal plan';
            document.getElementById('btnBook').disabled = true;
            return;
        }

        const lmExtraBedEl = document.getElementById('lemeridien_extra_bed');
        const lmExtraBed = (lmExtraBedEl && lmExtraBedEl.checked) ? 1 : 0;

        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;

        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                bed_type: bedType,
                meal_type: mealType,
                check_in: checkIn,
                check_out: checkOut,
                extra_bed: lmExtraBed
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }

                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);

                let breakdownHtml = '';
                const ranges = [];

                data.breakdown.forEach(item => {
                    const key = hotelHasWeekendSplit ? (item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)')) : item.rule_name;
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({ rule_name: key, count: 1, total: parseFloat(item.price) });
                    }
                });

                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });

                if (data.extra_bed_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div><span class="range-label">Extra Bed</span></div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }

                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });

        return;
    }
    
    // ============================================================
    // SINGLE-ROOM SUPPLEMENT HOTELS (Al Safwah, Conrad, Hilton Suites,
    // Hilton Convention, future hotels of this pattern)
    // ============================================================
    if (isSingleRoomSupplementHotel) {
        const extraBedEl = hotelHasExtraBed ? document.querySelector('input[name="extra_bed"]') : null;
        const extraBed = extraBedEl?.checked ? 1 : 0;
        const supplementEl = document.querySelector('input[name="supplement"]:checked');
        const supplement = supplementEl ? supplementEl.value : '';

        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;

        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                check_in: checkIn,
                check_out: checkOut,
                extra_bed: extraBed,
                supplement: supplement
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }

                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);

                let breakdownHtml = '';
                const ranges = [];

                data.breakdown.forEach(item => {
                    const key = item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)');
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({ rule_name: key, count: 1, total: parseFloat(item.price) });
                    }
                });

                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });

                if (data.extra_bed_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div><span class="range-label">Extra Bed</span></div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }
                if (data.supplement_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div><span class="range-label">Room Supplement</span></div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.supplement_total.toFixed(2)}</span>
                        </div>
                    `;
                }

                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });

        return;
    }
    
    // ============================================================
    // FAIRMONT CLOCK TOWER & SWISSOTEL MAKKAH
    // ============================================================
    if (isSimpleHiddenMarkupHotel) {
        const bedType = document.getElementById('selected_bed_type')?.value || '';
        if (!bedType) {
            // Room type select ho chuka hai lekin bed type abhi nahi --
            // is state mein calculate hi mat karo, warna galat/undefined
            // price aane ka wahi purana risk wapas aa jayega.
            document.getElementById('total_amount').value = 'Select a bed type';
            document.getElementById('btnBook').disabled = true;
            return;
        }

        const mealTypeVal = hotelRequiresMealType ? (document.getElementById('mealTypeSelect')?.value || '') : '';
        if (hotelRequiresMealType && !mealTypeVal) {
            document.getElementById('total_amount').value = 'Select a meal plan';
            document.getElementById('btnBook').disabled = true;
            return;
        }

        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;

        const extraBedEl2 = hotelHasExtraBed ? document.querySelector('input[name="extra_bed"]') : null;
        const extraBed2 = extraBedEl2?.checked ? 1 : 0;

        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type,
                bed_type: bedType,
                meal_type: mealTypeVal,
                check_in: checkIn,
                check_out: checkOut,
                extra_bed: extraBed2
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }

                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);

                let breakdownHtml = '';
                const ranges = [];

                data.breakdown.forEach(item => {
                    const key = hotelHasWeekendSplit ? (item.rule_name + (item.is_weekend ? ' (Weekend)' : ' (Weekday)')) : item.rule_name;
                    const existing = ranges.find(r => r.rule_name === key);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({ rule_name: key, count: 1, total: parseFloat(item.price) });
                    }
                });

                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });

                if (data.extra_bed_total > 0) {
                    breakdownHtml += `
                        <div class="breakdown-range" style="border-left-color: #d4af37;">
                            <div><span class="range-label">Extra Bed</span></div>
                            <span class="range-price" style="color:#d4af37;">SAR ${data.extra_bed_total.toFixed(2)}</span>
                        </div>
                    `;
                }

                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });

        return;
    }
    
    // ============================================================
    // OTHER HOTELS (Including Marriot) - Fallback
    // ============================================================
    if (room.has_seasonal) {
        document.getElementById('total_amount').value = 'Calculating...';
        document.getElementById('btnBook').disabled = true;
        
        fetch('get_hotel_room_price.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                hotel_id: hotelId,
                room_type: room.room_type.toLowerCase(),
                check_in: checkIn,
                check_out: checkOut,
                hotel_type: 'other'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                hideHotelUnavailable();
                if (data.nights > 0) {
                    document.getElementById('nights').value = data.nights;
                }
                
                document.getElementById('grandTotal').textContent = 'SAR ' + data.grand_total.toFixed(2);
                document.getElementById('total_amount').value = 'SAR ' + data.grand_total.toFixed(2);
                
                let breakdownHtml = '';
                const ranges = [];
                
                data.breakdown.forEach(item => {
                    const existing = ranges.find(r => r.rule_name === item.rule_name);
                    if (existing) {
                        existing.count++;
                        existing.total += parseFloat(item.price);
                    } else {
                        ranges.push({
                            rule_name: item.rule_name,
                            count: 1,
                            total: parseFloat(item.price)
                        });
                    }
                });
                
                ranges.forEach(range => {
                    breakdownHtml += `
                        <div class="breakdown-range">
                            <div>
                                <span class="range-label">${range.rule_name}</span>
                                <span class="range-nights">${range.count} night${range.count > 1 ? 's' : ''}</span>
                            </div>
                            <span class="range-price">SAR ${range.total.toFixed(2)}</span>
                        </div>
                    `;
                });
                
                document.getElementById('breakdownDetails').innerHTML = breakdownHtml;
                document.getElementById('priceBreakdown').style.display = 'block';
                document.getElementById('btnBook').disabled = false;
            } else {
                showHotelUnavailable();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('total_amount').value = 'Error calculating price';
            document.getElementById('btnBook').disabled = true;
        });
    } else {
        // Simple/normal hotels (NormalHotelHandler) ka room object mein
        // price field ka naam "price_per_night_sar" hai, "price" nahi —
        // isliye pehle "room.price" hamesha undefined tha aur total "NaN"
        // ban jata tha.
        const total = room.price_per_night_sar * nights;
        document.getElementById('total_amount').value = 'SAR ' + total.toFixed(2);
        document.getElementById('btnBook').disabled = total > 0 ? false : true;
        document.getElementById('priceBreakdown').style.display = 'none';
    }
}

function renderRoomDetail(room) {
    const imgWrap = document.getElementById('rd_image_wrap');
    if(room.image_url) {
        imgWrap.innerHTML = '<img src="' + room.image_url + '" alt="' + room.room_type + ' Room" onerror="this.onerror=null;this.parentElement.innerHTML=noImageMarkup(\'' + room.room_type + '\');">';
    } else {
        imgWrap.innerHTML = noImageMarkup(room.room_type);
    }

    document.getElementById('rd_badge').textContent = room.room_type;
    document.getElementById('rd_title').textContent = room.display_name || room.room_type + ' Room';
    document.getElementById('rd_desc').textContent = room.description || 'Comfortable room with premium amenities';
    
    const amenitiesBox = document.getElementById('rd_amenities');
    amenitiesBox.innerHTML = '';
    
    let amenities = room.amenities || [];
    if (typeof amenities === 'string') {
        amenities = amenities.split(',').map(a => a.trim());
    }
    
    if (amenities.length === 0) {
        amenities = ['Attached Washroom', 'Air Conditioning', 'WiFi'];
    }
    
    amenities.forEach(a => {
        if (a && a.toLowerCase() !== 'mini bar') {
            const span = document.createElement('span');
            span.className = 'amenity';
            span.textContent = a;
            amenitiesBox.appendChild(span);
        }
    });
}

function noImageMarkup(roomType) {
    return '<div class="room-detail-noimg">' +
        '<span>' + roomType + ' Room — photo coming soon</span>' +
        '</div>';
}

const select = document.getElementById('roomTypeSelect');
if(select) {
    select.addEventListener('change', function() {
        if(this.value === '') {
            document.getElementById('roomDetail').classList.remove('active');
            document.getElementById('bookingSection').classList.remove('active');
            document.getElementById('btnBook').disabled = true;
            document.getElementById('priceBreakdown').style.display = 'none';
            document.getElementById('nights').value = 0;
            selectedRoom = null;
            return;
        }

        selectedRoom = roomsData[parseInt(this.value)];
        renderRoomDetail(selectedRoom);
        document.getElementById('selected_room_id').value = selectedRoom.id;
        document.getElementById('selected_room_type_code').value = selectedRoom.room_type;
        document.getElementById('roomDetail').classList.add('active');
        document.getElementById('bookingSection').classList.add('active');
        calculateNights();

        if (isLeMeridien) {
            // Extra Bed sirf Royal Suite (rs) category ke liye hai --
            // is room object ka apna extra_bed_available flag check karo
            // (Fairmont/Sheraton jaisi hotels ke uske ulat, yahan ye
            // PER-CATEGORY farak hai, poori hotel ke liye nahi).
            const ebPanel = document.getElementById('lemeridienExtraBedPanel');
            if (ebPanel) {
                if (selectedRoom.extra_bed_available) {
                    ebPanel.style.display = 'block';
                } else {
                    ebPanel.style.display = 'none';
                    document.getElementById('lemeridien_extra_bed').checked = false;
                }
            }
            // Meal Plan panel abhi hide rakho -- bed type select hone ke
            // baad hi dikhega (neeche).
            const mealPanel = document.getElementById('lemeridienMealPanel');
            if (mealPanel) mealPanel.style.display = 'none';
            document.getElementById('lemeridienMealSelect').value = '';
        }

        if (isSimpleHiddenMarkupHotel || isLeMeridien) {
            // Bed Type dropdown ko is room ke actual available options se
            // bharo. Agar sirf EK hi bed type hai (jaisa Elaf Kinda mein --
            // har room category ka apna sirf ek hi config hai), to dropdown
            // dikhane ki zaroorat nahi -- khud-ba-khud select ho jayega.
            const bedTypes = selectedRoom.bed_types || [];
            const bedSelect = document.getElementById('bedTypeSelect');
            const bedPanel = document.getElementById('bedTypePanel');

            if (bedTypes.length <= 1) {
                bedPanel.style.display = 'none';
                document.getElementById('selected_bed_type').value = bedTypes[0] || '';
                if (isLeMeridien) {
                    // Bed type auto-select ho gaya, lekin Meal Plan abhi
                    // bhi zaroori hai -- seedha calculate mat karo.
                    document.getElementById('lemeridienMealPanel').style.display = 'block';
                    document.getElementById('total_amount').value = 'Select a meal plan';
                    document.getElementById('btnBook').disabled = true;
                } else if (hotelRequiresMealType) {
                    document.getElementById('mealTypePanel').style.display = 'block';
                    document.getElementById('mealTypeSelect').value = '';
                    document.getElementById('total_amount').value = 'Select a meal plan';
                    document.getElementById('btnBook').disabled = true;
                } else {
                    calculateTotal();
                }
            } else {
                bedSelect.innerHTML = '<option value="">— Choose a bed type —</option>';
                bedTypes.forEach(bt => {
                    const opt = document.createElement('option');
                    opt.value = bt;
                    opt.textContent = bt.charAt(0).toUpperCase() + bt.slice(1);
                    bedSelect.appendChild(opt);
                });
                bedPanel.style.display = 'block';
                document.getElementById('selected_bed_type').value = '';
                document.getElementById('total_amount').value = 'Select a bed type';
                document.getElementById('btnBook').disabled = true;
                document.getElementById('priceBreakdown').style.display = 'none';
            }
        } else {
            calculateTotal();
        }

        document.getElementById('roomDetail').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
}

const bedTypeSelectEl = document.getElementById('bedTypeSelect');
if (bedTypeSelectEl) {
    bedTypeSelectEl.addEventListener('change', function() {
        document.getElementById('selected_bed_type').value = this.value;
        if (this.value === '') {
            document.getElementById('total_amount').value = 'Select a bed type';
            document.getElementById('btnBook').disabled = true;
            document.getElementById('priceBreakdown').style.display = 'none';
            if (isLeMeridien) document.getElementById('lemeridienMealPanel').style.display = 'none';
            if (hotelRequiresMealType) document.getElementById('mealTypePanel').style.display = 'none';
            return;
        }
        if (isLeMeridien) {
            // Bed type mil gaya, ab Meal Plan chahiye pehle calculate
            // karne se -- warna galat price aa sakti hai (har meal plan
            // ki alag price hai is hotel mein).
            document.getElementById('lemeridienMealPanel').style.display = 'block';
            document.getElementById('lemeridienMealSelect').value = '';
            document.getElementById('total_amount').value = 'Select a meal plan';
            document.getElementById('btnBook').disabled = true;
            return;
        }
        if (hotelRequiresMealType) {
            document.getElementById('mealTypePanel').style.display = 'block';
            document.getElementById('mealTypeSelect').value = '';
            document.getElementById('total_amount').value = 'Select a meal plan';
            document.getElementById('btnBook').disabled = true;
            return;
        }
        calculateTotal();
    });
}

const mealTypeSelectEl = document.getElementById('mealTypeSelect');
if (mealTypeSelectEl) {
    mealTypeSelectEl.addEventListener('change', function() {
        document.getElementById('selected_meal_type_generic').value = this.value;
        if (this.value === '') {
            document.getElementById('total_amount').value = 'Select a meal plan';
            document.getElementById('btnBook').disabled = true;
            return;
        }
        calculateTotal();
    });
}

const checkInEl = document.getElementById('check_in');
const checkOutEl = document.getElementById('check_out');
if(checkInEl && checkOutEl) {
    checkInEl.addEventListener('change', function() {
        // NEW: keep check-out's own minimum in sync with whatever
        // check-in date was just picked, so the calendar itself can't
        // offer a check-out date before check-in in the first place
        // (previously check-out's min was a fixed "tomorrow" date,
        // regardless of check-in -- letting a customer pick a check-out
        // earlier than check-in and get a nonsensical "76 nights").
        if (checkInEl.value) {
            const nextDay = new Date(checkInEl.value);
            nextDay.setDate(nextDay.getDate() + 1);
            const minCheckOut = nextDay.toISOString().split('T')[0];
            checkOutEl.min = minCheckOut;
            if (checkOutEl.value && checkOutEl.value < minCheckOut) {
                checkOutEl.value = minCheckOut;
            }
        }
        calculateNights();
        calculateTotal();
    });
    checkOutEl.addEventListener('change', function() {
        calculateNights();
        calculateTotal();
    });
}

// Makkah Hotel meal plan change
document.querySelectorAll('input[name="meal_type"]').forEach(el => {
    el.addEventListener('change', calculateTotal);
});

// Makkah Hotel supplements
document.querySelectorAll('#supp_renovated, #supp_junior_suite, #supp_kaaba_view, #supp_suite').forEach(el => {
    el.addEventListener('change', calculateTotal);
});

// Makkah Hotel extra bed
document.getElementById('extra_bed')?.addEventListener('change', calculateTotal);

// Movenpick extra bed
document.getElementById('extra_bed_movenpick')?.addEventListener('change', calculateTotal);

// Makkah Towers extra bed
document.getElementById('extra_bed_makkah_towers')?.addEventListener('change', calculateTotal);

// Makkah Towers guests
document.getElementById('guests_makkah_towers')?.addEventListener('change', calculateTotal);

// Makkah Towers meal plan change
document.querySelectorAll('#handlerOptions input[name="meal_type"]').forEach(el => {
    el.addEventListener('change', calculateTotal);
});

/* NEW: page-transition overlay -- only for navbar/back-to-hotels links,
   does not touch any room-selection or booking logic above. */
document.querySelectorAll('.navbar a, .empty-state a').forEach(a => {
    a.addEventListener('click', function() {
        const pt = document.getElementById('pageTransition');
        if (pt) pt.classList.add('active');
    });
});

/* NEW: clear the overlay if this page is restored from the browser's
   back-forward cache (bfcache) via the Back/Forward button, so it never
   gets stuck showing. */
window.addEventListener('pageshow', function(event) {
    if (event.persisted) {
        const pt = document.getElementById('pageTransition');
        if (pt) pt.classList.remove('active');
    }
});
</script>

</body>
</html>