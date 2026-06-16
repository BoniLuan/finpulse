import { ask } from "./api";

const INDICATORS: Array<{ label: string; question: string }> = [
  { label: "Selic", question: "what is the current selic?" },
  { label: "IPCA", question: "what is the current ipca?" },
  { label: "USD", question: "what is the current usd?" },
];

async function loadIndicators(): Promise<void> {
  const el = document.getElementById("indicators")!;
  el.innerHTML = "";
  for (const ind of INDICATORS) {
    const card = document.createElement("div");
    card.className = "card";
    card.innerHTML = `<span class="card-label">${ind.label}</span><span class="card-value">…</span>`;
    el.appendChild(card);
    ask(ind.question)
      .then((r) => {
        const value = (r.data as { value?: number }).value;
        card.querySelector(".card-value")!.textContent =
          value !== undefined ? String(value) : "—";
      })
      .catch(() => {
        card.querySelector(".card-value")!.textContent = "—";
      });
  }
}

function wireChat(): void {
  const form = document.getElementById("ask-form") as HTMLFormElement;
  const input = document.getElementById("question") as HTMLInputElement;
  const answer = document.getElementById("answer")!;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const question = input.value.trim();
    if (!question) return;

    answer.textContent = "Thinking…";
    try {
      const res = await ask(question);
      const src = res.sources?.[0];
      answer.innerHTML = `<p>${res.answer}</p>` +
        (src ? `<small>Source: ${src.name} · series ${src.series} (${src.label})</small>` : "");
    } catch (err) {
      answer.textContent = `Error: ${(err as Error).message}`;
    }
  });
}

loadIndicators();
wireChat();
