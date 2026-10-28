<?php
require_once __DIR__ . '/../config/config.php';
require_role(ROLE_ADMIN);

$page_title = 'User management';
$active_nav = 'users';
$valid_roles = [ROLE_PATIENT, ROLE_STAFF, ROLE_ADMIN];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = post_value('action');
    try {
        if ($action === 'create') {
            $name = post_value('full_name');
            $email = strtolower(post_value('email'));
            $phone = post_value('phone');
            $role = post_value('role');
            $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
            if (strlen($name) < 2 || strlen($name) > 100 || !is_valid_email($email) || !in_array($role, $valid_roles, true)) {
                throw new DomainException('Enter a valid name, email and account role.');
            }
            if ($phone !== '' && !is_valid_phone($phone)) {
                throw new DomainException('Enter a valid phone number.');
            }
            $password_message = password_error($password);
            if ($password_message !== '') {
                throw new DomainException($password_message);
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (role, full_name, email, phone, password_hash, email_verified_at) VALUES (?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$role, $name, $email, $phone !== '' ? $phone : null, password_hash($password, PASSWORD_DEFAULT)]);
            $user_id = (int) $pdo->lastInsertId();
            if ($role === ROLE_PATIENT) {
                $stmt = $pdo->prepare('INSERT INTO patient_profiles (user_id) VALUES (?)');
            } else {
                $stmt = $pdo->prepare('INSERT INTO staff_profiles (user_id) VALUES (?)');
            }
            $stmt->execute([$user_id]);
            log_activity('user_created', 'Created ' . $role . ' account #' . $user_id);
            $pdo->commit();
            set_flash('success', 'Account created. Share the temporary password securely.');
        } elseif ($action === 'update') {
            $user_id = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $role = post_value('role');
            $is_active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
            if (!$user_id || !in_array($role, $valid_roles, true)) {
                throw new DomainException('Choose a valid account and role.');
            }
            if ((int) $user_id === current_user_id() && ($role !== ROLE_ADMIN || $is_active !== 1)) {
                throw new DomainException('You cannot remove your own active administrator access.');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE users SET role = ?, is_active = ? WHERE id = ?');
            $stmt->execute([$role, $is_active, $user_id]);
            if ($stmt->rowCount() === 0) {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                if (!$stmt->fetch()) {
                    throw new DomainException('That account no longer exists.');
                }
            }
            if ($role === ROLE_PATIENT) {
                $stmt = $pdo->prepare('INSERT IGNORE INTO patient_profiles (user_id) VALUES (?)');
            } else {
                $stmt = $pdo->prepare('INSERT IGNORE INTO staff_profiles (user_id) VALUES (?)');
            }
            $stmt->execute([$user_id]);
            log_activity('user_updated', 'Updated role/access for account #' . $user_id);
            $pdo->commit();
            set_flash('success', 'Account access updated.');
        } else {
            throw new DomainException('That account action is not available.');
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        set_flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('User management failed: ' . $exception->getMessage());
        set_flash('error', $exception->getCode() === '23000' ? 'That email address is already in use.' : 'The account could not be saved.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('User management failed: ' . $exception->getMessage());
        set_flash('error', 'The account could not be saved.');
    }
    redirect('admin/users.php');
}

$users = $pdo->query(
    'SELECT id, role, full_name, email, phone, is_active, email_verified_at, last_login_at, created_at
     FROM users ORDER BY created_at DESC, id DESC LIMIT 200'
)->fetchAll();

require __DIR__ . '/../includes/views/admin/users.php';
