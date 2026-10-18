<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Live queue';
$active_nav = 'queue';
$stmt = $pdo->prepare(
        "SELECT q.id, q.queue_number, q.status, a.start_time,
                        s.name AS service_name, d.full_name AS dentist_name,
                        (SELECT COUNT(*) FROM queue_entries q2
                         WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
                             AND q2.status IN ('waiting', 'called', 'serving')) AS ahead_count,
                        (SELECT COALESCE(SUM(s2.duration_minutes), 0) FROM queue_entries q2
                         JOIN appointments a2 ON a2.id = q2.appointment_id
                         JOIN services s2 ON s2.id = a2.service_id
                         WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
                             AND q2.status IN ('waiting', 'called', 'serving')) AS wait_estimate,
                        (SELECT q3.queue_number FROM queue_entries q3
                         WHERE q3.queue_date = q.queue_date AND q3.status = 'serving'
                         ORDER BY q3.updated_at DESC LIMIT 1) AS now_serving
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
         WHERE a.patient_id = ? AND q.queue_date = CURDATE()
             AND q.status IN ('waiting', 'called', 'serving')
         ORDER BY q.queue_number"
);
$stmt->execute([current_user_id()]);
$entries = $stmt->fetchAll();
$entry = $entries[0] ?? null;
$position = $entry ? (int) $entry['ahead_count'] + 1 : 0;
$progress = $entry ? max(10, min(100, (int) round(100 / max(1, $position + 1)))) : 0;
$stmt = $pdo->prepare(
    "SELECT subject, message, status, created_at
     FROM notifications
     WHERE user_id = ? AND type IN ('confirmation', 'reminder', 'reschedule', 'cancellation')
     ORDER BY created_at DESC LIMIT 3"
);
$stmt->execute([current_user_id()]);
$notifications = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 1"
);
$stmt->execute([current_user_id()]);
$upcoming = $stmt->fetch();
$google_calendar_url = '';
$outlook_calendar_url = '';
if ($upcoming) {
    $calendar_start = new DateTime($upcoming['appointment_date'] . ' ' . $upcoming['start_time'], new DateTimeZone('Asia/Manila'));
    $calendar_end = new DateTime($upcoming['appointment_date'] . ' ' . $upcoming['end_time'], new DateTimeZone('Asia/Manila'));
    $calendar_title = $upcoming['service_name'] . ' - ' . $upcoming['dentist_name'];
    $google_calendar_url = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode($calendar_title)
        . '&dates=' . $calendar_start->format('Ymd\THis') . '/' . $calendar_end->format('Ymd\THis') . '&ctz=Asia%2FManila';
    $outlook_calendar_url = 'https://outlook.live.com/calendar/0/deeplink/compose?subject=' . rawurlencode($calendar_title)
        . '&startdt=' . rawurlencode($calendar_start->format('Y-m-d\TH:i:sP')) . '&enddt=' . rawurlencode($calendar_end->format('Y-m-d\TH:i:sP'));
}

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Patient portal · Live</p><h1>My queue</h1><p>Your digital token and estimated wait.</p></div>
    <span class="dashboard-date" id="queue-refresh-state">Live</span>
</section>
<section class="patient-queue-view" id="patient-queue" data-queue-url="<?= e(url('api/queue-status.php')) ?>">
    <?php if ($entry): ?>
        <article class="queue-token-card" data-entry-id="<?= (int) $entry['id'] ?>">
            <div class="queue-token-top"><span>Your digital token</span><span class="live-pill">Live</span></div>
            <strong class="queue-token-number"><?= e(format_queue_number($entry['queue_number'])) ?></strong>
            <p class="queue-token-service"><?= e($entry['service_name']) ?> · Today, <?= e(format_time($entry['start_time'])) ?></p>
            <div class="queue-stats"><div><span>Position</span><strong class="queue-position"><?= e($position . ($position === 1 ? 'st' : ($position === 2 ? 'nd' : ($position === 3 ? 'rd' : 'th')))) ?></strong></div><div><span>Ahead of you</span><strong class="queue-ahead"><?= (int) $entry['ahead_count'] ?></strong></div><div><span>Est. wait</span><strong><b class="queue-wait"><?= (int) $entry['wait_estimate'] ?></b> min</strong></div></div>
            <div class="queue-progress-track"><span style="width: <?= $progress ?>%"></span></div>
            <p class="queue-now-serving">Now serving <strong class="queue-serving-number"><?= $entry['now_serving'] ? e(format_queue_number($entry['now_serving'])) : 'No one yet' ?></strong></p>
        </article>
        <label class="queue-alert-toggle"><span><strong>Alert me when 2 ahead</strong><small>Notify me as your turn gets close.</small></span><input id="queue-alert-toggle" type="checkbox"><span class="toggle-track" aria-hidden="true"></span></label>
    <?php else: ?>
        <div class="empty-state queue-empty"><p>You are not in today's queue.</p><span>After clinic check-in, your queue token and wait estimate will appear here.</span></div>
    <?php endif; ?>
</section>
<div class="patient-queue-support">
    <section class="visit-support-panel">
        <div class="content-heading"><div><p class="eyebrow">Notifications</p><h2>Recent updates</h2></div><a class="small-link" href="<?= e(url('patient/alerts.php')) ?>">All alerts</a></div>
        <?php if ($notifications): ?><div class="patient-notification-list compact-notifications"><?php foreach ($notifications as $notification): ?><article class="patient-notification-item"><span class="alert-type-mark" aria-hidden="true"></span><div><h3><?= e($notification['subject'] ?: 'Appointment update') ?></h3><p><?= e($notification['message']) ?></p><time><?= e(format_datetime($notification['created_at'])) ?></time></div></article><?php endforeach; ?></div><?php else: ?><p class="calendar-next-visit">Booking confirmations and reminders will appear here.</p><?php endif; ?>
    </section>
    <div class="queue-side-stack">
        <?php if ($upcoming): ?><section class="queue-upcoming-panel"><p class="eyebrow">Upcoming</p><h2><?= e(format_date($upcoming['appointment_date'])) ?> · <?= e(format_time($upcoming['start_time'])) ?></h2><p><?= e($upcoming['service_name']) ?> · <?= e($upcoming['dentist_name']) ?></p><a class="small-link" href="<?= e(url('patient/appointments.php')) ?>">Manage appointment</a></section><?php endif; ?>
        <section class="visit-support-panel calendar-sync-panel"><p class="eyebrow">Calendar sync</p><h2>Add your booking to a calendar.</h2>
            <?php if ($upcoming): ?><div class="calendar-actions"><a class="button button-outline" href="<?= e($google_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Google Calendar</a><a class="button button-outline" href="<?= e($outlook_calendar_url) ?>" target="_blank" rel="noopener noreferrer">Outlook</a></div><a class="small-link calendar-ics-link" href="<?= e(url('patient/calendar.php?id=' . (int) $upcoming['id'])) ?>">Download .ics file</a><?php else: ?><a class="button button-primary" href="<?= e(url('patient/book.php')) ?>">Book a visit</a><?php endif; ?>
        </section>
    </div>
</div>
<script src="<?= e(url('assets/js/patient-queue.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>