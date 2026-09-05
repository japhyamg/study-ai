# StudyAI marketing flyer

Source for `StudyAI-Flyer.pdf` (and the two PNG exports) in the repo root.

## What this is

A two-page A4 flyer built as HTML/CSS and rendered to PDF with headless
Chromium — **not** an AI-generated image. That means the text is real vector
type with embedded fonts, so it stays sharp at any size and prints properly.

| File | Purpose |
|---|---|
| `flyer.html` | The flyer itself — all layout and copy lives here |
| `fonts/` | Fraunces + Geist woff2, subset by the renderer into the PDF |
| `named/` | Product screenshots used in the layout |
| `render.js` | Renders `flyer.html` to PDF + PNG |

## Design

It deliberately follows the product's own design system (see the header
comment in `resources/css/app.css`): warm paper ground, near-black ink,
hairline rules, **one** accent (burnt amber `#C2410C`), no gradients, no drop
shadows, no pill radii. Type is **Fraunces** for display and **Geist** for
text — the same pairing the application uses, so the flyer and the product
look like the same thing.

The PDF embeds only those two families. If you edit the copy and a character
falls outside Fraunces' glyph set (arrows, dashes, symbols), Chromium will
silently substitute a fallback serif — check with:

```bash
python3 -c "import re;d=open('StudyAI-Flyer.pdf','rb').read();print(sorted(set(re.findall(rb'/FontName\s*/([A-Za-z0-9+-]+)',d))))"
```

Only `Fraunces-*` and `Geist-*` should appear.

## Editing the copy

Everything is plain HTML in `flyer.html`. The placeholder contact details
appear twice (once per page footer) and **must be replaced before you send
this to anyone**:

- `hello@studyai.example`
- `+234 000 000 0000`
- `studyai.example`

## Re-rendering

Needs a Chromium binary and `playwright-core`:

```bash
CHROMIUM=/path/to/chromium node marketing/flyer/render.js
```

Outputs to the repo root:

- `StudyAI-Flyer.pdf` — print-ready A4, vector text
- `StudyAI-Flyer-p1.png`, `-p2.png` — 3x raster for email, WhatsApp, social

`render.js` also prints a layout report and will flag any element that
overflows its page or collides with the footer.

## Screenshots

The screenshots come from the app running against `ShowcaseSeeder`
(`database/seeders/ShowcaseSeeder.php`), which builds a realistic school —
33 users, 6 classes, graded exam attempts, 30 days of AI usage history.

Use that seeder, not `DatabaseSeeder`, for any marketing capture:
`DatabaseSeeder` creates one of each record, so every dashboard shows counts
of 1 and the analytics screens read as an empty install.

```bash
php artisan db:seed --class=Database\\Seeders\\ShowcaseSeeder --force
```
