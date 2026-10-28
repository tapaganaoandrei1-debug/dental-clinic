<?php
require_once __DIR__ . '/../config/config.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $method === 'GET' ? get_value('action') : '';
$input = [];
if ($method === 'POST') {
    $raw_input = file_get_contents('php://input');
    $decoded_input = json_decode($raw_input ?: '', true);
    if (!is_array($decoded_input)) {
        json_response(['ok' => false, 'error' => 'Send a valid JSON request.'], 400);
    }
    $input = $decoded_input;
    $action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';
}

if ($method === 'GET' && $action === 'session') {
    if (!is_logged_in() || !refresh_session_user()) {
        json_response(['ok' => true, 'authenticated' => false, 'csrf_token' => csrf_token()]);
    }
    $_SESSION['last_activity'] = time();
    json_response([
        'ok' => true,
        'authenticated' => true,
        'csrf_token' => csrf_token(),
        'user' => [
            'id' => current_user_id(),
            'name' => current_user_name(),
            'email' => $_SESSION['email'],
            'role' => current_role(),
        ],
    ]);
}
if ($method === 'GET' && $action === 'token_status') {
    $type = get_value('type');
    $token = get_value('token');
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token) || !in_array($type, ['reset', 'verify'], true)) {
        json_response(['ok' => true, 'valid' => false]);
    }
    $table = $type === 'reset' ? 'password_reset_tokens' : 'email_verification_tokens';
    $stmt = $pdo->prepare(
        'SELECT t.id FROM ' . $table . ' t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.is_active = 1 LIMIT 1'
    );
    $stmt->execute([hash('sha256', $token)]);
    json_response(['ok' => true, 'valid' => (bool) $stmt->fetch()]);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'error' => 'This authentication action is not available.'], 405);
}
require_csrf_api();

if ($action === 'forgot_password') {
    $email = isset($input['email']) && is_string($input['email']) ? strtolower(trim($input['email'])) : '';
    if (is_valid_email($email)) {
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1 AND email_verified_at IS NOT NULL LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            try {
                $token = bin2hex(random_bytes(32));
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?');
                $stmt->execute([$user['id']]);
                $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
                $stmt->execute([$user['id'], hash('sha256', $token)]);
                $reset_url = absolute_url('app.html#reset-password?token=' . rawurlencode($token));
                $message = 'Use this link within 30 minutes to reset your password: ' . $reset_url;
                $stmt = $pdo->prepare(
                    "INSERT INTO notifications
                     (user_id, channel, type, recipient, subject, message, scheduled_for, status)
                     VALUES (?, 'email', 'password_reset', ?, 'Password reset request', ?, NOW(), 'queued')"
                );
                $stmt->execute([$user['id'], $user['email'], $message]);
                $notification_id = (int) $pdo->lastInsertId();
                $pdo->commit();
                $mail_from = getenv('DENTAL_MAIL_FROM') ?: 'no-reply@localhost';
                $headers = 'From: Dental Clinic <' . $mail_from . ">\r\nContent-Type: text/plain; charset=UTF-8";
                if (function_exists('mail') && @mail($user['email'], 'Password reset request', $message, $headers)) {
                    $stmt = $pdo->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$notification_id]);
                } else {
                    log_activity('password_reset_queued', 'Queued reset notice for account #' . $user['id'], (int) $user['id']);
                }
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('Password reset request failed: ' . $exception->getMessage());
                json_response(['ok' => false, 'error' => 'The reset request could not be completed. Please try again.'], 500);
            }
        }
    }
    json_response(['ok' => true, 'message' => 'If an active account matches that address, reset instructions are on the way.']);
}

if ($action === 'reset_password') {
    $token = isset($input['token']) && is_string($input['token']) ? $input['token'] : '';
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $confirmation = isset($input['password_confirmation']) && is_string($input['password_confirmation']) ? $input['password_confirmation'] : '';
    $password_message = password_error($password);
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        json_response(['ok' => false, 'error' => 'This reset link is invalid or has expired. Request a new one.'], 422);
    }
    if ($password_message !== '') {
        json_response(['ok' => false, 'error' => $password_message], 422);
    }
    if ($password !== $confirmation) {
        json_response(['ok' => false, 'error' => 'The passwords do not match.'], 422);
    }
    $token_hash = hash('sha256', $token);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT r.id, r.user_id FROM password_reset_tokens r
             JOIN users u ON u.id = r.user_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1 FOR UPDATE'
        );
        $stmt->execute([$token_hash]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new DomainException('This reset link is invalid or has expired. Request a new one.');
        }
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $record['user_id']]);
        $stmt = $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$record['id']]);
        $stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ? AND id <> ?');
        $stmt->execute([$record['user_id'], $record['id']]);
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE user_id = ? AND type = 'password_reset' AND status = 'queued'");
        $stmt->execute([$record['user_id']]);
        log_activity('password_reset_completed', 'Password reset completed', (int) $record['user_id']);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'Your password has been updated. Sign in with your new password.']);
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'error' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Password reset failed: ' . $exception->getMessage());
        json_response(['ok' => false, 'error' => 'The password could not be updated. Request a new reset link and try again.'], 500);
    }
}

if ($action === 'verify_email') {
    $token = isset($input['token']) && is_string($input['token']) ? $input['token'] : '';
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        json_response(['ok' => false, 'error' => 'This verification link is invalid or has expired. Create a new account or contact the clinic.'], 422);
    }
    $token_hash = hash('sha256', $token);
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'SELECT t.id, t.user_id FROM email_verification_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW() AND u.is_active = 1 FOR UPDATE'
        );
        $stmt->execute([$token_hash]);
        $record = $stmt->fetch();
        if (!$record) {
            throw new DomainException('This verification link is invalid or has expired. Create a new account or contact the clinic.');
        }
        $stmt = $pdo->prepare('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE id = ?');
        $stmt->execute([$record['user_id']]);
        $stmt = $pdo->prepare('UPDATE email_verification_tokens SET used_at = NOW() WHERE id = ?');
        $stmt->execute([$record['id']]);
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'simulated_sent', sent_at = NOW() WHERE user_id = ? AND type = 'email_verification' AND status = 'queued'");
        $stmt->execute([$record['user_id']]);
        log_activity('email_verified', 'Verified account email', (int) $record['user_id']);
        $pdo->commit();
        json_response(['ok' => true, 'message' => 'Your email is verified. You can sign in now.']);
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        json_response(['ok' => false, 'error' => $exception->getMessage()], 422);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Email verification failed: ' . $exception->getMessage());
        json_response(['ok' => false, 'error' => 'We could not verify this address. Please try again.'], 500);
    }
}

if ($action === 'login') {
    $email = isset($input['email']) && is_string($input['email']) ? strtolower(trim($input['email'])) : '';
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    if (!is_valid_email($email) || $password === '') {
        json_response(['ok' => false, 'error' => 'Enter a valid email address and password.'], 422);
    }

    $stmt = $pdo->prepare('SELECT id, role, full_name, email, password_hash, is_active, email_verified_at, failed_login_attempts, locked_until FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || (int) $user['is_active'] !== 1) {
        json_response(['ok' => false, 'error' => 'Email or password is incorrect.'], 401);
    }
    if (is_account_locked($user)) {
        json_response(['ok' => false, 'error' => 'Too many attempts. Try again in ' . lockout_minutes_left($user) . ' minute(s).'], 429);
    }
    if (!password_verify($password, $user['password_hash'])) {
        register_failed_login($user);
        json_response(['ok' => false, 'error' => 'Email or password is incorrect.'], 401);
    }
    if (empty($user['email_verified_at'])) {
        json_response(['ok' => false, 'error' => 'Verify your email address before signing in. Check your inbox or contact the clinic for the local verification link.'], 403);
    }
    clear_failed_logins((int) $user['id']);
    login_user($user);
    log_activity('login', 'Signed in');
    json_response([
        'ok' => true,
        'user' => [
            'id' => (int) $user['id'],
            'name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'csrf_token' => csrf_token(),
    ]);
}

if ($action === 'logout') {
    if (!is_logged_in()) {
        json_response(['ok' => true]);
    }
    require_role_api([ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN]);
    log_activity('logout', 'Signed out');
    logout_user();
    json_response(['ok' => true, 'csrf_token' => csrf_token()]);
}

if ($action === 'register') {
    $full_name = isset($input['full_name']) && is_string($input['full_name']) ? trim($input['full_name']) : '';
    $email = isset($input['email']) && is_string($input['email']) ? strtolower(trim($input['email'])) : '';
    $phone = isset($input['phone']) && is_string($input['phone']) ? trim($input['phone']) : '';
    $password = isset($input['password']) && is_string($input['password']) ? $input['password'] : '';
    $password_confirmation = isset($input['password_confirmation']) && is_string($input['password_confirmation']) ? $input['password_confirmation'] : '';
    $errors = [];

    if (strlen($full_name) < 2 || strlen($full_name) > 100) {
        $errors[] = 'Name must be between 2 and 100 characters.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($phone !== '' && !is_valid_phone($phone)) {
        $errors[] = 'Enter a valid phone number.';
    }
    $password_message = password_error($password);
    if ($password_message !== '') {
        $errors[] = $password_message;
    }
    if ($password !== $password_confirmation) {
        $errors[] = 'The passwords do not match.';
    }
    if ($errors) {
        json_response(['ok' => false, 'errors' => $errors], 422);
    }

    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'An account with that email already exists.'], 409);
    }

    $verification_url = '';
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO users (role, full_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([ROLE_PATIENT, $full_name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
        $user_id = (int) $pdo->lastInsertId();
        $stmt = $pdo->prepare('INSERT INTO patient_profiles (user_id) VALUES (?)');
        $stmt->execute([$user_id]);
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare('INSERT INTO email_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))');
        $stmt->execute([$user_id, hash('sha256', $token)]);
        $verification_url = absolute_url('app.html#verify-email?token=' . rawurlencode($token));
        $message = 'Verify your email address within 24 hours: ' . $verification_url;
        $stmt = $pdo->prepare(
            "INSERT INTO notifications
             (user_id, channel, type, recipient, subject, message, scheduled_for, status)
             VALUES (?, 'email', 'email_verification', ?, 'Verify your email address', ?, NOW(), 'queued')"
        );
        $stmt->execute([$user_id, $email, $message]);
        $notification_id = (int) $pdo->lastInsertId();
        log_activity('patient_registration', 'Created a patient account awaiting email verification', $user_id);
        $pdo->commit();

        $mail_from = getenv('DENTAL_MAIL_FROM') ?: 'no-reply@localhost';
        $headers = 'From: Dental Clinic <' . $mail_from . ">\r\nContent-Type: text/plain; charset=UTF-8";
        if (function_exists('mail') && @mail($email, 'Verify your dental clinic account', $message, $headers)) {
            $stmt = $pdo->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
            $stmt->execute([$notification_id]);
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception->getCode() === '23000') {
            json_response(['ok' => false, 'error' => 'An account with that email already exists.'], 409);
        }
        error_log('Patient registration failed: ' . $exception->getMessage());
        json_response(['ok' => false, 'error' => 'We could not create your account right now. Please try again.'], 500);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Patient registration failed: ' . $exception->getMessage());
        json_response(['ok' => false, 'error' => 'We could not create your account right now. Please try again.'], 500);
    }

    // Local testing only: on this computer (not production), return the link so no mail server is needed.
    $is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && getenv('APP_ENV') !== 'production';
    $response = ['ok' => true, 'message' => 'Your account was created. Verify your email before signing in.'];
    if ($is_local && $verification_url !== '') {
        $response['dev_verification_url'] = $verification_url;
    }
    json_response($response);
}

json_response(['ok' => false, 'error' => 'This authentication action is not available.'], 404);