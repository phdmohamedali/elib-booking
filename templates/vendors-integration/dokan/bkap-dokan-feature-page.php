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
$bkap_dokan_page              = str_replace( '-', '_', $end_point );
?>

<div class="dokan-dashboard-wrap">

	<?php

		/**
		 *  dokan_dashboard_content_before hook
		 *
		 *  @hooked get_dashboard_side_navigation
		 *
		 *  @since 4.6.0
		 */
		do_action( 'dokan_dashboard_content_before' );
		do_action( 'bkap_dokan_booking_content_before' );

		$bkap_url     = dokan_get_navigation_url( 'bkap-dashboard' );
		$current_page = get_query_var( 'bkap-dashboard' );
	?>

	<div class="dokan-dashboard-content dokan-bkap-dashboard-content">

		<?php

			/**
			 *  bkap_dokan_booking_inside_before hook
			 *
			 *  @since 4.6.0
			 */
			//do_action( 'bkap_dokan_booking_inside_before', $current_page, $bkap_url );
		?>


		<article class="dokan-dashboard-area">

			<?php

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

			?>

		</article>


		<?php

			/**
			 *  dokan_order_content_inside_after hook
			 *
			 *  @since 4.6.0
			 */
			do_action( 'bkap_dokan_booking_content_inside_after' );
		?>

	</div> <!-- #primary .content-area -->

	<?php

		/**
		 *  dokan_dashboard_content_after hook
		 *  dokan_order_content_after hook
		 *
		 *  @since 4.6.0
		 */
		do_action( 'dokan_dashboard_content_after' );
		do_action( 'bkap_dokan_booking_content_after' );

	?>

</div><!-- .dokan-dashboard-wrap -->


