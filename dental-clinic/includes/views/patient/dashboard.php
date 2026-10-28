<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Patient portal</p><h1>Good to see you, <em><?= e(current_user_name()) ?>.</em></h1><p>Here is your care at a glance.</p></div>
    <span class="dashboard-date"><?= e(format_date(today_string())) ?></span>
</section>
<section class="stat-grid" aria-label="Appointment summary">
    <article class="stat-item stat-highlight"><span>Upcoming</span><strong><?= (int) ($summary['upcoming_count'] ?? 0) ?></strong><small>appointments ahead</small></article>
    <article class="stat-item"><span>Completed</span><strong><?= (int) ($summary['completed_count'] ?? 0) ?></strong><small>visits with our team</small></article>
    <article class="stat-item"><span>All appointments</span><strong><?= (int) ($summary['total'] ?? 0) ?></strong><small>in your care history</small></article>
</section>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Coming up</p><h2>Your appointments</h2></div><a class="text-link" href="<?= e(url('patient/book.php')) ?>">Book a visit</a></div>
    <?php if ($upcoming): ?>
        <div class="appointment-list">
            <?php foreach ($upcoming as $appointment): ?>
                <article class="appointment-row">
                    <div class="appointment-date"><strong><?= e(date('d', strtotime($appointment['appointment_date']))) ?></strong><span><?= e(date('M', strtotime($appointment['appointment_date']))) ?></span></div>
                    <div class="appointment-details"><h3><?= e($appointment['service_name']) ?></h3><p><?= e($appointment['dentist_name']) ?> <span aria-hidden="true">&middot;</span> <?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></p></div>
                    <?= status_badge($appointment['status']) ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><p>You do not have an upcoming appointment yet.</p><span>When your next visit is scheduled, it will appear here.</span></div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>