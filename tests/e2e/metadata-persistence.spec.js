/**
 * Metadata persistence tests.
 *
 * Verifies that channel, playlist, and video metadata is correctly saved into
 * and reloaded from the single JSON meta keys:
 *   - Channel  → yousync_channel  (term meta)
 *   - Playlist → yousync_playlist (term meta)
 *   - Video    → _yousync_video   (post meta)
 *
 * Each test creates its own uniquely named entry so tests can run in any order
 * without colliding with each other or with existing data.
 */

import { test, expect } from '@playwright/test';
import {
  goToChannels,
  goToPlaylists,
  uniqueId,
  chooseTomSelectOption,
  waitForTomSelect,
} from './helpers.js';

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * Add a channel via the left-column add form, then navigate to its edit page.
 */
async function addChannelAndEdit(page, name, channelId) {
  await goToChannels(page);
  await page.locator('input[name="tag-name"]').fill(name);
  await page.locator('input[name="channel_id"]').fill(channelId);
  await page.locator('#submit').click();
  await page.waitForLoadState('networkidle');

  await page.locator('#the-list tr').filter({ hasText: name }).locator('a.row-title').click();
  await page.waitForLoadState('networkidle');
}

/**
 * Add a playlist via the left-column add form, then navigate to its edit page.
 */
async function addPlaylistAndEdit(page, name, playlistId) {
  await goToPlaylists(page);
  await page.locator('input[name="tag-name"]').fill(name);
  await page.locator('input[name="playlist_id"]').fill(playlistId);
  await page.locator('#submit').click();
  await page.waitForLoadState('networkidle');

  await page.locator('#the-list tr').filter({ hasText: name }).locator('a.row-title').click();
  await page.waitForLoadState('networkidle');
}

// ---------------------------------------------------------------------------
// Channel – basic field persistence
// ---------------------------------------------------------------------------

test.describe('Channel – field persistence', () => {
  test('channel ID is saved and shown on the edit form', async ({ page }) => {
    const id = uniqueId();
    await addChannelAndEdit(page, `Persist Channel ${id}`, `UC${id}`);

    await expect(page.locator('input[name="channel_id"]')).toHaveValue(`UC${id}`);
  });

  test('channel ID appears in the list column (read from yousync_channel JSON)', async ({ page }) => {
    await goToChannels(page);
    const id = uniqueId();

    await page.locator('input[name="tag-name"]').fill(`Col Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#the-list')).toContainText(`UC${id}`);
  });

  test('updating the channel ID on the edit page saves correctly', async ({ page }) => {
    const id = uniqueId();
    await addChannelAndEdit(page, `Update ID Channel ${id}`, `UC${id}`);

    const newId = `UCupdated${id}`;
    await page.locator('input[name="channel_id"]').fill(newId);
    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('input[name="channel_id"]')).toHaveValue(newId);
  });
});

// ---------------------------------------------------------------------------
// Channel – sync rule persistence
// ---------------------------------------------------------------------------

test.describe('Channel – sync rule persistence', () => {
  test('saved sync rule schedule and action persist on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Rule Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('weekly');
    await rule.locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Rule Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first();
    await expect(saved.locator('.ys-sync-schedule')).toHaveValue('weekly');
    await expect(saved.locator('.ys-action')).toHaveValue('videos_sync_new');
  });

  test('disabled toggle state persists on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Toggle Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-rule-toggle').uncheck({ force: true });

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Toggle Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.ys-sync-rule').first().locator('.ys-rule-toggle')).not.toBeChecked();
  });

  test('custom schedule value persists on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Custom Sched Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('custom');
    await rule.locator('.ys-custom-sync-schedule').fill('72');
    await rule.locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Custom Sched Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first();
    await expect(saved.locator('.ys-sync-schedule')).toHaveValue('custom');
    await expect(saved.locator('.ys-custom-sync-schedule')).toHaveValue('72');
    await expect(saved.locator('.ys-custom-sync-schedule')).toBeEnabled();
  });

  test('multiple sync rules persist with correct count in list column', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Count Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);

    // Rule 1 — enabled
    await page.locator('#ys-add-rule').click();
    const rule1 = page.locator('.ys-sync-rule').nth(0);
    await rule1.locator('.ys-action').selectOption('videos_sync_new');

    // Rule 2 — disabled
    await page.locator('#ys-add-rule').click();
    const rule2 = page.locator('.ys-sync-rule').nth(1);
    await rule2.locator('.ys-action').selectOption('videos_update_all');
    await rule2.locator('.ys-rule-toggle').uncheck({ force: true });

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    const row = page.locator('#the-list tr').filter({ hasText: `Count Channel ${id}` });
    await expect(row.locator('.column-sync_rules')).toContainText('1 of 2 enabled');
  });

  test('multiple saved sync rules all appear on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Multi Rule Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);

    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').nth(0).locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').nth(1).locator('.ys-action').selectOption('channel_update_all');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Multi Rule Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.ys-sync-rule')).toHaveCount(2);
  });

  test('removing the only sync rule clears it from metadata after save', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Remove Last Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').last().locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove Last Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    // Remove the only rule and wait for the 300 ms removal animation
    await page.locator('.ys-sync-rule').first().locator('.ys-remove-rule').click();
    await page.waitForTimeout(400);

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove Last Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.ys-sync-rule')).toHaveCount(0);
  });

  test('removing one of multiple sync rules removes only that rule after save', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Remove One Rule Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);

    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').nth(0).locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').nth(1).locator('.ys-action').selectOption('channel_update_all');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove One Rule Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    // Remove the first rule
    await page.locator('.ys-sync-rule').first().locator('.ys-remove-rule').click();
    await page.waitForTimeout(400);

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove One Rule Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.ys-sync-rule')).toHaveCount(1);
    await expect(page.locator('.ys-sync-rule').first().locator('.ys-action')).toHaveValue('channel_update_all');
  });
});

// ---------------------------------------------------------------------------
// Channel – condition persistence
// ---------------------------------------------------------------------------

test.describe('Channel – condition persistence', () => {
  test('condition field is pre-selected on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Cond Field Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('title');
    await cond.locator('.ys-condition-operator').selectOption('contains');
    await cond.locator('.ys-condition-value').fill('Tutorial');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Cond Field Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-field')).toHaveValue('title');
  });

  test('condition operator is pre-selected and enabled on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Cond Op Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('view_count');
    await cond.locator('.ys-condition-operator').selectOption('greater_than');
    await cond.locator('.ys-condition-value').fill('1000');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Cond Op Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-operator')).toBeEnabled();
    await expect(saved.locator('.ys-condition-operator')).toHaveValue('greater_than');
  });

  test('condition value is pre-filled and enabled on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Cond Val Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('title');
    await cond.locator('.ys-condition-operator').selectOption('contains');
    await cond.locator('.ys-condition-value').fill('My Saved Value');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Cond Val Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-value')).toHaveValue('My Saved Value');
    await expect(saved.locator('.ys-condition-value')).toBeEnabled();
  });

  test('date condition field, operator, and value all persist on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Date Cond Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('published_date');
    await cond.locator('.ys-condition-operator').selectOption('after');
    await cond.locator('.ys-condition-value').fill('2024-01-01');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Date Cond Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-field')).toHaveValue('published_date');
    await expect(saved.locator('.ys-condition-operator')).toHaveValue('after');
    await expect(saved.locator('.ys-condition-value')).toHaveValue('2024-01-01');
  });

  test('number condition with numeric field type persists correctly', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Num Cond Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('duration');
    await cond.locator('.ys-condition-operator').selectOption('less_than');
    await cond.locator('.ys-condition-value').fill('600');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Num Cond Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-field')).toHaveValue('duration');
    await expect(saved.locator('.ys-condition-operator')).toHaveValue('less_than');
    await expect(saved.locator('.ys-condition-value')).toHaveValue('600');
  });
});

// ---------------------------------------------------------------------------
// Channel – specific metadata persistence
// ---------------------------------------------------------------------------

test.describe('Channel – specific metadata persistence', () => {
  test('specific metadata wrapper is visible on edit when an update_specific action was saved', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Meta Wrapper Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('channel_update_specific');

    const wrapper = rule.locator('.ys-specific-metadata-wrapper');
    await waitForTomSelect(wrapper);
    await chooseTomSelectOption(page, wrapper, 'Channel Title');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Meta Wrapper Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const savedWrapper = page.locator('.ys-sync-rule').first().locator('.ys-specific-metadata-wrapper');
    await expect(savedWrapper).not.toHaveClass(/ys-hidden/);
  });

  test('saved video metadata selection appears as a TomSelect item on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Video Meta Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_update_specific_all');

    const wrapper = rule.locator('.ys-specific-metadata-wrapper');
    await waitForTomSelect(wrapper);
    await chooseTomSelectOption(page, wrapper, 'Title');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Video Meta Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const savedWrapper = page.locator('.ys-sync-rule').first().locator('.ys-specific-metadata-wrapper');
    await expect(savedWrapper).not.toHaveClass(/ys-hidden/);
    await waitForTomSelect(savedWrapper);
    await expect(savedWrapper.locator('.ts-wrapper .item[data-value="title"]')).toBeVisible();
  });

  test('saved channel metadata selection appears as a TomSelect item on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToChannels(page);

    await page.locator('input[name="tag-name"]').fill(`Chan Meta Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(`UC${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('channel_update_specific');

    const wrapper = rule.locator('.ys-specific-metadata-wrapper');
    await waitForTomSelect(wrapper);
    await chooseTomSelectOption(page, wrapper, 'Subscriber Count');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Chan Meta Channel ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const savedWrapper = page.locator('.ys-sync-rule').first().locator('.ys-specific-metadata-wrapper');
    await waitForTomSelect(savedWrapper);
    await expect(savedWrapper.locator('.ts-wrapper .item[data-value="subscriber_count"]')).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Playlist – field persistence
// ---------------------------------------------------------------------------

test.describe('Playlist – field persistence', () => {
  test('playlist ID is saved and shown on the edit form', async ({ page }) => {
    const id = uniqueId();
    await addPlaylistAndEdit(page, `Persist Playlist ${id}`, `PL${id}`);

    await expect(page.locator('input[name="playlist_id"]')).toHaveValue(`PL${id}`);
  });

  test('playlist ID appears in the list column (read from yousync_playlist JSON)', async ({ page }) => {
    await goToPlaylists(page);
    const id = uniqueId();

    await page.locator('input[name="tag-name"]').fill(`Col Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);
    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#the-list')).toContainText(`PL${id}`);
  });
});

// ---------------------------------------------------------------------------
// Playlist – sync rule persistence
// ---------------------------------------------------------------------------

test.describe('Playlist – sync rule persistence', () => {
  test('saved sync rule schedule and action persist on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToPlaylists(page);

    await page.locator('input[name="tag-name"]').fill(`Rule Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('monthly');
    await rule.locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Rule Playlist ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first();
    await expect(saved.locator('.ys-sync-schedule')).toHaveValue('monthly');
    await expect(saved.locator('.ys-action')).toHaveValue('videos_sync_new');
  });

  test('playlist sync rule count shows correctly in list column', async ({ page }) => {
    const id = uniqueId();
    await goToPlaylists(page);

    await page.locator('input[name="tag-name"]').fill(`Count Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);

    // Two rules, one disabled
    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').nth(0).locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#ys-add-rule').click();
    const rule2 = page.locator('.ys-sync-rule').nth(1);
    await rule2.locator('.ys-action').selectOption('videos_update_all');
    await rule2.locator('.ys-rule-toggle').uncheck({ force: true });

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    const row = page.locator('#the-list tr').filter({ hasText: `Count Playlist ${id}` });
    await expect(row.locator('.column-sync_rules')).toContainText('1 of 2 enabled');
  });

  test('playlist condition field, operator, and value persist on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToPlaylists(page);

    await page.locator('input[name="tag-name"]').fill(`Cond Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const cond = rule.locator('.ys-condition').last();
    await cond.locator('.ys-condition-field').selectOption('view_count');
    await cond.locator('.ys-condition-operator').selectOption('greater_than');
    await cond.locator('.ys-condition-value').fill('500');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Cond Playlist ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const saved = page.locator('.ys-sync-rule').first().locator('.ys-condition').first();
    await expect(saved.locator('.ys-condition-field')).toHaveValue('view_count');
    await expect(saved.locator('.ys-condition-operator')).toHaveValue('greater_than');
    await expect(saved.locator('.ys-condition-value')).toHaveValue('500');
  });

  test('removing the only sync rule clears it from playlist metadata after save', async ({ page }) => {
    const id = uniqueId();
    await goToPlaylists(page);

    await page.locator('input[name="tag-name"]').fill(`Remove Last Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);
    await page.locator('#ys-add-rule').click();
    await page.locator('.ys-sync-rule').last().locator('.ys-action').selectOption('videos_sync_new');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove Last Playlist ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await page.locator('.ys-sync-rule').first().locator('.ys-remove-rule').click();
    await page.waitForTimeout(400);

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Remove Last Playlist ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.ys-sync-rule')).toHaveCount(0);
  });

  test('playlist specific metadata persists as a TomSelect item on the edit page', async ({ page }) => {
    const id = uniqueId();
    await goToPlaylists(page);

    await page.locator('input[name="tag-name"]').fill(`Meta Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(`PL${id}`);
    await page.locator('#ys-add-rule').click();

    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('playlist_update_specific');

    const wrapper = rule.locator('.ys-specific-metadata-wrapper');
    await waitForTomSelect(wrapper);
    await chooseTomSelectOption(page, wrapper, 'Title');

    await page.locator('#submit').click();
    await page.waitForLoadState('networkidle');

    await page.locator('#the-list tr').filter({ hasText: `Meta Playlist ${id}` }).locator('a.row-title').click();
    await page.waitForLoadState('networkidle');

    const savedWrapper = page.locator('.ys-sync-rule').first().locator('.ys-specific-metadata-wrapper');
    await expect(savedWrapper).not.toHaveClass(/ys-hidden/);
    await waitForTomSelect(savedWrapper);
    await expect(savedWrapper.locator('.ts-wrapper .item[data-value="playlist_title"]')).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Video – metabox field persistence
// ---------------------------------------------------------------------------

test.describe('Video – metabox field persistence', () => {
  test('YouSync Video Details metabox is visible on the video edit page', async ({ page }) => {
    await page.goto('/wp-admin/post-new.php?post_type=yousync_videos');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#yousync_video_details')).toBeVisible();
    await expect(page.locator('#yousync_video_id')).toBeVisible();
    await expect(page.locator('#yousync_video_url')).toBeVisible();
  });

  test('video ID and video URL persist after saving the post', async ({ page }) => {
    const id = uniqueId();
    const videoYtId = `dQw${id}`;
    const videoUrl = `https://www.youtube.com/watch?v=${videoYtId}`;

    await page.goto('/wp-admin/post-new.php?post_type=yousync_videos');
    await page.waitForLoadState('networkidle');

    // Fill in the post title (required to save)
    await page.locator('#title').fill(`Test Video ${id}`);

    // Fill in the YouSync metabox fields
    await page.locator('#yousync_video_id').fill(videoYtId);
    await page.locator('#yousync_video_url').fill(videoUrl);

    // Save the post
    await page.locator('#publish').click();
    await page.waitForLoadState('networkidle');

    // WordPress reloads the edit page after publishing
    await expect(page.locator('#yousync_video_id')).toHaveValue(videoYtId);
    await expect(page.locator('#yousync_video_url')).toHaveValue(videoUrl);
  });

  test('video ID persists when updated on an existing post', async ({ page }) => {
    const id = uniqueId();

    // Create the post first
    await page.goto('/wp-admin/post-new.php?post_type=yousync_videos');
    await page.waitForLoadState('networkidle');

    await page.locator('#title').fill(`Update Video ${id}`);
    await page.locator('#yousync_video_id').fill(`original${id}`);
    await page.locator('#publish').click();
    await page.waitForLoadState('networkidle');

    // Update the video ID
    const updatedId = `updated${id}`;
    await page.locator('#yousync_video_id').fill(updatedId);
    await page.locator('#publish').click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('#yousync_video_id')).toHaveValue(updatedId);
  });
});
