<?php
// ============================================
// 1. REQUIRED FILES
// ============================================
require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/csrf.php';

// ============================================
// 2. DATABASE CONNECTION
// ============================================
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ============================================
// 3. START SESSION (AGAR PEHLE SE NAHI HAI)
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// 4. REMEMBER ME AUTO-LOGIN
// ============================================
require_once __DIR__ . '/remember_me_check.php';