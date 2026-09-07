# StudyAI flyers (Eazy-Learn)

Two single-page A4 flyers, same structure, different personality.

| Output | Palette / type | Aimed at |
|---|---|---|
| `StudyAI-Flyer-Indigo.pdf` | Indigo, green, orange from eazy-learn.com. Poppins + Arimo | School owners, principals, heads |
| `StudyAI-Flyer-Green.pdf` | Deep forest green, chalk, brass. Fraunces + Geist | Mixed audience: schools, teachers, parents |

Each also exports a PNG at 3x for WhatsApp, email and social.

## Not AI-generated slop

The flyers are **HTML and CSS rendered to vector PDF** by headless Chromium,
so all type is real embedded font data, not pixels. No AI-generated text
appears anywhere in the artwork. The illustrations are generated but were
prompted as flat vector pictograms with no lettering, then auto-cropped,
squared, upscaled and given transparent backgrounds.

Checks that run every build:

- Layout guard reports any element overflowing the page or colliding with
  the footer, and prints the remaining gap.
- Font audit: only the two intended families may appear in the PDF. A stray
  fallback (usually DejaVu, pulled in by one missing glyph such as an arrow)
  shows up immediately.

```bash
python3 -c "import re;d=open('StudyAI-Flyer-Green.pdf','rb').read();print(sorted(set(re.findall(rb'/FontName\s*/([A-Za-z0-9+-]+)',d))))"
```

There are **no em dashes or en dashes** in the copy, by request. Keep it that
way when editing; use commas, full stops or "and".

## The logo

The real Eazy-Learn mark is in place, taken from eazy-learn.com.

- `Eazy-learn.png` is the original: orange diamond, black wordmark, transparent.
- `Eazy-learn-white.png` recolours the black wordmark to white while keeping
  the orange diamond, so the logo reads on the indigo and green header bands.
  **This is the one both flyers use.**

If you have a higher resolution or vector original, replace both files and
re-render. The current raster is 1200px wide, which is comfortably sharp at
the 12mm slot height. The layout still falls back to a typeset wordmark if
the image is missing, so the flyer never breaks.

## Artwork

- `art4k/` holds the 4K masters (hero 3840px wide, icons 2048x2048).
- `art_print/` holds downscaled copies actually embedded in the PDF, so the
  files stay emailable at roughly 2.3MB instead of 8MB while still exceeding
  300dpi at print size.

Regenerate `art_print/` from the masters if you change any illustration.

## Contact details

Taken from eazy-learn.com and already set in both footers:

- www.eazy-learn.com
- hello@eazy-learn.com
- +234 (0) 814 175 2518
- Moshood Abiola Rd, Garki Area 1, Abuja, FCT

## Re-rendering

```bash
CHROMIUM=/path/to/chromium node render.js
```

Writes both PDFs and both PNGs to the repository root.
