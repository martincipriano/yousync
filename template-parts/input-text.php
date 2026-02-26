<?php
  $rule_index      = isset( $rule_index ) ? $rule_index : '{{RULE_INDEX}}';
  $condition_index = isset( $condition_index ) ? $condition_index : '{{CONDITION_INDEX}}';
  $is_disabled     = isset( $disabled ) ? (bool) $disabled : true;
  $input_value     = isset( $value ) ? $value : '';
?>
<input
  class="ys-text ys-condition-value"
  <?php echo $is_disabled ? 'disabled' : ''; ?>
  id="sync-rules-<?php echo esc_attr( $rule_index ); ?>-conditions-<?php echo esc_attr( $condition_index ); ?>-value"
  name="sync_rules[<?php echo esc_attr( $rule_index ); ?>][conditions][<?php echo esc_attr( $condition_index ); ?>][value]"
  type="text"
  value="<?php echo esc_attr( $input_value ); ?>"
  placeholder="Enter value..."
>