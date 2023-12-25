<?php
/**
 * Booking & Appointment Plugin for WooCommerce
 *
 * Class for Handling the timeslot pricing and to add hidden data on front end product page of the date and time booking product
 *
 * @author      Tyche Softwares
 * @category    Core
 * @package     BKAP/Special-Price
 * @version     2.0
 */

if ( ! class_exists( 'bkap_special_booking_price' ) ) {

	/**
	 * Class for Handling Special Pricing
	 *
	 * @class bkap_special_booking_price
	 */

	class bkap_special_booking_price {

		/**
		 * Default constructor
		 *
		 * @since 2.0
		 */

		public function __construct() {
			// Display the multiple days specia  booking price on the product page
			add_action( 'bkap_display_multiple_day_updated_price', array( &$this, 'bkap_special_booking_show_multiple_updated_price' ), 7, 11 );
		}

		/**
		 * This function is used to add/update special booking
		 *
		 * @since 2.0
		 * @global object $wpdb Global wpdb Object
		 * @param int   $post_id Product ID
		 * @param array $recurring_prices Array of special price set for Weekdays
		 * @param array $specific_prices Array of special price set for Specific Date
		 */

		function bkap_save_special_booking_price( $post_id, $recurring_prices = array(), $specific_prices = array() ) {

			global $wpdb;

			// Get the existing record
			$booking_special_prices = get_post_meta( $post_id, '_bkap_special_price', true );

			if ( is_array( $booking_special_prices ) && count( $booking_special_prices ) > 0 ) {
				$cnt = count( $booking_special_prices );
			} else {
				$booking_special_prices = array();
				$cnt                    = 0;
			}

			/**
			 * this is being done only to make sure the code is compatible with PHP versions lower than 5.3
			 * it should be removed when we decide to upgrade everything to 5.3+
			 */

			// Loop through the existing records, note down the weekday/date and the key

			$special_prices = array();

			if ( is_array( $booking_special_prices ) && count( $booking_special_prices ) > 0 ) {
				foreach ( $booking_special_prices as $special_key => $special_value ) {
					$weekday_set = $special_value['booking_special_weekday'];
					$date_set    = $special_value['booking_special_date'];
					if ( $weekday_set != '' ) {
						$special_prices[ $weekday_set ] = $special_key;
					} elseif ( $date_set != '' ) {
						$special_prices[ $date_set ] = $special_key;
					}
				}
			}

			$max_key_value = 0;

			if ( is_array( $special_prices ) && count( $special_prices ) > 0 ) {
				$max_key_value = max( $special_prices );
			}
			// Run a loop through all weekdays
			if ( is_array( $recurring_prices ) && count( $recurring_prices ) > 0 ) {
				foreach ( $recurring_prices as $w_key => $w_price ) {
					// check if record exists for the given weekday
					if ( $cnt > 0 ) {
						// if record is present, we need the key
						/*
						commented as USE is available only for PHP 5.3+
						$key = key( array_filter( $booking_special_prices, function( $item ) use( $w_key ) {
							return isset( $item[ 'booking_special_weekday' ] ) && $w_key == $item[ 'booking_special_weekday' ];
						}) ); */

						if ( array_key_exists( $w_key, $special_prices ) ) {
							$key = $special_prices[ $w_key ];
						}
					}
					// if key is found, update the existing record
					if ( isset( $key ) && is_numeric( $key ) && $key >= 0 ) {
						$booking_special_prices[ $key ]['booking_special_weekday'] = $w_key;
						$booking_special_prices[ $key ]['booking_special_date']    = '';
						$booking_special_prices[ $key ]['booking_special_price']   = $w_price;
						$key = ''; // reset the key
					} else { // add a new one
						$max_key_value++;
						$booking_special_prices[ $max_key_value ]['booking_special_weekday'] = $w_key;
						$booking_special_prices[ $max_key_value ]['booking_special_date']    = '';
						$booking_special_prices[ $max_key_value ]['booking_special_price']   = $w_price;
						$cnt++; // increment the count
					}
				}
			}

			// Loop through all specific dates
			if ( is_array( $specific_prices ) && count( $specific_prices ) > 0 ) {
				foreach ( $specific_prices as $w_key => $w_price ) {

					$w_key = date( 'Y-m-d', strtotime( $w_key ) );
					// Check if record exists for the given date
					if ( $cnt > 0 ) {
						// If record is present, we need the key
						/*
						commented as USE is available only for PHP 5.3+
						$key = key( array_filter( $booking_special_prices, function( $item ) use( $w_key ) {
							return isset( $item[ 'booking_special_date' ] ) && $w_key == $item[ 'booking_special_date' ];
						}) ); */
						if ( array_key_exists( $w_key, $special_prices ) ) {
							$key = $special_prices[ $w_key ];
						}
					}
					// If key found, update existing record
					if ( isset( $key ) && is_numeric( $key ) && $key >= 0 ) {
						$booking_special_prices[ $key ]['booking_special_weekday'] = '';
						$booking_special_prices[ $key ]['booking_special_date']    = $w_key;
						$booking_special_prices[ $key ]['booking_special_price']   = $w_price;
						$key = ''; // reset the key
					} else { // add a new record
						$max_key_value++;
						$booking_special_prices[ $max_key_value ]['booking_special_weekday'] = '';
						$booking_special_prices[ $max_key_value ]['booking_special_date']    = $w_key;
						$booking_special_prices[ $max_key_value ]['booking_special_price']   = $w_price;
						$cnt++; // increment the count
					}
				}
			}

			// Unset any records for recurring weekdays where price may have been reset to blanks
			if ( is_array( $special_prices ) && count( $special_prices ) > 0 ) {
				foreach ( $special_prices as $s_key => $s_value ) {

					if ( substr( $s_key, 0, 7 ) == 'booking' ) {
						if ( ! array_key_exists( $s_key, $recurring_prices ) ) {
							unset( $booking_special_prices[ $s_value ] );
						}
					} else { // it's a specific date
						$key_check = date( 'j-n-Y', strtotime( $s_key ) );
						if ( ! array_key_exists( $key_check, $specific_prices ) ) {
							unset( $booking_special_prices[ $s_value ] );
						}
					}
				}
			}

			// Update the record in the DB
			update_post_meta( $post_id, '_bkap_special_price', $booking_special_prices );

			return $booking_special_prices;
		}

		/**
		 * This function is used to updated price of product
		 *
		 * @since 2.0
		 *
		 * @global object $wpdb Global wpdb Object
		 * @param int    $product_id Product ID
		 * @param string $booking_date Date
		 * @param int    $variation_id Variation ID
		 */

		public static function special_booking_display_updated_price(
			$product_id,
			$booking_settings,
			$_product,
			$product_type,
			$booking_date,
			$variation_id,
			$gf_options,
			$resource_id
		) {

			$price_array = array(
				'special_booking_price' => '',
				'grouped_raw_price'     => '',
			);

			if ( $product_type == 'grouped' ) {
				$special_price_present = 'NO';
				$currency_symbol       = get_woocommerce_currency_symbol();
				$has_children          = '';
				$price_str             = '';
				$raw_price_str         = '';
				$price_arr             = array();

				if ( $_product->has_child() ) {
					$has_children = 'yes';
					$child_ids    = $_product->get_children();
				}

				$quantity_grp_str = $_POST['quantity'];
				$quantity_array   = explode( ',', $quantity_grp_str );
				$i                = 0;

				foreach ( $child_ids as $k => $v ) {
					$final_price           = 0;
					$child_product         = wc_get_product( $v );
					$product_type_child    = $child_product->get_type();
					$product_price         = bkap_common::bkap_get_price( $v, 0, 'simple' );
					$special_booking_price = self::get_price( $v, $booking_date );

					if ( isset( $special_booking_price ) && $special_booking_price != 0 && $special_booking_price != '' ) {
						$special_price_present = 'YES';
						$final_price           = $special_booking_price * trim( $quantity_array[ $i ] );
						$raw_price             = $final_price;

						if ( function_exists( 'icl_object_id' ) ) {
							global $woocommerce_wpml;
							// Multi currency is enabled
							if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
								$custom_post = bkap_common::bkap_get_custom_post( $v, 0, $product_type );
								if ( $custom_post == 0 ) {
									$raw_price = apply_filters( 'wcml_raw_price_amount', $final_price );
								}
							}
						}
						$wc_price_args = bkap_common::get_currency_args();
						$final_price   = wc_price( $raw_price, $wc_price_args );

						$raw_price_str .= $v . ':' . $raw_price . ',';
						$price_str     .= $child_product->get_title() . ': ' . $final_price . '<br>';
					} else {
						$final_price = $product_price * trim( $quantity_array[ $i ] );
						$raw_price   = $final_price;
						if ( function_exists( 'icl_object_id' ) ) {
							global $woocommerce_wpml;
							// Multi currency is enabled
							if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
								$custom_post = bkap_common::bkap_get_custom_post( $v, 0, $product_type );
								if ( $custom_post == 0 ) {
									$raw_price = apply_filters( 'wcml_raw_price_amount', $final_price );
								}
							}
						}
						$wc_price_args = bkap_common::get_currency_args();
						$final_price   = wc_price( $raw_price, $wc_price_args );

						$raw_price_str .= $v . ':' . $raw_price . ',';
						$price_str     .= $child_product->get_title() . ': ' . $final_price . '<br>';
					}
					$i++;
				}
				if ( isset( $price_str ) && $price_str != '' ) {
					$special_booking_price = $price_str;
					if ( $special_price_present == 'YES' ) {
						$price_array['special_booking_price'] = $special_booking_price;
						$price_array['grouped_raw_price']     = $raw_price_str;
						$_POST['special_booking_price']       = $special_booking_price;
						$_POST['grouped_raw_price']           = $raw_price_str;
					}
				}
			} else {
				$special_booking_price = self::get_price( $product_id, $booking_date );

				if ( isset( $special_booking_price ) && $special_booking_price != '' ) {
					$price_array['special_booking_price'] = $special_booking_price;
					$_POST['special_booking_price']       = $special_booking_price;
				}
			}

			return $price_array;
		}

		/**
		 * This function will calculate the special price for specific date or weekday
		 *
		 * @since 4.0.0
		 * @param int    $product_id Product ID
		 * @param string $booking_date Date
		 * @return int $special_booking_price Returns the special price
		 */

		public static function get_price( $product_id, $booking_date ) {

			$booking_special_prices = get_post_meta( $product_id, '_bkap_special_price', true );
			$special_booking_price  = '';

			if ( is_array( $booking_special_prices ) && count( $booking_special_prices ) > 0 ) {

				foreach ( $booking_special_prices as $key => $values ) {
					list( $year, $month, $day ) = explode( '-', $booking_date );

					if ( $values['booking_special_date'] == $booking_date ) {
						$special_booking_price = $values['booking_special_price'];
						break;
					}
				}

				if ( $special_booking_price == '' ) { // specific date price was not found
					foreach ( $booking_special_prices as $key => $values ) {

						list( $year, $month, $day ) = explode( '-', $booking_date );
						$booking_day                = date( 'w', mktime( 0, 0, 0, $month, $day, $year ) );
						$day_name                   = "booking_weekday_$booking_day";

						if ( isset( $values['booking_special_weekday'] ) && $day_name == $values['booking_special_weekday'] ) {
							$special_booking_price = $values['booking_special_price'];
							break;
						}
					}
				}
			}
			return $special_booking_price;
		}

		/**
		 * This function is used to updated price of product for Multiple dates
		 *
		 * @since 2.0
		 * @global object $wpdb global wpdb Object
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param string $product_obj Product Object.
		 * @param int    $variation_id_to_fetch Variation ID.
		 * @param string $checkin_date Date.
		 * @param string $checkout_date Date.
		 * @param int    $number Number of days.
		 * @param int    $gf_options Option price from Gravity Forms.
		 * @param int    $resource_id Resource ID.
		 * @param string $currency_selected Selected currency.
		 */
		public function bkap_special_booking_show_multiple_updated_price(
				$product_id,
				$booking_settings,
				$product_obj,
				$variation_id_to_fetch,
				$checkin_date,
				$checkout_date,
				$number,
				$gf_options,
				$resource_id,
				$person_data,
				$currency_selected
			) {

			$global_settings   = bkap_global_setting();
			$number_count      = $number;
			$product_type      = $product_obj->get_type();
			$special_pricing   = false;
			$quantity          = ( isset( $_POST['quantity'] ) && $_POST['quantity'] > 0 ) ? $_POST['quantity'] : 1;
			$product           = wc_get_product( $product_id );
			$number            = apply_filters( 'bkap_selected_days_value', $number, $checkin_date, $checkout_date, $product_id, $booking_settings );
			$selected_days_msg = apply_filters( 'bkap_selected_days_full_text', $number . '<br>', $number, $checkin_date, $checkout_date, $product_id, $booking_settings );
			$wp_send_json      = array();
			$resource_price    = 0;

			$wp_send_json['bkap_no_of_days'] = $selected_days_msg;

			$booking_special_prices = get_post_meta( $product_id, '_bkap_special_price', true );
			if ( is_array( $booking_special_prices ) && count( $booking_special_prices ) > 0 ) {
				$special_pricing = true;
			}

			if ( isset( $_POST['price'] ) // Fixed Blocks
				&& ( isset( $booking_settings['booking_fixed_block_enable'] )
					&& $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable' )
			) {
				$price = $_POST['price'];
				if ( $special_pricing ) {
					$price = $price / $number;
				}
				$number = 1;
			} elseif ( isset( $_POST['price'] ) // Price By Ranges
				&& ( isset( $booking_settings['booking_block_price_enable'] )
					&& $booking_settings['booking_block_price_enable'] == 'booking_block_price_enable' )
			) {

				$price   = $_POST['price'];
				$str_pos = strpos( $_POST['price'], '-' );

				if ( isset( $str_pos ) && $str_pos != '' ) {
					$price_type = explode( '-', $_POST['price'] );
					$price      = $price_type[0] / $number;
				}
			} else {
				$price = bkap_common::bkap_get_price( $product_id, $variation_id_to_fetch, $product_type, $checkin_date, $checkout_date );
			}

			if ( isset( $_POST['nyp'] ) && '' != $_POST['nyp'] ) {
				$price = $_POST['nyp'];
			}

			$special_multiple_day_booking_price = 0;
			$startDate                          = $checkin_date;
			$check_special_price_flag           = false;

			if ( $special_pricing ) {

				// If rental is active, then we have chk price for all selected days
				if ( function_exists( 'is_bkap_rental_active' )
					&& is_bkap_rental_active()
					&& isset( $booking_settings['booking_charge_per_day'] )
					&& $booking_settings['booking_charge_per_day'] == 'on'
				) {
					$endDate = strtotime( $checkout_date ) + ( 60 * 24 * 24 );
				} else {
					$endDate = strtotime( $checkout_date );
				}
				$price_per_date = array();

				while ( strtotime( $startDate ) < $endDate ) {

					$check_special_price_flag = true;
					$special_price            = $price;

					foreach ( $booking_special_prices as $key => $values ) {

						list( $year, $month, $day ) = explode( '-', $startDate );
						$booking_day                = date( 'w', mktime( 0, 0, 0, $month, $day, $year ) );
						$startDate1                 = "booking_weekday_$booking_day";

						if ( isset( $values['booking_special_weekday'] ) && $startDate1 == $values['booking_special_weekday'] ) {
							$special_price = $values['booking_special_price'];
						}
					}

					foreach ( $booking_special_prices as $key => $values ) {
						if ( $values['booking_special_date'] == $startDate ) {
							$special_price = $values['booking_special_price'];
						}
					}

					$is_new = apply_filters( 'bkap_apply_new_price_on_edit_booking', false );
					if ( ! $is_new && isset( $_POST['booking_id'] ) && ! empty( $_POST['booking_id'] ) ) {
						$booking_id    = (int) $_POST['booking_id'];
						$special_price = get_post_meta( $booking_id, '_bkap_cost', true );
					}

					$price_per_date[ $startDate ] = $special_price;

					$special_multiple_day_booking_price += $special_price;
					$startDate                           = date( 'Y-m-d', strtotime( '+1 day', strtotime( $startDate ) ) );
				}

				$_POST['price_per_date'] = $price_per_date;

				if ( ! $check_special_price_flag ) {
					$special_multiple_day_booking_price = $price;
				}

				// Don't divide price by no. of days if Fixed block.
				if ( isset( $booking_settings['booking_fixed_block_enable'] )
					&& $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable' ) {
					$special_multiple_day_booking_price = $special_multiple_day_booking_price;
				} else {
					$special_multiple_day_booking_price = $special_multiple_day_booking_price / $number;
				}

				$_POST['special_multiple_day_booking_price'] = $special_multiple_day_booking_price;
				$_POST['booking_multiple_days_count']        = $number;
			} else {
				$special_multiple_day_booking_price = $price;
			}

			// Calculate resource price.
			if ( '' !== $resource_id ) {
				$resource_id = explode( ',', $resource_id );
				if ( count( $resource_id ) > 0 ) {
					foreach ( $resource_id as $id ) {
						$resource = new BKAP_Product_Resource( $id, $product_id );
						$_price   = $resource->get_base_cost();

						if ( isset( $global_settings->resource_price_per_day ) && 'on' === $global_settings->resource_price_per_day ) {
							$_price = $_price * $number_count;
						}
						if ( isset( $_POST['quantity'] ) && (int) $_POST['quantity'] > 0 ) {
							$_price = $_price * $_POST['quantity'];
						}
						$resource_price += $_price;
					}
				}
			}

			if ( ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() )
				|| ( function_exists( 'is_bkap_seasonal_active' ) && is_bkap_seasonal_active() && 'yes' == $booking_settings['booking_seasonal_pricing_enable'] )
			) {
				if ( isset( $special_multiple_day_booking_price ) && $special_multiple_day_booking_price !== '' ) {
					$_POST['price'] = $special_multiple_day_booking_price + $resource_price;
				} else {
					$error_message = __( 'Please select an option.', 'woocommerce-booking' );
					// print( 'jQuery( "#bkap_price" ).html( "' . addslashes( $error_message ) . '");' );
					// die();
					$wp_send_json['bkap_price'] = addslashes( $error_message );
					wp_send_json( $wp_send_json );
				}
			} else {
				if ( isset( $number ) && $number > 1 ) {
					$special_multiple_day_booking_price = (float) $special_multiple_day_booking_price * $number;
				}

				$special_multiple_day_booking_price = (float) $special_multiple_day_booking_price * (int) $quantity;
				$special_multiple_day_booking_price = number_format( $special_multiple_day_booking_price, wc_get_price_decimals(), '.', '' );

				$special_multiple_day_booking_price += $resource_price;

				/* Person Price Calculations */
				$person_price = 0;
				$person_total = 0;
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
				}

				if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_price_per_person'] ) {
					$person_price = $person_price * $person_total;
				}

				$person_price *= $number;
				$person_price *= (int) $quantity;

				if ( apply_filters( 'bkap_apply_person_settings_on_other_prices', $product_id ) ) {
					if ( isset( $booking_settings['bkap_price_per_person'] ) && 'on' === $booking_settings['bkap_price_per_person'] ) {
						$special_multiple_day_booking_price = $special_multiple_day_booking_price * $person_total;
					}
				}

				$special_multiple_day_booking_price += $person_price;

				// Save the actual Bookable amount, as a raw amount If Multi currency is enabled, convert the amount before saving it
				$total_price = $special_multiple_day_booking_price;

				if ( function_exists( 'icl_object_id' ) ) {

					$custom_post = bkap_common::bkap_get_custom_post( $product_id, 0, $product_type );

					if ( $custom_post == 1 ) {
						$total_price = $special_multiple_day_booking_price;
					} elseif ( $custom_post == 0 ) {
						$total_price = apply_filters( 'wcml_raw_price_amount', $special_multiple_day_booking_price );
					}
				}

				if ( $person_price > 0 ) {
					$total_price = apply_filters( 'bkap_consider_person_settings_without_product_price', $total_price, $person_price );
				}

				$total_price = apply_filters( 'bkap_modify_booking_price', $total_price );

				// print( 'jQuery( "#total_price_calculated" ).val(' . $total_price . ');' );
				$wp_send_json['total_price_calculated'] = $total_price;
				// save the price in a hidden field to be used later
				// print( 'jQuery( "#bkap_price_charged" ).val(' . $total_price . ');' );
				$wp_send_json['bkap_price_charged'] = $total_price;
				// If gf options are enabled, multiply with the number of nights based on the settings
				$display_gf_in_booking = apply_filters( 'bkap_add_gf_option_total_to_booking_total', false );

				if ( isset( $gf_options ) && $gf_options > 0 && $display_gf_in_booking ) {

					$gf_total = $gf_options;

					if ( isset( $global_settings->woo_gf_product_addon_option_price )
						&& 'on' == $global_settings->woo_gf_product_addon_option_price
					) {
						if ( isset( $_POST['diff_days'] ) && $_POST['diff_days'] > 1 ) {
							$gf_total = $gf_options * $_POST['diff_days'];
						}
					}
					$total_price += $gf_total;
				}

				// Calculate the tax
				if ( wc_tax_enabled() ) {
					if ( 'incl' == get_option( 'woocommerce_tax_display_shop' ) ) {
						$total_price = wc_get_price_including_tax(
							$product_obj,
							array( 'price' => $total_price )
						);
					}
				}

				$wc_price_args   = bkap_common::get_currency_args();
				$formatted_price = wc_price( $total_price, $wc_price_args );

				if ( 'bundle' == $product_type ) {
					$bundle_price = bkap_common::get_bundle_price( $total_price, $product_id, $variation_id_to_fetch );
					// Calculate the tax
					if ( wc_tax_enabled() ) {
						if ( 'incl' == get_option( 'woocommerce_tax_display_shop' ) ) {
							$bundle_price = wc_get_price_including_tax(
								$product_obj,
								array( 'price' => $bundle_price )
							);
						}
					}
					$wp_send_json['total_price_calculated'] = $bundle_price;
					$formatted_price                        = wc_price( $bundle_price, $wc_price_args );
				} elseif ( 'composite' === $product_type ) {
					$composite_price = bkap_common::get_composite_price( $total_price, $product_id, $variation_id_to_fetch );
					// Calculate the tax
					if ( wc_tax_enabled() ) {
						if ( 'incl' == get_option( 'woocommerce_tax_display_shop' ) ) {
							$composite_price = wc_get_price_including_tax(
								$product_obj,
								array( 'price' => $composite_price )
							);
						}
					}
					$formatted_price = wc_price( $composite_price, $wc_price_args );
				}

				$display_price = get_option( 'book_price-label' ) . ' ' . $formatted_price;
				// display the price on the front end product page
				// print( 'jQuery( "#bkap_price" ).html( "' . addslashes( $display_price ) . '");' );
				// die();
				$wp_send_json['bkap_price'] = $display_price;

				wp_send_json( $wp_send_json );
			}
		}
	}
}
$bkap_special_booking_price = new bkap_special_booking_price();
