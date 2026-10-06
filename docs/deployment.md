# Vercel, Render, Supabase and Cloudinary

Deploy the frontend and backend from this repository as independent applications. No frontend secrets, database access, or Supabase browser SDK are required.

**For free `vercel.app` and `onrender.com` URLs, follow [the free testing guide](free-testing.md).** It uses a Vercel reverse proxy for API and CSRF requests, with host-only cookies. The direct cross-origin configuration below is an alternative for shared custom domains.

## Supabase PostgreSQL

Create a Supabase project and open **Connect → Session pooler**. Copy the host, port, database, username and password into Render's `DB_*` environment variables. Use `DB_CONNECTION=pgsql`, port **5432**, and `DB_SSLMODE=require`. The pooler username normally includes the project reference; copy it exactly. The session pooler supports IPv4 and persistent backend connections. The direct database endpoint is also supported where IPv6 is available. Prefer session pooling over transaction pooling for this Laravel deployment. See [Supabase connection documentation](https://supabase.com/docs/guides/database/connecting-to-postgres).

Use a dedicated database/project for the lodge. Never run the test suite, `migrate:fresh`, or destructive migration commands against production. Back up existing records before an architecture migration.

## Cloudinary

Create a Cloudinary account and copy the cloud name, API key and API secret into Render's `CLOUDINARY_*` variables. The backend signs uploads; the browser receives only public image URLs and metadata. No unsigned upload preset is needed. Uploads accept JPEG, PNG and WEBP up to 8 MB and require alternative text. The service uses [Cloudinary's signed upload and destroy APIs](https://cloudinary.com/documentation/image_upload_api_reference).

Bundled photographs and the supplied emblem live in `frontend/public/images`. These are static versioned assets. All new administrator uploads go to Cloudinary. Existing records referring to `/storage/...` from the previous architecture require migration: upload those images through the media library and replace their references before retiring the old filesystem. Existing CMS records and bundled-image references are preserved.

## Render backend

Create a Blueprint from `render.yaml`, or create a Docker web service using the repository root, `Dockerfile`, and health path `/api/health`. The image builds PHP/Laravel only. It installs `pdo_pgsql`, GD, ZIP, mbstring, intl and related extensions.

Set the secrets requested by the Blueprint. Generate a stable key locally with `php artisan key:generate --show`; copy it into Render `APP_KEY` and retain it across deploys. Keep `APP_DEBUG=false`. Configure the Supabase, Cloudinary and SMTP values described here. Provide `LODGE_ADMIN_EMAIL` and a strong `LODGE_ADMIN_PASSWORD` for the first deployment. The bootstrap command only creates an admin if none exists; remove the bootstrap password from Render after creation. Change the password later through the admin account page.

Startup runs `migrate --force`, idempotent `db:seed --force`, and configuration, route and view caching. Seeders preserve existing page content and settings. Uploaded media never needs a persistent Render disk. Sessions and cache use PostgreSQL. Notifications send synchronously with an 8-second SMTP timeout; no paid queue worker is required.

## Vercel frontend

Import the same repository, select **Vite**, and set **Root Directory: frontend**, **Build Command: npm run build**, **Output Directory: dist**. Set `VITE_API_BASE_URL` to the backend origin, without a trailing `/api`, for example `https://api.goldenfriendshiplodge40.org`. Vite embeds this value at build time; redeploy after changing it. `frontend/vercel.json` preserves static assets and directs application routes to `index.html`, so `/admin/login`, `/history` and other deep links work. See [Vercel Vite deployment](https://vercel.com/docs/frameworks/frontend/vite).

## Cookie authentication across hosts

Sanctum's SPA cookie authentication requires the frontend and API to share the same parent domain. Use the frontend domain on Vercel and `api` subdomain on Render:

```dotenv
APP_URL=https://api.goldenfriendshiplodge40.org
FRONTEND_URL=https://goldenfriendshiplodge40.org
FRONTEND_ALLOWED_ORIGINS=https://goldenfriendshiplodge40.org,https://www.goldenfriendshiplodge40.org
SANCTUM_STATEFUL_DOMAINS=goldenfriendshiplodge40.org,www.goldenfriendshiplodge40.org
SESSION_DOMAIN=.goldenfriendshiplodge40.org
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

Add both custom domains in the hosting dashboards, follow their DNS instructions, and wait for HTTPS certificates before logging in. Direct browser requests between unrelated default `*.vercel.app` and `*.onrender.com` hosts are unsuitable for this cookie-based admin flow. The [same-origin Vercel proxy configuration](free-testing.md) supports these free URLs without a domain purchase. Vercel preview origins require deliberate configuration; CORS does not use a wildcard.

Axios uses credentials and obtains `/sanctum/csrf-cookie` before mutations. The session cookie is HTTP-only; the separate XSRF cookie is readable so Axios can send the CSRF header. No authentication tokens are stored in localStorage. `SANCTUM_STATEFUL_DOMAINS` contains hosts with ports, without schemes; CORS origins contain full schemes. See [Laravel Sanctum SPA authentication](https://laravel.com/framework/docs/12.x/sanctum).

## Provider-independent email

Configure these backend environment variables using any SMTP-compatible provider:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=2525
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=website@your-verified-domain
MAIL_FROM_NAME="Golden Friendship Lodge"
MAIL_TIMEOUT=8
APPLICATION_NOTIFICATION_EMAIL=lodge@your-domain
```

Use `MAIL_ENCRYPTION=tls` for required STARTTLS, or `ssl` for implicit TLS; follow the provider's port instructions. Verify the sender/domain with the provider. SMTP credentials remain server-side. The administrator can override the recipient under **Notifications**, optionally enable applicant acknowledgments, and send a test email. Save the recipient before testing. Resend failed lodge alerts from an application's detail page.

**Render free services block outbound SMTP ports 25, 465 and 587.** A provider offering an alternate port such as 2525 is needed for SMTP on the free plan; otherwise use a paid service. A provider that only exposes a blocked port will not deliver email here. See [Render free service limitations](https://render.com/docs/free). This is a hosting restriction, not a provider hardcoded into the app.

The application is committed before any mail attempt. Submission still returns HTTP 201 if SMTP is unavailable. Delivery failure timestamps and a sanitized error appear in the private application record. Log entries contain the application reference and exception class, never SMTP credentials. Resending alerts does not create another application. Applicant acknowledgments default to disabled. For local testing use `MAIL_MAILER=array` to avoid external delivery.

## Verification and troubleshooting

1. Open `/api/health` on the backend; expect `{"status":"ok"}`. This endpoint does not contact Supabase, Cloudinary or SMTP.
2. Open public pages on Vercel and verify supplied photographs.
3. Log in to the administrator using the shared custom domains. A 419 usually indicates a stale CSRF cookie or incorrect stateful domain/session domain; a CORS failure indicates an origin mismatch. Clear cookies after changing domain configuration.
4. Submit a test application. Check its reference, unread badge, email timestamp and private detail page. Opening the record clears its unread state. Review it, add notes, and convert once to a private member.
5. Upload an image and verify its Cloudinary URL. An existing portrait remains intact if upload fails. A referenced image cannot be deleted until all draft and published references are removed.
6. Create a current-year public birthday, a private birthday and a next-year celebration. Only the current-year public item appears in upcoming celebrations; next-year entries become eligible when their year starts.

Free Render instances may sleep and cause an initial delay. The frontend shows a loading state and allows retries after connection errors. Live cloud deployments and actual SMTP delivery require your hosting accounts and credentials; they are not performed by local verification.
