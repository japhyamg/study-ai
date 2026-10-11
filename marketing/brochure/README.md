# StudyAI product brochure (Eazy-Learn)

Two built deliverables live at the repo root:

- `StudyAI-Brochure.pdf` — full 8-page **landscape** A4 brochure.
- `StudyAI-Brochure-3page.pdf` — 3-page **portrait** A4 print summary.

This folder holds the editable source for both.

The dashboards on each page are 1:1 captures of the real product UI (see `screens/`),
rendered from the app's own design system (`resources/css/app.css`), then framed in
Eazy-Learn brand chrome (`brochure.html` + `brochure.css`). No AI-drawn people or charts:
every number and screen matches the ShowcaseSeeder demo data.

## Layout
- `brochure.html` / `brochure.css`  the 8 brochure pages (Eazy-Learn brand)
- `screens/*.html` + `screens.css`  faithful product-UI replicas (teacher dashboard,
  study guide, flashcards, quiz, study session, topics)
- `shots/`      high-res captures of each screen (2x)
- `fonts/`      Poppins + Arimo (brand), Fraunces + Geist (product)
- `art/`        Eazy-Learn logos

## Rebuild
Requires the Chromium toolchain used elsewhere in `marketing/` (playwright-core +
@sparticuz/chromium, extracted to a `chromium` binary and `libs/` next to this folder).

    # 1. re-capture the product screens
    LD_LIBRARY_PATH=$PWD/libs:$PWD/libs/lib node shoot.js
    # 2. re-build the PDF + per-page PNGs
    LD_LIBRARY_PATH=$PWD/libs:$PWD/libs/lib node render.js
    # 3. re-build the 3-page portrait print summary
    LD_LIBRARY_PATH=$PWD/libs:$PWD/libs/lib node render3.js

Output: `StudyAI-Brochure.pdf` and `pages/p1..p8.png`.

Copy the PDF to the repo root and `public/downloads/` after rebuilding.
