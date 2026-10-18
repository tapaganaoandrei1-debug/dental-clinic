<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$reminder_hours = max(1, (int) get_setting('reminder_hours_before', '24'));
$window_end = date('Y-m-d H:i:s', time() + $reminder_hours * 3600);
$stmt = $pdo->prepare(
    "SELECT a.id, a.patient_id, a.appointment_date, a.start_time,
            u.email, s.name AS service_name
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     WHERE a.status = 'confirmed'
       AND TIMESTAMP(a.appointment_date, a.start_time) BETWEEN NOW() AND ?
       AND NOT EXISTS (
           SELECT 1 FROM notifications n
           WHERE n.appointment_id = a.id AND n.type = 'reminder' AND n.channel = 'email'
       )
     ORDER BY a.appointment_date, a.start_time"
);
$stmt->execute([$window_end]);
$appointments = $stmt->fetchAll();
$insert = $pdo->prepare(
    "INSERT INTO notifications
     (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
     VALUES (?, ?, 'email', 'reminder', ?, 'Appointment reminder', ?, NOW(), 'queued')"
);

$queued = 0;
foreach ($appointments as $appointment) {
    $message = 'Reminder: your ' . $appointment['service_name'] . ' appointment is ' . format_date($appointment['appointment_date']) . ' at ' . format_time($appointment['start_time']) . '.';
    $insert->execute([$appointment['patient_id'], $appointment['id'], $appointment['email'], $message]);
    $queued++;
}

echo 'Reminder notifications queued: ' . $queued . PHP_EOL;
echo 'Local delivery is simulated; configure an email provider before production use.' . PHP_EOL;