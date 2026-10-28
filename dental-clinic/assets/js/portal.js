(() => {
    const root = document.querySelector('#app-content');
    const nav = document.querySelector('#main-navigation');
    const accountActions = document.querySelector('#account-actions');
    const messageElement = document.querySelector('#app-message');
    const authEndpoint = 'api/auth.php';
    const portalEndpoint = 'api/portal.php';
    let csrfToken = '';
    let currentUser = null;
    let catalog = { services: [], dentists: [] };
    let queuePoll = 0;
    let messageTimeout = 0;

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[character]);

    function localDate(date = new Date()) {
        return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
    }

    function formatDate(value) {
        if (!value) return '';
        const [year, month, day] = value.slice(0, 10).split('-').map(Number);
        return new Intl.DateTimeFormat(undefined, { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
            .format(new Date(year, month - 1, day));
    }

    function formatTime(value) {
        if (!value) return '';
        const [hour, minute] = value.split(':').map(Number);
        return new Date(2000, 0, 1, hour, minute).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }

    function statusLabel(status) {
        return ({ no_show: 'No-show', serving: 'In service' })[status] || status.replaceAll('_', ' ');
    }

    async function request(url, options = {}) {
        const headers = { Accept: 'application/json', ...(options.headers || {}) };
        if (options.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-Token'] = csrfToken;
        }
        const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, headers });
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            throw new Error('The server returned an unexpected response. Refresh the page and try again.');
        }
        const data = await response.json();
        if (!response.ok || data.ok === false) {
            if (response.status === 401) {
                currentUser = null;
                await loadSession();
                location.hash = 'login';
            }
            throw new Error(data.error || data.errors?.join(' ') || 'The request could not be completed.');
        }
        if (data.csrf_token) csrfToken = data.csrf_token;
        return data;
    }

    function flash(message, isError = false) {
        window.clearTimeout(messageTimeout);
        messageElement.textContent = message;
        messageElement.classList.toggle('is-error', isError);
        messageElement.hidden = false;
        if (!isError) messageTimeout = window.setTimeout(() => { messageElement.hidden = true; }, 6000);
    }

    function route() {
        const hash = location.hash.replace(/^#/, '').split('?')[0];
        return hash || (currentUser ? 'dashboard' : 'home');
    }

    function navigate(path) {
        if (location.hash === `#${path}`) {
            void render();
        } else {
            location.hash = path;
        }
    }

    async function loadSession() {
        const data = await request(`${authEndpoint}?action=session`);
        csrfToken = data.csrf_token;
        currentUser = data.authenticated ? data.user : null;
        updateShell();
    }

    function updateShell() {
        const routeName = route();
        if (!currentUser) {
            nav.innerHTML = `<a href="#home" data-route="home"${routeName === 'home' ? ' class="is-active"' : ''}>Home</a><a href="#login" data-route="login"${routeName === 'login' ? ' class="is-active"' : ''}>Log in</a><a href="#register" data-route="register"${routeName === 'register' ? ' class="is-active"' : ''}>Create account</a>`;
            accountActions.innerHTML = '';
            return;
        }
        const staff = ['staff', 'admin'].includes(currentUser.role);
        const items = [
            ['dashboard', 'Dashboard'],
            ...(currentUser.role === 'patient' ? [['book', 'Book visit']] : []),
            ['appointments', 'Appointments'],
            ['queue', 'Live queue'],
            ...(currentUser.role === 'patient' ? [['notifications', 'Alerts']] : []),
            ...(staff ? [['walk-in', 'Walk-in']] : []),
            ...(currentUser.role === 'staff' ? [['staff-notifications', 'Outbox']] : []),
            ...(currentUser.role === 'admin' ? [
                ['admin-users', 'Users'],
                ['admin-schedules', 'Schedules'],
                ['admin-reports', 'Reports'],
                ['admin-activity', 'Activity'],
                ['admin-backup', 'Backup'],
            ] : []),
        ];
        nav.innerHTML = items.map(([path, label]) => `<a href="#${path}" data-route="${path}"${routeName === path ? ' class="is-active" aria-current="page"' : ''}>${label}</a>`).join('');
        accountActions.innerHTML = `<span class="account-user" title="${escapeHtml(currentUser.email)}">${escapeHtml(currentUser.name)}</span><button type="button" data-action="logout">Log out</button>`;
    }

    function pageHeader(eyebrow, title, description = '') {
        return `<header class="page-heading"><div><p class="eyebrow">${escapeHtml(eyebrow)}</p><h1>${title}</h1>${description ? `<p>${escapeHtml(description)}</p>` : ''}</div>${currentUser ? `<span class="role-pill">${escapeHtml(currentUser.role)}</span>` : ''}</header>`;
    }

    function emptyState(text) {
        return `<p class="empty-state">${escapeHtml(text)}</p>`;
    }

    function button(label, action, id, style = 'button-secondary', extra = '') {
        return `<button type="button" class="button button-small ${style}" data-action="${action}" data-id="${escapeHtml(id)}" ${extra}>${escapeHtml(label)}</button>`;
    }

    async function renderHome() {
        const data = await request(`${portalEndpoint}?view=catalog`);
        catalog = data;
        const services = data.services.map(service => `<article class="service-card"><h3>${escapeHtml(service.name)}</h3><p>${escapeHtml(service.description || 'Personalized care from our dental team.')}</p><span>${Number(service.duration_minutes)} min · ${escapeHtml(service.price)}</span></article>`).join('');
        root.innerHTML = `<section class="home-hero"><div class="home-hero-copy"><p class="eyebrow">A brighter kind of dental care</p><h1>Good care.<br><em>Good feeling.</em></h1><p>Thoughtful dentistry, friendly people, and a calmer way to look after your smile.</p><div class="form-actions"><a class="button button-primary" href="#register">Create a patient account →</a><a class="button button-secondary" href="#login">Sign in</a></div></div><aside class="home-hero-photo" role="img" aria-label="Dentist caring for a patient in a modern clinic"><div class="home-photo-caption"><span class="eyebrow">SmileCare Dental</span><h2>Comfortable care, every visit.</h2><p>Modern treatments with a personal touch.</p></div></aside></section><section class="photo-story"><div class="section-heading"><div><p class="eyebrow">A clinic built around you</p><h2>Care that feels different.</h2></div><p>From your first hello to your brighter smile, our team makes every step feel simple.</p></div><div class="photo-grid"><figure class="photo-card photo-card-room"><img src="assets/images/treatment-room.png" alt="Clean, modern dental treatment room"><figcaption><span>Modern treatment spaces</span></figcaption></figure><figure class="photo-card photo-card-smile"><img src="assets/images/smile-care.png" alt="A dentist carefully examining a patient's smile"><figcaption><span>Gentle, personal dentistry</span></figcaption></figure><figure class="photo-card photo-card-lounge"><img src="assets/images/clinic-lounge.png" alt="Bright dental clinic reception and lounge"><figcaption><span>A warm welcome from the moment you arrive</span></figcaption></figure></div></section><section><div class="section-heading"><div><p class="eyebrow">Care, made personal</p><h2>Explore our services</h2></div></div><div class="services-grid">${services || emptyState('Our services are being updated.')}</div></section>`;
    }

    function renderAuth(mode) {
        const register = mode === 'register';
        const fields = register
            ? `<div><label for="full-name">Full name</label><input id="full-name" name="full_name" autocomplete="name" minlength="2" maxlength="100" required></div><div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="email" maxlength="150" required></div><div><label for="phone">Phone number <span>(optional)</span></label><input id="phone" name="phone" type="tel" autocomplete="tel" maxlength="20"></div><div><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required></div><div><label for="password-confirmation">Confirm password</label><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required></div>`
            : `<div><label for="email">Email address</label><input id="email" name="email" type="email" autocomplete="username" maxlength="150" required></div><div><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>`;
        root.innerHTML = `<section class="auth-layout"><aside class="auth-aside"><p class="eyebrow">SmileCare Dental</p><h1>${register ? 'Start your care, your way.' : 'Your care, all in one place.'}</h1><p>${register ? 'Create your patient account to request appointments and follow your clinic queue.' : 'Sign in to see your appointments and live queue updates.'}</p></aside><div class="auth-form-panel"><p class="eyebrow">Patient portal</p><h2>${register ? 'Create account' : 'Sign in'}</h2><p>${register ? 'New patient accounts must be verified before first sign in.' : 'Use the email address connected to your account.'}</p><form id="${register ? 'register-form' : 'login-form'}" class="auth-form">${fields}<button class="button button-primary button-wide" type="submit">${register ? 'Create account' : 'Sign in'} →</button></form><p class="auth-switch">${register ? 'Already have an account?' : 'New to the clinic?'} <button type="button" data-route="${register ? 'login' : 'register'}">${register ? 'Log in' : 'Create an account'}</button></p>${!register ? '<p class="auth-switch"><a href="#forgot-password">Forgot your password?</a></p>' : ''}</div></section>`;
    }

    async function renderForgotPassword() {
        root.innerHTML = `${pageHeader('Secure account recovery', 'Reset your password', 'We will send a time-limited reset link if the address matches an active clinic account.')}<section class="panel auth-form-panel"><form id="forgot-password-form" class="auth-form"><div><label for="recovery-email">Email address</label><input id="recovery-email" name="email" type="email" maxlength="150" autocomplete="email" required></div><button class="button button-primary" type="submit">Send reset link</button></form><p class="form-help">In this local build, queued recovery notices can be reviewed in the staff notification outbox.</p><p class="auth-switch"><a href="#login">Return to sign in</a></p></section>`;
    }

    async function renderResetPassword() {
        const token = new URLSearchParams(location.hash.split('?')[1] || '').get('token') || '';
        const status = await request(`${authEndpoint}?action=token_status&type=reset&token=${encodeURIComponent(token)}`);
        if (!status.valid) {
            root.innerHTML = `${pageHeader('Secure account recovery', 'Reset link unavailable')}<section class="panel"><p>This reset link is invalid or has expired. Request a new one to continue.</p><a class="button button-primary" href="#forgot-password">Request a reset link</a></section>`;
            return;
        }
        root.innerHTML = `${pageHeader('Secure account recovery', 'Choose a new password', 'Use at least eight characters, including a letter and a number.')}<section class="panel auth-form-panel"><form id="reset-password-form" class="auth-form" data-token="${escapeHtml(token)}"><div><label for="reset-password">New password</label><input id="reset-password" name="password" type="password" minlength="8" autocomplete="new-password" required></div><div><label for="reset-confirmation">Confirm password</label><input id="reset-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></div><button class="button button-primary" type="submit">Update password</button></form></section>`;
    }

    async function renderVerifyEmail() {
        const token = new URLSearchParams(location.hash.split('?')[1] || '').get('token') || '';
        const status = await request(`${authEndpoint}?action=token_status&type=verify&token=${encodeURIComponent(token)}`);
        root.innerHTML = `${pageHeader('Account verification', status.valid ? 'Verify your email' : 'Verification link unavailable', status.valid ? 'Confirm your email address to activate sign-in.' : 'This verification link is invalid or has expired. Contact the clinic if you need help.')}<section class="panel">${status.valid ? `<form id="verify-email-form" data-token="${escapeHtml(token)}"><button class="button button-primary" type="submit">Verify email address</button></form>` : '<a class="button button-secondary" href="#login">Return to sign in</a>'}</section>`;
    }

    function appointmentCard(appointment) {
        const staff = ['staff', 'admin'].includes(currentUser.role);
        const queueStatus = appointment.queue_status;
        const status = queueStatus || appointment.status;
        const patientName = staff ? `<p class="record-name">${escapeHtml(appointment.patient_name)}</p>` : '';
        const meta = staff
            ? `${escapeHtml(appointment.service_name)} · ${escapeHtml(appointment.dentist_name)} · ${escapeHtml(appointment.patient_email)}`
            : `${escapeHtml(appointment.service_name)} · ${escapeHtml(appointment.dentist_name)}`;
        const time = `${formatDate(appointment.appointment_date)} · ${formatTime(appointment.start_time)}–${formatTime(appointment.end_time)}`;
        let actions = `<span class="status-pill" data-status="${escapeHtml(status)}">${escapeHtml(statusLabel(status))}</span>`;
        if (!staff && appointment.allowed_transitions.includes('cancelled')) {
            if (appointment.can_reschedule) {
                actions += button('Reschedule', 'reschedule-appointment', appointment.id);
            }
            actions += button('Cancel', 'cancel-appointment', appointment.id, 'button-danger');
            actions += `<a class="button button-small button-secondary" href="#receipt?id=${Number(appointment.id)}">Receipt</a><a class="button button-small button-secondary" href="patient/calendar.php?id=${Number(appointment.id)}">Calendar</a>`;
        } else if (!staff) {
            actions += `<a class="button button-small button-secondary" href="#receipt?id=${Number(appointment.id)}">Receipt</a><a class="button button-small button-secondary" href="patient/calendar.php?id=${Number(appointment.id)}">Calendar</a>`;
        }
        if (staff && appointment.allowed_transitions.length) {
            actions += `<div class="appointment-actions">${appointment.allowed_transitions.map(next => button(statusLabel(next), 'appointment-transition', appointment.id, ['cancelled', 'no_show'].includes(next) ? 'button-danger' : 'button-primary', `data-status="${escapeHtml(next)}"`)).join('')}</div>`;
        }
        return `<article class="record-card" data-appointment-id="${Number(appointment.id)}"><div>${patientName}<p class="record-meta">${meta}</p><span class="record-date">${escapeHtml(time)}</span>${appointment.staff_notes ? `<p class="visit-note">${escapeHtml(appointment.staff_notes)}</p>` : ''}</div><div class="record-actions">${actions}</div></article>`;
    }

    async function renderDashboard() {
        const data = (await request(`${portalEndpoint}?view=dashboard`)).dashboard;
        if (data.role === 'patient') {
            const summary = data.summary;
            root.innerHTML = `${pageHeader('Patient portal', `Good to see you, <em>${escapeHtml(currentUser.name.split(' ')[0])}.</em>`, 'Here is your care at a glance.')}<div class="stats-grid"><article class="stat-card is-highlight"><span>Upcoming</span><strong>${Number(summary.upcoming_count || 0)}</strong></article><article class="stat-card"><span>Completed</span><strong>${Number(summary.completed_count || 0)}</strong></article><article class="stat-card"><span>All appointments</span><strong>${Number(summary.total || 0)}</strong></article><article class="stat-card"><span>Live queue</span><strong>On</strong></article></div><div class="dashboard-grid"><section class="panel"><div class="panel-title"><div><p class="eyebrow">Coming up</p><h2>Your appointments</h2></div><a class="button button-secondary button-small" href="#appointments">View all</a></div><div class="record-list">${data.appointments.length ? data.appointments.map(item => `<article class="record-card"><div><p class="record-name">${escapeHtml(item.service_name)}</p><p class="record-meta">${escapeHtml(item.dentist_name)}</p><span class="record-date">${formatDate(item.appointment_date)} · ${formatTime(item.start_time)}</span></div><span class="status-pill" data-status="${escapeHtml(item.status)}">${escapeHtml(statusLabel(item.status))}</span></article>`).join('') : emptyState('No upcoming appointments. Book a visit when you are ready.')}</div></section><aside class="panel dashboard-actions"><div><p class="eyebrow">Quick actions</p><h2>Plan your next visit</h2></div><a class="button button-primary" href="#book">Book an appointment</a><a class="button button-secondary" href="#queue">View my live queue</a></aside></div>`;
            return;
        }
        const summary = data.summary;
        root.innerHTML = `${pageHeader(data.role === 'admin' ? 'Clinic administration' : 'Front desk', 'Clinic dashboard', 'A shared live view of today’s schedule and waiting room.')}<div class="stats-grid"><article class="stat-card is-highlight"><span>Appointments today</span><strong>${Number(summary.appointments_today || 0)}</strong></article><article class="stat-card"><span>Waiting now</span><strong>${Number(summary.waiting_now || 0)}</strong></article><article class="stat-card"><span>Average wait</span><strong>${Number(summary.average_wait || 0)} min</strong></article><article class="stat-card"><span>Pending requests</span><strong>${Number(summary.pending_today || 0)}</strong></article></div><div class="dashboard-grid"><section class="panel"><div class="panel-title"><div><p class="eyebrow">Today</p><h2>Appointments &amp; queue</h2></div><a href="#appointments" class="button button-secondary button-small">Manage appointments</a></div><div class="record-list">${data.appointments.length ? data.appointments.map(item => `<article class="record-card"><div><p class="record-name">${formatTime(item.start_time)} · ${escapeHtml(item.patient_name)}</p><p class="record-meta">${escapeHtml(item.service_name)} · ${escapeHtml(item.dentist_name)}</p></div><span class="status-pill" data-status="${escapeHtml(item.queue_status || item.status)}">${escapeHtml(statusLabel(item.queue_status || item.status))}</span></article>`).join('') : emptyState('No appointments scheduled for today.')}</div></section><aside class="panel dashboard-actions"><div><p class="eyebrow">Live waiting room</p><h2>${data.serving ? `Now serving ${escapeHtml(data.serving.full_name)}` : 'No patient in service'}</h2><p class="record-meta">${data.serving ? `${escapeHtml(data.serving.service_name)} · ${escapeHtml(data.serving.dentist_name)}` : `${Number(summary.waiting_now || 0)} patients waiting`}</p></div><a class="button button-primary" href="#queue">${data.next_waiting ? `Open queue · next Q-${String(data.next_waiting).padStart(3, '0')}` : 'Manage live queue'}</a><a class="button button-secondary" href="#walk-in">Create walk-in check-in</a></aside></div>`;
    }

    async function renderAppointments() {
        const staff = ['staff', 'admin'].includes(currentUser.role);
        const url = new URL(`${portalEndpoint}?view=appointments`, location.href);
        if (staff) {
            const params = new URLSearchParams(location.hash.split('?')[1] || '');
            url.searchParams.set('date', params.get('date') || localDate());
            if (params.get('status')) url.searchParams.set('status', params.get('status'));
            if (params.get('q')) url.searchParams.set('q', params.get('q'));
        }
        const data = await request(url.toString());
        const filters = staff ? `<form id="appointment-filter" class="filters"><div class="filter-control"><label for="filter-date">Date</label><input id="filter-date" name="date" type="date" value="${escapeHtml(url.searchParams.get('date'))}"></div><div class="filter-control"><label for="filter-status">Status</label><select id="filter-status" name="status"><option value="">All statuses</option>${['pending', 'confirmed', 'completed', 'cancelled', 'no_show'].map(status => `<option value="${status}"${url.searchParams.get('status') === status ? ' selected' : ''}>${escapeHtml(statusLabel(status))}</option>`).join('')}</select></div><div class="filter-control"><label for="filter-search">Search</label><input id="filter-search" name="q" type="search" value="${escapeHtml(url.searchParams.get('q') || '')}" placeholder="Patient, service or email"></div><button class="button button-primary" type="submit">Apply filters</button></form>` : '';
        const description = staff ? 'Review appointment requests and see their live queue status.' : 'Review your requested and confirmed clinic visits.';
        root.innerHTML = `${pageHeader(staff ? 'Front desk' : 'Patient portal', staff ? 'Appointment management' : 'My appointments', description)}${filters}<section class="panel"><div class="panel-title"><div><p class="eyebrow">Schedule</p><h2>${data.appointments.length} appointments</h2></div>${!staff ? '<a href="#book" class="button button-primary button-small">Book a visit</a>' : ''}</div><div class="record-list">${data.appointments.length ? data.appointments.map(appointmentCard).join('') : emptyState('No appointments match this view.')}</div></section>`;
    }

    async function renderQueue() {
        const params = new URLSearchParams(location.hash.split('?')[1] || '');
        const category = params.get('category') || '';
        const url = `${portalEndpoint}?view=queue${category ? `&category=${encodeURIComponent(category)}` : ''}`;
        const data = (await request(url)).queue;
        if (data.role === 'patient') {
            const entry = data.entry;
            const ahead = entry ? Number(entry.ahead_count || 0) : 0;
            const waiting = entry && ['waiting', 'called', 'serving'].includes(entry.status);
            const activeCard = waiting
                ? `<div class="queue-overview"><article class="now-serving-card"><div><p class="eyebrow">Your queue token</p><h2>${escapeHtml('Q-' + String(entry.queue_number).padStart(3, '0'))}</h2><p>${escapeHtml(entry.service_name)} · ${escapeHtml(entry.dentist_name)}</p></div><span class="status-pill" data-status="${escapeHtml(entry.status)}">${escapeHtml(statusLabel(entry.status))}</span></article><div class="queue-metrics"><article class="queue-metric"><span>People ahead</span><strong>${ahead}</strong></article><article class="queue-metric"><span>Wait estimate</span><strong>${Number(entry.estimated_wait_minutes || 0)}m</strong></article><article class="queue-metric"><span>Now serving</span><strong>${data.now_serving ? 'Q-' + String(data.now_serving).padStart(3, '0') : '—'}</strong></article></div></div>`
                : `<div class="panel">${entry ? `<p class="eyebrow">Today's visit</p><h2>Queue status: ${escapeHtml(statusLabel(entry.status))}</h2><p class="record-meta">${escapeHtml(entry.service_name)} · ${escapeHtml(entry.dentist_name)}</p>` : '<p class="eyebrow">No active visit</p><h2>You are not checked in to today’s queue.</h2><p class="record-meta">A confirmed appointment will appear here after clinic staff check you in.</p>'}<a href="#appointments" class="button button-secondary">View appointments</a></div>`;
            root.innerHTML = `${pageHeader('Live clinic updates', 'My live queue', 'Queue status refreshes automatically while this page is open.')}<div id="queue-content">${activeCard}<section class="panel"><div class="panel-title"><div><p class="eyebrow">Live update</p><h2>Now serving</h2></div><span class="live-indicator">Updates every 10 seconds</span></div><p class="record-meta">${data.now_serving ? `Queue token Q-${String(data.now_serving).padStart(3, '0')} is currently in service.` : 'No patient is currently in service.'}</p></section></div>`;
            return;
        }
        const active = data.entries.filter(entry => entry.status === 'waiting');
        const serving = data.entries.find(entry => entry.status === 'serving');
        const called = data.entries.find(entry => entry.status === 'called');
        const waitingCount = data.entries.filter(entry => entry.status === 'waiting').length;
        const categoryFilters = `<form id="queue-filter" class="filters"><div class="filter-control"><label for="queue-category">Service category</label><select id="queue-category" name="category"><option value="">All categories</option>${data.categories.map(item => `<option value="${escapeHtml(item)}"${category === item ? ' selected' : ''}>${escapeHtml(item)}</option>`).join('')}</select></div></form>`;
        const servingCard = `<article class="now-serving-card"><div><p class="eyebrow">Now serving</p><h2>${serving ? `${escapeHtml('Q-' + String(serving.queue_number).padStart(3, '0'))} · ${escapeHtml(serving.patient_name)}` : 'No patient in service'}</h2><p>${serving ? `${escapeHtml(serving.service_name)} · ${escapeHtml(serving.dentist_name)}` : 'Call the next checked-in patient when the team is ready.'}</p></div><span class="queue-token-large">${serving ? String(serving.queue_number).padStart(3, '0') : 'Q'}</span></article>`;
        const queueRows = data.entries.map(entry => {
            const next = entry.status === 'waiting' ? [['Call', 'called']] : entry.status === 'called' ? [['Start service', 'serving']] : [['Mark complete', 'completed']];
            const controls = [...next.map(([label, status]) => button(label, 'queue-transition', entry.id, 'button-primary', `data-status="${status}"`)), ...entry.next_statuses.filter(status => ['skipped', 'no_show', 'cancelled'].includes(status)).map(status => button(statusLabel(status), 'queue-transition', entry.id, 'button-danger', `data-status="${status}"`))].join('');
            return `<article class="queue-row"><span class="queue-number">Q-${String(entry.queue_number).padStart(3, '0')}</span><div><p class="record-name">${escapeHtml(entry.patient_name)}</p><p class="record-meta">${escapeHtml(entry.service_name)} · ${escapeHtml(entry.dentist_name)}</p><span class="record-date">${Number(entry.estimated_wait_minutes)} min estimated · ${formatTime(entry.start_time)}</span></div><div class="record-actions"><span class="status-pill" data-status="${escapeHtml(entry.status)}">${escapeHtml(statusLabel(entry.status))}</span><div class="appointment-actions">${controls}</div></div></article>`;
        }).join('');
        const checkins = data.check_ins.map(item => `<article class="checkin-row"><time>${formatTime(item.start_time)}</time><div><p class="record-name">${escapeHtml(item.patient_name)}</p><p class="record-meta">${escapeHtml(item.service_name)} · ${escapeHtml(item.dentist_name)} · ${escapeHtml(item.patient_email)}</p></div>${button('Check in', 'queue-check-in', item.appointment_id, 'button-primary')}</article>`).join('');
        root.innerHTML = `${pageHeader('Front desk controls', 'Live queue management', 'Check patients in and update their queue status for the whole clinic.')}<section class="queue-overview">${servingCard}<div class="queue-metrics"><article class="queue-metric"><span>Waiting</span><strong>${waitingCount}</strong></article><article class="queue-metric"><span>Called</span><strong>${data.entries.filter(item => item.status === 'called').length}</strong></article><article class="queue-metric"><span>In service</span><strong>${serving ? 1 : 0}</strong></article></div></section>${categoryFilters}<section class="panel"><div class="panel-title"><div><p class="eyebrow">Active patients · live</p><h2>Queue (${data.entries.length})</h2></div><span class="live-indicator">Auto-refreshing</span></div><div id="queue-content" class="queue-list">${queueRows || emptyState('There are no patients in the active queue.')}</div></section><section class="panel" style="margin-top:16px"><div class="panel-title"><div><p class="eyebrow">Confirmed for today</p><h2>Awaiting check-in (${data.check_ins.length})</h2></div></div>${checkins || emptyState('No confirmed appointments are awaiting check-in.')}</section>`;
    }

    async function renderBook() {
        const data = await request(`${portalEndpoint}?view=catalog`);
        catalog = data;
        const minDate = localDate();
        const routeParams = new URLSearchParams(location.hash.split('?')[1] || '');
        const rescheduleId = Number(routeParams.get('reschedule') || 0);
        let rescheduledAppointment = null;
        if (rescheduleId > 0) {
            const appointments = (await request(`${portalEndpoint}?view=appointments`)).appointments;
            rescheduledAppointment = appointments.find(item => Number(item.id) === rescheduleId && item.can_reschedule);
            if (!rescheduledAppointment) {
                throw new Error('This appointment can no longer be changed online.');
            }
        }
        const services = data.services.map(service => `<option value="${Number(service.id)}"${rescheduledAppointment && Number(service.id) === Number(rescheduledAppointment.service_id) ? ' selected' : ''}>${escapeHtml(service.name)} · ${Number(service.duration_minutes)} min</option>`).join('');
        const dentists = data.dentists.map(dentist => `<option value="${Number(dentist.id)}" data-services="${escapeHtml(dentist.service_ids || '')}"${rescheduledAppointment && Number(dentist.id) === Number(rescheduledAppointment.dentist_id) ? ' selected' : ''}>${escapeHtml(dentist.full_name)}</option>`).join('');
        const heading = rescheduledAppointment ? 'Reschedule your appointment' : 'Book an appointment';
        const serviceField = `<div class="form-field"><label for="booking-service">Service</label><select id="booking-service" name="service_id" required${rescheduledAppointment ? ' disabled' : ''}>${services}</select></div>`;
        const dentistField = `<div class="form-field"><label for="booking-dentist">Dentist</label><select id="booking-dentist" name="dentist_id" required${rescheduledAppointment ? ' disabled' : ''}>${rescheduledAppointment ? '' : '<option value="0">Any available dentist</option>'}${dentists}</select></div>`;
        root.innerHTML = `${pageHeader('Patient portal', heading, rescheduledAppointment ? 'Choose a new date and open time for your visit.' : 'Choose a service, dentist, date, and an available time.')}<div class="content-columns"><form id="booking-form" class="panel" data-reschedule-id="${rescheduleId}"><div class="form-grid">${serviceField}${dentistField}<div class="form-field"><label for="booking-date">Date</label><input id="booking-date" name="appointment_date" type="date" min="${minDate}" required></div><div class="form-field"><label for="booking-time">Available time</label><select id="booking-time" name="appointment_time" required disabled><option value="">Choose service and date first</option></select></div><div class="form-field is-wide"><label for="patient-notes">Note for the clinic (optional)</label><textarea id="patient-notes" name="patient_notes" maxlength="2000" placeholder="Anything you would like the care team to know?">${escapeHtml(rescheduledAppointment?.patient_notes || '')}</textarea></div></div><div class="form-actions"><button class="button button-primary" type="submit">${rescheduledAppointment ? 'Request new appointment time' : 'Request appointment'}</button><a class="button button-secondary" href="#appointments">Back to appointments</a></div><p class="form-help">Requests are sent to the clinic and remain pending until confirmed.</p></form><aside class="panel"><p class="eyebrow">Booking information</p><h2>Real-time availability</h2><p class="record-meta">Open times are checked against the clinic’s schedule, dentist availability, breaks, holidays and existing appointments.</p><p class="record-meta">Your appointment request is saved to your patient account and appears in staff appointment management.</p></aside></div>`;
        const date = document.querySelector('#booking-date');
        date.value = rescheduledAppointment?.appointment_date || minDate;
        await loadSlots();
    }

    async function loadSlots() {
        const form = document.querySelector('#booking-form');
        if (!form) return;
        const service = form.elements.service_id.value;
        const dentist = form.elements.dentist_id.value;
        const date = form.elements.appointment_date.value;
        const slots = form.elements.appointment_time;
        slots.disabled = true;
        slots.innerHTML = '<option value="">Checking availability...</option>';
        if (!service || !date) return;
        const params = new URLSearchParams({ service_id: service, dentist_id: dentist || '0', date });
        if (form.dataset.rescheduleId) params.set('exclude_appointment_id', form.dataset.rescheduleId);
        try {
            const response = await request(`api/availability.php?${params}`);
            if (!response.slots.length) {
                slots.innerHTML = '<option value="">No open times for this date</option>';
                return;
            }
            slots.innerHTML = `<option value="">Choose an available time</option>${response.slots.map(time => `<option value="${escapeHtml(time)}">${formatTime(time)}</option>`).join('')}`;
            slots.disabled = false;
        } catch (error) {
            slots.innerHTML = '<option value="">Availability could not be loaded</option>';
            flash(error.message, true);
        }
    }

    async function renderWalkIn() {
        const [catalogData, patientsData] = await Promise.all([
            request(`${portalEndpoint}?view=catalog`),
            request(`${portalEndpoint}?view=patients`),
        ]);
        catalog = catalogData;
        const patients = patientsData.patients.map(patient => `<option value="${Number(patient.id)}">${escapeHtml(patient.full_name)} · ${escapeHtml(patient.email)}</option>`).join('');
        const services = catalogData.services.map(service => `<option value="${Number(service.id)}">${escapeHtml(service.name)} · ${Number(service.duration_minutes)} min</option>`).join('');
        const dentists = catalogData.dentists.map(dentist => `<option value="${Number(dentist.id)}" data-services="${escapeHtml(dentist.service_ids || '')}">${escapeHtml(dentist.full_name)}</option>`).join('');
        root.innerHTML = `${pageHeader('Front desk', 'Walk-in check-in', 'Create today’s walk-in appointment and add the patient directly to the live queue.')}<form id="walk-in-form" class="panel"><div class="form-grid"><div class="form-field is-wide"><label for="walk-in-patient">Patient</label><select id="walk-in-patient" name="patient_id" required><option value="">Choose an active patient account</option>${patients}</select></div><div class="form-field"><label for="walk-in-service">Service</label><select id="walk-in-service" name="service_id" required>${services}</select></div><div class="form-field"><label for="walk-in-dentist">Dentist</label><select id="walk-in-dentist" name="dentist_id" required><option value="0">Any available dentist</option>${dentists}</select></div><div class="form-field"><label for="walk-in-time">Available time today</label><select id="walk-in-time" name="appointment_time" required disabled><option value="">Checking availability...</option></select></div><div class="form-field is-wide"><label for="walk-in-notes">Note (optional)</label><textarea id="walk-in-notes" name="patient_notes" maxlength="2000"></textarea></div></div><div class="form-actions"><button class="button button-primary" type="submit">Create walk-in and check in</button></div><p class="form-help">This creates an appointment in the main database and immediately creates its live queue entry.</p></form>`;
        await loadWalkInSlots();
    }

    async function loadWalkInSlots() {
        const form = document.querySelector('#walk-in-form');
        if (!form) return;
        const slots = form.elements.appointment_time;
        const today = localDate();
        slots.disabled = true;
        slots.innerHTML = '<option value="">Checking availability...</option>';
        const params = new URLSearchParams({ service_id: form.elements.service_id.value, dentist_id: form.elements.dentist_id.value || '0', date: today });
        try {
            const response = await request(`api/availability.php?${params}`);
            slots.innerHTML = response.slots.length
                ? `<option value="">Choose an open time</option>${response.slots.map(time => `<option value="${escapeHtml(time)}">${formatTime(time)}</option>`).join('')}`
                : '<option value="">No future times available today</option>';
            slots.disabled = response.slots.length === 0;
        } catch (error) {
            slots.innerHTML = '<option value="">Availability could not be loaded</option>';
            flash(error.message, true);
        }
    }

    async function renderAdminUsers() {
        const data = await request(`${portalEndpoint}?view=admin_users`);
        const rows = data.users.map(user => {
            const self = Number(user.id) === Number(data.current_user_id);
            return `<tr><td><strong>${escapeHtml(user.full_name)}</strong><span class="table-subline">${escapeHtml(user.email)}${user.phone ? ` · ${escapeHtml(user.phone)}` : ''}</span><span class="table-subline">${user.email_verified_at ? 'Email verified' : 'Email not verified'}</span></td><td><form class="admin-user-update form-actions" data-user-id="${Number(user.id)}"><select name="role" aria-label="Role for ${escapeHtml(user.full_name)}"${self ? ' disabled' : ''}><option value="patient"${user.role === 'patient' ? ' selected' : ''}>Patient</option><option value="staff"${user.role === 'staff' ? ' selected' : ''}>Staff</option><option value="admin"${user.role === 'admin' ? ' selected' : ''}>Admin</option></select><select name="is_active" aria-label="Access for ${escapeHtml(user.full_name)}"${self ? ' disabled' : ''}><option value="1"${Number(user.is_active) === 1 ? ' selected' : ''}>Active</option><option value="0"${Number(user.is_active) !== 1 ? ' selected' : ''}>Inactive</option></select><span class="table-subline">${user.last_login_at ? escapeHtml(user.last_login_at) : 'Never signed in'}</span><button class="button button-small button-primary" type="submit"${self ? ' disabled' : ''}>Save</button></form></td></tr>`;
        }).join('');
        root.innerHTML = `${pageHeader('Clinic administration', 'People and access', 'Create accounts, assign roles, and manage account access.')}<section class="panel"><div class="panel-title"><div><p class="eyebrow">New account</p><h2>Add a team member or patient</h2></div></div><form id="admin-user-create" class="form-grid"><div class="form-field"><label for="admin-user-name">Full name</label><input id="admin-user-name" name="full_name" maxlength="100" required></div><div class="form-field"><label for="admin-user-email">Email</label><input id="admin-user-email" name="email" type="email" maxlength="150" required></div><div class="form-field"><label for="admin-user-phone">Phone (optional)</label><input id="admin-user-phone" name="phone" type="tel" maxlength="20"></div><div class="form-field"><label for="admin-user-role">Role</label><select id="admin-user-role" name="role"><option value="patient">Patient</option><option value="staff">Staff</option><option value="admin">Administrator</option></select></div><div class="form-field"><label for="admin-user-password">Temporary password</label><input id="admin-user-password" name="password" type="password" minlength="8" autocomplete="new-password" required></div><div class="form-field is-wide"><button class="button button-primary" type="submit">Create account</button><p class="form-help">At least 8 characters, with a letter and a number. Share temporary passwords securely.</p></div></form></section><section class="panel"><div class="panel-title"><div><p class="eyebrow">Accounts</p><h2>${data.users.length} users</h2></div><span class="record-meta">Most recent 200</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Person</th><th>Role, access and last sign-in</th></tr></thead><tbody>${rows || `<tr><td colspan="2">${emptyState('No accounts found.')}</td></tr>`}</tbody></table></div></section>`;
    }

    async function renderAdminSchedules() {
        const data = await request(`${portalEndpoint}?view=admin_schedules`);
        const weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const scheduleMap = new Map(data.schedules.map(item => [`${item.dentist_id}-${item.day_of_week}`, item]));
        const grid = data.dentists.map(dentist => `<section class="panel"><div class="panel-title"><div><p class="eyebrow">Provider availability</p><h2>${escapeHtml(dentist.full_name)}</h2></div></div><div class="content-columns">${weekdays.map((day, index) => {
            const row = scheduleMap.get(`${dentist.id}-${index}`);
            return `<form class="admin-schedule-form panel" data-dentist-id="${Number(dentist.id)}" data-day="${index}"><h3>${day}</h3><div class="form-grid"><div class="form-field"><label>Start</label><input name="start_time" type="time" value="${escapeHtml(row?.start_time?.slice(0, 5) || '09:00')}" required></div><div class="form-field"><label>End</label><input name="end_time" type="time" value="${escapeHtml(row?.end_time?.slice(0, 5) || '17:00')}" required></div><div class="form-field"><label>Break start</label><input name="break_start" type="time" value="${escapeHtml(row?.break_start?.slice(0, 5) || '')}"></div><div class="form-field"><label>Break end</label><input name="break_end" type="time" value="${escapeHtml(row?.break_end?.slice(0, 5) || '')}"></div><label class="form-field"><span>Availability</span><span><input name="is_active" type="checkbox"${!row || Number(row.is_active) === 1 ? ' checked' : ''}> Available</span></label><div class="form-field"><button class="button button-secondary" type="submit">Save ${day}</button></div></div></form>`;
        }).join('')}</div></section>`).join('');
        const dentistOptions = data.dentists.map(dentist => `<option value="${Number(dentist.id)}">${escapeHtml(dentist.full_name)}</option>`).join('');
        const holidays = data.holidays.map(holiday => `<article class="record-card"><div><strong>${formatDate(holiday.holiday_date)}</strong><p class="record-meta">${escapeHtml(holiday.description)} · ${escapeHtml(holiday.full_name || 'Whole clinic')}</p></div>${button('Remove', 'delete-holiday', holiday.id, 'button-danger')}</article>`).join('');
        root.innerHTML = `${pageHeader('Clinic administration', 'Schedules and availability', 'Manage provider working hours, breaks, booking limits, and blocked dates.')}<section class="panel"><div class="panel-title"><div><p class="eyebrow">Booking rules</p><h2>Patient booking limits</h2></div></div><form id="admin-settings-form" class="form-grid"><div class="form-field"><label>Slot interval (minutes)</label><input name="slot_interval_minutes" type="number" min="5" max="120" step="5" value="${Number(data.settings.slot_interval_minutes)}" required></div><div class="form-field"><label>Book up to (days)</label><input name="booking_days_ahead" type="number" min="1" max="365" value="${Number(data.settings.booking_days_ahead)}" required></div><div class="form-field"><label>Minimum notice to change (hours)</label><input name="min_hours_before_change" type="number" min="0" max="72" value="${Number(data.settings.min_hours_before_change)}" required></div><div class="form-field"><button class="button button-primary" type="submit">Save booking rules</button></div></form></section>${grid}<section class="panel"><div class="panel-title"><div><p class="eyebrow">Holidays and leave</p><h2>Blocked dates</h2></div></div><form id="admin-holiday-form" class="form-grid"><div class="form-field"><label>Date</label><input name="holiday_date" type="date" min="${localDate()}" required></div><div class="form-field"><label>Description</label><input name="description" maxlength="150" required></div><div class="form-field"><label>Applies to</label><select name="dentist_id"><option value="">Whole clinic</option>${dentistOptions}</select></div><div class="form-field"><button class="button button-primary" type="submit">Add blocked date</button></div></form><div class="record-list">${holidays || emptyState('No blocked dates have been added.')}</div></section>`;
    }

    async function renderAdminReports() {
        const params = new URLSearchParams(location.hash.split('?')[1] || '');
        const start = params.get('start') || `${localDate().slice(0, 7)}-01`;
        const end = params.get('end') || localDate();
        const data = await request(`${portalEndpoint}?view=admin_reports&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
        const peak = Math.max(1, ...data.daily.map(day => Number(day.appointments)));
        const bars = data.daily.map(day => `<div class="report-bar-row"><time>${formatDate(day.appointment_date)}</time><span class="report-bar-track"><span class="report-bar" style="width:${Math.round(Number(day.appointments) * 100 / peak)}%"></span></span><strong>${Number(day.appointments)}</strong></div>`).join('');
        const services = data.services.map(item => `<article class="record-card"><div><strong>${escapeHtml(item.name)}</strong><p class="record-meta">${Number(item.appointments)} appointments · ${Number(item.completed)} completed</p></div><strong>${escapeHtml(item.revenue)}</strong></article>`).join('');
        const appointments = data.appointments.map(item => `<tr><td>${formatDate(item.appointment_date)} · ${formatTime(item.start_time)}</td><td>${escapeHtml(item.patient_name)}</td><td>${escapeHtml(item.service_name)}</td><td>${escapeHtml(item.dentist_name)}</td><td>${escapeHtml(statusLabel(item.status))}</td></tr>`).join('');
        root.innerHTML = `${pageHeader('Clinic administration', 'Reports and analytics', 'Appointment demand, service activity, and completed-visit revenue.')}<form id="admin-report-filter" class="panel form-grid"><div class="form-field"><label>From</label><input type="date" name="start" value="${escapeHtml(data.start)}" required></div><div class="form-field"><label>Through</label><input type="date" name="end" value="${escapeHtml(data.end)}" required></div><div class="form-field form-actions"><button class="button button-primary" type="submit">Update report</button><a class="button button-secondary" href="api/report-export.php?start=${encodeURIComponent(data.start)}&end=${encodeURIComponent(data.end)}">Export CSV</a><button class="button button-secondary" type="button" data-action="print-report">Print / Save PDF</button></div></form><div class="stats-grid"><article class="stat-card is-highlight"><span>Appointments</span><strong>${Number(data.summary.total)}</strong></article><article class="stat-card"><span>Completed</span><strong>${Number(data.summary.completed)}</strong></article><article class="stat-card"><span>Cancelled</span><strong>${Number(data.summary.cancelled)}</strong></article><article class="stat-card"><span>Average wait</span><strong>${Number(data.summary.average_wait)} min</strong></article></div><div class="content-columns"><section class="panel"><div class="panel-title"><div><p class="eyebrow">Demand</p><h2>Appointments by day</h2></div></div>${bars || emptyState('No appointments in this date range.')}</section><section class="panel"><div class="panel-title"><div><p class="eyebrow">Service mix</p><h2>By treatment</h2></div></div><div class="record-list">${services || emptyState('No service activity in this date range.')}</div></section></div><section class="panel"><div class="panel-title"><div><p class="eyebrow">Appointments</p><h2>Selected period</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Date and time</th><th>Patient</th><th>Service</th><th>Dentist</th><th>Status</th></tr></thead><tbody>${appointments || `<tr><td colspan="5">${emptyState('No appointments in this date range.')}</td></tr>`}</tbody></table></div></section>`;
    }

    async function renderAdminActivity() {
        const params = new URLSearchParams(location.hash.split('?')[1] || '');
        const from = params.get('from') || (() => { const date = new Date(); date.setDate(date.getDate() - 6); return localDate(date); })();
        const to = params.get('to') || localDate();
        const action = params.get('action') || '';
        const data = await request(`${portalEndpoint}?view=admin_activity&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}&action=${encodeURIComponent(action)}`);
        const rows = data.logs.map(log => `<tr><td>${escapeHtml(log.created_at)}</td><td>${escapeHtml(log.full_name || 'System')}<span class="table-subline">${escapeHtml(log.email || '')}</span></td><td>${escapeHtml(log.action)}</td><td>${escapeHtml(log.details)}</td><td>${escapeHtml(log.ip_address)}</td></tr>`).join('');
        root.innerHTML = `${pageHeader('Clinic administration', 'Activity log', 'Review sign-ins, appointment actions, and administrative changes.')}<form id="admin-activity-filter" class="panel form-grid"><div class="form-field"><label>From</label><input type="date" name="from" value="${escapeHtml(data.from)}" required></div><div class="form-field"><label>Through</label><input type="date" name="to" value="${escapeHtml(data.to)}" required></div><div class="form-field"><label>Action</label><input name="action" value="${escapeHtml(data.action)}" maxlength="60" placeholder="Any action"></div><div class="form-field"><button class="button button-primary" type="submit">Filter log</button></div></form><section class="panel"><div class="panel-title"><div><p class="eyebrow">Audit trail</p><h2>${data.logs.length} events</h2></div></div><div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Account</th><th>Action</th><th>Details</th><th>IP address</th></tr></thead><tbody>${rows || `<tr><td colspan="5">${emptyState('No activity found for this filter.')}</td></tr>`}</tbody></table></div></section>`;
    }

    async function renderAdminBackup() {
        root.innerHTML = `${pageHeader('Clinic administration', 'Database backup', 'Download a protected SQL snapshot of clinic data.')}<section class="panel"><p class="eyebrow">Database backup</p><h2>Download a complete SQL snapshot</h2><p class="record-meta">The backup is streamed securely to your browser. Store it in an approved, access-controlled location.</p><a class="button button-primary" href="admin/backup.php?download=1">Download SQL backup</a><p class="form-help">Database credentials are not included in the download URL.</p></section>`;
    }

    async function renderStaffNotifications() {
        const data = await request(`${portalEndpoint}?view=staff_notifications`);
        const notifications = data.notifications.map(item => `<article class="record-card"><div><strong>${escapeHtml(item.subject || item.type)}</strong><p class="record-meta">${escapeHtml(item.full_name)} · ${escapeHtml(item.recipient)} · ${escapeHtml(item.channel)} · ${escapeHtml(item.scheduled_for)}</p><p>${escapeHtml(item.message)}</p><span class="status-pill" data-status="${escapeHtml(item.status)}">${escapeHtml(statusLabel(item.status))}</span></div>${item.status === 'queued' ? button('Mark simulated', 'simulate-notification', item.id, 'button-primary') : ''}</article>`).join('');
        root.innerHTML = `${pageHeader('Clinic operations', 'Notification outbox', 'Review queued delivery records. Simulated delivery does not send email or SMS.')}<section class="panel"><div class="record-list">${notifications || emptyState('The notification outbox is empty.')}</div></section>`;
    }

    async function renderReceipt() {
        const id = new URLSearchParams(location.hash.split('?')[1] || '').get('id') || '';
        const data = await request(`${portalEndpoint}?view=receipt&id=${encodeURIComponent(id)}`);
        const item = data.appointment;
        const details = [
            ['Service', item.service_name],
            ['Dentist', item.dentist_name],
            ['Date and time', `${formatDate(item.appointment_date)}, ${formatTime(item.start_time)}–${formatTime(item.end_time)}`],
            ['Patient', item.patient_name],
            ['Email', item.email],
            ['Service fee', `₱${Number(item.price).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`],
            ['Requested', item.created_at],
            ...(item.cancellation_reason ? [['Cancellation note', item.cancellation_reason]] : []),
        ];
        root.innerHTML = `${pageHeader('Appointment record', `Booking reference #${Number(item.id)}`, 'This is an appointment record, not proof of payment.')}<section class="panel receipt-panel"><div class="panel-title"><div><p class="eyebrow">${escapeHtml(item.clinic_name)}</p><h2>Appointment details</h2></div><span class="status-pill" data-status="${escapeHtml(item.status)}">${escapeHtml(statusLabel(item.status))}</span></div><p class="record-meta">Appointment details for ${escapeHtml(item.patient_name)} · ${formatDate(item.appointment_date)}</p><dl class="receipt-details">${details.map(([label, value]) => `<div><dt>${escapeHtml(label)}</dt><dd>${escapeHtml(value)}</dd></div>`).join('')}</dl><p class="form-help">Pending appointments require clinic confirmation.</p><div class="form-actions"><button class="button button-primary" type="button" data-action="print-report">Print / Save as PDF</button><a class="button button-secondary" href="#appointments">Back to appointments</a></div></section>`;
    }

    async function renderNotifications() {
        const data = await request(`${portalEndpoint}?view=notifications`);
        root.innerHTML = `${pageHeader('Patient portal', 'Alerts and updates', 'Appointment and queue updates from the clinic.')}<section class="panel"><div class="record-list">${data.notifications.length ? data.notifications.map(item => `<article class="notification-row"><h3>${escapeHtml(item.subject || 'Clinic update')}</h3><p>${escapeHtml(item.message)}</p><time>${escapeHtml(item.created_at)}</time></article>`).join('') : emptyState('You do not have any clinic alerts yet.')}</div></section>`;
    }

    async function render() {
        window.clearInterval(queuePoll);
        updateShell();
        const currentRoute = route();
        const publicRoutes = ['home', 'login', 'register', 'forgot-password', 'reset-password', 'verify-email'];
        if (!currentUser && !publicRoutes.includes(currentRoute)) {
            navigate('login');
            return;
        }
        if (currentUser && ['home', 'login', 'register'].includes(currentRoute)) {
            navigate('dashboard');
            return;
        }
        const adminRoutes = ['admin-users', 'admin-schedules', 'admin-reports', 'admin-activity', 'admin-backup'];
        if (adminRoutes.includes(currentRoute) && currentUser.role !== 'admin') {
            navigate('dashboard');
            return;
        }
        if (currentRoute === 'staff-notifications' && !['staff', 'admin'].includes(currentUser.role)) {
            navigate('dashboard');
            return;
        }
        if (currentRoute === 'receipt' && currentUser.role !== 'patient') {
            navigate('dashboard');
            return;
        }
        if (['walk-in', 'notifications', 'book'].includes(currentRoute)) {
            if (currentRoute === 'book' && currentUser.role !== 'patient') {
                navigate('dashboard');
                return;
            }
            if (currentRoute === 'walk-in' && !['staff', 'admin'].includes(currentUser.role)) {
                navigate('dashboard');
                return;
            }
            if (currentRoute === 'notifications' && currentUser.role !== 'patient') {
                navigate('dashboard');
                return;
            }
        }
        if (currentRoute === 'login' || currentRoute === 'register') {
            renderAuth(currentRoute);
            return;
        }
        try {
            switch (currentRoute) {
                case 'home': await renderHome(); break;
                case 'appointments': await renderAppointments(); break;
                case 'queue': await renderQueue(); queuePoll = window.setInterval(() => { if (route() === 'queue') void renderQueue().catch(error => flash(error.message, true)); }, 10000); break;
                case 'book': await renderBook(); break;
                case 'walk-in': await renderWalkIn(); break;
                case 'notifications': await renderNotifications(); break;
                case 'admin-users': await renderAdminUsers(); break;
                case 'admin-schedules': await renderAdminSchedules(); break;
                case 'admin-reports': await renderAdminReports(); break;
                case 'admin-activity': await renderAdminActivity(); break;
                case 'admin-backup': await renderAdminBackup(); break;
                case 'staff-notifications': await renderStaffNotifications(); break;
                case 'receipt': await renderReceipt(); break;
                case 'forgot-password': await renderForgotPassword(); break;
                case 'reset-password': await renderResetPassword(); break;
                case 'verify-email': await renderVerifyEmail(); break;
                case 'dashboard':
                default: await renderDashboard(); break;
            }
        } catch (error) {
            root.innerHTML = `<section class="panel"><p class="eyebrow">Could not load this page</p><h1>We could not load that clinic information.</h1><p class="record-meta">${escapeHtml(error.message)}</p><button class="button button-primary" type="button" data-action="retry">Try again</button></section>`;
            if (error.message.includes('log in')) {
                currentUser = null;
                navigate('login');
            }
        }
    }

    document.addEventListener('click', async event => {
        const routeLink = event.target.closest('[data-route]');
        if (routeLink) {
            event.preventDefault();
            navigate(routeLink.dataset.route);
            return;
        }
        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;
        const action = actionButton.dataset.action;
        try {
            if (action === 'retry') {
                await render();
                return;
            }
            if (action === 'logout') {
                await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'logout' }) });
                currentUser = null;
                await loadSession();
                flash('You are signed out.');
                navigate('home');
                return;
            }
            if (action === 'cancel-appointment') {
                if (!window.confirm('Cancel this appointment request?')) return;
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'cancel', appointment_id: Number(actionButton.dataset.id) }) });
                flash(result.message);
                await renderAppointments();
                return;
            }
            if (action === 'reschedule-appointment') {
                navigate(`book?reschedule=${Number(actionButton.dataset.id)}`);
                if (route() === 'book') await renderBook();
                return;
            }
            if (action === 'appointment-transition') {
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'appointment_transition', appointment_id: Number(actionButton.dataset.id), status: actionButton.dataset.status }) });
                flash(result.message);
                await renderAppointments();
                return;
            }
            if (action === 'queue-check-in') {
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'queue_check_in', appointment_id: Number(actionButton.dataset.id) }) });
                flash(result.message);
                await renderQueue();
                return;
            }
            if (action === 'queue-transition') {
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'queue_transition', queue_entry_id: Number(actionButton.dataset.id), status: actionButton.dataset.status }) });
                flash(result.message);
                await renderQueue();
                return;
            }
            if (action === 'delete-holiday') {
                if (!window.confirm('Remove this blocked date?')) return;
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_holiday_delete', holiday_id: Number(actionButton.dataset.id) }) });
                flash(result.message);
                await renderAdminSchedules();
                return;
            }
            if (action === 'simulate-notification') {
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'notification_simulate', notification_id: Number(actionButton.dataset.id) }) });
                flash(result.message);
                await renderStaffNotifications();
                return;
            }
            if (action === 'print-report') {
                window.print();
            }
        } catch (error) {
            flash(error.message, true);
        }
    });

    document.addEventListener('submit', async event => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        event.preventDefault();
        try {
            if (form.id === 'login-form') {
                const values = new FormData(form);
                const data = await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'login', email: values.get('email'), password: values.get('password') }) });
                currentUser = data.user;
                csrfToken = data.csrf_token;
                flash(`Welcome back, ${currentUser.name}.`);
                navigate('dashboard');
            } else if (form.id === 'forgot-password-form') {
                const values = new FormData(form);
                const result = await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'forgot_password', email: values.get('email') }) });
                root.innerHTML = `${pageHeader('Secure account recovery', 'Check your email')}<section class="panel"><p>${escapeHtml(result.message)}</p><p class="form-help">In this local build, queued recovery notices can be reviewed in the staff notification outbox.</p><a class="button button-secondary" href="#login">Return to sign in</a></section>`;
            } else if (form.id === 'reset-password-form') {
                const values = new FormData(form);
                const result = await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'reset_password', token: form.dataset.token, password: values.get('password'), password_confirmation: values.get('password_confirmation') }) });
                flash(result.message);
                navigate('login');
            } else if (form.id === 'verify-email-form') {
                const result = await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'verify_email', token: form.dataset.token }) });
                flash(result.message);
                navigate('login');
            } else if (form.id === 'register-form') {
                const values = new FormData(form);
                const data = await request(authEndpoint, { method: 'POST', body: JSON.stringify({ action: 'register', full_name: values.get('full_name'), email: values.get('email'), phone: values.get('phone'), password: values.get('password'), password_confirmation: values.get('password_confirmation') }) });
                flash(data.message);
                navigate('login');
            } else if (form.id === 'booking-form') {
                const values = new FormData(form);
                const rescheduleId = Number(form.dataset.rescheduleId || 0);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify(rescheduleId
                    ? { action: 'reschedule', appointment_id: rescheduleId, appointment_date: values.get('appointment_date'), appointment_time: values.get('appointment_time') }
                    : { action: 'book', service_id: Number(values.get('service_id')), dentist_id: Number(values.get('dentist_id')), appointment_date: values.get('appointment_date'), appointment_time: values.get('appointment_time'), patient_notes: values.get('patient_notes') }) });
                flash(`${result.message} Reference #${result.appointment_id}.`);
                navigate('appointments');
            } else if (form.id === 'appointment-filter') {
                const values = new FormData(form);
                const params = new URLSearchParams();
                for (const [key, value] of values) if (value) params.set(key, value);
                navigate(`appointments?${params}`);
                if (route() === 'appointments') await renderAppointments();
            } else if (form.id === 'queue-filter') {
                const category = form.elements.category.value;
                navigate(`queue${category ? `?category=${encodeURIComponent(category)}` : ''}`);
                if (route() === 'queue') await renderQueue();
            } else if (form.id === 'walk-in-form') {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'walk_in', patient_id: Number(values.get('patient_id')), service_id: Number(values.get('service_id')), dentist_id: Number(values.get('dentist_id')), appointment_time: values.get('appointment_time'), patient_notes: values.get('patient_notes') }) });
                flash(`${result.message} Reference #${result.appointment_id}.`);
                navigate('queue');
            } else if (form.id === 'admin-user-create') {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_user_create', full_name: values.get('full_name'), email: values.get('email'), phone: values.get('phone'), role: values.get('role'), password: values.get('password') }) });
                flash(result.message);
                await renderAdminUsers();
            } else if (form.matches('.admin-user-update')) {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_user_update', user_id: Number(form.dataset.userId), role: values.get('role'), is_active: Number(values.get('is_active')) }) });
                flash(result.message);
                await renderAdminUsers();
            } else if (form.matches('.admin-schedule-form')) {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_schedule_save', dentist_id: Number(form.dataset.dentistId), day_of_week: Number(form.dataset.day), start_time: values.get('start_time'), end_time: values.get('end_time'), break_start: values.get('break_start'), break_end: values.get('break_end'), is_active: values.has('is_active') }) });
                flash(result.message);
                await renderAdminSchedules();
            } else if (form.id === 'admin-settings-form') {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_settings_save', slot_interval_minutes: Number(values.get('slot_interval_minutes')), booking_days_ahead: Number(values.get('booking_days_ahead')), min_hours_before_change: Number(values.get('min_hours_before_change')) }) });
                flash(result.message);
                await renderAdminSchedules();
            } else if (form.id === 'admin-holiday-form') {
                const values = new FormData(form);
                const result = await request(portalEndpoint, { method: 'POST', body: JSON.stringify({ action: 'admin_holiday_create', holiday_date: values.get('holiday_date'), description: values.get('description'), dentist_id: values.get('dentist_id') }) });
                flash(result.message);
                await renderAdminSchedules();
            } else if (form.id === 'admin-report-filter' || form.id === 'admin-activity-filter') {
                const values = new FormData(form);
                const query = new URLSearchParams();
                for (const [key, value] of values) if (value) query.set(key, value);
                navigate(`${form.id === 'admin-report-filter' ? 'admin-reports' : 'admin-activity'}?${query}`);
                if (route().startsWith('admin-')) await render();
            }
        } catch (error) {
            flash(error.message, true);
        }
    });

    document.addEventListener('change', event => {
        if (event.target.matches('#booking-service, #booking-dentist, #booking-date')) void loadSlots();
        if (event.target.matches('#walk-in-service, #walk-in-dentist')) void loadWalkInSlots();
    });

    window.addEventListener('hashchange', () => { void render(); });

    request(`${authEndpoint}?action=session`)
        .then(data => {
            csrfToken = data.csrf_token;
            currentUser = data.authenticated ? data.user : null;
            if (!location.hash) location.hash = currentUser ? 'dashboard' : 'home';
            void render();
        })
        .catch(error => {
            root.innerHTML = `<section class="panel"><h1>The clinic could not connect.</h1><p class="record-meta">${escapeHtml(error.message)}</p></section>`;
        });
})();
