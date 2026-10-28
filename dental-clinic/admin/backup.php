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
require __DIR__ . '/../includes/views/admin/backup.php';
