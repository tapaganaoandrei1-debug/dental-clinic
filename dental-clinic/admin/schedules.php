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

require __DIR__ . '/../includes/views/admin/schedules.php';
