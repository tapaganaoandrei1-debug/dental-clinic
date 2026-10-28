<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointment receipt #<?= (int) $appointment['id'] ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="receipt-page">
<main class="receipt-sheet">
    <div class="receipt-top"><a class="brand" href="<?= e(url('index.php')) ?>"><span class="brand-mark" aria-hidden="true">dc</span><span class="brand-name">Dental<span>Clinic</span></span></a><span class="receipt-label">Appointment record</span></div>
    <p class="eyebrow">Booking reference #<?= (int) $appointment['id'] ?></p>
    <h1><?= e($clinic_name) ?></h1>
    <p class="receipt-lead">Appointment details for <?= e($appointment['patient_name']) ?>.</p>
    <div class="receipt-status"><?= status_badge($appointment['status']) ?><span><?= e(format_date($appointment['appointment_date'])) ?></span></div>
    <dl class="receipt-details">
        <div><dt>Service</dt><dd><?= e($appointment['service_name']) ?></dd></div>
        <div><dt>Dentist</dt><dd><?= e($appointment['dentist_name']) ?></dd></div>
        <div><dt>Date and time</dt><dd><?= e(format_date($appointment['appointment_date'])) ?>, <?= e(format_time($appointment['start_time'])) ?>–<?= e(format_time($appointment['end_time'])) ?></dd></div>
        <div><dt>Patient</dt><dd><?= e($appointment['patient_name']) ?></dd></div>
        <div><dt>Email</dt><dd><?= e($appointment['email']) ?></dd></div>
        <div><dt>Service fee</dt><dd><?= e(format_money($appointment['price'])) ?></dd></div>
        <div><dt>Requested</dt><dd><?= e(format_datetime($appointment['created_at'])) ?></dd></div>
        <?php if ($appointment['cancellation_reason']): ?><div><dt>Cancellation note</dt><dd><?= e($appointment['cancellation_reason']) ?></dd></div><?php endif; ?>
    </dl>
    <p class="receipt-note">This is an appointment record, not proof of payment. Pending appointments require clinic confirmation.</p>
    <div class="receipt-actions"><button class="button button-primary" type="button" onclick="window.print()">Print / Save as PDF</button><a class="text-link" href="<?= e(url('patient/appointments.php')) ?>">Back to appointments</a></div>
</main>
</body>
</html>
