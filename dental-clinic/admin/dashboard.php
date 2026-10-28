<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'Clinic overview';
$active_nav = 'dashboard';
$today = today_string();
$summary = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) AS appointments_today,
        (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting') AS waiting_now,
        (SELECT COALESCE(ROUND(AVG(estimated_wait_minutes)), 0) FROM queue_entries WHERE queue_date = CURDATE() AND status IN ('waiting', 'called')) AS average_wait,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'no_show') AS no_shows"
)->fetch();
$stmt = $pdo->prepare(
    "SELECT a.start_time, a.status, u.full_name AS patient_name,
            s.name AS service_name, d.full_name AS dentist_name, q.status AS queue_status
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     LEFT JOIN queue_entries q ON q.appointment_id = a.id
     WHERE a.appointment_date = CURDATE()
     ORDER BY a.start_time LIMIT 12"
);
$stmt->execute();
$appointments = $stmt->fetchAll();
$serving = $pdo->query(
    "SELECT q.queue_number, u.full_name, d.full_name AS dentist_name
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN users u ON u.id = a.patient_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE q.queue_date = CURDATE() AND q.status = 'serving'
     ORDER BY q.updated_at DESC LIMIT 1"
)->fetch();
$next_waiting = $pdo->query(
    "SELECT queue_number FROM queue_entries
     WHERE queue_date = CURDATE() AND status = 'waiting'
     ORDER BY queue_number LIMIT 1"
)->fetchColumn();
$dentists_today = $pdo->query(
    "SELECT d.id, d.full_name,
            EXISTS (SELECT 1 FROM queue_entries q
                    JOIN appointments a ON a.id = q.appointment_id
                    WHERE a.dentist_id = d.id AND q.queue_date = CURDATE() AND q.status = 'serving') AS in_procedure
     FROM dentists d
     WHERE d.is_active = 1 AND EXISTS (
         SELECT 1 FROM schedules s WHERE s.dentist_id = d.id
           AND s.day_of_week = DAYOFWEEK(CURDATE()) - 1 AND s.is_active = 1
     ) ORDER BY d.full_name"
)->fetchAll();

require __DIR__ . '/../includes/views/admin/dashboard.php';
