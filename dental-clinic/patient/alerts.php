<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Alerts';
$active_nav = 'alerts';
$stmt = $pdo->prepare(
    "SELECT id, type, subject, message, status, created_at
     FROM notifications
     WHERE user_id = ? AND type IN ('confirmation', 'reminder', 'reschedule', 'cancellation')
     ORDER BY created_at DESC LIMIT 50"
);
$stmt->execute([current_user_id()]);
$notifications = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 1"
);
$stmt->execute([current_user_id()]);
$next_appointment = $stmt->fetch();
$google_calendar_url = '';
$outlook_calendar_url = '';
if ($next_appointment) {
    $start = new DateTime($next_appointment['appointment_date'] . ' ' . $next_appointment['start_time'], new DateTimeZone('Asia/Manila'));
    $end = new DateTime($next_appointment['appointment_date'] . ' ' . $next_appointment['end_time'], new DateTimeZone('Asia/Manila'));
    $summary = $next_appointment['service_name'] . ' - ' . $next_appointment['dentist_name'];
    $google_calendar_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($summary)
        . '&dates=' . $start->format('Ymd\THis') . '/' . $end->format('Ymd\THis')
        . '&ctz=Asia%2FManila&details=' . rawurlencode('Dental Clinic appointment');
    $outlook_calendar_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . rawurlencode($summary)
        . '&startdt=' . rawurlencode($start->format('Y-m-d\TH:i:sP'))
        . '&enddt=' . rawurlencode($end->format('Y-m-d\TH:i:sP'));
}

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Patient portal</p><h1>Alerts &amp; <em>updates.</em></h1><p>Appointment messages and calendar actions in one place.</p></div>
</section>
<div class="patient-alerts-layout">
    <section class="content-section patient-alerts-list">
        <div class="content-heading"><div><p class="eyebrow">Notifications</p><h2><?= count($notifications) ?> updates</h2></div></div>
        <?php if ($notifications): ?>
            <div class="patient-notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <article class="patient-notification-item"><span class="alert-type-mark" aria-hidden="true"></span><div><div class="visit-title"><h3><?= e($notification['subject'] ?: status_label($notification['type'])) ?></h3><?= status_badge($notification['status']) ?></div><p><?= e($notification['message']) ?></p><time><?= e(format_datetime($notification['created_at'])) ?></time></div></article>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="empty-state"><p>No appointment alerts yet.</p><span>Booking confirmations and reminders will show here.</span></div><?php endif; ?>
    </section>
    <aside class="patient-calendar-panel">
        <p class="eyebrow">Calendar sync</p><h2>Add your bookings to the calendar you already use.</h2>
        <?php if ($next_appointment): ?>
            <p class="calendar-next-visit"><?= e(format_date($next_appointment['appointment_date'])) ?> · <?= e(format_time($next_appointment['start_time'])) ?><br><?= e($next_appointment['service_name']) ?> · <?= e($next_appointment['dentist_name']) ?></p>
            <div class="calendar-actions"><a class="button button-outline" href="<?= e($google_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Google Calendar</a><a class="button button-primary" href="<?= e($outlook_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Outlook</a></div>
            <a class="text-link calendar-ics-link" href="<?= e(url('patient/calendar.php?id=' . (int) $next_appointment['id'])) ?>">Download calendar file (.ics)</a>
        <?php else: ?><p class="calendar-next-visit">Your next appointment will appear here.</p><a class="button button-primary" href="<?= e(url('patient/book.php')) ?>">Book a visit</a><?php endif; ?>
    </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>