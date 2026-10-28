<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>Reports</h1></div>
    <button class="button button-primary" type="button" onclick="window.print()">Export PDF</button>
</section>
<form class="report-filters" method="get" action="<?= e(url('admin/reports.php')) ?>">
    <label>From<input type="date" name="start" value="<?= e($range_start) ?>" required></label>
    <label>Through<input type="date" name="end" value="<?= e($range_end) ?>" required></label>
    <button class="button button-primary" type="submit">Update report</button>
    <a class="button button-outline" href="<?= e(url('admin/reports.php?start=' . rawurlencode($range_start) . '&end=' . rawurlencode($range_end) . '&export=csv')) ?>">Export CSV</a>
</form>
<section class="stat-grid" aria-label="Report summary">
    <article class="stat-item stat-highlight"><span>Appointments</span><strong><?= (int) $summary['total'] ?></strong></article>
    <article class="stat-item"><span>Completed</span><strong><?= (int) $summary['completed'] ?></strong></article>
    <article class="stat-item"><span>Cancelled</span><strong><?= (int) $summary['cancelled'] ?></strong></article>
    <article class="stat-item"><span>Average wait</span><strong><?= (int) $summary['average_wait'] ?> min</strong></article>
</section>
<div class="dashboard-columns report-columns">
    <section class="content-section">
        <div class="content-heading"><div><p class="eyebrow">Demand</p><h2>Appointments by day</h2></div></div>
        <?php if ($daily_summary): ?>
            <div class="report-bars">
                <?php foreach ($daily_summary as $day): $bar_width = (int) round(((int) $day['appointments'] / $peak_day) * 100); ?>
                    <div class="report-bar-row"><time><?= e(date('M j', strtotime($day['appointment_date']))) ?></time><span class="report-bar-track"><span class="report-bar" style="width: <?= $bar_width ?>%"></span></span><strong><?= (int) $day['appointments'] ?></strong></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?><div class="empty-state"><p>No appointments in this date range.</p></div><?php endif; ?>
    </section>
    <section class="content-section">
        <div class="content-heading"><div><p class="eyebrow">Service mix</p><h2>By treatment</h2></div></div>
        <?php if ($service_summary): ?><div class="report-service-list"><?php foreach ($service_summary as $service): ?><article><div><h3><?= e($service['name']) ?></h3><p><?= (int) $service['appointments'] ?> appointments · <?= (int) $service['completed'] ?> completed</p></div><strong><?= e(format_money($service['revenue'])) ?></strong></article><?php endforeach; ?></div><?php else: ?><div class="empty-state"><p>No service activity in this date range.</p></div><?php endif; ?>
    </section>
</div>
<section class="content-section report-detail-section">
    <div class="content-heading"><div><p class="eyebrow">Appointments</p><h2>Selected period</h2></div><span class="subtle-count"><?= e(format_date($range_start)) ?> – <?= e(format_date($range_end)) ?></span></div>
    <?php
    $stmt = $pdo->prepare(
        'SELECT a.appointment_date, a.start_time, u.full_name AS patient_name,
                s.name AS service_name, d.full_name AS dentist_name, a.status
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
         WHERE a.appointment_date BETWEEN ? AND ?
         ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 100'
    );
    $stmt->execute($range_params);
    $report_rows = $stmt->fetchAll();
    ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Date &amp; time</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($report_rows as $row): ?><tr><td><?= e(format_date($row['appointment_date'])) ?><span class="table-subline"><?= e(format_time($row['start_time'])) ?></span></td><td><?= e($row['patient_name']) ?></td><td><?= e($row['service_name']) ?></td><td><?= e($row['dentist_name']) ?></td><td><?= status_badge($row['status']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>