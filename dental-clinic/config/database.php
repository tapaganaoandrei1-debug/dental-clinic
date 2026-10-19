<?php
// config/database.php
// Database connection using PDO. XAMPP default: user "root", empty password.

define('DB_HOST', getenv('DENTAL_DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DENTAL_DB_PORT') ?: '3306');
define('DB_NAME', getenv('DENTAL_DB_NAME') ?: 'dental_clinic');
define('DB_USER', getenv('DENTAL_DB_USER') ?: 'root');
$database_password = getenv('DENTAL_DB_PASS');
define('DB_PASS', $database_password === false ? '' : $database_password);

$database_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$database_ssl_ca = getenv('DENTAL_DB_SSL_CA');
if ($database_ssl_ca !== false && $database_ssl_ca !== '') {
    if (strpos($database_ssl_ca, '-----BEGIN CERTIFICATE-----') !== false) {
        $database_ssl_ca_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'dental-clinic-mysql-ca-' . hash('sha256', $database_ssl_ca) . '.pem';
        if (!is_file($database_ssl_ca_path)
            && file_put_contents($database_ssl_ca_path, $database_ssl_ca, LOCK_EX) === false
        ) {
            throw new RuntimeException('Could not prepare the MySQL CA certificate.');
        }
    } else {
        $database_ssl_ca_path = $database_ssl_ca;
    }
    $database_options[PDO::MYSQL_ATTR_SSL_CA] = $database_ssl_ca_path;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        $database_options
    );
    // Use the same time zone as PHP (Philippines, UTC+8) for NOW() in SQL.
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    // Log the real error, but do not show passwords or details to visitors.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    exit('Could not connect to the database. Make sure MySQL is running in XAMPP and check config/database.php.');
}
