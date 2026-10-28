<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';

function portal_json_input(): array
{
    $body = file_get_contents('php://input');
    $input = json_decode($body ?: '', true);
    if (!is_array($input)) {
        json_response(['ok' => false, 'error' => 'Send a valid JSON request.'], 400);
    }
    return $input;
}

function portal_get_dashboard(): array
{
    global $pdo;
    $today = today_string();

    if (current_role() === ROLE_PATIENT) {
        $stmt = $pdo->prepare(
            "SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status,
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
        return [
            'role' => ROLE_PATIENT,
            'summary' => $stmt->fetch(),
            'appointments' => $upcoming,
        ];
    }

    $summary = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()) AS appointments_today,
            (SELECT SUM(status = 'pending') FROM appointments WHERE appointment_date = CURDATE()) AS pending_today,
            (SELECT SUM(status = 'confirmed') FROM appointments WHERE appointment_date = CURDATE()) AS confirmed_today,
            (SELECT COUNT(*) FROM queue_entries WHERE queue_date = CURDATE() AND status = 'waiting') AS waiting_now,
            (SELECT COALESCE(ROUND(AVG(estimated_wait_minutes)), 0) FROM queue_entries WHERE queue_date = CURDATE() AND status IN ('waiting', 'called')) AS average_wait,
            (SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE() AND status = 'no_show') AS no_shows"
    )->fetch();
    $stmt = $pdo->prepare(
        "SELECT a.id, a.start_time, a.status, u.full_name AS patient_name,
                s.name AS service_name, d.full_name AS dentist_name, q.status AS queue_status
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
         LEFT JOIN queue_entries q ON q.appointment_id = a.id
         WHERE a.appointment_date = ?
         ORDER BY a.start_time LIMIT 12"
    );
    $stmt->execute([$today]);
    $appointments = $stmt->fetchAll();
    $serving = $pdo->query(
        "SELECT q.queue_number, u.full_name, d.full_name AS dentist_name, s.name AS service_name
         FROM queue_entries q
         JOIN appointments a ON a.id = q.appointment_id
         JOIN users u ON u.id = a.patient_id
         JOIN dentists d ON d.id = a.dentist_id
         JOIN services s ON s.id = a.service_id
         WHERE q.queue_date = CURDATE() AND q.status = 'serving'
         ORDER BY q.updated_at DESC LIMIT 1"
    )->fetch();
    $next_waiting = $pdo->query(
        "SELECT queue_number FROM queue_entries
         WHERE queue_date = CURDATE() AND status = 'waiting'
         ORDER BY queue_number LIMIT 1"
    )->fetchColumn();

    return [
        'role' => current_role(),
        'summary' => $summary,
        'appointments' => $appointments,
        'serving' => $serving ?: null,
        'next_waiting' => $next_waiting ? (int) $next_waiting : null,
    ];
}

function portal_get_appointments(): array
{
    global $pdo;
    $role = current_role();
    $date = get_value('date');
    if ($date !== '' && !is_valid_date($date)) {
        json_response(['ok' => false, 'error' => 'Choose a valid appointment date.'], 422);
    }
    $status = get_value('status');
    if ($status !== '' && !in_array($status, APPOINTMENT_STATUSES, true)) {
        json_response(['ok' => false, 'error' => 'Choose a valid appointment status.'], 422);
    }
    $staff_note_column = $role === ROLE_PATIENT ? 'NULL' : 'a.staff_notes';
    $patient_note_column = $role === ROLE_PATIENT ? 'a.patient_notes' : 'NULL';
    $query =
        'SELECT a.id, a.service_id, a.dentist_id, a.appointment_date, a.start_time, a.end_time, a.status,
                ' . $staff_note_column . ' AS staff_notes, ' . $patient_note_column . ' AS patient_notes,
                a.cancellation_reason,
                u.full_name AS patient_name, u.email AS patient_email, s.name AS service_name,
                s.price, d.full_name AS dentist_name, q.queue_number, q.status AS queue_status
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
         LEFT JOIN queue_entries q ON q.appointment_id = a.id
         WHERE 1 = 1';
    $params = [];
    if ($role === ROLE_PATIENT) {
        $query .= ' AND a.patient_id = ?';
        $params[] = current_user_id();
    } elseif ($date !== '') {
        $query .= ' AND a.appointment_date = ?';
        $params[] = $date;
    } elseif (get_value('all_dates') !== '1') {
        $query .= ' AND a.appointment_date = CURDATE()';
    }
    if ($status !== '') {
        $query .= ' AND a.status = ?';
        $params[] = $status;
    }
    $search = substr(get_value('q'), 0, 100);
    if ($search !== '' && $role !== ROLE_PATIENT) {
        $query .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR s.name LIKE ? OR d.full_name LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $query .= ' ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 150';
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    foreach ($appointments as &$appointment) {
        $can_change = $role === ROLE_PATIENT && patient_can_change_appointment($appointment);
        $appointment['can_reschedule'] = $can_change;
        $appointment['allowed_transitions'] = $role === ROLE_PATIENT
            ? ($can_change ? ['cancelled'] : [])
            : (APPOINTMENT_TRANSITIONS[$appointment['status']] ?? []);
    }
    unset($appointment);
    return $appointments;
}

function portal_get_queue(): array
{
    global $pdo;
    if (current_role() === ROLE_PATIENT) {
        $stmt = $pdo->prepare(
            "SELECT q.id, q.queue_number, q.status, q.estimated_wait_minutes,
                    a.appointment_date, a.start_time, s.name AS service_name,
                    d.full_name AS dentist_name,
                    (SELECT COUNT(*) FROM queue_entries q2
                     WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
                       AND q2.status IN ('waiting', 'called', 'serving')) AS ahead_count,
                    (SELECT q3.queue_number FROM queue_entries q3
                     WHERE q3.queue_date = q.queue_date AND q3.status = 'serving'
                     ORDER BY q3.updated_at DESC LIMIT 1) AS now_serving
             FROM queue_entries q
             JOIN appointments a ON a.id = q.appointment_id
             JOIN services s ON s.id = a.service_id
             JOIN dentists d ON d.id = a.dentist_id
             WHERE a.patient_id = ? AND q.queue_date = CURDATE()
             ORDER BY q.queue_number DESC LIMIT 1"
        );
        $stmt->execute([current_user_id()]);
        $own_entry = $stmt->fetch();
        $serving_number = $pdo->query(
            "SELECT queue_number FROM queue_entries
             WHERE queue_date = CURDATE() AND status = 'serving'
             ORDER BY updated_at DESC LIMIT 1"
        )->fetchColumn();
        return [
            'role' => ROLE_PATIENT,
            'entry' => $own_entry ?: null,
            'now_serving' => $serving_number ? (int) $serving_number : null,
        ];
    }

    $category = get_value('category');
    $categories = $pdo->query('SELECT DISTINCT category FROM services WHERE is_active = 1 AND category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
    if ($category !== '' && !in_array($category, $categories, true)) {
        json_response(['ok' => false, 'error' => 'Choose a valid service category.'], 422);
    }
    $queue_query =
        "SELECT q.id, q.queue_number, q.status, q.estimated_wait_minutes,
                a.appointment_date, a.start_time, u.full_name AS patient_name, u.email AS patient_email,
                s.name AS service_name, s.category, d.full_name AS dentist_name
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
    $entries = $stmt->fetchAll();
    foreach ($entries as &$entry) {
        $entry['next_statuses'] = QUEUE_TRANSITIONS[$entry['status']] ?? [];
    }
    unset($entry);

    $checkin_query =
        "SELECT a.id AS appointment_id, a.start_time, u.full_name AS patient_name, u.email AS patient_email,
                s.name AS service_name, s.category, d.full_name AS dentist_name
         FROM appointments a
         JOIN users u ON u.id = a.patient_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
         WHERE a.appointment_date = CURDATE() AND a.status = 'confirmed' AND a.arrived_at IS NULL
           AND NOT EXISTS (SELECT 1 FROM queue_entries q WHERE q.appointment_id = a.id)";
    $checkin_params = [];
    if ($category !== '') {
        $checkin_query .= ' AND s.category = ?';
        $checkin_params[] = $category;
    }
    $checkin_query .= ' ORDER BY a.start_time';
    $stmt = $pdo->prepare($checkin_query);
    $stmt->execute($checkin_params);

    return [
        'role' => current_role(),
        'categories' => $categories,
        'entries' => $entries,
        'check_ins' => $stmt->fetchAll(),
        'csrf_token' => csrf_token(),
    ];
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $view = get_value('view');
    if ($view === 'catalog') {
        $services = $pdo->query(
            'SELECT id, name, category, description, duration_minutes, price
             FROM services WHERE is_active = 1 ORDER BY name'
        )->fetchAll();
        $dentists = $pdo->query(
            'SELECT d.id, d.full_name, d.specialty, GROUP_CONCAT(ds.service_id) AS service_ids
             FROM dentists d
             JOIN dentist_services ds ON ds.dentist_id = d.id
             JOIN services s ON s.id = ds.service_id AND s.is_active = 1
             WHERE d.is_active = 1
             GROUP BY d.id, d.full_name, d.specialty
             ORDER BY d.full_name'
        )->fetchAll();
        json_response(['ok' => true, 'services' => $services, 'dentists' => $dentists]);
    }

    require_role_api([ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN]);
    if ($view === 'dashboard') {
        json_response(['ok' => true, 'dashboard' => portal_get_dashboard()]);
    }
    if ($view === 'appointments') {
        json_response(['ok' => true, 'appointments' => portal_get_appointments()]);
    }
    if ($view === 'queue') {
        json_response(['ok' => true, 'queue' => portal_get_queue()]);
    }
    if ($view === 'notifications') {
        require_role_api([ROLE_PATIENT]);
        $stmt = $pdo->prepare(
            'SELECT id, subject, message, status, created_at
             FROM notifications WHERE user_id = ?
             ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([current_user_id()]);
        json_response(['ok' => true, 'notifications' => $stmt->fetchAll()]);
    }
    if ($view === 'receipt') {
        require_role_api([ROLE_PATIENT]);
        $appointment_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$appointment_id) {
            json_response(['ok' => false, 'error' => 'Choose a valid appointment receipt.'], 422);
        }
        $stmt = $pdo->prepare(
            'SELECT a.id, a.appointment_date, a.start_time, a.end_time, a.status, a.created_at,
                    a.cancellation_reason, s.name AS service_name, s.price,
                    d.full_name AS dentist_name, u.full_name AS patient_name, u.email
             FROM appointments a JOIN services s ON s.id = a.service_id
             JOIN dentists d ON d.id = a.dentist_id JOIN users u ON u.id = a.patient_id
             WHERE a.id = ? AND a.patient_id = ?'
        );
        $stmt->execute([$appointment_id, current_user_id()]);
        $appointment = $stmt->fetch();
        if (!$appointment) {
            json_response(['ok' => false, 'error' => 'Appointment not found.'], 404);
        }
        $appointment['clinic_name'] = get_setting('clinic_name', APP_NAME);
        json_response(['ok' => true, 'appointment' => $appointment]);
    }
    if ($view === 'patients') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $stmt = $pdo->query(
            "SELECT id, full_name, email FROM users
             WHERE role = 'patient' AND is_active = 1 AND email_verified_at IS NOT NULL
             ORDER BY full_name LIMIT 500"
        );
        json_response(['ok' => true, 'patients' => $stmt->fetchAll()]);
    }
    if ($view === 'admin_users') {
        require_role_api([ROLE_ADMIN]);
        $users = $pdo->query(
            'SELECT id, role, full_name, email, phone, is_active, email_verified_at, last_login_at, created_at
             FROM users ORDER BY created_at DESC, id DESC LIMIT 200'
        )->fetchAll();
        json_response(['ok' => true, 'users' => $users, 'current_user_id' => current_user_id()]);
    }
    if ($view === 'admin_schedules') {
        require_role_api([ROLE_ADMIN]);
        $dentists = $pdo->query('SELECT id, full_name FROM dentists ORDER BY full_name')->fetchAll();
        $schedule_rows = $pdo->query(
            'SELECT sc.id, sc.dentist_id, sc.day_of_week, sc.start_time, sc.end_time, sc.is_active,
                    b.break_start, b.break_end
             FROM schedules sc LEFT JOIN schedule_breaks b ON b.schedule_id = sc.id
             ORDER BY sc.dentist_id, sc.day_of_week'
        )->fetchAll();
        $holidays = $pdo->query(
            'SELECT h.id, h.holiday_date, h.description, h.dentist_id, d.full_name
             FROM holidays h LEFT JOIN dentists d ON d.id = h.dentist_id
             ORDER BY h.holiday_date'
        )->fetchAll();
        $settings = [];
        foreach (['slot_interval_minutes', 'booking_days_ahead', 'min_hours_before_change'] as $key) {
            $settings[$key] = get_setting($key, $key === 'slot_interval_minutes' ? '30' : ($key === 'booking_days_ahead' ? '60' : '2'));
        }
        json_response(['ok' => true, 'dentists' => $dentists, 'schedules' => $schedule_rows, 'holidays' => $holidays, 'settings' => $settings]);
    }
    if ($view === 'admin_reports') {
        require_role_api([ROLE_ADMIN]);
        $start = get_value('start') ?: date('Y-m-01');
        $end = get_value('end') ?: today_string();
        if (!is_valid_date($start) || !is_valid_date($end) || $start > $end) {
            json_response(['ok' => false, 'error' => 'Choose a valid date range.'], 422);
        }
        $params = [$start, $end];
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(a.status = 'completed') AS completed,
                    SUM(a.status = 'cancelled') AS cancelled,
                    SUM(a.status = 'no_show') AS no_show,
                    SUM(a.status = 'pending') AS pending,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) AS revenue,
                    (SELECT COALESCE(ROUND(AVG(q.estimated_wait_minutes)), 0)
                     FROM queue_entries q JOIN appointments qa ON qa.id = q.appointment_id
                     WHERE qa.appointment_date BETWEEN ? AND ? AND q.status IN ('waiting', 'called')) AS average_wait
             FROM appointments a JOIN services s ON s.id = a.service_id
             WHERE a.appointment_date BETWEEN ? AND ?"
        );
        $stmt->execute([$start, $end, $start, $end]);
        $summary = $stmt->fetch();
        $stmt = $pdo->prepare(
            "SELECT s.name, COUNT(*) AS appointments,
                    SUM(a.status = 'completed') AS completed,
                    COALESCE(SUM(CASE WHEN a.status = 'completed' THEN s.price ELSE 0 END), 0) AS revenue
             FROM appointments a JOIN services s ON s.id = a.service_id
             WHERE a.appointment_date BETWEEN ? AND ?
             GROUP BY s.id, s.name ORDER BY appointments DESC, s.name"
        );
        $stmt->execute($params);
        $services = $stmt->fetchAll();
        $stmt = $pdo->prepare(
            'SELECT a.appointment_date, COUNT(*) AS appointments
             FROM appointments a WHERE a.appointment_date BETWEEN ? AND ?
             GROUP BY a.appointment_date ORDER BY a.appointment_date'
        );
        $stmt->execute($params);
        $daily = $stmt->fetchAll();
        $stmt = $pdo->prepare(
            'SELECT a.appointment_date, a.start_time, u.full_name AS patient_name,
                    s.name AS service_name, d.full_name AS dentist_name, a.status
             FROM appointments a JOIN users u ON u.id = a.patient_id
             JOIN services s ON s.id = a.service_id JOIN dentists d ON d.id = a.dentist_id
             WHERE a.appointment_date BETWEEN ? AND ?
             ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 100'
        );
        $stmt->execute($params);
        json_response(['ok' => true, 'start' => $start, 'end' => $end, 'summary' => $summary, 'services' => $services, 'daily' => $daily, 'appointments' => $stmt->fetchAll()]);
    }
    if ($view === 'admin_activity') {
        require_role_api([ROLE_ADMIN]);
        $from = get_value('from') ?: date('Y-m-d', strtotime('-6 days'));
        $to = get_value('to') ?: today_string();
        $action_filter = substr(get_value('action'), 0, 60);
        if (!is_valid_date($from) || !is_valid_date($to) || $from > $to) {
            json_response(['ok' => false, 'error' => 'Choose a valid date range.'], 422);
        }
        $stmt = $pdo->prepare(
            'SELECT l.id, l.action, l.details, l.ip_address, l.created_at, u.full_name, u.email
             FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE l.created_at >= ? AND l.created_at < DATE_ADD(?, INTERVAL 1 DAY)
               AND (? = \'\' OR l.action = ?)
             ORDER BY l.created_at DESC LIMIT 300'
        );
        $stmt->execute([$from, $to, $action_filter, $action_filter]);
        json_response(['ok' => true, 'from' => $from, 'to' => $to, 'action' => $action_filter, 'logs' => $stmt->fetchAll()]);
    }
    if ($view === 'staff_notifications') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $stmt = $pdo->prepare(
            'SELECT n.id, n.channel, n.type, n.recipient, n.subject, n.message, n.scheduled_for, n.status, n.sent_at, u.full_name
             FROM notifications n JOIN users u ON u.id = n.user_id
             WHERE (? = \'admin\' OR n.type NOT IN (\'password_reset\', \'email_verification\'))
             ORDER BY (n.status = \'queued\') DESC, n.scheduled_for DESC LIMIT 150'
        );
        $stmt->execute([current_role()]);
        json_response(['ok' => true, 'notifications' => $stmt->fetchAll()]);
    }
    json_response(['ok' => false, 'error' => 'This clinic view is not available.'], 404);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'This clinic action is not available.'], 405);
}
require_csrf_api();
require_role_api([ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN]);
$input = portal_json_input();
$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';

try {
    if ($action === 'book') {
        require_role_api([ROLE_PATIENT]);
        $service_id = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT);
        $dentist_id = filter_var($input['dentist_id'] ?? null, FILTER_VALIDATE_INT);
        $date = isset($input['appointment_date']) && is_string($input['appointment_date']) ? $input['appointment_date'] : '';
        $time = isset($input['appointment_time']) && is_string($input['appointment_time']) ? $input['appointment_time'] : '';
        $notes = isset($input['patient_notes']) && is_string($input['patient_notes']) ? trim($input['patient_notes']) : '';
        if (!$service_id || $dentist_id === false || $dentist_id === null || $dentist_id < 0 || strlen($notes) > 2000) {
            throw new DomainException('Choose a service, dentist, date, and available time. Notes must be 2,000 characters or fewer.');
        }
        $appointment_id = create_patient_appointment(current_user_id(), (int) $service_id, (int) $dentist_id, $date, $time, $notes);
        json_response(['ok' => true, 'appointment_id' => $appointment_id, 'message' => 'Your appointment request was sent to the clinic.']);
    }

    if ($action === 'reschedule') {
        require_role_api([ROLE_PATIENT]);
        $appointment_id = filter_var($input['appointment_id'] ?? null, FILTER_VALIDATE_INT);
        $date = isset($input['appointment_date']) && is_string($input['appointment_date']) ? $input['appointment_date'] : '';
        $time = isset($input['appointment_time']) && is_string($input['appointment_time']) ? $input['appointment_time'] : '';
        if (!$appointment_id) {
            throw new DomainException('Choose a valid appointment to reschedule.');
        }
        reschedule_patient_appointment(current_user_id(), (int) $appointment_id, $date, $time);
        json_response(['ok' => true, 'appointment_id' => $appointment_id, 'message' => 'Your new appointment time was requested. The clinic will confirm it shortly.']);
    }

    if ($action === 'cancel') {
        require_role_api([ROLE_PATIENT]);
        $appointment_id = filter_var($input['appointment_id'] ?? null, FILTER_VALIDATE_INT);
        $reason = isset($input['cancellation_reason']) && is_string($input['cancellation_reason']) ? trim($input['cancellation_reason']) : '';
        if (!$appointment_id || strlen($reason) > 255) {
            throw new DomainException('Choose a valid appointment and keep the cancellation note under 255 characters.');
        }
        cancel_patient_appointment(current_user_id(), (int) $appointment_id, $reason);
        json_response(['ok' => true, 'message' => 'Your appointment was cancelled.']);
    }

    if ($action === 'queue_check_in') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $appointment_id = filter_var($input['appointment_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$appointment_id) {
            throw new DomainException('Choose a valid appointment to check in.');
        }
        $queue_number = check_in_appointment((int) $appointment_id, current_user_id());
        json_response(['ok' => true, 'queue_number' => $queue_number, 'message' => 'Patient checked in as ' . format_queue_number($queue_number) . '.']);
    }

    if ($action === 'queue_transition') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $entry_id = filter_var($input['queue_entry_id'] ?? null, FILTER_VALIDATE_INT);
        $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : '';
        if (!$entry_id) {
            throw new DomainException('Choose a valid queue entry.');
        }
        transition_queue_entry((int) $entry_id, $status, current_user_id());
        json_response(['ok' => true, 'message' => 'Queue status updated.']);
    }

    if ($action === 'appointment_transition') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $appointment_id = filter_var($input['appointment_id'] ?? null, FILTER_VALIDATE_INT);
        $next_status = isset($input['status']) && is_string($input['status']) ? $input['status'] : '';
        $staff_notes = isset($input['staff_notes']) && is_string($input['staff_notes']) ? trim($input['staff_notes']) : '';
        if (!$appointment_id || strlen($staff_notes) > 2000) {
            throw new DomainException('Choose a valid appointment and keep staff notes under 2,000 characters.');
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
            $stmt = $pdo->prepare('SELECT id FROM schedules WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1 FOR UPDATE');
            $stmt->execute([$appointment['dentist_id'], $day]);
            if (!$stmt->fetch()) {
                throw new DomainException('The dentist no longer has a schedule for this date.');
            }
            $stmt = $pdo->prepare(
                "SELECT id FROM appointments
                 WHERE dentist_id = ? AND appointment_date = ? AND id <> ?
                   AND status IN ('pending', 'confirmed', 'completed')
                   AND start_time < ? AND end_time > ? LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([$appointment['dentist_id'], $appointment['appointment_date'], $appointment_id, $appointment['end_time'], $appointment['start_time']]);
            if ($stmt->fetch()) {
                throw new DomainException('This time conflicts with another appointment. Resolve the conflict before confirming.');
            }
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
        json_response(['ok' => true, 'message' => 'Appointment updated.']);
    }

    if ($action === 'walk_in') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $patient_id = filter_var($input['patient_id'] ?? null, FILTER_VALIDATE_INT);
        $service_id = filter_var($input['service_id'] ?? null, FILTER_VALIDATE_INT);
        $dentist_id = filter_var($input['dentist_id'] ?? null, FILTER_VALIDATE_INT);
        $time = isset($input['appointment_time']) && is_string($input['appointment_time']) ? $input['appointment_time'] : '';
        $notes = isset($input['patient_notes']) && is_string($input['patient_notes']) ? trim($input['patient_notes']) : '';
        if (!$patient_id || !$service_id || $dentist_id === false || $dentist_id === null || $dentist_id < 0) {
            throw new DomainException('Choose a patient, service, dentist, and available time.');
        }
        $result = create_walk_in_appointment((int) $patient_id, (int) $service_id, (int) $dentist_id, today_string(), $time, $notes, current_user_id());
        json_response(['ok' => true, 'appointment_id' => $result['appointment_id'], 'queue_number' => $result['queue_number'], 'message' => 'Walk-in checked in as ' . format_queue_number($result['queue_number']) . '.']);
    }

    if (in_array($action, ['admin_user_create', 'admin_user_update', 'admin_schedule_save', 'admin_holiday_create', 'admin_holiday_delete', 'admin_settings_save'], true)) {
        require_role_api([ROLE_ADMIN]);
    }
    if ($action === 'admin_user_create') {
        $name = isset($input['full_name']) && is_string($input['full_name']) ? trim($input['full_name']) : '';
        $email = isset($input['email']) && is_string($input['email']) ? strtolower(trim($input['email'])) : '';
        $phone = isset($input['phone']) && is_string($input['phone']) ? trim($input['phone']) : '';
        $role = isset($input['role']) && is_string($input['role']) ? $input['role'] : '';
        $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
        if (strlen($name) < 2 || strlen($name) > 100 || !is_valid_email($email) || !in_array($role, [ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN], true)) {
            throw new DomainException('Enter a valid name, email and account role.');
        }
        if ($phone !== '' && !is_valid_phone($phone)) {
            throw new DomainException('Enter a valid phone number.');
        }
        $password_message = password_error($password);
        if ($password_message !== '') {
            throw new DomainException($password_message);
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$role, $name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
        $user_id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare($role === ROLE_PATIENT ? 'INSERT INTO patient_profiles (user_id) VALUES (?)' : 'INSERT INTO staff_profiles (user_id) VALUES (?)');
        $stmt->execute([$user_id]);
        log_activity('user_created', 'Created ' . $role . ' account #' . $user_id);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'Account created. Share the temporary password securely.']);
    }
    if ($action === 'admin_user_update') {
        $user_id = filter_var($input['user_id'] ?? null, FILTER_VALIDATE_INT);
        $role = isset($input['role']) && is_string($input['role']) ? $input['role'] : '';
        $is_active = filter_var($input['is_active'] ?? null, FILTER_VALIDATE_INT);
        if (!$user_id || !in_array($role, [ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN], true) || !in_array($is_active, [0, 1], true)) {
            throw new DomainException('Choose a valid account, role and access state.');
        }
        if ((int) $user_id === current_user_id() && ($role !== ROLE_ADMIN || $is_active !== 1)) {
            throw new DomainException('You cannot remove your own active administrator access.');
        }
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE users SET role = ?, is_active = ? WHERE id = ?');
        $stmt->execute([$role, $is_active, $user_id]);
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            if (!$stmt->fetch()) {
                throw new DomainException('That account no longer exists.');
            }
        }
        $stmt = $pdo->prepare($role === ROLE_PATIENT ? 'INSERT IGNORE INTO patient_profiles (user_id) VALUES (?)' : 'INSERT IGNORE INTO staff_profiles (user_id) VALUES (?)');
        $stmt->execute([$user_id]);
        log_activity('user_updated', 'Updated role/access for account #' . $user_id);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'Account access updated.']);
    }
    if ($action === 'admin_schedule_save') {
        $dentist_id = filter_var($input['dentist_id'] ?? null, FILTER_VALIDATE_INT);
        $day = filter_var($input['day_of_week'] ?? null, FILTER_VALIDATE_INT);
        $start = isset($input['start_time']) && is_string($input['start_time']) ? $input['start_time'] : '';
        $end = isset($input['end_time']) && is_string($input['end_time']) ? $input['end_time'] : '';
        $break_start = isset($input['break_start']) && is_string($input['break_start']) ? $input['break_start'] : '';
        $break_end = isset($input['break_end']) && is_string($input['break_end']) ? $input['break_end'] : '';
        $active = !empty($input['is_active']) ? 1 : 0;
        if (!$dentist_id || $day === false || $day < 0 || $day > 6 || !is_valid_time($start) || !is_valid_time($end) || $start >= $end) {
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
        log_activity('schedule_updated', 'Updated weekday ' . $day . ' schedule for dentist #' . $dentist_id);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'Dentist schedule saved.']);
    }
    if ($action === 'admin_holiday_create') {
        $date = isset($input['holiday_date']) && is_string($input['holiday_date']) ? $input['holiday_date'] : '';
        $description = isset($input['description']) && is_string($input['description']) ? trim($input['description']) : '';
        $dentist = $input['dentist_id'] ?? '';
        if (!is_valid_date($date) || $description === '' || strlen($description) > 150 || ($dentist !== '' && !filter_var($dentist, FILTER_VALIDATE_INT))) {
            throw new DomainException('Enter a valid date, description and optional dentist.');
        }
        $stmt = $pdo->prepare('INSERT INTO holidays (holiday_date, description, dentist_id) VALUES (?, ?, ?)');
        $stmt->execute([$date, $description, $dentist !== '' ? (int) $dentist : null]);
        log_activity('holiday_added', 'Added blackout date ' . $date);
        json_response(['ok' => true, 'message' => 'Blackout date added.']);
    }
    if ($action === 'admin_holiday_delete') {
        $holiday_id = filter_var($input['holiday_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$holiday_id) {
            throw new DomainException('Choose a valid blackout date.');
        }
        $stmt = $pdo->prepare('DELETE FROM holidays WHERE id = ?');
        $stmt->execute([$holiday_id]);
        log_activity('holiday_removed', 'Removed blackout date #' . $holiday_id);
        json_response(['ok' => true, 'message' => 'Blackout date removed.']);
    }
    if ($action === 'admin_settings_save') {
        $interval = filter_var($input['slot_interval_minutes'] ?? null, FILTER_VALIDATE_INT);
        $days = filter_var($input['booking_days_ahead'] ?? null, FILTER_VALIDATE_INT);
        $notice = filter_var($input['min_hours_before_change'] ?? null, FILTER_VALIDATE_INT);
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
        json_response(['ok' => true, 'message' => 'Availability settings saved.']);
    }
    if ($action === 'notification_simulate') {
        require_role_api([ROLE_STAFF, ROLE_ADMIN]);
        $notification_id = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$notification_id) {
            throw new DomainException('Choose a valid queued notification.');
        }
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE id = ? AND status = 'queued'");
        $stmt->execute([$notification_id]);
        if (!$stmt->rowCount()) {
            throw new DomainException('That notification is no longer queued.');
        }
        log_activity('notification_simulated', 'Simulated delivery for notification #' . $notification_id);
        json_response(['ok' => true, 'message' => 'Notification marked as simulated. No email or SMS was sent.']);
    }
} catch (DomainException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(['ok' => false, 'error' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Clinic API action failed: ' . $exception->getMessage());
    if ($exception instanceof PDOException && $exception->getCode() === '23000') {
        json_response(['ok' => false, 'error' => 'That record conflicts with existing data. Check for a duplicate email or blocked date.'], 409);
    }
    json_response(['ok' => false, 'error' => 'The request could not be completed. Please try again.'], 500);
}

json_response(['ok' => false, 'error' => 'This clinic action is not available.'], 404);
