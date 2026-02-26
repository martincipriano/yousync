<option disabled selected value="">&mdash; Select schedule &mdash;</option>
<option value="once" <?php selected( $selected, 'once' ); ?>>Once</option>
<option value="hourly" <?php selected( $selected, 'hourly' ); ?>>Hourly</option>
<option value="daily" <?php selected( $selected, 'daily' ); ?>>Daily</option>
<option value="weekly" <?php selected( $selected, 'weekly' ); ?>>Weekly</option>
<option value="monthly" <?php selected( $selected, 'monthly' ); ?>>Monthly</option>
<option value="custom" <?php selected( $selected, 'custom' ); ?>>Custom</option>