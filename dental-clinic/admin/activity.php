<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'Activity log';
$active_nav = 'activity';
$date_from = get_value('from') ?: date('Y-m-d', strtotime('-6 days'));
$date_to = get_value('to') ?: today_string();
$action_filter = get_value('action');
if (!is_valid_date($date_from) || !is_valid_date($date_to) || $date_from > $date_to) {
    http_response_code(400);
    exit('Choose a valid date range.');
}
$stmt = $pdo->prepare(
    'SELECT l.id, l.action, l.details, l.ip_address, l.created_at, u.full_name, u.email
     FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id
     WHERE l.created_at >= ? AND l.created_at < DATE_ADD(?, INTERVAL 1 DAY)
       AND (? = \'\' OR l.action = ?)
     ORDER BY l.created_at DESC LIMIT 300'
);
$stmt->execute([$date_from, $date_to, $action_filter, $action_filter]);
$logs = $stmt->fetchAll();

require __DIR__ . '/../includes/views/admin/activity.php';
