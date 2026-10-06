# Gmail API on Render Free

The backend supports `MAIL_MAILER=gmail_api`. It uses Laravel's existing mail templates and calls Google's OAuth and Gmail HTTPS endpoints. Both the admin alert and optional applicant reference acknowledgment use this mailer, including Admin → Notifications → Send test email. SMTP ports, Gmail app passwords, Mailtrap tokens, additional Composer packages, and a purchased domain are not required.

Only the sending Gmail account grants access. Applicants and the notification recipient do not need to authorize Google or sign in through Google. Gmail sending limits still apply; successful API acceptance does not guarantee inbox placement.

## 1. Create the Google project

1. Sign in at https://console.cloud.google.com/ with the Gmail account that should send lodge emails.
2. Create a project named `Mason CDO Email` and select it.
3. Open **APIs & Services → Library**, find **Gmail API**, and click **Enable**.
4. Open **Google Auth Platform → Branding** (older consoles label this OAuth consent screen) and **Get Started**.
5. App name: `Golden Friendship Lodge`. Support/contact email: the sending Gmail address.
6. Audience: **External** for a personal Gmail account. Leave publishing status **Testing** initially.
7. In **Audience → Test users**, add the sending Gmail account.
8. Under **Data Access → Add or remove scopes**, add only:

```text
https://www.googleapis.com/auth/gmail.send
```

This requests permission to send email, not read the inbox. See [Google consent setup](https://developers.google.com/workspace/guides/configure-oauth-consent) and [Gmail scopes](https://developers.google.com/workspace/gmail/api/auth/scopes).

## 2. Create OAuth credentials

1. Open **Google Auth Platform → Clients → Create client** (or APIs & Services → Credentials → Create Credentials → OAuth client ID).
2. Application type: **Web application**. Name: `Mason Gmail Sender`.
3. Add this exact **Authorized redirect URI**, without a trailing slash:

```text
https://developers.google.com/oauthplayground
```

4. Create the client and securely copy its **Client ID** and **Client secret**. Do not commit downloaded credentials or screenshots exposing the secret.

No callback route is needed on the lodge website for this one-time setup: Google's Playground performs authorization, and the backend subsequently refreshes access using the saved token.

## 3. Get the refresh token

1. Open https://developers.google.com/oauthplayground/.
2. Click the settings gear in the upper-right.
3. Check **Use your own OAuth credentials**. Enter the Client ID and Client secret from your project.
4. Ensure **Access type: Offline**, **OAuth endpoints: Google**, and **Force prompt: Consent Screen**.
5. Under Step 1, enter `https://www.googleapis.com/auth/gmail.send` in the custom scope field.
6. Click **Authorize APIs**. Sign in as the sending Gmail account (which must be your test user) and grant sending permission.
7. If Google's unverified-app notice appears, proceed only after confirming it is your own project and requests the expected sending scope. If an organization blocks the app, its administrator must approve access.
8. Under Step 2, click **Exchange authorization code for tokens**.
9. Copy the **Refresh token**, not the short-lived Access token. Save it securely for Render.

If no refresh token appears, check Offline access and consent prompt, then authorize again. Use your own client credentials: [Google Playground](https://developers.google.com/oauthplayground/) normally revokes tokens obtained with its shared client after 24 hours.

## 4. Deploy the code and configure Render

Commit and push the backend changes before selecting the new mailer. Add/update these variables in Render → Environment:

```dotenv
MAIL_MAILER=gmail_api
GMAIL_CLIENT_ID=YOUR_GOOGLE_CLIENT_ID
GMAIL_CLIENT_SECRET=YOUR_GOOGLE_CLIENT_SECRET
GMAIL_REFRESH_TOKEN=YOUR_GOOGLE_REFRESH_TOKEN
MAIL_FROM_ADDRESS=YOUR_AUTHORIZED_GMAIL_ADDRESS
MAIL_FROM_NAME=Golden Friendship Lodge
MAIL_TIMEOUT=8
APPLICATION_NOTIFICATION_EMAIL=YOUR_ADMIN_NOTIFICATION_ADDRESS
```

MAIL_FROM_ADDRESS must be the Gmail account you authorized. MAIL_FROM_NAME is the display name recipients see. The admin recipient and applicant can be different email addresses. Keep all OAuth values in backend environment variables only; do not put them in Vercel or a VITE_* variable.

MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD and MAIL_ENCRYPTION are unused by `gmail_api`; remove obsolete Mailtrap variables if desired. Save and redeploy Render so configuration caching picks up the changes. No frontend changes are needed.

## 5. Enable and test both emails

1. Sign in at https://masoncdo.vercel.app/admin/login.
2. Open **Notifications**.
3. Enable **Send email for new applications**.
4. Set **Application notification email** to the desired admin inbox. This saved value overrides the Render fallback recipient.
5. Enable **Send an acknowledgment email to the applicant**.
6. Click **Save notifications**, then **Send test email**.
7. Check the recipient's inbox/spam and the sending Gmail account's Sent folder. Mailtrap Email Logs are no longer used.
8. Submit a new test application using a different applicant email address. Expect the admin alert and the applicant acknowledgment with the application's reference number.

Old applications are not emailed automatically when you change providers. Existing admin alerts can be resent from their application's detail page; acknowledgment testing should use a new application. Failure to send never removes a saved application.

## Token expiration and troubleshooting

External OAuth apps in **Testing** issue Gmail refresh tokens that expire after **7 days**. Reauthorize and replace GMAIL_REFRESH_TOKEN when it expires. This is suitable for short testing; for ongoing use, review Google's publishing/verification requirements and move the OAuth app out of Testing when appropriate, then obtain a fresh token. Publishing does not guarantee indefinite validity: revoked access and other Google policies can invalidate refresh tokens. [Google OAuth lifecycle](https://developers.google.com/identity/protocols/oauth2).

- `Unsupported mail transport [gmail_api]`: the new backend code has not deployed.
- Missing credentials/authorization failure: check all three GMAIL_* variables, the client used to obtain the token, and Testing expiration or revoked access.
- Rejected email: ensure Gmail API is enabled, the token has gmail.send access, MAIL_FROM_ADDRESS matches the authorized account, and sending limits are not exceeded.
- No applicant email: enable the acknowledgment checkbox and submit a new application.
- Wrong admin recipient: update and save it in the admin Notifications page.
- Test email still reports failure: check Render logs and the application delivery status; do not share tokens or credential files.

The implementation never logs token responses or email payloads. Network/provider failures produce sanitized errors and do not silently fall back to a mailer that discards messages. Each failed send can be retried deliberately, without an automatic send retry that could duplicate email.

The transport has been tested using fake Google HTTP responses. Real Google authorization and delivery must be verified after you configure your account.

Reference: [Gmail send endpoint](https://developers.google.com/workspace/gmail/api/reference/rest/v1/users.messages/send), [OAuth token refresh](https://developers.google.com/identity/protocols/oauth2/web-server).
