import { Link } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import type { PageData } from "../../types";
export default function Dashboard({
  counts,
  activity,
  pages,
  recentApplications,
}: {
  counts: Record<string, number>;
  activity: { id: number; event: string; created_at: string }[];
  pages: PageData[];
  recentApplications: {
    id: number;
    reference_number: string;
    first_name: string;
    last_name: string;
    status: string;
    is_read_by_admin: boolean;
  }[];
}) {
  return (
    <AdminLayout title="Dashboard">
      {counts["unread applications"] > 0 && (
        <div className="notice">
          <strong>
            {counts["unread applications"]} new membership applications require
            review.
          </strong>{" "}
          <Link href="/admin/applications?unread=1">View applications →</Link>
        </div>
      )}
      <div className="stats-grid">
        {Object.entries(counts).map(([key, value]) => (
          <div className="stat" key={key}>
            <p>{key}</p>
            <strong>{value}</strong>
          </div>
        ))}
      </div>
      <section className="admin-panel">
        <h2>Recent applications</h2>
        {recentApplications.length ? (
          recentApplications.map((a) => (
            <Link
              className="dashboard-link"
              key={a.id}
              href={`/admin/applications/${a.id}`}
            >
              <span>
                {a.first_name} {a.last_name} Â· {a.reference_number}
              </span>
              <span>
                {!a.is_read_by_admin && (
                  <span className="badge pending">NEW</span>
                )}{" "}
                {a.status.replaceAll("_", " ")}
              </span>
            </Link>
          ))
        ) : (
          <p className="help">Applications will appear here when submitted.</p>
        )}
      </section>
      <div className="dashboard-columns">
        <section className="admin-panel">
          <h2>Manage the website</h2>
          <p className="help">
            Edit a page draft, preview it, then publish when ready.
          </p>
          {pages.map((p) => (
            <Link
              className="dashboard-link"
              key={p.id}
              href={`/admin/pages/${p.id}`}
            >
              {p.name}
              <span>Edit page â†’</span>
            </Link>
          ))}
        </section>
        <section className="admin-panel">
          <h2>Recent activity</h2>
          {activity.length ? (
            activity.map((a) => (
              <div className="activity-row" key={a.id}>
                <span>{a.event}</span>
                <time>{new Date(a.created_at).toLocaleString()}</time>
              </div>
            ))
          ) : (
            <p className="help">
              Activity will appear as you manage your lodge.
            </p>
          )}
        </section>
      </div>
    </AdminLayout>
  );
}
