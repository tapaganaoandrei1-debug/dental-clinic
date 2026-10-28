<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'Reports and analytics';
$active_nav = 'reports';
$range_start = get_value('start') ?: date('Y-m-01');
$range_end = get_value('end') ?: today_string();
if (!is_valid_date($range_start) || !is_valid_date($range_end) || $range_start > $range_end) {
    http_response_code(400);
    exit('Choose a valid date range.');
}
$range_params = [$range_start, $range_end];

if (get_value('export') === 'csv') {
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
    $stmt->execute($range_params);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="dental-clinic-report-' . $range_start . '-to-' . $range_end . '.csv"');
    header('Cache-Control: no-store');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Appointment ID', 'Date', 'Start time', 'Patient', 'Email', 'Service', 'Dentist', 'Status', 'Fee']);
    while ($row = $stmt->fetch()) {
        foreach ($row as $key => $value) {
            if (is_string($value) && preg_match('/^[=+@-]/', $value)) {
                $row[$key] = "'" . $value;
            }
        }
        fputcsv($output, array_values($row));
    }
    fclose($output);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(a.status = 'completed') AS completed,
            SUM(a.status = 'cancelled') AS cancelled,
            SUM(a.status = 'no_show') AS no_show,
            SUM(a.status = 'pending') AS pending,
            COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) AS revenue,
            (SELECT COALESCE(ROUND(AVG(q.estimated_wait_minutes)), 0)
             FROM queue_entries q JOIN appointments qa ON qa.id = q.appointment_id
             WHERE qa.appointment_date BETWEEN ? AND ? AND q.status IN ('waiting', 'called')) AS average_wait
     FROM appointments a JOIN services s ON s.id = a.service_id
     WHERE a.appointment_date BETWEEN ? AND ?"
);
$stmt->execute(array_merge($range_params, $range_params));
$summary = $stmt->fetch();
$stmt = $pdo->prepare(
    "SELECT s.name, COUNT(*) AS appointments,
            SUM(a.status = 'completed') AS completed,
            COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) AS revenue
     FROM appointments a JOIN services s ON s.id = a.service_id
     WHERE a.appointment_date BETWEEN ? AND ?
     GROUP BY s.id, s.name ORDER BY appointments DESC, s.name"
);
$stmt->execute($range_params);
$service_summary = $stmt->fetchAll();
$stmt = $pdo->prepare(
    'SELECT a.appointment_date, COUNT(*) AS appointments
     FROM appointments a WHERE a.appointment_date BETWEEN ? AND ?
     GROUP BY a.appointment_date ORDER BY a.appointment_date'
);
$stmt->execute($range_params);
$daily_summary = $stmt->fetchAll();
$peak_day = 1;
foreach ($daily_summary as $day) $peak_day = max($peak_day, (int) $day['appointments']);

require __DIR__ . '/../includes/views/admin/reports.php';
