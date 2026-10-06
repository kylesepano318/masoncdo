import { useApiForm } from "../../lib/ui";
import AdminLayout from "../../layouts/AdminLayout";
import { Field, Errors } from "../../components/ui/Form";
import MediaPicker from "../../components/admin/MediaPicker";
import type { MediaItem } from "../../types";
import Notifications from "./Notifications";
function Password() {
  const f = useApiForm({
    current_password: "",
    password: "",
    password_confirmation: "",
  });
  return (
    <form
      className="admin-panel editor-form"
      onSubmit={(e) => {
        e.preventDefault();
        f.put("/admin/password", { onSuccess: () => f.reset() });
      }}
    >
      <Errors errors={f.errors} />
      {(["current_password", "password", "password_confirmation"] as const).map(
        (key) => (
          <Field key={key} label={key.replaceAll("_", " ")}>
            <input
              type="password"
              autoComplete={
                key === "current_password" ? "current-password" : "new-password"
              }
              required
              value={f.data[key]}
              onChange={(e) => f.setData(key, e.target.value)}
            />
          </Field>
        ),
      )}
      <p className="help">
        Use at least 12 characters with mixed case, a number, and a symbol.
      </p>
      <button className="admin-button" disabled={f.processing}>
        Change password
      </button>
    </form>
  );
}
export default function Settings({
  group,
  settings,
  media,
}: {
  group: string;
  settings: Record<string, Record<string, string>>;
  media: MediaItem[];
}) {
  const f = useApiForm<Record<string, string>>(settings[group] || {});
  return (
    <AdminLayout
      title={
        group === "account"
          ? "Change password"
          : group.charAt(0).toUpperCase() + group.slice(1)
      }
    >
      {group === "notifications" ? (
        <Notifications settings={settings} />
      ) : group === "account" ? (
        <Password />
      ) : (
        <form
          className="admin-panel editor-form"
          onSubmit={(e) => {
            e.preventDefault();
            f.put(`/admin/settings/${group}`);
          }}
        >
          <Errors errors={f.errors} />
          {Object.entries(f.data).map(([key, value]) =>
            ["emblem", "federation_logo"].includes(key) ? (
              <div key={key}>
                <MediaPicker
                  label={
                    key === "emblem" ? "Lodge emblem" : "Federation emblem"
                  }
                  media={media}
                  value={value}
                  onChange={(v) => f.setData(key, v)}
                />
                <button
                  type="button"
                  className="admin-button secondary"
                  onClick={() =>
                    f.setData(
                      key,
                      key === "emblem"
                        ? "/images/golden-friendship-lodge-no-40.png"
                        : "",
                    )
                  }
                >
                  {key === "emblem"
                    ? "Restore supplied emblem"
                    : "Clear federation emblem"}
                </button>
              </div>
            ) : (
              <Field key={key} label={key.replaceAll("_", " ")}>
                {group === "theme" ? (
                  <div className="color-input">
                    <input
                      type="color"
                      value={value}
                      onChange={(e) => f.setData(key, e.target.value)}
                    />
                    <input
                      value={value}
                      onChange={(e) => f.setData(key, e.target.value)}
                    />
                  </div>
                ) : ["description", "address"].includes(key) ? (
                  <textarea
                    value={value}
                    onChange={(e) => f.setData(key, e.target.value)}
                  />
                ) : (
                  <input
                    type={key === "email" ? "email" : "text"}
                    value={value}
                    onChange={(e) => f.setData(key, e.target.value)}
                  />
                )}
              </Field>
            ),
          )}
          <button className="admin-button" disabled={f.processing}>
            Save settings
          </button>
        </form>
      )}
    </AdminLayout>
  );
}
