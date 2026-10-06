import type { PublicMember } from "../../types";
import { UserRound } from "lucide-react";
export default function MemberCard({
  member,
  officer = false,
}: {
  member: PublicMember;
  officer?: boolean;
}) {
  return (
    <article className={`member-card ${officer ? "officer" : ""}`}>
      {member.profile_photo ? (
        <img src={member.profile_photo} alt={member.name} loading="lazy" />
      ) : (
        <div className="portrait-placeholder">
          <UserRound size={48} strokeWidth={1} />
        </div>
      )}
      <h3>{member.name}</h3>
      <p className="eyebrow">{member.position.name}</p>
      {officer && member.biography && <p>{member.biography}</p>}
    </article>
  );
}
