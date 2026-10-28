<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$db = Database::getConnection();

if ($action === 'live-status') {
    $token = trim($_GET['token'] ?? '');
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT * FROM queue_tokens WHERE token_number = ? AND queue_date = ?");
    $stmt->execute([$token, $today]);
    $my = $stmt->fetch();
    if (!$my) sendJson(['status' => 'error', 'message' => 'Token not found for today'], 404);

    $stmt = $db->prepare("SELECT token_number FROM queue_tokens WHERE queue_date = ? AND queue_status = 'serving' LIMIT 1");
    $stmt->execute([$today]);
    $active = $stmt->fetch()['token_number'] ?? 'None';

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM queue_tokens WHERE queue_date = ? AND queue_status = 'waiting' AND priority_order < ?");
    $stmt->execute([$today, $my['priority_order']]);
    $ahead = (int)$stmt->fetch()['c'];

    sendJson(['status' => 'success', 'my_token' => $my['token_number'], 'current_called' => $active, 'ahead' => $ahead, 'wait_estimate' => ($ahead * 25) . ' mins']);
}

if ($action === 'admin-roster') {
    $today = date('Y-m-d');
    $stmt = $db->prepare("SELECT q.id, q.token_number, q.priority_order, q.queue_status, a.reference_no, s.name as service_name FROM queue_tokens q JOIN appointments a ON q.appointment_id = a.id JOIN services s ON a.service_id = s.id WHERE q.queue_date = ? ORDER BY q.priority_order ASC");
    $stmt->execute([$today]);
    sendJson(['status' => 'success', 'queue' => $stmt->fetchAll()]);
}

if ($action === 'admin-action' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['token_id'] ?? 0);
    $op = $input['operation'] ?? '';
    if ($op === 'call') $status = 'serving';
    elseif ($op === 'serve') $status = 'served';
    elseif ($op === 'no_show') $status = 'no_show';
    else $status = 'waiting';

    $stmt = $db->prepare("UPDATE queue_tokens SET queue_status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    sendJson(['status' => 'success']);
}
