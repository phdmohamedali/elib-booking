<?php
/**
 * Display generalized layout for Booking Dashboard.
 *
 * @package BKAP/Wcfm-Marketplace-Dashboard
 */

?>
<?php
/**
 *  Dokan Dashboard Bookings Template
 *
 *  Load all Tabs and Base Area ti display content
 *
 *  @since 4.6.0
 *
 *  @package woocommerce-booking
 */
?>

<style type="text/css">

</style>
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