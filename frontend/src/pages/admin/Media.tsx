import { useApiForm, apiActions } from "../../lib/ui";
import { useState } from "react";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Errors } from "../../components/ui/Form";
import Pagination from "../../components/admin/Pagination";
import type { MediaItem, Paginated } from "../../types";
function Details({ item }: { item: MediaItem }) {
  const f = useApiForm({
    alt_text: item.alt_text,
    caption: item.caption || "",
  });
  return (
    <form
      className="media-item"
      onSubmit={(e) => {
        e.preventDefault();
        f.put(`/admin/media/${item.id}`);
      }}
    >
      <a href={item.path} target="_blank" rel="noopener noreferrer">
        {item.mime_type === "application/pdf" ? (
          "Open PDF document"
        ) : (
          <img src={item.path} alt={item.alt_text} loading="lazy" />
        )}
      </a>
      <p>{item.original_name}</p>
      <Field label="Public file URL">
        <input
          readOnly
          value={item.path}
          onFocus={(e) => e.currentTarget.select()}
        />
      </Field>
      <Field label="Alternative text">
        <input
          value={f.data.alt_text}
          onChange={(e) => f.setData("alt_text", e.target.value)}
        />
      </Field>
      <Field label="Caption">
        <input
          value={f.data.caption}
          onChange={(e) => f.setData("caption", e.target.value)}
        />
      </Field>
      <div className="admin-actions">
        <button className="admin-button secondary" disabled={f.processing}>
          {f.processing ? "Saving…" : "Save details"}
        </button>
        <button
          type="button"
          className="danger-link"
          onClick={() => {
            if (
              confirm(
                "Delete this file? Files referenced by the website cannot be deleted.",
              )
            )
              apiActions.delete(`/admin/media/${item.id}`);
          }}
        >
          Delete
        </button>
      </div>
    </form>
  );
}
export default function Media({ media }: { media: Paginated<MediaItem> }) {
  const f = useApiForm({
    file: null as File | null,
    alt_text: "",
    caption: "",
  });
  const [search, setSearch] = useState("");
  return (
    <AdminLayout title="Media library">
      <form
        className="admin-panel media-upload"
        onSubmit={(e) => {
          e.preventDefault();
          f.post("/admin/media", {
            forceFormData: true,
            onSuccess: () => f.reset(),
          });
        }}
      >
        <h2>Upload an image or PDF</h2>
        <Errors errors={f.errors} />
        <div className="form-grid">
          <Field label="File (PNG, JPG, WEBP, PDF · max 8 MB)">
            <input
              type="file"
              required
              accept="image/png,image/jpeg,image/webp,application/pdf"
              onChange={(e) => f.setData("file", e.target.files?.[0] || null)}
            />
          </Field>
          <Field label="Alternative text *">
            <input
              required
              value={f.data.alt_text}
              onChange={(e) => f.setData("alt_text", e.target.value)}
            />
          </Field>
          <Field label="Caption">
            <input
              value={f.data.caption}
              onChange={(e) => f.setData("caption", e.target.value)}
            />
          </Field>
        </div>
        <button className="admin-button" disabled={f.processing}>
          {f.processing ? "Uploading…" : "Upload file"}
        </button>
      </form>
      <form
        className="search-bar"
        onSubmit={(e) => {
          e.preventDefault();
          apiActions.get("/admin/media", { search });
        }}
      >
        <input
          aria-label="Search media"
          placeholder="Search media filenames…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
        />
        <button className="admin-button secondary">Search</button>
      </form>
      <div className="media-grid">
        {media.data.map((m) => (
          <Details key={m.id} item={m} />
        ))}
      </div>
      <Pagination links={media.links} />
    </AdminLayout>
  );
}
