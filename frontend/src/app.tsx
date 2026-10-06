import { createRoot } from "react-dom/client";
import { useEffect, useState } from "react";
import type { ComponentType } from "react";
import {
  BrowserRouter,
  Routes,
  Route,
  useLocation,
  useNavigate,
} from "react-router-dom";
import { http, settingsApi, authApi } from "./lib/api";
import { SiteContext, SubmissionStatus, useSubmitting } from "./lib/ui";
import type { Shared } from "./types";
import "./styles.css";
const modules = import.meta.glob<{
  default: ComponentType<Record<string, unknown>>;
}>("./pages/**/*.tsx");
function App() {
  const submitting = useSubmitting();
  const location = useLocation(),
    navigate = useNavigate();
  const [shared, setShared] = useState<Shared>({
    site: {},
    adminPages: [],
    auth: { user: null },
    flash: {},
    unread: 0,
  });
  const [revision, reload] = useState(0),
    [screen, setScreen] = useState<{
      component: ComponentType<Record<string, unknown>>;
      props: Record<string, unknown>;
    } | null>(null),
    [error, setError] = useState("");
  useEffect(() => {
    const refresh = () => reload((v) => v + 1);
    const notification = (event: Event) =>
      setShared((v) => ({
        ...v,
        flash: { success: (event as CustomEvent<string>).detail },
      }));
    const move = (event: Event) => {
      const { path, state } = (event as CustomEvent).detail;
      navigate(path, { state });
    };
    window.addEventListener("lodge:refresh", refresh);
    window.addEventListener("lodge:notice", notification);
    window.addEventListener("lodge:navigate", move);
    return () => {
      window.removeEventListener("lodge:refresh", refresh);
      window.removeEventListener("lodge:notice", notification);
      window.removeEventListener("lodge:navigate", move);
    };
  }, [navigate]);
  useEffect(() => {
    if (
      !location.pathname.startsWith("/admin") ||
      location.pathname === "/admin/login"
    )
      return;
    const timer = setInterval(
      () =>
        http
          .get("/admin/applications/counts")
          .then((r) => setShared((v) => ({ ...v, unread: r.data.unread })))
          .catch(() => {}),
      30000,
    );
    return () => clearInterval(timer);
  }, [location.pathname]);
  useEffect(() => {
    let cancelled = false;
    setScreen(null);
    setError("");
    async function load() {
      try {
        const site = (await settingsApi.site()).data.site;
        let auth: Shared["auth"] = { user: null },
          adminPages: Shared["adminPages"] = [],
          unread = 0;
        const path = location.pathname,
          isAdmin = path.startsWith("/admin/") && path !== "/admin/login";
        if (isAdmin || path.startsWith("/preview/")) {
          const me = (await authApi.me()).data;
          auth = { user: me.user };
          adminPages = me.pages;
          unread = me.unread;
        } else {
          const session = await authApi
            .session()
            .catch(() => ({ data: { user: null } }));
          auth = { user: session.data.user };
        }
        let name = "public/Page",
          endpoint = "",
          props: Record<string, unknown> = {};
        if (path === "/admin/login") name = "admin/Login";
        else if (path === "/application/received") {
          name = "public/ApplicationSuccess";
          props = {
            reference:
              (location.state as { reference?: string })?.reference || "",
          };
          if (!props.reference) {
            navigate("/application", { replace: true });
            return;
          }
        } else if (path.startsWith("/preview/")) {
          endpoint = "/admin/preview/" + path.split("/").pop();
          name = path.endsWith("/application")
            ? "public/Application"
            : "public/Page";
        } else if (isAdmin) {
          endpoint = path + location.search;
          name =
            path === "/admin/dashboard"
              ? "admin/Dashboard"
              : path === "/admin/members"
                ? "admin/Members"
                : path.includes("/members/")
                  ? "admin/MemberEdit"
                  : path === "/admin/applications"
                    ? "admin/Applications"
                    : path.includes("/applications/")
                      ? "admin/ApplicationDetail"
                      : path.includes("/pages/")
                        ? "admin/PageEditor"
                        : path === "/admin/media"
                          ? "admin/Media"
                          : path === "/admin/affiliations"
                            ? "admin/Affiliations"
                            : path === "/admin/celebrations"
                              ? "admin/Celebrations"
                              : path.includes("/settings/")
                                ? "admin/Settings"
                                : "";
        } else {
          const slug = path === "/" ? "home" : path.slice(1);
          endpoint = "/public/pages/" + slug;
          name = slug === "application" ? "public/Application" : "public/Page";
        }
        if (!name) throw new Error("Page not found.");
        if (endpoint) props = (await http.get(endpoint)).data;
        if (Array.isArray(props.members))
          props.members = props.members.map((m: Record<string, unknown>) =>
            m.full_name
              ? {
                  ...m,
                  name: m.full_name,
                  profile_photo: m.photo_url,
                  biography: m.public_biography,
                }
              : m,
          );
        const component = (await modules[`./pages/${name}.tsx`]()).default;
        if (!cancelled) {
          setShared((v) => ({ ...v, site, auth, adminPages, unread }));
          setScreen({ component, props });
        }
      } catch (e) {
        if (cancelled) return;
        const status = (e as { response?: { status: number } }).response
          ?.status;
        if (status === 401) {
          navigate("/admin/login", { replace: true });
          return;
        }
        setError(
          status === 404
            ? "This page could not be found."
            : status === 403
              ? "You do not have access to this page."
              : "Unable to load this page. Please try again.",
        );
      }
    }
    void load();
    return () => {
      cancelled = true;
    };
  }, [location.pathname, location.search, location.state, revision, navigate]);
  return (
    <SiteContext.Provider value={shared}>
      <div inert={submitting} aria-busy={submitting}>
        {error ? (
          <main className="section">
            <h1>{error}</h1>
            <button onClick={() => reload((v) => v + 1)}>Try again</button>{" "}
            <a href="/">Return home</a>
          </main>
        ) : screen ? (
          <screen.component {...screen.props} />
        ) : (
          <main className="section" role="status">
            Loading…
          </main>
        )}
      </div>
      <SubmissionStatus />
    </SiteContext.Provider>
  );
}
createRoot(document.getElementById("root")!).render(
  <BrowserRouter>
    <Routes>
      <Route path="*" element={<App />} />
    </Routes>
  </BrowserRouter>,
);
