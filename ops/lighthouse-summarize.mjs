// Reduce raw Lighthouse JSON runs to the median run per page and form factor,
// a summary table, and a trimmed list of the audits that are not passing.
// Usage: node ops/lighthouse-summarize.mjs <raw-dir> <out-dir>
import { readdirSync, readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const [inDir, outDir] = process.argv.slice(2);
mkdirSync(outDir, { recursive: true });
const cats = ['performance', 'accessibility', 'best-practices', 'seo'];
const groups = {};
for (const f of readdirSync(inDir).filter((f) => f.endsWith('.json'))) {
	const [name, form] = f.split('-');
	try {
		const lhr = JSON.parse(readFileSync(`${inDir}/${f}`, 'utf8'));
		(groups[`${name}-${form}`] ??= []).push(lhr);
	} catch { /* skip broken run */ }
}
const score = (lhr, c) => Math.round((lhr.categories[c]?.score ?? 0) * 100);
const rows = [];
const order = ['home', 'members', 'class', 'member', 'team', 'tournament'];
const keys = Object.keys(groups).sort((a, b) => {
	const [an, af] = a.split('-'), [bn, bf] = b.split('-');
	return order.indexOf(an) - order.indexOf(bn) || af.localeCompare(bf);
});
for (const key of keys) {
	const runs = groups[key].sort((a, b) => score(a, 'performance') - score(b, 'performance'));
	const lhr = runs[Math.floor(runs.length / 2)];
	const audits = lhr.audits;
	const failing = Object.values(audits)
		.filter((a) => a.score !== null && a.score < 0.9 && !['manual', 'notApplicable', 'informative'].includes(a.scoreDisplayMode))
		.map((a) => ({
			id: a.id, title: a.title, score: a.score, display: a.displayValue,
			items: (Array.isArray(a.details?.items) ? a.details.items : []).slice(0, 8).map((it) => {
				const o = {};
				for (const [k, v] of Object.entries(it)) {
					if (v && typeof v === 'object') {
						if (v.type === 'node') o[k] = { selector: v.selector, snippet: v.snippet?.slice(0, 200), label: v.nodeLabel?.slice(0, 80) };
						else if (v.type !== 'table' && v.type !== 'list') o[k] = JSON.stringify(v).slice(0, 200);
					} else o[k] = v;
				}
				return o;
			}),
		}));
	const metrics = Object.fromEntries(['first-contentful-paint', 'largest-contentful-paint', 'total-blocking-time', 'cumulative-layout-shift', 'speed-index', 'server-response-time']
		.map((id) => [id, audits[id]?.displayValue]));
	const lcp = audits['largest-contentful-paint-element']?.details?.items?.[0]?.items?.[0]?.node?.snippet;
	writeFileSync(`${outDir}/${key}.json`, JSON.stringify({ url: lhr.finalDisplayedUrl, scores: Object.fromEntries(cats.map((c) => [c, score(lhr, c)])), allRuns: runs.map((r) => cats.map((c) => score(r, c))), metrics, lcp, failing }, null, 1));
	rows.push(`| ${key} | ${cats.map((c) => score(lhr, c)).join(' | ')} | ${metrics['largest-contentful-paint']} | ${metrics['total-blocking-time']} | ${metrics['cumulative-layout-shift']} |`);
}
const md = `| Page | Perf | A11y | Best practices | SEO | LCP | TBT | CLS |\n|---|---|---|---|---|---|---|---|\n${rows.join('\n')}\n`;
writeFileSync(`${outDir}/summary.md`, md);
console.log(md);
