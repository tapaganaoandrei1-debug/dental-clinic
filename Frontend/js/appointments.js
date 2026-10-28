/* ==========================================================
   appointments.js - My appointments page.
   Load order: config, fake-data, auth, layout.

   API CONTRACT (real mode):
   GET  my-appointments.php -> { ok,
          appointments:[{ id, date:"2026-10-15", start:"10:00", end:"10:30", status, service_name,
                          dentist_name, price, notes, cancellation_reason, can_change }],
          notifications:[{ subject, message, created_at }] }   (latest 3)
        status: pending | confirmed | completed | cancelled | no_show
        can_change: true only for pending/confirmed visits more than 2 hours away
   POST cancel-appointment.php { appointment_id, reason } -> { ok:true } or { ok:false, error }
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
  const link = (label, href, cls) => { const a = make("a", cls, label); a.href = href; return a; };
  const STATUS = { pending: "Pending", confirmed: "Confirmed", completed: "Completed", cancelled: "Cancelled", no_show: "No-show" };
  const state = { appointments: [], notifications: [], filter: "all" };
  const today = ymd(new Date());

  async function load() {
    if (USE_FAKE_DATA) {
      state.appointments = readFakeAppointments().filter((a) => a.patient_email === user.email).map((a) => {
        const service = FAKE_SERVICES.find((s) => s.id === a.service_id);
        const dentist = FAKE_DENTISTS.find((d) => d.id === a.dentist_id);
        return { ...a, service_name: service.name, price: service.price, dentist_name: dentist.name };
      });
      state.notifications = readFakeNotifications().filter((n) => n.email === user.email).slice(0, 3);
    } else {
      const data = await apiGet("my-appointments.php");
      if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
      state.appointments = data.appointments;
      state.notifications = data.notifications || [];
    }
    state.appointments.sort((a, b) => (b.date + b.start).localeCompare(a.date + a.start));
  }

  const isUpcoming = (a) => a.date >= today && ["pending", "confirmed"].includes(a.status);
  const badge = (status) => make("span", `status status-${status.replace("_", "-")}`, STATUS[status] || status);
  const when = (a) => `${fmtDate(a.date, { weekday: "short", month: "short", day: "numeric", year: "numeric" })} - ${fmtTime(a.start)}`;

  function actions(a) {
    const box = make("div", "visit-actions");
    box.append(link("Receipt", `receipt.html?id=${a.id}`, "small-link"));
    if (canChangeAppointment(a)) {
      box.append(link("Reschedule", `book.html?reschedule=${a.id}`, "small-link"));
      const cancel = make("button", "small-link danger-link", "Cancel");
      cancel.type = "button";
      cancel.addEventListener("click", () => cancelAppointment(a));
      box.append(cancel);
    }
    return box;
  }

  async function cancelAppointment(a) {
    if (!window.confirm(`Cancel your ${a.service_name} appointment on ${fmtDate(a.date, { month: "short", day: "numeric" })}?`)) return;
    const alertBox = $("form-alert");
    alertBox.hidden = true;
    try {
      const result = USE_FAKE_DATA ? await fakeCancel(a.id, "", user) : await apiPost("cancel-appointment.php", { appointment_id: a.id, reason: "" });
      if (!result.ok) return showAlert(alertBox, result.error || "We could not cancel that appointment. Please try again.");
      showAlert(alertBox, "Your appointment was cancelled.", "success");
      await load();
      render();
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
  }

  function renderNext() {
    const box = $("next-visit");
    const next = [...state.appointments].filter(isUpcoming).sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start))[0];
    box.hidden = !next;
    if (!next) return;
    const info = make("div");
    info.append(make("p", "next-label", "Your next appointment"), make("h2", "", when(next)),
      make("p", "next-meta", `${next.service_name} with ${next.dentist_name}`));
    const side = make("div", "next-side");
    side.append(badge(next.status), actions(next));
    if (!canChangeAppointment(next)) side.append(make("p", "field-hint", "Changes close 2 hours before the visit. Please call the clinic."));
    box.replaceChildren(info, side);
  }

  function renderSupport() {
    const updates = $("updates");
    updates.replaceChildren(...(state.notifications.length
      ? state.notifications.map((n) => {
        const row = make("div", "update-row");
        row.append(make("strong", "", n.subject), make("span", "", n.message));
        return row;
      })
      : [make("p", "empty-state", "No updates yet.")]));

    const next = state.appointments.filter(isUpcoming).sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start))[0];
    const cal = $("calendar-sync");
    if (!next) return cal.replaceChildren(make("p", "empty-state", "Book a visit to add it to your calendar."));
    const stamp = (date, t) => `${date.replaceAll("-", "")}T${t.replace(":", "")}00`;
    const title = `${next.service_name} - ${next.dentist_name}`;
    const google = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${stamp(next.date, next.start)}/${stamp(next.date, next.end)}&ctz=Asia%2FManila`;
    const outlook = `https://outlook.live.com/calendar/0/deeplink/compose?subject=${encodeURIComponent(title)}&startdt=${encodeURIComponent(`${next.date}T${next.start}:00+08:00`)}&enddt=${encodeURIComponent(`${next.date}T${next.end}:00+08:00`)}`;
    const row = make("div", "calendar-actions");
    [["Google Calendar", google], ["Outlook", outlook]].forEach(([label, href]) => {
      const a = link(label, href, "btn btn-outline-secondary btn-sm");
      a.target = "_blank"; a.rel = "noopener noreferrer";
      row.append(a);
    });
    cal.replaceChildren(make("p", "field-hint mt-0", `${when(next)}`), row);
  }

  function renderList() {
    const shown = state.appointments.filter((a) => state.filter === "all" || (state.filter === "upcoming" ? isUpcoming(a) : !isUpcoming(a)));
    $("visit-count").textContent = `${state.appointments.length} total`;
    if (!shown.length) {
      const empty = make("div", "empty-state");
      empty.append(make("p", "", state.appointments.length ? "Nothing in this view." : "No appointments in your history yet."));
      if (!state.appointments.length) empty.append(link("Choose a service to request your first visit", "book.html"));
      return $("visit-list").replaceChildren(empty);
    }
    $("visit-list").replaceChildren(...shown.map((a) => {
      const item = make("article", "visit-item");
      const date = make("div", "visit-date");
      date.append(make("strong", "", String(parseYmd(a.date).getDate())), make("span", "", fmtDate(a.date, { month: "short" })));
      const main = make("div", "visit-main");
      const title = make("div", "visit-title");
      title.append(make("h3", "", a.service_name), badge(a.status));
      main.append(title, make("p", "", `${fmtTime(a.start)} - ${a.dentist_name} - ${formatMoney(a.price)}`));
      if (a.status === "cancelled" && a.cancellation_reason) main.append(make("p", "field-hint", `Reason: ${a.cancellation_reason}`));
      item.append(date, main, actions(a));
      return item;
    }));
  }

  function render() { renderNext(); renderSupport(); renderList(); }

  document.querySelectorAll(".tab").forEach((tab) => tab.addEventListener("click", () => {
    state.filter = tab.dataset.filter;
    document.querySelectorAll(".tab").forEach((t) => t.setAttribute("aria-pressed", String(t === tab)));
    renderList();
  }));

  load().then(render).catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();
