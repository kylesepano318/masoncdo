import type { Section } from "../../types";
import { Heading, Body } from "./Common";
import YouTubeEmbed from "./YouTubeEmbed";

export default function VideoSection({ section }: { section: Section }) {
  return (
    <section className="section video-section">
      <Heading section={section} />
      <YouTubeEmbed
        url={section.settings?.video_url}
        title={
          section.settings?.video_title || section.title || "YouTube video"
        }
      />
      <Body html={section.body} />
    </section>
  );
}
