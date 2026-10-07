# Production: Render + Cloudflare R2 + Mailtrap

The root Dockerfile builds React and serves it with Laravel from one Render web service. A separate Render background worker sends application email. PostgreSQL runs on Render in the same Singapore region. Images are stored in R2 and delivered from its public media domain. This is a fresh production installation with a new Render database and an empty R2 bucket. No data or uploaded files are imported from the testing deployment.

This repository is prepared locally; no hosting account, billing plan, production database, DNS record, or external file has been changed automatically.

## 1. Prepare your domain and accounts

1. Have access to Render, Cloudflare, Mailtrap, and the GitHub repository.
2. Buy a domain for the lodge. You can initially use the Render `onrender.com` website address, but Mailtrap production sending needs a verified domain you control.
3. Add the domain to Cloudflare and update its nameservers at your registrar. Wait until Cloudflare marks it active.
4. Choose one canonical website address, such as `https://www.yourlodgedomain.com`.
5. Use `media.yourlodgedomain.com` for public images and `applications@yourlodgedomain.com` as your email sender. These are examples: replace them with your actual domain.

## 2. Create R2 image storage

1. In Cloudflare, open **R2 Object Storage** and activate R2 billing if prompted. R2 has a free usage allowance but activation can require payment details.
2. Create a **Standard** bucket named `golden-friendship-media`. Choose an Asia-Pacific location hint if available.
3. Open the bucket's **Settings → Custom Domains**, connect `media.yourlodgedomain.com`, and wait for the domain to become active. Configure a Cloudflare cache rule for this public image domain if caching is not already enabled. Do not cache the application's `/api` responses.
4. For temporary testing only, you may enable the bucket's public development `r2.dev` URL. It is rate limited and intended for development, not production delivery.
5. Create an **R2 API token** with **Object Read & Write**, restricted to this bucket. Copy the **Access Key ID**, **Secret Access Key**, and **S3 API endpoint**. The access key is not the token's label or account ID.
6. Set the Render values listed below. The S3 endpoint normally has the form `https://ACCOUNT_ID.r2.cloudflarestorage.com`; copy the exact endpoint Cloudflare provides. The public URL is your image domain, not that API endpoint.

| Render variable | Value |
| --- | --- |
| `MEDIA_DISK` | `r2` |
| `R2_ACCESS_KEY_ID` | Access Key ID from the R2 token |
| `R2_SECRET_ACCESS_KEY` | Secret Access Key from the R2 token |
| `R2_BUCKET` | `golden-friendship-media` |
| `R2_ENDPOINT` | S3 API endpoint supplied by Cloudflare |
| `R2_PUBLIC_URL` | `https://media.yourlodgedomain.com` (no trailing slash) |

Keep keys on the server. The current implementation uploads through Laravel with streamed R2 writes, unique object names, content types, and long-lived cache headers; the frontend never receives R2 credentials. Browser-to-R2 upload CORS rules are not needed for this implementation. Images are delivered directly from R2, not through Laravel.

This bucket is **public**. It is for lodge website photographs, not confidential application documents. The media library now accepts JPG/JPEG, PNG, WebP, and PDF up to 8 MB. PDF records display an open-document link and a selectable public URL. For an announcement, upload the PDF in **Admin ? Media**, copy its URL, and add a link in the relevant CMS rich-text section using the editor's link button, then publish the page. PDFs are excluded from image pickers so they cannot accidentally become greeting photos or portraits. This adds document attachments through CMS links; there is no separate announcement registry or automatic PDF thumbnail generator. R2 does not automatically resize images: prepare sensible image dimensions and file sizes before uploading. Automatic thumbnails/direct browser uploads can be added later.

Official guides: [R2 credentials](https://developers.cloudflare.com/r2/api/s3/), [public buckets and custom domains](https://developers.cloudflare.com/r2/buckets/public-buckets/), [R2 costs](https://developers.cloudflare.com/r2/pricing/).

## 3. Configure Mailtrap production email

1. Open **Email API/SMTP → Sending Domains** in Mailtrap, not Email Sandbox.
2. Add your owned domain and copy its verification DNS records into Cloudflare. Use the exact records Mailtrap provides and wait for verification. Keep mail verification records DNS-only where applicable. Configure DMARC as advised by Mailtrap; do not create duplicate SPF records at the same hostname.
3. Create an API token with sending permission for this domain.
4. Set `MAIL_MAILER=mailtrap_api` and `MAILTRAP_API_TOKEN` to that token. This is the HTTPS production sending API; neither Gmail OAuth nor SMTP passwords are needed.
5. Set `MAIL_FROM_ADDRESS=applications@yourlodgedomain.com`. Do not put a Gmail address or `website@example.test` here when sending from your verified lodge domain.
6. Set `APPLICATION_NOTIFICATION_EMAIL` to the actual admin recipient. This recipient can be a Gmail address.

| Render variable | Value |
| --- | --- |
| `MAIL_MAILER` | `mailtrap_api` |
| `MAILTRAP_API_TOKEN` | Domain-authorized production API token |
| `MAIL_FROM_ADDRESS` | `applications@yourlodgedomain.com` |
| `MAIL_FROM_NAME` | `Golden Friendship Lodge` |
| `MAIL_TIMEOUT` | `15` |
| `APPLICATION_NOTIFICATION_EMAIL` | Actual admin inbox |

A sending service does not automatically create a mailbox for `applications@...`; arrange a mailbox/forwarding separately if people need to reply to that address.

In **Admin → Notifications**, save the recipient and enable both **Send application notification email** and **Send an acknowledgment email to the applicant**. The saved recipient overrides `APPLICATION_NOTIFICATION_EMAIL`, so confirm it explicitly during initial setup. Click **Send test email**; this test sends immediately and reports configuration errors. Real applications enqueue two separate jobs. The worker retries failures up to five attempts with increasing delays; successful delivery timestamps prevent an already completed job from sending again. Network timeouts can be ambiguous, so exactly-once delivery cannot be guaranteed by the provider integration.

Official guides: [domain verification](https://docs.mailtrap.io/email-api-smtp/setup/sending-domain), [sending API](https://docs.mailtrap.io/developers/email-sending).

## 4. Start with a fresh production database

Use the Blueprint's new Render PostgreSQL database, `lodge-db`, and a new empty R2 bucket. Do not import testing database dumps or copy old uploaded files.

On first startup, Laravel migrations create the tables and seeds create the initial lodge website, branding, membership positions, and notification settings. Members, applications, celebrations, and the uploaded media library start empty. The website template and static photographs committed in `frontend/public` are bundled with the application; they do not require a storage transfer.

Create a new production administrator using `LODGE_ADMIN_EMAIL` and `LODGE_ADMIN_PASSWORD` as described below. Generate a new production `APP_KEY` once and use the same value for the web service and worker. Keep that key stable on future deployments. Add real members, celebrations, and media through the production admin after setup.

## 5. Set the shared Render environment group

Create an environment group named **`lodge-production`** manually in Render **before** creating the Blueprint. Add the R2 and Mailtrap values above, plus these values. The Blueprint supplies nonsecret defaults and links both services to this group. Render does not support secret prompts (`sync: false`) inside environment groups, so enter these values manually in its dashboard.

| Variable | Value |
| --- | --- |
| `APP_KEY` | Generate a new production key once with `php artisan key:generate --show` in `backend`; save it privately and keep it stable |
| `APP_URL` | Canonical website URL, e.g. `https://www.yourlodgedomain.com` |
| `FRONTEND_URL` | Exactly the same URL as `APP_URL`, no trailing slash |
| `FRONTEND_ALLOWED_ORIGINS` | Exactly the same URL, no trailing slash |
| `SANCTUM_STATEFUL_DOMAINS` | Hostname only: `www.yourlodgedomain.com` (no `https://`, path, or slash) |

You can initially use `https://YOUR-SERVICE.onrender.com` for all three URL values and `YOUR-SERVICE.onrender.com` for Sanctum. Use the actual hostname Render assigns, then update values and redeploy when adding your own domain.

The Blueprint sets production/debug/session/cache values, `MEDIA_DISK=r2`, `MAIL_MAILER=mailtrap_api`, `QUEUE_CONNECTION=database`, and host-only secure session cookies. The database URL is injected from Render's internal database connection, separately into web and worker. Do not add a testing database URL to the shared group; use the database connection supplied by the Blueprint.

For the fresh production services, use only the values in this guide and the Blueprint. Cloudinary credentials are not needed. The Docker build uses relative `/api` and `/sanctum` requests, so no `VITE_API_BASE_URL` override is needed. Keep `DB_CONNECTION=pgsql` and `DB_SSLMODE=prefer` for Render's internal database connection.

## 6. Deploy the repository on Render

1. Commit and push the reviewed changes to your GitHub repository, including `backend/composer.lock`, `Dockerfile`, `render.yaml`, and frontend files. Do not commit `.env` files or credentials.
2. Open **Render → New → Blueprint**, select the repository/branch, and review `render.yaml` before creating resources.
3. It defines three paid resources in Singapore:
   - `golden-friendship-lodge`: Docker web service, Starter.
   - `golden-friendship-lodge-mail`: Docker background worker, Starter.
   - `lodge-db`: Basic 256 MB PostgreSQL with 1 GB storage.
4. Review paid charges in Render before creation. The web + worker + entry database starts around $20/month plus database storage and usage; domain and R2 usage are separate. You do not need a Redis instance: jobs are stored in PostgreSQL.
5. Enter `LODGE_ADMIN_EMAIL` and a strong `LODGE_ADMIN_PASSWORD` for the web service's initial administrator setup.
6. Confirm the web and worker link to `lodge-production`, share the same `APP_KEY`, and point to the same database.
7. Wait for the web service's migrations, seeds, and startup to finish. If the worker starts before a fresh database is initialized, restart it after the web service is healthy.
8. Read the actual website hostname Render assigned. If your custom domain is not connected yet, set the three URL variables to `https://ACTUAL-HOST.onrender.com` and Sanctum to `ACTUAL-HOST.onrender.com`, then redeploy web and worker before testing login. Visit that address and `/api/health`. The website now comes from Render, not Vercel. Opening `/admin/login` directly or refreshing a deep link should work.
9. Remove `LODGE_ADMIN_PASSWORD` and `LODGE_ADMIN_EMAIL` from the web service after confirming administrator access. Later email/password changes are available in **Admin ? Login credentials**; enter the current password to save and leave the new password blank for an email-only change.

The worker uses `SERVICE_ROLE=worker` and the same Docker image. It runs `queue:work database` rather than Apache or database migrations. Keep it running whenever `QUEUE_CONNECTION=database`; otherwise real application email remains queued. The web service alone applies migrations on startup. Deploy both services when code or shared environment values change.

To use existing Render services instead of the Blueprint, the web service must use repository root as its Docker context and `./Dockerfile`. Create/link the new same-region database and a Docker background worker, copy the Blueprint's environment values, and set worker `SERVICE_ROLE=worker`. The existing backend-only root directory must be removed because the Docker build now needs both folders.

## 7. Connect the website domain

1. In the Render web service, add your canonical hostname under **Custom Domains**.
2. Add the DNS records Render requests in Cloudflare. Start with **DNS only** for website records while Render verifies the hostname and issues HTTPS. The R2 media domain has its own separate setup.
3. Update `APP_URL`, `FRONTEND_URL`, `FRONTEND_ALLOWED_ORIGINS`, and `SANCTUM_STATEFUL_DOMAINS` in the shared group to the canonical hostname, then redeploy web and worker.
4. Redirect alternate website hostnames to the canonical one so login cookies stay consistent.

## 8. Verify the fresh production installation

- Home, members, history, celebrations, application, and direct admin links load on Render with images intact.
- Login, logout, refresh, and authenticated writes work without CORS or 419 errors.
- Upload an image and a PDF; each new URL uses your R2 media domain and works in a private browser window. Link the PDF in a CMS section and confirm it opens after publishing. Verify in the R2 dashboard that the object exists. Existing referenced images cannot be deleted; unreferenced images are removed from R2 and the library.
- Set Admin → Notifications to the real recipient, enable both email options, and send a test email.
- Submit a real test application once. Confirm its reference appears, then both recipients receive matching references. Check Mailtrap **Email API/SMTP → Email Logs**, not Sandbox.
- Confirm the queue drains and worker logs have no errors. Inspect `php artisan queue:failed` in Render's web Shell. After fixing configuration, retry an individual failed job with `php artisan queue:retry JOB_UUID`. Do not bulk retry completed messages unnecessarily.
- Confirm members, applications, celebrations, and the uploaded media library are empty before adding production content or running the test submission. Review the seeded website content and inspect API `Server-Timing` headers for remaining database latency.
- Keep independent backups of the database and uploaded originals. Database backups contain file references, not the actual R2 photographs.

After verification, add the real lodge content through the production admin. Existing testing services and their records are left untouched; this guide does not reset or delete them. Future deployments run ordinary migrations and preserve production records; do not use `migrate:fresh` or reset commands on production.

## Local verification

The backend suite passed 58 tests / 415 assertions, with simulated R2 and Mailtrap responses. TypeScript checks, production frontend build, PHP formatting, Composer validation, Blueprint YAML parsing, and route/view caching passed. Nine browser checks passed for both the development proxy and the bundled React app served directly by Laravel, including desktop/mobile pages. Two device-inapplicable cases were intentionally skipped. Use `scripts/verify-admin.ps1 -BundledFrontend -Public` for the bundled browser run; it resets only the disposable `mason_browser` database. Docker Desktop's Linux engine was unavailable locally, so the actual container build and live R2/Mailtrap credentials must still be verified on Render.
