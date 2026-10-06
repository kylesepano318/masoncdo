import { Link, useSiteContext } from "../../lib/ui";
import type { Shared } from "../../types";
import { navPaths } from "./Header";
export default function Footer() {
  const { site } = useSiteContext<Shared>().props;
  const footer = site.footer || {};
  return (
    <footer className="public-footer">
      <div className="footer-main">
        <div className="footer-brand">
          <img
            src={site.branding?.emblem}
            alt="Golden Friendship Masonic Lodge No. 40 emblem"
          />
          <h2>
            {site.branding?.name}
            <span>Masonic Lodge No. 40</span>
          </h2>
          <p>{footer.description}</p>
        </div>
        <div>
          <h3>Explore the lodge</h3>
          <nav aria-label="Footer navigation">
            {Object.entries(navPaths).map(([key, path]) => (
              <Link key={key} href={path}>
                {site.navigation?.[key] || key}
              </Link>
            ))}
          </nav>
        </div>
        <div>
          <h3>Our home</h3>
          <p>{footer.address || site.branding?.location}</p>
          {footer.email && (
            <a href={`mailto:${footer.email}`}>{footer.email}</a>
          )}
          {footer.phone && <a href={`tel:${footer.phone}`}>{footer.phone}</a>}
          {footer.instagram && (
            <a
              href={footer.instagram}
              target="_blank"
              rel="noopener noreferrer"
            >
              Instagram ↗
            </a>
          )}
          {footer.facebook && (
            <a href={footer.facebook} target="_blank" rel="noopener noreferrer">
              Facebook â†—
            </a>
          )}
          <p className="footer-motto">Liberty Â· Equality Â· Fraternity</p>
        </div>
      </div>
      <div className="footer-bottom">
        <span>
          Â© {new Date().getFullYear()}{" "}
          {footer.copyright || "Golden Friendship Masonic Lodge No. 40"}
        </span>
        <Link href="/admin/login">Admin login</Link>
      </div>
    </footer>
  );
}
