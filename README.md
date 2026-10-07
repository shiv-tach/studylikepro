# Studylikepro

Tutoring marketplace MVP — a student uploads a question, AI identifies the lesson, the platform matches a verified teacher, the student books and pays for a live 1-on-1 lesson, and both sides rate and track the outcome.

Built with Laravel 12, Blade, Alpine.js, Tailwind CSS, Vite, and Pest.

## MVP scope

| Role | Capabilities |
| --- | --- |
| Student | Registration, profile with learner grade, subject/lesson selection for that grade, tutoring requests with question uploads, teacher discovery, booking, payment, live classes, chat, ratings, lesson history |
| Teacher | Invite-only registration, verification, subjects with grades & lessons, availability, pricing, requests, accept/reject, live lessons, earnings, reviews |
| Admin | Teacher verification, student/booking/payment management, disputes, refunds, commission, reports, curriculum management (levels, grades, subjects, lessons) |

Core flow: 📷 Upload question → 🤖 AI identifies the lesson → 👨‍🏫 Find matching verified teacher → 🕐 Choose available time → 💳 Pay → 🎥 Live lesson → ⭐ Review

## Getting started

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run dev
```

Local demo accounts (created by the demo seeder in the `local` environment):

| Role | Email | Password |
| --- | --- | --- |
| Admin | admin@studylikepro.test | password |
| Teacher | teacher@studylikepro.test | password |
| Student | student@studylikepro.test | password |

## Development

- `composer dev` — run the app server, queue worker, log viewer, and Vite together.
- `php artisan test` — run the Pest suite.
- `vendor/bin/pint` — format the codebase.
- Vite binds to `127.0.0.1` (`npm run dev`), which is what the Content-Security-Policy allows in local development; if you move it to another host, add that origin to the policy in `SecurityHeaders`.

### Curriculum & grades

The catalog follows the Sri Lankan structure: **Primary** (Grades 1–5), **O/L** (Grades 6–11), **A/L** (Grades 12–13) and **Other**. A subject belongs to one level and has a lesson list **per grade** (e.g. O/L Mathematics: 12 lessons in Grade 6, 10 in Grade 7), curated by admins on the grade-tab matrix in `/admin/curriculum` → subject editor. Choosing a subject as a teacher auto-assigns its grades and lessons, and students only ever see the subjects of their level and the lessons of their grade — in interests, the request form, the AI suggestion (the classifier prompt is scoped to the student's grade) and the booking page. New rows come from `CatalogSeeder`, which ships placeholder lesson names until the real syllabus names are entered in the matrix.

### AI lesson matching

Requests are classified on the queue (`ClassifyTutoringRequestJob`), so a worker must be running — `composer dev` starts one, or use `php artisan queue:work --stop-when-empty` for a one-off drain. Set `OPENAI_API_KEY` to enable classification; with no key (or on any API failure) the job marks the request `failed` and the student picks the subject/lesson manually, so the flow never blocks.

### Bookings

A reserved slot is a `pending_payment` hold with a **30-minute** TTL, and the checkout flow runs through a payment gateway contract. `PAYMENTS_GATEWAY=fake` (the default) keeps everything offline: the checkout page offers demo "pay" and "declined card" buttons that post a **signed provider webhook** to the real endpoint, so signature verification and idempotency are exercised locally. Set `PAYMENTS_GATEWAY=razorpay` (plus `RAZORPAY_KEY`, `RAZORPAY_SECRET`, `RAZORPAY_WEBHOOK_SECRET`) to use the live API and the hosted Razorpay widget — note that **Razorpay settles in INR only**, so an LKR marketplace needs a provider that handles Sri Lankan rupees (the gateway throws `UnsupportedCurrencyException` rather than sending an order Razorpay would reject).

Money rules live in the admin **Settings** page (`platform_settings` table, seeded from config): commission percentage, hold TTL, the free-cancellation window, refund percentages and the policy copy. Amounts are held in minor units and rendered in exactly one place — `PlatformSettings::formatMinor()` — so every page, email and export reads **`RS: 1,250.00`**: the marketplace trades in **Sri Lankan rupees (LKR)**. Change `PLATFORM_CURRENCY` / `PLATFORM_CURRENCY_SYMBOL`, or the admin *Currency code* setting, and the whole UI follows (an unknown code prints its ISO code instead of the symbol). Cancelling a paid lesson refunds it automatically — a teacher cancellation is always refunded in full, a student cancelling outside the window gets a full refund, and admins can override any percentage from the Payments console. Every teacher payout gets one ledger row (`teacher_earnings`) that goes pending → available when the lesson is delivered → paid when it is batched into a `payouts` transfer.

Scheduled jobs that keep the schedule and the money honest:

| Command | Cadence | What it does |
| --- | --- | --- |
| `studylikepro:expire-requests` | 10 min | Expires stale tutoring requests and their pending proposals |
| `studylikepro:expire-holds` | 1 min | Releases unpaid holds and notifies the student |
| `studylikepro:complete-lessons` | 10 min | Auto-completes lessons past their end time + grace period |
| `studylikepro:send-lesson-reminders` | 5 min | Reminds both parties a day ahead and again an hour before a confirmed lesson |
| `studylikepro:send-daily-digest` | daily 07:00 | Emails each user their day: lessons on the timetable plus unread chat and notifications |
| `payments:reconcile` | 15 min | Re-checks open orders with the gateway and applies the answer |

Double-booking protection is enforced inside a transaction that locks the teacher row (`lockForUpdate`) and re-checks the slot before inserting the hold, so two students racing for the same slot can never both win — and holds, confirmed lessons and in-progress lessons all block the slot they occupy. Provider callbacks are signature-verified and stored once per event id (`payment_webhook_events`), so a repeated delivery is acknowledged but applied a single time.

### Live classrooms

Confirming a booking queues `CreateBookingMeeting`, which opens a room with a video provider and stores both links on the booking: the teacher gets an owner link (`host_meeting_url`), the student gets a participant link (`meeting_url`). `MEETING_PROVIDER=fake` (the default) keeps this offline with realistic demo links; set `MEETING_PROVIDER=daily` plus `DAILY_API_KEY` to provision real private Daily.co rooms whose tokens expire shortly after the lesson.

Both sides join from `/classroom/{booking}` — linked from the booking pages, the lesson list ("Join now") and the reminder email. Joins open **15 minutes before** the start time and close **30 minutes after** the scheduled end, with a live countdown before the window and a closed notice afterwards. The page is participant-only: an unrelated student, another teacher or even an admin gets a 403, and each party only ever receives their own link. The first visit inside the window stamps `meeting_started_at`, which is the evidence a no-show dispute turns on.

Failures never block a payment: the job records the reason on the booking (`meeting_status`, `meeting_error`), re-queues itself with a backoff until `MEETING_PROVISION_ATTEMPTS` is used up, and gives up in the `failed` state. The admin Bookings console shows the classroom state and a **Regenerate link** action, and the teacher has the same button on the classroom page. A room that was never created is self-healed when either party opens the classroom close to the lesson. Delivering a lesson closes the room, releases the earning and notifies both parties.

### Lesson chat

Every confirmed lesson opens one chat thread (`conversations` + `messages`) for the student and the teacher — linked from the sidebar ("Messages"), the booking pages and the lesson list. The inbox shows one card per lesson with the counterpart, the lesson time, the last message and an unread badge; the thread polls `GET /messages/{conversation}/poll?after={id}` every five seconds and appends anything new (deduplicated by message id), so both sides see replies without reloading. Messages are **immutable** — there is no edit or delete — and photos can be attached (5 MB images on the public disk).

Access is participant-only: a different student, another teacher or an admin gets a 403, checked by `ConversationPolicy`. Unread counts are tracked per side with a read marker per thread, so the sidebar badge and the reminder emails always point at something real.

Lifecycle events are mirrored into the thread as system messages: payment received, lesson cancelled (with the reason), lesson delivered (by the teacher or by the auto-completion sweep) and refunds issued. **"Report a problem"** in the thread header opens a `disputes` row (reason + details) and emails/in-apps every admin — a second open report on the same lesson is refused so support is not spammed. Admins investigate and close those cases in the [dispute desk](#admin-console).

### Notifications

In-app notification centre at `/notifications`, reachable from the header bell (unread badge, latest five, *mark all read*). Every notification stores a title, a body and a deep link; opening one marks it read and forwards you to the lesson, receipt or earnings page it refers to.

Delivery runs through `App\Notifications\Notification`, which gives every notification the **database channel always** and the **mail channel only while the user has email switched on** — the global toggle lives in *Settings → Notifications* (`notification_preferences.email`, on by default). The catalogue covers requests published/accepted/declined, bookings created/confirmed/cancelled/expired, day-ahead and final reminders, payment receipts (in the confirmation mail) and failures, lesson completion with a review prompt, refunds, verification results, payout paid, disputes raised and reported reviews; verification mails stay email-only, and everything else is queued (`composer dev` runs a worker).

### Reviews & ratings

A delivered lesson can be reviewed by the student who booked it — one review per booking, 1–5 stars plus an optional comment, editable for `REVIEW_EDIT_WINDOW_DAYS` (7) days and then locked. The form lives at `/student/lessons/{booking}/review` (linked from the lesson page and flagged with a *Review this lesson* chip in the history list), and the published review shows on the lesson page with an edit link while the window is open.

The teacher's public numbers — `rating_avg`, `rating_count` and `lessons_completed_count` on `teacher_profiles` — are recomputed from the source rows by [TeacherStatsService](app/Services/TeacherStatsService.php) whenever a lesson is delivered or a review changes (`ReviewChanged`), so teacher search sorting, the profile chips and the directory cards all read one consistent value. Hidden reviews leave both the profile and the average without being deleted.

Teachers see everything students said on **Reviews** (`/teacher/reviews`): average, star breakdown, lessons taught and every comment, with a **Report this review** action that flags it and notifies the admins (`ReviewFlagged`) for moderation. Hidden reviews are excluded from the public profile and the rating maths; admins action the reports in the [moderation queue](#admin-console).

Lesson history filters round out the trust loop: *My lessons* filters by status, subject, teacher and date range, and each card links straight to the lesson, its receipt and its review.

### Admin console

The console runs the marketplace without touching the database: `/admin` with **Reports**, **Users**, **Invite teachers**, **Disputes**, **Moderation**, **Verification**, **Subjects**, **Bookings**, **Payments**, **Payouts**, **Activity log** and **Settings**.

- **People** (`/admin/users`) — searchable list of students and teachers, each opening on a detail page with their lessons, payments, reviews, disputes and notification history. Suspend (with a reason), reactivate, send an approved teacher back through verification, or resend the last notification. A suspension blocks the next sign-in *and* kills the live session — `EnsureUserIsNotSuspended` logs the user out on their next request.
- **Invite teachers** (`/admin/invites`) — teacher accounts are created only through a single-use onboarding link issued here. Creating an invite shows the link once (`/register?invite=…`, hashed at rest, expiring after `TEACHER_INVITE_EXPIRY_DAYS` days) and unused invites can be revoked; public registration always creates a student account — the teacher role is granted only through a valid invite link — and the separate teacher verification flow still gates who may teach.
- **Bookings** (`/admin/bookings`) — filters for status, teacher, student, learner name and date range; the detail page shows the parties, the timeline, the money split, the classroom state (with **Regenerate link** when provisioning failed), the review, any disputes and a read-only copy of the lesson chat. Support can **Cancel** a live lesson with a reason or **Force complete** one that was delivered but never closed.
- **Payments** (`/admin/payments`) — filters for status, gateway, student/reference and date range, one detail page per transaction (references, captured/failed timestamps, gateway payload, refund history) and a refund form with 25/50/75/100% presets. Everything is exportable as CSV.
- **Disputes** (`/admin/disputes`) — the desk for reports raised from the lesson chat. Each case opens with the report itself, the reporter and the reported party, the lesson, its payment and the conversation as evidence. Pick it up (*in review*) or close it with one of five resolutions — full refund, partial refund, dismiss, warn, or suspend the reported account — and both parties are notified (`DisputeResolved`) with the decision recorded on the dispute and in the audit log.
- **Moderation** (`/admin/moderation`) — reported reviews next to the verification queue. **Hide review** takes it off the profile and recalculates the teacher's rating immediately; **Keep review** dismisses the report and republishes it.
- **Reports** (`/admin/reports`) — date-range KPIs (collected, refunded, net, commission, payments, lessons booked/completed, new and active users, payouts, payouts due), a daily collections bar chart, a lessons-by-status breakdown, teacher performance ranked by gross, and CSV exports of bookings, payments, refunds, payouts and teacher performance.
- **Audit log** (`/admin/activity`) — every state-changing console request with the admin, the action, a human sentence, the subject and the sanitised input (passwords, tokens, gateway payloads and signatures are never stored). Filter by admin, action, description or date range.
- **Settings** — commission percentage, currency, hold TTL, cancellation window, refund percentages, policy copy and the AI confidence threshold (`ai_min_confidence`, float 0–1) that decides when a classification is trusted; each save records what changed in the audit log.

### Security & hardening

- **Browser headers** (`SecurityHeaders`) on every response: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, a `Permissions-Policy` that only delegates camera/microphone to the video provider, HSTS over HTTPS, and a Content-Security-Policy that names our own origin, Google Fonts and the Razorpay checkout — everything else is blocked. `SECURITY_CSP_ENABLED=false` switches the policy off if a provider ever needs it.
- **Rate limits** (`config/studylikepro.php → throttle`, all per account or per IP): login, registration, request submissions, uploads, chat messages, checkout attempts, the contact form and provider webhooks. Uploads are additionally bounded by count, size and pixel dimensions, and only raster formats are accepted.
- **Private files stay private.** Question photos and teacher verification documents live on the `local` disk and are streamed through policy-checked routes — never a public URL. Avatars and chat photos (which the counterparty is meant to see) are the only public uploads.
- **Money and accounts**: provider webhooks are HMAC-verified with a constant-time comparison and stored once per event id; refunds clamp to what is actually refundable; suspension blocks the next sign-in *and* ends an open session.
- **Audit trail**: every state-changing admin action is recorded with who, what, when and the sanitised input (passwords, tokens, gateway payloads and signatures are never stored).
- **Secrets**: a test fails the build if a key-shaped string (`sk-…`, `rzp_live_…`, `AKIA…`, private keys) lands in the repository, and `.env` stays out of version control.

Tests that keep it honest: `tests/Feature/Security/` (headers, rate limits, secret scan, policy coverage) and `tests/Feature/Performance/EagerLoadingTest.php`, which renders 40+ pages with lazy loading switched off so an N+1 fails the suite.

### Legal, support & email

`/privacy`, `/terms` and `/refund-policy` are public pages, and the refund page prints the live cancellation window, refund percentages and commission from the admin settings — the policy can never drift from what the platform actually does. `/contact` stores the message (so support can follow up even if an email is missed), notifies the admins and is spam-guarded (honeypot + rate limit). Teachers must accept the terms before submitting for verification, and the acceptance is recorded with a policy version. Transactional email is branded through the markdown mail theme in `resources/views/vendor/mail`.

## Roadmap

The phased build plan lives in [DOC/implementation-plan.md](DOC/implementation-plan.md). Current status: **Phase 11 — Hardening, UAT & Launch** and **Phase 12 — Sri Lankan Curriculum, Grades & Lessons** complete (see the companion plan [DOC/curriculum-and-grade-plan.md](DOC/curriculum-and-grade-plan.md)). See [DOC/deployment.md](DOC/deployment.md) for the deployment runbook (environments, queue worker, scheduler, backups, monitoring, rollback) and [DOC/uat-checklist.md](DOC/uat-checklist.md) for the acceptance run.
