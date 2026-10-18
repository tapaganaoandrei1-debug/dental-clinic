<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/queue.php';
require_role([ROLE_STAFF, ROLE_ADMIN]);

$page_title = 'Queue control';
$active_nav = 'queue';
$category = get_value('category');
$categories = $pdo->query('SELECT DISTINCT category FROM services WHERE is_active = 1 AND category IS NOT NULL ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);
if ($category !== '' && !in_array($category, $categories, true)) $category = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $action = post_value('action');
        if ($action === 'check_in') {
            $appointment_id = filter_var($_POST['appointment_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$appointment_id) {
                throw new DomainException('Choose a valid appointment to check in.');
            }
            $number = check_in_appointment((int) $appointment_id, current_user_id());
            set_flash('success', 'Patient checked in as ' . format_queue_number($number) . '.');
        } elseif ($action === 'transition') {
            $queue_entry_id = filter_var($_POST['queue_entry_id'] ?? null, FILTER_VALIDATE_INT);
            $next_status = post_value('status');
            if (!$queue_entry_id) {
                throw new DomainException('Choose a valid queue entry.');
            }
            transition_queue_entry((int) $queue_entry_id, $next_status, current_user_id());
            set_flash('success', 'Queue status updated.');
        } else {
            throw new DomainException('That queue action is not available.');
        }
    } catch (DomainException $exception) {
        set_flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        error_log('Queue action failed: ' . $exception->getMessage());
        set_flash('error', 'The queue could not be updated. Please try again.');
    }
    redirect('staff/queue.php' . ($category !== '' ? '?category=' . rawurlencode($category) : ''));
}

$checkin_query =
    "SELECT a.id, a.start_time, u.full_name AS patient_name, s.name AS service_name,
            d.full_name AS dentist_name
     FROM appointments a
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
     WHERE a.appointment_date = CURDATE() AND a.status = 'confirmed'
       AND a.arrived_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM queue_entries q WHERE q.appointment_id = a.id)";
$checkin_params = [];
if ($category !== '') {
    $checkin_query .= ' AND s.category = ?';
    $checkin_params[] = $category;
}
$checkin_query .= ' ORDER BY a.start_time';
$stmt = $pdo->prepare($checkin_query);
$stmt->execute($checkin_params);
$check_ins = $stmt->fetchAll();
$queue_query =
    "SELECT q.id, q.queue_number, q.status, q.estimated_wait_minutes,
            u.full_name AS patient_name, s.name AS service_name, s.category, d.full_name AS dentist_name
     FROM queue_entries q
     JOIN appointments a ON a.id = q.appointment_id
     JOIN users u ON u.id = a.patient_id
     JOIN services s ON s.id = a.service_id
     JOIN dentists d ON d.id = a.dentist_id
    WHERE q.queue_date = CURDATE() AND q.status IN ('waiting', 'called', 'serving')";
$queue_params = [];
if ($category !== '') {
    $queue_query .= ' AND s.category = ?';
    $queue_params[] = $category;
}
$queue_query .= " ORDER BY FIELD(q.status, 'serving', 'called', 'waiting'), q.queue_number";
$stmt = $pdo->prepare($queue_query);
$stmt->execute($queue_params);
$queue = $stmt->fetchAll();
$serving_entry = null;
$called_entry = null;
$next_waiting_entry = null;
foreach ($queue as $entry) {
    if ($serving_entry === null && $entry['status'] === 'serving') $serving_entry = $entry;
    if ($called_entry === null && $entry['status'] === 'called') $called_entry = $entry;
    if ($next_waiting_entry === null && $entry['status'] === 'waiting') $next_waiting_entry = $entry;
}
$csrf_token = csrf_token();

require __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-heading">
    <div><p class="eyebrow">Front desk controls</p><h1>Queue management</h1></div>
    <span class="dashboard-date" id="queue-refresh-state">All departments · Live</span>
</section>
<nav class="queue-category-filter" aria-label="Filter queue by service category">
    <a href="<?= e(url('staff/queue.php')) ?>"<?= $category === '' ? ' class="is-active" aria-current="page"' : '' ?>>All</a>
    <?php foreach ($categories as $queue_category): ?><a href="<?= e(url('staff/queue.php?category=' . rawurlencode($queue_category))) ?>"<?= $category === $queue_category ? ' class="is-active" aria-current="page"' : '' ?>><?= e($queue_category) ?></a><?php endforeach; ?>
</nav>
<?php $queue_action_url = url('staff/queue.php' . ($category !== '' ? '?category=' . rawurlencode($category) : '')); ?>
<section class="queue-now-panel">
    <?php if ($serving_entry): ?>
        <div><p class="eyebrow">Now serving · <?= e($serving_entry['dentist_name']) ?></p><h2><?= e(format_queue_number($serving_entry['queue_number'])) ?> · <?= e($serving_entry['patient_name']) ?></h2><p><?= e($serving_entry['service_name']) ?></p></div>
        <div class="queue-now-actions">
            <?php if ($serving_entry['status'] === 'called'): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $serving_entry['id'] ?>"><input type="hidden" name="status" value="serving"><button class="button button-outline" type="submit">Start service</button></form><?php endif; ?>
            <?php if ($serving_entry['status'] === 'serving'): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $serving_entry['id'] ?>"><input type="hidden" name="status" value="completed"><button class="button button-outline" type="submit">Mark done</button></form><?php endif; ?>
            <?php if ($called_entry): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $called_entry['id'] ?>"><input type="hidden" name="status" value="serving"><button class="button button-outline" type="submit">Start · <?= e(format_queue_number($called_entry['queue_number'])) ?></button></form><?php elseif ($next_waiting_entry): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $next_waiting_entry['id'] ?>"><input type="hidden" name="status" value="called"><button class="button button-primary" type="submit">Call next · <?= e(format_queue_number($next_waiting_entry['queue_number'])) ?></button></form><?php endif; ?>
        </div>
    <?php else: ?>
        <div><p class="eyebrow">Queue status</p><h2>No patient is in service.</h2><p>Call the next checked-in patient when the care team is ready.</p></div>
        <?php if ($called_entry): ?><div class="queue-called-next"><span>Called next · <?= e(format_queue_number($called_entry['queue_number'])) ?> · <?= e($called_entry['patient_name']) ?></span><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $called_entry['id'] ?>"><input type="hidden" name="status" value="serving"><button class="button button-primary" type="submit">Start service</button></form></div><?php elseif ($next_waiting_entry): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $next_waiting_entry['id'] ?>"><input type="hidden" name="status" value="called"><button class="button button-primary" type="submit">Call next · <?= e(format_queue_number($next_waiting_entry['queue_number'])) ?></button></form><?php endif; ?>
    <?php endif; ?>
</section>
<section class="content-section queue-waiting-section">
    <div class="content-heading"><div><p class="eyebrow">Waiting list</p><h2><?= count(array_filter($queue, static function ($entry) { return $entry['status'] === 'waiting'; })) ?> waiting</h2></div></div>
    <div class="queue-control-list" id="active-queue" data-queue-url="<?= e(url('api/queue-status.php' . ($category !== '' ? '?category=' . rawurlencode($category) : ''))) ?>" data-action-url="<?= e($queue_action_url) ?>" data-csrf-token="<?= e($csrf_token) ?>">
        <?php foreach ($queue as $entry): if ($entry['status'] !== 'waiting') continue; ?>
            <article class="queue-control-row"><span class="queue-number"><?= e(format_queue_number($entry['queue_number'])) ?></span><div class="appointment-details"><h3><?= e($entry['patient_name']) ?></h3><p><?= e($entry['service_name']) ?> · <?= e($entry['dentist_name']) ?></p></div><small class="queue-wait-estimate"><?= (int) $entry['estimated_wait_minutes'] ?> min</small><div class="queue-actions"><?php foreach (QUEUE_TRANSITIONS['waiting'] as $next_status): ?><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="transition"><input type="hidden" name="queue_entry_id" value="<?= (int) $entry['id'] ?>"><input type="hidden" name="status" value="<?= e($next_status) ?>"><button class="small-link<?= $next_status === 'skipped' ? ' danger-link' : '' ?>" type="submit"><?= $next_status === 'called' ? 'Call' : e(status_label($next_status)) ?></button></form><?php endforeach; ?></div></article>
        <?php endforeach; ?>
        <?php if (!$queue): ?><div class="empty-state"><p>No active patients in this department queue.</p></div><?php endif; ?>
    </div>
</section>
<details class="checkin-details"><summary>Confirmed appointments awaiting check-in (<?= count($check_ins) ?>)</summary>
    <?php if ($check_ins): ?><div class="checkin-list"><?php foreach ($check_ins as $appointment): ?><article class="checkin-row"><time><?= e(format_time($appointment['start_time'])) ?></time><div class="appointment-details"><h3><?= e($appointment['patient_name']) ?></h3><p><?= e($appointment['service_name']) ?> · <?= e($appointment['dentist_name']) ?></p></div><form method="post" action="<?= e($queue_action_url) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="check_in"><input type="hidden" name="appointment_id" value="<?= (int) $appointment['id'] ?>"><button class="button button-primary" type="submit">Check in</button></form></article><?php endforeach; ?></div><?php else: ?><p class="calendar-next-visit">No confirmed appointments are awaiting check-in.</p><?php endif; ?>
</details>
<script src="<?= e(url('assets/js/queue.js')) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>