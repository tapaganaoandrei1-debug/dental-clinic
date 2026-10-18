const activeQueue = document.querySelector('#active-queue');

if (activeQueue) {
    const updatedLabel = document.querySelector('#queue-refresh-state');
    const statusLabels = {
        waiting: 'Waiting',
        called: 'Called',
        serving: 'In service',
        completed: 'Completed',
        skipped: 'Skipped',
        no_show: 'No-show',
        cancelled: 'Cancelled',
    };

    function makeHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        return input;
    }

    function renderQueue(entries, token) {
        entries = entries.filter(entry => entry.status === 'waiting');
        activeQueue.replaceChildren();
        if (!entries.length) {
            const empty = document.createElement('div');
            empty.className = 'empty-state';
            const title = document.createElement('p');
            title.textContent = 'No active patients in the queue.';
            empty.append(title);
            activeQueue.append(empty);
            return;
        }

        for (const entry of entries) {
            const row = document.createElement('article');
            row.className = 'queue-control-row';
            const number = document.createElement('span');
            number.className = 'queue-number';
            number.textContent = `Q-${String(entry.queue_number).padStart(3, '0')}`;

            const details = document.createElement('div');
            details.className = 'appointment-details';
            const name = document.createElement('h3');
            name.textContent = entry.patient_name;
            const service = document.createElement('p');
            service.textContent = `${entry.service_name} · ${entry.dentist_name}`;
            details.append(name, service);

            const state = document.createElement('div');
            state.className = 'queue-state';
            const badge = document.createElement('span');
            badge.className = `badge badge-${entry.status.replaceAll('_', '-')}`;
            badge.textContent = statusLabels[entry.status] || entry.status;
            const wait = document.createElement('small');
            wait.textContent = `${entry.estimated_wait_minutes} min est.`;
            state.append(badge, wait);

            const actions = document.createElement('div');
            actions.className = 'queue-actions';
            for (const nextStatus of entry.next_statuses) {
                const form = document.createElement('form');
                form.method = 'post';
                form.action = activeQueue.dataset.actionUrl;
                form.append(
                    makeHiddenInput('csrf_token', token),
                    makeHiddenInput('action', 'transition'),
                    makeHiddenInput('queue_entry_id', entry.id),
                    makeHiddenInput('status', nextStatus),
                );
                const button = document.createElement('button');
                button.className = 'small-link';
                button.type = 'submit';
                button.textContent = statusLabels[nextStatus] || nextStatus;
                form.append(button);
                actions.append(form);
            }
            row.append(number, details, state, actions);
            activeQueue.append(row);
        }
    }

    async function refreshQueue() {
        try {
            const response = await fetch(activeQueue.dataset.queueUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error('Queue update unavailable.');
            renderQueue(data.entries, data.csrf_token);
            updatedLabel.textContent = 'Updated just now';
        } catch {
            updatedLabel.textContent = 'Live update unavailable';
        }
        window.setTimeout(refreshQueue, 15000);
    }

    refreshQueue();
}