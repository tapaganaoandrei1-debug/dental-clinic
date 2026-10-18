<?php
// includes/auth.php
// Login state, role checks and the failed-login cooldown.

// ---------- Who is logged in? ----------

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_role(): string
{
    return $_SESSION['role'] ?? '';
}

function current_user_name(): string
{
    return $_SESSION['full_name'] ?? '';
}

// The first page each role sees after logging in.
function role_home(string $role): string
{
    if ($role === ROLE_ADMIN) {
        return 'admin/dashboard.php';
    }
    if ($role === ROLE_STAFF) {
        return 'staff/dashboard.php';
    }
    return 'patient/dashboard.php';
}

// ---------- Login and logout ----------

// Call this right after the password was verified successfully.
function login_user(array $user): void
{
    global $pdo;

    session_regenerate_id(true);              // new session id stops session fixation
    $_SESSION['user_id']       = (int) $user['id'];
    $_SESSION['role']          = $user['role'];
    $_SESSION['full_name']     = $user['full_name'];
    $_SESSION['email']         = $user['email'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token']    = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare('UPDATE users SET last_login_at = ? WHERE id = ?');
    $stmt->execute([now_string(), $user['id']]);
}

// Clear the session data. The session stays open so we can show a flash message.
function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// ---------- Page guards ----------

// Reload the user from the database so deactivated accounts and role changes
// take effect right away. Returns false if the account cannot be used.
function refresh_session_user(): bool
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id, role, full_name, email, is_active FROM users WHERE id = ?');
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || (int) $user['is_active'] !== 1) {
        return false;
    }
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    return true;
}

// Put this at the top of every page that needs a login.
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }

    // Auto logout after a period of inactivity.
    $idle_limit = SESSION_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $idle_limit) {
        logout_user();
        set_flash('error', 'You were logged out because of inactivity. Please log in again.');
        redirect('login.php');
    }

    if (!refresh_session_user()) {
        logout_user();
        set_flash('error', 'Your account is not available. Please contact the clinic.');
        redirect('login.php');
    }

    $_SESSION['last_activity'] = time();
}

// Put this at the top of role-specific pages.
// Example: require_role(ROLE_ADMIN);  or  require_role([ROLE_STAFF, ROLE_ADMIN]);
function require_role($roles): void
{
    require_login();
    $roles = (array) $roles;

    if (!in_array(current_role(), $roles, true)) {
        set_flash('error', 'You are not allowed to open that page.');
        redirect(role_home(current_role()));
    }
}

// Same checks for api/ files: answer with JSON instead of redirecting.
function require_role_api($roles): void
{
    if (!is_logged_in()) {
        json_response(['ok' => false, 'error' => 'Please log in again.'], 401);
    }
    if (!refresh_session_user()) {
        json_response(['ok' => false, 'error' => 'Your account is not available.'], 403);
    }
    if (!in_array(current_role(), (array) $roles, true)) {
        json_response(['ok' => false, 'error' => 'You are not allowed to do that.'], 403);
    }
    $_SESSION['last_activity'] = time();
}

// Pages like login.php and register.php: skip them if already logged in.
function redirect_if_logged_in(): void
{
    if (is_logged_in()) {
        redirect(role_home(current_role()));
    }
}

// ---------- Failed-login cooldown ----------

// Is this account in the cooldown period? ($user is a row from the users table.)
function is_account_locked(array $user): bool
{
    return !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
}

// Minutes left in the cooldown (rounded up).
function lockout_minutes_left(array $user): int
{
    return max(1, (int) ceil((strtotime($user['locked_until']) - time()) / 60));
}

// Call after a wrong password. After MAX_LOGIN_ATTEMPTS mistakes the account
// is locked for LOCKOUT_MINUTES minutes.
function register_failed_login(array $user): void
{
    global $pdo;

    $attempts     = (int) $user['failed_login_attempts'] + 1;
    $locked_until = null;

    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        $locked_until = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
        $attempts     = 0;
    }

    $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?');
    $stmt->execute([$attempts, $locked_until, $user['id']]);
}

// Call after a successful login.
function clear_failed_logins(int $user_id): void
{
    global $pdo;

    $stmt = $pdo->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?');
    $stmt->execute([$user_id]);
}
