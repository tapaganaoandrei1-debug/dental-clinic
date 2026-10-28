<?php
if (!defined('APP_NAME')) {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../../header.php';
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
<?php require __DIR__ . '/../../footer.php'; ?>