const { chromium } = require('playwright-core');
const path = require('path');
const screens = ['teacher-dash','summary','flashcards','quiz','study','topics'];
(async () => {
  const b = await chromium.launch({ executablePath: path.join(__dirname,'chromium'),
    args:['--no-sandbox','--disable-gpu','--hide-scrollbars','--font-render-hinting=none','--force-color-profile=srgb'] });
  for (const s of screens) {
    const p = await b.newPage({ viewport:{width:1240,height:200}, deviceScaleFactor:2 });
    await p.goto('file://'+path.join(__dirname,'screens',s+'.html'), { waitUntil:'networkidle' });
    await p.addStyleTag({ content:'.app-shell{min-height:0!important}.app-side{min-height:0!important}' });
    await p.evaluate(() => document.fonts.ready);
    await p.waitForTimeout(500);
    await p.screenshot({ path: path.join(__dirname,'shots', s+'.png'), fullPage:true });
    await p.close();
    console.log('shot', s);
  }
  await b.close();
})();
