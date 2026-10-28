<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>Dashboard</h1></div>
</section>
<section class="stat-grid operations-stat-grid" aria-label="Today's clinic totals">
    <article class="stat-item"><span>Appointments today</span><strong><?= (int) $summary['appointments_today'] ?></strong></article>
    <article class="stat-item"><span>Waiting now</span><strong><?= (int) $summary['waiting_now'] ?></strong></article>
    <article class="stat-item"><span>Average wait</span><strong><?= (int) $summary['average_wait'] ?> min</strong></article>
    <article class="stat-item"><span>No-shows</span><strong><?= (int) $summary['no_shows'] ?></strong></article>
</section>
<div class="operations-dashboard-grid">
    <section class="operations-table-panel">
        <div class="content-heading"><div><p class="eyebrow">Today</p><h2>Today's appointments</h2></div><a class="small-link" href="<?= e(url('staff/appointments.php')) ?>">View schedule</a></div>
        <?php if ($appointments): ?><div class="table-wrap"><table class="data-table"><thead><tr><th>Time</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead><tbody><?php foreach ($appointments as $appointment): ?><tr><td><?= e(format_time($appointment['start_time'])) ?></td><td><?= e($appointment['patient_name']) ?></td><td><?= e($appointment['service_name']) ?></td><td><?= e($appointment['dentist_name']) ?></td><td><?= e(status_label($appointment['queue_status'] ?: $appointment['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty-state"><p>No appointments scheduled for today.</p></div><?php endif; ?>
    </section>
    <aside class="operations-dashboard-rail">
        <section class="now-serving-panel"><p>Now serving</p><strong><?= $serving ? e(format_queue_number($serving['queue_number'])) : '—' ?></strong><?php if ($serving): ?><span><?= e($serving['full_name']) ?> · <?= e($serving['dentist_name']) ?></span><?php endif; ?><?php if ($next_waiting): ?><a class="button button-queue-next" href="<?= e(url('staff/queue.php')) ?>">Call next · <?= e(format_queue_number($next_waiting)) ?></a><?php else: ?><span class="queue-empty-note">No patients waiting</span><?php endif; ?></section>
        <section class="dentists-today-panel"><h2>Dentists today</h2><?php foreach ($dentists_today as $dentist): ?><div><span><?= e($dentist['full_name']) ?></span><strong class="<?= (int) $dentist['in_procedure'] ? 'dentist-busy' : 'dentist-available' ?>"><?= (int) $dentist['in_procedure'] ? 'In procedure' : 'Available' ?></strong></div><?php endforeach; ?><?php if (!$dentists_today): ?><p class="calendar-next-visit">No providers scheduled today.</p><?php endif; ?></section>
    </aside>
</div>
<?php require __DIR__ . '/../../footer.php'; ?>