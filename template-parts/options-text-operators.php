<?php
  $operator = $args['operator'];
?>
<option disabled selected value="">&mdash; Select operator &mdash;</option>
<option value="contains" <?php selected($operator, 'contains'); ?>>Contains</option>
<option value="not_contains" <?php selected($operator, 'not_contains'); ?>>Does not contain</option>
<option value="equals" <?php selected($operator, 'equals'); ?>>Matches</option>
<option value="not_equals" <?php selected($operator, 'not_equals'); ?>>Does not match</option>
<option value="starts_with" <?php selected($operator, 'starts_with'); ?>>Starts with</option>
<option value="ends_with" <?php selected($operator, 'ends_with'); ?>>Ends with</option>