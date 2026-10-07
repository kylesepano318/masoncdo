import { useSiteContext } from "../../lib/ui";
import type { Shared } from "../../types";
import type { Section } from "../../types";
import { Body, SectionButton } from "./Common";
export default function HeroSection({ section }: { section: Section }) {
  const s = section.settings || {};
  const { site } = useSiteContext<Shared>().props;
  const emblem =
    section.image ||
    (section.title?.includes("S:.") ? site.branding?.federation_logo : "");
  const bg = s.style === "background";
  return (
    <section
      className={`hero-section ${s.lodge_hero || section.title?.includes("S:.") ? "federation-hero" : ""} ${bg ? "with-background" : ""} ${s.background_fit === "contain" ? "image-contained" : ""}`}
      style={{
        background: s.background || "var(--color-secondary)",
        color: s.text_color || "var(--color-background)",
        minHeight: s.height || undefined,
        textAlign: s.alignment || "center",
      }}
    >
      {bg && (
        <picture className="hero-bg">
          {section.mobile_image && (
            <source media="(max-width: 640px)" srcSet={section.mobile_image} />
          )}
          <img
            src={s.background_image || section.image || ""}
            alt=""
            style={{
              objectPosition: s.background_position || "center",
              objectFit: s.background_fit || "cover",
            }}
          />
        </picture>
      )}
      {bg && (
        <div
          className="hero-overlay"
          style={{ opacity: s.overlay_opacity ?? 0.65 }}
        />
      )}
      <div className="hero-frame" aria-hidden="true" />
      <div className="hero-content">
        {s.eyebrow && <p className="eyebrow">{s.eyebrow}</p>}
        {emblem && !bg && (
          <img
            className="federation-emblem"
            src={emblem}
            alt={s.alt || "Organization emblem"}
          />
        )}
        <div className="hero-symbol" aria-hidden="true">
          ◇
        </div>
        <h1>{section.title}</h1>
        {section.subtitle && (
          <p className="hero-subtitle">{section.subtitle}</p>
        )}
        <div className="ornament" aria-hidden="true">
          <span />✦<span />
        </div>
        <Body html={section.body} />
        <SectionButton section={section} />
      </div>
      {section.title?.includes("S:.") && (
        <div className="hero-footnote">
          <span>CAGAYAN DE ORO CITY</span>
          <span>SCROLL TO EXPLORE ↓</span>
          <span>PHILIPPINES</span>
        </div>
      )}
    </section>
  );
}
