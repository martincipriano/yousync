/**
 * Navigate to the Channels taxonomy admin page.
 */
export async function goToChannels(page) {
  await page.goto('/wp-admin/edit-tags.php?taxonomy=yousync_channel&post_type=yousync_videos');
}

/**
 * Navigate to the Playlists taxonomy admin page.
 */
export async function goToPlaylists(page) {
  await page.goto('/wp-admin/edit-tags.php?taxonomy=yousync_playlist&post_type=yousync_videos');
}

/**
 * Navigate to the YouSync Settings page.
 */
export async function goToSettings(page) {
  await page.goto('/wp-admin/edit.php?post_type=yousync_videos&page=yousync_settings');
}

/**
 * Wait for the Tom Select dropdown to be initialised on a given wrapper element.
 * Tom Select replaces the native <select> with its own markup, so we wait for
 * the `.ts-wrapper` to appear before interacting with options.
 *
 * @param {import('@playwright/test').Locator} wrapper - The parent container
 */
export async function waitForTomSelect(wrapper) {
  await wrapper.locator('.ts-wrapper').waitFor({ state: 'visible' });
}

/**
 * Open a Tom Select dropdown and choose an option by its visible label.
 *
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').Locator} selectWrapper - The .ys-form-group or parent
 * @param {string} optionLabel - Visible text of the option to choose
 */
export async function chooseTomSelectOption(page, selectWrapper, optionLabel) {
  const tsControl = selectWrapper.locator('.ts-control');
  await tsControl.click();
  const dropdown = selectWrapper.locator('.ts-dropdown');
  await dropdown.waitFor({ state: 'visible' });
  await dropdown.locator(`.option:has-text("${optionLabel}")`).click();
}

/**
 * Return a unique string to avoid test data collisions across runs.
 */
export function uniqueId() {
  return Date.now().toString(36);
}
