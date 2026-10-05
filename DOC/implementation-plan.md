# Studylikepro — MVP Implementation Plan

**Companion to:** [overview.md](./overview.md)
**Design rules:** [DESIGN_SYSTEM_AND_THEMING_GUIDE.md](../DESIGN_SYSTEM_AND_THEMING_GUIDE.md)
**Progress:** Phase 0 (foundation, roles, rebrand) ✅ · Phase 1 (profiles & onboarding) ✅ · Phase 2 (catalog, teacher subjects, admin verification) ✅ · Phase 3 (teacher discovery, availability, pricing) ✅ · Phase 4 (tutoring requests & AI matching) ✅ · Phase 5 (booking & scheduling engine) ✅ · Phase 6 (payments, commission, refunds & earnings) ✅ · Phase 7 (live classes) ✅ · Phase 8 (chat & notifications) ✅ · Phase 9 (reviews, ratings & lesson history) ✅ · Phase 10 (admin console: management, disputes, reports & settings) ✅ · Phase 11 (hardening, UAT & launch) ✅. Launch artefacts: [deployment.md](./deployment.md) runbook and [uat-checklist.md](./uat-checklist.md).

**Goal in one sentence:** A two-sided tutoring marketplace where a student uploads a question, AI identifies the topic, the platform matches a verified teacher, the student books and pays for a live 1-on-1 lesson, and both sides rate and track the outcome — with an admin console running verification, payments, disputes, and commission.

**Estimated duration:** ~13 weeks for one full-time developer. Phases are ordered by dependency, not calendar; compress or rebalance as team size changes. Every phase ends with the previous phase's demo path still working and the full Pest suite green.

---

## 1. Product Goal

Core journey (from overview):

> Upload question → AI identifies topic → find matching verified teacher → choose available time → pay → live lesson → review

V1 roles: **Student** (a parent can register as a student account and book "for" a learner), **Teacher**, **Admin**.
V1 assumptions to confirm (see Section 5): single market, single currency, English UI.

---

## 2. Where We Are Today

| Area | Status | Notes |
| --- | --- | --- |
| Framework | Done | Laravel 12, PHP 8.2, SQLite locally, Vite, Tailwind 3, Alpine, Pest |
| Auth | Done | Breeze: register, login, verify email, password reset |
| Profile & Settings | Done | Profile CRUD, settings index, theme editor |
| Theme system | Done | 6 presets, 7 accents, light/dark/system, anti-flash script, patterns. Must be reused, not rebuilt |
| Roles | Missing | No student/teacher/admin concept exists |
| Domain | Missing | No subjects, teachers, bookings, payments, chat, reviews |
| Dashboard | Placeholder | POS-themed demo content; needs replacement per role |
| Docs | Partial | `overview.md` (scope), design guide references stale `D:/POS` paths; `.agents/AGENTS.md` also stale |
| Tests | Green | 25 Pest tests passing — keep this standard for every phase |

---

## 3. Scope Traceability (every V1 bullet → phase)

### Student

| Feature | Delivered in |
| --- | --- |
| Registration | P0 (roles) + P1 (onboarding) |
| Parent/student profile | P1 |
| Subject selection | P2 |
| Topic selection | P2 (catalog) + P4 (AI suggestion, manual override) |
| Create tutoring request | P4 |
| Browse teachers | P3 |
| Teacher profile | P3 |
| Booking | P5 |
| Payment | P6 |
| Live class | P7 |
| Chat | P8 |
| Rating | P9 |
| Lesson history | P5 (statuses) + P9 (history views) |

### Teacher

| Feature | Delivered in |
| --- | --- |
| Registration | P0 + P1 |
| Verification | P1 (submit docs) + P2 (admin queue, approval gate) |
| Subjects | P2 |
| Topics | P2 |
| Availability | P3 |
| Pricing | P3 |
| Requests | P4 |
| Accept/reject | P4 (proposal) + P5 (booking states) |
| Live lesson | P7 |
| Earnings | P6 |
| Reviews | P9 |

### Admin

| Feature | Delivered in |
| --- | --- |
| Teacher verification | P2 |
| Student management | P10 |
| Booking management | P10 |
| Payment management | P10 |
| Disputes | P10 (entry point from P8 chat/booking) |
| Refunds | P6 (engine) + P10 (admin UI) |
| Commission | P6 (engine) + P10 (config + reports) |
| Reports | P10 |
| Subject/topic management | P2 |

---

## 4. Architecture & Conventions

### 4.1 Stack (locked)

- Laravel 12 + Blade + Alpine + Tailwind + Vite (existing). No SPA rewrite.
- Pest for tests; run `php artisan test` + `vendor/bin/pint` every phase.
- Queue + scheduler via database driver in dev; Redis optional in production.
- SQLite in dev; MySQL 8 or Postgres 16 in production (lock in P11).
- Money: store integer minor units (`*_minor`), format at the edge (`LKR`, rendered as `RS: 1,250.00` by `PlatformSettings::formatMinor`).
- Time: store UTC; convert for display using the user's timezone; schedule teacher availability in the teacher's timezone.

### 4.2 Roles

- Package: `spatie/laravel-permission`.
- Roles: `admin`, `teacher`, `student`. (A "parent" is a student-role account with guardian fields and booking-for-learner fields — see Decision 4.)
- Route groups: `/student/*`, `/teacher/*`, `/admin/*` behind `role:` middleware; shared profile/settings remain at root.
- Admin user is created by seeder only; no public admin registration.

### 4.3 Domain model (target)

| Entity | Key fields / relations |
| --- | --- |
| `users` | existing + `status` (active/suspended), role via spatie |
| `student_profiles` | user_id, grade level, timezone, learning goals, guardian name/phone |
| `teacher_profiles` | user_id, headline, bio, experience, education, languages, timezone, base hourly rate, verification_status, rating_avg/rating_count, lessons_completed_count |
| `teacher_verification_documents` | teacher_profile_id, type, private file path |
| `subjects` / `topics` | catalog with active flag + sort order (topic belongs to subject) |
| `teacher_subjects` / `teacher_topics` | which subjects/topics a teacher teaches (+ optional grade levels, rate override) |
| `student_subject_interests` / `student_topic_interests` | student's selection |
| `teacher_availability_slots` | weekly recurring ranges (day of week, start, end) |
| `teacher_time_off` | date ranges excluded from availability |
| `tutoring_requests` + `request_attachments` + `request_responses` | student question, AI classification, teacher accept/reject |
| `bookings` | student, teacher, subject/topic, UTC start/end, status, price/commission snapshot, learner name/grade, meeting fields, cancellation fields |
| `payments` / `refunds` | gateway ids, amounts, statuses, idempotency keys |
| `teacher_earnings` + `payouts` | ledger entries per completed booking; manual payout batches |
| `conversations` + `messages` | one thread per booking; text/image/system messages |
| `reviews` | one per completed booking, rating 1–5, comment, flag/hide |
| `disputes` | opened by party or admin from a booking; resolution + refund link |
| `platform_settings` | commission %, currency, cancellation window, hold TTL, AI threshold, policy text |
| `activity_log` | admin action audit trail |

### 4.4 Booking lifecycle (single source of truth)

```
pending_payment ──pay──▶ confirmed ──teacher joins──▶ in_progress ──complete──▶ completed
      │                       │                            │
      ├─ TTL expiry ─▶ expired│                            └─ no-show ─▶ no_show (admin decides)
      └─ cancel ─────▶ cancelled ◀── cancel (either party)
completed/cancelled ── dispute ─▶ disputed ─▶ resolved
```

Rules:
- `pending_payment`: slot held for a configurable TTL (default 30 min), then released by scheduled job.
- Slot conflict prevention: DB transaction + index and uniqueness check over active statuses; covered by a concurrency test.
- Only `completed` bookings produce review rights and payout-eligible earnings.

### 4.5 Money & commission rules

- On payment capture: `platform_fee = round(price × commission%)`, `teacher_payout = price − platform_fee`; commission % is snapshotted onto the booking.
- Default commission: 15% (editable in admin, P10).
- Refund defaults: teacher cancellation → 100%; student cancellation ≥ 24 h before start → 100%; inside window → no automatic refund (admin can override via dispute).
- Teacher payouts are manual in V1: admin marks a batch as paid (P10); the ledger is automated.

### 4.6 External integrations (all behind contracts)

| Concern | Contract | Recommended provider | Alternatives |
| --- | --- | --- | --- |
| Payments | `PaymentGateway` (order, webhook verify, refund) | Razorpay (UPI/cards, easy refunds — **INR only**) | Stripe, PayHere (LKR) |
| Video | `MeetingProvider` (create room, join URLs, status) | Daily.co (fast embed, per-room privacy) | Zoom Server-to-Server API, Jitsi Meet |
| AI classification | `TopicClassifier` (images + text → subject/topic + confidence) | OpenAI `gpt-4o-mini` (vision, cheap) | Google Gemini Flash, Anthropic Claude |
| Chat realtime | polling in V1 | 5-second polling with Alpine | Laravel Reverb (Post-V1) |
| Email | Laravel mail | SMTP provider (Postmark/Resend/SES) in prod, log locally | — |
| File storage | Laravel filesystem | S3-compatible (R2/S3) in prod, local in dev | — |

Every provider is a thin adapter so a swap touches one class + env vars. Spikes to create accounts/keys happen in P0, not the phase where they are needed.

### 4.7 Cross-cutting conventions

- **UI/theme:** reuse the existing theme system. No hardcoded accent colors — always `bg-primary`, `text-primary`, `border-primary`, translucent borders, `rounded-2xl` cards, glass/ocean variants. Build role layouts on the existing app layout pattern.
- **Authorization:** every user-input route gets a Form Request + Policy; feature tests assert 403s.
- **Queues:** emails, AI classification, meeting creation, reminders are queued jobs (never inline).
- **Notifications:** central `NotificationService`; database + mail channels; built incrementally from P4, finalized in P8.
- **Demo data:** `php artisan migrate:fresh --seed` must always produce a usable demo (admin, verified + pending teachers, student, sample bookings). Extend seeders each phase.

---

## 5. Decisions to Confirm

Each has a locked recommendation so implementation can proceed; changing a recommendation only affects the listed phase.

| # | Decision | Recommendation | Alternative | Needed by |
| --- | --- | --- | --- | --- |
| 1 | Payment gateway | Razorpay (test mode first) — **INR only, so an LKR-settling provider (PayHere, WebXPay, Stripe) must be added before going live in Sri Lanka** | Stripe, Paddle | P6 (accounts in P0) |
| 2 | Video provider | Daily.co embedded rooms | Zoom, Jitsi | P7 (accounts in P0) |
| 3 | AI provider | OpenAI gpt-4o-mini vision | Gemini Flash, Claude | P4 (key in P0) |
| 4 | Parent model | One account; booking carries learner name/grade | Full multi-child parent dashboards | P1 |
| 5 | Market & currency | Sri Lanka, LKR (displayed as `RS:`) | Other | P0 |
| 6 | Direct booking vs request | Direct booking is instant once paid; requests are the teacher-choice path | Teacher must approve every booking | P5 |
| 7 | Chat realtime | Polling in V1 | Reverb from day one | P8 |
| 8 | Lesson durations | 30 / 45 / 60 minutes | Custom | P3 |
| 9 | Cancellation policy | 24 h full refund; disputes overridden by admin | Custom | P5/P6 |
| 10 | Request acceptance | Accept creates a 30-min pending-payment hold; first paid wins, others expire | Interest-only, student picks later | P4/P5 |

---

## 6. Roadmap at a Glance

| Milestone | Phases | Outcome |
| --- | --- | --- |
| M1 — Accounts | P0–P1 | Roles, rebrand, profiles, onboarding |
| M2 — Supply | P2–P3 | Catalog, verified teachers, search, availability, pricing |
| M3 — Demand & money | P4–P6 | Requests + AI matching, booking engine, payments/refunds/earnings |
| M4 — Delivery | P7–P8 | Live classes, chat, notifications |
| M5 — Trust & operations | P9–P10 | Reviews, history, full admin console |
| M6 — Launch | P11 | Hardening, UAT, production launch |

| Phase | Theme | Est. |
| --- | --- | --- |
| P0 | Foundation, roles, rebrand, de-risk spikes | 1 week |
| P1 | Profiles & onboarding | 1 week |
| P2 | Catalog, teacher subjects, admin base + verification | 1 week |
| P3 | Discovery: search, teacher profile, availability, pricing | 1 week |
| P4 | Tutoring requests + AI question matching | 1 week |
| P5 | Booking & scheduling engine | 1 week |
| P6 | Payments, commission, refunds, earnings | 1 week |
| P7 | Live classes | 1 week |
| P8 | Chat & notifications | 1 week |
| P9 | Reviews, ratings, lesson history | 1 week |
| P10 | Admin console: management, disputes, reports | 2 weeks |
| P11 | Hardening, UAT, launch | 1 week |

---

## 7. Phase Details

### Phase 0 — Foundation, Roles & Rebrand (Week 1)

**Goal:** Every user has a role, every role has a home, the app is branded Studylikepro, and external integrations are de-risked.

**Build**
- Install `spatie/laravel-permission`; roles `admin`/`teacher`/`student`; seeders for roles + admin user (env-driven credentials).
- Registration form gains "I am a student / I am a teacher"; server-side whitelist; role assigned on register.
- Role-aware redirect after login; route groups `/student`, `/teacher`, `/admin` with `role:` middleware; three placeholder dashboards with themed sidebars (reuse existing theme system + navigation pattern).
- Replace POS placeholder content: dashboard, welcome page; set `APP_NAME=Studylikepro`.
- Clean stale docs: fix `D:/POS` references in the design guide and `.agents/AGENTS.md`; note this repo's theming rules apply to Studylikepro.
- `.env.example`: add placeholders for payment, video, AI keys.
- Non-code parallel track: create Razorpay test account, Daily.co key, OpenAI key; draft privacy/terms/refund policy copy; choose domain + SMTP provider.

**Data:** users.role assignment tables (spatie), admin seeder.

**Tests:** role assigned on registration; role-based landing; protected routes return 403 for wrong role; existing suite stays green (update the `dashboard` route expectations as needed).

**DoD:** register as teacher → teacher dashboard; `migrate:fresh --seed` gives admin/teacher/student demo accounts; tests + Pint clean.

---

### Phase 1 — Profiles & Onboarding (Week 2)

**Goal:** Students and teachers have real profiles; teachers can submit verification documents.

**Build**
- `student_profiles` (grade level, timezone, learning goals, guardian name/phone) + student onboarding form served by the profile page (grade → timezone → goals), with dashboards behind an onboarding gate.
- `teacher_profiles` (headline, bio, experience, education, languages, timezone, default rate, verification status `draft → pending → approved/rejected`) + teacher onboarding pages (professional info → photo → ID/degree document uploads).
- `teacher_verification_documents` on the **private** disk; admin-only signed temporary URLs later.
- Avatar/photo uploads (2 MB, jpg/png/webp) on public disk; delete old file on replace.
- Profile pages for both roles; verification status banner on teacher dashboard (pending/rejected + reason + resubmit).
- Teacher readiness checklist widget: profile complete → subjects → pricing → availability (drives P2/P3).

**Data:** `student_profiles`, `teacher_profiles`, `teacher_verification_documents`, avatar column.

**Tests:** profile CRUD + authorization; upload validation (size/mime); wizard completion redirects; status banner states.

**DoD:** a new teacher can complete a profile, upload docs, and see "pending review"; a student completes onboarding and lands on a personalized dashboard.

---

### Phase 2 — Catalog, Teacher Subjects & Admin Base (Week 3)

**Goal:** Subjects/topics exist, teachers tag what they teach, and an admin can verify teachers.

**Build**
- `subjects` + `topics` (slug, active, sort); seeder with a starter curriculum (Math, Physics, Chemistry, Biology, English, Computer Science — ~5 topics each) so the app is demoable and AI matching has ground truth.
- Admin shell: `/admin` layout (theme-consistent), dashboard, navigation; subject/topic CRUD with ordering + active toggle + usage counts.
- Teacher verification queue: pending list → detail with document previews (signed URLs) → approve / reject with reason → email to teacher (queued).
- Teacher teaching setup: pick subjects → pick topics within them; grade-level tags; optional per-subject rate override.
- Student subject/topic interests selection (used for AI hints and recommendations).
- Public endpoints: subjects list, topics per subject (used by filters + AI mapping).

**Data:** `subjects`, `topics`, `teacher_subjects`, `teacher_topics`, `student_subject_interests`, `student_topic_interests`.

**Tests:** admin-only CRUD; teacher attach/detach authorization; verification transitions (pending→approved/rejected, resubmission); non-admin cannot view documents; catalog seed idempotence.

**DoD:** admin verifies a teacher end-to-end; verified teacher has subjects+topics; catalog powers P3 filters.

---

### Phase 3 — Discovery: Search, Teacher Profile, Availability & Pricing (Week 4)

**Goal:** Students can find an approved teacher, see their live availability and price, and land on the booking CTA.

**Build**
- Availability: `teacher_availability_slots` (weekly ranges) + `teacher_time_off`; teacher availability manager UI (week grid, add/remove ranges, time off).
- `SlotService`: teacher tz → UTC conversion; open slots = weekly ranges − booked − time off; lesson-duration granularity; unit-tested with DST-crossing fixtures.
- Pricing: base hourly rate + optional per-subject override; revenue preview ("you earn X after 15% commission").
- Teacher listing `/teachers`: filters (subject, topic, grade, price range, language, weekday/time, min rating), sorts (rating, price, newest), pagination; only `approved` teachers appear.
- Teacher public profile: bio, subjects/topics, rate, availability preview, rating area (populated in P9), "Book lesson" + "Send request" CTAs.
- Search implementation: indexed DB queries (LIKE + composite indexes); Meilisearch noted as a Post-V1 upgrade.

**Data:** `teacher_availability_slots`, `teacher_time_off`; pricing columns finalized.

**Tests:** slot generation (overlaps, exclusions, timezones, DST); filters return eligible teachers only; non-approved teachers 404 in public routes; profile payload.

**DoD:** a student filters to a topic, opens a teacher profile, sees real slots and prices, and can start a booking or request.

**Status (shipped):** ✅ catalog-wide availability engine (`SlotService`), teacher availability manager, public directory + profiles with filters/sorts/pagination, commission-aware pricing preview. Profile CTAs are inert placeholders until requests land in P4 and booking in P5.

---

### Phase 4 — Tutoring Requests & AI Matching (Week 5)

**Goal:** The flagship flow — upload a question, AI identifies the subject/topic, matching verified teachers respond.

**Build**
- `tutoring_requests` (description, preferred windows, optional budget, status) + up to 3 image attachments (client-side downscale, 5 MB cap, jpg/png/webp).
- `ClassifyTutoringRequestJob` → `TopicClassifier` contract; `OpenAIClassifier` sends images+text and returns subject/topic + confidence + alternates; result mapped to catalog rows with fuzzy fallback.
- UX: student sees the AI suggestion and confirms/overrides it (never blocked — low confidence or API failure falls back to manual pick); raw AI response stored for audit; per-user daily rate limit + image-hash cache for cost control.
- Matching: open requests are visible to verified teachers whose topics include the classified topic and whose availability overlaps the preferred windows.
- Teacher request inbox with Accept / Reject; **Accept creates a `pending_payment` booking hold** (30 min TTL) using a preferred slot and the teacher's price; first paid wins, sibling proposals expire.
- Scheduler: expire stale requests; expire unpaid holds.
- Notifications: teachers emailed + in-app when a matching request arrives; student notified on accept/reject.

**Data:** `tutoring_requests`, `request_attachments`, `request_responses`; bookings gain nullable `tutoring_request_id`.

**Tests:** classifier fake matrix (success / low confidence / failure / invalid json); matching query correctness; accept→hold creation; TTL expiry releases slot; authorization on attachments; upload limits.

**DoD:** upload a chemistry photo → topic identified → matching teacher accepts → student sees a payable booking hold, all covered by tests.

**Status (shipped):** ✅ request/attachment/response/booking schema + status enums, `TopicClassifier` contract with the OpenAI implementation and `ClassifyTutoringRequestJob` (image-hash cache, manual-pick fallback on failure or low confidence, raw response kept for audit), `RequestMatcher` + `RequestResponseService` (accept creates the 30-minute `pending_payment` hold in a `lockForUpdate` transaction and expires sibling proposals), student request flow (photo upload, AI states, proposals, hold card), teacher request inbox with suggested slots, mail + database notifications, and the `expire-requests` / `expire-holds` scheduled commands. Client-side image downscaling is deferred (the server enforces 3 files × 5 MB, jpg/png/webp); the profile "Book lesson" CTA is still wired in P5, and first-paid-wins lands with payments in P6.

---

### Phase 5 — Booking & Scheduling Engine (Week 6)

**Goal:** A robust booking state machine both sides can trust.

**Build**
- `bookings` table (UTC times, status, price/commission snapshot, learner name/grade, cancellation fields, request FK) + status enum + transition service with guards.
- Direct booking flow from teacher profile: subject/topic → duration (30/45/60) → slot via `SlotService` → confirm page with price breakdown → `pending_payment` hold.
- Teacher-side: upcoming schedule, pending proposals from P4, accept/reject remains, cancel with reason.
- Student "My Lessons" (upcoming / past tabs) and booking detail page with a status timeline; cancel modal honoring the 24 h window; reschedule = cancel + rebook in V1.
- Double-booking protection: transaction + `lockForUpdate` + active-slot uniqueness; concurrency test simulates two simultaneous holds.
- Scheduled jobs: expire holds; auto-complete after lesson end + grace if teacher never marks it; lesson reminders queued (delivery in P8).
- Events: `BookingCreated`, `BookingConfirmed`, `BookingCancelled`, `BookingCompleted` (payment events land in P6).

**Data:** `bookings` (payment/meeting columns drafted for P6/P7).

**Tests:** transition matrix per role (invalid transitions rejected); cancel-window rules; hold expiry; concurrency; policies for student/teacher/admin; learner-for-child fields.

**DoD:** two users can complete book → hold → (simulated) confirmation → cancel, with every rule asserted.

**Status (shipped):** ✅ `bookings` lifecycle columns (confirmed/started/completed/reminder timestamps) + status machine with guarded transitions in `BookingTransitionService` (expire, confirm, start, complete, no-show, cancel) published as `BookingCreated`, `BookingConfirmed`, `BookingCancelled`, `BookingCompleted` and `BookingExpired` events; direct booking from the teacher profile (subject → topic → length → slot → learner details) through `BookingService`, which prices per lesson length, snapshots the 15% commission and **reserves inside a transaction that locks the teacher row** so two students cannot double-book; student "My lessons" (upcoming/past) and booking detail with a status timeline, cancel modal, reschedule via cancel + rebook; teacher schedule with starts-in-progress, delivered, no-show and cancel-with-reason actions; admin booking console with filters, stats and cancellation override; scheduled auto-completion (end + grace) and one-shot lesson reminders; mail + in-app notifications for booking, confirmation, cancellation and hold expiry. Cancellation rule for V1: students may release a hold any time and cancel a confirmed lesson up to 24 hours before it starts (inside that window support handles it, and the teacher can always cancel); refund percentages land with the payment engine in P6. Payments are simulated behind `PAYMENTS_SIMULATED` until P6 swaps in Razorpay, and the live classroom/chat arrive in P7/P8.

---

### Phase 6 — Payments, Commission, Refunds & Earnings (Week 7)

**Goal:** Money moves correctly: students pay, the platform keeps commission, teachers see earnings, refunds work.

**Build**
- Tables: `payments`, `refunds`, `teacher_earnings` (ledger), `payouts` (manual batches), `platform_settings` (commission %, currency, cancellation window, hold TTL, policy text).
- `PaymentGateway` contract + `RazorpayGateway`: create order, verify webhook signature, refund (full/partial); checkout page with provider widget; `/webhooks/payments/razorpay` CSRF-exempt, signature-verified, **idempotent** (event-id store).
- Flow: hold → order → paid → webhook captured → booking `confirmed` → teacher notified; failed/abandoned → hold expires and slot releases.
- Commission math with integer minor units + rounding invariant test (`fee + payout = price`); snapshot on booking; `teacher_earnings` row created `pending` → `eligible` on completion.
- Refund engine per Decision 9 (teacher cancel 100%, student ≥24 h 100%, admin override); refund webhook/status sync; earnings reversed when applicable.
- Teacher Earnings page (totals, ledger, next payout estimate); student receipt/invoice print view; `payments:reconcile` console command for stuck states.

**Data:** all money tables + settings seeder.

**Tests:** gateway contract fake; webhook signature valid/invalid/duplicate; capture→confirmed; refund matrix; commission rounding; earnings lifecycle; authorization on receipts.

**DoD:** against Razorpay test mode, a booking can be paid, confirmed, refunded, and both sides see correct money.

**Status (shipped):** ✅ money schema (`payments`, `refunds`, `teacher_earnings` ledger, `payouts`, `platform_settings`, `payment_webhook_events`); `PaymentGateway` contract with a `RazorpayGateway` (orders, payment fetch, refunds, HMAC webhook verification, hosted checkout payload) and an offline `FakePaymentGateway` selected by `PAYMENTS_GATEWAY`; checkout page (provider widget in production, signed-webhook demo panel locally), CSRF-exempt `POST /webhooks/payments/{gateway}` that verifies the signature over the exact payload and stores each event id once, plus a return-URL/browser-return path and `payments:reconcile` for stuck orders; capture → booking confirmed → earning row created `pending` → `eligible` on delivery; commission arithmetic in integer minor units with the `fee + payout = price` invariant tested across a range of prices and snapshotted onto the booking; refund engine driven by `platform_settings` (teacher cancel 100%, student outside the window 100%, admin override with 25/50/75/100 presets) that reverses the matching share of the teacher's earning; teacher Earnings page (available / pending / paid, full ledger with reversals, payout history); printable student receipt with refund lines; admin Payments console (totals, filters, refunds), Payouts console (batch available earnings, mark paid with a transfer reference) and a Settings page for commission, hold TTL, cancellation window, refund percentages and policy copy. **Razorpay test-mode verification still needs live sandbox keys** — the gateway itself is covered by HTTP-faked tests, and the full flow runs offline through the fake gateway.

---

### Phase 7 — Live Classes (Week 8)

**Goal:** Both parties join the lesson from the booking page, and completion flows into earnings.

**Build**
- `MeetingProvider` contract + `DailyMeetingProvider` (recommended): create a private room per booking, host URL for teacher, participant URL for student; adapter stubs for Zoom/Jitsi optional.
- Meeting created by queued job when a booking is confirmed; retry with backoff; failure visible to admin (P10) with a regenerate action.
- Join page visible within ±15 min of start (countdown before), closed after end + 30 min; participant-only via policy; provider iframe or new-tab join.
- Teacher actions on the booking: "Start lesson" (records `started_at`), "Complete lesson", "Report no-show".
- `completed` → earnings become eligible → both parties get review-prompt notifications (P9 link).
- No-show → booking marked `no_show` for admin review (refund decided via dispute, P10).

**Data:** meeting columns on `bookings` (provider, external id, join URLs, started/ended, recording URL reserved).

**Tests:** provider fake; join-window boundaries; completion→earnings; no-show flow; non-participant 403.

**DoD:** a paid booking shows JOIN for both users at the right time and completes into the earnings ledger.

**Status (shipped):** ✅ meeting columns on `bookings` (`meeting_status`, `meeting_external_id`, participant `meeting_url` + `host_meeting_url`, error, started/ended, reserved recording URL); `MeetingProvider` contract with a `DailyMeetingProvider` (private room per lesson, owner token for the teacher, member token for the student, room expiry after the lesson) and an offline `FakeMeetingProvider` selected by `MEETING_PROVIDER`; confirming a booking dispatches the queued `CreateBookingMeeting` job, which records the failure reason on the booking and re-queues itself with a backoff until `MEETING_PROVISION_ATTEMPTS` is spent, leaving the booking `failed` for the admin console to **regenerate** (the teacher has the same button on the classroom page, which also self-heals a room that was never created); shared participant-only classroom page at `/classroom/{booking}` (student and teacher, admins denied) with a live countdown before the window, the JOIN button while it is open (each party only ever sees their own link) and a closed notice afterwards — joins open 15 minutes before the start and close 30 minutes after the end; JOIN call-to-actions on both booking pages, the lesson list and the reminder email; first visit stamps `meeting_started_at` as evidence for no-show reviews; delivering a lesson (or cancelling it, or reporting a no-show) closes the room, releases the earning to `eligible` and sends both parties a completion notification (the student's review prompt points at the booking page until P9 adds the review form).

---

### Phase 8 — Chat & Notifications (Week 9)

**Goal:** Booking-scoped chat and a complete, queued notification system.

**Build**
- `conversations` (one per booking) + `messages` (text, image attachments, or auto system messages on booking/payment events).
- Chat UI: inbox + thread, Alpine polling every 5 s, cursor pagination, unread badges in navigation; messages immutable; "Report" action creates a dispute entry.
- Notification system finalized: database + mail channels, in-app bell + notifications page, global email toggle in settings; queued delivery.
- Catalog: new request, request accepted/rejected, booking confirmed/cancelled, 24 h + 1 h reminders, payment receipt/failed, lesson completed, review request, refund issued, verification approved/rejected, dispute updates, payout paid.
- Scheduler: reminders + digests.

**Data:** `conversations`, `messages`; notifications table (Laravel default).

**Tests:** participant-only access; polling payload shape; unread counts; system messages on lifecycle events; `Notification::fake` assertions per catalog item; preference toggle respected.

**DoD:** student and teacher can chat around a booking; every listed event produces exactly one notification through the right channels.

**Status (shipped):** ✅ booking-scoped chat (`conversations` one-per-booking with per-side read markers, `messages` typed text/image/system and immutable — no edit or delete) shared at `/messages` by the two participants through `ConversationPolicy` (an unrelated student, another teacher or an admin gets a 403); inbox with per-lesson cards, last message preview, unread badges, and a thread page that polls `?after={id}` every five seconds and appends new messages (deduplicated by id, system updates styled separately), attaches photos (5 MB images) and refuses empty messages; unread counts surface in the sidebar badge and on the inbox; lifecycle listeners mirror payment-confirmed, cancelled-with-reason, delivered (manual and automatic) and refunded events into the thread as system messages; **"Report a problem"** creates a `disputes` row (reason, details, booking/conversation links) and notifies every admin by mail and in-app, with a second open report on the same lesson refused; in-app notification centre at `/notifications` plus a header bell (latest five, unread badge capped at 9+, open-tracking links, mark-all-read, unread-count endpoint) and a `notification_preferences.email` toggle in Settings that every notification honours through the new `App\Notifications\Notification` base — database always, mail only while email is on; catalogue completed with `PaymentFailed`, `PayoutPaid`, `DisputeRaised`, the receipt line in the confirmation mail and a **day-ahead reminder window ahead of the one-hour reminder** (a lesson already inside the final window receives only the final reminder); daily digest (`studylikepro:send-daily-digest`, 07:00) emails the day's timetable plus unread chat/notifications and skips users with nothing to report or email switched off. The review prompt still rides in `LessonCompleted` until the review form lands in P9, and dispute resolution notifications follow the P10 moderation queue.

---

### Phase 9 — Reviews, Ratings & Lesson History (Week 10)

**Goal:** Trust loop closes — completed lessons produce reviews and rich history.

**Build**
- `reviews`: only for `completed` bookings, one per booking, rating 1–5 + comment, editable 7 days, flaggable; teacher aggregates (`rating_avg`, `rating_count`) updated via event listener.
- Teacher profile review list with rating breakdown; rating shown on search cards (backfill P3 views).
- Student lesson history: filters (status/subject/teacher/date), detail with invoice, join/review CTAs; teacher history + "Reviews received".
- Flagged review → admin moderation queue (surfaced in P10).

**Data:** `reviews` + cached aggregates.

**Tests:** only-completed rule; one-per-booking; edit window; aggregate correctness; flag/hide visibility; authorization.

**DoD:** a completed lesson leads to a review that updates the teacher's rating everywhere it is displayed.

**Status (shipped):** ✅ `reviews` table (one row per booking via a unique FK, rating 1–5, comment, `edited_at`, flag and hide columns) with the `ReviewPolicy` guarding every door: only the booking's own student can review a **completed** lesson, once; the author may edit for `REVIEW_EDIT_WINDOW_DAYS` (7) days, after which the form turns read-only; the teacher can flag a review once; admins can hide one. `ReviewService` owns submit/update/flag/hide, `ReviewChanged` feeds `TeacherStatsService`, which recomputes `rating_avg`, `rating_count` and `lessons_completed_count` on `teacher_profiles` from the visible reviews and completed lessons (the old fabricated demo numbers are gone) — so teacher-search sorting, the directory chips, the public profile and the new **Reviews** page (`/teacher/reviews`: average, star breakdown, lessons taught, comments, *Report this review* → `ReviewFlagged` to admins) all agree. The public profile lists the latest five visible reviews with the breakdown, and hidden reviews drop out of both the list and the average without being deleted. Reviewing is prompted on the lesson page (Leave a review → form → shows the published review with an edit link) and flagged with a *Review this lesson* chip in the history list, which now filters by status, subject, teacher and date range. The admin moderation queue itself lands with P10 — the flag data, the hide action (`ReviewService::hide`) and the `Review::scopeFlagged()` query are ready for it.

---

### Phase 10 — Admin Console: Management, Disputes, Reports & Settings (Weeks 11–12)

**Goal:** Operations team can run the marketplace without touching the database.

**Build (extends the P2 admin shell)**
1. **Students & teachers:** searchable lists, detail pages (profile, bookings, payments, reviews), suspend/reactivate (login blocked when suspended), teacher suspend/re-verify, regenerate meeting link, resend notification.
2. **Bookings:** filters (status/date/teacher/student), detail with timeline, cancel + refund action, force-complete, read-only conversation view for dispute context.
3. **Payments:** transaction list + filters, detail, full/partial refunds, refund history, CSV export.
4. **Payouts:** group eligible earnings by teacher, create payout batch, mark paid with reference + note, email receipt.
5. **Disputes:** parties open from booking/chat (reason, description, evidence); admin queue → detail (booking, payments, chat, evidence) → resolutions (refund full/partial, dismiss, warn/suspend) → notify + audit.
6. **Commission & settings:** edit commission %, currency, cancellation window, hold TTL, AI confidence threshold, policy text — with validation + audit log.
7. **Reports:** KPIs (revenue, commission, bookings by status, new users, active teachers), 30-day trends, exportable tables (bookings, payments, refunds, payouts, teacher performance), date-range filter.
8. **Moderation queues:** flagged reviews, verification resubmissions.
9. **Audit log:** every admin action recorded (`activity_log`) and filterable.

**Tests:** route→policy coverage for every admin route (403 sweep); refund/dispute resolution flows; payout batch math; settings validation + audit; CSV smoke tests.

**DoD:** a full dispute can be opened by a student, investigated, refunded, and communicated — entirely in the admin UI.

**Status (shipped):** the console now covers people (`/admin/users` list + detail, suspend/reactivate/re-verify/resend — suspension blocks sign-in *and* ends the live session), bookings (filters incl. date range, detail with timeline, money, classroom retry, review, disputes and a read-only chat copy; cancel with reason and force-complete), payments (filters, detail with refund history and gateway payload, 25–100% refunds, CSV export), disputes (queue → case file → five resolutions → `DisputeResolved` to both parties + audit), moderation (hide/dismiss reported reviews with an instant rating recalculation), reports (date-range KPIs, daily collections chart, lessons by status, teacher performance, CSV exports), the filterable audit log (`LogAdminActivity` + `ActivityLogger`, secrets redacted) and settings (`ai_min_confidence` added and read by the classifier). Phase 10 tests live in `tests/Feature/Admin/`: 403 sweep over every console route, suspension and login blocking, dispute resolutions, moderation, audit redaction and export smoke tests — 68 tests in the directory, 429 in the suite.

---

### Phase 11 — Hardening, UAT & Launch (Week 13)

**Goal:** Production-ready, verified against the overview, launched.

**Build / Verify**
- **E2E acceptance run** mapped to Section 11 checklist; fix pass; demo seed with a full realistic dataset.
- **Performance:** pagination audit, index audit (bookings teacher+starts_at+status, messages conversation+created, payments gateway ids unique, requests status+topic), eager-loading audit (`preventLazyLoading` in dev), catalog/settings caching, image sizing, Vite build budget.
- **Security:** policy coverage test, rate limits (auth, uploads, AI, messages), upload validation review, signed URLs for private files, webhook verification re-check, mass-assignment review, XSS audit, basic security headers, secret scan.
- **Compliance & content:** privacy policy, terms, refund/cancellation policy pages, teacher agreement checkbox, branded email templates, contact page.
- **Ops:** staging → production deploy (MySQL/Postgres, Redis optional, SMTP, object storage, queue worker under supervisor, scheduler cron, `storage:link`), daily backups + restore drill, log rotation, uptime + error monitoring, rollback plan, webhook URLs configured in provider dashboards.
- **UAT on staging:** register both roles → verify → search → request with photo → AI classify → accept → pay (test mode) → join → chat → complete → review → refund → dispute; then a small live-mode payment smoke test.

**DoD:** all tests green, UAT checklist 100% signed off, monitoring + backups live, launch announced.

**Status (shipped):**
- **E2E acceptance run** — `tests/Feature/Acceptance/CoreFlowTest.php` drives the whole story through real routes: register → profile → interests → question photo → AI classification → teacher application → admin approval → subjects/availability → accept → checkout → signed webhook → classroom → chat → start/complete → earnings → review, plus a refund/dispute/report walk-through. The human version is [uat-checklist.md](./uat-checklist.md); the go-live steps are in [deployment.md](./deployment.md).
- **Performance** — pagination verified on every user-facing list; the index audit added nothing new (bookings `teacher_profile_id+starts_at`, `student_id+starts_at`, `status+starts_at`; messages `conversation_id+id`; payments `gateway_order_id`/`gateway_payment_id` unique; requests `topic_id+status`, `status+expires_at`). `tests/Feature/Performance/EagerLoadingTest.php` renders 40+ pages with `preventLazyLoading()` on and mass-assignment protection enabled — zero violations, so no N+1 fixes were needed. The catalog (subjects + topics + topic counts) is cached with `CatalogService` and flushed by model events; platform settings were already cached. Uploads are bounded by dimensions as well as size; the production bundle is ~96 kB CSS + ~92 kB JS (~15 kB + ~34 kB gzipped).
- **Security** — `SecurityHeaders` middleware (nosniff, frame options, referrer policy, Permissions-Policy scoped to the video provider, HSTS over HTTPS, CSP allow-listing fonts and the payment vendor); named rate limits for login, registration, requests, uploads, chat, checkout, contact and webhooks; upload validation reviewed (raster-only, size + dimension caps, private vs public disks deliberate); webhook signatures re-checked (constant-time compare, single application per event id); mass-assignment audit found no `create($request->all())` patterns; XSS audit found no raw Blade echoes; a repository secret-scan test and a policy-coverage suite now run in CI.
- **Compliance & content** — `/privacy`, `/terms`, `/refund-policy` (figures read from platform settings), `/contact` (stored + notified + throttled), footer links everywhere, teacher agreement checkbox with versioned acceptance recorded on the profile, branded markdown mail theme.
- **Ops** — deployment runbook covering servers, env checklist, first deploy, release/rollback, Supervisor, scheduler, backups + restore drill template, logging/monitoring and the post-deploy smoke test.

---

## 8. Global Definition of Done (every phase)

- Pest tests added for all new behavior; full suite green; `vendor/bin/pint` clean.
- All user input validated via Form Requests; every route authorized via Policy/middleware; 403 paths tested.
- Queued jobs for AI, email, meeting creation; no inline external calls in requests.
- Theme system intact: no hardcoded accent colors, works across presets and dark mode, responsive.
- `migrate:fresh --seed` produces a working demo including the new phase's features.
- Docs updated: this plan's status, `.env.example`, and any operational notes.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
| --- | --- |
| AI misclassifies topics | Confidence threshold + student confirm/override step; manual fallback on failure; log corrections to tune prompts |
| AI cost creep | Per-user daily limits, client-side image downscale, image-hash cache, cheap model (gpt-4o-mini) |
| Video provider limits (embed policies, quotas) | P0 spike proves the flow before P7; adapter keeps alternatives viable; join link fallback (new tab) |
| Payment KYC / gateway delays | Start account setup in P0; build and test entirely in gateway test mode |
| Double-booking races | DB transaction + locking + concurrency test; holds expire automatically |
| Timezone/DST bugs in availability | UTC storage, CarbonImmutable, DST-crossing fixtures in slot tests |
| Scope creep ("enough for V1") | Section 10 non-goals backlog; new ideas go there, not into phases |
| Stale POS scaffolding misleads | Removed in P0; design guide re-pointed at this repo |
| Solo-dev risk / timeline slips | Phases are independently demoable; supply-side (P2–P3) can ship before demand-side externally |

---

## 10. Explicit Non-Goals (Post-V1 backlog)

Group sessions, subscriptions/lesson packages, recurring bookings, automated teacher payouts, mobile apps, multi-currency/multi-language, referrals/coupons, homework grading, recordings library, AI chat tutor, WebRTC/Laravel Reverb realtime chat upgrade, Meilisearch, parent multi-child dashboards, teacher-student blocking, advanced analytics warehouse.

---

## 11. Launch Acceptance Checklist (maps to overview)

**Student:** register → profile → pick subjects/topics → upload question → AI suggests topic → browse verified teachers → view teacher profile → book slot → pay → join live class → chat → rate → see lesson history.

**Teacher:** register → profile + docs → admin verifies → manage subjects/topics → set availability + pricing → receive request → accept/reject → teach live lesson → view earnings → see reviews.

**Admin:** verify teachers → manage students → manage bookings → manage payments → resolve disputes → issue refunds → configure commission → view reports → manage subjects/topics.

**Core flow end-to-end:** photo upload to AI topic to matched verified teacher to scheduled paid live lesson to review — verified on staging with real provider test integrations before launch.
