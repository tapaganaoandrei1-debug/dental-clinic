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

require __DIR__ . '/../includes/header.php';
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
<?php require __DIR__ . '/../includes/footer.php'; ?>