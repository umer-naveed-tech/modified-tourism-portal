<?php
session_start();
header('Content-Type: application/json');
require_once 'config.php';

if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] != 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit();
}

if(!csrf_valid()) {
    echo json_encode(['success' => false, 'error' => 'Security check failed']);
    exit();
}

$id = $_POST['id'] ?? 0;
$base = $_POST['base'] ?? 0;
$markup = $_POST['markup'] ?? 70;

if(!$id || $base < 0 || $markup < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// extra_bed_base / extra_bed_markup pehle yahan bilkul read hi nahi hote
// the -- is wajah se agent panel ka "Extra Bed" field (Makkah Hotel aur
// Makkah Towers ke liye) Update dabane ke baad bhi silently save nahi
// hota tha, DB mein purani value hi reh jaati thi.
//
// Ye dono fields sirf tab update karte hain jab request mein actually
// bheje gaye hon -- Marriot jaisi hotels ka generic "updateSeasonalPrice()"
// JS function ye fields bhejta hi nahi, is liye unke liye ye columns
// chhed nahi jaate (accidentally 0 nahi ho jaate).
if (isset($_POST['extra_bed_base']) && isset($_POST['extra_bed_markup'])) {
    $extra_bed_base = $_POST['extra_bed_base'];
    $extra_bed_markup = $_POST['extra_bed_markup'];

    if ($extra_bed_base < 0 || $extra_bed_markup < 0) {
        echo json_encode(['success' => false, 'error' => 'Invalid data']);
        exit();
    }

    $stmt = $pdo->prepare("UPDATE hotel_seasonal_pricing SET base_price_sar = ?, markup_sar = ?, extra_bed_base = ?, extra_bed_markup = ? WHERE id = ?");
    $success = $stmt->execute([$base, $markup, $extra_bed_base, $extra_bed_markup, $id]);
} else {
    $stmt = $pdo->prepare("UPDATE hotel_seasonal_pricing SET base_price_sar = ?, markup_sar = ? WHERE id = ?");
    $success = $stmt->execute([$base, $markup, $id]);
}

echo json_encode(['success' => $success]);
?>