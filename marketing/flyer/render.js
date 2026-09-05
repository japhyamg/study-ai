const { chromium } = require('playwright-core');
const path = require('path');

// Repo root — where the finished flyer files are written.
const OUT = path.resolve(__dirname, '..', '..');

(async () => {
  const browser = await chromium.launch({
    executablePath: process.env.CHROMIUM || '/home/user/flyer/chromium',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars',
           '--font-render-hinting=none', '--force-color-profile=srgb'],
  });

  const page = await browser.newPage({
    viewport: { width: 1240, height: 1754 },
    deviceScaleFactor: 3,
  });

  await page.goto('file://' + __dirname + '/flyer.html', { waitUntil: 'networkidle' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(900);

  // Print-ready PDF (vector text, embedded fonts).
  await page.pdf({
    path: OUT + '/StudyAI-Flyer.pdf',
    format: 'A4',
    printBackground: true,
    margin: { top: 0, right: 0, bottom: 0, left: 0 },
  });

  // High-res PNGs for email / social / WhatsApp.
  const pages = await page.$$('.page');
  for (let i = 0; i < pages.length; i++) {
    await pages[i].screenshot({ path: OUT + `/StudyAI-Flyer-p${i + 1}.png` });
  }

  // Overflow check: does any content collide with the footer?
  const report = await page.evaluate(() => {
    const out = [];
    document.querySelectorAll('.page').forEach((pg, i) => {
      const pr = pg.getBoundingClientRect();
      const foot = pg.querySelector('.footer').getBoundingClientRect();
      pg.querySelectorAll('.pad > *, .pad *').forEach((el) => {
        const r = el.getBoundingClientRect();
        if (r.height < 2 || r.width < 2) return;
        if (r.bottom > foot.top + 1) {
          out.push(`page ${i + 1}: <${el.className || el.tagName}> overlaps footer ` +
                   `(bottom ${Math.round(r.bottom)} > footer ${Math.round(foot.top)})`);
        }
        if (r.bottom > pr.bottom + 1) {
          out.push(`page ${i + 1}: <${el.className || el.tagName}> past page edge`);
        }
      });
    });
    return [...new Set(out)];
  });

  console.log(report.length ? report.join('\n') : 'layout OK — no overflow');
  await browser.close();
})();
