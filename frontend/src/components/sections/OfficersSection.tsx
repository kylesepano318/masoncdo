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
  const officers = members
    .filter((member) => member.position.is_officer)
    .sort((a, b) => a.position.rank - b.position.rank);
  const groups: { kind: string; members: PublicMember[] }[] = [];
  for (const member of officers) {
    const slug = member.position.slug;
    const kind =
      slug === "worshipful-master"
        ? "master"
        : ["senior-warden", "junior-warden"].includes(slug)
          ? "wardens"
          : "other";
    const last = groups[groups.length - 1];
    if (last?.kind === kind) last.members.push(member);
    else groups.push({ kind, members: [member] });
  }
  return (
    <section className="section officers-section">
      <Heading section={section} />
      {groups.map((group) => (
        <div
          key={group.members[0].id}
          className={
            group.kind === "master" && group.members.length === 1
              ? "master-row"
              : group.kind === "wardens"
                ? "wardens-row"
                : "members-grid"
          }
        >
          {group.members.map((member) => (
            <MemberCard key={member.id} member={member} officer />
          ))}
        </div>
      ))}
      {!officers.length && (
        <p className="empty-public">
          The current officers will be introduced here soon.
        </p>
      )}
      <SectionButton section={section} />
    </section>
  );
}
