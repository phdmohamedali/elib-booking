<?php
/**
 *  WC Vendor Dashboard Bookings Template
 *
 *  @since 5.10.0
 *
 *  @package woocommerce-booking
 */

// Loading Dashboard Page from Booking Plugin.
wc_get_template(
	'bkap-booking-dashboard.php',
	array(
		'bkap_vendor_endpoints'       => $bkap_vendor_endpoints,
		'bkap_vendor_endpoints_group' => $bkap_vendor_endpoints_group,
		'bkap_vendor'                 => $bkap_vendor,
		'end_point'                   => $end_point,
	),
	'woocommerce-booking/',
	BKAP_VENDORS_TEMPLATE_PATH
);
