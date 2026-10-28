<?php
require_once __DIR__ . '/config/config.php';
redirect_if_logged_in();

$page_title = 'Create account';
$active_nav = 'register';
$full_name = '';
$email = '';
$phone = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $full_name = post_value('full_name');
    $email = strtolower(post_value('email'));
    $phone = post_value('phone');
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $password_confirmation = isset($_POST['password_confirmation']) && is_string($_POST['password_confirmation']) ? $_POST['password_confirmation'] : '';

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

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        } else {
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
                $verification_url = absolute_url('verify-email.php?token=' . rawurlencode($token));
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
                $headers = 'From: Dental Clinic <' . $mail_from . ">
Content-Type: text/plain; charset=UTF-8";
                if (function_exists('mail') && @mail($email, 'Verify your dental clinic account', $message, $headers)) {
                    $stmt = $pdo->prepare("UPDATE notifications SET status = 'sent', sent_at = NOW() WHERE id = ?");
                    $stmt->execute([$notification_id]);
                }
                set_flash('success', 'Your account was created. Verify your email before signing in.');
                redirect('login.php');
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Patient registration failed: ' . $exception->getMessage());
                $errors[] = 'We could not create your account right now. Please try again.';
            }
        }
    }
}

require __DIR__ . '/includes/views/register.php';
