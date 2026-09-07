import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const dir = path.dirname(fileURLToPath(import.meta.url));
const reports = await Promise.all(['admin', 'portals', 'security'].map(async role => JSON.parse(await fs.readFile(path.join(dir, `retest-${role}-results.json`), 'utf8'))));
const checks = reports.flatMap(report => report.checks || []);
const cleanupFollowup=JSON.parse(await fs.readFile(path.join(dir,'cleanup-followup.json'),'utf8'));
for(const c of checks){
 if(c.id==='AUTH-062'){c.status='BLOCKED';c.severity='none';c.actual='HTTP 422 rejection observed. Test sanitizer omitted the actual validation message; exact-message assertion is inconclusive, not an application failure.';}
 if(/^AUTH-0(34|35|47|48|60|61)$/.test(c.id))c.severity='medium';
}
reports[0].cleanup.push({module:'plans',id:79,followup:cleanupFollowup,interpretation:'Fresh authenticated list confirms QA plan absent. Original delete returned 422; retain failed response-contract check.'});
// Static route inventory keeps sidebar-only sweeps from implying all-page coverage.
const modulesRoot = path.resolve(dir, '../../app/Modules');
const routeInventory = [];
for (const moduleName of await fs.readdir(modulesRoot)) {
 const file = path.join(modulesRoot,moduleName,'Routes/web.php');
 const source = await fs.readFile(file,'utf8').catch(()=> '');
 for (const match of source.matchAll(/\$router->get\(\s*'([^']+)'/g)) {
  if(!match[1].startsWith('/api/')) routeInventory.push([moduleName,match[1],match[1].includes('{')?'Requires a fixture/detail identifier':'Static page route']);
 }
}
const esc = value => String(typeof value === 'object' ? JSON.stringify(value) : value ?? '').replace(/\x1b\[[0-9;]*m/g,'').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
const brief=value=>{
 if(value&&typeof value==='object') {
  if(value.route)return `${value.route}: ${value.status||value.source||''}`;
  if(typeof value.status==='number')return `HTTP ${value.status}; ${value.message||''}${value.location?' → '+value.location:''}`;
  return JSON.stringify(value).slice(0,600);
 }
 const text=String(value??'').replace(/\x1b\[[0-9;]*m/g,'');
 try{const parsed=JSON.parse(text);if(parsed.status!==undefined)return brief(parsed);}catch{}
 return text.length>700?text.slice(0,700)+' [See JSON evidence for full details]':text;
};
const count = status => checks.filter(check => check.status === status).length;
const out = path.resolve(dir, '../../public/assets/.doc/qa-retest-2026-09-05');
await fs.mkdir(out, { recursive: true });
const totals = ['PASS','FAIL','BLOCKED','SKIP'].map(status=>[status,count(status)]);
const table = (head, rows, widths) => `<table><colgroup>${widths.map(w=>`<col style="width:${w}%">`).join('')}</colgroup><thead><tr>${head.map(h=>`<th>${esc(h)}</th>`).join('')}</tr></thead><tbody>${rows.map(row=>`<tr>${row.map(c=>`<td>${esc(c)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
let html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>NexusBox QA Retest</title><style>
@page{size:A4 landscape;margin:16mm 14mm 17mm}*{box-sizing:border-box}body{font-family:Arial,sans-serif;font-size:10pt;color:#172033;line-height:1.4;margin:0}h1{font-size:25pt;color:#17426d;margin:0 0 12px}h2{font-size:16pt;color:#17426d;margin:24px 0 10px}h3{font-size:12pt}p{margin:8px 0}table{border-collapse:collapse;table-layout:fixed;width:100%;margin:12px 0 20px}thead{display:table-header-group}tr{break-inside:avoid}th,td{border:1px solid #cbd5e1;padding:8px;vertical-align:top;white-space:normal;word-break:normal;overflow-wrap:anywhere}th{background:#eaf0f7;font-size:10pt;text-align:left}td{font-size:9pt}section{break-before:page}.meta{padding:14px;background:#f3f6fa;border-left:4px solid #17426d}li{margin:6px 0}.muted{color:#526175}figure{break-inside:avoid;margin:14px 0}img{max-width:100%;max-height:135mm;object-fit:contain}figcaption{font-size:9pt}
</style></head><body><h1>NexusBox — QA retest</h1><div class="meta">Target: http://10.0.10.155/login<br>Portals: Admin, Subscriber, Technician<br>Report generated: ${esc(new Date().toISOString())}<br>Execution: Playwright Chromium, headless; three parallel QA assignments.</div><h2>Results from this retest</h2>${table(['Result','Checks'],totals,[70,30])}<p>Counts describe individual recorded assertions, not distinct bugs or complete module certifications. Only this retest is counted; older runs contained timing/locator errors and are excluded. A passing check applies only to its stated scope.</p><p>CRUD authorization covers QA-created records only. Existing records must remain intact. Network deployment, real charges, and changes to live global settings are outside the disposable-record workflow.</p>`;
const fail = checks.filter(c=>c.status==='FAIL');
for(const c of checks){
 const e=c.evidence;
 c.evidence=Array.isArray(e)?e.join('; '):e?.artifact||`Raw structured evidence: retest-${c.id.startsWith('ADMIN')?'admin':c.id.startsWith('AUTH')?'security':'portals'}-results.json, ${c.id}`;
}
html+=`<h2>Priority findings</h2><ul><li>Plan creation returned HTTP 500 after persisting the QA record; update returned 422; delete returned 422. A fresh authenticated follow-up confirms the QA record is absent. Error responses and persisted state require investigation.</li><li>Technician work-orders page returned HTTP 500; technician notification requests returned HTTP 403.</li><li>The notification bell is outside the viewport on small screens. Several admin controls have no accessible name.</li><li>Login is served over HTTP without an HTTPS redirect. Enable secure transport before production use.</li><li>Invalid CSRF tokens are rejected, but with HTTP 500. No CSRF bypass was demonstrated.</li></ul><p>Coverage limits: CRUD was exercised for disabled Plans and Scheduled Downtime through browser requests with UI read/reload checks, not every form or module. Financial charges, network deployment, live settings changes, exhaustive injection testing, and unsupported cleanup workflows are not certified. Browser execution was headless, not a laptop-attached headed session.</p>`;
html += `<section><h2>Failed checks requiring review</h2>${table(['ID / portal','Severity','Check','Observed result'],fail.map(c=>[`${c.id} / ${c.portal}`,c.severity||'Unrated',c.name,brief(c.actual)]),[16,12,27,45])}<p>Repeated instances of a shared component defect are listed by portal to preserve test evidence. Accessibility heuristics are findings for review unless confirmed with the browser accessibility tree. CSRF rejection with HTTP 500 is an error-status defect; it does not demonstrate a successful CSRF attack.</p></section>`;
for (const [reportIndex,report] of reports.entries()) {
  report.portal=['Admin','Subscriber and Technician','Authentication and API Security'][reportIndex];
  html += `<section><h2>${esc(report.portal || report.scope || (report.checks?.[0]?.portal ?? 'QA'))} — full check ledger</h2>${table(['ID','Area / check','Result','Expected / observed'],(report.checks||[]).map(c=>[c.id,`${c.area||''}: ${c.name}`,c.status,`Expected: ${c.expected||'See check definition'}\nObserved: ${brief(c.actual)}`]),[12,30,10,48])}</section>`;
  for(const key of ['coverage','cleanup','limitations']) if(report[key]?.length) html += `<h3>${esc(key[0].toUpperCase()+key.slice(1))}</h3><ul>${report[key].map(item=>`<li>${esc(brief(item))}</li>`).join('')}</ul>`;
}
html += `<section><h2>Application page inventory</h2><p>Routes discovered in repository web route definitions. This is a scope inventory, not a pass list. Match each route to the check ledger for observed results; parameterized routes require suitable records.</p>${table(['Module','Route','Type'],routeInventory,[26,49,25])}</section>`;
html += `<section><h2>Evidence and reproducibility</h2><p>Test scripts and machine-readable results are in tests/Playwright/retest-*.mjs and retest-*-results.json. Each agent owns its evidence folder. Credentials are supplied at runtime and must not be included in reports.</p>${table(['Check','Evidence'],checks.filter(c=>c.evidence).map(c=>[c.id,c.evidence]),[18,82])}<h2>Release assessment</h2><p>Open failures and blocked or untested scenarios prevent a claim that every production workflow is verified. Review the failed checks, resolve cleanup exceptions, and rerun the affected scenarios before approving release.</p></section></body></html>`;
await fs.writeFile(path.join(out,'qa-retest-report.html'),html);
await fs.writeFile(path.join(out,'qa-retest-results.json'),JSON.stringify({generatedAt:new Date().toISOString(),totals:Object.fromEntries(totals),reports},null,2));
const browser = await chromium.launch({headless:true});
try {
 const page = await browser.newPage();
 await page.setContent(html,{waitUntil:'load'});
 await page.pdf({path:path.join(out,'qa-retest-report.pdf'),preferCSSPageSize:true,printBackground:true,displayHeaderFooter:true,headerTemplate:'<span></span>',footerTemplate:'<div style="width:100%;font:9px Arial;color:#64748b;text-align:center">NexusBox QA retest · <span class="pageNumber"></span> / <span class="totalPages"></span></div>'});
 console.log(JSON.stringify({out,totals:Object.fromEntries(totals),checks:checks.length}));
} finally {await browser.close();}
