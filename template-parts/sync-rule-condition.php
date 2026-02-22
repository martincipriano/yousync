<?php
/**
 * Template part for displaying a sync rule condition.
 *
 * @package YouSync
 *
 * Variables available in this template:
 * @var int|string $rule_index Rule index.
 * @var int|string $condition_index Condition index.
 * @var array      $condition Condition data.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Dual-mode: Use provided indices or fallback to placeholders for JavaScript
$rule_index = isset($rule_index) ? $rule_index : '{{RULE_INDEX}}';
$condition_index = isset($condition_index) ? $condition_index : '{{CONDITION_INDEX}}';

// Get condition values
$field = isset($condition['field']) ? $condition['field'] : '';
$operator = isset($condition['operator']) ? $condition['operator'] : '';
$value = isset($condition['value']) ? $condition['value'] : '';
?>

<div class="ys-3-columns ys-condition">
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr($rule_index); ?>-conditions-<?php echo esc_attr($condition_index); ?>-field"
    >
      Field
    </label>
    <select
      class="ys-select ys-condition-field"
      id="sync-rules-<?php echo esc_attr($rule_index); ?>-conditions-<?php echo esc_attr($condition_index); ?>-field"
      name="sync_rules[<?php echo $rule_index; ?>][conditions][<?php echo esc_attr($condition_index); ?>][field]"
    >
      <option value="" <?php selected($field, ''); ?>>&mdash; Select field &mdash;</option>
      <?php // Field options will be populated by JavaScript based on selected action ?>
    </select>
  </div>
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr($rule_index); ?>-conditions-<?php echo esc_attr($condition_index); ?>-operator"
    >
      Comparison Operator
    </label>
    <select
      class="ys-select ys-condition-operator"
      disabled
      id="sync-rules-<?php echo esc_attr($rule_index); ?>-conditions-<?php echo esc_attr($condition_index); ?>-operator"
      name="sync_rules[<?php echo esc_attr($rule_index); ?>][conditions][<?php echo esc_attr($condition_index); ?>][operator]"
    >
    </select>
  </div>
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr($rule_index); ?>-conditions-<?php echo esc_attr($condition_index); ?>-value"
    >
      Value
    </label>
    <?php
      echo yousync_return_template_part('input', 'text', array(
				'condition_index' => $condition_index,
				'rule_index' => $rule_index,
			));
    ?>
  </div>
  <div class="ys-condition-actions">
    <button class="button ys-remove-condition" title="Remove condition" type="button"><span class="dashicons dashicons-no"></span></button>
  </div>
</div>