# Golden Friendship Masonic Lodge No. 40

An independent React/TypeScript frontend and Laravel REST API for the lodge in Cagayan de Oro City. The ceremonial website, supplied emblem, photographs, members, editable CMS and celebrations are preserved.

| Application | Stack | Deployment |
| --- | --- | --- |
| `frontend/` | React 19, React Router, Axios, Vite, Tailwind 4 | Vercel |
| `backend/` | Laravel 12, PHP 8.3+, Sanctum cookie authentication | Render Docker |
| Database | PostgreSQL through Laravel PDO | Supabase |
| Uploaded images | Signed server-side uploads | Cloudinary |
| Email | Laravel Mail with configurable SMTP | Any compatible provider |

## Local installation

The existing workspace uses PostgreSQL on **5433** in a dedicated `C:\laragon\data\mason-postgres` cluster; its password is stored only in ignored `backend/.env`. Existing PostgreSQL on port 5432 is unchanged. The browser frontend is now **http://127.0.0.1:5173**; Laravel at port 8000 serves the API.

For a fresh checkout, create a PostgreSQL database and user, then:

```powershell
cd backend
composer install
Copy-Item .env.example .env
# Edit DB_* in .env; configure Cloudinary and SMTP when needed.
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

Do not overwrite an existing `.env` or regenerate an established deployment key. Admin creation is interactive with a hidden password and requires 12+ characters, mixed case, a number and a symbol. Only one admin is allowed, with no public registration or member/applicant login. The optional `--from-env` initialization uses `LODGE_ADMIN_EMAIL` and `LODGE_ADMIN_PASSWORD`.

## Application workflow

The visitor submits `/application` to `POST /api/applications`. Laravel validates the form, honeypot, referral details and required declarations. A transaction locks the yearly sequence and saves `APP-{YEAR}-{6 DIGITS}`, `pending` status, unread state and consent timestamps. Email is attempted after the save transaction ends. The visitor receives HTTP 201 and a reference even if delivery fails.

The administrator sees live database counts for total/active members, pending/unread applications and submissions this month, plus recent applications and a sidebar unread badge. Application lists support reference/name/email search, status and unread filters, sorting and pagination. Opening a detail marks it read; private notes and status have separate protected PATCH endpoints. Approve-and-create-member locks the application and prevents double conversion, even if the resulting member is later deleted. Converted members default to private.

Notifications settings include the lodge recipient, an on/off toggle for new application alerts, optional applicant acknowledgment (disabled by default), and a test email action. Each application records sent/failed timestamps and a sanitized failure message; its detail page supports resending. Delivery is synchronous with a short timeout and needs no queue worker. Use `MAIL_MAILER=array` locally when external mail should not be sent.

## Members, CMS and celebrations

- The three officer positions each allow one active occupant. Confirmed replacement moves the old officer to Past Officer transactionally; normal membership is unlimited. Private and inactive members are omitted from public API resources.
- Page sections support drafts, authenticated previews at `/preview/{slug}`, publishing snapshots, drag-and-drop ordering, duplication, visibility, rich text, banners, images, document lightboxes, galleries, officers, history, affiliations and calls to action. HTML is sanitized server-side.
- Branding, lodge/federation emblems, theme, navigation, contact information, header and footer are editable. Emblems use `object-contain`. Supplied assets live in `frontend/public/images`.
- All new image uploads go through Laravel to Cloudinary with validated type/size, metadata, alternative text and captions. Referenced media is protected from deletion, including draft sections, published snapshots and galleries. Portrait replacement uploads first, saves the member, then safely cleans up the old asset.
- Celebrations include birthdays, degree advancements, anniversaries, fellowship and other occasions, with descriptions and pictures. Visibility defaults to public and can be set private. Upcoming public celebrations end at December 31 of the current year. Future-year records can be prepared in advance and remain hidden until that year begins; older public records appear as recent moments. Birthdays are explicit scheduled celebrations, not automatically recurring records.

## API and deployment

`GET /api/health` is a lightweight check. Public endpoints expose allowlisted site settings, member resources and published pages. All `/api/admin/*` routes other than login require Sanctum authentication and administrator authorization. The API route list is available through `php artisan route:list --path=api`.

Follow [the deployment guide](docs/deployment.md) for Vercel SPA routing, Render environment values, Supabase session pooling, Cloudinary, shared-domain cookies and SMTP troubleshooting. The Render Blueprint uses external Supabase PostgreSQL and contains no managed Render database. The Docker image builds only Laravel; Vercel builds the independent frontend.

Render's free tier blocks SMTP ports 25, 465 and 587. SMTP testing on that tier needs a provider with a supported alternate port, or a paid service. For free Vercel/Render URLs including login, use [the same-origin proxy testing guide](docs/free-testing.md). Shared custom domains remain supported for direct cross-origin requests.

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

PHP tests use the disposable `mason_test` PostgreSQL database. Create it with the same test user before running; configure another disposable name in `backend/phpunit.xml` as needed. External email and Cloudinary calls are faked only in tests. Tests cover email failures, after-transaction mail order, unread counts, privacy, authorization, rate limiting, conversion, officer uniqueness, CMS publishing, safe uploads and year rollover.

Public browser tests expect the local API and Vite servers running. The isolated admin script creates/resets **only `mason_browser`**, uses a random temporary password, an API on 8001 and a frontend on 5174, and sends no external email. Never configure either test database as a production database. Admin browser checks cover login/logout, member creation, current/private/future-year birthdays, application submission/review/conversion, CMS preview/publishing and notification settings. `node tests/capture.mjs` creates ignored desktop/mobile screenshots.

The refactor removes Inertia and Laravel's Vite coupling. Existing migrations and business records are preserved by a new additive/column-rename migration. Legacy `/storage/...` image references require uploading those images to Cloudinary and replacing their CMS references before deployment. No hosting accounts have been published or external SMTP/Cloudinary credentials configured here. Social metadata is updated in the browser; crawler-specific pre-rendering would be a separate frontend deployment enhancement.

Latest local verification: **29 PHP tests / 203 assertions**, **6 public browser checks + 1 complete admin workflow**, TypeScript checks and production frontend build passed. Two browser cases are intentionally skipped on the inapplicable device project. Composer validation, PHP formatting, and route/view caching passed. The Docker configuration was reviewed; a container build was unavailable because Docker Desktop's Linux engine was not running.


## SIGLO homepage adaptation

The lodge homepage now follows [the SIGLO Federation homepage](https://www.siglofederation.org/): a photograph hero, white archive layout, historical charters and emblems, Scottish Rite text, and federation relationships. Golden Friendship's supplied emblem and lodge identity appear beneath the hero. The text comes from the supplied homepage transcript; federation documents remain labelled as federation documents. The lodge application form also works directly on the homepage.

All 18 content sections remain editable in the CMS, including archive column layouts, images, captions and links. Static reference images are bundled in `frontend/public/images/siglo`, with their origins recorded in `sources.json`. Subsequent admin uploads still use Cloudinary. The initial homepage template is in `backend/database/content/home.json`; fresh databases receive it during seeding. Existing production pages are preserved during ordinary startup. To deliberately replace an existing homepage with this template, back it up first and run `php artisan lodge:homepage`. This command replaces and publishes the homepage only. The previous local homepage was backed up to ignored `.local/home-before-siglo.json` before applying the change.
