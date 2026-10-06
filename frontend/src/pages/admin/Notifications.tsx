import { useApiForm, apiActions } from "../../lib/ui";
import { Field, Errors, Checkbox } from "../../components/ui/Form";
export default function Notifications({
  settings,
}: {
  settings: Record<string, Record<string, unknown>>;
}) {
  const f = useApiForm({
    application_notification_email: String(
      settings.notifications?.application_notification_email || "",
    ),
    send_application_notification_email:
      settings.notifications?.send_application_notification_email !== false,
    send_applicant_confirmation_email: Boolean(
      settings.notifications?.send_applicant_confirmation_email,
    ),
  });
  return (
    <form
      className="admin-panel editor-form"
      onSubmit={(e) => {
        e.preventDefault();
        void f.put("/admin/settings/notifications");
      }}
    >
      <Errors errors={f.errors} />
      <Checkbox
        label="Send email for new applications"
        checked={f.data.send_application_notification_email}
        onChange={(v) => f.setData("send_application_notification_email", v)}
      />
      <Field
        label="Application notification email"
        error={f.errors.application_notification_email}
      >
        <input
          type="email"
          required
          value={f.data.application_notification_email}
          onChange={(e) =>
            f.setData("application_notification_email", e.target.value)
          }
        />
      </Field>
      <p className="help">
        New application alerts are sent to this lodge email address. An email
        failure never discards an application.
      </p>
      <Checkbox
        label="Send an acknowledgment email to the applicant"
        checked={f.data.send_applicant_confirmation_email}
        onChange={(v) => f.setData("send_applicant_confirmation_email", v)}
      />
      <div className="admin-actions">
        <button className="admin-button" disabled={f.processing}>
          {f.processing ? "Saving…" : "Save notifications"}
        </button>
        <button
          className="admin-button secondary"
          type="button"
          onClick={() =>
            apiActions.post("/admin/settings/notifications/test-email")
          }
        >
          Send test email
        </button>
      </div>
      <p className="help">
        Save the recipient before testing. SMTP credentials belong in the
        backend environment.
      </p>
    </form>
  );
}
