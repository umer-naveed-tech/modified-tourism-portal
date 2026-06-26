<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    http_response_code(403);
    exit();
}
require_once 'config.php';
require_once 'send_status_email.php';

// Clear any previous output
ob_clean();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'];
    $new_status = $_POST['status'];
    
    $stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.email as user_email FROM bookings b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
    $stmt->execute([$id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($booking) {
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $success = $stmt->execute([$new_status, $id]);
        
        if($success && $booking['status'] != $new_status) {
            $email_sent = sendStatusEmail(
                $booking['user_email'],
                $booking['user_name'],
                $booking['booking_no'],
                $booking['service_type'],
                $booking['travel_date'],
                $booking['total_amount'],
                $new_status
            );
            echo json_encode(['success' => true, 'email_sent' => $email_sent]);
        } else {
            echo json_encode(['success' => $success]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
    }
    exit();
}
?>