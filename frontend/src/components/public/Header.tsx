import { Link, useSiteContext } from "../../lib/ui";
import { Menu, X } from "lucide-react";
import { useState, useEffect } from "react";
import type { Shared } from "../../types";
export const navPaths = {
  home: "/",
  members: "/members",
  history: "/history",
  celebrations: "/celebrations",
  application: "/application",
};
export default function Header() {
  const { site, auth } = useSiteContext<Shared>().props;
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);
  const { url } = useSiteContext();
  useEffect(() => {
    const scroll = () => setScrolled(window.scrollY > 30);
    scroll();
    window.addEventListener("scroll", scroll);
    return () => window.removeEventListener("scroll", scroll);
  }, []);
  useEffect(() => setOpen(false), [url]);
  return (
    <header className={`public-header ${scrolled ? "scrolled" : ""}`}>
      <div className="nav-inner">
        <Link href="/" className="brand">
          <img
            src={
              site.branding?.emblem ||
              "/images/golden-friendship-lodge-no-40.png"
            }
            alt="Golden Friendship lodge emblem"
          />
          <span>
            {site.branding?.name || "Golden Friendship"}
            <small>{site.header?.subtitle || "MASONIC LODGE NO. 40"}</small>
          </span>
        </Link>
        <button
          className="menu-toggle"
          aria-label={open ? "Close navigation" : "Open navigation"}
          aria-expanded={open}
          aria-controls="main-nav"
          onClick={() => setOpen(!open)}
        >
          {open ? <X /> : <Menu />}
        </button>
        <nav
          id="main-nav"
          className={open ? "open" : ""}
          aria-label="Main navigation"
        >
          {Object.entries(navPaths).map(([key, path]) => (
            <Link
              key={key}
              href={path}
              className={url.split("?")[0] === path ? "active" : ""}
              aria-current={url.split("?")[0] === path ? "page" : undefined}
            >
              {site.navigation?.[key] || key}
            </Link>
          ))}
          <Link href={auth.user ? "/admin/dashboard" : "/admin/login"}>
            {auth.user ? "Admin" : "Admin Login"}
          </Link>
        </nav>
      </div>
    </header>
  );
}
