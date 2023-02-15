<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for making Booking and Appointment compatible with other plugins
 * such as WooCommerce Composites & Bundles
 *
 * @author  Tyche Softwares
 * @package BKAP/Addons
 * @category Classes
 */

if ( ! class_exists( 'bkap_addon_compatibility_class' ) ) {

	/**
	 * Class for making other plugins compatible with Booking plugin
	 *
	 * @since 4.2.0
	 */

	class bkap_addon_compatibility_class {

		/**
		 * Initialize and attach functions to hooks
		 *
		 * @since 4.2.0
		 */

		function __construct() {

			add_action( 'woocommerce_before_add_to_cart_button', array( &$this, 'bkap_composites_before_cart_button' ) );
			add_action( 'woocommerce_checkout_create_order_line_item', array( &$this, 'bkap_add_wpa_prices' ), 10, 3 );
			add_filter( 'bkap_cart_allow_add_bookings', array( &$this, 'bkap_allow_composite_parent' ), 10, 2 );
			add_filter( 'bkap_cart_modify_meta', array( &$this, 'bkap_add_composite_child_meta' ), 10, 1 );
			add_filter( 'alg_pif_recalculate_product_price', array( &$this, 'bkap_alg_pif_recalculate_product_price' ), 10, 3 );
			add_filter( 'bkap_final_price_json_data', array( &$this, 'bkap_final_price_json_data_alg' ), 10, 2 );
			add_filter( 'bkap_add_additional_data', array( &$this, 'bkap_add_additional_data_wc_membership' ), 11, 3 );

			// Call for Price Compatibility.
			add_filter( 'woocommerce_is_purchasable', array( &$this, 'bkap_cfp_compatibility' ), PHP_INT_MAX, 2 );
			add_filter( 'bkap_show_basedon_options_in_alg_call_for_price', array( &$this, 'bkap_show_form_cfp_text_for_all_products' ), 10, 2 );

			add_filter( 'woocommerce_email_recipient_bkap_new_booking', array( &$this, 'bkap_change_vendor_emails' ), 10, 3 );
		}

		/**
		 * Change New Booking Email Recipient to Vendor's Email Address.
		 *
		 * @param string $recipient Recipient Email Address.
		 * @param obj    $object Shop Order Object.
		 * @param obj    $email_obj Email Object.
		 *
		 * @since 5.10.0
		 */
		public function bkap_change_vendor_emails( $recipient, $object, $email_obj ) {

			if ( isset( $object->product_id ) ) {
				$product_id     = $object->product_id;
				$vendor_id      = get_post_field( 'post_author', $product_id );
				$user           = get_user_by( 'ID', $vendor_id );
				$send_to_vendor = apply_filters( 'bkap_confirmation_emails_to_vendor', true );
				if ( $send_to_vendor && '' !== $user->user_email && ( is_plugin_active( 'wc-vendors/class-wc-vendors.php' ) || is_plugin_active( 'dokan-pro/dokan-pro.php' ) ) ) {
					$recipient = $user->user_email;
				}
			}

			return $recipient;
		}

		/**
		 * Show Booking Form when Call for Price Pro Plugin is active and Enable Call for Price for products having non-zero prices option is enabled.
		 *
		 * @param bool $status Status of Product Purchasable.
		 * @param obj  $_product Product Object.
		 *
		 * @since 5.8.2
		 */
		public static function bkap_cfp_compatibility( $status, $_product ) {

			if ( class_exists( 'Alg_WC_Call_For_Price' ) ) {

				$check = apply_filters( 'bkap_show_basedon_options_in_alg_call_for_price', false, $_product );

				if ( $check ) {
					$product_id   = $_product->get_id();
					$duplicate_of = bkap_common::bkap_get_product_id( $product_id );
					$bookable     = bkap_common::bkap_get_bookable_status( $duplicate_of );
					if ( $bookable ) {
						return true;
					}
				}
			}

			return $status;
		}

		/**
		 * Show Booking Form when Enable Call for Price for products having non-zero prices option is enabled.
		 *
		 * @param bool $status False.
		 * @param obj  $_product Product Object.
		 *
		 * @since 5.8.2
		 */
		public static function bkap_show_form_cfp_text_for_all_products( $status, $_product ) {
			if ( 'yes' === get_option( 'alg_call_for_price_enable_cfp_text_for_all_products', 'no' ) ) {
				return true;
			}
			return $status;
		}

		/**
		 * Membership Discount.
		 *
		 * @param array $additional_data Array of Booking Additional Data.
		 * @param array $booking_settings Booking Settings.
		 * @param int   $product_id Product ID.
		 *
		 * @since 5.6
		 */
		public static function bkap_add_additional_data_wc_membership( $additional_data, $booking_settings, $product_id ) {

			if ( ! function_exists( 'wc_memberships' ) ) {
				return $additional_data;
			}

			if ( wc_memberships_user_has_member_discount() ) {
				$additional_data['wc_membership'] = true;
			}
			return $additional_data;
		}

		/**
		 * Calculating the Price JSON Data based on selected currency - Currency Switcher for WooCommerce Pro.
		 *
		 * @param array $wp_send_json Array of Booking pricing info for selected booking details.
		 * @param int   $product_id Product ID.
		 *
		 * @since 5.6
		 */
		public static function bkap_final_price_json_data_alg( $wp_send_json, $product_id ) {

			if ( isset( $_POST['alg_lang'] ) ) {

				$price     = $wp_send_json['total_price_calculated'];
				$alg_price = alg_get_product_price_by_currency( $price, $_POST['alg_lang'], wc_get_product( $product_id ) );

				$wp_send_json['total_price_calculated'] = $price;
				$wp_send_json['bkap_price_charged']     = $price;

				$wc_price_args              = bkap_common::get_currency_args();
				$wc_price_args['currency']  = $_POST['alg_lang'];
				$formatted_price            = wc_price( $alg_price, $wc_price_args );
				$display_price              = get_option( 'book_price-label' ) . ' ' . $formatted_price;
				$wp_send_json['bkap_price'] = $display_price;
			}

			return $wp_send_json;
		}

		/**
		 * Hook woocommerce_before_add_to_cart_form not available for Composite product type.
		 * Hence hide Buttons and Quantity from here
		 *
		 * @since 4.2
		 *
		 * @globals WP_Post $post
		 * @globals mixed $wpdb
		 *
		 * @hook woocommerce_before_add_to_cart_button
		 */
		public function bkap_composites_before_cart_button() {

			global $post,$wpdb;

			$product_id       = bkap_common::bkap_get_product_id( $post->ID );
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			if ( $booking_settings == '' || ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] != 'on' ) ) {
				return;
			}

			$product      = wc_get_product( $product_id );
			$product_type = $product->get_type();

			if ( $product_type === 'composite' &&
				$booking_settings != '' &&
				( isset( $booking_settings['booking_enable_date'] ) &&
				$booking_settings['booking_enable_date'] == 'on' ) &&
				( isset( $booking_settings['booking_purchase_without_date'] ) &&
				$booking_settings['booking_purchase_without_date'] != 'on' ) ) {

				// check the setting
				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
				if ( isset( $global_settings->display_disabled_buttons ) && 'on' == $global_settings->display_disabled_buttons ) {
					?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery( ".single_add_to_cart_button" ).prop( "disabled", true );
							jQuery( '.quantity input[name="quantity"]' ).prop( "disabled", true );
						});

					</script>
					<?php
				} else {
					?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery( ".single_add_to_cart_button" ).hide();
							jQuery( '.quantity input[name="quantity"]' ).hide();
						});
					</script>
					<?php
				}
				?>
					<script type="text/javascript">
						jQuery(document).ready(function() {
							jQuery( ".payment_type" ).hide();
							jQuery(".partial_message").hide();
						});
					</script>
				<?php

				bkap_booking_process::bkap_price_display();
			}
		}

		/**
		 * Add WooCommerce Product Addon Prices in Order Item Meta
		 *
		 * @param WC_Order_Item $item WooCommerce Order Item
		 * @param string        $cart_item_key Cart Item Key
		 * @param array         $values Cart Item Meta Array
		 *
		 * @since 4.2.0
		 *
		 * @hook woocommerce_checkout_create_order_line_item
		 */
		public function bkap_add_wpa_prices( $item, $cart_item_key, $values ) {

			if ( isset( $values['bkap_booking'] ) && isset( $values['addons'] ) && count( $values['addons'] ) > 0 ) {
				$wpa_total = bkap_common::bkap_get_wpa_cart_totals( $values );
				$item->add_meta_data( '_wapbk_wpa_prices', $wpa_total );
			}
		}

		/**
		 * Allow only composite Parent Product to add Booking Details as it is
		 *
		 * @param bool  $add_details Boolean value depending on state to allow or disallow
		 * @param array $cart_item_meta Cart Item Meta
		 * @return bool Boolean on whether to allow or disallow
		 *
		 * @since 4.7.0
		 *
		 * @hook bkap_cart_allow_add_bookings
		 */
		public function bkap_allow_composite_parent( $add_details, $cart_item_meta ) {

			if ( ! array_key_exists( 'composite_parent', $cart_item_meta ) ) {
				return true;
			} elseif ( array_key_exists( 'composite_parent', $cart_item_meta ) ) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Add Booking Data to cart item meta for composite products
		 *
		 * @param array $cart_item_meta Cart Item Meta
		 * @return array Cart Item Meta Array with modified data
		 * @since 4.7.0
		 *
		 * @hook bkap_cart_modify_meta
		 */
		public function bkap_add_composite_child_meta( $cart_item_meta ) {

			if ( array_key_exists( 'composite_parent', $cart_item_meta ) && $cart_item_meta['composite_parent'] !== '' ) {

				$cart_arr = array();

				if ( isset( WC()->cart->cart_contents[ $cart_item_meta['composite_parent'] ]['bkap_booking'] ) ) {
					$composite_parent_booking = WC()->cart->cart_contents[ $cart_item_meta['composite_parent'] ]['bkap_booking'][0];
				}

				$parent_product = WC()->cart->cart_contents[ $cart_item_meta['composite_parent'] ]['data'];
				$component_data = $parent_product->get_component_data( $cart_item_meta['composite_item'] );

				$composite_data = $cart_item_meta['composite_data'][ $cart_item_meta['composite_item'] ];

				if ( isset( $composite_data['product_id'] ) && $composite_data['product_id'] !== '' ) {
					$composite_product = wc_get_product( $composite_data['product_id'] );
				}

				if ( isset( $component_data['priced_individually'] ) && 'yes' === $component_data['priced_individually'] ) {
					if ( isset( $composite_data['variation_id'] ) && $composite_data['variation_id'] !== '' ) {
						$composite_variation = wc_get_product( $composite_data['variation_id'] );
						$cart_arr['price']   = $composite_variation->get_price();
						if ( isset( $composite_data['discount'] ) && $composite_data['discount'] !== '' ) {
							$cart_arr['price'] = $cart_arr['price'] - ( $cart_arr['price'] * $composite_data['discount'] / 100 );
						}

						if ( isset( $_POST['wapbk_diff_days'] ) ) {

							if ( '' == $_POST['wapbk_diff_days'] ) {
								$cart_arr['price'] = $cart_arr['price'];
							} else {
								$cart_arr['price'] = $cart_arr['price'] * $_POST['wapbk_diff_days'];
							}
						}
					} else {
						$price        = $composite_product->get_regular_price();
						$booking_type = bkap_type( $composite_data['product_id'] );

						if ( 'multiple_days' === $booking_type ) {
							$param = array(
								date( 'Y-m-d', strtotime( $composite_parent_booking['hidden_date'] ) ),
								date( 'Y-m-d', strtotime( $composite_parent_booking['hidden_date_checkout'] ) ),
								$booking_type,
							);
						} else {
							$param = array(
								date( 'Y-m-d', strtotime( $composite_parent_booking['hidden_date'] ) ),
								date( 'w', strtotime( $composite_parent_booking['hidden_date'] ) ),
								$booking_type,
							);
						}


						$price = bkap_get_special_price( $composite_data['product_id'], $param, $price );

						if ( isset( $_POST['wapbk_diff_days'] ) && $_POST['wapbk_diff_days'] > 0 ) {
							$cart_arr['price'] = $price * $_POST['wapbk_diff_days'];
						} else {
							$cart_arr['price'] = $price;
						}
					}
				}

				$duplicate_of = bkap_common::bkap_get_product_id( $composite_data['product_id'] );

				$is_bookable = bkap_common::bkap_get_bookable_status( $duplicate_of );

				if ( $is_bookable && isset( $composite_parent_booking ) ) {

					$cart_arr['date']        = $composite_parent_booking['date'];
					$cart_arr['hidden_date'] = $composite_parent_booking['hidden_date'];

					if ( isset( $composite_parent_booking['date_checkout'] ) && $composite_parent_booking['date_checkout'] != '' ) {
						$cart_arr['date_checkout']        = $composite_parent_booking['date_checkout'];
						$cart_arr['hidden_date_checkout'] = $composite_parent_booking['hidden_date_checkout'];
					}

					if ( isset( $composite_parent_booking['time_slot'] ) ) {
						$cart_arr['time_slot'] = $composite_parent_booking['time_slot'];
					}
				}

				if ( isset( $cart_arr['date'] ) || isset( $cart_arr['price'] ) ) {
					$cart_item_meta['bkap_booking'][] = $cart_arr;
				}
			}

			return $cart_item_meta;
		}

		/**
		 * Calculating the Price in Cart/Checkout according to the Booking and Product Input Field Pro.
		 *
		 * @param float $alg_price Price From Product Input Field.
		 * @param float $price Product Price.
		 * @param array $value Cart Item Value.
		 *
		 * @hook alg_pif_recalculate_product_price
		 */
		public function bkap_alg_pif_recalculate_product_price( $alg_price, $price, $value ) {

			if ( isset( $value['bkap_booking'] ) ) {
				$alg_price = ( $alg_price - $price ) + $value['bkap_booking'][0]['price'];
			}
			return $alg_price;
		}
	}
}
$bkap_addon_compatibility_class = new bkap_addon_compatibility_class();
