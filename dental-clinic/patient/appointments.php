<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'My appointments';
$active_nav = 'appointments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $appointment_id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $reason = post_value('cancellation_reason');
    try {
        if (post_value('action') !== 'cancel' || !$appointment_id) {
            throw new DomainException('Choose a valid appointment to cancel.');
        }
        cancel_patient_appointment(current_user_id(), (int) $appointment_id, $reason);
        set_flash('success', 'Your appointment was cancelled.');
    } catch (DomainException $exception) {
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Appointment cancellation failed: ' . $exception->getMessage());
        set_flash('error', 'We could not cancel that appointment. Please try again.');
    }
    redirect('patient/appointments.php');
}

$stmt = $pdo->prepare(
    'SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status,
            a.cancellation_reason, a.patient_notes, s.name AS service_name,
            s.price, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ?
     ORDER BY a.appointment_date DESC, a.start_time DESC'
);
$stmt->execute([current_user_id()]);
$appointments = $stmt->fetchAll();
$upcoming_appointment = null;
foreach ($appointments as $appointment) {
    if ($appointment['appointment_date'] >= today_string() && in_array($appointment['status'], ['pending', 'confirmed'], true)) {
        $upcoming_appointment = $appointment;
    }
}
$stmt = $pdo->prepare(
    "SELECT subject, message, status, created_at
     FROM notifications
     WHERE user_id = ? AND type IN ('confirmation', 'reminder', 'reschedule', 'cancellation')
     ORDER BY created_at DESC LIMIT 3"
);
$stmt->execute([current_user_id()]);
$recent_notifications = $stmt->fetchAll();
$google_calendar_url = '';
$outlook_calendar_url = '';
if ($upcoming_appointment) {
    $appointment_start = new DateTime($upcoming_appointment['appointment_date'] . ' ' . $upcoming_appointment['start_time'], new DateTimeZone('Asia/Manila'));
    $appointment_end = new DateTime($upcoming_appointment['appointment_date'] . ' ' . $upcoming_appointment['end_time'], new DateTimeZone('Asia/Manila'));
    $calendar_summary = $upcoming_appointment['service_name'] . ' - ' . $upcoming_appointment['dentist_name'];
    $google_calendar_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($calendar_summary)
        . '&dates=' . $appointment_start->format('Ymd\THis') . '/' . $appointment_end->format('Ymd\THis') . '&ctz=Asia%2FManila';
    $outlook_calendar_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . rawurlencode($calendar_summary)
        . '&startdt=' . rawurlencode($appointment_start->format('Y-m-d\TH:i:sP')) . '&enddt=' . rawurlencode($appointment_end->format('Y-m-d\TH:i:sP'));
}

require __DIR__ . '/../includes/views/patient/appointments.php';
