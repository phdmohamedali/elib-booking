<?php
/**
 * Customer booking confirmed email
 */

echo '= ' . $email_heading . " =\n\n";

$order = new WC_order( $booking->order_id );

echo sprintf( __( 'Bookings have been rescheduled for an order from %s. The order is as follows:', 'woocommerce-booking' ), $order->get_formatted_billing_full_name() ) . "\n\n";

echo printf( __( 'Order #%s', 'woocommerce-booking' ), $order->get_order_number() );

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Rescheduled Product: %s', 'woocommerce-booking' ), $booking->product_title ) . "\n";

echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-date' ), $booking->item_booking_date ) . "\n";

if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'checkout_item-meta-date' ), $booking->item_checkout_date ) . "\n";
}

if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-time' ), $booking->item_booking_time ) . "\n";
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
