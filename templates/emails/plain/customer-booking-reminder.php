<?php
/**
 * Customer booking confirmed email
 */

echo '= ' . $email_heading . " =\n\n";

$order = wc_get_order( $booking->order_id );

echo sprintf( __( 'You have a booking for %s. Your order is as follows: ', 'woocommerce-booking' ), $booking->product_title ) . "\n\n";

echo printf( __( 'Order #%s', 'woocommerce-booking' ), $order->get_order_number() );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Booked Product: %s', 'woocommerce-booking' ), $booking->product_title ) . "\n";

echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-date' ), $booking->item_booking_date ) . "\n";

if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'checkout_item-meta-date' ), $booking->item_checkout_date ) . "\n";
}

if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-time' ), $booking->item_booking_time ) . "\n";
}
if ( isset( $booking->resource_title ) && '' != $booking->resource_title ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), $booking->resource_label, $booking->resource_title ) . "\n";
}

if ( isset( $booking->zoom_meeting ) && '' != $booking->zoom_meeting ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), bkap_zoom_join_meeting_label( $booking->product_id ), $booking->zoom_meeting ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
