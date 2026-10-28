<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Live queue';
$active_nav = 'queue';
$stmt = $pdo->prepare(
        "SELECT q.id, q.queue_number, q.status, a.start_time,
                        s.name AS service_name, d.full_name AS dentist_name,
                        (SELECT COUNT(*) FROM queue_entries q2
                         WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
                             AND q2.status IN ('waiting', 'called', 'serving')) AS ahead_count,
                        (SELECT COALESCE(SUM(s2.duration_minutes), 0) FROM queue_entries q2
                         JOIN appointments a2 ON a2.id = q2.appointment_id
                         JOIN services s2 ON s2.id = a2.service_id
                         WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
                             AND q2.status IN ('waiting', 'called', 'serving')) AS wait_estimate,
                        (SELECT q3.queue_number FROM queue_entries q3
                         WHERE q3.queue_date = q.queue_date AND q3.status = 'serving'
                         ORDER BY q3.updated_at DESC LIMIT 1) AS now_serving
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
         WHERE a.patient_id = ? AND q.queue_date = CURDATE()
             AND q.status IN ('waiting', 'called', 'serving')
         ORDER BY q.queue_number"
);
$stmt->execute([current_user_id()]);
$entries = $stmt->fetchAll();
$entry = $entries[0] ?? null;
$position = $entry ? (int) $entry['ahead_count'] + 1 : 0;
$progress = $entry ? max(10, min(100, (int) round(100 / max(1, $position + 1)))) : 0;
$stmt = $pdo->prepare(
    "SELECT subject, message, status, created_at
     FROM notifications
     WHERE user_id = ? AND type IN ('confirmation', 'reminder', 'reschedule', 'cancellation')
     ORDER BY created_at DESC LIMIT 3"
);
$stmt->execute([current_user_id()]);
$notifications = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 1"
);
$stmt->execute([current_user_id()]);
$upcoming = $stmt->fetch();
$google_calendar_url = '';
$outlook_calendar_url = '';
if ($upcoming) {
    $calendar_start = new DateTime($upcoming['appointment_date'] . ' ' . $upcoming['start_time'], new DateTimeZone('Asia/Manila'));
    $calendar_end = new DateTime($upcoming['appointment_date'] . ' ' . $upcoming['end_time'], new DateTimeZone('Asia/Manila'));
    $calendar_title = $upcoming['service_name'] . ' - ' . $upcoming['dentist_name'];
    $google_calendar_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($calendar_title)
        . '&dates=' . $calendar_start->format('Ymd\THis') . '/' . $calendar_end->format('Ymd\THis') . '&ctz=Asia%2FManila';
    $outlook_calendar_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . rawurlencode($calendar_title)
        . '&startdt=' . rawurlencode($calendar_start->format('Y-m-d\TH:i:sP')) . '&enddt=' . rawurlencode($calendar_end->format('Y-m-d\TH:i:sP'));
}

require __DIR__ . '/../includes/views/patient/queue.php';
