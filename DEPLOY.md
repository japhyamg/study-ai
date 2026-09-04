# Deploying Study AI to Render

Short answer to "can this be hosted on Render for free?" — **yes**, with two
caveats that matter for a presentation. Read the caveats first.

## The two free-tier catches

1. **Cold starts.** Free web services spin down after 15 minutes with no
   traffic, and the next request takes roughly a minute to wake them. If you
   open the link cold in front of an audience, you get a loading page for
   about 60 seconds. **Load the URL 2-3 minutes before you present** and it
   stays warm for the whole demo.
2. **The free database expires after 30 days.** Render deletes free Postgres
   instances 30 days after creation (plus a short grace period). Fine for a
   presentation; not something to build on. Re-create it, or move to the $7
   Starter tier, if you need this to last.

Also worth knowing: free instances have an **ephemeral filesystem**. Anything
written to local disk vanishes on restart, which is why the config below puts
sessions, cache, and queues in Postgres rather than on disk. Uploaded files
would need S3-compatible storage to survive; the demo does not exercise that.

## What is in this repo

| File | Purpose |
|---|---|
| `Dockerfile` | Two-stage build — Node builds Vite assets, PHP 8.3 runs the app |
| `docker/entrypoint.sh` | Boot-time config cache, migrations, optional seeding, starts the server |
| `render.yaml` | Blueprint defining the web service + free Postgres |
| `.dockerignore` | Keeps `vendor/`, `node_modules/`, `.env`, and the local SQLite file out of the image |

Render's native runtimes do not include PHP, so this deploys as a **Docker**
service. That is a fully supported and free option.

## Deploy steps

1. Push this branch to GitHub (already done).
2. In Render: **New → Blueprint**, select this repo, pick branch
   `arena/01a06c3e-study-ai`. Render reads `render.yaml` and proposes a web
   service plus a free Postgres database.
3. **Set `APP_KEY` before the first request.** It is intentionally marked
   `sync: false`, so Render will prompt you for it. Generate one locally:

   ```bash
   npx @php-wasm/cli artisan key:generate --show
   ```

   Paste the entire `base64:...` string. This must be a real 32-byte Laravel
   key — Render's own "generate value" button produces a random string that
   Laravel will reject, which is why this is a manual step.
4. Apply. First build takes several minutes (Composer + npm from scratch).
5. **To get the demo accounts**, set `RUN_SEEDERS=true` in the dashboard and
   deploy once, then set it back to `false` so you are not re-seeding on
   every restart. Migrations run automatically on every boot.

Your URL will be `https://study-ai.onrender.com` (or similar, if the name is
taken). It is public and needs no access token.

## Demo credentials

Same as the local preview — see `DEMO.md` for the full walkthrough. All
accounts use the password `password`:

`super@example.com` · `admin@example.com` · `teacher@example.com` ·
`student@example.com`

## Things I could not verify from the sandbox

I want to be straight about this: **Docker is not available in this
environment, so I could not build or run this image.** The configuration is
written carefully against the app's actual code, and I fixed three concrete
bugs while writing it (below), but the first `docker build` may still surface
something. Budget time for one or two iterations rather than deploying an
hour before you present.

Issues already found and fixed while writing these files:

- **`DB_URL`, not `DATABASE_URL`.** `config/database.php` reads Laravel's
  `DB_URL` convention. The blueprint wires Render's connection string to the
  correct name; the obvious default would have silently failed to connect.
- **`APP_KEY` cannot use `generateValue`.** Render's generator does not emit
  a `base64:`-prefixed 32-byte key, so Laravel would throw on boot. Made it a
  manual dashboard value instead.
- **`route:cache` would crash the container.** `routes/web.php` defines
  `/dashboard` as a closure, and Laravel cannot serialize closure routes. The
  entrypoint caches config and views but deliberately skips routes.

Remaining known-unknowns:

- The Alpine PHP extension build (`pdo_pgsql`, `intl`, `zip`) is the most
  likely place a first build fails.
- Tailwind's content globs are assumed to cover `resources/` and `app/`; if
  Blade files live elsewhere, some styles could be missing from the
  production CSS.
- 512 MB RAM / 0.1 CPU is enough for this app under demo traffic, but it is
  not fast.

## If you would rather not fight cold starts

The $7/month Starter instance removes spin-down entirely and allows a
persistent disk. For a single presentation the free tier is genuinely fine —
just warm it up first.
