import { Link } from "../../lib/ui";
import { ArrowUpRight } from "lucide-react";
import type { Section } from "../../types";
export function Heading({ section }: { section: Section }) {
  return (
    <div className="section-heading">
      {section.settings?.eyebrow && (
        <p className="eyebrow">{section.settings.eyebrow}</p>
      )}
      {section.subtitle && <p className="eyebrow">{section.subtitle}</p>}
      {section.title && <h2>{section.title}</h2>}
      <div className="ornament" aria-hidden="true">
        <span />◇<span />
      </div>
    </div>
  );
}
export function Body({ html }: { html: string | null | undefined }) {
  return html ? (
    <div className="rich-content" dangerouslySetInnerHTML={{ __html: html }} />
  ) : null;
}
export function SectionButton({ section }: { section: Section }) {
  return section.settings?.button_text && section.settings.button_url ? (
    <Link href={section.settings.button_url} className="ceremonial-button">
      {section.settings.button_text}
      <ArrowUpRight size={16} />
    </Link>
  ) : null;
}
