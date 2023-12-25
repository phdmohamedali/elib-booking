<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Calendar View for Vendor Bookings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/Dokan
 * @since    4.6.0
 * @category Classes
 */

if ( ! class_exists( 'bkap_dokan_calendar_class' ) ) {

	/**
	 * Class for displaying Booking details in a calendar view
	 *
	 * @since 4.6.0
	 */
	class bkap_dokan_calendar_class {

		/**
		 * Plugin Version
		 *
		 * @access public
		 * @since 4.6.0
		 */
		public $plugin_version = '';

		/**
		 * Default constructor. Defines the plugin_version property and hooks functions
		 *
		 * @since 4.6.0
		 */
		function __construct() {

			$this->plugin_version = get_option( 'woocommerce_booking_db_version' );

			add_action( 'bkap_dokan_booking_content_before', array( &$this, 'bkap_dokan_include_calendar_styles' ) );

			add_action( 'bkap_dokan_booking_calendar_after', array( &$this, 'bkap_dokan_include_calendar_scripts' ) );
		}

		/**
		 * Load Calendar View Styles before HTML is rendered
		 *
		 * @since 4.6.0
		 */
		public function bkap_dokan_include_calendar_styles() {

			bkap_load_scripts_class::bkap_load_products_css( $this->plugin_version );
			bkap_load_scripts_class::bkap_load_calendar_styles( $this->plugin_version );
		}

		/**
		 * Load scripts after HTML is rendered
		 *
		 * @since 4.6.0
		 */
		public function bkap_dokan_include_calendar_scripts() {

			bkap_load_scripts_class::bkap_load_calendar_scripts( $this->plugin_version, get_current_user_id() );
		}
	}
}

return new bkap_dokan_calendar_class();
