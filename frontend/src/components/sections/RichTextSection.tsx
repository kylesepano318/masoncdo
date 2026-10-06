import type { Section } from "../../types";
import { Body, Heading, SectionButton } from "./Common";
export default function RichTextSection({ section }: { section: Section }) {
  return (
    <section
      className="section rich-section"
      style={{
        background: section.settings?.background,
        color: section.settings?.text_color,
      }}
    >
      <Heading section={section} />
      <Body html={section.body} />
      <SectionButton section={section} />
    </section>
  );
}
