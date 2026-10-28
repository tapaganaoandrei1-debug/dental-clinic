<?php
require_once __DIR__ . '/config/config.php';
redirect_if_logged_in();

$page_title = 'Choose a new password';
$active_nav = 'login';
$token = get_value('token');
$error = '';
$reset_record = null;

if (preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT r.id, r.user_id FROM password_reset_tokens r
         JOIN users u ON u.id = r.user_id
         WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $reset_record = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $confirmation = isset($_POST['password_confirmation']) && is_string($_POST['password_confirmation']) ? $_POST['password_confirmation'] : '';
    $password_error_message = password_error($password);
    if (!$reset_record) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } elseif ($password_error_message !== '') {
        $error = $password_error_message;
    } elseif ($password !== $confirmation) {
        $error = 'The passwords do not match.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT r.id, r.user_id FROM password_reset_tokens r
                 JOIN users u ON u.id = r.user_id
                 WHERE r.id = ? AND r.token_hash = ? AND r.used_at IS NULL
                   AND r.expires_at > NOW() AND u.is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$reset_record['id'], hash('sha256', $token)]);
            $locked_record = $stmt->fetch();
            if (!$locked_record) {
                throw new DomainException('This reset link is invalid or has expired. Request a new one.');
            }
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $locked_record['user_id']]);
            $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
            $stmt->execute([$locked_record['id']]);
            $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?');
            $stmt->execute([$locked_record['user_id'], $locked_record['id']]);
            $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE user_id = ? AND type = 'password_reset' AND status = 'queued'");
            $stmt->execute([$locked_record['user_id']]);
            log_activity('password_reset_completed', 'Password reset completed', (int) $locked_record['user_id']);
            $pdo->commit();
            set_flash('success', 'Your password has been updated. Sign in with your new password.');
            redirect('login.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Password reset failed: ' . $exception->getMessage());
            $error = 'The password could not be updated. Request a new reset link and try again.';
        }
    }
}

require __DIR__ . '/includes/views/reset-password.php';
