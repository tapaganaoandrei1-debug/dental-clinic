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

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Clinic administration</p><h1>People and <em>access.</em></h1><p>Create accounts, assign roles and deactivate access when needed.</p></div>
    <a class="text-link" href="<?= e(url('admin/dashboard.php')) ?>">Back to overview</a>
</section>
<section class="admin-create-section">
    <div class="content-heading"><div><p class="eyebrow">New account</p><h2>Add a team member or patient</h2></div></div>
    <form class="admin-user-form" method="post" action="<?= e(url('admin/users.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <label>Full name<input name="full_name" type="text" maxlength="100" required></label>
        <label>Email<input name="email" type="email" maxlength="150" required></label>
        <label>Phone <span class="optional-label">Optional</span><input name="phone" type="tel" maxlength="20"></label>
        <label>Role<select name="role" required><option value="patient">Patient</option><option value="staff">Staff</option><option value="admin">Administrator</option></select></label>
        <label>Temporary password<input name="password" type="password" minlength="8" autocomplete="new-password" required></label>
        <button class="button button-primary" type="submit">Create account</button>
        <p class="field-hint">At least 8 characters, with a letter and a number. Ask the user to change it after first sign-in.</p>
    </form>
</section>
<section class="content-section">
    <div class="content-heading"><div><p class="eyebrow">Accounts</p><h2><?= count($users) ?> users</h2></div><span class="subtle-count">Most recent 200</span></div>
    <div class="table-wrap">
        <table class="data-table admin-users-table">
            <thead><tr><th>Person</th><th>Role</th><th>Last sign-in</th><th>Access</th><th>Save</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?= e($user['full_name']) ?></strong><span class="table-subline"><?= e($user['email']) ?><?= $user['phone'] ? ' · ' . e($user['phone']) : '' ?></span><span class="table-subline"><?= $user['email_verified_at'] ? 'Email verified' : 'Email not verified' ?></span></td>
                    <td colspan="4">
                        <form class="user-access-form" method="post" action="<?= e(url('admin/users.php')) ?>">
                            <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="user_id" value="<?= (int) $user['id'] ?>">
                            <select name="role" aria-label="Role for <?= e($user['full_name']) ?>"<?= (int) $user['id'] === current_user_id() ? ' disabled' : '' ?>>
                                <option value="patient"<?= $user['role'] === 'patient' ? ' selected' : '' ?>>Patient</option><option value="staff"<?= $user['role'] === 'staff' ? ' selected' : '' ?>>Staff</option><option value="admin"<?= $user['role'] === 'admin' ? ' selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ((int) $user['id'] === current_user_id()): ?><input type="hidden" name="role" value="admin"><?php endif; ?>
                            <select name="is_active" aria-label="Access for <?= e($user['full_name']) ?>"<?= (int) $user['id'] === current_user_id() ? ' disabled' : '' ?>><option value="1"<?= (int) $user['is_active'] === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) $user['is_active'] !== 1 ? ' selected' : '' ?>>Inactive</option></select>
                            <?php if ((int) $user['id'] === current_user_id()): ?><input type="hidden" name="is_active" value="1"><?php endif; ?>
                            <span class="table-subline"><?= $user['last_login_at'] ? e(format_datetime($user['last_login_at'])) : 'Never signed in' ?></span>
                            <button class="small-link" type="submit"<?= (int) $user['id'] === current_user_id() ? ' disabled' : '' ?>>Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>