<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$db = Database::getConnection();

if ($action === 'available-slots') {
    $staffId = (int)($_GET['staff_id'] ?? 0);
    $serviceId = (int)($_GET['service_id'] ?? 0);
    $date = $_GET['date'] ?? '';
    if (!$staffId || !$serviceId || !$date) sendJson(['status' => 'error', 'message' => 'Missing info'], 400);

    $dayOfWeek = (int)date('w', strtotime($date));
    $stmt = $db->prepare("SELECT * FROM staff_schedules WHERE staff_id = ? AND day_of_week = ?");
    $stmt->execute([$staffId, $dayOfWeek]);
    $schedule = $stmt->fetch();
    if (!$schedule) sendJson(['status' => 'success', 'slots' => []]);

    $slots = [
        ['start_time' => '09:00:00', 'display' => '09:00 AM - 09:45 AM'],
        ['start_time' => '10:00:00', 'display' => '10:00 AM - 10:45 AM'],
        ['start_time' => '11:00:00', 'display' => '11:00 AM - 11:45 AM'],
        ['start_time' => '14:00:00', 'display' => '02:00 PM - 02:45 PM'],
        ['start_time' => '15:00:00', 'display' => '03:00 PM - 03:45 PM']
    ];
    sendJson(['status' => 'success', 'slots' => $slots]);
}

if ($action === 'book') {
    if (empty($_SESSION['user_id'])) sendJson(['status' => 'error', 'message' => 'Please sign in to book.'], 401);
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $refNo = 'DC-' . strtoupper(bin2hex(random_bytes(3)));
    $staffId = (int)($input['staff_id'] ?? 2);
    $serviceId = (int)($input['service_id'] ?? 1);
    $date = $input['date'] ?? date('Y-m-d');
    $startTime = $input['start_time'] ?? '09:00:00';
    $endTime = date('H:i:s', strtotime($startTime) + 2700);

    $stmt = $db->prepare("INSERT INTO appointments (reference_no, client_id, staff_id, service_id, appointment_date, start_time, end_time, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'approved')");
    $stmt->execute([$refNo, $_SESSION['user_id'], $staffId, $serviceId, $date, $startTime, $endTime]);
    $apptId = (int)$db->lastInsertId();

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM queue_tokens WHERE queue_date = ?");
    $stmt->execute([$date]);
    $seq = (int)$stmt->fetch()['c'] + 1;
    $tokenNum = sprintf('Q%s-%03d', date('md', strtotime($date)), $seq);

    $stmt = $db->prepare("INSERT INTO queue_tokens (token_number, appointment_id, queue_date, priority_order, queue_status) VALUES (?, ?, ?, ?, 'waiting')");
    $stmt->execute([$tokenNum, $apptId, $date, $seq]);
    sendJson(['status' => 'success', 'reference_no' => $refNo, 'token' => $tokenNum]);
}
