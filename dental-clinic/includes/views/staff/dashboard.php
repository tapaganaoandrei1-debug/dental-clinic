<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Front desk</p><h1>Today at <em>the clinic.</em></h1><p>Appointments and queue activity for <?= e(format_date($today)) ?>.</p></div>
    <span class="dashboard-date"><?= e(current_user_name()) ?></span>
</section>
<section class="stat-grid" aria-label="Today's totals">
    <article class="stat-item stat-highlight"><span>Appointments</span><strong><?= (int) ($summary['appointments'] ?? 0) ?></strong><small>scheduled today</small></article>
    <article class="stat-item"><span>To review</span><strong><?= (int) ($summary['pending'] ?? 0) ?></strong><small>pending confirmation</small></article>
    <article class="stat-item"><span>Confirmed</span><strong><?= (int) ($summary['confirmed'] ?? 0) ?></strong><small>on today's schedule</small></article>
</section>
<div class="dashboard-columns">
    <section class="content-section">
        <div class="content-heading"><div><p class="eyebrow">Live queue</p><h2>Patients today</h2></div><span class="subtle-count"><?= count($queue) ?> in queue</span></div>
        <?php if ($queue): ?>
            <div class="queue-list">
                <?php foreach ($queue as $entry): ?>
                    <article class="queue-row"><span class="queue-number"><?= e(format_queue_number($entry['queue_number'])) ?></span><div class="appointment-details"><h3><?= e($entry['patient_name']) ?></h3><p><?= e($entry['service_name']) ?> <span aria-hidden="true">&middot;</span> <?= e($entry['dentist_name']) ?></p></div><div class="queue-state"><?= status_badge($entry['queue_status']) ?><small><?= (int) $entry['estimated_wait_minutes'] ?> min est.</small></div></article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No active queue entries.</p><span>Checked-in patients will appear here.</span></div>
        <?php endif; ?>
    </section>
    <section class="content-section agenda-section">
        <div class="content-heading"><div><p class="eyebrow">Schedule</p><h2>Today's visits</h2></div></div>
        <?php if ($appointments): ?>
            <div class="agenda-list">
                <?php foreach ($appointments as $appointment): ?>
                    <article class="agenda-row"><time><?= e(format_time($appointment['start_time'])) ?></time><div><h3><?= e($appointment['patient_name']) ?></h3><p><?= e($appointment['service_name']) ?> · <?= e($appointment['dentist_name']) ?></p></div><?= status_badge($appointment['status']) ?></article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state"><p>No appointments scheduled.</p></div>
        <?php endif; ?>
    </section>
</div>
<?php require __DIR__ . '/../../footer.php'; ?>