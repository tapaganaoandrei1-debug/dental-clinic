<?php
// includes/functions.php
// Small helper functions used by many pages.

// ---------- Output and URLs ----------

// Escape text before printing it in HTML (protects against XSS).
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Build a full URL path inside this project. Example: url('login.php')
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function absolute_url(string $path = ''): string
{
    $origin = rtrim((string) getenv('APP_ORIGIN'), '/');
    if (!preg_match('~\Ahttps?://[A-Za-z0-9.-]+(?::[0-9]{1,5})?\z~i', $origin)) {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        if (!preg_match('/\A(?:localhost|127\.0\.0\.1)(?::[0-9]{1,5})?\z/i', $host)) {
            $host = 'localhost';
        }
        $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
        $origin = $scheme . '://' . $host;
    }
    return $origin . url($path);
}

function nav_current_attributes(string $item, string $active_item): string
{
    return $item === $active_item ? ' class="is-active" aria-current="page"' : '';
}

// Redirect to a page inside this project and stop the script.
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

// Safe redirect: only allow paths that belong to this project.
// Use this when the target comes from the user (for example ?redirect=...).
// Returns a path relative to the project folder, ready for redirect().
function safe_redirect_path($target, string $default = 'index.php'): string
{
    if (!is_string($target) || $target === '') {
        return $default;
    }
    $prefix = BASE_URL . '/';
    if (strpos($target, $prefix) !== 0) {
        return $default;                      // must start with our own folder
    }
    if (strpbrk($target, "\\\r\n") !== false) {
        return $default;                      // no backslashes or line breaks
    }
    if (strpos($target, '//') !== false) {
        return $default;                      // no "//" or "://" tricks
    }
    return substr($target, strlen($prefix));
}

// Send a JSON response (used by the api/ files) and stop the script.
function json_response(array $data, int $status_code = 200): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// ---------- Flash messages (one-time alerts) ----------

function set_flash(string $type, string $message): void
{
    // $type is "success", "error" or "info"
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Print the flash message (if any) and remove it.
function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $type    = $_SESSION['flash']['type'];
        $message = $_SESSION['flash']['message'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($type) . '">' . e($message) . '</div>';
    }
}

// ---------- Reading and validating input ----------

// Get a trimmed text value from $_POST (empty string if missing).
function post_value(string $key): string
{
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
}

// Get a trimmed text value from $_GET (empty string if missing).
function get_value(string $key): string
{
    return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : '';
}

function is_valid_email(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 150;
}

// Phone: digits, spaces, +, -, ( ) and 7-20 characters long.
function is_valid_phone(string $phone): bool
{
    return preg_match('/^[0-9+\-\s()]{7,20}$/', $phone) === 1;
}

// Date in the format YYYY-MM-DD and a real calendar date.
function is_valid_date(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d !== false && $d->format('Y-m-d') === $date;
}

// Time in the format HH:MM (24 hour).
function is_valid_time(string $time): bool
{
    $t = DateTime::createFromFormat('H:i', $time);
    return $t !== false && $t->format('H:i') === $time;
}

// Returns an error message if the password is weak, or '' if it is OK.
function password_error(string $password): string
{
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return '';
}

// ---------- Dates, times and money ----------

function today_string(): string
{
    return date('Y-m-d');
}

function now_string(): string
{
    return date('Y-m-d H:i:s');
}

// 2026-10-15 -> "Thu, Oct 15, 2026"
function format_date($date): string
{
    return $date ? date('D, M j, Y', strtotime($date)) : '';
}

// 10:00:00 -> "10:00 AM"
function format_time($time): string
{
    return $time ? date('g:i A', strtotime($time)) : '';
}

// 2026-10-15 09:30:00 -> "Oct 15, 2026 9:30 AM"
function format_datetime($datetime): string
{
    return $datetime ? date('M j, Y g:i A', strtotime($datetime)) : '';
}

function format_money($amount): string
{
    return get_setting('currency_symbol', '₱') . number_format((float) $amount, 2);
}

// Queue number 14 -> "Q-014"
function format_queue_number($number): string
{
    return 'Q-' . str_pad((string) (int) $number, 3, '0', STR_PAD_LEFT);
}

// "09:30:00" -> 570 (minutes after midnight)
function time_to_minutes(string $time): int
{
    $parts = explode(':', $time);
    return ((int) $parts[0]) * 60 + (int) ($parts[1] ?? 0);
}

// 570 -> "09:30:00"
function minutes_to_time(int $minutes): string
{
    return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
}

// ---------- Status labels and badges ----------

function status_label(string $status): string
{
    $labels = [
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'no_show'   => 'No-show',
        'waiting'   => 'Waiting',
        'called'    => 'Called',
        'serving'   => 'In service',
        'skipped'   => 'Skipped',
    ];
    return $labels[$status] ?? ucfirst($status);
}

// Colored badge. The CSS class is "badge badge-<status>" (no_show -> badge-no-show).
function status_badge(string $status): string
{
    $class = 'badge badge-' . str_replace('_', '-', $status);
    return '<span class="' . e($class) . '">' . e(status_label($status)) . '</span>';
}

// ---------- Settings and logging ----------

// Read one value from the clinic_settings table (loaded once per request).
function get_setting(string $key, $default = null)
{
    global $pdo;
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        foreach ($pdo->query('SELECT setting_key, setting_value FROM clinic_settings') as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $settings[$key] ?? $default;
}

// Save an important action in activity_logs. Never breaks the page if it fails.
function log_activity(string $action, string $details = '', ?int $user_id = null): void
{
    global $pdo;
    try {
        if ($user_id === null && !empty($_SESSION['user_id'])) {
            $user_id = (int) $_SESSION['user_id'];
        }
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$user_id, $action, substr($details, 0, 255), $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (PDOException $e) {
        error_log('log_activity failed: ' . $e->getMessage());
    }
}
