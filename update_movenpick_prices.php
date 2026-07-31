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

$hotel_id = 63; // ye endpoint sirf Movenpick Hajar Tower ke liye hai
$room_type = $_POST['room_type'] ?? '';
$meal_type = $_POST['meal_type'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$wd_base = $_POST['wd_base'] ?? null;
$we_base = $_POST['we_base'] ?? null;
$eb_base = $_POST['eb_base'] ?? 0;

if ($room_type === '' || $meal_type === '' || $start_date === '' || $end_date === '' || $wd_base === null || $we_base === null) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit();
}

if ($wd_base < 0 || $we_base < 0 || $eb_base < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit();
}

// Agent panel Movenpick table (agent_price_management.php) ye rows
// GROUP BY room_type, start_date, end_date karke padhta hai — har "row"
// jo agent ko dikhti hai, asal mein DB mein DO rows hoti hain
// (is_weekend = 0 aur is_weekend = 1). Isi wajah se yahan bhi dono rows
// alag-alag update karni hain, taake preview/booking exactly wahi price
// dekhen jo agent ne save ki.
//
// markup_sar jaan-boojh kar yahan touch nahi kiya — sirf base_price_sar
// update hoti hai (jo JS ne already asal markup subtract kar ke bheja
// hai), taake stored markup value hamesha sahi rahe chahe wo kuch bhi ho.

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE hotel_seasonal_pricing
        SET base_price_sar = ?, extra_bed_base = ?
        WHERE hotel_id = ? AND room_type = ? AND is_weekend = 0
          AND start_date = ? AND end_date = ? AND meal_type = ?
    ");
    $stmt->execute([$wd_base, $eb_base, $hotel_id, $room_type, $start_date, $end_date, $meal_type]);
    $updated_weekday = $stmt->rowCount();

    $stmt = $pdo->prepare("
        UPDATE hotel_seasonal_pricing
        SET base_price_sar = ?, extra_bed_base = ?
        WHERE hotel_id = ? AND room_type = ? AND is_weekend = 1
          AND start_date = ? AND end_date = ? AND meal_type = ?
    ");
    $stmt->execute([$we_base, $eb_base, $hotel_id, $room_type, $start_date, $end_date, $meal_type]);
    $updated_weekend = $stmt->rowCount();

    $pdo->commit();

    if ($updated_weekday === 0 && $updated_weekend === 0) {
        // Kuch bhi row match nahi hui — matlab shayad room_type/meal_type
        // ya dates mismatch hain. Agent ko clear signal milna chahiye ke
        // "success" ka toast jhoota nahi hai.
        echo json_encode(['success' => false, 'error' => 'No matching pricing rows found to update']);
    } else {
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>