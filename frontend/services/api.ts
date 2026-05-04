/**
 * Tiny fetch wrapper for the EdgeRX backend.
 *
 * Why not axios: keep the bundle thin and fully control CSRF priming. Sanctum
 * SPA mode requires:
 *   1) GET /sanctum/csrf-cookie BEFORE the first state-changing call (one-time)
 *   2) credentials: 'include' on every request so the laravel_session and
 *      XSRF-TOKEN cookies are sent
 *   3) X-XSRF-TOKEN header copied from the XSRF-TOKEN cookie value, on
 *      mutating requests
 */

const API_BASE: string = (import.meta as any).env?.VITE_API_BASE ?? '/api';

let csrfPrimed = false;

function readCookie(name: string): string | null {
  const all = document.cookie.split(';').map(s => s.trim());
  for (const part of all) {
    const eq = part.indexOf('=');
    if (eq < 0) continue;
    const k = part.slice(0, eq);
    if (k === name) return decodeURIComponent(part.slice(eq + 1));
  }
  return null;
}

async function primeCsrf(): Promise<void> {
  if (csrfPrimed) return;
  await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
  csrfPrimed = true;
}

export class ApiError extends Error {
  status: number;
  data: any;
  constructor(message: string, status: number, data: any) {
    super(message);
    this.status = status;
    this.data = data;
  }
}

/**
 * FE-3 fix: global 401 handler. App.tsx calls subscribeUnauthorized() on mount
 * to register a callback that fires whenever any API call returns 401 — typically
 * because the session expired mid-use. The handler clears local state and
 * forces the user back to the login screen instead of leaving the SPA in a
 * "logged in" UI that silently fails on every action.
 */
type UnauthorizedHandler = () => void;
let _unauthHandlers: UnauthorizedHandler[] = [];
export function subscribeUnauthorized(fn: UnauthorizedHandler): () => void {
  _unauthHandlers.push(fn);
  return () => { _unauthHandlers = _unauthHandlers.filter(h => h !== fn); };
}
let _unauthFiredAt = 0;
function fireUnauthorized() {
  // Debounce — many parallel cache refreshes can all 401 at once on session
  // expiry. We only want the redirect to fire once per session-loss event.
  const now = Date.now();
  if (now - _unauthFiredAt < 2000) return;
  _unauthFiredAt = now;
  csrfPrimed = false; // re-prime CSRF on next mutation after re-login
  for (const h of _unauthHandlers) {
    try { h(); } catch {}
  }
}

interface RequestOpts {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  body?: any;
  headers?: Record<string, string>;
}

export async function apiRequest<T = any>(path: string, opts: RequestOpts = {}): Promise<T> {
  const method = opts.method ?? 'GET';
  const isMutation = method !== 'GET' && method !== 'HEAD';
  if (isMutation) await primeCsrf();

  const headers: Record<string, string> = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...(opts.headers ?? {}),
  };
  if (isMutation) {
    const xsrf = readCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;
  }
  let body: BodyInit | undefined;
  if (opts.body !== undefined) {
    if (opts.body instanceof FormData) {
      body = opts.body;
    } else {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(opts.body);
    }
  }

  const url = path.startsWith('http') ? path : `${API_BASE}${path}`;
  const resp = await fetch(url, {
    method,
    credentials: 'include',
    headers,
    body,
  });

  // 204 No Content
  if (resp.status === 204) return undefined as unknown as T;

  let data: any = null;
  const text = await resp.text();
  if (text) {
    try { data = JSON.parse(text); }
    catch { data = text; }
  }

  if (!resp.ok) {
    // FE-3: surface a global 401 event so the SPA can redirect to login.
    // Skip on /auth/login itself (genuine bad credentials) — caller path-checks.
    if (resp.status === 401 && !path.startsWith('/auth/login')) {
      fireUnauthorized();
    }
    const msg = (data && (data.message || data.error)) || resp.statusText;
    throw new ApiError(msg, resp.status, data);
  }

  // Laravel API Resources wrap collections/objects in { data: ... }
  if (data && typeof data === 'object' && 'data' in data && Object.keys(data).length === 1) {
    return data.data as T;
  }
  return data as T;
}

export const api = {
  get:    <T = any>(path: string)               => apiRequest<T>(path, { method: 'GET' }),
  post:   <T = any>(path: string, body?: any)   => apiRequest<T>(path, { method: 'POST',   body }),
  put:    <T = any>(path: string, body?: any)   => apiRequest<T>(path, { method: 'PUT',    body }),
  patch:  <T = any>(path: string, body?: any)   => apiRequest<T>(path, { method: 'PATCH',  body }),
  del:    <T = any>(path: string)               => apiRequest<T>(path, { method: 'DELETE' }),
};
