const patientQueue = document.querySelector('#patient-queue');

if (patientQueue) {
    const updatedLabel = document.querySelector('#queue-refresh-state');
    const alertToggle = document.querySelector('#queue-alert-toggle');
    let notifiedEntryId = '';

    if (alertToggle) {
        try {
            alertToggle.checked = localStorage.getItem('smilecare-queue-alerts') === 'on';
        } catch {
            alertToggle.checked = false;
        }

        alertToggle.addEventListener('change', async () => {
            try {
                localStorage.setItem('smilecare-queue-alerts', alertToggle.checked ? 'on' : 'off');
            } catch {
                updatedLabel.textContent = 'Alert preference is only available for this session';
            }
            if (alertToggle.checked && 'Notification' in window && Notification.permission === 'default') {
                await Notification.requestPermission();
            }
        });
    }

    function ordinal(number) {
        const suffix = number % 100 >= 11 && number % 100 <= 13
            ? 'th'
            : ({ 1: 'st', 2: 'nd', 3: 'rd' }[number % 10] || 'th');
        return `${number}${suffix}`;
    }

    function updateQueue(entry) {
        const card = patientQueue.querySelector('.queue-token-card');
        if (!card) {
            window.location.reload();
            return;
        }

        const ahead = Number(entry.ahead_count);
        card.querySelector('.queue-token-number').textContent = `Q-${String(entry.queue_number).padStart(3, '0')}`;
        card.querySelector('.queue-token-service').textContent = `${entry.service_name} · Today, ${entry.start_time.slice(0, 5)}`;
        card.querySelector('.queue-position').textContent = ordinal(ahead + 1);
        card.querySelector('.queue-ahead').textContent = String(ahead);
        card.querySelector('.queue-wait').textContent = String(entry.wait_estimate);
        card.querySelector('.queue-serving-number').textContent = entry.now_serving
            ? `Q-${String(entry.now_serving).padStart(3, '0')}`
            : 'No one yet';
        card.querySelector('.queue-progress-track > span').style.width = `${Math.max(10, Math.min(100, Math.round(100 / (ahead + 2))))}%`;

        if (alertToggle && alertToggle.checked && ahead <= 2) {
            updatedLabel.textContent = ahead === 0 ? 'You are next' : `${ahead} ${ahead === 1 ? 'patient' : 'patients'} ahead`;
            const key = `${entry.id}-${ahead}`;
            if (key !== notifiedEntryId && 'Notification' in window && Notification.permission === 'granted') {
                new Notification('SmileCare queue update', { body: ahead === 0 ? 'You are next. Please proceed to the clinic.' : `You are ${ahead} patients away.` });
                notifiedEntryId = key;
            }
        } else {
            updatedLabel.textContent = 'Updated just now';
        }
    }

    async function refreshQueue() {
        try {
            const response = await fetch(patientQueue.dataset.queueUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error('Queue update unavailable.');
            if (!data.entries.length) {
                if (patientQueue.querySelector('.queue-token-card')) window.location.reload();
                updatedLabel.textContent = 'No active queue token';
            } else {
                updateQueue(data.entries[0]);
            }
        } catch {
            updatedLabel.textContent = 'Live update unavailable';
        }
        window.setTimeout(refreshQueue, 15000);
    }

    refreshQueue();
}