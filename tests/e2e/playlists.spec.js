/**
 * Playlists admin page tests.
 *
 * The Playlists page mirrors the Channels page in structure:
 *  - Left column  : Add Playlist form (Playlist ID + Sync Rules)
 *  - Right column : Playlist list table
 *
 * Playlist sync rules follow the same pattern as channel rules:
 *  - The action is stored under the key `action` (name attr: sync_rules[n][action])
 *  - Only video actions are available (no channel/playlist sub-actions)
 *  - The metadata wrapper uses `.ys-specific-metadata-wrapper` (same as channels)
 *  - Conditions are dynamic: added via "Add condition" link, same JS as channels
 */

import { test, expect } from '@playwright/test';
import { goToPlaylists, uniqueId } from './helpers.js';

// ---------------------------------------------------------------------------
// Page layout
// ---------------------------------------------------------------------------

test.describe('Playlists page layout', () => {
  test('shows the taxonomy-style layout with add form and list table', async ({ page }) => {
    await goToPlaylists(page);

    await expect(page.locator('#col-left')).toBeVisible();
    await expect(page.locator('#addtag')).toBeVisible();
    await expect(page.locator('#col-right')).toBeVisible();
    await expect(page.locator('#the-list')).toBeVisible();
  });

  test('add form contains Playlist ID field and Sync Rules section', async ({ page }) => {
    await goToPlaylists(page);

    await expect(page.locator('input[name="playlist_id"]')).toBeVisible();
    await expect(page.locator('#ys-sync-rules')).toBeVisible();
    await expect(page.locator('#ys-add-rule')).toBeVisible();
  });

  test('playlist list table has expected columns', async ({ page }) => {
    await goToPlaylists(page);

    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Name' }).first()).toBeVisible();
    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Playlist ID' }).first()).toBeVisible();
    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Sync Rules' }).first()).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Adding a playlist
// ---------------------------------------------------------------------------

test.describe('Adding a playlist', () => {
  test('can add a playlist with a name and playlist ID', async ({ page }) => {
    await goToPlaylists(page);

    const id = uniqueId();
    const playlistName = `Test Playlist ${id}`;
    const playlistYtId = `PL${id}`;

    await page.locator('input[name="tag-name"]').fill(playlistName);
    await page.locator('input[name="playlist_id"]').fill(playlistYtId);
    await page.locator('#submit').click();

    await page.waitForLoadState('networkidle');
    await expect(page.locator('#the-list')).toContainText(playlistName);
  });

  test('shows the new playlist ID in the list after adding', async ({ page }) => {
    await goToPlaylists(page);

    const id = uniqueId();
    const playlistYtId = `PL${id}`;

    await page.locator('input[name="tag-name"]').fill(`Playlist ${id}`);
    await page.locator('input[name="playlist_id"]').fill(playlistYtId);
    await page.locator('#submit').click();

    await page.waitForLoadState('networkidle');
    await expect(page.locator('#the-list')).toContainText(playlistYtId);
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – add / remove
// ---------------------------------------------------------------------------

test.describe('Playlist Sync Rules', () => {
  test.beforeEach(async ({ page }) => {
    await goToPlaylists(page);
  });

  test('clicking "Add sync rule" appends a new rule block', async ({ page }) => {
    const rulesBefore = await page.locator('.ys-sync-rule').count();
    await page.locator('#ys-add-rule').click();
    await expect(page.locator('.ys-sync-rule')).toHaveCount(rulesBefore + 1);
  });

  test('clicking "Remove" deletes a sync rule', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    const countBefore = await page.locator('.ys-sync-rule').count();

    await page.locator('.ys-remove-rule').last().click();
    await page.waitForTimeout(400);
    await expect(page.locator('.ys-sync-rule')).toHaveCount(countBefore - 1);
  });

  test('rule toggle defaults to enabled', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    const toggle = page.locator('.ys-sync-rule').last().locator('.ys-rule-toggle');
    await expect(toggle).toBeChecked();
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Schedule (playlist)
// ---------------------------------------------------------------------------

test.describe('Playlist Sync Rule – Schedule', () => {
  test.beforeEach(async ({ page }) => {
    await goToPlaylists(page);
    await page.locator('#ys-add-rule').click();
  });

  test('custom schedule is disabled with preset selection', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('weekly');
    await expect(rule.locator('.ys-custom-sync-schedule')).toBeDisabled();
  });

  test('custom schedule is enabled when "Custom" is selected', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('custom');
    await expect(rule.locator('.ys-custom-sync-schedule')).toBeEnabled();
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Action / Metadata (playlist)
// ---------------------------------------------------------------------------

test.describe('Playlist Sync Rule – Action', () => {
  test.beforeEach(async ({ page }) => {
    await goToPlaylists(page);
    await page.locator('#ys-add-rule').click();
  });

  test('action dropdown is visible', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-action')).toBeVisible();
  });

  test('action dropdown name uses "action" key', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-action')).toHaveAttribute('name', /sync_rules\[\d+\]\[action\]/);
  });

  test('action dropdown contains Playlist optgroup', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-action optgroup[label="Playlist"]')).toBeAttached();
    await expect(rule.locator('.ys-action option[value="playlist_update_all"]')).toBeAttached();
    await expect(rule.locator('.ys-action option[value="playlist_update_specific"]')).toBeAttached();
  });

  test('action dropdown contains Videos optgroup', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-action optgroup[label="Videos"]')).toBeAttached();
    await expect(rule.locator('.ys-action option[value="videos_sync_new"]')).toBeAttached();
  });

  test('specific metadata wrapper is hidden by default', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-specific-metadata-wrapper')).toHaveClass(/ys-hidden/);
  });

  test('specific metadata wrapper appears when "update_specific" action is chosen', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const action = rule.locator('.ys-action');
    const specificOption = action.locator('option[value*="update_specific"]').first();
    const specificValue = await specificOption.getAttribute('value');

    await action.selectOption(specificValue);
    await expect(rule.locator('.ys-specific-metadata-wrapper')).not.toHaveClass(/ys-hidden/);
  });

  test('specific metadata wrapper hides again when a non-specific action is chosen', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const action = rule.locator('.ys-action');
    const specificOption = action.locator('option[value*="update_specific"]').first();
    const specificValue = await specificOption.getAttribute('value');

    await action.selectOption(specificValue);
    await action.selectOption('videos_sync_new');
    await expect(rule.locator('.ys-specific-metadata-wrapper')).toHaveClass(/ys-hidden/);
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Conditions (playlist)
//
// Playlist sync rules use the same dynamic condition system as channels:
// conditions are added via an "Add condition" link, and each condition row
// has Field, Operator, and Value inputs that activate based on field type.
// ---------------------------------------------------------------------------

test.describe('Playlist Sync Rule – Conditions', () => {
  test.beforeEach(async ({ page }) => {
    await goToPlaylists(page);
    await page.locator('#ys-add-rule').click();
  });

  test('"Add condition" link is visible inside a sync rule', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-add-condition')).toBeVisible();
  });

  test('clicking "Add condition" adds a condition row', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const condsBefore = await rule.locator('.ys-condition').count();

    await rule.locator('.ys-add-condition').click();
    await expect(rule.locator('.ys-condition')).toHaveCount(condsBefore + 1);
  });

  test('condition row contains Field, Operator, and Value inputs', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await expect(condition.locator('.ys-condition-field')).toBeVisible();
    await expect(condition.locator('.ys-condition-operator')).toBeVisible();
    await expect(condition.locator('.ys-condition-value')).toBeVisible();
  });

  test('operator and value are disabled until a field is selected', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await expect(condition.locator('.ys-condition-operator')).toBeDisabled();
    await expect(condition.locator('.ys-condition-value')).toBeDisabled();
  });

  test('selecting a text field populates text operators', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await condition.locator('.ys-condition-field').selectOption('title');

    const operator = condition.locator('.ys-condition-operator');
    await expect(operator).toBeEnabled();
    await expect(operator.locator('option[value="contains"]')).toBeAttached();
    await expect(operator.locator('option[value="not_contains"]')).toBeAttached();
    await expect(operator.locator('option[value="starts_with"]')).toBeAttached();
    await expect(operator.locator('option[value="ends_with"]')).toBeAttached();
  });

  test('selecting a numeric field populates numeric operators', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await condition.locator('.ys-condition-field').selectOption('view_count');

    const operator = condition.locator('.ys-condition-operator');
    await expect(operator).toBeEnabled();
    await expect(operator.locator('option[value="greater_than"]')).toBeAttached();
    await expect(operator.locator('option[value="less_than"]')).toBeAttached();
    await expect(operator.locator('option[value="equal_to"]')).toBeAttached();
  });

  test('selecting a date field populates date operators', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await condition.locator('.ys-condition-field').selectOption('published_date');

    const operator = condition.locator('.ys-condition-operator');
    await expect(operator).toBeEnabled();
    await expect(operator.locator('option[value="before"]')).toBeAttached();
    await expect(operator.locator('option[value="after"]')).toBeAttached();
  });

  test('value input is enabled after field and operator are selected', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await condition.locator('.ys-condition-field').selectOption('title');
    await condition.locator('.ys-condition-operator').selectOption('contains');

    await expect(condition.locator('.ys-condition-value')).toBeEnabled();
  });

  test('removing a condition reduces the condition count', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-add-condition').click();
    await rule.locator('.ys-add-condition').click();
    const countBefore = await rule.locator('.ys-condition').count();

    await rule.locator('.ys-remove-condition').last().click();
    await page.waitForTimeout(400);
    await expect(rule.locator('.ys-condition')).toHaveCount(countBefore - 1);
  });

  test('can add multiple conditions to the same rule', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-add-condition').click();
    await rule.locator('.ys-add-condition').click();
    await rule.locator('.ys-add-condition').click();

    await expect(rule.locator('.ys-condition')).toHaveCount(3);
  });
});
