import { useState } from "react";
import { useLocation } from "react-router-dom";
import { Link, apiActions } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import Pagination from "../../components/admin/Pagination";
import type { ApplicationRecord, Paginated } from "../../types";
export default function Applications({
  applications,
}: {
  applications: Paginated<ApplicationRecord>;
}) {
  const location = useLocation(),
    params = new URLSearchParams(location.search);
  const [search, setSearch] = useState(params.get("search") || "");
  const filter = (key: string, value: string) => {
    params.set(key, value);
    params.delete("page");
    apiActions.get("/admin/applications", Object.fromEntries(params));
  };
  return (
    <AdminLayout title="Applications">
      <form
        className="search-bar"
        onSubmit={(e) => {
          e.preventDefault();
          filter("search", search);
        }}
      >
        <input
          aria-label="Search applications"
          placeholder="Reference, applicant or email"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="admin-button">Search</button>
        <select
          aria-label="Filter applications by status"
          value={params.get("status") || ""}
          onChange={(e) => filter("status", e.target.value)}
        >
          <option value="">All statuses</option>
          {["pending", "under_review", "approved", "rejected"].map((s) => (
            <option key={s} value={s}>
              {s.replaceAll("_", " ")}
            </option>
          ))}
        </select>
        <select
          aria-label="Filter unread applications"
          value={params.get("unread") || "0"}
          onChange={(e) => filter("unread", e.target.value)}
        >
          <option value="0">All applications</option>
          <option value="1">Unread only</option>
        </select>
        <select
          aria-label="Sort applications"
          value={params.get("sort") || "submitted_at"}
          onChange={(e) => filter("sort", e.target.value)}
        >
          <option value="submitted_at">Submitted date</option>
          <option value="last_name">Applicant name</option>
          <option value="reference_number">Reference</option>
          <option value="status">Status</option>
        </select>
        <select
          aria-label="Sort direction"
          value={params.get("direction") || "desc"}
          onChange={(e) => filter("direction", e.target.value)}
        >
          <option value="desc">Descending</option>
          <option value="asc">Ascending</option>
        </select>
      </form>
      <div className="admin-panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Reference</th>
              <th>Applicant</th>
              <th>Email</th>
              <th>Submitted</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {applications.data.map((a) => (
              <tr key={a.id}>
                <td>
                  {a.reference_number}{" "}
                  {!a.is_read_by_admin && (
                    <span className="badge pending">NEW</span>
                  )}
                </td>
                <td>
                  {a.first_name} {a.last_name}
                </td>
                <td>{String(a.email)}</td>
                <td>{new Date(String(a.submitted_at)).toLocaleString()}</td>
                <td>
                  <span className={`badge ${a.status}`}>
                    {a.status.replaceAll("_", " ")}
                  </span>
                </td>
                <td>
                  <Link href={`/admin/applications/${a.id}`}>
                    View application →
                  </Link>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!applications.data.length && (
          <p className="empty-admin">No applications match these filters.</p>
        )}
      </div>
      <Pagination links={applications.links} />
    </AdminLayout>
  );
}
