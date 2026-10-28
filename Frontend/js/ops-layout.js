/* ==========================================================
   ops-layout.js - sidebar and shared helpers for staff and admin pages.
   Load order: config, fake-data, auth, ops-layout, then the page script.
   Put <aside id="ops-sidebar" data-active="queue"></aside> in the page.

   API CONTRACT (real mode, JSON, cookies sent):
   GET  staff-queue.php -> { ok,
          entries:[{ id, queue_number:14, patient_name, service_name, dentist_name,
                     status:"waiting|called|serving", start_time:"10:30", waiting_minutes:12 }],
          check_ins:[{ appointment_id, start:"10:00", patient_name, service_name, status }],   (today's appointments not yet checked in)
          stats:{ in_queue, now_serving:9 | null, avg_wait_minutes, no_shows } }
   POST queue-action.php { action:"call|start|serve|skip|no_show|call_next", id }  -> { ok } or { ok:false, error }
   POST queue-action.php { action:"check_in", appointment_id }                    -> { ok } or { ok:false, error }
   ========================================================== */
const ops = (function () {
  const user = requireLogin(["staff", "admin"]);
  if (!user) return null;

  const make = (tag, cls, text) => {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  };

  const NAV = {
    staff: [
      ["Dashboard", "staff-dashboard.html", "dashboard"],
      ["Appointments", "staff-appointments.html", "appointments"],
      ["Queue Control", "staff-queue.html", "queue"],
      ["Walk-in", "staff-walk-in.html", "walkin"],
      ["Notifications", "staff-notifications.html", "notifications"],
    ],
    admin: [
      ["Dashboard", "admin-dashboard.html", "dashboard"],
      ["Appointments", "staff-appointments.html", "appointments"],
      ["Queue Control", "staff-queue.html", "queue"],
      ["Staff & Schedules", "admin-schedules.html", "schedules"],
      ["Reports & Analytics", "admin-reports.html", "reports"],
      ["Users & Roles", "admin-users.html", "users"],
      ["Settings & Backup", "admin-backup.html", "backup"],
    ],
  };

  function renderSidebar() {
    const side = document.getElementById("ops-sidebar");
    if (!side) return;
    side.className = "ops-sidebar";
    const active = side.dataset.active || "";
    const brand = make("a", "brand");
    brand.href = homeFor(user.role);
    brand.append(make("span", "brand-mark", "D"), make("span", "", "Dental Clinic"));
    const nav = make("nav", "ops-nav");
    nav.setAttribute("aria-label", "Main");
    NAV[user.role].forEach(([label, href, key]) => {
      const a = make("a", "", label);
      a.href = href;
      if (key === active) a.setAttribute("aria-current", "page");
      nav.append(a);
    });
    const foot = make("div", "ops-user");
    const initials = user.name.split(/\s+/).slice(0, 2).map((p) => p[0].toUpperCase()).join("");
    const who = make("div");
    who.append(make("strong", "", user.name), make("span", "", user.role === "admin" ? "Administrator" : "Front desk"));
    const out = make("button", "ops-logout", "Log out");
    out.type = "button";
    out.addEventListener("click", logout);
    foot.append(make("span", "avatar", initials), who, out);
    side.replaceChildren(brand, nav, foot);
  }
  renderSidebar();

  async function queue() {
    if (USE_FAKE_DATA) return fakeStaffQueue();
    const data = await apiGet("staff-queue.php");
    if (!data.ok) throw new Error(data.error || GENERIC_ERROR);
    return data;
  }
  async function act(payload) {
    if (USE_FAKE_DATA) return fakeQueueAction(payload);
    return apiPost("queue-action.php", payload);
  }

  const LABEL = { waiting: "Waiting", called: "Called", serving: "In service", pending: "Pending", confirmed: "Confirmed" };
  const badge = (status) => make("span", `status status-${status === "serving" ? "in-service" : status}`, LABEL[status] || status);
  const ticket = (n) => formatQueueNumber(n);
  // Next step for a queue entry: [button label, action]
  const NEXT = { waiting: ["Call", "call"], called: ["Start service", "start"], serving: ["Mark served", "serve"] };

  // Call at the top of admin-only pages; staff are sent back to their own dashboard
  function requireAdmin() {
    if (user.role === "admin") return true;
    window.location.replace(homeFor(user.role));
    return false;
  }

  return { user, make, queue, act, badge, ticket, NEXT, LABEL, requireAdmin };
})();