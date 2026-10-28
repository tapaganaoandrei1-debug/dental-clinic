<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
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
<?php require __DIR__ . '/../../footer.php'; ?>