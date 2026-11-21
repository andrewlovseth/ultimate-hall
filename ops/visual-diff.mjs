// Screenshot production pages twice: as served, and with the theme's static files
// (style.css, fonts, js, images) swapped for this checkout's. Reports how many pixels differ.
// Catches CSS and font regressions; PHP template changes are not exercised.
// Usage: node ops/visual-diff.mjs <pages.txt> <out-dir>   (pages.txt: "name url" per line)
import { chromium } from 'playwright';
import { readFileSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { PNG } from 'pngjs';
import pixelmatch from 'pixelmatch';

const [pagesFile, outDir] = process.argv.slice(2);
mkdirSync(outDir, { recursive: true });
const pages = readFileSync(pagesFile, 'utf8').trim().split('\n').map((l) => l.split(' ')).filter(([, u]) => u);
const THEME = '/wp-content/themes/ultimate-hall/';
const types = { css: 'text/css', js: 'text/javascript', woff2: 'font/woff2', svg: 'image/svg+xml', png: 'image/png', jpg: 'image/jpeg' };
const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const viewports = { mobile: { width: 390, height: 844 }, desktop: { width: 1440, height: 900 } };

const browser = await chromium.launch();
async function shot(url, vp, local) {
	// A normal desktop Chrome user agent: Google Fonts serves different files to "HeadlessChrome".
	const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1, reducedMotion: 'reduce', userAgent: UA });
	const page = await ctx.newPage();
	if (local) {
		await page.route((u) => u.pathname.startsWith(THEME), (route) => {
			const path = new URL(route.request().url()).pathname.slice(THEME.length);
			if (!existsSync(path)) return route.fulfill({ status: 404 });
			route.fulfill({ body: readFileSync(path), contentType: types[path.split('.').pop()] || 'application/octet-stream' });
		});
	}
	await page.goto(url, { waitUntil: 'networkidle' });
	// Walk down the page so lazy images load, then back to the top.
	await page.evaluate(async () => {
		for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 120)); }
		window.scrollTo(0, 0);
	});
	await page.waitForLoadState('networkidle');
	await page.evaluate(() => document.fonts.ready);
	await page.addStyleTag({ content: '*,*::before,*::after{transition:none!important;animation:none!important;caret-color:transparent!important}' });
	await page.waitForTimeout(500);
	const buf = await page.screenshot({ fullPage: true });
	await ctx.close();
	return PNG.sync.read(buf);
}

const rows = [];
for (const [name, url] of pages) {
	for (const [form, vp] of Object.entries(viewports)) {
		const a = await shot(url, vp, false);
		const b = await shot(url, vp, true);
		const w = Math.max(a.width, b.width), h = Math.max(a.height, b.height);
		const pad = (img) => { const p = new PNG({ width: w, height: h }); PNG.bitblt(img, p, 0, 0, img.width, img.height, 0, 0); return p; };
		const pa = pad(a), pb = pad(b), diff = new PNG({ width: w, height: h });
		const n = pixelmatch(pa.data, pb.data, diff.data, w, h, { threshold: 0.1 });
		const pct = ((n / (w * h)) * 100).toFixed(3);
		rows.push(`| ${name}-${form} | ${a.height} | ${b.height} | ${pct}% |`);
		if (n > 0) {
			writeFileSync(`${outDir}/${name}-${form}-before.png`, PNG.sync.write(a));
			writeFileSync(`${outDir}/${name}-${form}-after.png`, PNG.sync.write(b));
			writeFileSync(`${outDir}/${name}-${form}-diff.png`, PNG.sync.write(diff));
		}
	}
}
await browser.close();
const md = `| Page | Height before | Height after | Pixels changed |\n|---|---|---|---|\n${rows.join('\n')}\n`;
writeFileSync(`${outDir}/visual.md`, md);
console.log(md);
