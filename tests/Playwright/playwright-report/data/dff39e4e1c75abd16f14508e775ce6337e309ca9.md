# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: qa.spec.js >> admin portal module smoke >> pages expose basic accessible structure and no uncaught runtime errors
- Location: tests/Playwright/qa.spec.js:91:5

# Error details

```
Error: admin unlabeled controls on /subscriber-plans

expect(received).toEqual(expected) // deep equality

- Expected  - 1
+ Received  + 5

- Array []
+ Array [
+   "input#",
+   "select#",
+   "select#",
+ ]
```

# Page snapshot

```yaml
- generic [active] [ref=f3e1]:
  - complementary "Primary navigation" [ref=f3e2]:
    - generic [ref=f3e4]:
      - img "ISP-IN-A-BOX logo" [ref=f3e5]
      - generic [ref=f3e6]:
        - generic [ref=f3e7]: ISP-IN-A-BOX
        - generic [ref=f3e8]: Powered by 1WAN
    - list [ref=f3e9]:
      - listitem [ref=f3e10]:
        - button "Operations" [expanded] [ref=f3e11] [cursor=pointer]:
          - generic [ref=f3e13]: 
      - listitem [ref=f3e14]:
        - link "Dashboard" [ref=f3e15] [cursor=pointer]:
          - /url: /dashboard
          - generic [ref=f3e16]: 
      - listitem [ref=f3e18]:
        - link "Plans" [ref=f3e19] [cursor=pointer]:
          - /url: /subscriber-plans
          - generic [ref=f3e20]: 
      - listitem [ref=f3e22]:
        - link "Subscribers" [ref=f3e23] [cursor=pointer]:
          - /url: /subscribers
          - generic [ref=f3e24]: 
      - listitem [ref=f3e26]:
        - button "Network" [expanded] [ref=f3e27] [cursor=pointer]:
          - generic [ref=f3e29]: 
      - listitem [ref=f3e30]:
        - link "BNG" [ref=f3e31] [cursor=pointer]:
          - /url: /bng
          - generic [ref=f3e32]: 
      - listitem [ref=f3e34]:
        - link "Routers" [ref=f3e35] [cursor=pointer]:
          - /url: /routers
          - generic [ref=f3e36]: 
      - listitem [ref=f3e38]:
        - link "CGNAT" [ref=f3e39] [cursor=pointer]:
          - /url: /cgnat
          - generic [ref=f3e40]: 
      - listitem [ref=f3e42]:
        - link "VLAN" [ref=f3e43] [cursor=pointer]:
          - /url: /vlan-management
          - generic [ref=f3e44]: 
      - listitem [ref=f3e46]:
        - link "RADIUS" [ref=f3e47] [cursor=pointer]:
          - /url: /radius
          - generic [ref=f3e48]: 
      - listitem [ref=f3e50]:
        - link "OLT" [ref=f3e51] [cursor=pointer]:
          - /url: /olt-management
          - generic [ref=f3e52]: 
      - listitem [ref=f3e54]:
        - link "NAP" [ref=f3e55] [cursor=pointer]:
          - /url: /nap-management
          - generic [ref=f3e56]: 
      - listitem [ref=f3e58]:
        - link "ONT" [ref=f3e59] [cursor=pointer]:
          - /url: /ont-devices
          - generic [ref=f3e60]: 
      - listitem [ref=f3e62]:
        - link "Provisioning" [ref=f3e63] [cursor=pointer]:
          - /url: /service-provisioning
          - generic [ref=f3e64]: 
      - listitem [ref=f3e66]:
        - button "Billing" [ref=f3e67] [cursor=pointer]:
          - generic [ref=f3e69]: 
      - listitem [ref=f3e70]:
        - button "Support Ticket" [ref=f3e71] [cursor=pointer]:
          - generic [ref=f3e73]: 
      - listitem [ref=f3e74]:
        - button "Workforce" [ref=f3e75] [cursor=pointer]:
          - generic [ref=f3e77]: 
      - listitem [ref=f3e78]:
        - button "Admin" [ref=f3e79] [cursor=pointer]:
          - generic [ref=f3e81]: 
      - listitem [ref=f3e82]:
        - button "Maintenance" [ref=f3e83] [cursor=pointer]:
          - generic [ref=f3e85]: 
      - listitem [ref=f3e86]:
        - button "Security" [ref=f3e87] [cursor=pointer]:
          - generic [ref=f3e89]: 
    - generic [ref=f3e91]:
      - generic [ref=f3e92]: A
      - generic [ref=f3e93]:
        - generic [ref=f3e94]: admin
        - generic [ref=f3e95]: SUPERADMIN
      - button "Log out" [ref=f3e97] [cursor=pointer]:
        - generic [ref=f3e98]: 
  - generic [ref=f3e99]:
    - generic [ref=f3e100]:
      - generic [ref=f3e101]:
        - button "Toggle navigation" [expanded] [ref=f3e102] [cursor=pointer]:
          - generic [ref=f3e103]: 
        - navigation "Current page" [ref=f3e104]:
          - generic [ref=f3e105]: Operations
          - generic [ref=f3e106]: 
          - generic [ref=f3e107]: Plans
      - generic [ref=f3e108]:
        - search [ref=f3e109]:
          - generic [ref=f3e110]: 
          - searchbox "Search accessible modules" [ref=f3e111]
        - button "View notifications (1 alerts)" [ref=f3e112] [cursor=pointer]:
          - generic [ref=f3e113]: 
          - generic: "1"
        - text:            
        - generic [ref=f3e114]:
          - button "Switch to dark mode" [ref=f3e115] [cursor=pointer]:
            - generic [ref=f3e116]: 
          - button "Log out" [ref=f3e118] [cursor=pointer]:
            - generic [ref=f3e119]: 
    - generic [ref=f3e122]:
      - banner [ref=f3e123]:
        - generic [ref=f3e124]:
          - paragraph [ref=f3e125]: Commercial catalog
          - heading "Plan inventory" [level=1] [ref=f3e126]
          - paragraph [ref=f3e127]: Commercial service profiles for subscriber activation, billing, and provisioning.
        - generic [ref=f3e128]:
          - button "+ New Plan" [ref=f3e129] [cursor=pointer]
          - button "Refresh" [ref=f3e130] [cursor=pointer]
      - generic [ref=f3e131]:
        - article [ref=f3e132]:
          - generic [ref=f3e133]:
            - paragraph [ref=f3e134]: Total Plans
            - paragraph [ref=f3e135]: "1"
            - paragraph [ref=f3e136]: Commercial packages configured
        - article [ref=f3e137]:
          - generic [ref=f3e138]:
            - paragraph [ref=f3e139]: Postpaid
            - paragraph [ref=f3e140]: "1"
            - paragraph [ref=f3e141]: Recurring commercial plans
        - article [ref=f3e142]:
          - generic [ref=f3e143]:
            - paragraph [ref=f3e144]: Prepaid
            - paragraph [ref=f3e145]: "0"
            - paragraph [ref=f3e146]: Time-bound service plans
        - article [ref=f3e147]:
          - generic [ref=f3e148]:
            - paragraph [ref=f3e149]: Average ARPU
            - paragraph [ref=f3e150]: ₱1,099.00
            - paragraph [ref=f3e151]: Average catalog price
      - generic [ref=f3e153]:
        - generic [ref=f3e154]:
          - generic [ref=f3e155]: Search plans
          - searchbox "Search plans" [ref=f3e156]
        - generic [ref=f3e157]:
          - combobox [ref=f3e158]:
            - option "All Types" [selected]
            - option "Prepaid"
            - option "Postpaid"
          - combobox [ref=f3e159]:
            - option "All Statuses" [selected]
            - option "Active"
            - option "Inactive"
      - generic [ref=f3e160]:
        - table [ref=f3e162]:
          - rowgroup [ref=f3e163]:
            - row [ref=f3e164]:
              - columnheader "Plan" [ref=f3e165]
              - columnheader "Type" [ref=f3e166]
              - columnheader "Validity" [ref=f3e167]
              - columnheader "Speed" [ref=f3e168]
              - columnheader "Price" [ref=f3e169]
              - columnheader "Status" [ref=f3e170]
              - columnheader "Description" [ref=f3e171]
              - columnheader "Actions" [ref=f3e172]
          - rowgroup [ref=f3e173]:
            - row [ref=f3e174]:
              - cell "PLAN-25M" [ref=f3e175]
              - cell "POSTPAID" [ref=f3e176]
              - cell "30 days" [ref=f3e178]
              - cell "25 Mbps" [ref=f3e179]
              - cell "₱1,099.00" [ref=f3e180]
              - cell "ACTIVE" [ref=f3e181]
              - cell "25MBPS" [ref=f3e183]
              - cell [ref=f3e184]:
                - generic [ref=f3e185]:
                  - button "View" [ref=f3e186] [cursor=pointer]:
                    - generic: 
                  - button "Edit" [ref=f3e187] [cursor=pointer]:
                    - generic: 
                  - button "Delete" [ref=f3e188] [cursor=pointer]:
                    - generic: 
        - generic [ref=f3e189]:
          - generic [ref=f3e190]: Page 1 of 1 · 1 records
          - generic [ref=f3e191]:
            - button "Previous" [disabled] [ref=f3e192]
            - button "Next" [disabled] [ref=f3e193]
```

# Test source

```ts
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
  115 |           const id = node.id;
  116 |           return !(id && document.querySelector(`label[for="${CSS.escape(id)}"]`));
  117 |         }).map(node => `${node.tagName.toLowerCase()}#${node.id}`));
> 118 |         expect(unlabeledControls, `${account.name} unlabeled controls on ${path}`).toEqual([]);
      |                                                                                    ^ Error: admin unlabeled controls on /subscriber-plans
  119 |       }
  120 |       expect(serverErrors, serverErrors.join('\n')).toEqual([]);
  121 |       expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
  122 |       await logout(page);
  123 |     });
  124 | 
  125 |     test('protected page redirects after logout and cannot be restored with back navigation', async ({ page }) => {
  126 |       await login(page, account);
  127 |       const protectedPath = new URL(page.url()).pathname === '/login' ? '/dashboard' : new URL(page.url()).pathname;
  128 |       await logout(page);
  129 |       await page.goto(protectedPath, { waitUntil: 'domcontentloaded' });
  130 |       await expect(page).toHaveURL(/\/login/);
  131 |       await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {});
  132 |       expect(new URL(page.url()).pathname).toBe('/login');
  133 |     });
  134 | 
  135 |     test('responsive layouts do not introduce horizontal page overflow', async ({ page }) => {
  136 |       await login(page, account);
  137 |       for (const width of [320, 390, 768, 1024]) {
  138 |         await page.setViewportSize({ width, height: 844 });
  139 |         await page.reload({ waitUntil: 'domcontentloaded' });
  140 |         const metrics = await page.evaluate(() => ({
  141 |           viewport: window.innerWidth,
  142 |           scrollWidth: document.documentElement.scrollWidth,
  143 |           scrollHeight: document.documentElement.scrollHeight,
  144 |         }));
  145 |         expect(metrics.scrollWidth, `${account.name} horizontal overflow at ${width}px`).toBeLessThanOrEqual(metrics.viewport + 2);
  146 |       }
  147 |       await logout(page);
  148 |     });
  149 | 
  150 |     test('keyboard navigation reaches visible controls', async ({ page }) => {
  151 |       await login(page, account);
  152 |       await page.keyboard.press('Tab');
  153 |       const focused = await page.evaluate(() => {
  154 |         const element = document.activeElement;
  155 |         if (!element || element === document.body) return { valid: false, tag: '' };
  156 |         const rect = element.getBoundingClientRect();
  157 |         return { valid: true, tag: element.tagName, visible: rect.width > 0 && rect.height > 0 };
  158 |       });
  159 |       expect(focused.valid).toBe(true);
  160 |       expect(focused.visible).toBe(true);
  161 |       await logout(page);
  162 |     });
  163 |   });
  164 | }
  165 | 
```