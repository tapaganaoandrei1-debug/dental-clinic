<?php
require_once __DIR__ . '/../config/config.php';
require_role_api([ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN]);

if (current_role() === ROLE_PATIENT) {
    $stmt = $pdo->prepare(
        "SELECT q.id, q.queue_number, q.status, a.start_time,
            s.name AS service_name, d.full_name AS dentist_name,
            (SELECT COUNT(*) FROM queue_entries q2
             WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
               AND q2.status IN ('waiting', 'called', 'serving')) AS ahead_count,
            (SELECT COALESCE(SUM(s2.duration_minutes), 0) FROM queue_entries q2
             JOIN appointments a2 ON a2.id = q2.appointment_id
             JOIN services s2 ON s2.id = a2.service_id
             WHERE q2.queue_date = q.queue_date AND q2.queue_number < q.queue_number
               AND q2.status IN ('waiting', 'called', 'serving')) AS wait_estimate,
            (SELECT q3.queue_number FROM queue_entries q3
             WHERE q3.queue_date = q.queue_date AND q3.status = 'serving'
             ORDER BY q3.updated_at DESC LIMIT 1) AS now_serving
         FROM queue_entries q
         JOIN appointments a ON a.id = q.appointment_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
                 WHERE a.patient_id = ? AND q.queue_date = CURDATE()
                     AND q.status IN ('waiting', 'called', 'serving')
         ORDER BY q.queue_number"
    );
    $stmt->execute([current_user_id()]);
} else {
    $queue_query =
        'SELECT q.id, q.queue_number, q.status, q.estimated_wait_minutes,
                a.appointment_date, a.start_time, u.full_name AS patient_name,
                s.name AS service_name, d.full_name AS dentist_name
         FROM queue_entries q
         JOIN appointments a ON a.id = q.appointment_id
         JOIN users u ON u.id = a.patient_id
         JOIN services s ON s.id = a.service_id
         JOIN dentists d ON d.id = a.dentist_id
         WHERE q.queue_date = CURDATE()
                     AND q.status IN (\'waiting\', \'called\', \'serving\')';
        $category = get_value('category');
        $params = [];
        if ($category !== '') {
                $queue_query .= ' AND s.category = ?';
                $params[] = $category;
        }
        $queue_query .= ' ORDER BY FIELD(q.status, \'serving\', \'called\', \'waiting\'), q.queue_number';
        $stmt = $pdo->prepare($queue_query);
        $stmt->execute($params);
}

$entries = $stmt->fetchAll();
foreach ($entries as &$entry) {
    $entry['next_statuses'] = QUEUE_TRANSITIONS[$entry['status']] ?? [];
}
unset($entry);

json_response(['ok' => true, 'entries' => $entries, 'csrf_token' => csrf_token()]);