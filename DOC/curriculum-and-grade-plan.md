# Sri Lankan Curriculum, Grades & Lessons — Implementation Plan

**Companion to:** [overview.md](./overview.md) · [implementation-plan.md](./implementation-plan.md)
**Design rules:** [DESIGN_SYSTEM_AND_THEMING_GUIDE.md](../DESIGN_SYSTEM_AND_THEMING_GUIDE.md)
**Status:** Phases A–F implemented (taxonomy foundation, admin curriculum management, teacher assignment, grade-aware student side, grade-scoped matching/AI/search, and the legacy-column cleanup + docs/UAT sweep), plus the O/L basket categories (§13).

**Goal in one sentence:** Replace the flat subject→topic catalog with the Sri Lankan education structure — *Primary (Grades 1–5), O/L (Grades 6–11), A/L (Grades 12–13), Other* — where every subject belongs to a level and has a **grade-by-grade lesson list** (e.g. O/L Mathematics: Grade 6 → 12 lessons, Grade 7 → 10 lessons), so that admins curate the curriculum once, teachers pick "O/L Mathematics" and instantly inherit its grades + lessons, and students are shown only the subjects and lessons that exist for *their* grade.

---

## 1. Why this change

### 1.1 What exists today

| Concern | Current state | Where |
| --- | --- | --- |
| Subjects | Flat list, no level/grade dimension | `subjects` table, [Subject.php](../app/Models/Subject.php) |
| Lessons | Called "topics", one flat list per subject, same for every grade | `topics` table, [Topic.php](../app/Models/Topic.php) |
| Grade levels | Coarse, non-Sri-Lankan buckets: `primary / middle_school / high_school / college / adult` | `config('studylikepro.grade_levels')` in [config/studylikepro.php](../config/studylikepro.php) |
| Teacher assignment | Teacher checks subjects → per subject checks topics + grade buckets + rate | [Teacher/SubjectsController.php](../app/Http/Controllers/Teacher/SubjectsController.php), `teacher_subjects.grade_levels` (JSON), `teacher_topics` pivot |
| Student profile | Single coarse `grade_level` string | `student_profiles.grade_level`, [StudentProfileRequest.php](../app/Http/Requests/StudentProfileRequest.php) |
| Student interests | Subject + topic checkboxes, **not** filtered by student's grade | [Student/InterestsController.php](../app/Http/Controllers/Student/InterestsController.php) |
| Tutoring request | `subject_id` + `topic_id`, no grade captured | `tutoring_requests`, [TutoringRequest.php](../app/Models/TutoringRequest.php) |
| Matching | Topic + availability only; grade ignored | [RequestMatcher.php](../app/Services/RequestMatcher.php) |
| AI classification | Maps question → subject + topic across the whole catalog | [OpenAITopicClassifier.php](../app/Services/AI/OpenAITopicClassifier.php) |
| Teacher search | Filter by subject + coarse grade bucket (JSON LIKE) | [TeacherSearch.php](../app/Services/TeacherSearch.php) |
| Admin | Subject CRUD + flat topic CRUD under a subject | [Admin/SubjectController.php](../app/Http/Controllers/Admin/SubjectController.php), [Admin/TopicController.php](../app/Http/Controllers/Admin/TopicController.php) |
| Catalog seed | 6 subjects × 5 generic topics, no levels/grades | [CatalogSeeder.php](../database/seeders/CatalogSeeder.php) |

### 1.2 Problems this plan solves

1. **No grade dimension.** Grade 6 and Grade 11 see the same topic list. A Grade 8 student can pick an A/L Calculus topic that no one teaches for Grade 8.
2. **Wrong taxonomy for Sri Lanka.** `middle_school / high_school / college` does not match Primary / O/L / A/L, and it is baked into ~15 files.
3. **No level scoping.** The same "Mathematics" subject row is used for Primary, O/L and A/L even though syllabi, teachers, and rates differ per level. A teacher cannot say "I teach *O/L* Mathematics" — only "Mathematics" + bucket checkboxes.
4. **Teacher assignment is manual and error-prone.** A teacher choosing "O/L Mathematics" must hand-pick topics that are actually the same for every grade.
5. **Student experience is generic.** Students must know which lesson applies to their grade; nothing is pre-filtered for them.

---

## 2. Target domain model

### 2.1 Sri Lankan structure (canonical)

| Level key | Name | Grades | Notes |
| --- | --- | --- | --- |
| `primary` | Primary | 1, 2, 3, 4, 5 | |
| `ol` | Ordinary Level (O/L) | 6, 7, 8, 9, 10, 11 | Grades 10-11 add the three **basket** categories; a candidate picks one optional subject from each (see §13) |
| `al` | Advanced Level (A/L) | 12, 13 | Streams exist but are **out of scope for V1**; subjects themselves differ per stream is a future extension |
| `other` | Other | — (no numbered grade) | Adult learners, hobby, professional help |

- Subjects differ **per level** (O/L has ~9 exam subjects; A/L has Bio/Maths/Commerce/Arts streams; Primary is integrated). The same name in two levels is two catalog entries: "Mathematics (O/L)" and "Mathematics (A/L)" are separate subjects.
- Lessons differ **per grade**: *O/L Mathematics → Grade 6 has 12 lessons, Grade 7 has 10 lessons, …*

### 2.2 Entity relationships

```
EducationLevel (primary | ol | al | other)
   ├── hasMany Grade            (level "ol" → grades 6..11; "other" → single grade row, number = null)
   ├── hasMany SubjectBasket    (level "ol" → Category I, II, III; see §13)
   └── hasMany Subject          (a subject lives in exactly one level)
          ├── belongsTo SubjectBasket  (nullable: mandatory subjects have no basket)
          └── hasMany Lesson    (a lesson belongs to subject + one grade)
```

```
TeacherProfile ──belongsToMany── Subject      (pivot teacher_subjects: grade ids JSON + rate override)
TeacherProfile ──belongsToMany── Lesson       (pivot teacher_lessons)

StudentProfile ──belongsTo── Grade            (exactly one grade)
User(Student) ──belongsToMany── Subject       (student_subject_interests, unchanged shape)
User(Student) ──belongsToMany── Lesson        (student_lesson_interests, replaces student_topic_interests)

TutoringRequest ──belongsTo── Subject, Lesson, Grade
```

---

## 3. Data model & migrations

Migrations follow the repo's `YYYY_MM_DD_######_description` convention. Suggested sequence (all in one phase, dependency-ordered):

### 3.1 `create_education_levels_table`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | id | |
| `key` | string(32), unique | `primary`, `ol`, `al`, `other` |
| `name` | string(60) | "Primary", "O/L (Ordinary Level)", "A/L (Advanced Level)", "Other" |
| `grade_min` | unsignedTinyInteger nullable | null for `other` |
| `grade_max` | unsignedTinyInteger nullable | null for `other` |
| `icon` | string(16) nullable | |
| `sort_order` | unsignedSmallInteger default 0 | |
| `is_active` | boolean default true | |
| timestamps | | |

### 3.2 `create_grades_table`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | id | |
| `education_level_id` | FK → education_levels, cascade | |
| `number` | unsignedTinyInteger **nullable** | 1–13; null = "Other" |
| `label` | string(40) | "Grade 6", "Other / Adult" (kept as data for admin edits) |
| `sort_order` | unsignedSmallInteger default 0 | |
| `is_active` | boolean default true | |
| timestamps | | |

Unique `(education_level_id, number)` (nullable `number` → single "Other" row per level). Seeded 1..13 + Other.

### 3.3 `add_education_level_to_subjects_table`

- `education_level_id` FK → education_levels, **nullOnDelete? No — restricted/cascade decided below** (use `cascadeOnDelete` with a guard in the admin that level deletion requires an empty level, so we never lose the syllabus silently).
- Keep the **`slug` index globally unique**. A subject that shares its name with one in another level stays a separate entry ("Primary Mathematics" vs "O/L Mathematics"), but its slug gets the level key prefixed (`mathematics` vs `ol-mathematics`): teacher, admin and catalog `{subject}` routes resolve the model from the slug alone, so per-level uniqueness made those URLs ambiguous (the first match won — found in Phase C browser testing). The seeder, the admin slug derivation in `SubjectRequest`, and `2026_10_08_000008_uniquify_subject_slugs` (de-duplicates existing rows) all apply the same rule.
- Index `(education_level_id, is_active, sort_order)`.

### 3.4 `rename_topics_to_lessons` (schema)

- Rename table `topics` → `lessons`.
- Add `grade_id` FK → grades (nullable **only during migration**; NOT NULL once backfilled).
- Add `description` string(500) nullable.
- Rename `teacher_topics` → `teacher_lessons`; rename column `topic_id` → `lesson_id`; keep unique `(teacher_profile_id, lesson_id)`.
- Rename `student_topic_interests` → `student_lesson_interests`; rename `topic_id` → `lesson_id`.
- `tutoring_requests`: rename `topic_id` → `lesson_id` (FK to lessons); add `grade_id` FK → grades nullable (backfilled from profile; NOT NULL enforced at request creation in code).
- Unique `(subject_id, grade_id, slug)` on `lessons` (replaces `(subject_id, slug)`).

### 3.5 `add_grade_to_student_profiles`

- `grade_id` FK → grades **nullable**. Kept nullable because guardian-registered profiles may exist before a learner grade is chosen; onboarding makes it required in the form request. Keep legacy `grade_level` column until Phase F, then drop it (and `learner_grade` handling migrates).

### 3.6 `update_teacher_subjects`

- Keep `grade_levels` JSON column name (avoids touching pivot plumbing in [TeacherProfile.php](../app/Models/TeacherProfile.php)), but its **contents change** from config keys (`high_school`) to **grade ids** (`["6","7","8", ...]`). JSON `LIKE` search updated accordingly.
- No other changes: `rate_per_hour_minor` stays.

### 3.7 `bookings.learner_grade`

- Add `learner_grade_id` FK → grades nullable (defaults from the student profile at booking creation). Keep `learner_grade` string until Phase F data migration, then drop. Display helpers switch to the `Grade` model.

> **Naming note:** the app already calls booked sessions "lessons" (`student/lessons` routes, "Book a lesson"). After this change "lesson" means *curriculum unit* in all catalog/admin/teacher contexts; booked sessions stay "lessons/bookings" in the student journey (they are "lessons *for* a lesson"), and copy is adjusted where ambiguous.

---

## 4. Models & config

### 4.1 New models

- `App\Models\EducationLevel` — `grades()`, `subjects()`, scopes `active()`, `ordered()`; accessors `gradeRangeLabel()`.
- `App\Models\Grade` — `level()`, `lessons()`; accessor `displayLabel()`; scope for the student picker.
- `App\Models\Lesson` — replaces `Topic` (see rename list in §6.4); `subject()`, `grade()`, `teacherProfiles()` (pivot `teacher_lessons`), scopes `active()`, `ordered()`.

### 4.2 Model updates

| Model | Change |
| --- | --- |
| `Subject` | `educationLevel()` belongsTo; `lessons()` hasMany; `gradeRange()` helper; scope `forLevel($key)` |
| `TeacherProfile` | `lessons()` replaces `topics()`; `subjects()` pivot unchanged (casts `grade_levels` in [TeacherSubject.php](../app/Models/TeacherSubject.php) already array) |
| `StudentProfile` | `grade()` belongsTo (nullable) |
| `TutoringRequest` | `lesson()` replaces `topic()`; `grade()` belongsTo; `isMatchable()` now requires `lesson_id` |
| `User` | `interestedLessons()` replaces `interestedTopics()` |

### 4.3 Config `studylikepro.php`

Replace the `grade_levels` array with:

```php
'education_levels' => [
    'primary' => ['name' => 'Primary',            'grade_min' => 1,  'grade_max' => 5,  'icon' => '🎒'],
    'ol'      => ['name' => 'O/L (Ordinary Level)', 'grade_min' => 6, 'grade_max' => 11, 'icon' => '📘'],
    'al'      => ['name' => 'A/L (Advanced Level)', 'grade_min' => 12, 'grade_max' => 13, 'icon' => '🎓'],
    'other'   => ['name' => 'Other',               'grade_min' => null, 'grade_max' => null, 'icon' => '🧭'],
],
```

DB stays the source of truth; config is only the bootstrap/seed reference so validation can hard-fail if a level row is missing. All ~15 references to `config('studylikepro.grade_levels')` are updated (list in §6.4).

### 4.4 Catalog cache

[CatalogService.php](../app/Services/CatalogService.php) cache payload becomes levels → subjects (with education level) → lessons **grouped by grade**. Cache keys renamed (`catalog:v3:*`, bumped whenever cached subjects change shape or slugs); flush stays in admin write paths.

---

## 5. Admin section — curriculum management

### 5.1 Screens

1. **Levels** (`/admin/curriculum`): table of 4 levels with grade ranges and subject counts. Levels are effectively fixed; admin can rename, reorder, toggle. Grades within a level are seeded and listed read-only.
2. **Subjects per level** (`/admin/curriculum/levels/{level}` or filtered view of existing `admin/subjects`): create "O/L Mathematics", "O/L Science", "A/L Physics"… Each subject form gains an `education_level` select (locked after creation — moving a subject between levels is a data migration, not an edit).
3. **Grade × lesson matrix** (`/admin/subjects/{subject}/edit`): the core admin screen.
   - Grade tabs (6 · 7 · 8 · 9 · 10 · 11) or a single grade dropdown.
   - Per grade: lesson list with name, description, sort order, active toggle, inline edit.
   - **Bulk tools:** "Copy lessons from Grade X → Grade Y" (most-used action — e.g. 80 % of lessons repeat across grades with small edits), "Add lesson", drag-free up/down ordering, bulk activate/deactivate.
   - Lesson count badge per grade in the tab (e.g. "Grade 6 · 12", "Grade 7 · 10").

### 5.2 Controllers & requests

| Artifact | Change |
| --- | --- |
| [Admin/SubjectController.php](../app/Http/Controllers/Admin/SubjectController.php) | `index` grouped by level; `store/update` handle `education_level_id`; `edit` loads lessons grouped by grade |
| [Admin/TopicController.php](../app/Http/Controllers/Admin/TopicController.php) | Renamed `Admin/LessonController`: routes become `admin.subjects.{subject}.lessons`; all actions scoped by `grade_id`; new `copyFromGrade` bulk action |
| New `Admin/EducationLevelController` | index + update (rename/order/active only) |
| [TopicRequest.php](../app/Http/Requests/TopicRequest.php) | → `LessonRequest`: validates `grade_id` belongs to the subject's level |
| [SubjectRequest.php](../app/Http/Requests/SubjectRequest.php) | adds `education_level_id` required on create; slug unique rule scoped to level |
| Views | [admin/subjects/](../resources/views/admin/subjects/) reworked; new `admin/subjects/edit` grade-tab layout; update admin nav + `LogAdminActivity` labels |

### 5.3 Seeding the real curriculum

Rewrite [CatalogSeeder.php](../database/seeders/CatalogSeeder.php):

- Create the 4 levels + grades (1–13, Other) from config.
- Seed subjects per level with the official subject names (admin can edit):
  - **Primary:** Mathematics, English, Sinhala/Tamil, Environment-Related Activities, Religion…
  - **O/L:** Mathematics, Science, English, Sinhala, Tamil, History, Buddhism/Religions, ICT, Commerce, Geography, Art, Music, Dancing, Health & PE…
  - **A/L:** Combined Mathematics, Physics, Chemistry, Biology, Accounting, Economics, Business Studies, Logic, ICT…
  - **Other:** English Conversation, Adult Mathematics, Coding…
- Seed lesson lists per grade. Use the user's real data as the canonical starter set and placeholders elsewhere, e.g.:

  ```php
  // O/L Mathematics
  'ol-mathematics' => [
      'grade_6'  => ['Lesson 6.1', …, 'Lesson 6.12'], // 12 lessons
      'grade_7'  => ['Lesson 7.1', …, 'Lesson 7.10'], // 10 lessons
      // grades 8–11 filled with sensible counts; exact names entered by admin from the syllabus
  ],
  ```

  > Exact lesson names should come from the official syllabus; the seeder ships with clearly-labelled placeholders ("Grade 6 – Unit 01 …") so admins can bulk-rename or re-import via the matrix editor.

### 5.4 Data migration of existing rows

A data migration in the same release:

1. Backfill `education_level_id` on existing subjects: Mathematics/Science/English/etc. → `ol` (current demo data is O/L-flavoured), Computer Science → `al`; anything ambiguous → `other`.
2. Backfill `lessons.grade_id`: existing topics have no grade. Default each subject's topic list into **one representative grade** (e.g. Grade 10) and let admin copy per grade with the bulk tool; or duplicate across all grades of the level with a `migration` flag. **Recommended:** default into all grades of the level, admin prunes.
3. `teacher_topics` → `teacher_lessons` maps 1:1 after step 2 chooses a canonical per-grade copy (use the lowest grade as canonical to keep pivot rows meaningful).
4. `teacher_subjects.grade_levels`: old bucket keys have no exact grade mapping — map `middle_school` → 6–9, `high_school` → 10–13 where the subject's level allows, `college/adult` → `other`, `primary` → 1–5.
5. `student_profiles.grade_level` → `grade_id`: `primary` → 5, `middle_school` → 9, `high_school` → 11, `college/adult` → `other` (guardians can correct it during onboarding).
6. `bookings.learner_grade`: same mapping to `learner_grade_id`, old column kept for history.

---

## 6. Teacher side — "I am an O/L Mathematics teacher"

### 6.1 New assignment flow

Current two-step flow in [teacher/subjects.blade.php](../resources/views/teacher/subjects.blade.php) becomes:

1. **Step 1 — Subject picker grouped by level.** Checkboxes render under four group headers: *Primary · O/L · A/L · Other*. A teacher checks **"O/L Mathematics"** — one entity, no ambiguity.
2. **Step 2 — Per-subject panel (auto-populated).** When a subject is newly selected, the platform **auto-assigns every grade of that level and every lesson of those grades** (the user's requirement: selecting "O/L Mathematics" assigns O/L grades 6–11 and all their lessons). The panel then shows:
   - **Grades** 6–11 as chips/checkboxes (default all on) — e.g. "I only teach Grades 6–9" → uncheck 10, 11 and their lessons unassign automatically.
   - **Lessons grouped by grade** (collapsible per grade), all checked by default; teacher prunes the ones they don't cover.
   - **Rate per grade**: every grade chip carries an hourly rate input, so the price can change grade by grade (e.g. Grade 6 → 1,500, Grade 11 → 2,500). A grade left empty falls back to the subject default rate (`teacher_subjects.rate_per_hour_minor`), which in turn falls back to the teacher's base rate. Public surfaces (subject pages, teacher profile, directory, booking flow, request inbox) resolve the rate through `TeacherProfile::effectiveRateFor($subject, $gradeId)` so the selected grade always shows its own price.
3. **Validation** ([SubjectSetupRequest.php](../app/Http/Requests/SubjectSetupRequest.php)): `grades` must be valid grade ids of the subject's level; `lessons` must belong to the subject **and** to one of the selected grades. Storing grade ids (JSON in `teacher_subjects.grade_levels`) + lesson ids (new `teacher_lessons` pivot).

### 6.2 Controller changes

[Teacher/SubjectsController.php](../app/Http/Controllers/Teacher/SubjectsController.php):

- `index`: load levels → subjects (with `educationLevel`) → lessons grouped by grade; existing selections for pre-checking.
- `store`: sync subjects; **on first selection of a subject, auto-create `teacher_lessons` rows for all active lessons in the level's grades and set `grade_levels` to all grade ids of that level** (served default, not forced — teacher can prune).
- `update`: sync lessons + grades with the cross-validation above; removing a grade detaches that grade's lessons automatically.
- Detach-on-subject-removal logic updated for `teacher_lessons`.

### 6.3 Public teacher profile

[resources/views/teachers/show.blade.php](../resources/views/teachers/show.blade.php) shows "O/L Mathematics · Grades 6–9" and lessons grouped by grade under each subject — replacing the bucket labels (`$gradeLevels[$level]` lookup at line ~95).

---

## 7. Student side — grade-aware, no wrong lessons

### 7.1 Onboarding

[StudentProfileController/Request/View](../resources/views/student/profile.blade.php): replace the single bucket select with a cascading **Level → Grade** picker (Primary → 1–5, O/L → 6–11, A/L → 12–13, Other). Store `grade_id`. The onboarding gate (`onboarded` middleware) already exists; `grade_id` becomes required to complete onboarding.

### 7.2 Interests

[Student/InterestsController.php](../app/Http/Controllers/Student/InterestsController.php) + [student/interests.blade.php](../resources/views/student/interests.blade.php):

- Subjects shown = subjects of the student's **level** only.
- Lessons shown = lessons of the student's **grade** only (grouped under each selected subject).
- Validation drops topics not matching the student's grade (mirror of the current subject-scoped filter).

### 7.3 Tutoring request

[Student/TutoringRequestController.php](../app/Http/Controllers/Student/TutoringRequestController.php) + [TutoringRequest.php](../app/Models/TutoringRequest.php):

- `grade_id` is locked to the student's profile grade — the form shows it read-only with a change-grade link, and any posted `grade_id` is ignored (the same lock as `learner_grade_id` on bookings), so every request is matched at the registered grade.
- Subject dropdown limited to the request grade's level; **lesson dropdown limited to that grade** — the student physically cannot pick a Grade 11 lesson for a Grade 8 request.
- AI classification (step 1 of the flow) is constrained to the same scope.

### 7.4 Booking & directory

- [student/bookings/create.blade.php](../resources/views/student/bookings/create.blade.php): lesson picker filtered by the teacher's lessons **for the learner's grade**; `learner_grade_id` is locked to the student profile — the page shows it read-only with a change-grade link and ignores any `learner_grade_id` in the request.
- Teacher directory ([teachers/](../resources/views/teachers/)): filters become Level → Grade → Subject → Lesson, cascading; URL query filters match [TeacherSearch.php](../app/Services/TeacherSearch.php).
- Signed-in students get a grade-matched finder ([student/find-teacher.blade.php](../resources/views/student/find-teacher.blade.php), `/student/find-a-teacher`) that always scopes to the profile grade and keeps the filters simple: subject, language and a concrete date plus time window. "Earliest availability" sorts by the next bookable slot, computed through [SlotService.php](../app/Services/SlotService.php) (weekly ranges, time off and live holds included).
- The public teacher profile ([teachers/show.blade.php](../resources/views/teachers/show.blade.php)) stays for guests and other roles; students are redirected into the workspace copy ([student/teacher.blade.php](../resources/views/student/teacher.blade.php), `/student/teachers/{teacher}`) where subjects, lessons and rates are scoped to their profile grade ("Grade X lessons & rates", booking CTAs beside the availability list, notice + finder link when the teacher does not take the grade). No student workspace view links out to the public pages.
- Teacher cards show "Teaches Grade 6–9 · 45 lessons" so a student skimming the directory immediately sees relevance.

---

## 8. Matching, AI & search

### 8.1 RequestMatcher

[RequestMatcher.php](../app/Services/RequestMatcher.php):

- `teachersFor()` / `inboxFor()` match on `teacher_lessons.lesson_id = request.lesson_id` **and** `JSON_CONTAINS(teacher_subjects.grade_levels, request.grade_id)` (or the portable `LIKE '%"<id>"%'` used in TeacherSearch today).
- Grade-mismatch teachers never see the request in their inbox.

### 8.2 AI classifier

[OpenAITopicClassifier.php](../app/Services/AI/OpenAITopicClassifier.php) → `OpenAILessonClassifier` (class rename; keep `TopicClassifier` contract or rename to `LessonClassifier` — mechanical, binding in [AppServiceProvider](../app/Providers/AppServiceProvider.php)):

- The system prompt receives the request's **grade** and the catalog of that grade's lessons **only**, so the model can never suggest an out-of-grade lesson.
- Output maps to `lesson_id` within the grade scope; `ClassificationResult` unchanged in shape.

### 8.3 TeacherSearch

[TeacherSearch.php](../app/Services/TeacherSearch.php) + [TeacherSearchRequest.php](../app/Http/Requests/TeacherSearchRequest.php):

- Filters: `level` (via `subjects.education_level_id`), `grade` (JSON contains grade id), `subject_id`, `lesson_id`.
- Sort relevance: teachers covering the exact lesson+grade first, then same grade, then level.

---

## 9. Phased implementation plan

Each phase ends with the full Pest suite green (current standard: suite in `tests/Feature`, run via `php artisan test`).

### Phase A — Taxonomy foundation (schema + models + seed) ✅

> **Implemented.** What shipped: the seven migrations (levels, grades, subject level,
> topics→lessons rename with canonical FK/index names on MySQL/MariaDB and SQLite,
> `student_profiles.grade_id`, `bookings.learner_grade_id`, curriculum backfill);
> `EducationLevel`/`Grade`/`Lesson` models with factories; the `education_levels`
> config and `EducationLevelSeeder`; the Sri Lankan `CatalogSeeder` (27 subjects,
> 748 placeholder lessons, O/L Mathematics 12 lessons in Grade 6 and 10 in Grade 7 —
> names and counts to be replaced by the admin); `CatalogService` v2 cache keys; the
> full `topic(s)` → `lesson(s)` rename (100 files); and grade-aware plumbing so every
> existing flow keeps working (teacher setup stores grade ids as strings, student
> profile takes `grade_id`, requests/bookings carry grade ids, directory filters by
> grade id).
>
> **Deferred to later phases (as planned):** admin grade-tab matrix + bulk copy (B),
> teacher auto-assignment on first subject selection (C), strict grade filtering on
> student/booking screens (D), grade-scoped AI prompt + matcher grade check (E),
> dropping the legacy `grade_level`/`learner_grade` columns (F).
>
> **Verified:** `vendor/bin/pint` clean; `php artisan test` → 527 passed, 1 failed
> (pre-existing `MailBrandingTest` branding mismatch, unrelated); `migrate:fresh --seed`
> on MariaDB and SQLite builds the full curriculum; the additive `php artisan migrate`
> upgrade path was exercised on the existing dev database.

1. Migrations §3.1–3.7 + data migration §5.4.
2. New models `EducationLevel`, `Grade`, `Lesson`; update `Subject`, `TeacherProfile`, `StudentProfile`, `TutoringRequest`, `User`.
3. Config `education_levels` (§4.3); rewrite [CatalogSeeder](../database/seeders/CatalogSeeder.php) with the Sri Lankan curriculum; add level/grades to [DemoDataSeeder.php](../database/seeders/DemoDataSeeder.php) (update its `grade_levels` arrays at lines 84–152).
4. Update [CatalogService](../app/Services/CatalogService.php) cache payload + keys; update `CatalogTest` and `SubjectManagementTest` expectations.
5. **Do the mechanical rename `topic(s)` → `lesson(s)` across `app/`, `resources/`, `routes/web.php`, `database/`, `tests/`** — full touch list:
   - Models: `Topic` → `Lesson`; relations `topics()` → `lessons()`; `TeacherSubject` pivot cast comment.
   - Routes/controllers: `Admin/TopicController` → `Admin/LessonController`; `teacher.topics`, request params `topics[]` → `lessons[]`.
   - Views: [teacher/subjects.blade.php](../resources/views/teacher/subjects.blade.php), [student/interests.blade.php](../resources/views/student/interests.blade.php), [student/bookings/create.blade.php](../resources/views/student/bookings/create.blade.php), [catalog](../resources/views/catalog/), [teachers/](../resources/views/teachers/) (index/show), admin subject views, and any `topic` labels in components.
   - Services: `RequestMatcher`, `TeacherSearch`, `AI/OpenAITopicClassifier`, `RequestResponseService` (any topic references), admin CSV exporter.
   - Requests: `TopicRequest` → `LessonRequest`, `InterestsRequest`, `SubjectSetupRequest`, `TeachingSetupRequest`, `StoreBookingRequest`.
   - Tests: update all references (list in §10).
6. **Gate:** `php artisan test` green; `php artisan migrate:fresh --seed` builds a browseable curriculum.

### Phase B — Admin curriculum management ✅

> **Implemented.** What shipped: the `/admin/curriculum` education-levels page
> (`Admin/EducationLevelController` + `EducationLevelRequest`, grade chips and
> per-level subject counts, inline level updates); the subject catalog page gains
> level filter chips (with counts) and level-aware create; `Admin/SubjectController`
> passes `grades`/`lessonsByGrade`/`selectedGradeId` to the edit screen;
> `Admin/LessonController` was rebuilt with grade-scoped CRUD, `copy` (duplicate a
> grade's lessons into another grade, skipping existing slugs), `bulk-active`
> (activate/deactivate a grade's lessons) and `move` (reorder, renumber 1..n) —
> all logged via `ActivityLogger`, all redirecting back to the edited grade
> (`?grade=` pre-selects the tab); `admin/subjects/edit` is now a **grade-tab
> matrix** (Alpine tabs per grade with lesson counts, add-lesson form bound to the
> active grade, copy tool, per-lesson inline name/description/sort/active/grade
> edits, row remove with confirm); nav gains the "Curriculum" item.
>
> **Bug fixed along the way:** nullable slugs skipped the `unique` validation rule,
> so duplicate names surfaced as a 500 from the DB constraint — `LessonRequest` and
> `SubjectRequest` now derive the slug in `prepareForValidation()`.
>
> **Deferred to later phases (as planned):** teacher auto-assignment on first
> subject selection (C), strict grade filtering on student/booking screens (D),
> grade-scoped AI prompt + matcher grade check (E), dropping the legacy
> `grade_level`/`learner_grade` columns (F).
>
> **Verified:** new `Admin/CurriculumLevelTest` (7 tests) and `Admin/GradeLessonTest`
> (10 tests) plus extended `SubjectManagementTest` all pass; `vendor/bin/pint` clean;
> `php artisan test` → 546 passed, 1 failed (pre-existing `MailBrandingTest`, unrelated);
> the whole admin flow was exercised in a live browser session (levels page, level
> filter, grade tabs, add lesson in the selected grade, grade-aware redirect,
> remove lesson).

1. `Admin/EducationLevelController` + level index view.
2. `Admin/SubjectController` level-aware list/create/edit; `Admin/LessonController` grade-scoped CRUD + `copyFromGrade`.
3. Grade-tab matrix editor in `admin/subjects/edit` (tabs per grade, lesson counts, bulk copy/activate).
4. Admin nav + `LogAdminActivity` entries ("curriculum-level-updated", "lesson-created", "lessons-copied").
5. Tests: new `Admin/CurriculumLevelTest`, `Admin/GradeLessonTest` (copy, unique slug per grade, grade-scoped validation), update `Admin/SubjectManagementTest`.

### Phase C — Teacher assignment ("O/L Mathematics teacher") ✅

> **Implemented.** What shipped: picking a subject in step 1 now **auto-assigns its
> full scope** — every active grade of the subject's level and every active lesson
> in those grades (`SubjectsController@store`, re-added subjects re-assign);
> the per-subject panel renders grades as Alpine-wired chips and lessons grouped by
> grade, and unchecking a grade disables its lesson checkboxes so saving detaches
> that grade's lessons (`update` keeps only lessons in the submitted grades; the
> `sync_grades` marker makes "no grades" authoritative). The public teacher profile
> shows the level under the subject name and collapses the teacher's grade scope
> into one label — "Grades 6-9" for a contiguous run, the grade list otherwise —
> with lessons grouped by grade.
>
> **Real bug found by browser verification:** subject slugs were only unique per
> level, so with the seeded catalog four "mathematics" subjects existed and every
> `{subject}` URL (teacher, admin, catalog) bound the *first* match — an O/L
> teacher's save silently validated against Primary Mathematics. Fixed by making
> slugs globally unique (`2026_10_08_000008_uniquify_subject_slugs` de-duplicates
> existing rows by prefixing the level key, e.g. `ol-mathematics`; seeder and
> `SubjectRequest` apply the same rule; `CatalogService` keys bumped to v3).
>
> **Deferred to later phases (as planned):** strict grade filtering on
> student/booking screens (D), grade-scoped AI prompt + matcher grade check (E),
> dropping the legacy `grade_level`/`learner_grade` columns (F).
>
> **Verified:** new `Teacher/GradeLessonAssignmentTest` (auto-assign on select,
> keep pruned selections on re-save, prune grade → detach, uncheck all grades →
> detach, cross-level lesson rejected, partial update safe, re-add re-assigns),
> extended `TeachingSetupTest` (slug-targeting regression, cross-level grade
> rejection) and `SubjectManagementTest` (slug prefixing, cross-level duplicate
> rejected, DB-level uniqueness); `vendor/bin/pint` clean; `php artisan test` →
> 557 passed, 1 failed (pre-existing `MailBrandingTest`, unrelated). The whole
> teacher flow was exercised in a live browser on a seeded scratch database,
> including the fix simulation (rollback → duplicate slugs → `migrate` re-prefixes)
> on SQLite and MariaDB.

1. Teacher subjects view grouped by level; per-subject panel with grade chips + lessons grouped by grade; auto-assign defaults on first selection.
2. `Teacher/SubjectsController` store/update with auto-assignment + grade/lesson cross-validation (`SubjectSetupRequest`).
3. Public teacher profile renders grade ranges + per-grade lessons.
4. Tests: update `Teacher/TeachingSetupTest`, `Teacher/TeacherOnboardingFlowTest`; new `Teacher/GradeLessonAssignmentTest` (auto-assign on select, prune grade → lessons detached, cross-level lesson rejected).

### Phase D — Student side (grade-aware) ✅

> **Implemented.** What shipped: the **interests page** only offers the student's
> level subjects and their grade's lessons (with a grade chip and tailored copy;
> saving silently drops lessons outside the selected subjects or the student's
> grade, and subjects from another level are rejected); the **request form**
> gains a learner-grade picker defaulting to the profile grade, `TutoringRequest`
> stores it, and the request page's subject and lesson pickers are scoped to that
> grade — the student physically cannot confirm a Grade 11 lesson on a Grade 8
> request (validation enforces it server-side too); the **booking page** offers
> only the teacher's lessons for the learner's grade (defaulting to the profile
> grade), changing the learner grade re-filters the list, and posting a lesson
> for a different grade is rejected. `learner_grade_id` on bookings already
> defaulted from the profile since Phase A.
>
> **Also in place from earlier phases:** `student_profiles.grade_id` is required
> by onboarding and the profile picker groups grades under Primary · O/L · A/L ·
> Other.
>
> **Deferred to later phases (as planned):** grade-scoped AI classification
> prompt (the AI can still *suggest* an out-of-grade lesson until Phase E — the
> student simply cannot confirm it), `RequestMatcher` grade checks, directory
> cascade, and the Phase F cleanup of legacy columns.
>
> **Verified:** `InterestsTest` reworked around grade-coherent data (+ cross-grade
> drop, cross-level rejection), new `Student/GradeScopedCatalogTest` (interests
> and request pages leak no other-level subject or other-grade lesson; request
> lesson confirm rejected/written correctly), `DirectBookingTest` scenario made
> grade-coherent (+ page shows only learner-grade lessons, switching grade
> re-filters, mismatched lesson rejected), `RequestSubmissionTest` covers the
> stored learner grade; `vendor/bin/pint` clean; `php artisan test` → 565 passed,
> 1 failed (pre-existing `MailBrandingTest`, unrelated). The whole student flow
> was exercised in a live browser on a seeded scratch database (interests → save;
> request create → failed classification → manual lesson confirm; booking page
> grade switch).

1. Student profile: cascading Level → Grade picker; `grade_id` in [StudentProfileRequest](../app/Http/Requests/StudentProfileRequest.php) and onboarding gate.
2. Interests filtered to level + grade; request form scoped subject→lesson by grade; `TutoringRequest` stores `grade_id` + `lesson_id`.
3. Booking create: lesson picker by learner grade; `learner_grade_id` default from profile.
4. Tests: update `Student/StudentProfileTest`, `Student/InterestsTest`, `Student/RequestSubmissionTest`, `Booking/DirectBookingTest`; new `Student/GradeScopedCatalogTest`.

### Phase E — Matching, AI, search ✅

> **Implemented.** What shipped:
>
> **`RequestMatcher`** — `teachersFor()` now requires the teacher's pivot row for
> the request's subject to cover the request's grade (`grade_levels` is a JSON
> array of grade ids; the quoted `LIKE '%"6"%'` keeps "6" from matching "16"), and
> `inboxFor()` reads every subject's grade scope off the teacher's pivots and drops
> any request the teacher does not teach the grade for. A teacher with **no**
> grades checked for a subject no longer receives that subject's graded requests
> (the checkbox state is authoritative); requests without a grade — legacy rows and
> anything created before the student picked one — keep matching everyone, so
> nothing disappears from an existing inbox.
>
> **AI classifier** — `OpenAILessonClassifier`'s system prompt is now built from
> the request's grade: only the subjects of that grade's education level and only
> that grade's lessons, with an explicit "the student is in Grade 6" instruction,
> and `matchSubject()`/`matchLesson()` re-apply the same scope when mapping the
> answer back (the old subject-less lesson fallback used to drop any grade
> constraint — fixed). `ClassifyTutoringRequestJob` keys its result cache by
> `grade_id + image hash` so one image can no longer leak a Grade 7 answer into a
> Grade 6 request, and refuses to publish a lesson whose grade contradicts the
> student's grade (low-confidence instead).
>
> **`TeacherSearch` / `TeacherSearchRequest`** — new `level` filter (subject's
> education level, validated against the active level keys); the `lesson` filter
> takes a **lesson id** rather than a lesson slug because slugs repeat per grade
> ("unit-01" exists in every subject/grade); teachers are loaded with an active
> lesson count, and the default "top rated" sort gets a grade-coverage tiebreaker
> when a grade filter is active, so teachers who actually teach that grade rank
> above teachers who only matched on the subject pivot.
>
> **Directory UI** — a Level select leads the filters and cascades server-side
> (changing it clears subject/lesson/grade and re-submits; changing subject clears
> the lesson). Subject options are scoped to the chosen level, lesson options to
> the chosen subject, grade options to the level or to the grades the chosen
> subject's lessons run in, and every lesson option carries its grade label. Cards
> now show what the teacher actually covers: "Grades 6-11 · 12 lessons".
>
> **Deferred to Phase F:** dropping the legacy `student_profiles.grade_level` /
> `bookings.learner_grade` columns, the docs/UAT sweep.
>
> **Verified:** new `Matching/GradeMatchTest` (grade-mismatched teacher is not
> matched and stays out of the inbox; matching-grade teacher is; grade-less request
> still matches) and `TeacherDiscoveryTest` additions (level filter + scoped
> pickers, grade-coverage ordering, lesson filter by id, unknown level rejected);
> `RequestClassificationTest` gained grade-scope coverage (prompt offers only the
> student's grade's lessons, an out-of-grade answer is refused, the cache is
> per-grade, the job never publishes a contradictory lesson); `RequestInboxTest`
> and `NotificationDeliveryTest` fixtures were made grade-coherent (they paired a
> Grade 11 pivot with a random-grade lesson, which the new grade check would have
> rejected); `vendor/bin/pint` clean; `php artisan test` → 576 passed, 1 failed
> (pre-existing `MailBrandingTest`, unrelated). Verified in a live browser on a
> seeded scratch database: the directory cascade (level → subject → lesson/grade,
> card meta) and the teacher inbox/services badge with grade-scoped requests.

1. `RequestMatcher` lesson+grade matching (JSON contains grade id).
2. `OpenAILessonClassifier` grade-scoped prompt.
3. `TeacherSearch` level/grade/lesson filters + cascade UI in directory.
4. Tests: update `Teacher/RequestInboxTest`, `Student/RequestClassificationTest`, `TeacherDiscoveryTest`; new `Matching/GradeMatchTest` (grade mismatch never matched).

### Phase F — Cleanup, docs, UAT ✅

> **Implemented.** What shipped:
>
> **Legacy columns dropped** — `2026_10_08_000009_drop_legacy_grade_columns`
> removes `student_profiles.grade_level` and `bookings.learner_grade` (guarded
> with `Schema::hasColumn`, re-added in `down()`), and both models lost the
> corresponding fillable entries, so a grade id is now the only source of learner
> grade on profiles and bookings. `config('studylikepro.grade_levels')` was already
> deleted in Phase A; a repo-wide grep for `grade_level` / `learner_grade` outside
> the original create-table migrations and the new pivot column returns nothing
> (the pivot's `grade_levels` JSON now holds grade ids and stays, as planned).
>
> **Docs** — [overview.md](./overview.md) gained the grade/lesson bullets,
> [implementation-plan.md](./implementation-plan.md) gained the **Phase 12** entry
> (roadmap, milestones, scope traceability, data-model and integration tables
> updated to levels/grades/lessons, with a naming note that phases 0–11 describe
> the pre-curriculum flat catalog), [uat-checklist.md](./uat-checklist.md) gained
> **section G — Curriculum, grades & lessons** (admin matrix with 12/10/… lesson
> counts, teacher auto-assign, grade-scoped inbox/student pickers, "a Grade 11-only
> teacher never sees a Grade 8 request") and the old topic wording was corrected,
> [README.md](../README.md) gained a "Curriculum & grades" section plus the Phase 12
> status, and [.agents/AGENTS.md](../.agents/AGENTS.md) now states the taxonomy rules
> so future work cannot reintroduce a flat subject→topic list.
>
> **Two demo-seeder bugs found by running it end to end** (it is commented out of
> `DatabaseSeeder`, so CI had never executed it): `seedReviewHistory()` referenced
> an undefined `$chemistry`, and `subjectFor()` looked subjects up by plain slug
> only, so after the global slug change `('ol', 'mathematics')` silently resolved
> **Primary** Mathematics. Both fixed: the lookup now tries both the plain and the
> level-prefixed slug scoped to the requested level, and the demo teacher's second
> subject is O/L Science (the catalog has no O/L Physics) so the demo data
> (Grade 11 student, grades 6–11 teacher scope) is coherent.
>
> **Verified:** new `tests/Feature/CurriculumSchemaTest.php` locks the dropped
> columns and the removed fillables; full suite green except the pre-existing
> `MailBrandingTest`; `vendor/bin/pint` clean; the drop migration applied,
> rolled back (columns restored) and re-applied on MariaDB 10.4 and SQLite;
> `migrate:fresh --seed` + `db:seed --class=DemoDataSeeder` run clean and were
> walked in a browser — `/admin/curriculum` (4 levels with counts), the O/L
> Mathematics matrix (Grade 6 → 12 lessons, Grade 7 → 10, …), the teacher's
> grade-grouped lesson panel, the Grade 6 request in the grade-scoped inbox, the
> Grade 11 student's interests page (O/L subjects + Grade 11 lessons only) and
> `/teachers?level=ol` (O/L subjects/grades only, card meta "Grades 6-11 · 12
> lessons").

1. Drop legacy columns (`student_profiles.grade_level`, `bookings.learner_grade`), remove config `grade_levels` shims; full `grade_levels` grep returns nothing.
2. Update [DOC/overview.md](./overview.md), [DOC/implementation-plan.md](./implementation-plan.md) (add Phase 12 entry), [DOC/uat-checklist.md](./uat-checklist.md) (curriculum scenarios), [README.md](../README.md), `.agents/AGENTS.md` subject/topic references.
3. UAT scenarios: admin creates "O/L Mathematics" with 12/10/… lessons per grade; teacher checks "O/L Mathematics" and sees grades 6–11 + lessons; Grade 8 student sees only Grade 8 lessons; Grade 8 request never reaches Grade 11-only teacher.

**Rough effort:** Phase A 4–5 days · B 3–4 days · C 2–3 days · D 3 days · E 2–3 days · F 1–2 days → ~3 weeks for one developer.

---

## 10. Test plan

**Update (existing):**

| Test file | Why |
| --- | --- |
| `tests/Feature/CatalogTest.php` | subjects now carry level; lessons grouped by grade |
| `tests/Feature/Admin/SubjectManagementTest.php` | level-aware admin CRUD |
| `tests/Feature/Teacher/TeachingSetupTest.php` | lessons + grade ids instead of topics + buckets |
| `tests/Feature/Teacher/TeacherOnboardingFlowTest.php` | auto-assignment defaults |
| `tests/Feature/Student/InterestsTest.php` | grade-scoped interests |
| `tests/Feature/Student/StudentProfileTest.php` | grade_id onboarding |
| `tests/Feature/Student/RequestSubmissionTest.php` | grade + lesson on requests |
| `tests/Feature/Student/RequestClassificationTest.php` | grade-scoped classifier |
| `tests/Feature/Teacher/RequestInboxTest.php` | grade-aware inbox |
| `tests/Feature/TeacherDiscoveryTest.php` | level/grade/lesson filters |
| `tests/Feature/Booking/DirectBookingTest.php` | lesson picker by learner grade |
| `tests/Feature/Acceptance/CoreFlowTest.php` | end-to-end with grades |

**New:** `Feature/Admin/CurriculumLevelTest.php`, `Feature/Admin/GradeLessonTest.php`, `Feature/Teacher/GradeLessonAssignmentTest.php`, `Feature/Student/GradeScopedCatalogTest.php`, `Feature/Matching/GradeMatchTest.php`, `Feature/CurriculumSchemaTest.php` (legacy columns gone, legacy attributes no longer fillable).

**Key assertions:** Grade 8 student can only submit a Grade 8 lesson request · selecting "O/L Mathematics" auto-assigns 6 grades + all lessons · unchecking Grade 10 detaches Grade 10 lessons · copying Grade 6 lessons to Grade 7 yields valid rows · a Grade 11-only teacher never receives a Grade 8 request.

---

## 11. Risks & decisions

| # | Decision / risk | Mitigation |
| --- | --- | --- |
| 1 | **Subject-per-level rows** (three "Mathematics" rows) vs. one subject with level pivots. Chose subject-per-level: matches the teacher's mental model ("O/L Mathematics"), simplifies assignment, rates and teacher pivots. | "Copy subject to another level" admin helper later if repetition annoys. |
| 2 | **Renaming topics → lessons** is a large mechanical refactor touching ~40 files. | Phase A does it in one pass with a grep checklist; tests are the safety net; rename is done before any new features. |
| 3 | **`teacher_subjects.grade_levels` JSON semantics change** (config keys → grade ids). | Data migration in §5.4; JSON `LIKE` search pattern updated; pivot cast already array. |
| 4 | **Existing topics have no grade.** | Backfill to all grades of the level (canonical copy in lowest grade), admin prunes with bulk tools. |
| 5 | **A/L streams** (Bio/Maths/Commerce/Arts) differ per grade 12/13. | Out of scope for this phase; level+grade model already accommodates it (stream would be a future `subject_stream_id`). |
| 6 | **Exact syllabus lesson names** must not be invented by seeders. | Seeders ship placeholders + counts; real names entered via admin matrix or a future CSV import. |
| 7 | **Terminology collision** ("lessons" already means booked sessions in student UI). | Context split: catalog/admin/teacher = curriculum lessons; student journey keeps "booked lessons" copy with clarified labels ("Lesson: Fractions — Grade 8"). |
| 8 | **Cache invalidation** — catalog is cached forever in `CatalogService`. | New cache keys + flush in every admin curriculum write path. |

---

## 12. Out of scope (future)

- A/L streams (Biology/Maths/Commerce/Arts) as a first-class entity.
- National exam support (O/L 2026 intake labels, exam-topic tagging, past-paper linking).
- CSV/bulk syllabus import into the matrix editor.
- Student year-group promotion (auto-advancing a student from Grade 8 to 9 each January).

---

## 13. O/L basket subjects — Categories I, II & III (addendum)

**Status:** implemented. Alongside the mandatory subjects, an O/L candidate in Grades 10-11
takes **one optional subject from each of the three baskets** ("baskets"/categories) the
Department of Examinations defines.

| Basket | Key | Starter catalog |
| --- | --- | --- |
| Category I | `category_1` | Art · Eastern, Western and Carnatic Music · Eastern and Bharatha Dancing · English, Sinhala, Tamil and Arabic Literature · Drama & Theatre |
| Category II | `category_2` | ICT · Agriculture & Food Technology · Aquatic Bioresources Technology · Arts & Crafts · Home Economics · Health & Physical Education · Communication & Media Studies · Design & Construction, Mechanical, Electrical & Electronic Technology · Electronic Writing & Shorthand |
| Category III | `category_3` | Commerce (the Business & Accounting Studies slot) · Geography · Civic Education · Entrepreneurship Studies · Second Language Sinhala/Tamil · Pali · Sanskrit · French · German · Hindi · Japanese · Arabic |

### 13.1 Data model

```
EducationLevel
   ├── hasMany SubjectBasket        (level "ol" → the three categories)
   └── hasMany Subject ──belongsTo── SubjectBasket   (nullable: mandatory subjects have none)
```

| Artifact | Change |
| --- | --- |
| `subject_baskets` | New table: `education_level_id`, `key`, `name`, `description`, `icon`, `sort_order`, `is_active`; unique `(education_level_id, key)` — `2026_10_08_000011` (description added by `2026_10_08_000014`, which also backfills the seeded text — the baskets are created before that column exists) |
| `subjects.basket_id` | Nullable FK → `subject_baskets`, `nullOnDelete` (deleting a basket detaches, never deletes, its subjects) — `2026_10_08_000012` |
| `2026_10_08_000013_backfill_subject_baskets` | Creates the baskets of every level that defines them and maps the O/L subjects that already existed: `ict`, `health-physical-education` → II; `geography`, `commerce` → III |
| [config/studylikepro.php](../config/studylikepro.php) | `education_levels.ol.baskets` (keys, names, the one-line descriptions students see, icons) and `education_levels.ol.basket_grades` = `[10, 11]` — the config stays the reference the seeder and the backfill read |
| [SubjectBasketSeeder](../database/seeders/SubjectBasketSeeder.php) | Inserts missing baskets from config and leaves admin renames alone; safe to re-run |
| [CatalogSeeder](../database/seeders/CatalogSeeder.php) | Ships the official basket subject list (Grades 10-11, placeholder lessons), assigns each subject to its basket, and never overwrites an admin's basket choice |
| [CatalogService](../app/Services/CatalogService.php) | Levels payload carries `subjectBaskets`, subjects payload carries `basket`; `groupByBasket()` is the one grouping the public catalog and the student interests page both render; the cache stores plain attribute rows that are rehydrated on read (the seeded catalog was ~2 MB as serialized models — more than a database cache store can write where MySQL keeps the legacy 1 MB `max_allowed_packet` — and a few hundred KB as rows); cache keys bumped to `v6`, and `AppServiceProvider` flushes the cache on `SubjectBasket` writes like it already did for levels, subjects and lessons |

### 13.2 Admin

- **Curriculum page** (`/admin/curriculum`): a level with baskets lists each one with its subject count and an inline form (name, description shown to students, icon, sort order, active) — `SubjectBasketController@update`, `SubjectBasketRequest`.
- **Subject create/edit forms**: a "Subject basket" select limited to the subject's own level (`SubjectRequest` rejects a basket of another level); the catalog list shows the basket as a chip. "None" keeps the subject mandatory.

### 13.3 Public catalog

Step 3 of `/subjects` (after level and grade) groups the subject cards for the O/L basket grades: **Compulsory subjects** (chip *all students*) first, then **🎨 Category I · 🛠️ Category II · 📚 Category III** (chip *pick one*, each with its description), plus a line explaining the exam rule. Below Grade 10 the subjects are simply compulsory, so the grid stays flat and a note says in which grades the baskets begin — the same subject (e.g. ICT) is therefore never presented as a choice before the choice exists. A basket subject's own page carries a "Category II · pick one" chip.

### 13.4 Student

- The interests page groups a Grade 10-11 student's subjects into **Category I · Category II · Category III · Compulsory subjects** (the same order and chips as the catalog); checking a subject unchecks its basket siblings (Alpine) and the card explains the rule.
- Saving is limited server-side to **one subject per basket** (`InterestsRequest`); the remaining subjects stay free to choose. An empty basket never blocks the save — the page then shows a reminder naming the baskets left empty.
- Below Grade 10, and in levels without baskets, nothing changes: one flat list, no limit.

### 13.5 Verified

`tests/Feature/Student/BasketSubjectsTest.php` (grouping above/below Grade 10, one-per-basket save, second pick in a basket rejected, reminder for an incomplete choice), `tests/Feature/Admin/SubjectBasketTest.php` (basket listing, rename/reorder/description/toggle, subject assignment, cross-level basket rejected, non-admin forbidden, and a basket edit reaching the public catalog immediately — the cache flush) and `tests/Feature/CatalogTest.php` (basket subjects seeded into Grades 10-11 only, existing subjects mapped, seeder idempotent, step 3 grouped for Grade 10 and flat with the "baskets begin" note for Grade 6, category chip on a basket subject page). On the MySQL dev database `migrate` creates the three baskets and `db:seed --class=CatalogSeeder` adds the 35 basket subjects (11 · 11 · 13).

Browser verification on the seeded catalog (`CACHE_STORE=database`) surfaced a real bug: with every lesson included, the cached catalog serialized to ~2 MB and the cache write died with `1153 Got a packet bigger than 'max_allowed_packet' bytes` (the local MySQL allows 1 MB), so the interests page returned a 500. `CatalogService` now caches raw attribute rows and rehydrates the models on read — same API, ~350 KB per entry — and the page works again.
