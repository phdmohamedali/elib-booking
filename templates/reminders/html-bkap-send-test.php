<?php
$booking_ids = get_posts(
	array(
		'post_type'     => 'bkap_booking',
		'posts_per_page' => 1,
		'post_status'   => array( 'paid', 'confirmed', 'pending-confirmation' ),
	)
);

$booking_id = 0;
foreach ( $booking_ids as $booking ) {
	$booking_id = $booking->ID;
}

?>
<table class="form-table">
	<tr>
		<td colspan="2">
			<input style="width:100%;" id="<?php echo $prefix;?>_test_reminder" name="<?php echo $prefix;?>_test_reminder" type="text" placeholder="Email Address" />
		</td>
	</tr>
	<tr>
		<td><input type="button" id="<?php echo $prefix;?>_reminder_test" class="button" value="<?php _e( 'Send Test', 'woocommerce-booking' ); ?>" /></td>
		<td><?php printf( '<a href="javascript:void(0)" id="bkap_preview_reminder">%s</a>', __( 'Preview in browser', 'woocommerce-booking' ) );?></td>
		<td><input type="hidden" name="<?php echo $prefix;?>_booking_id" id="<?php echo $prefix;?>_booking_id" value="<?php echo $booking_id; ?>" /></td>
	</tr>
</table>
<div id="bkap_reminder_message"></div>

<?php

bkap_edit_bookings_class::bkap_enqueue_edit_bookings_styles( '1.0.0' );

wc_get_template(
	'bkap-preview-reminder-modal.php',
	array(),
	'woocommerce-booking/',
	BKAP_BOOKINGS_TEMPLATE_PATH . 'reminders/'
);
