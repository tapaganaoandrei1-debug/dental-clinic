<?php
require_once __DIR__ . '/config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

require_csrf();
if (is_logged_in()) {
    log_activity('logout', 'Signed out');
}
logout_user();
set_flash('success', 'You have been signed out.');
redirect('login.php');