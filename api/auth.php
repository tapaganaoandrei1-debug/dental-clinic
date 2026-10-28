<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
$action = $_GET['action'] ?? '';
$db = Database::getConnection();

if ($action === 'check-session') {
    if (!empty($_SESSION['user_id'])) {
        sendJson(['status' => 'success', 'user' => ['id' => $_SESSION['user_id'], 'name' => $_SESSION['name'], 'role' => $_SESSION['role']]]);
    }
    sendJson(['status' => 'guest', 'user' => null]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    if ($action === 'register') {
        $first = trim($input['first_name'] ?? '');
        $last  = trim($input['last_name'] ?? '');
        $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $phone = trim($input['phone'] ?? '');
        $pass  = $input['password'] ?? '';
        if (!$first || !$last || !$email || strlen($pass) < 8) sendJson(['status' => 'error', 'message' => 'Invalid data'], 422);

        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) sendJson(['status' => 'error', 'message' => 'Email exists'], 409);

        $stmt = $db->prepare("INSERT INTO users (role, first_name, last_name, email, phone, password_hash) VALUES ('client', ?, ?, ?, ?, ?)");
        $stmt->execute([$first, $last, $email, $phone, password_hash($pass, PASSWORD_BCRYPT)]);
        sendJson(['status' => 'success', 'message' => 'Registered successfully']);
    }
    if ($action === 'login') {
        $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $pass = $input['password'] ?? '';
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $u = $stmt->fetch();
        if ($u && password_verify($pass, $u['password_hash'])) {
            $_SESSION['user_id'] = $u['id'];
            $_SESSION['role'] = $u['role'];
            $_SESSION['name'] = $u['first_name'] . ' ' . $u['last_name'];
            sendJson(['status' => 'success', 'user' => ['role' => $u['role'], 'name' => $_SESSION['name']]]);
        }
        sendJson(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }
}
if ($action === 'logout') {
    session_destroy();
    sendJson(['status' => 'success']);
}
