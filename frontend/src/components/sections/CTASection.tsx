import type { Section } from "../../types";
import { Heading, Body, SectionButton } from "./Common";
export default function CTASection({ section }: { section: Section }) {
  return (
    <section
      className="section cta-section"
      style={{
        background: section.settings?.background || "var(--color-secondary)",
        color: section.settings?.text_color || "var(--color-background)",
      }}
    >
      <Heading section={section} />
      <Body html={section.body} />
      <SectionButton section={section} />
    </section>
  );
}
