<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../header.php';
?>
<section class="form-page">
    <div class="form-aside">
        <p class="eyebrow">Account access</p>
        <h1>Back to<br><em>your care.</em></h1>
        <p>Choose a new password to secure your clinic account.</p>
        <span class="aside-index">DCO / RESET</span>
    </div>
    <div class="form-panel">
        <div class="form-heading"><p class="eyebrow">Secure account recovery</p><h2>New password</h2><p>Reset links expire after 30 minutes and can only be used once.</p></div>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <?php if ($reset_record): ?>
            <form class="stacked-form" method="post" action="<?= e(url('reset-password.php?token=' . rawurlencode($token))) ?>">
                <?= csrf_field() ?>
                <label for="password">New password</label><input id="password" name="password" type="password" autocomplete="new-password" required>
                <label for="password_confirmation">Confirm new password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                <p class="field-hint">At least 8 characters, including a letter and a number.</p>
                <button class="button button-primary button-wide" type="submit">Save new password</button>
            </form>
        <?php else: ?>
            <p class="form-switch">Request a new link from <a href="<?= e(url('forgot-password.php')) ?>">password recovery</a>.</p>
        <?php endif; ?>
    </div>
</section>
<?php require __DIR__ . '/../footer.php'; ?>