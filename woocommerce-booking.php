<?php
/**
 * Plugin Name: Booking & Appointment Plugin for WooCommerce
 * Plugin URI: https://www.tychesoftwares.com/products/woocommerce-booking-and-appointment-plugin/
 * Description: This plugin lets you capture the Booking Date & Booking Time for each product thereby allowing your WooCommerce store to effectively function as a Booking system. It allows you to add different time slots for different days, set maximum bookings per time slot, set maximum bookings per day, set global & product specific holidays and much more.
 * Version: 5.23.1
 * Author: Tyche Softwares
 * Author URI: https://www.tychesoftwares.com/
 * Text Domain: woocommerce-booking
 * Requires PHP: 7.3
 * WC requires at least: 3.9
 * WC tested up to: 8.0
 *
 * @package BKAP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woocommerce_Booking' ) ) {

	/**
	 * Booking & Appointment Plugin Core Class
	 *
	 * @class woocommerce_booking
	 */
	class Woocommerce_Booking {

		/**
		 * Default constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {

			/**
			 * Defining Constants.
			 */
			$this->bkap_define_constants();

			/**
			 * Check required pugins.
			 */
			add_action( 'admin_init', array( $this, 'bkap_do_required_plugin_check' ) );

			if ( ! self::bkap_is_required_plugin_active() ) {
				return;
			}

			/**
			 * Including Plugin Files
			 */
			self::bkap_maybe_include_files();

			/**
			 * Plugin Updater
			 */
			include dirname( __FILE__ ) . '/includes/plugin-updates/EDD_Plugin_Updater.php';

			/**
			 * Initialize settings
			 */
			register_activation_hook( __FILE__, array( &$this, 'bkap_bookings_activate' ) );

			/**
			 * Delete options and setting on deactivation of plugin.
			 */
			register_deactivation_hook( __FILE__, array( &$this, 'bkap_bookings_deactivate' ) );
		}

		/**
		 * Including plugin files.
		 *
		 * @since 1.7
		 */
		public static function bkap_include_files() {
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'bkap-availability-search.php';
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class-bkap-webhooks.php';
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class-bkap-include-files.php';
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'api/class-bkap-api-include-files.php'; // Include API files.
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class-bkap-wc-hpos.php';
		}

		/**
		 * Define constants to be used accross the plugin
		 *
		 * @since 4.6.0
		 */
		public static function bkap_define_constants() {

			/**
			 * This is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
			 * IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system
			 */
			define( 'EDD_SL_STORE_URL_BOOK', 'https://www.tychesoftwares.com/' );

			/**
			 * The name of your product. This is the title of your product in EDD and should match the download title in EDD exactly
			 * IMPORTANT: change the name of this constant to something unique to prevent conflicts with other plugins using this system
			 */
			define( 'EDD_SL_ITEM_NAME_BOOK', 'Booking & Appointment Plugin for WooCommerce' );

			define( 'BKAP_VERSION', '5.23.1' );

			define( 'BKAP_CDN', 'https://static.tychesoftwares.com/woocommerce-booking' );

			define( 'BKAP_DEV_MODE', false );

			if ( ! defined( 'BKAP_FILE' ) ) {
				define( 'BKAP_FILE', __FILE__ );
			}

			if ( ! defined( 'BKAP_PLUGIN_PATH' ) ) {
				define( 'BKAP_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
			}

			if ( ! defined( 'BKAP_PLUGIN_URL' ) ) {
				define( 'BKAP_PLUGIN_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
			}

			if ( ! defined( 'BKAP_REQUIRED_PLUGIN_ERROR_MESSAGE' ) ) {
				define( 'BKAP_REQUIRED_PLUGIN_ERROR_MESSAGE', sprintf( __( 'WooCommerce not found. %s requires a minimum of WooCommerce v3.3.0.', 'woocommerce-booking' ), EDD_SL_ITEM_NAME_BOOK ) );
			}

			define( 'BKAP_BOOKINGS_INCLUDE_PATH', BKAP_PLUGIN_PATH . '/includes/' );
			define( 'BKAP_BOOKINGS_TEMPLATE_PATH', BKAP_PLUGIN_PATH . '/templates/' );
			define( 'BKAP_VENDORS_INCLUDES_PATH', BKAP_PLUGIN_PATH . '/includes/vendors/' );
			define( 'BKAP_VENDORS_LIBRARIES_PATH', BKAP_PLUGIN_PATH . '/includes/libraries/' );
			define( 'BKAP_VENDORS_TEMPLATE_PATH', BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/' );
			define( 'AJAX_URL', get_admin_url() . 'admin-ajax.php' );
		}

		/**
		 * This function creates all the tables necessary in database detects when the booking plugin is activated.
		 */
		public function bkap_bookings_activate() {

			if ( ! self::bkap_is_required_plugin_active() ) {
				deactivate_plugins( plugin_basename( __FILE__ ) );
				wp_die( BKAP_REQUIRED_PLUGIN_ERROR_MESSAGE );
			}

			require_once plugin_dir_path( __FILE__ ) . 'includes/class-bkap-plugin-activate.php';
			Bkap_Plugin_Activate::bkap_activate();
		}

		/**
		 * Delete orphaned records from database on deactivation.
		 */
		public function bkap_bookings_deactivate() {
			delete_transient( 'bkap_timeslot_notice' );
		}

		/**
		 * Checks if WooCommerce is installed and active.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_do_required_plugin_check() {
			if ( ! self::bkap_is_required_plugin_active() ) {
				if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
					deactivate_plugins( plugin_basename( __FILE__ ) );
					add_action( 'admin_notices', array( 'Woocommerce_Booking', 'show_required_plugin_error_notice' ) );
					if ( isset( $_GET['activate'] ) ) {
						unset( $_GET['activate'] );
					}
				}
			}
		}

		/**
		 * Checks if WooCommerce is installed and active.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_is_required_plugin_active() {

			// WooCommerce is required, so we do a check.
			$woocommerce_path = 'woocommerce/woocommerce.php';
			$active_plugins   = (array) get_option( 'active_plugins', array() );

			$active = false;
			if ( is_multisite() ) {
				$plugins = get_site_option( 'active_sitewide_plugins' );
				if ( isset( $plugins[ $woocommerce_path ] ) ) {
					$active = true;
				}
			}

			return in_array( $woocommerce_path, $active_plugins ) || array_key_exists( $woocommerce_path, $active_plugins ) || $active;
		}

		/**
		 * Displays WooCommerce Required Notice.
		 *
		 * @since 5.12.0
		 */
		public static function show_required_plugin_error_notice() {
			echo '<div class="error"><p><strong>' . BKAP_REQUIRED_PLUGIN_ERROR_MESSAGE . '</strong></p></div>';
		}

		/**
		 * Checks whether to inlcude BKAP core files.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_maybe_include_files() {

			if ( self::bkap_is_required_plugin_active() ) {
				self::bkap_include_files();
			}
		}
	}

	$woocommerce_booking = new Woocommerce_Booking();
}
