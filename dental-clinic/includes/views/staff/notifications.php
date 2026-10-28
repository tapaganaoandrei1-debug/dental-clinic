<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Operations · Simulation</p><h1>Message <em>outbox.</em></h1><p>Review queued appointment and reminder notices. Delivery is simulated in this local build.</p></div>
    <a class="text-link" href="<?= e(url('staff/dashboard.php')) ?>">Back to overview</a>
</section>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Email and SMS queue</p><h2><?= count($notifications) ?> notices</h2></div></div>
    <?php if ($notifications): ?>
        <div class="notification-list">
            <?php foreach ($notifications as $notification): ?>
                <article class="notification-item">
                    <div class="notification-heading"><div><span class="notification-channel"><?= e(strtoupper($notification['channel'])) ?></span><span class="notification-type"><?= e(ucfirst($notification['type'])) ?></span></div><?= status_badge($notification['status']) ?></div>
                    <h3><?= e($notification['subject'] ?: $notification['type']) ?></h3>
                    <p class="notification-recipient"><?= e($notification['full_name']) ?> · <?= e($notification['recipient']) ?> · <?= e(format_datetime($notification['scheduled_for'])) ?></p>
                    <p class="notification-message"><?= e($notification['message']) ?></p>
                    <?php if ($notification['status'] === 'queued'): ?>
                        <form method="post" action="<?= e(url('staff/notifications.php')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="simulate"><input type="hidden" name="notification_id" value="<?= (int) $notification['id'] ?>"><button class="small-link" type="submit">Mark simulated sent</button></form>
                    <?php elseif ($notification['sent_at']): ?><p class="notification-recipient">Simulated at <?= e(format_datetime($notification['sent_at'])) ?></p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?><div class="empty-state"><p>No notifications have been queued.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>