# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: qa.spec.js >> technician portal module smoke >> keyboard navigation reaches visible controls
- Location: tests/Playwright/qa.spec.js:150:5

# Error details

```
TimeoutError: page.waitForFunction: Timeout 12000ms exceeded.
```

# Page snapshot

```yaml
- generic [ref=e1]:
  - main [ref=e2]:
    - region [ref=e3]:
      - generic [ref=e6]:
        - generic [ref=e7]: ISP-IN-A-BOX
        - heading [level=1] [ref=e8]: Access the operations console
        - paragraph [ref=e9]: Use your assigned credentials to continue.
        - generic [ref=e10]:
          - generic [ref=e11]:
            - generic [ref=e12]: Username
            - textbox [ref=e13]:
              - /placeholder: Enter your username
              - text: Lazy
          - generic [ref=e14]:
            - generic [ref=e15]: Password
            - generic [ref=e16]:
              - textbox [ref=e17]:
                - /placeholder: Enter your password
                - text: Pass.12345
              - button [ref=e18] [cursor=pointer]: Show
          - button [ref=e20] [cursor=pointer]: Forgot your password?
          - button [disabled] [ref=e21]: Signing in…
        - text:  
        - generic [ref=e22]:
          - strong [ref=e23]: Protected access
          - paragraph [ref=e24]: Role-based permissions, session monitoring and audit logging are enabled.
      - generic [ref=e25]: Powered by 1WAN
  - dialog [ref=e30]:
    - heading "Login failed" [level=2] [ref=e35]
    - generic [ref=e36]: Too many login attempts. Try again later.
    - text: "!"
    - button "OK" [active] [ref=e38] [cursor=pointer]
```

# Test source

```ts
  1   | import { test, expect } from '@playwright/test';
  2   | 
  3   | const roles = [
  4   |   { name: 'admin', username: process.env.QA_ADMIN_USER, password: process.env.QA_ADMIN_PASSWORD },
  5   |   { name: 'subscriber', username: process.env.QA_SUBSCRIBER_USER, password: process.env.QA_SUBSCRIBER_PASSWORD },
  6   |   { name: 'technician', username: process.env.QA_TECHNICIAN_USER, password: process.env.QA_TECHNICIAN_PASSWORD },
  7   | ];
  8   | 
  9   | async function login(page, account) {
  10  |   await page.goto('/login', { waitUntil: 'domcontentloaded' });
  11  |   await page.locator('input[name="username"]').fill(account.username);
  12  |   await page.locator('input[name="password"]').fill(account.password);
  13  |   await page.locator('#loginForm button[type="submit"], button[type="submit"]').first().click();
> 14  |   await page.waitForFunction(() => location.pathname !== '/login' || document.querySelector('#primarySidebar'), null, { timeout: 12_000 });
      |              ^ TimeoutError: page.waitForFunction: Timeout 12000ms exceeded.
  15  | }
  16  | 
  17  | async function logout(page) {
  18  |   const logout = page.locator('#nxRectilinearTheme form[action="/logout"] button[type="submit"]');
  19  |   if (await logout.count()) {
  20  |     await logout.click();
  21  |     await expect(page).toHaveURL(/\/login/);
  22  |   }
  23  | }
  24  | 
  25  | test.describe('login security', () => {
  26  |   for (const account of roles) {
  27  |     test(`${account.name} login rejects SQL injection`, async ({ page }) => {
  28  |       await page.goto('/login', { waitUntil: 'domcontentloaded' });
  29  |       await page.locator('input[name="username"]').fill("' OR '1'='1");
  30  |       await page.locator('input[name="password"]').fill("' OR '1'='1");
  31  |       await page.locator('#loginForm button[type="submit"], button[type="submit"]').first().click();
  32  |       await page.waitForTimeout(700);
  33  |       expect(new URL(page.url()).pathname).toBe('/login');
  34  |       await expect(page.locator('body')).not.toContainText(/SQLSTATE|PDOException|syntax error/i);
  35  |     });
  36  | 
  37  |     test(`${account.name} valid login reaches an authenticated page`, async ({ page }) => {
  38  |       await login(page, account);
  39  |       await expect(page.locator('#primarySidebar, aside.sidebar')).toBeVisible({ timeout: 12_000 });
  40  |       await expect(page.locator('body')).not.toContainText(/SQLSTATE|PDOException|Fatal error/i);
  41  |       await logout(page);
  42  |     });
  43  |   }
  44  | });
  45  | 
  46  | for (const account of roles) {
  47  |   test.describe(`${account.name} portal module smoke`, () => {
  48  |     test('all visible navigation modules load without server or console errors', async ({ page }) => {
  49  |       const consoleErrors = [];
  50  |       const failedRequests = [];
  51  |       page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  52  |       page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));
  53  | 
  54  |       await login(page, account);
  55  |       const links = await page.locator('#primarySidebar a[href], aside.sidebar a[href]').evaluateAll(nodes => nodes.map(node => ({ href: node.href, label: node.textContent.trim() })));
  56  |       const unique = [...new Map(links.map(link => [new URL(link.href).pathname, link])).values()];
  57  |       expect(unique.length).toBeGreaterThan(0);
  58  | 
  59  |       for (const link of unique) {
  60  |         await page.goto(link.href, { waitUntil: 'domcontentloaded' });
  61  |         expect(new URL(page.url()).pathname, `${account.name} redirected while opening ${link.label}`).not.toBe('/login');
  62  |         await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException/i);
  63  |       }
  64  | 
  65  |       expect(failedRequests, failedRequests.join('\n')).toEqual([]);
  66  |       expect(consoleErrors.filter(error => !/favicon/i.test(error)), consoleErrors.join('\n')).toEqual([]);
  67  |       await logout(page);
  68  |     });
  69  | 
  70  |     test('responsive navigation and notification badge behavior are usable', async ({ page }) => {
  71  |       await login(page, account);
  72  |       await page.setViewportSize({ width: 390, height: 844 });
  73  |       await page.reload({ waitUntil: 'domcontentloaded' });
  74  |       const toggle = page.locator('#sidebarToggleBtn');
  75  |       if (await toggle.count()) {
  76  |         await toggle.click();
  77  |         await expect(page.locator('body')).toHaveClass(/sidebar-mobile-open/);
  78  |         const scrim = page.locator('#sidebarScrim');
  79  |         if (await scrim.count()) await scrim.click({ force: true });
  80  |       }
  81  |       const bell = page.locator('#globalNotificationsToggle');
  82  |       if (await bell.count()) {
  83  |         await bell.click({ force: true });
  84  |         await expect(page.locator('#globalNotifications')).toBeVisible();
  85  |         const badge = page.locator('#globalNotificationsCount');
  86  |         if (await badge.count() && await badge.isVisible()) expect(await badge.textContent()).not.toMatch(/^0$/);
  87  |       }
  88  |       await logout(page);
  89  |     });
  90  | 
  91  |     test('pages expose basic accessible structure and no uncaught runtime errors', async ({ page }) => {
  92  |       const runtimeErrors = [];
  93  |       const serverErrors = [];
  94  |       page.on('pageerror', error => runtimeErrors.push(error.message));
  95  |       page.on('response', response => {
  96  |         if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
  97  |       });
  98  | 
  99  |       await login(page, account);
  100 |       const links = await page.locator('#primarySidebar a[href], aside.sidebar a[href]').evaluateAll(nodes => nodes.map(node => node.href));
  101 |       const paths = [...new Set(links.map(href => new URL(href).pathname))];
  102 |       for (const path of paths) {
  103 |         await page.goto(path, { waitUntil: 'domcontentloaded' });
  104 |         await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException|Unhandled exception/i);
  105 |         const duplicateIds = await page.locator('[id]').evaluateAll(nodes => {
  106 |           const counts = new Map();
  107 |           nodes.forEach(node => counts.set(node.id, (counts.get(node.id) || 0) + 1));
  108 |           return [...counts.entries()].filter(([, count]) => count > 1).map(([id]) => id);
  109 |         });
  110 |         expect(duplicateIds, `${account.name} duplicate IDs on ${path}`).toEqual([]);
  111 |         const unlabeledControls = await page.locator('input:not([type="hidden"]), select, textarea, button').evaluateAll(nodes => nodes.filter(node => {
  112 |           if (node.disabled || node.getAttribute('aria-hidden') === 'true') return false;
  113 |           if (node.tagName === 'BUTTON' && node.textContent.trim()) return false;
  114 |           if (node.getAttribute('aria-label') || node.getAttribute('title')) return false;
```