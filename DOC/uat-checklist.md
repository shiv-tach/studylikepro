# Launch acceptance & UAT checklist

The section 11 acceptance list from the implementation plan, turned into steps a
human can run on staging. Tick each row, note the date and who ran it, and keep
any bug references next to the row that failed.

**Environment:** staging, `APP_ENV=staging`, gateway in test mode, `MEETING_PROVIDER=fake`
or a Daily.co test key, `QUEUE_CONNECTION` pointing at a running worker.

**Accounts:** one fresh student, one fresh teacher, one admin.

---

## A. The core flow — photo to review

Automated equivalent: `tests/Feature/Acceptance/CoreFlowTest.php` (runs in CI).

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| A1 | Register as a student | Lands on the **Set up your learning** wizard (`/student/onboarding`), not the dashboard; the sidebar shows only *Get started*, and the dashboard/profile URLs bounce back to the wizard | ☐ |
| A1a | Step 1 — pick the education level (e.g. O/L) | Only that level's grades are offered next; the progress list starts at 4 steps (Level · Grade · Language · Finish) | ☐ |
| A1b | Step 2 — pick Grade 10 | The list grows to 5 steps and a **Subjects** step appears (O/L Grades 6-11 choose from baskets); an A/L grade keeps 4 steps | ☐ |
| A1c | Step 3 — pick the basket subjects | Grade 10-11: one from each of Category I, II and III, the counter reaching 3/3; Grade 6-9: Category II alone, reaching 1/1 with a note that junior grades pick their Category II subject. A second subject in the same basket cannot be selected; each basket shows its description and a *chosen* marker | ☐ |
| A1d | Step 4 — pick the learning language | Only Sinhala and English are offered | ☐ |
| A1e | Step 5 — check the summary and **Finish setup** | Summary repeats level, grade, the basket subjects and the language, each with an Edit link back to its step; the dashboard then opens with "Your profile is complete" | ☐ |
| A1f | Open *My subjects* after finishing | The picked category subjects appear under their basket headers marked *chosen* and every compulsory subject is listed; each card opens its subject page | ☐ |
| A2 | Change the learning profile (grade, language, goals) | Grade picker groups grades under O/L · A/L; the language select keeps the wizard's answer; saving returns to the profile | ☐ |
| A3 | Pick a subject and a lesson | Only your level's subjects and your grade's lessons are offered; choices persist after a reload | ☐ |
| A4 | Open a verified teacher from *Find a teacher* and click **Book a slot** | The booking page shows the learner grade read-only from the learning profile; only that grade's lessons and rate are offered | ☐ |
| A5 | Pick a published slot and confirm the booking | A pending-payment hold appears in *My lessons* with the payment countdown; the teacher sees the hold in *Schedule* | ☐ |
| A6 | Try to book the same slot in a second session | The slot is rejected because it is held; the first hold is untouched | ☐ |
| A7 | Register as a teacher | Lands on the **teacher setup wizard** (`/teacher/onboarding`), not the dashboard; the page has its own top bar and no workspace sidebar; the dashboard/profile URLs bounce back to the first step that is still owed | ☐ |
| A8 | Fill the **About you** step and reload the page mid-edit | The entered headline, bio, experience and education come back (server draft), with a "We restored your unsaved answers" banner and a *Start over* action | ☐ |
| A8a | Fill **Teaching preferences** (languages, rate, photo) and save | The profile completes, the weekly availability step opens, and the saved values are still there after a reload | ☐ |
| A8b | Open the availability step, load a suggested week, reload before saving | The draft week comes back instead of the recommended preset; saving replaces the stored ranges and opens verification | ☐ |
| A9 | Try the dashboard or verification before saving a weekly range | Redirected to the availability step; verification shows as locked in the progress rail | ☐ |
| A10 | Pick at least one subject, upload a government ID and submit for review | Subjects and the terms box are required; the wizard ends on **Application received — in review** at `/teacher/approval`, status Pending, and the *Review & approval* step is amber in the progress rail | ☐ |
| A10a | While pending, open the dashboard, services, subjects, schedule or earnings | Each redirects to the approval status page; the sidebar keeps only Approval status, Preview profile, Availability, Messages and Settings; every page carries the amber "not visible to students yet" banner with the expected decision date | ☐ |
| A10b | Click **Preview my public profile** while pending | The public teacher page opens with the "Preview only — your profile is not public yet" banner, an *Awaiting approval* badge and a *Not bookable yet* action; `/teachers/{id}` still 404s | ☐ |
| A10c | Wait past the 2-business-day review promise | The status page shows "This is taking longer than usual"; the admin queue shows an *Overdue* chip for the application | ☐ |
| A10 | Admin approves the application | First page load after approval shows the one-time **You are live** screen; *Start teaching* records it and opens the full workspace; the pending subjects are approved and the teacher appears in `/teachers` | ☐ |
| A11 | Teacher configures grades, lessons, a rate per grade and availability | Subject panels show grade chips with a rate input each and lesson checkboxes pre-selected; weekly slots appear | ☐ |
| A12 | Student pays from the checkout page (test mode) | Booking confirmed, "Payment received", receipt available | ☐ |
| A13 | Both open **Join** in the classroom | Classroom page loads with the lesson countdown and the correct join link per role | ☐ |
| A14 | Both exchange chat messages | Messages appear without a reload (5s poll), unread badges behave | ☐ |
| A15 | Teacher starts and completes the lesson | Status Completed; earnings row becomes available; system message in the chat | ☐ |
| A16 | Student leaves a review | Rating appears on the teacher profile; history shows the lesson | ☐ |

---

## B. Money paths

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| B1 | Student cancels 48h ahead | Automatic full refund; receipt shows the refund | ☐ |
| B2 | Student cancels inside the free window | Cancellation is refused for the student; support must step in | ☐ |
| B3 | Teacher cancels a paid lesson | Full refund regardless of timing; student notified | ☐ |
| B4 | Let a hold lapse (or force `expires_at` in the past) | Slot released, student emailed, slot bookable again | ☐ |
| B5 | Pay, then let the auto-completion sweep close the lesson | Earnings become eligible; teacher can be paid out | ☐ |
| B6 | Admin creates a payout batch and marks it paid with a reference | Teacher gets the payout receipt; balance drops to zero | ☐ |
| B7 | Admin refunds 50% from the Payments console | Payment shows Partially refunded; teacher's earning is adjusted | ☐ |
| B8 | Admin opens Admin → Earnings for a date range | Collections, commission, booking fees, refunds and teacher payouts reconcile with the Payments console; net platform earnings and the CSV export match | ☐ |

---

## C. Disputes, moderation and support

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| C1 | Student reports a problem from the lesson chat | Dispute row opens; every admin is notified | ☐ |
| C2 | Student tries to report the same lesson twice | Second report refused ("already open") | ☐ |
| C3 | Admin opens the dispute | Booking, payment, chat and both parties on one page | ☐ |
| C4 | Admin picks the case up | Status "In review", recorded in the audit log | ☐ |
| C5 | Admin resolves with a partial refund | Refund processed, dispute Resolved, both parties notified | ☐ |
| C6 | Teacher reports an unfair review | Review appears in Moderation with the teacher's note | ☐ |
| C7 | Admin hides the review | It disappears from the profile and the teacher's rating recalculates | ☐ |
| C8 | Admin dismisses the report on another review | Review stays published, report cleared | ☐ |
| C9 | Admin suspends a user with a reason | Their session ends on the next click; login is blocked with a clear message | ☐ |
| C10 | Admin reactivates the account | The user can sign in again | ☐ |
| C11 | Contact form is submitted from `/contact` | Message stored, admin notified, sender sees a confirmation | ☐ |

---

## D. Admin console & reports

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| D1 | Dashboard | Attention counts (disputes, flags, verifications, suspensions) match reality; last-30-day money tiles look right | ☐ |
| D2 | Bookings list filters (status, teacher, student, dates) | Result set matches the filter; detail page shows timeline + chat copy | ☐ |
| D3 | Force-complete a stuck lesson | Status Completed; teacher's earning released | ☐ |
| D4 | Regenerate a failed classroom link | New join links; the classroom page works for both sides | ☐ |
| D5 | Reports: change the date range | KPIs, chart and teacher table follow the range | ☐ |
| D6 | Export each CSV (bookings, payments, refunds, payouts, teachers) | Files download and open cleanly in a spreadsheet | ☐ |
| D7 | Settings: change commission, the booking fee and the cancellation window | New values apply to new bookings; the booking page, checkout and receipt show the new fee; refund policy page shows the new window | ☐ |
| D8 | Special offers: create a waiver offer running now, then reserve a slot as a student | Checkout total drops by the fee; the receipt shows the offer; bookings made outside the window keep paying the fee | ☐ |
| D9 | Activity log | Every action above is listed with who, what, when and the sanitised input | ☐ |

---

## E. Cross-cutting

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| E1 | Open the app on a phone | Sidebar collapses, tables scroll, nothing overflows | ☐ |
| E2 | Switch each theme preset and dark mode | Every admin, student and teacher page stays readable | ☐ |
| E3 | Turn off email notifications in Settings | In-app notifications still arrive; mailbox stays quiet | ☐ |
| E4 | Trigger 429s: hammer login, the contact form and chat posting | Friendly throttling, no 500s | ☐ |
| E5 | Open another user's lesson, receipt or document URL | 403, never a leaked page | ☐ |
| E6 | `curl -sI https://<domain>/up` | `200` plus the security headers (see the deployment runbook) | ☐ |
| E7 | Click the footer links | Privacy, terms, refund policy and contact all render with the current numbers | ☐ |

---

## F. Live-mode smoke test (after test mode is signed off)

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| F1 | One real payment with a live key (small amount) | Captured, booking confirmed, webhook accepted once | ☐ |
| F2 | Refund that payment in full from the console | Refund processed with a gateway reference; both parties notified | ☐ |
| F3 | One real Daily.co room | Both participants join; the room closes after the lesson | ☐ |
| F4 | One notification email to a real inbox | Branded, not in spam, links work | ☐ |

---

## G. Curriculum, grades & lessons

Automated equivalents: `tests/Feature/CatalogTest.php`, `tests/Feature/Admin/CurriculumLevelTest.php`, `tests/Feature/Admin/GradeLessonTest.php`,
`tests/Feature/Admin/SubjectBasketTest.php`, `tests/Feature/Teacher/GradeLessonAssignmentTest.php`, `tests/Feature/Student/GradeScopedCatalogTest.php`,
`tests/Feature/Student/BasketSubjectsTest.php`, `tests/Feature/TeacherDiscoveryTest.php`,
`tests/Feature/Student/StudentTeacherFinderTest.php`.

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| G1 | Admin opens `/admin/curriculum` | The two levels (O/L, A/L) with their active subject, grade and lesson counts | ☐ |
| G2 | Admin edits **O/L Mathematics** and reads the Grade 6 tab | The real lesson list (12 lessons in Grade 6, 10 in Grade 7, …); add, copy-to-grade, bulk-activate and move-lesson tools all work; the change is visible on the student side after a reload | ☐ |
| G3 | Teacher selects **O/L Mathematics** while setting up subjects | Grades 6–11 and every lesson are auto-assigned; unchecking Grade 10 detaches its lessons when saved | ☐ |
| G5 | Grade 8 student opens interests and the booking page | Interests lists only O/L Grade 8 subjects; the booking page offers only Grade 8 lessons; a Grade 11 lesson cannot be booked | ☐ |
| G7 | Admin renames lessons in the matrix | Teacher and student pickers show the new names (catalog cache flushed), and directory cards show "Grades 6-11 · N lessons" | ☐ |
| G8 | Visitor opens `/subjects` | The two levels (O/L, A/L) are offered; picking a level reveals its grades; picking a grade lists only the subjects that run in it (e.g. Commerce for Grade 10–11, not Grade 6), each with the grade's lesson count; opening one lists that grade's lessons with a "View all grades" link | ☐ |
| G9 | Visitor opens a subject (e.g. `/subjects/english?grade=6`) | Verified teachers for that subject and grade are listed with their hourly rate for the selected grade, teaching scope and next availability; "See all N teachers" opens the directory pre-filtered; each card opens the teacher profile to book | ☐ |
| G10 | Admin opens `/admin/curriculum` | The O/L card lists the three baskets (Category I, II, III) with their grade ranges (Grades 10-11 · Grades 6-11 · Grades 10-11), subject counts and inline name/description/icon/order/active forms | ☐ |
| G10a | Admin creates a subject in `/admin/subjects` | The form offers a **Grades** select whose labels follow the chosen education level — All grades (default), Grades 6-9, Grades 10-11 for O/L — and the created subject is kept out of the grades its band does not cover (a Grades 10-11 subject never appears for a Grade 7 student, a Grades 6-9 subject never for a Grade 10 student) | ☐ |
| G10b | Admin edits an O/L subject banded to Grades 6-9 (`/admin/subjects/{subject}/edit`) | The **Lessons by grade** matrix lists only Grades 6-9 — no Grade 10/11 tabs or copy targets — and its total matches those grades; switching the band to All grades brings the whole level back | ☐ |
| G11 | Admin edits an O/L subject (e.g. Art) and changes its **Subject basket**, then opens `/subjects?level=ol&grade=10` | The subject appears under the new basket; a basket of another level is rejected with a validation error | ☐ |
| G11a | Admin opens `/admin/subjects?level=ol` | A **Subject baskets** panel groups the level's subjects under Category I, II and III (each with its grade range and count) and lists the rest under *Not in a basket*; changing a subject's select moves it to that basket (or to *No basket*) in place and the page returns with "Basket updated."; the panel is absent when no level is filtered or the level has no baskets | ☐ |
| G12 | Grade 10 student opens interests | Compulsory subjects are all listed, and each basket shows only its picked subject (marked *chosen*); unpicked basket options stay hidden; every card opens the subject page | ☐ |
| G13 | Grade 10 student saves two subjects of the same basket (e.g. by disabling JS) | The save is rejected with "Pick one subject from each O/L basket …" | ☐ |
| G13a | Grade 9 (or another junior grade) student saves two Category II subjects | The same rejection — the one-subject-per-basket rule covers the junior grades too | ☐ |
| G14 | Grade 10 student with no Category II pick opens interests | The Category II group is simply absent — only compulsory subjects and picked category subjects appear | ☐ |
| G15 | A/L student opens interests | All subjects are listed as clickable cards in one flat grid — no basket groups, no lesson checkboxes | ☐ |
| G15a | Grade 8 student opens interests after picking a Category II subject in onboarding | The pick appears under a **Category II** header (chosen), the compulsory subjects are listed above it, and Categories I/III stay off the page | ☐ |
| G16 | Visitor opens `/subjects?level=ol&grade=10` | Step 3 shows *Compulsory subjects* first, then 📚 Category I, 🎨 Category II and 🛠️ Category III, each with a "pick one" chip and its one-line description; the note explains the exam rule | ☐ |
| G17 | Visitor opens `/subjects?level=ol&grade=7` | Step 3 groups the grade's Category II subjects (chip "pick one") with everything else under *Compulsory subjects*; Categories I and III never appear for a junior grade | ☐ |
| G18 | Admin renames/describes a basket, then reloads `/subjects?level=ol&grade=10` | The new name and description appear immediately (catalog cache flushed on the basket write) | ☐ |
| G19 | Grade 8 student opens *Find a teacher* from the dashboard | The page lists only verified teachers who take Grade 8 (their Grade 8 hourly rate shown); the subject picker offers Grade 8 subjects; picking a date and time window keeps only teachers genuinely free then; "Earliest availability" puts the soonest open slot first; the `grade` query parameter cannot widen the list | ☐ |
| G20 | Grade 8 student opens a teacher from the finder | The profile opens inside the student area (`/student/teachers/…`) showing only Grade 8 subjects, lessons and rates ("Grade 8 lessons & rates"); *Book a slot* opens the booking page; the student is redirected from `/teachers` and `/teachers/{id}`; a guest still gets the full public profile with the log-in CTA; a teacher who does not take Grade 8 gets a notice linking back to *Find a teacher* | ☐ |
| G21 | Grade 8 student opens `/student/lessons/book/{teacher}` | The grade shows as a read-only value taken from the learning profile (no grade picker); adding `?learner_grade_id=…` to the URL or posting one leaves the lessons and the rate at Grade 8; *Change grade* opens the learning profile, and the page then follows the new grade | ☐ |
| G23 | Grade 9 student opens interests and clicks a subject card | The dedicated subject page opens inside the student area at `/student/subjects/{slug}` (e.g. `/student/subjects/mathematics`) with only the profile grade's lessons, services and teachers | ☐ |
| G24 | Student opens the public `/subjects/{slug}?grade=…` URL (from a bookmark or the marketing site) | They are redirected into `/student/subjects/{slug}`; the query-string grade never widens the page — lessons, services and rates stay on the profile grade | ☐ |

---

## H. Service catalog — lesson plans, paper marking & Q&A

Automated equivalents: `tests/Feature/Services/PurchaseFlowTest.php`, `tests/Feature/Marking/PaperMarkingFlowTest.php`,
`tests/Feature/Teacher/ServiceOfferingConsoleTest.php`.

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| H1 | Teacher opens *Services & pricing* and creates a **lesson plan** for a lesson | Picks a lesson, sets the total allocation (e.g. 4h), a flat price, the default session length and a session cap; it saves and the price is in rupees | ☐ |
| H2 | Same teacher adds a **paper marking**, **paper discussion** and **Q&A** offering | Each type shows its type-specific fields (subject scope + turnaround for marking, lesson-or-subject for Q&A); wrong scopes are rejected | ☐ |
| H3 | Student opens the teacher profile | The *Services & pricing* section lists every active offering with its price; the teacher raises a price | ☐ |
| H4 | Teacher raises a price, then the student reloads | The new price shows immediately; a purchase made before the change keeps the old price snapshot | ☐ |
| H5 | Student buys the lesson plan and pays (test mode) | Purchase becomes Active with the full allocation and a validity date; "My purchases" shows the balance bar | ☐ |
| H6 | Student books one session from the plan | Session confirmed with no checkout — it is covered by the plan; balance drops by the session length; another session books later against the remaining time | ☐ |
| H7 | Teacher completes the final session | The plan completes; the teacher's earning becomes available; the student sees the plan under *Past* | ☐ |
| H8 | Student cancels a plan session | Time returns to the plan balance and can be rebooked | ☐ |
| H9 | Let a plan expire with time left (force `expires_at` into the past and run the scheduler) | Future sessions are cancelled; the unused time is refunded pro-rata; the purchase is Expired | ☐ |
| H10 | Student buys **paper marking**, uploads a paper and pays | Order Submitted with a paid deadline; the teacher sees it in the marking inbox | ☐ |
| H11 | Teacher uploads the marked paper, feedback and marks | Order Returned; the purchase completes and the earning is eligible; the student can download the marked files | ☐ |
| H12 | Student accepts the marked paper | Order Accepted | ☐ |
| H13 | Student disputes the marked paper | Order Disputed; the purchase is flagged; the admin resolves either way — refund path issues the refund, accept path keeps the result | ☐ |
| H14 | Student buys a **paper discussion** after receiving the marked paper | The live session books against the purchase and runs in the classroom like any lesson | ☐ |
| H15 | Student buys a **Q&A** (lesson-scoped, then subject-scoped) | The session books against the purchase; the subject-scoped Q&A shows no lesson on the booking | ☐ |
| H16 | Admin opens *Services*, *Purchases* and *Marking* consoles | Filtering works; a purchase can be refunded and cancelled; a disputed marking order can be resolved; an offering with purchases cannot be deleted | ☐ |
| H17 | Visitor opens a subject page (e.g. `/subjects/mathematics?grade=9`) | A *Services for Mathematics in Grade 9* section groups the active offerings of verified teachers by type (lesson plans, paper marking, paper discussions, Q&A) with price, grade/lesson scope chips and the teacher's name; other grades' offerings, deactivated offerings and unapproved teachers stay hidden; with no offerings yet the empty state links to the teacher directory | ☐ |
| H18 | Same page, signed in as a student | The student is redirected to `/student/subjects/mathematics` inside their workspace; there each offering shows *Buy now* (heading into that purchase's checkout) and paper marking shows *Submit a paper*; the teacher cards' "Book now" opens the booking page; the guest still gets the public page with *Sign up to buy* | ☐ |

---

## Sign-off

| Area | Run by | Date | Result | Notes / issues |
| --- | --- | --- | --- | --- |
| A. Core flow | | | | |
| B. Money paths | | | | |
| C. Disputes & moderation | | | | |
| D. Admin console | | | | |
| E. Cross-cutting | | | | |
| F. Live-mode smoke | | | | |
| G. Curriculum & grades | | | | |
| H. Service catalog | | | | |
