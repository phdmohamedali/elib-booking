<?php
/**
 * Admin new imported event email
 */
// echo "= " . $email_heading . " =\n\n";

$opening_paragraph = __( 'A new event has been imported. The details of the event are as follows:', 'woocommerce-booking' );
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Event Summary', 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $event_details->event_summary; ?></td>
		</tr>
		
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Event Description', 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $event_details->event_description; ?></td>
		</tr>
		
		<tr>
			<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( 'Event Start Date', 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $event_details->booking_start; ?></td>
		</tr>
		<?php
		if ( isset( $event_details->booking_end ) && '' != $event_details->booking_end ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( 'Event End Date', 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $event_details->booking_end; ?></td>
			</tr>
			<?php
		}
		if ( isset( $event_details->booking_time ) && '' != $event_details->booking_time ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( 'Event Time', 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $event_details->booking_time; ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>

<p><?php _e( 'This event has been imported and needs to be mapped. Please check it and map the event to the corresponding product to ensure it\'s added to the list of bookings on the website.', 'woocommerce-booking' ); ?></p>

<?php if ( $event_details->user_id == 0 ) { ?> 
	<p><?php echo make_clickable( sprintf( __( 'You can view and edit this event in the dashboard here: %s', 'woocommerce-booking' ), admin_url( 'admin.php?page=woocommerce_import_page' ) ) ); ?></p>
<?php } elseif ( $event_details->user_id > 0 ) { ?>
	<p><?php echo make_clickable( sprintf( __( 'You can view and edit this event in the dashboard here: %s', 'woocommerce-booking' ), admin_url( 'admin.php?page=tours_import_bookings' ) ) ); ?></p>
<?php }

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer' );
