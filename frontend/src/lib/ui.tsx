import {
  createContext,
  useContext,
  useState,
  useRef,
  useEffect,
  useSyncExternalStore,
  Children,
  isValidElement,
} from "react";
import type { ReactNode, AnchorHTMLAttributes } from "react";
import { Link as RouteLink, useLocation } from "react-router-dom";
import axios from "axios";
import { mutation } from "./api";
import type { Shared } from "../types";
export const SiteContext = createContext<Shared>({
  site: {},
  adminPages: [],
  auth: { user: null },
  flash: {},
});
export function useSiteContext<T = Shared>() {
  const location = useLocation();
  return {
    props: useContext(SiteContext) as T,
    url: location.pathname + location.search,
  };
}
export function Link({
  href,
  children,
  ...props
}: AnchorHTMLAttributes<HTMLAnchorElement> & {
  href: string;
  children?: ReactNode;
}) {
  const to =
    href.startsWith("http") && href.includes("/api/admin/")
      ? new URL(href).pathname.replace("/api", "") + new URL(href).search
      : href;
  return /^(https?:|mailto:|tel:|#)/.test(to) ? (
    <a href={to} {...props}>
      {children}
    </a>
  ) : (
    <RouteLink to={to} {...props}>
      {children}
    </RouteLink>
  );
}
export function Head({
  title,
  children,
}: {
  title?: string;
  children?: ReactNode;
}) {
  useEffect(() => {
    if (title) document.title = title;
    const created: HTMLElement[] = [];
    Children.forEach(children, (child) => {
      if (
        isValidElement<Record<string, string>>(child) &&
        child.type === "meta"
      ) {
        const node = document.createElement("meta");
        Object.entries(child.props).forEach(([key, value]) =>
          node.setAttribute(key, value),
        );
        node.dataset.pageMeta = "true";
        document.head.append(node);
        created.push(node);
      }
    });
    return () => created.forEach((node) => node.remove());
  }, [title, children]);
  return null;
}
export const refresh = () => window.dispatchEvent(new Event("lodge:refresh"));
export const notice = (message: string) =>
  window.dispatchEvent(new CustomEvent("lodge:notice", { detail: message }));
export const navigate = (path: string, state?: unknown): void => {
  window.dispatchEvent(
    new CustomEvent("lodge:navigate", { detail: { path, state } }),
  );
};
let submitting = false;
const submissionListeners = new Set<() => void>();
function setSubmitting(value: boolean) {
  submitting = value;
  submissionListeners.forEach((listener) => listener());
}
function beginSubmission() {
  // Acquire synchronously, before React renders or the CSRF request starts.
  if (submitting) return false;
  setSubmitting(true);
  return true;
}
export function useSubmitting() {
  return useSyncExternalStore(
    (listener) => {
      submissionListeners.add(listener);
      return () => {
        submissionListeners.delete(listener);
      };
    },
    () => submitting,
  );
}
export function SubmissionStatus() {
  const pending = useSubmitting();
  if (!pending) return null;
  return (
    <div className="submission-overlay">
      <div className="submission-status" role="status" aria-live="polite">
        <span className="submission-spinner" aria-hidden="true" />
        <strong>Submitting…</strong>
        <span>Please wait while we complete your request.</span>
      </div>
    </div>
  );
}
type Options = {
  refresh?: boolean;
  onSuccess?: (data: Record<string, unknown>) => void | Promise<void>;
  onFinish?: () => void;
  forceFormData?: boolean;
  preserveScroll?: boolean;
  preserveState?: boolean;
};
function errorsOf(e: unknown): Record<string, string> {
  if (axios.isAxiosError(e)) {
    const data = e.response?.data;
    return data?.errors
      ? Object.fromEntries(
          Object.entries(data.errors).map(([key, value]) => [
            key,
            Array.isArray(value) ? value[0] : String(value),
          ]),
        )
      : { request: data?.message || "Unable to connect. Please try again." };
  }
  return { request: "The request failed. Please try again." };
}
function formData(value: Record<string, unknown>) {
  const result = new FormData();
  const append = (key: string, item: unknown) => {
    if (item instanceof File) result.append(key, item);
    else if (item !== null && typeof item === "object")
      Object.entries(item).forEach(([k, v]) => append(`${key}[${k}]`, v));
    else
      result.append(
        key,
        item == null
          ? ""
          : typeof item === "boolean"
            ? item
              ? "1"
              : "0"
            : String(item),
      );
  };
  Object.entries(value).forEach(([k, v]) => append(k, v));
  return result;
}
export function useApiForm<T extends Record<string, unknown>>(initial: T) {
  const [data, set] = useState(initial),
    [errors, setErrors] = useState<Record<string, string>>({}),
    [processing, busy] = useState(false);
  const transformRef = useRef<(data: T) => Record<string, unknown>>(
    (data) => data,
  );
  const setData = <K extends keyof T>(key: K | T, value?: T[K]) =>
    set((current) =>
      typeof key === "object" ? key : { ...current, [key]: value },
    );
  const reset = (...keys: (keyof T)[]) =>
    set((current) =>
      keys.length
        ? {
            ...current,
            ...Object.fromEntries(keys.map((k) => [k, initial[k]])),
          }
        : initial,
    );
  const send = async (method: string, path: string, options: Options = {}) => {
    if (!beginSubmission()) return;
    busy(true);
    setErrors({});
    try {
      const payload = transformRef.current(data);
      const response = await mutation(
        method,
        path,
        options.forceFormData ||
          Object.values(payload).some((v) => v instanceof File)
          ? formData(payload)
          : payload,
      );
      await options.onSuccess?.(response.data);
      if (response.data.message) notice(response.data.message);
      if (options.refresh !== false) refresh();
    } catch (e) {
      setErrors(errorsOf(e));
    } finally {
      busy(false);
      setSubmitting(false);
      options.onFinish?.();
    }
  };
  return {
    data,
    setData,
    errors,
    processing,
    reset,
    transform: (fn: (data: T) => Record<string, unknown>) => {
      transformRef.current = fn;
    },
    post: (path: string, options?: Options) => send("post", path, options),
    put: (path: string, options?: Options) => send("put", path, options),
    patch: (path: string, options?: Options) => send("patch", path, options),
    delete: (path: string, options?: Options) => send("delete", path, options),
  };
}
async function action(method: string, path: string, data?: unknown) {
  if (!beginSubmission()) return;
  try {
    const response = await mutation(method, path, data);
    notice(response.data.message || "Changes saved.");
    if (path === "/admin/logout") navigate("/admin/login");
    else refresh();
  } catch (e) {
    notice(Object.values(errorsOf(e)).join(" "));
  } finally {
    setSubmitting(false);
  }
}
export const apiActions = {
  get: (path: string, params: Record<string, string>) =>
    navigate(path + "?" + new URLSearchParams(params)),
  post: (path: string, data?: unknown) => action("post", path, data),
  put: (path: string, data?: unknown) => action("put", path, data),
  delete: (path: string) => action("delete", path),
};
