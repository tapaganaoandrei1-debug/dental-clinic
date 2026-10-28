/* ==========================================================
   admin-users.js - users and roles (admin only). Needs ops-layout.js.
   GET  admin-users.php -> { ok, users:[{ id, name, email, phone, role, is_active, email_verified }] }
   POST admin-user-action.php
        { action:"set_role",   id, role:"patient|staff|admin" }
        { action:"set_active", id, active:true|false }
        { action:"create",     name, email, role:"staff|admin", password }     -> { ok } or { ok:false, error, field? }
   The backend must refuse changes to the signed-in admin's own role or active status.
   ========================================================== */
(function () {
  if (!ops || !ops.requireAdmin()) return;
  const $ = (id) => document.getElementById(id);
  const { make } = ops;
  const me = ops.user.email;

  async function load() {
    if (USE_FAKE_DATA) return getFakeUsers().map((u) => ({ id: u.email, name: u.name, email: u.email, role: u.role, is_active: u.active !== false, email_verified: !!u.verified }));
    const d = await apiGet("admin-users.php");
    if (!d.ok) throw new Error(d.error || GENERIC_ERROR);
    return d.users;
  }
  async function act(payload) {
    if (!USE_FAKE_DATA) return apiPost("admin-user-action.php", payload);
    const u = findFakeUser(payload.id || payload.email || "");
    if (payload.action === "create") {
      if (u) return { ok: false, field: "email", error: "An account with that email already exists." };
      saveFakeUser({ name: payload.name, email: payload.email, phone: "", password: payload.password, role: payload.role, verified: true });
      return { ok: true };
    }
    if (!u) return { ok: false, error: "User not found." };
    if (payload.action === "set_role") saveFakeUser({ ...u, role: payload.role });
    if (payload.action === "set_active") saveFakeUser({ ...u, active: payload.active });
    return { ok: true };
  }

  async function run(payload) {
    $("form-alert").hidden = true;
    try {
      const r = await act(payload);
      if (!r.ok) showAlert($("form-alert"), r.error || "That change could not be saved.");
      else if (payload.action === "create") showAlert($("form-alert"), "Account added.", "success");
    } catch { showAlert($("form-alert"), NETWORK_MSG); }
    render();
  }

  async function render() {
    try {
      const users = await load();
      $("user-count").textContent = `${users.length} users`;
      $("rows").replaceChildren(...users.map((u) => {
        const self = u.email === me;
        const tr = make("tr");
        const role = make("select", "form-select form-select-sm"); role.disabled = self; role.setAttribute("aria-label", `Role for ${u.name}`);
        ["patient", "staff", "admin"].forEach((r) => role.append(new Option(r[0].toUpperCase() + r.slice(1), r)));
        role.value = u.role; role.addEventListener("change", () => run({ action: "set_role", id: u.id, role: role.value }));
        const active = make("input"); active.type = "checkbox"; active.checked = u.is_active; active.disabled = self; active.setAttribute("aria-label", `Active: ${u.name}`);
        active.addEventListener("change", () => run({ action: "set_active", id: u.id, active: active.checked }));
        const tdName = make("td"); tdName.append(make("strong", "", u.name)); if (self) tdName.append(make("span", "cell-sub", "You"));
        const tdRole = make("td"); tdRole.append(role);
        const tdVer = make("td"); tdVer.append(make("span", `status ${u.email_verified ? "status-confirmed" : "status-pending"}`, u.email_verified ? "Verified" : "Not verified"));
        const tdAct = make("td"); tdAct.append(active);
        tr.append(tdName, make("td", "muted-cell", u.email), tdRole, tdVer, tdAct);
        return tr;
      }));
    } catch (e) { showAlert($("form-alert"), e.message || NETWORK_MSG); }
  }

  $("user-form").addEventListener("submit", (e) => {
    e.preventDefault();
    const name = $("u-name").value.trim(), email = $("u-email").value.trim().toLowerCase(), pw = $("u-pass").value;
    const checks = [[$("u-name"), name.length >= 2, "Enter a name."], [$("u-email"), EMAIL_RE.test(email), "Enter a valid email."], [$("u-pass"), passwordError(pw) === "", passwordError(pw)]];
    let ok = true;
    checks.forEach(([input, valid, msg]) => { setError(input, valid ? "" : msg); if (!valid) ok = false; });
    if (!ok) return;
    run({ action: "create", name, email, role: $("u-role").value, password: pw }).then(() => $("user-form").reset());
  });
  render();
})();
