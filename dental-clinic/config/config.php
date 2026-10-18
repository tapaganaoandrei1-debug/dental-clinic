<?php
// config/config.php
// Main settings. Every page starts with:
//   require_once __DIR__ . '/../config/config.php';   (adjust the path for the folder)
// This file starts the session and loads the other shared files.

// ---------- Basic settings ----------
define('APP_NAME', 'SmileCare Dental');

// URL path of this project on localhost (no trailing slash).
// Project in htdocs/dental-clinic  ->  '/dental-clinic'
define('BASE_URL', '/dental-clinic');

date_default_timezone_set('Asia/Manila');

// Show PHP errors while developing. Set to false before deploying.
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// ---------- Security settings ----------
define('SESSION_TIMEOUT_MINUTES', 60);  // auto logout after this much inactivity
define('MAX_LOGIN_ATTEMPTS', 5);        // wrong passwords before the cooldown starts
define('LOCKOUT_MINUTES', 10);          // cooldown length
define('PASSWORD_MIN_LENGTH', 8);

// ---------- Role names (stored in users.role) ----------
define('ROLE_PATIENT', 'patient');
define('ROLE_STAFF', 'staff');
define('ROLE_ADMIN', 'admin');

// ---------- Status values (must match the MySQL ENUMs exactly) ----------
const APPOINTMENT_STATUSES = ['pending', 'confirmed', 'completed', 'cancelled', 'no_show'];
const QUEUE_STATUSES = ['waiting', 'called', 'serving', 'completed', 'skipped', 'no_show', 'cancelled'];

// Appointment statuses that block a time slot (cancelled and no_show do not).
const SLOT_BLOCKING_STATUSES = ['pending', 'confirmed', 'completed'];

// Allowed appointment status changes: current status => list of next statuses.
const APPOINTMENT_TRANSITIONS = [
    'pending'   => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled', 'no_show'],
    'completed' => [],
    'cancelled' => [],
    'no_show'   => [],
];

// Allowed queue status changes: current status => list of next statuses.
// Main flow: waiting -> called -> serving -> completed
const QUEUE_TRANSITIONS = [
    'waiting'   => ['called', 'skipped', 'no_show', 'cancelled'],
    'called'    => ['serving', 'skipped', 'no_show', 'cancelled'],
    'serving'   => ['completed'],
    'skipped'   => ['waiting', 'no_show', 'cancelled'],
    'completed' => [],
    'no_show'   => [],
    'cancelled' => [],
];

// ---------- Session ----------
// Session keys used in the whole project:
//   $_SESSION['user_id'], ['role'], ['full_name'], ['email'],
//   ['csrf_token'], ['last_activity'], ['flash']
// Cookie settings are fine for localhost. In production use HTTPS
// (the 'secure' flag below turns on automatically when HTTPS is used).
$is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Basic security headers.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

// ---------- Load the shared files (order matters) ----------
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/booking.php';
