<?php
// agent_visa_form.php
//
// Add/Edit a single visa service -- name, price (editable anytime),
// description, and an optional photo (falls back to the generated
// poster design on the customer site if none is uploaded).

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';
require_once 'image_helper.php';

$visa_id = (int)($_GET['id'] ?? 0);
$is_edit = $visa_id > 0;
$visa = ['title' => '', 'description' => '', 'price' => '', 'image_url' => null];

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ? AND service_type = 'visa'");
    $stmt->execute([$visa_id]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$found) {
        header('Location: agent_manage_visas.php');
        exit();
    }
    $visa = $found;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);

    if ($title === '') $errors[] = 'Visa name is required.';
    if ($price <= 0) $errors[] = 'Please enter a valid price.';

    $image_url = $visa['image_url'] ?? null;
    if (!empty($_POST['remove_image'])) {
        if ($image_url && file_exists(__DIR__ . '/' . $image_url)) @unlink(__DIR__ . '/' . $image_url);
        $image_url = null;
    }
    if (!empty($_FILES['visa_image']) && $_FILES['visa_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/uploads/visa_images/';
        $filename = handleImageUpload($_FILES['visa_image'], $upload_dir, 'visa-' . preg_replace('/[^a-z0-9]/i', '', strtolower($title ?: 'new')), 2400, 88);
        if ($filename) {
            if ($visa['image_url'] && file_exists(__DIR__ . '/' . $visa['image_url'])) @unlink(__DIR__ . '/' . $visa['image_url']);
            $image_url = 'uploads/visa_images/' . $filename;
        } else {
            $errors[] = 'That photo could not be processed. Please try a JPG, PNG, or WEBP under 8MB.';
        }
    }

    if (empty($errors)) {
        if ($is_edit) {
            $stmt = $pdo->prepare("UPDATE services SET title = ?, description = ?, price = ?, image_url = ? WHERE id = ? AND service_type = 'visa'");
            $stmt->execute([$title, $description ?: null, $price, $image_url, $visa_id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO services (service_type, title, description, price, image_url) VALUES ('visa', ?, ?, ?, ?)");
            $stmt->execute([$title, $description ?: null, $price, $image_url]);
        }
        header('Location: agent_manage_visas.php?saved=1');
        exit();
    }

    // Re-populate form with what was submitted, so nothing is lost on error.
    $visa = ['title' => $title, 'description' => $description, 'price' => $price, 'image_url' => $image_url];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Visa' : 'Add Visa'; ?> | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 620px; margin: 0 auto; padding: 30px 24px 60px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 20px; }
        .error-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #f87171; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; line-height: 1.7; }
        .field { margin-bottom: 18px; }
        .field label { display: block; font-size: 12.5px; color: rgba(255,255,255,0.55); margin-bottom: 7px; font-weight: 500; }
        .field input, .field textarea { width: 100%; padding: 12px 14px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; color: white; font-family: inherit; font-size: 14px; }
        .field textarea { resize: vertical; min-height: 90px; }
        .field input:focus, .field textarea:focus { outline: none; border-color: #d4af37; }
        .price-input-wrap { position: relative; }
        .price-input-wrap span { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: rgba(255,255,255,0.4); font-size: 13px; }
        .price-input-wrap input { padding-left: 46px; }

        .img-preview { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.08); }
        .img-empty { width: 100%; height: 180px; border-radius: 10px; margin-bottom: 10px; background: linear-gradient(135deg,#1e2a3d,#3a5170); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.4); font-size: 28px; flex-direction: column; gap: 8px; }
        .img-empty small { font-size: 11px; color: rgba(255,255,255,0.5); }
        .file-drop { display: block; border: 1px dashed rgba(255,255,255,0.15); border-radius: 10px; padding: 14px; text-align: center; cursor: pointer; margin-bottom: 10px; }
        .file-drop:hover { border-color: rgba(212,175,55,0.4); }
        .file-drop input { display: none; }
        .file-drop-text { font-size: 12.5px; color: rgba(255,255,255,0.5); }
        .remove-check { display: flex; align-items: center; gap: 8px; font-size: 12px; color: #f87171; cursor: pointer; margin-bottom: 6px; }

        .btn-save { background: #d4af37; color: #0a0f1e; padding: 14px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; margin-top: 8px; }
        .btn-save:hover { background: #b8922e; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_manage_visas.php" class="btn-back">← Back to Manage Visas</a>
    <h1><?php echo $is_edit ? 'Edit Visa' : 'Add New Visa'; ?></h1>

    <?php if (!empty($errors)): ?>
        <div class="error-box"><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>

        <div class="field">
            <label>Card Photo <span style="color:rgba(255,255,255,0.3); font-weight:400;">(optional -- a designed poster is shown automatically if you skip this)</span></label>
            <?php if (!empty($visa['image_url'])): ?>
                <img class="img-preview" id="imgPreview" src="<?php echo htmlspecialchars($visa['image_url']); ?>">
                <label class="remove-check"><input type="checkbox" name="remove_image" value="1"> Remove this photo</label>
            <?php else: ?>
                <div class="img-empty" id="imgEmpty"><i class="fas fa-passport"></i><small>No photo -- generated poster will be used</small></div>
                <img class="img-preview" id="imgPreview" style="display:none;">
            <?php endif; ?>
            <label class="file-drop">
                <input type="file" name="visa_image" accept="image/jpeg,image/png,image/webp" onchange="previewImg(this)">
                <div class="file-drop-text" id="fileText"><i class="fas fa-cloud-arrow-up"></i> Click to choose a photo</div>
            </label>
        </div>

        <div class="field">
            <label>Visa Name</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($visa['title']); ?>" placeholder="e.g. Saudi Umrah Visa" required>
        </div>

        <div class="field">
            <label>Description <span style="color:rgba(255,255,255,0.3); font-weight:400;">(optional)</span></label>
            <textarea name="description" placeholder="e.g. 30 days single entry visa for Umrah pilgrimage"><?php echo htmlspecialchars($visa['description'] ?? ''); ?></textarea>
        </div>

        <div class="field">
            <label>Price (SAR)</label>
            <div class="price-input-wrap">
                <span>SAR</span>
                <input type="number" name="price" min="1" step="0.01" value="<?php echo htmlspecialchars($visa['price']); ?>" required>
            </div>
        </div>

        <button type="submit" class="btn-save"><?php echo $is_edit ? 'Save Changes' : 'Add Visa'; ?></button>
    </form>
</div>
<script>
function previewImg(input) {
    if (!input.files || !input.files[0]) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('imgPreview');
        const empty = document.getElementById('imgEmpty');
        img.src = e.target.result;
        img.style.display = 'block';
        if (empty) empty.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
    document.getElementById('fileText').innerHTML = '<i class="fas fa-check" style="color:#34d399;"></i> ' + input.files[0].name;
}
</script>
</body>
</html>