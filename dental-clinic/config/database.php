<?php
// config/database.php
// Database connection using PDO. XAMPP default: user "root", empty password.

define('DB_HOST', 'localhost');
define('DB_NAME', 'dental_clinic');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errors throw exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows come back as arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
        ]
    );
    // Use the same time zone as PHP (Philippines, UTC+8) for NOW() in SQL.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Log the real error, but do not show passwords or details to visitors.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not connect to the database. Make sure MySQL is running in XAMPP and check config/database.php.');
}
