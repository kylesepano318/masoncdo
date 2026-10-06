import { useState } from "react";
import { useApiForm, apiActions } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Checkbox, Errors } from "../../components/ui/Form";
import MediaPicker from "../../components/admin/MediaPicker";
import type { Affiliation, MediaItem } from "../../types";
function Editor({
  affiliation: a,
  media,
  onClose,
}: {
  affiliation: Affiliation | null;
  media: MediaItem[];
  onClose: () => void;
}) {
  const f = useApiForm({
    name: a?.name || "",
    logo: a?.logo || "",
    subtitle: a?.subtitle || "",
    description: a?.description || "",
    website_url: a?.website_url || "",
    display_order: a?.display_order || 0,
    is_visible: a?.is_visible ?? true,
  });
  return (
    <form
      className="admin-panel editor-form"
      onSubmit={(e) => {
        e.preventDefault();
        a
          ? f.put(`/admin/affiliations/${a.id}`, { onSuccess: onClose })
          : f.post("/admin/affiliations", { onSuccess: onClose });
      }}
    >
      <Errors errors={f.errors} />
      {(["name", "subtitle", "website_url"] as const).map((k) => (
        <Field key={k} label={k.replaceAll("_", " ")}>
          <input
            value={f.data[k]}
            onChange={(e) => f.setData(k, e.target.value)}
            required={k === "name"}
          />
        </Field>
      ))}
      <Field label="Description">
        <textarea
          value={f.data.description}
          onChange={(e) => f.setData("description", e.target.value)}
        />
      </Field>
      <Field label="Display order">
        <input
          type="number"
          min={0}
          value={f.data.display_order}
          onChange={(e) => f.setData("display_order", Number(e.target.value))}
        />
      </Field>
      <MediaPicker
        label="Organization emblem"
        media={media}
        value={f.data.logo}
        onChange={(v) => f.setData("logo", v)}
      />
      <Checkbox
        label="Visible on the website"
        checked={f.data.is_visible}
        onChange={(v) => f.setData("is_visible", v)}
      />
      <button className="admin-button">Save affiliation</button>
      <button type="button" onClick={onClose}>
        Cancel
      </button>
    </form>
  );
}
export default function Affiliations({
  affiliations,
  media,
}: {
  affiliations: Affiliation[];
  media: MediaItem[];
}) {
  const [editing, setEditing] = useState<Affiliation | null | undefined>();
  return (
    <AdminLayout
      title="Affiliations"
      actions={
        <button className="admin-button" onClick={() => setEditing(null)}>
          Add affiliation
        </button>
      }
    >
      {editing !== undefined && (
        <Editor
          key={editing?.id || "new"}
          affiliation={editing}
          media={media}
          onClose={() => setEditing(undefined)}
        />
      )}
      <div className="admin-panel">
        {affiliations.map((a) => (
          <div className="dashboard-link" key={a.id}>
            <strong>{a.name}</strong>
            <div>
              <button onClick={() => setEditing(a)}>Edit</button>
              <button
                className="danger-link"
                onClick={() => {
                  if (confirm("Delete this affiliation?"))
                    apiActions.delete(`/admin/affiliations/${a.id}`);
                }}
              >
                Delete
              </button>
            </div>
          </div>
        ))}
        {!affiliations.length && (
          <p className="empty-admin">
            No affiliations yet. Add verified organizations here.
          </p>
        )}
      </div>
    </AdminLayout>
  );
}
