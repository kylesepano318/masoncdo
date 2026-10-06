import axios from "axios";
import type { AxiosResponse } from "axios";
export const apiOrigin = (import.meta.env.VITE_API_BASE_URL || "")
  .replace(/\/$/, "")
  .replace(/\/api$/, "");
export const http = axios.create({
  baseURL: `${apiOrigin}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});
// Memory only: never persist authenticated responses in browser storage.
const reads = new Map<
  string,
  { expires: number; request: Promise<AxiosResponse> }
>();
export function cachedGet(path: string, ttl = 30000): Promise<AxiosResponse> {
  const existing = reads.get(path);
  if (existing && existing.expires > Date.now()) return existing.request;
  const entry = { expires: Infinity, request: http.get(path) };
  reads.set(path, entry);
  if (reads.size > 32) reads.delete(reads.keys().next().value!);
  entry.request.then(
    () => {
      entry.expires = Date.now() + ttl;
    },
    () => {
      if (reads.get(path) === entry) reads.delete(path);
    },
  );
  return entry.request;
}
export function invalidateReads(path: string) {
  for (const key of reads.keys()) {
    if (
      key.startsWith("/public/pages/") ||
      key === "/public/members" ||
      (path.startsWith("/admin/settings/") && key === "/public/site") ||
      (["/admin/login", "/admin/logout", "/admin/password"].includes(path) &&
        (key === "/admin/me" || key === "/public/session")) ||
      ((path.startsWith("/admin/applications") ||
        path.startsWith("/admin/pages/")) &&
        key === "/admin/me")
    )
      reads.delete(key);
  }
}
export function clearIdentity() {
  reads.delete("/admin/me");
  reads.delete("/public/session");
}
let csrfRequest: Promise<void> | null = null;
export async function csrf(force = false) {
  if (
    !force &&
    document.cookie
      .split(";")
      .some((cookie) => cookie.trim().startsWith("XSRF-TOKEN="))
  )
    return;
  if (csrfRequest) return csrfRequest;
  csrfRequest = axios
    .get(`${apiOrigin}/sanctum/csrf-cookie`, {
      withCredentials: true,
      withXSRFToken: true,
    })
    .then(() => {})
    .finally(() => {
      csrfRequest = null;
    });
  return csrfRequest;
}
export async function mutation(method: string, path: string, data?: unknown) {
  await csrf();
  let response;
  try {
    response = await http.request({ method, url: path, data });
  } catch (error) {
    // A 419 is rejected before the controller runs. Retry only that failure,
    // never a timeout or ambiguous network failure that could duplicate a write.
    if (!axios.isAxiosError(error) || error.response?.status !== 419)
      throw error;
    await csrf(true);
    response = await http.request({ method, url: path, data });
  }
  invalidateReads(path);
  return response;
}
export const authApi = {
  session: () => cachedGet("/public/session"),
  me: () => cachedGet("/admin/me"),
  login: (data: unknown) => mutation("post", "/admin/login", data),
  logout: () => mutation("post", "/admin/logout"),
};
export const membersApi = { list: () => http.get("/public/members") };
export const applicationsApi = {
  counts: () => http.get("/admin/applications/counts"),
  submit: (data: unknown) => mutation("post", "/applications", data),
  status: (id: number, status: string) =>
    mutation("patch", `/admin/applications/${id}/status`, { status }),
  notes: (id: number, admin_notes: string) =>
    mutation("patch", `/admin/applications/${id}/notes`, { admin_notes }),
};
export const pagesApi = {
  page: (slug: string) => http.get(`/public/pages/${slug}`),
};
export const mediaApi = { list: () => http.get("/admin/media") };
export const settingsApi = { site: () => cachedGet("/public/site", 300000) };
