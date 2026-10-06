# Testing with free Vercel and Render URLs

No purchased domain is required with a Vercel reverse proxy. The browser uses only the Vercel origin for `/api/*` and `/sanctum/*`; Vercel forwards these requests to Render. Secure session cookies have no Domain attribute and belong to the Vercel host. Login stays cookie-based, with CSRF protection and no localStorage tokens.

Example URLs below are placeholders. Use the stable Vercel production project URL for testing, not a changing preview URL.

## 1. Accounts and repository

Create accounts on GitHub, Vercel, Render, Supabase, Cloudinary, and Mailtrap. Publish the full repository with `frontend`, `backend`, `deployment`, and root `Dockerfile`. Do not upload `.env` files or credentials. Hosting accounts and cloud resources have not been created by this setup.

## 2. Supabase and Cloudinary

Create a fresh Supabase testing project. Under Connect, select **Session pooler** and copy the host, port 5432, database, username (including project reference), and database password exactly. Use SSL mode `require`. No Supabase Auth, browser keys, or Storage setup is needed.

Create a Cloudinary account and get its cloud name, API key, and API secret. New admin uploads already use signed backend requests to Cloudinary, with metadata in Supabase. Bundled images under `frontend/public` deploy with Vercel. No Render persistent disk or unsigned preset is required.

## 3. Reserve the Vercel project URL

Import the repository in Vercel. Framework: Vite; root: `frontend`; build: `npm run build`; output: `dist`. Set `VITE_API_BASE_URL=/` for the Production environment. Record its stable project URL, for example `https://mason-test.vercel.app`. The first deployment will not have a working API until the proxy is configured in step 5.

## 4. Deploy Render

Create a Docker Web Service from the same repository. Leave the Root Directory blank; Dockerfile path: `./Dockerfile`; health path: `/api/health`; choose Free for testing. Do not create a Render database.

Generate a new deployment key locally with `cd backend` then `php artisan key:generate --show`. Copy the entire output into Render APP_KEY and retain this key across redeployments. Enter the following environment variables, replacing every placeholder. Dashboard fields take raw values without quotation marks:

```dotenv
APP_NAME=Golden Friendship Lodge
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY
APP_URL=https://YOUR-SERVICE.onrender.com
APP_TIMEZONE=Asia/Manila
FRONTEND_URL=https://YOUR-PROJECT.vercel.app
FRONTEND_ALLOWED_ORIGINS=https://YOUR-PROJECT.vercel.app
SANCTUM_STATEFUL_DOMAINS=YOUR-PROJECT.vercel.app
DB_CONNECTION=pgsql
DB_HOST=YOUR_SUPABASE_SESSION_POOLER_HOST
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=YOUR_SUPABASE_POOLER_USERNAME
DB_PASSWORD=YOUR_DATABASE_PASSWORD
DB_SSLMODE=require
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
LOG_LEVEL=warning
CLOUDINARY_CLOUD_NAME=YOUR_CLOUD_NAME
CLOUDINARY_API_KEY=YOUR_API_KEY
CLOUDINARY_API_SECRET=YOUR_API_SECRET
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=YOUR_SANDBOX_USERNAME
MAIL_PASSWORD=YOUR_SANDBOX_PASSWORD
MAIL_ENCRYPTION=tls
MAIL_TIMEOUT=8
MAIL_FROM_ADDRESS=website@example.test
MAIL_FROM_NAME=Golden Friendship Lodge
APPLICATION_NOTIFICATION_EMAIL=lodge@example.test
LODGE_ADMIN_EMAIL=YOUR_ADMIN_EMAIL
LODGE_ADMIN_PASSWORD=YOUR_STRONG_PASSWORD
```

Use the actual database name from Supabase if it differs from `postgres`. `SESSION_DOMAIN=null` means the literal string `null`, which Laravel interprets as no cookie domain. Do not set a cookie domain to `.vercel.app` or your Render hostname. The stateful domain has no scheme or slash; the frontend origin values include `https://` and no trailing slash. APP_URL is the real Render URL.

The password must have at least 12 characters, mixed case, a number, and a symbol. Startup automatically migrates, seeds the template, and creates the admin only if none exists. Existing local records and accounts do not transfer automatically. Avoid running tests or migrate:fresh against this database.

## 5. Configure the Vercel proxy

Once Render has assigned your actual service URL, run locally:

```powershell
cd C:\laragon\www\mason\frontend
npm run configure:vercel -- https://YOUR-SERVICE.onrender.com
```

The command updates `frontend/vercel.json` with `/api/:path*` and `/sanctum/:path*` rewrites **before** the SPA fallback. Commit and push this file. Redeploy Vercel with `VITE_API_BASE_URL=/`. Re-run the command if the backend hostname changes. This configuration is committed before deployment; it is not generated during the build.

Open both `https://YOUR-SERVICE.onrender.com/api/health` and `https://YOUR-PROJECT.vercel.app/api/health`. Both should return `{"status":"ok"}`. A frontend HTML response at the second URL means the proxy rules are missing or the old deployment is still active. The health endpoint confirms reachability, not all database/upload/email connections.

## 6. Login and verification

Use `https://YOUR-PROJECT.vercel.app/admin/login` and the bootstrap credentials. Refresh the dashboard to check the session persists. After the admin is created, remove LODGE_ADMIN_PASSWORD from Render. Test uploads, CMS preview/publish, a public application submission and its admin record, birthday privacy/current-year filtering, and logout. Redeploy Render and confirm records and Cloudinary images survive.

For email, create a **Mailtrap Email Sandbox** inbox and copy its SMTP integration credentials using port 2525. Save the notification recipient under Admin → Notifications, then send a test email. Check Mailtrap's dashboard. Sandbox email is captured there and does not reach a real inbox. For real delivery, use a sending provider supporting port 2525 and its verified sender/domain. Render Free blocks SMTP ports 25, 465, and 587. No domain purchase is needed for sandbox testing.

Render Free sleeps after 15 minutes of inactivity. If the proxy times out during a cold start, open the Render health URL, wait for it to wake, and retry. Test actual hosted uploads for hosting request limits, especially near the app's 8 MB maximum.

## Troubleshooting

- 419: check SESSION_DOMAIN=null, the exact stateful hostname, proxying of `/sanctum/*`, and secure cookies. Clear old browser cookies and retry after redeployment.
- Browser requests go directly to Render: set VITE_API_BASE_URL=/ and rebuild Vercel.
- API returns HTML: confirm both proxy rules precede the SPA fallback.
- 401 after login: verify SESSION_DRIVER=database and the Supabase session table/migrations; confirm cookies are stored under the Vercel host.
- Preview deployment fails to log in: use the stable production project hostname configured in Render. Do not allow all preview origins with a wildcard.
- SMTP error: use the correct sandbox credentials and port 2525. Applications remain saved even when email fails.

Local verification of the same-origin cookie flow uses the disposable `mason_browser` database:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify-admin.ps1 -SameOrigin
```

References: [Vercel external rewrites](https://vercel.com/docs/routing/rewrites), [Laravel Sanctum](https://laravel.com/framework/docs/12.x/sanctum), [Supabase connections](https://supabase.com/docs/guides/database/connecting-to-postgres), [Render Free](https://render.com/docs/free), [Mailtrap sandbox SMTP](https://docs.mailtrap.io/email-sandbox/setup/sandbox-smtp-integration).
