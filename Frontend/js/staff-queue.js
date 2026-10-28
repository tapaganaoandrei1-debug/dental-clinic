/* staff-queue.js - queue control board. Needs ops-layout.js (see its API contract). Refreshes every 10 seconds. */
(function () {
  if (!ops) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  $("today-label").textContent = `${fmtDate(ymd(new Date()), { weekday: "long", month: "long", day: "numeric", year: "numeric" })} - updates live`;
  let current = null;

  async function run(payload) {
    $("form-alert").hidden = true;
    try {
      const r = await ops.act(payload);
      if (!r.ok) showAlert($("form-alert"), r.error || "That action could not be completed.");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
    refresh();
  }

  function card(e) {
    const c = make("article", "qcard");
    const top = make("div", "qcard-top");
    top.append(make("strong", "ticket-link", ops.ticket(e.queue_number)),
      make("span", "", e.status === "waiting" ? `Waiting ${e.waiting_minutes} min` : e.status === "called" ? `Called ${e.waiting_minutes} min ago` : `Started ${fmtTime(e.start_time.slice(0, 5))}`));
    c.append(top, make("p", "qcard-name", e.patient_name), make("p", "qcard-service", e.service_name));
    const row = make("div", "qcard-actions");
    const [label, action] = ops.NEXT[e.status];
    const main = make("button", e.status === "waiting" ? "btn btn-primary btn-sm" : "btn btn-outline-secondary btn-sm", label);
    main.type = "button"; main.addEventListener("click", () => run({ id: e.id, action }));
    row.append(main);
    if (e.status !== "serving") {
      const ns = make("button", "small-link danger-link", "No-show");
      ns.type = "button"; ns.addEventListener("click", () => window.confirm(`Mark ${e.patient_name} as a no-show?`) && run({ id: e.id, action: "no_show" }));
      row.append(ns);
    }
    c.append(row);
    return c;
  }

  function render(data) {
    current = data;
    const serving = data.entries.find((e) => e.status === "serving");
    $("serving-number").textContent = serving ? ops.ticket(serving.queue_number) : "-";
    $("serving-detail").textContent = serving ? `${serving.patient_name} - ${serving.service_name}` : "No patient is in service.";
    $("serve-btn").disabled = !serving;
    $("next-btn").disabled = !data.entries.some((e) => e.status === "waiting");
    $("st-queue").textContent = data.stats.in_queue;
    $("st-wait").textContent = `${data.stats.avg_wait_minutes} min`;
    $("st-noshow").textContent = data.stats.no_shows;
    ["waiting", "called", "serving"].forEach((s) => {
      const list = data.entries.filter((e) => e.status === s);
      $(`n-${s}`).textContent = list.length;
      $(`col-${s}`).replaceChildren(...(list.length ? list.map(card) : [make("p", "empty-state", "No one here.")]));
    });
    $("check-ins").replaceChildren(...(data.check_ins.length ? data.check_ins.map((a) => {
      const row = make("div", "sched-row");
      const who = make("div");
      who.append(make("strong", "", a.patient_name), make("span", "", a.service_name));
      const btn = make("button", "btn btn-primary btn-sm", "Check in");
      btn.type = "button"; btn.addEventListener("click", () => run({ action: "check_in", appointment_id: a.appointment_id }));
      row.append(make("time", "", fmtTime(a.start.slice(0, 5))), who, btn);
      return row;
    }) : [make("p", "empty-state", "No appointments waiting to check in.")]));
  }

  async function refresh() {
    try { render(await ops.queue()); } catch (e) { showAlert($("form-alert"), e.message || NETWORK_MSG); }
  }

  $("serve-btn").addEventListener("click", () => { const s = current.entries.find((e) => e.status === "serving"); if (s) run({ id: s.id, action: "serve" }); });
  $("next-btn").addEventListener("click", () => run({ action: "call_next" }));
  refresh();
  setInterval(refresh, 10000);
})();