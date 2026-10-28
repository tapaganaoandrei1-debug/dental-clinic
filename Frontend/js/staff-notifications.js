/* ==========================================================
   staff-notifications.js - notification log. Needs ops-layout.js.
   GET  staff-notifications.php -> { ok, notifications:[{ id, recipient, type, subject, message, status:"sent|failed|queued", created_at }] }
   POST send-reminders.php      -> { ok:true, sent:3 }   (reminders for tomorrow's pending/confirmed appointments)
   ========================================================== */
(function () {
  if (!ops) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  const TYPES = { confirmation: "Confirmation", reminder: "Reminder", reschedule: "Rescheduled", cancellation: "Cancelled" };

  async function load() {
    if (USE_FAKE_DATA) return fakeStaffNotifications().notifications;
    const d = await apiGet("staff-notifications.php");
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d.notifications;
  }

  function render(list) {
    $("rows").replaceChildren(...(list.length ? list.map((n) => {
      const tr = make("tr");
      const type = make("td"); type.append(make("span", "status status-waiting", TYPES[n.type] || n.type));
      const subject = make("td"); subject.append(make("strong", "", n.subject), make("span", "cell-sub", n.message));
      const st = make("td"); st.append(make("span", `status ${n.status === "failed" ? "status-cancelled" : "status-confirmed"}`, n.status));
      tr.append(make("td", "", new Date(n.created_at).toLocaleString("en-US", { month: "short", day: "numeric", hour: "numeric", minute: "2-digit" })),
        make("td", "muted-cell", n.recipient), type, subject, st);
      return tr;
    }) : [(() => { const tr = make("tr"); const td = make("td", "empty-state", "No notifications yet."); td.colSpan = 5; tr.append(td); return tr; })()]));
  }

  $("remind-btn").addEventListener("click", async () => {
    $("form-alert").hidden = true;
    try {
      const r = USE_FAKE_DATA ? fakeSendReminders() : await apiPost("send-reminders.php", {});
      showAlert($("form-alert"), r.ok ? `${r.sent} reminder${r.sent === 1 ? "" : "s"} sent.` : (r.error || "Reminders could not be sent."), r.ok ? "success" : "danger");
      render(await load());
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
  });
  load().then(render).catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();
