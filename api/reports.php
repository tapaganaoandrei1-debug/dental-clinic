<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$db = Database::getConnection();

if ($action === 'daily-summary') {
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT COUNT(DISTINCT a.id) as total_appointments, SUM(CASE WHEN q.queue_status = 'served' THEN 1 ELSE 0 END) as total_served, SUM(CASE WHEN q.queue_status = 'no_show' THEN 1 ELSE 0 END) as total_no_shows, SUM(CASE WHEN q.queue_status = 'waiting' THEN 1 ELSE 0 END) as total_waiting FROM appointments a LEFT JOIN queue_tokens q ON a.id = q.appointment_id WHERE a.appointment_date = ?");
    $stmt->execute([$today]);
    sendJson(['status' => 'success', 'analytics' => $stmt->fetch()]);
}

if ($action === 'export-csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename=daily_report.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Ref', 'Client', 'Service', 'Token', 'Status']);
    $stmt = $db->prepare("SELECT a.reference_no, CONCAT(u.first_name, ' ', u.last_name), s.name, q.token_number, q.queue_status FROM appointments a JOIN users u ON a.client_id = u.id JOIN services s ON a.service_id = s.id LEFT JOIN queue_tokens q ON a.id = q.appointment_id WHERE a.appointment_date = ?");
    $stmt->execute([date('Y-m-d')]);
    while ($row = $stmt->fetch()) { fputcsv($out, $row); }
    fclose($out);
    exit;
}
