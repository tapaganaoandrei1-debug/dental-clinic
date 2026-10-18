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

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Front desk</p><h1>New walk-in booking</h1><p>Check an existing patient in for an available time today.</p></div>
    <a class="text-link" href="<?= e(url('staff/queue.php')) ?>">Back to queue</a>
</section>
<section class="walkin-layout">
    <form class="walkin-form" method="post" action="<?= e(url('staff/walk-in.php')) ?>" data-availability-url="<?= e(url('api/availability.php')) ?>" data-today="<?= e($today) ?>">
        <?= csrf_field() ?>
        <?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <label for="patient_id">Patient</label><select id="patient_id" name="patient_id" required><option value="">Choose a patient</option><?php foreach ($patients as $patient): ?><option value="<?= (int) $patient['id'] ?>"<?= $patient_id === (int) $patient['id'] ? ' selected' : '' ?>><?= e($patient['full_name']) ?> · <?= e($patient['email']) ?></option><?php endforeach; ?></select>
        <label for="service_id">Service</label><select id="service_id" name="service_id" required><option value="">Choose a service</option><?php foreach ($services as $service): ?><option value="<?= (int) $service['id'] ?>"<?= $service_id === (int) $service['id'] ? ' selected' : '' ?>><?= e($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min</option><?php endforeach; ?></select>
        <label for="dentist_id">Dentist</label><select id="dentist_id" name="dentist_id" required><option value="0">Any available dentist</option><?php foreach ($dentists as $dentist): ?><option value="<?= (int) $dentist['id'] ?>" data-services="<?= e($dentist['service_ids']) ?>"<?= $dentist_id === (int) $dentist['id'] ? ' selected' : '' ?>><?= e($dentist['full_name']) ?></option><?php endforeach; ?></select>
        <div class="walkin-date"><span>Date</span><strong><?= e(format_date($today)) ?></strong></div>
        <fieldset class="slot-fieldset"><legend>Open times today</legend><div class="slot-options" id="walkin-slots" aria-live="polite"><span class="slot-hint">Choose a service to see open times.</span></div><input type="hidden" name="appointment_time" id="appointment_time" value="<?= e($appointment_time) ?>"></fieldset>
        <label for="patient_notes">Notes <span class="optional-label">Optional</span></label><textarea id="patient_notes" name="patient_notes" rows="3" maxlength="2000"><?= e($notes) ?></textarea>
        <button class="button button-primary button-wide" type="submit" id="walkin-submit" disabled>Check in and issue token</button>
    </form>
    <aside class="walkin-summary"><p class="eyebrow">Queue check-in</p><h2>Reserve the visit and queue spot together.</h2><p>The chosen time is revalidated before saving. The patient will be confirmed and assigned today’s next queue number.</p></aside>
</section>
<script src="<?= e(url('assets/js/walk-in.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>