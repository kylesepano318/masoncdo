import type { PublicProps, Section } from "../../types";
import type { CSSProperties } from "react";
import HeroSection from "./HeroSection";
import LodgeFeatureSection from "./LodgeFeatureSection";
import RichTextSection from "./RichTextSection";
import HistoricalDocumentSection from "./HistoricalDocumentSection";
import ImageTextSection from "./ImageTextSection";
import VideoSection from "./VideoSection";
import OfficersSection from "./OfficersSection";
import AffiliationsSection from "./AffiliationsSection";
import GallerySection from "./GallerySection";
import TimelineSection from "./TimelineSection";
import CTASection from "./CTASection";
import CelebrationsSection from "./CelebrationsSection";
import MemberCard from "../public/MemberCard";
import { Heading } from "./Common";
function RenderSection({
  section,
  data,
}: {
  section: Section;
  data: PublicProps;
}) {
  switch (section.section_type) {
    case "hero":
      return <HeroSection section={section} />;
    case "lodge_feature":
      return <LodgeFeatureSection section={section} />;
    case "rich_text":
      return <RichTextSection section={section} />;
    case "image_text":
      return <ImageTextSection section={section} />;
    case "video":
      return <VideoSection section={section} />;
    case "historical_document":
    case "full_width_image":
      return <HistoricalDocumentSection section={section} />;
    case "officers":
      return <OfficersSection section={section} members={data.members} />;
    case "affiliations":
      return (
        <AffiliationsSection
          section={section}
          affiliations={data.affiliations}
        />
      );
    case "gallery":
      return <GallerySection section={section} />;
    case "timeline":
      return <TimelineSection section={section} />;
    case "cta":
      return <CTASection section={section} />;
    case "celebrations":
      return (
        <CelebrationsSection
          section={section}
          celebrations={data.celebrations}
          today={data.today}
          preview={data.preview}
          compact={data.page.slug === "home"}
        />
      );
    case "members_grid":
      return (
        <section className="section">
          <Heading section={section} />
          <div className="members-grid">
            {data.members
              .filter((m) => !m.position.is_officer)
              .map((m) => (
                <MemberCard key={m.id} member={m} />
              ))}
          </div>
          {!data.members.some((m) => !m.position.is_officer) && (
            <p className="empty-public">
              Our member directory will be shared here soon.
            </p>
          )}
        </section>
      );
    case "quote":
      return (
        <section className="section quote-section">
          <blockquote>{section.title}</blockquote>
          <p className="eyebrow">{section.subtitle}</p>
        </section>
      );
    case "spacer":
      return (
        <div
          style={{ height: section.settings?.height || 80 }}
          aria-hidden="true"
        />
      );
    default:
      return null;
  }
}

export default function SectionRenderer({
  section,
  data,
}: {
  section: Section;
  data: PublicProps;
}) {
  const settings = section.settings || {};
  const darkDefaults =
    section.section_type === "hero" || section.section_type === "cta";
  const style = {
    background: settings.background,
    color: settings.text_color,
    "--section-heading":
      settings.text_color ||
      (darkDefaults ? "var(--color-background)" : "var(--color-heading)"),
  } as CSSProperties;
  return (
    <div
      className={`section-shell ${settings.layout === "half" ? "archive-half" : ""} ${settings.layout === "documents" ? "archive-documents" : ""}`}
      style={style}
    >
      <RenderSection section={section} data={data} />
    </div>
  );
}
