<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
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
<?php require __DIR__ . '/../../footer.php'; ?>