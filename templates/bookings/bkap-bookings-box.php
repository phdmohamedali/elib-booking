<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Template for Bookings Box. This template shall be resued on Cart, Checkout and My Account Pages
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$display_template = bkap_display_booking_fields( $product_id, $booking_settings, true );
$booking_type     = bkap_type( $product_id );

if ( $display_template ) {

	$booking_parameters = array(
		'product_id'       => $product_id,
		'product_obj'      => $product_obj,
		'booking_settings' => $booking_settings,
		'global_settings'  => $global_settings,
		'hidden_dates'     => $hidden_dates,
		'booking_type'     => $booking_type,
	);

	wc_get_template(
		'bookings/bkap-bookings-hidden-fields.php',
		$booking_parameters,
		'woocommerce-booking/',
		BKAP_BOOKINGS_TEMPLATE_PATH
	);

	if ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] === 'on' ) {

		wc_get_template(
			'bookings/bkap-bookings-date.php',
			$booking_parameters,
			'woocommerce-booking/',
			BKAP_BOOKINGS_TEMPLATE_PATH
		);

		do_action( 'bkap_after_booking_box_form', $booking_settings, $booking_parameters );
	}
} else {
	bkap_unavailable_for_booking();
}
