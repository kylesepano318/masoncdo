import { useState } from "react";
import { apiActions, useApiForm } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Errors, Field } from "../../components/ui/Form";

type Administrator = {
  id: number;
  name: string;
  username: string | null;
  email: string;
  is_superadmin: boolean;
};

function CreateAdministrator({ onClose }: { onClose: () => void }) {
  const f = useApiForm({
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
  });
  return (
    <form
      className="admin-panel editor-form"
      onSubmit={(e) => {
        e.preventDefault();
        void f.post("/admin/administrators", { onSuccess: onClose });
      }}
    >
      <h2>Add administrator</h2>
      <Errors errors={f.errors} />
      {(
        [
          "name",
          "username",
          "email",
          "password",
          "password_confirmation",
        ] as const
      ).map((key) => (
        <Field
          key={key}
          label={
            key === "password_confirmation"
              ? "Confirm password"
              : key.charAt(0).toUpperCase() + key.slice(1)
          }
          error={f.errors[key]}
        >
          <input
            required
            type={
              key.startsWith("password")
                ? "password"
                : key === "email"
                  ? "email"
                  : "text"
            }
            autoComplete={key.startsWith("password") ? "new-password" : "off"}
            value={f.data[key]}
            onChange={(e) => f.setData(key, e.target.value)}
          />
        </Field>
      ))}
      <p className="help">
        Passwords need at least 12 characters, uppercase and lowercase letters,
        a number, and a symbol. Share credentials privately; this form does not
        email them. The new administrator can change their credentials after
        logging in.
      </p>
      <div className="admin-actions">
        <button className="admin-button" disabled={f.processing}>
          {f.processing ? "Creating…" : "Create administrator"}
        </button>
        <button
          type="button"
          className="admin-button secondary"
          disabled={f.processing}
          onClick={onClose}
        >
          Cancel
        </button>
      </div>
    </form>
  );
}

export default function Administrators({
  administrators,
}: {
  administrators: Administrator[];
}) {
  const [creating, setCreating] = useState(false);
  return (
    <AdminLayout
      title="Administrators"
      actions={
        <button className="admin-button" onClick={() => setCreating(true)}>
          Add administrator
        </button>
      }
    >
      <p className="help">
        Only the two superadministrators can add or delete administrator
        accounts. Superadministrator accounts are protected from deletion.
      </p>
      {creating && <CreateAdministrator onClose={() => setCreating(false)} />}
      <div className="admin-panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Username</th>
              <th>Email</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {administrators.map((admin) => (
              <tr key={admin.id}>
                <td>{admin.name}</td>
                <td>{admin.username || "—"}</td>
                <td>{admin.email}</td>
                <td>
                  <span className="badge">
                    {admin.is_superadmin ? "Superadmin" : "Admin"}
                  </span>
                </td>
                <td>
                  {admin.is_superadmin ? (
                    "Protected account"
                  ) : (
                    <button
                      className="danger-link"
                      onClick={() => {
                        if (
                          confirm(
                            `Permanently delete administrator "${admin.name}"? Their login access will be removed. This cannot be undone.`,
                          )
                        )
                          void apiActions.delete(
                            `/admin/administrators/${admin.id}`,
                          );
                      }}
                    >
                      Delete administrator
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </AdminLayout>
  );
}
