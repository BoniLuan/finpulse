import { ask, getIndicators } from "./api.js";

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

loadIndicators();
wireChat();
