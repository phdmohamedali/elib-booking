<?php
/**
 * Customer booking cancelled email
 */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php
$order = new WC_order( $booking->order_id );
if ( $order ) :
	$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
	?>
	<p><?php printf( __( 'Hello %s', 'woocommerce-booking' ), $billing_first_name ); ?></p>
<?php endif; ?>

<p><?php _e( 'We are sorry to say that your booking could not be confirmed and has been cancelled. The details of the cancelled booking can be found below.', 'woocommerce-booking' ); ?></p>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Booked Product', 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->product_title; ?></td>
		</tr>
		<tr>
			<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'book_item-meta-date' ), 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_booking_date; ?></td>
		</tr>
		<?php
		if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'checkout_item-meta-date' ), 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_checkout_date; ?></td>
			</tr>
			<?php
		}
		if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( get_option( 'book_item-meta-time' ), 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->item_booking_time; ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>

<p><?php _e( 'Please contact us if you have any questions or concerns.', 'woocommerce-booking' ); ?></p>

<?php 
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
