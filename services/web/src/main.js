import {
  ask,
  getIndicators,
  token,
  register,
  login,
  me,
  listAlerts,
  createAlert,
  deleteAlert,
} from "./api.js";

const INDICATOR_OPTIONS = ["selic", "cdi", "ipca", "usd", "poupanca"];

// Display config per indicator key (icon, caption, value formatter).
const DISPLAY = {
  selic: { icon: "📈", caption: "annual target", fmt: (v) => `${v}%` },
  ipca: { icon: "🛒", caption: "monthly inflation", fmt: (v) => `${v}%` },
  usd: { icon: "💵", caption: "PTAX buy", fmt: (v) => `R$ ${v}` },
};
const SHOWN = ["selic", "ipca", "usd"];

const SUGGESTIONS = [
  "How much does 10 thousand in savings yield in 1 year?",
  "What is the current Selic?",
  "How much is 1000 worth adjusted for inflation over 12 months?",
];

async function loadIndicators() {
  const el = document.getElementById("indicators");
  el.innerHTML = "";
  const valueEls = {};
  for (const key of SHOWN) {
    const d = DISPLAY[key];
    const card = document.createElement("div");
    card.className = "card";
    card.innerHTML =
      `<div class="card-top"><span class="card-icon">${d.icon}</span>` +
      `<span class="card-label">${key.toUpperCase()}</span></div>` +
      `<div class="card-value"><span class="skeleton"></span></div>` +
      `<div class="card-caption">${d.caption}</div>`;
    el.appendChild(card);
    valueEls[key] = card.querySelector(".card-value");
  }

  try {
    const { indicators } = await getIndicators();
    const byKey = Object.fromEntries(indicators.map((i) => [i.key, i]));
    for (const key of SHOWN) {
      const v = byKey[key]?.value;
      valueEls[key].textContent = v != null ? DISPLAY[key].fmt(v) : "—";
    }
  } catch {
    for (const key of SHOWN) valueEls[key].textContent = "—";
  }
}

function renderChips(onPick) {
  const el = document.getElementById("chips");
  for (const q of SUGGESTIONS) {
    const chip = document.createElement("button");
    chip.type = "button";
    chip.className = "chip";
    chip.textContent = q;
    chip.addEventListener("click", () => onPick(q));
    el.appendChild(chip);
  }
}

function wireChat() {
  const form = document.getElementById("ask-form");
  const input = document.getElementById("question");
  const button = form.querySelector("button");
  const answer = document.getElementById("answer");

  async function submit(question) {
    answer.classList.add("show");
    answer.innerHTML = `<div class="typing"><span></span><span></span><span></span></div>`;
    button.disabled = true;
    try {
      const res = await ask(question);
      const src = res.sources?.[0];
      answer.innerHTML =
        `<p>${res.answer}</p>` +
        (src ? `<small>Source: ${src.name} · series ${src.series} (${src.label})</small>` : "");
    } catch (err) {
      answer.innerHTML = `<p>Sorry — ${err.message}</p>`;
    } finally {
      button.disabled = false;
    }
  }

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const q = input.value.trim();
    if (q) submit(q);
  });

  renderChips((q) => { input.value = q; submit(q); });
}

// ── small DOM/validation helpers ────────────────────────────────────────────
const $ = (id) => document.getElementById(id);
const val = (id) => $(id).value;
const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
const setText = (id, text) => { const e = $(id); if (e) e.textContent = text; };
function setMsg(id, text, ok = false) {
  const e = $(id);
  if (e) { e.textContent = text; e.className = `form-msg ${ok ? "ok" : "err"}`; }
}
async function submitting(btn, label, fn) {
  const original = btn.textContent;
  btn.disabled = true;
  btn.textContent = label;
  try { await fn(); } finally { btn.disabled = false; btn.textContent = original; }
}

// ── auth modal ───────────────────────────────────────────────────────────────
let authMode = "login";
let registerEmail = "";
const modal = () => $("auth-modal");

function openAuthModal(mode) {
  authMode = mode;
  registerEmail = "";
  if (mode === "register") renderRegisterStep1();
  else renderLogin();
  modal().showModal();
}
const closeAuthModal = () => modal().close();

function modalShell(inner) {
  return (
    `<div class="modal-head"><div class="tabs">` +
    `<button class="tab ${authMode === "login" ? "active" : ""}" id="tab-login">Log in</button>` +
    `<button class="tab ${authMode === "register" ? "active" : ""}" id="tab-register">Sign up</button>` +
    `</div><button class="modal-close" id="modal-close" aria-label="Close">×</button></div>` +
    inner
  );
}

function wireShell() {
  $("modal-close").addEventListener("click", closeAuthModal);
  $("tab-login").addEventListener("click", renderLogin);
  $("tab-register").addEventListener("click", renderRegisterStep1);
}

function renderLogin() {
  authMode = "login";
  $("auth-modal-body").innerHTML = modalShell(
    `<form id="login-form" class="modal-form" novalidate>` +
    `<label>Email<input id="li-email" type="email" autocomplete="username" placeholder="you@example.com" /></label>` +
    `<p class="field-err" id="li-email-err"></p>` +
    `<label>Password<input id="li-pass" type="password" autocomplete="current-password" /></label>` +
    `<p class="field-err" id="li-pass-err"></p>` +
    `<button type="submit" class="full">Log in</button>` +
    `<p class="form-msg" id="li-msg"></p></form>`,
  );
  wireShell();
  $("login-form").addEventListener("submit", onLogin);
}

async function onLogin(e) {
  e.preventDefault();
  const email = val("li-email").trim();
  const pass = val("li-pass");
  setText("li-email-err", isEmail(email) ? "" : "Enter a valid email.");
  setText("li-pass-err", pass ? "" : "Enter your password.");
  if (!isEmail(email) || !pass) return;

  await submitting(e.submitter, "Logging in…", async () => {
    try {
      const { token: t } = await login(email, pass);
      token.set(t);
      closeAuthModal();
      await refreshAuthUI();
    } catch (err) {
      setMsg("li-msg", err.message);
    }
  });
}

function renderRegisterStep1() {
  authMode = "register";
  $("auth-modal-body").innerHTML = modalShell(
    `<div class="stepper">Step 1 of 2 · Your email</div>` +
    `<form id="reg1-form" class="modal-form" novalidate>` +
    `<label>Email<input id="r1-email" type="email" autocomplete="username" placeholder="you@example.com" value="${registerEmail}" /></label>` +
    `<p class="field-err" id="r1-email-err"></p>` +
    `<button type="submit" class="full">Continue →</button></form>`,
  );
  wireShell();
  $("reg1-form").addEventListener("submit", (e) => {
    e.preventDefault();
    const email = val("r1-email").trim();
    if (!isEmail(email)) { setText("r1-email-err", "Enter a valid email."); return; }
    registerEmail = email;
    renderRegisterStep2();
  });
}

function renderRegisterStep2() {
  $("auth-modal-body").innerHTML = modalShell(
    `<div class="stepper">Step 2 of 2 · Choose a password</div>` +
    `<form id="reg2-form" class="modal-form" novalidate>` +
    `<p class="muted-line">Creating account for <strong>${registerEmail}</strong></p>` +
    `<label>Password<input id="r2-pass" type="password" autocomplete="new-password" /></label>` +
    `<ul class="pw-reqs"><li id="req-len">At least 8 characters</li></ul>` +
    `<label>Confirm password<input id="r2-confirm" type="password" autocomplete="new-password" /></label>` +
    `<p class="field-err" id="r2-err"></p>` +
    `<div class="step-actions"><button type="button" class="ghost" id="r2-back">← Back</button>` +
    `<button type="submit">Create account</button></div>` +
    `<p class="form-msg" id="r2-msg"></p></form>`,
  );
  wireShell();
  $("r2-back").addEventListener("click", renderRegisterStep1);
  const pass = $("r2-pass");
  pass.addEventListener("input", () => {
    $("req-len").classList.toggle("ok", pass.value.length >= 8);
  });
  $("reg2-form").addEventListener("submit", onRegister);
}

async function onRegister(e) {
  e.preventDefault();
  const pass = val("r2-pass");
  const confirm = val("r2-confirm");
  if (pass.length < 8) { setText("r2-err", "Password must be at least 8 characters."); return; }
  if (pass !== confirm) { setText("r2-err", "Passwords do not match."); return; }
  setText("r2-err", "");

  await submitting(e.submitter, "Creating…", async () => {
    try {
      await register(registerEmail, pass);
      const { token: t } = await login(registerEmail, pass);
      token.set(t);
      closeAuthModal();
      await refreshAuthUI();
    } catch (err) {
      setMsg("r2-msg", err.message);
    }
  });
}

// ── auth controls (navbar) + alerts panel ───────────────────────────────────
function renderAuthControls(user) {
  const el = $("auth-controls");
  if (user) {
    el.innerHTML = `<span class="who">${user.email}</span><button class="ghost" id="nav-logout">Log out</button>`;
    $("nav-logout").addEventListener("click", () => { token.clear(); refreshAuthUI(); });
  } else {
    el.innerHTML = `<button class="ghost" id="nav-login">Log in</button><button id="nav-register">Sign up</button>`;
    $("nav-login").addEventListener("click", () => openAuthModal("login"));
    $("nav-register").addEventListener("click", () => openAuthModal("register"));
  }
}

function renderAlertsPanel(loggedIn) {
  const el = $("alerts-panel");
  if (!loggedIn) {
    el.innerHTML =
      `<div class="empty-cta"><p>Log in to create price alerts for Selic, USD, IPCA and more.</p>` +
      `<button id="alerts-login">Log in or sign up</button></div>`;
    $("alerts-login").addEventListener("click", () => openAuthModal("login"));
    return;
  }
  el.innerHTML =
    `<form id="alert-form" class="alert-form">` +
    `<select id="al-indicator">${INDICATOR_OPTIONS.map((i) => `<option>${i}</option>`).join("")}</select>` +
    `<select id="al-op"><option value=">">&gt;</option><option value="<">&lt;</option></select>` +
    `<input id="al-threshold" type="number" step="0.01" placeholder="threshold" />` +
    `<button type="submit">Add alert</button></form>` +
    `<ul id="alert-list" class="alert-list"></ul>`;
  $("alert-form").addEventListener("submit", onCreateAlert);
  loadAlerts();
}

async function onCreateAlert(e) {
  e.preventDefault();
  const threshold = parseFloat(val("al-threshold"));
  if (Number.isNaN(threshold)) { $("al-threshold").placeholder = "enter a number"; return; }
  try {
    await createAlert({
      indicator: val("al-indicator"),
      operator: val("al-op"),
      threshold,
      channel: "log",
    });
    $("al-threshold").value = "";
    loadAlerts();
  } catch (err) {
    $("al-threshold").value = "";
    $("al-threshold").placeholder = err.message;
  }
}

async function loadAlerts() {
  const list = $("alert-list");
  if (!list) return;
  try {
    const { alerts } = await listAlerts();
    if (!alerts.length) {
      list.innerHTML = `<li class="empty">No alerts yet — add one above.</li>`;
      return;
    }
    list.innerHTML = "";
    for (const a of alerts) {
      const li = document.createElement("li");
      li.innerHTML = `<span><strong>${a.indicator.toUpperCase()}</strong> ${a.operator} ${a.threshold}</span>`;
      const del = document.createElement("button");
      del.className = "ghost";
      del.textContent = "Delete";
      del.addEventListener("click", async () => { await deleteAlert(a.id); loadAlerts(); });
      li.appendChild(del);
      list.appendChild(li);
    }
  } catch (err) {
    list.innerHTML = `<li class="empty">${err.message}</li>`;
  }
}

async function refreshAuthUI() {
  if (!token.get()) {
    renderAuthControls(null);
    renderAlertsPanel(false);
    return;
  }
  try {
    const user = await me();
    renderAuthControls(user);
    renderAlertsPanel(true);
  } catch {
    token.clear();
    renderAuthControls(null);
    renderAlertsPanel(false);
  }
}

loadIndicators();
wireChat();
refreshAuthUI();
// Close the modal when clicking the backdrop.
modal().addEventListener("click", (e) => { if (e.target === modal()) closeAuthModal(); });
