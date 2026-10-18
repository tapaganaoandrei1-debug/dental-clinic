<?php
require_once __DIR__ . '/config/config.php';

$page_title = 'Verify email';
$active_nav = 'register';
$token = get_value('token');
$error = '';
$verification = null;

if (preg_match('/\A[a-f0-9]{64}\z/', $token)) {
    $stmt = $pdo->prepare(
        'SELECT t.id, t.user_id, u.email, u.email_verified_at
         FROM email_verification_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.used_at IS NULL
           AND t.expires_at > NOW() AND u.is_active = 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    $verification = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    if (!$verification) {
        $error = 'This verification link is invalid or has expired. Create a new account or contact the clinic.';
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT t.id, t.user_id FROM email_verification_tokens t
                 JOIN users u ON u.id = t.user_id
                 WHERE t.id = ? AND t.token_hash = ? AND t.used_at IS NULL
                   AND t.expires_at > NOW() AND u.is_active = 1 FOR UPDATE'
            );
            $stmt->execute([$verification['id'], hash('sha256', $token)]);
            $locked_token = $stmt->fetch();
            if (!$locked_token) {
                throw new DomainException('This verification link is invalid or has expired. Create a new account or contact the clinic.');
            }
            $stmt = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?');
            $stmt->execute([$locked_token['user_id']]);
            $stmt = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?');
            $stmt->execute([$locked_token['id']]);
            $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE user_id = ? AND type = 'email_verification' AND status = 'queued'");
            $stmt->execute([$locked_token['user_id']]);
            log_activity('email_verified', 'Verified account email', (int) $locked_token['user_id']);
            $pdo->commit();
            set_flash('success', 'Your email is verified. You can sign in now.');
            redirect('login.php');
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Email verification failed: ' . $exception->getMessage());
            $error = 'We could not verify this address. Please try again.';
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<section class="form-page">
    <div class="form-aside">
        <p class="eyebrow">One quick check</p>
        <h1>Confirm your<br><em>email address.</em></h1>
        <p>This helps keep your clinic account connected to the right person.</p>
        <span class="aside-index">DCO / VERIFY</span>
    </div>
    <div class="form-panel">
        <div class="form-heading"><p class="eyebrow">Patient portal</p><h2>Verify your email</h2><p>Verification links expire after 24 hours and can only be used once.</p></div>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($verification): ?>
            <p class="verification-address"><?= e($verification['email']) ?></p>
            <form class="stacked-form" method="post" action="<?= e(url('verify-email.php?token=' . rawurlencode($token))) ?>">
                <?= csrf_field() ?>
                <button class="button button-primary button-wide" type="submit">Verify email address</button>
            </form>
        <?php else: ?>
            <p class="form-switch"><a href="<?= e(url('register.php')) ?>">Create a new account</a> or contact the clinic.</p>
        <?php endif; ?>
        <p class="form-switch"><a href="<?= e(url('login.php')) ?>">Return to sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>