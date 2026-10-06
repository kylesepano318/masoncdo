import { useApiForm } from "../../lib/ui";
import type {
  Section,
  SectionSettings,
  MediaItem,
  SectionItem,
} from "../../types";
import { Field, Errors, Checkbox } from "../ui/Form";
import RichEditor from "./RichEditor";
import MediaPicker from "./MediaPicker";
export const sectionTypes = [
  "hero",
  "lodge_feature",
  "rich_text",
  "image_text",
  "video",
  "full_width_image",
  "historical_document",
  "officers",
  "members_grid",
  "timeline",
  "affiliations",
  "gallery",
  "quote",
  "cta",
  "spacer",
  "celebrations",
];
export default function SectionEditor({
  section,
  pageId,
  media,
  onClose,
}: {
  section: Section | null;
  pageId: number;
  media: MediaItem[];
  onClose: () => void;
}) {
  const f = useApiForm({
    section_type: section?.section_type || "rich_text",
    title: section?.title || "",
    subtitle: section?.subtitle || "",
    body: section?.body || "",
    image: section?.image || "",
    mobile_image: section?.mobile_image || "",
    settings: section?.settings || ({} as SectionSettings),
    is_visible: section?.is_visible ?? true,
  });
  const setting = (key: string, value: unknown) =>
    f.setData("settings", { ...f.data.settings, [key]: value });
  const items = f.data.settings.items || [];
  const item = (i: number, key: keyof SectionItem, value: string) =>
    setting(
      "items",
      items.map((v, j) => (j === i ? { ...v, [key]: value } : v)),
    );
  const s = f.data.settings;
  return (
    <div className="admin-panel section-editor">
      <div className="editor-title">
        <h2>{section ? "Edit section" : "Add section"}</h2>
        <button type="button" onClick={onClose}>
          Close ×
        </button>
      </div>
      <form
        onSubmit={(e) => {
          e.preventDefault();
          const options = { onSuccess: onClose };
          if (section) f.put(`/admin/sections/${section.id}`, options);
          else f.post(`/admin/pages/${pageId}/sections`, options);
        }}
      >
        <Errors errors={f.errors} />
        <Field label="Homepage archive layout">
          <select
            value={s.layout || ""}
            onChange={(e) => setting("layout", e.target.value || undefined)}
          >
            <option value="">Full width</option>
            <option value="half">Half width archive column</option>
            <option value="documents">Document and emblem pair</option>
          </select>
        </Field>
        {f.data.section_type === "celebrations" && (
          <Field label="Celebration display">
            <select
              value={s.display_mode || ""}
              onChange={(e) =>
                setting("display_mode", e.target.value || undefined)
              }
            >
              <option value="">Default for this page</option>
              <option value="preview">Upcoming preview (up to 3)</option>
              <option value="all">All upcoming and recent</option>
            </select>
          </Field>
        )}
        <Field label="Section type">
          <select
            value={f.data.section_type}
            onChange={(e) => f.setData("section_type", e.target.value)}
          >
            {sectionTypes.map((t) => (
              <option key={t} value={t}>
                {t.replaceAll("_", " ")}
              </option>
            ))}
          </select>
        </Field>
        <div className="form-grid">
          <Field label="Heading">
            <textarea
              value={f.data.title}
              onChange={(e) => f.setData("title", e.target.value)}
            />
          </Field>
          <Field label="Subtitle">
            <input
              value={f.data.subtitle}
              onChange={(e) => f.setData("subtitle", e.target.value)}
            />
          </Field>
          <Field label="Eyebrow / overline">
            <input
              value={s.eyebrow || ""}
              onChange={(e) => setting("eyebrow", e.target.value)}
            />
          </Field>
          <Field label="Image caption">
            <input
              value={s.caption || ""}
              onChange={(e) => setting("caption", e.target.value)}
            />
          </Field>
        </div>
        <p className="field-title">Content</p>
        {["video", "image_text"].includes(f.data.section_type) && (
          <div className="form-grid">
            <Field label="YouTube URL">
              <input
                type="url"
                value={s.video_url || ""}
                placeholder="https://www.youtube.com/watch?v=..."
                onChange={(e) => setting("video_url", e.target.value)}
              />
            </Field>
            <Field label="Video title (accessibility)">
              <input
                value={s.video_title || ""}
                onChange={(e) => setting("video_title", e.target.value)}
              />
            </Field>
          </div>
        )}
        <RichEditor
          value={f.data.body}
          onChange={(v) => f.setData("body", v)}
        />
        <div className="form-grid">
          <MediaPicker
            label="Section image / emblem"
            media={media}
            value={f.data.image}
            onChange={(v) => f.setData("image", v)}
          />
          <MediaPicker
            label="Mobile image"
            media={media}
            value={f.data.mobile_image}
            onChange={(v) => f.setData("mobile_image", v)}
          />
        </div>
        <Field label="Image alternative text">
          <input
            value={s.alt || ""}
            onChange={(e) => setting("alt", e.target.value)}
          />
        </Field>
        {f.data.section_type === "lodge_feature" && (
          <Checkbox
            label="Use the global Lodge Emblem from Branding"
            checked={!!s.use_lodge_emblem}
            onChange={(v) => setting("use_lodge_emblem", v)}
          />
        )}
        <div className="form-grid">
          <Field label="Button text">
            <input
              value={s.button_text || ""}
              onChange={(e) => setting("button_text", e.target.value)}
            />
          </Field>
          <Field label="Button URL">
            <input
              value={s.button_url || ""}
              placeholder="/history or https://…"
              onChange={(e) => setting("button_url", e.target.value)}
            />
          </Field>
          <Field label="Background color">
            <div className="color-input">
              <input
                type="color"
                value={s.background || "#F8F5EC"}
                onChange={(e) => setting("background", e.target.value)}
              />
              <button type="button" onClick={() => setting("background", null)}>
                Use theme
              </button>
            </div>
          </Field>
          <Field label="Text color">
            <div className="color-input">
              <input
                type="color"
                value={s.text_color || "#444139"}
                onChange={(e) => setting("text_color", e.target.value)}
              />
              <button type="button" onClick={() => setting("text_color", null)}>
                Use theme
              </button>
            </div>
          </Field>
        </div>
        {f.data.section_type === "hero" && (
          <>
            <div className="form-grid">
              <Field label="Display style">
                <select
                  value={s.style || "contained"}
                  onChange={(e) => setting("style", e.target.value)}
                >
                  <option value="contained">Contained image</option>
                  <option value="background">Full background image</option>
                </select>
              </Field>
              <Field label="Text alignment">
                <select
                  value={s.alignment || "center"}
                  onChange={(e) => setting("alignment", e.target.value)}
                >
                  {["left", "center", "right"].map((a) => (
                    <option key={a} value={a}>
                      {a}
                    </option>
                  ))}
                </select>
              </Field>
              <Field label="Minimum height (200–1200px)">
                <input
                  type="number"
                  min={200}
                  max={1200}
                  value={s.height || 520}
                  onChange={(e) => setting("height", Number(e.target.value))}
                />
              </Field>
              <Field label="Overlay opacity (0–1)">
                <input
                  type="number"
                  min={0}
                  max={1}
                  step={0.05}
                  value={s.overlay_opacity ?? 0.65}
                  onChange={(e) =>
                    setting("overlay_opacity", Number(e.target.value))
                  }
                />
              </Field>
            </div>
            <MediaPicker
              label="Background image"
              media={media}
              value={s.background_image || ""}
              onChange={(v) => setting("background_image", v)}
            />
            <Field label="Background fit">
              <select
                value={s.background_fit || "cover"}
                onChange={(e) => setting("background_fit", e.target.value)}
              >
                <option value="cover">Fill banner (may crop)</option>
                <option value="contain">Fit entire image</option>
              </select>
            </Field>
          </>
        )}
        {["historical_document", "full_width_image"].includes(
          f.data.section_type,
        ) && (
          <Checkbox
            label="Full-width presentation"
            checked={!!s.full_width}
            onChange={(v) => setting("full_width", v)}
          />
        )}{" "}
        {f.data.section_type === "image_text" && (
          <Checkbox
            label="Reverse image and text"
            checked={!!s.reverse}
            onChange={(v) => setting("reverse", v)}
          />
        )}{" "}
        {["timeline", "gallery"].includes(f.data.section_type) && (
          <div className="items-editor">
            <h3>
              {f.data.section_type === "timeline"
                ? "Timeline entries"
                : "Gallery images"}
            </h3>
            {items.map((it, i) => (
              <div className="item-editor" key={i}>
                <div className="form-grid">
                  <Field label="Title / caption">
                    <input
                      value={it.title || ""}
                      onChange={(e) => item(i, "title", e.target.value)}
                    />
                  </Field>
                  {f.data.section_type === "timeline" && (
                    <>
                      <Field label="Year">
                        <input
                          value={it.year || ""}
                          onChange={(e) => item(i, "year", e.target.value)}
                        />
                      </Field>
                      <Field label="Description">
                        <textarea
                          value={it.description || ""}
                          onChange={(e) =>
                            item(i, "description", e.target.value)
                          }
                        />
                      </Field>
                      <Field label="Reference URL">
                        <input
                          value={it.url || ""}
                          onChange={(e) => item(i, "url", e.target.value)}
                        />
                      </Field>
                    </>
                  )}
                </div>
                <MediaPicker
                  label="Image"
                  media={media}
                  value={it.image || ""}
                  onChange={(v) => item(i, "image", v)}
                />
                <button
                  type="button"
                  className="danger-link"
                  onClick={() =>
                    setting(
                      "items",
                      items.filter((_, j) => i !== j),
                    )
                  }
                >
                  Remove item
                </button>
              </div>
            ))}
            <button
              type="button"
              className="admin-button secondary"
              onClick={() => setting("items", [...items, {}])}
            >
              Add item
            </button>
          </div>
        )}
        <Checkbox
          label="Section is visible"
          checked={f.data.is_visible}
          onChange={(v) => f.setData("is_visible", v)}
        />
        <button className="admin-button" disabled={f.processing}>
          {f.processing ? "Saving…" : "Save section to draft"}
        </button>
      </form>
    </div>
  );
}
