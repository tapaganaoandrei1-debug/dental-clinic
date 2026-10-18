const walkinForm = document.querySelector('.walkin-form');

if (walkinForm) {
    const service = walkinForm.querySelector('#service_id');
    const dentist = walkinForm.querySelector('#dentist_id');
    const slots = walkinForm.querySelector('#walkin-slots');
    const chosenTime = walkinForm.querySelector('#appointment_time');
    const submit = walkinForm.querySelector('#walkin-submit');

    function showMessage(message) {
        slots.replaceChildren();
        const hint = document.createElement('span');
        hint.className = 'slot-hint';
        hint.textContent = message;
        slots.append(hint);
        chosenTime.value = '';
        submit.disabled = true;
    }

    async function refreshSlots() {
        const selectedService = service.value;
        if (!selectedService) {
            showMessage('Choose a service to see open times.');
            return;
        }
        const params = new URLSearchParams({ service_id: selectedService, dentist_id: dentist.value || '0', date: walkinForm.dataset.today });
        showMessage('Checking today’s availability...');
        try {
            const response = await fetch(`${walkinForm.dataset.availabilityUrl}?${params}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const result = await response.json();
            if (!response.ok || !result.ok) throw new Error(result.error || 'Availability could not be loaded.');
            if (!result.slots.length) {
                showMessage('No open times remain today.');
                return;
            }
            slots.replaceChildren();
            for (const slot of result.slots) {
                const label = document.createElement('label');
                label.className = 'slot-option';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'walkin_time_choice';
                radio.value = slot;
                const text = document.createElement('span');
                const [hour, minute] = slot.split(':').map(Number);
                text.textContent = new Date(2000, 0, 1, hour, minute).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                radio.addEventListener('change', () => {
                    chosenTime.value = slot;
                    submit.disabled = false;
                });
                label.append(radio, text);
                slots.append(label);
            }
        } catch (error) {
            showMessage(error.message || 'Availability could not be loaded.');
        }
    }

    service.addEventListener('change', refreshSlots);
    dentist.addEventListener('change', refreshSlots);
    if (service.value) refreshSlots();
}