<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Import Booking related functionalities handled in this class
 *
 * @author      Tyche Softwares
 * @package     BKAP/Import-Booking
 * @since       3.6
 * @category    Classes
 */

class import_bookings {

	/**
	 * This function will map the imported calendar events to the product and generate the booking.
	 *
	 * @since 3.6
	 * @global object $wpdb Global wpdb Object
	 */

	public static function bkap_map_imported_event() {

		$notice         = ''; // default notices to blanks
		$message        = '';
		$google_post_id = $_POST['ID'];
		$product_id     = $_POST['product_id'];
		$bkap_settings  = bkap_setting( $product_id );

		if ( $_POST['type'] == 'by_post' ) {

			if ( get_post_type( $google_post_id ) === 'bkap_gcal_event' ) {
				$booking = new BKAP_Gcal_Event( $google_post_id );
			}

			$booking_details['product_id']  = $product_id;
			$booking_details['start']       = $booking->start;
			$booking_details['end']         = $booking->end;
			$booking_details['summary']     = $booking->summary;
			$booking_details['description'] = $booking->description;
			$booking_details['uid']         = $booking->uid;

			$resource_status = Class_Bkap_Product_Resource::bkap_resource_status( $product_id );
			if ( $resource_status ) {
				$resource_ids = Class_Bkap_Product_Resource::bkap_get_product_resources( $product_id );
				foreach ( $resource_ids as $key => $value ) {
					$booking_details['bkap_resource_id'] = $value;
					break;
				}
			}

			/* Person Calculations */
			if ( isset( $bkap_settings['bkap_person'] ) && 'on' === $bkap_settings['bkap_person'] ) {
				if ( 'on' === $bkap_settings['bkap_person_type'] && count( $bkap_settings['bkap_person_data'] ) > 0 ) {
					$person_data      = $bkap_settings['bkap_person_data'];
					$person_post_data = array();
					foreach ( $person_data as $p_id => $p_data ) {
						$person_min                = ( $p_data['person_min'] > 1 ) ? $p_data['person_min'] : 1;
						$person_post_data[ $p_id ] = $person_min;
					}
					$booking_details['persons'] = $person_post_data;
				} else {
					$person_min                 = ( $bkap_settings['bkap_min_person'] > 1 ) ? $bkap_settings['bkap_min_person'] : 1;
					$booking_details['persons'] = array( $person_min );
				}
			}
		}

		$status = self::bkap_create_order( $booking_details, true );

		$backdated_event  = $status['backdated_event'];
		$validation_check = $status['validation_check'];
		$grouped_product  = $status['grouped_product'];

		if ( 0 == $backdated_event && 0 == $validation_check && 0 == $grouped_product ) {

			$option_name            = get_post_meta( $google_post_id, '_bkap_event_option_name', true );
			$imported_event_details = json_decode( get_option( $option_name ) );

			// finally move the imported event details to item meta and delete the record from wp_options table
			$archive_events = 0; // 0 - archive (move from wp_options to item meta), 1 - delete from wp_options and don't save as item meta

			if ( 0 == $archive_events ) {

				wc_add_order_item_meta( $status['item_id'], '_gcal_event_reference', $imported_event_details ); // save as item meta for future reference
				delete_option( $option_name ); // delete the data frm wp_options

				// Update post and its post meta.

				$update_parent_post_id = array(
					'ID'          => $google_post_id,
					'post_parent' => $status['order_id'],
				);

				wp_update_post( $update_parent_post_id ); // Update the parent ID of post into the database
				update_post_meta( $google_post_id, '_bkap_product_id', $status['parent_id'] );
				update_post_meta( $google_post_id, '_bkap_variation_id', $status['variation_id'] );
				$booking->update_status( 'bkap-mapped' );
			} elseif ( 1 == $archive_events ) {
				delete_option( $option_name ); // delete the data from wp_options
			}
		}

		if ( 1 == $backdated_event ) {
			$message .= __( 'Back Dated Events cannot be imported. Please discard them.', 'woocommerce-booking' );
		}

		if ( 1 == $validation_check ) {
			$message .= __( 'The product is not available for the given date/time for the desired quantity.', 'woocommerce-booking' );
		}

		if ( 1 == $grouped_product ) {
			$message .= __( 'Imported Events cannot be mapped to grouped products.', 'woocommerce-booking' );
		}

		if ( $message != '' ) {
			update_post_meta( $google_post_id, '_bkap_reason_of_fail', $message );
			$notice = '<div class="error"><p>' . sprintf( __( '%s', 'woocommerce-booking' ), $message ) . '</p></div>';
			echo $notice;
		}

		if ( ! isset( $_POST['automated'] ) || ( isset( $_POST['automated'] ) && 0 == $_POST['automated'] ) ) {
			die();
		}
	}

	/**
	 * This function will map the imported calendar events to the product and generate the booking and create WooCommerce Order.
	 *
	 * @since 3.6
	 * @param array   $booking_details Array of all booking details
	 * @param boolean $gcal true if called from google event functionality and false if it is called from manual booking
	 * @return array $status Array which contains validation result, new order id, item id, variation id, and parent id
	 */

	static function bkap_create_order( $booking_details, $gcal = false ) {

		global $bkap_date_formats;

		$booking_date_to_display        = '';
		$checkout_date_to_display       = '';
		$booking_from_time              = '';
		$booking_to_time                = '';
		$global_settings                = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$date_format_to_display         = $global_settings->booking_date_format;
		$time_format_to_display         = $global_settings->booking_time_format;
		$order_created                  = false;
		$product_id                     = $booking_details['product_id'];
		$booking_type_is_multiple_dates = isset( $booking_details['has_multidates'] ) && count( $booking_details['multidates_booking'] ) > 0;
		$tdif                           = ! current_time( 'timestamp' ) ? 0 : current_time( 'timestamp' ) - time();
		$qty                            = 1;

		if ( '' !== $booking_details['end'] && '' !== $booking_details['start'] ) {

			// admin bookings passes time as per WP timezone.
			$event_start = $booking_details['start'];
			$event_end   = $booking_details['end'];

			$booking_date_to_display  = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
			$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_end );

			$event_start_time = $event_start;
			$event_end_time   = $event_end;

			if ( $gcal ) { // GCAL passes UTC
				$event_start_time         = strtotime( bkap_convert_date_from_timezone_to_timezone( date( 'Y-m-d H:i', $event_start ), 'UTC', bkap_booking_get_timezone_string(), 'Y-m-d H:i' ) );
				$event_end_time           = strtotime( bkap_convert_date_from_timezone_to_timezone( date( 'Y-m-d H:i', $event_end ), 'UTC', bkap_booking_get_timezone_string(), 'Y-m-d H:i' ) );
				$booking_details['start'] = $event_start_time;
				$booking_details['end']   = $event_end_time;
				$event_start              = $booking_details['start'];
				$event_end                = $booking_details['end'];
			}

			if ( $time_format_to_display == '12' ) {
				$booking_from_time = date( 'h:i A', $event_start_time );
				$booking_to_time   = ( date( 'h:i A', $event_end_time ) === '12:00 AM' ) ? '' : date( 'h:i A', $event_end_time ); // open ended slot compatibility
			} else {
				$booking_from_time = date( 'H:i', $event_start_time );
				$booking_to_time   = ( date( 'H:i', $event_end_time ) === '00:00' ) ? '' : date( 'H:i', $event_end_time ); // open ended slot compatibility
			}
		} elseif ( $booking_details['start'] != '' && $booking_details['end'] == '' ) {

			$event_start = $booking_details['start'];

			$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );

			$event_start_time = $event_start;

			if ( $gcal ) {
				$event_start_time         = strtotime( bkap_convert_date_from_timezone_to_timezone( date( 'Y-m-d H:i', $event_start ), 'UTC', bkap_booking_get_timezone_string(), 'Y-m-d H:i' ) );
				$booking_details['start'] = $event_start_time;
				$event_start              = $booking_details['start'];
			}

			if ( $time_format_to_display == '12' ) {
				$booking_from_time = date( 'h:i A', $event_start_time );
			} else {
				$booking_from_time = date( 'H:i', $event_start_time );
			}
		}

		$resource_id = 0;
		if ( isset( $booking_details['bkap_resource_id'] ) && $booking_details['bkap_resource_id'] != '' ) {
			$resource_id = $booking_details['bkap_resource_id'];
		}

		$persons = array();
		if ( isset( $booking_details['persons'] ) && $booking_details['persons'] != '' ) {
			$persons = $booking_details['persons'];
		}

		$fixed_block = '';
		if ( isset( $booking_details['fixed_block'] ) && $booking_details['fixed_block'] != '' ) {
			$fixed_block = $booking_details['fixed_block'];
		}

		$bkap_duration = '';
		if ( isset( $booking_details['duration'] ) && $booking_details['duration'] != '' ) {
			$bkap_duration = $booking_details['duration'];
		}

		$backdated_event  = 0;
		$validation_check = 0;
		$grouped_product  = 0;
		$quantity         = $qty;
		$lockout_quantity = $qty;
		$parent_id        = $product_id;
		$variation_id     = 0;
		$variationsArray  = array();

		if ( ! $booking_type_is_multiple_dates ) {
			$sanity_check               = array();
			$sanity_check['start']      = ( isset( $event_start ) ) ? $event_start : '';
			$sanity_check['end']        = ( isset( $event_end ) ) ? $event_end : '';
			$sanity_check['product_id'] = $product_id;
			$sanity_check['quantity']   = $qty;

			if ( isset( $booking_details['quantity'] ) && $booking_details['quantity'] != '' ) {
				$sanity_check['quantity'] = $booking_details['quantity'];
			}

			$sanity_return    = self::bkap_sanity_check( $sanity_check, $gcal );
			$backdated_event  = ( isset( $sanity_return['backdated_event'] ) ) ? $sanity_return['backdated_event'] : 0;
			$validation_check = ( isset( $sanity_return['validation_check'] ) ) ? $sanity_return['validation_check'] : 0;
			$grouped_product  = ( isset( $sanity_check['grouped_event'] ) ) ? $sanity_check['grouped_event'] : 0;

			$quantity_return  = self::bkap_quantity_setup( $sanity_check, $sanity_return );
			$quantity         = $quantity_return['quantity'];
			$lockout_quantity = $quantity_return['lockout_qty'];
			$parent_id        = $quantity_return['parent_id'];
			$variation_id     = $quantity_return['variation_id'];
			$variationsArray  = $quantity_return['variationsArray'];
		}

		/* Validate the booking details. Check if the product is available in the desired quantity for the given date and time */
		$_product         = wc_get_product( $product_id );
		$booking_settings = get_post_meta( $parent_id, 'woocommerce_booking_settings', true );
		$product_type     = $_product->get_type();

		if ( 'variation' == $_product->get_type() ) {
			$product_type = 'variable';
		}

		$totals = 0;

		if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
			$hidden_date          = date( 'Y-m-d', $event_start );
			$booking_date         = new DateTime( $hidden_date );
			$hidden_checkout_date = date( 'Y-m-d', $event_end );
			$checkout_date        = new DateTime( $hidden_checkout_date );

			$difference = $checkout_date->diff( $booking_date );

			if ( $gcal ) {
				$price  = bkap_common::bkap_get_price( $parent_id, $variation_id, $product_type );
				$totals = $price * $difference->days;
			} else {
				$totals = $booking_details['price'];
			}
		} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) {

			$hidden_date = date( 'Y-m-d', $event_start );
			$from_hrs    = date( 'H:i', strtotime( $booking_from_time ) );
			$to_hrs      = date( 'H:i', strtotime( $booking_to_time ) );

			if ( 1 !== $validation_check ) {

				$timeslot = "$from_hrs - $to_hrs";

				$price = ( $gcal ) ? bkap_timeslot_price::get_price( $parent_id, $variation_id, $product_type, $hidden_date, $timeslot, 'product', $global_settings ) : $booking_details['price'];

				$totals = $price;
			}
		} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' == $booking_settings['booking_enable_time'] ) {

			$hidden_date = date( 'Y-m-d', $event_start );

			if ( ! isset( $booking_details['duration'] ) ) {

				$validation_check   = 0;
				$d_setting          = get_post_meta( $parent_id, '_bkap_duration_settings', true );
				$d_type             = $d_setting['duration_type'];         // mins
				$d_hours            = (int) $d_setting['duration']; // 180
				$d_gap_type         = $d_setting['duration_gap_type'];         // mins
				$d_gap              = (int) $d_setting['duration_gap']; // 180
				$d_min              = (int) $d_setting['duration_min']; // 1
				$d_max              = (int) $d_setting['duration_max']; // 1
				$duration_with_type = '';
				$gap                = $event_end - $event_start; // 3600

				if ( $d_type == 'hours' ) {
					$time_division = 3600;
				} else {
					$time_division = 60;
				}

				if ( $d_gap_type == 'hours' ) {
					$d_gap = $d_gap * 3600;
				} else {
					$d_gap = $d_gap * 60;
				}

				$base_interval          = $d_hours * $time_division;
				$base_interval_with_gap = $base_interval + $d_gap;
				$first_duration         = $d_setting['first_duration'];
				$from                   = strtotime( $first_duration ? $first_duration : 'midnight', $event_start );
				$start_next_day         = strtotime( '+ 1 day', $event_start );

				for ( $i = $from; $i < $start_next_day; $i += $base_interval_with_gap ) {
					$blocks[] = $i;
				}

				if ( in_array( $event_start_time, $blocks ) ) {

					if ( ( $gap % $base_interval ) == 0 ) {

						$duration_with_type = $gap / $time_division;

						$d_min_h = $d_min * $d_hours;
						$d_max_h = $d_max * $d_hours;

						if ( $duration_with_type >= $d_min_h && $duration_with_type <= $d_max_h ) {
							$duration_with_type .= '-' . $d_type;
						}
					}
				}

				if ( $duration_with_type == '' ) {
					$validation_check = 1;
				} else {
					$bkap_duration               = $duration_with_type;
					$booking_details['duration'] = $duration_with_type;
				}
			}

			if ( $gcal ) {
				if ( $d_setting['duration_price'] == '' ) {
					$totals = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $hidden_date );
				} else {
					$totals = $d_setting['duration_price'];
				}
			} else {
				$totals = $booking_details['price'];
			}
		} elseif ( isset( $booking_details['has_multidates'] ) && count( $booking_details['multidates_booking'] ) > 0 ) {
			$totals = 0;

			foreach ( $booking_details['multidates_booking'] as $_booking_detail ) {
				$totals += $_booking_detail['price_charged'];
			}
		} else {
			$hidden_date = date( 'Y-m-d', $event_start );

			// Single Day.
			if ( $gcal ) {
				$totals = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $hidden_date );
			} else {
				$totals = $booking_details['price'];
			}
		}

		$variationsArray['totals'] = apply_filters(
			'bkap_before_creating_manual_order',
			array(
				'subtotal'     => $totals,
				'total'        => $totals,
				'subtotal_tax' => 0,
				'tax'          => 0,
			),
			$booking_details,
			$gcal
		);

		if ( 0 === $backdated_event && 0 === $validation_check && 0 === $grouped_product ) {
			$order_created = true;

			// create an order.

			if ( $gcal ) {

				$args = array(
					'status'        => 'pending',
					'customer_note' => $booking_details['summary'],
					'created_via'   => 'GCal',
				);

			} else {
				$args = array(
					'status'      => 'pending',
					'created_via' => 'manual_booking',
					'customer_id' => $booking_details['customer_id'],
				);

				// Create the billing address array to be added to the order.
				if ( $booking_details['customer_id'] > 0 ) {
					$user_meta = array_map(
						function( $a ) {
								return $a[0];
						},
						get_user_meta( $booking_details['customer_id'] )
					);

					$addr_args = array(
						'first_name' => ( isset( $user_meta['billing_first_name'] ) ) ? $user_meta['billing_first_name'] : '',
						'last_name'  => ( isset( $user_meta['billing_last_name'] ) ) ? $user_meta['billing_last_name'] : '',
						'email'      => ( isset( $user_meta['billing_email'] ) ) ? $user_meta['billing_email'] : '',
						'phone'      => ( isset( $user_meta['billing_phone'] ) ) ? $user_meta['billing_phone'] : '',
						'address_1'  => ( isset( $user_meta['billing_address_1'] ) ) ? $user_meta['billing_address_1'] : '',
						'address_2'  => ( isset( $user_meta['billing_address_2'] ) ) ? $user_meta['billing_address_2'] : '',
						'city'       => ( isset( $user_meta['billing_city'] ) ) ? $user_meta['billing_city'] : '',
						'state'      => ( isset( $user_meta['billing_state'] ) ) ? $user_meta['billing_state'] : '',
						'postcode'   => ( isset( $user_meta['billing_postcode'] ) ) ? $user_meta['billing_postcode'] : '',
						'country'    => ( isset( $user_meta['billing_country'] ) ) ? $user_meta['billing_country'] : '',
					);
				} else {
					$addr_args = array(
						'first_name' => 'Guest',
					);
				}
			}

			$order = wc_create_order( $args );

			$order_id = $order->get_id();

			if ( $gcal ) {
				if ( isset( $booking_details['summary'] ) && $booking_details['summary'] != '' ) {
					$order->add_order_note( $booking_details['summary'] );
				}
				if ( isset( $booking_details['description'] ) && $booking_details['description'] != '' ) {
					$order->add_order_note( $booking_details['description'] );
				}
				$order->add_order_note( 'Reserved by GCal' );
			} else {
				$order->add_order_note( 'Manual Booking' );
				// Add the Billing Address.
				$order->set_address( $addr_args, 'billing' );
			}
			// add the product to the order.

			$item_id = $order->add_product( $_product, $quantity, $variationsArray );

			if ( $gcal ) {
				// insert records to ensure we're aware the item has been imported.
				$event_items = get_option( 'bkap_event_item_ids' );
				if ( $event_items == '' || $event_items == '{}' || $event_items == '[]' || $event_items == 'null' ) {
					$event_items = array();
				}
				array_push( $event_items, $item_id );
				update_option( 'bkap_event_item_ids', $event_items );
			}

			// calculate order totals.
			$order->calculate_totals();

			if ( isset( $parent_id ) && 0 != $parent_id ) {
				$meta_update_id = $parent_id;
			} else {
				$meta_update_id = $product_id;
			}

			$order_obj = wc_get_order( $order_id ); // this needs to be done to ensure the booking details are displayed in Woo emails.
			if ( $booking_type_is_multiple_dates ) {

				foreach ( $booking_details['multidates_booking'] as $_booking_detail ) {

					$booking = $_booking_detail;

					$booking['price'] = $booking['price_charged'];

					$quantity = ( isset( $booking['quantity'] ) ) ? $booking['quantity'] : 1;

					bkap_common::bkap_update_order_item_meta( $item_id, $product_id, $booking, true );

					// adjust lockout.
					$product = wc_get_product( $product_id );

					// for grouped products.
					$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $product->get_parent() : bkap_common::bkap_get_parent_id( $product_id );

					// Add the booking as a post.
					$booking['gcal_uid'] = ( $gcal ) ? $booking_details['uid'] : false;

					bkap_checkout::bkap_create_booking_post( $item_id, $product_id, $quantity, $booking, $variation_id );

					$details = bkap_checkout::bkap_update_lockout( $order_id, $product_id, $parent_id, $quantity, $booking );

					// update the global time slot lockout.
					if ( isset( $booking['time_slot'] ) && '' != $booking['time_slot'] ) {
						bkap_checkout::bkap_update_global_lockout( $product_id, $quantity, $details, $booking );
					}
				}
			} else {

				// create the booking details array.
				$booking['date']        = $booking_date_to_display;
				$booking['hidden_date'] = date( 'j-n-Y', $event_start );

				if ( ! $gcal ) {
					$booking['price'] = isset( $booking_details['price_incl'] ) ? $booking_details['price_incl'] / $quantity : $booking_details['price'] / $quantity; // price is to be passed only if it's an admin booking
				} else {
					$booking['price'] = $totals;
				}

				$booking['uid']         = isset( $booking_details['uid'] ) ? $booking_details['uid'] : '';
				$booking['resource_id'] = $resource_id;
				$booking['fixed_block'] = $fixed_block;
				$booking['persons']     = $persons;

				if ( '' !== $bkap_duration ) {
					$booking['selected_duration'] = $bkap_duration;
				}

				$hidden_checkout_date = '';

				if ( isset( $checkout_date_to_display ) && '' != $checkout_date_to_display ) {
					$hidden_checkout_date = date( 'j-n-Y', $event_end );
				}

				$booking_method = get_post_meta( $meta_update_id, '_bkap_booking_type', true );

				if ( 'multiple_days' === $booking_method ) {
					$booking['date_checkout']        = $checkout_date_to_display;
					$booking['hidden_date_checkout'] = $hidden_checkout_date;
				}

				if ( isset( $booking_from_time ) && '' !== $booking_from_time && $booking_from_time !== $booking_to_time ) {
					$time_slot = $booking_from_time;
					if ( isset( $booking_to_time ) && '' !== $booking_to_time ) {
						$time_slot .= ' - ' . $booking_to_time;
					}
					// check the booking method
					if ( 'date_time' === $booking_method ) {
						$booking['time_slot'] = $time_slot;
					}

					if ( $bkap_duration != '' ) {
						$booking['duration_time_slot'] = date( 'H:i', strtotime( $booking_from_time ) );
					}
				}

				if ( isset( $parent_id ) && 0 != $parent_id ) {
					$meta_update_id = $parent_id;
				} else {
					$meta_update_id = $product_id;
				}

				bkap_common::bkap_update_order_item_meta( $item_id, $meta_update_id, $booking, true );

				// adjust lockout.
				$product = wc_get_product( $meta_update_id );

				// for grouped products.
				$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $product->get_parent() : bkap_common::bkap_get_parent_id( $meta_update_id );

				// Add the booking as a post.
				$booking['gcal_uid'] = ( $gcal ) ? $booking_details['uid'] : false;

				bkap_checkout::bkap_create_booking_post( $item_id, $meta_update_id, $lockout_quantity, $booking, $variation_id );

				$details = bkap_checkout::bkap_update_lockout( $order_id, $meta_update_id, $parent_id, $lockout_quantity, $booking );

				// update the global time slot lockout
				if ( isset( $booking['time_slot'] ) && '' !== $booking['time_slot'] ) {
					bkap_checkout::bkap_update_global_lockout( $meta_update_id, $lockout_quantity, $details, $booking );
				}

				// update the order status to processing.
				if ( $gcal ) {
					$order_status = apply_filters( 'bkap_manual_gcal_booking_order_status', 'processing' );
				} else {
					// Manual order status.
					$order_status = apply_filters( 'bkap_manual_booking_order_status', 'pending' );
				}
				$order_obj->update_status( $order_status );
			}

			if ( ! $gcal ) {

				$g_cal = new BKAP_Gcal();

				$user = get_user_by( 'email', get_option( 'admin_email' ) );

				$admin_id = 0;

				if ( isset( $user->ID ) ) {
					$admin_id = $user->ID;
				} else {
					// get the list of administrators.
					$args  = array(
						'role'   => 'administrator',
						'fields' => array( 'ID' ),
					);
					$users = get_users( $args );
					if ( isset( $users ) && count( $users ) > 0 ) {
						$admin_id = $users[0]->ID;
					}
				}

				$booking_settings = get_post_meta( $meta_update_id, 'woocommerce_booking_settings', true );

				// check the booking status, if pending confirmation, then do not insert event in the calendar.
				$booking_status = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );

				if ( ( isset( $booking_status ) && 'pending-confirmation' != $booking_status ) || ( ! isset( $booking_status ) ) ) {
					// ensure it's a future dated event
					$is_date_set = false;

					if ( isset( $booking['hidden_date'] ) ) {
						$day = date( 'Y-m-d', current_time( 'timestamp' ) );

						if ( strtotime( $booking['hidden_date'] ) >= strtotime( $day ) ) {
							$is_date_set = true;
						}
					}

					if ( $is_date_set ) {

						$event_details = array();

						$event_details['hidden_booking_date'] = $booking['hidden_date'];

						if ( isset( $booking['hidden_date_checkout'] ) && $booking['hidden_date_checkout'] != '' ) {

							if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
								$event_details['hidden_checkout_date'] = date( 'j-n-Y', strtotime( '+1 day', strtotime( $booking['hidden_date_checkout'] ) ) );
							} else {
								$event_details['hidden_checkout_date'] = $booking['hidden_date_checkout'];
							}
						}

						if ( isset( $booking['time_slot'] ) && $booking['time_slot'] != '' ) {
							$event_details['time_slot'] = $booking['time_slot'];
						}

						if ( isset( $booking['selected_duration'] ) && '' !== $booking['selected_duration'] ) {

							$start_date = $booking['hidden_date'];
							$time       = $booking['duration_time_slot'];

							$selected_duration = explode( '-', $booking['selected_duration'] );

							$hour   = $selected_duration[0];
							$d_type = $selected_duration[1];

							$end_str  = bkap_common::bkap_add_hour_to_date( $start_date, $time, $hour, $meta_update_id, $d_type ); // return end date timestamp
							$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta

							// updating end date.
							if ( $start_date != $end_date ) {
								$event_details['hidden_checkout_date'] = $end_date;
							}

							$endtime        = date( 'H:i', $end_str );// getend time in H:i format
							$back_time_slot = $time . ' - ' . $endtime; // to store time sting in the _wapbk_time_slot key of order item meta

							$event_details['duration_time_slot'] = $back_time_slot;
						}

						if ( isset( $booking['resource_id'] ) && '' != $booking['resource_id'] ) {
							$event_details['resource'] = get_the_title( $booking['resource_id'] );
						}

						$event_details['billing_email']      = ( isset( $addr_args['email'] ) ) ? $addr_args['email'] : '';
						$event_details['billing_first_name'] = ( isset( $addr_args['first_name'] ) ) ? $addr_args['first_name'] : '';
						$event_details['billing_last_name']  = ( isset( $addr_args['last_name'] ) ) ? $addr_args['last_name'] : '';
						$event_details['billing_address_1']  = ( isset( $addr_args['address_1'] ) ) ? $addr_args['address_1'] : '';
						$event_details['billing_address_2']  = ( isset( $addr_args['address_2'] ) ) ? $addr_args['address_2'] : '';
						$event_details['billing_city']       = ( isset( $addr_args['city'] ) ) ? $addr_args['city'] : '';
						$event_details['billing_phone']      = ( isset( $addr_args['phone'] ) ) ? $addr_args['phone'] : '';
						$event_details['order_id']           = $order_id;
						$event_details['order_comments']     = '';
						$event_details['product_name']       = $_product->get_title();

						if ( 0 < $variation_id ) {
							$variation_obj                 = new WC_Product_Variation( $variation_id );
							$variation_attr_cnt            = count( $variation_obj->get_variation_attributes() );
							$product_variations            = implode( ', ', $variation_obj->get_variation_attributes() );
							$event_details['product_name'] = $event_details['product_name'] . ' - ' . $product_variations;
						}

						$event_details['product_qty']   = 1;
						$event_details['product_total'] = $totals;
						$zoom_label                     = bkap_zoom_join_meeting_label( $product_id );
						$zoom_meeting                   = wc_get_order_item_meta( $item_id, $zoom_label );
						$event_details['zoom_meeting']  = '';
						if ( '' != $zoom_meeting ) {
							$event_details['zoom_meeting'] = $zoom_label . ' - ' . $zoom_meeting;
						}

						if ( in_array( $g_cal->get_api_mode( $admin_id, $meta_update_id ), array( 'directly', 'oauth' ), true ) ) {
							if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' == $booking_settings['product_sync_integration_mode'] ) ) {
								$meta_update_id = 0;
							}

							$g_cal->insert_event( $event_details, $item_id, $booking_details['customer_id'], $meta_update_id, false );
						}

						do_action( 'bkap_create_order_event', $item_id, $product_id, $order_id, $order_obj, $event_details );
					}
				}
			}
		}

		$status['backdated_event']  = $backdated_event;
		$status['validation_check'] = $validation_check;
		$status['grouped_product']  = $grouped_product;
		$status['new_order']        = $order_created;
		$status['order_id']         = ( $order_created ) ? $order_id : 0;
		$status['item_id']          = ( $order_created ) ? $item_id : 0;
		$status['parent_id']        = $parent_id;
		$status['variation_id']     = $variation_id;

		return $status;
	}

	/**
	 * This function will generate the booking and update the lockout for the product.
	 *
	 * @since 3.6
	 * @param array   $booking_details array of booking details
	 * @param boolean $gcal true if called from google event functionality and false if it is called from manual booking
	 * @return array $status Returns array of validation checks, order id, item id,
	 */

	static function bkap_create_booking( $booking_details, $gcal = false ) {

		global $bkap_date_formats;

		$booking_date_to_display        = '';
		$checkout_date_to_display       = '';
		$booking_from_time              = '';
		$booking_to_time                = '';
		$global_settings                = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$date_format_to_display         = $global_settings->booking_date_format;
		$time_format_to_display         = $global_settings->booking_time_format;
		$item_added                     = false;
		$product_id                     = $booking_details['product_id'];
		$booking_type_is_multiple_dates = isset( $booking_details['has_multidates'] ) && count( $booking_details['multidates_booking'] ) > 0;
		$tdif                           = ! current_time( 'timestamp' ) ? 0 : current_time( 'timestamp' ) - time();
		$qty                            = 1;

		if ( '' !== $booking_details['end'] && '' !== $booking_details['start'] ) {

			if ( $gcal ) { // GCAL passes UTC.
				$event_start = $booking_details['start '] + $tdif;
				$event_end   = $booking_details['end'] + $tdif;
			} else { // admin bookings passes time as per WP timezone.
				$event_start = $booking_details['start'];
				$event_end   = $booking_details['end'];
			}

			$booking_date_to_display  = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
			$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_end );

			if ( $time_format_to_display == '12' ) {
				$booking_from_time = date( 'h:i A', $event_start );
				$booking_to_time   = ( date( 'h:i A', $event_end ) === '12:00 AM' ) ? '' : date( 'h:i A', $event_end ); // open ended slot compatibility
			} else {
				$booking_from_time = date( 'H:i', $event_start );
				$booking_to_time   = ( date( 'H:i', $event_end ) === '00:00' ) ? '' : date( 'H:i', $event_end ); // open ended slot compatibility
			}
		} elseif ( $booking_details['start'] != '' && $booking_details['end'] == '' ) {

			$event_start             = $booking_details['start'] + $tdif;
			$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );

			if ( $time_format_to_display == '12' ) {
				$booking_from_time = date( 'h:i A', $event_start );
			} else {
				$booking_from_time = date( 'H:i', $event_start );
			}
		}

		$backdated_event  = 0;
		$validation_check = 0;
		$grouped_product  = 0;
		$quantity         = $qty;
		$lockout_quantity = $qty;
		$parent_id        = $product_id;
		$variation_id     = 0;
		$variationsArray  = array();

		if ( ! $booking_type_is_multiple_dates ) {

			$sanity_check               = array();
			$sanity_check['start']      = $event_start;
			$sanity_check['end']        = ( isset( $event_end ) ) ? $event_end : '';
			$sanity_check['product_id'] = $product_id;
			$sanity_check['quantity']   = 1;

			if ( isset( $booking_details['quantity'] ) && $booking_details['quantity'] != '' ) {
				$sanity_check['quantity'] = $booking_details['quantity'];
			}

			$sanity_return    = self::bkap_sanity_check( $sanity_check, $gcal );
			$backdated_event  = ( isset( $sanity_return['backdated_event'] ) ) ? $sanity_return['backdated_event'] : 0;
			$validation_check = ( isset( $sanity_return['validation_check'] ) ) ? $sanity_return['validation_check'] : 0;
			$grouped_product  = ( isset( $sanity_check['grouped_event'] ) ) ? $sanity_check['grouped_event'] : 0;

			$quantity_return  = self::bkap_quantity_setup( $sanity_check, $sanity_return );
			$quantity         = $quantity_return['quantity'];
			$lockout_quantity = $quantity_return['lockout_qty'];
			$parent_id        = $quantity_return['parent_id'];
			$variation_id     = $quantity_return['variation_id'];
			$variationsArray  = $quantity_return['variationsArray'];
		}

		$totals = $booking_details['price'];

		if ( $booking_type_is_multiple_dates ) {
			foreach ( $booking_details['multidates_booking'] as $_booking_detail ) {
				$totals += $_booking_detail['price_charged'];
			}
		}

		if ( 0 == $backdated_event && 0 == $validation_check && 0 == $grouped_product ) {
			$order         = wc_get_order( $booking_details['order_id'] );
			$item_added    = true;
			$order_id      = $booking_details['order_id'];
			$_product      = wc_get_product( $product_id );
			$product_title = $_product->get_name();

			// set the price.
			$variationsArray['totals'] = array(
				'subtotal'     => $totals,
				'total'        => $totals,
				'subtotal_tax' => 0,
				'tax'          => 0,
			);

			$order->add_order_note( "Added $product_title manually." );

			// add the product to the order.
			$item_id = $order->add_product( $_product, $quantity, $variationsArray );

			// calculate order totals.
			$order->calculate_totals();

			if ( isset( $parent_id ) && 0 !== $parent_id ) {
				$meta_update_id = $parent_id;
			} else {
				$meta_update_id = $product_id;
			}

			if ( isset( $booking_details['has_multidates'] ) && count( $booking_details['multidates_booking'] ) > 0 ) {

				foreach ( $booking_details['multidates_booking'] as $_booking_detail ) {

					$booking = $_booking_detail;

					$booking['price'] = $booking['price_charged'];

					$quantity = ( isset( $booking['quantity'] ) ) ? $booking['quantity'] : 1;

					bkap_common::bkap_update_order_item_meta( $item_id, $meta_update_id, $booking, true );

					// adjust lockout.
					$product = wc_get_product( $meta_update_id );

					// for grouped products.
					$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $product->get_parent() : bkap_common::bkap_get_parent_id( $meta_update_id );

					// Add the booking as a post.
					$booking['gcal_uid'] = false;
					bkap_checkout::bkap_create_booking_post( $item_id, $meta_update_id, $quantity, $booking, $variation_id );

					$details = bkap_checkout::bkap_update_lockout( $order_id, $meta_update_id, $parent_id, $quantity, $booking );

					// update the global time slot lockout
					if ( isset( $booking['time_slot'] ) && '' != $booking['time_slot'] ) {
						bkap_checkout::bkap_update_global_lockout( $meta_update_id, $lockout_quantity, $details, $booking );
					}
				}
			} else {

				// create the booking details array
				$booking['date']        = $booking_date_to_display;
				$booking['hidden_date'] = date( 'j-n-Y', $event_start );
				$booking['price']       = $booking_details['price'];
				$hidden_checkout_date   = '';

				if ( isset( $checkout_date_to_display ) && '' != $checkout_date_to_display ) {
					$hidden_checkout_date = date( 'j-n-Y', $event_end );
				}

				$booking_method = get_post_meta( $meta_update_id, '_bkap_booking_type', true );

				if ( 'multiple_days' === $booking_method ) {
					$booking['date_checkout']        = $checkout_date_to_display;
					$booking['hidden_date_checkout'] = $hidden_checkout_date;
				}

				if ( isset( $booking_from_time ) && '' != $booking_from_time && $booking_from_time != $booking_to_time ) {
					$time_slot = $booking_from_time;
					if ( isset( $booking_to_time ) && '' != $booking_to_time ) {
						$time_slot .= ' - ' . $booking_to_time;
					}
					if ( 'date_time' === $booking_method ) {
						$booking['time_slot'] = $time_slot;
					}
				}

				if ( isset( $booking_details['bkap_resource_id'] ) && $booking_details['bkap_resource_id'] != '' ) {
					$booking['resource_id'] = $booking_details['bkap_resource_id'];
				}

				bkap_common::bkap_update_order_item_meta( $item_id, $meta_update_id, $booking, true );

				// adjust lockout.
				$product = wc_get_product( $meta_update_id );
				// for grouped products.
				$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $product->get_parent() : bkap_common::bkap_get_parent_id( $meta_update_id );
				// Add the booking as a post.
				$booking['gcal_uid'] = false;
				bkap_checkout::bkap_create_booking_post( $item_id, $meta_update_id, $lockout_quantity, $booking, $variation_id );

				$details = bkap_checkout::bkap_update_lockout( $order_id, $meta_update_id, $parent_id, $lockout_quantity, $booking );

				// update the global time slot lockout.
				if ( isset( $booking['time_slot'] ) && '' != $booking['time_slot'] ) {
					bkap_checkout::bkap_update_global_lockout( $meta_update_id, $lockout_quantity, $details, $booking );
				}
			}

			if ( ! $gcal ) {

				$g_cal    = new BKAP_Gcal();
				$admin_id = bkap_get_user_id();

				// if ( in_array( $g_cal->get_api_mode( $admin_id, $meta_update_id ), array( 'directly', 'oauth' ), true ) ) {

				$booking_settings = get_post_meta( $meta_update_id, 'woocommerce_booking_settings', true );
				$booking_status   = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );

				if ( ( isset( $booking_status ) && 'pending-confirmation' != $booking_status ) || ( ! isset( $booking_status ) ) ) {

					$is_date_set = false;
					if ( isset( $booking['hidden_date'] ) ) { // ensure it's a future dated event.
						$day = date( 'Y-m-d', current_time( 'timestamp' ) );
						if ( strtotime( $booking['hidden_date'] ) >= strtotime( $day ) ) {
							$is_date_set = true;
						}
					}

					if ( $is_date_set ) {

						$event_details                        = array();
						$event_details['hidden_booking_date'] = $booking['hidden_date'];

						if ( isset( $booking['hidden_date_checkout'] ) && $booking['hidden_date_checkout'] != '' ) {
							if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
								$event_details['hidden_checkout_date'] = date( 'j-n-Y', strtotime( '+1 day', strtotime( $booking['hidden_date_checkout'] ) ) );
							} else {
								$event_details['hidden_checkout_date'] = $booking['hidden_date_checkout'];
							}
						}

						if ( isset( $booking['time_slot'] ) && '' != $booking['time_slot'] ) {
							$event_details['time_slot'] = $booking['time_slot'];
						}

						$event_details['billing_email']      = $order->get_billing_email();
						$event_details['billing_first_name'] = $order->get_billing_first_name();
						$event_details['billing_last_name']  = $order->get_billing_last_name();
						$event_details['billing_address_1']  = $order->get_billing_address_1();
						$event_details['billing_address_2']  = $order->get_billing_address_2();
						$event_details['billing_city']       = $order->get_billing_city();
						$event_details['billing_phone']      = $order->get_billing_phone();
						$event_details['order_id']           = $order->get_id();
						$event_details['order_comments']     = '';
						$event_details['product_name']       = $product->get_title();

						if ( 0 < $variation_id ) {
							$variation_obj                 = new WC_Product_Variation( $variation_id );
							$variation_attr_cnt            = count( $variation_obj->get_variation_attributes() );
							$product_variations            = implode( ', ', $variation_obj->get_variation_attributes() );
							$event_details['product_name'] = $event_details['product_name'] . ' - ' . $product_variations;
						}

						$event_details['product_qty']   = $booking_details['quantity'];
						$event_details['product_total'] = $booking_details['price'];
						$zoom_label                     = bkap_zoom_join_meeting_label( $product_id );
						$zoom_meeting                   = wc_get_order_item_meta( $item_id, $zoom_label );
						$event_details['zoom_meeting']  = '';
						if ( '' != $zoom_meeting ) {
							$event_details['zoom_meeting'] = $zoom_label . ' - ' . $zoom_meeting;
						}

						if ( in_array( $g_cal->get_api_mode( $admin_id, $meta_update_id ), array( 'directly', 'oauth' ), true ) ) {
							if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' == $booking_settings['product_sync_integration_mode'] ) ) {
								$meta_update_id = 0;
							}
							$g_cal->insert_event( $event_details, $item_id, $booking_details['customer_id'], $meta_update_id, false );
						}

						do_action( 'bkap_create_booking_event', $item_id, $product_id, $order_id, $order, $event_details );
					}
				}
				// }
			}
		}

		$status['backdated_event']  = $backdated_event;
		$status['validation_check'] = $validation_check;
		$status['grouped_product']  = $grouped_product;
		$status['item_added']       = $item_added;
		$status['order_id']         = $booking_details['order_id'];
		return $status;
	}

	/**
	 * This function will validate if the give booking details is valid for creating the booking for the product or not.
	 *
	 * @since 3.6
	 * @param array   $booking_details Array of booking details
	 * @param boolean $gcal true if called from google event functionality and false if it is called from manual booking
	 * @return array $sanity_results Returns result of the validation
	 */

	static function bkap_sanity_check( $booking_data, $gcal ) {

		global $bkap_date_formats;

		$event_start = $booking_data['start'];
		$event_end   = $booking_data['end'];
		$product_id  = $booking_data['product_id'];

		// default  variables
		$backdated_event  = 0; // it's a future event
		$validation_check = 0; // product is available for desired quantity
		$grouped_product  = 0; // for grouped product

		$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

		$date_format_to_display = $global_settings->booking_date_format;
		$time_format_to_display = $global_settings->booking_time_format;

		// lets compare with only date start to ensure current dated bookings can go through
		$current_time = current_time( 'timestamp' );
		$date_start   = strtotime( date( 'Ymd', $current_time ) );

		if ( $event_start !== '' && $event_end !== '' ) {
			if ( $event_end >= $date_start || $event_start >= $date_start ) {

				if ( $time_format_to_display == '12' ) {
					$booking_from_time = date( 'h:i A', $event_start );
					$booking_to_time   = date( 'h:i A', $event_end );
				} else {
					$booking_from_time = date( 'H:i', $event_start );
					$booking_to_time   = date( 'H:i', $event_end );
				}

				$booking_date_to_display  = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
				$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_end );

			} else {
				$backdated_event = 1;
			}
		} else {
			if ( $event_start >= $date_start ) {

				if ( $time_format_to_display == '12' ) {
					$booking_from_time = date( 'h:i A', $event_start );
				} else {
					$booking_from_time = date( 'H:i', $event_start );
				}

				$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
			} else {
				$backdated_event = 1;
			}
		}

		/* Validate the booking details. Check if the product is available in the desired quantity for the given date and time */
		$_product = wc_get_product( $product_id );
		if ( 'grouped' == $_product->get_type() ) {
			$grouped_product = 1;
		}

		$variationsArray = array();

		// if the product ID has a parent post Id, then it means it's a variable product
		$variation_id     = 0;
		$lockout_quantity = 1;

		if ( $_product->get_type() == 'variation' ) {

			$check_variation = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->variation_id : $_product->get_id();
		}

		if ( isset( $check_variation ) && 0 != $check_variation ) {

			$parent_id    = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->parent->id : $_product->get_parent_id();
			$variation_id = $product_id;

			$parent_product  = wc_get_product( $parent_id );
			$variations_list = $parent_product->get_available_variations();

			foreach ( $variations_list as $variation ) {
				if ( $variation['variation_id'] == $product_id ) {
					$variationsArray['variation'] = $variation['attributes'];
				}
			}

			// Product Attributes - Booking Settings
			$attribute_booking_data = get_post_meta( $parent_id, '_bkap_attribute_settings', true );

			if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
				$lockout_quantity = 1;
				foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
					$attr_name = 'attribute_' . $attr_name;
					if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] ) {
						if ( array_key_exists( $attr_name, $variationsArray['variation'] ) ) {
							$lockout_quantity += $variationsArray['variation'][ $attr_name ];
						}
					}
				}
			}
		} else {
			$parent_id = $product_id;
		}

		$hidden_date      = date( 'Y-m-d', $event_start );
		$booking_settings = get_post_meta( $parent_id, 'woocommerce_booking_settings', true );

		// if the event is not backdated
		$backdated_event = apply_filters( 'bkap_allow_backdated_bookings', $backdated_event );
		if ( $backdated_event != 1 ) {
			// if time is enabled for the product.
			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {

				$from_hrs = bkap_date_as_format( $booking_from_time, 'H:i' );
				$to_hrs   = bkap_date_as_format( $booking_to_time, 'H:i' );

				if ( '00:00' === $to_hrs ) {
					$to_hrs = '';
				}

				$availability = bkap_booking_process::bkap_get_time_availability( $parent_id, $hidden_date, $from_hrs, $to_hrs, 'YES' );

				if ( $availability == 0 ) {

					$from_hrss = date( 'H:i', strtotime( $booking_from_time ) );
					$to_hrss   = date( 'H:i', strtotime( $booking_to_time ) );

					if ( $to_hrss == '00:00' ) {
						$to_hrss = '';
					}

					$availability = bkap_booking_process::bkap_get_time_availability( $parent_id, $hidden_date, $from_hrss, $to_hrss, 'YES' );
				}

				$unlimited_lang = __( 'Unlimited', 'woocommerce-booking' );

				if ( trim( $availability ) == $unlimited_lang ) {
					$validation_check = 0;
				} elseif ( $availability > 0 ) {
					if ( $availability > 0 ) {
						$new_availability = $availability - $lockout_quantity;
						if ( $new_availability < 0 ) {
							$validation_check = 1; // product is unavailable for the desired quantity
						}
					}
				} else {
					$validation_check = 1; // product is not available
				}
			} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' == $booking_settings['booking_enable_time'] ) {

				// min date j-n-y
				// strtotime

				$booked_duration = get_bookings_for_range( $product_id, date( 'j-n-Y', $event_start ), $event_end );

				$duration_available = bkap_check_duration_available( $product_id, $booking_settings, $event_start, $event_end, $booked_duration );

				if ( ! $duration_available ) {
					$validation_check = 1; // product is unavailable for the desired quantity
				}
			} else {

				$hidden_checkout_date = '';
				if ( isset( $checkout_date_to_display ) && $checkout_date_to_display != '' ) {
					$hidden_checkout_date = date( 'Y-m-d', $event_end );
				}
				$bookings_placed      = '';
				$attr_bookings_placed = '';

				$variation_booked_dates_array = bkap_variations::bkap_get_booked_dates_for_variation( $parent_id, $variation_id );

				$bookings_placed = ( isset( $variation_booked_dates_array['wapbk_bookings_placed_'] ) && $variation_booked_dates_array['wapbk_bookings_placed_'] != '' ) ? $variation_booked_dates_array['wapbk_bookings_placed_'] : '';

				$availability = bkap_booking_process::bkap_get_date_availability( $parent_id, $variation_id, $hidden_date, $booking_date_to_display, $bookings_placed, $attr_bookings_placed, $hidden_checkout_date, false );

				$unlimited_lang = __( 'Unlimited', 'woocommerce-booking' );
				if ( ! trim( $availability ) == $unlimited_lang ) {
					if ( $availability > 0 ) {
						if ( $availability > 0 ) {
							$new_availability = $availability - $lockout_quantity;
							if ( $new_availability < 0 ) {
								$validation_check = 1; // product is unavailable for the desired quantity
							}
						}
					} else {
						$validation_check = 1; // product is not available
					}
				}
			}
		} else {
			$validation_check = 1;
		}
		$sanity_results = array(
			'backdated_event'  => $backdated_event,
			'validation_check' => $validation_check,
			'grouped_event'    => $grouped_product,
		);

		return $sanity_results;
	}

	/**
	 * This function is to calculate the parent id and its data.
	 *
	 * @since 3.6
	 * @param array $booking_details Array of Booking details
	 * @param array $sanity_check Array of validation result
	 * @return array $quantity_return Return array of parent id, quantity, lockout quantity, variation id and variation array
	 */

	static function bkap_quantity_setup( $booking_data, $sanity_check ) {
		$quantity         = $booking_data['quantity'];
		$lockout_quantity = $booking_data['quantity']; // this is the qty which will be used to update lockout. Item and lockout qty can be different in case of attribute vaues being used as lockout qty

		$product_id = $booking_data['product_id'];
		$_product   = wc_get_product( $product_id );

		$variationsArray = array();

		// if the product ID has a parent post Id, then it means it's a variable product
		$variation_id = 0;

		$parent_id = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $_product->parent->id : $_product->get_parent_id();
		if ( $parent_id > 0 ) {
			$variation_id = $product_id;

			$parent_product  = wc_get_product( $parent_id );
			$variations_list = $parent_product->get_available_variations();

			foreach ( $variations_list as $variation ) {
				if ( $variation['variation_id'] == $product_id ) {
					$variationsArray['variation'] = $variation['attributes'];
				}
			}

			// Product Attributes - Booking Settings
			$attribute_booking_data = get_post_meta( $parent_id, '_bkap_attribute_settings', true );

			if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
				$lockout_quantity = 1;
				foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
					$attr_name = 'attribute_' . $attr_name;
					if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] ) {
						if ( array_key_exists( $attr_name, $variationsArray['variation'] ) ) {
							$lockout_quantity += $variationsArray['variation'][ $attr_name ];
						}
					}
				}
			}
		} else {
			$parent_id = $product_id;
		}

		$quantity_return['quantity']        = $quantity;
		$quantity_return['lockout_qty']     = $lockout_quantity;
		$quantity_return['parent_id']       = $parent_id;
		$quantity_return['variation_id']    = $variation_id;
		$quantity_return['variationsArray'] = $variationsArray;

		return $quantity_return;
	}
}
