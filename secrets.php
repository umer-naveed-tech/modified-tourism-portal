<?php
/**
 * secrets.php — REAL credentials go here.
 * This file is listed in .gitignore and must NEVER be committed to Git.
 *
 * Setup:
 * 1. Generate a NEW Gmail App Password (the old one is compromised because
 *    it was pushed to a public repo — revoke it at
 *    https://myaccount.google.com/apppasswords before using this file).
 * 2. Fill in the values below with your real credentials.
 * 3. Save this file locally — Git will ignore it automatically.
 */

// ---- Database ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'tourism_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- SMTP (Gmail) ----
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your_email@gmail.com');       // ← apni Gmail ID
define('SMTP_APP_PASSWORD', 'your_new_app_password');  // ← NAYA app password (16 digit)
define('SMTP_FROM_NAME', 'Ahmed Travels');

// ---- Admin ----
define('ADMIN_EMAIL', 'your_admin_email@gmail.com');

// ---- AI Assistant (Groq — free) ----
// Get a free key at https://console.groq.com/keys (no credit card needed)
define('GROQ_API_KEY', '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');