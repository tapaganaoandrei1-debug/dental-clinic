<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/booking.php';
require_role_api([ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN]);

$service_id = filter_var($_GET['service_id'] ?? null, FILTER_VALIDATE_INT);
$dentist_id = filter_var($_GET['dentist_id'] ?? null, FILTER_VALIDATE_INT);
$exclude_appointment_id = filter_var($_GET['exclude_appointment_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$date = get_value('date');

if (!$service_id || $dentist_id === false || $dentist_id < 0 || !is_valid_date($date)) {
    json_response(['ok' => false, 'error' => 'Choose a service, dentist and valid date.'], 400);
}

json_response([
    'ok' => true,
    'slots' => available_appointment_slots((int) $service_id, (int) $dentist_id, $date, (int) $exclude_appointment_id),
]);