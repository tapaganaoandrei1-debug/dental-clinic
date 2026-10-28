<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Front desk</p><h1>Appointments in <em>motion.</em></h1><p>Review requests, confirm visits and record outcomes.</p></div>
    <a class="text-link" href="<?= e(url('staff/dashboard.php')) ?>">Back to overview</a>
</section>
<form class="report-filters" method="get" action="<?= e(url('staff/appointments.php')) ?>">
    <label>Date<input type="date" name="date" value="<?= e($selected_date) ?>" required></label>
    <label>Status<select name="status"><option value="">All statuses</option><?php foreach (APPOINTMENT_STATUSES as $status): ?><option value="<?= e($status) ?>"<?= $selected_status === $status ? ' selected' : '' ?>><?= e(status_label($status)) ?></option><?php endforeach; ?></select></label>
    <button class="button button-primary" type="submit">Filter</button>
</form>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Schedule</p><h2><?= count($appointments) ?> appointments</h2></div></div>
    <?php if ($appointments): ?>
        <div class="appointment-control-list">
            <?php foreach ($appointments as $appointment): ?>
                <article class="appointment-control-row">
                    <time><?= e(format_time($appointment['start_time'])) ?><span><?= e(format_time($appointment['end_time'])) ?></span></time>
                    <div class="appointment-details"><div class="visit-title"><h3><?= e($appointment['patient_name']) ?></h3><?= status_badge($appointment['status']) ?></div><p><?= e($appointment['service_name']) ?> · <?= e($appointment['dentist_name']) ?></p><p><?= e($appointment['email']) ?></p><?php if ($appointment['staff_notes']): ?><p class="visit-note"><?= e($appointment['staff_notes']) ?></p><?php endif; ?></div>
                    <?php $next_statuses = APPOINTMENT_TRANSITIONS[$appointment['status']] ?? []; ?>
                    <?php if ($next_statuses): ?>
                        <form class="appointment-update-form" method="post" action="<?= e(url('staff/appointments.php?date=' . rawurlencode($selected_date) . ($selected_status !== '' ? '&status=' . rawurlencode($selected_status) : ''))) ?>">
                            <?= csrf_field() ?><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>">
                            <label class="sr-only" for="notes-<?= (int) $appointment['id'] ?>">Staff note</label><input id="notes-<?= (int) $appointment['id'] ?>" type="text" name="staff_notes" maxlength="2000" placeholder="Optional staff note">
                            <div class="appointment-actions"><?php foreach ($next_statuses as $next_status): ?><button class="small-link<?= in_array($next_status, ['cancelled', 'no_show'], true) ? ' danger-link' : '' ?>" type="submit" name="next_status" value="<?= e($next_status) ?>"><?= e(status_label($next_status)) ?></button><?php endforeach; ?></div>
                        </form>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?><div class="empty-state"><p>No appointments match this date and status.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>