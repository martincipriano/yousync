/**
 * Admin menu ordering tests.
 *
 * Verifies that the YouSync submenu items appear in the correct order:
 * Videos, Add New Video, Categories, Tags, Channels, Playlists, Settings.
 */

import { test, expect } from '@playwright/test';

test.describe('YouSync admin submenu order', () => {
  test('submenu items appear in the correct order', async ({ page }) => {
    await page.goto('/wp-admin/');

    const menu = page.locator('#menu-posts-yousync_videos');
    await menu.hover();

    // Collect visible submenu link labels in DOM order.
    const links = menu.locator('ul.wp-submenu li a');
    await links.first().waitFor({ state: 'visible' });

    const labels = await links.allInnerTexts();

    // Strip any non-breaking spaces and trim whitespace.
    const cleaned = labels.map( l => l.replace(/\u00a0/g, ' ').trim() ).filter( Boolean );

    expect( cleaned ).toEqual([
      'Videos',
      'Add New Video',
      'Categories',
      'Tags',
      'Channels',
      'Playlists',
      'Settings',
    ]);
  });

  test('each submenu item links to the correct URL', async ({ page }) => {
    await page.goto('/wp-admin/');

    const menu = page.locator('#menu-posts-yousync_videos');
    await menu.hover();

    const submenu = menu.locator('ul.wp-submenu');
    await submenu.waitFor({ state: 'visible' });

    await expect( submenu.getByRole('link', { name: 'Videos' }) ).toHaveAttribute('href', /edit\.php\?post_type=yousync_videos/);
    await expect( submenu.getByRole('link', { name: 'Add New Video' }) ).toHaveAttribute('href', /post-new\.php\?post_type=yousync_videos/);
    await expect( submenu.getByRole('link', { name: 'Categories' }) ).toHaveAttribute('href', /taxonomy=video_category/);
    await expect( submenu.getByRole('link', { name: 'Tags' }) ).toHaveAttribute('href', /taxonomy=video_tag/);
    await expect( submenu.getByRole('link', { name: 'Channels' }) ).toHaveAttribute('href', /taxonomy=yousync_channel/);
    await expect( submenu.getByRole('link', { name: 'Playlists' }) ).toHaveAttribute('href', /taxonomy=yousync_playlist/);
    await expect( submenu.getByRole('link', { name: 'Settings' }) ).toHaveAttribute('href', /yousync_settings/);
  });
});
