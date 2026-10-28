/* ==========================================================
   auth.js - login, sign up, password reset, email verification,
   and session helpers. Needs config.js loaded first.

   Rules copied from the PHP project:
   - Roles: "patient", "staff", "admin"
   - Password: at least 8 characters, with a letter and a number
   - Phone is optional
   - New accounts must verify their email before they can sign in

   API CONTRACT for the backend teammate
   (every response is JSON with "ok": true/false and, on failure, "error")

   POST login.php            { email, password }
        -> { ok:true, user:{ id, name, email, role } }
        -> { ok:false, error:"Email or password is incorrect." }
   POST register.php         { name, email, phone, password }
        -> { ok:true }
        -> { ok:false, field:"email", error:"An account with that email already exists." }
   POST logout.php
   POST forgot-password.php  { email }            -> { ok:true }  (always, so accounts are not revealed)
   POST reset-password.php   { token, password }  -> { ok:true } or { ok:false, error }
   GET  verify-email.php?token=...                -> { ok:true, email } or { ok:false, error }
   POST verify-email.php     { token }            -> { ok:true } or { ok:false, error }

   Requests send cookies (credentials: "include") so PHP sessions work.
   ========================================================== */

const SESSION_KEY = "dcq_user";
const FAKE_USERS_KEY = "dcq_fake_users";
const PENDING_VERIFY_KEY = "dcq_pending_verify";
const PENDING_RESET_KEY = "dcq_pending_reset";

const NETWORK_MSG =
  "Can't reach the server. Check your internet connection, or ask the backend team to confirm the API address in js/config.js.";
const GENERIC_ERROR = "The server had a problem. Please try again in a moment.";

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_RE = /^[0-9+\-\s()]{7,20}$/;

/* ---------- Fake data (used while USE_FAKE_DATA is true) ----------
   Accounts match the README of the PHP project. Passwords are kept
   in plain text here ONLY because this is a throwaway demo mode.
   The real backend must store password hashes. */
const DEMO_USERS = [
  { name: "Clinic Administrator", email: "admin@dentalclinic.test", phone: "09170000001", password: "Admin@123", role: "admin", verified: true },
  { name: "Front Desk Staff", email: "staff@dentalclinic.test", phone: "09170000002", password: "Staff@123", role: "staff", verified: true },
  { name: "Maria Cruz", email: "patient@dentalclinic.test", phone: "09171234567", password: "Patient@123", role: "patient", verified: true },
];

const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function readStoredUsers() {
  try {
    return JSON.parse(localStorage.getItem(FAKE_USERS_KEY) || "[]");
  } catch {
    return [];
  }
}

function getFakeUsers() {
  const byEmail = new Map();
  [...DEMO_USERS, ...readStoredUsers()].forEach((u) => byEmail.set(u.email.toLowerCase(), u));
  return [...byEmail.values()];
}

function findFakeUser(email) {
  return getFakeUsers().find((u) => u.email.toLowerCase() === email.toLowerCase());
}

function saveFakeUser(user) {
  const stored = readStoredUsers().filter((u) => u.email.toLowerCase() !== user.email.toLowerCase());
  stored.push(user);
  localStorage.setItem(FAKE_USERS_KEY, JSON.stringify(stored));
}

async function fakeLogin(email, password) {
  await delay(450);
  const user = findFakeUser(email);
  if (!user) return { ok: false, error: "Email or password is incorrect." };
  if (!user.verified) {
    return { ok: false, error: "Verify your email address before signing in. Check your inbox or contact the clinic." };
  }
  if (user.password !== password) return { ok: false, error: "Email or password is incorrect." };
  if (user.active === false) return { ok: false, error: "This account is inactive. Please contact the clinic." };
  return { ok: true, user: { id: user.email, name: user.name, email: user.email, role: user.role } };
}

async function fakeRegister(data) {
  await delay(450);
  if (findFakeUser(data.email)) {
    return { ok: false, field: "email", error: "An account with that email already exists." };
  }
  saveFakeUser({ name: data.name, email: data.email, phone: data.phone, password: data.password, role: "patient", verified: false });
  sessionStorage.setItem(PENDING_VERIFY_KEY, data.email);
  return { ok: true };
}

async function fakeForgot(email) {
  await delay(450);
  const user = findFakeUser(email);
  if (user && user.verified) sessionStorage.setItem(PENDING_RESET_KEY, user.email);
  return { ok: true };
}

async function fakeReset(token, password) {
  await delay(450);
  const email = sessionStorage.getItem(PENDING_RESET_KEY);
  const user = email && token === "demo" ? findFakeUser(email) : null;
  if (!user) return { ok: false, error: "This reset link is invalid or has expired. Request a new one." };
  saveFakeUser({ ...user, password });
  sessionStorage.removeItem(PENDING_RESET_KEY);
  return { ok: true };
}

async function fakeVerifyInfo(email) {
  await delay(300);
  const user = email ? findFakeUser(email) : null;
  if (!user || user.verified) {
    return { ok: false, error: "This verification link is invalid or has expired. Create a new account or contact the clinic." };
  }
  return { ok: true, email: user.email };
}

async function fakeVerify(email) {
  await delay(450);
  const user = findFakeUser(email);
  if (!user) return { ok: false, error: "This verification link is invalid or has expired." };
  saveFakeUser({ ...user, verified: true });
  sessionStorage.removeItem(PENDING_VERIFY_KEY);
  return { ok: true };
}

/* ---------- Real API ---------- */
async function apiRequest(method, endpoint, body) {
  const options = { method, credentials: "include", headers: { Accept: "application/json" } };
  if (body !== undefined) {
    options.headers["Content-Type"] = "application/json";
    options.body = JSON.stringify(body);
  }
  const res = await fetch(`${API_URL}/${endpoint}`, options);
  let data = {};
  try {
    data = await res.json();
  } catch {
    /* response was not JSON */
  }
  if (!res.ok && !data.error) {
    data.ok = false;
    data.error = GENERIC_ERROR;
  }
  return data;
}
const apiPost = (endpoint, body) => apiRequest("POST", endpoint, body);
const apiGet = (endpoint) => apiRequest("GET", endpoint);

const loginRequest = (email, password) =>
  USE_FAKE_DATA ? fakeLogin(email, password) : apiPost("login.php", { email, password });
const registerRequest = (data) => (USE_FAKE_DATA ? fakeRegister(data) : apiPost("register.php", data));
const forgotRequest = (email) => (USE_FAKE_DATA ? fakeForgot(email) : apiPost("forgot-password.php", { email }));
const resetRequest = (token, password) =>
  USE_FAKE_DATA ? fakeReset(token, password) : apiPost("reset-password.php", { token, password });
const verifyInfoRequest = ({ token, email }) =>
  USE_FAKE_DATA ? fakeVerifyInfo(email) : apiGet(`verify-email.php?token=${encodeURIComponent(token)}`);
const verifyRequest = ({ token, email }) =>
  USE_FAKE_DATA ? fakeVerify(email) : apiPost("verify-email.php", { token });

/* ---------- Session helpers (use these on other pages too) ----------
   These only control what a page shows. The backend must still verify
   the session and role on every request. */
function saveSession(user) {
  sessionStorage.setItem(SESSION_KEY, JSON.stringify(user));
}

function getSession() {
  try {
    return JSON.parse(sessionStorage.getItem(SESSION_KEY));
  } catch {
    return null;
  }
}

function homeFor(role) {
  if (role === "admin") return "admin-dashboard.html";
  if (role === "staff") return "staff-dashboard.html";
  return "book.html";
}

// Only allow simple same-folder page names like "book.html" (never other sites)
function safeRedirect(target) {
  return typeof target === "string" && /^[a-z0-9-]+\.html$/i.test(target) ? target : null;
}

// Call at the top of a protected page: requireLogin() or requireLogin(["admin", "staff"])
function requireLogin(allowedRoles) {
  const user = getSession();
  if (!user) {
    const here = window.location.pathname.split("/").pop();
    window.location.replace(`login.html?redirect=${encodeURIComponent(here)}`);
    return null;
  }
  if (allowedRoles && !allowedRoles.includes(user.role)) {
    window.location.replace(homeFor(user.role));
    return null;
  }
  return user;
}

function logout() {
  sessionStorage.removeItem(SESSION_KEY);
  if (!USE_FAKE_DATA) {
    fetch(`${API_URL}/logout.php`, { method: "POST", credentials: "include" }).catch(() => {});
  }
  window.location.href = "login.html";
}

/* ---------- Form helpers ---------- */
function passwordError(password) {
  if (password.length < 8) return "Password must be at least 8 characters long.";
  if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
    return "Password must contain at least one letter and one number.";
  }
  return "";
}

function setError(input, message) {
  const errorEl = document.getElementById(`${input.id}-error`);
  input.classList.toggle("has-error", Boolean(message));
  input.setAttribute("aria-invalid", message ? "true" : "false");
  if (errorEl) {
    errorEl.textContent = message || "";
    errorEl.hidden = !message;
  }
}

function showAlert(box, message, type = "danger") {
  box.className = `alert alert-${type}`;
  box.textContent = message;
  box.hidden = false;
}

function setLoading(button, loading, idleText, busyText) {
  button.disabled = loading;
  button.textContent = loading ? busyText : idleText;
}

function focusFirstError(form) {
  const bad = form.querySelector(".has-error");
  if (bad) bad.focus();
}

/* Show / Hide password buttons */
document.addEventListener("click", (e) => {
  const toggle = e.target.closest("[data-toggle-password]");
  if (!toggle) return;
  const input = document.getElementById(toggle.dataset.togglePassword);
  const showing = input.type === "text";
  input.type = showing ? "password" : "text";
  toggle.textContent = showing ? "Show" : "Hide";
});

/* Clear an error as soon as the person starts fixing it */
document.addEventListener("input", (e) => {
  const input = e.target;
  if (input.classList && input.classList.contains("has-error")) setError(input, "");
  const box = document.getElementById("form-alert");
  if (box && box.classList.contains("alert-danger") && input.closest && input.closest("form")) box.hidden = true;
});

/* ---------- Login page ---------- */
function initLoginForm() {
  const form = document.getElementById("login-form");
  if (!form) return;

  const alertBox = document.getElementById("form-alert");
  const email = document.getElementById("email");
  const password = document.getElementById("password");
  const button = document.getElementById("submit-btn");
  const params = new URLSearchParams(window.location.search);

  if (params.get("registered")) {
    showAlert(alertBox, "Your account was created. Verify your email before signing in.", "success");
  } else if (params.get("reset")) {
    showAlert(alertBox, "Your password has been updated. Sign in with your new password.", "success");
  } else if (params.get("verified")) {
    showAlert(alertBox, "Your email is verified. You can sign in now.", "success");
  }

  if (USE_FAKE_DATA) {
    document.getElementById("demo-hint").hidden = false;
    const pending = sessionStorage.getItem(PENDING_VERIFY_KEY);
    if (pending) {
      document.getElementById("demo-verify-link").href = `verify-email.html?email=${encodeURIComponent(pending)}`;
      document.getElementById("demo-verify").hidden = false;
    }
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    alertBox.hidden = true;

    const emailValue = email.value.trim();
    let valid = true;

    if (!EMAIL_RE.test(emailValue)) {
      setError(email, "Enter the email address you registered with.");
      valid = false;
    } else {
      setError(email, "");
    }
    if (!password.value) {
      setError(password, "Enter your password.");
      valid = false;
    } else {
      setError(password, "");
    }
    if (!valid) return focusFirstError(form);

    setLoading(button, true, "Log in", "Logging in...");
    try {
      const result = await loginRequest(emailValue, password.value);
      if (result.ok) {
        saveSession(result.user);
        window.location.href = safeRedirect(params.get("redirect")) || homeFor(result.user.role);
        return;
      }
      showAlert(alertBox, result.error || "Email or password is incorrect.");
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
    setLoading(button, false, "Log in");
  });
}

/* ---------- Sign up page ---------- */
function initRegisterForm() {
  const form = document.getElementById("register-form");
  if (!form) return;

  const alertBox = document.getElementById("form-alert");
  const fields = {
    name: document.getElementById("name"),
    email: document.getElementById("email"),
    phone: document.getElementById("phone"),
    password: document.getElementById("password"),
    confirm: document.getElementById("confirm"),
  };
  const button = document.getElementById("submit-btn");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    alertBox.hidden = true;

    const name = fields.name.value.trim();
    const email = fields.email.value.trim().toLowerCase();
    const phone = fields.phone.value.trim();
    const pw = fields.password.value;
    let valid = true;

    const check = (input, ok, message) => {
      setError(input, ok ? "" : message);
      if (!ok) valid = false;
    };

    check(fields.name, name.length >= 2 && name.length <= 100, "Name must be between 2 and 100 characters.");
    check(fields.email, EMAIL_RE.test(email), "Enter a valid email address.");
    check(fields.phone, phone === "" || PHONE_RE.test(phone), "Enter a valid phone number.");
    check(fields.password, passwordError(pw) === "", passwordError(pw));
    check(fields.confirm, pw !== "" && fields.confirm.value === pw, "The passwords do not match.");

    if (!valid) return focusFirstError(form);

    setLoading(button, true, "Create account", "Creating account...");
    try {
      const result = await registerRequest({ name, email, phone, password: pw });
      if (result.ok) {
        window.location.href = "login.html?registered=1";
        return;
      }
      if (result.field && fields[result.field]) {
        setError(fields[result.field], result.error);
        fields[result.field].focus();
      } else {
        showAlert(alertBox, result.error || "We could not create your account right now. Please try again.");
      }
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
    setLoading(button, false, "Create account");
  });
}

/* ---------- Forgot password page ---------- */
function initForgotForm() {
  const form = document.getElementById("forgot-form");
  if (!form) return;

  const alertBox = document.getElementById("form-alert");
  const email = document.getElementById("email");
  const button = document.getElementById("submit-btn");
  const done = document.getElementById("forgot-done");

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    alertBox.hidden = true;

    const emailValue = email.value.trim();
    if (!EMAIL_RE.test(emailValue)) {
      setError(email, "Enter the email address on your account.");
      return email.focus();
    }
    setError(email, "");

    setLoading(button, true, "Send reset link", "Sending...");
    try {
      await forgotRequest(emailValue);
      // Same message whether or not the account exists, as in the PHP version
      form.hidden = true;
      done.hidden = false;
      if (USE_FAKE_DATA && sessionStorage.getItem(PENDING_RESET_KEY)) {
        document.getElementById("demo-reset").hidden = false;
      }
    } catch {
      showAlert(alertBox, NETWORK_MSG);
      setLoading(button, false, "Send reset link");
    }
  });
}

/* ---------- Reset password page ---------- */
function initResetForm() {
  const form = document.getElementById("reset-form");
  if (!form) return;

  const alertBox = document.getElementById("form-alert");
  const password = document.getElementById("password");
  const confirm = document.getElementById("confirm");
  const button = document.getElementById("submit-btn");
  const token = new URLSearchParams(window.location.search).get("token") || "";

  if (!token) {
    form.hidden = true;
    showAlert(alertBox, "This reset link is invalid or has expired. Request a new one.");
    return;
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    alertBox.hidden = true;

    const pw = password.value;
    let valid = true;
    const pwMessage = passwordError(pw);
    setError(password, pwMessage);
    if (pwMessage) valid = false;
    const mismatch = pw !== confirm.value;
    setError(confirm, mismatch ? "The passwords do not match." : "");
    if (mismatch) valid = false;
    if (!valid) return focusFirstError(form);

    setLoading(button, true, "Save new password", "Saving...");
    try {
      const result = await resetRequest(token, pw);
      if (result.ok) {
        window.location.href = "login.html?reset=1";
        return;
      }
      showAlert(alertBox, result.error || "The password could not be updated. Request a new reset link and try again.");
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
    setLoading(button, false, "Save new password");
  });
}

/* ---------- Verify email page ---------- */
async function initVerifyPage() {
  const checking = document.getElementById("verify-checking");
  if (!checking) return;

  const alertBox = document.getElementById("form-alert");
  const ready = document.getElementById("verify-ready");
  const done = document.getElementById("verify-done");
  const address = document.getElementById("verify-address");
  const button = document.getElementById("verify-btn");
  const params = new URLSearchParams(window.location.search);
  const link = { token: params.get("token") || "", email: params.get("email") || "" };

  if (!link.token && !link.email) {
    checking.hidden = true;
    return showAlert(alertBox, "This verification link is invalid or has expired. Create a new account or contact the clinic.");
  }

  try {
    const info = await verifyInfoRequest(link);
    checking.hidden = true;
    if (!info.ok) return showAlert(alertBox, info.error || "This verification link is invalid or has expired.");
    address.textContent = info.email;
    ready.hidden = false;
  } catch {
    checking.hidden = true;
    return showAlert(alertBox, NETWORK_MSG);
  }

  button.addEventListener("click", async () => {
    alertBox.hidden = true;
    setLoading(button, true, "Verify email address", "Verifying...");
    try {
      const result = await verifyRequest(link);
      if (result.ok) {
        ready.hidden = true;
        done.hidden = false;
        return;
      }
      showAlert(alertBox, result.error || "We could not verify this address. Request a new link.");
    } catch {
      showAlert(alertBox, NETWORK_MSG);
    }
    setLoading(button, false, "Verify email address");
  });
}

initLoginForm();
initRegisterForm();
initForgotForm();
initResetForm();
initVerifyPage();