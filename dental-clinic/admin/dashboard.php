<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'Clinic overview';
$active_nav = 'dashboard';
$today = today_string();
$summary = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) AS appointments_today,
        (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting') AS waiting_now,
        (SELECT COALESCE(ROUND(AVG(estimated_wait_minutes)), 0) FROM queue_entries WHERE queue_date = CURDATE() AND status IN ('waiting', 'called')) AS average_wait,
        (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'no_show') AS no_shows"
)->fetch();
$stmt = $pdo->prepare(
    "SELECT a.start_time, a.status, u.full_name AS patient_name,
            s.name AS service_name, d.full_name AS dentist_name, q.status AS queue_status
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     LEFT JOIN queue_entries q ON q.appointment_id = a.id
     WHERE a.appointment_date = CURDATE()
     ORDER BY a.start_time LIMIT 12"
);
$stmt->execute();
$appointments = $stmt->fetchAll();
$serving = $pdo->query(
    "SELECT q.queue_number, u.full_name, d.full_name AS dentist_name
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN users u ON u.id = a.patient_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE q.queue_date = CURDATE() AND q.status = 'serving'
     ORDER BY q.updated_at DESC LIMIT 1"
)->fetch();
$next_waiting = $pdo->query(
    "SELECT queue_number FROM queue_entries
     WHERE queue_date = CURDATE() AND status = 'waiting'
     ORDER BY queue_number LIMIT 1"
)->fetchColumn();
$dentists_today = $pdo->query(
    "SELECT d.id, d.full_name,
            EXISTS (SELECT 1 FROM queue_entries q
                    JOIN appointments a ON a.id = q.appointment_id
                    WHERE a.dentist_id = d.id AND q.queue_date = CURDATE() AND q.status = 'serving') AS in_procedure
     FROM dentists d
     WHERE d.is_active = 1 AND EXISTS (
         SELECT 1 FROM schedules s WHERE s.dentist_id = d.id
           AND s.day_of_week = DAYOFWEEK(CURDATE()) - 1 AND s.is_active = 1
     ) ORDER BY d.full_name"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
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
<?php require __DIR__ . '/../includes/footer.php'; ?>