<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../header.php';
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
<?php require __DIR__ . '/../footer.php'; ?>