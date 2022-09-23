<?php
/**
 * Customer booking confirmed email
 */

echo '= ' . $email_heading . " =\n\n";

$order = new WC_order( $booking->order_id );
if ( $order ) {
	$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
	echo sprintf( __( 'Hello %s', 'woocommerce-booking' ), $billing_first_name ) . "\n\n";
}

echo __( 'We are sorry to say that your booking could not be confirmed and has been cancelled. The details of the cancelled booking can be found below.', 'woocommerce-booking' ) . "\n\n";

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Booked: %s', 'woocommerce-booking' ), $booking->product_title ) . "\n";

echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-date' ), $booking->item_booking_date ) . "\n";

if ( isset( $booking->item_checkout_date ) && '' != $booking->item_checkout_date ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'checkout_item-meta-date' ), $booking->item_checkout_date ) . "\n";
}

if ( isset( $booking->item_booking_time ) && '' != $booking->item_booking_time ) {
	echo sprintf( __( '%1$s: %2$s', 'woocommerce-booking' ), get_option( 'book_item-meta-time' ), $booking->item_booking_time ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo __( 'Please contact us if you have any questions or concerns.', 'woocommerce-booking' ) . "\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
