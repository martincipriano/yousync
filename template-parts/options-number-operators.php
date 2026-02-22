<?php
  $operator = $args['operator'];
?>
<option disabled selected value="">&mdash; Select number operator &mdash;</option>
<option value="greater_than" <?php selected($operator, 'greater_than'); ?>>Greater than</option>
<option value="less_than" <?php selected($operator, 'less_than'); ?>>Less than</option>
<option value="equal_to" <?php selected($operator, 'equal_to'); ?>>Equal to</option>