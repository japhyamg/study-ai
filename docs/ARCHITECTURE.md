# Architecture — Multi-tenant SaaS structure

This document explains how the platform separates the **super-admin (platform)** on the
main domain from **schools** on subdomains, and how the four user types
(`super_admin`, `admin`, `teacher`, `student`) are stored and authenticated.

---

## 1. User separation — one identity table + one table per user type

### The problem

A school SaaS needs three school-scoped user types (administrators, teachers,
students) that all **log in through the same route**, plus a platform-level
super-admin. The question is how to model "separate tables per user type"
without creating four login flows.

### The approach used here (recommended)

```
users                ← ONE credentials/identity table (email, password, 2FA)
├── platform_admins  ← super-admins of the SaaS (main domain)   [user_id]
├── school_admins    ← school administrators                     [user_id, school_id]
├── teachers         ← teachers + teacher fields (staff_no, …)   [user_id, school_id]
└── students         ← students + student fields (admission_no, level) [user_id, school_id]
```

* **`users` is the single authentication identity.** Email + password + 2FA live
  here, so there is exactly **one login route, one password reset flow and one
  session guard** for everyone — which is what makes "all types log in via the
  same route" trivial and safe.
* **Each user type has its own table** with its role-specific columns and
  relations (`teachers.staff_no`, `students.admission_no`, …). This is the
  "class-table inheritance" pattern: clean separation of concerns without
  splitting authentication.
* **School membership IS the role row.** A `teachers` row for (user, school)
  means "this person is a teacher at that school". No separate pivot table is
  needed; role checks (`User::highestRole()`) read the four tables, and the
  result is cached per request.
* Moving a person between roles = moving their row between the role tables
  (see `AdminController::updateMemberRole`). Credentials are untouched.

### Why not the alternatives?

| Alternative | Downside |
|---|---|
| One `users` table with a `role` column (or the old `school_members` pivot) | No place for type-specific fields; roles and identity stay coupled; "separate tables" requirement unmet. |
| Four separate auth tables with four guards, each with its own login form | Four password-reset flows, four session guards, every foreign key (`created_by`, `teacher_id`, `user_id`…) becomes polymorphic or ambiguous; one human with two roles exists twice. |

### Key code paths

| Concern | Where |
|---|---|
| Role resolution (cached) | `App\Models\User::highestRole()` |
| School membership | `User::schoolIds()` / `User::belongsToSchool()` / `User::currentSchool()` |
| Type map (admin/teacher/student → model) | `App\Support\Members\MemberTypes` |
| Login (shared route, 2FA, tenant checks) | `Auth\AuthenticatedSessionController::store()` |
| Role middleware (+ tenant scope) | `App\Http\Middleware\EnsureRole` |

---

## 2. Domain separation — main domain vs school subdomains

```
https://studyai.com            ← MAIN domain: platform (super-admin), shared sign-in,
                                  onboarding (create / join a school)
https://demo.studyai.com       ← SCHOOL workspace ("demo"): admin / teacher / student
                                  areas, sign-in scoped to that school
https://lincoln.studyai.com    ← another school
```

Configured with `APP_CENTRAL_DOMAINS=studyai.com` (see `.env.example`). Leave it
empty in local development — the app then runs path-based on `localhost` and the
active school is resolved from the logged-in user's profile.

### How it works

1. **`App\Http\Middleware\IdentifyTenant`** runs on every web request. It parses
   the host:
   * host == a central domain → **central context** (`Tenant::school()` is null);
   * host == `{slug}.{central-domain}` → looks up the school by slug → **tenant
     context**. Unknown subdomains 404. Reserved subdomains (`www`, `api`, …)
     behave like central.
   * logged-in users that do **not** belong to the subdomain's school are logged
     out with a clear error.
2. **`User::currentSchool()`** prefers the tenant school, then the session
   override, then the first profile school — so all school-scoped queries
   (classes, members, exams, …) automatically follow the subdomain.
3. **Sign-in**
   * On a **school subdomain**: the account must belong to that school
     (platform admins always pass).
   * On the **main domain**: platform admins land on `/super-admin`; school
     users are redirected straight to their school's subdomain
     (`School::appUrl()`, e.g. `https://demo.studyai.com/dashboard`).
4. **Registration**
   * On a **school subdomain**: the new account immediately becomes a **student**
     of that school.
   * On the **main domain**: the account starts role-less and goes to
     **onboarding** — create a school (become its admin) or join with a class
     invite code (become a student).
5. **`App\Http\Middleware\EnsureCentralDomain`** keeps `/super-admin/*` on the
   main domain; visiting it from a school subdomain bounces you to the central
   URL.
6. **Sessions across subdomains**: set `SESSION_DOMAIN=.studyai.com` in
   production so the session cookie is shared across the main domain and all
   school subdomains (see `.env.example`).

### Local development

No DNS required — leave `APP_CENTRAL_DOMAINS` empty. To exercise the subdomain
flow locally, map hosts in `/etc/hosts`:

```
127.0.0.1  studyai.test demo.studyai.test
```

set `APP_URL=http://studyai.test` + `APP_CENTRAL_DOMAINS=studyai.test`, then run
`php artisan serve --host=0.0.0.0 --port=8000` and open
`http://studyai.test:8000` (platform) and `http://demo.studyai.test:8000`
(seeded demo school).

---

## 3. Two-factor authentication

Dependency-free TOTP (RFC 6238) implemented in `App\Support\Totp` — compatible
with Google Authenticator, Authy, 1Password, etc.

* Columns on `users`: `two_factor_secret` (encrypted), `two_factor_recovery_codes`
  (encrypted array), `two_factor_confirmed_at`.
* Profile page (`/profile`) flow: **enable** (password re-prompt) → scan QR /
  manual key → **confirm** with a 6-digit code → recovery codes shown once.
* Login flow: after correct email + password, the user is parked on
  `/two-factor-challenge` and only fully signed in after a valid TOTP **or**
  single-use recovery code. Disable / regenerate recovery codes always require
  the current password.

---

## 4. Migrating from the previous schema

`2026_08_22_000001_create_role_profiles_and_two_factor.php`:

1. creates `platform_admins`, `school_admins`, `teachers`, `students`;
2. adds the 2FA columns to `users`;
3. **backfills** the new tables from the legacy `school_members` pivot (every
   role, with timestamps preserved);
4. drops `school_members` — a single source of truth remains.

Run `php artisan migrate --seed` as usual; fresh installs simply skip the
backfill.
