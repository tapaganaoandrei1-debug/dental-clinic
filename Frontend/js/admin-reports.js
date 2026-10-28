/* ==========================================================
   admin-reports.js - summary report (admin only). Needs ops-layout.js.
   GET admin-reports.php?days=7 -> { ok,
        totals:{ appointments, completed, cancelled, no_show, revenue },
        by_day:[{ date:"2026-10-26", count }],          (one row per day in the range, oldest first)
        by_service:[{ name, count }] }
   ========================================================== */
(function () {
  if (!ops || !ops.requireAdmin()) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;

  async function load(days) {
    if (!USE_FAKE_DATA) {
      const d = await apiGet(`admin-reports.php?days=${days}`);
      if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
      return d;
    }
    const from = ymd(new Date(Date.now() - (days - 1) * 86400000)), to = ymd(new Date());
    const list = readFakeAppointments().filter((a) => a.date >= from && a.date <= to);
    const count = (s) => list.filter((a) => a.status === s).length;
    const price = (a) => FAKE_SERVICES.find((s) => s.id === a.service_id).price;
    return {
      totals: { appointments: list.length, completed: count("completed"), cancelled: count("cancelled"), no_show: count("no_show"),
        revenue: list.filter((a) => a.status === "completed").reduce((t, a) => t + price(a), 0) },
      by_day: Array.from({ length: days }, (_, i) => { const date = ymd(new Date(Date.now() - (days - 1 - i) * 86400000)); return { date, count: list.filter((a) => a.date === date).length }; }),
      by_service: FAKE_SERVICES.filter((s) => s.is_active).map((s) => ({ name: s.name, count: list.filter((a) => a.service_id === s.id).length })),
    };
  }

  function bars(box, rows, label) {
    const max = Math.max(1, ...rows.map((r) => r.count));
    box.replaceChildren(...rows.map((r) => {
      const row = make("div", "bar-row");
      const track = make("div", "bar-track"); const fill = make("span", "bar-fill"); fill.style.width = `${(r.count / max) * 100}%`; track.append(fill);
      row.append(make("span", "bar-label", label(r)), track, make("strong", "", String(r.count)));
      return row;
    }));
  }

  async function render() {
    const days = Number($("range").value);
    try {
      const d = await load(days);
      $("r-total").textContent = d.totals.appointments;
      $("r-total-sub").textContent = `In the last ${days} days`;
      $("r-done").textContent = d.totals.completed;
      $("r-lost").textContent = d.totals.cancelled + d.totals.no_show;
      $("r-lost-sub").textContent = `${d.totals.cancelled} cancelled - ${d.totals.no_show} no-show`;
      $("r-rev").textContent = formatMoney(d.totals.revenue);
      bars($("by-day"), d.by_day, (r) => fmtDate(r.date, days > 7 ? { month: "short", day: "numeric" } : { weekday: "short", month: "short", day: "numeric" }));
      bars($("by-service"), d.by_service, (r) => r.name);
    } catch (e) { showAlert($("form-alert"), e.message || NETWORK_MSG); }
  }
  $("range").addEventListener("change", render);
  render();
})();
