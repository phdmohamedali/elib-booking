<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for AJAX
 *
 * @author   Tyche Softwares
 * @package  BKAP/Edit-Booking-Post
 * @category Classes
 * @class    Bkap_Edit_Booking_Post
 */

if ( ! class_exists( 'Bkap_Edit_Booking_Post' ) ) {

	/**
	 * Class Bkap_Edit_Booking_Post.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Edit_Booking_Post {

		/**
		 * Bkap_Edit_Booking_Post constructor.
		 */
		public function __construct() {
			/**
			 * Edit Booking Post - save the changes in booking details meta box
			 */
			add_filter( 'wp_insert_post_data', array( &$this, 'bkap_meta_box_save_booking_details' ), 11, 2 );
		}

		/**
		 * This function saves the booking data for
		 * Edit Booking posts - from Woo->Orders
		 *
		 * @param mixed   $post_data Post Data to be saved.
		 * @param WP_Post $post Post Object.
		 * @return mixed post_data if post is invalid else update the meta information.
		 * @since 4.1.0
		 * @since Updated 5.15.0 Multiple Resources
		 *
		 * @global mixed $wpdb global variable
		 * @global array Booking Date Formats Array
		 */
		public function bkap_meta_box_save_booking_details( $post_data, $post ) {

			global $wpdb, $bkap_date_formats;

			if ( 'bkap_booking' !== $post['post_type'] ) {
				return $post_data;
			}

			$post_id = $post['ID'];

			// Check the post being saved == the $post_id to prevent triggering this call for other save_post events.
			if ( empty( $post['post_ID'] ) || intval( $post['post_ID'] ) !== $post_id || ! isset( $post['wapbk_hidden_date'] ) ) {
				return $post_data;
			}

			if ( ! isset( $post['bkap_details_meta_box_nonce'] ) || ! wp_verify_nonce( $post['bkap_details_meta_box_nonce'], 'bkap_details_meta_box' ) ) {
				return $post_data;
			}

			$product_id              = wc_clean( $post['bkap_product_id'] );
			$resource_selection_type = BKAP_Product_Resource::get_resource_selection_type( $product_id );

			// Getting Date & Time Format Setting.
			$global_settings        = bkap_global_setting();
			$date_format_to_display = $global_settings->booking_date_format;
			$time_format_to_display = bkap_common::bkap_get_time_format( $global_settings );

			// Fetching Labels of Booking fields.
			$book_item_meta_date     = get_option( 'book_item-meta-date' );
			$book_item_meta_date     = ( '' === $book_item_meta_date ) ? __( 'Start Date', 'woocommerce-booking' ) : $book_item_meta_date;
			$checkout_item_meta_date = get_option( 'checkout_item-meta-date' );
			$checkout_item_meta_date = ( '' === $checkout_item_meta_date ) ? __( 'End Date', 'woocommerce-booking' ) : $checkout_item_meta_date;
			$book_item_meta_time     = get_option( 'book_item-meta-time' );
			$book_item_meta_time     = ( '' === $book_item_meta_time ) ? __( 'Booking Time', 'woocommerce-booking' ) : $book_item_meta_time;

			// Get booking object.
			$booking                     = new BKAP_Booking( $post_id );
			$bkap_setting                = bkap_setting( $product_id );
			$hidden_date                 = $post['wapbk_hidden_date'];
			$booking_data['date']        = date( 'Y-m-d', strtotime( $hidden_date ) );
			$booking_data['hidden_date'] = $hidden_date;
			$booking_type                = get_post_meta( $product_id, '_bkap_booking_type', true );
			$days                        = 1;
			$old_end                     = '';
			$old_time                    = '';
			$new_time                    = '';

			if ( 'multiple_days' === $booking_type ) {

				$old_end                              = date( 'Y-m-d', strtotime( $booking->get_end() ) );
				$hidden_date_checkout                 = $post['wapbk_hidden_date_checkout'];
				$booking_data['date_checkout']        = date( 'Y-m-d', strtotime( $hidden_date_checkout ) );
				$booking_data['hidden_date_checkout'] = $hidden_date_checkout;
				$days                                 = ceil( ( strtotime( $hidden_date_checkout ) - strtotime( $hidden_date ) ) / 86400 );

			} elseif ( 'date_time' === $booking_type || 'multidates_fixedtime' === $booking_type ) {
				$old_time       = $booking->get_time();
				$new_time_array = explode( '-', wc_clean( $post['time_slot'] ) );
				$new_time       = bkap_date_as_format( trim( $new_time_array[0] ), 'H:i' );
				if ( isset( $new_time_array[1] ) && '' != $new_time_array[1] ) {
					$new_time .= ' - ' . bkap_date_as_format( trim( $new_time_array [1] ), 'H:i' );
				}

				$booking_data['time_slot'] = $new_time;
			} elseif ( 'duration_time' === $booking_type ) {
				$old_time                           = $booking->get_selected_duration_time();
				$new_time                           = wc_clean( $post['duration_time_slot'] );
				$booking_data['duration_time_slot'] = $new_time;

				$old_duration                      = $booking->get_selected_duration();
				$new_duration                      = wc_clean( $post['bkap_duration_field'] );
				$booking_data['selected_duration'] = $new_duration;
			}

			$new_qty       = ( $post['bkap_qty'] != '' ) ? wc_clean( $post['bkap_qty'] ) : 1;
			$new_status    = wc_clean( $post['_bkap_status'] );
			$product       = wc_get_product( $product_id );
			$product_title = $product->get_name();

			// get the existing data, so we can figure out what has been modified.
			$old_qty    = get_post_meta( $post_id, '_bkap_qty', true );
			$old_status = $booking->get_status();
			$old_start  = bkap_date_as_format( $booking->get_start(), 'Y-m-d' );
			$item_id    = get_post_meta( $post_id, '_bkap_order_item_id', true );
			$order_id   = bkap_order_id_by_itemid( $item_id );

			// default the variables.
			$qty_update       = false;
			$date_update      = false;
			$time_update      = false;
			$resource_changed = false;
			$notes_array      = array();

			$current_user      = wp_get_current_user();
			$current_user_name = $current_user->display_name;

			/* Checking if the quantity is changed */
			if ( absint( $old_qty ) !== absint( $new_qty ) ) {
				$qty_update    = true;
				$notes_array[] = "The quantity for $product_title was modified from $old_qty to $new_qty by $current_user_name";
			}

			/* Checking if the booking details are changed */
			if ( strtotime( $old_start ) !== strtotime( $hidden_date ) ) {
				$date_update = true;
			}

			if ( 'multiple_days' === $booking_type ) {
				if ( strtotime( $old_end ) !== strtotime( $hidden_date_checkout ) ) {
					$date_update = true;
				}
			} elseif ( 'date_time' === $booking_type || 'multidates_fixedtime' === $booking_type ) {
				if ( $old_time !== $new_time ) {
					$time_update = true;
				}
			} elseif ( 'duration_time' === $booking_type ) {
				if ( $old_time !== $new_time ) {
					$time_update = true;
				}

				if ( $old_duration != $new_duration ) {
					$time_update = true;
				}
			}

			/* check if price has been modified */
			$new_price         = $post['bkap_price_charged'];
			$new_price_per_qty = (float) $new_price / (int) $new_qty;

			/* Woo Product Addon Options price are present then add those */
			$addon_price = wc_get_order_item_meta( $item_id, '_wapbk_wpa_prices' );
			if ( $addon_price && $addon_price > 0 ) {
				if ( isset( $global_settings->woo_product_addon_price ) && 'on' === $global_settings->woo_product_addon_price ) {
					$addon_price = $addon_price * $days;
				}
				$new_price_per_qty += $addon_price;
			}

			/* GF Product Addon Options price are present then add those */
			$gf_history = wc_get_order_item_meta( $item_id, '_gravity_forms_history' );
			if ( $gf_history && count( $gf_history ) > 0 ) {
				$gf_details = isset( $gf_history['_gravity_form_lead'] ) ? $gf_history['_gravity_form_lead'] : array();

				if ( count( $gf_details ) > 0 ) {
					$addon_price = array_pop( $gf_details );
					if ( isset( $addon_price ) && $addon_price > 0 ) {
						if ( isset( $global_settings->woo_gf_product_addon_option_price ) && 'on' === $global_settings->woo_gf_product_addon_option_price ) {
							$addon_price = $addon_price * $days;
						}
						$new_price_per_qty += $addon_price;
					}
				}
			}

			$new_price    = $new_price_per_qty * $new_qty;
			$old_price    = (float) $booking->get_cost() * $booking->get_quantity();
			$price_update = false;

			if ( $old_price !== $new_price ) {
				$price_update = apply_filters( 'bkap_price_change_on_edit_booking', true, $booking );
			}

			/* Checking if the persons data has been changed */
			$person_changed = false;
			if ( isset( $post['bkap_field_persons'] ) ) {
				$old_person_info = $booking->get_persons();
				$new_person_info = array( (int) $post['bkap_field_persons'] );

				if ( $old_person_info !== $new_person_info ) {
					$person_changed = true;
				}
			} else {
				if ( isset( $bkap_setting['bkap_person'] ) && 'on' === $bkap_setting['bkap_person'] && 'on' === $bkap_setting['bkap_person_type'] ) {
					$person_data     = $bkap_setting['bkap_person_data'];
					$old_person_info = $booking->get_persons();
					$new_person_info = array();
					foreach ( $person_data as $p_id => $p_data ) {
						$p_key = 'bkap_field_persons_' . $p_id;
						if ( isset( $_POST[ $p_key ] ) && '' !== $_POST[ $p_key ] ) {
							$new_person_info[ $p_id ] = (int) $_POST[ $p_key ];
						}
					}
					if ( $old_person_info !== $new_person_info ) {
						$person_changed = true;
					}
				}
			}

			/* Resource */
			$resource_id     = '';
			$new_resource_id = '';

			if ( isset( $post['bkap_front_resource_selection'] ) ) {

				$resource_id      = $post['bkap_front_resource_selection'];
				$old_resource_id  = $booking->get_resource();
				$resource_changed = false;

				if ( 'multiple' === $resource_selection_type ) {

					if ( ! is_array( $resource_id ) ) {
						$temp        = $resource_id;
						$resource_id = array( $temp );
					}

					// Fetch existing resource_ids from the Order.
					$old_resource_id = wc_get_order_item_meta( $item_id, '_resource_id' );

					if ( '' !== $old_resource_id && is_array( $old_resource_id ) ) {

						$_old_resource_id = array_values( $old_resource_id );
						sort( $_old_resource_id );

						$_resource_id = array_values( $resource_id );
						sort( $_resource_id );

						if ( $_resource_id !== $_old_resource_id ) {
							$new_resource_id  = $resource_id;
							$resource_changed = true;
						}
					}
				} else {
					if ( $resource_id !== $old_resource_id ) {
						$new_resource_id  = $resource_id;
						$resource_changed = true;
					}
				}
			}

			if ( $old_status !== $new_status || $qty_update || $date_update || $time_update || $resource_changed || $person_changed ) {
				// gather the data & validate.
				$data['product_id']           = $product_id;
				$data['booking_type']         = $booking_type;
				$data['qty']                  = $new_qty;
				$data['hidden_date']          = $booking_data['hidden_date'];
				$data['hidden_date_checkout'] = isset( $booking_data['hidden_date_checkout'] ) ? $booking_data['hidden_date_checkout'] : '';
				$data['time_slot']            = isset( $booking_data['time_slot'] ) ? $booking_data['time_slot'] : '';
				$data['duration_time_slot']   = isset( $booking_data['duration_time_slot'] ) ? $booking_data['duration_time_slot'] : '';
				$data['post_id']              = $post_id;

				if ( $old_status !== $new_status && 'cancelled' == $old_status ) {
					$data['edit_from'] = 'order';
				}

				$sanity_results = bkap_cancel_order::bkap_sanity_check( $data );
				if ( count( $sanity_results ) > 0 ) {
					update_post_meta( $post_id, '_bkap_update_errors', $sanity_results );
					return $post_data;
				}
			}

			if ( 'cancelled' === $new_status && $old_status === $new_status ) {
				$error = array(
					__( 'You can\'t update booking details of cancelled booking.', 'woocommerce-booking' ),
				);
				update_post_meta( $post_id, '_bkap_update_errors', $error );
				return $post_data;
			}

			$bookings = array();

			// When resource is changed, then update data in the respective places.
			if ( '' !== $resource_id ) {

				if ( 'single' === $resource_selection_type && $resource_changed ) {

					$old_resource_title = get_the_title( $booking->get_resource() );
					$resource_data      = wc_get_order_item_meta( $item_id, '_resource_id' );
					$resource_title     = Class_Bkap_Product_Resource::get_resource_name( $new_resource_id );
					$note               = "The resource for $product_title was modified from $old_resource_title to $resource_title by $current_user_name";

					Class_Bkap_Product_Resource::update_order_item_meta( $product_id, $item_id, $new_resource_id );
					update_post_meta( $post_id, '_bkap_resource_id', $new_resource_id );
					$notes_array[] = $note;
				}

				if ( 'multiple' === $resource_selection_type ) {

					$bookings = bkap_common::get_booking_id( $item_id, true );

					// Ensure that the resources that have been set conform to the number of bookings.
					try {
						$new_bookings = Class_Bkap_Product_Resource::conform_bookings_with_resources( $bookings, $resource_id, $post_id );
					} catch ( Exception $e ) {
						update_post_meta( $post_id, '_bkap_update_errors', $e->getMessage() );
						return $post_data;
					}

					foreach ( $new_bookings as $key => $id ) {

						$resource_title = Class_Bkap_Product_Resource::get_resource_name( $resource_id[ $key ] );

						// Create new bookings for new resources that have been added.
						if ( 0 === $id ) {
							$booking_data['price']       = $new_price_per_qty;
							$booking_data['resource_id'] = $resource_id[ $key ];
							$_booking                    = bkap_checkout::bkap_create_booking_post( $item_id, $product_id, $new_qty, $booking_data );
							$notes_array[]               = 'Booking #' . $_booking->id . ' has been created for Resource ' . $resource_title . '.Product: ' . $product_title;
							$new_bookings[ $key ]        = strval( $_booking->id );

							unset( $booking_data['price'] );
							unset( $booking_data['resource_id'] );
						} else {
							if ( $resource_changed ) {
								update_post_meta( $id, '_bkap_resource_id', $resource_id[ $key ] );
							}
						}
					}

					$bookings = $new_bookings;

					if ( $resource_changed ) {
						Class_Bkap_Product_Resource::update_order_item_meta( $product_id, $item_id, $resource_id );
					}
				}
			}

			/* Checking if the booking status is changed */
			if ( $old_status !== $new_status ) {
				$_POST['item_id']         = $item_id;
				$_POST['status']          = $new_status;
				$post_data['post_status'] = $new_status;

				if ( 'single' === $resource_selection_type ) {
					bkap_booking_confirmation::bkap_save_booking_status( $item_id, $new_status, $post_id );
				} elseif ( 'multiple' === $resource_selection_type ) {
					foreach ( $bookings as $id ) {
						bkap_booking_confirmation::bkap_save_booking_status( $item_id, $new_status, $id );
					}
				}
			}

			if ( $qty_update || $date_update || $time_update || $resource_changed || $person_changed ) {
				$booking_post = bkap_common::get_booking_id( $item_id ); // update the booking post status.
				$item_key     = 0;
				if ( is_array( $booking_post ) ) {
					foreach ( $booking_post as $k => $v ) {
						if ( $v == $post_id ) {
							$item_key = $k;
						}
					}
				}

				if ( 'cancelled' === $new_status || $date_update ) {
					if ( $booking_post ) { // update the booking post status.
						$new_booking = bkap_checkout::get_bkap_booking( $booking_post );
						do_action( 'bkap_rental_delete', $new_booking, $booking_post );
					}
				}

				$order_id = bkap_order_id_by_itemid( $item_id );
				if ( $order_id > 0 ) {

					$booking_ids_to_update = array();

					if ( 'single' === $resource_selection_type ) {
						$booking_ids_to_update[] = $post_id;
					} elseif ( 'multiple' === $resource_selection_type ) {
						$booking_ids_to_update = $bookings;
					}

					foreach ( $booking_ids_to_update as $id ) {

						// Update the booking information in the booking tables.
						bkap_edit_bookings_class::bkap_edit_bookings(
							$id,
							$item_key,
							$order_id,
							$item_id,
							$old_start,
							$old_end,
							$old_time,
							$product_id
						);

						// add a new booking.
						$details = bkap_checkout::bkap_update_lockout( $order_id, $product_id, 0, $new_qty, $booking_data );

						// update the global time slot lockout.
						if ( isset( $booking_data['time_slot'] ) && $booking_data['time_slot'] != '' ) {
							bkap_checkout::bkap_update_global_lockout( $product_id, $new_qty, $details, $booking_data );
						}
					}

					// update item meta.
					$display_start = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $hidden_date ) );
					if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {
						$item_bookings = bkap_common::get_booking_id( $item_id );
						foreach ( $item_bookings as $k => $v ) {
							if ( $v == $post_id ) {
								$item_key = $k;
							}
						}

						if ( isset( $item_key ) ) {
							bkap_update_order_itemmeta_multidates( $item_id, $book_item_meta_date, $display_start, $booking->get_start_date(), $item_key );
							bkap_update_order_itemmeta_multidates( $item_id, '_wapbk_booking_date', date( 'Y-m-d', strtotime( $hidden_date ) ), $old_start, $item_key );
						}
					} else {
						wc_update_order_item_meta( $item_id, $book_item_meta_date, $display_start, $booking->get_start_date() );
						wc_update_order_item_meta( $item_id, '_wapbk_booking_date', date( 'Y-m-d', strtotime( $hidden_date ) ), $old_start );
					}

					$meta_start = date( 'Ymd', strtotime( $hidden_date ) );

					switch ( $booking_type ) {
						case 'only_day':
						case 'multidates':
							$meta_start .= '000000';
							$meta_end    = $meta_start;

							// add order notes if needed.
							if ( $date_update ) {
								$old_start_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $old_start ) );
								$notes_array[]     = "The booking details have been modified from $old_start_display to $display_start by $current_user_name";
							}
							break;
						case 'multiple_days':
							$display_end = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $hidden_date_checkout ) );
							wc_update_order_item_meta( $item_id, $checkout_item_meta_date, $display_end, '' );

							wc_update_order_item_meta( $item_id, '_wapbk_checkout_date', date( 'Y-m-d', strtotime( $hidden_date_checkout ) ), '' );

							$meta_start .= '000000';
							$meta_end    = date( 'Ymd', strtotime( $hidden_date_checkout ) );
							$meta_end   .= '000000';

							// add order notes if needed.
							if ( $date_update ) {
								$old_start_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $old_start ) );
								$old_end_display   = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $old_end ) );
								$notes_array[]     = "The booking details have been modified from $old_start_display - $old_end_display to $display_start - $display_end by $current_user_name";
							}
							break;
						case 'date_time':
						case 'multidates_fixedtime':
							$time_array    = explode( ' - ', $new_time );
							$timezone_name = $booking->get_timezone_name();

							if ( $timezone_name != '' ) {
								$offset = bkap_get_offset( $booking->get_timezone_offset() );
								date_default_timezone_set( bkap_booking_get_timezone_string() );
								$display_time = date( $time_format_to_display, $offset + strtotime( $time_array[0] ) );
								$db_time      = date( 'H:i', $offset + strtotime( $time_array[0] ) );

								$hidden_date_time_str = $offset + strtotime( $hidden_date . ' ' . $time_array[0] );

								if ( isset( $time_array[1] ) && '' !== $time_array[1] ) {
									$display_time .= ' - ' . date( $time_format_to_display, $offset + strtotime( $time_array[1] ) );
									$db_time      .= ' - ' . date( 'H:i', $offset + strtotime( $time_array[1] ) );

									date_default_timezone_set( 'UTC' );

									$meta_end  = date( 'Ymd', strtotime( $hidden_date ) );
									$meta_end .= date( 'His', strtotime( $time_array[1] ) );
								} else {
									date_default_timezone_set( 'UTC' );
									$meta_end  = date( 'Ymd', strtotime( $hidden_date ) );
									$meta_end .= '000000';
								}

								$display_start = date( $bkap_date_formats[ $date_format_to_display ], $hidden_date_time_str );
								wc_update_order_item_meta( $item_id, $book_item_meta_date, $display_start, '' );
								wc_update_order_item_meta( $item_id, '_wapbk_booking_date', date( 'Y-m-d', $hidden_date_time_str ), '' );
								// $meta_start = date( 'Ymd', $hidden_date_time_str );

								$meta_start .= date( 'His', strtotime( trim( $time_array[0] ) ) );
							} else {
								$display_time = date( $time_format_to_display, strtotime( $time_array[0] ) );
								$db_time      = date( 'H:i', strtotime( $time_array[0] ) );
								$meta_start  .= date( 'His', strtotime( $time_array[0] ) );
								if ( isset( $time_array[1] ) && '' !== $time_array[1] ) {
									$display_time .= ' - ' . date( $time_format_to_display, strtotime( $time_array[1] ) );
									$db_time      .= ' - ' . date( 'H:i', strtotime( $time_array[1] ) );
									$meta_end      = date( 'Ymd', strtotime( $hidden_date ) );
									$meta_end     .= date( 'His', strtotime( $time_array[1] ) );
								} else {
									$meta_end  = date( 'Ymd', strtotime( $hidden_date ) );
									$meta_end .= '000000';
								}
							}

							$booking_datas = $data;
							$display_time  = apply_filters( 'bkap_new_time_in_note_on_edit_booking', $display_time, $data, $product_id );

							// add order notes if needed.
							if ( $date_update || $time_update ) {
								$old_start_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $old_start ) );

								if ( $timezone_name != '' ) {
									$old_time_disp = bkap_convert_system_time_to_timezone_time( $old_time, $offset, $time_format_to_display );
								} else {
									$old_time_array = explode( '-', $old_time );
									$old_time_disp  = date( $time_format_to_display, strtotime( trim( $old_time_array[0] ) ) );

									if ( isset( $old_time_array[1] ) && '' !== $old_time_array[1] ) {
										$old_time_disp .= ' - ' . date( $time_format_to_display, strtotime( $old_time_array[1] ) );
									}
								}

								$time_change_note = apply_filters(
									'bkap_edit_booking_time_change_note',
									"The booking details have been modified from $old_start_display, $old_time_disp to $display_start, $display_time by $current_user_name",
									$product_id,
									array(
										'old_display_date' => $old_start_display,
										'old_display_time' => $old_time_disp,
										'new_display_date' => $display_start,
										'new_display_time' => $display_time,
										'user_name'        => $current_user_name,
										'old_date'         => $old_start,
										'old_time'         => $old_time,
										'new_date'         => $hidden_date,
										'new_time'         => $new_time,
									)
								);

								$notes_array[] = $time_change_note;

								if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {
									if ( isset( $item_key ) ) {
										bkap_update_order_itemmeta_multidates( $item_id, $book_item_meta_time, $display_time, $old_time_disp, $item_key );
										bkap_update_order_itemmeta_multidates( $item_id, '_wapbk_time_slot', $db_time, $old_time, $item_key );
									}
								} else {
									wc_update_order_item_meta( $item_id, $book_item_meta_time, $display_time, '' );
									wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $db_time, '' );
								}
							}

							break;

						case 'duration_time':
							$start_date        = $data['hidden_date'];
							$date_booking      = date( 'Y-m-d', strtotime( $data['hidden_date'] ) );
							$time              = $data['duration_time_slot'];
							$meta_start        = date( 'YmdHis', strtotime( $date_booking . ' ' . $time ) );
							$selected_duration = explode( '-', $data['selected_duration'] );
							$end_date_str      = $date_booking;

							$hour     = $selected_duration[0];
							$d_type   = $selected_duration[1];
							$end_str  = bkap_common::bkap_add_hour_to_date( $start_date, $time, $hour, $product_id, $d_type ); // return end date timestamp
							$meta_end = date( 'YmdHis', $end_str );
							$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta

							// updating end date
							if ( $data['hidden_date'] != $end_date ) {

								$name_checkout = ( '' == get_option( 'checkout_item-meta-date' ) ) ? __( 'End Date', 'woocommerce-booking' ) : get_option( 'checkout_item-meta-date' );

								$bkap_format  = bkap_common::bkap_get_date_format(); // get date format set at global
								$end_date_str = date( 'Y-m-d', strtotime( $end_date ) ); // conver date to Y-m-d format

								$end_date_str    = $date_booking . ' - ' . $end_date_str;
								$end_date_string = date( $bkap_format, strtotime( $end_date ) ); // Get date based on format at global level

								$end_date_string = $start_date . ' - ' . $end_date_string;

								// Updating end date field in order item meta
								wc_update_order_item_meta( $item_id, '_wapbk_booking_date', sanitize_text_field( $end_date_str, '' ) );
								wc_update_order_item_meta( $item_id, $book_item_meta_date, sanitize_text_field( $end_date_string, '' ) );
							}

							$endtime  = date( 'H:i', $end_str );// getend time in H:i format
							$startime = bkap_common::bkap_get_formated_time( $time ); // return start time based on the time format at global
							$endtime  = bkap_common::bkap_get_formated_time( $endtime ); // return end time based on the time format at global

							$time_slot = $startime . ' - ' . $endtime; // to store time sting in the _wapbk_time_slot key of order item meta

							// Updating timeslot
							$time_slot_label = ( '' == get_option( 'book_item-meta-time' ) ) ? __( 'Booking Time', 'woocommerce-booking' ) : get_option( 'book_item-meta-time' );

							wc_update_order_item_meta( $item_id, $time_slot_label, $time_slot, '' );
							wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $time_slot, '' );

							$notes_array[] = "The booking details have been modified to $end_date_str, $time_slot by $current_user_name";
							break;
					}

					// if qty has been updated, update the same to be reflected in Woo->Orders.
					if ( $qty_update ) {
						wc_update_order_item_meta( $item_id, '_qty', $new_qty, '' );
					}

					if ( $person_changed ) {

						wc_update_order_item_meta( $item_id, '_persons', $new_person_info );
						update_post_meta( $post_id, '_bkap_persons', $new_person_info );

						if ( 'multiple' === $resource_selection_type ) {
							Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_persons', $new_person_info );
						}

						if ( isset( $new_person_info[0] ) ) {
							wc_update_order_item_meta( $item_id, Class_Bkap_Product_Person::bkap_get_person_label( $product_id ), $new_person_info[0] );
						} else {
							foreach ( $new_person_info as $p_key => $p_data ) {
								wc_update_order_item_meta( $item_id, get_the_title( $p_key ), $p_data );
							}
						}
						$notes_array[] = sprintf( __( 'The person data for %1$s was modified by %2$s' ), $product_title, $current_user_name );
					}

					if ( isset( $post['block_option'] ) ) { // updating selected fixed block data.
						update_post_meta( $post_id, '_bkap_fixed_block', $fixed_block );

						if ( 'multiple' === $resource_selection_type ) {
							Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_fixed_block', $fixed_block );
						}
					}

					// update the post meta for the booking.
					update_post_meta( $post_id, '_bkap_start', $meta_start );
					update_post_meta( $post_id, '_bkap_end', $meta_end );
					update_post_meta( $post_id, '_bkap_qty', $new_qty );

					if ( 'multiple' === $resource_selection_type ) {
						Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_start', $meta_start );
						Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_end', $meta_end );
						Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_qty', $new_qty );
					}

					$new_order_obj = wc_get_order( $order_id );

					if ( $price_update ) {
						$newprice = bkap_common::compute_price_for_order_with_tax_for_edited_bookings( $product_id, $item_id, $order_id, $new_price );
						// $wc_price_args = bkap_common::get_currency_args();
						// $newprice      = number_format( $newprice, $wc_price_args['decimals'], $wc_price_args['decimal_separator'], $wc_price_args['thousand_separator'] );
						update_post_meta( $post_id, '_bkap_cost', $newprice );

						if ( 'multiple' === $resource_selection_type ) {
							Class_Bkap_Product_Resource::update_data_for_related_bookings( $post_id, '_bkap_cost', $newprice );
						}

						// update the order total.
						$old_total = $new_order_obj->get_total();
						$new_total = round( $old_total - $old_price + $new_price, 2 );
						// $new_order_obj->set_total( $new_total );

						$order_currency    = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $new_order_obj->get_order_currency() : $new_order_obj->get_currency();
						$currency_symbol   = get_woocommerce_currency_symbol( $order_currency );
						$display_old_price = $currency_symbol . $old_price;
						$display_new_price = $currency_symbol . $newprice;
						$notes_array[]     = "The booking price for $product_title has been modified from $display_old_price to $display_new_price by $current_user_name";
					}

					$new_order_obj->calculate_totals();

					// Creating Zoom Meeting.
					bkap_common::create_zoom_meetings_for_edited_bookings( $order_id, $post_id );

					bkap_insert_event_to_gcal( $new_order_obj, $product_id, $item_id, $item_key );

					if ( is_array( $notes_array ) && count( $notes_array ) > 0 ) { // add order notes.
						foreach ( $notes_array as $msg ) {
							$new_order_obj->add_order_note( __( $msg, 'woocommerce-booking' ) );
						}
					}

					do_action( 'bkap_after_update_booking_post', $post_id, $booking, bkap_get_meta_data( $post_id ) );
				}
			}

			return $post_data;
		}
	}
	$bkap_edit_booking_post = new Bkap_Edit_Booking_Post();
}
