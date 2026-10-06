import type { Section, Affiliation } from "../../types";
import { Heading, Body } from "./Common";
export default function AffiliationsSection({
  section,
  affiliations,
}: {
  section: Section;
  affiliations: Affiliation[];
}) {
  return (
    <section className="section affiliations-section">
      <Heading section={section} />
      {affiliations.length ? (
        affiliations.map((a) => (
          <article key={a.id} className="affiliation">
            {a.logo && (
              <img src={a.logo} alt={`${a.name} emblem`} loading="lazy" />
            )}
            <h3>{a.name}</h3>
            {a.subtitle && <p className="eyebrow">{a.subtitle}</p>}
            <p>{a.description}</p>
            {a.website_url && (
              <a
                className="text-link"
                href={a.website_url}
                target="_blank"
                rel="noopener noreferrer"
              >
                Visit organization ↗
              </a>
            )}
          </article>
        ))
      ) : (
        <Body html={section.body} />
      )}
    </section>
  );
}
