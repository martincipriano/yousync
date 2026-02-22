<?php
  $operator = $args['operator'];
?>
<option disabled selected value="">&mdash; Select date operator &mdash;</option>
<option value="before" <?php selected($operator, 'before'); ?>>Before</option>
<option value="after" <?php selected($operator, 'after'); ?>>After</option>
<option value="on" <?php selected($operator, 'after'); ?>>On</option>
