<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for operations related to Cart
 *
 * @author   Tyche Softwares
 * @package  BKAP/Cart
 * @category Classes
 */

require_once 'bkap-common.php';

if ( ! class_exists( 'bkap_cart' ) ) {

	/**
	 * Class for all the operations when Cart is created or modified
	 * i.e. Add to cart or Cart update
	 *
	 * @since 1.7.0
	 * @todo Remove unnecessary global variable $wpdb
	 * @todo add description where $wpdb has been used and remove if needed
	 */
	class bkap_cart {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */

		public function __construct() {

			add_filter( 'woocommerce_add_cart_item_data', array( $this, 'bkap_add_cart_item_data' ), 25, 2 );
			add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'bkap_get_cart_item_from_session' ), 25, 2 );
			add_filter( 'woocommerce_get_item_data', array( $this, 'bkap_get_item_data_booking' ), 25, 2 );
			add_filter( 'woocommerce_add_cart_item', array( $this, 'bkap_add_cart_item' ), 25, 1 );
			// add_filter( 'woocommerce_add_to_cart_fragments',        array( $this, 'bkap_woo_cart_widget_subtotal' ) );
		}

		/**
		 * This function adjust the extra prices for the product
		 * with the price calculated from booking plugin.
		 *
		 * @param mixed $cart_item Cart Item Array
		 *
		 * @globals mixed $wpdb
		 *
		 * @return mixed Cart Item Array with modified data
		 *
		 * @hook woocommerce_add_cart_item
		 *
		 * @since 1.7.0
		 */
		public static function bkap_add_cart_item( $cart_item ) {

			global $wpdb;

			if ( isset( $cart_item['bkap_booking'] ) ) {

				$extra_cost = 0;

				foreach ( $cart_item['bkap_booking'] as $addon ) {

					if ( isset( $addon['price'] ) && is_numeric( $addon['price'] ) ) {
						$extra_cost += $addon['price'];
					}
				}

				$duplicate_of = bkap_common::bkap_get_product_id( $cart_item['product_id'] );
				$product      = wc_get_product( $cart_item['product_id'] );
				$product_type = $product->get_type();
				$variation_id = 0;

				if ( 'variable' === $product_type ) {
					$variation_id = $cart_item['variation_id'];
				}

				if ( ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ) {
					$price      = bkap_common::bkap_get_price( $cart_item['product_id'], $variation_id, $product_type );
					$extra_cost = $extra_cost - $price;
					$cart_item['data']->adjust_price( $extra_cost );

					// Advanced Dynamic Pricing for WooCommerce Pro plugin not supported for lower WC versions.
				} else {

					if ( isset( $cart_item['bundled_by'] ) ) {

						if ( isset( $cart_item['variation_id'] ) && absint( $cart_item['variation_id'] ) > 0 ) {
							$_bundle_child = wc_get_product( $cart_item['variation_id'] );
						} else {
							$_bundle_child = wc_get_product( $cart_item['product_id'] );
						}

						$booking_type       = get_post_meta( $cart_item['product_id'], '_bkap_booking_type', true );
						$bundle_child_price = ( $_bundle_child ) ? $_bundle_child->get_price() : 0;
						$start              = ! empty( $cart_item['bkap_booking'][0]['hidden_date'] ) ? strtotime( $cart_item['bkap_booking'][0]['hidden_date'] ) : 0;
						$end                = ! empty( $cart_item['bkap_booking'][0]['hidden_date_checkout'] ) ? strtotime( $cart_item['bkap_booking'][0]['hidden_date_checkout'] ) : 0;

						if ( $end > 0 ) {
							$datediff = $end - $start;
							$diff     = $datediff / ( 60 * 60 * 24 );
							$diff     = ( $diff == 0 ) ? 1 : $diff;
						} else {
							$diff = 1;
						}

						if ( isset( $cart_item['bkap_booking'][0]['price'] ) && in_array( $booking_type, array( 'multiple_days' ) ) ) {
							if ( $extra_cost === $cart_item['bkap_booking'][0]['price'] ) { // If no additional cost added.
								if ( $diff > 1 ) {
									$net_cost = $bundle_child_price * $diff;
								} else {
									$net_cost = $extra_cost;
								}
								$cart_item['data']->set_price( $net_cost );
								} else { // phpcs:ignore.
								// @ TODO Handling the case when extra cost does not match item price.
							}
						}
						$cart_item['diff_days'] = $diff;
					} // Check if discount has been done by Advanced Dynamic Pricing for WooCommerce Pro plugin.
					elseif ( isset( $cart_item['adp'] ) ) {

						// Do not set price when it is set from advanced dynamic pricing is there.

					} else {
						$cart_item['data']->set_price( $extra_cost );
					}
				}

				$cart_item = apply_filters( 'bkap_modify_product_price', $cart_item );
			}

			return $cart_item;
		}

		/**
		 * This function adds the booking details of the product when add to
		 * cart button is clicked.
		 *
		 * @param mixed      $cart_item_meta Cart Item Meta Object
		 * @param string|int $product_id Product ID of the product added to cart
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 1.7.0
		 * @since 4.5.0 Compatibility with Bundles Product
		 * @since 4.7.0 Compatibility with Composite Products
		 *
		 * @return mixed Cart Item Meta Object with Booking Array Added
		 *
		 * @hook woocommerce_add_cart_item_data
		 */
		public static function bkap_add_cart_item_data( $cart_item_meta, $product_id ) {
			global $wpdb;

			$duplicate_of   = bkap_common::bkap_get_product_id( $product_id );
			$is_bookable    = bkap_common::bkap_get_bookable_status( $duplicate_of );
			$allow_bookings = apply_filters( 'bkap_cart_allow_add_bookings', true, $cart_item_meta );

			if ( $is_bookable && ( ! array_key_exists( 'bundled_by', $cart_item_meta ) ) && $allow_bookings ) {
				if ( isset( $_POST['wapbk_dropdown_hidden_date'] ) && '' !== $_POST['wapbk_dropdown_hidden_date'] ) {
					$booking_calendar = $_POST['wapbk_dropdown_hidden_date'];
					$booking_date     = $_POST['wapbk_dropdown_hidden_date'];
				}else {
					$booking_calendar = ( isset( $_POST['booking_calender'] ) && '' !== $_POST['booking_calender'] );
					$booking_date     = $_POST['booking_calender'];
				}

				$bkap_multidates  = ( isset( $_POST['bkap_multidate_data'] ) && '' != $_POST['bkap_multidate_data'] );

				if ( $booking_calendar || $bkap_multidates ) { // If booking start date is set then only prepare the cart array.

					$booking_settings     = bkap_setting( $duplicate_of );
					$booking_type         = bkap_type( $duplicate_of );
					$product              = wc_get_product( $product_id );
					$product_type         = $product->get_type();
					$global_settings      = bkap_global_setting();
					$diff_days            = 1;
					$cart_arr             = array();
					$date_disp_checkout   = '';
					$hidden_date_checkout = '';
					$block_info           = ''; // Initialize the variables for multiple nights booking.

					if ( $bkap_multidates ) {
						$posted_multidate_data = $_POST['bkap_multidate_data'];
						$temp_data             = str_replace( '\\', '', $posted_multidate_data );
						$bkap_multidate_data   = (array) json_decode( $temp_data );
						foreach ( $bkap_multidate_data as $key => $value ) {
							$date_checks[]   = $value->hidden_date;
							$booking_dates[] = $value->date;

							if ( isset( $value->time_slot ) ) {
								$time_slots[] = $value->time_slot;
							}

							$price_charged[] = $value->price_charged;

							if ( isset( $value->resource_id ) ) {
								$resource_id[] = ( 'multiple' === BKAP_Product_Resource::get_resource_selection_type( $product_id ) ) ? explode( ',', $value->resource_id ) : $value->resource_id;
							}
						}
					} else {
						$date_checks[]   = isset( $_POST['wapbk_hidden_date'] ) ? $_POST['wapbk_hidden_date'] : '';
						$booking_dates[] = $booking_date;
						$price_charged[] = ( isset( $_POST['bkap_price_charged'] ) && '' != $_POST['bkap_price_charged'] ) ? $_POST['bkap_price_charged'] : '';
						if ( isset( $_POST['time_slot'] ) ) {
							$time_slots[] = $_POST['time_slot'];
						}
					}

					foreach ( $date_checks as $key => $date_check ) {
						$cart_arr                = array();
						$cart_arr['date']        = $booking_dates[ $key ];
						$cart_arr['hidden_date'] = $date_check;

						switch ( $booking_type ) {
							case 'multiple_days':
								if ( isset( $_POST['block_option'] ) && '' !== $_POST['block_option'] ) {
									$cart_arr['fixed_block'] = $_POST['block_option'];
								}

								if ( isset( $_POST['booking_calender_checkout'] ) ) {
									$cart_arr['date_checkout'] = $_POST['booking_calender_checkout'];
								}

								if ( isset( $_POST['wapbk_hidden_date_checkout'] ) ) {
									$cart_arr['hidden_date_checkout'] = $_POST['wapbk_hidden_date_checkout'];
								}

								if ( isset( $_POST['wapbk_diff_days'] ) ) {
									$diff_days = $_POST['wapbk_diff_days'];
								}
								$cart_arr['diff_days'] = $diff_days;
								break;
							case 'date_time':
							case 'multidates_fixedtime':
								if ( isset( $time_slots[ $key ] ) ) {
									$cart_arr['time_slot'] = $time_slots[ $key ];
									if ( function_exists( 'bkap_get_timeslot_data' ) ) {
										$cart_arr['time_slot_note'] = bkap_get_timeslot_data( $product_id, $cart_arr['hidden_date'], $time_slots[ $key ], 'booking_notes' );
									}
								}
								break;
							case 'duration_time':
								// Duration Time Information.
								$selected_duration = $duration_time_disp = '';

								if ( isset( $_POST['bkap_duration_field'] ) ) {
									$selected_duration = $_POST['bkap_duration_field'];
								}

								if ( isset( $_POST['duration_time_slot'] ) ) {
									$duration_time_disp = $_POST['duration_time_slot'];
								}

								$d_setting         = $booking_settings['bkap_duration_settings'];
								$selected_duration = (int) $selected_duration * (int) $d_setting['duration'];
								$duration_type     = $d_setting['duration_type'];

								// Setting duration information to cart array.
								if ( $selected_duration != '' && $duration_time_disp != '' ) {
									$cart_arr['selected_duration']  = $selected_duration . '-' . $duration_type;
									$cart_arr['duration_time_slot'] = $duration_time_disp;
								}
								break;
							default:
								break;
						}

						if ( isset( $_POST['bkap_front_resource_selection'] ) ) {
							$cart_arr['resource_id'] = $_POST['bkap_front_resource_selection'];
						}

						if ( $bkap_multidates && isset( $resource_id ) ) {
							$cart_arr['resource_id'] = $resource_id[ $key ];
						}

						// Adding information for persons.
						if ( isset( $booking_settings['bkap_person'] ) && 'on' === $booking_settings['bkap_person'] ) {
							if ( 'on' === $booking_settings['bkap_person_type'] && count( $booking_settings['bkap_person_data'] ) > 0 ) {
								foreach ( $booking_settings['bkap_person_data'] as $p_key => $p_data ) {
									$person_field = 'bkap_field_persons_' . $p_key;
									if ( isset( $_POST[ $person_field ] ) && absint( $_POST[ $person_field ] ) > 0 ) {
										$p_title = get_the_title( $p_key );
										// $cart_arr[ $p_title ]          = absint( $_POST[ $person_field ] );
										$cart_arr['persons'][ $p_key ] = absint( $_POST[ $person_field ] );
									}
								}
							} else {
								if ( isset( $_POST['bkap_field_persons'] ) ) {
									// $cart_arr[ Class_Bkap_Product_Person::bkap_get_person_label() ] = absint( $_POST[ 'bkap_field_persons' ] );
									$cart_arr['persons'][0] = absint( $_POST['bkap_field_persons'] );
								}
							}
						}

						$variation_id = 0;
						if ( $product_type == 'variable' && isset( $_POST['variation_id'] ) ) {
							$variation_id = $_POST['variation_id'];
						}

						if ( ! isset( $cart_item_meta['bundled_by'] ) ) {

							$price = 0;
							if ( '' !== $price_charged[ $key ] ) {

								if ( is_numeric( $price_charged[ $key ] ) ) {
									$price = $price_charged[ $key ];

									if ( isset( $cart_item_meta['bundle_sell_of'] ) ) {
										$price           = $product->get_price();
										$resource_status = Class_Bkap_Product_Resource::bkap_resource_status( $duplicate_of );
										if ( $resource_status ) { // Adding resource information when product is added via bundle sells.
											$resource_selection = Class_Bkap_Product_Resource::bkap_product_resource_selection( $duplicate_of );
											$resource_ids       = Class_Bkap_Product_Resource::bkap_get_product_resources( $duplicate_of );
											if ( 'bkap_automatic_resource' == $resource_selection ) {
												$cart_arr['resource_id'] = $resource_ids[0];
											} else {
												if ( in_array( $_POST['bkap_front_resource_selection'], $resource_ids ) ) {
													$cart_arr['resource_id'] = $_POST['bkap_front_resource_selection'];
												} else {
													$cart_arr['resource_id'] = $resource_ids[0];
												}
											}
										}
									}
								} else {
									// It's a string when the product is a grouped product.
									$price_array = explode( ',', $price_charged[ $key ] );
									foreach ( $price_array as $array_k => $array_v ) {
										$per_product_array = explode( ':', $array_v );

										if ( $per_product_array[0] == $duplicate_of ) {
											$price            = $per_product_array[1];
											$child_product_id = $per_product_array[0];
											break;
										}
									}
								}
							} else {
								if ( wp_doing_ajax() ) {
									// Calculating the price when adding to cart from Calendar.
									$price = (float) $_POST['bkap_price'];
									$price = $price * (int) $_POST['quantity'];
								} else {
									$price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type );
								}
							}

							if ( isset( $_POST['total_multiple_price_calculated'] ) && '' != $_POST['total_multiple_price_calculated'] ) {
								$cart_arr['multiple_prices'] = $_POST['total_multiple_price_calculated'];
							}

							$gf_options_price  = 0;
							$wpa_options_price = 0;

							// GF Compatibility.
							if ( isset( $_POST['bkap_gf_options_total'] ) && $_POST['bkap_gf_options_total'] != 0 ) {
								$gf_options_price = $_POST['bkap_gf_options_total'];
							}

							// Woo Product Addons compatibility
							$wpa_diff = 1;
							if ( isset( $global_settings->woo_product_addon_price ) && $global_settings->woo_product_addon_price == 'on' ) {
								$wpa_diff = $diff_days;
							}

							// Set the price per quantity as Woocommerce multiplies the price set with the qty.
							$product_quantity = 1;
							if ( isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ) {
								$product_quantity = $_POST['quantity'][ $product_id ];
							} elseif ( isset( $_POST['quantity'] ) && $_POST['quantity'] > 1 ) {
								$product_quantity = $_POST['quantity'];
								$cart_arr['qty']  = $_POST['quantity'];
							}

							$wpa_options_price = bkap_common::woo_product_addons_compatibility_cart( $wpa_diff, $cart_item_meta, $product_quantity );
							$gf_options_price  = apply_filters( 'bkap_modify_cart_gf_prices', $gf_options_price, $product_quantity );
							$final_price       = ( $price + $gf_options_price + $wpa_options_price ) / $product_quantity;
							$cart_arr['price'] = $final_price;
						} elseif ( isset( $cart_item_meta['bundled_by'] ) && isset( $cart_item_meta['bundled_item_id'] ) ) {

							$bundled_item_obj = wc_pb_get_bundled_item( $cart_item_meta['bundled_item_id'] );
							if ( $bundled_item_obj->is_priced_individually() ) {
								$cart_arr['price'] = $bundled_item_obj->get_price() * $diff_days;
							}
						}
						$cart_arr = (array) apply_filters( 'bkap_addon_add_cart_item_data', $cart_arr, $product_id, $variation_id, $cart_item_meta, $booking_settings, $global_settings );

						// Added to add the selected currency on the product page from WPML Multi currency dropdown.
						if ( function_exists( 'icl_object_id' ) ) {
							global $woocommerce_wpml, $woocommerce;
							if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
								$client_currency           = $woocommerce->session->get( 'client_currency' );
								$cart_arr['wcml_currency'] = $client_currency;
							}
						}

						$cart_item_meta['bkap_booking'][] = $cart_arr;
					}
				}
			} elseif ( $is_bookable && array_key_exists( 'bundled_by', $cart_item_meta ) && '' !== $cart_item_meta['bundled_by'] ) {

				$cart_arr = array();

				if ( isset( WC()->cart->cart_contents[ $cart_item_meta['bundled_by'] ]['bkap_booking'] ) ) {
					$bundle_parent_booking = WC()->cart->cart_contents[ $cart_item_meta['bundled_by'] ]['bkap_booking'][0];
				}

				$bundle_stamp     = $cart_item_meta['stamp'][ $cart_item_meta['bundled_item_id'] ];
				$product_id       = $bundle_stamp['product_id'];
				$booking_settings = bkap_setting( $duplicate_of );
				$global_settings  = bkap_global_setting();

				$bundle_item  = wc_pb_get_bundled_item( $cart_item_meta['bundled_item_id'] );
				$variation_id = 0;

				if ( $bundle_item->is_priced_individually() ) {

					$wapbk_diff_days = 1;
					if ( ! empty( $_POST['wapbk_diff_days'] ) ) {
						$wapbk_diff_days = (int) $_POST['wapbk_diff_days'];
					}
					$cart_arr['diff_days'] = $wapbk_diff_days;

					if ( isset( $bundle_stamp['variation_id'] ) && $bundle_stamp['variation_id'] !== '' ) {
						$variation_id     = $bundle_stamp['variation_id'];
						$bundle_variation = wc_get_product( $bundle_stamp['variation_id'] );

						$cart_arr['price'] = $bundle_variation->get_price();

						if ( isset( $bundle_stamp['discount'] ) && $bundle_stamp['discount'] !== '' ) {
							$cart_arr['price'] = $cart_arr['price'] - ( $cart_arr['price'] * $bundle_stamp['discount'] / 100 );
						}

						$cart_arr['price'] = $cart_arr['price'] * $wapbk_diff_days;

					} else {

						// If discount is set on bundle item then don't use that already discounted price.
						if ( ! empty( $bundle_stamp['discount'] ) ) {

							// Get the actual product price.
							$_bundle_child     = wc_get_product( $bundle_stamp['product_id'] );
							$cart_arr['price'] = $_bundle_child->get_price() * $wapbk_diff_days;
						} else {
							$cart_arr['price'] = $bundle_item->get_price() * $wapbk_diff_days;
						}
					}
				}

				if ( $is_bookable && isset( $bundle_parent_booking ) ) {
					$cart_arr['date']        = $bundle_parent_booking['date'];
					$cart_arr['hidden_date'] = $bundle_parent_booking['hidden_date'];

					if ( isset( $bundle_parent_booking['date_checkout'] ) ) {
						$cart_arr['date_checkout']        = $bundle_parent_booking['date_checkout'];
						$cart_arr['hidden_date_checkout'] = $bundle_parent_booking['hidden_date_checkout'];
					}

					if ( isset( $bundle_parent_booking['selected_duration'] ) ) {
						$cart_arr['selected_duration'] = $bundle_parent_booking['selected_duration'];
					}
					if ( isset( $bundle_parent_booking['duration_time_slot'] ) ) {
						$cart_arr['duration_time_slot'] = $bundle_parent_booking['duration_time_slot'];
					}

					if ( isset( $bundle_parent_booking['time_slot'] ) ) {
						$cart_arr['time_slot'] = $bundle_parent_booking['time_slot'];
						if ( function_exists( 'bkap_get_timeslot_data' ) ) {
							$cart_arr['time_slot_note'] = bkap_get_timeslot_data( $product_id, $cart_arr['hidden_date'], $cart_arr['time_slot'], 'booking_notes' );
						}
					}
				}

				$cart_arr = (array) apply_filters( 'bkap_addon_add_cart_item_data', $cart_arr, $product_id, $variation_id, $cart_item_meta, $booking_settings, $global_settings );

				if ( isset( $cart_arr['date'] ) || isset( $cart_arr['price'] ) ) {
					$cart_item_meta['bkap_booking'][] = $cart_arr;
				}
			} else {
				$cart_item_meta = apply_filters( 'bkap_cart_modify_meta', $cart_item_meta );
			}

			return $cart_item_meta;
		}

		/**
		 * This function adjust the prices calculated
		 * from the plugin in the cart session.
		 *
		 * @param mixed $cart_item Cart Item Object
		 * @param mixed $values Cart Session Object
		 *
		 * @globals mixed $wpdb
		 *
		 * @since 1.7.0
		 *
		 * @return mixed Cart Item Object
		 *
		 * @hook woocommerce_get_cart_item_from_session
		 */

		public static function bkap_get_cart_item_from_session( $cart_item, $values ) {

			if ( isset( $values['bkap_booking'] ) ) :

				// Added to calculate the price for each product in cart based on the selected currency on the product page from WPML Multi currency dropdown
				if ( function_exists( 'icl_object_id' ) ) {

					global $woocommerce_wpml, $woocommerce;

					if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {

						$client_currency = $woocommerce->session->get( 'client_currency' );

						foreach ( $values['bkap_booking'] as $bkap_key => $bkap_value ) {

							if ( $bkap_value['wcml_currency'] != $client_currency ) {

								if ( $bkap_value['wcml_currency'] == get_option( 'woocommerce_currency' ) ) {
									$final_price = $bkap_value['price'];
								} else {
									if ( WCML_VERSION >= '3.8' ) {
										$currencies = $woocommerce_wpml->multi_currency->get_client_currency();
									} else {
										$currencies = $woocommerce_wpml->multi_currency_support->get_client_currency();
									}

									$rate        = $currencies[ $bkap_value['wcml_currency'] ]['rate'];
									$final_price = $bkap_value['price'] / $rate;
								}

								$raw_price                           = apply_filters( 'wcml_raw_price_amount', $final_price );
								$bkap_value['price']                 = $raw_price;
								$bkap_value['wcml_currency']         = $client_currency;
								$values['bkap_booking'][ $bkap_key ] = $bkap_value;
							}
						}
					}
				}

				if ( ( isset( $cart_item['bundled_by'] ) && '' != $cart_item['bundled_by'] )
				|| ( isset( $cart_item['composite_parent'] ) && '' != $cart_item['composite_parent'] )
				) {

					if ( isset( $cart_item['bkap_booking'][0] ) ) {
						if ( isset( $cart_item['bkap_booking'][0]['hidden_date_checkout'] )
						&& '' != $cart_item['bkap_booking'][0]['hidden_date_checkout'] ) {
							$booking_settings = get_post_meta( $cart_item['product_id'], 'woocommerce_booking_settings', true );

							$start    = strtotime( $cart_item['bkap_booking'][0]['hidden_date'] );
							$end      = strtotime( $cart_item['bkap_booking'][0]['hidden_date_checkout'] );
							$datediff = $end - $start;
							$diff     = $datediff / ( 60 * 60 * 24 );

							if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() && ( isset( $booking_settings ) && isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' ) ) {
								if ( $end > $start ) {

									$diff++;
								}
							}
							$diff = ( $diff == 0 ) ? 1 : $diff;
							$cost = $cart_item['data']->get_price();

							if ( isset( $cart_item['composite_parent'] ) && '' != $cart_item['composite_parent'] ) {
								$booking_type = bkap_type( $cart_item['product_id'] );
								$cost         = $cart_item['data']->get_regular_price();
								if ( 'multiple_days' === $booking_type ) {
									$param = array(
										date( 'Y-m-d', $start ),
										date( 'Y-m-d', $end ),
										$booking_type,
									);
								} else {
									$param = array(
										date( 'Y-m-d', $start ),
										date( 'w', $start ),
										$booking_type,
									);
								}

								$cost = bkap_get_special_price( $cart_item['product_id'], $param, $cost );
								if ( isset( $cart_item['composite_item'] ) ) {
									$composite_item     = $cart_item['composite_item'];
									$composite_discount = $cart_item['composite_data'][ $composite_item ]['discount'];
									if ( '' !== $composite_discount ) {
										$cost = $cost - ( ( $cost * $composite_discount ) / 100 );
									}
								}
							}
							$cost                               = $cost * $diff;
							$values['bkap_booking'][0]['price'] = $cost;
							$values['diff_days']                = $diff;
						} else {
							if ( ! isset( $cart_item['bundled_by'] ) ) {

								if ( isset( WC()->cart->cart_contents[ $cart_item['composite_parent'] ]['bkap_booking'] ) ) {
									$composite_parent_booking = WC()->cart->cart_contents[ $cart_item['composite_parent'] ]['bkap_booking'][0];

									if ( isset( $composite_parent_booking['hidden_date_checkout'] ) ) {
										$start    = strtotime( $composite_parent_booking['hidden_date'] );
										$end      = strtotime( $composite_parent_booking['hidden_date_checkout'] );
										$datediff = $end - $start;
										$diff     = $datediff / ( 60 * 60 * 24 );

										$diff = ( $diff == 0 ) ? 1 : $diff;
										$cost = $cart_item['data']->get_price();

										$cost                               = $cost * $diff;
										$values['bkap_booking'][0]['price'] = $cost;
										$values['diff_days']                = $diff;
									} else {
										if ( 'simple' === $cart_item['data']->get_type() ) {
											$product_child                      = wc_get_product( $cart_item['data']->get_id() );
											$cost                               = ( $product_child ) ? $product_child->get_price() : $cart_item['data']->get_price();
											$values['bkap_booking'][0]['price'] = $cost;
										}
									}
								}
							}
						}
					}
				}

				$cart_item['bkap_booking'] = $values['bkap_booking'];

				$cart_item = self::bkap_add_cart_item( $cart_item );

				$cart_item = (array) apply_filters( 'bkap_get_cart_item_from_session', $cart_item, $values );
				endif;
			return $cart_item;
		}

		/**
		 * This function displays the Booking
		 * details on cart page, checkout page in cart table
		 *
		 * @param mixed $other_data Cart Meta Data Object
		 * @param mixed $cart_item Session Cart Item Object
		 *
		 * @return mixed Cart Meta Data Object
		 *
		 * @since 1.7.0
		 *
		 * @hook woocommerce_get_item_data
		 */

		public static function bkap_get_item_data_booking( $other_data, $cart_item ) {

			if ( isset( $cart_item['bkap_booking'] ) ) {

				$hide_other_data = apply_filters( 'before_bkap_get_item_data', false, $other_data, $cart_item );

				if ( $hide_other_data ) {
					return $other_data;
				}

				$duplicate_of        = bkap_common::bkap_get_product_id( $cart_item['product_id'] );
				$booking_settings    = bkap_setting( $duplicate_of );
				$type_of_slot        = apply_filters( 'bkap_slot_type', $duplicate_of );
				$is_multidates       = isset( $cart_item['bkap_booking'] ) && is_array( $cart_item['bkap_booking'] ) && count( $cart_item['bkap_booking'] ) > 1;
				$bkap_multidate_data = array();
				$person_check        = true;

				foreach ( $cart_item['bkap_booking'] as $key => $booking ) {

					// Booking Start Date Label.
					if ( isset( $booking['date'] ) && '' !== $booking['date'] ) {

						$cart_start_lable = bkap_option( 'cart_start_date' );

						$start_date_name = __( ( '' !== $cart_start_lable ? $cart_start_lable : 'Start Date' ), 'woocommerce-booking' );
						$start_date_name = apply_filters( 'bkap_change_cart_start_date_label', $start_date_name, $booking_settings );

						if ( $is_multidates ) {
							$bkap_multidate_data['date'][ $key ] = $start_date_name . ': ' . $booking['date'];
						} else {
							$other_data[] = array(
								'name'    => $start_date_name,
								'display' => $booking['date'],
							);
						}
					}

					// Booking End Date Label.
					if ( isset( $booking['date_checkout'] ) && '' !== $booking['date_checkout'] ) {

						if ( 'on' === $booking_settings['booking_enable_multiple_day'] ) {
							$cart_end_label = bkap_option( 'cart_end_date' );
							$name_checkout  = __( ( '' !== $cart_end_label ? $cart_end_label : 'End Date' ), 'woocommerce-booking' );
							$other_data[]   = array(
								'name'    => $name_checkout,
								'display' => $booking['date_checkout'],
							);
						}

						if ( isset( $booking['diff_days'] ) && $booking['diff_days'] >= 1 ) {
							$name_days    = __( 'No. of Days', 'woocommerce-booking' );
							$other_data[] = array(
								'name'    => $name_days,
								'display' => $booking['diff_days'],
							);
							$day_price    = __( 'Per Day Price', 'woocommerce-booking' );
							$other_data[] = array(
								'name'    => $day_price,
								'display' => wc_price( $booking['price'] / $booking['diff_days'] ),
							);
						}
					}

					// Booking Time slot label and its value.
					if ( isset( $booking['time_slot'] ) && '' !== $booking['time_slot'] ) {

						$cart_time_label      = bkap_option( 'cart_time' );
						$time_slot_to_display = $booking['time_slot'];
						$time_exploded        = explode( '-', $time_slot_to_display );
						$from_time            = bkap_common::bkap_get_formated_time( $time_exploded[0] );
						$to_time              = isset( $time_exploded[1] ) ? bkap_common::bkap_get_formated_time( $time_exploded[1] ) : '';
						$time_slot_to_display = '' !== $to_time ? $from_time . ' - ' . $to_time : $from_time;
						$label                = __( ( '' !== $cart_time_label ? $cart_time_label : 'Booking Time' ), 'woocommerce-booking' );

						if ( 'multiple' !== $type_of_slot || $is_multidates ) {

							if ( $is_multidates ) {
								$bkap_multidate_data['time'][ $key ] = $label . ': ' . $time_slot_to_display;
							} else {
								$other_data[] = apply_filters(
									'bkap_get_item_data_time_slot',
									array(
										'name'    => $label,
										'display' => $time_slot_to_display,
									),
									$booking,
									$cart_item['product_id']
								);
							}
						}
					}

					// Booking Duration Time slot label and its value.
					if ( isset( $booking['duration_time_slot'] ) && $booking['duration_time_slot'] != '' ) {

						if ( 'multiple' !== $type_of_slot ) {
							$cart_time_label       = bkap_option( 'cart_time' );
							$duration_time_display = bkap_common::bkap_get_formated_time( $booking['duration_time_slot'] );
							$duration_name         = __( ( '' !== $cart_time_label ? $cart_time_label : 'Booking Time' ), 'woocommerce-booking' );
							$other_data[]          = array(
								'name'    => $duration_name,
								'display' => $duration_time_display,
							);
						}
					}

					// Booking duration and hours/minutes.
					if ( isset( $booking['selected_duration'] ) && $booking['selected_duration'] != '' ) {

						$d_setting         = $booking_settings['bkap_duration_settings'];
						$duration_label    = __( 'Duration', 'woocommerce-booking' );
						$selected_duration = explode( '-', $booking['selected_duration'] );
						$duration          = $selected_duration[0];
						$d_type            = 'hours' === $selected_duration[1] ? __( 'Hour(s)', 'woocommerce-booking' ) : __( 'Minute(s)', 'woocommerce-booking' );
						$other_data[]      = array(
							'name'    => $duration_label,
							'display' => $duration . ' ' . $d_type,
						);
					}

					// Booking resource label and its value.
					if ( isset( $booking['resource_id'] ) && '' !== $booking['resource_id'] ) {

						$show_resource = apply_filters( 'bkap_display_resource_info_on_cart_checkout', true, $cart_item );

						if ( $show_resource ) {
							$resource_title = Class_Bkap_Product_Resource::bkap_get_resource_label( $cart_item['product_id'] );
							$resource_title = ( '' != $resource_title ) ? $resource_title : __( 'Resource Type', 'wocommerce-booking' );
							$resource_name  = Class_Bkap_Product_Resource::get_resource_name( $booking['resource_id'] );

							if ( $is_multidates ) {
								$bkap_multidate_data['resource'][ $key ] = $resource_title . ': ' . $resource_name;
							} else {
								$other_data[] = array(
									'name'    => $resource_title,
									'display' => $resource_name,
								);
							}
						}
					}

					// Booking person and its value.
					if ( isset( $booking['persons'] ) ) {
						if ( $person_check ) {
							if ( isset( $booking['persons'][0] ) ) {
								$other_data[] = array(
									'name'    => Class_Bkap_Product_Person::bkap_get_person_label( $duplicate_of ),
									'display' => $booking['persons'][0],
								);
							} else {
								foreach ( $booking['persons'] as $key => $value ) {
									$other_data[] = array(
										'name'    => get_the_title( $key ),
										'display' => $value,
									);
								}
							}
							$person_check = false;
						}
					}

					$other_data = apply_filters( 'bkap_get_item_data', $other_data, $cart_item );
				}

				if ( $is_multidates ) {

					// Date - Time - Resource.
					$multidates_display_data = '';

					foreach ( $cart_item['bkap_booking'] as $key => $booking ) {

						$display_data = '';

						// Date.
						if ( isset( $bkap_multidate_data['date'][ $key ] ) ) {
							$display_data .= $bkap_multidate_data['date'][ $key ];
						}

						// Time.
						if ( isset( $bkap_multidate_data['time'][ $key ] ) ) {
							$display_data .= isset( $bkap_multidate_data['date'][ $key ] ) ? ' - ' . $bkap_multidate_data['time'][ $key ] : $bkap_multidate_data['time'][ $key ];
						}

						// Resource.
						if ( isset( $bkap_multidate_data['resource'][ $key ] ) ) {
							$display_data .= ( '' === $display_data ? '' : '<br/>' ) . $bkap_multidate_data['resource'][ $key ];
						}

						if ( '' !== $display_data ) {
							$multidates_display_data .= $display_data . '<br/><br/>';
						}
					}

					$other_data[] = array(
						'name'    => __( 'Booking Summary', 'woocommerce-booking' ),
						'display' => $multidates_display_data,
					);
				}
			}

			return $other_data;
		}

		/**
		 * This function modifies the product price in WooCommerce Cart Widget
		 *
		 * @param array $fragments WooCommerce Cart fragements that display data
		 *
		 * @globals mixed $woocommerce
		 *
		 * @return array Cart Fragments
		 *
		 * @since 2.5.0
		 *
		 * @hook woocommerce_add_to_cart_fragments
		 */

		public static function bkap_woo_cart_widget_subtotal( $fragments ) {

			global $woocommerce;

			$price = 0;
			foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {

				if ( isset( $values['bkap_booking'] ) ) {
					$booking = $values['bkap_booking'];
				}

				if ( isset( $booking[0]['price'] ) && $booking[0]['price'] != 0 ) {
					$price += ( $booking[0]['price'] ) * $values['quantity'];
				} else {

					if ( $values['variation_id'] == '' ) {
						$product_type = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $values['data']->product_type : $values['data']->get_type();
					} else {
						$product_type = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $values['data']->parent->product_type : $values['data']->parent->get_type();
					}

					$variation_id = 0;

					if ( $product_type == 'variable' ) {
						$variation_id = $values['variation_id'];
					}

					$book_price = bkap_common::bkap_get_price( $values['product_id'], $variation_id, $product_type );

					$price += $book_price * $values['quantity'];
				}
			}

			$total_price = number_format( $price, wc_get_price_decimals(), '.', '' );

			ob_start();
			$currency_symbol = get_woocommerce_currency_symbol();
			print( '<p class="total"><strong>Subtotal:</strong> <span class="amount">' . $currency_symbol . $total_price . '</span></p>' );

			$fragments['p.total'] = ob_get_clean();

			return $fragments;
		}
	}
	$bkap_cart = new bkap_cart();
}
