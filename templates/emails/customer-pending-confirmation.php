<?php
/**
 * Customer booking Pending email.
 */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php 
$order = wc_get_order( $booking->order_id ); 
?>
<?php if ( $message !== '' ) : ?>
	<p><?php echo wpautop( wptexturize( $message ) ); ?></p>
<?php else : ?>
	<?php

	if ( $order ) :
		$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
		?>
		<p><?php printf( __( 'Hello %s', 'woocommerce-booking' ), $billing_first_name ); ?></p>
	<?php endif; ?>

	<p><?php _e( 'We have received your request for a booking. The details of the booking are as follows:', 'woocommerce-booking' ); ?></p>
<?php endif; ?>

<table cellspacing="0" cellpadding="6" style="width: 100%;border-color: #aaa; border: 1px solid #aaa;">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Booked Product', 'woocommerce-booking' ); ?></th>
			<td scope="row" style="text-align:left; border: 1px solid #eee;"><?php echo $booking->product_title; ?></td>
		</tr>
		<tr>
			<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->start_date_label, 'woocommerce-booking' ); ?></th>
			<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->item_booking_date; ?></td>
		</tr>
		<?php
		if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->end_date_label, 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->item_checkout_date; ?></td>
			</tr>
			<?php
		}
		if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->time_label, 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->item_booking_time; ?></td>
			</tr>
			<?php
		}

		if ( isset( $booking->resource_title ) && '' != $booking->resource_title ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->resource_label, 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->resource_title; ?></td>
			</tr>
			<?php
		}

		if ( isset( $booking->person_data ) && '' != $booking->person_data ) {
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $booking->person_label, 'woocommerce-booking' ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->person_data; ?></td>
			</tr>
			<?php
		}

		?>

	</tbody>
</table>
<p><?php _e( 'You shall receive confirmation email with the next steps once your booking is confirmed.', 'woocommerce-booking' ); ?></p>

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
