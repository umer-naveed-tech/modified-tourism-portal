<?php
session_start();
if(!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'agent') {
    header('Location: login.php');
    exit();
}
require_once 'config.php';

$stmt = $pdo->query("SELECT * FROM services");
$services = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Panel | Ahmed Travels</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #0a0f1e; min-height: 100vh; overflow-x: hidden; }

        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #0a0f1e; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(180deg, #d4af37, #8a6d1f); border-radius: 6px; }
        html { scrollbar-color: #8a6d1f #0a0f1e; scrollbar-width: thin; }

        .navbar { background: rgba(10, 15, 30, 0.9); backdrop-filter: blur(20px); border-bottom: 1px solid rgba(212, 175, 55, 0.08); padding: 14px 0; }
        .container { max-width: 1100px; margin: 0 auto; padding: 0 24px; }
        .navbar .container-inner { display: flex; justify-content: space-between; align-items: center; }
        .navbar-brand { font-family: 'Playfair Display', serif; color: white; font-size: 20px; font-weight: 800; }
        .btn-dashboard { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); padding: 8px 18px; border-radius: 8px; text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.3s ease; }
        .btn-dashboard:hover { background: #d4af37; color: #0a0f1e; }

        .page-body { max-width: 1100px; margin: 32px auto; padding: 0 24px; }
        .welcome-banner {
            background: rgba(16,185,129,0.06); color: #34d399; border: 1px solid rgba(16,185,129,0.1);
            padding: 14px 20px; border-radius: 12px; margin-bottom: 24px; font-size: 14px;
            display: flex; align-items: center; gap: 10px;
        }

        .table-container { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); border-radius: 16px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 14px 16px; background: rgba(255,255,255,0.02); font-weight: 600; font-size: 11.5px; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid rgba(255,255,255,0.04); }
        td { padding: 13px 16px; font-size: 13.5px; color: rgba(255,255,255,0.75); border-bottom: 1px solid rgba(255,255,255,0.02); }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        .btn-edit { background: rgba(212,175,55,0.1); color: #d4af37; border: 1px solid rgba(212,175,55,0.15); padding: 5px 14px; border-radius: 7px; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.3s ease; }
        .btn-edit:hover { background: #d4af37; color: #0a0f1e; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="container container-inner">
        <span class="navbar-brand">Agent Panel</span>
        <a href="dashboard.php" class="btn-dashboard">Dashboard</a>
    </div>
</nav>

<div class="page-body">
    <div class="welcome-banner">✅ Welcome Agent! You can manage services here.</div>
    <div class="table-container">
        <table>
            <tr><th>ID</th><th>Type</th><th>Title</th><th>Price</th><th>Action</th></tr>
            <?php foreach($services as $s): ?>
            <tr>
                <td><?php echo $s['id']; ?></td>
                <td><?php echo htmlspecialchars($s['service_type']); ?></td>
                <td><?php echo htmlspecialchars($s['title']); ?></td>
                <td>Rs. <?php echo number_format($s['price']); ?></td>
                <td><a href="edit_service.php?id=<?php echo $s['id']; ?>" class="btn-edit">Edit</a></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

</body>
</html>