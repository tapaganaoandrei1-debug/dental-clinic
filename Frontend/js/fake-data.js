/* ==========================================================
   fake-data.js - sample data used while USE_FAKE_DATA is true.
   Copied from the seed data in the project's database
   (database/dental_clinic.sql) so the screens match the design.
   Delete or ignore this file once the real API is connected.
   ========================================================== */

const CLINIC = {
  name: "Dental Clinic Online",
  tagline: "Care that keeps you smiling.",
  address: "Taguig City, Metro Manila",
  phone: "(02) 8123 4567",
  email: "info@dentalclinic.test",
  currency: "\u20B1",
};

(function () { try { Object.assign(CLINIC, JSON.parse(localStorage.getItem("dcq_fake_clinic") || "{}")); } catch { /* ignore */ } })();

const FAKE_DENTISTS = [
  { id: 1, name: "Dr. Elena Ramos", specialty: "General Dentistry" },
  { id: 2, name: "Dr. Marco Villanueva", specialty: "Oral Surgery" },
  { id: 3, name: "Dr. Sofia Lim", specialty: "Orthodontics and Cosmetic" },
];

const FAKE_SERVICES = [
  { id: 1, name: "Check-up & Cleaning", category: "General Dentistry", description: "Oral exam and professional teeth cleaning.", duration_minutes: 30, price: 800, is_active: true },
  { id: 2, name: "Tooth Extraction", category: "Oral Surgery", description: "Removal of a damaged or problem tooth.", duration_minutes: 60, price: 1500, is_active: true },
  { id: 3, name: "Braces Consultation", category: "Orthodontics", description: "Evaluation and treatment plan for braces.", duration_minutes: 30, price: 500, is_active: true },
  { id: 4, name: "Teeth Whitening", category: "Cosmetic", description: "In-clinic professional teeth whitening.", duration_minutes: 60, price: 4500, is_active: true },
  { id: 5, name: "Dental Filling", category: "General Dentistry", description: "Filling for a cavity.", duration_minutes: 60, price: 1200, is_active: true },
  { id: 6, name: "Root Canal Treatment", category: "Oral Surgery", description: "Currently not offered.", duration_minutes: 90, price: 6000, is_active: false },
];

// Which dentist offers which services (dentist id -> service ids)
const FAKE_DENTIST_SERVICES = { 1: [1, 5], 2: [1, 2, 6], 3: [3, 4] };

function formatMoney(amount) {
  return CLINIC.currency + Number(amount).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ---------- Date/time helpers (shared by all pages) ---------- */
const pad2 = (n) => String(n).padStart(2, "0");
const ymd = (d) => `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
const parseYmd = (s) => { const [y, m, d] = s.split("-").map(Number); return new Date(y, m - 1, d); };
const toMin = (t) => { const [h, m] = t.split(":").map(Number); return h * 60 + m; };
const fromMin = (n) => `${pad2(Math.floor(n / 60))}:${pad2(n % 60)}`;
const fmtTime = (t) => { const [h, m] = t.split(":").map(Number); return new Date(2000, 0, 1, h, m).toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit" }); };
const fmtDate = (s, opts) => new Intl.DateTimeFormat("en-US", opts).format(parseYmd(s));

/* ---------- Clinic schedule (from the seed data; days: 0 = Sunday) ---------- */
const FAKE_SCHEDULES = {
  1: { days: [1, 2, 3, 4, 5, 6], start: "09:00", end: "17:00" },
  2: { days: [1, 3, 5], start: "09:00", end: "17:00" },
  3: { days: [2, 4, 6], start: "09:00", end: "15:00" },
};
const SCHEDULE_KEY = "dcq_fake_schedules";
function getSchedules() {
  try { return JSON.parse(localStorage.getItem(SCHEDULE_KEY)) || FAKE_SCHEDULES; } catch { return FAKE_SCHEDULES; }
}
const LUNCH_BREAK = { start: "12:00", end: "13:00" };
const SLOT_INTERVAL = 30;
const BOOKING_DAYS_AHEAD = 60;
const FAKE_QUEUE_SUMMARY = { ok: true, now_serving: "Q-009", people_ahead: 4, estimated_wait_minutes: 25, next_queue_number: "Q-014" };

/* ---------- Fake appointments (saved in this browser only) ---------- */
const APPT_KEY = "dcq_fake_appointments";
function rawAppointments() {
  try { return JSON.parse(localStorage.getItem(APPT_KEY) || "[]"); } catch { return []; }
}
function readFakeAppointments() {
  ensureSeedAppointments();
  return rawAppointments();
}
function saveFakeAppointments(list) {
  localStorage.setItem(APPT_KEY, JSON.stringify(list));
}

// Is this dentist unavailable from `start` for `duration` minutes?
function fakeBusy(dentistId, date, start, duration, ignoreId) {
  const from = toMin(start), to = from + duration;
  const taken = readFakeAppointments().some((a) =>
    a.id !== ignoreId && a.dentist_id === dentistId && a.date === date && a.status !== "cancelled" &&
    from < toMin(a.end) && to > toMin(a.start));
  if (taken) return true;
  // A few pre-booked times so disabled slots show up, like the design
  for (let m = from; m < to; m += SLOT_INTERVAL) {
    const h = Array.from(`${dentistId}|${date}|${m}`).reduce((x, c) => (x * 31 + c.charCodeAt(0)) >>> 0, 7);
    if (h % 5 === 0) return true;
  }
  return false;
}

// Returns [{ time:"09:00", available:true|false }]. dentistId 0 = any available.
function fakeSlots(serviceId, dentistId, date, ignoreId) {
  const service = FAKE_SERVICES.find((s) => s.id === serviceId);
  if (!service) return [];
  const dow = parseYmd(date).getDay();
  const now = new Date();
  const nowMin = date === ymd(now) ? now.getHours() * 60 + now.getMinutes() : -1;
  const slots = new Map();
  FAKE_DENTISTS.filter((d) => FAKE_DENTIST_SERVICES[d.id].includes(serviceId) && (dentistId === 0 || d.id === dentistId))
    .forEach((d) => {
      const sch = getSchedules()[d.id];
      if (!sch.days.includes(dow)) return;
      for (let t = toMin(sch.start); t + service.duration_minutes <= toMin(sch.end); t += SLOT_INTERVAL) {
        const overlapsLunch = t < toMin(LUNCH_BREAK.end) && t + service.duration_minutes > toMin(LUNCH_BREAK.start);
        if (overlapsLunch || t <= nowMin) continue;
        const time = fromMin(t);
        const free = !fakeBusy(d.id, date, time, service.duration_minutes, ignoreId);
        slots.set(time, slots.get(time) || free);
      }
    });
  return [...slots].sort().map(([time, available]) => ({ time, available }));
}

async function fakeBook({ service_id, dentist_id, date, time, notes }, user) {
  await delay(500);
  const service = FAKE_SERVICES.find((s) => s.id === service_id);
  const dentist = FAKE_DENTISTS.find((d) =>
    (dentist_id === 0 || d.id === dentist_id) && FAKE_DENTIST_SERVICES[d.id].includes(service_id) &&
    getSchedules()[d.id].days.includes(parseYmd(date).getDay()) && !fakeBusy(d.id, date, time, service.duration_minutes));
  if (!dentist) return { ok: false, error: "That time was just taken. Please choose another slot." };
  const list = readFakeAppointments();
  const id = (list.reduce((max, a) => Math.max(max, a.id), 0)) + 1;
  list.push({ id, patient_email: user.email, patient_name: user.name, service_id, dentist_id: dentist.id, date,
    start: time, end: fromMin(toMin(time) + service.duration_minutes), status: "pending", notes: notes || "", created_at: new Date().toISOString() });
  saveFakeAppointments(list);
  addFakeNotification(user.email, "confirmation", "Appointment request received", `${service.name} on ${fmtDate(date, { month: "short", day: "numeric" })} at ${fmtTime(time)}. The clinic will confirm it shortly.`);
  return { ok: true, appointment_id: id };
}

/* ---------- Notifications, change rules, cancel and reschedule (fake) ---------- */
const NOTIF_KEY = "dcq_fake_notifications";
const MIN_HOURS_BEFORE_CHANGE = 2;
function readFakeNotifications() {
  try { return JSON.parse(localStorage.getItem(NOTIF_KEY) || "[]"); } catch { return []; }
}
function addFakeNotification(email, type, subject, message) {
  const list = readFakeNotifications();
  list.unshift({ id: Date.now() + list.length, email, type, subject, message, created_at: new Date().toISOString() });
  localStorage.setItem(NOTIF_KEY, JSON.stringify(list.slice(0, 50)));
}

// Patients can change pending/confirmed visits that are more than 2 hours away
function canChangeAppointment(a) {
  if (a.can_change !== undefined) return a.can_change;
  if (!["pending", "confirmed"].includes(a.status)) return false;
  return new Date(`${a.date}T${a.start}:00`).getTime() - Date.now() >= MIN_HOURS_BEFORE_CHANGE * 3600000;
}

async function fakeCancel(id, reason, user) {
  await delay(300);
  const list = readFakeAppointments();
  const a = list.find((x) => x.id === id && x.patient_email === user.email);
  if (!a || !canChangeAppointment(a)) return { ok: false, error: "This appointment can no longer be cancelled online. Please call the clinic." };
  a.status = "cancelled"; a.cancellation_reason = reason || "";
  saveFakeAppointments(list);
  const svc = FAKE_SERVICES.find((s) => s.id === a.service_id);
  addFakeNotification(user.email, "cancellation", "Appointment cancelled", `${svc.name} on ${fmtDate(a.date, { month: "short", day: "numeric" })} was cancelled.`);
  return { ok: true };
}

async function fakeReschedule({ appointment_id, date, time, dentist_id, notes }, user) {
  await delay(500);
  const list = readFakeAppointments();
  const a = list.find((x) => x.id === appointment_id && x.patient_email === user.email);
  if (!a || !canChangeAppointment(a)) return { ok: false, error: "This appointment can no longer be changed online. Please call the clinic." };
  const service = FAKE_SERVICES.find((s) => s.id === a.service_id);
  const dentist = FAKE_DENTISTS.find((d) =>
    (dentist_id === 0 || d.id === dentist_id) && FAKE_DENTIST_SERVICES[d.id].includes(a.service_id) &&
    getSchedules()[d.id].days.includes(parseYmd(date).getDay()) && !fakeBusy(d.id, date, time, service.duration_minutes, a.id));
  if (!dentist) return { ok: false, error: "That time was just taken. Please choose another slot." };
  Object.assign(a, { dentist_id: dentist.id, date, start: time, end: fromMin(toMin(time) + service.duration_minutes), notes: notes || "", status: "pending" });
  saveFakeAppointments(list);
  addFakeNotification(user.email, "reschedule", "Appointment rescheduled", `${service.name} moved to ${fmtDate(date, { month: "short", day: "numeric" })} at ${fmtTime(time)}.`);
  return { ok: true };
}

/* ---------- Fake queue board (shared by the patient page and the staff pages) ---------- */
const BOARD_KEY = "dcq_fake_board";
const ACTIVE = ["waiting", "called", "serving"];
const minutesAgo = (m) => new Date(Date.now() - m * 60000).toISOString();

function readBoard() {
  try {
    const saved = JSON.parse(localStorage.getItem(BOARD_KEY));
    if (saved) return saved;
  } catch { /* fall through to the starting data */ }
  const row = (id, number, name, service, dentist, status, start, mins) => ({ id, number, patient_name: name, patient_email: "",
    service_name: service, dentist_name: dentist, status, start_time: start, since: minutesAgo(mins) });
  const seed = [
    row(1, 9, "Ana Reyes", "Check-up & Cleaning", "Dr. Elena Ramos", "serving", "09:00", 40),
    row(2, 10, "Joel Santos", "Tooth Extraction", "Dr. Marco Villanueva", "called", "09:30", 14),
    row(3, 11, "Liza Moreno", "Braces Consultation", "Dr. Sofia Lim", "waiting", "10:30", 12),
    row(4, 12, "Ben Torres", "Teeth Whitening", "Dr. Sofia Lim", "waiting", "11:00", 8),
    row(5, 13, "Carla Dizon", "Check-up & Cleaning", "Dr. Elena Ramos", "waiting", "11:30", 3),
  ];
  writeBoard(seed);
  return seed;
}
function writeBoard(list) { localStorage.setItem(BOARD_KEY, JSON.stringify(list)); }
const FAKE_AVG_WAIT = 18, FAKE_BASE_NO_SHOWS = 2;

// What the staff pages receive from staff-queue.php
function fakeStaffQueue() {
  ensureSeedAppointments();
  const board = readBoard();
  const entries = board.filter((e) => ACTIVE.includes(e.status)).sort((a, b) => a.number - b.number)
    .map((e) => ({ id: e.id, queue_number: e.number, patient_name: e.patient_name, service_name: e.service_name,
      dentist_name: e.dentist_name, status: e.status, start_time: e.start_time, waiting_minutes: Math.floor((Date.now() - new Date(e.since)) / 60000) }));
  const serving = entries.find((e) => e.status === "serving");
  const today = ymd(new Date());
  const inBoard = new Set(board.map((e) => e.appointment_id).filter(Boolean));
  const check_ins = readFakeAppointments().filter((a) => a.date === today && ["pending", "confirmed"].includes(a.status) && !inBoard.has(a.id))
    .map((a) => ({ appointment_id: a.id, start: a.start, patient_name: a.patient_name, status: a.status,
      service_name: FAKE_SERVICES.find((x) => x.id === a.service_id).name }));
  return { ok: true, entries, check_ins, stats: { in_queue: entries.length, now_serving: serving ? serving.queue_number : null,
    avg_wait_minutes: FAKE_AVG_WAIT, no_shows: FAKE_BASE_NO_SHOWS + board.filter((e) => e.status === "no_show").length } };
}

// action: call | start | serve | skip | no_show | call_next | check_in
function fakeQueueAction({ id, action, appointment_id }) {
  const board = readBoard();
  const e = board.find((x) => x.id === id);
  const set = (entry, status, msg) => {
    entry.status = status; entry.since = new Date().toISOString();
    if (entry.patient_email && msg) addFakeNotification(entry.patient_email, "reminder", msg[0], msg[1]);
  };
  if (action === "check_in") {
    const a = readFakeAppointments();
    const appt = a.find((x) => x.id === appointment_id);
    if (!appt) return { ok: false, error: "Appointment not found." };
    const number = board.reduce((m, x) => Math.max(m, x.number), 0) + 1;
    board.push({ id: board.reduce((m, x) => Math.max(m, x.id), 0) + 1, number, appointment_id: appt.id, patient_name: appt.patient_name,
      patient_email: appt.patient_email, service_name: FAKE_SERVICES.find((s) => s.id === appt.service_id).name,
      dentist_name: FAKE_DENTISTS.find((d) => d.id === appt.dentist_id).name, status: "waiting", start_time: appt.start, since: new Date().toISOString() });
    appt.status = "confirmed"; saveFakeAppointments(a);
    addFakeNotification(appt.patient_email, "reminder", "You're checked in", `Your queue number is ${formatQueueNumber(number)}.`);
  } else if (action === "call_next") {
    const next = board.filter((x) => x.status === "waiting").sort((a, b) => a.number - b.number)[0];
    if (!next) return { ok: false, error: "No one is waiting." };
    set(next, "called", ["You're next", "Please proceed to the clinic."]);
  } else if (!e) {
    return { ok: false, error: "Queue entry not found." };
  } else if (action === "call" && e.status === "waiting") set(e, "called", ["You're next", "Please proceed to the clinic."]);
  else if (action === "start" && e.status === "called") set(e, "serving", ["It's your turn", "The dentist is ready for you."]);
  else if (action === "serve" && e.status === "serving") set(e, "completed", ["Visit complete", "Thank you for visiting the clinic."]);
  else if (action === "skip" && ACTIVE.includes(e.status)) set(e, "skipped", ["Queue update", "Your turn was skipped. Please see the front desk."]);
  else if (action === "no_show" && ACTIVE.includes(e.status)) set(e, "no_show", ["Marked as no-show", "Please contact the clinic to rebook."]);
  else return { ok: false, error: "That action is not available for this patient." };
  writeBoard(board);
  return { ok: true };
}

/* ---------- Patient view of the same board (used by my-queue.js) ---------- */
function fakeQueueEntries(user) {
  const board = readBoard();
  const mine = board.filter((e) => e.patient_email === user.email && ACTIVE.includes(e.status)).sort((a, b) => a.number - b.number)[0];
  if (!mine) return [];
  const active = board.filter((e) => ACTIVE.includes(e.status));
  const serving = active.find((e) => e.status === "serving");
  const ahead = active.filter((e) => e.number < mine.number && e.status !== "serving").length;
  return [{ id: mine.id, queue_number: mine.number, status: mine.status, start_time: mine.start_time, service_name: mine.service_name,
    dentist_name: mine.dentist_name, ahead_count: ahead, wait_estimate: Math.round((ahead * 6.25) / 5) * 5, now_serving: serving ? serving.number : null }];
}
const formatQueueNumber = (n) => (typeof n === "string" ? n : `Q-${String(n).padStart(3, "0")}`);

// Demo buttons on the patient's Live Queue page (stand in for the front desk)
function writeFakeQueue(email, state) {
  if (state) return;
  writeBoard(readBoard().filter((e) => e.patient_email !== email));
}
function fakeCheckIn(user) {
  const board = readBoard();
  const number = board.reduce((m, x) => Math.max(m, x.number), 0) + 1;
  board.push({ id: board.reduce((m, x) => Math.max(m, x.id), 0) + 1, number, patient_name: user.name, patient_email: user.email,
    service_name: "Check-up & Cleaning", dentist_name: "Dr. Elena Ramos", status: "waiting", start_time: "10:00", since: new Date().toISOString() });
  writeBoard(board);
  addFakeNotification(user.email, "reminder", "You're checked in", `Your queue number is ${formatQueueNumber(number)}.`);
}
function fakeAdvanceQueue() {
  const board = readBoard();
  const active = board.filter((e) => ACTIVE.includes(e.status)).sort((a, b) => a.number - b.number);
  if (!active.length) return;
  active[0].status = "completed";
  if (active[1]) active[1].status = "serving";
  if (active[2]) active[2].status = "called";
  writeBoard(board);
}

/* ---------- Sample clinic activity (added once, so staff and admin pages are not empty) ---------- */
function ensureSeedAppointments() {
  if (localStorage.getItem("dcq_seeded")) return;
  localStorage.setItem("dcq_seeded", "1");
  const list = rawAppointments();
  let id = list.reduce((m, a) => Math.max(m, a.id), 0);
  const day = (off) => ymd(new Date(Date.now() + off * 86400000));
  const add = (off, start, svc, dent, name, status) => {
    const s = FAKE_SERVICES.find((x) => x.id === svc);
    id += 1;
    list.push({ id, patient_email: `${name.toLowerCase().replace(/\s+/g, ".")}@example.com`, patient_name: name,
      phone: "09" + String(100000000 + id * 7777777).slice(0, 9), service_id: svc, dentist_id: dent, date: day(off), start,
      end: fromMin(toMin(start) + s.duration_minutes), status, notes: "", created_at: new Date(Date.now() + (off - 1) * 86400000).toISOString() });
  };
  [[0, "09:00", 1, 1, "Ana Reyes", "confirmed"], [0, "09:30", 2, 2, "Joel Santos", "confirmed"], [0, "10:00", 1, 1, "Rina Flores", "pending"],
   [0, "10:30", 3, 3, "Liza Moreno", "pending"], [0, "11:00", 4, 3, "Ben Torres", "confirmed"], [1, "09:00", 5, 1, "Paolo Garcia", "pending"],
   [1, "14:00", 1, 1, "Nina Castro", "confirmed"], [-1, "09:00", 1, 1, "Carlo Mendoza", "completed"], [-1, "10:00", 5, 1, "Joy Ramos", "completed"],
   [-1, "13:30", 2, 2, "Ken Lopez", "no_show"], [-2, "09:30", 4, 3, "Mae Santos", "completed"], [-2, "11:00", 3, 3, "Dan Perez", "completed"],
   [-3, "14:00", 1, 1, "Lea Gomez", "cancelled"], [-3, "09:00", 1, 1, "Ron Diaz", "completed"], [-4, "10:00", 2, 2, "Tess Ong", "completed"],
   [-5, "15:00", 1, 1, "Vic Cruz", "completed"], [-6, "09:30", 5, 1, "Gia Lim", "completed"], [-6, "13:00", 1, 1, "Ed Tan", "no_show"],
  ].forEach((r) => add(...r));
  saveFakeAppointments(list);
  const board = readBoard();
  board.forEach((e) => { const a = list.find((x) => x.patient_name === e.patient_name && x.date === day(0)); if (a) e.appointment_id = a.id; });
  writeBoard(board);
}

/* ---------- Staff: appointments, walk-ins, notifications ---------- */
function fakeStaffAppointments() {
  return { ok: true, appointments: readFakeAppointments().map((a) => ({ id: a.id, date: a.date, start: a.start, end: a.end, status: a.status,
    patient_name: a.patient_name, phone: a.phone || "", notes: a.notes || "", service_name: FAKE_SERVICES.find((s) => s.id === a.service_id).name,
    dentist_name: FAKE_DENTISTS.find((d) => d.id === a.dentist_id).name })).sort((a, b) => (b.date + b.start).localeCompare(a.date + a.start)) };
}

// action: confirm | cancel | complete | no_show
function fakeAppointmentAction({ id, action }) {
  const list = readFakeAppointments();
  const a = list.find((x) => x.id === id);
  if (!a) return { ok: false, error: "Appointment not found." };
  const rules = { confirm: [["pending"], "confirmed"], cancel: [["pending", "confirmed"], "cancelled"],
    complete: [["confirmed"], "completed"], no_show: [["pending", "confirmed"], "no_show"] };
  const rule = rules[action];
  if (!rule || !rule[0].includes(a.status)) return { ok: false, error: "That action is not available for this appointment." };
  a.status = rule[1];
  saveFakeAppointments(list);
  const svc = FAKE_SERVICES.find((s) => s.id === a.service_id).name;
  if (action === "confirm") addFakeNotification(a.patient_email, "confirmation", "Appointment confirmed", `${svc} on ${fmtDate(a.date, { month: "short", day: "numeric" })} at ${fmtTime(a.start)} is confirmed.`);
  if (action === "cancel") addFakeNotification(a.patient_email, "cancellation", "Appointment cancelled", `${svc} on ${fmtDate(a.date, { month: "short", day: "numeric" })} was cancelled by the clinic.`);
  return { ok: true };
}

function fakeWalkIn({ name, phone, service_id, dentist_id, notes }) {
  const service = FAKE_SERVICES.find((s) => s.id === service_id);
  const dentist = FAKE_DENTISTS.find((d) => FAKE_DENTIST_SERVICES[d.id].includes(service_id) && (dentist_id === 0 || d.id === dentist_id));
  if (!service || !dentist) return { ok: false, error: "No dentist offers that service." };
  const list = readFakeAppointments();
  const id = list.reduce((m, a) => Math.max(m, a.id), 0) + 1;
  const now = new Date(), start = `${pad2(now.getHours())}:${pad2(now.getMinutes())}`;
  list.push({ id, patient_email: `walkin-${id}@clinic.local`, patient_name: name, phone: phone || "", service_id, dentist_id: dentist.id, date: ymd(now),
    start, end: fromMin(toMin(start) + service.duration_minutes), status: "confirmed", notes: notes || "Walk-in", created_at: now.toISOString() });
  saveFakeAppointments(list);
  const board = readBoard();
  const number = board.reduce((m, x) => Math.max(m, x.number), 0) + 1;
  board.push({ id: board.reduce((m, x) => Math.max(m, x.id), 0) + 1, number, appointment_id: id, patient_name: name, patient_email: "",
    service_name: service.name, dentist_name: dentist.name, status: "waiting", start_time: start, since: now.toISOString() });
  writeBoard(board);
  return { ok: true, queue_number: number };
}

function fakeStaffNotifications() {
  return { ok: true, notifications: readFakeNotifications().map((n) => ({ id: n.id, recipient: n.email, type: n.type, subject: n.subject, message: n.message, status: "sent", created_at: n.created_at })) };
}
function fakeSendReminders() {
  const list = readFakeAppointments();
  const tomorrow = ymd(new Date(Date.now() + 86400000));
  let sent = 0;
  list.filter((a) => a.date === tomorrow && ["pending", "confirmed"].includes(a.status) && !a.reminded).forEach((a) => {
    a.reminded = true; sent += 1;
    addFakeNotification(a.patient_email, "reminder", "Appointment reminder", `You have an appointment tomorrow at ${fmtTime(a.start)}.`);
  });
  saveFakeAppointments(list);
  return { ok: true, sent };
}