# StudyAI — setup & architecture notes

This document covers the multi-tenant SaaS changes: how to run the app, how
tenancy resolves, and how the user model is split.

---

## 1. Running locally

```bash
composer install
cp .env.example .env
php artisan key:generate

# SQLite is easiest to start with
touch database/database.sqlite
# then in .env:  DB_CONNECTION=sqlite
#                DB_DATABASE=/absolute/path/to/database/database.sqlite

php artisan migrate --seed
npm install && npm run build      # or: npm run dev

php artisan serve
```

### Seeded accounts

Password for **every** account is `password`.

| Role | Email | Where to sign in |
|---|---|---|
| Super admin | `super@studyai.test` | `/super-admin/login` |
| Admin (Lincoln) | `admin@lincoln.test` | `/login?tenant=lincoln` |
| Teacher (Lincoln) | `daniel@lincoln.test` | `/login?tenant=lincoln` |
| Student (Lincoln) | `chidi@lincoln.test` | `/login?tenant=lincoln` |
| Admin (Riverside) | `admin@riverside.test` | `/login?tenant=riverside` |
| Student (Riverside) | `chidi@riverside.test` | `/login?tenant=riverside` |

> Two schools are seeded on purpose so you can verify tenant isolation.
> Note `Chidi Nwosu` exists at *both* schools with different emails — the same
> email address is also allowed at two schools, because email is unique
> **per tenant**, not globally.

---

## 2. Tenancy

### Production — real subdomains

Set in `.env`:

```dotenv
APP_DOMAIN=studyai.com
APP_SCHEME=https
CENTRAL_SUBDOMAIN=admin
TENANCY_PATH_FALLBACK=false
SESSION_DOMAIN=.studyai.com     # leading dot: share session across subdomains
```

This gives you:

| Host | Serves | Guard |
|---|---|---|
| `studyai.com` | Marketing / "find my school" | — |
| `admin.studyai.com` | Platform console | `superadmin` |
| `lincoln.studyai.com` | Lincoln's workspace | `web` |
| `riverside.studyai.com` | Riverside's workspace | `web` |

Requires a wildcard DNS record (`*.studyai.com`) and a wildcard TLS cert.

### Local / preview — no wildcard DNS

Leave `APP_DOMAIN` empty and keep `TENANCY_PATH_FALLBACK=true`. Routes then
bind to the default host and the tenant is selected with `?tenant=lincoln`,
which is remembered in the session. Platform routes move to `/super-admin/*`.

`ResolveTenant` resolution order:

1. Vanity domain (`schools.domain`) — e.g. a school's own `lincoln.edu.ng`
2. Subdomain of `APP_DOMAIN`
3. `?tenant=` query parameter *(fallback only)*
4. Session-remembered tenant *(fallback only)*
5. If exactly one school exists, use it *(fallback only)*

Unknown host → a friendly picker page. Suspended school → a 503 notice.

---

## 3. Users: one login, separate tables

You asked for separate tables per user type while keeping a single login
route. The split is:

```
super_admins          ← platform staff. OWN TABLE + OWN GUARD ('superadmin')
                        Never a member of any school.

users                 ← every school user: the shared credential store
                        (email, password, 2FA). Scoped by school_id.
  ├── admin_profiles      staff_number, job_title, department, is_primary…
  ├── teacher_profiles    staff_number, title, department, qualification,
  │                       specialisations, hired_on, bio, office_hours…
  └── student_profiles    admission_number, grade_level, section, DOB,
                          guardian_*, address, enrolled_on, status…

school_members        ← which role a user holds in which school
```

**Why this shape**

- *One* login route, *one* password-reset broker, *one* 2FA implementation —
  three auth stacks would have to be built and secured three times over.
- Role-specific columns live on the role's own table, so `users` stays clean
  and a student's `guardian_phone` never sits on a teacher row.
- `users.email` is unique **per school** (`UNIQUE(school_id, email)`), so the
  same person can hold accounts at multiple schools.
- Super-admins are genuinely separate — different table, different guard,
  different session cookie, different domain — as you requested.

Accessors:

```php
$user->roleInSchool();   // 'admin' | 'teacher' | 'student' (in the active tenant)
$user->profile();        // AdminProfile | TeacherProfile | StudentProfile
$user->isAdmin();        // role checks are tenant-scoped
```

### Super-admins entering a school

Platform staff have **no implicit rights** inside a tenant. To provide support
they impersonate explicitly:

```
POST /super-admin/schools/{school}/impersonate/{user}
```

This issues a real `web` session, shows a persistent banner in the UI, and logs
who is behind the session. `POST /stop-impersonating` ends it.

---

## 4. Security middleware

| Alias | Class | Purpose |
|---|---|---|
| `tenant` | `ResolveTenant` | Resolves + binds the active school |
| `school.user` | `EnsureUserBelongsToSchool` | Hard isolation; logs out cross-tenant sessions |
| `role` | `EnsureRole` | Tenant-scoped role gate |
| `2fa` | `EnsureTwoFactorIsConfirmed` | Blocks until an open 2FA challenge is answered |
| `guest` | `RedirectIfAuthenticated` | Guest gate aware of both guards |

Middleware priority is set explicitly so the tenant is always resolved before
authentication and role checks run.

---

## 5. Two-factor authentication

TOTP (RFC 6238), implemented in `app/Support/TwoFactor/`:

- `TotpAuthenticator` — verified against all five RFC 6238 test vectors.
- `QrCode` — generates the enrolment QR inline as SVG (no image dependency).

Both school users and super-admins can enable it from their profile
(**Profile → Security**). Flow is enable → scan → confirm, so a mis-scanned
QR can't lock anyone out. Recovery codes are single-use and regenerable.

Columns use Fortify's exact names (`two_factor_secret`,
`two_factor_recovery_codes`, `two_factor_confirmed_at`) and the same encrypted
payload format, so **Laravel Fortify can be installed later and will read this
data with no migration**. Fortify itself isn't required at runtime — Packagist
was unreachable in the environment this was built in, so the maths is
self-contained rather than left broken.

To adopt Fortify later:

```bash
composer require laravel/fortify
```

then enable its 2FA features; the existing secrets keep working.

---

## 6. Design system

`resources/css/app.css` + `tailwind.config.js`.

Colours are CSS custom properties holding **raw RGB channels**, so every
Tailwind utility keeps opacity modifiers (`bg-brand-600/10`) *and* flips
automatically in dark mode.

Principles applied, per the brief:

- One brand colour (indigo), used only to signal intent — never decoration
- Hairline borders instead of shadows; shadow reserved for floating layers
- 6–10px radii; no pill shapes, no square corners
- No gradients, no bright colour blocks, no oversized cards
- Dense tables, roomy sections — whitespace used deliberately

Reusable components: `<x-ui.card>`, `<x-ui.stat>`, `<x-ui.field>`,
`<x-ui.button>`, `<x-ui.badge>`, `<x-ui.empty>`, `<x-icon>`.

---

## 7. Responsive layout

`resources/views/components/layouts/studyai.blade.php`.

- **< 1024px** — sidebar becomes an off-canvas drawer with a scrim; opens from
  the topbar hamburger, closes on backdrop click or <kbd>Esc</kbd>.
- **≥ 1024px** — sidebar is fixed; content is offset by its width.
- Topbar is sticky and holds the **logged-in user's name, avatar, role and the
  options dropdown** (profile, password & 2FA, preferences, sign out), plus the
  theme toggle.
- Tables are wrapped in `.table-wrap` so they scroll horizontally instead of
  breaking the layout.
- Uses `100dvh` and `viewport-fit=cover` for correct mobile browser chrome.

Navigation is built per role in `app/Support/Navigation.php` rather than with
`@if` branches in the template, so each role gets a purpose-built menu.

---

## 8. Notes

- `devserver/` is git-ignored: it's a PHP-WASM harness used only because the
  build sandbox had no PHP binary. It is not part of the application.
- `bootstrap/app.php` registers three route files: `routes/public.php` (apex),
  `routes/superadmin.php` (central domain), `routes/web.php` (tenant).
