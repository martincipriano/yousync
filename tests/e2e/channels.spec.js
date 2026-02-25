/**
 * Channels admin page tests.
 *
 * The Channels page mimics a WordPress taxonomy admin page:
 *  - Left column  : Add Channel form
 *  - Right column : Channel list table
 *
 * Each channel stores a Channel ID (YouTube channel ID) and any number of
 * Sync Rules. A Sync Rule defines a schedule, an action to perform, optional
 * metadata fields to update, and optional conditions to filter videos.
 */

import { test, expect } from '@playwright/test';
import { goToChannels, uniqueId, chooseTomSelectOption } from './helpers.js';

// ---------------------------------------------------------------------------
// Page layout
// ---------------------------------------------------------------------------

test.describe('Channels page layout', () => {
  test('shows the taxonomy-style layout with add form and list table', async ({ page }) => {
    await goToChannels(page);

    // Left column: add form
    await expect(page.locator('#col-left')).toBeVisible();
    await expect(page.locator('#addtag')).toBeVisible();

    // Right column: channel table
    await expect(page.locator('#col-right')).toBeVisible();
    await expect(page.locator('#the-list')).toBeVisible();
  });

  test('add form contains Channel ID field and Sync Rules section', async ({ page }) => {
    await goToChannels(page);

    await expect(page.locator('input[name="channel_id"]')).toBeVisible();
    await expect(page.locator('#ys-sync-rules')).toBeVisible();
    await expect(page.locator('#ys-add-rule')).toBeVisible();
  });

  test('channel list table has expected columns', async ({ page }) => {
    await goToChannels(page);

    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Name' }).first()).toBeVisible();
    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Channel ID' }).first()).toBeVisible();
    await expect(page.locator('.wp-list-table th').filter({ hasText: 'Sync Rules' }).first()).toBeVisible();
  });
});

// ---------------------------------------------------------------------------
// Adding a channel
// ---------------------------------------------------------------------------

test.describe('Adding a channel', () => {
  test('can add a channel with a name and channel ID', async ({ page }) => {
    await goToChannels(page);

    const id = uniqueId();
    const channelName = `Test Channel ${id}`;
    const channelYtId = `UC${id}`;

    await page.locator('input[name="tag-name"]').fill(channelName);
    await page.locator('input[name="channel_id"]').fill(channelYtId);
    await page.locator('#submit').click();

    // WordPress reloads the page and shows the new entry in the list
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#the-list')).toContainText(channelName);
  });

  test('shows the new channel ID in the list after adding', async ({ page }) => {
    await goToChannels(page);

    const id = uniqueId();
    const channelYtId = `UC${id}`;

    await page.locator('input[name="tag-name"]').fill(`Channel ${id}`);
    await page.locator('input[name="channel_id"]').fill(channelYtId);
    await page.locator('#submit').click();

    await page.waitForLoadState('networkidle');
    await expect(page.locator('#the-list')).toContainText(channelYtId);
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – add / remove
// ---------------------------------------------------------------------------

test.describe('Sync Rules', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
  });

  test('clicking "Add sync rule" appends a new rule block', async ({ page }) => {
    const rulesBefore = await page.locator('.ys-sync-rule').count();
    await page.locator('#ys-add-rule').click();
    await expect(page.locator('.ys-sync-rule')).toHaveCount(rulesBefore + 1);
  });

  test('can add multiple sync rules', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    await page.locator('#ys-add-rule').click();
    const count = await page.locator('.ys-sync-rule').count();
    expect(count).toBeGreaterThanOrEqual(2);
  });

  test('clicking "Remove" deletes a sync rule', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    const countBefore = await page.locator('.ys-sync-rule').count();

    await page.locator('.ys-remove-rule').last().click();

    // Wait for the removal animation (300 ms) to complete
    await page.waitForTimeout(400);
    await expect(page.locator('.ys-sync-rule')).toHaveCount(countBefore - 1);
  });

  test('sync rule toggle is enabled by default', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    const toggle = page.locator('.ys-sync-rule').last().locator('.ys-rule-toggle');
    await expect(toggle).toBeChecked();
  });

  test('toggle can be switched off', async ({ page }) => {
    await page.locator('#ys-add-rule').click();
    const toggle = page.locator('.ys-sync-rule').last().locator('.ys-rule-toggle');
    await toggle.uncheck({ force: true });
    await expect(toggle).not.toBeChecked();
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Schedule
// ---------------------------------------------------------------------------

test.describe('Sync Rule – Schedule', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await page.locator('#ys-add-rule').click();
  });

  test('schedule dropdown defaults are visible', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const schedule = rule.locator('.ys-sync-schedule');
    await expect(schedule).toBeVisible();
  });

  test('custom schedule input is disabled when a preset is selected', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const schedule = rule.locator('.ys-sync-schedule');
    const custom = rule.locator('.ys-custom-sync-schedule');

    await schedule.selectOption('daily');
    await expect(custom).toBeDisabled();
  });

  test('custom schedule input is enabled when "Custom" is selected', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const schedule = rule.locator('.ys-sync-schedule');
    const custom = rule.locator('.ys-custom-sync-schedule');

    await schedule.selectOption('custom');
    await expect(custom).toBeEnabled();
  });

  test('custom schedule accepts a numeric hour value', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-sync-schedule').selectOption('custom');
    const custom = rule.locator('.ys-custom-sync-schedule');
    await custom.fill('48');
    await expect(custom).toHaveValue('48');
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Action & Specific Metadata
// ---------------------------------------------------------------------------

test.describe('Sync Rule – Action dropdown', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await page.locator('#ys-add-rule').click();
  });

  test('action dropdown is visible', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-action')).toBeVisible();
  });

  test('specific metadata wrapper is hidden by default', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await expect(rule.locator('.ys-specific-metadata-wrapper')).toHaveClass(/ys-hidden/);
  });

  test('specific metadata wrapper appears when "update_specific" action is chosen', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    const action = rule.locator('.ys-action');

    // Find an option whose value includes "update_specific"
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
// Sync Rules – Specific Metadata options
// ---------------------------------------------------------------------------

test.describe('Sync Rule – Specific Metadata options', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
    await page.locator('#ys-add-rule').click();
  });

  test('channel_update_specific shows profile_picture and banner_image options', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('channel_update_specific');

    const select = rule.locator('.ys-specific-metadata');
    await expect(select.locator('option[value="profile_picture"]')).toBeAttached();
    await expect(select.locator('option[value="banner_image"]')).toBeAttached();
  });

  test('channel_update_specific does not show a generic thumbnail option', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('channel_update_specific');

    const select = rule.locator('.ys-specific-metadata');
    await expect(select.locator('option[value="thumbnail"]')).not.toBeAttached();
  });

  test('channel_update_specific shows channel title and description options', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('channel_update_specific');

    const select = rule.locator('.ys-specific-metadata');
    await expect(select.locator('option[value="channel_title"]')).toBeAttached();
    await expect(select.locator('option[value="channel_description"]')).toBeAttached();
    await expect(select.locator('option[value="subscriber_count"]')).toBeAttached();
    await expect(select.locator('option[value="video_count"]')).toBeAttached();
  });

  test('videos_update_specific_all shows thumbnail option for videos', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_update_specific_all');

    const select = rule.locator('.ys-specific-metadata');
    await expect(select.locator('option[value="thumbnail"]')).toBeAttached();
  });

  test('videos_update_specific_all shows all expected video metadata options', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_update_specific_all');

    const select = rule.locator('.ys-specific-metadata');
    await expect(select.locator('option[value="title"]')).toBeAttached();
    await expect(select.locator('option[value="description"]')).toBeAttached();
    await expect(select.locator('option[value="thumbnail"]')).toBeAttached();
    await expect(select.locator('option[value="tags"]')).toBeAttached();
    await expect(select.locator('option[value="view_count"]')).toBeAttached();
    await expect(select.locator('option[value="like_count"]')).toBeAttached();
    await expect(select.locator('option[value="comment_count"]')).toBeAttached();
  });

  test('thumbnail does not appear in condition field options for video actions', async ({ page }) => {
    const rule = page.locator('.ys-sync-rule').last();
    await rule.locator('.ys-action').selectOption('videos_sync_new');
    await rule.locator('.ys-add-condition').click();

    const condition = rule.locator('.ys-condition').last();
    await expect(condition.locator('.ys-condition-field option[value="thumbnail"]')).not.toBeAttached();
  });
});

// ---------------------------------------------------------------------------
// Sync Rules – Conditions
// ---------------------------------------------------------------------------

test.describe('Sync Rule – Conditions', () => {
  test.beforeEach(async ({ page }) => {
    await goToChannels(page);
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

    // Select a video action so video fields are available
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

// ---------------------------------------------------------------------------
// Editing a channel
// ---------------------------------------------------------------------------

test.describe('Editing a channel', () => {
  test('clicking a channel name opens the edit form', async ({ page }) => {
    await goToChannels(page);

    const rows = page.locator('#the-list tr');
    const rowCount = await rows.count();
    if (rowCount === 0) test.skip(true, 'No channels to edit');

    await rows.first().locator('td.column-name a.row-title').click();
    // Modern WordPress uses term.php for taxonomy term editing (not edit-tags.php?action=edit)
    await expect(page).toHaveURL(/term\.php/);
    await expect(page.locator('input[name="channel_id"]')).toBeVisible();
  });
});
