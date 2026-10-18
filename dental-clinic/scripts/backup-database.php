<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/backup.php';

$backup_directory = getenv('DENTAL_BACKUP_DIR');
if (!$backup_directory) {
    fwrite(STDERR, "Set DENTAL_BACKUP_DIR to a protected backup folder.\n");
    exit(2);
}
if (!is_dir($backup_directory) && !mkdir($backup_directory, 0700, true) && !is_dir($backup_directory)) {
    fwrite(STDERR, "Could not create the backup folder.\n");
    exit(2);
}

$backup_path = rtrim($backup_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'dental_clinic_' . date('Ymd_His') . '.sql';
$stream = fopen($backup_path, 'xb');
if (!$stream) {
    fwrite(STDERR, "Could not create the backup file.\n");
    exit(2);
}

try {
    write_database_dump($stream);
    fclose($stream);
    echo 'Database backup created: ' . $backup_path . PHP_EOL;
} catch (Throwable $exception) {
    fclose($stream);
    if (is_file($backup_path)) unlink($backup_path);
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}