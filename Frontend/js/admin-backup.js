/* ==========================================================
   admin-backup.js - clinic details and backup (admin only). Needs ops-layout.js.
   GET  admin-settings.php  -> { ok, settings:{ name, phone, email, address } }
   POST admin-settings.php  { name, phone, email, address } -> { ok } or { ok:false, error }
   GET  admin-backup.php    -> downloads the database backup file (the backend decides the format, e.g. .sql)
   ========================================================== */
(function () {
  if (!ops || !ops.requireAdmin()) return;
  const $ = (id) => document.getElementById(id);
  const F = { name: $("s-name"), phone: $("s-phone"), email: $("s-email"), address: $("s-address") };

  async function loadSettings() {
    if (USE_FAKE_DATA) return { name: CLINIC.name, phone: CLINIC.phone, email: CLINIC.email, address: CLINIC.address };
    const d = await apiGet("admin-settings.php");
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d.settings;
  }

  $("settings-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    $("form-alert").hidden = true;
    const v = Object.fromEntries(Object.entries(F).map(([k, el]) => [k, el.value.trim()]));
    let ok = true;
    setError(F.name, v.name.length >= 2 ? "" : "Enter the clinic name."); if (v.name.length < 2) ok = false;
    const badEmail = v.email !== "" && !EMAIL_RE.test(v.email);
    setError(F.email, badEmail ? "Enter a valid email." : ""); if (badEmail) ok = false;
    if (!ok) return;
    try {
      let r;
      if (USE_FAKE_DATA) { localStorage.setItem("dcq_fake_clinic", JSON.stringify(v)); r = { ok: true }; } else r = await apiPost("admin-settings.php", v);
      showAlert($("form-alert"), r.ok ? "Clinic details saved. Pages show the new details after a refresh." : (r.error || "The details could not be saved."), r.ok ? "success" : "danger");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
  });

  $("backup-btn").addEventListener("click", () => {
    if (!USE_FAKE_DATA) { window.location.href = `${API_URL}/admin-backup.php`; return; }
    const data = {};
    Object.keys(localStorage).filter((k) => k.startsWith("dcq_") && k !== FAKE_USERS_KEY).forEach((k) => { data[k] = JSON.parse(localStorage.getItem(k)); });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(new Blob([JSON.stringify({ created: new Date().toISOString(), data }, null, 2)], { type: "application/json" }));
    link.download = `clinic-backup-${ymd(new Date())}.json`;
    link.click();
    URL.revokeObjectURL(link.href);
    $("backup-note").textContent = "Backup downloaded (demo data, user passwords are not included).";
  });
  $("backup-note").textContent = USE_FAKE_DATA ? "Demo mode: this downloads the sample data saved in this browser." : "";

  loadSettings().then((s) => { Object.keys(F).forEach((k) => { F[k].value = s[k] || ""; }); })
    .catch((e) => showAlert($("form-alert"), e.message || NETWORK_MSG));
})();
