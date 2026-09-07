import { chromium } from 'playwright';
import { mkdir, readFile, writeFile, readdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import readline from 'node:readline';

// Usage: QA_ADMIN_USER=... QA_ADMIN_PASSWORD=... node tests/Playwright/retest-admin.mjs
// --interactive retains this single context while locally reviewed step modules are added.
const dir = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(dir, '../..');
const artifactDir = path.join(dir, 'admin');
const target = process.env.QA_BASE_URL || 'http://10.0.10.155/login';
const baseURL = new URL(target).origin;
const result = { target, startedAt: new Date().toISOString(), completedAt: null, checks: [], cleanup: [], coverage: [], limitations: [] };
const prefix = `pw-qa-admin-${Date.now()}`;
const inventory = [];
const save = () => writeFile(path.join(dir, 'retest-admin-results.json'), JSON.stringify(result, null, 2));
const scrub = value => String(value).replace(/[a-f0-9]{32,}/gi, '[redacted]').replace(/(password|token|secret|cookie|authorization)(\s*[:=]\s*)[^\s,;]+/gi, '$1$2[redacted]');
const urlOnly = u => { const v = new URL(u, baseURL); return v.origin === baseURL ? v.pathname : v.origin + v.pathname; };
function check(area, name, status, expected, actual, evidence = {}, severity = 'none') {
  const c = { id: `ADMIN-${String(result.checks.length + 1).padStart(3, '0')}`, portal: 'ADMIN', area, name, status, expected: String(expected).slice(0, 500), actual: scrub(typeof actual === 'string' ? actual : JSON.stringify(actual)).slice(0, 1200), evidence, severity: status === 'FAIL' ? severity === 'none' ? 'medium' : severity : 'none' };
  result.checks.push(c); console.log(JSON.stringify({ id: c.id, name, status })); return c;
}
await mkdir(artifactDir, { recursive: true });
for (const module of await readdir(path.join(root, 'app/Modules'))) {
  for (const file of ['web.php', 'api.php']) {
    const source = `app/Modules/${module}/Routes/${file}`;
    const text = await readFile(path.join(root, source), 'utf8').catch(() => '');
    for (const m of text.matchAll(/\$router->(get|post|put|delete|patch)\(\s*['"]([^'"]+)['"]/g)) inventory.push({ method: m[1].toUpperCase(), route: m[2], module, source });
  }
}
await writeFile(path.join(artifactDir, 'route-inventory.json'), JSON.stringify(inventory, null, 2));
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ baseURL, viewport: { width: 1440, height: 1000 } });
const page = await context.newPage();
page.setDefaultTimeout(10000);
let events = [], pending = new Set(), lastActivity = Date.now();
const relevant = req => ['document', 'xhr', 'fetch', 'script', 'stylesheet'].includes(req.resourceType());
page.on('request', req => { if (relevant(req)) { pending.add(req); lastActivity = Date.now(); } });
page.on('requestfinished', req => { pending.delete(req); lastActivity = Date.now(); });
page.on('requestfailed', req => { pending.delete(req); lastActivity = Date.now(); events.push({ type: 'requestfailed', method: req.method(), url: urlOnly(req.url()), error: scrub(req.failure()?.errorText) }); });
page.on('response', res => { if (['xhr', 'fetch', 'document'].includes(res.request().resourceType()) || res.status() >= 400) events.push({ type: 'http', method: res.request().method(), url: urlOnly(res.url()), status: res.status(), resource: res.request().resourceType() }); });
page.on('pageerror', error => events.push({ type: 'pageerror', error: scrub(error.message) }));
page.on('console', message => { if (message.type() === 'error') events.push({ type: 'console', error: scrub(message.text()).slice(0, 350) }); });
// Guard ordinary page visits against automatic mutations, including billing generation.
let allowedWrites = new Set(['/login']);
await context.route('**/*', async route => {
  const req = route.request(), u = new URL(req.url());
  if (u.origin === baseURL && !['GET', 'HEAD', 'OPTIONS'].includes(req.method()) && !allowedWrites.has(u.pathname)) {
    events.push({ type: 'blocked-write', method: req.method(), url: u.pathname });
    return route.fulfill({ status: 403, contentType: 'application/json', body: JSON.stringify({ success: false, message: 'QA scope guard: mutation not authorized for this step.' }) });
  }
  return route.continue();
});
async function settle(timeout = 20000) {
  const start = Date.now();
  while (Date.now() - start < timeout) {
    if (!pending.size && Date.now() - lastActivity > 850) break;
    await page.waitForTimeout(200);
  }
  const loading = await page.locator('body').evaluate(body => [...body.querySelectorAll('[aria-busy="true"], .spinner-border, .nx-loading, .loading')].filter(el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden').length);
  return { pending: [...pending].map(r => urlOnly(r.url())), visibleLoadingIndicators: loading, elapsedMs: Date.now() - start };
}
async function names() {
  const cdp = await context.newCDPSession(page);
  const { nodes } = await cdp.send('Accessibility.getFullAXTree');
  await cdp.detach();
  const roles = ['button', 'textbox', 'combobox', 'checkbox', 'radio', 'tab', 'searchbox', 'switch', 'spinbutton'];
  return nodes.filter(n => !n.ignored && roles.includes(n.role?.value)).map(n => ({ role: n.role.value, name: scrub(n.name?.value || ''), disabled: n.properties?.some(p => p.name === 'disabled' && p.value?.value) || false }));
}
async function visit(route, source = 'source route') {
  events = []; pending.clear(); lastActivity = Date.now();
  const record = { route, source, viewport: { width: 1440, height: 1000 }, status: 'BLOCKED' };
  result.coverage.push(record);
  try {
    const response = await page.goto(route, { waitUntil: 'domcontentloaded', timeout: 30000 });
    const ready = await settle();
    const content = await page.locator('body').innerText();
    const controls = await names();
    const bad = events.filter(e => e.type === 'pageerror' || e.type === 'console' || e.type === 'requestfailed' || e.type === 'http' && e.status >= 400);
    const guarded = events.filter(e => e.type === 'blocked-write');
    const denied = response?.status() === 403 || /Access denied|Forbidden|do not have permission/i.test(content.slice(0, 1200));
    const status = denied || guarded.length ? 'BLOCKED' : response?.status() >= 400 || new URL(page.url()).pathname === '/login' || /SQLSTATE|PDOException|Fatal error/i.test(content) || bad.length || ready.pending.length || ready.visibleLoadingIndicators ? 'FAIL' : 'PASS';
    Object.assign(record, { status, httpStatus: response?.status(), finalPath: new URL(page.url()).pathname, ready, events: [...events], controls, headings: await page.locator('h1,h2,h3').allTextContents() });
    const artifact = `admin/${route.replace(/[^a-z0-9]/gi, '_') || 'root'}-desktop.json`;
    await writeFile(path.join(dir, artifact), JSON.stringify(record, null, 2));
    check('page loading', `${route}: desktop module and API completion`, status, 'Route renders its module, loading settles, requests complete without runtime or HTTP failures.', denied ? 'Role denied access.' : guarded.length ? 'Automatic write blocked by QA scope guard.' : { httpStatus: record.httpStatus, ready, failures: bad }, { route, artifact }, 'high');
    const unnamed = controls.filter(c => !c.disabled && !c.name);
    check('accessibility', `${route}: control accessible names`, denied ? 'BLOCKED' : unnamed.length ? 'FAIL' : 'PASS', 'Every exposed enabled control has an accessible name in the browser accessibility tree.', { totalControls: controls.length, unnamed }, { route, artifact }, 'medium');
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(300);
    const mobile = await page.evaluate(() => ({ width: innerWidth, scrollWidth: document.documentElement.scrollWidth, bodyScrollWidth: document.body.scrollWidth }));
    record.mobile = mobile;
    check('responsive', `${route}: mobile document overflow`, denied ? 'BLOCKED' : mobile.scrollWidth > mobile.width + 2 ? 'FAIL' : 'PASS', 'Document fits 390px viewport; wide content scrolls inside containers.', mobile, { route, viewport: '390x844' }, 'medium');
    await page.setViewportSize({ width: 1440, height: 1000 });
  } catch (e) {
    record.error = scrub(e.message).slice(0, 600); record.events = [...events];
    check('page loading', `${route}: desktop visit`, 'BLOCKED', 'Load module and observe settled API responses.', record.error, { route, events: record.events });
  }
  await save();
}
const api = async (endpoint, data) => {
  if (data !== undefined && !allowedWrites.has(endpoint)) throw new Error('Unapproved write endpoint');
  return page.evaluate(async ({ endpoint, data }) => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || document.querySelector('input[name="csrf_token"]')?.value;
    const res = await fetch(endpoint, { method: data === undefined ? 'GET' : 'POST', headers: { Accept: 'application/json', ...(data === undefined ? {} : { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf || '' }) }, ...(data === undefined ? {} : { body: JSON.stringify({ ...data, csrf_token: csrf }) }) });
    return { status: res.status, body: await res.json().catch(() => null) };
  }, { endpoint, data });
};
const helpers = { page, context, result, prefix, check, save, settle, names, visit, api, artifactDir, dir, inventory, allow: endpoint => allowedWrites.add(endpoint), events: () => [...events], resetEvents: () => { events = []; } };
try {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await settle();
  const before = await context.cookies();
  const loginControls = await names();
  await writeFile(path.join(artifactDir, 'login-controls.json'), JSON.stringify(loginControls, null, 2));
  if (!process.env.QA_ADMIN_USER || !process.env.QA_ADMIN_PASSWORD) throw new Error('QA_ADMIN_USER and QA_ADMIN_PASSWORD required');
  const username = page.getByRole('textbox', { name: /username/i });
  await username.fill(process.env.QA_ADMIN_USER);
  await page.getByLabel('Password', { exact: true }).fill(process.env.QA_ADMIN_PASSWORD);
  const loginResponse = page.waitForResponse(r => new URL(r.url()).pathname === '/login' && r.request().method() === 'POST');
  await page.getByRole('button', { name: /sign in|log in|login/i }).click();
  const lr = await loginResponse;
  await page.waitForURL(u => u.pathname !== '/login', { timeout: 15000 }).catch(() => {});
  await settle();
  if (new URL(page.url()).pathname === '/login') throw new Error(`Single login did not authenticate; POST status ${lr.status()}; no retry or login probes performed.`);
  check('authentication', 'Single valid admin login', 'PASS', 'One login opens authenticated admin portal.', { postStatus: lr.status(), finalPath: new URL(page.url()).pathname }, { artifact: 'admin/login-controls.json', loginAttempts: 1 });
  const after = await context.cookies();
  const session = before.find(c => /session|phpsess/i.test(c.name));
  const rotated = session && after.some(c => c.name === session.name && c.value !== session.value);
  check('authentication', 'Session identifier rotates after login', session ? rotated ? 'PASS' : 'FAIL' : 'BLOCKED', 'Existing anonymous session identifier changes on authentication.', session ? { rotated: Boolean(rotated) } : 'No pre-login session identifier observed.', {}, 'high');
  const nav = await page.locator('#primarySidebar a[href]').evaluateAll(es => es.map(e => ({ route: new URL(e.href).pathname, label: e.textContent.trim() })));
  await writeFile(path.join(artifactDir, 'navigation.json'), JSON.stringify(nav, null, 2));
  const pages = inventory.filter(r => r.method === 'GET' && !r.route.startsWith('/api/') && !r.route.includes('{') && !['/', '/login'].includes(r.route) && !/subscriber-portal|technician-portal/.test(r.route));
  const paths = [...new Set([...nav.map(n => n.route), ...pages.map(p => p.route)])].filter(r => r !== '/' && !/logout|login|subscriber-portal|technician-portal/.test(r));
  for (const route of paths) await visit(route, nav.some(n => n.route === route) ? 'admin navigation' : 'source route (not in navigation)');
  result.limitations.push('Login abuse probes deferred to main after other agents authenticate; one retained admin context. No brute force or limiter clearing.', 'Read-only page smoke and mobile width checks are not full workflow coverage. Parameterized pages and tab-specific coverage are separately recorded.', 'No production code changes. No charges, provisioning, deployment, global configuration or maintenance actions. Unapproved browser writes are blocked and identified as limitations.', 'Browser accessibility-tree names are a smoke check, not a full WCAG or screen-reader audit. No cookies, credential values, API payload dumps, storage state or raw traces are saved.');
  await save();
  console.log('ADMIN_INVENTORY_COMPLETE_SESSION_RETAINED');
  if (process.argv.includes('--interactive')) {
    const rl = readline.createInterface({ input: process.stdin, terminal: false });
    for await (const line of rl) {
      if (!line.trim()) continue;
      if (line.trim() === 'finish') { rl.close(); break; }
      try {
        const spec = JSON.parse(line);
        const filename = path.resolve(dir, spec.module);
        if (!filename.startsWith(artifactDir + '/')) throw new Error('Step module must be within admin artifact directory');
        await (await import(`file://${filename}?v=${Date.now()}`)).default(helpers);
        await save(); console.log('ADMIN_STEP_COMPLETE');
      } catch (e) { console.log('ADMIN_STEP_ERROR ' + scrub(e.message)); await save(); }
    }
  } else {
    await (await import('./admin/crud.mjs')).default(helpers);
    await (await import('./admin/final-checks.mjs')).default(helpers);
  }
} catch (e) {
  check('harness', 'Admin execution prerequisite', 'BLOCKED', 'Authenticated retained context available for coverage.', scrub(e.message).slice(0, 1000));
  result.limitations.push('Execution stopped; unexecuted checks are not counted.');
} finally {
  result.completedAt = new Date().toISOString();
  await save(); await browser.close();
  console.log(JSON.stringify({ counts: result.checks.reduce((a, c) => ({ ...a, [c.status]: (a[c.status] || 0) + 1 }), {}), cleanup: result.cleanup }));
}
