/* ==========================================================
   receipt.js - printable appointment receipt (receipt.html?id=5).
   Load order: config, fake-data, auth.

   API CONTRACT (real mode):
   GET receipt.php?id=5 -> { ok, clinic_name,
        appointment:{ id, date, start, end, status, service_name, dentist_name, price,
                      patient_name, email, created_at, cancellation_reason } }
        or { ok:false, error:"Appointment not found." }  (the backend must check it belongs to the patient)
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
  const STATUS = { pending: "Pending", confirmed: "Confirmed", completed: "Completed", cancelled: "Cancelled", no_show: "No-show" };
  const id = Number(new URLSearchParams(location.search).get("id"));

  async function load() {
    if (USE_FAKE_DATA) {
      const a = readFakeAppointments().find((x) => x.id === id && x.patient_email === user.email);
      if (!a) throw new Error("Appointment not found.");
      return { clinic_name: CLINIC.name, appointment: { ...a, service_name: FAKE_SERVICES.find((s) => s.id === a.service_id).name,
        dentist_name: FAKE_DENTISTS.find((d) => d.id === a.dentist_id).name, price: FAKE_SERVICES.find((s) => s.id === a.service_id).price,
        patient_name: user.name, email: user.email } };
    }
    const data = await apiGet(`receipt.php?id=${id}`);
    if (!data.ok) throw new Error(data.error || "Appointment not found.");
    return data;
  }

  load().then(({ clinic_name, appointment: a }) => {
    $("rec-ref").textContent = `Booking reference #${a.id}`;
    $("rec-clinic").textContent = clinic_name;
    $("rec-lead").textContent = `Appointment details for ${a.patient_name}.`;
    $("rec-status").className = `status status-${a.status.replace("_", "-")}`;
    $("rec-status").textContent = STATUS[a.status] || a.status;
    $("rec-date").textContent = fmtDate(a.date, { weekday: "long", month: "long", day: "numeric", year: "numeric" });
    const rows = [
      ["Service", a.service_name], ["Dentist", a.dentist_name],
      ["Date and time", `${fmtDate(a.date, { month: "short", day: "numeric", year: "numeric" })}, ${fmtTime(a.start.slice(0, 5))} - ${fmtTime(a.end.slice(0, 5))}`],
      ["Patient", a.patient_name], ["Email", a.email], ["Service fee", formatMoney(a.price)],
    ];
    if (a.created_at) rows.push(["Requested", new Date(a.created_at).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" })]);
    if (a.cancellation_reason) rows.push(["Cancellation note", a.cancellation_reason]);
    $("rec-details").replaceChildren(...rows.map(([label, value]) => {
      const row = make("div");
      row.append(make("dt", "", label), make("dd", "", value));
      return row;
    }));
    $("receipt-body").hidden = false;
  }).catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));

  $("print-btn").addEventListener("click", () => window.print());
})();