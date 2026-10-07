import { Link, useSiteContext, apiActions, Head } from "../lib/ui";
import {
  LayoutDashboard,
  Users,
  Inbox,
  FileText,
  Image,
  Palette,
  Settings,
  LogOut,
  ExternalLink,
  Menu,
  CalendarDays,
  Network,
} from "lucide-react";
import { useState } from "react";
import type { ReactNode } from "react";
import type { Shared } from "../types";
const groups = [
  {
    title: "Overview",
    links: [
      ["Dashboard", "/admin/dashboard", LayoutDashboard],
      ["Members", "/admin/members", Users],
      ["Add member", "/admin/members/create", Users],
      ["Applications", "/admin/applications", Inbox],
      ["Celebrations", "/admin/celebrations", CalendarDays],
    ],
  },
  {
    title: "Website",
    links: [
      ["Home", "/admin/page/home", FileText],
      ["History", "/admin/page/history", FileText],
      ["Members page", "/admin/page/members", FileText],
      ["Application page", "/admin/page/application", FileText],
      ["Celebrations page", "/admin/page/celebrations", FileText],
      ["Header", "/admin/settings/header", FileText],
      ["Footer", "/admin/settings/footer", FileText],
      ["Affiliations", "/admin/affiliations", Network],
      ["Media library", "/admin/media", Image],
    ],
  },
  {
    title: "Appearance & account",
    links: [
      ["Branding", "/admin/settings/branding", Settings],
      ["Theme", "/admin/settings/theme", Palette],
      ["Navigation", "/admin/settings/navigation", Settings],
      ["Notifications", "/admin/settings/notifications", Inbox],
      ["Login credentials", "/admin/settings/account", Settings],
      ["Administrators", "/admin/administrators", Users],
    ],
  },
] as const;
export default function AdminLayout({
  title,
  children,
  actions,
}: {
  title: string;
  children: ReactNode;
  actions?: ReactNode;
}) {
  const page = useSiteContext<Shared>();
  const [open, setOpen] = useState(false);
  return (
    <div className="admin-app">
      <Head title={`${title} · Lodge Administration`} />
      <aside className={`admin-sidebar ${open ? "open" : ""}`}>
        <Link href="/admin/dashboard" className="admin-brand">
          <img src={page.props.site.branding?.emblem} alt="Lodge emblem" />
          <span>
            Golden Friendship<small>LODGE ADMINISTRATION</small>
          </span>
        </Link>
        {groups.map((g) => (
          <div className="sidebar-group" key={g.title}>
            <p>{g.title}</p>
            {g.links
              .filter(
                ([, url]) =>
                  url !== "/admin/administrators" ||
                  page.props.auth.user?.is_superadmin,
              )
              .map(([label, url, Icon]) => (
                <Link
                  href={
                    url.startsWith("/admin/page/")
                      ? `/admin/pages/${page.props.adminPages.find((p) => p.slug === url.split("/").pop())?.id}`
                      : url
                  }
                  key={label}
                  className={
                    (
                      url.startsWith("/admin/page/")
                        ? page.url ===
                          `/admin/pages/${page.props.adminPages.find((p) => p.slug === url.split("/").pop())?.id}`
                        : page.url === url
                    )
                      ? "active"
                      : ""
                  }
                >
                  <Icon size={17} />
                  {label}
                  {label === "Applications" &&
                    Number(page.props.unread) > 0 && (
                      <span
                        className="badge pending"
                        aria-label={`${page.props.unread} unread applications`}
                      >
                        {Number(page.props.unread)}
                      </span>
                    )}
                </Link>
              ))}
          </div>
        ))}
        <button
          onClick={() => apiActions.post("/admin/logout")}
          className="logout"
        >
          <LogOut size={17} /> Logout
        </button>
      </aside>
      {open && (
        <button
          className="sidebar-backdrop"
          aria-label="Close sidebar"
          onClick={() => setOpen(false)}
        />
      )}
      <div className="admin-main">
        <header className="admin-topbar">
          <button
            className="admin-mobile-toggle"
            onClick={() => setOpen(!open)}
            aria-label="Toggle sidebar"
          >
            <Menu />
          </button>
          <span>{page.props.auth.user?.name}</span>
          <Link href="/" target="_blank">
            View website <ExternalLink size={15} />
          </Link>
        </header>
        <div className="admin-content">
          <div className="admin-page-heading">
            <div>
              <p className="admin-eyebrow">GOLDEN FRIENDSHIP · NO. 40</p>
              <h1>{title}</h1>
            </div>
            <div className="admin-actions">{actions}</div>
          </div>
          {page.props.flash.success && (
            <div className="notice" role="status">
              {page.props.flash.success}
            </div>
          )}
          {children}
        </div>
      </div>
    </div>
  );
}
