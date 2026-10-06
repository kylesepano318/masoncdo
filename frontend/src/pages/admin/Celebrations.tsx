import { useState } from "react";
import { useApiForm, apiActions } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Checkbox, Errors } from "../../components/ui/Form";
import RichEditor from "../../components/admin/RichEditor";
import CelebrationTypes from "../../components/admin/CelebrationTypes";
import MediaPicker from "../../components/admin/MediaPicker";
import Pagination from "../../components/admin/Pagination";
import type {
  Celebration,
  CelebrationType,
  MediaItem,
  Paginated,
  SectionItem,
} from "../../types";
type MemberChoice = { id: number; first_name: string; last_name: string };
function Editor({
  event,
  media,
  members,
  types,
  onClose,
}: {
  event: Celebration | null;
  media: MediaItem[];
  members: MemberChoice[];
  types: CelebrationType[];
  onClose: () => void;
}) {
  const f = useApiForm({
    title: event?.title || "",
    category:
      event?.category ||
      types.find((t) => t.slug === "birthday")?.slug ||
      types[0]?.slug ||
      "",
    event_date: event?.event_date || "",
    location: event?.location || "",
    description: event?.description || "",
    image: event?.image || "",
    gallery: event?.gallery || ([] as SectionItem[]),
    member_id: event?.member_id || "",
    is_public: event?.is_public ?? true,
  });
  return (
    <form
      className="admin-panel editor-form"
      onSubmit={(e) => {
        e.preventDefault();
        const options = { onSuccess: onClose };
        event
          ? f.put(`/admin/celebrations/${event.id}`, options)
          : f.post("/admin/celebrations", options);
      }}
    >
      <div className="editor-title">
        <h2>{event ? "Edit celebration" : "Add celebration"}</h2>
        <button type="button" onClick={onClose}>
          Close ×
        </button>
      </div>
      <Errors errors={f.errors} />
      <div className="form-grid">
        <Field label="Title *">
          <input
            required
            value={f.data.title}
            onChange={(e) => f.setData("title", e.target.value)}
          />
        </Field>
        <Field label="Celebration type">
          <select
            required
            value={f.data.category}
            onChange={(e) => f.setData("category", e.target.value)}
          >
            <option value="" disabled>
              Select a type
            </option>
            {types.map((type) => (
              <option key={type.id} value={type.slug}>
                {type.name}
              </option>
            ))}
          </select>
        </Field>
        <Field label="Celebration date *">
          <input
            type="date"
            required
            value={f.data.event_date}
            onChange={(e) => f.setData("event_date", e.target.value)}
          />
        </Field>
        <Field label="Associated member (optional)">
          <select
            value={f.data.member_id}
            onChange={(e) =>
              f.setData(
                "member_id",
                e.target.value ? Number(e.target.value) : "",
              )
            }
          >
            <option value="">No associated member</option>
            {members.map((m) => (
              <option key={m.id} value={m.id}>
                {m.first_name} {m.last_name}
              </option>
            ))}
          </select>
        </Field>
      </div>
      <Field label="Location">
        <input
          value={f.data.location}
          onChange={(e) => f.setData("location", e.target.value)}
        />
      </Field>
      <p className="field-title">Details</p>
      <RichEditor
        value={f.data.description}
        onChange={(v) => f.setData("description", v)}
      />
      <MediaPicker
        label="Featured photograph"
        allowUpload
        media={media}
        value={f.data.image}
        onChange={(v) => f.setData("image", v)}
      />
      <p className="help">
        Choose a greeting image here independently of the associated member’s
        profile photo.
      </p>
      <h3>Celebration photographs</h3>
      <p className="help">
        Add multiple photographs and captions for birthdays, ceremonies, or
        other celebrations (up to 30 photographs).
      </p>
      {f.data.gallery.map((item, i) => (
        <div className="item-editor" key={i}>
          <MediaPicker
            label="Photograph"
            allowUpload
            media={media}
            value={item.image || ""}
            onChange={(v) =>
              f.setData(
                "gallery",
                f.data.gallery.map((p, j) =>
                  j === i ? { ...p, image: v } : p,
                ),
              )
            }
          />
          <Field label="Photo caption">
            <input
              value={item.title || ""}
              onChange={(e) =>
                f.setData(
                  "gallery",
                  f.data.gallery.map((p, j) =>
                    j === i ? { ...p, title: e.target.value } : p,
                  ),
                )
              }
            />
          </Field>
          <button
            className="danger-link"
            type="button"
            onClick={() =>
              f.setData(
                "gallery",
                f.data.gallery.filter((_, j) => j !== i),
              )
            }
          >
            Remove photograph
          </button>
        </div>
      ))}
      <button
        type="button"
        className="admin-button secondary"
        disabled={f.data.gallery.length >= 30}
        onClick={() =>
          f.setData("gallery", [...f.data.gallery, { image: "", title: "" }])
        }
      >
        Add photograph
      </button>
      <Checkbox
        label="Public — display this celebration on the website"
        checked={f.data.is_public}
        onChange={(v) => f.setData("is_public", v)}
      />
      <p className="help">
        New birthdays default to Public. Private entries stay in the admin.
        Upcoming public entries appear only in their calendar year. Use the date
        of this year’s birthday celebration; the member’s birth year is not
        needed.
      </p>
      <button className="admin-button" disabled={f.processing}>
        {f.processing ? "Saving…" : "Save celebration"}
      </button>
    </form>
  );
}
export default function Celebrations({
  celebrations,
  media,
  members,
  types,
}: {
  celebrations: Paginated<Celebration>;
  media: MediaItem[];
  members: MemberChoice[];
  types: CelebrationType[];
}) {
  const [showTypes, setShowTypes] = useState(false);
  const [editing, setEditing] = useState<Celebration | null | undefined>();
  return (
    <AdminLayout
      title="Celebrations & birthdays"
      actions={
        <div className="admin-actions">
          <button
            className="admin-button secondary"
            onClick={() => {
              setEditing(undefined);
              setShowTypes(true);
            }}
          >
            Manage types
          </button>
          <button
            className="admin-button"
            disabled={!types.length}
            onClick={() => {
              setShowTypes(false);
              setEditing(null);
            }}
          >
            Add celebration
          </button>
        </div>
      }
    >
      <p className="help">
        Manage birthdays, degree advancements, anniversaries, and lodge
        gatherings. Future-year entries are saved here and appear publicly when
        their year begins.
      </p>
      {showTypes && (
        <CelebrationTypes types={types} onClose={() => setShowTypes(false)} />
      )}
      {!types.length && (
        <p className="help">
          Add a celebration type before creating a celebration.
        </p>
      )}
      {editing !== undefined && (
        <Editor
          key={editing?.id || "new"}
          event={editing}
          media={media}
          members={members}
          types={types}
          onClose={() => setEditing(undefined)}
        />
      )}
      <div className="admin-panel table-wrap">
        <table>
          <thead>
            <tr>
              <th>Celebration</th>
              <th>Type</th>
              <th>Date</th>
              <th>Visibility</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {celebrations.data.map((c) => (
              <tr key={c.id}>
                <td>{c.title}</td>
                <td>{c.category_label || c.category.replaceAll("_", " ")}</td>
                <td>{c.event_date}</td>
                <td>
                  <span className="badge">
                    {c.is_public ? "Public" : "Private"}
                  </span>
                </td>
                <td>
                  <button onClick={() => setEditing(c)}>Edit</button>
                  <button
                    className="danger-link"
                    onClick={() => {
                      if (confirm("Delete this celebration?"))
                        apiActions.delete(`/admin/celebrations/${c.id}`);
                    }}
                  >
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
        {!celebrations.data.length && (
          <p className="empty-admin">
            Add the first celebration or member birthday.
          </p>
        )}
      </div>
      <Pagination links={celebrations.links} />
    </AdminLayout>
  );
}
