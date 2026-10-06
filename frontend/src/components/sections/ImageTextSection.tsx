import type { Section } from "../../types";
import { Heading, Body, SectionButton } from "./Common";
import YouTubeEmbed from "./YouTubeEmbed";
export default function ImageTextSection({ section }: { section: Section }) {
  return (
    <section
      className={`section image-text ${section.settings?.reverse ? "reverse" : ""}`}
      style={{
        background: section.settings?.background,
        color: section.settings?.text_color,
      }}
    >
      <figure>
        <YouTubeEmbed
          url={section.settings?.video_url}
          title={section.settings?.video_title || "YouTube video"}
        />
        {section.image && (
          <img
            src={section.image}
            alt={section.settings?.alt || section.title || ""}
            loading="lazy"
          />
        )}
        {section.settings?.caption && (
          <figcaption>{section.settings.caption}</figcaption>
        )}
      </figure>
      <div>
        <Heading section={section} />
        <Body html={section.body} />
        <SectionButton section={section} />
      </div>
    </section>
  );
}
