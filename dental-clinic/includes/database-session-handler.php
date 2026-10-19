<?php

final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    public function __construct(private PDO $pdo)
    {
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT session_data FROM dental_sessions WHERE session_id = ? AND last_activity >= ?'
        );
        $stmt->execute([$id, time() - (int) ini_get('session.gc_maxlifetime')]);
        $data = $stmt->fetchColumn();
        return $data === false ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dental_sessions (session_id, session_data, last_activity) VALUES (?, ?, ?)'
            . ' ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)'
        );
        return $stmt->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM dental_sessions WHERE session_id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM dental_sessions WHERE last_activity < ?');
        $stmt->execute([time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    public function validateId(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM dental_sessions WHERE session_id = ? AND last_activity >= ?'
        );
        $stmt->execute([$id, time() - (int) ini_get('session.gc_maxlifetime')]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return $this->write($id, $data);
    }
}
