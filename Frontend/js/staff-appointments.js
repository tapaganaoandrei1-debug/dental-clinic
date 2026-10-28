/* ==========================================================
   staff-appointments.js - all appointments. Needs ops-layout.js.
   GET  staff-appointments.php -> { ok, appointments:[{ id, date, start, end, status, patient_name, phone,
                                    service_name, dentist_name, notes }] }   (latest 200; the page filters in the browser)
   POST appointment-action.php { id, action:"confirm|cancel|complete|no_show" } -> { ok } or { ok:false, error }
   ========================================================== */
(function () {
  if (!ops) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  const ACTIONS = { pending: [["Confirm", "confirm"], ["Cancel", "cancel"]], confirmed: [["Complete", "complete"], ["No-show", "no_show"], ["Cancel", "cancel"]] };
  const LABELS = { pending: "Pending", confirmed: "Confirmed", completed: "Completed", cancelled: "Cancelled", no_show: "No-show" };
  let all = [];

  async function load() {
    if (USE_FAKE_DATA) return fakeStaffAppointments().appointments;
    const d = await apiGet("staff-appointments.php");
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d.appointments;
  }

  async function run(a, action) {
    if (action === "cancel" && !window.confirm(`Cancel ${a.patient_name}'s appointment?`)) return;
    $("form-alert").hidden = true;
    try {
      const r = USE_FAKE_DATA ? fakeAppointmentAction({ id: a.id, action }) : await apiPost("appointment-action.php", { id: a.id, action });
      if (!r.ok) showAlert($("form-alert"), r.error || "That action could not be completed.");
      all = await load();
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
    render();
  }

  function render() {
    const q = $("f-search").value.trim().toLowerCase(), st = $("f-status").value, d = $("f-date").value;
    const list = all.filter((a) => (!st || a.status === st) && (!d || a.date === d) && (!q || `${a.patient_name} ${a.service_name}`.toLowerCase().includes(q)));
    $("row-count").textContent = `${list.length} of ${all.length} appointments`;
    $("rows").replaceChildren(...(list.length ? list.map((a) => {
      const tr = make("tr");
      const status = make("td"); status.append(make("span", `status status-${a.status.replace("_", "-")}`, LABELS[a.status] || a.status));
      const acts = make("td", "row-actions");
      (ACTIONS[a.status] || []).forEach(([label, action]) => {
        const b = make("button", action === "cancel" ? "small-link danger-link" : "small-link", label);
        b.type = "button"; b.addEventListener("click", () => run(a, action)); acts.append(b);
      });
      if (!acts.children.length) acts.textContent = "-";
      const who = make("td"); who.append(make("strong", "", a.patient_name));
      if (a.phone) who.append(make("span", "cell-sub", a.phone));
      tr.append(make("td", "", fmtDate(a.date, { month: "short", day: "numeric", year: "numeric" })), make("td", "", fmtTime(a.start.slice(0, 5))),
        who, make("td", "", a.service_name), make("td", "muted-cell", a.dentist_name), status, acts);
      return tr;
    }) : [(() => { const tr = make("tr"); const td = make("td", "empty-state", "No appointments match."); td.colSpan = 7; tr.append(td); return tr; })()]));
  }

  ["f-search", "f-status", "f-date"].forEach((id) => $(id).addEventListener("input", render));
  load().then((l) => { all = l; render(); }).catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();
