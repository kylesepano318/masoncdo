import { apiActions, useApiForm, Link } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Errors } from "../../components/ui/Form";
import type { ApplicationRecord } from "../../types";
export default function ApplicationDetail({
  application: a,
}: {
  application: ApplicationRecord;
}) {
  const f = useApiForm({
    status: a.status,
    admin_notes: a.admin_notes || "",
  });
  return (
    <AdminLayout title={a.reference_number}>
      <div className="dashboard-columns">
        <section className="admin-panel">
          <h2>
            {a.first_name} {a.last_name}
          </h2>
          <p className="help">
            Private applicant information · administrator access only
          </p>
          <dl className="application-details">
            {Object.entries(a)
              .filter(
                ([k]) =>
                  ![
                    "id",
                    "admin_notes",
                    "converted_member_id",
                    "updated_at",
                  ].includes(k),
              )
              .map(([k, v]) => (
                <div key={k}>
                  <dt>{k.replaceAll("_", " ")}</dt>
                  <dd>{v === null ? "—" : String(v)}</dd>
                </div>
              ))}
          </dl>
        </section>
        <form
          className="admin-panel"
          onSubmit={(e) => {
            e.preventDefault();
            f.patch(`/admin/applications/${a.id}/status`);
          }}
        >
          <h2>Review application</h2>
          <Errors errors={f.errors} />
          <Field label="Status">
            <select
              value={f.data.status}
              onChange={(e) => f.setData("status", e.target.value)}
            >
              {["pending", "under_review", "approved", "rejected"].map((s) => (
                <option key={s} value={s}>
                  {s.replaceAll("_", " ")}
                </option>
              ))}
            </select>
          </Field>
          <Field label="Internal notes">
            <textarea
              rows={7}
              value={f.data.admin_notes}
              onChange={(e) => f.setData("admin_notes", e.target.value)}
            />
          </Field>
          <button className="admin-button" disabled={f.processing}>
            {f.processing ? "Saving…" : "Save review"}
          </button>
          <hr />
          <p className="help">
            Notification email:{" "}
            {a.notification_email_sent_at
              ? `sent ${new Date(String(a.notification_email_sent_at)).toLocaleString()}`
              : "not sent"}
          </p>
          {a.notification_email_error && (
            <p className="warning-box">{String(a.notification_email_error)}</p>
          )}
          <button
            type="button"
            className="admin-button secondary"
            onClick={() =>
              apiActions.post(`/admin/applications/${a.id}/resend-notification`)
            }
          >
            Resend email notification
          </button>
          <hr />
          {a.converted_at ? (
            <>
              <p className="notice">
                Already converted to a private member record.
              </p>
              {a.converted_member_id && (
                <Link href={`/admin/members/${a.converted_member_id}/edit`}>
                  Edit member →
                </Link>
              )}
            </>
          ) : (
            <>
              <p className="help">
                Creates an active member with the Member position. The record
                stays private until you choose to publish it.
              </p>
              <button
                type="button"
                className="admin-button"
                onClick={() => {
                  if (confirm("Approve this application and create a member?"))
                    apiActions.post(
                      `/admin/applications/${a.id}/convert-to-member`,
                    );
                }}
              >
                Approve & create member
              </button>
            </>
          )}
        </form>
      </div>
    </AdminLayout>
  );
}
