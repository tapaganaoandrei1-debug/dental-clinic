<?php
$page_title = $page_title ?? APP_NAME;
$active_nav = $active_nav ?? '';
$dashboard_path = is_logged_in() ? role_home(current_role()) : '';
$operations_shell = is_logged_in() && in_array(current_role(), [ROLE_STAFF, ROLE_ADMIN], true);
$name_parts = preg_split('/\s+/', trim(current_user_name()), -1, PREG_SPLIT_NO_EMPTY);
$avatar_text = '';
foreach (array_slice($name_parts ?: [], 0, 2) as $name_part) {
    $avatar_text .= strtoupper(substr($name_part, 0, 1));
}
$avatar_text = $avatar_text ?: 'SC';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#1769aa">
    <title><?= e($page_title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="<?= $operations_shell ? 'operations-body' : 'patient-body' ?>">
<?php if ($operations_shell): ?>
    <div class="operations-shell">
        <aside class="operations-sidebar">
            <a class="operations-brand" href="<?= e(url($dashboard_path)) ?>"><span class="operations-brand-mark" aria-hidden="true">S</span><span>SmileCare<small><?= current_role() === ROLE_ADMIN ? 'Admin' : 'Clinic team' ?></small></span></a>
            <nav class="operations-nav" aria-label="Clinic administration">
                <a href="<?= e(url($dashboard_path)) ?>"<?= nav_current_attributes('dashboard', $active_nav) ?>>Dashboard</a>
                <a href="<?= e(url('staff/queue.php')) ?>"<?= nav_current_attributes('queue', $active_nav) ?>>Queue</a>
                <a href="<?= e(url('staff/appointments.php')) ?>"<?= nav_current_attributes('staff-appointments', $active_nav) ?>>Appointments</a>
                <a href="<?= e(url('staff/notifications.php')) ?>"<?= nav_current_attributes('notifications', $active_nav) ?>>Notices</a>
                <?php if (current_role() === ROLE_ADMIN): ?>
                    <a href="<?= e(url('admin/schedules.php')) ?>"<?= nav_current_attributes('schedules', $active_nav) ?>>Schedules</a>
                    <a href="<?= e(url('admin/reports.php')) ?>"<?= nav_current_attributes('reports', $active_nav) ?>>Reports</a>
                    <a href="<?= e(url('admin/users.php')) ?>"<?= nav_current_attributes('users', $active_nav) ?>>Patients &amp; users</a>
                    <a href="<?= e(url('admin/activity.php')) ?>"<?= nav_current_attributes('activity', $active_nav) ?>>Activity log</a>
                    <a href="<?= e(url('admin/backup.php')) ?>"<?= nav_current_attributes('backup', $active_nav) ?>>Backup</a>
                <?php endif; ?>
            </nav>
            <div class="operations-user"><span class="operations-avatar"><?= e($avatar_text) ?></span><span><?= e(current_user_name()) ?><small><?= current_role() === ROLE_ADMIN ? 'Administrator' : 'Clinic staff' ?></small></span>
                <form action="<?= e(url('logout.php')) ?>" method="post"><?= csrf_field() ?><button type="submit" aria-label="Log out" title="Log out">Log out</button></form>
            </div>
        </aside>
        <div class="operations-main">
            <header class="operations-topbar">
                <form class="operations-search" method="get" action="<?= e(url('staff/appointments.php')) ?>"><label class="sr-only" for="operations-search">Search patient or appointment</label><input id="operations-search" name="q" type="search" value="<?= e(get_value('q')) ?>" placeholder="Search patient, token or booking"><button type="submit" aria-label="Search">Search</button></form>
                <div class="operations-top-actions"><span class="today-chip"><?= e(format_date(today_string())) ?></span><a class="button button-primary" href="<?= e(url('staff/walk-in.php')) ?>">New walk-in booking</a></div>
            </header>
            <main class="page-main operations-page-main"><div class="container"><?php show_flash(); ?>
<?php else: ?>
    <header class="patient-header">
        <div class="patient-header-inner">
            <a class="patient-brand" href="<?= e(url('index.php')) ?>" aria-label="SmileCare Dental home"><span class="patient-brand-mark" aria-hidden="true">S</span><span>SmileCare Dental</span></a>
            <nav class="patient-nav" aria-label="Patient navigation">
                <?php if (current_role() === ROLE_PATIENT): ?>
                    <a href="<?= e(url('patient/book.php')) ?>"<?= nav_current_attributes('book', $active_nav) ?>>Book</a>
                    <a href="<?= e(url('patient/queue.php')) ?>"<?= nav_current_attributes('queue', $active_nav) ?>>My Queue</a>
                    <a href="<?= e(url('patient/appointments.php')) ?>"<?= nav_current_attributes('appointments', $active_nav) ?>>Appointments</a>
                    <a href="<?= e(url('patient/alerts.php')) ?>"<?= nav_current_attributes('alerts', $active_nav) ?>>Alerts</a>
                    <form class="patient-avatar-form" action="<?= e(url('logout.php')) ?>" method="post"><?= csrf_field() ?><button class="patient-avatar" type="submit" aria-label="Log out" title="Log out"><?= e($avatar_text) ?></button></form>
                <?php else: ?>
                    <a href="<?= e(url('index.php')) ?>"<?= nav_current_attributes('home', $active_nav) ?>>Home</a>
                    <a href="<?= e(url('index.php#services')) ?>">Services</a>
                    <a href="<?= e(url('login.php')) ?>"<?= nav_current_attributes('login', $active_nav) ?>>Log in</a>
                    <a class="patient-signup" href="<?= e(url('register.php')) ?>"<?= nav_current_attributes('register', $active_nav) ?>>Sign up</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="page-main"><div class="container"><?php show_flash(); ?>
<?php endif; ?>