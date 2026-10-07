import { Link, apiActions } from "../../lib/ui";
import { useState } from "react";
import AdminLayout from "../../layouts/AdminLayout";
import Pagination from "../../components/admin/Pagination";
import MembershipPositionForm from "../../components/admin/MembershipPositionForm";
import type { AdminMember, Paginated, Position } from "../../types";
export default function Members({
  members,
  positions,
}: {
  members: Paginated<AdminMember>;
  positions: Position[];
}) {
  const [search, setSearch] = useState("");
  const [addingPosition, setAddingPosition] = useState(false);
  const move = (index: number, direction: number) => {
    const ids = members.data.map((m) => m.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    apiActions.post("/admin/members/reorder", { ids });
  };
  return (
    <AdminLayout
      title="Members"
      actions={
        <>
          <button
            className="admin-button secondary"
            onClick={() => setAddingPosition(true)}
          >
            Add position
          </button>
          <Link className="admin-button" href="/admin/members/create">
            Add member
          </Link>
        </>
      }
    >
      {addingPosition && (
        <MembershipPositionForm
          positions={positions}
          onClose={() => setAddingPosition(false)}
        />
      )}
      <form
        className="search-bar"
        onSubmit={(e) => {
          e.preventDefault();
          apiActions.get("/admin/members", { search });
        }}
      >
        <input
          aria-label="Search members"
          placeholder="Search members…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="admin-button secondary">Search</button>
      </form>
      <div className="admin-panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Position</th>
              <th>Status</th>
              <th>Visibility</th>
              <th>Order</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {members.data.map((m, i) => (
              <tr key={m.id}>
                <td>
                  {m.first_name} {m.last_name}
                </td>
                <td>{m.position?.name}</td>
                <td>
                  <span className={`badge ${m.status}`}>
                    {m.status.replaceAll("_", " ")}
                  </span>
                </td>
                <td>{m.is_public ? "Public" : "Private"}</td>
                <td>
                  {!m.position?.is_officer && (
                    <>
                      <button
                        aria-label={`Move ${m.first_name} up`}
                        onClick={() => move(i, -1)}
                      >
                        ↑
                      </button>
                      <button
                        aria-label={`Move ${m.first_name} down`}
                        onClick={() => move(i, 1)}
                      >
                        ↓
                      </button>
                    </>
                  )}
                </td>
                <td>
                  <Link href={`/admin/members/${m.id}/edit`}>Edit</Link>
                  <button
                    className="danger-link"
                    onClick={() => {
                      if (confirm(`Permanently delete ${m.first_name} ${m.last_name}? This cannot be undone. Related celebrations will remain but will no longer be linked to this member.`))
                        apiActions.delete(`/admin/members/${m.id}`);
                    }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!members.data.length && (
          <p className="empty-admin">
            No members yet. Add your officers and brethren to begin.
          </p>
        )}
      </div>
      <Pagination links={members.links} />
    </AdminLayout>
  );
}
