const { test, expect } = require('@playwright/test');

test('admin login and protected inventory/reviews pages render', async ({ page }) => {
  await page.goto('http://127.0.0.1:5174/login');
  await page.getByLabel('Admin email').fill('phase7-admin@example.invalid');
  await page.getByRole('textbox', { name: 'Password' }).fill('Phase7-Test-Password-123!');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/127\.0\.0\.1:5174\/dashboard/);
  await expect(page.getByText(/dashboard/i).first()).toBeVisible();
  await page.goto('http://127.0.0.1:5174/dashboard/inventory');
  await expect(page.getByText('Inventory & Arrangements')).toBeVisible();
  await page.screenshot({ path: 'e2e/artifacts/admin-inventory.png', fullPage: true });
  await page.goto('http://127.0.0.1:5174/dashboard/reviews');
  await expect(page.getByText(/reviews/i).first()).toBeVisible();
});
