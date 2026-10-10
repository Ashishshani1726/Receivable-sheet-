<?php
// XAMPP defaults usually work as-is. Change these values if your MySQL setup is different.
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'receivables_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Basik Receivables Portal');
define('APP_TIMEZONE', 'Asia/Kolkata');
date_default_timezone_set(APP_TIMEZONE);

// Live Google Sheet Web App API.
// The key stays on the PHP server and is not sent to employee browsers.
define('GOOGLE_SHEET_LIVE', true);
define('GOOGLE_SHEET_API_URL', 'https://script.google.com/macros/s/AKfycbyk9_2W8J4mC8UC5Ujj4wk3P9GKWwCztEIJKP0_XLVfA4mfBfP9NXW3-Rt1sRl6FcWg/exec?key=BASIK2026');
// Cache live data briefly so multiple employee page loads do not repeatedly hit Apps Script.
define('GOOGLE_SHEET_CACHE_SECONDS', 60);


// Second live Google Sheet for PR / PO dashboard.
// Important: paste the Apps Script Web App URL here, not the normal /edit Google Sheet link.
// Google Sheet ID received: 1_4aVIV9GrJzdVNtBWH0hEuQrPi216NensynFFBH9Mpk
// Apps Script template is available in: google_sync/pr_po_live_apps_script.gs
define('PR_PO_SHEET_LIVE', true);
define('PR_PO_SHEET_API_URL', 'https://script.google.com/macros/s/AKfycbwglEv4_MnNXyq0Ca-C5bZT3WwILCXr6StorUZvGmGTYcTgd7i-csIEutKIirE19D1aQQ/exec?key=BASIK2026&sheet=Sheet55');
define('PR_PO_SHEET_CACHE_SECONDS', 60);
define('PR_PO_DEFAULT_SHEET_NAME', 'Sheet55');
