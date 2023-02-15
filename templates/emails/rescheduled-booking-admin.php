<?php
/**
 * Admin booking rescheduled email
 */
?>

<?php do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<?php

if ( ! is_null( $booking ) ) :

	$order = wc_get_order( $booking->order_id );
	?>

<p><?php printf( __( 'Bookings have been rescheduled for an order from %s. The order is as follows:', 'woocommerce-booking' ), $order->get_formatted_billing_full_name() ); ?></p>

<h2>
	<a class="link" href="<?php echo esc_url( bkap_order_url( $order->get_id() ) ); ?>">
		<?php printf( __( 'Order #%s', 'woocommerce-booking' ), $order->get_order_number() ); ?>
	</a> 
	(<?php printf( '<time datetime="%s">%s</time>', $order->get_date_created()->format( 'c' ), wc_format_datetime( $order->get_date_created() ) ); ?>)
</h2>

<table cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #eee;" border="1" bordercolor="#eee">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left; border: 1px solid #eee;"><?php _e( 'Rescheduled Product', 'woocommerce-booking' ); ?></th>
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

		if ( isset( $booking->resource_title ) && '' != $booking->resource_title ) {
			?>
				<tr>
					<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php echo $booking->resource_label; ?></th>
					<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->resource_title; ?></td>
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

		if ( isset( $booking->zoom_meeting ) && '' != $booking->zoom_meeting ) {

			$meeting_label = bkap_zoom_join_meeting_label( $booking->product_id );
			?>
			<tr>
				<th style="text-align:left; border: 1px solid #eee;" scope="row"><?php _e( $meeting_label ); ?></th>
				<td style="text-align:left; border: 1px solid #eee;"><?php echo $booking->zoom_meeting; ?></td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>

<?php endif; ?>

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
