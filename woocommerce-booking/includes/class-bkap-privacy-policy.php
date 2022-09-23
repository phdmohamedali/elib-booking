<?php
/**
 * Export Booking data in
 * Dashboard->Tools->Export Personal Data
 *
 * @since 4.9.0
 */
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_Privacy_Policy' ) ) {

	/**
	 * Export Booking data in
	 * Dashboard->Tools->Export Personal Data
	 */
	class Bkap_Privacy_Policy {

		/**
		 * Construct
		 *
		 * @since 4.9.0
		 */
		public function __construct() {

			add_filter( 'woocommerce_privacy_export_order_personal_data_props', array( &$this, 'bkap_privacy_export_order_personal_data_props' ), 10, 2 );
			add_filter( 'woocommerce_privacy_export_order_personal_data_prop', array( &$this, 'bkap_privacy_export_order_personal_data_prop_callback' ), 10, 3 );
			add_action( 'wp_ajax_bkap_dismiss_admin_notices', array( &$this, 'bkap_dismiss_admin_notices' ) );
			add_filter( 'woocommerce_account_settings', array( &$this, 'bkap_woocommerce_account_settings' ), 10, 1 );

		}

		/**
		 * This function will append the information to be shown in the Personal data retention section.
		 *
		 * @param Array $setting Array of all the settings added in the Personal data retention section
		 * @since 4.10.0
		 *
		 * @hook woocommerce_account_settings
		 */

		public function bkap_woocommerce_account_settings( $settings ) {

			foreach ( $settings as $key => $setting ) {

				if ( 'personal_data_retention' == $setting['id'] && 'title' == $setting['type'] ) {
					$desc = $setting['desc'];

					$bkap_desc = __( "Also please ensure that 'Retain Pending Orders' is set to a value such that no booking orders awaiting confirmation are deleted before the payment is completed by the customer.", 'woocommerce-booking' );

					$settings[ $key ]['desc'] = $desc . ' ' . $bkap_desc;
				}
			}

			return $settings;
		}

		/**
		 * Showing Privacy Policy notice on admin end on 0-15-45 days intervals
		 *
		 * @since 4.9.0
		 *
		 * @hook admin_notices
		 */

		public function bkap_dismiss_admin_notices() {

			if ( isset( $_POST['notice'] ) ) {
				switch ( $_POST['notice'] ) {
					case 'bkap-timeslot-notice':
						set_transient( 'bkap_timeslot_notice', '-1' );
						break;
					case 'bkap-meeting-notice':
						delete_option( 'bkap_assign_meeting_scheduled' );
						break;
					default:
						break;
				}
			}
		}


		/**
		 * Adding Booking Details lable to personal data exporter order table
		 *
		 * @param array  $props_to_export array of the order property being exported
		 * @param object $order WooCommerce Order Post
		 *
		 * @since 4.9.0
		 *
		 * @hook woocommerce_privacy_export_order_personal_data_props
		 */


		public static function bkap_privacy_export_order_personal_data_props( $props_to_export, $order ) {

			$my_key_value = array( 'items_booking' => __( 'Items Booking Details', 'woocommerce-booking' ) );
			$key_pos      = array_search( 'items', array_keys( $props_to_export ) );

			if ( $key_pos !== false ) {
				$key_pos++;

				$second_array    = array_splice( $props_to_export, $key_pos );
				$props_to_export = array_merge( $props_to_export, $my_key_value, $second_array );
			}
			return $props_to_export;
		}

		/**
		 * Adding Booking Details value to personal data exporter order table
		 *
		 * @param string  $value
		 * @param stringn $prop key of the exported data
		 * @param object  $order WooCommerce Order Post
		 *
		 * @since 4.9.0
		 *
		 * @hook woocommerce_privacy_export_order_personal_data_props
		 */

		public static function bkap_privacy_export_order_personal_data_prop_callback( $value, $prop, $order ) {

			if ( $prop == 'items_booking' ) {

				$date_format = get_option( 'date_format' );
				$item_names  = array();

				foreach ( $order->get_items() as $item => $item_value ) {

					$product_id  = $item_value['product_id'];
					$is_bookable = bkap_common::bkap_get_bookable_status( $product_id );

					if ( $is_bookable ) {

						$value_string = $item_value->get_name() . ' x ' . $item_value->get_quantity();
						$item_meta    = $item_value->get_meta_data();

						$booking_time          = '';
						$booking_date_form     = '';
						$booking_end_date_form = '';

						foreach ( $item_meta as $meta_data ) {

							if ( '_wapbk_booking_date' == $meta_data->key ) {
								$booking_date      = $meta_data->value;
								$booking_date_form = date( $date_format, strtotime( $booking_date ) );
							}

							if ( '_wapbk_checkout_date' == $meta_data->key ) {
								$booking_end_date      = $meta_data->value;
								$booking_end_date_form = date( $date_format, strtotime( $booking_end_date ) );
							}

							if ( '_wapbk_time_slot' == $meta_data->key ) {
								$booking_time = $meta_data->value;
							}
						}

						$booking_details = $booking_date_form . ' ' . $booking_end_date_form . ' ' . $booking_time;

						$value_string .= ' -- ' . $booking_details;
						$item_names[]  = $value_string;
					}
				}
				$value = implode( ', ', $item_names );
			}
			return $value;
		}
	} // end of class
	$Bkap_Privacy_Policy = new Bkap_Privacy_Policy();
} // end if

