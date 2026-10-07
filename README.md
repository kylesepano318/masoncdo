# Golden Friendship Masonic Lodge No. 40

An independent React/TypeScript frontend and Laravel REST API for the lodge in Cagayan de Oro City. The ceremonial website, supplied emblem, photographs, members, editable CMS and celebrations are preserved.

| Application | Stack | Deployment |
| --- | --- | --- |
| `frontend/` | React 19, React Router, Axios, Vite, Tailwind 4 | Built locally; uploaded to Hostinger public_html |
| `backend/` | Laravel 12, PHP 8.3+, Sanctum cookie authentication | Hostinger PHP web hosting |
| Database | MySQL through Laravel PDO | Hostinger MySQL |
| Uploaded images and PDFs | Streamed server-side uploads | Hostinger public storage |
| Email | Laravel SMTP and database queue | Hostinger mailbox + cron |

Gmail API is also supported for Render Free: see [Gmail OAuth setup](docs/gmail-api.md). It sends both lodge notifications and applicant reference acknowledgments using HTTPS and backend-only refresh tokens.

Automatic updates are prepared in [the GitHub Actions setup guide](docs/github-hostinger.md). Complete the initial installation and configure SSH/secrets before enabling deployment from `main`.

## Local installation

For fresh production, follow [the Hostinger deployment guide](docs/deployment.md). It covers the Unlimited plan, MySQL, uploads, mailbox setup, two administrator seeds and cron. Build the upload archive with `powershell -ExecutionPolicy Bypass -File scripts/build-hostinger.ps1`. Existing local PostgreSQL credentials and test data are preserved; PostgreSQL remains supported as an alternative.

For a fresh local checkout, create a MySQL database and user, then:

```powershell
cd backend
composer install
Copy-Item .env.example .env
# Edit DB_* in .env; local MySQL usually uses port 3306.
php artisan key:generate
php artisan migrate --seed
php artisan lodge:admin
php artisan serve --host=127.0.0.1 --port=8000
```

In a second terminal:

```powershell
cd frontend
npm ci
Copy-Item .env.example .env
npm run dev
```

Use `127.0.0.1` consistently on both sides, rather than mixing it with `localhost`. `FRONTEND_URL` and `FRONTEND_ALLOWED_ORIGINS` include the scheme and port; `SANCTUM_STATEFUL_DOMAINS=127.0.0.1:5173` omits the scheme. Local session cookies are insecure only for HTTP development; hosted deployments use secure HTTP-only session cookies through the same-origin proxy or shared custom domains. No admin token is stored in localStorage.

Do not overwrite an existing `.env` or regenerate an established deployment key. Admin creation is interactive with a hidden password and requires 12+ characters, mixed case, a number and a symbol. The administrator can update their login email and password in **Admin ? Login credentials** (`/admin/settings/account`), confirming the current password. Leave the new password blank to change only the email. Multiple administrators are supported, with no public registration or member/applicant login. Login accepts email or username. Use `AdministratorSeeder` with private `LODGE_ADMIN_1_*` and `LODGE_ADMIN_2_*` environment values for the two production accounts; reseeding preserves changed passwords. The optional `--from-env` initialization uses `LODGE_ADMIN_EMAIL` and `LODGE_ADMIN_PASSWORD`.

## Application workflow

The visitor submits `/application` to `POST /api/applications`. Laravel validates the form, honeypot, referral details and required declarations. A transaction locks the yearly sequence and saves `APP-{YEAR}-{6 DIGITS}`, `pending` status, unread state and consent timestamps. In production, two database queue jobs are committed with the application and Hostinger cron sends email after commit. With the local sync queue, email is attempted after the save transaction ends. The visitor receives HTTP 201 and a reference even if provider delivery fails.

The administrator sees live database counts for total/active members, pending/unread applications and submissions this month, plus recent applications and a sidebar unread badge. Application lists support reference/name/email search, status and unread filters, sorting and pagination. Opening a detail marks it read; private notes and status have separate protected PATCH endpoints. Approve-and-create-member locks the application and prevents double conversion, even if the resulting member is later deleted. Converted members default to private.

Notifications settings include the lodge recipient, an on/off toggle for new application alerts, optional applicant acknowledgment (disabled by default), and a test email action. Each application records sent/failed timestamps and a sanitized failure message; its detail page supports resending. Production uses `QUEUE_CONNECTION=database` and the bounded `lodge:mail-queue` cron command with retries. Local `QUEUE_CONNECTION=sync` is supported; test emails and manual resends remain immediate. Use `MAIL_MAILER=array` locally when external mail should not be sent.

## Members, CMS and celebrations

- Admin → Members → Add position creates membership categories with a name and display order. Regular positions allow multiple members; officer positions each allow one active occupant and appear in the public officers section. Confirmed replacement moves the old officer to Past Officer transactionally. Duplicate names are rejected. Private and inactive members are omitted from public API resources.
- Page sections support drafts, authenticated previews at `/preview/{slug}`, publishing snapshots, drag-and-drop ordering, duplication, visibility, rich text, banners, images, document lightboxes, galleries, officers, history, affiliations and calls to action. HTML is sanitized server-side.
- Branding, lodge/federation emblems, theme, navigation, contact information, header and footer are editable. Emblems use `object-contain`. Supplied assets live in `frontend/public/images`.
- New image/PDF uploads go through Laravel to Hostinger public storage (optional R2 is retained) with validated type/size, metadata, alternative text and captions. PDF URLs can be linked in CMS rich-text announcements and are excluded from image pickers. Referenced media is protected from deletion, including draft sections, published snapshots and galleries. Portrait replacement uploads first, saves the member, then safely cleans up the old asset.
- Celebrations include birthdays, degree advancements, anniversaries, fellowship and other occasions, with descriptions and pictures. Visibility defaults to public and can be set private. Upcoming public celebrations end at December 31 of the current year. Future-year records can be prepared in advance and remain hidden until that year begins; older public records appear as recent moments. Birthdays are explicit scheduled celebrations, not automatically recurring records.

## API and deployment

`GET /api/health` is a lightweight check. Public endpoints expose allowlisted site settings, member resources and published pages. All `/api/admin/*` routes other than login require Sanctum authentication and administrator authorization. The API route list is available through `php artisan route:list --path=api`.

Follow [the production deployment guide](docs/deployment.md) for fresh Hostinger Unlimited web hosting, MySQL, local public uploads, SMTP email, and the two administrator accounts. React is built locally and uploaded with Laravel's public entry point; private PHP source and credentials remain outside `public_html`. New production starts with seeded website content and empty member, application, celebration and upload records. No testing database or uploaded files are imported.

The [Render alternative](docs/render-deployment.md), Docker deployment, R2, Mailtrap API and Gmail API remain available for an alternative hosting setup. They are not required for Hostinger.

MySQL verification uses `backend/phpunit.mysql.xml` and an isolated MySQL test database on port 15306. Run `php vendor/phpunit/phpunit/phpunit --configuration phpunit.mysql.xml` from `backend`. For browser verification, run `scripts/verify-admin.ps1 -MySql -TestDatabasePort 15306 -BundledFrontend -Public`; it resets only `mason_mysql_browser` and uses temporary credentials. PostgreSQL verification remains in `phpunit.xml`.

## Verification

```powershell
cd backend
composer test
php vendor/bin/pint --test
cd ../frontend
npm run typecheck
npm run build
npx playwright test tests/public.spec.ts
cd ..
powershell -ExecutionPolicy Bypass -File scripts/verify-admin.ps1
```

PHP tests use the disposable `mason_test` PostgreSQL database. Create it with the same test user before running; configure another disposable name in `backend/phpunit.xml` as needed. External email, R2 storage, and legacy Cloudinary calls are faked in tests. Tests cover email failures, after-transaction mail order, unread counts, privacy, authorization, rate limiting, conversion, officer uniqueness, CMS publishing, safe uploads and year rollover.

Public browser tests expect the local API and Vite servers running. The isolated admin script creates/resets **only `mason_browser`**, uses a random temporary password, an API on 8001 and a frontend on 5174, and sends no external email. Never configure either test database as a production database. Admin browser checks cover login/logout, member creation, current/private/future-year birthdays, application submission/review/conversion, CMS preview/publishing and notification settings. `node tests/capture.mjs` creates ignored desktop/mobile screenshots.

The refactor removes Inertia and Laravel's Vite coupling. Existing migrations and business records are preserved by a new additive/column-rename migration. Fresh production uses bundled website assets and new Hostinger public uploads. No hosting accounts have been published or external storage/email credentials configured here. Social metadata is updated in the browser; crawler-specific pre-rendering would be a separate frontend deployment enhancement.

Latest verification: **63 PHP tests / 459 assertions on both MySQL and PostgreSQL**, the complete admin workflow, both seeded username logins, two performance checks and six public desktop/mobile checks passed. TypeScript checks, production build and PHP formatting passed. External delivery still needs the live SMTP and cron checks in the deployment guide.
