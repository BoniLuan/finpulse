// Thin API client. All requests go through the gateway under /api/v1.
const BASE = "/api/v1";

export async function getIndicators() {
  const res = await fetch(`${BASE}/indicators`);
  if (!res.ok) throw new Error(`request failed (${res.status})`);
  return res.json();
}

export async function ask(question) {
  const res = await fetch(`${BASE}/ask`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ question }),
  });
  if (!res.ok) {
    const body = await res.json().catch(() => null);
    throw new Error(body?.error?.message ?? `request failed (${res.status})`);
  }
  return res.json();
}
