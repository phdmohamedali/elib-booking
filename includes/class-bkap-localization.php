<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Plugin Localization
 *
 * @author   Tyche Softwares
 * @package  BKAP/Plugin-Localization
 * @category Classes
 * @class    Bkap_Localization
 */

if ( ! class_exists( 'Bkap_Localization' ) ) {

	/**
	 * Class Bkap_Localization.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Localization {

		/**
		 * Bkap_Localization constructor.
		 */
		public function __construct() {
			add_action( 'init', array( &$this, 'bkap_update_po_file' ) );
		}

		/**
		 * Load plugin text domain and specify the location of localization po & mo files
		 *
		 * @since 1.7
		 */
		public static function bkap_update_po_file() {

			$domain = 'woocommerce-booking';
			$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
			$loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '-' . $locale . '.mo' );

			if ( $loaded ) {
				return $loaded;
			} else {
				load_plugin_textdomain( $domain, false, basename( dirname( BKAP_FILE ) ) . '/languages/' );
			}
		}
	}
	$bkap_localization = new Bkap_Localization();
}
