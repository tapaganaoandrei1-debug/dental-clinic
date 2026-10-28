<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Patient portal</p><h1>Book an appointment</h1><p>Choose a service, then pick an open slot. Availability updates in real time.</p></div>
</section>
<nav class="booking-steps" aria-label="Booking steps"><span class="booking-step is-done"><b>1</b> Service</span><i></i><span class="booking-step is-current"><b>2</b> Date &amp; time</span><i></i><span class="booking-step"><b>3</b> Confirm</span></nav>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="booking-form" method="post" action="<?= e(url('patient/book.php' . ($reschedule_id > 0 ? '?reschedule=' . $reschedule_id : ''))) ?>" data-availability-url="<?= e(url('api/availability.php')) ?>" data-reschedule-id="<?= $reschedule_id ?>" data-min-date="<?= e($minimum_date) ?>" data-max-date="<?= e($maximum_date) ?>" data-week-start="<?= e($week_start_date) ?>">
    <?= csrf_field() ?>
    <?php if ($reschedule_id > 0): ?><input type="hidden" name="reschedule_id" value="<?= $reschedule_id ?>"><input type="hidden" name="service_id" value="<?= $selected_service ?>"><input type="hidden" name="dentist_id" value="<?= $selected_dentist ?>"><?php endif; ?>
    <input type="hidden" name="appointment_date" id="appointment_date" value="<?= e($selected_date) ?>" required>
    <input type="hidden" name="appointment_time" id="appointment_time" value="<?= e($selected_time) ?>" required>
    <div class="booking-columns">
        <div class="booking-workflow">
            <section class="booking-stage">
                <h2>1. Service &amp; dentist</h2>
                <div class="service-choice-grid">
                    <?php foreach ($services as $service): ?>
                        <label class="service-choice<?= $selected_service === (int) $service['id'] ? ' is-selected' : '' ?>">
                            <input type="radio" name="service_id" value="<?= (int) $service['id'] ?>"<?= $selected_service === (int) $service['id'] ? ' checked' : '' ?><?= $reschedule_id > 0 ? ' disabled' : '' ?> required>
                            <span class="service-choice-name"><?= e($service['name']) ?></span><span class="service-choice-meta"><?= (int) $service['duration_minutes'] ?> min · <?= e(format_money($service['price'])) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <fieldset class="dentist-choice-group"><legend class="sr-only">Choose a dentist</legend>
                    <label class="dentist-choice<?= $selected_dentist === 0 ? ' is-selected' : '' ?>"><input type="radio" name="dentist_id" value="0"<?= $selected_dentist === 0 ? ' checked' : '' ?><?= $reschedule_id > 0 ? ' disabled' : '' ?>><span>Any available dentist</span></label>
                    <?php foreach ($dentists as $dentist): ?>
                        <label class="dentist-choice<?= $selected_dentist === (int) $dentist['id'] ? ' is-selected' : '' ?>" data-services="<?= e($dentist['service_ids']) ?>"><input type="radio" name="dentist_id" value="<?= (int) $dentist['id'] ?>"<?= $selected_dentist === (int) $dentist['id'] ? ' checked' : '' ?><?= $reschedule_id > 0 ? ' disabled' : '' ?>><span><?= e($dentist['full_name']) ?></span></label>
                    <?php endforeach; ?>
                </fieldset>
            </section>
            <section class="booking-stage date-stage">
                <div class="date-stage-heading"><h2>2. Date &amp; time</h2><div class="week-controls"><button type="button" id="previous-week" aria-label="Previous week">&#8592;</button><span id="week-label"><?= e($week_label) ?></span><button type="button" id="next-week" aria-label="Next week">&#8594;</button></div></div>
                <div class="booking-week" id="booking-week" role="group" aria-label="Choose an appointment date">
                    <?php for ($day = 0; $day < 7; $day++): $date_value = date('Y-m-d', strtotime('+' . $day . ' days', $week_start)); $date_is_valid = $date_value >= $minimum_date && $date_value <= $maximum_date; ?>
                        <button type="button" class="week-day<?= $selected_date === $date_value ? ' is-selected' : '' ?>" data-date="<?= e($date_value) ?>" aria-pressed="<?= $selected_date === $date_value ? 'true' : 'false' ?>"<?= !$date_is_valid ? ' disabled' : '' ?>><span><?= e(date('D', strtotime($date_value))) ?></span><strong><?= e(date('d', strtotime($date_value))) ?></strong></button>
                    <?php endfor; ?>
                </div>
                <fieldset class="slot-fieldset"><legend>Available times</legend><div class="slot-options" id="slot-options" aria-live="polite"><span class="slot-hint">Checking open times...</span></div></fieldset>
                <details class="booking-notes"><summary>Add a note for the care team</summary><textarea id="patient_notes" name="patient_notes" maxlength="2000" rows="3" placeholder="Anything you would like the care team to know?"><?= e($patient_notes) ?></textarea></details>
            </section>
        </div>
        <aside class="booking-aside">
            <img class="booking-clinic-photo" src="https://images.unsplash.com/photo-1606811971618-4486d14f3f99?auto=format&amp;fit=crop&amp;w=900&amp;q=80" alt="Patient receiving dental care">
            <div class="booking-summary">
                <p class="eyebrow">Your booking</p><h2>Almost there.</h2>
                <dl><div><dt>Service</dt><dd id="summary-service"><?= e($selected_service_name ?: 'Choose a service') ?></dd></div><div><dt>Dentist</dt><dd id="summary-dentist"><?= e($selected_dentist_name) ?></dd></div><div><dt>Date</dt><dd id="summary-date"><?= e(format_date($selected_date)) ?></dd></div><div><dt>Time</dt><dd id="summary-time"><?= $selected_time ? e(format_time($selected_time)) : 'Choose a time' ?></dd></div></dl>
                <button class="button button-primary button-wide" id="booking-submit" type="submit" disabled><?= $reschedule_id > 0 ? 'Confirm new time' : 'Confirm booking' ?></button>
                <p class="booking-footnote"><?= $reschedule_id > 0 ? 'The clinic will confirm your new time.' : 'Your request is sent to the clinic for confirmation.' ?></p>
            </div>
        </aside>
    </div>
</form>
</section>
<script src="<?= e(url('assets/js/booking.js')) ?>" defer></script>
<?php require __DIR__ . '/../../footer.php'; ?>