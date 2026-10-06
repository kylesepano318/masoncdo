import { useState } from "react";
import type { Section, Celebration } from "../../types";
import { Heading, Body, SectionButton } from "./Common";
import Lightbox from "../public/Lightbox";
function Event({ event }: { event: Celebration }) {
  const [image, setImage] = useState<string | null>(null);
  return (
    <article className="celebration-entry">
      <time dateTime={event.event_date}>
        {new Date(`${event.event_date}T12:00:00`).toLocaleDateString("en-PH", {
          month: "short",
          day: "numeric",
          year: "numeric",
        })}
      </time>
      <div>
        <p className="eyebrow">{event.category.replaceAll("_", " ")}</p>
        <h3>{event.title}</h3>
        {event.location && <p className="event-location">{event.location}</p>}
        <Body html={event.description} />
        {event.image && (
          <button
            className="event-image"
            onClick={() => setImage(event.image)}
            aria-label={`Enlarge ${event.title}`}
          >
            <img src={event.image} alt={event.title} loading="lazy" />
          </button>
        )}
        <div className="event-gallery">
          {event.gallery?.map((item, i) => (
            <button
              key={i}
              onClick={() => setImage(item.image || null)}
              aria-label={`Enlarge ${item.title || "celebration photograph"}`}
            >
              <img
                src={item.image}
                alt={item.title || event.title}
                loading="lazy"
              />
            </button>
          ))}
        </div>
      </div>
      {image && (
        <Lightbox
          image={image}
          alt={event.title}
          onClose={() => setImage(null)}
        />
      )}
    </article>
  );
}
export default function CelebrationsSection({
  section,
  celebrations,
  today,
  preview = false,
  compact = false,
}: {
  section: Section;
  celebrations: Celebration[];
  today: string;
  preview?: boolean;
  compact?: boolean;
}) {
  const upcoming = celebrations.filter((c) => c.event_date >= today);
  const recent = celebrations.filter((c) => c.event_date < today).reverse();
  const showAll = section.settings?.display_mode
    ? section.settings.display_mode === "all"
    : !compact;
  return (
    <section className="section celebrations-section">
      <Heading section={section} />
      {!celebrations.length ? (
        <p className="empty-public">
          Our next celebrations and milestones will be announced here.
        </p>
      ) : (
        <>
          {upcoming.length > 0 && (
            <>
              <h3 className="event-group-title">Upcoming celebrations</h3>
              {(showAll ? upcoming : upcoming.slice(0, 3)).map((c) => (
                <Event key={c.id} event={c} />
              ))}
            </>
          )}
          {showAll && recent.length > 0 && (
            <>
              <h3 className="event-group-title">Recent moments</h3>
              {recent.map((c) => (
                <Event key={c.id} event={c} />
              ))}
            </>
          )}
        </>
      )}
      <SectionButton section={section} />
      {preview && (
        <p className="empty-public">
          Celebration records are managed separately from page drafts.
        </p>
      )}
    </section>
  );
}
