<table class="form-table" role="presentation">
	<tbody>
	<tr>
		<th scope="row"><label for="<?php echo $prefix; ?>_email_subject"><?php esc_html_e( 'Email Subject :', 'woocommerce-booking' ); ?></label></th>
		<td>
			<input style="width:100%;" type="text" name="<?php echo $prefix; ?>_email_subject" id="<?php echo $prefix; ?>_email_subject" value="<?php esc_attr_e( $email_subject ); ?>" autocomplete="off" title="<?php esc_attr_e( 'The quantity of this resource available at any given time.', 'woocommerce-booking' ); ?>">
			<p><small><?php echo __( 'This controls the email subject line. Leave blank to to use the default subject: [{blogname}] You have a booking for {product_title}', 'woocommerce-booking' ); ?></small></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="<?php echo $prefix; ?>_email_heading"><?php esc_html_e( 'Email Heading :', 'woocommerce-booking' ); ?></label></th>
		<td>
			<input style="width:100%;"type="text" name="<?php echo $prefix; ?>_email_heading" id="<?php echo $prefix; ?>_email_heading" value="<?php esc_attr_e( $email_heading ); ?>" autocomplete="off" title="<?php esc_attr_e( 'The quantity of this resource available at any given time.', 'woocommerce-booking' ); ?>">
			<p><small><?php echo __( 'This controls the email heading. Leave blank to to use the default heading: Booking Reminder', 'woocommerce-booking' ); ?></small></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="<?php echo $prefix; ?>_email_content"><?php esc_html_e( 'Email Content :', 'woocommerce-booking' ); ?></label></th>
		<td><?php
		wp_editor(
			$email_content,
			$prefix . '_email_content',
			array(
				'wpautop'       => false,
				'media_buttons' => true,
				'textarea_name' => $prefix . '_email_content',
				'textarea_rows' => 20,
				'teeny'         => false
			)
		);
		?>
		</td>
	</tr>
	</tbody>
</table>