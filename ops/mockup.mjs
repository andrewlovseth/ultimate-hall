import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';
const out = 'mockups'; mkdirSync(out, { recursive: true });
const blue = ':root{--color-blue:#346c93}';
const variants = {
  current: '',
  a_dark_text: `${blue} .cta .btn{color:var(--color-charcoal)}`,
  b_dark_gold: `${blue} .cta .btn{background:var(--color-gold-700);border-color:var(--color-gold-800)}`,
};
const pages = { home: 'https://ultimatehall.org/', member: 'https://ultimatehall.org/members/nicole-sprout-beck/' };
const vps = { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844 } };
const b = await chromium.launch();
for (const [pn, url] of Object.entries(pages)) for (const [vn, vp] of Object.entries(vps)) {
  const ctx = await b.newContext({ viewport: vp, deviceScaleFactor: 2, reducedMotion: 'reduce' });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'networkidle' });
  await p.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { scrollTo(0, y); await new Promise(r => setTimeout(r, 100)); } scrollTo(0, 0); });
  await p.waitForLoadState('networkidle');
  for (const [k, css] of Object.entries(variants)) {
    await p.evaluate((css) => { let s = document.getElementById('mock'); if (!s) { s = document.createElement('style'); s.id = 'mock'; document.head.append(s); } s.textContent = css; }, css);
    await p.waitForTimeout(300);
    await p.screenshot({ path: `${out}/${pn}-${vn}-${k}.png`, fullPage: true });
  }
  await ctx.close();
}
await b.close();
