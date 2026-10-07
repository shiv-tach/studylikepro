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
| A2 | Complete the learning profile (grade, goals) | Dashboard unlocks, "Profile complete" status | ☐ |
| A3 | Pick a subject and a topic | Choices persist after a reload | ☐ |
| A4 | Create a request with a question photo attached | Request page shows *Classifying…* then the subject, topic and confidence | ☐ |
| A5 | Trust the AI suggestion | Request status becomes Open; matching teachers notified | ☐ |
| A6 | Override the suggestion on another request | The chosen subject/topic replaces the guess | ☐ |
| A7 | Register as a teacher, complete the teaching profile | Step 1 is marked completed in the 2-step progress; verification page opens | ☐ |
| A8 | Try the dashboard or another teacher page before submitting verification | Redirected back to verification; step 2 shows as in progress | ☐ |
| A9 | Upload a government ID and submit for review | Terms checkbox required; status Pending and the teacher area unlocks with both steps completed | ☐ |
| A10 | Admin approves the application | Teacher sees "Approved"; appears in `/teachers` | ☐ |
| A11 | Teacher adds subjects, topics, rate and availability | Subject chips and weekly slots appear | ☐ |
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

## Sign-off

| Area | Run by | Date | Result | Notes / issues |
| --- | --- | --- | --- | --- |
| A. Core flow | | | | |
| B. Money paths | | | | |
| C. Disputes & moderation | | | | |
| D. Admin console | | | | |
| E. Cross-cutting | | | | |
| F. Live-mode smoke | | | | |
