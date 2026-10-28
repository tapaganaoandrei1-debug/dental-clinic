/* ==========================================================
   staff-walk-in.js - register a walk-in patient. Needs ops-layout.js.
   GET services.php and dentists.php (same as the booking page)
   POST walk-in.php { name, phone, service_id, dentist_id (0 = any), notes }
        -> { ok:true, queue_number:14 } or { ok:false, error }
        (the backend creates a confirmed appointment for now and a waiting queue entry)
   ========================================================== */
(function () {
  if (!ops) return;
  const $ = (id) => document.getElementById(id);
  let services = [], dentists = [];

  async function get(endpoint, key) {
    const d = await apiGet(endpoint);
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d[key];
  }

  function fillDentists() {
    const sid = Number($("service").value);
    const eligible = dentists.filter((d) => d.service_ids.includes(sid));
    $("dentist").replaceChildren(new Option("Any available", "0"), ...eligible.map((d) => new Option(`${d.name} (${d.specialty})`, String(d.id))));
  }

  $("service").addEventListener("change", fillDentists);
  $("walkin-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    $("form-alert").hidden = true;
    const name = $("name").value.trim(), phone = $("phone").value.trim();
    let ok = true;
    setError($("name"), name.length >= 2 ? "" : "Enter the patient's name."); if (name.length < 2) ok = false;
    const badPhone = phone !== "" && !PHONE_RE.test(phone);
    setError($("phone"), badPhone ? "Enter a valid phone number." : ""); if (badPhone) ok = false;
    if (!ok) return;
    const btn = $("submit-btn");
    setLoading(btn, true, "Add to queue", "Adding...");
    try {
      const payload = { name, phone, service_id: Number($("service").value), dentist_id: Number($("dentist").value), notes: $("notes").value.trim() };
      const r = USE_FAKE_DATA ? fakeWalkIn(payload) : await apiPost("walk-in.php", payload);
      if (r.ok) {
        $("done-number").textContent = formatQueueNumber(r.queue_number);
        $("done-text").textContent = `${name} was added to today's queue.`;
        $("walkin-done").hidden = false;
        $("walkin-form").reset(); fillDentists();
      } else showAlert($("form-alert"), r.error || "The walk-in could not be added.");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
    setLoading(btn, false, "Add to queue");
  });

  (async function () {
    try {
      services = USE_FAKE_DATA ? FAKE_SERVICES.filter((s) => s.is_active) : await get("services.php", "services");
      dentists = USE_FAKE_DATA ? FAKE_DENTISTS.map((d) => ({ ...d, service_ids: FAKE_DENTIST_SERVICES[d.id] })) : await get("dentists.php", "dentists");
      $("service").replaceChildren(...services.map((s) => new Option(`${s.name} (${s.duration_minutes} min)`, String(s.id))));
      fillDentists();
    } catch (e) { showAlert($("form-alert"), e.message || NETWORK_MSG); }
  })();
})();
