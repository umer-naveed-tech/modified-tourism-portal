<?php
// agent_manage_taxis.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$stmt = $pdo->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM car_fares WHERE car_id = c.id) AS route_count,
        (SELECT COUNT(*) FROM bookings WHERE service_type = 'taxi' AND service_id = c.id) AS booking_count
    FROM cars c
    ORDER BY c.car_name
");
$cars = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Taxis | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; }
        .container { max-width: 1000px; margin: 0 auto; padding: 30px 24px; }
        .headrow { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 14px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; }
        .btn-add { background: #d4af37; color: #0a0f1e; padding: 12px 22px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; }
        .btn-add:hover { background: #b8922e; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 14px; overflow: hidden; }
        th { text-align: left; padding: 14px 16px; background: rgba(255,255,255,0.02); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.4); border-bottom: 1px solid rgba(255,255,255,0.05); }
        td { padding: 13px 16px; font-size: 13.5px; color: rgba(255,255,255,0.8); border-bottom: 1px solid rgba(255,255,255,0.03); vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .car-thumb { width: 50px; height: 34px; border-radius: 6px; object-fit: cover; background: rgba(255,255,255,0.05); }
        .badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge.has-data { background: rgba(16,185,129,0.1); color: #34d399; }
        .badge.empty { background: rgba(251,191,36,0.1); color: #fbbf24; }
        .actions a, .actions button { font-size: 12.5px; padding: 6px 14px; border-radius: 7px; text-decoration: none; margin-right: 6px; border: none; cursor: pointer; font-family: inherit; }
        .btn-edit { background: rgba(212,175,55,0.1); color: #d4af37; }
        .btn-edit:hover { background: #d4af37; color: #0a0f1e; }
        .btn-delete { background: rgba(239,68,68,0.1); color: #f87171; }
        .btn-delete:hover { background: #dc2626; color: white; }
        .btn-delete:disabled { opacity: 0.35; cursor: not-allowed; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(5,8,16,0.75); z-index: 999; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-overlay.active { display: flex; }
        .modal-box { background: #10182c; border: 1px solid rgba(239,68,68,0.2); border-radius: 16px; padding: 28px; max-width: 420px; width: 100%; }
        .modal-box h3 { font-family: 'Playfair Display', serif; margin-bottom: 10px; }
        .modal-box p { color: rgba(255,255,255,0.5); font-size: 13.5px; line-height: 1.6; margin-bottom: 20px; }
        .modal-actions { display: flex; gap: 10px; }
        .modal-actions button { flex: 1; padding: 11px; border-radius: 8px; font-weight: 700; font-size: 13.5px; border: none; cursor: pointer; }
        .btn-confirm-delete { background: #dc2626; color: white; }
        .btn-cancel-delete { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.7); }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_dashboard.php" class="btn-back">← Back to Dashboard</a>
    <div class="headrow">
        <h1>Manage Taxis</h1>
        <a href="agent_taxi_form.php" class="btn-add"><i class="fas fa-plus"></i> Add New Vehicle</a>
    </div>

    <table>
        <thead>
            <tr><th></th><th>Vehicle</th><th>Capacity</th><th>Routes</th><th>Bookings</th><th>Action</th></tr>
        </thead>
        <tbody>
            <?php foreach ($cars as $c): ?>
            <tr>
                <td><img class="car-thumb" src="<?php echo htmlspecialchars($c['image_url'] ?: 'https://placehold.co/60x40/1a1a2e/d4af37?text=Car'); ?>" alt=""></td>
                <td><?php echo htmlspecialchars($c['car_name'] . ' ' . $c['car_model']); ?></td>
                <td><?php echo (int)$c['capacity']; ?> persons</td>
                <td>
                    <?php if ($c['route_count'] > 0): ?>
                        <span class="badge has-data"><?php echo $c['route_count']; ?> routes</span>
                    <?php else: ?>
                        <span class="badge empty">No routes yet</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $c['booking_count']; ?></td>
                <td class="actions">
                    <a href="agent_taxi_form.php?id=<?php echo (int)$c['id']; ?>" class="btn-edit">Edit</a>
                    <?php if ($c['booking_count'] > 0): ?>
                        <button type="button" class="btn-delete" disabled title="This vehicle has <?php echo $c['booking_count']; ?> existing booking(s) -- cannot be deleted until those are handled">Has Bookings</button>
                    <?php else: ?>
                        <button type="button" class="btn-delete" data-id="<?php echo (int)$c['id']; ?>" data-name="<?php echo htmlspecialchars($c['car_name'] . ' ' . $c['car_model']); ?>">Delete</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <h3>Delete this vehicle?</h3>
        <p id="deleteModalText"></p>
        <div class="modal-actions">
            <button class="btn-cancel-delete" onclick="closeDeleteModal()">Cancel</button>
            <button class="btn-confirm-delete" id="confirmDeleteBtn">Yes, Delete Permanently</button>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?php echo csrf_token(); ?>';
let pendingDeleteId = null;

document.querySelectorAll('.btn-delete:not(:disabled)').forEach(btn => {
    btn.addEventListener('click', function() {
        pendingDeleteId = this.dataset.id;
        document.getElementById('deleteModalText').textContent =
            'Are you sure you want to permanently delete "' + this.dataset.name + '"? This will remove the vehicle and all its routes/fares. This cannot be undone.';
        document.getElementById('deleteModal').classList.add('active');
    });
});
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
    pendingDeleteId = null;
}
document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
    if (!pendingDeleteId) return;
    this.disabled = true;
    this.textContent = 'Deleting...';
    fetch('delete_taxi_admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'car_id=' + encodeURIComponent(pendingDeleteId) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else {
            alert('Could not delete: ' + (data.error || 'unknown error'));
            this.disabled = false;
            this.textContent = 'Yes, Delete Permanently';
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        this.disabled = false;
        this.textContent = 'Yes, Delete Permanently';
    });
});
</script>
</body>
</html>