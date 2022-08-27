<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Bookings of an order when an Order or Booking is cancelled
 *
 * @author   Tyche Softwares
 * @package  BKAP/Cancel-Order
 * @category Classes
 */

require_once 'bkap-common.php';
require_once 'bkap-lang.php';

if ( ! class_exists( 'bkap_cancel_order' ) ) {

	/**
	 * Class for listing the bookings on respective pages
	 */
	class bkap_cancel_order {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */
		public function __construct() {

			// Free up bookings when an order is cancelled or refunded or failed.
			add_action( 'woocommerce_order_status_cancelled', array( &$this, 'bkap_woocommerce_cancel_order' ), 10, 1 );
			add_action( 'woocommerce_order_status_refunded', array( &$this, 'bkap_woocommerce_cancel_order' ), 10, 1 );
			add_action( 'woocommerce_order_status_failed', array( &$this, 'bkap_woocommerce_cancel_order' ), 10, 1 );

			add_action( 'woocommerce_order_status_changed', array( &$this, 'bkap_woocommerce_restore_bookings' ), 10, 3 );

			// Free up the bookings when an order is trashed.
			add_action( 'wp_trash_post', array( &$this, 'bkap_trash_order' ), 10, 1 );
			add_action( 'untrash_post', array( &$this, 'bkap_untrash_order' ), 10, 1 );

			add_filter( 'woocommerce_my_account_my_orders_actions', array( &$this, 'bkap_get_add_cancel_button' ), 10, 3 );

			// Reallocate booking when changing order status from failed to processing, completed and on-hold.
			add_action( 'woocommerce_order_status_changed', array( &$this, 'bkap_reallocate_booking_when_order_status_failed_to_processing' ), 10, 3 );
			add_action( 'woocommerce_refund_created', array( &$this, 'bkap_woocommerce_refund_created' ), 10, 2 );
			add_action( 'woocommerce_before_delete_order_item', array( &$this, 'bkap_woocommerce_delete_order_item' ), 10, 1 );

			add_action( 'woocommerce_saved_order_items', array( &$this, 'bkap_edit_quantity_from_edit_order' ), 10, 2 );

			add_action( 'bkap_auto_cancel_booking', array( &$this, 'bkap_auto_cancel_booking' ), 10 );
		}

		/**
		 * After certain time of the order placed, bookings that requires confirmation will get cancelled automatically.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_auto_cancel_booking() {

			$global_setting           = bkap_global_setting();
			$bkap_auto_cancel_booking = 0;
			if ( isset( $global_setting->bkap_auto_cancel_booking ) &&
				$global_setting->bkap_auto_cancel_booking !== '' ) {
				$bkap_auto_cancel_booking = $global_setting->bkap_auto_cancel_booking;
			}

			if ( $bkap_auto_cancel_booking > 0 ) {

				global $wpdb;

				$date              = date( 'Y-m-d', current_time( 'timestamp' ) ); // phpcs:ignore
				$current_date      = date( 'Y-m-d H', current_time( 'timestamp' ) ); // phpcs:ignore
				$current_date      = $current_date . ':00';
				$current_date_time = strtotime( $current_date );
				$hours             = 1;
				$from_date         = $current_date_time - ( $hours * 3600 );
				$from_date         = date( 'Y-m-d', $from_date );

				$result = $wpdb->get_results(
					"SELECT * FROM $wpdb->posts 
							WHERE post_type = 'shop_order'
							AND post_date BETWEEN '{$date}  00:00:00' AND '{$from_date} 23:59:59'
						"
				);

				foreach ( $result as $key => $value ) {

					$order_date = date( 'Y-m-d H', strtotime( $value->post_date ) );
					$order_date = $order_date . ':00';
					$order_date = strtotime( $order_date ); // phpcs:ignore
					$interval   = absint( $order_date - $current_date_time ); // booking date - current date time.

					if ( $interval === absint( $bkap_auto_cancel_booking * 3600 ) ) { // phpcs:ignore
						$order = wc_get_order( $value->ID );
						foreach ( $order->get_items() as $order_item_id => $item ) {

							$booking_id = bkap_common::get_booking_id( $order_item_id ); // get the booking ID for each item.

							if ( $booking_id ) {
								$booking        = bkap_checkout::get_bkap_booking( $booking_id );
								$booking_status = $booking->get_status();

								if ( 'pending-confirmation' === $booking_status ) {
									bkap_booking_confirmation::bkap_save_booking_status( $order_item_id, 'cancelled', $booking_id );
									$note = sprintf( esc_html__( 'Booking #%d has been successsfully cancelled.', 'woocommerce-booking' ), esc_html( $booking_id ) );
									$order->add_order_note( $note );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * This function will be executed upon deleting the line items using Refund Button in Order table.
		 *
		 * @hook woocommerce_before_delete_order_item
		 *
		 * @param int $item_id Item ID.
		 *
		 * @since 5.0.2
		 */
		public static function bkap_woocommerce_delete_order_item( $item_id ) {

			$order_id = wc_get_order_id_by_order_item_id( $item_id );
			$item     = new WC_Order_Item_Product( $item_id );
			$quantity = $item->get_quantity();
			self::bkap_reallocate_booking_upon_refund( $item_id, $order_id, $quantity );
		}

		/**
		 * This function will be executed upon refunding the items using Refund Button in Order table.
		 *
		 * @hook woocommerce_refund_created
		 *
		 * @param int   $refund_id Refund ID.
		 * @param array $args Array of retunded items infomration.
		 *
		 * @since 5.0.2
		 */
		public static function bkap_woocommerce_refund_created( $refund_id, $args ) {

			$line_items = $args['line_items'];
			$order_id   = $args['order_id'];
			foreach ( $line_items as $item_id => $item_value ) {
				if ( $item_value['qty'] > 0 ) {
					self::bkap_reallocate_booking_upon_refund( $item_id, $order_id, $item_value['qty'] );
				}
			}
		}

		/**
		 * This function will reallocated the booking based on the item and qty.
		 *
		 * @param int $item_id Refund ID.
		 * @param int $order_id Order ID.
		 * @param int $item_qty Selected quantity of item being refunded.
		 *
		 * @since 5.0.2
		 */
		public static function bkap_reallocate_booking_upon_refund( $item_id, $order_id, $item_qty ) {

			global $wpdb;

			$booking_details = array();
			$order_obj       = wc_get_order( $order_id );
			$order_items     = $order_obj->get_items();
			$select_query    = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history` WHERE order_id= %d';
			$results         = $wpdb->get_results( $wpdb->prepare( $select_query, $order_id ) );

			foreach ( $results as $key => $value ) {

				$select_query_post = 'SELECT post_id, start_date, end_date, from_time, to_time, weekday FROM `' . $wpdb->prefix . 'booking_history` WHERE id= %d';
				$results_post      = $wpdb->get_results( $wpdb->prepare( $select_query_post, $value->booking_id ) );

				if ( count( $results_post ) > 0 ) {
					$booking_info = array(
						'post_id'    => $results_post[0]->post_id,
						'start_date' => $results_post[0]->start_date,
						'end_date'   => $results_post[0]->end_date,
						'from_time'  => $results_post[0]->from_time,
						'to_time'    => $results_post[0]->to_time,
						'weekday'    => $results_post[0]->weekday,
					);

					$booking_details[ $value->booking_id ] = $booking_info;
					$item_booking_id                       = $value->booking_id;
				}
			}

			foreach ( $order_items as $item_key => $item_value ) {

				if ( $item_key != $item_id ) {
					continue;
				}

				$_status = $item_value['wapbk_booking_status'];

				if ( ( isset( $_status ) && 'cancelled' !== $_status ) ) {
					$cancelled = false;
					foreach ( $booking_details as $booking_id => $booking_data ) {

						if ( $item_value['product_id'] == $booking_data['post_id'] ) {

							$duration_dates = array(); // duration start and end date is different then check for start date.
							if ( strpos( $item_value['wapbk_booking_date'], ' - ' ) !== false ) {
								$duration_dates = explode( ' - ', $item_value['wapbk_booking_date'] );
							}

							if ( $item_value['wapbk_booking_date'] == $booking_data['start_date'] || ( ! empty( $duration_dates ) && $duration_dates[0] == $booking_data['start_date'] ) ) {

								if ( isset( $booking_data['to_time'] ) && '' != $booking_data['to_time'] ) {
									$time = date( 'H:i', strtotime( $booking_data['from_time'] ) ) . ' - ' . date( 'H:i', strtotime( $booking_data['to_time'] ) );
								} else {
									$time = date( 'H:i', strtotime( $booking_data['from_time'] ) );
								}

								if ( isset( $item_value['wapbk_checkout_date'] ) ) {
									if ( $item_value['wapbk_checkout_date'] == $booking_data['end_date'] ) {
										$item_booking_id = $booking_id;
										break;
									}
								} elseif ( isset( $item_value['wapbk_time_slot'] ) ) {
									if ( strpos( $item_value['wapbk_time_slot'], ',' ) > 0 ) {
										$time_slot_list = explode( ',', $item_value['wapbk_time_slot'] );
										foreach ( $time_slot_list as $t_key => $t_value ) {

											// Checking time as per timezone booking.
											if ( isset( $item_value['wapbk_timezone'] ) && $item_value['wapbk_timezone'] != '' ) {
												$offset  = bkap_get_offset( $item_value['wapbk_timeoffset'] );
												$t_value = bkap_convert_timezone_time_to_system_time( $t_value, $item_value, 'H:i' );
											}

											if ( $time == $t_value ) {
												$item_booking_id = $booking_id;
												self::bkap_reallot_item( $item_value, $item_booking_id, $order_id );
												$cancelled = true;
											}
										}
									} else {

										$metatime = $item_value['wapbk_time_slot'];

										// Checking time as per timezone booking.
										if ( isset( $item_value['wapbk_timezone'] ) && $item_value['wapbk_timezone'] != '' ) {
											$offset   = bkap_get_offset( $item_value['wapbk_timeoffset'] );
											$metatime = bkap_convert_timezone_time_to_system_time( $metatime, $item_value, 'H:i' );
										}

										if ( $metatime == $time ) {
											$item_booking_id = $booking_id;
											break;
										}
									}
								} else {
									$item_booking_id = $booking_id;
									break;
								}
							}
						}
					}

					if ( ! $cancelled ) {
						$booking_post_id = bkap_common::get_booking_id( $item_key );

						if ( $booking_post_id ) {
							$booking_post = bkap_checkout::get_bkap_booking( $booking_post_id );
							do_action( 'bkap_rental_delete', $booking_post, $booking_post_id );

							if ( isset( $booking_post ) && isset( $booking_post->qty ) ) {
								$bkap_qty = $booking_post->qty;
							}

							if ( isset( $bkap_qty ) ) {
								$update_post_qty = $bkap_qty - $item_qty;
								$bkap_qty        = $item_qty;

								self::bkap_reallot_item( $item_value, $item_booking_id, $order_id, $bkap_qty );
								update_post_meta( $booking_post->get_id(), '_bkap_qty', $update_post_qty );

								if ( 0 == $update_post_qty ) {
									$booking_post->update_status( 'cancelled' );
									$delete_order_history = 'DELETE FROM `' . $wpdb->prefix . 'booking_order_history`
															WHERE order_id = %d AND booking_id = %d';
									$wpdb->query( $wpdb->prepare( $delete_order_history, $order_id, $item_booking_id ) );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * This function will add cancel order button on the “MY ACCOUNT” page. For cancelling the order.
		 *
		 * @hook woocommerce_my_account_my_orders_actions
		 *
		 * @param WC_Order $order Order Object.
		 * @param mixed    $action Action performed.
		 *
		 * @return WC_Order WooCommerce Order Object.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_get_add_cancel_button( $order, $action ) {

			global $wpdb;

			$myaccount_page_id = get_option( 'woocommerce_myaccount_page_id' );

			if ( $myaccount_page_id ) {
				$myaccount_page_url = get_permalink( $myaccount_page_id );
			}

			$wc_version    = version_compare( WOOCOMMERCE_VERSION, '3.0.0' );
			$action_id     = ( $wc_version < 0 ) ? $action->id : $action->get_id();
			$action_status = ( $wc_version < 0 ) ? $action->status : $action->get_status();

			if ( isset( $_GET['order_id'] ) && $_GET['order_id'] == $action_id && $_GET['cancel_order'] == 'yes' ) {

				// Retrieve Booking ID.
				$booking_id = (int) $wpdb->get_col(
					$wpdb->prepare(
						"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_bkap_parent_id' AND meta_value = %d",
						$action_id
					)
				)[0];

				if ( isset( $booking_id ) && ( $booking_id > 0 ) ) {
					$booking = new BKAP_Booking( $booking_id );
					$item_id = $booking->get_item_id();

					$booking_date = wc_get_order_item_meta( $item_id, '_wapbk_booking_date', true );
					$booking_date = explode( '-', $booking_date );
					$booking_date = $booking_date[2] . '-' . $booking_date[1] . '-' . $booking_date[0];

					$booking_time = wc_get_order_item_meta( $item_id, '_wapbk_time_slot', true );
					if ( '' !== $booking_time ) {
						$booking_time_explode = explode( ' - ', $booking_time );
						$booking_date        .= ' ' . $booking_time_explode[0];
					}

					$settings                          = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
					$bkap_booking_minimum_hours_cancel = ( isset( $settings->bkap_booking_minimum_hours_cancel ) && '' !== $settings->bkap_booking_minimum_hours_cancel ) ? $settings->bkap_booking_minimum_hours_cancel : 0;

					$_diff_interval = (int) $bkap_booking_minimum_hours_cancel * 60 * 60; // Convert to seconds so that it can be same format with $diff_from_booked_date.

					$diff_from_booked_date = (int) ( (int) strtotime( $booking_date ) - current_time( 'timestamp' ) );

					if ( isset( $settings->bkap_booking_minimum_hours_cancel ) && ( $diff_from_booked_date >= $_diff_interval ) ) {
						$order_obj = wc_get_order( $action_id );
						$order_obj->update_status( 'cancelled' );
						wc_add_notice( __( 'Booking has been successsfully cancelled.', 'woocommerce-booking' ), 'success' );
					} else {
						wc_add_notice( __( 'This Booking can no longer be cancelled.', 'woocommerce-booking' ), 'error' );
					}
				} else {
					wc_add_notice( __( 'Unable to perform operation.', 'woocommerce-booking' ), 'error' );
				}

				print( '<script type="text/javascript">location.href="' . $myaccount_page_url . '";</script>' );
			}

			if ( $action_status != 'cancelled' && $action_status != 'completed' && $action_status != 'refunded' && $action->get_created_via() != 'wc_deposits' ) {
				$order['cancel'] = array(
					'url'  => apply_filters( 'woocommerce_get_cancel_order_url', add_query_arg( 'order_id', $action_id ) . '&cancel_order=yes' ),
					'name' => __( 'Cancel', 'woocommerce-booking' ),
				);
			}
			return $order;
		}

		/**
		 * This function frees up the booking dates and/or time for all the items in an order when the order is trashed without cancelling or refunding it.
		 *
		 * @hook wp_trash_post
		 *
		 * @param string|int $post_id Order ID whose booking needs to be trashed.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_trash_order( $post_id ) {

			$post_obj  = get_post( $post_id );
			$post_type = $post_obj->post_type;

			switch ( $post_type ) {
				case 'shop_order':
					// array of all the  order statuses for which the bookings do not need to be freed up.
					$status = array( 'wc-cancelled', 'wc-refunded', 'wc-failed' );

					if ( ! in_array( $post_obj->post_status, $status ) ) {

						// trash the booking posts as well.
						$order = wc_get_order( $post_id );

						self::bkap_woocommerce_cancel_order( $post_id ); // Previously it was below foreach but when moving the order to trash from bulk then booking id was coming blank hence moved it above foreach.

						foreach ( $order->get_items() as $order_item_id => $item ) {

							if ( 'line_item' == $item['type'] ) {
								$booking_id = bkap_common::get_booking_id( $order_item_id ); // get the booking ID for each item.

								if ( $booking_id ) {
									wp_trash_post( $booking_id );
								}
							}
						}
					}
					break;
				case 'bkap_booking':
					$booking_id = $post_obj->ID;
					self::bkap_delete_booking( $booking_id );
					break;
			}
		}

		/**
		 * Untrash the order and restore the bookings.
		 *
		 * @hook untrash_post
		 *
		 * @param string|int $post_id Order ID whose booking needs to be untrashed.
		 *
		 * @since 1.7.0
		 */
		public static function bkap_untrash_order( $post_id ) {
			$post_obj = get_post( $post_id );

			if ( 'shop_order' == $post_obj->post_type && ( 'wc-cancelled' != $post_obj->post_status || 'wc-refunded' != $post_obj->post_status ) ) {

				// untrash the booking posts as well.
				$order = wc_get_order( $post_id );
				foreach ( $order->get_items() as $order_item_id => $item ) {
					if ( 'line_item' == $item['type'] ) {
						// get the booking ID for each item.
						$booking_id = bkap_common::get_booking_id( $order_item_id );
						wp_untrash_post( $booking_id );
					}
				}
			}

			if ( 'bkap_booking' == $post_obj->post_type ) {
				$booking = new BKAP_Booking( $post_id );
				$order   = $booking->get_order();

				foreach ( $order->get_items() as $item_key => $item_value ) {
					if ( 'line_item' == $item_value['type'] ) {
						// get the booking ID for each item
						$booking_id = bkap_common::get_booking_id( $item_key );
						if ( $booking_id == $post_id ) {
							self::bkap_restore_trashed_booking( $item_key, $item_value, $order );
						}
					}
				}
			}

		}

		/**
		 * Restore bookings once an Order is untrashed
		 *
		 * @param string|int $order_id Order ID.
		 * @param string     $old_status Current Status active.
		 * @param string     $new_status New Status to be changed.
		 *
		 * @since 1.7.0
		 *
		 * @globals mixed $wpdb global variable
		 * @globals WP_Post $post
		 */
		public static function bkap_woocommerce_restore_bookings( $order_id, $old_status, $new_status ) {

			$old_status_arr = array( 'cancelled', 'refunded', 'trashed' );
			$new_status_arr = array( 'cancelled', 'refunded', 'trashed', 'refund-requested' );

			if ( in_array( $old_status, $old_status_arr ) && ! in_array( $new_status, $new_status_arr ) ) {
				global $wpdb, $post;
				$order_obj   = wc_get_order( $order_id );
				$order_items = $order_obj->get_items();
				foreach ( $order_items as $item_key => $item_value ) {
					self::bkap_restore_trashed_booking( $item_key, $item_value, $order_obj );
				}
			}
		}

		/**
		 * Restore bookings if an order or booking is trashed.
		 *
		 * @param int   $item_key Item Key.
		 * @param array $item_value Item Value Array.
		 * @param obj   $order Order Object.
		 *
		 * @since 5.0.1
		 */
		public static function bkap_restore_trashed_booking( $item_key, $item_value, $order ) {
			$product_id   = bkap_common::bkap_get_product_id( $item_value['product_id'] );
			$order_id     = $order->get_id();
			$booking_data = array();
			if ( isset( $item_value[ get_option( 'book_item-meta-date' ) ] ) ) {
				$booking_data['date'] = $item_value[ get_option( 'book_item-meta-date' ) ];
			}

			if ( isset( $item_value['wapbk_booking_date'] ) ) {
				$booking_data['hidden_date'] = $item_value['wapbk_booking_date'];
			}

			if ( isset( $item_value['wapbk_checkout_date'] ) ) {
				$booking_data['hidden_date_checkout'] = $item_value['wapbk_checkout_date'];
			}

			if ( isset( $item_value[ get_option( 'book_item-meta-time' ) ] ) ) {
				$booking_data['time_slot'] = $item_value[ get_option( 'book_item-meta-time' ) ];
			}

			$_product  = wc_get_product( $product_id );
			$parent_id = 0;
			if ( is_bool( $_product ) === false ) {
				$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $product_id );
			}

			$details = bkap_checkout::bkap_update_lockout( $order_id, $product_id, $parent_id, $item_value['qty'], $booking_data, 'admin' );
			// update the global time slot lockout.
			if ( isset( $item_value[ get_option( 'book_item-meta-time' ) ] ) && $item_value[ get_option( 'book_item-meta-time' ) ] != '' ) {
				bkap_checkout::bkap_update_global_lockout( $product_id, $item_value['qty'], $details, $booking_data );
			}

			// get booking post ID.
			$booking_post = bkap_common::get_booking_id( $item_key );
			// update the booking post status.
			if ( $booking_post ) {
				$new_booking = bkap_checkout::get_bkap_booking( $booking_post );
				$status      = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );
				$new_booking->update_status( $status );

				/*
				 * Issue #3870.
				 * We are assigning cancelled status when booking is trashed.
				 * Upon untrashing it takes status from post meta.
				 */
				$status_before_trash = get_post_meta( $booking_post, '_wp_trash_meta_status', true );
				if ( $status_before_trash ) {
					update_post_meta( $booking_post, '_wp_trash_meta_status', $status );
				}

				// Creating-Adding meeting to booking.
				$new_booking_data = bkap_get_meta_data( $booking_post );
				foreach ( $new_booking_data as $data ) {
					Bkap_Zoom_Meeting_Settings::bkap_create_zoom_meeting( $booking_post, $data );
				}
			}

			// if automated sync is enabled, add the event back to the calendar.
			require_once plugin_dir_path( __FILE__ ) . '/class.gcal.php';
			// add event in GCal if sync is set to autmated.
			$gcal          = new BKAP_Gcal();
			$user_id       = get_current_user_id();
			$event_details = self::bkap_create_gcal_object( $order_id, $item_value, $order );
			if ( in_array( $gcal->get_api_mode( $user_id, $item_value['product_id'] ), array( 'directly', 'oauth' ), true ) ) {

				// if sync is disabled at the product level, set post_id to 0 to ensure admin settings are taken into consideration.
				$booking_settings = get_post_meta( $item_value['product_id'], 'woocommerce_booking_settings', true );
				$post_id          = $item_value['product_id'];
				if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' == $booking_settings['product_sync_integration_mode'] ) ) {
					$post_id = 0;
				}

				$status = $gcal->insert_event( $event_details, $item_key, $user_id, $post_id, false );

				if ( $status ) {
					// add an order note, mentioning an event has been created for the item.
					$post_title = $event_details['product_name'];
					$order_note = __( "Booking_details for $post_title have been exported to the Google Calendar", 'woocommerce-booking' );
					$order->add_order_note( $order_note );
				}
			}

			do_action( 'bkap_restore_trashed_booking', $item_key, $item_value, $order_id, $order, $event_details );
		}

		/**
		 * This function creates an event details array which contains all the details required to insert an event in Google Calendar
		 *
		 * @param string|int $order_id Order ID.
		 * @param array      $item_details Order Item Details.
		 * @param WC_Order   $order Order Object.
		 *
		 * @return array $event_details Event Details for GCal mapping.
		 *
		 * @since 3.5.2
		 */
		public static function bkap_create_gcal_object( $order_id, $item_details, $order, $item_number = 0 ) {

			$booking_labels       = bkap_booking_fields_label();
			$all_booking_details  = self::bkap_get_booking_item_meta( $item_details->get_id(), $item_details, $booking_labels );
			$item_booking_details = $all_booking_details[ $item_number ];

			$valid_date = false;
			if ( isset( $item_booking_details['hidden_date'] ) ) {
				$valid_date = bkap_common::bkap_check_date_set( $item_booking_details['hidden_date'] );
			}

			if ( $valid_date ) {
				$event_details = array();

				$event_details['hidden_booking_date'] = $item_booking_details['hidden_date'];

				if ( isset( $item_details['wapbk_checkout_date'] ) && $item_booking_details['hidden_date_checkout'] != '' ) {
					$event_details['hidden_checkout_date'] = $item_booking_details['hidden_date_checkout'];
				}

				if ( isset( $item_booking_details['wapbk_time_slot'] ) && $item_booking_details['wapbk_time_slot'] != '' ) {
					if ( isset( $item_details['wapbk_timezone'] ) && $item_details['wapbk_timezone'] != '' ) {
						$offset                     = bkap_get_offset( $item_details['wapbk_timeoffset'] );
						$event_details['time_slot'] = bkap_convert_timezone_time_to_system_time( $item_details['wapbk_time_slot'], $item_details, 'H:i' );
					} else {
						$event_details['time_slot'] = $item_booking_details['wapbk_time_slot'];
					}
				}

				$event_details['billing_email']      = $order->get_billing_email();
				$event_details['billing_first_name'] = $order->get_billing_first_name();
				$event_details['billing_last_name']  = $order->get_billing_last_name();
				$event_details['billing_address_1']  = $order->get_billing_address_1();
				$event_details['billing_address_2']  = $order->get_billing_address_2();
				$event_details['billing_city']       = $order->get_billing_city();
				$event_details['billing_phone']      = $order->get_billing_phone();
				$event_details['order_comments']     = $order->get_customer_note();
				$event_details['order_id']           = $order_id;

				$shipping_first_name = $order->get_shipping_first_name();
				if ( isset( $shipping_first_name ) && $shipping_first_name != '' ) {
					$event_details['shipping_first_name'] = $shipping_first_name;
				}

				$shipping_last_name = $order->get_shipping_last_name();
				if ( isset( $shipping_last_name ) && $shipping_last_name != '' ) {
					$event_details['shipping_last_name'] = $shipping_last_name;
				}

				$shipping_address_1 = $order->get_shipping_address_1();
				if ( isset( $shipping_address_1 ) && $shipping_address_1 != '' ) {
					$event_details['shipping_address_1'] = $shipping_address_1;
				}

				$shipping_address_2 = $order->get_shipping_address_2();
				if ( isset( $shipping_address_2 ) && $shipping_address_2 != '' ) {
					$event_details['shipping_address_2'] = $shipping_address_2;
				}

				$shipping_city = $order->get_shipping_city();
				if ( isset( $shipping_city ) && $shipping_city != '' ) {
					$event_details['shipping_city'] = $shipping_city;
				}

				$_product   = wc_get_product( $item_details['product_id'] );
				$post_title = '';
				if ( false != $_product ) {
					$post_title = $_product->get_title();
				}
				$event_details['product_name']  = $post_title;
				$event_details['product_qty']   = $item_details['qty'];
				$event_details['product_total'] = $item_details['line_total'];

				if ( isset( $item_details['resource_id'] ) && $item_details['resource_id'] != '' ) {
					$event_details['resource'] = get_the_title( $item_details['resource_id'] );
				}

				if ( isset( $item_details['person_ids'] ) && $item_details['person_ids'] != '' ) {

					if ( isset( $item_details['person_ids'][0] ) ) {
						$person_info = Class_Bkap_Product_Person::bkap_get_person_label( $item_details['product_id'] ) . ' : ' . $item_details['person_ids'][0];
					} else {
						$person_info = '';
						foreach ( $item_details['person_ids'] as $p_key => $p_value ) {
							$person_info .= get_the_title( $p_key ) . ' : ' . $p_value . ',';
						}
					}
					$event_details['persons'] = $person_info;
				}

				$zoom_label                    = bkap_zoom_join_meeting_label( $item_details['product_id'] );
				$zoom_meeting                  = wc_get_order_item_meta( $item_details->get_id(), $zoom_label );
				$event_details['zoom_meeting'] = '';
				if ( '' != $zoom_meeting ) {
					$event_details['zoom_meeting'] = $zoom_label . ' - ' . $zoom_meeting;
				}

				return $event_details;
			}
		}

		/**
		 * This function deletes booking for the products in order
		 * when the order is cancelled or refunded.
		 *
		 * @hook woocommerce_order_status_cancelled
		 * @hook woocommerce_order_status_refunded
		 * @hook woocommerce_order_status_failed
		 *
		 * @param string|int $order Order ID.
		 *
		 * @globals mixed $wpdb
		 * @globals WP_Post $post - Order
		 *
		 * @since 1.7.0
		 */
		public static function bkap_woocommerce_cancel_order( $order_id ) {
			global $wpdb,$post;

			$array           = array();
			$order_obj       = wc_get_order( $order_id );
			$order_items     = $order_obj->get_items();
			$select_query    = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history` WHERE order_id= %d';
			$results         = $wpdb->get_results( $wpdb->prepare( $select_query, $order_id ) );
			$item_booking_id = 0;
			$booking_details = array();

			foreach ( $results as $key => $value ) {

				$select_query_post = 'SELECT post_id,start_date, end_date, from_time, to_time, weekday FROM `' . $wpdb->prefix . 'booking_history`
									WHERE id= %d';
				$results_post      = $wpdb->get_results( $wpdb->prepare( $select_query_post, $value->booking_id ) );

				if ( count( $results_post ) > 0 ) {
					$booking_info                          = array(
						'post_id'    => $results_post[0]->post_id,
						'start_date' => $results_post[0]->start_date,
						'end_date'   => $results_post[0]->end_date,
						'from_time'  => $results_post[0]->from_time,
						'to_time'    => $results_post[0]->to_time,
						'weekday'    => $results_post[0]->weekday,
					);
					$booking_details[ $value->booking_id ] = $booking_info;
					$item_booking_id                       = $value->booking_id;
				}
			}

			$booking_labels = bkap_booking_fields_label();

			$i = 0;
			foreach ( $order_items as $item_key => $item_value ) {

				if ( $item_value['_bkap_resch_rem_bal_order_id'] !== '' && $item_value['_bkap_resch_rem_bal_order_id'] !== null ) {

					$related_order = wc_get_order( $item_value['_bkap_resch_rem_bal_order_id'] );

					if ( $order_obj->has_status( 'cancelled' ) ) {
						$related_order->update_status( 'cancelled', 'Parent Order Cancelled.', false );
					} elseif ( $order_obj->has_status( 'refunded' ) ) {
						$related_order->update_status( 'refunded', 'Parent Order Refunded.', false );
					} elseif ( $order_obj->has_status( 'failed' ) ) {
						$related_order->update_status( 'failed', 'Parent Order Failed.', false );
					}
				}

				$all_booking_details = self::bkap_get_booking_item_meta( $item_key, $item_value, $booking_labels );

				foreach ( $all_booking_details as $key => $value ) {

					if ( 'cancelled' !== $value['wapbk_booking_status'] ) {
						self::bkap_reallocation_of_booking( $key, $value, $item_key, $item_value, $booking_details, $booking_labels, $order_id );
					}
				}
			}
		}

		/**
		 * This fucntion will find the matching booking based on the item data and reallocate the booking.
		 *
		 * @param int   $key Item Number.
		 * @param array $value Booking Item Data Array.
		 * @param int   $item_key Item ID.
		 * @param array $item_value Item Value Array.
		 * @param array $booking_details Booking Details with Booking ID of DB.
		 * @param array $booking_labels Booking Field Label Array.
		 * @param int   $order_id Order ID.
		 *
		 * @since 5.5.2
		 */
		public static function bkap_reallocation_of_booking( $key, $value, $item_key, $item_value, $booking_details, $booking_labels, $order_id ) {

			global $wpdb;

			$cancelled       = false;
			$item_booking_id = 0;
			// find the correct booking ID from the results array and pass the same.
			foreach ( $booking_details as $booking_id => $booking_data ) {

				if ( $item_value['product_id'] == $booking_data['post_id'] ) {
					// cross check the date and time as well as the product can be added to the cart more than once with different booking details.

					$duration_dates = array(); // duration start and end date is different then check for start date.
					if ( strpos( $value['hidden_date'], ' - ' ) !== false ) {
						$duration_dates = explode( ' - ', $value['hidden_date'] );
					}

					if ( $value['hidden_date'] == $booking_data['start_date']
					|| ( ! empty( $duration_dates ) && $duration_dates[0] == $booking_data['start_date'] )
						) {

						if ( isset( $booking_data['to_time'] ) && '' != $booking_data['to_time'] ) {
							$time = bkap_date_as_format( $booking_data['from_time'], 'H:i' ) . ' - ' . bkap_date_as_format( $booking_data['to_time'], 'H:i' );
						} else {
							$time = bkap_date_as_format( $booking_data['from_time'], 'H:i' );
						}

						$item_booking_id = $booking_id;

						if ( isset( $value['hidden_date_checkout'] ) ) {
							if ( $value['hidden_date_checkout'] == $booking_data['end_date'] ) {
								$item_booking_id = $booking_id;
								break;
							}
						} elseif ( isset( $value['wapbk_time_slot'] ) ) {
							if ( strpos( $value['wapbk_time_slot'], ',' ) > 0 ) {
								$time_slot_list = explode( ',', $value['wapbk_time_slot'] );
								foreach ( $time_slot_list as $t_key => $t_value ) {

									// Checking time as per timezone booking.
									if ( isset( $item_value['wapbk_timezone'] ) && $item_value['wapbk_timezone'] != '' ) {
										$offset  = bkap_get_offset( $item_value['wapbk_timeoffset'] );
										$t_value = bkap_convert_timezone_time_to_system_time( $t_value, $item_value, 'H:i' );
									}

									if ( $time == $t_value ) {
										$item_booking_id = $booking_id;
										self::bkap_reallot_item( $item_value, $item_booking_id, $order_id );
										$cancelled = true;
										// gcal sync for multiple time slots should be added here
									}
								}
							} else {

								$metatime = $value['wapbk_time_slot'];
								// Checking time as per timezone booking.
								if ( isset( $item_value['wapbk_timezone'] ) && $item_value['wapbk_timezone'] != '' ) {
									$offset   = bkap_get_offset( $item_value['wapbk_timeoffset'] );
									$metatime = bkap_convert_timezone_time_to_system_time( $metatime, $item_value, 'H:i' );
								}

								if ( $metatime == $time ) {
									$item_booking_id = $booking_id;
									break;
								}
							}
						} else {
							$item_booking_id = $booking_id;
							break;
						}
					}
				}
			}

			if ( ! $cancelled ) {

				// update the booking post status.
				$booking_ids     = bkap_common::get_booking_id( $item_key );
				$booking_post_id = is_array( $booking_ids ) ? $booking_ids[ $key ] : $booking_ids;

				$new_booking = bkap_checkout::get_bkap_booking( $booking_post_id );
				do_action( 'bkap_rental_delete', $new_booking, $booking_post_id );
				$new_booking->update_status( 'cancelled' );

				if ( isset( $new_booking ) && isset( $new_booking->qty ) ) {
					$bkap_qty = $new_booking->qty;
				}

				if ( isset( $bkap_qty ) ) {
					self::bkap_reallot_item( $item_value, $item_booking_id, $order_id, $bkap_qty );
				} else {
					self::bkap_reallot_item( $item_value, $item_booking_id, $order_id );
				}

				$delete_order_history = 'DELETE FROM `' . $wpdb->prefix . 'booking_order_history`
										WHERE order_id = %d AND booking_id = %d';
				$wpdb->query( $wpdb->prepare( $delete_order_history, $order_id, $item_booking_id ) );

				$product_id = bkap_common::bkap_get_product_id( $item_value['product_id'] );

				// Delete Zooom Meeting.
				if ( isset( $new_booking ) ) {
					Bkap_Zoom_Meeting_Settings::bkap_delete_zoom_meeting( $booking_post_id, $new_booking );
				}

				// check GCal sync.
				$user_id          = bkap_get_user_id();
				$booking_settings = bkap_setting( $product_id );

				if ( isset( $booking_settings['product_sync_integration_mode'] ) && in_array( $booking_settings['product_sync_integration_mode'], array( 'directly', 'oauth' ), true ) ) {
					$gcal_product_id = $product_id;
				} else {
					$gcal_product_id = 0;
				}

				// check if tour operators are allowed to setup GCal.
				if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
					// if tour operator addon is active, pass the tour operator user Id else the admin ID.
					if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {
						if ( isset( $booking_settings['booking_tour_operator'] ) && $booking_settings['booking_tour_operator'] != 0 ) {
							$user_id = $booking_settings['booking_tour_operator'];
						}
					}
				}

				require_once plugin_dir_path( __FILE__ ) . '/class.gcal.php';
				// get the mode for the product settings as well if applicable.
				$gcal = new BKAP_Gcal();
				if ( $gcal->get_api_mode( $user_id, $gcal_product_id ) != 'disabled' ) {
					$gcal->delete_event( $item_key, $user_id, $gcal_product_id );
				}

				do_action( 'bkap_reallocation_of_booking', $item_key, $item_value, $product_id, $order_id );
			}
		}

		/**
		 * This function will prepare the array for the booking details in the order item.
		 *
		 * @param int   $item_id Item ID.
		 * @param array $item Item Object.
		 * @param array $booking_labels Array of Booking Label.
		 *
		 * @since 5.5.2
		 */
		public static function bkap_get_booking_item_meta( $item_id, $item, $booking_labels ) {

			$book_item_meta_date     = $booking_labels['start_date'];
			$checkout_item_meta_date = $booking_labels['end_date'];
			$book_item_meta_time     = $booking_labels['time_slot'];

			$booking_ids               = bkap_common::get_booking_id( $item_id );
			$all_booking_details       = array();
			$date_meta                 = 0;
			$hidden_date_meta          = 0;
			$date_checkout_meta        = 0;
			$hidden_date_checkout_meta = 0;
			$time_slot_meta            = 0;
			$wapbk_time_slot_meta      = 0;
			$resource_id_meta          = 0;
			$status_meta               = 0;
			$i                         = 0;

			foreach ( $item->get_meta_data() as $meta_index => $meta ) {

				switch ( $meta->key ) {
					case $book_item_meta_date:
						$all_booking_details[ $date_meta ]['date'] = $meta->value;
						$date_meta++;
						break;
					case '_wapbk_booking_date':
						$hidden_date = explode( '-', $meta->value );
						$all_booking_details[ $hidden_date_meta ]['hidden_date'] = $meta->value;
						$hidden_date_meta++;
						break;
					case $checkout_item_meta_date:
						$all_booking_details[ $date_checkout_meta ]['date_checkout'] = $meta->value;
						$date_checkout_meta++;
						break;
					case '_wapbk_checkout_date':
						$hidden_date_checkout = explode( '-', $meta->value );
						$all_booking_details[ $hidden_date_checkout_meta ]['hidden_date_checkout'] = $hidden_date_checkout[2] . '-' . $hidden_date_checkout[1] . '-' . $hidden_date_checkout[0];
						$hidden_date_checkout_meta++;
						break;
					case $book_item_meta_time:
						$all_booking_details[ $time_slot_meta ]['time_slot'] = $meta->value;
						$time_slot_meta++;
						break;
					case '_wapbk_time_slot':
						$all_booking_details[ $wapbk_time_slot_meta ]['wapbk_time_slot'] = $meta->value;
						$wapbk_time_slot_meta++;
						break;
					case '_resource_id':
						$all_booking_details[ $resource_id_meta ]['resource_id'] = $meta->value;
						$resource_id_meta++;
						break;
					case '_wapbk_booking_status':
						$all_booking_details[ $status_meta ]['wapbk_booking_status'] = $meta->value;
						$status_meta++;
						break;
				}
			}

			return $all_booking_details;
		}

		/**
		 * Re-allots the booking date and/or time for each item in the order
		 *
		 * @param array $item_value Order Item Values.
		 * @param int   $booking_id Booking History Table ID.
		 * @param int   $order_id Order ID.
		 * @param int   $bkap_qty Quantity.
		 *
		 * @globals mixed $wpdb
		 * @globals WP_Post $post
		 *
		 * @since 1.7.0
		 */

		public static function bkap_reallot_item( $item_value, $booking_id, $order_id, $bkap_qty = null ) {
			global $wpdb;
			global $post;

			$product_id = bkap_common::bkap_get_product_id( $item_value['product_id'] );
			$_product   = wc_get_product( $product_id );
			$parent_id  = 0;

			/**
			 * It will confirm that we have the product object.
			 * If it is boolean then we will not fetch the parent id.
			 */
			if ( is_bool( $_product ) === false ) {
				$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $product_id );
			}

			$details = array();

			$variation_id = '';
			if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) >= 0 ) {
				$variation_id = $item_value->get_variation_id();
			} elseif ( array_key_exists( 'variation_id', $item_value ) ) {
				$variation_id = $item_value['variation_id'];
			}

			$booking_settings = bkap_setting( $product_id );
			$booking_type     = get_post_meta( $product_id, '_bkap_booking_type', true );

			if ( $bkap_qty != null && $bkap_qty >= 0 ) {
				$qty = $bkap_qty;
			} elseif ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) >= 0 ) {
				$qty = $item_value->get_quantity();
			} else {
				$qty = $item_value['qty'];
			}

			if ( isset( $variation_id ) && $variation_id != 0 ) {
				// Product Attributes - Booking Settings.
				$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

				if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
					$attr_qty = 0;

					if ( $bkap_qty != null && $bkap_qty >= 0 ) {
						$attr_qty = $bkap_qty;
					} else {

						foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {

							// check if the setting is on.
							if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] ) {

								if ( array_key_exists( $attr_name, $item_value ) && $item_value[ $attr_name ] != 0 ) {
									$attr_qty += $item_value[ $attr_name ];
								}
							}
						}
						if ( isset( $attr_qty ) && $attr_qty > 0 ) {
							$attr_qty = $attr_qty * $item_value['qty'];
						}
					}
				}
			}

			if ( isset( $attr_qty ) && $attr_qty > 0 ) {
				$qty = $attr_qty;
			}

			$from_time  = '';
			$to_time    = '';
			$date_date  = '';
			$end_date   = '';
			$start_date = '';

			switch ( $booking_type ) {
				case 'multiple_days':
					if ( isset( $parent_id ) && $parent_id != '' ) {

						// double the qty as we need to delete records for the child product as well as the parent product.
						$qty              += $qty;
						$booking_id       += 1;
						$first_record_id   = $booking_id - $qty;
						$first_record_id  += 1;
						$select_data_query = 'DELETE FROM `' . $wpdb->prefix . 'booking_history`
													WHERE ID BETWEEN %d AND %d';
						$results_data      = $wpdb->query( $wpdb->prepare( $select_data_query, $first_record_id, $booking_id ) );
					} else {
						// if parent ID is not found, means its a normal product.
						// DELETE the records using the ID in the booking history table.
						// The ID in the order history table, is the last record inserted for the order, so find the first ID by subtracting the qty.
						$first_record_id = $booking_id - $qty;

						$first_record_id += 1;

						$select_data_query = 'DELETE FROM `' . $wpdb->prefix . 'booking_history`
												WHERE ID BETWEEN %d AND %d';
						$results_data      = $wpdb->query( $wpdb->prepare( $select_data_query, $first_record_id, $booking_id ) );

					}
					break;
				case 'date_time':
				case 'multidates_fixedtime':
					$type_of_slot = apply_filters( 'bkap_slot_type', $product_id );
					$ovelapping   = bkap_booking_overlapping_timeslot( bkap_global_setting(), $product_id );

					if ( $type_of_slot == 'multiple' ) {
						do_action( 'bkap_order_status_cancelled', $order_id, $item_value, $booking_id );
					} else {
						$select_data_query = 'SELECT * FROM `' . $wpdb->prefix . 'booking_history`
											WHERE id= %d';
						$results_data      = $wpdb->get_results( $wpdb->prepare( $select_data_query, $booking_id ) );
						$j                 = 0;

						foreach ( $results_data as $k => $v ) {
							$start_date = $results_data[ $j ]->start_date;
							$from_time  = $results_data[ $j ]->from_time;
							$to_time    = $results_data[ $j ]->to_time;

							if ( $from_time != '' && $to_time != '' || $from_time != '' ) {
								$parent_query = '';
								if ( $to_time != '' ) {

									if ( $ovelapping ) {
										// overlapaing time slots free booking product level.
										$query              = 'SELECT from_time, to_time, available_booking  FROM `' . $wpdb->prefix . "booking_history`
									WHERE post_id = '" . $product_id . "' AND
									start_date = '" . $start_date . "' AND
									status != 'inactive' ";
										$get_all_time_slots = $wpdb->get_results( $query );

										foreach ( $get_all_time_slots as $time_slot_key => $time_slot_value ) {

											$query_from_time_time_stamp      = strtotime( $from_time );
											$query_to_time_time_stamp        = strtotime( $to_time );
											$time_slot_value_from_time_stamp = strtotime( $time_slot_value->from_time );
											$time_slot_value_to_time_stamp   = strtotime( $time_slot_value->to_time );

											$revised_available_booking = $time_slot_value->available_booking + $qty;

											if ( $query_to_time_time_stamp > $time_slot_value_from_time_stamp && $query_from_time_time_stamp < $time_slot_value_to_time_stamp ) {

												if ( $time_slot_value_from_time_stamp != $query_from_time_time_stamp || $time_slot_value_to_time_stamp != $query_to_time_time_stamp ) {
													$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
															SET available_booking = ' . $revised_available_booking . "
															WHERE post_id = '" . $product_id . "' AND
															start_date = '" . $start_date . "' AND
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
												SET available_booking = available_booking + ' . $qty . "
												WHERE
												id = '" . $booking_id . "' AND
												start_date = '" . $start_date . "' AND
												from_time = '" . $from_time . "' AND
												to_time = '" . $to_time . "' AND
												status != 'inactive' AND
												total_booking > 0";
									// Update records for parent products - Grouped Products.
									if ( isset( $parent_id ) && $parent_id != '' ) {
										$parent_query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
															 SET available_booking = available_booking + ' . $qty . "
															 WHERE
															 post_id = '" . $parent_id . "' AND
															 start_date = '" . $start_date . "' AND
															 from_time = '" . $from_time . "' AND
															 to_time = '" . $to_time . "' AND
															 status != 'inactive' AND 
															 total_booking > 0";

										$wpdb->query( $parent_query );

										$select = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
															WHERE post_id = %d AND
															start_date = %s AND
															from_time = %s AND
															to_time = %s AND
															status != 'inactive'";

										$select_results = $wpdb->get_results( $wpdb->prepare( $select, $parent_id, $start_date, $from_time, $to_time ) );

										foreach ( $select_results as $k => $v ) {
											$details[ $product_id ] = $v;
										}
									}

									$select         = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
														 WHERE post_id = %d AND
														 start_date = %s AND
														 from_time = %s AND
														 to_time = %s AND
														 status != 'inactive' ";
									$select_results = $wpdb->get_results( $wpdb->prepare( $select, $product_id, $start_date, $from_time, $to_time ) );

									foreach ( $select_results as $k => $v ) {
										$details[ $product_id ] = $v;
									}
								} else {
									$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
												  SET available_booking = available_booking + ' . $qty . "
												  WHERE
												  id = '" . $booking_id . "' AND
												  start_date = '" . $start_date . "' AND
												  from_time = '" . $from_time . "' AND
												  status != 'inactive' AND 
												  total_booking > 0";

									// Update records for parent products - Grouped Products.
									if ( isset( $parent_id ) && $parent_id != '' ) {
										$parent_query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
															SET available_booking = available_booking + ' . $qty . "
															WHERE
															post_id = '" . $parent_id . "' AND
															start_date = '" . $start_date . "' AND
															from_time = '" . $from_time . "' AND
															status != 'inactive' AND 
															total_booking > 0";

										$wpdb->query( $parent_query );

										$select         = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
															WHERE post_id = %d AND
															start_date = %s AND
															from_time = %s AND
															status != 'inactive' ";
										$select_results = $wpdb->get_results( $wpdb->prepare( $select, $parent_id, $start_date, $from_time ) );

										foreach ( $select_results as $k => $v ) {
											$details[ $product_id ] = $v;
										}
									}

									$select         = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
														 WHERE post_id = %d AND
														 start_date = %s AND
														 from_time = %s AND 
														 status != 'inactive' ";
									$select_results = $wpdb->get_results( $wpdb->prepare( $select, $product_id, $start_date, $from_time ) );

									foreach ( $select_results as $k => $v ) {
										$details[ $product_id ] = $v;
									}
								}
								// Run the Update query for the product
								$wpdb->query( $query );
							}
							$j++;
						}
						self::reallot_global_timeslot( $start_date, $from_time, $to_time, $booking_settings, $details, $qty );
					}
					break;
				case 'only_day':
				case 'multidates':
					$select_data_query = 'SELECT * FROM `' . $wpdb->prefix . 'booking_history`
										 WHERE id= %d';
					$results_data      = $wpdb->get_results( $wpdb->prepare( $select_data_query, $booking_id ) );
					$j                 = 0;

					foreach ( $results_data as $k => $v ) {
						$start_date = $results_data[ $j ]->start_date;
						$from_time  = $results_data[ $j ]->from_time;
						$to_time    = $results_data[ $j ]->to_time;
						$query      = 'UPDATE `' . $wpdb->prefix . 'booking_history`
											SET available_booking = available_booking + ' . $qty . "
											WHERE
											id = '" . $booking_id . "' AND
											start_date = '" . $start_date . "' AND
											from_time = '' AND
											to_time = '' AND
											status != 'inactive' AND 
											total_booking > 0";
						$wpdb->query( $query );

						// Update records for parent products - Grouped Products
						if ( isset( $parent_id ) && $parent_id != '' ) {
							$parent_query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
												SET available_booking = available_booking + ' . $qty . "
												WHERE
												post_id = '" . $parent_id . "' AND
												start_date = '" . $start_date . "' AND
												from_time = '' AND
												to_time = '' AND
												status != 'inactive' AND 
												total_booking > 0";
							$wpdb->query( $parent_query );
						}
					}
					$j++;
					break;
				case 'duration_time':
					if ( isset( $parent_id ) && $parent_id != '' ) {

						// double the qty as we need to delete records for the child product as well as the parent product
						$qty              += $qty;
						$booking_id       += 1;
						$first_record_id   = $booking_id - $qty;
						$first_record_id  += 1;
						$select_data_query = 'DELETE FROM `' . $wpdb->prefix . 'booking_history`
													WHERE ID BETWEEN %d AND %d';
						$results_data      = $wpdb->query( $wpdb->prepare( $select_data_query, $first_record_id, $booking_id ) );
					} else { // if parent ID is not found, means its a normal product
						// DELETE the records using the ID in the booking history table.
						// The ID in the order history table, is the last record inserted for the order, so find the first ID by subtracting the qty
						$first_record_id = $booking_id - $qty;

						$first_record_id += 1;

						$select_data_query = 'DELETE FROM `' . $wpdb->prefix . 'booking_history`
												WHERE ID BETWEEN %d AND %d';

						$results_data = $wpdb->query( $wpdb->prepare( $select_data_query, $first_record_id, $booking_id ) );
					}
					break;
			}
		}

		/**
		 * Reallot the global timeslot when a booking is trashed
		 *
		 * @param string $start_date Start Date.
		 * @param string $from_time From Time.
		 * @param string $to_time To Time.
		 * @param mixed  $booking_settings Booking Settings.
		 * @param mixed  $details Booking Details.
		 * @param int    $qty Quantity.
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 2.3.5
		 */
		public static function reallot_global_timeslot( $start_date, $from_time, $to_time, $booking_settings, $details, $qty ) {
			global $wpdb;

			$book_global_settings    = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$global_timeslot_lockout = '';
			$label                   = get_option( 'book_item-meta-date' );
			$hidden_date             = '';
			$ovelapping              = bkap_booking_overlapping_timeslot( $book_global_settings );

			if ( isset( $start_date ) && $start_date != '' ) {
				$hidden_date = date( 'd-n-Y', strtotime( $start_date ) );
			}

			if ( isset( $booking_settings['booking_time_settings'][ $hidden_date ] ) ) {
				$lockout_settings = $booking_settings['booking_time_settings'][ $hidden_date ];
			} else {
				$lockout_settings = array();
			}

			if ( count( $lockout_settings ) == 0 ) {
				$week_day = date( 'l', strtotime( $hidden_date ) );
				$weekdays = bkap_get_book_arrays( 'bkap_weekdays' );
				$weekday  = array_search( $week_day, $weekdays );
				if ( isset( $booking_settings['booking_time_settings'][ $weekday ] ) ) {
					$lockout_settings = $booking_settings['booking_time_settings'][ $weekday ];
				} else {
					$lockout_settings = array();
				}
			}

			if ( count( $lockout_settings ) > 0 ) {
				$week_day = date( 'l', strtotime( $hidden_date ) );
				$weekdays = bkap_get_book_arrays( 'bkap_weekdays' );
				$weekday  = array_search( $week_day, $weekdays );

				if ( isset( $booking_settings['booking_time_settings'][ $weekday ] ) ) {
					$lockout_settings = $booking_settings['booking_time_settings'][ $weekday ];
				} else {
					$lockout_settings = array();
				}
			}

			$from_time = date( 'H:i', strtotime( $from_time ) );

			$from_lockout_time = explode( ':', $from_time );
			if ( isset( $from_lockout_time[0] ) ) {
				$from_hours = $from_lockout_time[0];
			} else {
				$from_hours = '';
			}

			if ( isset( $from_lockout_time[1] ) ) {
				$from_minute = $from_lockout_time[1];
			} else {
				$from_minute = '';
			}

			if ( $to_time != '' ) {
				$to_time         = date( 'H:i', strtotime( $to_time ) );
				$to_lockout_time = explode( ':', $to_time );
				$to_hours        = $to_lockout_time[0];
				$to_minute       = $to_lockout_time[1];
			} else {
				$to_hours  = '';
				$to_minute = '';
			}

			if ( count( $lockout_settings ) > 0 ) {

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

				foreach ( $product_ids as $k => $v ) {

					$duplicate_of     = bkap_common::bkap_get_product_id( $v );
					$booking_settings = bkap_setting( $duplicate_of );

					if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {

						if ( isset( $details ) && count( $details ) > 0 ) {

							if ( ! array_key_exists( $duplicate_of, $details ) ) {

								foreach ( $details as $key => $val ) {
									$start_date = $val->start_date;
									$from_time  = date( 'H:i', strtotime( $val->from_time ) );

									$revised_available_booking = '';
									if ( $val->to_time != '' ) {
										$to_time = date( 'H:i', strtotime( $val->to_time ) );

										if ( $ovelapping ) {

											// over lapaing time slots free booking product level.
											$query              = 'SELECT from_time, to_time, available_booking  FROM `' . $wpdb->prefix . "booking_history`
													WHERE post_id = '" . $duplicate_of . "' AND
													start_date = '" . $start_date . "' AND
													status !=  'inactive' ";
											$get_all_time_slots = $wpdb->get_results( $query );

											foreach ( $get_all_time_slots as $time_slot_key => $time_slot_value ) {

												$query_from_time_time_stamp      = strtotime( $from_time );
												$query_to_time_time_stamp        = strtotime( $to_time );
												$time_slot_value_from_time_stamp = strtotime( $time_slot_value->from_time );
												$time_slot_value_to_time_stamp   = strtotime( $time_slot_value->to_time );

												if ( $query_to_time_time_stamp > $time_slot_value_from_time_stamp && $query_from_time_time_stamp < $time_slot_value_to_time_stamp ) {

													if ( $time_slot_value_from_time_stamp != $query_from_time_time_stamp || $time_slot_value_to_time_stamp != $query_to_time_time_stamp ) {
														$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
																	SET available_booking = available_booking + ' . $qty . "
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
										}

										$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
												SET available_booking = available_booking + ' . $qty . "
												WHERE post_id = '" . $duplicate_of . "' AND
												start_date = '" . $start_date . "' AND
												TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_time . "' AND
												TIME_FORMAT( to_time, '%H:%i' ) = '" . $to_time . "' AND
												status != 'inactive' AND 
												total_booking > 0";
										$wpdb->query( $query );
									} else {
										$query = 'UPDATE `' . $wpdb->prefix . 'booking_history`
													  SET available_booking = available_booking + ' . $qty . "
													  WHERE post_id = '" . $duplicate_of . "' AND
													  start_date = '" . $start_date . "' AND
													  TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_time . "' AND
													  status != 'inactive' AND 
													  total_booking > 0";
										$wpdb->query( $query );
									}
								}
							}
						}
					}
				}
			}
		}

		/**
		 * This function is used to update/reallocate the booking based on the increase decrease of quantity of item from Edit order page.
		 *
		 * @param int   $order_id Order ID.
		 * @param array $item Array of Item information.
		 *
		 * @since 5.9.0
		 */
		public static function bkap_edit_quantity_from_edit_order( $order_id, $item ) {

			foreach ( $item['order_item_id'] as $key => $value ) {
				$item_id    = $item['order_item_id'][ $key ];
				$booking_id = bkap_common::get_booking_id( $item_id ); // get the booking ID using the item ID.

				if ( $booking_id ) {
					$booking  = bkap_checkout::get_bkap_booking( $booking_id );
					$bkap_qty = $booking->get_quantity();
					$item_qty = $item['order_item_qty'][ $item_id ];
					if ( $item_qty != $bkap_qty ) {
						if ( $item_qty < $bkap_qty ) {
							$quantity = $bkap_qty - $item_qty;
							self::bkap_reallocate_booking_upon_refund( $item_id, $order_id, $quantity );
						} elseif ( $item_qty > $bkap_qty ) {
							$quantity = $item_qty - $bkap_qty;
							self::bkap_additional_booking_quantity( $item_id, $order_id, $quantity );
						}
					}
				}
			}
		}

		/**
		 * This function is used to assign additional booking quantity from edit order page.
		 *
		 * @param int $item_id Order Item ID.
		 * @param int $order_id Order ID.
		 * @param int $quantity Quantity.
		 *
		 * @since 5.9.0
		 */
		public static function bkap_additional_booking_quantity( $item_id, $order_id, $quantity ) {

			$order       = wc_get_order( $order_id );
			$details     = array();
			$php_version = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );

			foreach ( $order->get_items() as $order_item_id => $item ) {

				if ( $order_item_id != $item_id ) {
					continue;
				}

				$booking          = array();
				$product_bookable = '';
				$parent_id        = $php_version ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $item['product_id'] );
				$post_id          = bkap_common::bkap_get_product_id( $item['product_id'] );
				$quantity         = $quantity;
				$product_bookable = bkap_common::bkap_get_bookable_status( $post_id );
				$booking_type     = get_post_meta( $post_id, '_bkap_booking_type', true );

				if ( $product_bookable ) {

					// get booking post ID.
					$booking_id = bkap_common::get_booking_id( $order_item_id );

					// update the booking post status.
					if ( $booking_id ) {

						$booking_post = bkap_checkout::get_bkap_booking( $booking_id );
						$bkap_qty     = $booking_post->get_quantity();
						$booking      = array(
							'date'                 => $item['_wapbk_booking_date'],
							'hidden_date'          => date( 'd-m-Y', strtotime( $item['_wapbk_booking_date'] ) ),
							'date_checkout'        => $item['wapbk_checkout_date'],
							'hidden_date_checkout' => date( 'd-m-Y', strtotime( $item['_wapbk_checkout_date'] ) ),
							'price'                => $item['cost'],
							'time_slot'            => $item['_wapbk_time_slot'],
						);

						$booking_check = array(
							'product_id'           => $post_id,
							'hidden_date'          => date( 'd-m-Y', strtotime( $item['_wapbk_booking_date'] ) ),
							'qty'                  => $quantity,
							'post_id'              => $booking_id,
							'booking_type'         => $booking_type,
							'hidden_date_checkout' => date( 'd-m-Y', strtotime( $item['_wapbk_checkout_date'] ) ),
							'time_slot'            => $item['_wapbk_time_slot'],
							'edit_from'            => 'order',
						);

						$sanity_results = self::bkap_sanity_check( $booking_check );
						if ( count( $sanity_results ) > 0 ) {
							foreach ( $sanity_results as $sr ) {
								$order->add_order_note( $sr );
							}
						} else {
							$details = bkap_checkout::bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking );
							// update the global time slot lockout.
							if ( isset( $booking['time_slot'] ) && $booking['time_slot'] != '' ) {
								bkap_checkout::bkap_update_global_lockout( $post_id, $quantity, $details, $booking );
							}

							if ( $booking_id ) {
								update_post_meta( $booking_id, '_bkap_qty', $quantity + $bkap_qty );
							}
						}
					}
				}
			}
		}

		/**
		 * This will reallocate the bookings when order status changed from failed to processing, completed and on-hold.
		 * Also, it will change the booking status to paid.
		 *
		 * @hook woocommerce_order_status_changed
		 *
		 * @param int    $order_id Order ID.
		 * @param string $old_status Old Order Status.
		 * @param string $new_status New Order Status.
		 *
		 * @since 4.2.0
		 */
		public static function bkap_reallocate_booking_when_order_status_failed_to_processing( $order_id, $old_status, $new_status ) {

			$order   = wc_get_order( $order_id );
			$details = array();

			$php_version = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );

			if ( 'failed' === $old_status && in_array( $new_status, array( 'processing', 'completed', 'on-hold', 'pending', 'cod' ), true ) ) {

				foreach ( $order->get_items() as $order_item_id => $item ) {

					$booking          = array();
					$product_bookable = '';
					$parent_id        = $php_version ? $_product->get_parent() : bkap_common::bkap_get_parent_id( $item['product_id'] );
					$post_id          = bkap_common::bkap_get_product_id( $item['product_id'] );
					$quantity         = $item['qty'];
					$product_bookable = bkap_common::bkap_get_bookable_status( $post_id );
					$booking_type     = get_post_meta( $post_id, '_bkap_booking_type', true );

					if ( $product_bookable ) {

						// get booking post ID.
						$booking_id = bkap_common::get_booking_id( $order_item_id );

						// update the booking post status.
						if ( $booking_id ) {

							$booking_post = bkap_checkout::get_bkap_booking( $booking_id );
							$status       = $booking_post->get_status();

							if ( 'cancelled' === $status ) {
								$booking = array(
									'date'                 => $item['_wapbk_booking_date'],
									'hidden_date'          => date( 'd-m-Y', strtotime( $item['_wapbk_booking_date'] ) ),
									'date_checkout'        => $item['wapbk_checkout_date'],
									'hidden_date_checkout' => date( 'd-m-Y', strtotime( $item['_wapbk_checkout_date'] ) ),
									'price'                => $item['cost'],
									'time_slot'            => $item['_wapbk_time_slot'],
								);

								$booking_check = array(
									'product_id'           => $post_id,
									'hidden_date'          => date( 'd-m-Y', strtotime( $item['_wapbk_booking_date'] ) ),
									'qty'                  => $quantity,
									'post_id'              => $booking_id,
									'booking_type'         => $booking_type,
									'hidden_date_checkout' => date( 'd-m-Y', strtotime( $item['_wapbk_checkout_date'] ) ),
									'time_slot'            => $item['_wapbk_time_slot'],
									'edit_from'            => 'order',
								);

								$sanity_results = self::bkap_sanity_check( $booking_check );
								if ( count( $sanity_results ) > 0 ) {
									foreach ( $sanity_results as $sr ) {
										$order->add_order_note( $sr );
									}
								} else {
									$details = bkap_checkout::bkap_update_lockout( $order_id, $post_id, $parent_id, $quantity, $booking );
									// update the global time slot lockout.
									if ( isset( $booking['time_slot'] ) && $booking['time_slot'] != '' ) {
										bkap_checkout::bkap_update_global_lockout( $post_id, $quantity, $details, $booking );
									}

									$booking_status = wc_get_order_item_meta( $order_item_id, '_wapbk_booking_status' );

									if ( isset( $booking_status ) && 'confirmed' == $booking_status ) {
										wc_update_order_item_meta( $order_item_id, '_wapbk_booking_status', 'paid' );

										// update the booking post status.
										if ( $booking_id ) {
											$booking_post->update_status( 'paid' );
										}

										bkap_insert_event_to_gcal( $order, $post_id, $order_item_id );
									}
								}
							}
						}
					}
				}
			}

			if ( 'failed' !== $old_status && in_array( $new_status, array( 'processing', 'on-hold', 'completed', 'cod' ), true ) ) {

				// Issue #4878. We need to stop the Booking Status from being changed to paid when the Booking is just newly created. WooCommerce changes the Order Status from Pending to On-Hold which triggers the WC Order Status change hook.
				if ( 'pending' === $old_status && 'on-hold' === $new_status ) {
					return;
				}

				$order_items = $order->get_items();
				foreach ( $order_items as $item_key => $item_value ) {
					$booking_status = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );
					if ( isset( $booking_status ) && 'confirmed' === $booking_status ) {
						$status = apply_filters( 'bkap_booking_status_on_create_order', 'paid' );
						wc_update_order_item_meta( $item_key, '_wapbk_booking_status', $status );
						$booking_id = bkap_common::get_booking_id( $item_key );
						if ( $booking_id ) {
							$new_booking = bkap_checkout::get_bkap_booking( $booking_id );
							$new_booking->update_status( $status );
						}
					}
				}
			}
		}

		/**
		 * Checks the details of the booking post being
		 * edited. In case of any errors, it returns the
		 * list of errors.
		 *
		 * @param array $booking Booking Data.
		 * @return array Results of the sanity check.
		 * @since 4.2.0
		 */
		public static function bkap_sanity_check( $booking ) {

			$results         = array();
			$product_id      = $booking['product_id'];
			$start           = $booking['hidden_date'];
			$qty             = $booking['qty'];
			$booking_post_id = $booking['post_id'];

			$booking_type = $booking['booking_type'];
			$qty_check    = true;
			$date_check   = true; // date/s and/or time is valid
			$current_time = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );

			switch ( $booking_type ) {
				case 'multiple_days':
					$end             = $booking['hidden_date_checkout'];
					$end_timestamp   = strtotime( $end );
					$start_timestamp = strtotime( $start );

					if ( $start === '' || $end === '' || $end_timestamp < $current_time ) {
						$date_check = false;
					} else {
						$bookings = get_bookings_for_range( $product_id, $start_timestamp, $end_timestamp );

						$order_dates = bkap_common::bkap_get_betweendays( date( 'd-n-Y', strtotime( $start ) ), date( 'd-n-Y', $end_timestamp ) );

						$least_availability = '';
						// get the least available bookings for the range.
						foreach ( $order_dates as $date ) {
							$lockout       = get_date_lockout( $product_id, $date );
							$new_available = 0;

							$date_ymd          = date( 'Ymd', strtotime( $date ) );
							$bookings_for_date = ( isset( $bookings[ $date_ymd ] ) ) ? $bookings[ $date_ymd ] : 0;

							if ( absint( $lockout ) > 0 ) {
								$new_available = $lockout - $bookings_for_date;
							}

							if ( $least_availability === '' ) {
								$least_availability = $new_available;
							}

							if ( $least_availability > $new_available ) {
								$least_availability = $new_available;
							}
						}

						// change in qty
						$old_qty = get_post_meta( $booking_post_id, '_bkap_qty', true );
						$change  = $qty - $old_qty; // assume change is always an increase

						if ( $change > 0 ) {
							if ( $change > $least_availability ) {
								$qty_check = false;
							}
						}

						if ( isset( $booking['edit_from'] ) && 'order' === $booking['edit_from'] ) {
							if ( $qty > $least_availability ) {
								$qty_check = false;
							}
						}
					}
					break;
				case 'only_day':
					if ( $start === '' || strtotime( $start ) < $current_time ) { // Date is blank or past date
						$date_check = false;
					} else {
						// returns an array for all the bookings received for the set date
						$dates = get_bookings_for_date( $product_id, $start );
						// returns an array containing the available bookings and if unlimited bookings are allowed or no
						$get_availability  = get_availability_for_date( $product_id, $start, $dates );
						$available_tickets = $get_availability['available'];
						$unlimited         = $get_availability['unlimited'];

						// change in qty
						$old_qty = get_post_meta( $booking_post_id, '_bkap_qty', true );
						$change  = $qty - $old_qty; // assume change is always an increase

						if ( $change > 0 ) {
							if ( $unlimited === 'NO' ) {
								if ( $change > $available_tickets ) {
									$qty_check = false;
								}
							}
						}
					}
					break;
				case 'date_time':
					$time = '00:00';

					if ( $booking['time_slot'] != '' ) {
						$time_slot     = $booking['time_slot'];
						$exploded_time = explode( '-', $time_slot );
						$time          = date( 'H:i', strtotime( $exploded_time[0] ) );
						if ( isset( $exploded_time[1] ) && '' !== $exploded_time[1] ) {
							$time .= ' - ' . date( 'H:i', strtotime( $exploded_time[1] ) );
						}
					}

					if ( $start === '' || $time === '00:00' ) {
						$date_check = false;
					} else {
						// returns an array for all the bookings received for the set date
						$dates        = get_bookings_for_date( $product_id, $start );
						$availability = get_slot_availability( $product_id, $start, $time, $dates );

						if ( $availability['unlimited'] === 'NO' ) {
							// change in qty
							$old_qty = get_post_meta( $booking_post_id, '_bkap_qty', true );
							$change  = $qty - $old_qty; // assume change is always an increase

							if ( $change > $availability['available'] ) {
								$qty_check = false;
							}
						}
					}
					break;

				case 'duration_time':
					$time_slot = '';
					if ( $booking['duration_time_slot'] != '' ) {
						$time_slot = $booking['duration_time_slot'];
					}

					if ( $start === '' || $time_slot == '' ) {
						$date_check = false;
					} else {

						$d_setting = get_post_meta( $product_id, '_bkap_duration_settings', true );

						if ( $d_setting['duration_max_booking'] != '' && $d_setting['duration_max_booking'] > 0 ) {
							// change in qty
							// $old_qty 	= get_post_meta( $booking_post_id, '_bkap_qty', true );
							// $change 	= $qty - $old_qty; //assume change is always an increase

							if ( $qty > $d_setting['duration_max_booking'] ) {
								$qty_check = false;
							}
						}
					}

					break;
			}

			if ( ! $qty_check ) {
				$qty_msg = __( 'Quantity being set is not available for the desired date.', 'woocommerce-booking' );
				if ( isset( $booking['edit_from'] ) && 'order' === $booking['edit_from'] ) {
					$qty_msg = 'Booking #' . $booking_post_id . ' - ' . $qty_msg;
				}
				$results[] = $qty_msg;
			}

			if ( ! $date_check ) {
				$results[] = __( 'The Booking details are incorrect. Please fill them up correctly.', 'woocommerce-booking' );
			}

			return $results;
		}

		/**
		 * Trashes/Deletes the booking and item from the order.
		 *
		 * @since 4.1.0
		 * @globals mixed $wpdb wpdb global variable
		 */
		public static function bkap_trash_booking() {
			$booking_post_id = $_POST['booking_id'];
			self::bkap_delete_booking( $booking_post_id );
		}

		/**
		 * Deletes the booking item from the order
		 * and sets the booking status to cancelled
		 *
		 * @param int $booking_post_id Booking Post ID to delete
		 * @globals mixed $wpdb global variable
		 *
		 * @since 4.2.0
		 */
		static function bkap_delete_booking( $booking_post_id ) {

			global $wpdb;

			$new_booking    = bkap_checkout::get_bkap_booking( $booking_post_id ); // Booking Post
			$booking_status = $new_booking->get_status();

			$item_id       = get_post_meta( $booking_post_id, '_bkap_order_item_id', true );
			$product_id    = get_post_meta( $booking_post_id, '_bkap_product_id', true );
			$booking_start = get_post_meta( $booking_post_id, '_bkap_start', true );
			$booking_end   = get_post_meta( $booking_post_id, '_bkap_end', true );
			$booking_type  = get_post_meta( $product_id, '_bkap_booking_type', true );

			// get the order ID
			$order_query   = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
								  WHERE order_item_id = %s';
			$order_results = $wpdb->get_results( $wpdb->prepare( $order_query, $item_id ) );

			$order_id = $order_results[0]->order_id;

			if ( $order_id > 0 ) {

				$order_obj   = wc_get_order( absint( $order_id ) );
				if ( ! $order_obj ) {
					return;
				}
				$order_items = $order_obj->get_items();

				foreach ( $order_items as $oid => $o_value ) {
					if ( $oid == $item_id ) {
						$item_value = $o_value;
						break;
					}
				}

				if ( isset( $item_value ) ) {
					$get_booking_id  = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history`
											 WHERE order_id = %d';
					$results_booking = $wpdb->get_results( $wpdb->prepare( $get_booking_id, $order_id ) );

					foreach ( $results_booking as $id ) {

						$get_booking_details = 'SELECT post_id, start_date, end_date, from_time, to_time FROM `' . $wpdb->prefix . 'booking_history`
													WHERE id = %d';
						$bkap_details        = $wpdb->get_results( $wpdb->prepare( $get_booking_details, $id->booking_id ) );

						$matched = false;

						if ( isset( $bkap_details[0] ) && $bkap_details[0]->post_id == $product_id ) {

							$start_date = substr( $booking_start, 0, 8 );
							$start_date = date( 'Y-m-d', strtotime( $start_date ) );

							switch ( $booking_type ) {
								case 'only_day':
									if ( $start_date === $bkap_details[0]->start_date ) {
										$booking_id = $id->booking_id;
										$matched    = true;
									}
									break;
								case 'multiple_days':
									$end_date = substr( $booking_end, 0, 8 );
									$end_date = date( 'Y-m-d', strtotime( $end_date ) );
									if ( $start_date === $bkap_details[0]->start_date && $end_date === $bkap_details[0]->end_date ) {
										$booking_id = $id->booking_id;
										$matched    = true;
									}
									break;
								case 'date_time':
									$ft = date( 'H:i', strtotime( $bkap_details[0]->from_time ) );
									$tt = date( 'H:i', strtotime( $bkap_details[0]->to_time ) );

									$db_time_slot = $ft . '-' . $tt;
									$meta_time    = substr( $booking_start, 8, 2 ) . ':' . substr( $booking_start, 10, 2 ) . '-' . substr( $booking_end, 8, 2 ) . ':' . substr( $booking_end, 10, 2 );
									if ( $start_date === $bkap_details[0]->start_date && $meta_time === $db_time_slot ) {
										$booking_id = $id->booking_id;
										$matched    = true;
									}
									break;
								case 'duration_time':
									$db_time_slot = $bkap_details[0]->from_time . '-' . $bkap_details[0]->to_time;

									$meta_time = substr( $booking_start, 8, 2 ) . ':' . substr( $booking_start, 10, 2 ) . '-' . substr( $booking_end, 8, 2 ) . ':' . substr( $booking_end, 10, 2 );

									if ( $start_date === $bkap_details[0]->start_date && $meta_time === $db_time_slot ) {
										$booking_id = $id->booking_id;
										$matched    = true;
									}
									break;
							}

							if ( $matched ) {
								break;
							}
						}
					}

					if ( isset( $booking_id ) && $booking_id > 0 && 'cancelled' != $booking_status ) {

						do_action( 'bkap_before_delete_booking_post', $booking_post_id, $new_booking );

						bkap_delete_event_from_gcal( $product_id, $item_id );

						self::bkap_reallot_item( $item_value, $booking_id, $order_id ); // cancel the booking

						if ( 'multiple_days' !== $booking_type ) { // delete the order from booking order history

							bkap_delete_from_order_hitory( $order_id, $booking_id );
						}

						do_action( 'bkap_rental_delete', $new_booking, $booking_post_id );

						$new_booking->update_status( 'cancelled' );
						// Delete Zoom Meeting.
						Bkap_Zoom_Meeting_Settings::bkap_delete_zoom_meeting( $booking_post_id, $new_booking );

						do_action( 'bkap_after_delete_booking_post', $booking_post_id, $new_booking );
					}

					$_product = wc_get_product( $product_id );

					if ( false != $_product ) {
						$product_title = $_product->get_name();
						$order_obj->add_order_note( __( "The booking for $product_title has been trashed.", 'woocommerce-booking' ) );
					} else {
						$order_obj->add_order_note( __( 'The booking has been trashed.', 'woocommerce-booking' ) );
					}
				}
			}
		}
	}
	$bkap_cancel_order = new bkap_cancel_order();
}
