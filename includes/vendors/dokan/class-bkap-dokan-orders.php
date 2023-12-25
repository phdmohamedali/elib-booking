<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/Dokan
 * @version  4.8.0
 * @category Classes
 */

if ( ! class_exists( 'bkap_dokan_orders_class' ) ) {

	/**
	 * Class for Integrating Orders with Dokan
	 */
	class bkap_dokan_orders_class {

		/**
		 * Constructor
		 *
		 * @since 4.6.0
		 */
		function __construct() {

			add_action( 'bkap_dokan_booking_content_before', array( &$this, 'bkap_dokan_include_booking_styles' ) );

			add_filter( 'bkap_dokan_booking_status', array( &$this, 'bkap_show_booking_status' ), 10, 1 );

			add_action( 'bkap_dokan_booking_list', array( &$this, 'bkap_load_view_template' ), 10, 4 );

			add_action( 'wp', array( &$this, 'bkap_download_booking_files' ) );

			add_action( 'wp_ajax_nopriv_bkap_dokan_change_status', array( &$this, 'bkap_dokan_change_status' ) );
			add_action( 'wp_ajax_bkap_dokan_change_status', array( &$this, 'bkap_dokan_change_status' ) );

			add_filter( 'bkap_display_multiple_modals', array( &$this, 'bkap_dokan_load_modals' ) );

			add_action( 'dokan_dashboard_content_before', array( &$this, 'bkap_load_order_scripts' ) );

			add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( &$this, 'bkap_dokan_woocommerce_order_item_get_formatted_meta_data' ), 10, 2 );
			
			add_filter( 'bkap_show_add_day_button', array( &$this, 'bkap_show_add_day_button' ), 10, 1 );

		}
		
		/**
    	 * Hide Add Day button in Edit Booking Modal on View Booking for Dokan
    	 *
    	 * @param string $status Show add day button.
    	 *
    	 * @since 5.7.0
    	 */
    	public function bkap_show_add_day_button( $status ) {
    
    		if ( strpos( get_page_link(), 'dokan-dashboard') !== false ) {
    			$status = false;
    		}
    		return $status;
    	}

		/**
		 * This function will not show hidden booking data on dokan order details page.
		 *
		 * @param array  $formatted_meta Array of item meta to be displayed.
		 * @param object $order Order Object.
		 *
		 * @hook woocommerce_order_item_get_formatted_meta_data
		 * @since 4.19.0
		 */

		public function bkap_dokan_woocommerce_order_item_get_formatted_meta_data( $formatted_meta, $order ) {

			$hidden_booking_field = array(
				'_wapbk_checkout_date',
				'_wapbk_booking_date',
				'_wapbk_time_slot',
				'_wapbk_booking_status',
				'_gcal_event_reference',
				'_wapbk_wpa_prices',
				'_resource_id',
				'_wapbk_timezone',
				'_wapbk_timeoffset',
			);

			foreach ( $formatted_meta as $key => $value ) {
				if ( in_array( $value->key, $hidden_booking_field ) ) {
					unset( $formatted_meta[ $key ] );
				}
			}

			return $formatted_meta;
		}

		/**
		 * Load common styles before rendering templates
		 *
		 * @since 4.6.0
		 */
		public function bkap_dokan_include_booking_styles() {

			$plugin_version = get_option( 'woocommerce_booking_db_version' );
			bkap_load_scripts_class::bkap_load_dokan_booking_styles( $plugin_version );
		}

		/**
		 * Show Booking status as an icon on vendor dashboard
		 *
		 * @param string $status Status string
		 * @return string HTML formatted string to be displayed in status column
		 *
		 * @since 4.6.0
		 */
		public function bkap_show_booking_status( $status ) {

			$booking_statuses = bkap_common::get_bkap_booking_statuses();
			$status_label     = ( array_key_exists( $status, $booking_statuses ) ) ? $booking_statuses[ $status ] : ucwords( $status );
			return '<span class="bkap_dokan_status status-' . esc_attr( $status ) . ' tips" data-toggle="tooltip" data-placement="top" title="' . esc_attr( $status_label ) . '">' . esc_html( $status_label ) . '</span>';
		}

		/**
		 * Download CSV and Print files
		 *
		 * @since 4.6.0
		 */
		public function bkap_download_booking_files() {

			if ( isset( $_GET['view'] ) && ( $_GET['view'] == 'bkap-print' || $_GET['view'] ) == 'bkap-csv' ) {
				
				$current_page    = $_GET['view'];
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
		 * Load Booking Template for Editing Bookings for Vendor
		 *
		 * @param string|int $booking_id Booking ID
		 * @param array      $booking_post Post data containing Booking Information
		 *
		 * @since 4.6.0
		 */
		public static function bkap_load_view_template( $booking_id, $booking_post, $booking_details, $item_no ) {

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

			$pro_obj = wc_get_product( $product_id );

			if ( $pro_obj != false ) {
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

			wp_register_script(
				'bkap-dokan-reschedule-booking',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/vendors/dokan/bkap-dokan-reschedule-booking.js', BKAP_FILE ),
				'',
				'',
				true
			);

			wp_enqueue_script( 'bkap-dokan-reschedule-booking' );
		}

		/**
		 * Change Booking Status from View Bookings for Vendor Dashboard
		 *
		 * @since 4.6.0
		 */
		public function bkap_dokan_change_status() {

			$item_id     = $_POST['item_id'];
			$booking_ids = bkap_common::get_booking_id( $item_id );
			$status      = $_POST['status'];

			if ( is_array( $booking_ids ) ) {
				foreach ( $booking_ids as $booking_id ) {
					bkap_booking_confirmation::bkap_save_booking_status( $item_id, $status, $booking_id );
				}
			} else {
				bkap_booking_confirmation::bkap_save_booking_status( $item_id, $status, $booking_ids );
			}
			die();
		}

		/**
		 * Enable Global Params to be set for Modals to load on View Bookings
		 *
		 * @param bool $display Status indicating presence of multiple products for booking
		 * @return bool True if multiple products present
		 * @since 4.6.0
		 */
		public function bkap_dokan_load_modals( $display ) {

			global $wp;
			if ( function_exists( 'dokan_is_seller_dashboard' ) && dokan_is_seller_dashboard() ) {
				if ( isset( $wp->query_vars['bkap-create-booking'] ) ) {
					return $display;
				} else {
					return $display = true;
				}
			} else {
				return $display;
			}
		}

		/**
		 * Load CSS file for Edit Orders Page
		 *
		 * @since 4.6.0
		 */
		public function bkap_load_order_scripts() {

			if ( dokan_is_seller_dashboard() && isset( $_GET['order_id'] ) ) {
				wp_enqueue_style(
					'bkap_dokan_orders',
					bkap_load_scripts_class::bkap_asset_url( '/assets/css/vendors/dokan/bkap-dokan-view-order.css', BKAP_FILE ),
					'',
					'',
					false
				);
			}
		}
	}
}

return new bkap_dokan_orders_class();
