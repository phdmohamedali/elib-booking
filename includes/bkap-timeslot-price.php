<?php
/**
 * Booking & Appointment Plugin for WooCommerce
 *
 * Class for Handling the timeslot pricing and to add hidden data on front end product page of the date and time booking product
 *
 * @author      Tyche Softwares
 * @category    Core
 * @package     BKAP/Date-Time
 * @version     2.0
 */

if ( ! class_exists( 'bkap_timeslot_price' ) ) {

	/**
	 * Class for price caluculations for the timeslots
	 *
	 * @class bkap_timeslot_price
	 */
	class bkap_timeslot_price {

		/**
		 * Default constructor
		 *
		 * @since 2.0
		 */
		public function __construct() {

			// Print hidden fields.
			add_action( 'bkap_print_hidden_fields', array( &$this, 'timeslot_hidden_fields' ), 10, 2 );
			// Display updated price on the product page.
			add_action( 'bkap_display_updated_addon_price', array( &$this, 'timeslot_display_updated_price' ), 3, 8 );

			// Updating saved timeslots from G:i to H:i format.
			add_action( 'bkap_update_time_gi_to_hi', array( &$this, 'bkap_update_time_gi_to_hi_callback' ), 10 );
		}

		/**
		 * This function will add hidden field for the timeslot price.
		 *
		 * @since 2.0
		 * @hook bkap_print_hidden_fields
		 * @param int $product_id Product Id.
		 * @param int $booking_settings Booking Settings.
		 */
		public function timeslot_hidden_fields( $product_id, $booking_settings ) {

			$variable_timeslot_price = self::get_timeslot_variable_price( $product_id );
			print( '<input type="hidden" id="wapbk_hidden_variable_timeslot_price" name="wapbk_hidden_variable_timeslot_price" value="' . $variable_timeslot_price . '">' );
		}

		/**
		 * This function will calculate if the timeslot having price or not
		 *
		 * @since 2.0
		 * @param int $product_id  Product Id.
		 * @return string $variable_timeslot_price if timeslot having price then returns Yes else No.
		 */
		public function get_timeslot_variable_price( $product_id ) {
			$slot_price       = array();
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {
				$time_slot_arr = ( isset( $booking_settings['booking_time_settings'] ) ) ? $booking_settings['booking_time_settings'] : array();

				if ( is_array( $time_slot_arr ) && count( $time_slot_arr ) > 0 ) {
					foreach ( $time_slot_arr as $key => $value ) {
						foreach ( $value as $k => $v ) {
							if ( isset( $v['slot_price'] ) && $v['slot_price'] > 0 ) {
								$slot_price[] = $v['slot_price'];
							}
						}
					}
				}
			}

			$variable_timeslot_price = 'no';
			if ( count( $slot_price ) > 0 ) {
				$variable_timeslot_price = 'yes';
			}
			return $variable_timeslot_price;
		}

		/**
		 * This function will display the price of selected date and time.
		 *
		 * @since 2.0
		 * @since Updated 5.15.0
		 * @hook bkap_display_updated_addon_price
		 * @param int    $product_id Product ID.
		 * @param int    $booking_settings Booking Settings.
		 * @param int    $_product Product Object.
		 * @param string $booking_date Date.
		 * @param int    $variation_id Variation ID.
		 * @param int    $gf_options Option price from Gravity Forms.
		 * @param int    $resource_id Resource ID.
		 */
		public static function timeslot_display_updated_price(
			$product_id,
			$booking_settings,
			$_product,
			$booking_date,
			$variation_id,
			$gf_options = 0,
			$resource_id = '',
			$person_data = array()
		) {

			$global_settings     = bkap_global_setting();
			$product_type        = $_product->get_type();
			$time_slot           = isset( $_POST['timeslot_value'] ) ? sanitize_text_field( $_POST['timeslot_value'] ) : '';
			$special_price_array = bkap_special_booking_price::special_booking_display_updated_price( $product_id, $booking_settings, $_product, $product_type, $booking_date, $variation_id, $gf_options, $resource_id );

			// If resource is attached to the product then calculating resource price and adding it to final price.
			$resource_price = 0;

			if ( '' !== $resource_id ) {
				$resource_id = explode( ',', $resource_id );

				foreach ( $resource_id as $id ) {
					$resource        = new BKAP_Product_Resource( $id, $product_id );
					$resource_price += $resource->get_base_cost();
				}
			}

			$person_price        = 0;
			$person_total        = 0;
			$consider_person_qty = false;
			if ( count( $person_data ) > 0 ) {

				$person_types = $booking_settings['bkap_person_data'];
				foreach ( $person_data as $p_id => $p_value ) {
					if ( 1 === absint( $p_value['person_id'] ) ) {
						$person_total = absint( $p_value['person_val'] );
					} elseif ( 'on' === $booking_settings['bkap_person_type'] ) {
						$person_total += absint( $p_value['person_val'] );
						if ( absint( $p_value['person_val'] ) > 0 ) {
							$person_price += $person_types[ $p_value['person_id'] ]['base_cost'];
							$person_price += ( $person_types[ $p_value['person_id'] ]['block_cost'] * absint( $p_value['person_val'] ) );
						}
					}
				}
				if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_each_person_booking'] ) {
					$consider_person_qty = true;
				}
			}

			if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_price_per_person'] ) {
				$person_price = $person_price * $person_total;
			}

			if ( isset( $_POST['date_time_type'] ) && $_POST['date_time_type'] == 'duration_time' ) {

				if ( $_POST['timeslot_value'] == '' ) {
					die();
				}

				$qty_check = 0;
				foreach ( WC()->cart->get_cart() as $cart_check_key => $cart_check_value ) {
					if ( $product_id == $cart_check_value['product_id']
					&& $_POST['bkap_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
					&& $_POST['timeslot_value'] == $cart_check_value['bkap_booking'][0]['duration_time_slot'] ) {

						$cart_total_person = 1;
						if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
							if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_each_person_booking'] ) {
								$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );
							}
						}
						$qty_check = $qty_check + ( $cart_check_value['quantity'] * $cart_total_person );
						break;
					}
				}

				$duration_date = sanitize_text_field( $_POST['bkap_date'] );
				$duration_time = sanitize_text_field( $_POST['timeslot_value'] );
				$from          = strtotime( $duration_date . ' ' . $duration_time );
				$d_setting     = get_post_meta( $product_id, '_bkap_duration_settings', true );
				$d_max_booking = isset( $d_setting['duration_max_booking'] ) ? $d_setting['duration_max_booking'] : '';

				if ( '' !== $resource_id && is_array( $resource_id ) ) {
					$d_max_booking = Class_Bkap_Product_Resource::compute_maximum_booking( $resource_id, $duration_date, $product_id, $booking_settings );
				}

				$base_interval     = (int) $d_setting['duration']; // 2 Hour set for product
				$selected_duration = sanitize_text_field( $_POST['bkap_duration'] ); // Entered value on front end : 1
				$duration_type     = $d_setting['duration_type']; // Type of Duration set for product Hours/mins
				$interval          = $selected_duration * $base_interval; // Total hours/mins based on selected duration and set duration : 2
				$resource_id       = isset( $_POST['resource_id'] ) ? $_POST['resource_id'] : ''; // ID of selected Resource.

				if ( 'hours' === $duration_type ) {
					$interval      = $interval * 3600;
					$base_interval = $base_interval * 3600;
				} else {
					$interval      = $interval * 60;
					$base_interval = $base_interval * 60;
				}

				$to              = $from + $interval;
				$blocks          = array( $from );
				$duration_booked = Bkap_Duration_Time::bkap_display_availabile_blocks_html( $product_id, $blocks, $from, $to, array( $interval, $base_interval ), $resource_id, $duration_type );
				$quantity        = explode( ',', $_POST['quantity'] );

				foreach ( $quantity as $key => $value ) {

					if ( ! empty( $duration_booked['duration_booked'] ) ) {

						if ( $consider_person_qty ) {
							$value = $value * $person_total;
						}

						if ( $value > $duration_booked['duration_booked'][ $from ] ) {

							if ( $consider_person_qty ) {
								$validation_msg = bkap_max_persons_available_msg( '', $product_id );
								$not_available  = sprintf( $validation_msg, $duration_booked['duration_booked'][ $from ] );
							} else {
								$not_available = __( 'Booking is not available for selected quantity', 'woocommerce-booking' );
							}

							$not_available = bkap_woocommerce_error_div( $not_available );
							wp_send_json(
								array(
									'message' => $not_available,
									'error'   => true,
								)
							);
						}
					} elseif ( $d_max_booking != '' && $d_max_booking != 0 ) {

						if ( $consider_person_qty ) {
							$value = $value * $person_total;
						}

						if ( $value > ( $d_max_booking - $qty_check ) ) {
							if ( $consider_person_qty ) {
								$validation_msg = bkap_max_persons_available_msg( '', $product_id );
								$not_available  = sprintf( $validation_msg, ( $d_max_booking - $qty_check ) );
							} else {
								$not_available = __( 'Booking is not available for selected quantity', 'woocommerce-booking' );
							}
							$not_available = bkap_woocommerce_error_div( $not_available );
							wp_send_json(
								array(
									'message' => $not_available,
									'error'   => true,
								)
							);
						}
					}
				}
			}

			if ( 'grouped' === $product_type ) {

				$currency_symbol = get_woocommerce_currency_symbol();
				$has_children    = $price_str = '';
				$price_arr       = array();

				if ( $_product->has_child() ) {
					$has_children = 'yes';
					$child_ids    = $_product->get_children();
				}

				$quantity_grp_str = $_POST['quantity'];
				$quantity_array   = explode( ',', $quantity_grp_str );
				$i                = 0;
				$raw_price_str    = '';

				foreach ( $child_ids as $k => $v ) {
					$child_product      = wc_get_product( $v );
					$product_type_child = $child_product->get_type();
					$time_slot_price    = self::get_price( $v, 0, $product_type_child, $booking_date, $time_slot, 'product', $global_settings );
					$time_slot_price    = $time_slot_price + $resource_price;
					$final_price        = $time_slot_price * $quantity_array[ $i ];
					$raw_price          = $final_price;

					if ( function_exists( 'icl_object_id' ) ) {
						global $woocommerce_wpml;
						// Multi currency is enabled
						if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
							$custom_post = bkap_common::bkap_get_custom_post( $v, 0, $product_type );
							if ( $custom_post == 0 ) {
								$raw_price   = apply_filters( 'wcml_raw_price_amount', $final_price );
								$final_price = $raw_price;
							}
						}
					}
					$wc_price_args = bkap_common::get_currency_args();
					$final_price   = wc_price( $final_price, $wc_price_args );

					$raw_price_str .= $v . ':' . $raw_price . ',';
					$price_str     .= $child_product->get_title() . ': ' . $final_price . '<br>';
					$i++;
				}
				$time_slot_price = $price_str;
			} else {

				$time_slot_price = '';
				if ( $time_slot != '' ) {
					$time_slot_price = self::get_price( $product_id, $variation_id, $product_type, $booking_date, $time_slot, 'product', $global_settings );
				}

				if ( ( $time_slot_price == '' ) && ( isset( $_POST['special_booking_price'] ) && $_POST['special_booking_price'] != '' ) ) {
					$time_slot_price = $_POST['special_booking_price'];
					$raw_price_str   = ( isset( $_POST['grouped_raw_price'] ) ) ? $_POST['grouped_raw_price'] : '';
				} elseif ( $time_slot_price === '' ) {
					$time_slot_price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $booking_date );
				}

				if ( ! is_array( $time_slot_price ) ) {

					$time_slot_price = $time_slot_price + $resource_price;
					if ( apply_filters( 'bkap_apply_person_settings_on_other_prices', $product_id ) ) {
						if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_price_per_person'] ) {
							$time_slot_price = $time_slot_price * $person_total;
						}
					}
					$time_slot_price = $time_slot_price + $person_price;
				} else {
					foreach ( $time_slot_price as $tsp_key => $tsp_value ) {
						$time_slot_price[ $tsp_key ] = $time_slot_price[ $tsp_key ] + $resource_price;
						if ( apply_filters( 'bkap_apply_person_settings_on_other_prices', $product_id ) ) {
							if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_price_per_person'] ) {
								$time_slot_price = $time_slot_price * $person_total;
							}
						}
						$time_slot_price[ $tsp_key ] = $time_slot_price[ $tsp_key ] + $person_price;
					}
				}
			}

			if ( isset( $_POST['nyp'] ) && '' != $_POST['nyp'] ) {
				$time_slot_price = $_POST['nyp'];
			}

			$time_slot_price = apply_filters( 'bkap_modify_booking_price', $time_slot_price, $product_id, $variation_id, $product_type );

			$check_addon = true;
			if ( in_array( $product_type, array( 'grouped', 'composite', 'bundle' ) ) ) {
				$check_addon = false;
			}

			if ( $check_addon && ( ( function_exists( 'is_bkap_seasonal_active' ) && is_bkap_seasonal_active() && 'yes' == $booking_settings['booking_seasonal_pricing_enable'] ) ||
				( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active( $product_id ) ) ||
				( function_exists( 'is_bkap_multi_time_active' ) && is_bkap_multi_time_active() && isset( $booking_settings['booking_enable_multiple_time'] ) && 'multiple' == $booking_settings['booking_enable_multiple_time'] ) ) ) {
				// Calculate the tax.
				if ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
					$total_price = 0;

					if ( is_array( $time_slot_price ) ) {

						foreach ( $time_slot_price as $key => $price ) {
							$total_price += wc_get_price_including_tax(
								$_product,
								array( 'price' => $price )
							);
						}
					} else {
						$total_price = wc_get_price_including_tax(
							$_product,
							array( 'price' => $time_slot_price )
						);
					}
					$_POST['price'] = $total_price;

				} else {
					$_POST['price'] = $time_slot_price;
				}
			} else {

				$wp_send_json = array();

				if ( $product_type != 'grouped' ) {

					if ( isset( $_POST['quantity'] ) && $_POST['quantity'] != 0 ) {
						$time_slot_price = $time_slot_price * $_POST['quantity'];
					}

					$time_slot_price = number_format( $time_slot_price, wc_get_price_decimals(), '.', '' );

					// Save the actual Bookable amount, as a raw amount
					// If Multi currency is enabled, convert the amount before saving it.
					$total_price = $time_slot_price;
					if ( function_exists( 'icl_object_id' ) ) {
						$custom_post = bkap_common::bkap_get_custom_post( $product_id, $variation_id, $product_type );
						if ( $custom_post == 1 ) {
							$total_price = $time_slot_price;
						} elseif ( $custom_post == 0 ) {
							$total_price = apply_filters( 'wcml_raw_price_amount', $time_slot_price );
						}
					}

					$wp_send_json['total_price_calculated'] = $total_price;
					$wp_send_json['bkap_price_charged']     = $total_price;

					// if gf options are enable .. commented since we no longer need to display price below Booking Box.
					if ( isset( $gf_options ) && $gf_options > 0 ) {
						$wp_send_json['bkap_gf_options_total'] = addslashes( $gf_options );
					}

					// Calculate the tax.
					if ( wc_tax_enabled() ) {
						if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
							$total_price = wc_get_price_including_tax(
								$_product,
								array( 'price' => $total_price )
							);
						}
					}

					// format the price.
					$wc_price_args   = bkap_common::get_currency_args();
					$formatted_price = wc_price( $total_price, $wc_price_args );

				} else {
					// Calculate the tax.
					if ( $product_type != 'grouped' && wc_tax_enabled() ) {
						if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
							$time_slot_price = wc_get_price_including_tax(
								$_product,
								array( 'price' => $time_slot_price )
							);
						}
					}
					$formatted_price                        = $time_slot_price;
					$wp_send_json['total_price_calculated'] = addslashes( $raw_price_str );
					$wp_send_json['bkap_price_charged']     = addslashes( $raw_price_str );
				}

				if ( isset( $total_price ) && 'bundle' == $product_type ) {
					$bundle_price    = bkap_common::get_bundle_price( $total_price, $product_id, $variation_id );
					$formatted_price = wc_price( $bundle_price, $wc_price_args );

					$wp_send_json['total_price_calculated'] = $bundle_price;
				}

				if ( 'composite' === $product_type ) {
					$composite_price = bkap_common::get_composite_price( $total_price, $product_id, $variation_id );
					$formatted_price = wc_price( $composite_price, $wc_price_args );
				}

				// display the price on the front end product page.
				$display_price  = get_option( 'book_price-label' ) . ' ' . $formatted_price;
				$additional_msg = apply_filters( 'bkap_display_custom_message_in_price_box', '', $product_id, $booking_settings );
				if ( $additional_msg ) {
					$wp_send_json['bkap_no_of_days'] = $additional_msg;
				}

				$wp_send_json['bkap_price'] = $display_price;
				$wp_send_json               = apply_filters( 'bkap_final_price_json_data', $wp_send_json, $product_id );

				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * This function will return the price of the selected timeslot.
		 *
		 * @since 4.2.0
		 * @param int    $product_id Product ID.
		 * @param int    $variation_id Variation ID.
		 * @param string $product_type Type of the Product.
		 * @param string $booking_date Date.
		 * @param string $time_slot Time Slot.
		 * @param string $called_from Called from product.
		 *
		 * @return int $time_slot_price Price of the timeslot
		 */
		public static function get_price( $product_id, $variation_id, $product_type, $booking_date, $time_slot, $called_from, $global_settings ) {

			$time_slot_price = $time_slot_price_total = 0; // set the slot price as the product base price.
			$timezone_check  = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.

			if ( isset( $_POST['special_booking_price'] ) && '' != $_POST['special_booking_price'] ) {
				$time_slot_price = $_POST['special_booking_price'];
			} else {
				$time_slot_price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type, $booking_date );
			}

			$booking_settings = bkap_setting( $product_id );

			if ( '' != $time_slot ) {
				// Check if multiple time slots are enabled.
				$seperator_pos         = strpos( $time_slot, ',' );
				$time_slot_array_price = array();
				if ( isset( $seperator_pos ) && $seperator_pos != '' ) {
					$time_slot_array = explode( ',', $time_slot );
				} else {
					$time_slot_array   = array();
					$time_slot_array[] = $time_slot;
				}

				for ( $i = 0; $i < count( $time_slot_array ); $i++ ) {
					// split the time slot into from and to time.
					$timeslot_explode = explode( '-', $time_slot_array[ $i ] );

					if ( $timezone_check ) {
						$gmt_offset = get_option( 'gmt_offset' );
						$gmt_offset = $gmt_offset * 60 * 60;

						$bkap_offset = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' );
						$bkap_offset = $bkap_offset * 60;
						$offset      = $bkap_offset - $gmt_offset;

						$site_timezone     = bkap_booking_get_timezone_string();
						$customer_timezone = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );

						$timeslot_explode[0] = bkap_convert_date_from_timezone_to_timezone( $booking_date . ' ' . $timeslot_explode[0], $customer_timezone, $site_timezone, 'H:i' );
						$timeslot_explode[1] = isset( $timeslot_explode[1] ) ? bkap_convert_date_from_timezone_to_timezone( $booking_date . ' ' . $timeslot_explode[1], $customer_timezone, $site_timezone, 'H:i' ) : '';
					} else {
						$timeslot_explode[0] = date( 'H:i', strtotime( $timeslot_explode[0] ) );

						if ( isset( $timeslot_explode[1] ) && $timeslot_explode[1] != '' ) {
							$timeslot_explode[1] = date( 'H:i', strtotime( $timeslot_explode[1] ) );
						}
					}

					$from_hrs = explode( ':', $timeslot_explode[0] ); // split frm hrs in hrs and min.
					$to_hrs   = array(
						'0' => '0',
						'1' => '00',
					); // similarly for to time, but first default it to 0:00, so it works for open ended time slots as well.

					if ( isset( $timeslot_explode[1] ) && $timeslot_explode[1] != '' ) {
						$to_hrs = explode( ':', $timeslot_explode[1] );
					}

					if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time']
						&& isset( $booking_settings['booking_time_settings'] ) && count( $booking_settings['booking_time_settings'] ) > 0 ) {

						// match the booking date as specific overrides recurring.
						$booking_date_to_check = date( 'j-n-Y', strtotime( $booking_date ) );
						if ( array_key_exists( $booking_date_to_check, $booking_settings['booking_time_settings'] ) ) {
							foreach ( $booking_settings['booking_time_settings'] as $key => $value ) {
								if ( $key == $booking_date_to_check ) {
									foreach ( $value as $k => $v ) {
										$price = 0;
										// match the time slot.
										if ( ( intval( $from_hrs[0] ) == intval( $v['from_slot_hrs'] ) ) && ( intval( $from_hrs[1] ) == intval( $v['from_slot_min'] ) ) && ( intval( $to_hrs[0] ) == intval( $v['to_slot_hrs'] ) ) && ( intval( $to_hrs[1] ) == intval( $v['to_slot_min'] ) ) ) {
											// fetch and save the price.
											if ( isset( $v['slot_price'] ) && $v['slot_price'] != '' ) {
												$price = $v['slot_price'];
												if ( isset( $called_from ) && $called_from == 'cart' ) {
													$price = apply_filters( 'wcml_raw_price_amount', $v['slot_price'] );
												}
												$time_slot_array_price[] = $price;
												$time_slot_price_total  += $price;
											} else {
												$time_slot_array_price[] = $time_slot_price;
												$time_slot_price_total  += $time_slot_price;
											}
										}
									}
								}
							}
						} else {
							// Get the weekday.
							$weekday         = date( 'w', strtotime( $booking_date ) );
							$booking_weekday = 'booking_weekday_' . $weekday;
							foreach ( $booking_settings['booking_time_settings'] as $key => $value ) {
								// match the weekday.
								if ( $key == $booking_weekday ) {
									foreach ( $value as $k => $v ) {
										$price = '';
										// match the time slot.
										if ( ( intval( $from_hrs[0] ) == intval( $v['from_slot_hrs'] ) ) && ( intval( $from_hrs[1] ) == intval( $v['from_slot_min'] ) ) && ( intval( $to_hrs[0] ) == intval( $v['to_slot_hrs'] ) ) && ( intval( $to_hrs[1] ) == intval( $v['to_slot_min'] ) ) ) {
											// fetch and save the price.
											if ( isset( $v['slot_price'] ) && '' != $v['slot_price'] ) {
												$price = $v['slot_price'];
												if ( isset( $called_from ) && 'cart' === $called_from ) {
													$price = apply_filters( 'wcml_raw_price_amount', $v['slot_price'] );
												}
												$time_slot_array_price[] = $price;
												$time_slot_price_total  += $price;
											} else {
												$time_slot_array_price[] = $time_slot_price;
												$time_slot_price_total  += $time_slot_price;
											}
										}
									}
								}
							}
						}
					} elseif ( isset( $booking_settings['bkap_duration_settings'] ) && count( $booking_settings['bkap_duration_settings'] ) > 0 ) {

						$d_setting = $booking_settings['bkap_duration_settings'];

						if ( $d_setting['duration_price'] != '' ) {
							$time_slot_price_total += $d_setting['duration_price']; // ajax ma selected duration pan pass karvu padse.
						} else {
							$time_slot_price_total += $time_slot_price;
						}
					}
				}

				if ( isset( $_POST['bkap_duration'] ) && $_POST['bkap_duration'] > 0 ) {
					$final_price           = $time_slot_price_total * apply_filters( 'bkap_fixed_duration_price', $_POST['bkap_duration'] );
					$time_slot_price_total = apply_filters( 'bkap_final_duration_price', $final_price, $product_id, $booking_settings, $_POST );
				}

				if ( $time_slot_price_total !== 0 ) {
					$time_slot_price = $time_slot_price_total;
					$time_slot_price = $time_slot_price / count( $time_slot_array );
				}

				if ( count( $time_slot_array_price ) > 1 ) {
					$time_slot_price = $time_slot_array_price;
				}
			}

			return $time_slot_price;
		}

		/**
		 * This function will update the time format from G:i to H:i.
		 * Issue #3847
		 *
		 * @since 4.3.0
		 */
		public function bkap_update_time_gi_to_hi_callback() {

			global $wpdb;

			// Updating 9:00 to 09:00 in from_time and to_time.
			$query   = 'UPDATE `' . $wpdb->prefix . "booking_history`
			SET from_time=CONCAT('0',from_time) WHERE length(from_time) = 4";
			$updated = $wpdb->query( $query ); // db call ok; no-cache ok.

			$query   = 'UPDATE `' . $wpdb->prefix . "booking_history`
						SET to_time=CONCAT('0',to_time) WHERE length(to_time) = 4";
			$updated = $wpdb->query( $query ); // db call ok; no-cache ok.

			// Updating G:i to H:i in woocommerce_order_itemmeta table.
			$today_ymd  = date( 'Y-m-d', current_time( 'timestamp' ) );
			$query_date = 'SELECT order_item_id FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
							WHERE meta_key = '_wapbk_booking_date'
							AND meta_value >= %s";
			$results    = $wpdb->get_results( $wpdb->prepare( $query_date, $today_ymd ), ARRAY_A );

			$order_item_ids = '';
			if ( count( $results ) > 0 ) {
				$order_item_ids = implode( ',', array_column( $results, 'order_item_id' ) );
			}

			if ( '' !== $order_item_ids ) {
				$query_time = 'SELECT * FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
								WHERE meta_key = '_wapbk_time_slot'
								AND order_item_id IN ( $order_item_ids )";
				$results    = $wpdb->get_results( $query_time );

				$hi = strtotime( '10:00' );
				foreach ( $results as $key => $value ) {
					$item_id       = $value->order_item_id;
					$time          = $value->meta_value;
					$time_explode  = explode( ' - ', $time );
					$fromstrtotime = strtotime( $time_explode[0] );
					if ( $fromstrtotime < $hi ) { // only update for the time less 10:00.
						$time_slot = date( 'H:i', strtotime( $time_explode[0] ) );
						if ( isset( $time_explode[1] ) ) {
							$to_time   = date( 'H:i', strtotime( $time_explode[1] ) );
							$time_slot = $time_slot . ' - ' . $to_time;
						}
						wc_update_order_item_meta( $item_id, '_wapbk_time_slot', $time_slot );
					}
				}
			}
		}
	}
}
$bkap_timeslot_price = new bkap_timeslot_price();
