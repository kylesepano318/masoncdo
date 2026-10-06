import { Link } from "../../lib/ui";
export default function Pagination({
  links,
}: {
  links: { url: string | null; label: string; active: boolean }[];
}) {
  return (
    <nav className="pagination" aria-label="Pagination">
      {links.map((l, i) =>
        l.url ? (
          <Link
            key={i}
            href={l.url}
            className={l.active ? "active" : ""}
            dangerouslySetInnerHTML={{ __html: l.label }}
          />
        ) : (
          <span key={i} dangerouslySetInnerHTML={{ __html: l.label }} />
        ),
      )}
    </nav>
  );
}
