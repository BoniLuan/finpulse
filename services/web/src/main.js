import { ask } from "./api.js";

const INDICATORS = [
  { icon: "📈", label: "Selic", caption: "annual target", question: "what is the current selic?", fmt: (v) => `${v}%` },
  { icon: "🛒", label: "IPCA", caption: "monthly inflation", question: "what is the current ipca?", fmt: (v) => `${v}%` },
  { icon: "💵", label: "USD", caption: "PTAX buy", question: "what is the current usd?", fmt: (v) => `R$ ${v}` },
];

const SUGGESTIONS = [
  "How much does 10 thousand in savings yield in 1 year?",
  "What is the current Selic?",
  "How much is 1000 worth adjusted for inflation over 12 months?",
];

function loadIndicators() {
  const el = document.getElementById("indicators");
  el.innerHTML = "";
  for (const ind of INDICATORS) {
    const card = document.createElement("div");
    card.className = "card";
    card.innerHTML =
      `<div class="card-top"><span class="card-icon">${ind.icon}</span>` +
      `<span class="card-label">${ind.label}</span></div>` +
      `<div class="card-value"><span class="skeleton"></span></div>` +
      `<div class="card-caption">${ind.caption}</div>`;
    el.appendChild(card);
    const valueEl = card.querySelector(".card-value");
    ask(ind.question)
      .then((r) => {
        const v = r.data?.value;
        valueEl.textContent = v !== undefined ? ind.fmt(v) : "—";
      })
      .catch(() => { valueEl.textContent = "—"; });
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
