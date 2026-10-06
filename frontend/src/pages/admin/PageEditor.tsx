import { useState, useEffect } from "react";
import { apiActions, useApiForm, Link } from "../../lib/ui";
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from "@dnd-kit/core";
import type { DragEndEvent } from "@dnd-kit/core";
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
  sortableKeyboardCoordinates,
} from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { GripVertical, Copy, Eye, EyeOff, Trash2, Pencil } from "lucide-react";
import AdminLayout from "../../layouts/AdminLayout";
import SectionEditor from "../../components/admin/SectionEditor";
import { Field, Errors } from "../../components/ui/Form";
import type { PageData, Section, MediaItem } from "../../types";
function Row({ section, onEdit }: { section: Section; onEdit: () => void }) {
  const { attributes, listeners, setNodeRef, transform, transition } =
    useSortable({ id: section.id });
  return (
    <div
      ref={setNodeRef}
      style={{ transform: CSS.Transform.toString(transform), transition }}
      className={`section-row ${!section.is_visible ? "hidden-section" : ""}`}
    >
      <button
        className="drag-handle"
        aria-label={`Reorder ${section.title}`}
        {...attributes}
        {...listeners}
      >
        <GripVertical size={20} />
      </button>
      <div className="section-label">
        <strong>
          {section.title || section.section_type.replaceAll("_", " ")}
        </strong>
        <small>
          {section.section_type.replaceAll("_", " ")} ·{" "}
          {section.is_visible ? "Visible" : "Hidden"}
        </small>
      </div>
      <div className="section-tools">
        <button aria-label="Edit section" onClick={onEdit}>
          <Pencil size={16} />
        </button>
        <button
          aria-label={section.is_visible ? "Hide section" : "Show section"}
          onClick={() =>
            apiActions.put(`/admin/sections/${section.id}`, {
              ...section,
              is_visible: !section.is_visible,
            })
          }
        >
          {section.is_visible ? <Eye size={16} /> : <EyeOff size={16} />}
        </button>
        <button
          aria-label="Duplicate section"
          onClick={() =>
            apiActions.post(`/admin/sections/${section.id}/duplicate`)
          }
        >
          <Copy size={16} />
        </button>
        <button
          aria-label="Delete section"
          onClick={() => {
            if (confirm("Delete this section from the draft?"))
              apiActions.delete(`/admin/sections/${section.id}`);
          }}
        >
          <Trash2 size={16} />
        </button>
      </div>
    </div>
  );
}
export default function PageEditor({
  page,
  media,
}: {
  page: PageData & { id: number; sections: Section[] };
  media: MediaItem[];
}) {
  const [sections, setSections] = useState(page.sections);
  const [editing, setEditing] = useState<Section | null | undefined>(undefined);
  useEffect(() => setSections(page.sections), [page.sections]);
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );
  const f = useApiForm({
    name: page.name,
    meta_title: page.meta_title || "",
    meta_description: page.meta_description || "",
    seo: page.seo || {},
    publish: false,
  });
  const save = (publish: boolean) => {
    f.transform((d) => ({ ...d, publish }));
    f.put(`/admin/pages/${page.id}`);
  };
  const reorder = (e: DragEndEvent) => {
    if (!e.over || e.active.id === e.over.id) return;
    const list = arrayMove(
      sections,
      sections.findIndex((s) => s.id === e.active.id),
      sections.findIndex((s) => s.id === e.over?.id),
    );
    setSections(list);
    apiActions.post(`/admin/pages/${page.id}/reorder`, {
      ids: list.map((s) => s.id),
    });
  };
  return (
    <AdminLayout
      title={`${page.name} page`}
      actions={
        <>
          <Link
            className="admin-button secondary"
            href={`/preview/${page.slug}`}
            target="_blank"
          >
            Preview draft ↗
          </Link>
          <button
            className="admin-button secondary"
            disabled={f.processing}
            onClick={() => save(false)}
          >
            Save draft
          </button>
          <button
            className="admin-button"
            disabled={f.processing}
            onClick={() => save(true)}
          >
            Publish
          </button>
        </>
      }
    >
      <p className="help">
        Section edits are saved to a draft. Publish applies the draft to the
        public website. Drag the handles or use the keyboard to reorder.
      </p>
      <Errors errors={f.errors} />
      <div className="page-builder-grid">
        <div>
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={reorder}
          >
            <SortableContext
              items={sections.map((s) => s.id)}
              strategy={verticalListSortingStrategy}
            >
              {sections.map((s) => (
                <Row key={s.id} section={s} onEdit={() => setEditing(s)} />
              ))}
            </SortableContext>
          </DndContext>
          <button className="add-section" onClick={() => setEditing(null)}>
            + Add section
          </button>
          {editing !== undefined && (
            <SectionEditor
              key={editing?.id || "new"}
              section={editing}
              pageId={page.id}
              media={media}
              onClose={() => setEditing(undefined)}
            />
          )}
        </div>
        <aside className="admin-panel seo-panel">
          <h2>Page & search settings</h2>
          <Field label="Page name">
            <input
              value={f.data.name}
              onChange={(e) => f.setData("name", e.target.value)}
            />
          </Field>
          <Field label="Page title">
            <input
              value={f.data.meta_title}
              onChange={(e) => f.setData("meta_title", e.target.value)}
            />
          </Field>
          <Field label="Meta description">
            <textarea
              value={f.data.meta_description}
              onChange={(e) => f.setData("meta_description", e.target.value)}
            />
          </Field>
          <Field label="OpenGraph title">
            <input
              value={f.data.seo.og_title || ""}
              onChange={(e) =>
                f.setData("seo", { ...f.data.seo, og_title: e.target.value })
              }
            />
          </Field>
          <Field label="OpenGraph description">
            <textarea
              value={f.data.seo.og_description || ""}
              onChange={(e) =>
                f.setData("seo", {
                  ...f.data.seo,
                  og_description: e.target.value,
                })
              }
            />
          </Field>
          <Field label="OpenGraph image">
            <select
              value={f.data.seo.og_image || ""}
              onChange={(e) =>
                f.setData("seo", { ...f.data.seo, og_image: e.target.value })
              }
            >
              <option value="">No image</option>
              <option value="/images/golden-friendship-lodge-no-40.png">
                Lodge emblem
              </option>
              {media.map((m) => (
                <option key={m.id} value={m.path}>
                  {m.alt_text}
                </option>
              ))}
            </select>
          </Field>
        </aside>
      </div>
    </AdminLayout>
  );
}
