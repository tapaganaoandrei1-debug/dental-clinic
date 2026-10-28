<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'Queue control';
$active_nav = 'queue';
$category = get_value('category');
$categories = $pdo->query('SELECT DISTINCT category FROM services WHERE is_active = 1 AND category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
if ($category !== '' && !in_array($category, $categories, true)) $category = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = post_value('action');
        if ($action === 'check_in') {
            $appointment_id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$appointment_id) {
                throw new DomainException('Choose a valid appointment to check in.');
            }
            $number = check_in_appointment((int) $appointment_id, current_user_id());
            set_flash('success', 'Patient checked in as ' . format_queue_number($number) . '.');
        } elseif ($action === 'transition') {
            $queue_entry_id = filter_var($_POST['queue_entry_id'] ?? null, FILTER_VALIDATE_INT);
            $next_status = post_value('status');
            if (!$queue_entry_id) {
                throw new DomainException('Choose a valid queue entry.');
            }
            transition_queue_entry((int) $queue_entry_id, $next_status, current_user_id());
            set_flash('success', 'Queue status updated.');
        } else {
            throw new DomainException('That queue action is not available.');
        }
    } catch (DomainException $exception) {
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Queue action failed: ' . $exception->getMessage());
        set_flash('error', 'The queue could not be updated. Please try again.');
    }
    redirect('staff/queue.php' . ($category !== '' ? '?category=' . rawurlencode($category) : ''));
}

$checkin_query =
    "SELECT a.id, a.start_time, u.full_name AS patient_name, s.name AS service_name,
            d.full_name AS dentist_name
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.appointment_date = CURDATE() AND a.status = 'confirmed'
       AND a.arrived_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM queue_entries q WHERE q.appointment_id = a.id)";
$checkin_params = [];
if ($category !== '') {
    $checkin_query .= ' AND s.category = ?';
    $checkin_params[] = $category;
}
$checkin_query .= ' ORDER BY a.start_time';
$stmt = $pdo->prepare($checkin_query);
$stmt->execute($checkin_params);
$check_ins = $stmt->fetchAll();
$queue_query =
    "SELECT q.id, q.queue_number, q.status, q.estimated_wait_minutes,
            u.full_name AS patient_name, s.name AS service_name, s.category, d.full_name AS dentist_name
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
    WHERE q.queue_date = CURDATE() AND q.status IN ('waiting', 'called', 'serving')";
$queue_params = [];
if ($category !== '') {
    $queue_query .= ' AND s.category = ?';
    $queue_params[] = $category;
}
$queue_query .= " ORDER BY FIELD(q.status, 'serving', 'called', 'waiting'), q.queue_number";
$stmt = $pdo->prepare($queue_query);
$stmt->execute($queue_params);
$queue = $stmt->fetchAll();
$serving_entry = null;
$called_entry = null;
$next_waiting_entry = null;
foreach ($queue as $entry) {
    if ($serving_entry === null && $entry['status'] === 'serving') $serving_entry = $entry;
    if ($called_entry === null && $entry['status'] === 'called') $called_entry = $entry;
    if ($next_waiting_entry === null && $entry['status'] === 'waiting') $next_waiting_entry = $entry;
}
$csrf_token = csrf_token();

require __DIR__ . '/../includes/views/staff/queue.php';
