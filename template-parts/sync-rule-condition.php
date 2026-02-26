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
 * @var string|null $field_options_html Pre-rendered field options HTML (server-side rendering).
 * @var string|null $operator_html Pre-rendered operator options HTML (server-side rendering).
 * @var string|null $value_html Pre-rendered value input HTML (server-side rendering).
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Dual-mode: Use provided indices or fallback to placeholders for JavaScript
$rule_index      = isset( $rule_index ) ? $rule_index : '{{RULE_INDEX}}';
$condition_index = isset( $condition_index ) ? $condition_index : '{{CONDITION_INDEX}}';

// Get condition values
$field    = isset( $condition['field'] ) ? $condition['field'] : '';
$operator = isset( $condition['operator'] ) ? $condition['operator'] : '';
$value    = isset( $condition['value'] ) ? $condition['value'] : '';

// Determine if pre-rendered HTML is available (server-side rendering of existing conditions)
$has_field_options = isset( $field_options_html ) && $field_options_html !== null && $field_options_html !== '';
$has_operator      = isset( $operator_html ) && $operator_html !== null && $operator_html !== '';
$has_value         = isset( $value_html ) && $value_html !== null && $value_html !== '';
?>

<div class="ys-3-columns ys-condition">
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-field"
    >
      Field
    </label>
    <select
      class="ys-select ys-condition-field"
      id="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-field"
      name="sync_rules[<?php echo $rule_index; ?>][conditions][<?php echo esc_attr( $condition_index ); ?>][field]"
    >
      <?php if ( $has_field_options ) : ?>
        <?php echo $field_options_html; ?>
      <?php else : ?>
        <option value="" <?php selected( $field, '' ); ?>>&mdash; Select field &mdash;</option>
      <?php endif; ?>
    </select>
  </div>
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-operator"
    >
      Comparison Operator
    </label>
    <select
      class="ys-select ys-condition-operator"
      <?php echo $has_operator ? '' : 'disabled'; ?>
      id="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-operator"
      name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][conditions][<?php echo esc_attr( $condition_index ); ?>][operator]"
    >
      <?php if ( $has_operator ) echo $operator_html; ?>
    </select>
  </div>
  <div class="ys-form-group ys-mb-0">
    <label
      class="screen-reader-text"
      for="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-value"
    >
      Value
    </label>
    <?php if ( $has_value ) : ?>
      <?php echo $value_html; ?>
    <?php else : ?>
      <?php
        echo yousync_return_template_part( 'input', 'text', array(
          'condition_index' => $condition_index,
          'rule_index'      => $rule_index,
        ) );
      ?>
    <?php endif; ?>
  </div>
  <div class="ys-condition-actions">
    <button class="button ys-remove-condition" title="Remove condition" type="button"><span class="dashicons dashicons-no"></span></button>
  </div>
</div>
