<?php

?>
<section class="bkap-automatic">
	<div class="wrap">
		<h2><?php echo esc_html( $heading ); ?></h2>
		<div id="content">
			<form method="POST">
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><?php echo esc_html( $row_heading ); ?></th>
							<td>
								<input type="number" name="bkap_reminder_settings[reminder_email_before_hours]" id="reminder_email_before_hours" value="<?php echo esc_attr( $number_of_hours ); ?>">
								<label for="reminder_email_before_hours"><?php echo esc_html( $label ); ?></label>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit"><input type="submit" name="<?php echo esc_attr( $save_button ); ?>" id="<?php echo esc_attr( $save_button ); ?>" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'woocommerce-booking' ); ?>"></p>
			</form>
		</div>
	</div>
</section>
<hr>
