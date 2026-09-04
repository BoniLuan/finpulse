import {
  ask,
  getIndicators,
  getSelicIpcaComparison,
  token,
  register,
  login,
  me,
  updateMe,
  listAlerts,
  createAlert,
  deleteAlert,
  listHistory,
  deleteHistory,
} from "./api.js";

const SESSION_HISTORY_KEY = "finpulse_session_history";
const SESSION_HISTORY_EXPANDED_KEY = "finpulse_history_expanded";
const SESSION_ALERTS_EXPANDED_KEY = "finpulse_alerts_expanded";
const THEME_KEY = "finpulse_theme";
let historyExpanded = sessionStorage.getItem(SESSION_HISTORY_EXPANDED_KEY) === "true";
let alertsExpanded = sessionStorage.getItem(SESSION_ALERTS_EXPANDED_KEY) === "true";

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

const ALERT_INDICATORS = {
  selic: "Selic rate",
  cdi: "CDI rate",
  ipca: "IPCA inflation",
  usd: "USD/BRL",
  poupanca: "Savings yield",
  btc: "Bitcoin / BRL",
  eth: "Ethereum / BRL",
};

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

function renderMacroInsights(summary) {
  const insights = $("macro-insights");
  const metrics = [
    ["Selic change", summary.selic_change_pp, " pp"],
    ["Accumulated IPCA", summary.ipca_accumulated_pct, "%"],
    ["Estimated real rate", summary.real_rate_latest_pct, "% p.a."],
    ["Monthly correlation", summary.monthly_correlation, ""],
  ];
  insights.innerHTML = metrics.map(([label, value, suffix]) =>
    `<article><span>${label}</span><strong>${value == null ? "—" : `${Number(value).toFixed(2)}${suffix}`}</strong></article>`,
  ).join("");
}

function renderMacroChart(series) {
  const chart = $("macro-chart");
  const populated = series.filter((item) => item.observations.length);
  const points = populated.flatMap((item) => item.observations);
  if (!points.length) {
    chart.innerHTML = `<p class="chart-empty">Historical data is being collected. Check back shortly.</p>`;
    return;
  }

  const width = 1000;
  const height = 300;
  const padding = 48;
  const timestamps = points.map((point) => Date.parse(point.date));
  const minDate = Math.min(...timestamps);
  const maxDate = Math.max(...timestamps);
  const x = (date) => padding + ((Date.parse(date) - minDate) / Math.max(1, maxDate - minDate)) * (width - padding * 2);
  const scaleFor = (observations) => {
    const values = observations.map((point) => point.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const margin = Math.max((max - min) * 0.12, 0.1);
    const low = min - margin;
    const high = max + margin;
    return {
      low,
      high,
      y: (value) => height - padding - ((value - low) / Math.max(0.01, high - low)) * (height - padding * 2),
    };
  };
  const scaled = populated.map((item) => ({ ...item, scale: scaleFor(item.observations) }));
  const path = (item) => item.observations
    .map((point, index) => `${index ? "L" : "M"}${x(point.date).toFixed(1)},${item.scale.y(point.value).toFixed(1)}`)
    .join(" ");
  const grid = [0, 0.25, 0.5, 0.75, 1].map((ratio) => {
    const gridY = padding + ratio * (height - padding * 2);
    return `<line x1="${padding}" y1="${gridY}" x2="${width - padding}" y2="${gridY}"/>`;
  }).join("");
  chart.innerHTML = `<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="Historical Selic and IPCA trend comparison with independent scales">` +
    `<g class="chart-grid">${grid}</g>` +
    scaled.map((item) => `<path class="chart-line ${item.indicator.key}" d="${path(item)}"/>`).join("") +
    `</svg>`;
}
async function loadMacroHistory() {
  const chart = $("macro-chart");
  const months = Number($("history-period").value);
  chart.innerHTML = `<span class="skeleton chart-skeleton"></span>`;
  $("macro-insights").innerHTML = "";
  try {
    const comparison = await getSelicIpcaComparison(months);
    const series = comparison.series;
    renderMacroInsights(comparison.summary);
    renderMacroChart(series);
    const total = series.reduce((sum, item) => sum + item.observations.length, 0);
    $("macro-chart-status").textContent = `${total} normalized observations · independent visual scales · persisted in PostgreSQL`;
  } catch (error) {
    chart.innerHTML = `<p class="chart-empty">Historical series are temporarily unavailable.</p>`;
    $("macro-chart-status").textContent = error.message;
  }
}

function wireMacroHistory() {
  $("history-period").addEventListener("change", loadMacroHistory);
  loadMacroHistory();
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
    if (!token.get()) {
      answer.classList.add("show");
      answer.innerHTML = "<p>Log in or create an account to use the AI assistant.</p>";
      openAuthModal("login");
      return;
    }
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

async function renderHistoryPanel(loggedIn) {
  const panel = $("history-panel");
  let rows = [];
  try { rows = loggedIn ? (await listHistory()).history : sessionHistory(); }
  catch { rows = []; }
  $("history-section").hidden = rows.length === 0;
  if (!rows.length) return;
  if (historyExpanded) {
    await loadHistory(loggedIn, rows);
    return;
  }
  panel.innerHTML = `<div class="history-gate"><p>${loggedIn ? "Your saved conversations are available on this account." : "Questions from this browser session stay only in this tab."}</p>` +
    `<button id="show-history" class="ghost">Show history</button></div>`;
  $("show-history").addEventListener("click", () => {
    historyExpanded = true;
    sessionStorage.setItem(SESSION_HISTORY_EXPANDED_KEY, "true");
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
    const toolbar = document.createElement("div");
    toolbar.className = "history-toolbar";
    const hide = document.createElement("button");
    hide.type = "button";
    hide.className = "ghost";
    hide.textContent = "Hide history";
    hide.addEventListener("click", () => {
      historyExpanded = false;
      sessionStorage.removeItem(SESSION_HISTORY_EXPANDED_KEY);
      renderHistoryPanel(loggedIn);
    });
    toolbar.appendChild(hide);
    panel.replaceChildren(toolbar, list);
  } catch (err) {
    panel.textContent = err.message;
  }
}

// ── small DOM/validation helpers ────────────────────────────────────────────
const $ = (id) => document.getElementById(id);
const val = (id) => $(id).value;
const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
  "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;",
})[char]);
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
let registerName = "";
let registerPhone = "";
let currentUser = null;
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
    `<div class="stepper">Step 1 of 2 · Your profile</div>` +
    `<form id="reg1-form" class="modal-form" novalidate>` +
    `<label>Name or nickname<input id="r1-name" autocomplete="name" placeholder="How should we call you?" value="${escapeHtml(registerName)}" /></label>` +
    `<p class="field-err" id="r1-name-err"></p>` +
    `<label>Email<input id="r1-email" type="email" autocomplete="username" placeholder="you@example.com" value="${escapeHtml(registerEmail)}" /></label>` +
    `<p class="field-err" id="r1-email-err"></p>` +
    `<label>Mobile number <span class="optional">optional · include country code</span><input id="r1-phone" type="tel" autocomplete="tel" placeholder="+55 11 99999-9999" value="${escapeHtml(registerPhone)}" /></label>` +
    `<p class="field-err" id="r1-phone-err"></p>` +
    `<button type="submit" class="full">Continue →</button></form>`,
  );
  wireShell();
  $("reg1-form").addEventListener("submit", (e) => {
    e.preventDefault();
    const name = val("r1-name").trim();
    const email = val("r1-email").trim();
    const phone = val("r1-phone").trim();
    const phoneDigits = phone.replace(/\D/g, "");
    setText("r1-name-err", name.length >= 2 ? "" : "Enter at least 2 characters.");
    setText("r1-phone-err", !phone || (phoneDigits.length >= 10 && phoneDigits.length <= 15) ? "" : "Include country code and 10 to 15 digits.");
    if (!isEmail(email)) { setText("r1-email-err", "Enter a valid email."); return; }
    if (name.length < 2 || (phone && (phoneDigits.length < 10 || phoneDigits.length > 15))) return;
    registerName = name;
    registerEmail = email;
    registerPhone = phone;
    renderRegisterStep2();
  });
}

function renderRegisterStep2() {
  $("auth-modal-body").innerHTML = modalShell(
    `<div class="stepper">Step 2 of 2 · Choose a password</div>` +
    `<form id="reg2-form" class="modal-form" novalidate>` +
    `<p class="muted-line">Creating account for <strong>${escapeHtml(registerEmail)}</strong></p>` +
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
      await register(registerEmail, pass, registerName, registerPhone);
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
    const profileLabel = user.display_name || user.email;
    el.innerHTML = `<button class="ghost profile-button" id="nav-profile" title="Profile · ${escapeHtml(profileLabel)}" aria-label="Open profile for ${escapeHtml(profileLabel)}"><span>${escapeHtml(profileLabel)}</span></button><button class="ghost" id="nav-logout">Log out</button>`;
    $("nav-profile").addEventListener("click", renderProfile);
    $("nav-logout").addEventListener("click", () => { token.clear(); refreshAuthUI(); });
  } else {
    el.innerHTML = `<button class="ghost" id="nav-login">Log in</button><button id="nav-register">Sign up</button>`;
    $("nav-login").addEventListener("click", () => openAuthModal("login"));
    $("nav-register").addEventListener("click", () => openAuthModal("register"));
  }
}

function renderProfile() {
  if (!currentUser) return;
  $("auth-modal-body").innerHTML =
    `<div class="modal-head"><div><span class="eyebrow">Account</span><h2>Your profile</h2></div><button class="modal-close" id="modal-close" aria-label="Close">×</button></div>` +
    `<form id="profile-form" class="modal-form" novalidate>` +
    `<label>Name or nickname<input id="profile-name" autocomplete="name" value="${escapeHtml(currentUser.display_name || "")}" /></label>` +
    `<label>Email<input value="${escapeHtml(currentUser.email)}" disabled /></label>` +
    `<label>WhatsApp mobile <span class="optional">optional · include country code</span><input id="profile-phone" type="tel" autocomplete="tel" placeholder="+55 11 99999-9999" value="${escapeHtml(currentUser.phone || "")}" /></label>` +
    `<p class="form-msg" id="profile-msg"></p><button type="submit" class="full">Save profile</button></form>`;
  $("modal-close").addEventListener("click", closeAuthModal);
  $("profile-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    await submitting(event.submitter, "Saving…", async () => {
      try {
        currentUser = await updateMe(val("profile-name").trim(), val("profile-phone").trim());
        closeAuthModal();
        renderAuthControls(currentUser);
        renderAlertsPanel(currentUser);
      } catch (error) { setMsg("profile-msg", error.message); }
    });
  });
  modal().showModal();
}

function renderAlertsPanel(user) {
  const el = $("alerts-panel");
  if (!user) {
    $("alerts-section").hidden = true;
    el.replaceChildren();
    return;
  }
  $("alerts-section").hidden = false;
  el.innerHTML =
    `<form id="alert-form" class="alert-form">` +
    `<label>Market<select id="al-indicator">${Object.entries(ALERT_INDICATORS).map(([value, label]) => `<option value="${value}">${label}</option>`).join("")}</select></label>` +
    `<label>Condition<select id="al-op"><option value=">">Rises above</option><option value="<">Falls below</option></select></label>` +
    `<label>Target value<input id="al-threshold" type="number" step="0.01" placeholder="Enter a value" /></label>` +
    `<label>Notify by<select id="al-channel"><option value="log">Application log</option><option value="email">Email · ${escapeHtml(user.email)}</option><option value="whatsapp" ${user.phone ? "" : "disabled"}>WhatsApp${user.phone ? ` · +${escapeHtml(user.phone)}` : " · add phone in Profile"}</option></select></label>` +
    `<button type="submit">Create alert</button></form><p id="alert-form-status" class="form-msg" aria-live="polite"></p>` +
    `${alertsExpanded ? "" : `<div id="alert-history-gate" class="history-gate"><p>Saved alerts stay hidden until you choose to load them.</p><button id="show-alerts" type="button" class="ghost">Show saved alerts</button></div>`}` +
    `<ul id="alert-list" class="alert-list"></ul>`;
  $("alert-form").addEventListener("submit", onCreateAlert);
  if (alertsExpanded) loadAlerts();
  else $("show-alerts").addEventListener("click", () => {
    alertsExpanded = true;
    sessionStorage.setItem(SESSION_ALERTS_EXPANDED_KEY, "true");
    loadAlerts();
  });
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
      channel: val("al-channel"),
    });
    $("al-threshold").value = "";
    setMsg("alert-form-status", "Alert created.", true);
    if (alertsExpanded) loadAlerts();
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
    let toolbar = $("alert-list-toolbar");
    if (!toolbar) {
      toolbar = document.createElement("div");
      toolbar.id = "alert-list-toolbar";
      toolbar.className = "history-toolbar";
      const hide = document.createElement("button");
      hide.type = "button";
      hide.className = "ghost";
      hide.textContent = "Hide saved alerts";
      hide.addEventListener("click", () => {
        alertsExpanded = false;
        sessionStorage.removeItem(SESSION_ALERTS_EXPANDED_KEY);
        renderAlertsPanel(currentUser);
      });
      toolbar.appendChild(hide);
      list.before(toolbar);
    }
    if (!alerts.length) {
      list.innerHTML = `<li class="empty">No alerts yet — add one above.</li>`;
      return;
    }
    list.innerHTML = "";
    for (const a of alerts) {
      const li = document.createElement("li");
      const condition = a.operator === ">" ? "rises above" : "falls below";
      li.innerHTML = `<span><strong>${ALERT_INDICATORS[a.indicator] || a.indicator.toUpperCase()}</strong> ${condition} ${a.threshold} <small>via ${a.channel}</small></span>`;
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
    currentUser = null;
    renderAuthControls(null);
    renderAlertsPanel(false);
    await renderHistoryPanel(false);
    return;
  }
  try {
    const user = await me();
    currentUser = user;
    renderAuthControls(user);
    renderAlertsPanel(user);
    await renderHistoryPanel(true);
  } catch {
    token.clear();
    currentUser = null;
    renderAuthControls(null);
    renderAlertsPanel(false);
    await renderHistoryPanel(false);
  }
}

loadIndicators();
wireMacroHistory();
wireChat();
wireTheme();
refreshAuthUI();
// Close the modal when clicking the backdrop.
modal().addEventListener("click", (e) => { if (e.target === modal()) closeAuthModal(); });
