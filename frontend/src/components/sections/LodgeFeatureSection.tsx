import { useSiteContext } from "../../lib/ui";
import type { Section, Shared } from "../../types";
import { Body, SectionButton } from "./Common";
export default function LodgeFeatureSection({ section }: { section: Section }) {
  const { site } = useSiteContext<Shared>().props;
  const s = section.settings || {};
  return (
    <section
      id="our-lodge"
      className="lodge-feature section"
      style={{ background: s.background, color: s.text_color }}
    >
      <p className="eyebrow">
        {s.eyebrow || "GOLDEN FRIENDSHIP MASONIC LODGE NO. 40"}
      </p>
      <img
        className="lodge-emblem"
        src={(s.use_lodge_emblem ? site.branding?.emblem : section.image) || ""}
        alt={s.alt || "Golden Friendship Masonic Lodge No. 40 emblem"}
        loading="lazy"
      />
      <h2>{section.title}</h2>
      <p className="lodge-subtitle">{section.subtitle}</p>
      <p className="eyebrow location">{s.caption || site.branding?.location}</p>
      <div className="ornament" aria-hidden="true">
        <span />◇<span />
      </div>
      <Body html={section.body} />
      <SectionButton section={section} />
    </section>
  );
}
