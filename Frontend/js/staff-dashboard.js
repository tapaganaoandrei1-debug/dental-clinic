/* staff-dashboard.js - staff dashboard. Needs ops-layout.js (see its API contract).
   Extra API for this page:
   GET staff-dashboard.php -> { ok, stats:{ appointments_today, confirmed, pending, cancelled },
                                schedule:[{ start:"09:00", patient_name, service_name, status }] } */
(function () {
  if (!ops) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  $("today-label").textContent = fmtDate(ymd(new Date()), { weekday: "long", month: "long", day: "numeric", year: "numeric" });
  $("sched-date").textContent = fmtDate(ymd(new Date()), { month: "short", day: "numeric" });

  const SEED_SCHEDULE = [
    { start: "09:00", patient_name: "Ana Reyes", service_name: "Check-up & Cleaning", status: "confirmed" },
    { start: "09:30", patient_name: "Joel Santos", service_name: "Tooth Extraction", status: "confirmed" },
    { start: "10:00", patient_name: "Maria Cruz", service_name: "Check-up & Cleaning", status: "pending" },
    { start: "10:30", patient_name: "Liza Moreno", service_name: "Braces Consultation", status: "pending" },
    { start: "11:00", patient_name: "Ben Torres", service_name: "Teeth Whitening", status: "confirmed" },
  ];

  async function dashboard() {
    if (USE_FAKE_DATA) {
      const today = readFakeAppointments().filter((a) => a.date === ymd(new Date()))
        .map((a) => ({ start: a.start, patient_name: a.patient_name, status: a.status, service_name: FAKE_SERVICES.find((s) => s.id === a.service_id).name }));
      const schedule = [...SEED_SCHEDULE, ...today.filter((a) => a.status !== "cancelled")].sort((a, b) => a.start.localeCompare(b.start));
      return { stats: { appointments_today: schedule.length, confirmed: schedule.filter((a) => a.status === "confirmed").length,
        pending: schedule.filter((a) => a.status === "pending").length, cancelled: today.filter((a) => a.status === "cancelled").length }, schedule };
    }
    const data = await apiGet("staff-dashboard.php");
    if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
    return data;
  }

  function renderQueue(entries) {
    $("queue-rows").replaceChildren(...(entries.length ? entries.map((e) => {
      const tr = make("tr");
      const [label, action] = ops.NEXT[e.status];
      const btn = make("button", e.status === "serving" ? "btn btn-outline-secondary btn-sm" : "btn btn-primary btn-sm", label);
      btn.type = "button";
      btn.addEventListener("click", () => run({ id: e.id, action }));
      const tdAction = make("td"); tdAction.append(btn);
      const tdStatus = make("td"); tdStatus.append(ops.badge(e.status));
      const tdTicket = make("td"); tdTicket.append(make("strong", "ticket-link", ops.ticket(e.queue_number)));
      tr.append(tdTicket, make("td", "", e.patient_name), make("td", "muted-cell", e.service_name), tdStatus, tdAction);
      return tr;
    }) : [(() => { const tr = make("tr"); const td = make("td", "empty-state", "Nobody is in the queue."); td.colSpan = 5; tr.append(td); return tr; })()]));
  }

  function renderSchedule(list) {
    $("schedule").replaceChildren(...list.map((a) => {
      const row = make("div", "sched-row");
      const who = make("div");
      who.append(make("strong", "", a.patient_name), make("span", "", a.service_name));
      row.append(make("time", "", fmtTime(a.start.slice(0, 5))), who, ops.badge(a.status));
      return row;
    }));
  }

  async function run(payload) {
    $("form-alert").hidden = true;
    try {
      const r = await ops.act(payload);
      if (!r.ok) showAlert($("form-alert"), r.error || "That action could not be completed.");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
    refresh();
  }

  async function refresh() {
    try {
      const [q, d] = await Promise.all([ops.queue(), dashboard()]);
      $("st-appts").textContent = d.stats.appointments_today;
      $("st-appts-sub").textContent = `${d.stats.confirmed} confirmed - ${d.stats.pending} pending`;
      $("st-queue").textContent = q.stats.in_queue;
      $("st-serving").textContent = q.stats.now_serving ? `Now serving ${ops.ticket(q.stats.now_serving)}` : "No one in service";
      $("st-wait").textContent = `${q.stats.avg_wait_minutes} min`;
      $("st-noshow").textContent = q.stats.no_shows;
      $("st-cancel").textContent = `${d.stats.cancelled} cancelled`;
      renderQueue(q.entries);
      renderSchedule(d.schedule);
    } catch (e) { showAlert($("form-alert"), e.message || NETWORK_MSG); }
  }

  $("call-next").addEventListener("click", () => run({ action: "call_next" }));
  refresh();
  setInterval(refresh, 10000);
})();