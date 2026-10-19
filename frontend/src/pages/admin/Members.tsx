import { Link, apiActions } from "../../lib/ui";
import { useState } from "react";
import { useLocation } from "react-router-dom";
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
  const [editingPosition, setEditingPosition] = useState<Position>();
  const location = useLocation();
  const params = new URLSearchParams(location.search);
  const managingPositions = params.get("positions") === "1";
  const movePosition = (index: number, direction: number) => {
    const ids = positions.map((position) => position.id);
    const target = index + direction;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    void apiActions.post("/admin/membership-positions/reorder", { ids });
  };
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
            onClick={() => {
              setEditingPosition(undefined);
              setAddingPosition(true);
            }}
          >
            Add position
          </button>
          <button
            className="admin-button secondary"
            onClick={() => {
              if (managingPositions) params.delete("positions");
              else params.set("positions", "1");
              apiActions.get("/admin/members", Object.fromEntries(params));
            }}
          >
            Manage positions
          </button>
          <Link className="admin-button" href="/admin/members/create">
            Add member
          </Link>
        </>
      }
    >
      {addingPosition && (
        <MembershipPositionForm
          key={editingPosition?.id || "new"}
          position={editingPosition}
          positions={positions}
          onClose={() => setAddingPosition(false)}
        />
      )}
      {managingPositions && (
        <div className="admin-panel table-wrap">
          <h2>Membership positions</h2>
          <p className="help">
            Use the arrows to move positions up or down. Positions can be
            deleted only when no members are assigned, including inactive and
            private members.
          </p>
          <table>
            <thead>
              <tr>
                <th>Position</th>
                <th>Order</th>
                <th>Type</th>
                <th>Assigned members</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {positions.map((position, index) => (
                <tr key={position.id}>
                  <td>{position.name}</td>
                  <td>
                    <div className="admin-actions">
                      <span>{position.rank}</span>
                      <button
                        aria-label={`Move ${position.name} position up`}
                        disabled={index === 0}
                        onClick={() => movePosition(index, -1)}
                      >
                        ↑
                      </button>
                      <button
                        aria-label={`Move ${position.name} position down`}
                        disabled={index === positions.length - 1}
                        onClick={() => movePosition(index, 1)}
                      >
                        ↓
                      </button>
                    </div>
                  </td>
                  <td>{position.is_officer ? "Officer" : "Regular"}</td>
                  <td>{position.members_count ?? 0}</td>
                  <td>
                    <button
                      onClick={() => {
                        setEditingPosition(position);
                        setAddingPosition(true);
                      }}
                    >
                      Edit position
                    </button>
                    <button
                      className="danger-link"
                      disabled={(position.members_count ?? 0) > 0}
                      title={
                        (position.members_count ?? 0) > 0
                          ? "Reassign all members before deleting this position."
                          : "Delete this unused position"
                      }
                      onClick={() => {
                        if (
                          confirm(
                            `Permanently delete position "${position.name}"? This cannot be undone.`,
                          )
                        )
                          void apiActions.delete(
                            `/admin/membership-positions/${position.id}`,
                          );
                      }}
                    >
                      Delete position
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <form
        className="search-bar"
        onSubmit={(e) => {
          e.preventDefault();
          apiActions.get("/admin/members", {
            search,
            ...(managingPositions ? { positions: "1" } : {}),
          });
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
                      if (
                        confirm(
                          `Permanently delete ${m.first_name} ${m.last_name}? This cannot be undone. Related celebrations will remain but will no longer be linked to this member.`,
                        )
                      )
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
