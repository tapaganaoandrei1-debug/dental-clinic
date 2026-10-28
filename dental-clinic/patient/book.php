<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/booking.php';
require_role(ROLE_PATIENT);

$page_title = 'Book an appointment';
$active_nav = 'book';
$errors = [];
$selected_service = (int) ($_POST['service_id'] ?? $_GET['service_id'] ?? 0);
$selected_dentist = (int) ($_POST['dentist_id'] ?? $_GET['dentist_id'] ?? 0);
$reschedule_id = (int) ($_POST['reschedule_id'] ?? $_GET['reschedule'] ?? 0);
$selected_date = post_value('appointment_date') ?: get_value('date') ?: date('Y-m-d', strtotime('+1 day'));
$selected_time = post_value('appointment_time');
$patient_notes = post_value('patient_notes');

$services = $pdo->query('SELECT id, name, duration_minutes, price FROM services WHERE is_active = 1 ORDER BY id')->fetchAll();
if ($selected_service < 1 && $services) {
    $selected_service = (int) $services[0]['id'];
}
$dentists = $pdo->query(
    'SELECT d.id, d.full_name, d.specialty, GROUP_CONCAT(ds.service_id) AS service_ids
     FROM dentists d
     JOIN dentist_services ds ON ds.dentist_id = d.id
     JOIN services s ON s.id = ds.service_id AND s.is_active = 1
     WHERE d.is_active = 1
     GROUP BY d.id, d.full_name, d.specialty
     ORDER BY d.full_name'
)->fetchAll();
$minimum_date = today_string();
$maximum_date = date('Y-m-d', strtotime('+' . max(1, (int) get_setting('booking_days_ahead', '60')) . ' days'));
$week_start = strtotime('monday this week', strtotime($selected_date));
$week_start = $week_start === false ? strtotime('monday this week') : $week_start;
$week_start_date = date('Y-m-d', $week_start);
$week_end_date = date('Y-m-d', strtotime('+6 days', $week_start));
$week_label = date('M j', $week_start) . ' - ' . date('M j, Y', strtotime('+6 days', $week_start));
$selected_service_name = '';
$selected_service_duration = 0;
foreach ($services as $service) {
    if ((int) $service['id'] === $selected_service) {
        $selected_service_name = $service['name'];
        $selected_service_duration = (int) $service['duration_minutes'];
        break;
    }
}
$selected_dentist_name = 'Any available';
foreach ($dentists as $dentist) {
    if ((int) $dentist['id'] === $selected_dentist) {
        $selected_dentist_name = $dentist['full_name'];
        break;
    }
}
$reschedule_appointment = null;

if ($reschedule_id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ?');
    $stmt->execute([$reschedule_id, current_user_id()]);
    $reschedule_appointment = $stmt->fetch();
    if (!$reschedule_appointment || !patient_can_change_appointment($reschedule_appointment)) {
        set_flash('error', 'This appointment can no longer be changed online. Contact the clinic for help.');
        redirect('patient/appointments.php');
    }
    $selected_service = (int) $reschedule_appointment['service_id'];
    $selected_dentist = (int) $reschedule_appointment['dentist_id'];
    $selected_dentist_name = '';
    foreach ($dentists as $dentist) {
        if ((int) $dentist['id'] === $selected_dentist) $selected_dentist_name = $dentist['full_name'];
    }
    if ($selected_date === '') {
        $selected_date = $reschedule_appointment['appointment_date'];
    }
}
$selected_service_name = '';
$selected_service_duration = 0;
foreach ($services as $service) {
    if ((int) $service['id'] === $selected_service) {
        $selected_service_name = $service['name'];
        $selected_service_duration = (int) $service['duration_minutes'];
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if ($selected_service < 1 || $selected_dentist < 1 || !is_valid_date($selected_date) || !is_valid_time($selected_time)) {
        $errors[] = 'Choose an available service, dentist, date and time.';
    }
    if (strlen($patient_notes) > 2000) {
        $errors[] = 'Notes must be 2,000 characters or fewer.';
    }

    if (!$errors) {
        try {
            if ($reschedule_appointment) {
                reschedule_patient_appointment(current_user_id(), $reschedule_id, $selected_date, $selected_time);
                set_flash('success', 'Your new appointment time was requested. The clinic will confirm it shortly.');
            } else {
                create_patient_appointment(current_user_id(), $selected_service, $selected_dentist, $selected_date, $selected_time, $patient_notes);
                set_flash('success', 'Your appointment request was sent. The clinic will confirm it shortly.');
            }
            redirect('patient/appointments.php');
        } catch (DomainException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Appointment booking failed: ' . $exception->getMessage());
            $errors[] = 'We could not place that appointment request. Please try again.';
        }
    }
}

require __DIR__ . '/../includes/views/patient/book.php';
