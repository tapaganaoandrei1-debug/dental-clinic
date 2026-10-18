<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$appointment_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$stmt = $pdo->prepare(
    'SELECT a.appointment_date, a.start_time, a.end_time, a.status,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.id = ? AND a.patient_id = ?'
);
$stmt->execute([$appointment_id, current_user_id()]);
$appointment = $stmt->fetch();
if (!$appointment) {
    http_response_code(404);
    exit('Appointment not found.');
}

function ics_escape(string $value): string
{
    return str_replace(["\\", ";", ",", "\r\n", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", "\\n", "\\n"], $value);
}

$start = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time'], new DateTimeZone('Asia/Manila'));
$end = new DateTime($appointment['appointment_date'] . ' ' . $appointment['end_time'], new DateTimeZone('Asia/Manila'));
$uid = 'appointment-' . (int) $appointment_id . '@dentalclinic.local';
$summary = ics_escape($appointment['service_name'] . ' - ' . $appointment['dentist_name']);
$description = ics_escape('Appointment status: ' . status_label($appointment['status']));
$calendar = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Dental Clinic Online//Appointments//EN\r\nBEGIN:VEVENT\r\nUID:" . $uid . "\r\nDTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\nDTSTART;TZID=Asia/Manila:" . $start->format('Ymd\THis') . "\r\nDTEND;TZID=Asia/Manila:" . $end->format('Ymd\THis') . "\r\nSUMMARY:" . $summary . "\r\nDESCRIPTION:" . $description . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="dental-appointment-' . (int) $appointment_id . '.ics"');
echo $calendar;