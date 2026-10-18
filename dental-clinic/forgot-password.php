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

require __DIR__ . '/includes/header.php';
?>
<section class="form-page">
    <div class="form-aside">
        <p class="eyebrow">Account access</p>
        <h1>A fresh start<br>for your <em>password.</em></h1>
        <p>We’ll send a time-limited reset link if the address matches an active clinic account.</p>
        <span class="aside-index">DCO / RESET</span>
    </div>
    <div class="form-panel">
        <div class="form-heading"><p class="eyebrow">Secure account recovery</p><h2>Reset password</h2><p>Enter the email address on your account.</p></div>
        <?php if ($submitted): ?>
            <div class="alert alert-success" role="status">If an active account matches that address, reset instructions are on the way.</div>
            <p class="form-switch">In this local build, queued messages are visible to clinic administrators in the Notices outbox.</p>
        <?php else: ?>
            <form class="stacked-form" method="post" action="<?= e(url('forgot-password.php')) ?>">
                <?= csrf_field() ?>
                <label for="email">Email address</label><input id="email" name="email" type="email" maxlength="150" autocomplete="email" required>
                <button class="button button-primary button-wide" type="submit">Send reset link</button>
            </form>
        <?php endif; ?>
        <p class="form-switch"><a href="<?= e(url('login.php')) ?>">Return to sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>