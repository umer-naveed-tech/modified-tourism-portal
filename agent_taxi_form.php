<?php
// agent_taxi_form.php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$car_id = (int)($_GET['id'] ?? 0);
$car = null;
$routes = [];

if ($car_id) {
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$car) { header('Location: agent_manage_taxis.php'); exit(); }

    $stmt = $pdo->prepare("SELECT from_city, to_city, price_sar FROM car_fares WHERE car_id = ? ORDER BY from_city, to_city");
    $stmt->execute([$car_id]);
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$is_edit = $car_id > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_edit ? 'Edit Vehicle' : 'Add New Vehicle'; ?> | Agent Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; color: white; min-height: 100vh; padding-bottom: 60px; }
        .container { max-width: 720px; margin: 0 auto; padding: 30px 24px; }
        .btn-back { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 13px; }
        h1 { font-family: 'Playfair Display', serif; font-size: 26px; margin: 14px 0 24px; }
        .card { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 26px; margin-bottom: 20px; }
        .card h3 { font-size: 15px; color: #d4af37; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; }
        .row { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
        .field { flex: 1; min-width: 160px; }
        .field label { display: block; font-size: 12px; color: rgba(255,255,255,0.5); margin-bottom: 6px; }
        .field input { width: 100%; padding: 11px 13px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; font-family: inherit; font-size: 13.5px; }
        .route-row { display: flex; gap: 10px; align-items: flex-end; margin-bottom: 10px; }
        .route-row .field { margin-bottom: 0; }
        .btn-remove { background: rgba(239,68,68,0.1); color: #f87171; border: none; width: 38px; height: 38px; border-radius: 8px; cursor: pointer; font-size: 14px; flex-shrink: 0; }
        .btn-remove:hover { background: #dc2626; color: white; }
        .btn-add-row { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px dashed rgba(212,175,55,0.3); padding: 10px 16px; border-radius: 8px; font-size: 13px; cursor: pointer; font-family: inherit; width: 100%; margin-top: 6px; }
        .btn-add-row:hover { background: rgba(212,175,55,0.18); }
        .btn-save { background: #d4af37; color: #0a0f1e; padding: 14px 30px; border-radius: 10px; border: none; font-weight: 700; font-size: 15px; cursor: pointer; width: 100%; }
        .btn-save:hover { background: #b8922e; }
        .img-preview { width: 100px; height: 65px; object-fit: cover; border-radius: 8px; margin-top: 8px; border: 1px solid rgba(255,255,255,0.08); }
        .hint { font-size: 11.5px; color: rgba(255,255,255,0.35); margin-top: 4px; }
        .error-box { background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); color: #f87171; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13.5px; }
    </style>
</head>
<body>
<div class="container">
    <a href="agent_manage_taxis.php" class="btn-back">← Back to Manage Taxis</a>
    <h1><?php echo $is_edit ? 'Edit Vehicle' : 'Add New Vehicle'; ?></h1>
    <div id="errorBox" class="error-box" style="display:none;"></div>

    <form method="POST" action="save_taxi.php" enctype="multipart/form-data" id="taxiForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="car_id" value="<?php echo $car_id; ?>">
        <input type="hidden" name="routes_json" id="routesJson">

        <div class="card">
            <h3>Vehicle Details</h3>
            <div class="row">
                <div class="field">
                    <label>Car Name (make)</label>
                    <input type="text" name="car_name" required placeholder="e.g. Toyota" value="<?php echo htmlspecialchars($car['car_name'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Model</label>
                    <input type="text" name="car_model" required placeholder="e.g. Corolla" value="<?php echo htmlspecialchars($car['car_model'] ?? ''); ?>">
                </div>
                <div class="field">
                    <label>Capacity (persons)</label>
                    <input type="number" name="capacity" min="1" required value="<?php echo htmlspecialchars($car['capacity'] ?? 4); ?>">
                </div>
            </div>
            <div class="field">
                <label>Vehicle Photo</label>
                <input type="file" name="car_image" accept="image/jpeg,image/png,image/webp">
                <div class="hint">JPG, PNG, or WEBP -- max 5 MB. Leave empty to keep the current photo.</div>
                <?php if (!empty($car['image_url'])): ?>
                    <img class="img-preview" src="<?php echo htmlspecialchars($car['image_url']); ?>" alt="Current photo">
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h3>Routes &amp; Fares</h3>
            <div id="routesContainer"></div>
            <button type="button" class="btn-add-row" onclick="addRoute()"><i class="fas fa-plus"></i> Add Route</button>
        </div>

        <button type="submit" class="btn-save"><?php echo $is_edit ? 'Save Changes' : 'Create Vehicle'; ?></button>
    </form>
</div>

<script>
let routes = <?php echo json_encode($routes); ?>;
if (routes.length === 0) routes.push({ from_city: '', to_city: '', price_sar: '' });

function renderRoutes() {
    const container = document.getElementById('routesContainer');
    container.innerHTML = '';
    routes.forEach((r, i) => {
        const div = document.createElement('div');
        div.className = 'route-row';
        div.innerHTML = `
            <div class="field"><label>From City</label><input type="text" value="${escAttr(r.from_city)}" oninput="updateRoute(${i}, 'from_city', this.value)"></div>
            <div class="field"><label>To City</label><input type="text" value="${escAttr(r.to_city)}" oninput="updateRoute(${i}, 'to_city', this.value)"></div>
            <div class="field"><label>Fare (SAR)</label><input type="number" min="0" value="${r.price_sar}" oninput="updateRoute(${i}, 'price_sar', this.value)"></div>
            <button type="button" class="btn-remove" onclick="removeRoute(${i})">&times;</button>
        `;
        container.appendChild(div);
    });
}
function escAttr(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML.replace(/"/g, '&quot;'); }
function updateRoute(i, field, value) { routes[i][field] = field === 'price_sar' ? (parseFloat(value) || 0) : value; }
function addRoute() { routes.push({ from_city: '', to_city: '', price_sar: '' }); renderRoutes(); }
function removeRoute(i) { routes.splice(i, 1); renderRoutes(); }

document.getElementById('taxiForm').addEventListener('submit', function(e) {
    const errors = [];
    routes.forEach((r, i) => {
        if (!r.from_city || !r.to_city || !r.price_sar) errors.push('Route ' + (i + 1) + ' needs From, To, and Fare filled in.');
    });
    if (errors.length) {
        e.preventDefault();
        const box = document.getElementById('errorBox');
        box.innerHTML = errors.map(er => '• ' + er).join('<br>');
        box.style.display = 'block';
        window.scrollTo(0, 0);
        return;
    }
    document.getElementById('routesJson').value = JSON.stringify(routes);
});

renderRoutes();
</script>
</body>
</html>