const { chromium } = require('playwright-core');
const path = require('path');
(async () => {
  const b = await chromium.launch({ executablePath: path.join(__dirname,'chromium'),
    args:['--no-sandbox','--disable-gpu','--hide-scrollbars','--font-render-hinting=none','--force-color-profile=srgb'] });
  const p = await b.newPage({ viewport:{width:794,height:1123}, deviceScaleFactor:3 });
  await p.goto('file://'+path.join(__dirname,'brochure3.html'), { waitUntil:'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  await p.waitForTimeout(800);
  await p.pdf({ path: path.join(__dirname,'StudyAI-Brochure-3page.pdf'), format:'A4', printBackground:true,
    margin:{top:0,right:0,bottom:0,left:0}, preferCSSPageSize:true });
  const pages = await p.$$('.page');
  for (let i=0;i<pages.length;i++){
    await pages[i].screenshot({ path: path.join(__dirname,'pages3', 'p'+(i+1)+'.png') });
  }
  const rep = await p.evaluate(() => {
    const out=[];
    document.querySelectorAll('.page').forEach((pg,idx)=>{
      const pr=pg.getBoundingClientRect();
      pg.querySelectorAll('*').forEach(el=>{
        const r=el.getBoundingClientRect();
        if(r.height<2) return;
        if(r.bottom>pr.bottom+2) out.push('p'+(idx+1)+' '+(el.className&&el.className.baseVal!==undefined?el.className.baseVal:el.className)+' over by '+Math.round(r.bottom-pr.bottom));
      });
    });
    return [...new Set(out)].slice(0,20);
  });
  console.log('pages:', pages.length);
  console.log(rep.length?rep.join(' | '):'no overflow');
  await b.close();
})();
