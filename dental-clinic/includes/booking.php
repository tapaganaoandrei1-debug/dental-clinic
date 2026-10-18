<?php

function available_appointment_slots(int $service_id, int $dentist_id, string $date, int $exclude_appointment_id = 0): array
{
    global $pdo;

    if ($service_id < 1 || $dentist_id < 0 || !is_valid_date($date)) {
        return [];
    }

    if ($dentist_id === 0) {
        $stmt = $pdo->prepare(
            'SELECT ds.dentist_id FROM dentist_services ds
             JOIN dentists d ON d.id = ds.dentist_id
             WHERE ds.service_id = ? AND d.is_active = 1 ORDER BY ds.dentist_id'
        );
        $stmt->execute([$service_id]);
        $slots = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $available_dentist_id) {
            $slots = array_merge($slots, available_appointment_slots($service_id, (int) $available_dentist_id, $date, $exclude_appointment_id));
        }
        $slots = array_values(array_unique($slots));
        sort($slots);
        return $slots;
    }

    $days_ahead = max(1, (int) get_setting('booking_days_ahead', '60'));
    $last_booking_date = date('Y-m-d', strtotime('+' . $days_ahead . ' days'));
    if ($date < today_string() || $date > $last_booking_date) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT s.duration_minutes
         FROM services s
         JOIN dentist_services ds ON ds.service_id = s.id
         JOIN dentists d ON d.id = ds.dentist_id
         WHERE s.id = ? AND ds.dentist_id = ? AND s.is_active = 1 AND d.is_active = 1'
    );
    $stmt->execute([$service_id, $dentist_id]);
    $service = $stmt->fetch();
    if (!$service) {
        return [];
    }

    $day_of_week = (int) date('w', strtotime($date));
    $stmt = $pdo->prepare(
        'SELECT id, start_time, end_time FROM schedules
         WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$dentist_id, $day_of_week]);
    $schedule = $stmt->fetch();
    if (!$schedule) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM holidays
         WHERE holiday_date = ? AND (dentist_id IS NULL OR dentist_id = ?) LIMIT 1'
    );
    $stmt->execute([$date, $dentist_id]);
    if ($stmt->fetch()) {
        return [];
    }

    $stmt = $pdo->prepare('SELECT break_start, break_end FROM schedule_breaks WHERE schedule_id = ?');
    $stmt->execute([$schedule['id']]);
    $breaks = $stmt->fetchAll();

        $booked_query = "SELECT id, start_time, end_time FROM appointments
                                         WHERE dentist_id = ? AND appointment_date = ?
                                             AND status IN ('pending', 'confirmed', 'completed')";
        $booked_params = [$dentist_id, $date];
        if ($exclude_appointment_id > 0) {
                $booked_query .= ' AND id <> ?';
                $booked_params[] = $exclude_appointment_id;
        }
        $stmt = $pdo->prepare($booked_query);
        $stmt->execute($booked_params);
    $booked = $stmt->fetchAll();

    $duration = (int) $service['duration_minutes'];
    $interval = max(5, (int) get_setting('slot_interval_minutes', '30'));
    $schedule_start = time_to_minutes($schedule['start_time']);
    $schedule_end = time_to_minutes($schedule['end_time']);
    $slots = [];

    for ($start = $schedule_start; $start + $duration <= $schedule_end; $start += $interval) {
        $end = $start + $duration;

        if ($date === today_string() && $start <= ((int) date('G') * 60 + (int) date('i'))) {
            continue;
        }

        $overlaps_break = false;
        foreach ($breaks as $break) {
            if ($start < time_to_minutes($break['break_end']) && $end > time_to_minutes($break['break_start'])) {
                $overlaps_break = true;
                break;
            }
        }
        if ($overlaps_break) {
            continue;
        }

        $overlaps_appointment = false;
        foreach ($booked as $appointment) {
            if ($start < time_to_minutes($appointment['end_time']) && $end > time_to_minutes($appointment['start_time'])) {
                $overlaps_appointment = true;
                break;
            }
        }
        if (!$overlaps_appointment) {
            $slots[] = sprintf('%02d:%02d', intdiv($start, 60), $start % 60);
        }
    }

    return $slots;
}

function patient_can_change_appointment(array $appointment): bool
{
    if (!in_array($appointment['status'], ['pending', 'confirmed'], true)) {
        return false;
    }
    $change_hours = max(0, (int) get_setting('min_hours_before_change', '2'));
    $appointment_time = new DateTime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
    $change_deadline = new DateTime('+' . $change_hours . ' hours');
    return $appointment_time > $change_deadline;
}

function create_patient_appointment(int $patient_id, int $service_id, int $dentist_id, string $date, string $start_time, string $notes): int
{
    global $pdo;

    if (!is_valid_date($date) || !is_valid_time($start_time)) {
        throw new DomainException('Choose a valid date and available appointment time.');
    }

    $pdo->beginTransaction();
    try {
        $day_of_week = (int) date('w', strtotime($date));
        if ($dentist_id === 0) {
            $stmt = $pdo->prepare(
                'SELECT ds.dentist_id FROM dentist_services ds
                 JOIN dentists d ON d.id = ds.dentist_id
                 WHERE ds.service_id = ? AND d.is_active = 1 ORDER BY ds.dentist_id'
            );
            $stmt->execute([$service_id]);
            $dentist_candidates = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $dentist_candidates = [$dentist_id];
        }

        $resolved_dentist_id = 0;
        foreach ($dentist_candidates as $candidate_id) {
            $stmt = $pdo->prepare(
                'SELECT id FROM schedules
                 WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$candidate_id, $day_of_week]);
            if (!$stmt->fetch()) continue;
            $candidate_slots = available_appointment_slots($service_id, $candidate_id, $date);
            if (in_array($start_time, $candidate_slots, true)) {
                $resolved_dentist_id = $candidate_id;
                break;
            }
        }
        if ($resolved_dentist_id < 1) {
            throw new DomainException('That time is no longer available. Please choose another slot.');
        }
        $dentist_id = $resolved_dentist_id;

        $stmt = $pdo->prepare(
            'SELECT s.name, s.duration_minutes, u.email
             FROM services s
             JOIN dentist_services ds ON ds.service_id = s.id
             JOIN dentists d ON d.id = ds.dentist_id
             JOIN users u ON u.id = ?
             WHERE s.id = ? AND ds.dentist_id = ? AND s.is_active = 1 AND d.is_active = 1'
        );
        $stmt->execute([$patient_id, $service_id, $dentist_id]);
        $details = $stmt->fetch();
        if (!$details) {
            throw new DomainException('Choose a service and dentist from the available options.');
        }

        $start_minutes = time_to_minutes($start_time . ':00');
        $end_time = minutes_to_time($start_minutes + (int) $details['duration_minutes']);
        $stmt = $pdo->prepare(
            "INSERT INTO appointments
             (patient_id, service_id, dentist_id, appointment_date, start_time, end_time, status, patient_notes)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([$patient_id, $service_id, $dentist_id, $date, $start_time . ':00', $end_time, $notes !== '' ? $notes : null]);
        $appointment_id = (int) $pdo->lastInsertId();

        $message = 'Your ' . $details['name'] . ' appointment is requested for ' . format_date($date) . ' at ' . format_time($start_time) . '.';
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, ?, 'email', 'confirmation', ?, ?, ?, NOW(), 'queued')"
        );
        $stmt->execute([$patient_id, $appointment_id, $details['email'], 'Appointment request received', $message]);
        log_activity('appointment_booked', 'Requested appointment #' . $appointment_id, $patient_id);
        $pdo->commit();

        return $appointment_id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function reschedule_patient_appointment(int $patient_id, int $appointment_id, string $date, string $start_time): void
{
    global $pdo;

    if (!is_valid_date($date) || !is_valid_time($start_time)) {
        throw new DomainException('Choose a valid date and available appointment time.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ? FOR UPDATE');
        $stmt->execute([$appointment_id, $patient_id]);
        $appointment = $stmt->fetch();
        if (!$appointment || !patient_can_change_appointment($appointment)) {
            throw new DomainException('This appointment can no longer be changed online. Contact the clinic for help.');
        }

        $day_of_week = (int) date('w', strtotime($date));
        $stmt = $pdo->prepare(
            'SELECT id FROM schedules
             WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1 FOR UPDATE'
        );
        $stmt->execute([$appointment['dentist_id'], $day_of_week]);
        if (!$stmt->fetch()) {
            throw new DomainException('That dentist is not scheduled on this date. Choose another time.');
        }

        $available_slots = available_appointment_slots(
            (int) $appointment['service_id'],
            (int) $appointment['dentist_id'],
            $date,
            $appointment_id
        );
        if (!in_array($start_time, $available_slots, true)) {
            throw new DomainException('That time is no longer available. Please choose another slot.');
        }

        $stmt = $pdo->prepare('SELECT name, duration_minutes FROM services WHERE id = ?');
        $stmt->execute([$appointment['service_id']]);
        $service = $stmt->fetch();
        $end_time = minutes_to_time(time_to_minutes($start_time . ':00') + (int) $service['duration_minutes']);
        $stmt = $pdo->prepare(
            "UPDATE appointments SET appointment_date = ?, start_time = ?, end_time = ?, status = 'pending'
             WHERE id = ? AND patient_id = ?"
        );
        $stmt->execute([$date, $start_time . ':00', $end_time, $appointment_id, $patient_id]);

        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$patient_id]);
        $email = $stmt->fetchColumn();
        $message = 'Your ' . $service['name'] . ' appointment was moved to ' . format_date($date) . ' at ' . format_time($start_time) . '. The clinic will confirm the new time.';
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, ?, 'email', 'reschedule', ?, ?, ?, NOW(), 'queued')"
        );
        $stmt->execute([$patient_id, $appointment_id, $email, 'Appointment reschedule requested', $message]);
        log_activity('appointment_rescheduled', 'Rescheduled appointment #' . $appointment_id, $patient_id);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function cancel_patient_appointment(int $patient_id, int $appointment_id, string $reason): void
{
    global $pdo;

    if (strlen($reason) > 255) {
        throw new DomainException('Cancellation reason must be 255 characters or fewer.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM appointments WHERE id = ? AND patient_id = ? FOR UPDATE');
        $stmt->execute([$appointment_id, $patient_id]);
        $appointment = $stmt->fetch();
        if (!$appointment || !patient_can_change_appointment($appointment)) {
            throw new DomainException('This appointment can no longer be changed online. Contact the clinic for help.');
        }

        $stmt = $pdo->prepare(
            "UPDATE appointments SET status = 'cancelled', cancellation_reason = ?
             WHERE id = ? AND patient_id = ?"
        );
        $stmt->execute([$reason !== '' ? $reason : null, $appointment_id, $patient_id]);
        $stmt = $pdo->prepare(
            'SELECT u.email, s.name FROM users u
             JOIN appointments a ON a.patient_id = u.id
             JOIN services s ON s.id = a.service_id
             WHERE u.id = ? AND a.id = ?'
        );
        $stmt->execute([$patient_id, $appointment_id]);
        $details = $stmt->fetch();
        $message = 'Your ' . $details['name'] . ' appointment on ' . format_date($appointment['appointment_date']) . ' at ' . format_time($appointment['start_time']) . ' was cancelled.';
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, ?, 'email', 'cancellation', ?, ?, ?, NOW(), 'queued')"
        );
        $stmt->execute([$patient_id, $appointment_id, $details['email'], 'Appointment cancelled', $message]);
        log_activity('appointment_cancelled', 'Cancelled appointment #' . $appointment_id, $patient_id);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function create_walk_in_appointment(int $patient_id, int $service_id, int $dentist_id, string $date, string $start_time, string $notes, int $staff_user_id): array
{
    global $pdo;

    if ($date !== today_string() || !is_valid_time($start_time) || strlen($notes) > 2000) {
        throw new DomainException('Walk-in bookings must use an open time today.');
    }

    $pdo->beginTransaction();
    try {
        if ($dentist_id === 0) {
            $stmt = $pdo->prepare(
                'SELECT ds.dentist_id FROM dentist_services ds
                 JOIN dentists d ON d.id = ds.dentist_id
                 WHERE ds.service_id = ? AND d.is_active = 1 ORDER BY ds.dentist_id'
            );
            $stmt->execute([$service_id]);
            $dentist_candidates = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } else {
            $dentist_candidates = [$dentist_id];
        }

        $resolved_dentist_id = 0;
        $day_of_week = (int) date('w', strtotime($date));
        foreach ($dentist_candidates as $candidate_id) {
            $stmt = $pdo->prepare(
                'SELECT id FROM schedules
                 WHERE dentist_id = ? AND day_of_week = ? AND is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$candidate_id, $day_of_week]);
            if (!$stmt->fetch()) continue;
            if (in_array($start_time, available_appointment_slots($service_id, $candidate_id, $date), true)) {
                $resolved_dentist_id = $candidate_id;
                break;
            }
        }
        if (!$resolved_dentist_id) {
            throw new DomainException('That time is no longer available. Choose another slot.');
        }

        $stmt = $pdo->prepare(
            "SELECT s.name, s.duration_minutes, u.email
             FROM services s
             JOIN dentist_services ds ON ds.service_id = s.id
             JOIN dentists d ON d.id = ds.dentist_id
             JOIN users u ON u.id = ? AND u.role = 'patient' AND u.is_active = 1
             WHERE s.id = ? AND ds.dentist_id = ? AND s.is_active = 1 AND d.is_active = 1"
        );
        $stmt->execute([$patient_id, $service_id, $resolved_dentist_id]);
        $details = $stmt->fetch();
        if (!$details) throw new DomainException('Choose an active patient and an available service.');

        $start_minutes = time_to_minutes($start_time . ':00');
        $end_time = minutes_to_time($start_minutes + (int) $details['duration_minutes']);
        $stmt = $pdo->prepare(
            "INSERT INTO appointments
             (patient_id, service_id, dentist_id, appointment_date, start_time, end_time, status, patient_notes, arrived_at)
             VALUES (?, ?, ?, ?, ?, ?, 'confirmed', ?, NOW())"
        );
        $stmt->execute([$patient_id, $service_id, $resolved_dentist_id, $date, $start_time . ':00', $end_time, $notes !== '' ? $notes : null]);
        $appointment_id = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare('SELECT queue_number FROM queue_entries WHERE queue_date = ? ORDER BY queue_number DESC LIMIT 1 FOR UPDATE');
        $stmt->execute([$date]);
        $queue_number = (int) $stmt->fetchColumn() + 1;
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(s.duration_minutes), 0) FROM queue_entries q
             JOIN appointments a ON a.id = q.appointment_id JOIN services s ON s.id = a.service_id
             WHERE q.queue_date = ? AND q.status IN ('waiting', 'called', 'serving')"
        );
        $stmt->execute([$date]);
        $estimated_wait = min(65535, (int) $stmt->fetchColumn());
        $stmt = $pdo->prepare(
            "INSERT INTO queue_entries
             (appointment_id, queue_number, queue_date, status, checked_in_at, estimated_wait_minutes)
             VALUES (?, ?, ?, 'waiting', NOW(), ?)"
        );
        $stmt->execute([$appointment_id, $queue_number, $date, $estimated_wait]);

        $message = 'Your walk-in ' . $details['name'] . ' visit has been checked in as ' . format_queue_number($queue_number) . '.';
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, ?, 'email', 'confirmation', ?, 'Walk-in checked in', ?, NOW(), 'queued')"
        );
        $stmt->execute([$patient_id, $appointment_id, $details['email'], $message]);
        log_activity('walk_in_created', 'Created walk-in appointment #' . $appointment_id . ' as queue ' . $queue_number, $staff_user_id);
        $pdo->commit();

        return ['appointment_id' => $appointment_id, 'queue_number' => $queue_number];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $exception;
    }
}