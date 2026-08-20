<?php
// ============================================
// 1. REQUIRED FILES
// ============================================
require_once __DIR__ . '/secrets.php';
require_once __DIR__ . '/csrf.php';

// ============================================
// 1b. TAMARA PAYMENT GATEWAY (Saudi) -- REPLACE the 3 placeholder
// values below with your actual live keys before this goes live.
// ============================================
define('TAMARA_API_URL', 'https://api.tamara.co');
define('TAMARA_API_TOKEN', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhY2NvdW50SWQiOiJmNWUzNmExYS01ZWY2LTRmYzctYWU5My05MmVmZmJmYmQ4MGEiLCJ0eXBlIjoibWVyY2hhbnQiLCJzYWx0IjoiY2Q1Yzg5ZjMtZTk2Ny00NDE2LTg3NTktMjE3YTkzYTAyY2IxIiwicm9sZXMiOlsiUk9MRV9NRVJDSEFOVCJdLCJpc010bHMiOmZhbHNlLCJpYXQiOjE3ODcwNjEyOTIsImlzcyI6IlRhbWFyYSBQUCJ9.ojrDRdsNdQdaCL3OA8jXtKNKYl4LCcbJUGlEGpH4jch3IECuHhO0FHMrGmIsg7pZkc5rmT1VkGR1bKKIWBww0zT8y-mrUcZAhLORuk5p3yNn_lG0JeJ6IIhKQDxlTEfuHr24fVmXf5Dkt_ssGl7YupLAXTYkOMXhoBdDTem_gIuIANLrJlf0bmp6n4wpSjRg68OxZ6OSBe7tTipcrhUvrGh-Aq1dmUqap-4LB5VsIGBhkzeDVxI3Gv-Ezs71VMpldrUP5LqQJY4xk8as_KpIdXWSoLhjneNb3TSPS8r1I-tlTJMQwDJBdXlRBmAOywisey8zmvRirlcpScwzlShpkw');
define('TAMARA_PUBLIC_KEY', '94f3d876-8f5c-425b-93cc-5ea7a184282b');
define('TAMARA_NOTIFICATION_TOKEN', '5a566bba-e5e5-404f-b0d6-c5150583e367');

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