import { useApiForm } from "../../lib/ui";
import { Field, Errors, Checkbox } from "../ui/Form";
import type { Position } from "../../types";

export default function MembershipPositionForm({
  positions,
  onClose,
}: {
  positions: Position[];
  onClose: () => void;
}) {
  const form = useApiForm({
    name: "",
    rank: Math.min(
      1000,
      Math.max(0, ...positions.map((position) => position.rank)) + 1,
    ),
    is_officer: false,
  });
  return (
    <section className="admin-panel">
      <h2>Add membership position</h2>
      <p className="help">
        Existing positions:{" "}
        {positions.map((position) => position.name).join(", ")}.
      </p>
      <form
        onSubmit={(event) => {
          event.preventDefault();
          void form.post("/admin/membership-positions", { onSuccess: onClose });
        }}
      >
        <Errors errors={form.errors} />
        <div className="form-grid">
          <Field label="Position name" error={form.errors.name}>
            <input
              required
              maxLength={100}
              value={form.data.name}
              onChange={(event) => form.setData("name", event.target.value)}
              placeholder="For example, Secretary or Honorary Member"
            />
          </Field>
          <Field label="Display order" error={form.errors.rank}>
            <input
              type="number"
              required
              min={1}
              max={1000}
              value={form.data.rank}
              onChange={(event) =>
                form.setData("rank", Number(event.target.value))
              }
            />
          </Field>
        </div>
        <Checkbox
          label="Officer position — one active member at a time"
          checked={form.data.is_officer}
          onChange={(value) => form.setData("is_officer", value)}
        />
        <p className="help">
          Lower display numbers appear first in position lists. Officer
          positions appear in the officers section; regular positions allow
          multiple members.
        </p>
        <div className="admin-actions">
          <button className="admin-button" disabled={form.processing}>
            {form.processing ? "Saving…" : "Save position"}
          </button>
          <button
            type="button"
            className="admin-button secondary"
            disabled={form.processing}
            onClick={onClose}
          >
            Cancel
          </button>
        </div>
      </form>
    </section>
  );
}
