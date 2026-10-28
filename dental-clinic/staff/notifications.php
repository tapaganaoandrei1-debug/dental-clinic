<?php
require_once __DIR__ . '/../config/config.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'Notification outbox';
$active_nav = 'notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $notification_id = filter_var($_POST['notification_id'] ?? null, FILTER_VALIDATE_INT);
    if (post_value('action') !== 'simulate' || !$notification_id) {
        set_flash('error', 'Choose a valid queued notification.');
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE id = ? AND status = 'queued'");
        $stmt->execute([$notification_id]);
        if ($stmt->rowCount()) {
            log_activity('notification_simulated', 'Simulated delivery for notification #' . $notification_id);
            set_flash('success', 'Notification marked as simulated. No email or SMS was sent.');
        } else {
            set_flash('error', 'That notification is no longer queued.');
        }
    }
    redirect('staff/notifications.php');
}

$notifications_query =
    'SELECT n.id, n.channel, n.type, n.recipient, n.subject, n.message,
            n.scheduled_for, n.status, n.sent_at, u.full_name
     FROM notifications n JOIN users u ON u.id = n.user_id
    WHERE (? = \'admin\' OR n.type NOT IN (\'password_reset\', \'email_verification\'))
     ORDER BY (n.status = \'queued\') DESC, n.scheduled_for DESC LIMIT 150';
$stmt = $pdo->prepare($notifications_query);
$stmt->execute([current_role()]);
$notifications = $stmt->fetchAll();

require __DIR__ . '/../includes/views/staff/notifications.php';
