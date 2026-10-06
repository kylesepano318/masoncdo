import { useApiForm, navigate } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Errors, Checkbox } from "../../components/ui/Form";
import type { AdminMember, Position } from "../../types";
export default function MemberEdit({
  member,
  positions,
}: {
  member: AdminMember | null;
  positions: Position[];
}) {
  const f = useApiForm({
    first_name: member?.first_name || "",
    middle_name: member?.middle_name || "",
    last_name: member?.last_name || "",
    suffix: member?.suffix || "",
    member_number: member?.member_number || "",
    membership_position_id:
      member?.membership_position_id ||
      positions.find((p) => p.slug === "member")?.id ||
      4,
    profile_photo: member?.profile_photo || "",
    photo: null as File | null,
    member_since: member?.member_since?.slice(0, 10) || "",
    biography: member?.biography || "",
    status: member?.status || "active",
    display_order: member?.display_order || 0,
    is_public: member?.is_public ?? true,
    replace_officer: false,
  });
  const text = (
    key:
      | "first_name"
      | "middle_name"
      | "last_name"
      | "suffix"
      | "member_number"
      | "member_since",
    label: string,
    type = "text",
  ) => (
    <Field label={label} error={f.errors[key]}>
      <input
        type={type}
        value={f.data[key]}
        onChange={(e) => f.setData(key, e.target.value)}
        required={["first_name", "last_name"].includes(key)}
      />
    </Field>
  );
  return (
    <AdminLayout title={member ? "Edit member" : "Add member"}>
      <form
        className="admin-panel editor-form"
        onSubmit={(e) => {
          e.preventDefault();
          if (member) {
            f.transform((data) => ({ ...data, _method: "put" }));
            f.post(`/admin/members/${member.id}`, {
              forceFormData: true,
              onSuccess: () => navigate("/admin/members"),
            });
          } else
            f.post("/admin/members", {
              forceFormData: true,
              onSuccess: () => navigate("/admin/members"),
            });
        }}
      >
        <Errors errors={f.errors} />
        <div className="form-grid">
          {text("first_name", "First name *")}
          {text("middle_name", "Middle name")}
          {text("last_name", "Last name *")}
          {text("suffix", "Suffix")}
          {text("member_number", "Member number")}
          {text("member_since", "Member since", "date")}
          <Field label="Membership position">
            <select
              value={f.data.membership_position_id}
              onChange={(e) =>
                f.setData("membership_position_id", Number(e.target.value))
              }
            >
              {positions.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Status">
            <select
              value={f.data.status}
              onChange={(e) => f.setData("status", e.target.value)}
            >
              {["active", "inactive", "past_officer"].map((s) => (
                <option key={s} value={s}>
                  {s.replaceAll("_", " ")}
                </option>
              ))}
            </select>
          </Field>
        </div>
        <Field label="Biography">
          <textarea
            rows={4}
            value={f.data.biography}
            onChange={(e) => f.setData("biography", e.target.value)}
          />
        </Field>
        <Field label="Profile photo (PNG, JPG, WEBP · up to 8 MB)">
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(e) => f.setData("photo", e.target.files?.[0] || null)}
          />
        </Field>
        {f.data.profile_photo && (
          <div className="member-photo-preview">
            <img src={f.data.profile_photo} alt="Current member portrait" />
            <button
              type="button"
              className="danger-link"
              onClick={() => f.setData("profile_photo", "")}
            >
              Remove photo
            </button>
          </div>
        )}
        <Checkbox
          label="Show this member on the public website"
          checked={f.data.is_public}
          onChange={(v) => f.setData("is_public", v)}
        />
        {f.errors.replace_officer && (
          <div className="warning-box">
            <p>{f.errors.replace_officer}</p>
            <Checkbox
              label="Confirm replacement and move the current officer to Past Officer"
              checked={f.data.replace_officer}
              onChange={(v) => f.setData("replace_officer", v)}
            />
          </div>
        )}
        <button className="admin-button" disabled={f.processing}>
          Save member
        </button>
      </form>
    </AdminLayout>
  );
}
