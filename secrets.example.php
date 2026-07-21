<?php
/**
 * secrets.example.php — TEMPLATE ONLY, no real credentials.
 * Copy this file to secrets.php and fill in real values there.
 * secrets.php is gitignored; this example file is safe to commit.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tourism_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- SMTP (Gmail) ----
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');
define('SMTP_APP_PASSWORD', 'your_16_digit_app_password');
define('SMTP_FROM_NAME', 'Ahmed Travels');

// ---- Admin ----
define('ADMIN_EMAIL', 'your_admin_email@gmail.com');

// ---- AI Assistant (Groq — free) ----
define('GROQ_API_KEY', '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');