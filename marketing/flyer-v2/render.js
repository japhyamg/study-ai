const { chromium } = require('playwright-core');
const path = require('path');
const fs = require('fs');

const VARIANTS = [
  ['flyer-brand.html', 'StudyAI-Flyer-Indigo'],
  ['flyer-green.html', 'StudyAI-Flyer-Green'],
];
const OUT = '/home/user/study-ai';

(async () => {
  const browser = await chromium.launch({
    executablePath: '/home/user/flyer2/chromium',
    args: ['--no-sandbox', '--disable-gpu', '--hide-scrollbars',
           '--font-render-hinting=none', '--force-color-profile=srgb'],
  });

  for (const [file, name] of VARIANTS) {
    if (!fs.existsSync(path.join(__dirname, file))) { console.log('skip', file); continue; }

    const page = await browser.newPage({
      viewport: { width: 1240, height: 1754 }, deviceScaleFactor: 3,
    });
    await page.goto('file://' + path.join(__dirname, file), { waitUntil: 'networkidle' });
    await page.evaluate(() => document.fonts.ready);
    await page.waitForTimeout(900);

    await page.pdf({
      path: `${OUT}/${name}.pdf`, format: 'A4', printBackground: true,
      margin: { top: 0, right: 0, bottom: 0, left: 0 },
    });

    const el = await page.$('.page');
    await el.screenshot({ path: `${OUT}/${name}.png` });

    // Layout guard: flag anything overflowing the page or hitting the footer.
    const problems = await page.evaluate(() => {
      const out = [];
      const pg = document.querySelector('.page');
      const pr = pg.getBoundingClientRect();
      const foot = pg.querySelector('.foot').getBoundingClientRect();
      pg.querySelectorAll('.band, .herowrap, .steps, .who, .proof').forEach(sec => {
        sec.querySelectorAll('*').forEach(el => {
          // .deco circles intentionally bleed off the page edge.
          if (/deco/.test(el.className)) return;
          const r = el.getBoundingClientRect();
          if (r.height < 2 || r.width < 2) return;
          if (r.bottom > foot.top + 1) out.push(`${el.className||el.tagName} overlaps footer`);
          if (r.right > pr.right + 1) out.push(`${el.className||el.tagName} past right edge`);
        });
      });
      const last = pg.querySelector('.proof').getBoundingClientRect();
      out.push(`gap between last block and footer: ${Math.round(foot.top - last.bottom)}px`);
      return [...new Set(out)];
    });
    console.log(name, '->', problems.join(' | '));
    await page.close();
  }

  await browser.close();
})();
