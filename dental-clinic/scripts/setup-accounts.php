<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

$admin_email = getenv('DENTAL_ADMIN_EMAIL');
$admin_password = getenv('DENTAL_ADMIN_PASSWORD');
if ($admin_email !== false || $admin_password !== false) {
    if ($admin_email === false || !is_valid_email($admin_email)
        || $admin_password === false || strlen($admin_password) < PASSWORD_MIN_LENGTH
    ) {
        fwrite(STDERR, "Set both DENTAL_ADMIN_EMAIL and a password of at least " . PASSWORD_MIN_LENGTH . " characters.\n");
        exit(2);
    }

    $find_admin = $pdo->prepare('SELECT id, role, password_hash FROM users WHERE email = ? LIMIT 1');
    $find_admin->execute([$admin_email]);
    $admin = $find_admin->fetch();
    if ($admin && $admin['role'] !== ROLE_ADMIN) {
        fwrite(STDERR, "That email already belongs to a non-admin account.\n");
        exit(2);
    }

    if (!$admin) {
        $create_admin = $pdo->prepare(
            'INSERT INTO users (role, full_name, email, password_hash, email_verified_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $create_admin->execute([
            ROLE_ADMIN,
            'Clinic Administrator',
            $admin_email,
            password_hash($admin_password, PASSWORD_DEFAULT),
        ]);
        $create_profile = $pdo->prepare('INSERT INTO staff_profiles (user_id, position) VALUES (?, ?)');
        $create_profile->execute([(int) $pdo->lastInsertId(), 'Clinic Manager']);
        echo "Created the clinic administrator account.\n";
        exit;
    }

    if ($admin['password_hash'] === 'RUN_SETUP_SCRIPT') {
        $initialize_admin = $pdo->prepare(
            "UPDATE users SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW())
             WHERE id = ? AND password_hash = 'RUN_SETUP_SCRIPT'"
        );
        $initialize_admin->execute([password_hash($admin_password, PASSWORD_DEFAULT), $admin['id']]);
        echo "Initialized the clinic administrator account.\n";
        exit;
    }

    echo "The clinic administrator already has a password; it was left unchanged.\n";
    exit;
}

if (getenv('APP_ENV') === 'production') {
    fwrite(STDERR, "Production requires DENTAL_ADMIN_EMAIL and DENTAL_ADMIN_PASSWORD.\n");
    exit(2);
}

$seed_accounts = [
    'admin@dentalclinic.test' => 'Admin@123',
    'staff@dentalclinic.test' => 'Staff@123',
    'patient@dentalclinic.test' => 'Patient@123',
    'ana.reyes@example.com' => 'Patient@123',
    'joel.santos@example.com' => 'Patient@123',
    'liza.moreno@example.com' => 'Patient@123',
    'ben.torres@example.com' => 'Patient@123',
    'carla.dizon@example.com' => 'Patient@123',
];

$update_account = $pdo->prepare(
    "UPDATE users
     SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW())
     WHERE email = ? AND password_hash = 'RUN_SETUP_SCRIPT'"
);
$updated = 0;

foreach ($seed_accounts as $email => $password) {
    $update_account->execute([password_hash($password, PASSWORD_DEFAULT), $email]);
    $updated += $update_account->rowCount();
}

echo $updated
    ? "Initialized {$updated} development account(s).\n"
    : "No uninitialized development accounts found; existing passwords were left unchanged.\n";
