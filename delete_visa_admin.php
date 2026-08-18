<?php
// delete_visa_admin.php
//
// Deletes a visa -- blocked (like hotels/taxis) if real bookings
// exist against it, so a customer's booking history never points to
// a service that silently vanished.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: agent_manage_visas.php');
    exit();
}
csrf_verify();

$visa_id = (int)($_POST['id'] ?? 0);
if (!$visa_id) {
    header('Location: agent_manage_visas.php');
    exit();
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE service_type = 'visa' AND service_id = ?");
$stmt->execute([$visa_id]);
if ($stmt->fetchColumn() > 0) {
    header('Location: agent_manage_visas.php?error=has_bookings');
    exit();
}

$stmt = $pdo->prepare("SELECT image_url FROM services WHERE id = ? AND service_type = 'visa'");
$stmt->execute([$visa_id]);
$image_url = $stmt->fetchColumn();
if ($image_url && file_exists(__DIR__ . '/' . $image_url)) @unlink(__DIR__ . '/' . $image_url);

$pdo->prepare("DELETE FROM services WHERE id = ? AND service_type = 'visa'")->execute([$visa_id]);

header('Location: agent_manage_visas.php?deleted=1');
exit();