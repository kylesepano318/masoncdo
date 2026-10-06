import axios from "axios";
export const apiOrigin = (import.meta.env.VITE_API_BASE_URL || "")
  .replace(/\/$/, "")
  .replace(/\/api$/, "");
export const http = axios.create({
  baseURL: `${apiOrigin}/api`,
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: "application/json" },
});
export async function csrf() {
  await axios.get(`${apiOrigin}/sanctum/csrf-cookie`, {
    withCredentials: true,
    withXSRFToken: true,
  });
}
export async function mutation(method: string, path: string, data?: unknown) {
  await csrf();
  return http.request({ method, url: path, data });
}
export const authApi = {
  session: () => http.get("/public/session"),
  me: () => http.get("/admin/me"),
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
export const settingsApi = { site: () => http.get("/public/site") };
