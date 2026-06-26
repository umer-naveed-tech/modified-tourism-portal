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
<html>
<head>
    <title>Agent Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Agent Panel</span><a href="dashboard.php" class="btn btn-light">Dashboard</a></div></nav>
<div class="container mt-4">
    <div class="alert alert-success">Welcome Agent! You can manage services here.</div>
    <table class="table table-bordered">
        <tr><th>ID</th><th>Type</th><th>Title</th><th>Price</th><th>Action</th></tr>
        <?php foreach($services as $s): ?>
        <tr>
            <td><?php echo $s['id']; ?></td>
            <td><?php echo $s['service_type']; ?></td>
            <td><?php echo htmlspecialchars($s['title']); ?></td>
            <td>Rs. <?php echo number_format($s['price']); ?></td>
            <td><a href="edit_service.php?id=<?php echo $s['id']; ?>" class="btn btn-sm btn-warning">Edit</a></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
</body>
</html>