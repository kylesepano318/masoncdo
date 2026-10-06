import type { Section } from "../../types";
import { Heading, Body } from "./Common";
export default function TimelineSection({ section }: { section: Section }) {
  return (
    <section className="section timeline-section">
      <Heading section={section} />
      <Body html={section.body} />
      <ol className="timeline">
        {(section.settings?.items || []).map((item, i) => (
          <li key={i}>
            <span>{item.year}</span>
            <div>
              <h3>{item.title}</h3>
              <p>{item.description}</p>
              {item.image && (
                <img src={item.image} alt={item.title || ""} loading="lazy" />
              )}
              {item.url && (
                <a href={item.url} className="text-link">
                  Reference ↗
                </a>
              )}
            </div>
          </li>
        ))}
      </ol>
    </section>
  );
}
