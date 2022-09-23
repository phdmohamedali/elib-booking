<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * This class is for validating the booking on Product, Cart and Checkout page.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Validation
 * @since    2.0
 * @category Classes
 */

require_once 'bkap-common.php';
require_once 'bkap-lang.php';

if ( ! class_exists( 'Bkap_Validation' ) ) {

	/**
	 * Class for Handling Validation on Product, Cart and Checkout page
	 *
	 * @class Bkap_Validation
	 */
	class Bkap_Validation {

		/**
		 * Default constructor
		 *
		 * @since 2.0
		 */

		public function __construct() {

			add_filter( 'woocommerce_add_to_cart_validation', array( &$this, 'bkap_get_validate_add_cart_item' ), 8, 3 );

			add_action( 'woocommerce_before_checkout_process', array( &$this, 'bkap_cart_checkout_quantity_check' ) );
			add_action( 'woocommerce_check_cart_items', array( &$this, 'bkap_cart_checkout_quantity_check' ) );

			// To validate the product in cart and checkout as per the Advance Booking Period set.
			add_action( 'woocommerce_check_cart_items', array( &$this, 'bkap_remove_product_from_cart' ) );
			add_action( 'woocommerce_before_checkout_process', array( &$this, 'bkap_remove_product_from_cart' ) );

			add_filter( 'bkap_get_validate_add_cart_item', array( &$this, 'bkap_same_bookings_in_cart_validation' ), 10, 4 );
		}

		/**
		 * This functions validates the Availability for the selected date and timeslots.
		 *
		 * @global $wp Global $wp Object
		 * @param string $passed Validation Status.
		 * @param int    $product_id Product Id.
		 * @param int    $qty Quantity selected when adding product to cart.
		 *
		 * @since 2.0
		 * @hook woocommerce_add_to_cart_validation
		 * @return string $passed Returns true if allowed to add to cart else false if not.
		 */

		public static function bkap_get_validate_add_cart_item( $passed, $product_id, $qty ) {

			global $wp;

			$perform_validation = apply_filters( 'bkap_skip_add_to_cart_validation', false, $product_id );

			if ( $perform_validation ) {
				return $passed;
			}

			$product_id       = bkap_common::bkap_get_product_id( $product_id );
			$booking_settings = bkap_setting( $product_id );
			$product          = wc_get_product( $product_id );
			$bkap_sale        = apply_filters( 'bkap_get_validate_add_cart_item_sale_rent', 'sale' );

			do_action( 'bkap_get_validate_add_cart_item_before', $product_id, $booking_settings, $product );

			if ( '' != $booking_settings && ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] == 'on' ) ) {

				$date_check = self::bkap_post_date_validation( $product_id, $booking_settings );
				if ( $date_check ) {

					$quantity     = self::bkap_get_quantity( $product_id );
					$passed       = 'yes' === $quantity ? true : false;
					$product_type = $product->get_type();

					if ( 'composite' === $product_type ) {
						$passed = self::bkap_get_composite_item_validations( $product_id, $product );
					}

					if ( 'bundle' === $product_type ) {
						$passed = self::bkap_get_bundle_item_validations( $product_id, $product );
					}
				} elseif ( isset( $_GET['pay_for_order'] ) && isset( $_GET['key'] ) && isset( $wp->query_vars['order-pay'] ) && isset( $_GET['subscription_renewal'] ) && $_GET['subscription_renewal'] === 'true' ) {
					$passed = true;
				} else {

					if ( isset( $booking_settings['booking_purchase_without_date'] ) && $booking_settings['booking_purchase_without_date'] == 'on' && $bkap_sale == 'sale' ) {
						$passed = true;
					} else {
						$passed  = false;
						$message = apply_filters( 'bkap_cant_be_added_to_cart_without_booking', __( 'Product can\'t be added to cart. Please select booking details to continue.', 'woocommerce-booking' ) );
						if ( ! wc_has_notice( $message, 'error' ) ) {
							wc_add_notice( $message, 'error' );
						}
						wp_safe_redirect( get_permalink( $product_id ) );
						exit();
					}
				}
			} else {
				$passed = true;
			}

			do_action( 'bkap_get_validate_add_cart_item_after', $product_id, $booking_settings, $product );

			return apply_filters( 'bkap_get_validate_add_cart_item', $passed, $product_id, $booking_settings, $product );
		}

		/**
		 * This functions check if date is selected when adding to cart or not.
		 *
		 * @param string|int $product_id Product ID.
		 * @param array      $booking_settings Booking Settings.
		 *
		 * @return bool true if booking date is selected else false.
		 * @since 5.3.0
		 */
		public static function bkap_post_date_validation( $product_id, $booking_settings ) {

			$status = false;
			if ( isset( $_POST['wapbk_hidden_date'] ) && '' !== $_POST['wapbk_hidden_date'] ) {
				$status = true;
			}

			if ( isset( $_POST['bkap_multidate_data'] ) && '' !== $_POST['bkap_multidate_data'] ) {
				$status = true;
			}

			return $status;
		}

		/**
		 * This functions Validate Composite Products
		 *
		 * @param string|int $product_id Product ID.
		 * @param WC_Product $product Product Object.
		 *
		 * @return bool true for available and false for locked out
		 * @since 5.13.0
		 */
		public static function bkap_get_composite_item_validations( $product_id, $product ) {

			$wc_cp_cart    = new WC_CP_Cart();
			$configuration = $wc_cp_cart->get_posted_composite_configuration( $product_id );
			$status = array();
			foreach ( $configuration as $cart_key => $cart_value ) {
				if ( isset( $cart_value['product_id'] ) && '' !== $cart_value['product_id'] ) {
					if ( isset( $cart_value['quantity'] ) && $cart_value['quantity'] > 0 ) {

						if ( isset( $cart_value['variation_id'] ) && $cart_value['variation_id'] !== '' ) {
							$_POST['variation_id'] = $cart_value['variation_id'];
						}

						$quantity = self::bkap_get_quantity( $cart_value['product_id'], $cart_value['quantity'] );
						$status[] = $quantity;
					}
				}
			}

			if ( in_array( 'no', $status, true ) ) {
				return false;
			}

			return true;
		}

		/**
		 * This functions Validate Bundle Products
		 *
		 * @param string|int $product_id Product ID.
		 * @param WC_Product $product Product Object.
		 *
		 * @return bool true for available and false for locked out
		 * @since 4.2
		 */

		public static function bkap_get_bundle_item_validations( $product_id, $product ) {

			$cart_configs = bkap_common::bkap_bundle_add_to_cart_config( $product );

			foreach ( $cart_configs as $cart_key => $cart_value ) {

				if ( isset( $cart_value['quantity'] ) && $cart_value['quantity'] > 0 ) {

					if ( isset( $cart_value['variation_id'] ) && $cart_value['variation_id'] !== '' ) {
						$_POST['variation_id'] = $cart_value['variation_id'];
					}

					$quantity = self::bkap_get_quantity( $cart_value['product_id'], $cart_value['quantity'] );

					if ( 'yes' !== $quantity ) {
						return false;
					}
				}
			}

			return true;
		}

		/**
		 * This function checks the overlapping timeslot for the selected date
		 * and timeslots when the product is added to cart.
		 *
		 * If the overlapped timeslot is already in cart and availability is less then selected
		 * bookinng date and timeslot then it will retun and array which contains the overlapped date
		 * and timeslot present in the cart
		 *
		 * @param int    $product_id Product ID
		 * @param array  $post $_POST
		 * @param string $check_in_date Date
		 * @param string $from_time_slot From Time
		 * @param string $to_time_slot To Time
		 * @since 4.4.0
		 * @return $pass_fail_result_array Array contains overlapped start date and timeslot present in cart.
		 */

		public static function bkap_validate_overlap_time_cart( $product_id, $post, $check_in_date, $from_time_slot, $to_time_slot ) {
			global $wpdb;

			$qty                    = isset( $post['quantity'] ) ? $post['quantity'] : 1;
			$pass_fail_result_array = array();
			$pass_fail_result       = true;

			$query              = "SELECT *  FROM `" . $wpdb->prefix . "booking_history`
                                        WHERE post_id = '" . $product_id . "' AND
                                        start_date = '" . $check_in_date . "' AND
                                        available_booking > 0  AND
                                        status !=  'inactive' ";
			$get_all_time_slots = $wpdb->get_results( $query );

			if ( count( $get_all_time_slots ) == 0 ) {
				return $pass_fail_result_array;
			}

			foreach ( $get_all_time_slots as $time_slot_key => $time_slot_value ) {

				$timeslot = $from_time_slot . ' - ' . $to_time_slot;

				$query_from_time_time_stamp = strtotime( $from_time_slot );
				$query_to_time_time_stamp   = strtotime( $to_time_slot );

				$time_slot_value_from_time_stamp = strtotime( $time_slot_value->from_time );
				$time_slot_value_to_time_stamp   = strtotime( $time_slot_value->to_time );

				$db_timeslot = $time_slot_value->from_time . ' - ' . $time_slot_value->to_time;

				if ( $query_to_time_time_stamp > $time_slot_value_from_time_stamp && $query_from_time_time_stamp < $time_slot_value_to_time_stamp ) {

					if ( $time_slot_value_from_time_stamp != $query_from_time_time_stamp || $time_slot_value_to_time_stamp != $query_to_time_time_stamp ) {

						foreach ( WC()->cart->cart_contents as $prod_in_cart_key => $prod_in_cart_value ) {

							if ( isset( $prod_in_cart_value['bkap_booking'] ) && ! empty( $prod_in_cart_value['bkap_booking'] ) ) {

								$booking_data = $prod_in_cart_value['bkap_booking'];
								$product_qty  = $prod_in_cart_value['quantity'];

								foreach ( $booking_data as $value ) {

									if ( isset( $value['time_slot'] ) && $value['time_slot'] != '' ) {

										if ( $value['time_slot'] == $db_timeslot && $time_slot_value->available_booking > 0 ) {

											$compare_qty = $time_slot_value->available_booking - $product_qty;

											if ( $compare_qty < $qty ) {
												$pass_fail_result = false;

												$pass_fail_result_array['pass_fail_result']   = $pass_fail_result;
												$pass_fail_result_array['pass_fail_date']     = $check_in_date;
												$pass_fail_result_array['pass_fail_timeslot'] = $db_timeslot;
											}
										}
									}
								}
							}
						}
					}
				}
			}

			return $pass_fail_result_array;
		}

		/**
		 * Based on the global timeslot option, this function adds up to the quantity to the selected quantity.
		 *
		 * @param int    $post_id Product ID.
		 * @param array  $post $_POST.
		 * @param int    $item_quantity Item Quantity.
		 * @since 5.6.1
		 */
		public static function bkap_validate_global_time_cart( $post_id, $post, $item_quantity ) {

			foreach ( WC()->cart->cart_contents as $cart_key => $cart_value ) {
				if ( isset( $cart_value['bkap_booking'] ) && ! empty( $cart_value['bkap_booking'] ) ) {
					$product_id = $cart_value['product_id'];
					if ( $product_id != $post_id ) {

						$booking_data = $cart_value['bkap_booking'];
						foreach ( $booking_data as $value ) {

							if ( isset( $value['time_slot'] ) && '' !== $value['time_slot'] ) {

								if ( $post['wapbk_hidden_date'] === $value['hidden_date'] && $value['time_slot'] === $post['time_slot'] ) {
									$item_quantity = $item_quantity + $cart_value['quantity'];
								}
							}
						}
					}
				}
			}

			return $item_quantity;
		}

		/**
		 * This function checks the availabilty for the selected date and timeslots when the product is added to cart.
		 * If availability is less then selected it prevents product from being added to the cart and displays an error message.
		 *
		 * @param int $post_id Product ID.
		 * @param int $bundle_child_qty Bundle Child Quantity.
		 *
		 * @since   4.4.0
		 * @global  object $wpdb Global wpdb Object.
		 * @global  object $woocommerce Global WooCommerce Object.
		 * @global  array $bkap_date_formats Array of Date Format.
		 *
		 * @return  Array $pass_fail_result_array Array contains overlapped start date and timeslot present in cart.
		 */
		public static function bkap_get_quantity( $post_id, $bundle_child_qty = '' ) {

			global $wpdb;
			global $woocommerce;
			global $bkap_date_formats;

			$post_id      = bkap_common::bkap_get_product_id( $post_id );
			$product      = wc_get_product( $post_id );
			$product_name = $product->get_name();
			$wc_version   = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );
			$parent_id    = ( $wc_version ) ? $product->get_parent() : bkap_common::bkap_get_parent_id( $post_id );

			$booking_settings       = bkap_setting( $post_id );
			$booking_type           = bkap_type( $post_id );
			$global_settings        = bkap_global_setting();
			$time_format            = $global_settings->booking_time_format;
			$date_format_to_display = $global_settings->booking_date_format;

			$timezone_check = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.

			// new chanegs..
			$date_checks   = array();
			$booking_dates = array();
			$time_slots    = array();
			if ( isset( $_POST['wapbk_hidden_date'] ) && '' !== $_POST['wapbk_hidden_date'] ) {
				$date_checks[]   = $_POST['wapbk_hidden_date']; // Selected date in Y-m-d format.
				$booking_dates[] = $_POST['booking_calender']; // Date will be in selected language.
				if ( isset( $_POST['time_slot'] ) ) {
					$time_slots[] = $_POST['time_slot'];
				}
				$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date'] ) ); // Date as per global setting.
			}

			if ( isset( $_POST['bkap_multidate_data'] ) && '' !== $_POST['bkap_multidate_data'] ) {

				$posted_multidate_data = $_POST['bkap_multidate_data'];
				$temp_data             = str_replace( '\\', '', $posted_multidate_data );
				$bkap_multidate_data   = (array) json_decode( $temp_data );

				foreach ( $bkap_multidate_data as $key => $value ) {
					$date_checks[]   = $value->hidden_date;
					$booking_dates[] = $value->date;
					if ( isset( $value->time_slot ) ) {
						$time_slots[] = $value->time_slot;
					}
				}
			}

			if ( isset( $booking_settings['booking_product_holiday'] ) && ! empty( $booking_settings['booking_product_holiday'] ) ) {
				foreach ( $date_checks as $key => $value ) {
					if ( isset( $booking_settings['booking_product_holiday'][ $value ] ) ) {
						$message                              = __( "$product_name cannot be booked for $booking_date due to holiday. Please choose some other date for booking.", 'woocommerce-booking' );
						if ( ! wc_has_notice( $message, 'error' ) ) {
							wc_add_notice( $message, $notice_type = 'error' );
						}
						return 'no';
					}
				}
			}

			$quantity_check_pass = 'yes';
			$variation_id        = isset( $_POST['variation_id'] ) ? $_POST['variation_id'] : '';
			$_POST['product_id'] = $post_id;

			/* Person Calculations - Total Selected Persons should be considered with the selected quantity */
			$total_person = 1;
			if ( isset( $booking_settings['bkap_person'] ) && 'on' === $booking_settings['bkap_person'] && 'on' === $booking_settings['bkap_each_person_booking'] ) {
				if ( isset( $_POST[ 'bkap_field_persons' ] ) ) {
					$total_person = (int) $_POST[ 'bkap_field_persons' ];
				} else {
					$person_data  = $booking_settings['bkap_person_data'];
					$total_person = 0;
					foreach ( $person_data as $p_id => $p_data ) {
						$p_key = 'bkap_field_persons_' . $p_id;
						if ( isset( $_POST[ $p_key ] ) && '' !== $_POST[ $p_key ] ) {
							$total_person = $total_person + (int) $_POST[ $p_key ];
						}
					}
				}
			}

			if ( isset( $_POST['bkap_front_resource_selection'] ) ) { // Resource validation start here.

				if ( '' == $_POST['bkap_front_resource_selection'] ) {
					$message = __( 'Please select the resource to add the product to cart.', 'woocommerce-booking' );
					wc_add_notice( $message, $notice_type = 'error' );

					return 'no';
				}

				$resource_id                = (int) $_POST['bkap_front_resource_selection'];
				$resource_name              = get_the_title( $resource_id );
				$resource_validation_result = array(
					'quantity_check_pass'        => $quantity_check_pass,
					'resource_booking_available' => '',
				);
				$resource_validation_result = apply_filters( 'bkap_resource_add_to_cart_validation', $_POST, $post_id, $booking_settings, $quantity_check_pass, $resource_validation_result );

				if ( 'no' === $resource_validation_result['quantity_check_pass'] ) {

					if ( isset( $resource_validation_result['resource_booking_available'] ) && $resource_validation_result['resource_booking_available'] > 0 ) {
						if ( isset( $_POST['time_slot'] ) && '' !== $_POST['time_slot'] ) {
							$message = sprintf(
								'%s bookings are available for %s on date %s for %s timeslot',
								$resource_validation_result['resource_booking_available'],
								$resource_name,
								$date_to_display,
								$_POST['time_slot']
							);
						} else {
							$message = sprintf(
								'%s bookings are available for %s on date.',
								$resource_validation_result['resource_booking_available'],
								$resource_name,
								$date_to_display
							);
						}
					} else {
						if ( isset( $_POST['time_slot'] ) && '' !== $_POST['time_slot'] ) {
							$message = sprintf(
								__( 'Available bookings for %s on date %s for %s timeslot are already added in the cart. You can add bookings for %s for a different date and time or place the order that is currently in the <a href="%s">%s</a>.', 'woocommerce-booking' ),
								$resource_name,
								$date_to_display,
								$_POST['time_slot'],
								$resource_name,
								esc_url( wc_get_page_permalink( 'cart' ) ),
								esc_html__( 'cart', 'woocommerce' )
							);

							$message = apply_filters( 'bkap_all_resource_datetime_availability_present_in_cart', $message, $resource_name, $date_to_display, $_POST['time_slot'] );
						} else {
							$message = sprintf(
								__( 'Available bookings for %s for date %s are already added in the cart. You can add bookings for %s for a different date or place the order that is currently in the <a href="%s">%s</a>.', 'woocommerce-booking' ),
								$resource_name,
								$date_to_display,
								$resource_name,
								esc_url( wc_get_page_permalink( 'cart' ) ),
								esc_html__( 'cart', 'woocommerce' )
							);
							$message = apply_filters( 'bkap_all_resource_date_availability_present_in_cart', $message, $resource_name, $date_to_display );
						}
					}
					if ( ! wc_has_notice( $message, 'error' ) ) {
						wc_add_notice( $message, $notice_type = 'error' );
					}
				}

				return $resource_validation_result['quantity_check_pass'];
			}

			/* Before checking lockout validations, confirm that the cart does not contain any conflicting products */
			$quantity_check_pass = apply_filters( 'bkap_validate_cart_products', $_POST, $post_id ); // yes if all good.

			switch ( $booking_type ) {
				case 'only_day':
				case 'multidates':
					do_action( 'bkap_single_days_product_validation' );

					if ( ! isset( $_POST['validated'] ) || ( isset( $_POST['validated'] ) && $_POST['validated'] == 'NO' ) ) {

						foreach ( $date_checks as $date_check ) { // loop through each date.

							$date_check_ymd = date( 'Y-m-d', strtotime( $date_check ) );

							$query   = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
										WHERE post_id = %d
										AND start_date = %s 
										AND status != 'inactive' ";
							$results = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $date_check_ymd ) );

							$item_quantity = isset( $_POST['quantity'] ) ? $_POST['quantity'] : 1;

							if ( isset( $bundle_child_qty ) && $bundle_child_qty > 0 ) {
								$item_quantity = $item_quantity * $bundle_child_qty;
							}

							if ( isset( $results ) && count( $results ) > 0 ) {

								$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $results[0]->start_date ) );

								// Validation for parent products page - Grouped Products  , Here $item_array come Array when order place from the Parent page
								if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {

									$item_quantity[ $post_id ] = $item_quantity[ $post_id ] * $total_person;
									if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $item_quantity[ $post_id ] ) {

										$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
										$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $results[0]->available_booking, $date_to_display ), $msg_text );
										if ( ! wc_has_notice( $message, 'error' ) ) {
											wc_add_notice( $message, $notice_type = 'error' );
										}
										$quantity_check_pass                  = 'no';

									} elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 ) {

										$msg_text                             = __( get_option( 'book_no-booking-msg-date' ), 'woocommerce-booking' );
										$message                              = str_replace( array( 'PRODUCT_NAME', 'DATE' ), array( $product_name, $date_to_display ), $msg_text );
										if ( ! wc_has_notice( $message, 'error' ) ) {
											wc_add_notice( $message, $notice_type = 'error' );
										}
										$quantity_check_pass                  = 'no';
									}
								} else {
									$item_quantity = $item_quantity * $total_person;
									if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $item_quantity ) {

										$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
										$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $results[0]->available_booking, $date_to_display ), $msg_text );
										if ( ! wc_has_notice( $message, 'error' ) ) {
											wc_add_notice( $message, $notice_type = 'error' );
										}
										$quantity_check_pass                  = 'no';

									} elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 ) {

										$msg_text                             = __( get_option( 'book_no-booking-msg-date' ), 'woocommerce-booking' );
										$message                              = str_replace( array( 'PRODUCT_NAME', 'DATE' ), array( $product_name, $date_to_display ), $msg_text );
										if ( ! wc_has_notice( $message, 'error' ) ) {
											wc_add_notice( $message, $notice_type = 'error' );
										}
										$quantity_check_pass                  = 'no';
									}
								}
							}
							
							if ( 'yes' === $quantity_check_pass ) {

								$total_quantity = 0;
								foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {

									if ( array_key_exists( 'bkap_booking', $values ) ) {
										$booking = $values['bkap_booking'];
									} else {
										$booking = array();
									}

									$cart_total_person = 1;
									if ( isset( $booking[0][ 'persons' ] ) ) {
										if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
											$cart_total_person = array_sum( $booking[0][ 'persons' ] );	
										}
									}

									$quantity   = $values['quantity'] * $cart_total_person;
									$product_id = $values['product_id'];

									if ( $product_id == $post_id && isset( $booking[0]['hidden_date'] ) && $date_check && $booking[0]['hidden_date'] == $date_check ) {

										//if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {
											//$item_quantity[ $post_id ] = $item_quantity[ $post_id ] * $total_person;
											$total_quantity += /* $item_quantity[ $post_id ] + */ $quantity;
										//} else {
											/* $item_quantity  = ( $added ) ? 0 : $item_quantity * $total_person; */
											//$total_quantity += /* $item_quantity + */ $quantity;
										//}
									}
								}

								if ( isset( $results ) && count( $results ) > 0 ) {

									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $results[0]->start_date ) );
									
									if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {
										$total_quantity = $total_quantity + $item_quantity[ $post_id ];
										if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $total_quantity ) {
											$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
											$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $results[0]->available_booking, $date_to_display ), $msg_text );
											if ( ! wc_has_notice( $message, 'error' ) ) {
												wc_add_notice( $message, $notice_type = 'error' );
											}
											$quantity_check_pass                  = 'no';
										}
									} else {
										$total_quantity = $total_quantity + $item_quantity;
										if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $total_quantity ) {
											$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
											$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $results[0]->available_booking, $date_to_display ), $msg_text );
											if ( ! wc_has_notice( $message, 'error' ) ) {
												wc_add_notice( $message, $notice_type = 'error' );
											}
											$quantity_check_pass                  = 'no';
										}
									}
								}
							}
						}
					} else {
						$quantity_check_pass = $_POST['quantity_check_pass'];
					}
					break;
				case 'multiple_days':
					do_action( 'bkap_multiple_days_product_validation' );

					if ( ! isset( $_POST['validated'] ) || ( isset( $_POST['validated'] ) && $_POST['validated'] == 'NO' ) ) {

						$date_checkout = date( 'd-n-Y', strtotime( $_POST['wapbk_hidden_date_checkout'] ) );
						$date_cheeckin = date( 'd-n-Y', strtotime( $_POST['wapbk_hidden_date'] ) );
						$order_dates   = bkap_common::bkap_get_betweendays( $date_cheeckin, $date_checkout );
						$todays_date   = date( 'Y-m-d' );

						$query_date = "SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM " . $wpdb->prefix . "booking_history
									WHERE start_date >='" . $todays_date . "' AND post_id = '" . $post_id . "'";

						$results_date = $wpdb->get_results( $query_date );

						$dates_new = array();

						foreach ( $results_date as $k => $v ) {
							$start_date = $v->start_date;
							$end_date   = $v->end_date;
							$dates      = bkap_common::bkap_get_betweendays( $start_date, $end_date );
							$dates_new  = array_merge( $dates, $dates_new );
						}
						$dates_new_arr = array_count_values( $dates_new );

						$main_lockout = bkap_get_maximum_booking( $post_id, $booking_settings );

						if ( isset( $_POST['quantity'] ) && is_array( $_POST['quantity'] ) ) {
							$item_quantity = $_POST['quantity'][ $post_id ];
						} else {

							if ( isset( $_POST['quantity'] ) ) {
								$item_quantity = (int) $_POST['quantity'];
							} else {
								$item_quantity = 1;
							}
						}

						if ( isset( $bundle_child_qty ) && $bundle_child_qty > 0 ) {
							$item_quantity = $item_quantity * $bundle_child_qty;
						}

						$date_availablity = array();

						if ( $main_lockout > 0 ) {
							foreach ( $order_dates as $k => $v ) {

								$lockout                = bkap_get_specific_date_maximum_booking( $main_lockout, $v, $post_id, $booking_settings );
								$date_availablity[ $v ] = $lockout;

								if ( array_key_exists( $v, $dates_new_arr ) ) {

									if ( $lockout != 0 && $lockout < $dates_new_arr[ $v ] + ( $item_quantity * $total_person ) ) {
										$available_tickets      = $lockout - $dates_new_arr[ $v ];
										$date_availablity[ $v ] = $available_tickets;
										$quantity_check_pass    = 'no';
									}
								} else {

									if ( $lockout != 0 && $lockout < ( $item_quantity * $total_person ) ) {
										$available_tickets      = $lockout;
										$date_availablity[ $v ] = $available_tickets;
										$quantity_check_pass    = 'no';
									}
								}
							}

							if ( $quantity_check_pass == 'no' ) {

								if ( is_array( $date_availablity ) && count( $date_availablity ) > 0 ) {
									$least_availability = '';
									// find the least availability
									foreach ( $date_availablity as $date => $available ) {
										if ( '' == $least_availability ) {
											$least_availability = $available;
										}

										if ( $least_availability > $available ) {
											$least_availability = $available;
										}
									}
									// setup the dates to be displayed
									$check_in_to_display  = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date'] ) );
									$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date_checkout'] ) );
									$date_range           = "$check_in_to_display to $check_out_to_display";

									$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $least_availability, $date_range ), $msg_text );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
								}
							}
						}

						// check if the same product has been added to the cart for the same dates
						if ( $quantity_check_pass == 'yes' ) {

							$date_availablity = array();
							$quantity         = 0;
							foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {

								if ( isset( $values['bkap_booking'] ) ) {
									$booking = $values['bkap_booking'];
								}

								$product_id = $values['product_id'];

								$cart_total_person = 1;
								if ( isset( $booking[0][ 'persons' ] ) ) {
									if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
										$cart_total_person = array_sum( $booking[0][ 'persons' ] );
									}
								}

								if ( isset( $booking[0]['hidden_date'] ) && isset( $booking[0]['hidden_date_checkout'] ) ) {
									$hidden_date          = date( 'd-n-Y', strtotime( $booking[0]['hidden_date'] ) );
									$hidden_date_checkout = date( 'd-n-Y', strtotime( $booking[0]['hidden_date_checkout'] ) );
									$dates                = bkap_common::bkap_get_betweendays( $hidden_date, $hidden_date_checkout );
								}

								if ( $product_id == $post_id ) {
									$quantity += ( $values['quantity'] * $cart_total_person );

									if ( $main_lockout > 0 ) {

										foreach ( $order_dates as $k => $v ) {

											$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $v ) );

											$lockout = bkap_get_specific_date_maximum_booking( $main_lockout, $v, $post_id, $booking_settings );

											if ( array_key_exists( $v, $dates_new_arr ) ) {

												if ( isset( $date_availablity[ $v ] ) ) {
													$date_availablity[ $v ] += $item_quantity * $total_person;
												} else {
													$date_availablity[ $v ] = $dates_new_arr[ $v ] + ( $item_quantity * $total_person );
												}

												if ( in_array( $v, $dates ) ) {

													if ( $lockout != 0 && $lockout < $date_availablity[ $v ] + $quantity ) {
														$available_tickets      = $lockout - $dates_new_arr[ $v ];
														$date_availablity[ $v ] = $available_tickets;
														$quantity_check_pass    = 'no';
													}
												} else {

													if ( $lockout != 0 && $lockout < $date_availablity[ $v ] ) {
														$available_tickets      = $lockout - $dates_new_arr[ $v ];
														$date_availablity[ $v ] = $available_tickets;
														$quantity_check_pass    = 'no';
													}
												}
											} else {

												if ( isset( $date_availablity[ $v ] ) ) {
													$date_availablity[ $v ] += ( $values['quantity'] * $cart_total_person );
												} else {
													$date_availablity[ $v ] = ( $values['quantity'] * $cart_total_person );
												}

												if ( in_array( $v, $dates ) ) {
													if ( $lockout != 0 && $lockout < ( $quantity + ( $item_quantity * $total_person ) ) ) {
														$available_tickets      = $lockout;
														$date_availablity[ $v ] = $available_tickets;
														$quantity_check_pass    = 'no';
													}
												} else {
													if ( $lockout != 0 && $lockout < ( $item_quantity * $total_person ) ) {
														$available_tickets      = $lockout;
														$date_availablity[ $v ] = $available_tickets;
														$quantity_check_pass    = 'no';
													}
												}
											}
										}

										if ( $quantity_check_pass == 'no' ) {

											if ( is_array( $date_availablity ) && count( $date_availablity ) > 0 ) {
												$least_availability = '';
												// find the least availability
												foreach ( $date_availablity as $date => $available ) {
													if ( '' == $least_availability ) {
														$least_availability = $available;
													}

													if ( $least_availability > $available ) {
														$least_availability = $available;
													}
												}

												$least_availability = $lockout - $quantity;
												// setup the dates to be displayed
												$check_in_to_display  = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date'] ) );
												$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date_checkout'] ) );
												$date_range           = "$check_in_to_display to $check_out_to_display";

												$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
												$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $product_name, $least_availability, $date_range ), $msg_text );
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
											}
										}
									}
								}
							}
						}
					} else {
						$quantity_check_pass = $_POST['quantity_check_pass'];
					}
					break;
				case 'date_time':
				case 'multidates_fixedtime':
					$type_of_slot = apply_filters( 'bkap_slot_type', $post_id );

					if ( $type_of_slot == 'multiple' && ! isset( $_POST[ 'bkap_multidate_data' ] ) ) {
						$quantity_check_pass = apply_filters( 'bkap_validate_add_to_cart', $_POST, $post_id );
					} else {

						do_action( 'bkap_date_time_product_validation' );

						if ( ! isset( $_POST['validated'] ) || ( isset( $_POST['validated'] ) && $_POST['validated'] == 'NO' ) ) {

							$item_quantity = isset( $_POST['quantity'] ) ? $_POST['quantity'] : 1;

							if ( isset( $bundle_child_qty ) && $bundle_child_qty > 0 ) {
								$item_quantity = $item_quantity * $bundle_child_qty;
							}

							foreach ( $date_checks as $date_check_key => $date_check ) { // loop through each date.
							// starts from here
								$date_check_ymd = date( 'Y-m-d', strtotime( $date_check ) );

								if ( isset( $time_slots[ $date_check_key ] ) ) {
									$time_range = explode( '-', $time_slots[ $date_check_key ] );
									$from_time  = bkap_date_as_format( $time_range[0], 'H:i' );
									$to_time    = isset( $time_range[1] ) ? bkap_date_as_format( $time_range[1], 'H:i' ) : '';

									if ( $timezone_check ) {
										$offset            = bkap_get_offset( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' ) );
										$site_timezone     = bkap_booking_get_timezone_string();
										$customer_timezone = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );

										$db_from_time   = bkap_convert_date_from_timezone_to_timezone( $date_check_ymd . ' ' . $time_range[0], $customer_timezone, $site_timezone, 'H:i' );
										$db_to_time     = bkap_convert_date_from_timezone_to_timezone( $date_check_ymd . ' ' . $time_range[1], $customer_timezone, $site_timezone, 'H:i' );
										$date_check_ymd = bkap_convert_date_from_timezone_to_timezone( $date_check_ymd . ' ' . $time_range[0], $customer_timezone, $site_timezone, 'Y-m-d' );

										// Converting booking date to store timezone for getting correct availability.
									} else {
										$db_from_time = bkap_date_as_format( $time_range[0], 'H:i' );
										$db_to_time   = ( $to_time != '' ) ? bkap_date_as_format( $time_range[1], 'H:i' ) : '';
									}
								} else {
									$to_time   = '';
									$from_time = '';
								}

								$overlapping = bkap_booking_overlapping_timeslot( $global_settings, $post_id );

								if ( $overlapping ) {
									// overlapping is still pending for timezone feature.
									$overlapping_quantity_check_pass = self::bkap_validate_overlap_time_cart( $post_id, $_POST, $date_check_ymd, $from_time, $to_time );

									if ( ! empty( $overlapping_quantity_check_pass ) ) {

										$overlap_timeslot = $overlapping_quantity_check_pass['pass_fail_timeslot'];
										$overlap_time     = explode( '-', $overlap_timeslot );

										if ( $time_format === '12' ) {
											$overlap_start_time = date( 'h:i A', strtotime( $overlap_time[0] ) );
											$overlap_end_time   = date( 'h:i A', strtotime( $overlap_time[1] ) );
										} else {
											$overlap_start_time = date( 'H:i', strtotime( $overlap_time[0] ) );
											$overlap_end_time   = date( 'H:i', strtotime( $overlap_time[0] ) );
										}

										$overlap_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $overlapping_quantity_check_pass['pass_fail_date'] ) );

										$original_timeslot = $time_slots[ $date_check_key ];
										$message           = __( "$product_name cannot be booked for $overlap_date_to_display, $original_timeslot as booking has already been added to the cart for $overlap_date_to_display, $overlap_start_time - $overlap_end_time. In case if you wish to book for <$overlap_start_time - $original_timeslot please remove the existing product from the cart and add it or edit the booking details.", 'woocommerce-booking' );

										if ( ! wc_has_notice( $message, 'error' ) ) {
											wc_add_notice( $message, $notice_type = 'error' );
										}
										$quantity_check_pass                  = 'no';
									}
								}

								if ( isset( $global_settings->booking_global_timeslot ) && 'on' === $global_settings->booking_global_timeslot ) {
									$item_quantity = self::bkap_validate_global_time_cart( $post_id, $_POST, $item_quantity );
								}

								if ( $to_time != '' ) {
									$query   = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
												WHERE post_id = %d
												AND start_date = %s
												AND from_time = %s
												AND to_time = %s
												AND status !=  'inactive'";
									$results = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $date_check_ymd, $db_from_time, $db_to_time ) );

									if ( isset( $results ) && count( $results ) == 0 ) {
										$db_from_time = date( 'H:i', strtotime( $db_from_time ) );
										$db_to_time   = date( 'H:i', strtotime( $db_to_time ) );
										$results      = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $date_check_ymd, $db_from_time, $db_to_time ) );
									}

									if ( isset( $results ) && count( $results ) == 0 ) {

										$weekday         = date( 'w', strtotime( $date_check_ymd ) );
										$booking_weekday = "booking_weekday_$weekday";

										$query   = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
													WHERE post_id = %d
													AND weekday = %s
													AND from_time = %s
													AND to_time = %s
													AND status !=  'inactive'";
										$results = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $booking_weekday, $db_from_time, $db_to_time ) );
									}
								} else {
									$query = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
													WHERE post_id = %d
													AND start_date = %s
													AND from_time = %s
													AND status !=  'inactive'";

									$prepare_query = $wpdb->prepare( $query, $post_id, $date_check_ymd, $db_from_time );
									$results       = $wpdb->get_results( $prepare_query );

									if ( isset( $results ) && count( $results ) == 0 ) {
										$f_t     = date( 'H:i', strtotime( $db_from_time ) );
										$results = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $date_check_ymd, $f_t ) );
									}

									if ( isset( $results ) && count( $results ) == 0 ) { // If date record not found then search for base record

										$weekday         = date( 'w', strtotime( $date_check_ymd ) );
										$booking_weekday = "booking_weekday_$weekday";

										$query   = "SELECT total_booking, available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
													WHERE post_id = %d
													AND weekday = %s
													AND ( from_time = %s
													OR from_time = %s )
													AND status !=  'inactive'";
										$results = $wpdb->get_results( $wpdb->prepare( $query, $post_id, $booking_weekday, $db_from_time, $f_t ) );
									}
								}

								if ( isset( $results ) && count( $results ) > 0 ) {

									if ( isset( $time_slots[ $date_check_key ] ) && $time_slots[ $date_check_key ] != '' ) {

										if ( $time_format == '12' ) {
											$time_exploded        = explode( '-', $time_slots[ $date_check_key ] );
											$from_time            = date( 'h:i A', strtotime( $time_exploded[0] ) );
											$to_time              = isset( $time_exploded[1] ) ? date( 'h:i A', strtotime( $time_exploded[1] ) ) : '';
											$time_slot_to_display = ( $to_time != '' ) ? $from_time . ' - ' . $to_time : $from_time;
										} else {
											$time_slot_to_display = ( $to_time != '' ) ? $from_time . ' - ' . $to_time : $from_time;
										}

										// $date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $date_check ) );
										$date_to_display = $booking_dates[ $date_check_key ];
										if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {

											$item_quantity[ $post_id ] = $item_quantity[ $post_id ] * $total_person;

											if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $item_quantity[ $post_id ] ) {

												$msg_text                                 = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
												$message                                  = str_replace(
													array(
														'PRODUCT_NAME',
														'AVAILABLE_SPOTS',
														'DATE',
														'TIME',
													),
													array(
														$product_name,
														$results[0]->available_booking,
														$date_to_display,
														$time_slot_to_display,
													),
													$msg_text
												);
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												$quantity_check_pass                  = 'no';
											} elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 ) {

												$msg_text                             = __( get_option( 'book_no-booking-msg-time' ), 'woocommerce-booking' );
												$message                              = str_replace(
													array(
														'PRODUCT_NAME',
														'DATE',
														'TIME',
													),
													array(
														$product_name,
														$date_to_display,
														$time_slot_to_display,
													),
													$msg_text
												);
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												$quantity_check_pass                  = 'no';
											}
										} else {
											$item_quantity = $item_quantity * $total_person;

											if ( $results[0]->available_booking > 0 && $results[0]->available_booking < $item_quantity ) {

												$msg_text                                 = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
												$message                                  = str_replace(
													array(
														'PRODUCT_NAME',
														'AVAILABLE_SPOTS',
														'DATE',
														'TIME',
													),
													array(
														$product_name,
														$results[0]->available_booking,
														$date_to_display,
														$time_slot_to_display,
													),
													$msg_text
												);
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												$quantity_check_pass = 'no';
											} elseif ( $results[0]->total_booking > 0 && $results[0]->available_booking == 0 ) {

												$msg_text                             = __( get_option( 'book_no-booking-msg-time' ), 'woocommerce-booking' );
												$message                              = str_replace(
													array(
														'PRODUCT_NAME',
														'DATE',
														'TIME',
													),
													array(
														$product_name,
														$date_to_display,
														$time_slot_to_display,
													),
													$msg_text
												);
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												$quantity_check_pass                  = 'no';
											}
										}
									}
								} else {
									$message                              = __( 'This product cannot be added to cart. Please contact Store Manager for further information', 'woocommerce-booking' );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
									$quantity_check_pass                  = 'no';
								}

								if ( $quantity_check_pass == 'yes' ) { // check if the same product has been added to the cart for the same dates
									$total_quantity = 0;
									foreach ( $woocommerce->cart->get_cart() as $cart_item_key => $values ) {
										if ( isset( $values['bkap_booking'] ) ) {
											$booking    = $values['bkap_booking'];
											$quantity   = $values['quantity'];
											$product_id = $values['product_id'];

											$cart_total_person = 1;
											if ( isset( $booking[0][ 'persons' ] ) ) {
												if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
													$cart_total_person = array_sum( $booking[0][ 'persons' ] );
													$quantity *= $cart_total_person;
												}
											}

											if ( isset( $booking ) && count( $booking ) > 0 ) {

												if ( $product_id == $post_id
													&& $booking[0]['hidden_date'] == $date_check
													&& isset( $booking[0]['time_slot'] )
													&& isset( $time_slots[ $date_check_key ] )
													&& ( $booking[0]['time_slot'] == $time_slots[ $date_check_key ] )
													) {

													//if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {
														$total_quantity += /* $item_quantity[ $post_id ] + */ $quantity;
													//} else {
														//$total_quantity += /* $item_quantity +  */$quantity;
													//}
												}
											}
										}
									}

									if ( isset( $results ) && count( $results ) > 0 ) {

										if ( isset( $parent_id ) && $parent_id != '' && is_array( $item_quantity ) ) {
											$total_quantity += $item_quantity[ $post_id ];
										} else {
											$total_quantity += $item_quantity;
										}

										if ( $results[0]->available_booking > 0
										&& $results[0]->available_booking < $total_quantity
										) {

											$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
											$message                              = str_replace(
												array(
													'PRODUCT_NAME',
													'AVAILABLE_SPOTS',
													'DATE',
													'TIME',
												),
												array(
													$product_name,
													$results[0]->available_booking,
													$date_to_display,
													$time_slot_to_display,
												),
												$msg_text
											);
											if ( ! wc_has_notice( $message, 'error' ) ) {
												wc_add_notice( $message, $notice_type = 'error' );
											}
											$quantity_check_pass                  = 'no';
										}
									}
								}
							} // foreach ends..
						} else {
							$quantity_check_pass = $_POST['quantity_check_pass'];
						}
					}
					break;
				case 'duration_time':

					$duration_date     = $_POST['wapbk_hidden_date'];
					$duration_time     = $_POST['duration_time_slot'];
					$time_display      = bkap_common::bkap_get_formated_time( $duration_time, $global_settings );
					$selected_duration = $_POST['bkap_duration_field']; // Entered value on front end : 1
					$resource_id       = isset( $bkap_booking['resource_id'] ) ? $bkap_booking['resource_id'] : 0; // Id of selected Resource

					// Duration Settings
					$d_setting     = get_post_meta( $post_id, '_bkap_duration_settings', true );
					$d_max_booking = $d_setting['duration_max_booking'];
					$base_interval = (int) $d_setting['duration']; // 2 Hour set for product
					$duration_type = $d_setting['duration_type']; // Type of Duration set for product Hours/mins

					$from     = strtotime( $duration_date . ' ' . $duration_time );
					$interval = (int) $selected_duration; // Total hours/mins based on selected duration and set duration : 2

					if ( 'hours' === $duration_type ) {
						$interval      = $interval * 3600;
						$base_interval = $base_interval * 3600;
					} else {
						$interval      = $interval * 60;
						$base_interval = $base_interval * 60;
					}

					$to     = $from + $interval;
					$blocks = array( $from );

					$duration_booked_blocks = Bkap_Duration_Time::bkap_display_availabile_blocks_html( $post_id, $blocks, $from, $to, array( $interval, $base_interval ), $resource_id, $duration_type, 'backend' );

					if ( isset( $bundle_child_qty ) && $bundle_child_qty > 0 ) { // Validating duration when using Bundles.
						$qty_check = $bundle_child_qty;

						foreach ( $woocommerce->cart->get_cart() as $cart_check_key => $cart_check_value ) {

							if ( $post_id == $cart_check_value['product_id']
							&& $_POST['wapbk_hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
							&& $_POST['duration_time_slot'] == $cart_check_value['bkap_booking'][0]['duration_time_slot'] ) {

								$qty_check = $qty_check + $cart_check_value['quantity'];
								break;
							}
						}

						if ( ! empty( $duration_booked_blocks['duration_booked'] ) ) {

							if ( $duration_booked_blocks['duration_booked'][ $from ] < $qty_check ) {

								$available_duration = $duration_booked_blocks['duration_booked'][ $from ];

								if ( $available_duration == '0' ) {
									$msg_text                             = __( get_option( 'book_no-booking-msg-time' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'DATE', 'TIME' ), array( $product_name, $date_to_display, $time_display ), $msg_text );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
								} else {
									$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $product_name, $available_duration, $date_to_display, $time_display ), $msg_text );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
								}

								$quantity_check_pass = 'no';
							}
						}
					} else {
						$item_quantity = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 1;
						$item_quantity = $item_quantity * $total_person;
						if ( ! empty( $duration_booked_blocks['duration_booked'] ) ) {
	
							if ( $duration_booked_blocks['duration_booked'][ $from ] < $item_quantity ) {
	
								$available_duration = $duration_booked_blocks['duration_booked'][ $from ];
	
								if ( $available_duration == '0' ) {
									$msg_text                             = __( get_option( 'book_no-booking-msg-time' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'DATE', 'TIME' ), array( $product_name, $date_to_display, $time_display ), $msg_text );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
								} else {
									$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $product_name, $available_duration, $date_to_display, $time_display ), $msg_text );
									if ( ! wc_has_notice( $message, 'error' ) ) {
										wc_add_notice( $message, $notice_type = 'error' );
									}
								}
	
								$quantity_check_pass = 'no';
							}
						} /* elseif ( $d_max_booking != '' && $d_max_booking != 0 ) {
							
							if ( ( $item_quantity * $total_person ) > ( $d_max_booking - $qty_check ) ) {
								if ( $consider_person_qty ) {
									$not_available = sprintf( __( 'There are a maximum of %s places remaining.', 'woocommerce-booking' ), ( $d_max_booking - $qty_check ) );
								} else {
									$not_available = __( 'Booking is not available for selected quantity', 'woocommerce-booking' );
								}
								$not_available = bkap_woocommerce_error_div( $not_available );
								wp_send_json( array( 'message' => $not_available, 'error' => true ) );
							}
						} */
					}
					break;
			}
			return $quantity_check_pass;
		}

		/**
		 * This function checks an order was already created, this means the validation has been run once already.
		 *
		 * @since 4.10.0
		 *
		 * @global object $wpdb Global wpdb Object
		 * @return bool $status true if order is created else false.
		 */

		public static function bkap_order_created_check() {
			global $wpdb;

			$status   = false;
			$order_id = absint( WC()->session->order_awaiting_payment ); // Get the order ID if an order is already pending

			if ( $order_id > 0 && ( $order = wc_get_order( $order_id ) ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
				// Confirm if data is found in the order history table for the given order, then we need to skip the validation
				$check_data    = 'SELECT * FROM `' . $wpdb->prefix . 'booking_order_history`
                                WHERE order_id = %s';
				$results_check = $wpdb->get_results( $wpdb->prepare( $check_data, $order_id ) );

				if ( count( $results_check ) > 0 ) {
					$status = true;
				}
			}
			return $status;
		}

		/**
		 * This function checks availability for date and time slot on the cart page when quantity on cart page is changed.
		 *
		 * @since 2.0
		 * @hook woocommerce_before_checkout_process
		 * @global object $wpdb Global wpdb Object
		 * @global object $woocommerce Global WooCommerce Object
		 * @global array $bkap_date_formats Array of Date Format
		 */

		public static function bkap_cart_checkout_quantity_check() {
			global $wpdb, $bkap_date_formats;

			// check if the order is already created.
			if ( self::bkap_order_created_check() ) {
				return;
			}

			$wc_cart_object = WC();
			if ( count( $wc_cart_object->cart->cart_contents ) > 0 ) {

				$availability_display = array();
				$global_settings      = bkap_global_setting();
				foreach ( $wc_cart_object->cart->cart_contents as $key => $value ) {

					if ( isset( $value['bkap_booking'][0]['hidden_date'] ) ) {
						$bkap_bookings = $value['bkap_booking'];
					} else {
						continue;
					}

					foreach ( $bkap_bookings as $b => $bkap_booking ) { // Multidates mate nu changes.
						$date_check = bkap_date_as_format( $bkap_booking['hidden_date'], 'Y-m-d' );

						$date_availablity = array();
						$duplicate_of     = bkap_common::bkap_get_product_id( $value['product_id'] );
						$booking_settings = bkap_setting( $duplicate_of );
						$booking_type     = get_post_meta( $duplicate_of, '_bkap_booking_type', true );
						$post_title       = get_post( $value['product_id'] );

						$date_checkout = isset( $bkap_booking['hidden_date_checkout'] ) ? bkap_date_as_format( $bkap_booking['hidden_date_checkout'], 'd-n-Y' ) : '';

						$time_format            = $global_settings->booking_time_format;
						$date_format_to_display = $global_settings->booking_date_format;

						if ( isset( $bkap_booking['date'] ) ) {
							$date_to_display = $bkap_booking['date'];
						} else {
							$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $date_check ) );
						}

						if ( isset( $bkap_booking['date_checkout'] ) ) {
							$check_out_to_display = $bkap_booking['date_checkout'];
						} else {
							$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $date_checkout ) );
						}

						$variation_id = isset( $value['variation_id'] ) ? $value['variation_id'] : '';

						/* Persons Calculations */
						$total_person = 1;
						$selected_persons_total = 1;
						if ( isset( $bkap_booking[ 'persons' ] ) ) {
							$selected_persons_total = array_sum( $bkap_booking[ 'persons' ] );
							if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
								$total_person = $selected_persons_total;	
							}
						}

						// Validating persons.
						if ( isset( $bkap_booking['persons'] ) && count( $wc_cart_object->cart->cart_contents ) == 1 ) {

							$bkap_persons = array();
							if ( isset( $bkap_booking['persons'][0] ) ) {
								$bkap_persons[] = array( 'person_id' => 1, 'person_val' => $bkap_booking['persons'][0] );
							} else {
								foreach ($bkap_booking[ 'persons' ] as $pkey => $pvalue) {
									$bkap_persons[] = array( 'person_id' => $pkey, 'person_val' => $pvalue );
								}
							}

							$message = bkap_validate_person_selection( $bkap_persons, $selected_persons_total, $booking_settings, $duplicate_of );
							if ( '' !== $message ) {
								$message .= ' for ' . $post_title->post_title;
								if ( ! wc_has_notice( $message, 'error' ) ) {
									wc_add_notice( $message, $notice_type = 'error' );
								}
								bkap_remove_proceed_to_checkout();
							}
						}

						// save the data in $_POST, so it can be accessed in hooks and does not need to be passed each time
						$_POST['product_id']    = $duplicate_of;
						$_POST['variation_id']  = $variation_id;
						$_POST['booking_date']  = $date_check;
						$_POST['quantity']      = ( $value['quantity'] * $total_person );
						$_POST['cart_item_key'] = $key;

						/* Resource checking start */
						if ( isset( $bkap_booking['resource_id'] ) && $bkap_booking['resource_id'] > 0 ) {

							$resource_id                = $bkap_booking['resource_id'];
							$bkap_resource_availability = bkap_resource_max_booking( $resource_id, $date_check, $duplicate_of, $booking_settings );
							$resource_qty               = 0;
							$resource_booking_available = 0;

							if ( 0 != $bkap_resource_availability ) {

								$resource_booking_data      = Class_Bkap_Product_Resource::print_hidden_resource_data( array(), $booking_settings, $duplicate_of );
								$resource_booked_for_date   = 0;
								$selected_date              = $bkap_booking['hidden_date'];

								if ( isset( $bkap_booking['time_slot'] ) && '' != $bkap_booking['time_slot'] ) {
									$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_date_time_array'];
									if ( isset( $resource_bookings_placed[ $selected_date ] ) ) {
										if ( isset( $resource_bookings_placed[ $selected_date ][ $bkap_booking['time_slot'] ] ) ) {
											$resource_booked_for_date = $resource_bookings_placed[ $selected_date ];
										}
									}
								} elseif ( isset( $bkap_booking['duration_time_slot'] ) && $bkap_booking['duration_time_slot'] != '' ) {

									$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_date_time_array'];

									$duration_time         = $bkap_booking['duration_time_slot'];
									$duration_time_str     = strtotime( $duration_time );
									$bkap_duration_field   = explode( '-', $bkap_booking['selected_duration'] );
									$duration_end_time     = date( 'H:i', strtotime( "+$bkap_duration_field[0] hour", $duration_time_str ) );
									$duration_end_time_str = strtotime( $duration_end_time );

									if ( array_key_exists( $selected_date, $resource_bookings_placed ) ) {

										foreach ( $resource_bookings_placed[ $selected_date ] as $dkey => $dvalue ) {

											$explode = explode( ' - ', $dkey );

											if ( $duration_time_str >= strtotime( $explode[0] ) && $duration_end_time_str < strtotime( $explode[1] ) ) {
												// inside
												$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];

											} elseif ( $duration_time_str >= strtotime( $explode[0] ) && $duration_time_str < strtotime( $explode[1] ) ) {
												// inside start time range
												$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
											} elseif ( $duration_time_str < strtotime( $explode[0] ) && $duration_end_time_str > strtotime( $explode[1] ) ) {
												// out side
												$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
											} elseif ( $duration_end_time_str > strtotime( $explode[0] ) && $duration_end_time_str < strtotime( $explode[1] ) ) {
												$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
											}
										}
									}
								} else {
									$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_booking_placed'];

									$resource_bookings_placed_list_dates = explode( ',', $resource_bookings_placed );
									$resource_date_array                 = array();

									foreach ( $resource_bookings_placed_list_dates as $list_key => $list_value ) {

										$explode_date = explode( '=>', $list_value );

										if ( isset( $explode_date[1] ) && $explode_date[1] != '' ) {
											$date                         = substr( $explode_date[0], 1, -1 );
											$resource_date_array[ $date ] = (int) $explode_date[1];
										}
									}

									if ( array_key_exists( $selected_date, $resource_date_array ) ) {
										$resource_booked_for_date = $resource_date_array[ $selected_date ];
									}
								}

								$resource_booking_available = intval( $bkap_resource_availability ) - intval( $resource_booked_for_date );

								foreach ( $wc_cart_object->cart->cart_contents as $cart_check_key => $cart_check_value ) {

									if ( isset( $cart_check_value['bkap_booking'][0]['resource_id'] ) ) {

										if ( $bkap_booking['resource_id'] == $cart_check_value['bkap_booking'][0]['resource_id'] ) {

											// Calculation for resource qty for product parent foreach product is single day.
											if ( ! isset( $bkap_booking['hidden_date_checkout'] ) ) {

												// here we have to do the magic.

												$booking_type_check = $booking_type;

												$hidden_date_str = $hidden_date_checkout_str = $val_hidden_date_str = '';
												$hidden_date_str = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date'] );

												if ( isset( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] ) && $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] != '' ) {
													$hidden_date_checkout_str = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] );
												}

												$val_hidden_date_str = strtotime( $bkap_booking['hidden_date'] );

												switch ( $booking_type_check ) {
													case 'date_time':
														if ( $hidden_date_checkout_str == '' ) {
															if ( $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {

																$time_check = false;
																if ( isset( $cart_check_value['bkap_booking'][0]['time_slot'] ) ) {
																	$time_check = true;
																	if ( $bkap_booking['time_slot'] == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
																		$resource_qty += $cart_check_value['quantity'];
																	}
																}
																if ( isset( $cart_check_value['bkap_booking'][0]['duration_time_slot'] ) ) {
																	$time_check = true;

																	$cartstart          = strtotime( $cart_check_value['bkap_booking'][0]['duration_time_slot'] );
																	$d_explode          = explode( '-', $cart_check_value['bkap_booking'][0]['selected_duration'] );
																	$d_explode_duration = $d_explode[0];
																	$cartend            = strtotime( date( 'H:i', strtotime( "+$d_explode_duration hour", $cartstart ) ) );

																	if ( $duration_time_str >= $cartstart && $duration_end_time_str < $cartend ) {
																		// inside
																		$resource_qty += $cart_check_value['quantity'];
																	} elseif ( $duration_time_str >= $cartstart && $duration_time_str < $cartend ) {
																		// inside start time range
																		$resource_qty += $cart_check_value['quantity'];
																	} elseif ( $duration_time_str < $cartstart && $duration_end_time_str > $cartend ) {
																		// out side
																		$resource_qty += $cart_check_value['quantity'];
																	} elseif ( $duration_end_time_str > $cartstart && $duration_end_time_str < $cartend ) {
																		$resource_qty += $cart_check_value['quantity'];
																	}
																}
																if ( ! $time_check ) {
																	$resource_qty += $cart_check_value['quantity'];
																}
															}
														} else {
															if ( $val_hidden_date_str >= $hidden_date_str && $val_hidden_date_str < $hidden_date_checkout_str ) {
																$resource_qty += $cart_check_value['quantity'];
															}
														}

														break;
													case 'duration_time':
														if ( $hidden_date_checkout_str == '' ) {
															if ( $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {

																$time_check = false;
																if ( isset( $cart_check_value['bkap_booking'][0]['time_slot'] ) ) {
																	$time_check = true;
																	if ( $bkap_booking['time_slot'] == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
																		$resource_qty += $cart_check_value['quantity'];
																	}
																}
																if ( isset( $cart_check_value['bkap_booking'][0]['duration_time_slot'] ) ) {
																	$time_check = true;

																	$cartstart          = strtotime( $cart_check_value['bkap_booking'][0]['duration_time_slot'] );
																	$d_explode          = explode( '-', $cart_check_value['bkap_booking'][0]['selected_duration'] );
																	$d_explode_duration = $d_explode[0];
																	$cartend            = strtotime( date( 'H:i', strtotime( "+$d_explode_duration hour", $cartstart ) ) );

																	if ( $duration_time_str >= $cartstart && $duration_end_time_str < $cartend ) {
																		// inside
																		$resource_qty += $cart_check_value['quantity'];

																	} elseif ( $duration_time_str >= $cartstart && $duration_time_str < $cartend ) {
																		// inside start time range
																		$resource_qty += $cart_check_value['quantity'];
																	} elseif ( $duration_time_str < $cartstart && $duration_end_time_str > $cartend ) {
																		// out side
																		$resource_qty += $cart_check_value['quantity'];
																	} elseif ( $duration_end_time_str > $cartstart && $duration_end_time_str < $cartend ) {
																		$resource_qty += $cart_check_value['quantity'];
																	}
																}
																if ( ! $time_check ) {
																	$resource_qty += $cart_check_value['quantity'];
																}
															}
														} else {
															if ( $val_hidden_date_str >= $hidden_date_str && $val_hidden_date_str < $hidden_date_checkout_str ) {
																$resource_qty += $cart_check_value['quantity'];
															}
														}

														break;
													default:
														if ( $hidden_date_checkout_str == '' ) {
															if ( $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {
																$resource_qty += $cart_check_value['quantity'];
															}
														} else {
															if ( $val_hidden_date_str >= $hidden_date_str && $val_hidden_date_str < $hidden_date_checkout_str ) {
																$resource_qty += $cart_check_value['quantity'];
															}
														}

														break;
												}
												// switch end here

											} else { // Calculation for resource qty for product parent foreach product is multiple nights.

												$hidden_date_str = $hidden_date_checkout_str = $cart_check_hidden_date_str = '';
												$hidden_date_str = strtotime( $bkap_booking['hidden_date'] );

												if ( isset( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] ) && $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] != '' ) {
													$hidden_date_checkout_str = strtotime( $bkap_booking['hidden_date_checkout'] );
												}

												$cart_check_hidden_date_str = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date'] );

												if ( $cart_check_hidden_date_str >= $hidden_date_str && $cart_check_hidden_date_str <= $hidden_date_checkout_str ) {
													$resource_qty += $cart_check_value['quantity'];
												}
											}
										}
									}
								}
							}

							if ( $resource_qty > $resource_booking_available ) {

								if ( isset( $bkap_booking['time_slot'] ) && '' != $bkap_booking['time_slot'] ) {
									$values_tobe_replaced = array(
										get_the_title( $resource_id ),
										$resource_booking_available,
										$date_to_display,
										$bkap_booking['time_slot'],
									);

									$message = bkap_str_replace( 'book_limited-booking-msg-time', $values_tobe_replaced );
								} else {
									$values_tobe_replaced = array(
										get_the_title( $resource_id ),
										$resource_booking_available,
										$date_to_display,
									);
									$message              = bkap_str_replace( 'book_limited-booking-msg-date', $values_tobe_replaced );
								}
								if ( ! wc_has_notice( $message, 'error' ) ) {
									wc_add_notice( $message, $notice_type = 'error' );
								}
								bkap_remove_proceed_to_checkout();
							}
							continue;
						}
						/* Resource checking end */

						switch ( $booking_type ) {

							case 'date_time':
							case 'multidates_fixedtime':
								$type_of_slot = apply_filters( 'bkap_slot_type', $duplicate_of );

								if ( $type_of_slot == 'multiple' ) {
									do_action( 'bkap_validate_cart_items', $value );
								} else {

									if ( isset( $bkap_booking['time_slot'] ) && $bkap_booking['time_slot'] != '' ) {

										$_POST['time_slot'] = $bkap_booking['time_slot'];

										do_action( 'bkap_date_time_cart_validation' );

										$validation_completed = isset( $_POST['validation_status'] ) ? $_POST['validation_status'] : '';

										$qty_check   = 0;
										$overlapping = bkap_booking_overlapping_timeslot( $global_settings, $duplicate_of );

										$main_timeslot = explode( ' - ', $bkap_booking['time_slot'] );
										$m_from        = strtotime( $main_timeslot[0] );

										if ( isset( $main_timeslot[1] ) ) {
											$m_to = strtotime( $main_timeslot[1] );

											foreach ( $wc_cart_object->cart->cart_contents as $cart_check_key => $cart_check_value ) {

												if ( $value['product_id'] == $cart_check_value['product_id']
												&& $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
												&& $key != $cart_check_key ) {
													if ( $bkap_booking['time_slot'] == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
														
														$cart_total_person = 1;
														if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
															if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
																$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
															}
														}

														$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );
														break;
													} else {
														// overlapping check.
														if ( $overlapping ) {
															$c_timeslot = explode( ' - ', $cart_check_value['bkap_booking'][0]['time_slot'] );
															$c_from     = strtotime( $c_timeslot[0] );
															$c_to       = strtotime( $c_timeslot[1] );

															if ( ( $m_from > $c_from && $m_from < $c_to ) || ( $m_to > $c_from && $m_to < $c_to ) || ( $m_from <= $c_from && $m_to >= $c_to ) ) {
																$cart_total_person = 1;
																if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
																	if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
																		$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
																	}
																}
																$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );
																break;
															}
														}
													}
												} else if (	isset( $bkap_booking['hidden_date'] )
												&& isset( $cart_check_value['bkap_booking'] )
												&& isset( $cart_check_value['bkap_booking'][0]['hidden_date'] )
												&& ( $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] )
												&& $key != $cart_check_key
												&& $bkap_booking['time_slot'] == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
													// Global check.
													if ( isset( $global_settings->booking_global_timeslot ) && 'on' === $global_settings->booking_global_timeslot  ) {
														$cart_total_person = 1;
														if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
															if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
																$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
															}
														}
														$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );
														break;
													}
												}
											}
										}

										$qty_check = ( $qty_check == 0 ) ? ( $value['quantity'] * $total_person ) : $qty_check;

										if ( $validation_completed == 'NO' ) {

											$time_range     = explode( '-', $bkap_booking['time_slot'] );
											$timezone_check = bkap_timezone_check( $global_settings );

											if ( $timezone_check ) {
												$offset            = bkap_get_offset( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' ) );
												$site_timezone     = bkap_booking_get_timezone_string();
												$customer_timezone = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );

												$db_from_time   = bkap_convert_date_from_timezone_to_timezone( $date_check . ' ' . $time_range[0], $customer_timezone, $site_timezone, 'H:i' );
												$db_to_time     = isset( $time_range[1] ) ?  bkap_convert_date_from_timezone_to_timezone( $date_check . ' ' . $time_range[1], $customer_timezone, $site_timezone, 'H:i' ) : '';
												$date_check     = bkap_convert_date_from_timezone_to_timezone( $date_check . ' ' . $time_range[0], $customer_timezone, $site_timezone, 'Y-m-d' );

												// Converting booking date to store timezone for getting correct availability.
											} else {
												$db_from_time = bkap_date_as_format( $time_range[0], 'H:i' );
												$db_to_time   = isset( $time_range[1] ) ? bkap_date_as_format( $time_range[1], 'H:i' ) : '';
											}

											$results = bkap_fetch_date_records( $duplicate_of, $date_check, $db_from_time, $db_to_time );

											if ( ! $results ) {
												break;
											} else {
												$time_slot_to_display = $bkap_booking['time_slot'];

												/* Person Calculations */
												$bookings_data = get_bookings_for_range( $value['product_id'], $date_check, strtotime( $date_check . ' 23:59'  ) );
												if ( count( $bookings_data ) > 0 ) {
													if ( isset( $bookings_data[ date( 'Ymd', strtotime( $date_check ) ) ] ) ) {
														$db_to_time = ( '' === $db_to_time ) ? '00:00' : $db_to_time;
														if ( isset( $bookings_data[ date( 'Ymd', strtotime( $date_check ) ) ][ $db_from_time . ' - ' . $db_to_time ] ) ) {
															$available = $bookings_data[ date( 'Ymd', strtotime( $date_check ) ) ][ $db_from_time . ' - ' . $db_to_time ];
															$available = $results[0]->total_booking - $available;
														}
													}
												}

												$available_booking = isset( $available ) ? $available : $results[0]->available_booking;

												if ( $available_booking > 0 && $available_booking < $qty_check ) {

													$values_tobe_replaced = array(
														$post_title->post_title,
														$available_booking,
														$date_to_display,
														$time_slot_to_display,
													);

													$message = bkap_str_replace( 'book_limited-booking-msg-time', $values_tobe_replaced );
													if ( ! wc_has_notice( $message, 'error' ) ) {
														wc_add_notice( $message, $notice_type = 'error' );
													}
													bkap_remove_proceed_to_checkout();
													break;

												} elseif ( $results[0]->total_booking > 0 && $available_booking == 0 ) {

													$values_tobe_replaced                 = array(
														$post_title->post_title,
														$date_to_display,
														$time_slot_to_display,
													);
													$message = bkap_str_replace( 'book_no-booking-msg-time', $values_tobe_replaced );
													if ( ! wc_has_notice( $message, 'error' ) ) {
														wc_add_notice( $message, $notice_type = 'error' );
													}
													bkap_remove_proceed_to_checkout();
													break;
												}
											}
										}
									}
								}

								break;
							case 'duration_time':
								if ( isset( $bkap_booking['duration_time_slot'] ) && $bkap_booking['duration_time_slot'] != '' ) {

									$_POST['duration_time_slot'] = $bkap_booking['duration_time_slot'];
									do_action( 'bkap_duration_time_cart_validation' );

									if ( isset( $_POST['validation_status'] ) ) {
										$validation_completed = $_POST['validation_status'];
									}

									$qty_check = 0;
									foreach ( $wc_cart_object->cart->cart_contents as $cart_check_key => $cart_check_value ) {

										if ( $value['product_id'] == $cart_check_value['product_id']
										&& $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
										&& $bkap_booking['duration_time_slot'] == $cart_check_value['bkap_booking'][0]['duration_time_slot']
										&& $key != $cart_check_key ) {
											$cart_total_person = 1;
											if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
												if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
													$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
												}
											}
											$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );
											break;
										}
									}

									$qty_check = ( $qty_check == 0 ) ? ( $value['quantity'] * $total_person ) : $qty_check;

									$duration_date     = $bkap_booking['hidden_date'];
									$duration_time     = $bkap_booking['duration_time_slot'];
									$time_display      = bkap_common::bkap_get_formated_time( $duration_time, $global_settings );
									$selected_duration = $bkap_booking['selected_duration']; // Entered value on front end : 1
									$resource_id       = isset( $bkap_booking['resource_id'] ) ? $bkap_booking['resource_id'] : 0; // Id of selected Resource

									// Duration Settings
									$d_setting     = get_post_meta( $duplicate_of, '_bkap_duration_settings', true );
									$d_max_booking = $d_setting['duration_max_booking'];
									$base_interval = (int) $d_setting['duration']; // 2 Hour set for product
									$duration_type = $d_setting['duration_type']; // Type of Duration set for product Hours/mins

									$from     = strtotime( $duration_date . ' ' . $duration_time );
									$interval = (int) $selected_duration; // Total hours/mins based on selected duration and set duration : 2

									if ( 'hours' === $duration_type ) {
										$interval      = $interval * 3600;
										$base_interval = $base_interval * 3600;
									} else {
										$interval      = $interval * 60;
										$base_interval = $base_interval * 60;
									}

									$to     = $from + $interval;
									$blocks = array( $from );

									$duration_booked_blocks = Bkap_Duration_Time::bkap_display_availabile_blocks_html( $duplicate_of, $blocks, $from, $to, array( $interval, $base_interval ), $resource_id, $duration_type, 'backend' );
									if ( ! empty( $duration_booked_blocks['duration_booked'] ) ) {

										if ( $duration_booked_blocks['duration_booked'][ $from ] < 0 ) {

											$available_duration = $duration_booked_blocks['duration_booked'][ $from ] + $qty_check;

											$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
											$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $post_title->post_title, $available_duration, $date_to_display, $time_display ), $msg_text );
											if ( ! wc_has_notice( $message, 'error' ) ) {
												wc_add_notice( $message, $notice_type = 'error' );
											}
										}
									}
								}

								break;
							case 'multiple_days':
								$date_cheeckin = isset( $bkap_booking['hidden_date'] ) ? bkap_date_as_format( $bkap_booking['hidden_date'], 'd-n-Y' ) : '';

								$_POST['booking_date']     = $date_cheeckin;
								$_POST['booking_checkout'] = $date_checkout;
								do_action( 'bkap_multiple_days_cart_validation' );

								$qty_check = 0;
								foreach ( $wc_cart_object->cart->cart_contents as $cart_check_key => $cart_check_value ) {

									if ( $value['product_id'] == $cart_check_value['product_id']
									&& isset( $cart_check_value['bkap_booking'] ) && $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
									&& $bkap_booking['hidden_date_checkout'] == $cart_check_value['bkap_booking'][0]['hidden_date_checkout']
									&& $key != $cart_check_key ) {

										$cart_total_person = 1;
										if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
											if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
												$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
											}
										}

										$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );
										break;
									}
								}

								$qty_check = ( $qty_check == 0 ) ? ( $value['quantity'] * $total_person ) : $qty_check;

								$validation_completed = isset( $_POST['validation_status'] ) ? $_POST['validation_status'] : '';

								if ( $validation_completed == 'NO' ) {
									$order_dates = bkap_common::bkap_get_betweendays( $date_cheeckin, $date_checkout );
									$todays_date = date( 'Y-m-d' );

									$query_date = "SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date FROM " . $wpdb->prefix . "booking_history
											WHERE start_date >='" . $todays_date . "' AND post_id = '" . $duplicate_of . "'";

									$results_date = $wpdb->get_results( $query_date );

									$dates_new = array();

									foreach ( $results_date as $k => $v ) {
										$start_date = $v->start_date;
										$end_date   = $v->end_date;
										$dates      = bkap_common::bkap_get_betweendays( $start_date, $end_date );
										$dates_new  = array_merge( $dates, $dates_new );
									}

									$dates_new_arr = array_count_values( $dates_new );
									$main_lockout  = bkap_get_maximum_booking( $duplicate_of, $booking_settings );

									$check = 'pass';
									if ( isset( $main_lockout ) && $main_lockout > 0 ) {
										foreach ( $order_dates as $k => $v ) {

											$lockout = bkap_get_specific_date_maximum_booking( $main_lockout, $v, $duplicate_of, $booking_settings );

											if ( ! isset( $date_availablity[ $v ] ) ) {
												$date_availablity[ $v ] = $lockout;
											}

											if ( array_key_exists( $v, $dates_new_arr ) ) {

												$date_availablity[ $v ] -= ( $dates_new_arr[ $v ] + $qty_check );

												if ( $lockout != 0 && $date_availablity[ $v ] < 0 ) {
													$date_availablity[ $v ]     = 0; // needs to be reset to 0, to ensure negative availability is not displayed to the user
													$availability_display[ $v ] = $lockout - $dates_new_arr[ $v ];
													$check                      = 'failed';
												}
											} else {
												$date_availablity[ $v ] -= $qty_check;
												if ( $lockout != 0 && $date_availablity[ $v ] < 0 ) {
													$date_availablity[ $v ]     = 0; // needs to be reset to 0, to ensure negative availability is not displayed to the user
													$availability_display[ $v ] = $lockout;
													$check                      = 'failed';
												}
											}
										}

										if ( isset( $check ) && 'failed' == $check ) {
											if ( is_array( $availability_display ) && count( $availability_display ) > 0 ) {
												$least_availability = '';
												// find the least availability
												foreach ( $availability_display as $date => $available ) {
													if ( '' == $least_availability && '0' != $least_availability ) {
														$least_availability = $available;
													}

													if ( $least_availability > $available ) {
														$least_availability = $available;
													}
												}

												$date_range                           = "$date_to_display to $check_out_to_display"; // setup the dates to be displayed
												$values_tobe_replaced                 = array( $post_title->post_title, $least_availability, $date_range );
												$message                              = bkap_str_replace( 'book_limited-booking-msg-date', $values_tobe_replaced );
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												bkap_remove_proceed_to_checkout();
											}
										}
									}
								}
								break;
							case 'only_day':
							case 'multidates':

								do_action( 'bkap_single_days_cart_validation' );
								$validation_completed = isset( $_POST['validation_status'] ) ? $_POST['validation_status'] : '';

								if ( $validation_completed == 'NO' ) {

									$lockout = get_date_lockout( $value['product_id'], $date_check );
									if ( 'unlimited' != $lockout ) {
										$query   = "SELECT total_booking,available_booking, start_date FROM `" . $wpdb->prefix . "booking_history`
												WHERE post_id = %d
												AND start_date = %s
												AND status != 'inactive' ";
										$results = $wpdb->get_results( $wpdb->prepare( $query, $duplicate_of, $date_check ) );

										$qty_check = 0;
										foreach ( $wc_cart_object->cart->cart_contents as $cart_check_key => $cart_check_value ) {

											if ( $value['product_id'] == $cart_check_value['product_id']
											&& isset( $cart_check_value['bkap_booking'] )
											&& $bkap_booking['hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date']
											&& $key != $cart_check_key ) {

												$cart_total_person = 1;
												if ( isset( $cart_check_value['bkap_booking'][0]['persons'] ) ) {
													if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
														$cart_total_person = array_sum( $cart_check_value['bkap_booking'][0]['persons'] );	
													}
												}

												$qty_check = ( $value['quantity'] * $total_person ) + ( $cart_check_value['quantity'] * $cart_total_person );

												break;
											}
										}
										$qty_check = ( $qty_check == 0 ) ? ( $value['quantity'] * $total_person ) : $qty_check;

										/* Person Calculations */
										$bookings_data = get_bookings_for_range( $value['product_id'], $date_check, strtotime( $date_check ) );

										if ( count( $bookings_data ) > 0 ) {
											if ( isset( $bookings_data[ date( 'Ymd', strtotime( $date_check ) ) ] ) ) {
												$available = $bookings_data[ date( 'Ymd', strtotime( $date_check ) ) ];
												$available = $lockout - $available;
											}
										}

										if ( ! $results ) {
											break;
										} else {
											$available_tickets = isset( $available ) ? $available : $results[0]->available_booking;
											if ( $available_tickets > 0 && $available_tickets < $qty_check ) {
												$values_tobe_replaced                 = array( $post_title->post_title, $available_tickets, $date_to_display );
												$message                              = bkap_str_replace( 'book_limited-booking-msg-date', $values_tobe_replaced );
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												bkap_remove_proceed_to_checkout();
											} elseif ( $results[0]->total_booking > 0 && $available_tickets == 0 ) {
												$values_tobe_replaced                 = array( $post_title->post_title, $date_to_display );
												$message                              = bkap_str_replace( 'book_no-booking-msg-date', $values_tobe_replaced );
												if ( ! wc_has_notice( $message, 'error' ) ) {
													wc_add_notice( $message, $notice_type = 'error' );
												}
												bkap_remove_proceed_to_checkout();
											}
										}
									}
								}
								break;
						} // switch end here.
					}
				} // cart each.
			}
		}

		/**
		 * This function will remove the product from cart when date and/or time is passed.
		 *
		 * @since 2.5.3
		 * @hook woocommerce_check_cart_items
		 * @hook woocommerce_before_checkout_process
		 * @global object $wpdb Global wpdb Object
		 */

		public static function bkap_remove_product_from_cart() {
			global $wpdb;

			// Run only in the Cart or Checkout Page
			if ( is_cart() || is_checkout() ) {

				$global_settings = bkap_global_setting();
				$current_time    = current_time( 'timestamp' );
				$date_today      = date( 'Y-m-d H:i', $current_time );
				$today           = new DateTime( $date_today );
				$phpversion      = version_compare( phpversion(), '5.3', '>' );
				$global_holidays = array();
				if ( isset( $global_settings->booking_global_holidays ) ) {
					$global_holidays = explode( ',', $global_settings->booking_global_holidays );
				}

				foreach ( WC()->cart->cart_contents as $prod_in_cart_key => $prod_in_cart_value ) {

					if ( isset( $prod_in_cart_value['bkap_booking'] ) && ! empty( $prod_in_cart_value['bkap_booking'] ) ) {

						$date_strtotime = '';

						// Get the Variation or Product ID
						if ( isset( $prod_in_cart_value['product_id'] ) && $prod_in_cart_value['product_id'] != 0 ) {
							$prod_id = $prod_in_cart_value['product_id'];
						}

						$duplicate_of     = bkap_common::bkap_get_product_id( $prod_id );						
						$booking_settings = bkap_setting( $duplicate_of );
						$holiday_array    = isset( $booking_settings['booking_product_holiday'] ) ? $booking_settings['booking_product_holiday'] : array();

						$holiday_array_keys = array();
						if ( is_array( $holiday_array ) && count( $holiday_array ) > 0 ) {
							$holiday_array_keys = array_keys( $holiday_array );
						}

						$booking_data = $prod_in_cart_value['bkap_booking'];

						foreach ( $booking_data  as $key => $value ) {

							if ( isset( $value['hidden_date'] ) && $value['hidden_date'] != '' ) { // can be blanks if the product has been purchased without a date
								$date           = $value['hidden_date'];
								$date_strtotime = strtotime( $date );

								// Product is in cart and later store admin set the date as holiday then remove from cart.
								if ( in_array( $date, $holiday_array_keys ) ) {
									unset( WC()->cart->cart_contents[ $prod_in_cart_key ] );
									continue;
								}

								// Product is in cart and later store admin set the date as global holiday then remove from cart.
								if ( is_array( $global_holidays ) && count( $global_holidays ) > 0 ) {
									if ( in_array( $date, $global_holidays ) ) {
										unset( WC()->cart->cart_contents[ $prod_in_cart_key ] );
										continue;
									}
								}

								if ( isset( $value['time_slot'] ) && $value['time_slot'] != '' ) {
									$advance_booking_hrs  = bkap_advance_booking_hrs( $booking_settings, $duplicate_of );
									$time_slot_to_display = $value['time_slot'];
									
									if ( strpos( $time_slot_to_display, '<br>' ) !== false ) {
										$timeslots = explode( '<br>', $time_slot_to_display );
									} else {
										$timeslots = array( $time_slot_to_display );
									}

									foreach ( $timeslots as $k => $v ) {
										if ( '' !== $v ) {
											$time_exploded        = explode( ' - ', $v );
											$from_time            = $time_exploded[0];
											$to_time              = isset( $time_exploded[1] ) ? $time_exploded[1] : '';
											$dateymd              = date( 'Y-m-d', $date_strtotime );
											$booking_time         = $dateymd . $from_time;
											$booking_time         = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $dateymd, $from_time, $to_time, $duplicate_of, $booking_settings );
											$date2                = new DateTime( $booking_time );
											$include              = bkap_dates_compare( $today, $date2, $advance_booking_hrs, $phpversion );
											if ( ! $include ) {
												break;
											}
										}
									}

									if ( ! $include ) {
										unset( WC()->cart->cart_contents[ $prod_in_cart_key ] );
									}
								}

								if ( isset( WC()->cart->cart_contents[ $prod_in_cart_key ] ) ) {
									do_action( 'bkap_remove_bookable_product_from_cart', $value, $prod_in_cart_key, WC()->cart->cart_contents[ $prod_in_cart_key ] );
								}
							}
						}
					}
				}
			}
		}

		/**
		 * This function force the same booking details in the cart.
		 *
		 * @param bool   $passed true.
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param object $product Product Object.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_same_bookings_in_cart_validation( $passed, $product_id, $booking_settings, $product ) {

			$global_settings = bkap_global_setting();

			if ( ! isset( $global_settings->same_bookings_in_cart ) || ( isset( $global_settings->same_bookings_in_cart ) && 'on' !== $global_settings->same_bookings_in_cart ) ) {
				return $passed;
			}

			if ( $passed && isset( $_POST['wapbk_hidden_date'] ) ) {

				$bkap_booking = bkap_get_first_booking_data_from_cart();

				if ( ! empty( $bkap_booking ) ) {

					$date         = $_POST['wapbk_hidden_date'];
					$end_date     = '';
					$booking_type = bkap_type( $product_id );

					if ( isset( $_POST['variation_id'] ) && '' !== $_POST['variation_id'] ) {
						$variation     = wc_get_product( $_POST['variation_id'] );
						$product_title = $variation->get_formatted_name();
					} else {
						$variation    = wc_get_product( $product_id );
						$product_title = $product->get_name();
					}

					if ( $date !== $bkap_booking['hidden_date'] ) {
						$message = sprintf( __( 'Please select %s to %s to book %s.', 'woocommerce-booking' ), $bkap_booking['date'], $bkap_booking['date_checkout'], $product_title ); 
						wc_add_notice( $message, $notice_type = 'error' );
						return false;
					}

					if ( isset( $_POST['wapbk_hidden_date_checkout'] ) && '' !== $_POST['wapbk_hidden_date_checkout'] ) {
						$end_date = $_POST['wapbk_hidden_date_checkout'];
					}

					switch ( $booking_type ) {

						case 'multiple_days':

							if ( '' != $end_date ) { // Check if the product being added to cart is havin end date or not.

								if ( isset( $bkap_booking['hidden_date_checkout'] ) ) { // first product in cart having end date or not.

									if ( $date !== $bkap_booking['hidden_date'] || $end_date !== $bkap_booking['hidden_date_checkout'] ) {

										$message = sprintf( __( 'Please select %s to %s to book %s.', 'woocommerce-booking' ), $bkap_booking['date'], $bkap_booking['date_checkout'], $product_title ); 
										wc_add_notice( $message, $notice_type = 'error' );
										$passed = false;
									}
								} else {

									// checks in cart for the multiple days booking.
									if ( isset( WC()->cart ) ) {

										$mbkap_booking = bkap_get_first_booking_data_from_cart( 'multiple_days' );

										if ( ! empty( $mbkap_booking ) ) {

											if ( $date !== $mbkap_booking['hidden_date'] || $end_date !== $mbkap_booking['hidden_date_checkout'] ) {
												$message = sprintf( __( 'Please select %s to %s to book %s.', 'woocommerce-booking' ), $mbkap_booking['date'], $mbkap_booking['date_checkout'], $product_title ); 
												wc_add_notice( $message, $notice_type = 'error' );
												$passed = false;
											}

										} else {

											if ( $date !== $bkap_booking['hidden_date'] ) {
												$message = sprintf( __( 'Please select %s to %s to book %s.', 'woocommerce-booking' ), $bkap_booking['date'], $bkap_booking['date_checkout'], $product_title );
												wc_add_notice( $message, $notice_type = 'error' );
												$passed = false;
											}
										}
									}
								}
							}
							break;
						case 'date_time':
						case 'duration_time':

							$key = ( 'duration_time' == $booking_type ) ? 'duration_time_slot' : 'time_slot';
							if ( isset( $_POST[ $key ] ) && '' !== $_POST[ $key ] ) {

								$time_slot = $_POST[ $key ];

								if ( isset( $bkap_booking[ $key ] ) ) { // first product in cart having end date or not.

									if ( $time_slot !== $bkap_booking[ $key ] ) {

										$message = sprintf( __( 'Please select %s and %s to book %s.', 'woocommerce-booking' ), $bkap_booking['date'], $bkap_booking[$key], $product_title ); 
										wc_add_notice( $message, $notice_type = 'error' );
										$passed = false;
									}
								} else {
									// checks in cart for the date and time booking.
									if ( isset( WC()->cart ) ) {

										$mbkap_booking = bkap_get_first_booking_data_from_cart( $booking_type );

										if ( ! empty( $mbkap_booking ) ) {

											if ( $time_slot !== $mbkap_booking[ $key ] ) {
												$message = sprintf( __( 'Please select %s to %s to book %s.', 'woocommerce-booking' ), $mbkap_booking['date'], $mbkap_booking[$key], $product_title ); 
												wc_add_notice( $message, $notice_type = 'error' );
												$passed = false;
											}
										}
									}
								}
							}
							break;
					}
				}
			}

			return $passed;
		}
	}
	$bkap_validation = new Bkap_Validation();
}

