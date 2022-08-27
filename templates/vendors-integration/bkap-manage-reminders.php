<?php
/**
 * The template adds Resource Form on Front End for Adding/Editing Resource.
 *
 * @package BKAP/Wcfm-Marketplace-Manage-Resource
 * @version 1.1.0
 */
?>
<a href="<?php echo $manage_reminder_url; ?>" class="button" style="margin-bottom:16px;"><?php echo __( 'Back', 'woocommerce-booking' ); ?></a>
<?php
	if ( $edit ) {
	?>
	<button style="float:right;" id="bkap_delete_reminder" name="bkap_delete_reminder" class="button-primary" ><i class="fa fa-trash"></i></button>
	<?php } ?>

<form method="POST">
	<div id="titlewrap">
		<input type="text" id="bkap_reminder_title" placeholder="<?php esc_attr_e( 'Add Reminder Title', 'woocommerce-booking' ); ?>" name="bkap_reminder_title" style="width:100%" value="<?php esc_attr_e( $reminder_title ); // phpcs:ignore ?>" >
	</div>
	<br>
	<h2><?php __( 'Email Reminder Settings', 'woocommerce-booking' ); ?></h2>
	<?php
	$prefix = 'bkap';
	/* Email Reminder Settings */
	wc_get_template(
		'reminders/html-bkap-email-reminder-settings.php',
		array(
			'post'          => $post,
			'prefix'        => $prefix,
			'email_subject' => $email_subject,
			'email_heading' => $email_heading,
			'email_content' => $email_content,
		),
		'woocommerce-booking/',
		BKAP_BOOKINGS_TEMPLATE_PATH
	);

	?>
	<p><span class="description"><?php esc_html_e( 'You can insert the following tags. They will be replaced dynamically', 'woocommerce-booking' ); ?>: <code>{product_title} {order_date} {order_number} {customer_name} {customer_first_name} {customer_last_name} {start_date} {end_date} {booking_time} {booking_id} {booking_resource} {booking_persons} {zoom_link}</code></span></p>
	<h2 style="margin-top:32px;">Reminder Status</h2>
	<br>
	<p class="wide" id="actions">
		<label for="<?php echo $prefix ; ?>_reminder_action"><?php echo __( 'Status: ', 'woocommerce-booking' ); ?></label>
		<select name="<?php echo $prefix ; ?>_reminder_action">
			<?php foreach ( $reminder_actions as $action => $title ) { ?>
				<option value="<?php echo esc_attr( $action ); ?>" <?php echo ( $action == $reminder_status ) ? 'selected="selected"' : ''; ?>><?php echo esc_html( $title ); ?></option>
			<?php } ?>
		</select>
	</p>

	<h2><?php __( 'Reminder Settings', 'woocommerce-booking' ); ?></h2>

	<div class="bkap-reminder-settings" style="margin-top:32px;">
	<?php

	/* Reminder Settings */
	wc_get_template(
		'reminders/html-bkap-reminder-settings.php',
		array(
			'post'          => $post,
			'prefix'        => $prefix,
			'bkap_version'  => BKAP_VERSION,
			'reminder'      => new BKAP_Reminder( $reminder_id ),
		),
		'woocommerce-booking/',
		BKAP_BOOKINGS_TEMPLATE_PATH
	);
	?>
	</div>
<?php
	if ( $edit ) {
		?>
		<input type="hidden" id="bkap_reminder_id" name="bkap_reminder_id" value="<?php esc_attr_e( $reminder_id ); // phpcs:ignore ?>">
		<?php
	}
	?>
	<input type="hidden" id="bkap_reminder_url" name="bkap_reminder_url" value="<?php esc_attr_e( $manage_reminder_url ); // phpcs:ignore ?>">
	<br />
	<div id="bkap_reminder_manager_submit">
		<input type="submit" id="bkap_reminder_manager" name="bkap_reminder_manager" value="<?php esc_attr_e( 'Save Changes', 'woocommerce-booking' ); ?>" class="button">
	</div>
</form>
