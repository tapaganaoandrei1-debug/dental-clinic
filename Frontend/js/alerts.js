/* ==========================================================
   alerts.js - notification history and calendar links.
   Load order: config, fake-data, auth, layout.

   API CONTRACT (real mode):
   GET alerts.php -> { ok,
        notifications:[{ id, type:"confirmation|reminder|reschedule|cancellation", subject, message, created_at }],   (latest 50)
        next_appointment:{ id, date, start, end, service_name, dentist_name } | null }
   Optional: GET calendar.php?id=ID returns a .ics file (the "Download calendar file" link).
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
  const TYPES = {
    confirmation: ["Confirmation", "status-confirmed"],
    reminder: ["Reminder", "status-pending"],
    reschedule: ["Rescheduled", "status-in-service"],
    cancellation: ["Cancelled", "status-cancelled"],
  };

  async function load() {
    if (USE_FAKE_DATA) {
      const next = readFakeAppointments()
        .filter((a) => a.patient_email === user.email && ["pending", "confirmed"].includes(a.status) && a.date >= ymd(new Date()))
        .sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start))[0];
      return {
        notifications: readFakeNotifications().filter((n) => n.email === user.email).slice(0, 50),
        next_appointment: next && {
          id: next.id, date: next.date, start: next.start, end: next.end,
          service_name: FAKE_SERVICES.find((s) => s.id === next.service_id).name,
          dentist_name: FAKE_DENTISTS.find((d) => d.id === next.dentist_id).name,
        },
      };
    }
    const data = await apiGet("alerts.php");
    if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
    return data;
  }

  function renderList(list) {
    $("alert-count").textContent = `${list.length} update${list.length === 1 ? "" : "s"}`;
    if (!list.length) {
      const empty = make("div", "empty-state");
      empty.append(make("p", "", "No appointment alerts yet."), make("span", "field-hint", "Booking confirmations and reminders will show here."));
      return $("alert-list").replaceChildren(empty);
    }
    $("alert-list").replaceChildren(...list.map((n) => {
      const [label, cls] = TYPES[n.type] || ["Update", "status-waiting"];
      const row = make("article", "alert-item");
      const title = make("div", "visit-title");
      title.append(make("h3", "", n.subject || label), make("span", `status ${cls}`, label));
      const when = new Date(n.created_at);
      row.append(title, make("p", "", n.message || ""),
        make("small", "", when.toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" })));
      return row;
    }));
  }

  function renderCalendar(next) {
    const box = $("calendar-sync");
    if (!next) return box.replaceChildren(make("p", "empty-state", "Book a visit to add it to your calendar."));
    const stamp = (date, t) => `${date.replaceAll("-", "")}T${t.slice(0, 5).replace(":", "")}00`;
    const title = `${next.service_name} - ${next.dentist_name}`;
    const google = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(title)}&dates=${stamp(next.date, next.start)}/${stamp(next.date, next.end)}&ctz=Asia%2FManila&details=${encodeURIComponent("Dental Clinic appointment")}`;
    const outlook = `https://outlook.live.com/calendar/0/deeplink/compose?subject=${encodeURIComponent(title)}&startdt=${encodeURIComponent(`${next.date}T${next.start.slice(0, 5)}:00+08:00`)}&enddt=${encodeURIComponent(`${next.date}T${next.end.slice(0, 5)}:00+08:00`)}`;
    const row = make("div", "calendar-actions");
    [["Google Calendar", google], ["Outlook", outlook]].forEach(([label, href]) => {
      const a = make("a", "btn btn-outline-secondary btn-sm", label);
      a.href = href; a.target = "_blank"; a.rel = "noopener noreferrer";
      row.append(a);
    });
    const nodes = [make("p", "next-meta", `${fmtDate(next.date, { weekday: "short", month: "short", day: "numeric" })} - ${fmtTime(next.start.slice(0, 5))}`),
      make("p", "field-hint mt-0", title), row];
    if (!USE_FAKE_DATA) {
      const ics = make("a", "small-link", "Download calendar file (.ics)");
      ics.href = `${API_URL}/calendar.php?id=${next.id}`;
      nodes.push(ics);
    }
    box.replaceChildren(...nodes);
  }

  load().then((data) => { renderList(data.notifications); renderCalendar(data.next_appointment); })
    .catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();