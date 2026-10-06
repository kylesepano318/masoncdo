import { useState } from "react";
import type { Section } from "../../types";
import Lightbox from "../public/Lightbox";
import { Heading, Body } from "./Common";
export default function HistoricalDocumentSection({
  section,
}: {
  section: Section;
}) {
  const [open, setOpen] = useState(false);
  const s = section.settings || {};
  return (
    <section
      className={`section document-section ${s.full_width ? "full-width" : ""}`}
      style={{ background: s.background, color: s.text_color }}
    >
      <Heading section={section} />
      {section.image && (
        <figure>
          <button
            className="image-zoom"
            onClick={() => setOpen(true)}
            aria-label={`Enlarge ${s.alt || section.title || "historical image"}`}
          >
            <img
              src={section.image}
              alt={s.alt || section.title || "Lodge archive image"}
              loading="lazy"
            />
            <span>VIEW PHOTOGRAPH ↗</span>
          </button>
          {s.caption && <figcaption>{s.caption}</figcaption>}
        </figure>
      )}
      <Body html={section.body} />
      {open && section.image && (
        <Lightbox
          image={section.image}
          alt={s.alt || section.title || ""}
          onClose={() => setOpen(false)}
        />
      )}
    </section>
  );
}
