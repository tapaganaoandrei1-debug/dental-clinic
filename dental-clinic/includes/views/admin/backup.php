<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>Protect the <em>records.</em></h1><p>Create a SQL backup of the clinic database.</p></div>
    <a class="text-link" href="<?= e(url('admin/dashboard.php')) ?>">Back to overview</a>
</section>
<section class="backup-panel">
    <div><p class="eyebrow">Database backup</p><h2>Download a complete SQL snapshot.</h2><p>The export uses a consistent database transaction and is streamed directly to your browser. Database passwords are passed through the process environment, not command arguments.</p></div>
    <a class="button button-primary" href="<?= e(url('admin/backup.php?download=1')) ?>">Download SQL backup</a>
</section>
<p class="backup-note">For scheduled backups, run <code>scripts/backup-database.php</code> from Windows Task Scheduler after setting a protected <code>DENTAL_BACKUP_DIR</code>.</p>
<?php require __DIR__ . '/../../footer.php'; ?>