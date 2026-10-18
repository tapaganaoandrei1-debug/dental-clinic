<?php
require_once __DIR__ . '/config/config.php';
redirect_if_logged_in();

$page_title = 'Sign in';
$active_nav = 'login';
$email = '';
$error = '';
$redirect_target = get_value('redirect');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = post_value('email');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $redirect_target = post_value('redirect');

    if (!is_valid_email($email) || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, role, full_name, email, password_hash, is_active, email_verified_at, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || (int) $user['is_active'] !== 1) {
            $error = 'Email or password is incorrect.';
        } elseif (empty($user['email_verified_at'])) {
            $error = 'Verify your email address before signing in. Check your inbox or contact the clinic for the local verification link.';
        } elseif (is_account_locked($user)) {
            $error = 'Too many attempts. Try again in ' . lockout_minutes_left($user) . ' minute(s).';
        } elseif (!password_verify($password, $user['password_hash'])) {
            register_failed_login($user);
            $error = 'Email or password is incorrect.';
        } else {
            clear_failed_logins((int) $user['id']);
            login_user($user);
            log_activity('login', 'Signed in');
            $destination = role_home($user['role']);
            if ($redirect_target !== '') {
                $destination = safe_redirect_path($redirect_target, $destination);
            }
            redirect($destination);
        }
    }
}

require __DIR__ . '/includes/header.php';
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
<?php require __DIR__ . '/includes/footer.php'; ?>