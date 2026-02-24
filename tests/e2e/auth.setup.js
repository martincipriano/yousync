import { test as setup } from '@playwright/test';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const authFile = path.join(__dirname, '.auth/admin.json');

setup('authenticate as admin', async ({ page }) => {
  await page.goto('/wp-login.php');

  await page.locator('#user_login').fill(process.env.WP_ADMIN_USER);
  await page.locator('#user_pass').fill(process.env.WP_ADMIN_PASS);
  await page.locator('#wp-submit').click();

  // Dismiss the "Administration email verification" interstitial if it appears
  const emailVerification = page.locator('text=Administration email verification');
  if (await emailVerification.isVisible({ timeout: 5000 }).catch(() => false)) {
    await page.locator('button:has-text("The email is correct")').click();
  }

  await page.waitForURL('**/wp-admin/**');

  await page.context().storageState({ path: authFile });
});
