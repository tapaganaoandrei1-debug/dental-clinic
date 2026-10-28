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

require __DIR__ . '/includes/views/login.php';
