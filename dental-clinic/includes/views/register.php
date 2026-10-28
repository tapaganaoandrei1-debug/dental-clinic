<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../header.php';
?>
<section class="form-page register-page">
    <div class="form-aside">
        <p class="eyebrow">A little more at ease</p>
        <h1>Make room<br>for <em>better care.</em></h1>
        <p>Create an account to keep your clinic details and care journey together.</p>
        <span class="aside-index">DCO / 01</span>
    </div>
    <div class="form-panel">
        <div class="form-heading"><p class="eyebrow">Patient portal</p><h2>Create account</h2><p>Verify your email to activate sign-in.</p></div>
        <nav class="auth-tabs" aria-label="Account access"><a href="<?= e(url('login.php')) ?>">Log in</a><a class="is-active" aria-current="page" href="<?= e(url('register.php')) ?>">Sign up</a></nav>
        <?php if ($errors): ?>
            <div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <form class="stacked-form" action="<?= e(url('register.php')) ?>" method="post">
            <?= csrf_field() ?>
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" type="text" value="<?= e($full_name) ?>" autocomplete="name" maxlength="100" required>
            <label for="email">Email address</label>
            <input id="email" name="email" type="email" value="<?= e($email) ?>" autocomplete="email" maxlength="150" required>
            <label for="phone">Phone <span class="optional-label">Optional</span></label>
            <input id="phone" name="phone" type="tel" value="<?= e($phone) ?>" autocomplete="tel" maxlength="20">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="new-password" required>
            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            <p class="field-hint">At least 8 characters, including a letter and a number.</p>
            <button class="button button-primary button-wide" type="submit">Create account <span aria-hidden="true">&#8594;</span></button>
        </form>
        <p class="form-switch">Already registered? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
    </div>
</section>
<?php require __DIR__ . '/../footer.php'; ?>