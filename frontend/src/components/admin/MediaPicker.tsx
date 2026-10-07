import type { MediaItem } from "../../types";
import { useState, useRef, useEffect } from "react";
import { useApiForm } from "../../lib/ui";
import { http } from "../../lib/api";
import { Field, Errors } from "../ui/Form";
export default function MediaPicker({
  label,
  value,
  onChange,
  media,
  allowUpload = false,
}: {
  label: string;
  value: string | null;
  onChange: (v: string) => void;
  media: MediaItem[];
  allowUpload?: boolean;
}) {
  const [uploaded, setUploaded] = useState<MediaItem[]>([]);
  const [browsing, setBrowsing] = useState(false);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [library, setLibrary] = useState(media);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState("");
  useEffect(() => {
    if (!browsing) return;
    const controller = new AbortController();
    setLoading(true);
    setLoadError("");
    const timer = setTimeout(() => {
      void http
        .get("/admin/media", {
          params: { search, page, type: "image" },
          signal: controller.signal,
        })
        .then(({ data }) => {
          setLibrary(data.media.data);
          setLastPage(data.media.last_page);
        })
        .catch(() => {
          if (!controller.signal.aborted)
            setLoadError("Unable to load images. Please try again.");
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 250);
    return () => {
      clearTimeout(timer);
      controller.abort();
    };
  }, [browsing, search, page]);
  const fileInput = useRef<HTMLInputElement>(null);
  const upload = useApiForm({ file: null as File | null, alt_text: "" });
  const supplied = [
    "golden-friendship-lodge-no-40",
    "lodge-brethren",
    "lodge-ceremony",
  ];
  const choices = [
    ...new Map(
      [...library, ...uploaded].map((item) => [item.path, item]),
    ).values(),
  ];
  const knownImage =
    choices.some((item) => item.path === value) ||
    supplied.some((name) => `/images/${name}.jpg` === value);
  return (
    <div className="media-picker">
      <Field label={label}>
        <select
          value={value || ""}
          onChange={(e) => onChange(e.target.value)}
          disabled={loading && browsing}
        >
          <option value="">No image</option>
          {value && !knownImage && (
            <option value={value}>Current selected image</option>
          )}
          <optgroup label="Supplied lodge images">
            {supplied.map((n) => (
              <option key={n} value={`/images/${n}.jpg`}>
                {n.replaceAll("-", " ")}
              </option>
            ))}
          </optgroup>
          <optgroup label="Media library">
            {choices.map((m) => (
              <option key={m.id} value={m.path}>
                {m.alt_text || m.original_name}
              </option>
            ))}
          </optgroup>
        </select>
      </Field>
      {value && (
        <img
          src={value}
          alt="Selected image preview"
          loading="lazy"
          decoding="async"
        />
      )}
      <button
        type="button"
        className="admin-button secondary"
        aria-expanded={browsing}
        onClick={() => {
          setBrowsing((v) => !v);
          setLoading(false);
        }}
      >
        {browsing ? "Close library browser" : "Browse media library"}
      </button>
      {browsing && (
        <div>
          <Field label={`Search ${label.toLowerCase()} in media library`}>
            <input
              type="search"
              maxLength={255}
              value={search}
              onKeyDown={(e) => {
                if (e.key === "Enter") e.preventDefault();
              }}
              onChange={(e) => {
                setSearch(e.target.value);
                setPage(1);
              }}
            />
          </Field>
          {loading && <p role="status">Loading images…</p>}
          {loadError && <p role="alert">{loadError}</p>}
          <div className="admin-actions">
            <button
              type="button"
              disabled={loading || page <= 1}
              onClick={() => setPage((p) => p - 1)}
            >
              Previous images
            </button>
            <span>
              Page {page} of {lastPage}
            </span>
            <button
              type="button"
              disabled={loading || page >= lastPage}
              onClick={() => setPage((p) => p + 1)}
            >
              Next images
            </button>
          </div>
          <p className="help">
            Choose an image from the dropdown above. Search finds older images
            by filename or description.
          </p>
        </div>
      )}
      {allowUpload && (
        <div>
          <Field label={`Choose ${label.toLowerCase()} file`}>
            <input
              ref={fileInput}
              type="file"
              accept="image/jpeg,image/png,image/webp"
              disabled={upload.processing}
              onChange={(e) => {
                const file = e.target.files?.[0] || null;
                upload.setData({
                  file,
                  alt_text: file?.name.slice(0, 255) || "",
                });
              }}
            />
          </Field>
          <Errors errors={upload.errors} />
          <button
            type="button"
            className="admin-button secondary"
            disabled={upload.processing || !upload.data.file}
            onClick={() =>
              upload.post("/admin/media", {
                forceFormData: true,
                refresh: false,
                onSuccess: (data) => {
                  const item = data.media as MediaItem;
                  setUploaded((items) => [...items, item]);
                  onChange(item.path);
                  upload.reset();
                  if (fileInput.current) fileInput.current.value = "";
                },
              })
            }
          >
            {upload.processing ? "Uploading…" : `Upload ${label.toLowerCase()}`}
          </button>
          <p className="help">
            JPEG, PNG, or WebP, up to 8 MB. Uploads are saved to the Media
            library and selected here. Save the celebration to keep your
            changes.
          </p>
        </div>
      )}
      {!allowUpload && (
        <p className="help">
          Upload new images in Media library, then select them here.
        </p>
      )}
    </div>
  );
}
