const bookingForm = document.querySelector('.booking-form');

if (bookingForm) {
    const serviceInputs = [...bookingForm.querySelectorAll('input[type="radio"][name="service_id"]')];
    const dentistInputs = [...bookingForm.querySelectorAll('input[type="radio"][name="dentist_id"]')];
    const dateInput = bookingForm.querySelector('#appointment_date');
    const selectedTime = bookingForm.querySelector('#appointment_time');
    const slotsContainer = bookingForm.querySelector('#slot-options');
    const submitButton = bookingForm.querySelector('#booking-submit');
    const weekContainer = bookingForm.querySelector('#booking-week');
    const weekLabel = bookingForm.querySelector('#week-label');
    const summaryService = bookingForm.querySelector('#summary-service');
    const summaryDentist = bookingForm.querySelector('#summary-dentist');
    const summaryDate = bookingForm.querySelector('#summary-date');
    const summaryTime = bookingForm.querySelector('#summary-time');
    const rescheduleId = Number(bookingForm.dataset.rescheduleId || 0);
    let weekStart = new Date(`${bookingForm.dataset.weekStart}T12:00:00`);

    function selectedService() {
        return serviceInputs.find(input => input.checked);
    }

    function selectedDentist() {
        return dentistInputs.find(input => input.checked);
    }

    function showSlotMessage(message) {
        slotsContainer.replaceChildren();
        const hint = document.createElement('span');
        hint.className = 'slot-hint';
        hint.textContent = message;
        slotsContainer.append(hint);
        selectedTime.value = '';
        summaryTime.textContent = 'Choose a time';
        submitButton.disabled = true;
    }

    function refreshChoiceStyles() {
        for (const input of [...serviceInputs, ...dentistInputs]) {
            input.closest('label').classList.toggle('is-selected', input.checked);
        }
        const service = selectedService();
        if (!service) return;
        for (const input of dentistInputs) {
            const choice = input.closest('label');
            if (input.value === '0') continue;
            const available = choice.dataset.services.split(',').includes(service.value);
            choice.hidden = !available;
            input.disabled = !available || rescheduleId > 0;
            if (!available && input.checked) dentistInputs[0].checked = true;
        }
        summaryService.textContent = service.closest('label').querySelector('.service-choice-name').textContent;
        const dentist = selectedDentist();
        summaryDentist.textContent = dentist ? dentist.closest('label').querySelector('span').textContent : 'Any available';
    }

    function dateLabel(dateString, options) {
        return new Intl.DateTimeFormat('en', options).format(new Date(`${dateString}T12:00:00`));
    }

    function updateWeekLabel() {
        const end = new Date(weekStart);
        end.setDate(end.getDate() + 6);
        weekLabel.textContent = `${dateLabel(weekStart.toISOString().slice(0, 10), { month: 'short', day: 'numeric' })} - ${dateLabel(end.toISOString().slice(0, 10), { month: 'short', day: 'numeric', year: 'numeric' })}`;
    }

    function renderWeek() {
        weekContainer.replaceChildren();
        const minDate = new Date(`${bookingForm.dataset.minDate}T12:00:00`);
        const maxDate = new Date(`${bookingForm.dataset.maxDate}T12:00:00`);
        for (let day = 0; day < 7; day++) {
            const date = new Date(weekStart);
            date.setDate(date.getDate() + day);
            const isoDate = date.toISOString().slice(0, 10);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'week-day';
            button.dataset.date = isoDate;
            button.setAttribute('aria-pressed', String(isoDate === dateInput.value));
            button.classList.toggle('is-selected', isoDate === dateInput.value);
            button.disabled = date < minDate || date > maxDate;
            const weekday = document.createElement('span');
            weekday.textContent = dateLabel(isoDate, { weekday: 'short' });
            const number = document.createElement('strong');
            number.textContent = dateLabel(isoDate, { day: '2-digit' });
            button.append(weekday, number);
            button.addEventListener('click', () => {
                dateInput.value = isoDate;
                selectedTime.value = '';
                renderWeek();
                updateSummaryDate();
                loadSlots();
            });
            weekContainer.append(button);
        }
        updateWeekLabel();
    }

    function updateSummaryDate() {
        summaryDate.textContent = dateInput.value
            ? dateLabel(dateInput.value, { weekday: 'short', month: 'short', day: 'numeric' })
            : 'Choose a date';
    }

    async function loadSlots() {
        const service = selectedService();
        const dentist = selectedDentist();
        selectedTime.value = '';
        submitButton.disabled = true;
        if (!service || !dentist || !dateInput.value) {
            showSlotMessage('Choose a service, dentist and date first.');
            return;
        }

        showSlotMessage('Checking open times...');
        const params = new URLSearchParams({ service_id: service.value, dentist_id: dentist.value, date: dateInput.value });
        if (rescheduleId > 0) params.set('exclude_appointment_id', String(rescheduleId));

        try {
            const response = await fetch(`${bookingForm.dataset.availabilityUrl}?${params}`, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'Availability could not be loaded.');
            if (!data.slots.length) {
                showSlotMessage('No times available. Try another day.');
                return;
            }

            slotsContainer.replaceChildren();
            for (const slot of data.slots) {
                const label = document.createElement('label');
                label.className = 'slot-option';
                const radio = document.createElement('input');
                radio.type = 'radio';
                radio.name = 'appointment_time_choice';
                radio.value = slot;
                radio.required = true;
                radio.checked = selectedTime.value === slot;
                const text = document.createElement('span');
                const [hour, minute] = slot.split(':').map(Number);
                const displayTime = new Date(2000, 0, 1, hour, minute).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
                text.textContent = displayTime;
                radio.addEventListener('change', () => {
                    selectedTime.value = slot;
                    summaryTime.textContent = displayTime;
                    submitButton.disabled = false;
                });
                label.append(radio, text);
                slotsContainer.append(label);
            }
        } catch (error) {
            showSlotMessage(error.message || 'Availability could not be loaded. Try again.');
        }
    }

    for (const input of serviceInputs) input.addEventListener('change', () => { refreshChoiceStyles(); loadSlots(); });
    for (const input of dentistInputs) input.addEventListener('change', () => { refreshChoiceStyles(); loadSlots(); });
    bookingForm.querySelector('#previous-week').addEventListener('click', () => {
        weekStart.setDate(weekStart.getDate() - 7);
        renderWeek();
    });
    bookingForm.querySelector('#next-week').addEventListener('click', () => {
        weekStart.setDate(weekStart.getDate() + 7);
        renderWeek();
    });

    refreshChoiceStyles();
    renderWeek();
    updateSummaryDate();
    if (selectedTime.value) summaryTime.textContent = selectedTime.value;
    loadSlots();
}