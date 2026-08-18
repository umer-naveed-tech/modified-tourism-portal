<?php
// agent_manage_visas.php
//
// Full CRUD list for Visa Services, matching the same pattern as
// agent_manage_hotels.php / agent_manage_taxis.php -- Add New, Edit,
// Delete, each with the same safety checks (delete blocked if real
// bookings exist against a visa).

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$stmt = $pdo->query("SELECT * FROM services WHERE service_type = 'visa' ORDER BY title");
$visas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Visas | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 24px 60px; }
        .top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 6px; }
        .sub { color: rgba(255,255,255,0.45); font-size: 13.5px; }
        .btn-add { background: #d4af37; color: #0a0f1e; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 13.5px; }
        .btn-add:hover { background: #b8922e; }
        .flash { background: rgba(52,211,153,0.08); border: 1px solid rgba(52,211,153,0.2); color: #34d399; padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 13px; }
        .flash.err { background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.2); color: #f87171; }

        .visa-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 20px; }
        .visa-card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 14px; overflow: hidden; }
        .visa-img { width: 100%; height: 130px; object-fit: cover; background: linear-gradient(135deg,#1e2a3d,#3a5170); }
        .visa-img-empty { width: 100%; height: 130px; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg,#1e2a3d,#3a5170); color: rgba(255,255,255,0.4); font-size: 26px; }
        .visa-body { padding: 16px; }
        .visa-body h3 { font-size: 15px; margin-bottom: 4px; }
        .visa-body .desc { font-size: 12px; color: rgba(255,255,255,0.4); margin-bottom: 10px; line-height: 1.5; max-height: 36px; overflow: hidden; }
        .visa-body .price { color: #d4af37; font-weight: 700; font-size: 15px; margin-bottom: 12px; }
        .visa-actions { display: flex; gap: 8px; }
        .visa-actions a, .visa-actions button { flex: 1; text-align: center; padding: 9px; border-radius: 8px; font-size: 12.5px; font-weight: 600; text-decoration: none; cursor: pointer; font-family: inherit; border: none; }
        .btn-edit { background: rgba(212,175,55,0.1); color: #d4af37; }
        .btn-edit:hover { background: #d4af37; color: #0a0f1e; }
        .btn-del { background: rgba(239,68,68,0.08); color: #f87171; }
        .btn-del:hover { background: #dc2626; color: white; }

        .empty-state { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.35); font-size: 13.5px; }
        .empty-state i { font-size: 32px; margin-bottom: 14px; display: block; opacity: 0.4; }
    </style>
</head>
<body>
<div class="container">
    <div class="top-row">
        <div>
            <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
            <h1>Manage Visas</h1>
            <div class="sub">Add, edit, or remove visa services -- changes apply to the customer site immediately.</div>
        </div>
        <a href="agent_visa_form.php" class="btn-add"><i class="fas fa-plus"></i> Add New Visa</a>
    </div>

    <?php if (isset($_GET['saved'])): ?><div class="flash"><i class="fas fa-circle-check"></i> Saved.</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="flash"><i class="fas fa-circle-check"></i> Visa deleted.</div><?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'has_bookings'): ?>
        <div class="flash err"><i class="fas fa-circle-exclamation"></i> Can't delete -- this visa has existing bookings. Remove or reassign those first.</div>
    <?php endif; ?>

    <?php if (empty($visas)): ?>
        <div class="empty-state">
            <i class="fas fa-passport"></i>
            No visas added yet. Click "Add New Visa" to create your first one.
        </div>
    <?php else: ?>
    <div class="visa-grid">
        <?php foreach ($visas as $v): ?>
        <div class="visa-card">
            <?php if (!empty($v['image_url'])): ?>
                <img class="visa-img" src="<?php echo htmlspecialchars($v['image_url']); ?>" alt="">
            <?php else: ?>
                <div class="visa-img-empty"><i class="fas fa-passport"></i></div>
            <?php endif; ?>
            <div class="visa-body">
                <h3><?php echo htmlspecialchars($v['title']); ?></h3>
                <div class="desc"><?php echo htmlspecialchars($v['description'] ?? ''); ?></div>
                <div class="price">SAR <?php echo number_format($v['price']); ?></div>
                <div class="visa-actions">
                    <a href="agent_visa_form.php?id=<?php echo (int)$v['id']; ?>" class="btn-edit">Edit</a>
                    <form method="POST" action="delete_visa_admin.php" onsubmit="return confirm('Delete \'<?php echo htmlspecialchars(addslashes($v['title'])); ?>\'? This removes it from the customer site immediately.');" style="flex:1;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="id" value="<?php echo (int)$v['id']; ?>">
                        <button type="submit" class="btn-del">Delete</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>