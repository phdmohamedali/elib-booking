<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for including Booking API files.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api/Include_Files
 * @category    Classes
 * @since       5.9.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Include_Files' ) ) {

	/**
	 * Booking API Include Files.
	 *
	 * @since 5.9.1
	 */
	class BKAP_API_Include_Files {

		/**
		 * Construct
		 *
		 * @since 5.9.1
		 */
		public function __construct() {
			add_action( 'woocommerce_api_loaded', array( &$this, 'load_custom_woocommerce_api_class' ), 10, 1 );
			add_filter( 'woocommerce_api_classes', array( &$this, 'register_woocommerce_api_class' ), 10, 1 );
		}

		/**
		 * Adds the Booking API Class to the WooCommerce API class list.
		 *
		 * @since 5.9.1
		 * @param array $classes Array of WooCommerce classes.
		 * @return array
		 */
		public function register_woocommerce_api_class( $classes ) {

			$bkap_api_classes = array(
				'BKAP_API_Bookings',
				'BKAP_API_Resources',
				'BKAP_API_Products',
				'BKAP_API_Zapier',
				'BKAP_API_Zapier_Bookings',
			);

			return array_merge( $classes, $bkap_api_classes );
		}

		/**
		 * Loads the Booking API Class together with other WooCommerce classes.
		 *
		 * @since 5.9.1
		 */
		public static function load_custom_woocommerce_api_class() {
			include_once BKAP_PLUGIN_PATH . '/includes/api/class-bkap-api-bookings.php';
			include_once BKAP_PLUGIN_PATH . '/includes/api/class-bkap-api-resources.php';
			include_once BKAP_PLUGIN_PATH . '/includes/api/class-bkap-api-products.php';
			include_once BKAP_PLUGIN_PATH . '/includes/api/zapier/class-bkap-api-zapier.php';
			include_once BKAP_PLUGIN_PATH . '/includes/api/zapier/class-bkap-api-zapier-bookings.php';
		}
	}
	$bkap_api_bookings_include_files = new BKAP_API_Include_Files();
}
