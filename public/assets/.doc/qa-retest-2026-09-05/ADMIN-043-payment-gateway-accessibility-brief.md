# ADMIN-043 — Payment Gateway switch accessibility

## Problem

The PayMongo enable/disable switch was exposed as a `role="switch"` control, but the browser accessibility tree reported an empty accessible name. This makes the control difficult to identify for screen-reader and keyboard users and caused the QA accessibility assertion to fail.

## Solution implemented

- Added the visible label `Enable PayMongo payments` with the stable ID `paymongoEnabledLabel`.
- Connected the switch to that label with `aria-labelledby="paymongoEnabledLabel"`.
- Added `aria-checked="false"` to the initial control state.
- Updated the Payment Gateway settings loader to keep `aria-checked` synchronized with the actual checkbox state.

## Verification

Expected browser result:

```js
await expect(page.getByRole('switch', {
  name: 'Enable PayMongo payments'
})).toBeVisible();
```

The focused Playwright check should report a named switch and no unnamed enabled controls on `/payment-gateway`.
