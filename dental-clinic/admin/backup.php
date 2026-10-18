<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/backup.php';
require_role(ROLE_ADMIN);

if (get_value('download') === '1') {
    $stream = fopen('php://temp', 'w+b');
    try {
        write_database_dump($stream);
        rewind($stream);
        log_activity('database_backup_downloaded', 'Downloaded a database backup');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="dental_clinic_' . date('Ymd_His') . '.sql"');
        header('Cache-Control: no-store');
        fpassthru($stream);
        fclose($stream);
        exit;
    } catch (Throwable $exception) {
        fclose($stream);
        error_log('Database backup failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('Database backup is unavailable. Check MYSQLDUMP_PATH and the local MySQL configuration.');
    }
}

$page_title = 'Database backup';
$active_nav = 'backup';
require __DIR__ . '/../includes/header.php';
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
<?php require __DIR__ . '/../includes/footer.php'; ?>