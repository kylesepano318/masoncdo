import { Head, useApiForm, Link, useSiteContext, navigate } from "../../lib/ui";
import { Field, Checkbox, Errors } from "../../components/ui/Form";
import type { Shared } from "../../types";
export default function Login() {
  const { site } = useSiteContext<Shared>().props;
  const f = useApiForm({ email: "", password: "", remember: false });
  return (
    <main className="login-page">
      <Head title="Administrator login" />
      <div className="login-box">
        <img src={site.branding?.emblem} alt="Lodge emblem" />
        <p className="eyebrow">GOLDEN FRIENDSHIP · NO. 40</p>
        <h1>Administrator login</h1>
        <p>Welcome to the lodge administration.</p>
        <form
          onSubmit={(e) => {
            e.preventDefault();
            f.post("/admin/login", {
              onSuccess: () => navigate("/admin/dashboard"),
              onFinish: () => f.reset("password"),
            });
          }}
        >
          <Errors errors={f.errors} />
          <Field label="Email address">
            <input
              type="email"
              autoComplete="username"
              required
              value={f.data.email}
              onChange={(e) => f.setData("email", e.target.value)}
            />
          </Field>
          <Field label="Password">
            <input
              type="password"
              autoComplete="current-password"
              required
              value={f.data.password}
              onChange={(e) => f.setData("password", e.target.value)}
            />
          </Field>
          <Checkbox
            label="Remember me"
            checked={f.data.remember}
            onChange={(v) => f.setData("remember", v)}
          />
          <button className="admin-button" disabled={f.processing}>
            {f.processing ? "Signing in…" : "Login"}
          </button>
        </form>
        <Link href="/">← Return to the lodge website</Link>
      </div>
    </main>
  );
}
