<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Patient portal</p><h1>My visits</h1><p>Your upcoming care and appointment history.</p></div>
    <a class="button button-primary" href="<?= e(url('patient/book.php')) ?>">Book appointment <span aria-hidden="true">&#8594;</span></a>
</section>
<?php if ($upcoming_appointment): ?>
    <?php $upcoming_can_change = patient_can_change_appointment($upcoming_appointment); ?>
    <section class="visit-spotlight">
        <div><p class="eyebrow">Your next appointment</p><h2><?= e(format_date($upcoming_appointment['appointment_date'])) ?> · <?= e(format_time($upcoming_appointment['start_time'])) ?></h2><p><?= e($upcoming_appointment['service_name']) ?> · <?= e($upcoming_appointment['dentist_name']) ?></p><?= status_badge($upcoming_appointment['status']) ?></div>
        <div class="spotlight-actions">
            <?php if ($upcoming_can_change): ?><a class="button button-outline" href="<?= e(url('patient/book.php?reschedule=' . (int) $upcoming_appointment['id'])) ?>">Reschedule</a><form method="post" action="<?= e(url('patient/appointments.php')) ?>" onsubmit="return confirm('Cancel this appointment?')"><?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $upcoming_appointment['id'] ?>"><input type="hidden" name="action" value="cancel"><button class="button button-danger-outline" type="submit">Cancel</button></form><?php endif; ?>
            <a class="small-link" href="<?= e(url('patient/receipt.php?id=' . (int) $upcoming_appointment['id'])) ?>">View booking record</a>
        </div>
    </section>
<?php endif; ?>
<div class="visit-support-grid">
    <section class="visit-support-panel">
        <div class="content-heading"><div><p class="eyebrow">Notifications</p><h2>Recent updates</h2></div><a class="small-link" href="<?= e(url('patient/alerts.php')) ?>">All alerts</a></div>
        <?php if ($recent_notifications): ?><div class="patient-notification-list compact-notifications"><?php foreach ($recent_notifications as $notification): ?><article class="patient-notification-item"><span class="alert-type-mark" aria-hidden="true"></span><div><h3><?= e($notification['subject'] ?: 'Appointment update') ?></h3><p><?= e($notification['message']) ?></p><time><?= e(format_datetime($notification['created_at'])) ?></time></div></article><?php endforeach; ?></div><?php else: ?><p class="calendar-next-visit">Booking updates and reminders will appear here.</p><?php endif; ?>
    </section>
    <section class="visit-support-panel calendar-sync-panel">
        <p class="eyebrow">Calendar sync</p><h2>Add your booking to your calendar.</h2>
        <?php if ($upcoming_appointment): ?><div class="calendar-actions"><a class="button button-outline" href="<?= e($google_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Google</a><a class="button button-outline" href="<?= e($outlook_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Outlook</a></div><a class="small-link" href="<?= e(url('patient/calendar.php?id=' . (int) $upcoming_appointment['id'])) ?>">Download .ics file</a><?php else: ?><p class="calendar-next-visit">Book your next visit to add it to a calendar.</p><?php endif; ?>
    </section>
</div>
<section class="content-section visits-section">
    <div class="content-heading"><div><p class="eyebrow">All visits</p><h2>Appointment history</h2></div><span class="subtle-count"><?= count($appointments) ?> total</span></div>
    <?php if ($appointments): ?>
        <div class="visit-list">
            <?php foreach ($appointments as $appointment): ?>
                <?php $can_change = patient_can_change_appointment($appointment); ?>
                <article class="visit-item">
                    <div class="appointment-date"><strong><?= e(date('d', strtotime($appointment['appointment_date']))) ?></strong><span><?= e(date('M', strtotime($appointment['appointment_date']))) ?></span></div>
                    <div class="visit-main"><div class="visit-title"><h3><?= e($appointment['service_name']) ?></h3><?= status_badge($appointment['status']) ?></div><p><?= e(format_date($appointment['appointment_date'])) ?> · <?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></p><p><?= e($appointment['dentist_name']) ?> · <?= e(format_money($appointment['price'])) ?></p><?php if ($appointment['cancellation_reason']): ?><p class="visit-note">Cancellation: <?= e($appointment['cancellation_reason']) ?></p><?php endif; ?></div>
                    <div class="visit-actions">
                        <a class="small-link" href="<?= e(url('patient/receipt.php?id=' . (int) $appointment['id'])) ?>">Receipt</a>
                        <a class="small-link" href="<?= e(url('patient/calendar.php?id=' . (int) $appointment['id'])) ?>">Add to calendar</a>
                        <?php if ($can_change): ?>
                            <a class="small-link" href="<?= e(url('patient/book.php?reschedule=' . (int) $appointment['id'])) ?>">Reschedule</a>
                            <form method="post" action="<?= e(url('patient/appointments.php')) ?>" onsubmit="return confirm('Cancel this appointment?')">
                                <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><input type="hidden" name="action" value="cancel">
                                <button class="small-link danger-link" type="submit">Cancel</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><p>No appointments in your history yet.</p><span>Choose a service to request your first visit.</span></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>