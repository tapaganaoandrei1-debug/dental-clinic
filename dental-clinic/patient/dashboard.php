<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_PATIENT);

$page_title = 'Your dashboard';
$active_nav = 'dashboard';
$stmt = $pdo->prepare(
    "SELECT a.appointment_date, a.start_time, a.end_time, a.status,
            s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.patient_id = ? AND a.appointment_date >= CURDATE()
       AND a.status IN ('pending', 'confirmed')
     ORDER BY a.appointment_date, a.start_time LIMIT 6"
);
$stmt->execute([current_user_id()]);
$upcoming = $stmt->fetchAll();
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(status IN ('pending', 'confirmed') AND appointment_date >= CURDATE()) AS upcoming_count,
            SUM(status = 'completed') AS completed_count
     FROM appointments WHERE patient_id = ?"
);
$stmt->execute([current_user_id()]);
$summary = $stmt->fetch();

require __DIR__ . '/../includes/header.php';
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
<?php require __DIR__ . '/../includes/footer.php'; ?>