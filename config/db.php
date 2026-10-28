<?php
declare(strict_types=1);
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    session_start();
}
class Database {
    public static function getConnection(): PDO {
        return new PDO("mysql:host=127.0.0.1;dbname=dental_clinic;charset=utf8mb4", "root", "", [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}
function sendJson(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}
