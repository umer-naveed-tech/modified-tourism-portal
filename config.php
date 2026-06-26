<?php
$host = 'localhost';
$dbname = 'tourism_db';
$username = 'root';
$password = '';

// Admin Email Configuration
define('ADMIN_EMAIL', 'cabubakar663@gmail.com'); // ← Apni admin email dalo

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>