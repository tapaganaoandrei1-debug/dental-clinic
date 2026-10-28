<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
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
<?php require __DIR__ . '/../../footer.php'; ?>