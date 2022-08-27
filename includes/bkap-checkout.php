<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for operations related to Checkout process
 *
 * @author   Tyche Softwares
 * @package  BKAP/Checkout-Process
 * @category Classes
 */

require_once 'bkap-common.php';

if ( ! class_exists( 'bkap_checkout' ) ) {

	/**
	 * Class for all the operations related to order completion process
	 *
	 * @since 1.7.0
	 */
	class bkap_checkout {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */
		public function __construct() {

			add_action( 'woocommerce_checkout_update_order_meta', array( &$this, 'bkap_order_item_meta' ), 10, 2 );

			// Hide the hardcoded item meta records frm being displayed on the admin orders page.
			add_filter( 'woocommerce_hidden_order_itemmeta', array( &$this, 'bkap_hidden_order_itemmeta' ), 10, 1 );

			add_action( 'woocommerce_resume_order', array( &$this, 'bkap_woocommerce_resume_order' ), 10, 1 );
			add_action( 'woocommerce_checkout_order_processed', array( &$this, 'bkap_woocommerce_new_order' ), 10, 3 );
			// Action scheduler for the Global Time Slots Booking.
			add_action( 'bkap_update_global_lockout_schedule', array( &$this, 'bkap_update_global_lockout_schedule_callback' ), 10, 1 );
		}

		/**
		 * Removing entry from order hitory table and trashing booking when order is failed and resuming purchase from checkout page.
		 * issue #3751
		 *
		 * @param int $order_id Order ID.
		 * @since 5.0.2
		 *
		 * @hook woocommerce_resume_order
		 */
		public static function bkap_woocommerce_resume_order( $order_id ) {

			global $wpdb;

			$order = wc_get_order( $order_id );

			if ( in_array( $order->get_status(), array( 'failed', 'pending' ), true ) ) {
				$order       = wc_get_order( $order_id );
				$item_values = $order->get_items();

				foreach ( $item_values as $item_id => $item_value ) {
					$booking_id = bkap_common::get_booking_id( $item_id ); // get the booking ID for each item.
					if ( $booking_id ) {
						wp_trash_post( $booking_id );
						wp_delete_post( $booking_id, true );
					}
				}
			}
		}

		/**
		 * Cancelling the booking data and its lockout on multiple failed payments. No order changed hooke was being fire upon multiple failed payment.
		 * The woocommerce_order_status_failed hook was being fired only for first failed occurance. This function will be used for rest of the failed attemp.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $posted_data Posted Data.
		 * @param obj   $order Order Object.
		 * @since 5.0.2
		 *
		 * @hook woocommerce_checkout_order_processed
		 */
		public static function bkap_woocommerce_new_order( $order_id, $posted_data, $order ) {
			$order_status = $order->get_status();
			if ( in_array( $order_status, array( 'failed' ), true ) ) {
				bkap_cancel_order::bkap_woocommerce_cancel_order( $order_id );
			}
		}

		/**
		 * Hide the hardcoded item meta records frm being displayed on the admin orders page
		 *
		 * @param array $arr array containing meta fields that are hidden on Admin Order Page.
		 *
		 * @return array Hidden Fields Array modified
		 *
		 * @since 1.7.0
		 * @since 4.5.0 Added field for WooCommerce Product Addons
		 * @since 4.7.0 Added field for Resources
		 *
		 * @hook woocommerce_hidden_order_itemmeta
		 */
		public static function bkap_hidden_order_itemmeta( $arr ) {

			$bkap_order_items = array(
				'_wapbk_checkout_date',
				'_wapbk_booking_date',
				'_wapbk_time_slot',
				'_wapbk_booking_status',
				'_gcal_event_reference',
				'_wapbk_wpa_prices', // This item meta is used for calculating addon prices while rescheduling.
				'_resource_id',
				'_person_ids',
				'_wapbk_timezone',
				'_wapbk_timeoffset',
				'_wapbk_order_note_id'
			);

			foreach ( $bkap_order_items as $bkap_order_item ) {
				$arr[] = $bkap_order_item;
			}

			return apply_filters( 'bkap_hidden_order_itemmeta', $arr );
		}

		/**
		 * Updated the availability/bookings left for a product when an order is placed.
		 *
		 * @param int    $order_id Order ID.
		 * @param int    $post_id Product ID.
		 * @param int    $parent_id Parent Product ID in case of Grouped Products.
		 * @param int    $quantity Quantity.
		 * @param array  $booking_data Booking data array.
		 * @param string $called_from Called from front order admin.
		 * @return array An array containing the list of products IDs for whom availability was updated.
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 1.7.0
		 */
		public static function bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking_data, $called_from = '' ) {

			global $wpdb;

			$details = array();

			if ( isset( $booking_data['hidden_date'] ) && '' !== $booking_data['hidden_date'] ) {

				$hidden_date      = $booking_data['hidden_date'];
				$date_query       = bkap_date_as_format( $hidden_date, 'Y-m-d' );
				$booking_settings = bkap_setting( $post_id );
				$booking_type     = get_post_meta( $post_id, '_bkap_booking_type', true );
				$global_settings  = bkap_global_setting();

				if ( 'multiple_days' === $booking_type ) {
					if ( isset( $booking_data['hidden_date_checkout'] ) ) {
						$date_checkout       = $booking_data['hidden_date_checkout'];
						$date_checkout_query = bkap_date_as_format( $date_checkout, 'Y-m-d' );
					}

					for ( $i = 0; $i < $quantity; $i++ ) {
						$new_booking_id = bkap_insert_record_booking_history( $post_id, '', $date_query, $date_checkout_query, '', '' );
						if ( isset( $parent_id ) && '' != $parent_id ) { // Insert records for parent products - Grouped Products.
							bkap_insert_record_booking_history( $parent_id, '', $date_query, $date_checkout_query, '', '' );
						}
					}
					if ( 'admin' !== $called_from ) {
						self::bkap_update_booking_order_history( $order_id, $new_booking_id );
					} else {
						self::bkap_update_booking_order_history( $order_id, $new_booking_id, 'update' );
					}
				} elseif ( isset( $booking_data['time_slot'] ) && '' !== $booking_data['time_slot'] ) {

					$timezone_check = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.
					$time_select    = $booking_data['time_slot'];
					$time_exploded  = explode( '-', $time_select );

					if ( $timezone_check ) {
						$site_timezone = bkap_booking_get_timezone_string();
						if ( isset( $booking_data['timezone_name'] ) && '' !== $booking_data['timezone_name'] ) { // My-Account.
							$offset            = bkap_get_offset( $booking_data['timezone_offset'] );
							$customer_timezone = $booking_data['timezone_name'];
						} else {
							$offset            = bkap_get_offset( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' ) ); // Front end.
							$customer_timezone = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );
						}

						$query_from_time = bkap_convert_date_from_timezone_to_timezone( $hidden_date . ' ' . $time_exploded[0], $customer_timezone, $site_timezone, 'H:i' );
						$date_query      = bkap_convert_date_from_timezone_to_timezone( $hidden_date . ' ' . $time_exploded[0], $customer_timezone, $site_timezone, 'Y-m-d' );

					} else {
						$query_from_time = date( 'H:i', strtotime( $time_exploded[0] ) );
					}
					$from_hi = date( 'H:i', strtotime( $query_from_time ) );

					$query_to_time = '';
					if ( isset( $time_exploded[1] ) ) {
						if ( $timezone_check ) {
							$query_to_time = bkap_convert_date_from_timezone_to_timezone( $hidden_date . ' ' . $time_exploded[1], $customer_timezone, $site_timezone, 'H:i' );

						} else {
							$query_to_time = date( 'H:i', strtotime( $time_exploded[1] ) );
						}
						$to_hi = date( 'H:i', strtotime( $query_to_time ) );
					}

					if ( $query_to_time != '' ) {

						$query              = "SELECT from_time, to_time  FROM `" . $wpdb->prefix . "booking_history`
											WHERE post_id = '" . $post_id . "' AND
											start_date = '" . $date_query . "' AND
											status !=  'inactive' ";
						$get_all_time_slots = $wpdb->get_results( $query );

						// this is possible when we are trying to create an order while importing events by GCal.
						if ( ! isset( $get_all_time_slots ) || ( isset( $get_all_time_slots ) && count( $get_all_time_slots ) == 0 ) ) {

							$weekday = bkap_weekday_string( $date_query );

							$base_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
										WHERE post_id = %d
										AND weekday = %s
										AND start_date = '0000-00-00'
										AND status != 'inactive'";

							$get_base = $wpdb->get_results( $wpdb->prepare( $base_query, $post_id, $weekday ) );

							foreach ( $get_base as $key => $value ) {

								bkap_insert_record_booking_history( $post_id, $weekday, $date_query, '0000-00-00', $value->from_time, $value->to_time, $value->total_booking, $value->available_booking );

								if ( isset( $parent_id ) && $parent_id != '' ) {
									bkap_insert_record_booking_history( $parent_id, $weekday, $date_query, '0000-00-00', $value->from_time, $value->to_time, $value->total_booking, $value->available_booking );
								}
							}
						}

						if ( isset( $global_settings->booking_overlapping_timeslot ) && 'on' === $global_settings->booking_overlapping_timeslot ) {
							// Here we are updating all the overlapping timeslot of the same product.
							foreach ( $get_all_time_slots as $time_slot_key => $time_slot_value ) {

								$query_from_time_time_stamp      = strtotime( $query_from_time );
								$query_to_time_time_stamp        = strtotime( $query_to_time );
								$time_slot_value_from_time_stamp = strtotime( $time_slot_value->from_time );
								$time_slot_value_to_time_stamp   = strtotime( $time_slot_value->to_time );

								if ( $query_to_time_time_stamp > $time_slot_value_from_time_stamp && $query_from_time_time_stamp < $time_slot_value_to_time_stamp ) {

									if ( $time_slot_value_from_time_stamp != $query_from_time_time_stamp || $time_slot_value_to_time_stamp != $query_to_time_time_stamp ) {
										$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
															SET available_booking = available_booking - ' . $quantity . "
															WHERE post_id = '" . $post_id . "' AND
															start_date = '" . $date_query . "' AND
															from_time = '" . $time_slot_value->from_time . "' AND
															to_time = '" . $time_slot_value->to_time . "' AND
															status != 'inactive' AND
															total_booking > 0";
										$wpdb->query( $query );
									}
								}
							}
						}

						$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
											SET available_booking = available_booking - ' . $quantity . "
											WHERE post_id = '" . $post_id . "' AND
											start_date = '" . $date_query . "' AND
											TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "' AND
											TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_hi . "' AND
											status != 'inactive' AND
											total_booking > 0";
						$wpdb->query( $query );

						// Update records for parent products - Grouped Products.
						if ( isset( $parent_id ) && $parent_id != '' ) {

							$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
									SET available_booking = available_booking - ' . $quantity . "
									WHERE post_id = '" . $parent_id . "' AND
									start_date = '" . $date_query . "' AND
									from_time = '" . $query_from_time . "' AND
									to_time = '" . $query_to_time . "' AND
									status != 'inactive' AND
									total_booking > 0";
							$wpdb->query( $query );
						}

						$select         = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
													WHERE post_id = %d AND
													start_date = %s AND
													TIME_FORMAT( from_time, '%H:%i' ) = %s AND
													TIME_FORMAT( to_time, '%H:%i' ) = %s AND
													status != 'inactive' ";
						$select_results = $wpdb->get_results( $wpdb->prepare( $select, $post_id, $date_query, $from_hi, $to_hi ) );

						foreach ( $select_results as $k => $v ) {
							$details[ $post_id ] = $v;
						}
					} else {

						$query   = 'UPDATE `' . $wpdb->prefix . 'booking_history`
								SET available_booking = available_booking - ' . $quantity . "
								WHERE post_id = '" . $post_id . "' AND
								start_date = '" . $date_query . "' AND
								TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "' AND
								status != 'inactive' AND
								total_booking > 0";
						$updated = $wpdb->query( $query );

						if ( 0 == $updated ) {
								$weekday = bkap_weekday_string( $date_query );

								$base_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
									   WHERE post_id = %d
									   AND weekday = %s
									   AND start_date = '0000-00-00'
									   AND status != 'inactive'
									   AND total_booking > 0";

								$get_base = $wpdb->get_results( $wpdb->prepare( $base_query, $post_id, $weekday ) );

							foreach ( $get_base as $key => $value ) {
								bkap_insert_record_booking_history( $post_id, $weekday, $date_query, '0000-00-00', $value->from_time, '', $value->total_booking, $value->available_booking );

								if ( isset( $parent_id ) && $parent_id != '' ) {
											bkap_insert_record_booking_history( $parent_id, $weekday, $date_query, '0000-00-00', $value->from_time, '', $value->total_booking, $value->available_booking );
								}
							}

								$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
									SET available_booking = available_booking - ' . $quantity . '
									WHERE post_id = ' . $post_id . ' AND
									start_date = ' . $date_query . ' AND
									TIME_FORMAT( from_time, "%H:%i" ) = ' . $from_hi . ' AND                                                
									status != "inactive" AND
									total_booking > 0';
								$wpdb->query( $query );
						}

						// Update records for parent products - Grouped Products
						if ( isset( $parent_id ) && $parent_id != '' ) {
							$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
									SET available_booking = available_booking - ' . $quantity . "
									WHERE post_id = '" . $parent_id . "' AND
									start_date = '" . $date_query . "' AND
									from_time = '" . $query_from_time . "' AND
									status != 'inactive' AND
									total_booking > 0";
							$wpdb->query( $query );
						}

								$select         = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
										WHERE post_id =  %d AND
										start_date = %s AND
										from_time = %s AND
										status != 'inactive'";
								$select_results = $wpdb->get_results( $wpdb->prepare( $select, $post_id, $date_query, $query_from_time ) );

						foreach ( $select_results as $k => $v ) {
							$details[ $post_id ] = $v;
						}
					}
				} elseif ( isset( $booking_data['duration_time_slot'] ) && $booking_data['duration_time_slot'] != '' ) {

					$from_time         = date( 'H:i', strtotime( $booking_data['duration_time_slot'] ) );
					$selected_duration = explode( '-', $booking_data['selected_duration'] );
					$hour              = $selected_duration[0];
					$d_type            = $selected_duration[1];

					$end_str  = bkap_common::bkap_add_hour_to_date( $hidden_date, $from_time, $hour, $post_id, $d_type ); // return end date timestamp
					$to_time  = date( 'H:i', $end_str );// getend time in H:i format
					$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta

					// updating end date
					if ( $hidden_date != $end_date ) {
						$end_date_str = date( 'Y-m-d', strtotime( $end_date ) ); // conver date to Y-m-d format
					} else {
						$end_date_str = '0000-00-00';
					}

					for ( $i = 0; $i < $quantity; $i++ ) {

						$query = "INSERT INTO `" . $wpdb->prefix . "booking_history`
													 (post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
													 VALUES (
													 '" . $post_id . "',
													 '',
													 '" . $date_query . "',
													 '" . $end_date_str . "',
													 '" . $from_time . "',
													 '" . $to_time . "',
													 '0',
													 '0' )";
						$wpdb->query( $query );
						$new_booking_id = $wpdb->insert_id;/*
						//Insert records for parent products - Grouped Products
						if ( isset( $parent_id ) && $parent_id != '' ) {
						$query_parent   =   "INSERT INTO `".$wpdb->prefix."booking_history`
														(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
														VALUES (
														'".$parent_id."',
														'',
														'".$date_query."',
														'".$date_checkout_query."',
														'',
														'',
														'0',
														'0' )";
						$wpdb->query( $query_parent );
						}*/
					}

					if ( $called_from != 'admin' ) {
						self::bkap_update_booking_order_history( $order_id, $new_booking_id );
					} else {
						self::bkap_update_booking_order_history( $order_id, $new_booking_id, 'update' );
					}
				} else {
					$query   = 'UPDATE `' . $wpdb->prefix . 'booking_history`
										 SET available_booking = available_booking - ' . $quantity . "
										 WHERE post_id = '" . $post_id . "' AND
										 start_date = '" . $date_query . "' AND
										 status != 'inactive' AND
										 total_booking > 0";
					$updated = $wpdb->query( $query );
					if ( $updated == 0 ) {

						$weekday = date( 'w', strtotime( $date_query ) );
						$weekday = 'booking_weekday_' . $weekday;

						$base_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
										   WHERE post_id = %d
										   AND weekday = %s
										   AND start_date = '0000-00-00'
										   AND status != 'inactive'
										   AND total_booking > 0";

						$get_base = $wpdb->get_results( $wpdb->prepare( $base_query, $post_id, $weekday ) );

						if ( isset( $get_base ) && count( $get_base ) > 0 ) {
							foreach ( $get_base as $key => $value ) {
								$new_availability = $value->available_booking - $quantity;
								$insert_records   = "INSERT INTO `" . $wpdb->prefix . "booking_history`
														(post_id,weekday,start_date,total_booking,available_booking)
														VALUES (
													   '" . $post_id . "',
													   '" . $weekday . "',
													   '" . $date_query . "',
													   '" . $value->total_booking . "',
													   '" . $new_availability . "' ) ";

								$wpdb->query( $insert_records );

								if ( isset( $parent_id ) && $parent_id != '' ) {

									$insert_parent_records = "INSERT INTO `" . $wpdb->prefix . "booking_history`
														(post_id,weekday,start_date,total_booking,available_booking)
														VALUES (
													   '" . $parent_id . "',
													   '" . $weekday . "',
													   '" . $date_query . "',
													   '" . $value->total_booking . "',
													   '" . $new_availability . "' ) ";

									$wpdb->query( $insert_parent_records );

								}
							}
						} else {
							// this might happen when gcal is being used and the date has unlimited booking lockout.

							$unlimited_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
										   WHERE post_id = %d
										   AND start_date = %s
										   AND status != 'inactive'
										   AND total_booking = 0";

							$unlimited_results = $wpdb->get_results( $wpdb->prepare( $unlimited_query, $post_id, $date_query ) );

							if ( isset( $unlimited_results ) && count( $unlimited_results ) > 0 ) {
							} else {

								$base_query = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
										   WHERE post_id = %d
										   AND weekday = %s
										   AND start_date = '0000-00-00'
										   AND status != 'inactive'
										   AND total_booking = 0";

								$get_base = $wpdb->get_results( $wpdb->prepare( $base_query, $post_id, $weekday ) );

								if ( isset( $get_base ) && count( $get_base ) > 0 ) {
									foreach ( $get_base as $base_key => $value ) {
										$insert_records = "INSERT INTO `" . $wpdb->prefix . "booking_history`
														(post_id,weekday,start_date,total_booking,available_booking)
														VALUES (
													   '" . $post_id . "',
													   '" . $weekday . "',
													   '" . $date_query . "',
													   '" . $value->total_booking . "',
													   '" . $value->available_booking . "' ) ";

										$wpdb->query( $insert_records );

										if ( isset( $parent_id ) && $parent_id != '' ) {

											$insert_parent_records = "INSERT INTO `" . $wpdb->prefix . "booking_history`
															(post_id,weekday,start_date,total_booking,available_booking)
															VALUES (
														   '" . $parent_id . "',
														   '" . $weekday . "',
														   '" . $date_query . "',
														   '" . $value->total_booking . "',
														   '" . $value->available_booking . "' ) ";

											$wpdb->query( $insert_parent_records );

										}
									}
								}
							}
						}
					}

					// Update records for parent products - Grouped Products
					if ( isset( $parent_id ) && $parent_id != '' ) {
						$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
								SET available_booking = available_booking - ' . $quantity . "
								WHERE post_id = '" . $parent_id . "' AND
								start_date = '" . $date_query . "' AND
								status != 'inactive' AND
								total_booking > 0";
						$wpdb->query( $query );
						$update_one_time_singe_day_parent = 'true';
					}
				} // else end here

				if ( $booking_type != 'multiple_days' && $booking_type != 'duration_time' ) {
					if ( $called_from != 'admin' ) {

						if ( isset( $booking_data['time_slot'] )
						&& $booking_data['time_slot'] != ''
						&& ( isset( $booking_data['date'] ) || isset( $booking_data['booking_date'] ) )
						) {
							if ( isset( $query_to_time ) && $query_to_time != '' ) {
								$order_select_query = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
														WHERE post_id = %d AND
														start_date = %s AND
														TIME_FORMAT( from_time,'%H:%i' ) = %s AND
														TIME_FORMAT ( to_time, '%H:%i' ) = %s AND
														status = ''";
								$order_results      = $wpdb->get_results( $wpdb->prepare( $order_select_query, $post_id, $date_query, $from_hi, $to_hi ) );
							} else {
								$order_select_query = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
													WHERE post_id = %d AND
													start_date = %s AND
													TIME_FORMAT( from_time,'%H:%i' ) = %s AND
													status = ''";
								$order_results      = $wpdb->get_results( $wpdb->prepare( $order_select_query, $post_id, $date_query, $from_hi ) );
							}
						} else {
							$order_select_query = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
												WHERE post_id = %d AND
												start_date = %s AND
												status = ''";
							$order_results      = $wpdb->get_results( $wpdb->prepare( $order_select_query, $post_id, $date_query ) );
						}

						$j                = 0;
						$update_or_insert = '';
						// Rescheduling from My Account page hence we have to update the records.
						/*
						if ( $called_from == 'view-order' ){
						$update_or_insert = 'update';
						}*/
						foreach ( $order_results as $k => $v ) {
							$booking_id = $order_results[ $j ]->id;
							self::bkap_update_booking_order_history( $order_id, $booking_id, $update_or_insert );
							$j++;
						}
					}
				}

				do_action( 'bkap_update_lockout', $order_id, $post_id, $parent_id, $quantity, $booking_data );
			}
			return $details;
		}

		/**
		 * Update Booking Order History Table
		 *
		 * @param int    $order_id Order ID.
		 * @param int    $booking_id Booking ID.
		 * @param string $query type of query to perform.
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 2.5.0
		 */
		public static function bkap_update_booking_order_history( $order_id, $booking_id, $query = 'insert' ) {
			global $wpdb;

			if ( 0 == $order_id || '' === $order_id ) {
				return;
			}

			if ( isset( $query ) && 'update' == $query ) {
				$order_query = "UPDATE `" . $wpdb->prefix . "booking_order_history`
						   SET booking_id = '" . $booking_id . "'
						   WHERE order_id = '" . $order_id . "'";
				$result      = $wpdb->query( $order_query );

				if ( $result == 0 ) {
					$order_query = "INSERT INTO `" . $wpdb->prefix . "booking_order_history`
											  (order_id,booking_id)
											  VALUES (
											  '" . $order_id . "',
											  '" . $booking_id . "' )";
					$wpdb->query( $order_query );
				}
			} else {
				$order_query = "INSERT INTO `" . $wpdb->prefix . "booking_order_history`
											  (order_id,booking_id)
											  VALUES (
											  '" . $order_id . "',
											  '" . $booking_id . "' )";
				$wpdb->query( $order_query );
			}
		}

		/**
		 * Creates & returns a booking post meta record
		 * array to be inserted in post meta.
		 *
		 * @param int    $item_id Order Item ID.
		 * @param int    $product_id Product ID.
		 * @param int    $qty Quantity.
		 * @param array  $booking_details Booking Data Array.
		 * @param int    $variation_id Variation ID if exists else pass 0.
		 * @param string $status Status of Booking to be created.
		 *
		 * @return BKAP_Booking Booking Object.
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 4.0.0
		 */
		static function bkap_create_booking_post( $item_id, $product_id, $qty, $booking_details, $variation_id = 0, $status = 'confirmed' ) {

			global $wpdb;

			$new_booking_data = array();

			// Merge booking data
			$defaults = array(
				'product_id'      => $product_id,
				'order_item_id'   => $item_id,
				'start_date'      => '',
				'end_date'        => '',
				'resource_id'     => '',
				'persons'         => array(),
				'qty'             => $qty,
				'variation_id'    => $variation_id,
				'gcal_event_uid'  => false,
				'timezone_name'   => '',
				'timezone_offset' => '',
			);

			$new_booking_data = wp_parse_args( $new_booking_data, $defaults );

			// order ID
			$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items` WHERE order_item_id = %d';
			$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_id ) );

			$order_id = 0;
			if ( isset( $get_order_id ) && is_array( $get_order_id ) && count( $get_order_id ) > 0 ) {
				$order_id = $get_order_id[0]->order_id;
			}

			$all_day = 0;
			if ( isset( $booking_details['hidden_date_checkout'] ) && '' != $booking_details['hidden_date_checkout'] ) { // multiple day

				$start_date = date( 'Ymd', strtotime( $booking_details['hidden_date'] ) );
				$end_date   = date( 'Ymd', strtotime( $booking_details['hidden_date_checkout'] ) );
				$start_time = $end_time = '000000'; // for now, we default the start and end times to 000000

				// check if rental addon is enabled and per day booking is allowed
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
					$end_time = '235959'; // if  rental addon flat charge per day is enabled, then the entire checkout date is considered booked, hence 235959
				}

				// when checkin and checkout time will be introduced, they will be taken as is set by the user

			} elseif ( isset( $booking_details['time_slot'] ) && '' != $booking_details['time_slot'] ) { // date & time

				$explode_time = explode( ' - ', $booking_details['time_slot'] );

				if ( isset( $booking_details['timezone_offset'] ) ) {

					// aiya start date nu calculation karvanu chhe...
					$offset            = bkap_get_offset( $booking_details['timezone_offset'] );
					$site_timezone     = bkap_booking_get_timezone_string();
					$customer_timezone = $booking_details['timezone_name'];

					$start_time = bkap_convert_date_from_timezone_to_timezone( $booking_details['hidden_date'] . ' ' . trim( $explode_time[0] ), $customer_timezone, $site_timezone, 'His' );
					$end_time   = isset( $explode_time[1] ) ? bkap_convert_date_from_timezone_to_timezone( $booking_details['hidden_date'] . ' ' . trim( $explode_time[1] ), $customer_timezone, $site_timezone, 'His' ) : '000000';
					$start_date = $end_date = bkap_convert_date_from_timezone_to_timezone( $booking_details['hidden_date'] . ' ' . trim( $explode_time[0] ), $customer_timezone, $site_timezone, 'Ymd' );

					// calculating start date in case of different date based on timezone
				} else {
					$start_date = $end_date = bkap_date_as_format( $booking_details['hidden_date'], 'Ymd' );
					$start_time = date( 'His', strtotime( trim( $explode_time[0] ) ) );
					if ( isset( $explode_time[1] ) && '' != $explode_time[1] ) {
						$end_time = date( 'His', strtotime( trim( $explode_time[1] ) ) );
					} else {
						$end_time = '000000';
					}
				}
			} elseif ( isset( $booking_details['selected_duration'] ) && '' != $booking_details['selected_duration'] ) { // date & time

				$start_date = date( 'Ymd', strtotime( $booking_details['hidden_date'] ) );
				$time       = $booking_details['duration_time_slot'];

				$start_time = date( 'His', strtotime( $time ) );

				$selected_duration = explode( '-', $booking_details['selected_duration'] );
				$hour              = $selected_duration[0];
				$d_type            = $selected_duration[1];

				$end_date_str = bkap_common::bkap_add_hour_to_date( $booking_details['hidden_date'], $time, $hour, $product_id, $d_type );

				$end_date = date( 'YmdHis', $end_date_str );
				$end_time = '';

			} else { // only day
				$all_day = 1;
				if ( isset( $booking_details['hidden_date'] ) ) {
					$start_date = date( 'Ymd', strtotime( $booking_details['hidden_date'] ) );
					$end_date   = date( 'Ymd', strtotime( $booking_details['hidden_date'] ) );
					$start_time = '000000';
					$end_time   = '000000';
				} else {
					$start_date = '000000';
					$end_date   = '000000';
					$start_time = '000000';
					$end_time   = '000000';
				}
			}

			$event_uid = '';
			if ( isset( $booking_details['uid'] ) && '' != $booking_details['uid'] ) {
				$event_uid = $booking_details['uid'];
			}

			$resource_id = '';
			if ( isset( $booking_details['resource_id'] ) && '' != $booking_details['resource_id'] ) {
				$resource_id = $booking_details['resource_id'];
			}

			$persons = array();
			if ( isset( $booking_details['persons'] ) && '' != $booking_details['persons'] ) {
				$persons = $booking_details['persons'];
			}

			$fixed_block = '';
			if ( isset( $booking_details['fixed_block'] ) && '' != $booking_details['fixed_block'] ) {
				$fixed_block = $booking_details['fixed_block'];
			}

			$booking_price = $booking_details['price'];

			/*
			if( isset( $booking_details[ 'Deposit' ] ) && '' != $booking_details[ 'Deposit' ] ) {
			$booking_price = $booking_price + $booking_details[ 'Deposit' ];
			} */

			$duration = '';
			if ( isset( $booking_details['selected_duration'] ) && '' != $booking_details['selected_duration'] ) {
				$duration = $booking_details['selected_duration'];
			}

			$timezone_name = '';
			if ( isset( $booking_details['timezone_name'] ) && '' != $booking_details['timezone_name'] ) {
				$timezone_name = $booking_details['timezone_name'];
			}

			$timezone_offset = '';
			if ( isset( $booking_details['timezone_offset'] ) && '' != $booking_details['timezone_offset'] ) {
				$timezone_offset = $booking_details['timezone_offset'];
			}

			$new_booking_data['start']           = $start_date . $start_time;
			$new_booking_data['end']             = $end_date . $end_time;
			$new_booking_data['cost']            = $booking_price;
			$new_booking_data['user_id']         = get_post_meta( $order_id, '_customer_user', true );
			$new_booking_data['all_day']         = $all_day;
			$new_booking_data['parent_id']       = $order_id;
			$new_booking_data['gcal_event_uid']  = $event_uid;
			$new_booking_data['resource_id']     = $resource_id;
			$new_booking_data['persons']         = $persons;
			$new_booking_data['fixed_block']     = $fixed_block;
			$new_booking_data['duration']        = $duration;
			$new_booking_data['timezone_name']   = $timezone_name;
			$new_booking_data['timezone_offset'] = $timezone_offset;

			$status = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );

			// Create it
			$new_booking = self::get_bkap_booking( $new_booking_data );
			$new_booking->create( $status );

			do_action( 'bkap_update_booking_post_meta', $new_booking->id, $new_booking_data );

			return $new_booking;

		}

		/**
		 * Get Booking Object
		 *
		 * @param int $id ID for Booking to fetch
		 * @return BKAP_Booking Booking Object
		 * @since 4.0.0
		 */
		static function get_bkap_booking( $id ) {
			return new BKAP_Booking( $id );
		}

		/**
		 * This function updates the database for the booking
		 * details and adds booking fields on the Order Received page,
		 * Woocommerce->Orders when an order is placed for Woocommerce
		 * version breater than 2.0.
		 *
		 * @param int   $item_meta Order ID.
		 * @param mixed $cart_item Cart Item data.
		 *
		 * @globals mixed $wpdb
		 * @globals mixed $woocommerce
		 *
		 * @since 1.7.0
		 *
		 * @hook woocommerce_checkout_update_order_meta
		 */
		public static function bkap_order_item_meta( $order_id, $cart_item ) {

			global $wpdb;

			$booking_data_present = bkap_booking_data_present_check( $order_id ); // true is present
			$order_item_ids       = array();
			$sub_query            = '';
			$ticket_content       = array();
			$wc_version_compare   = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );

			foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {				

				$_product     = $values['data'];
				$parent_id    = $wc_version_compare ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $values['product_id'] );
				$variation_id = ( isset( $values['variation_id'] ) ) ? $values['variation_id'] : 0;
				$i            = 0;

				if ( isset( $values['bkap_booking'] ) && isset( $values['bkap_booking'][0]['hidden_date'] ) ) {
					$booking = $values['bkap_booking'][0];
				} else {
					do_action( 'bkap_update_order_non_booking', $values );
					continue;
				}

				$booking_count = count( $values['bkap_booking'] );

				$post_id    = bkap_common::bkap_get_product_id( $values['product_id'] );
				$quantity   = $values['quantity'];
				$post_title = $wc_version_compare ? $_product->get_title() : $_product->get_name();

				// To accomodate the change where attribute values are taken as quantity.
				if ( $variation_id > 0 ) {
					$attribute_lockout = self::bkap_set_attribute_as_qty( $post_id, $values, $quantity );
					if ( $attribute_lockout['is_attribute_lockout'] ) {
						$quantity = $attribute_lockout['quantity'];
					}
				}

				// Fetch line item.
				if ( count( $order_item_ids ) > 0 ) {
					$order_item_ids_to_exclude = implode( ',', $order_item_ids );
					$sub_query                 = ' AND order_item_id NOT IN (' . $order_item_ids_to_exclude . ')';
				}

				$query   = 'SELECT order_item_id,order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
									WHERE order_id = %s AND order_item_name LIKE %s' . $sub_query;
				$results = $wpdb->get_results( $wpdb->prepare( $query, $order_id, trim( $post_title, ' ' ) . '%' ) );

				$order_item_ids[] = $results[0]->order_item_id;
				$order_id         = $results[0]->order_id;
				$order_obj        = new WC_order( $order_id );

				for ( $i = 0; $i < $booking_count; $i++ ) {

					if ( isset( $values['bkap_booking'][ $i ]['hidden_date'] ) ) {
						$booking = $values['bkap_booking'][ $i ];
					} else {
						break;
					}

					/**
					 * Multiple timeslots option is enabled for product then 'multiple' else product id.
					 */
					$type_of_slot = apply_filters( 'bkap_slot_type', $post_id );

					if ( $type_of_slot === 'multiple' ) {

						if ( isset( $booking['time_slot'] ) && '' !== $booking['time_slot'] ) {

							bkap_common::bkap_update_order_item_meta( $results[0]->order_item_id, $post_id, $booking, false ); // Add booking data as item meta
							/**
							 * Action for updating order item meta for other booking info.
							 * Partial Deposits Addon : Deposits amounts infomration
							 * Tour Operators Addon: Comment information
							 */
							do_action( 'bkap_update_order', $values, $results[0] );

							if ( $booking_data_present ) {
								continue;
							}

							$time_exploded = explode( '<br>', $booking['time_slot'] );
							array_shift( $time_exploded );

							$multiple_price = isset( $booking['multiple_prices'] ) ? explode( ',', $booking['multiple_prices'] ) : array();

							foreach ( $time_exploded as $tkey => $tvalue ) {

								$booking['time_slot'] = $tvalue;

								if ( ! empty( $multiple_price ) ) {
									$booking['price'] = $multiple_price[ $tkey ];
								}

								$booking['price']         = $values['line_total'] / $quantity;
								$booking_data             = $booking;
								$booking_data['gcal_uid'] = false;
								// Add the booking as a post.
								$created_booking = self::bkap_create_booking_post( $results[0]->order_item_id, $post_id, $quantity, $booking_data, $variation_id );

								// Update the availability for that product.
								$details = self::bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking_data );

								do_action( 'after_bkap_create_booking_post', $results[0]->order_item_id, $post_id, $quantity, $booking_data, $variation_id, $order_id, $created_booking );
							}
						}
					} else {

						bkap_common::bkap_update_order_item_meta( $results[0]->order_item_id, $post_id, $booking, false ); // Add booking data as item meta

						/**
						 * Action for updating order item meta for other booking info.
						 * Partial Deposits Addon : Deposits amounts infomration
						 * Tour Operators Addon: Comment information
						 */
						do_action( 'bkap_update_order', $values, $results[0] );

						if ( $booking_data_present ) {
							continue; // do not execute code if true as its already added once.
						}

						if ( $booking_count == 1 ) {
							$booking['price'] = $values['data']->get_price();

							if ( isset( $attribute_lockout ) && $attribute_lockout['is_attribute_lockout'] ) {
								$booking['price'] = $values['line_total'] / $quantity;
							}
						}

						$booking_data             = $booking;
						$booking_data['gcal_uid'] = false;

						$created_booking = self::bkap_create_booking_post( $results[0]->order_item_id, $post_id, $quantity, $booking_data, $variation_id );

						// Update the availability for that product.
						$details = self::bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking );

						do_action( 'after_bkap_create_booking_post', $results[0]->order_item_id, $post_id, $quantity, $booking_data, $variation_id, $order_id, $created_booking );
					}

					// update the global time slot lockout.
					if ( isset( $booking['time_slot'] ) && $booking['time_slot'] != '' ) {
						self::bkap_update_global_lockout( $post_id, $quantity, $details, $booking );
					}

					$ticket         = array( apply_filters( 'bkap_send_ticket', $values, $order_obj ) );
					$ticket_content = array_merge( $ticket_content, $ticket );

					// The below code needs to be run only if WooCommerce verison is > 2.5.
					if ( version_compare( WOOCOMMERCE_VERSION, '2.5' ) < 0 ) {
						continue;
					} else {

						// Code where the Booking dates and time slots dates are not displayed in the customer new order email from WooCommerce version 2.5.
						$cache_key       = WC_Cache_Helper::get_cache_prefix( 'orders' ) . 'item_meta_array_' . $results[0]->order_item_id;
						$item_meta_array = wp_cache_get( $cache_key, 'orders' );

						if ( false !== $item_meta_array ) {

							$start_date_label    = get_option( 'book_item-meta-date' );
							$checkout_date_label = get_option( 'checkout_item-meta-date' );
							$time_slot_lable     = get_option( 'book_item-meta-time' );
							$metadata            = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT meta_key, meta_value, meta_id 
																		FROM {$wpdb->prefix}woocommerce_order_itemmeta 
																		WHERE order_item_id = %d 
																		AND meta_key 
																		IN (%s,%s,%s,%s,%s,%s) 
																		ORDER BY meta_id",
									absint( $results[0]->order_item_id ),
									$start_date_label,
									'_wapbk_booking_date',
									$checkout_date_label,
									'_wapbk_checkout_date',
									$time_slot_lable,
									'_wapbk_time_slot'
								)
							);
							foreach ( $metadata as $metadata_row ) {
								$item_meta_array[ $metadata_row->meta_id ] = (object) array(
									'key'   => $metadata_row->meta_key,
									'value' => $metadata_row->meta_value,
								);
							}
							wp_cache_set( $cache_key, $item_meta_array, 'orders' );
						}
					}
				}
			}

			do_action( 'bkap_send_email', $ticket_content );
		}

		/**
		 * This function will update the quantity based on the selected attribute as per attribute level lockout.
		 *
		 * @param int   $post_id Product ID.
		 * @param array $values Cart Item data.
		 * @param int   $quantity quantity of the item.
		 *
		 * @since 4.10.0
		 */
		public static function bkap_set_attribute_as_qty( $post_id, $values, $quantity ) {

			$attribute_booking_data = get_post_meta( $post_id, '_bkap_attribute_settings', true ); // Product Attributes - Booking Settings

			$is_attr_lockout = false;

			if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
				$order_attr = $values['variation']; // attribute values in the order
				$attr_qty   = 0;

				foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
					$attr_name = 'attribute_' . $attr_name;

					// check if the setting is on
					if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] ) {

						if ( array_key_exists( $attr_name, $order_attr ) && $order_attr[ $attr_name ] != 0 ) {
							$is_attr_lockout = true;
							$attr_qty        += $order_attr[ $attr_name ];
						}
					}
				}

				if ( isset( $attr_qty ) && $attr_qty > 0 ) {
					$attr_qty = $attr_qty * $values['quantity'];
				}
			}

			if ( isset( $attr_qty ) && $attr_qty > 0 ) {
				$quantity = $attr_qty;
			}

			return array( 'is_attribute_lockout' => $is_attr_lockout, 'quantity' => $quantity );
		}

		/**
		 * This function is responsible for Global Time Slots Booking.
		 *
		 * @param array $data Product ID.
		 *
		 * @since 5.2.1
		 */
		public static function bkap_update_global_lockout_schedule_callback( $data ) {

			global $wpdb;

			extract( $data ); // post_id, quantity, details, booking_data.

			$book_global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$booking_settings     = get_post_meta( $post_id, 'woocommerce_booking_settings', true );

			$hidden_date = $booking_data['hidden_date'];
			$date_query  = date( 'Y-m-d', strtotime( $hidden_date ) );

			$week_day = date( 'l', strtotime( $hidden_date ) );
			$weekdays = bkap_get_book_arrays( 'bkap_weekdays' );
			$weekday  = array_search( $week_day, $weekdays );

			if ( isset( $booking_settings['booking_time_settings'] ) && isset( $hidden_date ) ) {

				if ( isset( $booking_settings['booking_time_settings'][ $hidden_date ] ) ) {
					$lockout_settings = $booking_settings['booking_time_settings'][ $hidden_date ];
				} else {
					$lockout_settings = array();
				}

				if ( count( $lockout_settings ) == 0 ) {

					if ( isset( $booking_settings['booking_time_settings'][ $weekday ] ) ) {
						$lockout_settings = $booking_settings['booking_time_settings'][ $weekday ];
					} else {
						$lockout_settings = array();
					}
				}

				$from_hours  = '';
				$from_minute = '';
				$to_hours    = '';
				$to_minute   = '';
				if ( isset( $booking_data['time_slot'] ) && '' !== $booking_data['time_slot'] ) {

					$time_select   = $booking_data['time_slot'];
					$time_exploded = explode( '-', $time_select );

					$query_from_time = date( 'H:i', strtotime( $time_exploded[0] ) );
					if ( isset( $time_exploded[1] ) ) {
						$query_to_time = date( 'H:i', strtotime( $time_exploded[1] ) );
					} else {
						$query_to_time = '0:00';
					}
				}

				if ( isset( $query_from_time ) ) {
					$from_lockout_time = explode( ':', $query_from_time );
					$from_hours        = $from_lockout_time[0];
					$from_minute       = $from_lockout_time[1];

					if ( isset( $query_to_time ) && '' !== $query_to_time ) {
						$to_lockout_time = explode( ':', $query_to_time );
						$to_hours        = $to_lockout_time[0];
						$to_minute       = $to_lockout_time[1];
					}
				}

				foreach ( $lockout_settings as $l_key => $l_value ) {
					if ( $l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute ) {

						if ( isset( $l_value['global_time_check'] ) ) {
							$global_timeslot_lockout = $l_value['global_time_check'];
						} else {
							$global_timeslot_lockout = '';
						}
					}
				}
			}

			if ( isset( $book_global_settings->booking_global_timeslot ) && $book_global_settings->booking_global_timeslot == 'on' || isset( $global_timeslot_lockout ) && $global_timeslot_lockout == 'on' ) {
				$args    = array(
					'post_type'      => 'product',
					'posts_per_page' => -1,
				);
				$product = query_posts( $args );

				foreach ( $product as $k => $v ) {
					$product_ids[] = $v->ID;
				}

				$product_ids = apply_filters( 'bkap_checkout_product_ids_for_global_timeslots', $product_ids );

				foreach ( $product_ids as $k => $v ) {
					$duplicate_of = bkap_common::bkap_get_product_id( $v );

					$booking_settings = get_post_meta( $v, 'woocommerce_booking_settings', true );

					if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {

						if ( ! array_key_exists( $duplicate_of, $details ) ) {

							foreach ( $details as $key => $val ) {
								$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

								$start_date = $val['start_date'];
								$from_time  = $val['from_time'];
								$to_time    = $val['to_time'];

								$from_hi = date( 'H:i', strtotime( $from_time ) );

								if ( $to_time != '' ) {

									// global time slots over lapping
									$get_all_time_slots = array();

									$insert = 'NO';

									$query              = "SELECT *  FROM `" . $wpdb->prefix . "booking_history`
															WHERE post_id = '" . $duplicate_of . "' AND
															start_date = '" . $start_date . "' AND
															status !=  'inactive' ";
									$get_all_time_slots = $wpdb->get_results( $query );

									if ( ! $get_all_time_slots ) {
										$insert             = 'YES';
										$query              = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
																WHERE post_id = %d
																AND weekday = %s
																AND start_date = '0000-00-00'
																AND status !=  'inactive'";
										$get_all_time_slots = $wpdb->get_results( $wpdb->prepare( $query, $duplicate_of, $weekday ) );
									}

									foreach ( $get_all_time_slots as $time_slot_key => $time_slot_value ) {

										if ( 'YES' == $insert ) {

											$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																VALUES (
																'" . $duplicate_of . "',
																'" . $weekday . "',
																'" . $start_date . "',
																'" . $time_slot_value->from_time . "',
																'" . $time_slot_value->to_time . "',
																'" . $time_slot_value->total_booking . "',
																'" . $time_slot_value->available_booking . "' ) ";

											$wpdb->query( $query_insert );

										}

										$query_from_time_time_stamp      = strtotime( $from_time );
										$query_to_time_time_stamp        = strtotime( $to_time );
										$time_slot_value_from_time_stamp = strtotime( $time_slot_value->from_time );
										$time_slot_value_to_time_stamp   = strtotime( $time_slot_value->to_time );

										if ( $query_to_time_time_stamp > $time_slot_value_from_time_stamp && $query_from_time_time_stamp < $time_slot_value_to_time_stamp ) {

											if ( $time_slot_value_from_time_stamp != $query_from_time_time_stamp || $time_slot_value_to_time_stamp != $query_to_time_time_stamp ) {

												$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
															SET available_booking = available_booking - ' . $quantity . "
															WHERE post_id = '" . $duplicate_of . "' AND
															start_date = '" . $start_date . "' AND
															from_time = '" . $time_slot_value->from_time . "' AND
															to_time = '" . $time_slot_value->to_time . "' AND
															status != 'inactive' AND
															total_booking > 0";
												$wpdb->query( $query );
											}
										}
									}

									$to_hi = date( 'H:i', strtotime( $to_time ) );

									$check_record = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
												   WHERE post_id = %d
												   AND start_date = %s
												   AND TIME_FORMAT( from_time, '%H:%i' ) = %s
												   AND TIME_FORMAT( to_time, '%H:%i' ) = %s";
									$get_results  = $wpdb->get_col( $wpdb->prepare( $check_record, $duplicate_of, $date_query, $from_hi, $to_hi ) );

									$query   = 'UPDATE `' . $wpdb->prefix . 'booking_history`
													SET available_booking = available_booking - ' . $quantity . "
													WHERE post_id = '" . $duplicate_of . "' AND
													start_date = '" . $date_query . "' AND
													TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "' AND
													TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_hi . "' AND
													total_booking > 0 AND
													status != 'inactive' ";
									$updated = $wpdb->query( $query );

									if ( isset( $get_results ) && count( $get_results ) == 0 && $updated == 0 ) {

										if ( $val['weekday'] == '' ) {
											$week_day = date( 'l', strtotime( $date_query ) );
											$weekdays = bkap_get_book_arrays( 'bkap_weekdays' );
											$weekday  = array_search( $week_day, $weekdays );
											// echo $weekday;exit;
										} else {
											$weekday = $val['weekday'];
										}

										$results = array();
										$query   = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
													  WHERE post_id = %s
													  AND weekday = %s
													  AND start_date = '0000-00-00'
													  AND status !=  'inactive' ";

										$results = $wpdb->get_results( $wpdb->prepare( $query, $duplicate_of, $weekday ) );

										if ( ! $results ) {
											break;
										} else {

											foreach ( $results as $r_key => $r_val ) {

												if ( $from_time == $r_val->from_time && $to_time == $r_val->to_time ) {
													$available_booking = ( $r_val->total_booking > 0 ) ? $r_val->available_booking - $quantity : $r_val->available_booking;
													$query_insert      = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																			(post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																			VALUES (
																			'" . $duplicate_of . "',
																			'" . $weekday . "',
																			'" . $start_date . "',
																			'" . $r_val->from_time . "',
																			'" . $r_val->to_time . "',
																			'" . $r_val->total_booking . "',
																			'" . $available_booking . "' )";

													$wpdb->query( $query_insert );

												} else {
													$from_lockout_time = explode( ':', $r_val->from_time );

													if ( isset( $from_lockout_time[0] ) ) {
														$from_hours = $from_lockout_time[0];
													} else {
														$from_hours = ' ';
													}

													if ( isset( $from_lockout_time[1] ) ) {
														$from_minute = $from_lockout_time[1];
													} else {
														$from_minute = ' ';
													}
													// default to blanks
													$to_hours = $to_minute = '';

													if ( isset( $r_val->to_time ) && $r_val->to_time != '' ) {
														$to_lockout_time = explode( ':', $r_val->to_time );

														if ( isset( $to_lockout_time[0] ) ) {
															$to_hours = $to_lockout_time[0];
														}

														if ( isset( $to_lockout_time[1] ) ) {
															$to_minute = $to_lockout_time[1];
														}
													}
													foreach ( $lockout_settings as $l_key => $l_value ) {

														if ( $l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute && $l_value['to_slot_hrs'] == $to_hours && $l_value['to_slot_min'] == $to_minute ) {
															$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																				 (post_id,weekday,start_date,from_time,to_time,total_booking,available_booking)
																				 VALUES (
																				 '" . $duplicate_of . "',
																				 '" . $weekday . "',
																				 '" . $start_date . "',
																				 '" . $r_val->from_time . "',
																				 '" . $r_val->to_time . "',
																				 '" . $r_val->total_booking . "',
																				 '" . $r_val->available_booking . "' )";
															$wpdb->query( $query_insert );
														}
													}
												}
											}
										}
									}
								} else {

									$check_record = "SELECT id FROM `" . $wpdb->prefix . "booking_history`
												   WHERE post_id = %d
												   AND start_date = %s
												   AND TIME_FORMAT( from_time, '%H:%i' ) = %s
												   AND to_time = ''";
									$get_results  = $wpdb->get_col( $wpdb->prepare( $check_record, $duplicate_of, $date_query, $from_hi ) );

									$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
													SET available_booking = available_booking - ' . $quantity . "
													WHERE post_id = '" . $duplicate_of . "' AND
													start_date = '" . $date_query . "' AND
													TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "'
													AND to_time = ''
													AND total_booking > 0
													AND status != 'inactive' ";

									$updated = $wpdb->query( $query );

									if ( isset( $get_results ) && count( $get_results ) == 0 && $updated == 0 ) {
										if ( $val['weekday'] == '' ) {
											$week_day = date( 'l', strtotime( $date_query ) );
											$weekdays = bkap_get_book_arrays( 'bkap_weekdays' );
											$weekday  = array_search( $week_day, $weekdays );

										} else {
											$weekday = $val['weekday'];
										}

										$results = array();
										$query   = "SELECT * FROM `" . $wpdb->prefix . "booking_history`
													  WHERE post_id = %d
													  AND weekday = %s
													  AND start_date = '0000-00-00'
													  AND to_time = '' 
													  AND status !=  'inactive' ";
										$results = $wpdb->get_results( $wpdb->prepare( $query, $duplicate_of, $weekday ) );

										if ( ! $results ) {
											break;
										} else {
											foreach ( $results as $r_key => $r_val ) {

												if ( $from_time == $r_val->from_time ) {
													$available_booking = ( $r_val->total_booking > 0 ) ? $r_val->available_booking - $quantity : $r_val->available_booking;
													$query_insert      = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																				(post_id,weekday,start_date,from_time,total_booking,available_booking)
																				VALUES (
																				'" . $duplicate_of . "',
																				'" . $weekday . "',
																				'" . $start_date . "',
																				'" . $r_val->from_time . "',
																				'" . $r_val->total_booking . "',
																				'" . $available_booking . "' )";
													$wpdb->query( $query_insert );
												} else {
													$from_lockout_time = explode( ':', $r_val->from_time );
													$from_hours        = 0;
													$from_minute       = 0;
													if ( isset( $from_lockout_time[0] ) ) {
														$from_hours = $from_lockout_time[0];
													}
													if ( isset( $from_lockout_time[1] ) ) {
														$from_minute = $from_lockout_time[1];
													}

													foreach ( $lockout_settings as $l_key => $l_value ) {

														if ( $l_value['from_slot_hrs'] == $from_hours && $l_value['from_slot_min'] == $from_minute ) {
															$query_insert = "INSERT INTO `" . $wpdb->prefix . "booking_history`
																				(post_id,weekday,start_date,from_time,total_booking,available_booking)
																				VALUES (
																				'" . $duplicate_of . "',
																				'" . $weekday . "',
																				'" . $start_date . "',
																				'" . $r_val->from_time . "',
																				'" . $r_val->available_booking . "',
																				'" . $r_val->available_booking . "' )";
															$wpdb->query( $query_insert );
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}

		/**
		 * This function updates the database resords for the booking which has globally same timeslots
		 *
		 * @param int   $post_id Post ID.
		 * @param mixed $quantity quantity.
		 * @param array $details array of all matching records for booking.
		 * @param array $booking_data array of selected booking details.
		 *
		 * @global mixed $wpdb Global wp Object.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_update_global_lockout( $post_id, $quantity, $details, $booking_data ) {

			$data = array(
				'post_id'      => $post_id,
				'quantity'     => $quantity,
				'details'      => $details,
				'booking_data' => $booking_data,
			);

			// Schedule an action 'bkap_update_global_lockout_schedule' for global time slot booking.
			as_enqueue_async_action( 'bkap_update_global_lockout_schedule', array( 'data' => $data ) );
		}
	}
	$bkap_checkout = new bkap_checkout();
}

