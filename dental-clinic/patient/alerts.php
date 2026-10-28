<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Alerts';
$active_nav = 'alerts';
$stmt = $pdo->prepare(
    "SELECT id, type, subject, message, status, created_at
     FROM notifications
     WHERE user_id = ? AND type IN ('confirmation', 'reminder', 'reschedule', 'cancellation')
     ORDER BY created_at DESC LIMIT 50"
);
$stmt->execute([current_user_id()]);
$notifications = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 1"
);
$stmt->execute([current_user_id()]);
$next_appointment = $stmt->fetch();
$google_calendar_url = '';
$outlook_calendar_url = '';
if ($next_appointment) {
    $start = new DateTime($next_appointment['appointment_date'] . ' ' . $next_appointment['start_time'], new DateTimeZone('Asia/Manila'));
    $end = new DateTime($next_appointment['appointment_date'] . ' ' . $next_appointment['end_time'], new DateTimeZone('Asia/Manila'));
    $summary = $next_appointment['service_name'] . ' - ' . $next_appointment['dentist_name'];
    $google_calendar_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($summary)
        . '&dates=' . $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis')
        . '&ctz=Asia%2FManila&details=' . rawurlencode('Dental Clinic appointment');
    $outlook_calendar_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . rawurlencode($summary)
        . '&startdt=' . rawurlencode($start->format('Y-m-d\TH:i:sP'))
        . '&enddt=' . rawurlencode($end->format('Y-m-d\TH:i:sP'));
}

require __DIR__ . '/../includes/views/patient/alerts.php';
