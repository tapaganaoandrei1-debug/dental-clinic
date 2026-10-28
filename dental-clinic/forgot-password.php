<?php
require_once __DIR__ . '/config/config.php';
redirect_if_logged_in();

$page_title = 'Reset password';
$active_nav = 'login';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = strtolower(post_value('email'));
    if (is_valid_email($email)) {
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 AND email_verified_at IS NOT NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                $stmt->execute([$user['id'], hash('sha256', $token)]);

                $reset_url = absolute_url('reset-password.php?token=' . rawurlencode($token));
                $message = 'Use this link within 30 minutes to reset your password: ' . $reset_url;
                $stmt = $pdo->prepare(
                    "INSERT INTO notifications
                     (user_id, channel, type, recipient, subject, message, scheduled_for, status)
                     VALUES (?, 'email', 'password_reset', ?, 'Password reset request', ?, NOW(), 'queued')"
                );
                $stmt->execute([$user['id'], $user['email'], $message]);
                $notification_id = (int) $pdo->lastInsertId();
                $pdo->commit();

                $headers = "From: Dental Clinic <no-reply@localhost>\r\nContent-Type: text/plain; charset=UTF-8";
                if (function_exists('mail') && @mail($user['email'], 'Password reset request', $message, $headers)) {
                    $stmt = $pdo->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$notification_id]);
                } else {
                    log_activity('password_reset_queued', 'Queued reset notice for account #' . $user['id'], (int) $user['id']);
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Password reset request failed: ' . $exception->getMessage());
            }
        }
    }
    $submitted = true;
}

require __DIR__ . '/includes/views/forgot-password.php';
