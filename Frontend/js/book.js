/* ==========================================================
   book.js - booking page. Load order: config, fake-data, auth, layout.

   API CONTRACT (real mode, JSON, cookies sent):
   GET  services.php        -> { ok, services:[{ id, name, category, duration_minutes, price }] }  (active only)
   GET  dentists.php        -> { ok, dentists:[{ id, name, specialty, service_ids:[1,5] }] }
   GET  availability.php?service_id=1&dentist_id=0&date=2026-10-15
                            -> { ok, slots:[{ time:"09:00", available:true }, { time:"09:30", available:false }] }
        (dentist_id 0 = any available. Please return booked times too, with available:false,
         so the page can show them disabled. Plain strings "09:00" are also accepted.)
   GET  queue-summary.php   -> { ok, now_serving:"Q-009", people_ahead:4, estimated_wait_minutes:25, next_queue_number:"Q-014" }
   POST book-appointment.php { service_id, dentist_id, date, time, notes }
                            -> { ok:true, appointment_id } or { ok:false, error:"..." }
   ========================================================== */
(function () {
  const user = requireLogin(["patient"]);
  if (!user) return;

  const $ = (id) => document.getElementById(id);
  const make = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  const rescheduleId = Number(new URLSearchParams(location.search).get("reschedule")) || null;
  const state = { services: [], dentists: [], serviceId: null, dentistId: 0, date: null, time: null, month: null, slots: [] };
  const today = ymd(new Date());
  const lastDay = ymd(new Date(Date.now() + BOOKING_DAYS_AHEAD * 86400000));

  async function get(endpoint) {
    const data = await apiGet(endpoint);
    if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
    return data;
  }

  const api = {
    services: async () => (USE_FAKE_DATA ? FAKE_SERVICES.filter((s) => s.is_active) : (await get("services.php")).services),
    dentists: async () => (USE_FAKE_DATA
      ? FAKE_DENTISTS.map((d) => ({ ...d, service_ids: FAKE_DENTIST_SERVICES[d.id] }))
      : (await get("dentists.php")).dentists),
    queue: async () => (USE_FAKE_DATA ? FAKE_QUEUE_SUMMARY : get("queue-summary.php")),
    async slots() {
      if (USE_FAKE_DATA) {
        await delay(150);
        return fakeSlots(state.serviceId, state.dentistId, state.date, rescheduleId);
      }
      const params = new URLSearchParams({ service_id: state.serviceId, dentist_id: state.dentistId, date: state.date });
      const data = await get(`availability.php?${params}`);
      return data.slots.map((s) => (typeof s === "string" ? { time: s.slice(0, 5), available: true } : { ...s, time: s.time.slice(0, 5) }));
    },
    async appointment(id) {
      if (USE_FAKE_DATA) return readFakeAppointments().find((a) => a.id === id && a.patient_email === user.email);
      return (await get(`appointment.php?id=${id}`)).appointment;
    },
    reschedule: (payload) => (USE_FAKE_DATA ? fakeReschedule(payload, user) : apiPost("reschedule-appointment.php", payload)),
    book: (payload) => (USE_FAKE_DATA ? fakeBook(payload, user) : apiPost("book-appointment.php", payload)),
  };

  const service = () => state.services.find((s) => s.id === state.serviceId);
  const dentist = () => state.dentists.find((d) => d.id === state.dentistId);

  /* ----- Step 1: services and dentist ----- */
  function renderServices() {
    $("service-list").replaceChildren(...state.services.map((s) => {
      const b = make("button", "choice-card");
      b.type = "button";
      b.setAttribute("aria-pressed", String(s.id === state.serviceId));
      b.append(make("strong", "", s.name), make("span", "", s.category || "Dental care"),
        make("small", "", `${s.duration_minutes} min - ${formatMoney(s.price)}`));
      b.addEventListener("click", () => { state.serviceId = s.id; state.dentistId = 0; state.time = null; renderServices(); renderDentists(); renderSummary(); loadSlots(); });
      return b;
    }));
  }

  function renderDentists() {
    const select = $("dentist");
    const eligible = state.dentists.filter((d) => d.service_ids.includes(state.serviceId));
    select.replaceChildren(new Option("Any available", "0"), ...eligible.map((d) => new Option(`${d.name} (${d.specialty})`, String(d.id))));
    select.value = String(state.dentistId);
  }

  /* ----- Step 2: calendar and slots ----- */
  function renderCalendar() {
    const first = state.month;
    $("month-label").textContent = new Intl.DateTimeFormat("en-US", { month: "long", year: "numeric" }).format(first);
    const cells = [];
    for (let i = 0; i < first.getDay(); i++) cells.push(make("span"));
    const days = new Date(first.getFullYear(), first.getMonth() + 1, 0).getDate();
    for (let d = 1; d <= days; d++) {
      const date = ymd(new Date(first.getFullYear(), first.getMonth(), d));
      const b = make("button", "cal-day" + (date === state.date ? " is-selected" : ""), String(d));
      b.type = "button";
      b.disabled = date < today || date > lastDay;
      b.setAttribute("aria-pressed", String(date === state.date));
      b.addEventListener("click", () => { state.date = date; state.time = null; renderCalendar(); renderSummary(); loadSlots(); });
      cells.push(b);
    }
    $("calendar-days").replaceChildren(...cells);
    $("prev-month").disabled = first <= parseYmd(today.slice(0, 8) + "01");
    $("next-month").disabled = ymd(new Date(first.getFullYear(), first.getMonth() + 1, 1)) > lastDay;
  }

  function slotMessage(text) {
    $("slot-grid").replaceChildren(make("p", "slot-hint", text));
  }

  function renderSlots() {
    if (!state.slots.length) return slotMessage("No times available. Try another day.");
    $("slot-grid").replaceChildren(...state.slots.map((s) => {
      const b = make("button", "slot" + (s.time === state.time ? " is-selected" : ""), fmtTime(s.time));
      b.type = "button";
      b.disabled = !s.available;
      b.setAttribute("aria-pressed", String(s.time === state.time));
      b.addEventListener("click", () => { state.time = s.time; renderSummary(); renderSlots(); });
      return b;
    }));
  }

  async function loadSlots() {
    $("slots-title").textContent = state.date
      ? `Available time slots - ${fmtDate(state.date, { weekday: "short", month: "short", day: "numeric" })}`
      : "Available time slots";
    updateConfirm();
    if (!state.serviceId || !state.date) return slotMessage("Choose a service and a date first.");
    slotMessage("Checking open times...");
    const asked = `${state.serviceId}|${state.dentistId}|${state.date}`;
    try {
      const slots = await api.slots();
      if (asked !== `${state.serviceId}|${state.dentistId}|${state.date}`) return; // choice changed meanwhile
      state.slots = slots;
      renderSlots();
    } catch (e) {
      slotMessage(e.message || "Availability could not be loaded. Try again.");
    }
  }

  /* ----- Summary, ticket, confirm ----- */
  function renderSummary() {
    $("sum-service").textContent = service() ? service().name : "-";
    $("sum-date").textContent = state.date ? fmtDate(state.date, { weekday: "short", month: "short", day: "numeric", year: "numeric" }) : "Choose a date";
    $("sum-time").textContent = state.time ? fmtTime(state.time) : "Choose a time";
    $("sum-dentist").textContent = dentist() ? dentist().name : "Any available";
    updateConfirm();
  }

  function updateConfirm() {
    $("confirm-btn").disabled = !(state.serviceId && state.date && state.time);
  }

  async function loadTicket() {
    try {
      const q = await api.queue();
      $("ticket-number").textContent = q.next_queue_number;
      $("now-serving").textContent = q.now_serving;
      $("people-ahead").textContent = q.people_ahead;
      $("est-wait").textContent = `~${q.estimated_wait_minutes} min`;
    } catch { /* the ticket panel just stays empty */ }
  }

  async function confirm() {
    const alertBox = $("form-alert"), btn = $("confirm-btn");
    alertBox.hidden = true;
    setLoading(btn, true, rescheduleId ? "Confirm new time" : "Confirm booking", "Saving...");
    try {
      const payload = { service_id: state.serviceId, dentist_id: state.dentistId, date: state.date, time: state.time, notes: $("notes").value.trim() };
      const result = rescheduleId ? await api.reschedule({ ...payload, appointment_id: rescheduleId }) : await api.book(payload);
      if (result.ok && rescheduleId) {
        showAlert(alertBox, "Your appointment was rescheduled. Taking you back to your appointments...", "success");
        return setTimeout(() => { window.location.href = "appointments.html"; }, 1200);
      }
      if (result.ok) {
        showAlert(alertBox, "Your appointment request was sent. The clinic will confirm it shortly.", "success");
        state.time = null; $("notes").value = "";
        renderSummary(); loadSlots(); loadTicket();
      } else {
        showAlert(alertBox, result.error || "We could not place that appointment request. Please try again.");
        state.time = null; renderSummary(); loadSlots();
      }
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
    setLoading(btn, false, rescheduleId ? "Confirm new time" : "Confirm booking");
    updateConfirm();
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  /* ----- Start ----- */
  $("dentist").addEventListener("change", (e) => { state.dentistId = Number(e.target.value); state.time = null; renderSummary(); loadSlots(); });
  $("prev-month").addEventListener("click", () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() - 1, 1); renderCalendar(); });
  $("next-month").addEventListener("click", () => { state.month = new Date(state.month.getFullYear(), state.month.getMonth() + 1, 1); renderCalendar(); });
  $("cancel-btn").addEventListener("click", () => { state.date = null; state.time = null; $("notes").value = ""; $("form-alert").hidden = true; renderCalendar(); renderSummary(); loadSlots(); });
  $("confirm-btn").addEventListener("click", confirm);

  (async function init() {
    try {
      [state.services, state.dentists] = await Promise.all([api.services(), api.dentists()]);
    } catch (e) {
      return showAlert($("form-alert"), e.message || NETWORK_MSG);
    }
    if (rescheduleId) {
      const current = await api.appointment(rescheduleId).catch(() => null);
      if (!current || !canChangeAppointment(current)) {
        return showAlert($("form-alert"), "This appointment can't be changed online. Please call the clinic or go back to your appointments.");
      }
      state.rescheduleFrom = current;
      state.dentistId = current.dentist_id || 0;
      $("page-title").textContent = "Reschedule your appointment";
      $("page-sub").textContent = "Pick a new date and time. Your service stays the same.";
      $("confirm-btn").textContent = "Confirm new time";
      $("notes").value = current.notes || "";
      $("service-title").textContent = "1. Your service";
      $("service-list").hidden = true;
    }
    const wanted = rescheduleId ? state.rescheduleFrom.service_id : Number(new URLSearchParams(location.search).get("service_id"));
    state.serviceId = (state.services.find((s) => s.id === wanted) || state.services[0] || {}).id || null;
    state.date = ymd(new Date(Date.now() + 86400000)); // tomorrow, as in the PHP version
    state.month = parseYmd(state.date.slice(0, 8) + "01");
    renderServices(); renderDentists(); renderCalendar(); renderSummary(); loadSlots(); loadTicket();
  })();
})();
