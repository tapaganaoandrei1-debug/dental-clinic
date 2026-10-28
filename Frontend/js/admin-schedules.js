/* ==========================================================
   admin-schedules.js - dentist working days and hours (admin only). Needs ops-layout.js.
   GET  admin-schedules.php -> { ok, dentists:[{ id, name, specialty, days:[1,2,3,4,5,6], start:"09:00", end:"17:00" }] }   (0 = Sunday)
   POST admin-schedules.php { dentist_id, days:[...], start, end } -> { ok } or { ok:false, error }
   ========================================================== */
(function () {
  if (!ops || !ops.requireAdmin()) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
  $("lunch-note").textContent = `Lunch break ${fmtTime(LUNCH_BREAK.start)} - ${fmtTime(LUNCH_BREAK.end)} applies to everyone. Slots are ${SLOT_INTERVAL} minutes.`;

  async function load() {
    if (USE_FAKE_DATA) return FAKE_DENTISTS.map((d) => ({ ...d, ...getSchedules()[d.id] }));
    const d = await apiGet("admin-schedules.php");
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d.dentists;
  }

  async function save(d, days, start, end) {
    if (!days.length) return showAlert($("form-alert"), `Choose at least one working day for ${d.name}.`);
    if (toMin(end) <= toMin(start)) return showAlert($("form-alert"), "The end time must be after the start time.");
    try {
      let r;
      if (USE_FAKE_DATA) {
        localStorage.setItem(SCHEDULE_KEY, JSON.stringify({ ...getSchedules(), [d.id]: { days, start, end } }));
        r = { ok: true };
      } else r = await apiPost("admin-schedules.php", { dentist_id: d.id, days, start, end });
      showAlert($("form-alert"), r.ok ? `Saved ${d.name}'s schedule.` : (r.error || "The schedule could not be saved."), r.ok ? "success" : "danger");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
  }

  load().then((list) => $("cards").replaceChildren(...list.map((d) => {
    const card = make("section", "card-surface booking-card");
    card.append(make("h2", "", d.name), make("p", "field-hint mt-0", d.specialty));
    const chips = make("div", "day-chips");
    const boxes = DAYS.map((label, i) => {
      const l = make("label", "day-chip"); const c = make("input"); c.type = "checkbox"; c.checked = d.days.includes(i);
      l.append(c, make("span", "", label)); chips.append(l); return c;
    });
    const times = make("div", "time-row");
    const start = make("input", "form-control"); start.type = "time"; start.value = d.start.slice(0, 5); start.setAttribute("aria-label", "Start time");
    const end = make("input", "form-control"); end.type = "time"; end.value = d.end.slice(0, 5); end.setAttribute("aria-label", "End time");
    const btn = make("button", "btn btn-primary btn-sm", "Save schedule"); btn.type = "button";
    btn.addEventListener("click", () => save(d, boxes.map((c, i) => (c.checked ? i : -1)).filter((i) => i >= 0), start.value, end.value));
    times.append(start, make("span", "", "to"), end);
    card.append(chips, times, btn);
    return card;
  }))).catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();
