<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>Every change, <em>accounted for.</em></h1><p>Recent sign-ins, appointment actions and administrative changes.</p></div>
    <a class="text-link" href="<?= e(url('admin/dashboard.php')) ?>">Back to overview</a>
</section>
<form class="report-filters" method="get" action="<?= e(url('admin/activity.php')) ?>">
    <label>From<input type="date" name="from" value="<?= e($date_from) ?>" required></label>
    <label>Through<input type="date" name="to" value="<?= e($date_to) ?>" required></label>
    <label>Action<input type="text" name="action" value="<?= e($action_filter) ?>" maxlength="60" placeholder="Any action"></label>
    <button class="button button-primary" type="submit">Filter log</button>
</form>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Audit trail</p><h2><?= count($logs) ?> events</h2></div></div>
    <?php if ($logs): ?><div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead><tbody>
        <?php foreach ($logs as $log): ?><tr><td><?= e(format_datetime($log['created_at'])) ?></td><td><?= e($log['full_name'] ?: 'System') ?><span class="table-subline"><?= e($log['email'] ?: '') ?></span></td><td><?= e($log['action']) ?></td><td><?= e($log['details']) ?></td><td><?= e($log['ip_address']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><div class="empty-state"><p>No activity found for this filter.</p></div><?php endif; ?>
</section>
<?php require __DIR__ . '/../../footer.php'; ?>