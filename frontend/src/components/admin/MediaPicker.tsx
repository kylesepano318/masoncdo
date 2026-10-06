import type { MediaItem } from "../../types";
import { useState, useRef } from "react";
import { useApiForm } from "../../lib/ui";
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
  const fileInput = useRef<HTMLInputElement>(null);
  const upload = useApiForm({ file: null as File | null, alt_text: "" });
  return (
    <div className="media-picker">
      <Field label={label}>
        <select value={value || ""} onChange={(e) => onChange(e.target.value)}>
          <option value="">No image</option>
          <optgroup label="Supplied lodge images">
            {[
              "golden-friendship-lodge-no-40",
              "lodge-brethren",
              "lodge-ceremony",
            ].map((n) => (
              <option key={n} value={`/images/${n}.jpg`}>
                {n.replaceAll("-", " ")}
              </option>
            ))}
          </optgroup>
          <optgroup label="Media library">
            {[...media, ...uploaded].map((m) => (
              <option key={m.id} value={m.path}>
                {m.alt_text || m.original_name}
              </option>
            ))}
          </optgroup>
        </select>
      </Field>
      {value && <img src={value} alt="Selected image preview" />}
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
