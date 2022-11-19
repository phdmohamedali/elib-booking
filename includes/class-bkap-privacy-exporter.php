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

if ( ! class_exists( 'bkap_Personal_Data_Export' ) ) {

	/**
	 * Export Booking data in
	 * Dashboard->Tools->Export Personal Data
	 */
	class Bkap_Personal_Data_Export {

		/**
		 * Construct
		 *
		 * @since 4.9.0
		 */
		public function __construct() {
			// Hook into the WP export process
			add_filter( 'wp_privacy_personal_data_exporters', array( &$this, 'bkap_exporter_array' ), 6 );
			add_filter( 'wp_privacy_personal_data_exporters', array( &$this, 'bkap_gcal_exporter_array' ), 6 );
		}

		/**
		 * Add our export and it's callback function
		 *
		 * @param array $exporters - Any exportes that need to be added by 3rd party plugins
		 * @param array $exporters - Exportes list containing our plugin details
		 *
		 * @since 4.9.0
		 */
		public static function bkap_exporter_array( $exporters = array() ) {

			$exporter_list = array();
			// Add our export and it's callback function
			$exporter_list['bkap_booking'] = array(
				'exporter_friendly_name' => __( 'Bookings', 'woocommerce-booking' ),
				'callback'               => array( 'Bkap_Personal_Data_Export', 'bkap_data_exporter' ),
			);

			$exporters = array_merge( $exporters, $exporter_list );

			return $exporters;
		}

		/**
		 * Add our export and it's callback function
		 *
		 * @param array $exporters - Any exportes that need to be added by 3rd party plugins
		 * @param array $exporters - Exportes list containing our plugin details
		 *
		 * @since 4.9.0
		 */

		public static function bkap_gcal_exporter_array( $exporters = array() ) {

			$exporter_list = array();
			// Add our export and it's callback function
			$exporter_list['bkap_gcal_event'] = array(
				'exporter_friendly_name' => __( 'Google Event', 'woocommerce-booking' ),
				'callback'               => array( 'Bkap_Personal_Data_Export', 'bkap_gcal_data_exporter' ),
			);

			$exporters = array_merge( $exporters, $exporter_list );

			return $exporters;

		}

		/**
		 * Returns data to be displayed for exporting the booking details
		 *
		 * @param string  $email_address - EMail Address for which personal data is being exported
		 * @param integer $page - The Export page number
		 * @return array $data_to_export - Data to be exported
		 *
		 * @hook wp_privacy_personal_data_exporters
		 * @global $wpdb
		 * @since 4.9.0
		 */
		static function bkap_data_exporter( $email_address, $page ) {

			global $wpdb;

			$done           = false;
			$page           = (int) $page;
			$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
			$data_to_export = array();
			$order_query    = array(
				'limit'    => 10,
				'page'     => $page,
				'customer' => array( $email_address ),
			);

			if ( $user instanceof WP_User ) {
				$order_query['customer'][] = (int) $user->ID;
			}

			$orders = wc_get_orders( $order_query );

			$booking_ids = array();

			if ( count( $orders ) > 0 ) {
				foreach ( $orders as $key => $value ) {

					$booking_id = bkap_common::get_booking_ids_from_order_id( $value->get_id() );

					foreach ( $booking_id as $key => $value ) {
						$booking_ids[] = $value;
					}
				}
			}

			if ( 0 < count( $booking_ids ) ) {
				foreach ( $booking_ids as $booking_id ) {

					$data_to_export[] = array(
						'group_id'    => 'bkap_booking',
						'group_label' => __( 'Bookings', 'woocommerce-booking' ),
						'item_id'     => 'booking-' . $booking_id,
						'data'        => self::get_booking_data( $booking_id ),
					);
				}
				$done = 10 > count( $booking_ids );
			} else {
				$done = true;
			}

			return array(
				'data' => $data_to_export,
				'done' => $done,
			);

		}

		/**
		 * Returns data to be displayed for exporting the google event details
		 *
		 * @param string  $email_address - EMail Address for which personal data is being exported
		 * @param integer $page - The Export page number
		 * @return array $data_to_export - Data to be exported
		 *
		 * @hook wp_privacy_personal_data_exporters
		 * @global $wpdb
		 * @since 4.9.0
		 */

		static function bkap_gcal_data_exporter( $email_address, $page ) {

			global $wpdb;

			$done           = false;
			$page           = (int) $page;
			$user           = get_user_by( 'email', $email_address ); // Check if user has an ID in the DB to load stored personal data.
			$data_to_export = array();

			$booking_query = 'SELECT post_id FROM `' . $wpdb->prefix . "postmeta` WHERE meta_key = '_bkap_description' AND meta_value LIKE '%" . $email_address . "%'";
			$query_results = $wpdb->get_results( $booking_query );

			$google_ids = array();

			if ( count( $query_results ) > 0 ) {
				foreach ( $query_results as $key => $value ) {
					$google_ids[] = $value->post_id;
				}
			}

			if ( 0 < count( $google_ids ) ) {
				foreach ( $google_ids as $google_id ) {

					$data_to_export[] = array(
						'group_id'    => 'bkap_gcal_event',
						'group_label' => __( 'Google Event', 'woocommerce-booking' ),
						'item_id'     => 'gcal-' . $google_id,
						'data'        => self::get_gcal_event_data( $google_id ),
					);
				}
				$done = 10 > count( $google_ids );
			} else {
				$done = true;
			}

			return array(
				'data' => $data_to_export,
				'done' => $done,
			);

		}

		/**
		 * Returns the personal data for each booking
		 *
		 * @param integer $booking_id - Booking Post ID
		 * @return array $personal_data - Personal data to be displayed
		 * @global $wpdb
		 * @since 4.9.0
		 */
		static function get_booking_data( $booking_id ) {
			$personal_data = array();

			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );

			global $wpdb;

			$booking_details_to_export = apply_filters(
				'bkap_personal_booking_details_prop',
				array(
					'booking_id'      => __( 'Booking ID', 'woocommerce-booking' ),
					'order_id'        => __( 'Order ID', 'woocommerce-booking' ),
					'item_booked'     => __( 'Item Booked', 'woocommerce-booking' ),
					'booking_details' => __( 'Booking Details', 'woocommerce-booking' ),
					'booking_created' => __( 'Booking Created', 'woocommerce-booking' ),
					'total'           => __( 'Total', 'woocommerce-booking' ),
				),
				$booking_id
			);

			$booking = new BKAP_Booking( $booking_id );

			foreach ( $booking_details_to_export as $prop => $name ) {

				switch ( $prop ) {
					case 'booking_id':
						$value = $booking_id;
						break;
					case 'order_id':
						$order = $booking->get_order();

						if ( $order ) {
							$value = $order->get_order_number();

						} else {
							$value = __( '[Deleted]', 'woocommerce-booking' );
						}
						break;
					case 'item_booked':
						$product = $booking->get_product();

						if ( $product ) {
							$value = $product->get_title();
						} else {
							$value = '';
						}

						break;
					case 'booking_details':
						$start = $booking->get_start_date() . '<br>' . $booking->get_start_time();
						$start = date( $date_format, $booking->start );
						$end   = date( $date_format, $booking->end );
						$value = $start . '<br>' . $end;

						break;
					case 'booking_created':
						$value = date( $date_format . ' ' . $time_format, strtotime( $booking->booking_date ) );

						break;
					case 'total':
						$amount    = $booking->get_cost();
						$final_amt = $amount * $booking->get_quantity();
						$order_id  = $booking->get_order_id();

						if ( absint( $order_id ) > 0 ) {
							$the_order = wc_get_order( $order_id );
							if ( $the_order ) {
								$currency  = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $the_order->get_order_currency() : $the_order->get_currency();
							}
						} else {
							// get default woocommerce currency
							$currency = get_woocommerce_currency();
						}
						$currency_symbol = get_woocommerce_currency_symbol( $currency );
						$value           = wc_price( $final_amt, array( 'currency' => $currency ) );

						break;
					default:
						$value = ( isset( $booking->$prop ) ) ? $booking->$prop : '';
						break;
				}

				$value = apply_filters( 'bkap_personal_booking_details_prop_value', $value, $prop, $booking );

				$personal_data[] = array(
					'name'  => $name,
					'value' => $value,
				);

			}
			$personal_data = apply_filters( 'bkap_personal_data_bookings_export', $personal_data, $booking );

			return $personal_data;
		}

		/**
		 * Returns the personal data for each google event
		 *
		 * @param integer $google_id - Google Post ID
		 * @return array $personal_data - Personal data to be displayed
		 * @global $wpdb
		 * @since 4.9.0
		 */

		static function get_gcal_event_data( $google_id ) {
			$personal_data = array();

			$date_format = get_option( 'date_format' );
			$time_format = get_option( 'time_format' );

			global $wpdb;

			$google_details_to_export = apply_filters(
				'bkap_personal_export_google_events_prop',
				array(
					'gcal_id'          => __( 'Google Event ID', 'woocommerce-booking' ),
					'gcal_status'      => __( 'Event Status', 'woocommerce-booking' ),
					'order_id'         => __( 'Order ID', 'woocommerce-booking' ),
					'booking_details'  => __( 'Event Date Details', 'woocommerce-booking' ),
					'gcal_created'     => __( 'Google Event Post Created', 'woocommerce-booking' ),
					'gcal_uid'         => __( 'Google Event UID', 'woocommerce-booking' ),
					'gcal_summary'     => __( 'Google Event Summary', 'woocommerce-booking' ),
					'gcal_description' => __( 'Google Event Description', 'woocommerce-booking' ),
					'gcal_location'    => __( 'Google Event Location', 'woocommerce-booking' ),
				),
				$google_id
			);

			$google = new BKAP_Gcal_Event( $google_id );

			foreach ( $google_details_to_export as $prop => $name ) {

				switch ( $prop ) {
					case 'gcal_id':
						$value = $google_id;
						break;
					case 'gcal_status':
						$status = $google->get_status();

						if ( $status == 'bkap-mapped' ) {

							$mapped_with = __( 'Mapped with ', 'woocommerce-booking' );
							$product_id  = get_post_meta( $google_id, '_bkap_product_id', true );
							$pro_title   = '';

							if ( $product_id != '' ) {
								$_product = wc_get_product( $product_id );

								if ( $_product ) {
									$pro_title = $product->get_title();
								}

								$value = $mapped_with . $pro_title;
							} else {
								$value = __( 'Mapped Event', 'woocommerce-booking' );
							}
						} else {
							$value = __( 'Unmapped Event', 'woocommerce-booking' );
						}

						break;
					case 'order_id':
						$status = $google->get_status();

						if ( $status == 'bkap-mapped' ) {
							$value = wp_get_post_parent_id( $google_id );
						} else {
							$value = '-';
						}

						break;
					case 'booking_details':
						// $value = date( $date_format." ".$time_format , strtotime( $booking->booking_date ) );

						$start = $google->get_start_date();
						$end   = $google->get_end_date();
						$time  = '';

						if ( $start == $end || $end == '' ) {
							$start_time = $google->get_start_time();
							$end_time   = $google->get_end_time();

							if ( $end_time == '' ) {
								$time = $start_time;
							} else {
								$time = $start_time . '-' . $end_time;
							}
						}

						$value = $start . ' - ' . $end . '<br>' . $time;

						break;
					case 'gcal_created':
						$value = get_the_date( $date_format . ' ' . $time_format, $google_id );

						break;
					case 'gcal_uid':
						$value = $google->uid;

						break;
					case 'gcal_summary':
						$value = $google->summary;

						break;
					case 'gcal_description':
						$value = $google->description;

						break;
					case 'gcal_location':
						$value = $google->location;

						break;
					default:
						$value = ( isset( $google->$prop ) ) ? $google->$prop : '';
						break;
				}

				$value = apply_filters( 'bkap_personal_export_google_events_prop_value', $value, $prop, $google );

				$personal_data[] = array(
					'name'  => $name,
					'value' => $value,
				);

			}
			$personal_data = apply_filters( 'bkap_personal_data_google_events_export', $personal_data, $google );

			return $personal_data;
		}
	} // end of class
	$Bkap_Personal_Data_Export = new Bkap_Personal_Data_Export();
} // end if

