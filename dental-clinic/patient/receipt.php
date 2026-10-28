<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$appointment_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$stmt = $pdo->prepare(
    'SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status, a.created_at,
            a.cancellation_reason, s.name AS service_name, s.price,
            d.full_name AS dentist_name, u.full_name AS patient_name, u.email
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     JOIN users u ON u.id = a.patient_id
     WHERE a.id = ? AND a.patient_id = ?'
);
$stmt->execute([$appointment_id, current_user_id()]);
$appointment = $stmt->fetch();
if (!$appointment) {
    http_response_code(404);
    exit('Appointment not found.');
}
$clinic_name = get_setting('clinic_name', APP_NAME);
require __DIR__ . '/../includes/views/patient/receipt.php';