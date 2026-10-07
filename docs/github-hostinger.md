# Automatic Hostinger updates from GitHub

The workflow is `.github/workflows/deploy-hostinger.yml`. It deploys application changes pushed to `main`, or a manual **Actions ? Deploy to Hostinger ? Run workflow** on `main`. It builds React and production PHP dependencies on GitHub, then updates the server over SSH/rsync. Repository contents remain organized as backend/frontend; server files remain `lodge` and `public_html`.

## 1. Connect the existing live website

Your SSH access is active: host **145.79.28.141**, port **65002**, username **u270132584**. PHP 8.3, Composer and rsync were confirmed. The exact domain directory is `/home/u270132584/domains/gfmasoniclodge40.org`.

The website has already been installed from the ZIP and initialized over SSH. Use ordinary **update** mode, including the first GitHub Actions run. Leave **First installation** unchecked. Do not upload another `.env`, regenerate APP_KEY, or seed the database again. The workflow deploys to the existing top-level `lodge` and `public_html` folders.

The production configuration remains in `lodge/.env` on Hostinger. Updates preserve it, including the database password and existing APP_KEY. `CACHE_STORE=database` and the migrated cache tables are required for the deployment/mail-worker lock.

Email is not configured yet. Keep `MAIL_MAILER=array` until a real mail transport is configured, and do not enable the email cron while using this temporary transport. Email setup is separate from automatic code deployment; follow [deployment.md](deployment.md) when the mailbox is ready.

## 2. Create a dedicated deployment key

A dedicated key has already been generated locally:

- Public key: `C:/Users/Admin/.ssh/hostinger_mason_deploy.pub`
- Private key: `C:/Users/Admin/.ssh/hostinger_mason_deploy`
- Verified server entry from your existing SSH connection: `C:/laragon/www/mason/.local/hostinger-known-hosts.txt`

Use these existing files; do not generate another key or overwrite them. The public `.pub` file goes to Hostinger, the private file goes only into the GitHub Actions secret.

In **Hostinger ? SSH Access ? SSH Keys**, import/add the contents of **hostinger_mason_deploy.pub**. The public key is the one-line text beginning `ssh-ed25519`. If hPanel has no import UI, log in using its displayed SSH command and add the public key as a new line to the account's `~/.ssh/authorized_keys`, preserving existing keys. Use directory mode 700 and authorized_keys mode 600.

Test the key using the actual host, port and account username from hPanel:

```powershell
ssh -i "$env:USERPROFILE\.ssh\hostinger_mason_deploy" -p 65002 u270132584@145.79.28.141
```

Check the server host-key fingerprint when connecting; confirm it against your established trusted SSH connection or Hostinger support before accepting an unfamiliar key. After a trusted successful connection, exit SSH.

## 3. Add GitHub repository secrets

Open the repository [Settings ? Secrets and variables ? Actions](https://github.com/kylesepano/masoncdo/settings/secrets/actions), then **New repository secret**. Add:

| Secret name | Value |
|---|---|
| `HOSTINGER_SSH_HOST` | `145.79.28.141` |
| `HOSTINGER_SSH_PORT` | `65002` |
| `HOSTINGER_SSH_USER` | `u270132584` |
| `HOSTINGER_SITE_PATH` | `/home/u270132584/domains/gfmasoniclodge40.org` |
| `HOSTINGER_SSH_PRIVATE_KEY` | Entire contents of the private key file, including BEGIN/END OPENSSH PRIVATE KEY lines |
| `HOSTINGER_SSH_KNOWN_HOSTS` | The verified known_hosts entry for this SSH host and port |

Open the private key file privately to copy its contents into the GitHub secret. Do not commit it, paste it in chat or put it in Hostinger's public folder. The public key belongs on Hostinger; the private key belongs in the GitHub secret.

To retrieve the trusted known_hosts entry after the verified SSH connection:

```powershell
ssh-keygen -F "[YOUR_HOST]:YOUR_PORT" -f "$env:USERPROFILE\.ssh\known_hosts"
```

For port 22, use `ssh-keygen -F "YOUR_HOST" ...` instead. Copy the actual host-key lines into `HOSTINGER_SSH_KNOWN_HOSTS`; omit lines beginning `#`. The workflow checks the stored server key strictly. Do not substitute an unverified `ssh-keyscan` result or disable host checking.

Under the repository's **Variables** tab, optionally add **HOSTINGER_PHP_BIN** with the PHP executable path you verified on the server. Leave it unset to use `php`; ensure that CLI PHP matches the compatible version configured for your website. These are repository secrets/variables, so a paid GitHub deployment environment is not required. Actions usage still follows your account's allowance.

## 4. Publish the workflow and run it

After the SSH key test succeeds and all six secrets are configured, commit and push the reviewed application/deployment changes to the repository's `main` branch, keeping all `.env` files and keys excluded. The local workflow is not on GitHub until pushed. The push automatically starts an ordinary update. Open **GitHub Actions > Deploy to Hostinger** to watch it. For a manual retry, choose **Run workflow**, select `main`, and leave **First installation** unchecked because this website is already live.

Then check the homepage, admin login, a picture/PDF URL, and a test application. Once email is configured, also verify both application emails through the cron job. Subsequent pushes affecting the application or deployment scripts will deploy automatically; documentation-only changes do not trigger it.

## What an update preserves

### Administrator management

The role migration upgrades the original `worshipfulmasterRJMahilum` and `milzan` accounts to superadmins, matched by their original username or seeded email. Password hashes are preserved. New initial seed accounts are also superadmins; ordinary updates do not rerun the seeder. The role is stored on the account and remains when login credentials change.

After deployment, log in with either superadmin and open **Administrators** in the sidebar to add regular admins with a name, unique username, email, and confirmed password. Only superadmins can list, add, or delete administrator accounts. Superadmin accounts cannot be deleted from this page, and new admins cannot be granted the superadmin role through the API. Regular admins keep the existing lodge management features and their own credential editor. Deleting a regular admin removes database sessions and prevents future login. Share initial credentials privately; account creation does not send them by email.

The rsync rules exclude/protect `lodge/.env`, all `lodge/storage` data, `lodge/bootstrap/cache`, and all uploaded `public_html/storage` files. Hosting-managed `.well-known`, `cgi-bin`, `.user.ini` and `php.ini` are protected too. The storage `.htaccess` is updated separately. `--delete` removes obsolete application/build files only in the validated application/public directories; excluded persistent data is protected, and `--delete-excluded` is never used.

The database is migrated normally and never reset. Only the explicit First installation run seeds initial data and generates a missing APP_KEY; successful initialization is recorded and repeated initialization is refused. Ordinary updates preserve APP_KEY and do not seed. Deployments are serialized, and the website enters maintenance mode before files change. The deployment obtains the mail cron lock and waits up to 90 seconds for an active email batch to finish; cron skips during the transfer. It discovers packages, clears/rebuilds generated caches, applies migrations, releases the lock and leaves maintenance only after successful completion.

This is an in-place deployment with a short maintenance window, not automatic rollback. Keep hPanel backups for code, uploads and MySQL. If a deployment fails after maintenance starts, the site stays in maintenance so mixed code is not served. Inspect the failed Actions step, fix the cause and rerun. The mail lock expires after 30 minutes if interrupted. If needed, restore a known good backup, clear generated caches, and run `php artisan up` only after verifying the application is healthy; a code rollback alone does not undo database migrations.

Official references: [Hostinger SSH access](https://www.hostinger.com/support/1583245-how-to-connect-to-a-hosting-plan-via-ssh-in-hostinger/), [Hostinger SSH keys](https://support.hostinger.com/en/articles/5634532-how-to-generate-ssh-keys-and-add-them-to-hpanel), [Hostinger rsync](https://www.hostinger.com/support/how-to-use-rsync-to-sync-files-and-directories-at-hostinger/), [GitHub Actions secrets](https://docs.github.com/en/actions/how-tos/write-workflows/choose-what-workflows-do/use-secrets).
