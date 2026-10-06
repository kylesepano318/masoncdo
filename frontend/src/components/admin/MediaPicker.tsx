import type { MediaItem } from "../../types";
import { Field } from "../ui/Form";
export default function MediaPicker({
  label,
  value,
  onChange,
  media,
}: {
  label: string;
  value: string | null;
  onChange: (v: string) => void;
  media: MediaItem[];
}) {
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
            {media.map((m) => (
              <option key={m.id} value={m.path}>
                {m.alt_text || m.original_name}
              </option>
            ))}
          </optgroup>
        </select>
      </Field>
      {value && <img src={value} alt="Selected image preview" />}
      <p className="help">
        Upload new images in Media library, then select them here.
      </p>
    </div>
  );
}
