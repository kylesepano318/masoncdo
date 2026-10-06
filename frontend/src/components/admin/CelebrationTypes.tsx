import { useApiForm, apiActions } from "../../lib/ui";
import { Field, Errors } from "../ui/Form";
import type { CelebrationType } from "../../types";

export default function CelebrationTypes({
  types,
  onClose,
}: {
  types: CelebrationType[];
  onClose: () => void;
}) {
  const form = useApiForm({ name: "" });
  return (
    <section className="admin-panel">
      <div className="editor-title">
        <h2>Celebration types</h2>
        <button type="button" onClick={onClose}>
          Close
        </button>
      </div>
      <form
        className="editor-form"
        onSubmit={(e) => {
          e.preventDefault();
          form.post("/admin/celebration-types", {
            onSuccess: () => form.reset(),
          });
        }}
      >
        <Errors errors={form.errors} />
        <Field label="Celebration type name">
          <input
            required
            maxLength={100}
            value={form.data.name}
            onChange={(e) => form.setData("name", e.target.value)}
          />
        </Field>
        <button className="admin-button" disabled={form.processing}>
          {form.processing ? "Saving…" : "Add type"}
        </button>
      </form>
      <p className="help">
        Types are shared by all celebrations. A type can be deleted after all
        celebrations using it have been reassigned or removed.
      </p>
      <div className="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Type</th>
              <th>Celebrations</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {types.map((type) => (
              <tr key={type.id}>
                <td>{type.name}</td>
                <td>{type.celebrations_count}</td>
                <td>
                  <button
                    type="button"
                    className="danger-link"
                    disabled={type.celebrations_count > 0}
                    aria-label={`Delete type ${type.name}`}
                    onClick={() => {
                      if (confirm(`Delete celebration type ${type.name}?`))
                        apiActions.delete(
                          `/admin/celebration-types/${type.id}`,
                        );
                    }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
