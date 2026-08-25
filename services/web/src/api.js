// Thin API client. All requests go through the gateway under /api/v1.
const BASE = "/api/v1";
const TOKEN_KEY = "finpulse_token";
const REQUEST_TIMEOUT_MS = 15_000;

// JWT store (localStorage).
export const token = {
  get: () => localStorage.getItem(TOKEN_KEY),
  set: (t) => localStorage.setItem(TOKEN_KEY, t),
  clear: () => localStorage.removeItem(TOKEN_KEY),
};

async function request(path, { method = "GET", body, auth = false } = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), REQUEST_TIMEOUT_MS);
  const headers = {};
  if (body !== undefined) headers["Content-Type"] = "application/json";
  if (auth) {
    const t = token.get();
    if (t) headers["Authorization"] = `Bearer ${t}`;
  }

  let res;
  try {
    res = await fetch(`${BASE}${path}`, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });
  } catch (error) {
    if (error.name === "AbortError") throw new Error("The request took too long. Please try again.");
    throw error;
  } finally {
    clearTimeout(timeout);
  }

  if (res.status === 204) return null;
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    throw new Error(data?.error?.message ?? `request failed (${res.status})`);
  }
  return data;
}

export const getIndicators = () => request("/indicators");
export const ask = (question) => request("/ask", { method: "POST", body: { question }, auth: true });

export const register = (email, password) =>
  request("/auth/register", { method: "POST", body: { email, password } });
export const login = (email, password) =>
  request("/auth/login", { method: "POST", body: { email, password } });
export const me = () => request("/auth/me", { auth: true });

export const listAlerts = () => request("/alerts", { auth: true });
export const createAlert = (alert) => request("/alerts", { method: "POST", body: alert, auth: true });
export const deleteAlert = (id) => request(`/alerts/${id}`, { method: "DELETE", auth: true });
export const listHistory = () => request("/history", { auth: true });
export const deleteHistory = (id) => request(`/history/${id}`, { method: "DELETE", auth: true });
