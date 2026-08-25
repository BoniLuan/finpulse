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
  listHistory,
  deleteHistory,
} from "./api.js";

const SESSION_HISTORY_KEY = "finpulse_session_history";
const THEME_KEY = "finpulse_theme";
let historyExpanded = false;

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  const toggle = document.getElementById("theme-toggle");
  if (toggle) {
    toggle.textContent = theme === "dark" ? "☀️" : "🌙";
    toggle.title = `Switch to ${theme === "dark" ? "light" : "dark"} mode`;
  }
}

function wireTheme() {
  const saved = localStorage.getItem(THEME_KEY);
  const initial = saved || (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
  applyTheme(initial);
  $("theme-toggle").addEventListener("click", () => {
    const next = document.documentElement.dataset.theme === "dark" ? "light" : "dark";
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
  });
}

const INDICATOR_OPTIONS = ["selic", "cdi", "ipca", "usd", "poupanca"];

// Display config per indicator key (icon, caption, value formatter).
const DISPLAY = {
  selic: { icon: "📈", caption: "annual target", fmt: (v) => `${v}%` },
  ipca: { icon: "🛒", caption: "monthly inflation", fmt: (v) => `${v}%` },
  usd: { icon: "💵", caption: "PTAX buy", fmt: (v) => `R$ ${v}` },
  btc: { icon: "₿", caption: "spot price in BRL", fmt: (v) => v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) },
  eth: { icon: "◆", caption: "spot price in BRL", fmt: (v) => v.toLocaleString("pt-BR", { style: "currency", currency: "BRL" }) },
};
const SHOWN = ["selic", "ipca", "usd", "btc", "eth"];

const SUGGESTIONS = [
  "How much does 10 thousand in savings yield in 1 year?",
  "What is the current Selic?",
  "How much is 1000 worth adjusted for inflation over 12 months?",
  "How much would R$ 5,000 earn in a CDB at 110% of CDI?",
  "How much does R$ 8,000 in Tesouro Selic yield over 18 months?",
  "What is the current dollar exchange rate?",
  "How has inflation affected R$ 2,500 over the last year?",
  "What is the current CDI rate?",
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
  el.innerHTML =
    `<div class="suggestions-head"><span>Try a question</span><div class="suggestion-controls">` +
    `<button type="button" class="ghost" id="suggestion-prev" aria-label="Previous suggestions">←</button>` +
    `<button type="button" class="ghost" id="suggestion-next" aria-label="Next suggestions">→</button>` +
    `</div></div><div class="suggestion-viewport"><div class="suggestion-track"></div></div>`;
  const viewport = el.querySelector(".suggestion-viewport");
  const track = el.querySelector(".suggestion-track");
  for (const q of SUGGESTIONS) {
    const chip = document.createElement("button");
    chip.type = "button";
    chip.className = "chip suggestion-card";
    chip.textContent = q;
    chip.addEventListener("click", () => onPick(q));
    track.appendChild(chip);
  }

  const move = (direction) => {
    const distance = Math.max(viewport.clientWidth * 0.82, 240);
    const atEnd = viewport.scrollLeft + viewport.clientWidth >= viewport.scrollWidth - 8;
    const atStart = viewport.scrollLeft <= 8;
    if (direction > 0 && atEnd) viewport.scrollTo({ left: 0, behavior: "smooth" });
    else if (direction < 0 && atStart) viewport.scrollTo({ left: viewport.scrollWidth, behavior: "smooth" });
    else viewport.scrollBy({ left: distance * direction, behavior: "smooth" });
  };
  $("suggestion-prev").addEventListener("click", () => move(-1));
  $("suggestion-next").addEventListener("click", () => move(1));

  if (!matchMedia("(prefers-reduced-motion: reduce)").matches) {
    let timer = setInterval(() => move(1), 6500);
    const pause = () => clearInterval(timer);
    const resume = () => { clearInterval(timer); timer = setInterval(() => move(1), 6500); };
    el.addEventListener("mouseenter", pause);
    el.addEventListener("mouseleave", resume);
    el.addEventListener("focusin", pause);
    el.addEventListener("focusout", resume);
  }
}

function wireVoiceInput(input) {
  const button = $("voice-input");
  const status = $("voice-status");
  const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!Recognition) {
    button.hidden = true;
    status.textContent = "Voice dictation is not available in this browser.";
    return;
  }

  const recognition = new Recognition();
  recognition.continuous = false;
  recognition.interimResults = true;
  recognition.maxAlternatives = 1;
  recognition.lang = navigator.language || "pt-BR";
  let listening = false;
  let startingText = "";

  const setListening = (active) => {
    listening = active;
    button.classList.toggle("listening", active);
    button.setAttribute("aria-pressed", String(active));
    button.title = active ? "Stop listening" : "Dictate a question";
    status.textContent = active
      ? "Listening… speak your question."
      : "Voice is transcribed by your browser and remains editable.";
  };

  recognition.addEventListener("start", () => setListening(true));
  recognition.addEventListener("end", () => {
    setListening(false);
    input.focus();
  });
  recognition.addEventListener("result", (event) => {
    let transcript = "";
    for (let index = event.resultIndex; index < event.results.length; index += 1) {
      transcript += event.results[index][0].transcript;
    }
    input.value = [startingText, transcript.trim()].filter(Boolean).join(" ");
  });
  recognition.addEventListener("error", (event) => {
    const messages = {
      "not-allowed": "Microphone permission was denied.",
      "audio-capture": "No microphone was found.",
      "no-speech": "No speech was detected. Try again.",
      network: "The browser speech service is unavailable.",
    };
    status.textContent = messages[event.error] || "Voice dictation could not start.";
  });

  button.addEventListener("click", () => {
    if (listening) {
      recognition.stop();
      return;
    }
    startingText = input.value.trim();
    try { recognition.start(); }
    catch { status.textContent = "Voice dictation is already starting."; }
  });

  status.textContent = "Voice is transcribed by your browser and remains editable.";
}

function wireChat() {
  const form = document.getElementById("ask-form");
  const input = document.getElementById("question");
  const button = form.querySelector('button[type="submit"]');
  const answer = document.getElementById("answer");
  wireVoiceInput(input);

  function showAnswer(result) {
    const text = document.createElement("p");
    text.textContent = result.answer;
    answer.replaceChildren(text);
    const source = result.sources?.[0];
    if (source) {
      const citation = document.createElement("small");
      citation.textContent = `Source: ${source.name} · series ${source.series} (${source.label})`;
      answer.appendChild(citation);
    }
  }

  async function submit(question) {
    answer.classList.add("show");
    answer.innerHTML = `<div class="typing"><span></span><span></span><span></span></div>`;
    button.disabled = true;
    try {
      const res = await ask(question);
      if (!token.get()) saveSessionHistory({ ...res, question, created_at: new Date().toISOString() });
      showAnswer(res);
      await renderHistoryPanel(Boolean(token.get()));
    } catch (err) {
      const message = document.createElement("p");
      message.textContent = `Sorry — ${err.message}`;
      answer.replaceChildren(message);
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

function sessionHistory() {
  try { return JSON.parse(sessionStorage.getItem(SESSION_HISTORY_KEY) || "[]"); }
  catch { return []; }
}

function saveSessionHistory(row) {
  const rows = sessionHistory();
  rows.unshift(row);
  sessionStorage.setItem(SESSION_HISTORY_KEY, JSON.stringify(rows));
}

function syncContextRail() {
  const rail = $("context-rail");
  const visible = !$("alerts-section").hidden;
  rail.hidden = !visible;
  $("assistant").classList.toggle("no-context", !visible);
}

async function renderHistoryPanel(loggedIn) {
  const panel = $("history-panel");
  let rows = [];
  try { rows = loggedIn ? (await listHistory()).history : sessionHistory(); }
  catch { rows = []; }
  $("history-section").hidden = rows.length === 0;
  syncContextRail();
  if (!rows.length) return;
  if (historyExpanded) {
    await loadHistory(loggedIn, rows);
    return;
  }
  panel.innerHTML = `<div class="history-gate"><p>${loggedIn ? "Your saved conversations are available on this account." : "Questions from this browser session stay only in this tab."}</p>` +
    `<button id="show-history" class="ghost">Show history</button></div>`;
  $("show-history").addEventListener("click", () => {
    historyExpanded = true;
    loadHistory(loggedIn, rows);
  });
}

async function loadHistory(loggedIn, knownRows = null) {
  const panel = $("history-panel");
  panel.innerHTML = `<div class="typing"><span></span><span></span><span></span></div>`;
  try {
    const rows = knownRows ?? (loggedIn ? (await listHistory()).history : sessionHistory());
    if (!rows.length) {
      $("history-section").hidden = true;
      syncContextRail();
      return;
    }
    const list = document.createElement("ol");
    list.className = "history-list";
    for (const row of rows) {
      const item = document.createElement("li");
      const text = document.createElement("div");
      const question = document.createElement("strong");
      const answerText = document.createElement("p");
      question.textContent = row.question;
      answerText.textContent = row.answer;
      text.append(question, answerText);
      const del = document.createElement("button");
      del.className = "ghost";
      del.textContent = "Delete";
      del.addEventListener("click", async () => {
        if (loggedIn) await deleteHistory(row.id);
        else sessionStorage.setItem(SESSION_HISTORY_KEY, JSON.stringify(sessionHistory().filter((entry) => entry.id !== row.id)));
        loadHistory(loggedIn);
      });
      item.append(text, del);
      list.appendChild(item);
    }
    panel.replaceChildren(list);
  } catch (err) {
    panel.textContent = err.message;
  }
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
    $("alerts-section").hidden = true;
    el.replaceChildren();
    syncContextRail();
    return;
  }
  $("alerts-section").hidden = false;
  syncContextRail();
  el.innerHTML =
    `<form id="alert-form" class="alert-form">` +
    `<select id="al-indicator">${INDICATOR_OPTIONS.map((i) => `<option>${i}</option>`).join("")}</select>` +
    `<select id="al-op"><option value=">">&gt;</option><option value="<">&lt;</option></select>` +
    `<input id="al-threshold" type="number" step="0.01" placeholder="threshold" />` +
    `<button type="submit">Add alert</button></form>` +
    `<div id="alert-history-gate" class="history-gate"><p>Previous alerts are hidden when a session starts.</p>` +
    `<button id="show-alerts" type="button" class="ghost">Show my alerts</button></div>` +
    `<ul id="alert-list" class="alert-list"></ul>`;
  $("alert-form").addEventListener("submit", onCreateAlert);
  $("show-alerts").addEventListener("click", loadAlerts);
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
  const gate = $("alert-history-gate");
  if (gate) gate.remove();
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
    await renderHistoryPanel(false);
    return;
  }
  try {
    const user = await me();
    renderAuthControls(user);
    renderAlertsPanel(true);
    await renderHistoryPanel(true);
  } catch {
    token.clear();
    renderAuthControls(null);
    renderAlertsPanel(false);
    await renderHistoryPanel(false);
  }
}

loadIndicators();
wireChat();
wireTheme();
refreshAuthUI();
// Close the modal when clicking the backdrop.
modal().addEventListener("click", (e) => { if (e.target === modal()) closeAuthModal(); });
