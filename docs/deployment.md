# Hostinger Unlimited deployment: fresh production

The intended setup is one PHP website at **https://gfmasoniclodge40.org**: React static assets and Laravel on the same origin, Hostinger MySQL, Hostinger disk storage for pictures/PDFs, and a Hostinger mailbox for outgoing application emails. No VPS, Node server, Render, Vercel, Supabase, R2 or Cloudinary account is needed for this deployment.

The Unlimited plan shown in your screenshot costs **PHP 449/month with one-month billing** and includes 50 GB storage. Premium can also run the application, but Unlimited offers more room for photographs and the backup features shown in your plan. Storage, file count, database size, CPU and PHP worker limits still apply. Check your actual hPanel limits and mailbox renewal price before purchasing; the included email offer is time-limited. Shared hosting does not promise that every page will load instantly.

## 1. Domain and hosting

1. Complete the verification email for the domain, which currently shows Pending verification. Find Hostinger's domain verification message in the address used to purchase the domain, and follow its confirmation link.
2. Purchase **Unlimited Web Hosting**, attach your existing `gfmasoniclodge40.org`, and choose an **empty PHP/HTML website**. Do not install WordPress or build the site with the Website Builder. This application already exists.
3. In **Websites ? Manage/Dashboard**, locate **PHP Configuration**. Select **PHP 8.3 or a compatible newer version**. Enable `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `ctype`, `curl`, `dom`, `xml`, `tokenizer`, `session` and `zip`; enable `gd` for image handling. Confirm Composer's requirements in step 6. Configure `upload_max_filesize` at least `10M`, `post_max_size` at least `16M`, and `memory_limit` at least `256M`. Application uploads remain limited to 8 MB per file.
4. Enable SSL and Force HTTPS for the domain. Choose a nearby server region if offered, such as Singapore. Leave the nameservers supplied by Hostinger unless its setup explicitly asks you to change them.
5. Enable **SSH Access**. Copy its hostname, port and account username. Use the exact SSH command displayed in hPanel. All commands below run on Hostinger after connecting through SSH.

## 2. Create the database

In the website dashboard, open **Databases ? Management/MySQL Databases**. Create a database and database user with a unique password. Record the **complete names including the account prefix**, such as `u123456789_lodge`. Record the database host shown by Hostinger (normally `localhost`) and port (normally `3306`). Do not use Supabase connection details. This is a fresh database; there is no old database import.

## 3. Create the sending mailbox

In **Emails**, select the domain and create **applications@gfmasoniclodge40.org**. Set its mailbox password. In email setup, complete the DNS connection checks, including SPF and DKIM, and follow Hostinger's DMARC instructions. Use the exact records displayed for your email service; avoid duplicate SPF records. Log in to webmail and send a manual email to confirm the mailbox is active.

The application uses this mailbox's SMTP credentials, **not your Hostinger account password**:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_ENCRYPTION=ssl
MAIL_USERNAME=applications@gfmasoniclodge40.org
MAIL_PASSWORD="YOUR_MAILBOX_PASSWORD"
MAIL_FROM_ADDRESS=applications@gfmasoniclodge40.org
```

If hPanel provides different connection details for your actual email product, use those details instead. Check **Emails ? Mailboxes ? Limits** for the sending quota. Two emails per application consume two outgoing messages. Hosting storage and mailbox storage/quotas are separate.

## 4. Build and upload the application

On your Windows computer, from the project folder:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/build-hostinger.ps1
```

The script requires Python 3 for portable ZIP packaging and builds React with relative `/api` URLs and prints the path to a ZIP in `.local`. The package deliberately excludes actual `.env` files, database dumps, existing uploads, development dependencies and cached configuration. **PHP Composer dependencies are installed on Hostinger in step 6.** Node is only needed on your computer for this build.

In Hostinger File Manager, navigate to the domain directory **one level above `public_html`**. Upload and extract the archive there. The resulting structure must be:

```text
/home/uXXXXX/domains/gfmasoniclodge40.org/
??? lodge/                 Laravel source, private .env and vendor dependencies
?   ??? artisan
?   ??? bootstrap/
?   ??? storage/
?   ??? .env.example
??? public_html/           Only browser-accessible files
    ??? index.php          Laravel entry point
    ??? index.html         React build
    ??? .htaccess
    ??? assets/
    ??? images/
    ??? storage/           Uploaded pictures and PDFs
        ??? .htaccess
```

Ensure hidden files were extracted. The application `.env`, `vendor`, `app`, and database files must remain in `lodge`, outside `public_html`. Remove or rename Hostinger's placeholder `default.php`/old landing page if present. Preserve the package's `index.php` and `.htaccess`.

## 5. Fill in private production configuration

The local file **deployment/hostinger/.env** contains the two usernames and initial passwords you supplied. It is Git-ignored and **is not included in the ZIP**. Open it privately, complete the database/mailbox details, and upload it as **lodge/.env**, outside the public web directory. Alternatively copy the uploaded `.env.example` to `.env` and privately supply both seed passwords. Do not place credentials in frontend configuration.

Replace:

- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: the exact MySQL values from step 2.
- `DB_HOST`: the host provided by Hostinger, normally `localhost`.
- `MEDIA_PUBLIC_ROOT`: the actual absolute folder path, e.g. `/home/u123456789/domains/gfmasoniclodge40.org/public_html/storage`. File Manager/SSH shows the correct account username; do not leave `uXXXXX`.
- `MAIL_PASSWORD`: the password of the sending mailbox.
- `APPLICATION_NOTIFICATION_EMAIL`: the address that should receive administrator alerts. It can be Gmail or a lodge mailbox; it does not need to match the sending address.

Keep `APP_DEBUG=false`, `APP_ENV=production`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, and secure cookies enabled. Keep all frontend/origin URLs at `https://gfmasoniclodge40.org` with **no trailing slash**. `SANCTUM_STATEFUL_DOMAINS` is `gfmasoniclodge40.org`, without `https://`. There is no need to keep the former Vercel/Render origins or Mailtrap/R2/Cloudinary credentials.

The seed configuration is:

```dotenv
LODGE_ADMIN_1_USERNAME=worshipfulmasterRJMahilum
LODGE_ADMIN_1_PASSWORD="YOUR_FIRST_INITIAL_PASSWORD"
LODGE_ADMIN_2_USERNAME=milzan
LODGE_ADMIN_2_PASSWORD="YOUR_SECOND_INITIAL_PASSWORD"
```

Passwords are hashed when the accounts are created. Re-running this seeder never resets existing usernames/passwords or promotes an unrelated existing account. Optional `LODGE_ADMIN_1_EMAIL` and `LODGE_ADMIN_2_EMAIL` can provide real personal addresses. If omitted, the initial account email uses the username at the lodge domain; this **does not create a mailbox**. Each administrator can update their username, real email and password in **Admin ? Login credentials**. Notification recipients are configured separately.

## 6. Initialize through SSH

Use your actual account path:

```bash
cd /home/uXXXXX/domains/gfmasoniclodge40.org/lodge
php -v
composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts
php artisan package:discover --ansi
composer check-platform-reqs --no-dev
php artisan config:clear
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Only generate `APP_KEY` on this **first fresh installation**. Preserve it on future deployments. Do not run `migrate:fresh` in production. The `--no-scripts` option avoids Composer launching a PHP subprocess when Hostinger disables `proc_open`; package discovery is run directly afterwards. Composer/platform checks must succeed before proceeding; use the PHP CLI version matching the website's configured version if `php -v` differs.

Laravel needs write permission on `lodge/storage`, `lodge/bootstrap/cache`, and `public_html/storage`. Start with owner-writable directories (usually 755 on Hostinger); adjust through File Manager if the hosting account requires 775. Do not make everything 777. Uploads go directly to `public_html/storage`; **no symlink or `storage:link` is required**.

After the first successful seed, remove the four `LODGE_ADMIN_*_USERNAME/PASSWORD` lines from production `.env`, then run `php artisan config:cache`. The database keeps both accounts. Keep your local private deployment file securely if you need it; do not share it or include it in an archive.

## 7. Enable the email cron job

Open **Websites ? Dashboard ? Advanced ? Cron Jobs**. Choose **Custom**, and enter (using your real account path):

```text
/usr/bin/php /home/uXXXXX/domains/gfmasoniclodge40.org/lodge/artisan lodge:mail-queue
```

Set minute, hour, day, month and weekday to `*` to run every minute. If your plan offers a longer minimum interval, choose its shortest allowed interval; that interval is the normal email delay. Use the PHP binary/version confirmed in step 6.

`lodge:mail-queue` runs at most 20 jobs, stops when there are no ready jobs, and checks a 45-second batch budget between jobs. A job in progress can take the run past that budget. A shared database cache lock prevents overlapping cron runs for up to five minutes. Failed sends retry with backoff; no permanent worker is needed. Check cron output in hPanel. If cron is absent, new applications remain saved but their queued emails wait.

Run one batch manually to verify the command:

```bash
php artisan lodge:mail-queue
```

## 8. Verify the live site

1. Open `https://gfmasoniclodge40.org`, then `/members` and `/celebrations` directly to verify deep links.
2. Open **https://gfmasoniclodge40.org/admin/login**. Sign in using either of the two supplied usernames and its password. Both have administrator access. Update each account's real email/password under Login credentials.
3. In **Admin ? Notifications**, enable new application notifications, enter the administrator recipient, save, and send a test email. Test emails are sent immediately; ordinary application emails use cron.
4. Enable **Send an acknowledgment email to the applicant** and save. Submit one test application with an email you can check. Confirm its reference is displayed immediately. After cron runs, confirm **two messages**: an administrator notification and the applicant's acknowledgment containing the reference. Check inbox and spam.
5. Upload a picture, use it in a celebration, and confirm it displays publicly. Upload a PDF in Media and link it in announcement content. Referenced files cannot be deleted accidentally. Image/PDF files persist across code deployments.
6. If emails fail, inspect `lodge/storage/logs/laravel.log`, cron output and `php artisan queue:failed`. Correct SMTP/DNS/limits first, then use `php artisan queue:retry all` for failed application jobs and run the mail queue. Do not post passwords or the whole `.env` publicly.

For the faster first installation and automatic updates without ZIP extraction, follow [GitHub ? Hostinger deployment](github-hostinger.md).

## 9. Updates, storage and backups

Enable the backup facilities included in your purchased plan and periodically download a MySQL backup plus `public_html/storage` and the private `.env`. Confirm backups can actually be restored. Images consume hosting disk space until deleted; the 50 GB allowance is capacity, not a monthly upload refill. File/inode and account resource limits can be reached before disk space is full. Resize large photos before upload.

For updates, build a new archive, back up, enter maintenance with `php artisan down`, and replace application code and static build assets. **Preserve `lodge/.env`, `lodge/storage`, and the entire existing `public_html/storage` directory**; do not empty or replace uploads. Install Composer dependencies, run `php artisan migrate --force`, refresh config/route/view caches, and run `php artisan up`. Never regenerate the app key or run database reset commands. Keep cron configured. If reseeding, clear config first so `.env` seed values load, and restore the cache afterwards.

The old Render deployment remains an optional alternative in [render-deployment.md](render-deployment.md); it is not needed for this Hostinger setup.

Official references: [Hostinger databases](https://www.hostinger.com/support/which-databases-and-data-tools-are-supported-at-hostinger/), [Laravel directory layout](https://www.hostinger.com/support/6152127-how-to-deploy-laravel-8-at-hostinger/), [cron setup](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/), [mailbox SMTP settings](https://www.hostinger.com/support/1575756-how-to-get-email-account-configuration-details-for-hostinger-email/).
