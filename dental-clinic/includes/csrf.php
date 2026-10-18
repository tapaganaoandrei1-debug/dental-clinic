<?php
// includes/csrf.php
// CSRF protection: every form that changes data sends a secret token.
// The server checks that the token matches the one stored in the session.
//
// In a form, call csrf_field() inside the markup.
// At the top of the page that handles the POST:   require_csrf();
// For fetch() requests: send the token in the "X-CSRF-Token" header,
//                       then call require_csrf_api() in the PHP file.

// Get the token for this session (create one if needed).
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Hidden input to put inside a <form>.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Check the token from the form field or the X-CSRF-Token header.
function csrf_is_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $real = $_SESSION['csrf_token'] ?? '';
    return is_string($sent) && $sent !== '' && $real !== '' && hash_equals($real, $sent);
}

// For normal pages: stop with an error if the token is wrong.
function require_csrf(): void
{
    if (!csrf_is_valid()) {
        http_response_code(419);
        exit('Security check failed (the form token is missing or expired). '
           . 'Please go back, refresh the page and try again.');
    }
}

// For api/ files: answer with JSON if the token is wrong.
function require_csrf_api(): void
{
    if (!csrf_is_valid()) {
        json_response(['ok' => false, 'error' => 'Security check failed. Please refresh the page.'], 419);
    }
}
