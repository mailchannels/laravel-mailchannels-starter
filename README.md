# Laravel MailChannels starter

A small Laravel 13 app that sends email through the [MailChannels Email API](https://docs.mailchannels.com/email-api/overview)
using the official [`mailchannels/mailchannels-php`](https://packagist.org/packages/mailchannels/mailchannels-php)
Laravel mail driver. It covers the email flows most apps need, and it includes a stub API so you can run
everything locally without a MailChannels account.

## Features

- **Sign-up with email verification**: Laravel's `MustVerifyEmail` contract and `Registered` event send
  a one-time link, `APP_URL/verify?token=...`. Each token is 32 random bytes, stored only as a sha256 hash.
  It expires after 24 hours and works once.
- **Password reset**: Laravel's password broker sends `APP_URL/reset-password?token=...`. The token is stored
  hashed, expires after 60 minutes and works once. The response is the same whether or not the address has
  an account. Passwords are hashed with bcrypt, Laravel's default hasher.
- **Contact form**: sends name, email and message to `CONTACT_TO_EMAIL`, with `reply_to` set to the person who
  submitted it. The form is validated and CSRF-protected.
- **Signed webhooks**: `POST /webhooks/mailchannels` checks MailChannels' RFC 9421 Ed25519 signature before
  it logs the events. Parsing, the digest check and the signature base come from the SDK's
  `MailChannels\Webhook\Verifier`. The Ed25519 check uses libsodium. The public key is fetched from
  `/webhook/public-key` and cached. Invalid, tampered or stale requests get `401`, and bad JSON gets `400`.
- **Stub Email API** (`stub/`): a dependency-free PHP stub of `/send`, `/webhook/public-key` and an inspection
  endpoint. It includes a CLI that sends signed (or deliberately broken) synthetic webhooks.

All email goes through the standard `Mail` facade and Mailables (`app/Mail`). The only MailChannels-specific
setting is the `mailchannels` mailer.

## Quick start (stub mode, no account needed)

Requirements: PHP 8.4+ with the `sodium` and `pdo_sqlite` extensions, plus Composer.

```bash
composer install
cp .env.example .env          # already points at the stub on 127.0.0.1:4006
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# terminal 1: the stub Email API (TEST ONLY, it never delivers mail)
php -S 127.0.0.1:4006 stub/server.php

# terminal 2: the app
php artisan serve --port=3006
```

1. Open http://127.0.0.1:3006/signup and sign up as `user@example.test`.
2. Look at what the app sent: `curl http://127.0.0.1:4006/_stub/messages`. Open the `/verify?token=...` link
   from the message body to verify the address.
3. Try http://127.0.0.1:3006/forgot-password and http://127.0.0.1:3006/contact, and check the stub again.
   To clear its log, run `curl -X DELETE http://127.0.0.1:4006/_stub/messages`.
4. Send signed synthetic webhooks:

   ```bash
   php stub/send-webhook.php                   # 200 {"received":2}
   php stub/send-webhook.php --tamper          # 401, body changed after signing
   php stub/send-webhook.php --bad-signature   # 401, signed with another key
   php stub/send-webhook.php --stale           # 401, signed 10 minutes ago
   ```

`sh scripts/smoke-test.sh` runs all of the above against a running app and stub.

With Docker you can instead run `APP_KEY=$(php artisan key:generate --show) docker compose up --build`
to start the app on port 3006 and the stub on port 4006.

## Sending for real

1. Complete the [PHP quickstart prerequisites](https://docs.mailchannels.com/email-api/php/quickstart#prerequisites):
   create an account, create an API key, and add the Domain Lockdown and SPF records for your sending domain.
2. In `.env`, set `MAILCHANNELS_API_BASE_URL=https://api.mailchannels.net/tx/v1` (or leave it empty),
   `MAILCHANNELS_API_KEY`, and `MAIL_FROM_EMAIL` with an address on that domain.
3. In the MailChannels dashboard, register a webhook endpoint that points at `https://<your-host>/webhooks/mailchannels`.

The SDK's transport defaults to the queued `/send-async` endpoint. The Mailables in `app/Mail/TransactionalMail.php`
set the `x-mailchannels-send-async: false` control header to use `/send`, so delivery errors surface
straight away. They also set `x-mailchannels-transactional: true`. See the
[Laravel guide](https://docs.mailchannels.com/email-api/laravel) for the other control headers.

## Environment variables

| Variable | Required | Description |
| --- | --- | --- |
| `APP_KEY` | yes | Laravel's framework secret key. Generate it with `php artisan key:generate`. |
| `APP_URL` | yes | Public base URL. Links in emails are always built from it, never from the request's Host header. |
| `MAIL_MAILER` | yes | `mailchannels`. |
| `MAILCHANNELS_API_KEY` | yes | Your MailChannels API key. Any non-empty value works with the stub. |
| `MAILCHANNELS_API_BASE_URL` | no | Default `https://api.mailchannels.net/tx/v1`. For the stub, use `http://127.0.0.1:4006/tx/v1`. |
| `MAIL_FROM_EMAIL` | yes | Sender address on your authorized domain, for example `noreply@example.com`. |
| `MAIL_FROM_NAME` | no | Sender display name. |
| `CONTACT_TO_EMAIL` | yes | Where contact form messages go. |
| `MAILCHANNELS_WEBHOOK_PUBLIC_KEY` | no | Pins the webhook key instead of fetching it. Accepts base64 SPKI DER, PEM or a raw 32-byte base64 key. |
| `DB_CONNECTION` / `DB_DATABASE` | no | SQLite by default (`database/database.sqlite`). |

`.env.example` holds placeholders only. Never commit real keys.

## Tests and linting

```bash
composer install
php artisan test       # PHPUnit: all flows, token expiry and single use, webhook signatures
vendor/bin/pint --test # Laravel Pint code style
```

The tests send through the real SDK transport and client. A PSR-18 client routes their HTTP calls to the
bundled stub in-process (`tests/Support/StubHttpClient.php`), so no network or secrets are needed. The tests
check the exact `/send` payloads, including the token URLs.

CI (`ci/github-actions/ci.yml`) runs Pint and the tests on PHP 8.4 and 8.5.

> **CI workflow pending:** move `ci/github-actions/ci.yml` to `.github/workflows/ci.yml`. This needs a maintainer
> with workflow permission.

## Deploying

### Render

[![Deploy to Render](https://render.com/images/deploy-to-render-button.svg)](https://render.com/deploy?repo=https://github.com/mailchannels/laravel-mailchannels-starter)

`render.yaml` builds the `Dockerfile` as a web service. Render prompts you for `APP_KEY`
(from `php artisan key:generate --show`), `APP_URL`, `MAILCHANNELS_API_KEY`, `MAIL_FROM_EMAIL` and
`CONTACT_TO_EMAIL`. On startup the container runs the migrations and serves the app on `$PORT`.

The free plan's disk is ephemeral, so the SQLite database resets on each deploy. For real use, attach a
[persistent disk](https://render.com/docs/disks) and point `DB_DATABASE` at it, or switch `DB_CONNECTION` to
Postgres. The container uses `php artisan serve`, which keeps the starter simple. For heavier traffic, use
PHP-FPM with nginx or FrankenPHP.

### Laravel Cloud

Create an application from this repository on [Laravel Cloud](https://cloud.laravel.com). Add a database, set
the environment variables above, and keep the default deploy command `php artisan migrate --force`.

## Learn more

- [Email API quickstart (PHP)](https://docs.mailchannels.com/email-api/php/quickstart)
- [Laravel mail driver guide](https://docs.mailchannels.com/email-api/laravel)
- [Webhooks and signature verification](https://docs.mailchannels.com/email-api/webhooks#verifying-message-signatures)
- [MailChannels docs](https://docs.mailchannels.com)
- [PHP SDK source](https://github.com/mailchannels/mailchannels-email-api-sdk-php)

## License

MIT. See [LICENSE](LICENSE).
