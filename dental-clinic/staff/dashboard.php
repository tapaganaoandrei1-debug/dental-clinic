<?php
require_once __DIR__ . '/../config/config.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'Front desk';
$active_nav = 'dashboard';
$today = today_string();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS appointments,
            SUM(status = 'pending') AS pending,
            SUM(status = 'confirmed') AS confirmed
     FROM appointments WHERE appointment_date = ?"
);
$stmt->execute([$today]);
$summary = $stmt->fetch();
$stmt = $pdo->prepare(
    "SELECT q.queue_number, q.status AS queue_status, q.estimated_wait_minutes,
            u.full_name AS patient_name, s.name AS service_name, d.full_name AS dentist_name
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE q.queue_date = ? AND q.status IN ('waiting', 'called', 'serving')
     ORDER BY FIELD(q.status, 'serving', 'called', 'waiting'), q.queue_number"
);
$stmt->execute([$today]);
$queue = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT a.start_time, a.status, u.full_name AS patient_name, s.name AS service_name,
            d.full_name AS dentist_name
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.appointment_date = ? ORDER BY a.start_time LIMIT 8"
);
$stmt->execute([$today]);
$appointments = $stmt->fetchAll();

require __DIR__ . '/../includes/views/staff/dashboard.php';
