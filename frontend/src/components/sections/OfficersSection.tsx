import type { Section, PublicMember } from "../../types";
import MemberCard from "../public/MemberCard";
import { Heading, SectionButton } from "./Common";
export default function OfficersSection({
  section,
  members,
}: {
  section: Section;
  members: PublicMember[];
}) {
  const master = members.find((m) => m.position.slug === "worshipful-master");
  const wardens = members.filter((m) =>
    ["senior-warden", "junior-warden"].includes(m.position.slug),
  );
  const otherOfficers = members.filter(
    (member) =>
      member.position.is_officer &&
      !["worshipful-master", "senior-warden", "junior-warden"].includes(
        member.position.slug,
      ),
  );
  return (
    <section className="section officers-section">
      <Heading section={section} />
      {master && (
        <div className="master-row">
          <MemberCard member={master} officer />
        </div>
      )}
      {wardens.length > 0 && (
        <div className="wardens-row">
          {wardens.map((m) => (
            <MemberCard key={m.id} member={m} officer />
          ))}
        </div>
      )}
      {otherOfficers.length > 0 && (
        <div className="members-grid">
          {otherOfficers.map((member) => (
            <MemberCard key={member.id} member={member} officer />
          ))}
        </div>
      )}
      {!master && !wardens.length && !otherOfficers.length && (
        <p className="empty-public">
          The current officers will be introduced here soon.
        </p>
      )}
      <SectionButton section={section} />
    </section>
  );
}
