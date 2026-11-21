import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
mkdirSync('mockups-gold', { recursive: true });
const sel = 'section.page-header .location, section.page-header .location *';
const variants = { current: '', a_deep_gold: '#856800', b_bronze: '#946014', c_charcoal: '#404040' };
const pages = { team: 'https://ultimatehall.org/team/maine-iacs/', tournament: 'https://ultimatehall.org/tournaments/2018-wucc/' };
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const b = await chromium.launch();
for (const [pn, url] of Object.entries(pages)) for (const [vn, vp] of Object.entries({ desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } })) {
  const ctx = await b.newContext({ viewport: vp, deviceScaleFactor: 2, userAgent: UA });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.evaluate(() => document.fonts.ready);
  for (const [k, c] of Object.entries(variants)) {
    await p.evaluate(([s, c]) => { let e = document.getElementById('mock'); if (!e) { e = document.createElement('style'); e.id = 'mock'; document.head.append(e); } e.textContent = c ? `${s}{color:${c}!important}` : ''; }, [sel, c]);
    await p.waitForTimeout(200);
    await p.locator('section.page-header').first().screenshot({ path: `mockups-gold/${pn}-${vn}-${k}.png` });
  }
  await ctx.close();
}
await b.close();
