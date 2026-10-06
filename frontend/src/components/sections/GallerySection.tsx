import { useState } from "react";
import type { Section, SectionItem } from "../../types";
import { Heading, Body } from "./Common";
import Lightbox from "../public/Lightbox";
export default function GallerySection({ section }: { section: Section }) {
  const [selected, setSelected] = useState<SectionItem | null>(null);
  return (
    <section className="section gallery-section">
      <Heading section={section} />
      <div className="gallery-grid">
        {(section.settings?.items || []).map((item, i) => (
          <figure key={i}>
            <button
              onClick={() => setSelected(item)}
              aria-label={`Enlarge ${item.title || "photograph"}`}
            >
              <img
                src={item.image}
                alt={item.title || "Lodge photograph"}
                loading="lazy"
              />
            </button>
            <figcaption>{item.title}</figcaption>
          </figure>
        ))}
      </div>
      <Body html={section.body} />
      {selected?.image && (
        <Lightbox
          image={selected.image}
          alt={selected.title || ""}
          onClose={() => setSelected(null)}
        />
      )}
    </section>
  );
}
