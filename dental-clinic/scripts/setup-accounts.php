<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../config/config.php';

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
