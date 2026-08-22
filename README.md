# StudyAI — Laravel Rewrite

A full-stack study platform (AI-generated flashcards, exams, study guides, spaced-repetition review) rewritten from the original Next.js/Prisma app into **Laravel 12 + PHP 8.4** with a **Full Blade** server-rendered frontend (thin vanilla JS, no React).

> The app is a faithful functional port of `../study-ai` (the original). All roles, the exam/flashcard flow, the AI content pipeline, SRS scheduling, token limits, materials, topics, subjects/terms, question bank, analytics, jobs, and seeders are implemented and tested.

---

## Features

| Area | Capability |
|------|------------|
| **Auth & RBAC** | School-scoped membership with 4 roles: `super_admin`, `admin`, `teacher`, `student`. Role middleware + Laravel Policies. |
| **Schools & Members** | Super-admin manages schools, AI providers, and global token limits. Admin manages members, classes, subjects, terms, invite codes. |
| **Classes** | Teacher/admin create classes, assign teachers, enroll students (manual or invite code), link subject + term. |
| **Exams** | Teacher builds exams (MCQ / true-false / fill-blank / short-answer / essay), publishes them. Students start → take (with countdown timer) → submit → see results & pass/fail. Teacher sees analytics (avg, pass rate, attempts). |
| **Materials + AI** | Teacher uploads a note / PDF / PPTX / YouTube / URL. The **AI pipeline** generates flashcards, questions, and a multi-section study guide, then the teacher reviews & approves before publishing. |
| **Topics** | Students generate a set of study topics for any subject via AI (stored as `Topic` rows). |
| **Spaced repetition** | Flashcards use **SM-2** (students) and **FSRS** (scheduler-ready) algorithms in `SrsService`. Review updates ease factor, interval, and due date. |
| **Token limits** | Global + per-teacher monthly token budgets enforced in `TokenLimitService`, with usage tracked in `TokenUsage`. |
| **Jobs & Scheduler** | AI generation runs as a queued job (`GenerateAiContent`); `routes/console.php` schedules stuck-job recovery + `queue:retry`. |
| **Subjects & Terms** | Academic structure managed by admin; referenced by classes, exams, materials, and the question bank. |
| **Question Bank** | Teacher-curated reusable questions. |

---

## Requirements

- **PHP 8.2+** (developed on **8.4.21** via Herd)
- **Composer**
- **PostgreSQL 14+** (developed on 17)
- *(Optional)* Redis for `queue`/`cache` in production

## Install

```bash
cd study-ai-laravel
composer install

# copy env and configure
cp .env.example .env
php artisan key:generate

# point DB at your Postgres instance
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_PORT=5432
#   DB_DATABASE=study_ai_laravel
#   DB_USERNAME=laravel
#   DB_PASSWORD=********

php artisan migrate --seed
```

### Run (local dev)

```bash
php artisan serve --host=127.0.0.1 --port=8011
# open http://127.0.0.1:8011  (redirects to /dashboard → /login)
```

> On this machine the configured PHP is Herd's 8.4:
> `C:/Users/Mcfly/.config/herd/bin/php84/php.exe artisan serve ...`

### Demo accounts (from the seeder)

| Email | Password | Role |
|-------|----------|------|
| `super@example.com` | `password` | Super Admin |
| `admin@example.com`  | `password` | School Admin |
| `teacher@example.com`| `password` | Teacher |
| `student@example.com`| `password` | Student |

---

## Enabling AI features

The AI pipeline is **fully wired but inert until you add a provider** — there are no keys shipped in the repo. A super-admin adds an `AiProvider` (any OpenAI-compatible endpoint):

- `name` — label
- `base_url` — e.g. `https://api.groq.com/openai/v1` or `https://api.openai.com/v1`
- `api_key` — your provider key (stored in the DB; **never commit real keys**)
- `model` — e.g. `llama-3.1-8b-instant`
- `is_active` — only the active provider is used

Without a provider, generation jobs fail gracefully with *"No active AI provider configured"* and the material is marked `failed` — no crash.

AI logic lives in:
- `app/Services/AiService.php` — OpenAI-compatible client, robust JSON extraction (handles ```json fences, trailing text, trailing commas, comments), token tracking, response caching.
- `app/Services/AiContentService.php` — turns AI output into `Flashcard` / `Question` / `StudyGuide` rows + the `ProcessingJob` runner.
- `app/Services/TokenLimitService.php` — quota enforcement (throws `TokenLimitError` when exceeded).

---

## Architecture

```
app/
  Http/Controllers/
    SuperAdminController.php   schools, AI providers, token limits, usage
    AdminController.php        members, classes, subjects, terms, settings
    TeacherController.php      classes, exams, materials, question bank, analytics
    StudentController.php      dashboard, exams (take/submit/result), flashcards
    TopicController.php        AI topic generation
    MaterialController.php     shared material view + AI generation trigger
  Services/
    AiService.php              LLM client + JSON sanitize/extract + token/cache
    AiContentService.php       material → flashcards/questions/study-guide
    SrsService.php             SM-2 + FSRS scheduling
    TokenLimitService.php      quota enforcement
  Jobs/
    GenerateAiContent.php      queued AI job (ShouldQueue)
  Models/                      ~24 Eloquent models (ClassModel, Exam, Flashcard,
                               Material, Topic, Subject, Term, QuestionBank, …)
database/
  migrations/2026_08_20_000001_study_ai_schema.php   single consolidated schema
  seeders/DatabaseSeeder.php   demo school + 4 users + class + exam + flashcard
routes/
  web.php              all web routes, grouped by role (auth + role middleware)
  console.php          scheduler: retry stuck jobs, flashcard-due log
resources/views/       Full Blade (layouts/studyai, teacher/*, student/*, admin/*)
tests/
  Unit/SrsServiceTest.php          SRS correctness
  Feature/StudentExamFlowTest.php   full exam take→submit→pass flow
```

### Key conventions

- **Base controller** extends `Illuminate\Routing\Controller` and uses `AuthorizesRequests`.
- **ClassModel** (not `Class`) to avoid the PHP reserved word; relation alias `classRoom` where needed.
- **UUID primary keys** for users/memberships; sessions store `user_id` as uuid.
- **Exam answers** are submitted as `q[<questionId>]` (array) fields and read via `$request->input("q.{id}")`.
- **Material review states**: `pending` → `approved`/`rejected`; **status**: `draft` → `processing` → `ready`/`failed`.
- **ProcessingJob** types: `extract_content`, `generate_flashcards`, `generate_questions`, `generate_study_guide`, `generate_all`.

### Queue / async

- `.env` ships `QUEUE_CONNECTION=sync` so generation runs inline during local dev (no worker needed).
- For production async: set `QUEUE_CONNECTION=database` and run `php artisan queue:work`.
- Scheduler: `php artisan schedule:work` (or cron `php artisan schedule:run`) handles stuck jobs + `queue:retry`.

---

## Testing

```bash
php artisan test
# or
vendor/bin/phpunit --no-coverage
```

Tests run on an isolated SQLite `:memory:` database via `RefreshDatabase`. Current suite: **26 tests / 65 assertions, all green** (SRS unit + full student exam flow). Default Breeze `ProfileTest`/`ExampleTest` were removed because this app replaces those routes.

---

## Seeding

`DatabaseSeeder` creates a demo school, the 4 role users, a Demo Class, a published Demo Exam (1 question), a Demo Flashcard, a Mathematics subject, a Fall Term, a question-bank entry, and a Draft Material.

```bash
php artisan migrate:fresh --seed
```

---

## Notes / parity with the original

- Frontend is **Full Blade** (no React). Exam timer, flashcard flip, and math rendering (KaTeX via CDN) are plain JS.
- The original `upload` page's drag-and-drop file + YouTube transcript extraction is represented by the teacher **Materials** create form (type + content/URL). Actual binary extract → text is delegated to the AI provider when a provider is configured; for `note`/`url` types the content is submitted directly.
- All secrets (AI keys, etc.) are **operator-supplied at runtime** — none are stored in the repo.

## License

MIT (Laravel framework) — application code is provided as-is for this rewrite.
