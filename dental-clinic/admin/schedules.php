<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'Schedules and availability';
$active_nav = 'schedules';
$weekdays = [0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = post_value('action');
        if ($action === 'save_schedule') {
            $dentist_id = filter_var($_POST['dentist_id'] ?? null, FILTER_VALIDATE_INT);
            $day = filter_var($_POST['day_of_week'] ?? null, FILTER_VALIDATE_INT);
            $start = post_value('start_time');
            $end = post_value('end_time');
            $break_start = post_value('break_start');
            $break_end = post_value('break_end');
            $active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
            if (!$dentist_id || $day === false || !isset($weekdays[$day]) || !is_valid_time($start) || !is_valid_time($end) || $start >= $end) {
                throw new DomainException('Enter valid working hours for a dentist and weekday.');
            }
            if (($break_start === '') !== ($break_end === '') || ($break_start !== '' && (!is_valid_time($break_start) || !is_valid_time($break_end) || $break_start >= $break_end || $break_start < $start || $break_end > $end))) {
                throw new DomainException('A break must have valid start and end times inside working hours.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO schedules (dentist_id, day_of_week, start_time, end_time, is_active)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE start_time = VALUES(start_time), end_time = VALUES(end_time), is_active = VALUES(is_active)'
            );
            $stmt->execute([$dentist_id, $day, $start . ':00', $end . ':00', $active]);
            $stmt = $pdo->prepare('SELECT id FROM schedules WHERE dentist_id = ? AND day_of_week = ?');
            $stmt->execute([$dentist_id, $day]);
            $schedule_id = (int) $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT id FROM schedule_breaks WHERE schedule_id = ? ORDER BY id LIMIT 1');
            $stmt->execute([$schedule_id]);
            $break_id = $stmt->fetchColumn();
            if ($break_start === '') {
                $stmt = $pdo->prepare('DELETE FROM schedule_breaks WHERE schedule_id = ?');
                $stmt->execute([$schedule_id]);
            } elseif ($break_id) {
                $stmt = $pdo->prepare('UPDATE schedule_breaks SET break_start = ?, break_end = ? WHERE id = ?');
                $stmt->execute([$break_start . ':00', $break_end . ':00', $break_id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO schedule_breaks (schedule_id, break_start, break_end) VALUES (?, ?, ?)');
                $stmt->execute([$schedule_id, $break_start . ':00', $break_end . ':00']);
            }
            log_activity('schedule_updated', 'Updated ' . $weekdays[$day] . ' schedule for dentist #' . $dentist_id);
            $pdo->commit();
            set_flash('success', 'Dentist schedule saved.');
        } elseif ($action === 'holiday_create') {
            $date = post_value('holiday_date');
            $description = post_value('description');
            $dentist = post_value('holiday_dentist_id');
            if (!is_valid_date($date) || $description === '' || strlen($description) > 150 || ($dentist !== '' && !filter_var($dentist, FILTER_VALIDATE_INT))) {
                throw new DomainException('Enter a valid date, description and optional dentist.');
            }
            $stmt = $pdo->prepare('INSERT INTO holidays (holiday_date, description, dentist_id) VALUES (?, ?, ?)');
            $stmt->execute([$date, $description, $dentist !== '' ? (int) $dentist : null]);
            log_activity('holiday_added', 'Added blackout date ' . $date);
            set_flash('success', 'Blackout date added.');
        } elseif ($action === 'holiday_delete') {
            $holiday_id = filter_var($_POST['holiday_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$holiday_id) throw new DomainException('Choose a valid blackout date.');
            $stmt = $pdo->prepare('DELETE FROM holidays WHERE id = ?');
            $stmt->execute([$holiday_id]);
            log_activity('holiday_removed', 'Removed blackout date #' . $holiday_id);
            set_flash('success', 'Blackout date removed.');
        } elseif ($action === 'settings_save') {
            $interval = filter_var($_POST['slot_interval_minutes'] ?? null, FILTER_VALIDATE_INT);
            $days = filter_var($_POST['booking_days_ahead'] ?? null, FILTER_VALIDATE_INT);
            $notice = filter_var($_POST['min_hours_before_change'] ?? null, FILTER_VALIDATE_INT);
            if (!$interval || $interval < 5 || $interval > 120 || !$days || $days < 1 || $days > 365 || $notice === false || $notice < 0 || $notice > 72) {
                throw new DomainException('Use a 5–120 minute interval, 1–365 booking days and 0–72 notice hours.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO clinic_settings (setting_key, setting_value, description) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach (['slot_interval_minutes' => $interval, 'booking_days_ahead' => $days, 'min_hours_before_change' => $notice] as $key => $value) {
                $stmt->execute([$key, (string) $value, 'Managed in clinic availability settings']);
            }
            log_activity('availability_settings_updated', 'Updated booking limits and slot interval');
            set_flash('success', 'Availability settings saved.');
        } else {
            throw new DomainException('That availability action is not available.');
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Schedule management failed: ' . $exception->getMessage());
        set_flash('error', 'Availability could not be saved. Please try again.');
    }
    redirect('admin/schedules.php');
}

$dentists = $pdo->query('SELECT id, full_name FROM dentists ORDER BY full_name')->fetchAll();
$schedule_rows = $pdo->query(
    'SELECT sc.id, sc.dentist_id, sc.day_of_week, sc.start_time, sc.end_time, sc.is_active,
            d.full_name, b.break_start, b.break_end, b.label AS break_label
     FROM schedules sc
     JOIN dentists d ON d.id = sc.dentist_id
     LEFT JOIN schedule_breaks b ON b.schedule_id = sc.id
     ORDER BY d.full_name, sc.day_of_week'
)->fetchAll();
$schedules = [];
foreach ($schedule_rows as $row) {
    $schedules[$row['dentist_id'] . '-' . $row['day_of_week']] = $row;
}
$holidays = $pdo->query(
    'SELECT h.id, h.holiday_date, h.description, d.full_name
     FROM holidays h LEFT JOIN dentists d ON d.id = h.dentist_id
     ORDER BY h.holiday_date'
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>Set the clinic’s <em>rhythm.</em></h1><p>Working hours, breaks, blocked dates and patient booking limits.</p></div>
    <a class="text-link" href="<?= e(url('admin/dashboard.php')) ?>">Back to overview</a>
</section>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Weekly schedule</p><h2>Provider availability</h2></div><span class="subtle-count">Select a day to edit its hours</span></div>
    <div class="schedule-matrix-wrap"><table class="schedule-matrix"><thead><tr><th>Dentist</th><?php foreach (array_slice($weekdays, 1, null, true) as $day_name): ?><th><?= e(substr($day_name, 0, 3)) ?></th><?php endforeach; ?></tr></thead><tbody>
        <?php foreach ($dentists as $dentist): ?><tr><th><?= e($dentist['full_name']) ?></th>
            <?php foreach (array_slice($weekdays, 1, null, true) as $day => $day_name): $row = $schedules[$dentist['id'] . '-' . $day] ?? null; $is_open = $row && (int) $row['is_active'] === 1; ?>
                <td><details class="schedule-cell"><summary class="<?= $is_open ? 'is-open' : 'is-off' ?>"><?= $is_open ? e(date('g', strtotime($row['start_time'])) . '–' . date('g', strtotime($row['end_time']))) : 'Off' ?></summary>
                    <form class="schedule-editor-form" method="post" action="<?= e(url('admin/schedules.php')) ?>">
                        <strong><?= e($dentist['full_name']) ?> · <?= e($day_name) ?></strong>
                        <?= csrf_field() ?><input type="hidden" name="action" value="save_schedule"><input type="hidden" name="dentist_id" value="<?= (int) $dentist['id'] ?>"><input type="hidden" name="day_of_week" value="<?= $day ?>">
                        <label>Start<input type="time" name="start_time" value="<?= $row ? e(substr($row['start_time'], 0, 5)) : '09:00' ?>" required></label>
                        <label>End<input type="time" name="end_time" value="<?= $row ? e(substr($row['end_time'], 0, 5)) : '17:00' ?>" required></label>
                        <label>Break start<input type="time" name="break_start" value="<?= $row && $row['break_start'] ? e(substr($row['break_start'], 0, 5)) : '' ?>"></label>
                        <label>Break end<input type="time" name="break_end" value="<?= $row && $row['break_end'] ? e(substr($row['break_end'], 0, 5)) : '' ?>"></label>
                        <label class="schedule-active"><input type="checkbox" name="is_active" value="1"<?= !$row || (int) $row['is_active'] === 1 ? ' checked' : '' ?>> Available</label>
                        <button class="button button-primary" type="submit">Save changes</button>
                    </form>
                </details></td>
            <?php endforeach; ?>
        </tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<section class="admin-settings-grid">
    <div class="content-section">
        <div class="content-heading"><div><p class="eyebrow">Booking rules</p><h2>Patient limits</h2></div></div>
        <form class="stacked-form" method="post" action="<?= e(url('admin/schedules.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="settings_save">
            <label for="slot_interval_minutes">Slot interval (minutes)</label><input id="slot_interval_minutes" type="number" name="slot_interval_minutes" min="5" max="120" step="5" value="<?= e(get_setting('slot_interval_minutes', '30')) ?>" required>
            <label for="booking_days_ahead">Book up to (days ahead)</label><input id="booking_days_ahead" type="number" name="booking_days_ahead" min="1" max="365" value="<?= e(get_setting('booking_days_ahead', '60')) ?>" required>
            <label for="min_hours_before_change">Minimum notice to change (hours)</label><input id="min_hours_before_change" type="number" name="min_hours_before_change" min="0" max="72" value="<?= e(get_setting('min_hours_before_change', '2')) ?>" required>
            <button class="button button-primary" type="submit">Save booking rules</button>
        </form>
    </div>
    <div class="content-section">
        <div class="content-heading"><div><p class="eyebrow">Blocked dates</p><h2>Holidays and leave</h2></div></div>
        <form class="stacked-form holiday-form" method="post" action="<?= e(url('admin/schedules.php')) ?>">
            <?= csrf_field() ?><input type="hidden" name="action" value="holiday_create">
            <label for="holiday_date">Date</label><input id="holiday_date" type="date" name="holiday_date" required>
            <label for="description">Description</label><input id="description" type="text" name="description" maxlength="150" required>
            <label for="holiday_dentist_id">Applies to</label><select id="holiday_dentist_id" name="holiday_dentist_id"><option value="">Whole clinic</option><?php foreach ($dentists as $dentist): ?><option value="<?= (int) $dentist['id'] ?>"><?= e($dentist['full_name']) ?></option><?php endforeach; ?></select>
            <button class="button button-primary" type="submit">Add blocked date</button>
        </form>
        <div class="holiday-list">
            <?php foreach ($holidays as $holiday): ?>
                <div class="holiday-row"><span><strong><?= e(format_date($holiday['holiday_date'])) ?></strong><small><?= e($holiday['description']) ?> · <?= e($holiday['full_name'] ?: 'Whole clinic') ?></small></span><form method="post" action="<?= e(url('admin/schedules.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="holiday_delete"><input type="hidden" name="holiday_id" value="<?= (int) $holiday['id'] ?>"><button class="small-link danger-link" type="submit">Remove</button></form></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>