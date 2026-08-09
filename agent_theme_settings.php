<?php
// agent_theme_settings.php
//
// Lets the agent upload the exact background photos the customer
// dashboard uses -- one hero photo, plus one for each of the 3
// service categories (Hotel/Taxi/Visa). Images are compressed
// automatically (image_helper.php) so the customer panel stays fast
// no matter how large the originals are.

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'image_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $slots = ['dashboard_hero', 'service_hotel', 'service_taxi', 'service_visa'];
    $upload_dir = __DIR__ . '/uploads/theme_images/';
    $stmt = $pdo->prepare("UPDATE site_theme_images SET image_path = ? WHERE setting_key = ?");
    foreach ($slots as $slot) {
        if (!empty($_FILES[$slot]) && $_FILES[$slot]['error'] === UPLOAD_ERR_OK) {
            $filename = handleImageUpload($_FILES[$slot], $upload_dir, $slot);
            if ($filename) {
                $stmt->execute(['uploads/theme_images/' . $filename, $slot]);
            }
        }
    }
    header('Location: agent_theme_settings.php?saved=1');
    exit();
}

$stmt = $pdo->query("SELECT setting_key, image_path FROM site_theme_images");
$images = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$slots_info = [
    'dashboard_hero' => ['label' => 'Dashboard Hero Background', 'hint' => 'The big background photo behind "Welcome back" on the customer dashboard. A wide, atmospheric travel/city/skyline shot works best.'],
    'service_hotel' => ['label' => 'Hotel Service Card', 'hint' => 'Background for the "Hotel" card shown when a customer clicks Book a Service.'],
    'service_taxi' => ['label' => 'Taxi Service Card', 'hint' => 'Background for the "Taxi" card.'],
    'service_visa' => ['label' => 'Visa/Tour Service Card', 'hint' => 'Background for the "Visa & Tours" card.'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Theme Images | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 800px; margin: 0 auto; padding: 30px 24px 60px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 6px; }
        .sub { color: rgba(255,255,255,0.45); font-size: 13.5px; margin-bottom: 26px; }
        .flash { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: #34d399; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .slot-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 16px; padding: 22px; margin-bottom: 16px; }
        .slot-card h3 { font-size: 14.5px; margin-bottom: 4px; }
        .slot-card .hint { font-size: 12px; color: rgba(255,255,255,0.4); margin-bottom: 14px; line-height: 1.5; }
        .slot-preview { width: 100%; height: 140px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; border: 1px solid rgba(255,255,255,0.08); }
        .slot-empty { width: 100%; height: 140px; border-radius: 10px; margin-bottom: 12px; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.25); font-size: 12.5px; }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 14px; text-align: center; cursor: pointer; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 12.5px; color: rgba(255,255,255,0.5); }
        .btn-save { background: #d4af37; color: #0a0f1e; padding: 14px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; margin-top: 6px; }
        .btn-save:hover { background: #b8922e; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <h1>Customer Panel Theme Images</h1>
    <div class="sub">These photos are what customers see behind the dashboard and service cards. Upload your own -- landscape/wide photos work best.</div>

    <?php if (isset($_GET['saved'])): ?>
    <div class="flash"><i class="fas fa-circle-check"></i> Saved. Changes are live immediately.</div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <?php foreach ($slots_info as $key => $info): ?>
        <div class="slot-card">
            <h3><?php echo htmlspecialchars($info['label']); ?></h3>
            <div class="hint"><?php echo htmlspecialchars($info['hint']); ?></div>
            <?php if (!empty($images[$key])): ?>
                <img class="slot-preview" src="<?php echo htmlspecialchars($images[$key]); ?>" alt="">
            <?php else: ?>
                <div class="slot-empty">No image uploaded yet -- a default look is used until you add one</div>
            <?php endif; ?>
            <label class="file-drop">
                <input type="file" name="<?php echo $key; ?>" accept="image/jpeg,image/png,image/webp">
                <div class="file-drop-text"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
            </label>
        </div>
        <?php endforeach; ?>
        <button type="submit" class="btn-save">Save Theme Images</button>
    </form>
</div>
</body>
</html>