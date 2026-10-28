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
<?php require __DIR__ . '/../footer.php'; ?>