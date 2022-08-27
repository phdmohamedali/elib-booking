<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Allow Bookings to be edited from Cart and Checkout Page
 *
 * @author      Tyche Softwares
 * @package     BKAP/Reschedule
 * @category    Classes
 */

if ( ! class_exists( 'bkap_edit_bookings_class' ) ) {

	/**
	 * Class for allowing Bookings to be edited from Cart and Checkout Page
	 *
	 * @since 4.1.0
	 */
	class bkap_edit_bookings_class {

		/**
		 * Constructor function
		 *
		 * @param array $global_settings Global Settings array.
		 * @since 4.1.0
		 */
		public function __construct() {

			add_action( 'admin_init', array( &$this, 'bkap_add_edit_settings' ) );

			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $global_settings->bkap_enable_booking_edit ) &&
				$global_settings->bkap_enable_booking_edit === 'on' ) {

				add_filter( 'woocommerce_cart_item_name', array( &$this, 'bkap_add_edit_link' ), 10, 3 );
			}

			if ( (
					isset( $global_settings->bkap_enable_booking_reschedule ) &&
					$global_settings->bkap_enable_booking_reschedule === 'on'
				) ||
				(
					isset( $global_settings->bkap_enable_booking_without_date ) &&
					'on' === $global_settings->bkap_enable_booking_without_date
				)
			) {

				add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_add_reschedule_link' ), 10, 3 );
			}

			add_action( 'wp_ajax_nopriv_bkap_update_edited_bookings', array( &$this, 'bkap_update_edited_bookings' ) );
			add_action( 'wp_ajax_bkap_update_edited_bookings', array( &$this, 'bkap_update_edited_bookings' ) );
		}

		/**
		 * Load modal template for booking box
		 *
		 * @param array      $booking_details Booking Details array.
		 * @param WC_Product $cart_product Product Object.
		 * @param int|string $product_id Product ID.
		 * @param array      $localized_array Localized array to be passed to JS.
		 * @param string     $bkap_cart_item_key Cart Item key or Order Item key for unique ID of modal.
		 * @param int|string $variation_id Variation ID.
		 * @param int|string $additional_addon_data Gravity Forms Options totals.
		 * @since 4.1.0
		 */
		public static function bkap_load_template( $booking_details, $cart_product, $product_id, $localized_array, $bkap_cart_item_key, $variation_id, $additional_addon_data = array() ) {

			$bkap_order_id = ! empty( $localized_array['bkap_order_id'] ) ? $localized_array['bkap_order_id'] : 0;
			wc_get_template(
				'bkap-edit-booking-modal.php',
				array(
					'bkap_booking'       => $booking_details,
					'product_obj'        => $cart_product,
					'bkap_order_id'      => $bkap_order_id,
					'product_id'         => $product_id,
					'variation_id'       => $variation_id,
					'bkap_cart_item_key' => $bkap_cart_item_key,
					'bkap_addon_data'    => $additional_addon_data,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);

			$plugin_version_number = get_option( 'woocommerce_booking_db_version' );

			if ( isset( $variation_id ) && $variation_id > 0 ) {
				$variation_class = new WC_Product_Variation( $variation_id );
				$get_attributes  = $variation_class->get_variation_attributes();

				if ( is_array( $get_attributes ) && count( $get_attributes ) > 0 ) {
					foreach ( $get_attributes as $attr_name => $attr_value ) {
						$attr_value = htmlspecialchars( $attr_value, ENT_QUOTES );
						// print a hidden field for each of these.
						print( "<input type='hidden' name='$attr_name' value='$attr_value' />" );
					}
				}
			}

			self::bkap_enqueue_edit_bookings_scripts(
				$bkap_cart_item_key,
				$plugin_version_number,
				$localized_array
			);

			self::bkap_enqueue_edit_bookings_styles(
				$plugin_version_number
			);
		}

		/**
		 * Add Edit Link on Cart and Checkout page
		 *
		 * @param string     $product_title Product Title to which additional string needs to be appeded.
		 * @param WC_Product $cart_item Cart Item in WC_Product object form.
		 * @param string     $cart_item_key Cart Item key.
		 * @return string Product Title with appended data.
		 * @since 4.1.0
		 *
		 * @hook woocommerce_cart_item_name
		 */
		public function bkap_add_edit_link( $product_title, $cart_item, $cart_item_key ) {

			if ( ( ( is_cart() && ! wp_doing_ajax() ) || is_checkout() ) && ! is_product() &&
				! ( is_wc_endpoint_url( 'view-order' ) || is_wc_endpoint_url( 'order-received' ) ) &&
				isset( $cart_item['bkap_booking'] ) &&
				! bkap_common::bkap_is_cartitem_bundled( $cart_item ) &&
				! bkap_common::bkap_is_cartitem_composite( $cart_item ) ) {

				$product_id   = $cart_item['product_id'];
				$product_id   = bkap_common::bkap_get_product_id( $product_id );
				$booking_type = bkap_type( $product_id );

				// Not allowing edit for multidates
				if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {
					return $product_title;
				}

				$_product          = $cart_item['data'];
				$product_permalink = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';

				if ( $cart_item['variation_id'] !== 0 ) {
					$variation_id = $cart_item['variation_id'];
				} else {
					$variation_id = 0;
				}

				$edit_booking_str = apply_filters( 'bkap_edit_booking_label', 'Edit Booking' );
				$edit_booking_str = __( $edit_booking_str, 'woocommerce-booking' );

				if ( strpos( $product_title, '<a href' ) !== false ) {

					printf(
						'<a href="%s">%s</a><div style="clear:both;"></div><input type="button" class="bkap_edit_bookings" onclick=bkap_edit_booking_class.bkap_edit_bookings(%d,"%s") value="%s">',
						esc_url( $product_permalink ),
						$_product->get_name(),
						$product_id,
						$cart_item_key,
						$edit_booking_str
					);

					$product_title = '';
				} else {
					printf(
						'%s<div style="clear:both;"></div><input type="button" class="bkap_edit_bookings" onclick=bkap_edit_booking_class.bkap_edit_bookings(%d,"%s") value="%s">',
						$_product->get_name(),
						$product_id,
						$cart_item_key,
						$edit_booking_str
					);

					$product_title = '';
				}

				$page_type = '';
				if ( is_cart() ) {
					$page_type = 'cart';
				} elseif ( is_checkout() ) {
					$page_type = 'checkout';
				}

				$localized_array = array(
					'bkap_booking_params' => $cart_item['bkap_booking'][0],
					'bkap_cart_item'      => $cart_item,
					'bkap_cart_item_key'  => $cart_item_key,
					'bkap_page_type'      => $page_type,
				);

				// Additional data for addons.
				$additional_addon_data = bkap_common::bkap_get_cart_item_addon_data( $cart_item );

				self::bkap_load_template(
					$cart_item['bkap_booking'][0],
					$cart_item['data'],
					$product_id,
					$localized_array,
					$cart_item_key,
					$variation_id,
					$additional_addon_data
				);

				return $product_title;
			} else {
				return $product_title;
			}
		}

		/**
		 * Add Edit Booking link on My Account Page
		 *
		 * @param srting        $item_id Order Item ID.
		 * @param WC_Order_Item $item Order Item.
		 * @param WC_Order      $order Order Object.
		 * @since 4.1.0
		 *
		 * @hook woocommerce_order_item_meta_end
		 */
		public function bkap_add_reschedule_link( $item_id, $item, $order ) {

			$book_item_meta_date     = ( '' == get_option( 'book_item-meta-date' ) ) ? __( 'Start Date', 'woocommerce-booking' ) : get_option( 'book_item-meta-date' );
			$checkout_item_meta_date = ( '' == get_option( 'checkout_item-meta-date' ) ) ? __( 'End Date', 'woocommerce-booking' ) : get_option( 'checkout_item-meta-date' );
			$book_item_meta_time     = ( '' == get_option( 'book_item-meta-time' ) ) ? __( 'Booking Time', 'woocommerce-booking' ) : get_option( 'book_item-meta-time' );

			if ( is_wc_endpoint_url( 'view-order' ) ) {

				$order_status = $order->get_status();

				if ( isset( $order_status ) && ( $order_status !== 'cancelled' ) && ( $order_status !== 'refunded' ) && ( $order_status !== 'trash' ) && ( $order_status !== '' ) && ( $order_status !== 'failed' ) && ( 'auto-draft' !== $order_status ) && ! bkap_common::bkap_is_orderitem_bundled( $item ) && ! bkap_common::bkap_is_orderitem_composite( $item ) ) {

					$booking_details = array(
						'date'                 => '',
						'hidden_date'          => '',
						'date_checkout'        => '',
						'hidden_date_checkout' => '',
						'price'                => '',
					);

					foreach ( $item->get_meta_data() as $meta_index => $meta ) {

						if ( $meta->key === $book_item_meta_date ) {
							$booking_details['date'] = $meta->value;
						} elseif ( $meta->key === '_wapbk_booking_date' ) {
							$hidden_date                    = explode( '-', $meta->value );
							$booking_details['hidden_date'] = $hidden_date[2] . '-' . $hidden_date[1] . '-' . $hidden_date[0];
						} elseif ( $meta->key === $checkout_item_meta_date ) {
							$booking_details['date_checkout'] = $meta->value;
						} elseif ( $meta->key === '_wapbk_checkout_date' ) {
							$hidden_date_checkout                    = explode( '-', $meta->value );
							$booking_details['hidden_date_checkout'] = $hidden_date_checkout[2] . '-' . $hidden_date_checkout[1] . '-' . $hidden_date_checkout[0];
						} elseif ( $meta->key === $book_item_meta_time ) {
							$booking_details['time_slot'] = $meta->value;
						} elseif ( $meta->key == '_resource_id' ) {
							$booking_details['resource_id'] = $meta->value;
						} elseif ( $meta->key == '_wapbk_booking_status' ) {
							$booking_details['booking_status'] = $meta->value;
						} elseif ( $meta->key == '_person_ids' ) {
							$booking_details['persons'] = $meta->value;
						}
					}

					$booking_details = apply_filters( 'bkap_add_reschedule_link_booking_details', $booking_details, $item, $order );

					$product_id   = $item->get_product_id( 'view' );
					$bkap_setting = bkap_setting( $product_id );
					$booking_type = bkap_type( $product_id );

					// Not allowing rescheduling for multidates.
					$show_button = true;
					if ( in_array( $booking_type, array( 'multidates', 'multidates_fixedtime' ), true ) ) {
						$show_button = false;
					}

					if ( 'duration_time' === $booking_type && isset( $bkap_setting['bkap_duration_settings'] ) && ! empty( $bkap_setting['bkap_duration_settings'] ) ) {
						$d_setting = $bkap_setting['bkap_duration_settings'];

						$base_interval = (int) $d_setting['duration']; // 2 Hour set for product.
						$duration_type = $d_setting['duration_type']; // Type of Duration set for product Hours/mins.

						$time_range                            = $booking_details['time_slot'];
						$exploded_time                         = explode( ' - ', $time_range );
						$f_time                                = date( 'H:i', strtotime( $exploded_time[0] ) );
						$booking_details['duration_time_slot'] = $f_time;

						if ( isset( $exploded_time[1] ) && $exploded_time[1] != '' ) {
							$t_time = $exploded_time[1];
						}

						$time1 = strtotime( $f_time );
						$time2 = strtotime( $t_time );

						if ( 'hours' === $duration_type ) {
							$difference = round( abs( $time2 - $time1 ) / 3600, 2 );
						} else {
							$difference = round( abs( $time2 - $time1 ) / 60, 2 );
						}

						$selected_duration = $difference . '-' . $duration_type; // Entered value on front end : 1.

						$booking_details['selected_duration'] = $selected_duration;
						unset( $booking_details['time_slot'] );
						if ( isset( $booking_details['hidden_date_checkout'] ) ) {
							unset( $booking_details['hidden_date_checkout'] );
						}
						if ( isset( $booking_details['date_checkout'] ) ) {
							unset( $booking_details['date_checkout'] );
						}
					}

					$diff_from_booked_date = (int) ( (int) strtotime( $booking_details['hidden_date'] ) - current_time( 'timestamp' ) );
					$global_settings       = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

					// For clarity, all time comparisons shall be in seconds.
					$_diff_interval   = 0;
					$reschedule_hours = $this->bkap_update_booking_reschedule_day_to_hour();
					$_diff_interval   = (int) $reschedule_hours * 60 * 60; // Convert to seconds so that it can be same format with $diff_from_booked_date.

					if (
						( isset( $global_settings->bkap_enable_booking_reschedule ) &&
						isset( $global_settings->bkap_booking_reschedule_hours ) &&
						$diff_from_booked_date >= $_diff_interval &&
						'on' === $global_settings->bkap_enable_booking_reschedule &&
						'' !== $booking_details['date'] &&
						$show_button
						) ||
						( isset( $global_settings->bkap_enable_booking_without_date ) &&
							'on' === $global_settings->bkap_enable_booking_without_date &&
							'on' === $bkap_setting['booking_purchase_without_date']
						)
					) {

						$edit_booking_label = apply_filters(
							'bkap_edit_booking_label',
							__( 'Reschedule Booking', 'woocommerce-booking' )
						);

						if ( isset( $global_settings->bkap_enable_booking_without_date ) &&
							'on' === $global_settings->bkap_enable_booking_without_date &&
							'on' === $bkap_setting['booking_purchase_without_date'] &&
							'' === $booking_details['date']
						) {
							$edit_booking_label = apply_filters(
								'bkap_edit_booking_label',
								__( 'Add Booking Date', 'woocommerce-booking' )
							);
						}

						if ( ! isset( $booking_details['booking_status'] ) || 'cancelled' != $booking_details['booking_status'] ) {
							printf( '<input type="button" class="bkap_edit_bookings" onclick="bkap_edit_booking_class.bkap_edit_bookings(%d,%s)" value="%s">', $item->get_product_id( 'view' ), $item_id, __( $edit_booking_label, 'woocommerce-booking' ) );
						} else {
							esc_html_e( 'Cancelled', 'woocommerce-booking' );
						}

						$localized_array = array(
							'bkap_booking_params' => $booking_details,
							'bkap_cart_item'      => $item,
							'bkap_cart_item_key'  => $item_id,
							'bkap_order_id'       => $order->get_id(),
							'bkap_page_type'      => 'view-order',
						);

						// Additional Data for addons.
						$additional_addon_data = bkap_common::bkap_get_order_item_addon_data( $item );

						self::bkap_load_template(
							$booking_details,
							$item->get_product(),
							$item->get_product_id( 'view' ),
							$localized_array,
							$item_id,
							$item->get_variation_id( 'view' ),
							$additional_addon_data
						);
					}

					$booking_id     = bkap_common::get_booking_id( $item_id );
					$current_date   = date( 'YmdHis', current_time( 'timestamp' ) );
					$start          = get_post_meta( $booking_id, '_bkap_start', true );
					$booking_status = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );
					if ( 'cancelled' !== $booking_status ) {

						if ( $start >= $current_date ) {
							$bkap_cancel_booking_action = Bkap_Cancel_Booking::bkap_cancel_booking_action( $booking_id, $called_from = 'order_details' );
							echo wp_kses_post( $bkap_cancel_booking_action );
						}
					}
				}
			}
		}

		/**
		 * Enqueue JS files for edit booking
		 *
		 * @param string $bkap_cart_item_key Unique ID used for Modal ID.
		 * @param string $plugin_version_number Plugin Version number.
		 * @param array  $localized_array Localized array to be passed to JS.
		 * @since 4.1.0
		 */
		public static function bkap_enqueue_edit_bookings_scripts( $bkap_cart_item_key, $plugin_version_number, $localized_array ) {

			wp_register_script(
				'bkap-edit-booking',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-edit-booking.js', BKAP_FILE ),
				'',
				$plugin_version_number,
				true
			);

			wp_localize_script( 'bkap-edit-booking', "bkap_edit_params_$bkap_cart_item_key", $localized_array );

			wp_enqueue_script( 'bkap-edit-booking' );
		}

		/**
		 * Enqueue CSS files
		 *
		 * @param string $plugin_version_number Plugin version number.
		 * @since 4.1.0
		 */
		public static function bkap_enqueue_edit_bookings_styles( $plugin_version_number ) {

			wp_enqueue_style(
				'bkap-edit-booking-styles',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/bkap-edit-booking.css', BKAP_FILE ),
				'',
				$plugin_version_number,
				false
			);
		}

		/**
		 * Ajax call back when confirm bookings is clicked on either Cart, Checkout or My Account Page
		 *
		 * @since 4.1.0
		 *
		 * @globals mixed $wpdb
		 *
		 * @hook wp_ajax_nopriv_bkap_update_edited_bookings
		 * @hook wp_ajax_bkap_update_edited_bookings
		 */
		public function bkap_update_edited_bookings() {

			global $wpdb;

			if ( isset( $_POST['page_type'] ) && $_POST['page_type'] !== 'view-order' ) {

				$session_cart  = WC()->session->cart;
				$cart_item_obj = $_POST['cart_item_obj'];
				// Set the per qty price for 'price' in 'bkap_booking'.
				$per_qty_price = $cart_item_obj['bkap_booking'][0]['price'] / $session_cart[ $_POST['cart_item_key'] ]['quantity'];

				$cart_item_obj['bkap_booking'][0]['price'] = $per_qty_price;

				/* Persons Calculations */
				if ( isset( $cart_item_obj['bkap_booking'][0]['persons'] ) ) {
					$data          = array();
					$person_update = false;
					foreach ( $cart_item_obj['bkap_booking'][0]['persons'] as $persons ) {
						if ( isset( $persons['person_id'] ) ) {
							$person_update                 = true;
							$data[ $persons['person_id'] ] = $persons['person_val'];
						}
					}
					if ( $person_update ) {
						$cart_item_obj['bkap_booking'][0]['persons'] = $data;
					}
				}
				$session_cart[ $_POST['cart_item_key'] ]['bkap_booking']  = $cart_item_obj['bkap_booking'];
				$session_cart[ $_POST['cart_item_key'] ]['line_total']    = 0;
				$session_cart[ $_POST['cart_item_key'] ]['line_subtotal'] = 0;

				if ( isset( $cart_item_obj['line_total'] ) ) {
					$session_cart[ $_POST['cart_item_key'] ]['line_total']    = $cart_item_obj['line_total'];
					$session_cart[ $_POST['cart_item_key'] ]['line_subtotal'] = $cart_item_obj['line_total'];
				}
				if ( isset( $cart_item_obj['bundled_items'] ) ) {
					$session_cart = self::bkap_update_bundled_cartitems( $session_cart, $cart_item_obj['bundled_items'], $cart_item_obj['bkap_booking'] );
				}

				WC()->session->set( 'cart', $session_cart );

				do_action( 'bkap_updated_edited_bookings', 'bkap_updated_edited_bookings', $session_cart );
			} elseif ( isset( $_POST['page_type'] ) && $_POST['page_type'] === 'view-order' ) {

				/* When Rescheduling the booking from My Account Page then it comes here */

				$order_id     = $_POST['order_id'];
				$item_id      = $_POST['item_id'];
				$booking_data = $_POST['booking_data'];
				$product_id   = $_POST['product_id'];
				$page         = $_POST['page_type'];
				$old_bookings = array();

				$book_item_meta_date = get_option( 'book_item-meta-date', '' );
				$book_item_meta_date = ( '' === $book_item_meta_date ) ? __( 'Start Date', 'woocommerce-booking' ) : $book_item_meta_date;

				$checkout_item_meta_date = get_option( 'checkout_item-meta-date', '' );
				$checkout_item_meta_date = ( '' === $checkout_item_meta_date ) ? __( 'End Date', 'woocommerce-booking' ) : $checkout_item_meta_date;

				$book_item_meta_time = get_option( 'book_item-meta-time', '' );
				$book_item_meta_time = ( '' === $book_item_meta_time ) ? __( 'Booking Time', 'woocommerce-booking' ) : $book_item_meta_time;

				$additional_data                     = array();
				$additional_data['start_date_label'] = $book_item_meta_date;
				$additional_data['end_date_label']   = $checkout_item_meta_date;
				$additional_data['time_slot_label']  = $book_item_meta_time;
				$additional_data['multiple_dates']   = false;

				$multidates  = false;
				$item_number = 0;
				if ( strpos( $item_id, '_' ) !== false ) {
					$item_id_explode = explode( '_', $item_id );
					$item_id         = $item_id_explode[0];
					$item_number     = $item_id_explode[1];

					$item_boking_date           = wc_get_order_item_meta( $item_id, $book_item_meta_date, false );
					$item_booking_date_checkout = wc_get_order_item_meta( $item_id, $checkout_item_meta_date, false );
					$item_time_slot             = wc_get_order_item_meta( $item_id, $book_item_meta_time, false );

					$old_bookings['booking_date']          = $item_boking_date[ $item_number ];
					$old_bookings['booking_date_checkout'] = isset( $item_booking_date_checkout[ $item_number ] ) ? $item_booking_date_checkout[ $item_number ] : '';
					$old_bookings['time_slot']             = isset( $item_time_slot[ $item_number ] ) ? $item_time_slot[ $item_number ] : '';

					$multidates                        = true;
					$additional_data['multiple_dates'] = $multidates;

					$booking_data['item_number']    = $item_number;
					$additional_data['item_number'] = $item_number;

				} else {

					$old_bookings['booking_date']          = wc_get_order_item_meta( $item_id, $book_item_meta_date );
					$old_bookings['booking_date_checkout'] = wc_get_order_item_meta( $item_id, $checkout_item_meta_date );
					$old_bookings['time_slot']             = wc_get_order_item_meta( $item_id, $book_item_meta_time );
				}

				// Fallback to zero if not set.
				if ( ! isset( $additional_data['item_number'] ) ) {
					$additional_data['item_number'] = 0;
				}

				if ( isset( $booking_data['duration_time_slot'] ) ) {
					$old_bookings['duration_time_slot'] = $old_bookings['time_slot'];
				}

				/* Persons Calculations */
				if ( isset( $_POST['booking_data']['persons'] ) ) {
					$data          = array();
					$person_update = false;
					foreach ( $_POST['booking_data']['persons'] as $persons ) {

						if ( isset( $persons['person_id'] ) ) {
							$person_update                 = true;
							$data[ $persons['person_id'] ] = $persons['person_val'];
						}
					}
					if ( $person_update ) {
						$booking_data['persons'] = $data;
					}
				}

				$additional_data['old_bookings'] = $old_bookings;

				$item_obj    = new WC_Order_Item_Product( $item_id );
				$product_obj = wc_get_product( $product_id );
				$quantity    = $item_obj->get_quantity();

				$item_total = number_format( (float) $item_obj->get_total(), wc_get_price_decimals(), '.', '' );
				$order      = wc_get_order( $order_id );

				$item_tax   = number_format( (float) $item_obj->get_total_tax(), wc_get_price_decimals(), '.', '' );
				$item_total = ( $item_total + $item_tax ) / $quantity;

				$booking_id = isset( $_POST['booking_post_id'] ) ? $_POST['booking_post_id'] : bkap_common::get_booking_id( $item_id );

				if ( is_array( $booking_id ) && ! $multidates ) {
					foreach ( $booking_id as $key => $id ) {
						self::bkap_call_update_item_bookings( $order_id, $item_id, $item_obj, $product_id, $booking_data, $id, $quantity, $page, $key, $additional_data );
					}
				} else {
					self::bkap_call_update_item_bookings( $order_id, $item_id, $item_obj, $product_id, $booking_data, $booking_id, $quantity, $page, 0, $additional_data );
				}

				$new_item_total = (float) $_POST['booking_data']['booking_price'];

				if ( $multidates ) {
					$old_price = get_post_meta( $booking_id, '_bkap_cost', true );
					if ( $old_price != $new_item_total ) {
						$updated_item_total = $item_total - $old_price;
						$updated_item_total = $updated_item_total + $new_item_total;
					}
				}

				foreach ( $order->get_coupon_codes() as $coupon_code ) {

					// Retrieving the coupon ID.
					$coupon_post_obj = get_page_by_title( $coupon_code, OBJECT, 'shop_coupon' );
					$coupon_id       = $coupon_post_obj->ID;

					// Get an instance of WC_Coupon object in an array(necessary to use WC_Coupon methods).
					$coupon      = new WC_Coupon( $coupon_id );
					$coupon_type = $coupon->get_discount_type();

					if ( 'percent' === $coupon_type ) {
						$new_item_total = $new_item_total - ( ( $new_item_total * $coupon->get_amount() ) / 100 );
					} elseif ( 'fixed_product' === $coupon_type || 'fixed_cart' === $coupon_type ) {
						// @todo Issue when multiple products present in cart.
						$new_item_total = $new_item_total - $coupon->get_amount();
					}
				}

				if ( $multidates ) {
					$difference_amount = isset( $updated_item_total ) ? $updated_item_total - $item_total : 0;
				} else {
					$difference_amount = $new_item_total - $item_total;
				}

				$additional_note         = '';
				$difference_amount_order = apply_filters( 'bkap_new_order_for_difference_amount_on_reschedule', true, $difference_amount );

				if ( $difference_amount > 0 && $difference_amount_order ) {

					if ( wc_tax_enabled() && get_option( 'woocommerce_prices_include_tax' ) == 'yes' ) {

						$difference_amount = wc_get_price_excluding_tax(
							$product_obj,
							array( 'price' => $difference_amount )
						);
					}

					$item = array(
						'product' => $product_obj,
						'qty'     => $quantity,
						'amount'  => $difference_amount * $quantity,
					);

					$new_order_id = Bkap_Rescheduled_Order_Class::bkap_rescheduled_create_order( $order_id, $item );
					wc_update_order_item_meta( $item_id, '_bkap_resch_rem_bal_order_id', $new_order_id, '' );

					$additional_note = sprintf( __( 'Please pay difference amount via Order #%s', 'woocommerce-booking' ), $new_order_id );
				} elseif ( 0 > $difference_amount ) {
					$additional_note = __( 'Please contact shop manager for differences in amount', 'woocommerce-booking' );
				}

				$additional_note = apply_filters( 'bkap_additional_note_on_reschedule', $additional_note );

				self::bkap_add_reschedule_order_note( $product_id, $order_id, $old_bookings, $booking_data, $item_obj->get_name( 'view' ), $additional_note );

				// Trigger invoice email for additional order. This needs to be done after adding order notes.
				if ( $difference_amount > 0 && $difference_amount_order ) {
					$invoice_email = new WC_Email_Customer_Invoice();
					$invoice_email->trigger( $new_order_id );
				}

				do_action( 'bkap_booking_rescheduled_admin', $item_id, $item_number );
			}

			die();
		}

		public static function bkap_call_update_item_bookings( $order_id, $item_id, $item_obj, $product_id, $booking_data, $booking_id, $quantity, $page, $key, $additional_data ) {

			do_action( 'bkap_before_rescheduling_booking', $order_id, $item_id, $item_obj, $product_id, $booking_data, $booking_id, $quantity, $page, $key, $additional_data );

			$old_start    = '';
			$old_end      = '';
			$old_time     = '';
			$old_resource = '';
			$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );

			if ( $booking_id > 0 ) {
				$booking      = new BKAP_Booking( $booking_id );
				$old_start    = date( 'Y-m-d', strtotime( $booking->get_start() ) );
				$old_resource = $booking->get_resource();
				if ( 'multiple_days' === $booking_type ) {
					$old_end = date( 'Y-m-d', strtotime( $booking->get_end() ) );
				} elseif ( 'date_time' === $booking_type || 'multidates_fixedtime' == $booking_type ) {
					$old_time = $booking->get_time();
				}
			} else {
				// when do not have any bookings.
				wc_add_order_item_meta( $item_id, '_wapbk_booking_status', 'confirmed' );
				$booking    = bkap_checkout::bkap_create_booking_post( $item_id, $product_id, $quantity, $booking_data );
				$booking_id = $booking->id;
			}

			if ( isset( $booking_data['time_slot'] ) && '' != $booking_data['time_slot'] ) {

				if ( strpos( $booking_data['time_slot'], ',' ) !== false ) {

					$booking_data['multiple_time_slot'] = $booking_data['time_slot'];

					$time_slots = explode( ',', $booking_data['time_slot'] );

					foreach ( $time_slots as $k => $v ) {
						if ( $k == $key ) {
							$booking_data['time_slot'] = $v;
						}
					}
				}

				// Adding Timezone infomration in the booking data for further calculations.
				$timezone_name = wc_get_order_item_meta( $item_id, '_wapbk_timezone' );
				if ( $timezone_name != '' ) {
					$booking_data['timezone_name']   = $timezone_name;
					$booking_data['timezone_offset'] = wc_get_order_item_meta( $item_id, '_wapbk_timeoffset' );
				}
			}

			if ( function_exists( 'wc_pb_is_bundle_container_order_item' ) &&
				wc_pb_is_bundle_container_order_item( $item_obj ) ) {

				$order_obj       = wc_get_order( $order_id );
				$bundled_item_id = wc_pb_get_bundled_order_items( $item_obj, $order_obj, true );

				foreach ( $bundled_item_id as $bundle_key ) {

					self::bkap_update_item_bookings(
						$order_id,
						$bundle_key,
						$old_start,
						$old_end,
						$old_time,
						$product_id,
						$booking_data,
						$booking_id,
						$quantity,
						$old_resource,
						$page,
						$booking,
						$additional_data
					);
				}
			}

			self::bkap_update_item_bookings(
				$order_id,
				$item_id,
				$old_start,
				$old_end,
				$old_time,
				$product_id,
				$booking_data,
				$booking_id,
				$quantity,
				$old_resource,
				$page,
				$booking,
				$additional_data
			);
		}

		/**
		 * Used for updating the booking details for a particular Item ID.
		 *
		 * @param string $order_id Order ID.
		 * @param string $item_id Item ID.
		 * @param string $old_start Old Start Date.
		 * @param string $old_end Old End Date.
		 * @param string $old_time Old Time.
		 * @param string $product_id Product ID.
		 * @param array  $booking_data Booking Data.
		 * @param string $booking_id Booking ID.
		 * @param int    $quantity Quantity.
		 * @since 4.2
		 */
		public static function bkap_update_item_bookings( $order_id, $item_id, $old_start, $old_end, $old_time, $product_id, $booking_data, $booking_id, $quantity, $old_resource, $page, $booking, $additional_data ) {

			$item_number = (int) $additional_data['item_number'];
			Bkap_Zoom_Meeting_Settings::bkap_delete_zoom_meeting( $booking_id, $booking );

			do_action( 'bkap_rental_delete', $booking, $booking_id );

			bkap_delete_event_from_gcal( $product_id, $item_id, $item_number ); // Removing the google event from google calendar.

			// Updating the booking information in the booking tables.
			self::bkap_edit_bookings(
				$order_id,
				$item_id,
				$old_start,
				$old_end,
				$old_time,
				$product_id
			);

			$date_to_convert = date( 'Y-m-d', strtotime( $booking_data['hidden_date'] ) );

			$book_item_meta_date     = $additional_data['start_date_label'];
			$checkout_item_meta_date = $additional_data['end_date_label'];
			$book_item_meta_time     = $additional_data['time_slot_label'];

			if ( $additional_data['multiple_dates'] ) { // rescheduling multiple dates booking.
				bkap_update_order_itemmeta_multidates( $item_id, $book_item_meta_date, $booking_data['booking_date'], $additional_data['old_bookings']['booking_date'], $additional_data['item_number'] );
				bkap_update_order_itemmeta_multidates( $item_id, '_wapbk_booking_date', $date_to_convert, $old_start, $additional_data['item_number'] );
			} else {
				wc_update_order_item_meta( $item_id, $book_item_meta_date, $booking_data['booking_date'], $additional_data['old_bookings']['booking_date'] );
				wc_update_order_item_meta( $item_id, '_wapbk_booking_date', $date_to_convert, $old_start );
			}

			$postmeta_start_date = $date_to_convert . '000000';
			$postmeta_end_date   = $date_to_convert . '000000';

			if ( isset( $booking_data['hidden_date_checkout'] ) && $booking_data['hidden_date_checkout'] !== '' ) {

				$checkout_date_to_convert = date( 'Y-m-d', strtotime( $booking_data['hidden_date_checkout'] ) );

				wc_update_order_item_meta( $item_id, $checkout_item_meta_date, $booking_data['booking_date_checkout'], $additional_data['old_bookings']['booking_date_checkout'] );
				wc_update_order_item_meta( $item_id, '_wapbk_checkout_date', $checkout_date_to_convert, $old_end );

				$postmeta_end_date = $checkout_date_to_convert . '000000';
			}

			if ( isset( $booking_data['time_slot'] ) && $booking_data['time_slot'] !== '' ) { // new selected time.

				$timeslots   = explode( ' - ', $booking_data['time_slot'] );
				$db_timeslot = date( 'H:i', strtotime( $timeslots[0] ) );

				$timezone = false;
				if ( isset( $booking_data['timezone_name'] ) && $booking_data['timezone_name'] != '' ) {
					$timezone          = true;
					$offset            = bkap_get_offset( $booking_data['timezone_offset'] );
					$site_timezone     = bkap_booking_get_timezone_string();
					$customer_timezone = $booking_data['timezone_name'];
				}

				if ( $timezone ) { // Timezone conversion to create booking details for post.
					$his             = bkap_convert_date_from_timezone_to_timezone( $booking_data['hidden_date'] . ' ' . $timeslots[0], $customer_timezone, $site_timezone, 'His' );
					$date_to_convert = bkap_convert_date_from_timezone_to_timezone( $booking_data['hidden_date'] . ' ' . $timeslots[0], $customer_timezone, $site_timezone, 'Ymd' );
				} else {
					$his = date( 'His', strtotime( $timeslots[0] ) );
				}

				$postmeta_start_date = $date_to_convert . $his;

				if ( isset( $timeslots[1] ) && $timeslots[1] !== '' ) {
					$db_timeslot .= ' - ' . date( 'H:i', strtotime( $timeslots[1] ) );

					if ( $timezone ) { // Timezone conversion to create booking details for post.
						$his = bkap_convert_date_from_timezone_to_timezone( $booking_data['hidden_date'] . ' ' . $timeslots[1], $customer_timezone, $site_timezone, 'His' );

					} else {
						$his = date( 'His', strtotime( $timeslots[1] ) );
					}
					$postmeta_end_date = $date_to_convert . $his;
				} else {
					$postmeta_end_date = $date_to_convert . '000000';
				}

				if ( isset( $booking_data['multiple_time_slot'] ) ) {
					wc_update_order_item_meta( $item_id, $book_item_meta_time, $booking_data['multiple_time_slot'], '' );
					wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $booking_data['multiple_time_slot'], '' );
				} else {

					if ( $additional_data['multiple_dates'] ) { // rescheduling multiple dates booking.
						bkap_update_order_itemmeta_multidates( $item_id, $book_item_meta_time, $booking_data['time_slot'], $additional_data['old_bookings']['time_slot'], $additional_data['item_number'] );
						bkap_update_order_itemmeta_multidates( $item_id, '_wapbk_time_slot', $db_timeslot, $old_time, $additional_data['item_number'] );
					} else {
						$booking_time_slot = apply_filters( 'bkap_update_item_bookings_timeslot', $booking_data['time_slot'], $booking_data, $product_id );
						wc_update_order_item_meta( $item_id, $book_item_meta_time, $booking_time_slot, $additional_data['old_bookings']['time_slot'] );
						wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $db_timeslot, $old_time );
					}
				}
			}

			if ( array_key_exists( 'selected_duration', $booking_data ) && $booking_data['selected_duration'] != 0 ) {

				$start_date = $booking_data['hidden_date'];
				$time       = $booking_data['duration_time_slot'];

				$postmeta_start_date = $date_to_convert . date( 'His', strtotime( $time ) );

				$selected_duration = explode( '-', $booking_data['selected_duration'] );

				$hour   = $selected_duration[0];
				$d_type = $selected_duration[1];

				$end_str  = bkap_common::bkap_add_hour_to_date( $start_date, $time, $hour, $product_id, $d_type ); // return end date timestamp.
				$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta.

				$postmeta_end_date = date( 'YmdHis', $end_str );

				// updating end date.
				if ( $start_date != $end_date ) {

					$name_checkout = ( '' == get_option( 'checkout_item-meta-date' ) ) ? __( 'End Date', 'woocommerce-booking' ) : get_option( 'checkout_item-meta-date' );

					$bkap_format  = bkap_common::bkap_get_date_format(); // get date format set at global.
					$end_date_str = date( 'Y-m-d', strtotime( $end_date ) ); // conver date to Y-m-d format.

					$end_date_str    = $date_booking . ' - ' . $end_date_str;
					$end_date_string = date( $bkap_format, strtotime( $end_date ) ); // Get date based on format at global level.

					$end_date_string = $start_date . ' - ' . $end_date_string;

					// Updating end date field in order item meta.
					wc_update_order_item_meta( $item_id, '_wapbk_booking_date', sanitize_text_field( $end_date_str, true ) );
					wc_update_order_item_meta( $item_id, $book_item_meta_date, sanitize_text_field( $end_date_string, true ) );
				}

				$endtime        = date( 'H:i', $end_str );// getend time in H:i format.
				$back_time_slot = $time . ' - ' . $endtime; // to store time sting in the _wapbk_time_slot key of order item meta.

				$startime = bkap_common::bkap_get_formated_time( $time ); // return start time based on the time format at global.
				$endtime  = bkap_common::bkap_get_formated_time( $endtime ); // return end time based on the time format at global.

				$time_slot = $startime . ' - ' . $endtime; // to store time sting in the timeslot of order item meta.
				// Updating timeslot
				$time_slot_label = ( '' == get_option( 'book_item-meta-time' ) ) ? __( 'Booking Time', 'woocommerce-booking' ) : get_option( 'book_item-meta-time' );

				wc_update_order_item_meta( $item_id, $book_item_meta_time, $time_slot, '' );
				wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $back_time_slot, '' );

				update_post_meta( $booking_id, '_bkap_duration', $booking_data['selected_duration'] );
			}

			if ( isset( $booking_data['resource_id'] ) && $booking_data['resource_id'] != 0 ) {

				$r_label = get_post_meta( $product_id, '_bkap_product_resource_lable', true );

				wc_update_order_item_meta( $item_id, $r_label, get_the_title( $booking_data['resource_id'] ), '' );
				wc_update_order_item_meta( $item_id, '_resource_id', $booking_data['resource_id'], '' );
				update_post_meta( $booking_id, '_bkap_resource_id', $booking_data['resource_id'] );
			}

			/**
			 * Updating Persons Information
			 */
			if ( isset( $booking_data['persons'] ) && $booking_data['persons'] ) {
				if ( isset( $booking_data['persons'][0] ) ) {
					wc_update_order_item_meta( $item_id, Class_Bkap_Product_Person::bkap_get_person_label( $product_id ), $booking_data['persons'][0] );
				} else {
					foreach ( $booking_data['persons'] as $key => $value ) {
						wc_update_order_item_meta( $item_id, get_the_title( $key ), $value );
					}
				}
				wc_update_order_item_meta( $item_id, '_person_ids', $booking_data['persons'] );
				update_post_meta( $booking_id, '_bkap_persons', $booking_data['persons'] );
			}

			$details = bkap_checkout::bkap_update_lockout( $order_id, $product_id, '', $quantity, $booking_data, $page );
			// update the global time slot lockout.
			if ( isset( $booking_data['time_slot'] ) && $booking_data['time_slot'] != '' ) {
				bkap_checkout::bkap_update_global_lockout( $product_id, $quantity, $details, $booking_data );
			}

			$postmeta_start_date = str_replace( '-', '', $postmeta_start_date );
			$postmeta_end_date   = str_replace( '-', '', $postmeta_end_date );
			update_post_meta( $booking_id, '_bkap_start', $postmeta_start_date );
			update_post_meta( $booking_id, '_bkap_end', $postmeta_end_date );

			$order_obj = wc_get_order( $order_id );

			$order_obj->calculate_totals();

			// Creating Zoom Meeting.
			$new_booking_data = bkap_get_meta_data( $booking_id );

			foreach ( $new_booking_data as $data ) {
				$updated_meeting_data = Bkap_Zoom_Meeting_Settings::bkap_create_zoom_meeting( $booking_id, $data, 'update' );

				if ( count( $updated_meeting_data ) > 0 ) {
					/* translators: %s: Booking ID and Meeting Link. */
					$order_note = sprintf( __( 'Updated Zoom Meeting Link for Booking #%1$s - %2$s', 'woocommerce-booking' ), $booking_id, $updated_meeting_data['meeting_link'] );
					$order_obj->add_order_note( $order_note, 1, false );
				}
			}

			bkap_insert_event_to_gcal( $order_obj, $product_id, $item_id, $item_number );

			do_action( 'bkap_after_rescheduling_booking', $booking_id, $booking, $new_booking_data );
		}

		/**
		 * Update the bookings after Confirm Booking is clicked
		 *
		 * @param string|int $order_id Order ID
		 * @param string|int $item_id Item ID
		 * @param string     $old_start Previous Start Date
		 * @param string     $old_end Previous End Date
		 * @param string     $old_time Previous Time Details
		 * @param string|int $product_id Product ID
		 *
		 * @global mixed $wpdb global variable
		 *
		 * @since 4.1.0
		 */
		public static function bkap_edit_bookings( $order_id, $item_id, $old_start, $old_end, $old_time, $product_id ) {

			global $wpdb;

			$order_obj   = new WC_Order( absint( $order_id ) );
			$order_items = $order_obj->get_items();

			foreach ( $order_items as $oid => $o_value ) {
				if ( $oid == $item_id ) {
					$item_value = $o_value;
					break;
				}
			}

			$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );

			if ( isset( $item_value ) ) {

				$get_booking_id  = 'SELECT booking_id FROM `' . $wpdb->prefix . 'booking_order_history` WHERE order_id = %d';
				$results_booking = $wpdb->get_results( $wpdb->prepare( $get_booking_id, $order_id ) );

				foreach ( $results_booking as $id ) {

					$get_booking_details = 'SELECT post_id, start_date, end_date, from_time, to_time 
												FROM `' . $wpdb->prefix . 'booking_history`
												WHERE id = %d';
					$bkap_details        = $wpdb->get_results( $wpdb->prepare( $get_booking_details, $id->booking_id ) );

					$matched = false;
					if ( $bkap_details[0]->post_id == $product_id ) {

						switch ( $booking_type ) {

							case 'only_day':
							case 'multidates':
								if ( $old_start === $bkap_details[0]->start_date ) {
									$booking_id = $id->booking_id;
									$matched    = true;
								}
								break;
							case 'multiple_days':
								if ( $old_start === $bkap_details[0]->start_date && $old_end === $bkap_details[0]->end_date ) {
									$booking_id = $id->booking_id;
									$matched    = true;
								}
								break;
							case 'date_time':
							case 'multidates_fixedtime':
								if ( '' != $bkap_details[0]->to_time ) {
									$time_slot = $bkap_details[0]->from_time . ' - ' . $bkap_details[0]->to_time;
								} else {
									$time_slot = $bkap_details[0]->from_time;
								}

								if ( $old_start === $bkap_details[0]->start_date && $old_time === $time_slot ) {
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

				if ( isset( $booking_id ) ) {
					bkap_cancel_order::bkap_reallot_item( $item_value, $booking_id, $order_id );
					$delete_order_history = 'DELETE FROM `' . $wpdb->prefix . 'booking_order_history`
										 		WHERE order_id = %d and booking_id = %d';
					$wpdb->query( $wpdb->prepare( $delete_order_history, $order_id, $booking_id ) );
				}
			}
		}

		/**
		 * Add Order Notes when bookings are rescheduled
		 *
		 * @param string|int $order_id Order ID.
		 * @param array      $old_bookings Old Booking data array.
		 * @param array      $new_bookings New Booking data array.
		 * @param array      $item_name Name of Item.
		 * @param array      $additional_note Additional Notes.
		 * @since 4.2.0
		 */
		public function bkap_add_reschedule_order_note( $product_id, $order_id, $old_bookings, $new_bookings, $item_name, $additional_note ) {

			$order_obj = wc_get_order( $order_id );

			if ( isset( $old_bookings['booking_date'] ) && $old_bookings['booking_date'] !== '' &&
				isset( $new_bookings['booking_date'] ) && $new_bookings['booking_date'] !== '' &&
				isset( $old_bookings['booking_date_checkout'] ) && $old_bookings['booking_date_checkout'] !== '' &&
				isset( $new_bookings['booking_date_checkout'] ) && $new_bookings['booking_date_checkout'] !== '' ) {

				$note_details_old = $old_bookings['booking_date'] . ' - ' . $old_bookings['booking_date_checkout'];
				$note_details_new = $new_bookings['booking_date'] . ' - ' . $new_bookings['booking_date_checkout'];
			} elseif ( isset( $old_bookings['booking_date'] ) && $old_bookings['booking_date'] !== '' &&
				isset( $new_bookings['booking_date'] ) && $new_bookings['booking_date'] !== '' &&
				isset( $old_bookings['time_slot'] ) && $old_bookings['time_slot'] !== '' &&
				isset( $new_bookings['time_slot'] ) && $new_bookings['time_slot'] !== '' ) {

				$note_details_old          = $old_bookings['booking_date'] . ' ' . $old_bookings['time_slot'];
				$new_bookings['time_slot'] = apply_filters( 'bkap_update_item_bookings_timeslot', $new_bookings['time_slot'], $new_bookings, $product_id );
				$note_details_new          = $new_bookings['booking_date'] . ' ' . $new_bookings['time_slot'];
			} elseif ( isset( $old_bookings['booking_date'] ) && $old_bookings['booking_date'] !== '' &&
				isset( $new_bookings['booking_date'] ) && $new_bookings['booking_date'] !== '' &&
				isset( $old_bookings['duration_time_slot'] ) && $old_bookings['duration_time_slot'] !== '' &&
				isset( $new_bookings['duration_time_slot'] ) && $new_bookings['duration_time_slot'] !== '' ) {

				$durations          = explode( '-', $new_bookings['selected_duration'] );
				$startime           = $new_bookings['duration_time_slot'];
				$startime           = bkap_common::bkap_get_formated_time( $startime );
				$duration_time_slot = strtotime( $startime );

				if ( $durations[1] == 'hours' ) {
					$time_division = 3600;
				} else {
					$time_division = 60;
				}

				$duration     = $durations[0] * $time_division;
				$duration     = $duration + $duration_time_slot;
				$endtime      = date( 'H:i', $duration );
				$endtime      = bkap_common::bkap_get_formated_time( $endtime );
				$new_duration = $startime . ' - ' . $endtime;

				$note_details_old = $old_bookings['booking_date'] . ' ' . $old_bookings['duration_time_slot'];
				$note_details_new = $new_bookings['booking_date'] . ' ' . $new_duration;
			} else {

				$note_details_old = $old_bookings['booking_date'];
				$note_details_new = $new_bookings['booking_date'];
			}

			$order_note = sprintf( __( 'Booking has been rescheduled from <strong>%1$s</strong> to <strong>%2$s</strong> for <strong>%3$s</strong>.', 'woocommerce-booking' ), $note_details_old, $note_details_new, $item_name );

			if ( empty( $old_bookings['booking_date'] ) ) {
				$order_note = sprintf( __( 'Booking has been added on <strong>%1$s</strong> for <strong>%2$s</strong>.', 'woocommerce-booking' ), $note_details_new, $item_name );
			}

			$order_note = $order_note . $additional_note;
			$order_obj->add_order_note( $order_note, 1, false );
		}

		/**
		 * Update bundled items added to cart.
		 *
		 * @param array $session_cart Cart Session array.
		 * @param array $bundled_items Bundled items array.
		 * @param array $booking_details Booking Details.
		 * @return array Session cart array with updated booking details.
		 * @since 4.2
		 */
		public static function bkap_update_bundled_cartitems( $session_cart, $bundled_items, $booking_details ) {

			foreach ( $bundled_items as $bundlekey ) {
				$session_cart[ $bundlekey ]['bkap_booking'] = $booking_details;
			}

			return $session_cart;
		}

		/**
		 * Register additional settings for Edit Bookings
		 *
		 * @since 4.1.0
		 *
		 * @hook init
		 */
		public function bkap_add_edit_settings() {

			add_settings_field(
				'bkap_enable_booking_edit',
				__( 'Allow Bookings to be editable:', 'woocommerce-booking' ),
				array( &$this, 'bkap_allow_bookings_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Enabling this option will allow Bookings to be editable from Cart and Checkout page', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_enable_booking_reschedule',
				__( 'Allow Bookings to be reschedulable:', 'woocommerce-booking' ),
				array( &$this, 'bkap_allow_reschedulable_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Enabling this option will allow Bookings to be reschedulable from My Account page', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_enable_booking_without_date',
				__( 'Allow adding bookings date:', 'woocommerce-booking' ),
				array( &$this, 'bkap_allow_booking_without_date_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Enabling this option will allow you to add booking date from my account page, when purchased without choosing a date.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_booking_reschedule_hours',
				__( 'Minimum number of hours for rescheduling:', 'woocommerce-booking' ),
				array( &$this, 'bkap_reschedulable_hours_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Minimum number of hours before the booking date, after which Booking cannot be rescheduled. <em>(24 hours = 1 day)</em>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_booking_minimum_hours_cancel',
				__( 'Minimum number of hours for cancelling booking:', 'woocommerce-booking' ),
				array( &$this, 'bkap_booking_minimum_hours_cancel_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Minimum number of hours before the booking date, after which Booking cannot be cancelled.', 'woocommerce-booking' ) )
			);

			do_action( 'bkap_add_new_settings' );
		}

		/**
		 * Call back for displaying settings option for Cart/Checkout page
		 *
		 * @param mixed $args arguments.
		 */
		public function bkap_allow_bookings_callback( $args ) {

			$saved_settings             = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$bkap_enable_booking_option = '';
			if ( isset( $saved_settings->bkap_enable_booking_edit ) &&
				$saved_settings->bkap_enable_booking_edit === 'on' ) {

				$bkap_enable_booking_option = 'checked';
			}

			?>
				<input 
					type="checkbox" 
					id="bkap_enable_booking_edit" 
					name="woocommerce_booking_global_settings[bkap_enable_booking_edit]"
					<?php echo $bkap_enable_booking_option; ?>
				/>
				<label for="bkap_enable_booking_edit">
					<?php echo $args[0]; ?>
				</label>
			<?php
		}

		/**
		 * Call back for displaying settings option for My Account page
		 *
		 * @param mixed $args arguments.
		 */
		public function bkap_allow_reschedulable_callback( $args ) {

			$saved_settings                 = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$bkap_enable_booking_reschedule = '';
			if ( isset( $saved_settings->bkap_enable_booking_reschedule ) &&
				$saved_settings->bkap_enable_booking_reschedule === 'on' ) {

				$bkap_enable_booking_reschedule = 'checked';
			}

			?>
				<input 
					type="checkbox" 
					id="bkap_enable_booking_reschedule" 
					name="woocommerce_booking_global_settings[bkap_enable_booking_reschedule]"
					<?php echo $bkap_enable_booking_reschedule; ?>
				/>
				<label for="bkap_enable_booking_reschedule">
					<?php echo $args[0]; ?>
				</label>
			<?php
		}

		/**
		 * Call back for displaying booking without date settings option.
		 *
		 * @param mixed $args arguments.
		 */
		public function bkap_allow_booking_without_date_callback( $args ) {

			$saved_settings                   = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$bkap_enable_booking_without_date = '';
			if ( isset( $saved_settings->bkap_enable_booking_without_date ) &&
				( 'on' === $saved_settings->bkap_enable_booking_without_date )
			) {

				$bkap_enable_booking_without_date = 'checked';
			}

			?>
				<input 
					type="checkbox" 
					id="bkap_enable_booking_without_date" 
					name="woocommerce_booking_global_settings[bkap_enable_booking_without_date]"
					<?php echo $bkap_enable_booking_without_date; ?>
				/>
				<label for="bkap_enable_booking_without_date">
					<?php echo $args[0]; ?>
				</label>
			<?php
		}

		/**
		 * Call back for displaying settings option for rescheduling period for hours option
		 *
		 * @param mixed $args arguments.
		 */
		public function bkap_reschedulable_hours_callback( $args ) {

			$bkap_booking_reschedule_hours = $this->bkap_update_booking_reschedule_day_to_hour();

			?>
				<input 
					type="number" 
					id="bkap_booking_reschedule_hours" 
					min=0
					name="woocommerce_booking_global_settings[bkap_booking_reschedule_hours]"
					value="<?php echo esc_attr( $bkap_booking_reschedule_hours ); ?>"
				/>
				<label for="bkap_booking_reschedule_hours">
					<?php echo wp_kses( $args[0], array( 'em' => array() ) ); ?>
				</label>
			<?php
		}

		/**
		 * Function to update 'day' values to 'hour' values
		 *
		 * @return int reschedule_hours.
		 */
		public function bkap_update_booking_reschedule_day_to_hour() {

			$settings         = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$reschedule_hours = ( isset( $settings->bkap_booking_reschedule_hours ) &&
			'' !== $settings->bkap_booking_reschedule_hours ) ? $settings->bkap_booking_reschedule_hours : 0;

			// Check if previous record exists for bkap_booking_reschedule_days. If it exists, convert to hours and update record.
			if ( isset( $settings->bkap_booking_reschedule_days ) ) {

				// Sometimes, bkap_booking_reschedule_days may still exist even when bkap_booking_reschedule_hours has been set. In that case, ignore bkap_booking_reschedule_days and use bkap_booking_reschedule_hours instead.

				if ( ! isset( $settings->bkap_booking_reschedule_hours ) && ( ( (int) $settings->bkap_booking_reschedule_days ) > 0 ) ) {
					$reschedule_hours                        = ( (int) $settings->bkap_booking_reschedule_days ) * 24;
					$settings->bkap_booking_reschedule_hours = $reschedule_hours;
				}

				// Delete bkap_booking_reschedule_days and update record.
				unset( $settings->bkap_booking_reschedule_days );
				update_option( 'woocommerce_booking_global_settings', wp_json_encode( $settings ) );
			}
			return $reschedule_hours;
		}

		/**
		 * Call back for displaying settings option for cancelling period
		 *
		 * @param mixed $args arguments
		 */
		public function bkap_booking_minimum_hours_cancel_callback( $args ) {

			$saved_settings                    = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$bkap_booking_minimum_hours_cancel = 0;
			if ( isset( $saved_settings->bkap_booking_minimum_hours_cancel ) &&
				$saved_settings->bkap_booking_minimum_hours_cancel !== '' ) {
				$bkap_booking_minimum_hours_cancel = $saved_settings->bkap_booking_minimum_hours_cancel;
			}

			?>
				<input 
					type="number" 
					id="bkap_booking_minimum_hours_cancel" 
					min=0
					name="woocommerce_booking_global_settings[bkap_booking_minimum_hours_cancel]"
					value="<?php echo $bkap_booking_minimum_hours_cancel; ?>"
				/>
				<label for="bkap_booking_minimum_hours_cancel">
					<?php echo $args[0]; ?>
				</label>
			<?php
		}
	}
	$edit_booking_class = new bkap_edit_bookings_class();
}
