<?php
require_once __DIR__ . '/../config/config.php';
require_role_api([ROLE_ADMIN]);

$start = get_value('start') ?: date('Y-m-01');
$end = get_value('end') ?: today_string();
if (!is_valid_date($start) || !is_valid_date($end) || $start > $end) {
    json_response(['ok' => false, 'error' => 'Choose a valid date range.'], 422);
}

$stmt = $pdo->prepare(
    'SELECT a.id, a.appointment_date, a.start_time, u.full_name, u.email,
            s.name AS service_name, d.full_name AS dentist_name, a.status, s.price
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.appointment_date BETWEEN ? AND ?
     ORDER BY a.appointment_date, a.start_time'
);
$stmt->execute([$start, $end]);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="dental-clinic-report-' . $start . '-to-' . $end . '.csv"');
header('Cache-Control: no-store');
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Appointment ID', 'Date', 'Start time', 'Patient', 'Email', 'Service', 'Dentist', 'Status', 'Fee']);
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    foreach ($row as &$value) {
        if (is_string($value) && preg_match('/^[=+@-]/', $value)) {
            $value = "'" . $value;
        }
    }
    unset($value);
    fputcsv($output, $row);
}
fclose($output);
