<?php
/**
 * Display generalized layout for Feature Page.
 *
 * @package BKAP/Wcfm-Marketplace-Feature-Page
 */

$bkap_vendors          = new BKAP_Vendors();
$bkap_vendor_endpoints = $bkap_vendors->bkap_get_vendor_endpoints( $bkap_vendor );

$booking_heading = __( 'Booking', 'woocommerce-booking' );
foreach ( $bkap_vendor_endpoints as $key => $value ) {
	if ( $value['slug'] === $end_point ) {
		$booking_heading = $value['name'];
		break;
	}
}
array_shift( $bkap_vendor_endpoints ); // removing dashboard endpoint.
$bkap_vendor_endpoints_group = array_chunk( $bkap_vendor_endpoints, 2 );
$bkap_dokan_page             = str_replace( '-', '_', $end_point );

// Loading Dashboard Page from Booking Plugin.
wc_get_template(
	'bkap-booking-feature-page.php',
	array(
		'bkap_vendor_endpoints'       => $bkap_vendor_endpoints,
		'bkap_vendor_endpoints_group' => $bkap_vendor_endpoints_group,
		'bkap_vendor'                 => $bkap_vendor,
		'end_point'                   => $end_point,
		'booking_heading'             => $booking_heading,
	),
	'woocommerce-booking/',
	BKAP_VENDORS_TEMPLATE_PATH
);