# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: qa.spec.js >> subscriber portal module smoke >> pages expose basic accessible structure and no uncaught runtime errors
- Location: tests/Playwright/qa.spec.js:91:5

# Error details

```
Error: subscriber unlabeled controls on /subscriber-portal

expect(received).toEqual(expected) // deep equality

- Expected  -  1
+ Received  + 10

- Array []
+ Array [
+   "button#",
+   "select#",
+   "input#",
+   "textarea#",
+   "button#",
+   "input#",
+   "input#",
+   "input#",
+ ]
```

# Page snapshot

```yaml
- generic [active] [ref=f2e1]:
  - complementary "Primary navigation" [ref=f2e2]:
    - generic [ref=f2e4]:
      - img "ISP-IN-A-BOX logo" [ref=f2e5]
      - generic [ref=f2e6]:
        - generic [ref=f2e7]: ISP-IN-A-BOX
        - generic [ref=f2e8]: Powered by 1WAN
    - list [ref=f2e9]:
      - listitem [ref=f2e10]:
        - button "Operations" [expanded] [ref=f2e11] [cursor=pointer]:
          - generic [ref=f2e13]: 
      - listitem [ref=f2e14]:
        - link "My Account" [ref=f2e15] [cursor=pointer]:
          - /url: /subscriber-portal
          - generic [ref=f2e16]: 
      - listitem [ref=f2e18]:
        - link "My Services" [ref=f2e19] [cursor=pointer]:
          - /url: /subscriber-portal/services
          - generic [ref=f2e20]: 
      - listitem [ref=f2e22]:
        - link "My Invoices" [ref=f2e23] [cursor=pointer]:
          - /url: /subscriber-portal/invoices
          - generic [ref=f2e24]: 
      - listitem [ref=f2e26]:
        - link "My Payments" [ref=f2e27] [cursor=pointer]:
          - /url: /subscriber-portal/payments
          - generic [ref=f2e28]: 
      - listitem [ref=f2e30]:
        - link "My Tickets" [ref=f2e31] [cursor=pointer]:
          - /url: /subscriber-portal/tickets
          - generic [ref=f2e32]: 
      - listitem [ref=f2e34]:
        - link "Security" [ref=f2e35] [cursor=pointer]:
          - /url: /subscriber-portal/security
          - generic [ref=f2e36]: 
    - generic [ref=f2e39]:
      - generic [ref=f2e40]: J
      - generic [ref=f2e41]:
        - generic [ref=f2e42]: John Doe
        - generic [ref=f2e43]: kevinmdebelen23@gmail.com
      - button "Log out" [ref=f2e45] [cursor=pointer]:
        - generic [ref=f2e46]: 
  - generic [ref=f2e47]:
    - generic [ref=f2e48]:
      - generic [ref=f2e49]:
        - button "Toggle navigation" [expanded] [ref=f2e50] [cursor=pointer]:
          - generic [ref=f2e51]: 
        - navigation "Current page" [ref=f2e52]:
          - generic [ref=f2e53]: Subscriber portal
          - generic [ref=f2e54]: 
          - generic [ref=f2e55]: My Account
      - generic [ref=f2e56]:
        - search [ref=f2e57]:
          - generic [ref=f2e58]: 
          - searchbox "Search accessible modules" [ref=f2e59]
        - button "View notifications" [ref=f2e60] [cursor=pointer]:
          - generic [ref=f2e61]: 
        - text:  
        - generic [ref=f2e62]:
          - button "Switch to dark mode" [ref=f2e63] [cursor=pointer]:
            - generic [ref=f2e64]: 
          - button "Log out" [ref=f2e66] [cursor=pointer]:
            - generic [ref=f2e67]: 
    - generic [ref=f2e69]:
      - generic [ref=f2e72]:
        - generic [ref=f2e73]:
          - generic [ref=f2e74]: 
          - generic [ref=f2e76]:
            - generic [ref=f2e77]: Welcome, John Doe
            - generic [ref=f2e78]: "Account #1000000001 is in good standing. You currently have 1 active service."
        - generic [ref=f2e79]:
          - generic [ref=f2e80]: Account Status
          - generic [ref=f2e81]: GOOD STANDING
          - button " Refresh" [ref=f2e83] [cursor=pointer]:
            - generic [ref=f2e84]: 
            - text: Refresh
      - generic [ref=f2e85]:
        - generic [ref=f2e88]:
          - generic [ref=f2e89]: Total Balance
          - generic [ref=f2e90]: ₱0.00
          - generic [ref=f2e91]: Open billing balance
        - generic [ref=f2e94]:
          - generic [ref=f2e95]: Overdue Balance
          - generic [ref=f2e96]: ₱0.00
          - generic [ref=f2e97]: Past due invoices
        - generic [ref=f2e100]:
          - generic [ref=f2e101]: Active Services
          - generic [ref=f2e102]: "1"
          - generic [ref=f2e103]: Current active subscriptions
        - generic [ref=f2e106]:
          - generic [ref=f2e107]: Next Due Date
          - generic [ref=f2e108]: 2026-10-04
          - generic [ref=f2e109]: Nearest upcoming due date
      - generic [ref=f2e110]:
        - generic [ref=f2e112]:
          - generic [ref=f2e113]:
            - heading "Profile" [level=6] [ref=f2e114]
            - text: Your account information
          - generic [ref=f2e115]:
            - generic [ref=f2e116]: John Doe
            - generic [ref=f2e117]: "Account # 1000000001"
            - generic [ref=f2e118]:
              - generic [ref=f2e119]:
                - generic [ref=f2e120]: Email
                - generic [ref=f2e121]: kevinmdebelen23@gmail.com
              - generic [ref=f2e122]:
                - generic [ref=f2e123]: Contact Number
                - generic [ref=f2e124]: "0987654321"
              - generic [ref=f2e125]:
                - generic [ref=f2e126]: Address
                - generic [ref=f2e127]: Makati City
        - generic [ref=f2e129]:
          - generic [ref=f2e130]:
            - heading "Quick Access" [level=6] [ref=f2e131]
            - text: Go directly to your service, billing, payment, and support options
          - generic [ref=f2e132]:
            - generic [ref=f2e133]:
              - link " My Services View plan and service status" [ref=f2e135] [cursor=pointer]:
                - /url: /subscriber-portal/services
                - generic [ref=f2e136]: 
                - generic [ref=f2e138]:
                  - generic [ref=f2e139]: My Services
                  - generic [ref=f2e140]: View plan and service status
              - link " My Invoices View balances and due dates" [ref=f2e142] [cursor=pointer]:
                - /url: /subscriber-portal/invoices
                - generic [ref=f2e143]: 
                - generic [ref=f2e145]:
                  - generic [ref=f2e146]: My Invoices
                  - generic [ref=f2e147]: View balances and due dates
              - link " My Payments View payment history" [ref=f2e149] [cursor=pointer]:
                - /url: /subscriber-portal/payments
                - generic [ref=f2e150]: 
                - generic [ref=f2e152]:
                  - generic [ref=f2e153]: My Payments
                  - generic [ref=f2e154]: View payment history
            - generic [ref=f2e156]:
              - generic [ref=f2e157]:
                - generic [ref=f2e158]: 
                - generic [ref=f2e159]:
                  - generic [ref=f2e160]: Portal Security
                  - generic [ref=f2e161]: Change your subscriber portal login password.
              - button " Change Password" [ref=f2e162] [cursor=pointer]:
                - generic [ref=f2e163]: 
                - text: Change Password
            - generic [ref=f2e165]:
              - generic [ref=f2e166]:
                - generic [ref=f2e167]: 
                - generic [ref=f2e168]:
                  - generic [ref=f2e169]: Need help with your account?
                  - generic [ref=f2e170]: Raise a concern for internet, billing, payment, or account-related assistance.
              - button " Raise a Concern" [ref=f2e171] [cursor=pointer]:
                - generic [ref=f2e172]: 
                - text: Raise a Concern
        - generic [ref=f2e174]:
          - generic [ref=f2e175]:
            - heading "Latest Billing Summary" [level=6] [ref=f2e176]
            - text: Your most recent invoice information
          - generic [ref=f2e178]:
            - generic [ref=f2e179]:
              - generic [ref=f2e180]:
                - generic [ref=f2e181]: INV-2026-000016
                - generic [ref=f2e182]: 2026-08-25 to 2026-09-23
              - generic [ref=f2e183]:
                - generic [ref=f2e184]: PAID
                - generic [ref=f2e186]: ₱0.00
                - generic [ref=f2e187]: "Due: 2026-09-04"
            - link " View Invoices" [ref=f2e189] [cursor=pointer]:
              - /url: /subscriber-portal/invoices
              - generic [ref=f2e190]: 
              - text: View Invoices
      - text:  
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
      |                                                                                    ^ Error: subscriber unlabeled controls on /subscriber-portal
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