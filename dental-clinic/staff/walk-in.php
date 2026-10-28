<?php
require_once __DIR__ . '/../config/config.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'New walk-in booking';
$active_nav = 'staff-appointments';
$errors = [];
$patient_id = (int) ($_POST['patient_id'] ?? 0);
$service_id = (int) ($_POST['service_id'] ?? 0);
$dentist_id = (int) ($_POST['dentist_id'] ?? 0);
$appointment_time = post_value('appointment_time');
$notes = post_value('patient_notes');
$patients = $pdo->query("SELECT id, full_name, email FROM users WHERE role = 'patient' AND is_active = 1 AND email_verified_at IS NOT NULL ORDER BY full_name")->fetchAll();
$services = $pdo->query('SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 ORDER BY id')->fetchAll();
$dentists = $pdo->query(
    'SELECT d.id, d.full_name, GROUP_CONCAT(ds.service_id) AS service_ids
     FROM dentists d JOIN dentist_services ds ON ds.dentist_id = d.id
     JOIN services s ON s.id = ds.service_id AND s.is_active = 1
     WHERE d.is_active = 1 GROUP BY d.id, d.full_name ORDER BY d.full_name'
)->fetchAll();
$today = today_string();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$patient_id || !$service_id || !is_valid_time($appointment_time)) {
        $errors[] = 'Choose a patient, service, dentist and available time.';
    }
    try {
        if (!$errors) {
            $result = create_walk_in_appointment($patient_id, $service_id, $dentist_id, $today, $appointment_time, $notes, current_user_id());
            set_flash('success', 'Walk-in checked in as ' . format_queue_number($result['queue_number']) . '.');
            redirect('staff/queue.php');
        }
    } catch (DomainException $exception) {
        $errors[] = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('Walk-in booking failed: ' . $exception->getMessage());
        $errors[] = 'The walk-in could not be booked. Please choose another time.';
    }
}

require __DIR__ . '/../includes/views/staff/walk-in.php';
