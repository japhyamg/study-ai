# Study AI — Live Demo Guide

The app is running as a live preview on port **8080**.

## Demo accounts

All accounts use the password `password`.

| Role        | Email                  | Lands on            |
|-------------|------------------------|---------------------|
| Super Admin | super@example.com      | `/super-admin`      |
| School Admin| admin@example.com      | `/admin/dashboard`  |
| Teacher     | teacher@example.com    | `/teacher/dashboard`|
| Student     | student@example.com    | `/student/dashboard`|

Visiting `/` redirects to `/dashboard`, which routes each user to the
dashboard for their role — so you can just log out and log back in as the
next persona during the presentation.

## Suggested presentation flow

1. **Super Admin** — `/super-admin` → Schools, AI Providers, Token Usage,
   Token Limits, Usage by Teacher, Analytics. Shows the multi-tenant/ops layer.
2. **School Admin** (`admin@example.com`) — `/admin/dashboard` → Members,
   Classes, Subjects, Terms, Analytics, Settings. Shows school setup.
3. **Teacher** (`teacher@example.com`) — `/teacher/dashboard` → Classes
   ("Demo Class", invite code `DEMO123`), Materials ("Demo Material" — the
   AI generation entry point), Exams ("Demo Exam"), Question Bank.
4. **Student** (`student@example.com`) — `/student/dashboard` → Classes,
   Exams (take "Demo Exam" — one MCQ, submit, see the result), Flashcards
   (one card due for review).

## Seeded demo data

- School: **Demo School** (`demo-school`)
- Class: **Demo Class**, invite code `DEMO123`, student enrolled
- Subject: Mathematics (MATH), Term: Fall Term
- Material: **Demo Material** (draft, pending review)
- Exam: **Demo Exam** (published, 10 min, pass mark 50, 1 MCQ)
- Flashcard: "Capital of France?" → "Paris"
- Question bank: "What is the derivative of x^2?"

## How this environment is set up

- **PHP**: `@php-wasm/cli` (PHP 8.5) — no system PHP was available in the
  sandbox, and the Debian/Composer mirrors are unreachable from here.
- **Composer vendor tree**: the network blocks packagist, so every package
  in `composer.lock` was fetched directly from its GitHub source at the
  locked commit, and the Composer autoloader files
  (`vendor/composer/autoload_*.php`, `installed.php`) were generated to match.
  `vendor/` stays gitignored.
- **Database**: SQLite at `database/database.sqlite` (`.env` uses
  `DB_CONNECTION=sqlite`) instead of Postgres, migrated and seeded.
- **Frontend**: `npm run build` (Vite production build in `public/build`),
  so no dev server / HMR is needed.
- **Proxy**: `bootstrap/app.php` trusts all proxies so Laravel generates
  correct `https://…e2b.app` URLs behind the Arena preview proxy;
  `SESSION_SECURE_COOKIE=true` keeps logins working over HTTPS.

### Restarting the server manually

```bash
npx @php-wasm/cli -S 0.0.0.0:8080 -t public public/index.php
```

### Rebuilding demo data from scratch

```bash
rm -f database/database.sqlite && touch database/database.sqlite
npx @php-wasm/cli artisan migrate --force
npx @php-wasm/cli artisan db:seed --force
```
