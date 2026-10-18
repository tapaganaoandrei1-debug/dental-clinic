<?php

function check_in_appointment(int $appointment_id, int $staff_user_id): int
{
    global $pdo;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT id, status, arrived_at FROM appointments
             WHERE id = ? AND appointment_date = CURDATE() FOR UPDATE'
        );
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        if (!$appointment || $appointment['status'] !== 'confirmed' || $appointment['arrived_at'] !== null) {
            throw new DomainException('Only confirmed appointments for today can be checked in.');
        }

        $stmt = $pdo->prepare('SELECT id FROM queue_entries WHERE appointment_id = ?');
        $stmt->execute([$appointment_id]);
        if ($stmt->fetch()) {
            throw new DomainException('This appointment is already in the queue.');
        }

        $queue_date = today_string();
        $stmt = $pdo->prepare(
            'SELECT queue_number FROM queue_entries WHERE queue_date = ?
             ORDER BY queue_number DESC LIMIT 1 FOR UPDATE'
        );
        $stmt->execute([$queue_date]);
        $queue_number = (int) $stmt->fetchColumn() + 1;

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(s.duration_minutes), 0)
             FROM queue_entries q
             JOIN appointments a ON a.id = q.appointment_id
             JOIN services s ON s.id = a.service_id
             WHERE q.queue_date = ? AND q.status IN ('waiting', 'called', 'serving')"
        );
        $stmt->execute([$queue_date]);
        $estimated_wait = min(65535, (int) $stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "INSERT INTO queue_entries
             (appointment_id, queue_number, queue_date, status, checked_in_at, estimated_wait_minutes)
             VALUES (?, ?, ?, 'waiting', NOW(), ?)"
        );
        $stmt->execute([$appointment_id, $queue_number, $queue_date, $estimated_wait]);
        $stmt = $pdo->prepare('UPDATE appointments SET arrived_at = NOW() WHERE id = ?');
        $stmt->execute([$appointment_id]);
        $stmt = $pdo->prepare(
            'SELECT a.patient_id, u.email, s.name AS service_name
             FROM appointments a JOIN users u ON u.id = a.patient_id
             JOIN services s ON s.id = a.service_id WHERE a.id = ?'
        );
        $stmt->execute([$appointment_id]);
        $patient = $stmt->fetch();
        $message = 'Your ' . $patient['service_name'] . ' visit is checked in. Your queue token is ' . format_queue_number($queue_number) . '.';
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, ?, 'email', 'confirmation', ?, 'Queue token issued', ?, NOW(), 'queued')"
        );
        $stmt->execute([$patient['patient_id'], $appointment_id, $patient['email'], $message]);
        log_activity('check_in', 'Checked in appointment #' . $appointment_id . ' as queue number ' . $queue_number, $staff_user_id);
        $pdo->commit();

        return $queue_number;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function transition_queue_entry(int $queue_entry_id, string $next_status, int $staff_user_id): void
{
    global $pdo;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, appointment_id, status FROM queue_entries WHERE id = ? FOR UPDATE');
        $stmt->execute([$queue_entry_id]);
        $entry = $stmt->fetch();
        if (!$entry || !in_array($next_status, QUEUE_TRANSITIONS[$entry['status']] ?? [], true)) {
            throw new DomainException('That queue status change is not allowed.');
        }

        $timestamp_columns = [
            'called' => 'called_at',
            'serving' => 'service_started_at',
            'completed' => 'completed_at',
        ];
        $timestamp_update = isset($timestamp_columns[$next_status]) ? ', ' . $timestamp_columns[$next_status] . ' = NOW()' : '';
        $stmt = $pdo->prepare('UPDATE queue_entries SET status = ?' . $timestamp_update . ' WHERE id = ?');
        $stmt->execute([$next_status, $queue_entry_id]);

        if ($next_status === 'completed' || $next_status === 'no_show' || $next_status === 'cancelled') {
            $appointment_status = $next_status === 'completed' ? 'completed' : $next_status;
            $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
            $stmt->execute([$appointment_status, $entry['appointment_id']]);
        }

        if (in_array($next_status, ['called', 'serving', 'completed', 'no_show', 'cancelled'], true)) {
            $stmt = $pdo->prepare(
                'SELECT a.patient_id, u.email, s.name AS service_name, q.queue_number
                 FROM queue_entries q JOIN appointments a ON a.id = q.appointment_id
                 JOIN users u ON u.id = a.patient_id JOIN services s ON s.id = a.service_id
                 WHERE q.id = ?'
            );
            $stmt->execute([$queue_entry_id]);
            $patient = $stmt->fetch();
            $status_message = [
                'called' => 'Please proceed to the clinic. Your token is being called.',
                'serving' => 'Your visit is now in service.',
                'completed' => 'Your visit is complete. Thank you for choosing SmileCare Dental.',
                'no_show' => 'The clinic recorded this visit as a no-show. Contact the clinic if you need help.',
                'cancelled' => 'Your queue visit has been cancelled.',
            ][$next_status];
            $message = 'Queue update for ' . $patient['service_name'] . ' (' . format_queue_number($patient['queue_number']) . '): ' . $status_message;
            $stmt = $pdo->prepare(
                "INSERT INTO notifications
                 (user_id, appointment_id, channel, type, recipient, subject, message, scheduled_for, status)
                 VALUES (?, ?, 'email', 'reminder', ?, 'Queue status update', ?, NOW(), 'queued')"
            );
            $stmt->execute([$patient['patient_id'], $entry['appointment_id'], $patient['email'], $message]);
        }

        log_activity('queue_' . $next_status, 'Queue entry #' . $queue_entry_id . ' changed to ' . $next_status, $staff_user_id);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}