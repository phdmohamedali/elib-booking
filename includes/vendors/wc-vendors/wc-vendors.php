<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan Products with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/WC-Vendors
 * @version  4.8.0
 * @category Classes
 */

/**
 * Class for listing the bookings on respective pages
 *
 * @since 4.6.0
 */
class bkap_wc_vendors {

	/**
	 * Default Constructor
	 *
	 * @since 4.6.0
	 */
	public function __construct() {

		// include the files
		add_action( 'init', array( &$this, 'bkap_wcv_include_files' ), 6 );

		// add the booking menu in NAV
		add_filter( 'wcv_pro_dashboard_urls', array( &$this, 'bkap_add_menu' ), 10, 1 );
		//add_filter( 'wcv_dashboard_pages_nav', array( &$this, 'bkap_modify_menu' ), 10, 1 );

		// add the custom pages
		add_filter( 'wcv_dashboard_custom_pages', array( &$this, 'bkap_booking_menu' ), 10, 1 );

		// View Bookings Data Export
		add_action( 'wp', array( &$this, 'bkap_download_booking_files' ) );

		add_filter( 'bkap_display_multiple_modals', array( &$this, 'bkap_wc_vendors_enable_modals' ) );

		add_action( 'bkap_wc_vendors_booking_list', array( &$this, 'bkap_wc_vendors_load_modals' ), 10, 4 );

		add_action( 'admin_init', array( &$this, 'bkap_remove_menus' ) );

		add_filter( 'bkap_show_add_day_button', array( &$this, 'bkap_show_add_day_button' ), 10, 1 );

		add_action( 'wp_enqueue_scripts', array( &$this, 'bkap_wc_enqueue_scripts' ) );

		add_filter( 'bkap_after_successful_manual_booking', array( $this, 'bkap_wcv_modify_manual_booking_redirect_url' ), 11, 2 );

		add_action( 'bkap_after_reminder_settings', array( &$this, 'bkap_after_reminder_settings' ) );
	}

	/**
	 * This function will prepare the redirect url for manually created booking.
	 *
	 * @param string $redirect_url Redirect URL.
	 * @param int    $order_id Order ID.
	 *
	 * @since 5.10.0
	 */
	public static function bkap_wcv_modify_manual_booking_redirect_url( $redirect_url, $order_id ) {

		global $wp;

		$base_url = isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ( '/' . $wp->request . '/' );
		if ( false !== strpos( $base_url, 'bkap-create-booking' ) || false !== strpos( $base_url, 'bkap-calendar' ) ) {
			$redirect_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'order' );
		}

		return $redirect_url;
	}

	/**
	 * Adding Styles and Scripts for WC-Vendors.
	 *
	 * @since 5.10.0
	 */
	public function bkap_wc_enqueue_scripts() {
		global $wp;

		$end_point = '';

		
		if ( isset( $wp->query_vars['custom'] ) ) {
			$end_point = $wp->query_vars['custom'];
		}

		if ( '' !== $end_point ) {
			BKAP_Vendors::bkap_vendor_load_scripts( $end_point );
			BKAP_Vendors::bkap_vendor_load_styles( $end_point );
		}
	}

	/**
	 * Hide Add Day button in Edit Booking Modal on View Booking for WC Vendors
	 *
	 * @param string $status Show add day button.
	 *
	 * @since 5.7.0
	 */
	public function bkap_show_add_day_button( $status ) {

		if ( isset( $_GET['custom'] ) && 'bkap-booking' === $_GET['custom'] ) {
			$status = false;
		}
		return $status;
	}

	/**
	 * Include files as needed
	 *
	 * @since 4.6.0
	 */
	function bkap_wcv_include_files() {
		// product page Booking tab file
		include_once 'product.php';
	}

	/**
	 * Add the Booking menu to the Vendor dashboard
	 *
	 * @param array $pages Current Pages displayed in the menu
	 * @return array
	 * @since 4.6.0
	 */
	function bkap_add_menu( $pages ) {

		$pages['bkap-booking'] = array(
			'label'   => __( 'Bookings', 'woocommerce-booking' ),
			'slug'    => 'bkap-booking/bkap-dashboard',
			'actions' => array(),
			'custom'  => 'bkap-booking',
		);

		return $pages;
	}

	/**
	 * Add the Booking menu to the Vendor dashboard
	 *
	 * @param array $pages Current Pages displayed in the menu
	 * @return array
	 */
	function bkap_modify_menu( $pages ) {

		$pages['bkap-booking'] = array(
			'label'   => __( 'Bookings', 'woocommerce-booking' ),
			'slug'    => 'bkap-booking?custom=bkap-booking',
			'id'      => 'bkap-booking',
			'actions' => array(),
			'custom'  => 'bkap-booking',
		);

		return $pages;
	}

	/**
	 * Add the templates
	 *
	 * @param array $menu Current Menu Array
	 *
	 * @return array Menu array modified to containg Booking Page details
	 * @since 4.6.0
	 */
	function bkap_booking_menu( $menu ) {
	
		$bkap_vendor           = 'wc-vendor';
		$bkap_vendors          = new BKAP_Vendors();
		$bkap_vendor_endpoints = $bkap_vendors->bkap_get_vendor_endpoints( $bkap_vendor );

		array_shift( $bkap_vendor_endpoints ); // removing dashboard endpoint.
		$bkap_vendor_endpoints_group = array_chunk( $bkap_vendor_endpoints, 2 );
		
		$menu['bkap-dashboard'] = array(
			'slug'          => 'bkap-dashboard',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-main-navigation',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array(
				'bkap_vendor_endpoints'       => $bkap_vendor_endpoints,
				'bkap_vendor_endpoints_group' => $bkap_vendor_endpoints_group,
				'bkap_vendor'                 => $bkap_vendor,
				'end_point'                   => 'bkap-dashboard',
			),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		$menu['bkap-list'] = array(
			'slug'          => 'bkap-list',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-feature-page',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array( 'end_point' => 'bkap-list', 'bkap_vendor' => $bkap_vendor ),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		$menu['bkap-create-booking'] = array(
			'slug'          => 'bkap-create-booking',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-feature-page',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array( 'end_point' => 'bkap-create-booking', 'bkap_vendor' => $bkap_vendor ),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		$menu['bkap-calendar'] = array(
			'slug'          => 'bkap-calendar',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-feature-page',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array( 'end_point' => 'bkap-calendar', 'bkap_vendor' => $bkap_vendor ),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		$menu['bkap-send-reminders'] = array(
			'slug'          => 'bkap-send-reminders',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-feature-page',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array( 'end_point' => 'bkap-send-reminders', 'bkap_vendor' => $bkap_vendor ),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		$menu['bkap-manage-resource'] = array(
			'slug'          => 'bkap-manage-resource',
			'label'         => __( 'Bookings', 'woocommerce-booking' ),
			'template_name' => 'bkap-wcv-feature-page',
			'base_dir'      => BKAP_BOOKINGS_TEMPLATE_PATH . 'vendors-integration/wc-vendors/',
			'args'          => array( 'end_point' => 'bkap-manage-resource', 'bkap_vendor' => $bkap_vendor ),
			'actions'       => array(),
			'parent'        => 'bkap-booking',
		);

		return $menu;
	}

	/**
	 * View Bookings Data Export
	 * Print & CSV
	 *
	 * @since 4.6.0
	 */
	function bkap_download_booking_files() {

		if ( isset( $_GET['custom'] ) && ( $_GET['custom'] == 'bkap-print' || $_GET['custom'] ) == 'bkap-csv' ) {

			$current_page = $_GET['custom'];

			$additional_args = array(
				'meta_key'   => '_bkap_vendor_id',
				'meta_value' => get_current_user_id(),
			);
			$data            = bkap_common::bkap_get_bookings( '', $additional_args );

			if ( isset( $current_page ) && $current_page === 'bkap-csv' ) {
				BKAP_Bookings_View::bkap_download_csv_file( $data );
			} elseif ( isset( $current_page ) && $current_page === 'bkap-print' ) {
				BKAP_Bookings_View::bkap_download_print_file( $data );
			}
		}
	}

	/**
	 * Loads modal template for editing the bookings for a Vendor from Vendor Dashboard
	 *
	 * @param int    $booking_id Booking Post ID
	 * @param Object $booking_post WP Post Booking
	 * @since 4.6.0
	 */

	public function bkap_wc_vendors_load_modals( $booking_id, $booking_post, $booking_details, $item_no ) {

		$product_id = $booking_post['product_id'];

		$variation_id = '';

		$page_type = 'view-order';

		$localized_array = array(
			'bkap_booking_params' => $booking_details,
			'bkap_cart_item'      => '',
			'bkap_cart_item_key'  => $booking_post['order_item_id'] . $item_no,
			'bkap_order_id'       => $booking_post['order_id'],
			'bkap_page_type'      => $page_type,
			'bkap_booking_id'     => $booking_id,
		);

		// Additional data for addons
		$additional_addon_data = '';// bkap_common::bkap_get_cart_item_addon_data( $cart_item );

		bkap_edit_bookings_class::bkap_load_template(
			$booking_details,
			wc_get_product( $product_id ),
			$product_id,
			$localized_array,
			$booking_post['order_item_id'] . $item_no,
			$variation_id, // $booking_post['variation_id'],
			$additional_addon_data
		);
	}

	/**
	 * Enable Global Params to be set for Modals to load on View Bookings
	 *
	 * @return bool True if WC Vendros Pro plugin is active and the User is on the View Bookings page on WC Vendor.
	 * @since 4.6.0. Updated 5.10.0
	 */
	public function bkap_wc_vendors_enable_modals() {

		global $wp;

		return ( class_exists( 'WCVendors_Pro_Dashboard' ) && isset( $wp->query_vars ) && isset( $wp->query_vars['object'] ) && isset( $wp->query_vars['custom'] ) && 'bkap-booking' === $wp->query_vars['object'] && 'bkap-list' === $wp->query_vars['custom'] );
	}

	/**
	 * Remove the booking menu from the vendor admin dashboard
	 *
	 * @since 4.6.0
	 */
	function bkap_remove_menus() {

		if ( current_user_can( 'vendor' ) && ! is_multisite() ) {
			remove_menu_page( 'edit.php?post_type=bkap_booking' );
		}
	}

	/**
	 * Adding script on the reminders page.
	 *
	 * @since 5.14.0
	 */
	function bkap_after_reminder_settings() {

		if ( ! is_admin() ) {

			$ajax_url = get_admin_url() . 'admin-ajax.php';

			wp_register_script(
				'bkap-wcv',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/vendors/wc-vendors/product.js', BKAP_FILE ),
				'',
				BKAP_VERSION,
				true
			);

			wp_localize_script(
				'bkap-wcv',
				'bkap_wcv_params',
				array(
					'ajax_url' => $ajax_url,
					'post_id'  => 0,
				)
			);

			wp_enqueue_script( 'bkap-wcv' );

			wp_enqueue_style(
				'bkap-wcv-products',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/vendors/wc-vendors/bkap-wcv-products.css', BKAP_FILE ),
				'',
				BKAP_VERSION,
				false
			);
		}
	}
} // end of class

$bkap_wc_vendors = new bkap_wc_vendors();

