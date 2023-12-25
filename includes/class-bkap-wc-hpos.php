<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * BKAP WC HPOS Compatibility Class.
 *
 * @author      Tyche Softwares
 * @package     BKAP/WC_HPOS
 * @category    Classes
 * @since       5.17.0
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! class_exists( 'BKAP_Wc_Hpos' ) ) {
	
	/**
	 * BKAP Product Settings.
	 *
	 * @since 5.17.0
	 */
	class BKAP_Wc_Hpos {

		/**
		 * Initializes the BKAP_Product class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 5.17.0
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new self();
			}

			return $instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 5.17.0
		 */
		public function __construct() {
			add_action( 'before_woocommerce_init', array( &$this, 'bkap_custom_order_tables_compatibility' ), 999 );
		}

		/**
		 * Sets the bookable Product as purchasable when a price has not been set.
		 *
		 * @since 5.17.0
		 */
		public static function bkap_custom_order_tables_compatibility() {

			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', 'woocommerce-booking/woocommerce-booking.php', true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', 'woocommerce-booking/woocommerce-booking.php', true );
			}
		}
	}
}

/**
 * Returns a single instance of the class.
 *
 * @since 5.15.0
 * @return object
 */
function bkap_wc_hpos() {
	return BKAP_Wc_Hpos::init();
}

bkap_wc_hpos();
