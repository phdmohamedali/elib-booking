<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Including plugin files.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Include-Files
 * @category Classes
 * @class    Bkap_Include_Files
 */

if ( ! class_exists( 'Bkap_Include_Files' ) ) {

	/**
	 * Class Bkap_Include_Files.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Include_Files {

		/**
		 * Bkap_Plugin_Meta constructor.
		 */
		public function __construct() {
			/**
			 * Including files
			 */
			add_action( 'init', array( &$this, 'bkap_include_files' ), 5 );
			add_action( 'admin_init', array( &$this, 'bkap_include_files' ) );

			// Insert our booking setting page screen id into woocommerce id for tooltip handling.
			add_filter( 'woocommerce_screen_ids', array( $this, 'bkap_insert_booking_screen_ids_into_wc_ids' ) );
		}

		/**
		 * Include the dependent plugin files.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_include_files() {

			$include_files = array(
				'/includes/class-bkap-license.php',
				'/includes/bkap-admin-bookings.php',
				'/includes/class-bkap-bookable-query.php',
				'/includes/class-bkap-plugin-meta.php',
				'/includes/class-bkap-plugin-update.php',
				'/includes/class-bkap-localization.php',
				'/includes/bkap-lang.php',
				'/includes/bkap-common.php',
				'/includes/bkap-block-pricing.php',
				'/includes/bkap-special-booking-price.php',
				'/includes/bkap-validation.php',
				'/includes/bkap-checkout.php',
				'/includes/bkap-cart.php',
				'/includes/bkap-ics.php',
				'/includes/class.gcal.php',
				'/includes/bkap-calendar-sync-settings.php',
				'/includes/bkap-import-bookings.php',
				'/includes/bkap-cancel-order.php',
				'/includes/bkap-booking-process.php',
				'/includes/bkap-global-menu.php',
				'/includes/bkap-global-settings.php',
				'/includes/class-bkap-addon-settings.php',
				'/includes/class-bkap-reminder.php',
				'/includes/bkap-send-reminder.php',
				'/includes/bkap-zoom-meeting-functions.php',
				'/includes/class-bkap-zoom-meeting-settings.php',
				'/includes/bkap-fluentcrm-functions.php',
				'/includes/class-bkap-fluentcrm-settings.php',
				'/includes/class-bkap-background-process.php',
				'/includes/class-bkap-bulk-booking-settings.php',
				'/includes/bkap-booking-box.php',
				'/includes/class-bkap-multidates.php',
				'/includes/bkap-timeslot-price.php',
				'/includes/class-bkap-duration-time.php',
				'/includes/class-bkap-timezone-conversion.php',
				'/includes/bkap-booking-confirmation.php',
				'/includes/class-booking-email-manager.php',
				'/includes/bkap-variation-lockout.php',
				'/includes/bkap-attribute-lockout.php',
				'/includes/bkap-calendar-sync.php',
				'/includes/class-bkap-gateway.php',
				'/includes/class-bkap-edit-bookings.php',
				'/includes/class-bkap-calendar-view.php',
				'/includes/class-bkap-custom-post-types.php',
				'/templates/meta-boxes/class-bkap-send-reminder-meta-box.php',
				'/includes/class-bkap-privacy-policy.php',
				'/includes/class-bkap-privacy-exporter.php',
				'/includes/class-bkap-privacy-erasure.php',
				'/includes/class-bkap-booking.php',
				'/includes/class-bkap-booking-view-bookings.php',
				'/includes/class-bkap-resource-listing.php',
				'/includes/class-bkap-edit-bookings.php',
				'/includes/class-bkap-rescheduled-order.php',
				'/includes/class-bkap-addon-compatibility.php',
				'/includes/class-bkap-gcal-event.php',
				'/includes/class-bkap-gcal-event-view.php',
				'/includes/class-bkap-list-booking.php',
				'/includes/bkap-process-functions.php',
				'/includes/class-bkap-resources-cpt.php',
				'/includes/class-bkap-product-resource.php',
				'/includes/class-bkap-person-cpt.php',
				'/includes/class-bkap-person.php',
				'/includes/class-bkap-product-filter.php',
				'/includes/class-bkap-booking-dashboard-widget.php',
				'/includes/class-bkap-scripts.php',
				'/includes/class-bkap-booking-endpoints.php',
				'/includes/class-bkap-sms-settings.php',
				'/includes/class-bkap-oauth-google-calendar.php',
				'/includes/bkap-oauth-gcal-options.php',
				'/includes/class-bkap-ajax.php',
				'/includes/class-bkap-edit-booking-post.php',
				'/includes/class-bkap-cancel-booking.php',
				'/includes/api/zapier/class-bkap-api-zapier-settings.php',
				'/includes/api/zapier/class-bkap-api-zapier-log.php',
				'/includes/class-bkap-coupons.php',
			);

			foreach ( $include_files as $include_file ) {
				include_once BKAP_PLUGIN_PATH . $include_file;
			}

			if ( function_exists( 'woo_vou_default_settings' ) ) {
				include_once BKAP_PLUGIN_PATH . '/includes/class-bkap-wc-voucher-pdf.php';
			}

			$vendor = false;
			if ( class_exists( 'WeDevs_Dokan' ) && BKAP_License::business_license() ) {
				include_once BKAP_VENDORS_INCLUDES_PATH . 'dokan/class-bkap-dokan-integration.php';
				$vendor = true;
			}

			include_once BKAP_VENDORS_INCLUDES_PATH . 'vendors-common.php';

			if ( function_exists( 'is_wcvendors_active' ) && is_wcvendors_active() && BKAP_License::business_license() ) {
				include_once BKAP_VENDORS_INCLUDES_PATH . 'wc-vendors/wc-vendors.php';
				$vendor = true;
			}

			if ( function_exists( 'is_wcfm_page' ) && BKAP_License::business_license() ) {
				$vendor = true;
			}

			if ( $vendor ) {
				include_once BKAP_VENDORS_INCLUDES_PATH . 'class-bkap-vendor-compatibility.php';
			}

			if ( class_exists( 'PP_One_Page_Checkout' ) ) {
				include_once BKAP_PLUGIN_PATH . '/includes/class-bkap-onepage-checkout.php';
			}

			if ( true === is_admin() ) {
				include_once BKAP_PLUGIN_PATH . '/includes/bkap-all-component.php';
				include_once BKAP_PLUGIN_PATH . '/includes/class-bkap-system-status.php';
			}
		}

		/**
		 * Insert our booking setting page screen into woocommerce screen id
		 * to make tooltip uniform accross all pages.
		 *
		 * @param array $screen The screen IDS array object.
		 */
		public function bkap_insert_booking_screen_ids_into_wc_ids( $screen ) {
			$screen[] = 'bkap_booking_page_woocommerce_booking_page';
			return $screen;
		}

	}
	$bkap_include_files = new Bkap_Include_Files();
}
