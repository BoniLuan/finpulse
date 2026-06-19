// Thin API client. All requests go through the gateway under /api/v1.
const BASE = "/api/v1";
const TOKEN_KEY = "finpulse_token";

// JWT store (localStorage).
export const token = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

async function request(path, { method = "GET", body, auth = false } = {}) {
  const headers = {};
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (auth) {
    const t = token.get();
    if (t) headers["Authorization"] = `Bearer ${t}`;
  }

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });

  if (res.status === 204) return null;
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error(data?.error?.message ?? `request failed (${res.status})`);
  }
  return data;
}

export const getIndicators = () => request("/indicators");
export const ask = (question) => request("/ask", { method: "POST", body: { question } });

export const register = (email, password) =>
  request("/auth/register", { method: "POST", body: { email, password } });
export const login = (email, password) =>
  request("/auth/login", { method: "POST", body: { email, password } });
export const me = () => request("/auth/me", { auth: true });

export const listAlerts = () => request("/alerts", { auth: true });
export const createAlert = (alert) => request("/alerts", { method: "POST", body: alert, auth: true });
export const deleteAlert = (id) => request(`/alerts/${id}`, { method: "DELETE", auth: true });
