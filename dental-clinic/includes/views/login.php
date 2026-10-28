<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../header.php';
?>
<section class="form-page login-page">
    <div class="form-aside">
        <p class="eyebrow">Welcome back</p>
        <h1>Your care,<br><em>all in one place.</em></h1>
        <p>Sign in to keep up with your appointments and clinic updates.</p>
        <span class="aside-index">DCO / 02</span>
    </div>
    <div class="form-panel">
        <div class="form-heading"><p class="eyebrow">Patient portal</p><h2>Sign in</h2><p>Use the email address connected to your account.</p></div>
        <nav class="auth-tabs" aria-label="Account access"><a class="is-active" aria-current="page" href="<?= e(url('login.php')) ?>">Log in</a><a href="<?= e(url('register.php')) ?>">Sign up</a></nav>
        <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="stacked-form" action="<?= e(url('login.php')) ?>" method="post">
            <?= csrf_field() ?>
            <?php if ($redirect_target !== ''): ?><input type="hidden" name="redirect" value="<?= e($redirect_target) ?>"><?php endif; ?>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" maxlength="150" required>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
            <button class="button button-primary button-wide" type="submit">Sign in <span aria-hidden="true">&#8594;</span></button>
        </form>
        <p class="form-switch"><a href="<?= e(url('forgot-password.php')) ?>">Forgot your password?</a></p>
        <p class="form-switch">New to the clinic? <a href="<?= e(url('register.php')) ?>">Create an account</a></p>
    </div>
</section>
<?php require __DIR__ . '/../footer.php'; ?>