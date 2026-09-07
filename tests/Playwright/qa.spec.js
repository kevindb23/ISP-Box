import { test, expect } from '@playwright/test';

const roles = [
  { name: 'admin', username: process.env.QA_ADMIN_USER, password: process.env.QA_ADMIN_PASSWORD },
  { name: 'subscriber', username: process.env.QA_SUBSCRIBER_USER, password: process.env.QA_SUBSCRIBER_PASSWORD },
  { name: 'technician', username: process.env.QA_TECHNICIAN_USER, password: process.env.QA_TECHNICIAN_PASSWORD },
];

async function login(page, account) {
  await page.goto('/login', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="username"]').fill(account.username);
  await page.locator('input[name="password"]').fill(account.password);
  await page.locator('#loginForm button[type="submit"], button[type="submit"]').first().click();
  await page.waitForFunction(() => location.pathname !== '/login' || document.querySelector('#primarySidebar'), null, { timeout: 12_000 });
}

async function logout(page) {
  const logout = page.locator('#nxRectilinearTheme form[action="/logout"] button[type="submit"]');
  if (await logout.count()) {
    await logout.click();
    await expect(page).toHaveURL(/\/login/);
  }
}

test.describe('login security', () => {
  for (const account of roles) {
    test(`${account.name} login rejects SQL injection`, async ({ page }) => {
      await page.goto('/login', { waitUntil: 'domcontentloaded' });
      await page.locator('input[name="username"]').fill("' OR '1'='1");
      await page.locator('input[name="password"]').fill("' OR '1'='1");
      await page.locator('#loginForm button[type="submit"], button[type="submit"]').first().click();
      await page.waitForTimeout(700);
      expect(new URL(page.url()).pathname).toBe('/login');
      await expect(page.locator('body')).not.toContainText(/SQLSTATE|PDOException|syntax error/i);
    });

    test(`${account.name} valid login reaches an authenticated page`, async ({ page }) => {
      await login(page, account);
      await expect(page.locator('#primarySidebar, aside.sidebar')).toBeVisible({ timeout: 12_000 });
      await expect(page.locator('body')).not.toContainText(/SQLSTATE|PDOException|Fatal error/i);
      await logout(page);
    });
  }
});

for (const account of roles) {
  test.describe(`${account.name} portal module smoke`, () => {
    test('all visible navigation modules load without server or console errors', async ({ page }) => {
      const consoleErrors = [];
      const failedRequests = [];
      page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
      page.on('requestfailed', request => failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`));

      await login(page, account);
      const links = await page.locator('#primarySidebar a[href], aside.sidebar a[href]').evaluateAll(nodes => nodes.map(node => ({ href: node.href, label: node.textContent.trim() })));
      const unique = [...new Map(links.map(link => [new URL(link.href).pathname, link])).values()];
      expect(unique.length).toBeGreaterThan(0);

      for (const link of unique) {
        await page.goto(link.href, { waitUntil: 'domcontentloaded' });
        expect(new URL(page.url()).pathname, `${account.name} redirected while opening ${link.label}`).not.toBe('/login');
        await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException/i);
      }

      expect(failedRequests, failedRequests.join('\n')).toEqual([]);
      expect(consoleErrors.filter(error => !/favicon/i.test(error)), consoleErrors.join('\n')).toEqual([]);
      await logout(page);
    });

    test('responsive navigation and notification badge behavior are usable', async ({ page }) => {
      await login(page, account);
      await page.setViewportSize({ width: 390, height: 844 });
      await page.reload({ waitUntil: 'domcontentloaded' });
      const toggle = page.locator('#sidebarToggleBtn');
      if (await toggle.count()) {
        await toggle.click();
        await expect(page.locator('body')).toHaveClass(/sidebar-mobile-open/);
        const scrim = page.locator('#sidebarScrim');
        if (await scrim.count()) await scrim.click({ force: true });
      }
      const bell = page.locator('#globalNotificationsToggle');
      if (await bell.count()) {
        await bell.click({ force: true });
        await expect(page.locator('#globalNotifications')).toBeVisible();
        const badge = page.locator('#globalNotificationsCount');
        if (await badge.count() && await badge.isVisible()) expect(await badge.textContent()).not.toMatch(/^0$/);
      }
      await logout(page);
    });

    test('pages expose basic accessible structure and no uncaught runtime errors', async ({ page }) => {
      const runtimeErrors = [];
      const serverErrors = [];
      page.on('pageerror', error => runtimeErrors.push(error.message));
      page.on('response', response => {
        if (response.status() >= 500) serverErrors.push(`${response.status()} ${response.url()}`);
      });

      await login(page, account);
      const links = await page.locator('#primarySidebar a[href], aside.sidebar a[href]').evaluateAll(nodes => nodes.map(node => node.href));
      const paths = [...new Set(links.map(href => new URL(href).pathname))];
      for (const path of paths) {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
        await expect(page.locator('body')).not.toContainText(/Fatal error|SQLSTATE|PDOException|Unhandled exception/i);
        const duplicateIds = await page.locator('[id]').evaluateAll(nodes => {
          const counts = new Map();
          nodes.forEach(node => counts.set(node.id, (counts.get(node.id) || 0) + 1));
          return [...counts.entries()].filter(([, count]) => count > 1).map(([id]) => id);
        });
        expect(duplicateIds, `${account.name} duplicate IDs on ${path}`).toEqual([]);
        const unlabeledControls = await page.locator('input:not([type="hidden"]), select, textarea, button').evaluateAll(nodes => nodes.filter(node => {
          if (node.disabled || node.getAttribute('aria-hidden') === 'true') return false;
          if (node.tagName === 'BUTTON' && node.textContent.trim()) return false;
          if (node.getAttribute('aria-label') || node.getAttribute('title')) return false;
          const id = node.id;
          return !(id && document.querySelector(`label[for="${CSS.escape(id)}"]`));
        }).map(node => `${node.tagName.toLowerCase()}#${node.id}`));
        expect(unlabeledControls, `${account.name} unlabeled controls on ${path}`).toEqual([]);
      }
      expect(serverErrors, serverErrors.join('\n')).toEqual([]);
      expect(runtimeErrors, runtimeErrors.join('\n')).toEqual([]);
      await logout(page);
    });

    test('protected page redirects after logout and cannot be restored with back navigation', async ({ page }) => {
      await login(page, account);
      const protectedPath = new URL(page.url()).pathname === '/login' ? '/dashboard' : new URL(page.url()).pathname;
      await logout(page);
      await page.goto(protectedPath, { waitUntil: 'domcontentloaded' });
      await expect(page).toHaveURL(/\/login/);
      await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {});
      expect(new URL(page.url()).pathname).toBe('/login');
    });

    test('responsive layouts do not introduce horizontal page overflow', async ({ page }) => {
      await login(page, account);
      for (const width of [320, 390, 768, 1024]) {
        await page.setViewportSize({ width, height: 844 });
        await page.reload({ waitUntil: 'domcontentloaded' });
        const metrics = await page.evaluate(() => ({
          viewport: window.innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
          scrollHeight: document.documentElement.scrollHeight,
        }));
        expect(metrics.scrollWidth, `${account.name} horizontal overflow at ${width}px`).toBeLessThanOrEqual(metrics.viewport + 2);
      }
      await logout(page);
    });

    test('keyboard navigation reaches visible controls', async ({ page }) => {
      await login(page, account);
      await page.keyboard.press('Tab');
      const focused = await page.evaluate(() => {
        const element = document.activeElement;
        if (!element || element === document.body) return { valid: false, tag: '' };
        const rect = element.getBoundingClientRect();
        return { valid: true, tag: element.tagName, visible: rect.width > 0 && rect.height > 0 };
      });
      expect(focused.valid).toBe(true);
      expect(focused.visible).toBe(true);
      await logout(page);
    });
  });
}
