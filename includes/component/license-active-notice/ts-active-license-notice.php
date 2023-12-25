<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Active License Notice class
 *
 * @class Bkap_Active_License_Notice
 * @version 1.0
 */

class Bkap_Active_License_Notice {

	/**
	 *
	 * @var string The name of the plugin
	 * @access public
	 */
	public $plugin_name = '';

	/**
	 *
	 * @var string The option name where the license key is stored
	 * @access public
	 */
	public $plugin_license_option = '';

	/**
	 * Store the path of the license page.
	 *
	 * @var string Path of the license page.
	 * @access public
	 */
	public $ts_license_page_url = '';

	/**
	 * Store the plguin locale.
	 *
	 * @var string Used Plugin locale.
	 * @access public
	 */
	public $ts_locale = '';

	/**
	 *  Default Constructor
	 *
	 * @access public
	 * @since  7.7
	 */
	public function __construct( $ts_plugin_name = '', $ts_license_option_name = '', $ts_license_page_url = '', $ts_locale = '' ) {
		$this->plugin_name           = $ts_plugin_name;
		$this->plugin_license_option = $ts_license_option_name;
		$this->ts_license_page_url   = $ts_license_page_url;
		$this->ts_locale             = $ts_locale;
		if ( '' != $this->plugin_license_option ) {
			add_action( 'admin_init', array( &$this, 'ts_check_if_license_active' ) );
		}

	}

	/*
	 Check if the license key is active for the plugin. If not active a notice will be displayed
	 *
	 * @access public
	 * @since 7.7
	 */
	public function ts_check_if_license_active() {
		if ( ! $this->ts_check_active_license() ) {
			add_action( 'admin_notices', array( &$this, 'ts_license_active_notice' ), 12 );
		}
	}

	/**
	 * Returns the result of the license key
	 *
	 * @access public
	 * @return bool
	 * @since  7.7
	 */
	public function ts_check_active_license() {
		$status = get_option( $this->plugin_license_option );
		if ( false !== $status && 'valid' == $status ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 *  Display the notice if the license key is not active
	 *
	 * @access public
	 * @since 7.7
	 */

	public function ts_license_active_notice() {

		// Fix #4313: 1. Replaced get_post_type() with $current_screen->post_type. 2. Prevent notices from displaying in plugin activation page.

		global $current_screen;

		if ( 'page' !== $current_screen->post_type && 'post' !== $current_screen->post_type && 'update' !== $current_screen->base && 'bkap_booking_page_booking_license_page' !== $current_screen->id ) {

			$class   = 'notice notice-error';
			$message = __( 'We have noticed that the license for <b>' . $this->plugin_name . '</b> plugin is not active. To receive automatic updates & support, please activate the license <a href= "' . $this->ts_license_page_url . '"> here </a>.', "'. $this->ts_locale .'" );
			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );

		}
	}
}
