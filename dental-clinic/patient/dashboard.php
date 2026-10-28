<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Your dashboard';
$active_nav = 'dashboard';
$stmt = $pdo->prepare(
    "SELECT a.appointment_date, a.start_time, a.end_time, a.status,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 6"
);
$stmt->execute([current_user_id()]);
$upcoming = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status IN ('pending', 'confirmed') AND appointment_date >= CURDATE()) AS upcoming_count,
            SUM(status = 'completed') AS completed_count
     FROM appointments WHERE patient_id = ?"
);
$stmt->execute([current_user_id()]);
$summary = $stmt->fetch();

require __DIR__ . '/../includes/views/patient/dashboard.php';
