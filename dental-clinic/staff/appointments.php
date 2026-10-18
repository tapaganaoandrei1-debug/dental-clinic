<?php
require_once __DIR__ . '/../config/config.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'Appointment control';
$active_nav = 'staff-appointments';
$date_filter = get_value('date');
$selected_date = $date_filter ?: today_string();
$selected_status = get_value('status');
$search = get_value('q');
if (!is_valid_date($selected_date)) $selected_date = today_string();
if (!is_valid_date($date_filter)) $date_filter = '';
if (!in_array($selected_status, APPOINTMENT_STATUSES, true)) $selected_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $appointment_id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
    $next_status = post_value('next_status');
    $staff_notes = post_value('staff_notes');
    try {
        if (!$appointment_id || strlen($staff_notes) > 2000) {
            throw new DomainException('Choose a valid appointment and keep notes under 2,000 characters.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            'SELECT a.*, s.name AS service_name, u.email
             FROM appointments a
             JOIN services s ON s.id = a.service_id
             JOIN users u ON u.id = a.patient_id
             WHERE a.id = ? FOR UPDATE'
        );
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if (!$appointment || !in_array($next_status, APPOINTMENT_TRANSITIONS[$appointment['status']] ?? [], true)) {
            throw new DomainException('That appointment status change is not allowed.');
        }

        if ($next_status === 'confirmed') {
            $day = (int) date('w', strtotime($appointment['appointment_date']));
            $stmt = $pdo->prepare('SELECT id FROM schedules WHERE dentist_id = ? AND day_of_week = ? FOR UPDATE');
            $stmt->execute([$appointment['dentist_id'], $day]);
            if (!$stmt->fetch()) throw new DomainException('The dentist no longer has a schedule for this date.');
            $stmt = $pdo->prepare(
                "SELECT id FROM appointments
                 WHERE dentist_id = ? AND appointment_date = ? AND id <> ?
                   AND status IN ('pending', 'confirmed', 'completed')
                   AND start_time < ? AND end_time > ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$appointment['dentist_id'], $appointment['appointment_date'], $appointment_id, $appointment['end_time'], $appointment['start_time']]);
            if ($stmt->fetch()) throw new DomainException('This time conflicts with another appointment. Resolve the conflict before confirming.');
        }

        $stmt = $pdo->prepare(
            'UPDATE appointments SET status = ?, staff_notes = COALESCE(NULLIF(?, \'\'), staff_notes),
             cancellation_reason = CASE WHEN ? = \'cancelled\' AND ? <> \'\' THEN ? ELSE cancellation_reason END
             WHERE id = ?'
        );
        $stmt->execute([$next_status, $staff_notes, $next_status, $staff_notes, $staff_notes, $appointment_id]);
        if ($next_status === 'confirmed' || $next_status === 'cancelled') {
            $type = $next_status === 'confirmed' ? 'confirmation' : 'cancellation';
            $subject = $next_status === 'confirmed' ? 'Appointment confirmed' : 'Appointment cancelled';
            $message = 'Your ' . $appointment['service_name'] . ' appointment for ' . format_date($appointment['appointment_date']) . ' at ' . format_time($appointment['start_time']) . ' is ' . $next_status . '.';
            $stmt = $pdo->prepare(
                'INSERT INTO notifications (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
                 VALUES (?, ?, \'email\', ?, ?, ?, ?, NOW(), \'queued\')'
            );
            $stmt->execute([$appointment['patient_id'], $appointment_id, $type, $appointment['email'], $subject, $message]);
        }
        log_activity('appointment_' . $next_status, 'Changed appointment #' . $appointment_id . ' to ' . $next_status);
        $pdo->commit();
        set_flash('success', 'Appointment updated.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Appointment update failed: ' . $exception->getMessage());
        set_flash('error', 'The appointment could not be updated.');
    }
    redirect('staff/appointments.php?date=' . rawurlencode($selected_date) . ($selected_status !== '' ? '&status=' . rawurlencode($selected_status) : ''));
}

$query =
    'SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status, a.staff_notes,
            u.full_name AS patient_name, u.email, s.name AS service_name, d.full_name AS dentist_name
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE 1 = 1';
$params = [];
if ($date_filter !== '') {
    $query .= ' AND a.appointment_date = ?';
    $params[] = $date_filter;
} elseif ($search === '') {
    $query .= ' AND a.appointment_date = CURDATE()';
}
if ($selected_status !== '') {
    $query .= ' AND a.status = ?';
    $params[] = $selected_status;
}
$search = substr($search, 0, 100);
if ($search !== '') {
    $query .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR s.name LIKE ? OR d.full_name LIKE ? OR EXISTS (SELECT 1 FROM queue_entries q WHERE q.appointment_id = a.id AND CONCAT("Q-", LPAD(q.queue_number, 3, "0")) LIKE ?))';
    $search_term = '%' . $search . '%';
    array_push($params, $search_term, $search_term, $search_term, $search_term, $search_term);
}
$query .= ' ORDER BY a.start_time LIMIT 150';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
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
<?php require __DIR__ . '/../includes/footer.php'; ?>