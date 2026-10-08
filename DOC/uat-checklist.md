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
| A1 | Register as a student | Lands on the student dashboard, onboarding prompt shown | ☐ |
| A2 | Complete the learning profile (grade, goals) | Grade picker groups grades under Primary · O/L · A/L · Other; dashboard unlocks, "Profile complete" status | ☐ |
| A3 | Pick a subject and a lesson | Only your level's subjects and your grade's lessons are offered; choices persist after a reload | ☐ |
| A4 | Create a request with a question photo attached | Request page shows *Classifying…* then the subject, lesson and confidence | ☐ |
| A5 | Trust the AI suggestion | Request status becomes Open; matching teachers notified | ☐ |
| A6 | Override the suggestion on another request | The chosen subject/lesson replaces the guess; only lessons of the request's grade are offered | ☐ |
| A7 | Register as a teacher, complete the teaching profile | Step 1 is marked completed in the 2-step progress; verification page opens | ☐ |
| A8 | Try the dashboard or another teacher page before submitting verification | Redirected back to verification; step 2 shows as in progress | ☐ |
| A9 | Upload a government ID and submit for review | Terms checkbox required; status Pending and the teacher area unlocks with both steps completed | ☐ |
| A10 | Admin approves the application | Teacher sees "Approved"; appears in `/teachers` | ☐ |
| A11 | Teacher adds subjects (grades and lessons auto-assign), sets a rate per grade and availability | Subject panels show grade chips with a rate input each and lesson checkboxes pre-selected; weekly slots appear | ☐ |
| A12 | Teacher opens the request inbox | The student's question and photo are visible | ☐ |
| A13 | Teacher accepts with a slot | A booking hold exists; the student is notified | ☐ |
| A14 | Student pays from the checkout page (test mode) | Booking confirmed, "Payment received", receipt available | ☐ |
| A15 | Both open **Join** in the classroom | Classroom page loads with the lesson countdown and the correct join link per role | ☐ |
| A16 | Both exchange chat messages | Messages appear without a reload (5s poll), unread badges behave | ☐ |
| A17 | Teacher starts and completes the lesson | Status Completed; earnings row becomes available; system message in the chat | ☐ |
| A18 | Student leaves a review | Rating appears on the teacher profile; history shows the lesson | ☐ |

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
`tests/Feature/Student/BasketSubjectsTest.php`, `tests/Feature/Matching/GradeMatchTest.php`, `tests/Feature/TeacherDiscoveryTest.php`,
`tests/Feature/Student/StudentTeacherFinderTest.php`.

| # | Step | Expected | ✅ |
| --- | --- | --- | --- |
| G1 | Admin opens `/admin/curriculum` | The four levels (Primary, O/L, A/L, Other) with their active subject, grade and lesson counts | ☐ |
| G2 | Admin edits **O/L Mathematics** and reads the Grade 6 tab | The real lesson list (12 lessons in Grade 6, 10 in Grade 7, …); add, copy-to-grade, bulk-activate and move-lesson tools all work; the change is visible on the student side after a reload | ☐ |
| G3 | Teacher selects **O/L Mathematics** while setting up subjects | Grades 6–11 and every lesson are auto-assigned; unchecking Grade 10 detaches its lessons when saved | ☐ |
| G4 | Teacher opens the request inbox with a Grade 8 request pending | Only requests for grades the teacher covers appear, each with its grade chip; the nav badge count matches | ☐ |
| G5 | Grade 8 student opens interests, the request form and the booking page | Only O/L subjects and Grade 8 lessons are offered; a Grade 11 lesson cannot be submitted, confirmed or booked | ☐ |
| G6 | Grade 8 request where the lesson is only taught by a Grade 11-only teacher | That teacher is neither notified nor shown the request in the inbox | ☐ |
| G7 | Admin renames lessons in the matrix | Teacher and student pickers show the new names (catalog cache flushed), and directory cards show "Grades 6-11 · N lessons" | ☐ |
| G8 | Visitor opens `/subjects` | The four levels (Primary, O/L, A/L, Other) are offered; picking a level reveals its grades; picking a grade lists only the subjects that run in it (e.g. Commerce for Grade 10–11, not Grade 6), each with the grade's lesson count; opening one lists that grade's lessons with a "View all grades" link | ☐ |
| G9 | Visitor opens a subject (e.g. `/subjects/english?grade=6`) | Verified teachers for that subject and grade are listed with their hourly rate for the selected grade, teaching scope and next availability; "See all N teachers" opens the directory pre-filtered; each card opens the teacher profile to book | ☐ |
| G10 | Admin opens `/admin/curriculum` | The O/L card lists the three baskets (Category I, II, III) with subject counts and inline name/description/icon/order/active forms | ☐ |
| G11 | Admin edits an O/L subject (e.g. Art) and changes its **Subject basket**, then opens `/subjects?level=ol&grade=10` | The subject appears under the new basket; a basket of another level is rejected with a validation error | ☐ |
| G12 | Grade 10 student opens interests | Subjects are grouped Compulsory subjects · Category I · Category II · Category III; checking a Category I subject unchecks the other Category I choices | ☐ |
| G13 | Grade 10 student saves two subjects of the same basket (e.g. by disabling JS) | The save is rejected with "Pick one subject from each O/L basket …" | ☐ |
| G14 | Grade 10 student saves with one basket still empty | The interests are saved and the confirmation names the empty basket as a reminder | ☐ |
| G15 | Grade 9 (or Primary) student opens interests | The flat subject list stays as before — no basket groups, no limit | ☐ |
| G16 | Visitor opens `/subjects?level=ol&grade=10` | Step 3 shows *Compulsory subjects* first, then 🎨 Category I, 🛠️ Category II and 📚 Category III, each with a "pick one" chip and its one-line description; the note explains the exam rule | ☐ |
| G17 | Visitor opens `/subjects?level=ol&grade=6` | The grid stays flat with the note "Every subject below is compulsory in Grade 6; the optional O/L baskets … begin in Grades 10–11" — never a "pick one" chip | ☐ |
| G18 | Admin renames/describes a basket, then reloads `/subjects?level=ol&grade=10` | The new name and description appear immediately (catalog cache flushed on the basket write) | ☐ |
| G19 | Grade 8 student opens *Find a teacher* from the dashboard | The page lists only verified teachers who take Grade 8 (their Grade 8 hourly rate shown); the subject picker offers Grade 8 subjects; picking a date and time window keeps only teachers genuinely free then; "Earliest availability" puts the soonest open slot first; the `grade` query parameter cannot widen the list | ☐ |
| G20 | Grade 8 student opens a teacher from the finder | The profile opens inside the student area (`/student/teachers/…`) showing only Grade 8 subjects, lessons and rates ("Grade 8 lessons & rates"); *Book a slot* opens the booking page; the student is redirected from `/teachers` and `/teachers/{id}`; a guest still gets the full public profile with the log-in CTA; a teacher who does not take Grade 8 gets a notice linking back to *Find a teacher* | ☐ |
| G21 | Grade 8 student opens `/student/lessons/book/{teacher}` | The grade shows as a read-only value taken from the learning profile (no grade picker); adding `?learner_grade_id=…` to the URL or posting one leaves the lessons and the rate at Grade 8; *Change grade* opens the learning profile, and the page then follows the new grade | ☐ |
| G22 | Grade 8 student opens `/student/requests/create` | The learner grade shows read-only from the learning profile (no grade picker); posting another `grade_id` still stores the request at Grade 8, so matching and the AI lesson scope stay at that grade | ☐ |

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
