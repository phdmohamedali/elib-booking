<?php
/**
 * Admin new imported event email
 */
// echo "= " . $email_heading . " =\n\n";

$opening_paragraph = __( 'A new event has been imported. The details of the event are as follows:', 'woocommerce-booking' );


echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo sprintf( __( 'Event Summary: %s', 'woocommerce-booking' ), $event_details->event_summary ) . "\n";

echo sprintf( __( 'Event Description: %s', 'woocommerce-booking' ), $event_details->event_description ) . "\n";

echo sprintf( __( 'Event Start Date: %s', 'woocommerce-booking' ), $event_details->booking_start ) . "\n";

if ( isset( $event_details->booking_end ) && '' != $event_details->booking_end ) {
	echo sprintf( __( 'Event End Date: %s', 'woocommerce-booking' ), $event_details->booking_end ) . "\n";
}

if ( isset( $event_details->booking_time ) && '' != $event_details->booking_time ) {
	echo sprintf( __( 'Event Time: %s', 'woocommerce-booking' ), $event_details->booking_time ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo __( 'This event has been imported and needs to be mapped. Please check it and map the event to the corresponding product to ensure it\'s added to the list of bookings on the website.', 'woocommerce-booking' ) . "\n\n";

/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( $additional_content ) {
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
	echo "\n\n----------------------------------------\n\n";
}

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );

