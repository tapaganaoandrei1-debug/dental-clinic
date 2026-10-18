<?php

function write_database_dump($output_stream): void
{
    $dump_path = getenv('MYSQLDUMP_PATH') ?: 'C:/xampp/mysql/bin/mysqldump.exe';
    if (!is_file($dump_path) || !function_exists('proc_open')) {
        throw new RuntimeException('The database dump utility is unavailable. Configure MYSQLDUMP_PATH.');
    }

    $environment = getenv();
    if (!is_array($environment)) $environment = [];
    $environment['MYSQL_PWD'] = DB_PASS;
    $command = [
        $dump_path,
        '--single-transaction',
        '--quick',
        '--no-tablespaces',
        '--default-character-set=utf8mb4',
        '--host=' . DB_HOST,
        '--user=' . DB_USER,
        DB_NAME,
    ];
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!is_resource($process)) {
        throw new RuntimeException('The database dump process could not start.');
    }

    fclose($pipes[0]);
    stream_copy_to_stream($pipes[1], $output_stream);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exit_code = proc_close($process);
    if ($exit_code !== 0) {
        throw new RuntimeException('Database dump failed: ' . trim((string) $error));
    }
}