<?php
/**
 * Customer new pending booking email.
 */
$order = wc_get_order( $booking->order_id );

echo '= ' . $email_heading . " =\n\n";
$opening_paragraph = __( 'We have received your request for a booking. The details of the booking are as follows:', 'woocommerce-booking' );

$billing_first_name = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_first_name : $order->get_billing_first_name();
$billing_last_name  = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->billing_last_name : $order->get_billing_last_name();

echo sprintf( $opening_paragraph ) . "\n\n";


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

if ( bkap_common::bkap_order_requires_confirmation( $order ) && 'pending-confirmation' == $booking->item_booking_status ) {
	echo __( 'You shall receive confirmation email with the next steps once your booking is confirmed.', 'woocommerce-booking' ) . "\n\n";
}

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
