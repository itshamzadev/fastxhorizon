const { test, expect } = require('@playwright/test');

test.describe('FASTXHORIZON storefront', () => {
  test('loads homepage, categories and responsive product discovery', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByText('Browse what fits you.')).toBeVisible();
    await expect(page.getByText('Clothing', { exact: true }).first()).toBeVisible();
    await page.screenshot({ path: 'e2e/artifacts/homepage-desktop.png', fullPage: false });
  });

  test('shop uses click-to-load and does not fetch on scroll', async ({ page }) => {
    let productRequests = 0;
    page.on('request', request => { if (request.url().includes('/api/products')) productRequests += 1; });
    await page.goto('/shop');
    await expect(page.getByText('Phase 7 Discount Shirt')).toBeVisible();
    const initialRequests = productRequests;
    await page.mouse.wheel(0, 3000);
    await page.waitForTimeout(500);
    expect(productRequests).toBe(initialRequests);
    const loadMore = page.getByRole('button', { name: 'Load More Products' });
    if (await loadMore.isVisible()) {
      await loadMore.click();
      await expect.poll(() => productRequests).toBeGreaterThan(initialRequests);
    }
  });

  test('opens product details, shows genuine discount and adds to cart', async ({ page }) => {
    await page.goto('/shop');
    await expect(page.getByText('Phase 7 Discount Shirt')).toBeVisible();
    await expect(page.getByText(/(?:25% OFF|-25%)/)).toBeVisible();
    await page.getByText('Phase 7 Discount Shirt', { exact: true }).click();
    await expect(page).toHaveURL(/\/product\//);
    await expect(page.getByText('Rs. 1,500')).toBeVisible();
    const add = page.getByRole('button', { name: 'Add to Cart', exact: true });
    await expect(add).toBeEnabled();
    await add.click();
    await page.goto('/cart');
    await expect(page.getByText('Phase 7 Discount Shirt')).toBeVisible();
    await page.screenshot({ path: 'e2e/artifacts/cart.png', fullPage: false });
  });

  test('customer login and logout work', async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('textbox', { name: 'Email', exact: true }).fill('phase7-customer@example.invalid');
    await page.getByRole('textbox', { name: 'Password', exact: true }).fill('Phase7-Test-Password-123!');
    await page.getByRole('button', { name: /sign in|login/i }).click();
    await expect(page).toHaveURL(/127\.0\.0\.1:3001\/$/);
    await expect(page.getByRole('button', { name: /logout|sign out/i })).toBeVisible();
  });

  test('sold-out product exposes the arrangement request form', async ({ page }) => {
    await page.goto('/shop');
    await page.getByText('Phase 7 Arrange Shirt', { exact: true }).click();
    await expect(page.getByText(/Out of Stock|Sold out/i).first()).toBeVisible();
    await page.getByRole('button', { name: 'Arrange on Request' }).click();
    await expect(page).toHaveURL(/\/arrange\//);
    await page.getByLabel('Full name').fill('Phase 7 Customer');
    await page.getByLabel('Mobile number').fill('03001234567');
    await page.getByLabel('Delivery city').fill('Lahore');
    await page.getByLabel('Delivery address').fill('E2E test address');
    await page.getByRole('button', { name: 'Submit Request to Arrange' }).click();
    await expect(page.getByText('Request received')).toBeVisible();
    await expect(page.getByText(/60% advance is requested only after confirmation/i)).toBeVisible();
  });
});
