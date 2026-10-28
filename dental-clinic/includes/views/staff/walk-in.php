<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
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
<?php require __DIR__ . '/../../footer.php'; ?>