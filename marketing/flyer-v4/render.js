const { chromium } = require('playwright-core');
(async () => {
  const b = await chromium.launch({ executablePath:'/home/user/flyer4/chromium',
    args:['--no-sandbox','--disable-gpu','--hide-scrollbars','--font-render-hinting=none','--force-color-profile=srgb'] });
  const p = await b.newPage({ viewport:{width:1240,height:1754}, deviceScaleFactor:3 });
  await p.goto('file:///home/user/flyer4/flyer.html', { waitUntil:'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  await p.waitForTimeout(1000);
  await p.pdf({ path:'/home/user/study-ai/StudyAI-Advert-School-Owners.pdf', format:'A4',
                printBackground:true, margin:{top:0,right:0,bottom:0,left:0} });
  await (await p.$('.page')).screenshot({ path:'/home/user/study-ai/StudyAI-Advert-School-Owners.png' });
  const rep = await p.evaluate(() => {
    const pg=document.querySelector('.page'), pr=pg.getBoundingClientRect();
    const cta=pg.querySelector('.cta').getBoundingClientRect();
    const money=pg.querySelector('.money').getBoundingClientRect();
    const out=[];
    pg.querySelectorAll('.body *,.head *,.mast *').forEach(el=>{
      const r=el.getBoundingClientRect();
      if(r.height<2) return;
      if(r.bottom>cta.top+1) out.push((el.className||el.tagName)+' hits CTA');
      if(r.right>pr.right+1||r.left<pr.left-1) out.push((el.className||el.tagName)+' past edge');
    });
    out.push('gap money->cta: '+Math.round(cta.top-money.bottom)+'px');
    return [...new Set(out)];
  });
  console.log(rep.join(' | '));
  await b.close();
})();
