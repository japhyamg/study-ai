# Generating professional advertising imagery for StudyAI

Written for Eazy-Learn. This is the prompt that produced the 3D character on
`StudyAI-Advert-Flyer.pdf`, plus the reasoning behind every choice, so the
next person can adapt it without the result collapsing into generic AI output.

---

## The single most important rule

**Never ask the image model to render text, logos, or contact details.**

Diffusion models draw letterforms as texture rather than spelling them, so
anything past about five words reliably breaks into gibberish. A flyer with
warped type is the clearest possible signal that nobody art-directed it.

The professional workflow is a two-layer composite:

| Layer | Produced by | Contains |
|---|---|---|
| Background | Image model | The character, props, environment, lighting |
| Foreground | Page layout (HTML/CSS, InDesign, Figma) | All type, the real logo, phone, email, website |

That is exactly how the current flyer is built. The render is a photograph in
the layout's eyes; every word sitting on top is real vector type with embedded
fonts, and the logo is your actual PNG. This is also why the flyer can be
proofread, translated, or re-priced in seconds without regenerating anything.

**Always add an explicit negative instruction banning text**, because models
love to invent signage and UI labels unprompted.

---

## Colour, and why this flyer is mostly indigo

Grounded in standard colour-psychology practice rather than taste:

- **Blue/indigo reads as trust, competence and security.** It is the default
  for institutions that ask to be believed. For a product a *school* must
  authorise and a *parent* must feel safe about, this is the right ground.
- **Orange reads as energy, friendliness and urgency,** and is a well
  established conversion colour for calls to action.
- **Green reads as growth and progress**, which suits pupil outcomes.

The decisive principle is not "orange converts" but the **isolation effect**:
the element that differs most from its surroundings gets acted upon. Orange
works on this flyer *because* the page is overwhelmingly indigo. If the whole
flyer were orange, the button would disappear.

So the discipline is:

1. Indigo `#6864ED` dominates, establishing trust and brand recognition.
2. Green `#33CC79` appears only on the three audience labels.
3. **Orange `#FF7425` is reserved almost entirely for the call to action**,
   plus one small rule above the kicker. Nothing else competes with it.

All three are your existing brand colours, pulled from the eazy-learn.com
stylesheet, so the flyer reinforces the site instead of contradicting it.

One trap worth naming: the first draft set the kicker in orange over the
render, and it vanished, because the classroom render is itself warm. Colour
choices have to survive contact with the image behind them. The fix was white
type with a short orange rule beside it.

---

## Type

**Poppins** for display, **Arimo** for body: the same pairing as your website.
Poppins is a geometric sans with near-circular bowls, so it stays friendly at
large sizes without looking childish. Arimo is metric-compatible with Arial
and stays legible at 8pt, which matters for the small print.

Rules that keep it looking commissioned rather than assembled:

- Two families maximum. Weight carries the hierarchy, not extra typefaces.
- Tighten letter-spacing on large display type (`-0.028em` here). Headlines
  set at default tracking look amateurish.
- Open up letter-spacing on small uppercase labels (`0.2em`).
- Never centre long body copy. Ragged-right is easier to read.
- **No em dashes or en dashes**, per Eazy-Learn's standing preference. Use
  commas, full stops, or "and".

---

## The prompt that produced the current hero

Copy this wholesale, or edit the SUBJECT block to change the character.

```
Stylized 3D animated feature film character render, single subject, medium
shot, eye level, 50mm lens look.

SUBJECT: A cheerful West African teenage schoolgirl, about 15, seated at a
simple wooden desk, three-quarter turn toward camera, looking up and away from
her laptop screen with a genuine relieved smile, one hand resting flat on the
desk, the other hand relaxed on the laptop edge. Dark coily hair in neat twin
bantu knots with a few loose strands. Warm deep brown skin with believable
subsurface scattering, soft natural skin shading, gentle asymmetry in the
face, small imperfections, a few freckles across the nose. Navy school
pinafore over a crisp white collared shirt, matte cotton fabric with visible
weave and soft natural creases.

PROPS: An open silver laptop, screen tilted mostly away from camera so its
contents are not legible, emitting a soft warm glow onto her face and hands.
On the desk beside it: one closed exercise book, two pencils in a small cup,
and a single folded pair of reading glasses. Floating gently in the air behind
her shoulder, clearly separated in depth: three simple abstract rounded card
shapes and one small abstract bar chart plate, all blank with no writing
whatsoever, rendered as clean matte objects in indigo, green and warm orange,
arranged in a loose rising arc suggesting study material generated from her
work.

LIGHTING: Directional warm key light from the upper left casting soft but
clearly defined shadows to the lower right, cool indigo rim light along her
right shoulder and cheek separating her from the background, gentle bounce
fill from the desk surface. Soft global illumination, clean specular
highlights in the eyes, believable contact shadows where her arm meets the
desk.

BACKGROUND: A softly defocused warm classroom interior in shallow depth of
field, muted cream and pale indigo tones, suggestions of a window and a shelf,
deliberately simple and uncluttered with generous negative space in the upper
right of the frame for later typography.

COLOUR: Restrained palette of indigo, warm orange accent, soft green, cream
and warm neutral. Balanced warm and cool separation, natural saturation,
filmic colour grade, not oversaturated.

RENDER: Polished production quality 3D animation, clean topology, matte and
fabric materials rather than glossy plastic, physically plausible shading,
subtle depth of field, gentle film grain.

STRICT NEGATIVES: absolutely no text, no letters, no numbers, no words, no
signage, no logos, no watermarks, no UI elements, no readable screen content,
no floating icons with symbols, no extra fingers, no deformed hands, no
plastic or waxy skin, no airbrushed doll face, no dead glassy stare, no
oversaturated candy colours, no cluttered busy background, no lens flare, no
sparkles, no glowing particles.
```

### Why it is structured in labelled blocks

Models follow a prompt organised as SUBJECT, then PROPS, LIGHTING,
BACKGROUND, COLOUR, RENDER far more reliably than the same information in one
run-on sentence. Blocks stop the model averaging unrelated ideas together.

### Why each block earns its place

**"Stylized 3D animated feature film character render"** rather than naming a
studio. Naming a specific animation studio is both a copyright risk and
imprecise. What you actually want are the *rendering properties*: stylised
proportions, clean materials, soft global illumination. Ask for those.

**A specific action, not a pose.** "Looking up from her laptop with a relieved
smile" tells a story. "A student smiling at a laptop" produces a stock-photo
void. The relief is the entire product benefit rendered as an expression.

**Hands explicitly placed.** Hands are where these models fail most often.
Describing exactly where both hands rest, in simple relaxed positions, avoids
the six-fingered giveaway. If a pose needs complex hand work, reframe so the
hands are out of shot.

**Deliberate imperfection.** "Gentle asymmetry", "small imperfections",
"freckles", "soft natural creases", "gentle film grain". Left alone, these
models produce flawless bilateral symmetry and poreless plastic skin, which is
the single most recognisable AI tell. Asking for imperfection is what buys
believability.

**Directional lighting with a named direction.** "Warm key from upper left,
shadows falling lower right, cool indigo rim light." The default AI look is
flat, sourceless, shadowless light. Naming a direction and demanding visible
shadows is most of the difference between a render and a photograph.

**Negative space requested on purpose.** "Generous negative space in the upper
right for later typography." The image is not the advert; it is the stage the
advert stands on. Ask for the room you need.

**Screen contents deliberately hidden.** The laptop is angled away, so the
model never attempts UI text it cannot spell. If you need the actual product
on screen, composite a real screenshot in afterwards.

**Long, specific negatives.** Everything on that list is a failure mode these
models fall into by default.

---

## Working method

1. **Write the copy first.** The headline determines the composition, never
   the reverse. This flyer's layout exists to serve "She just revised a whole
   term in one evening."
2. **Generate several variants.** Three or four, then choose. First outputs
   are rarely the best.
3. **Inspect at full size before building on it.** Zoom to the hands, the
   eyes, and any surface that might have grown text.
4. **Upscale after selection, not before.** Masters live in
   `marketing/flyer-v3/art4k/` at 3840px; the copy embedded in the PDF is
   2600px, which is 314dpi at A4 width. Print needs 300dpi; anything beyond
   that only inflates the file.
5. **Composite type and logo in the layout**, never in the prompt.
6. **Check the finished PDF**, not just the picture:

```bash
# Only the two intended font families may appear.
python3 -c "import re;d=open('StudyAI-Advert-Flyer.pdf','rb').read();print(sorted(set(re.findall(rb'/FontName\s*/([A-Za-z0-9+-]+)',d))))"
```

`render.js` additionally reports any element that overflows the page or
collides with the call-to-action band.

---

## Adapting the character

Swap only the SUBJECT block; leave LIGHTING, BACKGROUND, COLOUR, RENDER and
the NEGATIVES exactly as they are, since those carry the house style.

- **A teacher instead of a pupil:** "A warm, confident West African woman
  teacher in her late thirties, seated at a staffroom desk, marking finished,
  leaning back with a satisfied smile, patterned blouse in muted indigo."
- **A boy pupil:** "A West African schoolboy, about 14, short cropped hair,
  navy school jumper over a white shirt, leaning in toward the laptop with
  focused curiosity."
- **Two pupils revising together:** expect more hand errors; keep both pairs
  of hands flat on the desk and generate more variants.

For a consistent character across a campaign, generate one clean front-facing
anchor image on a plain background first, then use it as a reference for every
later pose. Identity drifts otherwise.

---

## Files

| Path | What |
|---|---|
| `StudyAI-Advert-Flyer.pdf` | Finished A4 advert, 314dpi, ~1MB |
| `StudyAI-Advert-Flyer.png` | 3x raster for WhatsApp, email, social |
| `marketing/flyer-v3/flyer.html` | Layout and all copy |
| `marketing/flyer-v3/art4k/hero-student-4k.png` | 3840px master render |
| `marketing/flyer-v3/Eazy-learn-white.png` | Logo, wordmark in white for dark grounds |
