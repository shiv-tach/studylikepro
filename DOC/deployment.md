# Deployment & operations runbook

Everything needed to take Studylikepro from a laptop to a production host, and to
keep it healthy afterwards. Written for a single small server (one web process,
one queue worker, one scheduler); scale each piece horizontally when traffic
demands it.

---

## 1. What production needs

| Piece | Requirement | Notes |
| --- | --- | --- |
| PHP | 8.2+ with `pdo_mysql`, `mbstring`, `gd` or `imagick`, `zip`, `intl` | `bcmath` is not required; money is stored in minor units. |
| Database | MySQL 8 / MariaDB 10.4+ (PostgreSQL works too) | `utf8mb4` collation, UTC timezone on the connection. |
| Cache / queue | Database (default) or Redis when available | `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis` once traffic grows. |
| Web server | Nginx + PHP-FPM | Document root is `public/`; deny access to `.env`, `storage/`, `vendor/`. |
| Node (build time only) | Node 20+ for `npm ci && npm run build` | The built assets are committed to the release, not to git. |
| Object storage | S3-compatible bucket for avatars and chat photos (optional) | Keeps uploads off the app server; set `FILESYSTEM_DISK`, `AWS_*`. |
| SMTP | Any transactional provider | `MAIL_MAILER=smtp` + host/user/password, and a verified `MAIL_FROM_ADDRESS`. |
| Supervisor | Keeps `php artisan queue:work` alive | Notifications, AI classification, meeting creation and receipts are queued. |
| Cron | `php artisan schedule:run` every minute | Holds, reminders, digests, auto-completion, reconciliation. |

---

## 2. Environment checklist

Copy `.env.example` and fill in, at minimum:

```dotenv
APP_NAME=Studylikepro
APP_ENV=production
APP_DEBUG=false
APP_URL=https://studylikepro.com
APP_KEY=            # php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=studylikepro
DB_USERNAME=
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS="hello@studylikepro.com"
MAIL_FROM_NAME="${APP_NAME}"

SUPPORT_EMAIL=support@studylikepro.com
SUPPORT_PHONE="+91 …"
POLICY_VERSION=2026-10-01

ADMIN_EMAIL=            # first administrator, used by AdminUserSeeder
ADMIN_PASSWORD=

PLATFORM_CURRENCY=LKR            # Sri Lankan rupees; shown as "RS: 1,250.00"
PLATFORM_CURRENCY_SYMBOL="RS:"
PLATFORM_COMMISSION_PERCENT=15

PAYMENTS_GATEWAY=razorpay        # INR-only; an LKR provider must be implemented first
RAZORPAY_KEY=
RAZORPAY_SECRET=
RAZORPAY_WEBHOOK_SECRET=

MEETING_PROVIDER=daily
DAILY_API_KEY=

OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o-mini
AI_MIN_CONFIDENCE=0.55
```

Then run the first deploy:

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate            # only on the very first deploy
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=PlatformSettingsSeeder --force
php artisan db:seed --class=CatalogSeeder --force
php artisan db:seed --class=AdminUserSeeder --force   # needs ADMIN_EMAIL/PASSWORD
php artisan storage:link
php artisan optimize                 # config + route + view caches + events
```

`DemoDataSeeder` only runs in the `local` environment, so production never gets
demo lessons, logins or reviews.

---

## 3. Deploying a new release

Zero-drama order (repeatable, ~1 minute of downtime-free operation):

```bash
cd /var/www/studylikepro
php artisan down --secret="<one-time-bypass>"     # optional; skip for tiny releases

git fetch --tags && git checkout "$RELEASE_TAG"   # or rsync the release folder
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force                        # never --seed in production
php artisan optimize:clear && php artisan optimize
php artisan queue:restart                          # workers pick up the new code
php artisan up
```

Rollback plan (keep it short and rehearsed):

1. `git checkout <previous-tag>` (the previous release folder stays on disk).
2. `composer install --no-dev --optimize-autoloader` if dependencies changed.
3. `php artisan migrate:rollback --step=<n> --force` **only** for migrations that
   are safe to reverse; the schema changes in this project are additive, so
   rolling the code back without rolling the schema back is the default choice.
4. `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`.
5. Announce in the status channel; re-run the smoke test in section 7.

---

## 4. Background services

Supervisor for the queue worker (`/etc/supervisor/conf.d/studylikepro-worker.conf`):

```ini
[program:studylikepro-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/studylikepro/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=120
autostart=true
autorestart=true
stopwaitsecs=3600
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/studylikepro/storage/logs/worker.log
stopasgroup=true
killasgroup=true
```

Worker rules:

- Notifications and jobs are idempotent; `--tries=3` is enough with the backoff
  already configured on the meeting job.
- After every deploy: `php artisan queue:restart`.
- If a job fails repeatedly, `php artisan queue:failed` then
  `php artisan queue:retry all` once the cause is fixed.

Scheduler (one line in `crontab -e` for the deploy user):

```cron
* * * * * cd /var/www/studylikepro && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled work that must be running:

| Command | Cadence | Purpose |
| --- | --- | --- |
| `studylikepro:expire-holds` | every minute | Releases unpaid slots |
| `studylikepro:send-lesson-reminders` | every 5 minutes | Day-ahead and final reminders |
| `studylikepro:expire-requests` | every 10 minutes | Closes stale tutoring requests |
| `studylikepro:complete-lessons` | every 10 minutes | Closes lessons nobody ended |
| `payments:reconcile` | every 15 minutes | Re-checks open orders with the gateway |
| `studylikepro:send-daily-digest` | daily 07:00 | Per-user digest email |

---

## 5. Backups and restore drill

Nightly database dump plus uploads, kept for 30 days (3 months on cold storage):

```cron
30 2 * * * mysqldump --single-transaction --default-character-set=utf8mb4 -u studylikepro -p"$DB_PASSWORD" studylikepro | gzip > /var/backups/studylikepro/db-$(date +\%F).sql.gz
45 2 * * * aws s3 sync /var/www/studylikepro/storage/app/public s3://studylikepro-backups/uploads/$(date +\%F) --delete
```

Restore drill (run quarterly, on a scratch database):

```bash
gunzip -c db-2026-10-04.sql.gz | mysql -u studylikepro -p studylikepro_restore
php artisan migrate --force --pretend      # expect: nothing to migrate
```

Then point a staging `.env` at `studylikepro_restore`, run `php artisan optimize:clear`
and walk the smoke test. Record the date and the outcome in the log below.

| Drill date | Backup used | Result | Notes |
| --- | --- | --- | --- |
| _(first drill pending)_ | | | |

---

## 6. Logs and monitoring

- **Application log**: `storage/logs/laravel.log`, daily rotation with 14 days
  kept (`LOG_CHANNEL=daily`, `LOG_LEVEL=warning` in production). Ship it to your
  log service or read it with `tail -f`.
- **Worker log**: `storage/logs/worker.log` from Supervisor.
- **Web server**: nginx access/error logs, rotated by `logrotate` (default rules).
- **Uptime**: monitor `GET /up` (Laravel health endpoint, no auth, returns 200)
  from an external checker every minute. Alert on two consecutive failures.
- **Errors**: point `LOG_CHANNEL` at an error tracker (Sentry, Flare, Bugsnag) by
  adding the channel to `config/logging.php`; the app already writes exceptions,
  failed jobs and rejected webhooks (`Log::warning` on a bad signature).
- **Payments**: watch `payments:reconcile` output and the admin console's
  *Platform health* tile (stuck payments + failed classrooms). Anything other
  than *All clear* means a provider is unhappy.
- **Queues**: alert when `queue:failed` count rises, or when the oldest pending
  job is older than 10 minutes (`queue:monitor redis:default --max=100`).

Useful one-liners:

```bash
php artisan queue:monitor default --max=100
php artisan queue:failed
php artisan studylikepro:send-daily-digest --dry-run    # when supported
tail -n 200 storage/logs/laravel.log | grep -i error
```

---

## 7. Provider dashboards & post-deploy smoke test

Configure these once, then re-check after any URL change:

- **Razorpay** → Settings → Webhooks: `https://<domain>/webhooks/payments/razorpay`
  with the events `payment.captured`, `payment.failed`, and the signing secret
  copied into `RAZORPAY_WEBHOOK_SECRET`. Copy the live keys into `RAZORPAY_KEY`
  and `RAZORPAY_SECRET`, and keep a test-mode key pair for staging.
  **Razorpay settles in INR only** — the marketplace trades in LKR, so before
  taking real payments in Sri Lanka implement a `PaymentGateway` for a provider
  that handles LKR (PayHere, WebXPay, Stripe) and select it with
  `PAYMENTS_GATEWAY`. Until then the app throws `UnsupportedCurrencyException`
  instead of sending an order the provider would reject.
- **Daily.co** → API key with room-creation rights into `DAILY_API_KEY`. Rooms are
  private and tokens expire shortly after the lesson, so no domain allow-list is
  needed; the join links only ever go to the two participants.
- **OpenAI** → key into `OPENAI_API_KEY`, spend limit set, and the model pinned in
  `OPENAI_MODEL`.
- **Mail** → SPF, DKIM and DMARC records for the sending domain, and a verified
  sender. Send one digest to a real inbox and check it is not in spam.

Smoke test after every deploy (five minutes, in order):

1. Log in as the admin from `ADMIN_EMAIL`; the dashboard loads with real numbers.
2. `/privacy`, `/terms`, `/refund-policy` and `/contact` render, and the contact
   form stores a message (check the admin bell).
3. As a student: open a lesson, a receipt and the lesson chat.
4. As a teacher: open the schedule, earnings and reviews.
5. Admin: open Reports, export one CSV, open the audit log.
6. `curl -sI https://<domain>/up` → `200`, and the response carries
   `content-security-policy`, `x-frame-options`, `x-content-type-options` and
   (over HTTPS) `strict-transport-security`.

---

## 8. Day-two operations

| Task | How |
| --- | --- |
| Issue a refund | Admin → Payments → the transaction → *Issue a refund*, or resolve the dispute on the dispute desk (both are audited). |
| Investigate a dispute | Admin → Disputes → the case; the lesson, payment, chat and both parties are on one page. |
| Suspend an account | Admin → Users → the person → *Suspend account* with a reason. The session ends on their next request. |
| Re-verify a teacher | Admin → Users → the teacher → *Send back for verification*. |
| Change commission or the refund policy | Admin → Settings (audited; the refund policy page reads the same numbers). |
| Rotate a provider key | Update `.env`, then `php artisan optimize:clear && php artisan optimize && php artisan queue:restart`. |
| Reset an admin password | `php artisan tinker` → `User::where('email', '…')->first()->update(['password' => Hash::make('…')]);` |

## 9. Known limits before launch

- **Currency vs gateway:** the marketplace trades in LKR and displays `RS:`, but
  no configured provider settles in LKR yet. Razorpay (the built-in live
  integration) is INR-only and refuses anything else, so a Sri Lankan gateway
  (PayHere, WebXPay, Stripe) has to implement `PaymentGateway` before real money
  moves. The offline gateway covers local and test flows.
- Real Razorpay and Daily.co credentials have not been exercised end to end yet;
  both are covered by the offline gateways and by HTTP fakes in the suite, so the
  first live-mode payment and the first live classroom must be watched manually.
- Single-region deployment: no CDN in front of assets yet, and uploads live on the
  app server's disk unless object storage is configured.
- Email deliverability depends on DNS records being in place before the first
  campaign; transactional mail only needs SPF + DKIM.
