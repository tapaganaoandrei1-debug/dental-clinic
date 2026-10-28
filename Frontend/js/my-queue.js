/* ==========================================================
   my-queue.js - Live queue page (refreshes every 15 seconds).
   Load order: config, fake-data, auth, layout.

   API CONTRACT (real mode):
   GET queue-status.php -> { ok,
        entries:[{ id, queue_number:14, status:"waiting|called|serving", start_time:"10:00:00",
                   service_name, dentist_name, ahead_count, wait_estimate, now_serving:9 | null }],
        notifications:[{ subject, message, created_at }] }      (patient's active entries for today, latest 3 notices)
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
  const ordinal = (n) => `${n}${n % 100 >= 11 && n % 100 <= 13 ? "th" : ({ 1: "st", 2: "nd", 3: "rd" }[n % 10] || "th")}`;
  const MESSAGES = {
    waiting: "You are in the queue. This page updates by itself.",
    called: "You are next. Please proceed to the clinic.",
    serving: "It is your turn. The dentist is ready for you.",
  };
  let lastAlertKey = "";

  async function fetchQueue() {
    if (USE_FAKE_DATA) {
      return { entries: fakeQueueEntries(user), notifications: readFakeNotifications().filter((n) => n.email === user.email).slice(0, 3) };
    }
    const data = await apiGet("queue-status.php");
    if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
    return data;
  }

  function renderUpdates(list) {
    $("updates").replaceChildren(...(list.length ? list.map((n) => {
      const row = make("div", "update-row");
      row.append(make("strong", "", n.subject), make("span", "", n.message));
      return row;
    }) : [make("p", "empty-state", "No updates yet.")]));
  }

  function notify(entry) {
    const ahead = Number(entry.ahead_count);
    if (!$("alert-toggle").checked || ahead > 2 || !("Notification" in window) || Notification.permission !== "granted") return;
    const key = `${entry.id}-${ahead}-${entry.status}`;
    if (key === lastAlertKey) return;
    lastAlertKey = key;
    new Notification("Dental Clinic Online", { body: ahead === 0 ? "You are next. Please proceed to the clinic." : `You are ${ahead} patient${ahead === 1 ? "" : "s"} away.` });
  }

  function renderEntry(entry) {
    const ahead = Number(entry.ahead_count);
    $("token-card").hidden = false;
    $("no-token").hidden = true;
    $("token-number").textContent = formatQueueNumber(entry.queue_number);
    $("token-service").textContent = `${entry.service_name} - Today, ${fmtTime(entry.start_time.slice(0, 5))}`;
    $("queue-message").textContent = MESSAGES[entry.status] || MESSAGES.waiting;
    $("stat-position").textContent = ordinal(ahead + 1);
    $("stat-ahead").textContent = String(ahead);
    $("stat-wait").textContent = `${entry.wait_estimate} min`;
    $("stat-serving").textContent = entry.now_serving ? formatQueueNumber(entry.now_serving) : "No one yet";
    $("progress-bar").style.width = `${Math.max(10, Math.min(100, Math.round(100 / (ahead + 1))))}%`;
    notify(entry);
  }

  async function showUpcoming() {
    const box = $("upcoming-info");
    if (!USE_FAKE_DATA) return box.replaceChildren();
    const next = readFakeAppointments().filter((a) => a.patient_email === user.email && ["pending", "confirmed"].includes(a.status) && a.date >= ymd(new Date()))
      .sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start))[0];
    box.replaceChildren(next ? make("p", "field-hint", `Next visit: ${fmtDate(next.date, { weekday: "short", month: "short", day: "numeric" })} at ${fmtTime(next.start)}`) : make("span"));
  }

  async function refresh() {
    try {
      const data = await fetchQueue();
      renderUpdates(data.notifications || []);
      if (data.entries.length) {
        renderEntry(data.entries[0]);
        $("refresh-state").textContent = "Updated just now";
      } else {
        $("token-card").hidden = true;
        $("no-token").hidden = false;
        $("refresh-state").textContent = "No active queue token";
        showUpcoming();
      }
    } catch {
      $("refresh-state").textContent = "Live update unavailable";
    }
  }

  // "Notify me" toggle (browser notifications)
  const toggle = $("alert-toggle");
  try { toggle.checked = localStorage.getItem("dcq-queue-alerts") === "on"; } catch { toggle.checked = false; }
  toggle.addEventListener("change", async () => {
    try { localStorage.setItem("dcq-queue-alerts", toggle.checked ? "on" : "off"); } catch { /* storage unavailable */ }
    if (toggle.checked && "Notification" in window && Notification.permission === "default") await Notification.requestPermission();
  });

  // Demo controls
  if (USE_FAKE_DATA) {
    $("demo-panel").hidden = false;
    $("demo-checkin").addEventListener("click", () => { fakeCheckIn(user); refresh(); });
    $("demo-advance").addEventListener("click", () => { fakeAdvanceQueue(); refresh(); });
    $("demo-reset").addEventListener("click", () => { writeFakeQueue(user.email, null); refresh(); });
  }

  refresh();
  setInterval(refresh, 15000);
})();