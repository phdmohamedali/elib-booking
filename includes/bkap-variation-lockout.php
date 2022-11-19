<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * This class is for Variation Level Lockout feature
 *
 * @author      Tyche Softwares
 * @package     BKAP/Variation-Lockout
 * @since       2.5.3
 * @category    Classes
 */

if ( ! class_exists( 'bkap_variations' ) ) {

	/**
	 * Class for Handling Variation Level Lokcout functionalities
	 *
	 * @class bkap_variations
	 */

	class bkap_variations {

		/**
		 * Default constructor
		 *
		 * @since 2.5.3
		 */

		public function __construct() {

			// Display Fields in the variations box
			add_action( 'woocommerce_product_after_variable_attributes', array( &$this, 'add_variation_fields' ), 10, 3 );

			// Save custom fields in the variations box
			add_action( 'woocommerce_save_product_variation', array( &$this, 'save_variation_fields' ), 10, 2 );

			// print hidden fields on the front end product page
			add_action( 'bkap_print_hidden_fields', array( &$this, 'print_hidden_lockout' ), 10, 1 );

			// validations on the product page
			add_action( 'bkap_multiple_days_product_validation', array( &$this, 'validate_multiple_days_product_page' ), 10 );
			add_action( 'bkap_single_days_product_validation', array( &$this, 'validate_single_days_product_page' ), 10 );
			add_action( 'bkap_date_time_product_validation', array( &$this, 'validate_date_time_product_page' ), 10 );

			// validation on the cart page
			add_action( 'bkap_multiple_days_cart_validation', array( &$this, 'validate_multiple_days_cart_page' ), 10 );
			add_action( 'bkap_single_days_cart_validation', array( &$this, 'validate_single_days_cart_page' ), 10 );
			add_action( 'bkap_date_time_cart_validation', array( &$this, 'validate_date_time_cart_page' ), 10 );

			// add_filter( 'woocommerce_show_variation_price',                 array( &$this, 'bkap_woocommerce_show_variation_price_callback'),  10, 3 );
		}

		/**
		 * This function will remove the variation price display on front end
		 *
		 * @since 4.9.0
		 * @hook woocommerce_show_variation_price
		 * @param bool   $show
		 * @param Object $parent WC_Product_Variable
		 * @param Object $variation WC_Product_Variation
		 */

		function bkap_woocommerce_show_variation_price_callback( $show, $parent, $variation ) {

			$duplicate_of     = bkap_common::bkap_get_product_id( $parent->get_id() );
			$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

			if ( $booking_settings == '' || ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] != 'on' ) ) {
				return $show;
			}

			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $global_settings->hide_variation_price ) && 'on' == $global_settings->hide_variation_price ) {
				return false;
			}

			return $show;
		}

		/**
		 * This function will add the Lockout Field in all variations of the product
		 *
		 * @since 2.5.3
		 * @hook woocommerce_product_after_variable_attributes
		 * @param int     $loop
		 * @param array   $variation_data Variation Data
		 * @param WP_Post $variation Post of the Variation
		 */

		function add_variation_fields( $loop, $variation_data, $variation ) {

			global $post;

			$lockout_value = get_post_meta( $variation->ID, '_booking_lockout_field', true );

			if ( isset( $post->post_status ) && $post->post_status == 'publish' ) {
				?>
				
				<div class='variable_lockout'>
				<?php
				if ( function_exists( 'woocommerce_wp_text_input' ) ) {
					// Text Field
					woocommerce_wp_text_input(
						array(
							'id'                => '_booking_lockout_field[' . $variation->ID . ']',
							'label'             => __( 'Lockout', 'woocommerce-booking' ),
							'placeholder'       => __( 'Enter the Booking Lockout value for this variation.', 'woocommerce-booking' ),
							'desc_tip'          => 'true',
							'description'       => __( 'Enter the Booking Lockout for this variation.', 'woocommerce-booking' ),
							'value'             => isset( $lockout_value ) ? $lockout_value : '',
							'data_type'         => 'stock',
							'type'              => 'number',
							'custom_attributes' => array(
								'min'  => '0',
								'step' => '1',
							),
						)
					);
				}
				?>
				</div>
				<?php
			}
		}


		/**
		 * This function will save the lockout value in the _booking_lockout_field meta key
		 *
		 * @since 2.5.3
		 * @hook woocommerce_save_product_variation
		 * @param int $variation_id Variation ID
		 * @param int $i Loop count
		 */

		function save_variation_fields( $variation_id, $i ) {
			global $wpdb;

			if ( isset( $_POST['variable_post_id'] ) ) {

				$variable_post_id = $_POST['variable_post_id'];

				foreach ( $variable_post_id as $k => $v ) {
					$variation_id = (int) $variable_post_id[ $k ];
					// Text Field
					$_text_field = '';

					if ( isset( $_POST['_booking_lockout_field'] ) ) {
						$_text_field = $_POST['_booking_lockout_field'][ $variation_id ];
					}

					if ( isset( $_text_field ) && ! is_numeric( $_text_field ) ) {
						$_text_field = 0;
					}

					if ( isset( $_text_field ) ) {
						update_post_meta( $variation_id, '_booking_lockout_field', stripslashes( $_text_field ) );
					}
				}
			}
		}

		/**
		 * This function will print hidden fields for variation lockout on the front end product page.
		 *
		 * @since 2.5.3
		 * @hook bkap_print_hidden_fields
		 * @global object $wpdb Global wpdb Object
		 * @global WP_Post $post Product Post Object
		 * @param int $product_id Product ID
		 */

		function print_hidden_lockout( $product_id ) {
			global $wpdb, $post;

			if ( get_post_type( $post ) === 'product' ) {
				$product_id = $post->ID;
			}
			// get product type
			$product      = wc_get_product( $product_id );
			$product_type = $product->get_type();

			// Booking settings
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			// for a variable and bookable product
			if ( 'variable' == $product_type && isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) {

				$variations = $product->get_available_variations();

				foreach ( $variations as $var_key => $var_val ) {

					$variation_id     = $var_val['variation_id'];
					$cur_variation_id = bkap_common::bkap_get_variation_id( $variation_id );

					$bookings_placed = $this->bkap_get_booked_dates_for_variation( $product_id, $cur_variation_id );

					print( "<input type='hidden' id='wapbk_lockout_" . $variation_id . "' name='wapbk_lockout_" . $variation_id . "' value='" . $bookings_placed['wapbk_lockout_'] . "' />" );

					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
						print( "<input type='hidden' id='wapbk_lockout_checkout_" . $variation_id . "' name='wapbk_lockout_checkout_" . $variation_id . "' value='" . $bookings_placed['wapbk_lockout_checkout_'] . "' />" );
					}

					if ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) {
						print( "<input type='hidden' id='wapbk_timeslot_lockout_" . $variation_id . "' name='wapbk_timeslot_lockout_" . $variation_id . "' value='" . $bookings_placed['wapbk_timeslot_lockout_'] . "' />" );
					}
						print( "<input type='hidden' id='wapbk_bookings_placed_" . $variation_id . "' name='wapbk_bookings_placed_" . $variation_id . "' value='" . $bookings_placed['wapbk_bookings_placed_'] . "' />" );
				}
			}

		}


		/**
		 * The validation on product page for multiple nights based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_multiple_days_product_validation
		 * @global array $bkap_date_formats Array of Date Format
		 */

		function validate_multiple_days_product_page() {

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			if ( ! isset( $_POST['quantity_check_pass'] ) ) {
				$quantity_check_pass = 'yes';
			} else {
				$quantity_check_pass = $_POST['quantity_check_pass'];
			}

			$_POST['validated'] = 'NO';

			$_product = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;
			// get variation Id for a variable product & its lockout value if set
			if ( isset( $_POST['variation_id'] ) && $_POST['variation_id'] != '' ) {
				$variation_id      = $_POST['variation_id'];
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			// if variable product and lockout is set at the variation level
			if ( isset( $product_type ) && $product_type == 'variable' && isset( $variation_lockout ) && $variation_lockout > 0 ) {
				$_POST['validated'] = 'YES';
				$field_name         = 'wapbk_bookings_placed_' . $variation_id;
				$bookings_placed    = '';
				if ( isset( $_POST[ $field_name ] ) ) {
					$bookings_placed = $_POST[ $field_name ];
				}
				// create an array of dates for which orders have already been placed and the qty for each date
				if ( isset( $bookings_placed ) && $bookings_placed != '' ) {
					$list_dates = explode( ',', $bookings_placed );
					foreach ( $list_dates as $list_key => $list_value ) {
						$explode_date = explode( '=>', $list_value );
						if ( isset( $explode_date[1] ) && $explode_date[1] != '' ) {
							$date                = substr( $explode_date[0], 2, -2 );
							$date_array[ $date ] = $explode_date[1];
						}
					}
				}
				// create an array of the current dates selected by the user
				$bookings_array   = bkap_common::bkap_get_betweendays( $_POST['wapbk_hidden_date'], $_POST['wapbk_hidden_date_checkout'] );
				$date_availablity = array();
				foreach ( $bookings_array as $date_key => $date_value ) {
					$final_qty = $_POST['quantity'];
					// add the number of already placed orders for that date
					if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
						if ( array_key_exists( $date_value, $date_array ) ) {
							$qty        = $date_array[ $date_value ];
							$final_qty += $qty;
						}
					}
					$date_availablity[ $date_value ] = $variation_lockout;
					if ( $final_qty > $variation_lockout ) {
						if ( isset( $qty ) && $qty > 0 ) {
							$availability = $variation_lockout - $qty;
						} else {
							$availability = $variation_lockout;
						}
						$date_availablity[ $date_value ] = $availability;
						$quantity_check_pass             = 'no';
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
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $least_availability, $date_range ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				}
				// check if the same product has been added to the cart for the same dates
				if ( $quantity_check_pass == 'yes' ) {
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						if ( isset( $values['bkap_booking'] ) ) {
							$booking = $values['bkap_booking'];
						}
						$quantity           = $values['quantity'];
						$variation_id_added = $values['variation_id'];

						if ( isset( $booking[0]['hidden_date'] ) && isset( $booking[0]['hidden_date_checkout'] ) ) {
							$hidden_date          = $booking[0]['hidden_date'];
							$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
							$dates                = bkap_common::bkap_get_betweendays( $booking[0]['hidden_date'], $booking[0]['hidden_date_checkout'] );

							if ( $variation_id == $variation_id_added ) {
								$date_availablity = array();
								foreach ( $bookings_array as $date_key => $date_value ) {
									$date_availablity[ $date_value ] = $variation_lockout;
									$final_qty                       = $_POST['quantity'];
									// add the number of already placed orders for that date
									if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
										if ( array_key_exists( $date_value, $date_array ) ) {
											$qty        = $date_array[ $date_value ];
											$final_qty += $qty;
										}
									}
									// add the qty from the item in the cart
									if ( isset( $dates ) && is_array( $dates ) && count( $dates ) > 0 ) {
										if ( in_array( $date_value, $dates ) ) {
											$qty        = $quantity;
											$final_qty += $qty;
										}
									}
									if ( $final_qty > $variation_lockout ) {
										if ( isset( $qty ) && $qty > 0 ) {
											$availability = $variation_lockout - $qty;
										} else {
											$availability = $variation_lockout;
										}
										$date_availablity[ $date_value ] = $availability;
										$quantity_check_pass             = 'no';
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
										$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $least_availability, $date_range ), $msg_text );
										wc_add_notice( $message, $notice_type = 'error' );
									}
								}
							}
						}
					}
				}
			}
			$_POST['quantity_check_pass'] = $quantity_check_pass;
		}

		/**
		 * The validation on product page for Single Day based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_single_days_product_validation
		 * @global array $bkap_date_formats Array of Date Format
		 */

		function validate_single_days_product_page() {

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			if ( ! isset( $_POST['quantity_check_pass'] ) ) {
				$quantity_check_pass = 'yes';
			} else {
				$quantity_check_pass = $_POST['quantity_check_pass'];
			}

			$_POST['validated'] = 'NO';
			$_product           = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;
			// get variation Id for a variable product & its lockout value if set
			if ( isset( $_POST['variation_id'] ) && $_POST['variation_id'] != '' ) {
				$variation_id      = $_POST['variation_id'];
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			// if variable product and lockout is set at the variation level
			if ( isset( $product_type ) && $product_type == 'variable' && isset( $variation_lockout ) && $variation_lockout > 0 ) {
				$_POST['validated'] = 'YES';

				$field_name      = 'wapbk_bookings_placed_' . $variation_id;
				$bookings_placed = $_POST[ $field_name ];
				// create an array of dates for which orders have already been placed and the qty for each date
				if ( isset( $bookings_placed ) && $bookings_placed != '' ) {
					// create an array of the dates
					$list_dates = explode( ',', $bookings_placed );
					foreach ( $list_dates as $list_key => $list_value ) {
						// separate the qty for each date
						$explode_date = explode( '=>', $list_value );

						if ( isset( $explode_date[1] ) && $explode_date[1] != '' ) {
							$date                = substr( $explode_date[0], 2, -2 );
							$date_array[ $date ] = $explode_date[1];
						}
					}
				}
	
				$booking_date = isset( $_POST['wapbk_hidden_date'] ) ? sanitize_text_field( $_POST['wapbk_hidden_date'] ) : ''; // Booking Date.
				$final_qty    = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0; // Quantity.

				// add the number of already placed orders for that date
				if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
					if ( array_key_exists( $booking_date, $date_array ) ) {
						$qty        = $date_array[ $booking_date ];
						$final_qty += $qty;
					}
				}
				// now check if the final qty exceeds the lockout value
				if ( $final_qty > $variation_lockout ) {
					if ( isset( $qty ) && $qty > 0 ) {
						$availability = $variation_lockout - $qty;
					} else {
						$availability = $variation_lockout;
					}
					$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

					$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
					$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $availability, $date_to_display ), $msg_text );
					wc_add_notice( $message, $notice_type = 'error' );
					$quantity_check_pass                  = 'no';
				}

				// check if the same product has been added to the cart for the same dates
				if ( 'yes' == $quantity_check_pass ) {
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						if ( isset( $values['bkap_booking'] ) ) {
							$booking = $values['bkap_booking'];
						}
						$quantity           = $values['quantity'];
						$variation_id_added = $values['variation_id'];

						if ( isset( $booking[0]['hidden_date'] ) ) {
							$hidden_date = $booking[0]['hidden_date'];

							if ( $variation_id == $variation_id_added ) {
								$final_qty = $_POST['quantity'];
								// add the number of already placed orders for that date
								if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
									if ( array_key_exists( $booking_date, $date_array ) ) {
										$qty        = $date_array[ $booking_date ];
										$final_qty += $qty;
									}
								}
								// add the qty from the item in the cart
								if ( isset( $hidden_date ) && $hidden_date != '' ) {
									if ( $hidden_date == $booking_date ) {
										$qty_cart   = $quantity;
										$final_qty += $qty_cart;
									}
								}

								if ( $final_qty > $variation_lockout ) {
									if ( isset( $qty ) && $qty > 0 ) {
										$availability = $variation_lockout - $qty;
									} else {
										$availability = $variation_lockout;
									}
									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $availability, $date_to_display ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
									$quantity_check_pass                  = 'no';
								}
							}
						}
					}
				}
			}
			$_POST['quantity_check_pass'] = $quantity_check_pass;
		}

		/**
		 * The validation on product page for Date & Time booking based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_date_time_product_validation
		 * @global array $bkap_date_formats Array of Date Format
		 */

		function validate_date_time_product_page() {

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$quantity_check_pass = 'yes';
			$_POST['validated']  = 'NO';
			$_product            = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;
			// get variation Id for a variable product & its lockout value if set
			if ( isset( $_POST['variation_id'] ) && $_POST['variation_id'] != '' ) {
				$variation_id      = $_POST['variation_id'];
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			// if variable product and lockout is set at the variation level
			if ( isset( $product_type ) && $product_type == 'variable' && isset( $variation_lockout ) && $variation_lockout > 0 ) {
				$_POST['validated'] = 'YES';

				$field_name      = 'wapbk_bookings_placed_' . $variation_id;
				$bookings_placed = $_POST[ $field_name ];
				// create an array of dates for which orders have already been placed and the qty for each date
				if ( isset( $bookings_placed ) && $bookings_placed != '' ) {
					// create an array of the dates
					$list_dates = explode( ',', $bookings_placed );
					foreach ( $list_dates as $list_key => $list_value ) {
						// separate the qty for each date & time slot
						$explode_date = explode( '=>', $list_value );

						if ( isset( $explode_date[2] ) && $explode_date[2] != '' ) {
							$date                                    = substr( $explode_date[0], 2, -2 );
							$date_array[ $date ][ $explode_date[1] ] = $explode_date[2];
						}
					}
				}

				$booking_date  = $_POST['wapbk_hidden_date']; // booking date.
				$booking_time  = $_POST['time_slot']; // booking time.
				$exploded_time = explode( '-', $booking_time );
				$booking_time  = bkap_date_as_format( trim( $exploded_time[0] ), 'H:i' );

				if ( isset( $exploded_time[1] ) && $exploded_time[1] != '' ) {
					$booking_time .= ' - ' . bkap_date_as_format( trim( $exploded_time[1] ), 'H:i' );
				}

				$final_qty = $_POST['quantity']; // qty.

				// add the number of already placed orders for that date.
				if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
					if ( array_key_exists( $booking_date, $date_array ) ) {
						if ( array_key_exists( $booking_time, $date_array[ $booking_date ] ) ) {
							$qty        = $date_array[ $booking_date ][ $booking_time ];
							$final_qty += $qty;
						}
					}
				}

				// now check if the final qty exceeds the lockout value.
				if ( $final_qty > $variation_lockout ) {
					if ( isset( $qty ) && $qty > 0 ) {
						$availability = $variation_lockout - $qty;
					} else {
						$availability = $variation_lockout;
					}
					$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

					$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
					$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $post_title->post_title, $availability, $date_to_display, $_POST['time_slot'] ), $msg_text );
					wc_add_notice( $message, $notice_type = 'error' );
					$quantity_check_pass                  = 'no';
				}

				// check if the same product has been added to the cart for the same date and time
				if ( 'yes' == $quantity_check_pass ) {
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
						if ( isset( $values['bkap_booking'] ) ) {
							$booking = $values['bkap_booking'];
						}
						$quantity           = $values['quantity'];
						$variation_id_added = $values['variation_id'];

						if ( isset( $booking[0]['hidden_date'] ) && isset( $booking[0]['time_slot'] ) ) {
							$hidden_date = $booking[0]['hidden_date'];
							$hidden_time = $booking[0]['time_slot'];

							if ( $variation_id == $variation_id_added ) {
								$final_qty = $_POST['quantity'];

								// add the number of already placed orders for that date
								if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
									if ( array_key_exists( $booking_date, $date_array ) ) {
										if ( array_key_exists( $booking_time, $date_array[ $booking_date ] ) ) {
											$qty        = $date_array[ $booking_date ][ $booking_time ];
											$final_qty += $qty;
										}
									}
								}
								// add the qty from the item in the cart
								if ( isset( $hidden_date ) && $hidden_date != '' && isset( $hidden_time ) && $hidden_time != '' ) {
									if ( $hidden_date == $booking_date && $hidden_time == $booking_time ) {
										$qty_cart   = $quantity;
										$final_qty += $qty_cart;
									}
								}

								if ( $final_qty > $variation_lockout ) {
									if ( isset( $qty ) && $qty > 0 ) {
										$availability = $variation_lockout - $qty;
									} else {
										$availability = $variation_lockout;
									}
									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $post_title->post_title, $availability, $date_to_display, $_POST['time_slot'] ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
									$quantity_check_pass                  = 'no';
								}
							}
						}
					}
				}
			}
			$_POST['quantity_check_pass'] = $quantity_check_pass;
		}

		/**
		 * The validation on Cart & Checkout page for Date & Time booking based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_multiple_days_cart_validation
		 * @global array $bkap_date_formats Array of Date Format
		 * @global array $wpdb Global wpdb Object
		 */

		function validate_multiple_days_cart_page() {
			global $wpdb;

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$product_id      = $_POST['product_id'];
			$variation_id    = $_POST['variation_id'];
			$date_checkin    = $_POST['booking_date'];
			$date_checkout   = $_POST['booking_checkout'];
			$quantity_booked = $_POST['quantity'];
			$cart_item_key   = $_POST['cart_item_key'];

			if ( ! isset( $_POST['validation_status'] ) ) {
				$validation_completed = 'NO';
			} else {
				$validation_completed = $_POST['validation_status'];
			}

			$order_dates = bkap_common::bkap_get_betweendays( $date_checkin, $date_checkout );
			if ( $variation_id > 0 ) {
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			if ( $variation_id > 0 && isset( $variation_lockout ) && $variation_lockout > 0 ) {

				$validation_completed = 'YES';

				// Booking settings
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				$post_title = get_post( $product_id );
				// get all the dates for which bookings have been made for this variation ID
				$query_get_order_item_ids = 'SELECT order_item_id FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
												WHERE meta_key = '_variation_id'
												AND meta_value = %d";
				$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $variation_id ) );
				$total_bookings           = $total_bookings_checkout = array();

				// once u hv a list of all the orders placed for a given variation ID, create a list of dates and compare it with lockout
				if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {
					foreach ( $get_order_item_ids as $item_key => $item_value ) {
						// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
						$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
												WHERE order_item_id = %d';
						$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

						// check the booking post status
						$booking_id     = bkap_common::get_booking_id( $item_value->order_item_id );
						$booking_status = get_post_status( $booking_id );

						// check if it's a valid order ID & booking ID
						$order = wc_get_order( $get_order_id[0]->order_id );
						if ( $order && false !== $booking_status ) {
							
							$order_status = $order->get_status();
							$order_status = "wc-$order_status";

							if ( ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

								// get the booking details for the given order item ID
								$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
    													WHERE meta_key IN (%s,%s,%s)
    													AND order_item_id = %d';

								$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_wapbk_checkout_date', '_qty', $item_value->order_item_id ) );
								// save the date in an array
								if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {
									$start_date = $end_date = '';
									$dates      = array();
									if ( isset( $get_dates[1]->meta_value ) ) {
										$start_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
									}
									if ( isset( $get_dates[2]->meta_value ) ) {
										$end_date = date( 'j-n-Y', strtotime( $get_dates[2]->meta_value ) );
									}

									$current_time = current_time( 'timestamp' );

									if ( $current_time <= strtotime( $end_date ) ) {

										// if both start and end date is set then get the between days
										if ( isset( $start_date ) && $start_date != '' && isset( $end_date ) && $end_date != '' ) {
											$dates = bkap_common::bkap_get_betweendays( $start_date, $end_date );
											// if renatl addon is active
											if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {
												// if charge per is enabled, then the checkout date should also be disabled once lockout is reached
												if ( isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' ) {
													$dates[] = $end_date;
												}
												// add the prior and post dates in the list
												if ( isset( $booking_settings['booking_prior_days_to_book'] ) && $booking_settings['booking_prior_days_to_book'] ) {
													$days        = '-' . $booking_settings['booking_prior_days_to_book'] . ' days';
													$prior_date  = date( 'j-n-Y', strtotime( $days, strtotime( $start_date ) ) );
													$prior_block = bkap_common::bkap_get_betweendays( $prior_date, $start_date );
													foreach ( $prior_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}
												if ( isset( $booking_settings['booking_later_days_to_book'] ) && $booking_settings['booking_later_days_to_book'] ) {
													$days         = '+' . $booking_settings['booking_later_days_to_book'] . ' days';
													$late_date    = date( 'j-n-Y', strtotime( $days, strtotime( $end_date ) ) );
													$end_date_new = date( 'j-n-Y', strtotime( '+1 day', strtotime( $end_date ) ) );
													$later_block  = bkap_common::bkap_get_betweendays( $end_date_new, $late_date );
													foreach ( $later_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}
											}
										}
										if ( is_numeric( $get_dates[0]->meta_value ) ) {
											if ( is_array( $dates ) && count( $dates ) > 0 ) {
												foreach ( $dates as $array_key => $array_value ) {
													$list_dates[ $array_value ] = $get_dates[0]->meta_value;
												}
											}
										}
									}
								}
							}
						}
					}

					// cart quantity check.
					$cart_dates = array();
					foreach ( WC()->cart->get_cart() as $cart_key => $values ) {
						if ( isset( $values['bkap_booking'] ) && $cart_key !== $cart_item_key ) {
							$cart_booking = $values['bkap_booking'][0];
							if ( isset( $cart_booking['hidden_date_checkout'] ) && isset( $values['variation_id'] ) && $values['variation_id'] == $variation_id ) {
								$cart_date_range = bkap_common::bkap_get_betweendays( $cart_booking['hidden_date'], $cart_booking['hidden_date_checkout'] );
								foreach ( $cart_date_range as $key => $value ) {
									if ( isset( $list_dates[ $value ] ) ) {
										$list_dates[ $value ] += $values['quantity'];
									} else {
										$list_dates[ $value ] = $values['quantity'];
									}
								}
							}
						}
					}

					// create/edit a final array which contains each date once and the value is the qty for which the order has been placed for this date
					if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {
						foreach ( $list_dates as $date_key => $qty_value ) {
							// check if the date is already present in the array, if yes, then edit the qty
							if ( isset( $total_bookings[ $date_key ] ) ) {
								$qty_present                 = $total_bookings[ $date_key ];
								$new_qty                     = $qty_present + $qty_value;
								$total_bookings[ $date_key ] = $new_qty;
							} else {
								$total_bookings[ $date_key ] = $qty_value;
							}
						}
					}

					$date_availablity = array();
					$check            = 'pass';

					foreach ( $order_dates as $k => $v ) {
						$date_availablity[ $v ] = $variation_lockout;
						$final_qty              = $quantity_booked;
						if ( array_key_exists( $v, $total_bookings ) ) {
							$final_qty += $total_bookings[ $v ];
						}
						if ( $variation_lockout > 0 && $variation_lockout < $final_qty ) {
							if ( is_array( $total_bookings ) && isset( $total_bookings[ $v ] ) ) {
								$available_tickets = $variation_lockout - $total_bookings[ $v ];
							} else {
								$available_tickets = $variation_lockout;
							}
							$date_availablity[ $v ] = $available_tickets;
							$check                  = 'failed';
						}
					}
				} else {

					$date_availablity = array();
					$check            = 'pass';

					$list_dates = array();
					// cart quantity check.
					$cart_dates = array();
					if ( count( WC()->cart->get_cart() ) > 0 ) {
						foreach ( WC()->cart->get_cart() as $cart_key => $values ) {
							if ( isset( $values['bkap_booking'] ) && $cart_key !== $cart_item_key ) {
								$cart_booking = $values['bkap_booking'][0];
								if ( isset( $cart_booking['hidden_date_checkout'] ) && isset( $values['variation_id'] ) && $values['variation_id'] == $variation_id ) {
									$cart_date_range = bkap_common::bkap_get_betweendays( $cart_booking['hidden_date'], $cart_booking['hidden_date_checkout'] );
									foreach ( $cart_date_range as $key => $value ) {
										if ( isset( $list_dates[ $value ] ) ) {
											$list_dates[ $value ] += $values['quantity'];
										} else {
											$list_dates[ $value ] = $values['quantity'];
										}
									}
								}
							}
						}

						$total_bookings = array();

						if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {
							foreach ( $list_dates as $date_key => $qty_value ) {
								// check if the date is already present in the array, if yes, then edit the qty
								if ( array_key_exists( $date_key, $total_bookings ) ) {
									$qty_present                 = $total_bookings[ $date_key ];
									$new_qty                     = $qty_present + $qty_value;
									$total_bookings[ $date_key ] = $new_qty;
								}
								// else create a new entry in the array
								else {
									$total_bookings[ $date_key ] = $qty_value;
								}
							}
						}
					}

					foreach ( $order_dates as $k => $v ) {
						$date_availablity[ $v ] = $variation_lockout;
						$final_qty              = $quantity_booked;
						if ( array_key_exists( $v, $total_bookings ) ) {
							$final_qty += $total_bookings[ $v ];
						}
						if ( $variation_lockout > 0 && $variation_lockout < $final_qty ) {
							if ( is_array( $total_bookings ) && isset( $total_bookings[ $v ] ) ) {
								$available_tickets = $variation_lockout - $total_bookings[ $v ];
							} else {
								$available_tickets = $variation_lockout;
							}
							$date_availablity[ $v ] = $available_tickets;
							$check                  = 'failed';
						}
					}
				}

				if ( isset( $check ) && 'failed' == $check ) {
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
						$check_in_to_display  = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $date_checkin ) );
						$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $date_checkout ) );
						$date_range           = "$check_in_to_display to $check_out_to_display";

						$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $least_availability, $date_range ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				}
			}
			$_POST['validation_status'] = $validation_completed;
		}

		/**
		 * The validation on Cart & Checkout page for Single Day booking based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_single_days_cart_validation
		 * @global array $bkap_date_formats Array of Date Format
		 */

		function validate_single_days_cart_page() {

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$product_id      = $_POST['product_id'];
			$variation_id    = $_POST['variation_id'];
			$booking_date    = $_POST['booking_date'];
			$quantity_booked = $_POST['quantity'];
			$booking_date    = date( 'j-n-Y', strtotime( $booking_date ) );
			$item_key        = $_POST['cart_item_key'];

			global $wpdb;

			if ( ! isset( $_POST['validation_status'] ) ) {
				$validation_completed = 'NO';
			} else {
				$validation_completed = $_POST['validation_status'];
			}

			if ( $variation_id > 0 ) {
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			if ( $variation_id > 0 && isset( $variation_lockout ) && $variation_lockout > 0 ) {

				$validation_completed = 'YES';

				// Booking settings
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				$post_title = get_post( $product_id );
				// get all the dates for which bookings have been made for this variation ID
				$query_get_order_item_ids = 'SELECT order_item_id FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
												WHERE meta_key = '_variation_id'
												AND meta_value = %d";
				$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $variation_id ) );
				$total_bookings           = array();

				// once u hv a list of all the orders placed for a given variation ID, create a list of dates and compare it with lockout
				if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {
					foreach ( $get_order_item_ids as $item_key => $item_value ) {

						// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
						$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
												WHERE order_item_id = %d';

						$get_order_id = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

						// check the booking post status
						$booking_id     = bkap_common::get_booking_id( $item_value->order_item_id );
						$booking_status = get_post_status( $booking_id );

						// check if it's a valid order ID & booking ID
						$order = wc_get_order( $get_order_id[0]->order_id );
						if ( $order && false !== $booking_status ) {
							$order_status = $order->get_status();
							$order_status = "wc-$order_status";
							
							if ( isset( $order_status ) && ( $order_status != '' ) && ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

								// get the booking details for the given order item ID
								$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
    													WHERE meta_key IN (%s,%s)
    													AND order_item_id = %d';

								$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_qty', $item_value->order_item_id ) );

								// save the date in an array
								if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {
									$start_date = '';
									$dates      = array();
									if ( isset( $get_dates[1]->meta_value ) ) {
										$start_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
									}

									$current_time = current_time( 'timestamp' );

									if ( $current_time <= strtotime( $start_date ) ) {

										if ( is_numeric( $get_dates[0]->meta_value ) ) {
											$list_dates[ $start_date ] = $get_dates[0]->meta_value;
										}
									}
								}

								// create/edit a final array which contains each date once and the value is the qty for which the order has been placed for this date
								if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {
									foreach ( $list_dates as $date_key => $qty_value ) {
										// check if the date is already present in the array, if yes, then edit the qty
										if ( array_key_exists( $date_key, $total_bookings ) ) {
											$qty_present                 = $total_bookings[ $date_key ];
											$new_qty                     = $qty_present + $qty_value;
											$total_bookings[ $date_key ] = $new_qty;
										}
										// else create a new entry in the array
										else {
											$total_bookings[ $date_key ] = $qty_value;
										}
									}
								}
							}
						}
					}

					$final_qty = $quantity_booked;

					if ( array_key_exists( $booking_date, $total_bookings ) ) {
						$qty_ordered = $total_bookings[ $booking_date ];

						$final_qty += $total_bookings[ $booking_date ];
					}

					if ( $variation_lockout > 0 && $variation_lockout < $final_qty ) {
						if ( isset( $qty_ordered ) && $qty_ordered > 0 ) {
							$available_tickets = $variation_lockout - $qty_ordered;
						} else {
							$available_tickets = $variation_lockout;
						}
						$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

						$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $available_tickets, $date_to_display ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				} else {

					$cart_qty = 0;
					foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

						if ( isset( $values['bkap_booking'] ) && $cart_item_key !== $item_key ) {
							$cart_booking = $values['bkap_booking'][0];

							if ( isset( $values['variation_id'] ) && $values['variation_id'] == $variation_id ) {
								if ( strtotime( $booking_date ) == strtotime( $cart_booking['hidden_date'] ) ) {
									$cart_qty += $values['quantity'];
								}
							}
						}
					}

					$quantity_booked += $cart_qty;


					if ( $variation_lockout > 0 && $variation_lockout < $quantity_booked ) {
						$available_tickets = $variation_lockout;
						$date_to_display   = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

						$msg_text                             = __( get_option( 'book_limited-booking-msg-date' ), 'woocommerce-booking' );
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE' ), array( $post_title->post_title, $available_tickets, $date_to_display ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				}
			}

			$_POST['validation_status'] = $validation_completed;
		}

		/**
		 * The validation on Cart & Checkout page for Date & Time booking based on the variation level lockout.
		 *
		 * @since 2.5.3
		 * @hook bkap_date_time_cart_validation
		 * @global array $bkap_date_formats Array of Date Format
		 */

		function validate_date_time_cart_page() {

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$product_id      = $_POST['product_id'];
			$variation_id    = $_POST['variation_id'];
			$booking_date    = $_POST['booking_date'];
			$booking_time    = $_POST['time_slot'];
			$quantity_booked = $_POST['quantity'];

			if ( ! isset( $_POST['validation_status'] ) ) {
				$validation_completed = 'NO';
			} else {
				$validation_completed = $_POST['validation_status'];
			}

			$booking_date  = date( 'j-n-Y', strtotime( $booking_date ) );
			$exploded_time = explode( '-', $booking_time );
			$booking_time  = bkap_date_as_format( trim( $exploded_time[0] ), 'H:i' );

			if ( isset( $exploded_time[1] ) && $exploded_time[1] != '' ) {
				$booking_time .= ' - ' . bkap_date_as_format( trim( $exploded_time[1] ), 'H:i' );
			}

			global $wpdb;

			$validation_completed = 'NO';

			if ( $variation_id > 0 ) {
				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );
			}
			if ( $variation_id > 0 && isset( $variation_lockout ) && $variation_lockout > 0 ) {

				$validation_completed = 'YES';

				// Booking settings
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				$post_title = get_post( $product_id );

				// get all the dates for which bookings have been made for this variation ID
				$query_get_order_item_ids = "SELECT order_item_id FROM `" . $wpdb->prefix . "woocommerce_order_itemmeta`
												WHERE meta_key = '_variation_id'
												AND meta_value = %d";
				$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $variation_id ) );
				$total_bookings           = array();

				// once u hv a list of all the orders placed for a given variation ID, create a list of dates and compare it with lockout
				if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {
					foreach ( $get_order_item_ids as $item_key => $item_value ) {
						// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
						$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
												WHERE order_item_id = %d';

						$get_order_id = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

						// check the booking post status
						$booking_id     = bkap_common::get_booking_id( $item_value->order_item_id );
						$booking_status = get_post_status( $booking_id );

						// check if it's a valid order ID & booking ID
						$order = wc_get_order( $get_order_id[0]->order_id );
						if ( $order && false !== $booking_status ) {

							$order_status = $order->get_status();
							$order_status = "wc-$order_status";
							
							if ( ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

								// get the booking details for the given order item ID
								$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
    													WHERE meta_key IN (%s,%s,%s)
    													AND order_item_id = %d';

								$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_wapbk_time_slot', '_qty', $item_value->order_item_id ) );

								// save the date in an array
								if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {
									$booked_date = '';
									$dates       = array();
									if ( isset( $get_dates[1]->meta_value ) ) {
										$booked_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
									}

									$current_time = current_time( 'timestamp' );

									if ( $current_time <= strtotime( $booked_date ) ) {

										if ( is_numeric( $get_dates[0]->meta_value ) && isset( $get_dates[2] ) ) {
											$list_dates[ $booked_date ][ $get_dates[2]->meta_value ] = $get_dates[0]->meta_value;
										}
									}
								}

								// create/edit a final array which contains each date once and the value is the qty for which the order has been placed for this date
								if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {
									foreach ( $list_dates as $date_key => $qty_value ) {

										// check if the date & time is already present in the array, if yes, then edit the qty
										foreach ( $qty_value as $k => $v ) {
											if ( array_key_exists( $date_key, $total_bookings ) && array_key_exists( ( $k ), $total_bookings[ $date_key ] ) ) {
												$qty_present                       = $total_bookings[ $date_key ][ $k ];
												$new_qty                           = $qty_present + $v;
												$total_bookings[ $date_key ][ $k ] = $new_qty;
											} else { // else create a new entry in the array
												$total_bookings[ $date_key ][ $k ] = $v;
											}
										}
									}
								}
							}
						}
					}

					$final_qty = $quantity_booked;

					if ( array_key_exists( $booking_date, $total_bookings ) && array_key_exists( $booking_time, $total_bookings[ $booking_date ] ) ) {
						$qty_ordered = $total_bookings[ $booking_date ][ $booking_time ];
						$final_qty  += $total_bookings[ $booking_date ][ $booking_time ];
					}

					if ( $variation_lockout > 0 && $variation_lockout < $final_qty ) {
						if ( isset( $qty_ordered ) && $qty_ordered > 0 ) {
							$available_tickets = $variation_lockout - $qty_ordered;
						} else {
							$available_tickets = $variation_lockout;
						}
						$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

						$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $post_title->post_title, $available_tickets, $date_to_display, $_POST['time_slot'] ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				} else {
					if ( $variation_lockout > 0 && $variation_lockout < $quantity_booked ) {
						$available_tickets = $variation_lockout;

						$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

						$msg_text                             = __( get_option( 'book_limited-booking-msg-time' ), 'woocommerce-booking' );
						$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $post_title->post_title, $available_tickets, $date_to_display, $_POST['time_slot'] ), $msg_text );
						wc_add_notice( $message, $notice_type = 'error' );
					}
				}
			}
			$_POST['validation_status'] = $validation_completed;
		}

		/**
		 * Retuning the lockout and booked dates for the given variation id.
		 *
		 * @since 4.5.0
		 * @param int $product_id Product ID
		 * @param int $variation_id Variation ID
		 * @global $wpdb Global wpdb Object
		 * @return array $return_variation_lockout Lockout Date and/or time data based booking type
		 */

		public static function bkap_get_booked_dates_for_variation( $product_id, $variation_id ) {

			global $wpdb;
			// Booking settings
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );

			// check if lockout is set at the variation level
			if ( isset( $variation_lockout ) && $variation_lockout > 0 ) {

				// get all the dates for which bookings have been made for this variation ID
				$query_get_order_item_ids = 'SELECT order_item_id FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
                                                WHERE meta_key = '_variation_id'
                                                AND meta_value = %d";
				$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $variation_id ) );

				$total_bookings = $total_bookings_checkout = array();

				// once u hv a list of all the orders placed for a given variation ID, create a list of dates and compare it with lockout
				if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {

					foreach ( $get_order_item_ids as $item_key => $item_value ) {

						// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
						$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
                                                WHERE order_item_id = %d';

						$get_order_id = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

						// check the booking post status
						$booking_id = bkap_common::get_booking_id( $item_value->order_item_id );

						$booking_status = get_post_status( $booking_id );

						// check if it's a valid order ID & booking ID
						$order = wc_get_order( $get_order_id[0]->order_id );
						if ( $order && false !== $booking_status ) {

							$order_status = $order->get_status();
							$order_status = "wc-$order_status";

							if ( isset( $order_status ) && ( $order_status != '' ) && ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

								$current_time = current_time( 'timestamp' );
								$current_time = date( 'Y-m-d', $current_time );
								$current_time = strtotime( $current_time );
								// check booking type and calculate lockout accordingly
								// multiple days

								if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
									// get the booking details for the given order item ID
									$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . "woocommerce_order_itemmeta`
                                                            WHERE meta_key IN (%s,%s,%s)
                                                            AND order_item_id = %d
                                                            ORDER BY FIELD( meta_key, '_qty', '_wapbk_booking_date','_wapbk_checkout_date' )";

									$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_wapbk_checkout_date', '_qty', $item_value->order_item_id ) );

									// save the date in an array
									if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {

										$start_date = $end_date = '';
										$dates      = $list_dates = $list_dates_checkout = array();

										if ( isset( $get_dates[1]->meta_value ) ) {
											$start_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
										}

										if ( isset( $get_dates[2]->meta_value ) ) {
											$end_date = date( 'j-n-Y', strtotime( $get_dates[2]->meta_value ) );
										}

										if ( isset( $start_date ) && $start_date != '' && isset( $end_date ) && $end_date != '' ) {

											if ( strtotime( $start_date ) > strtotime( $end_date ) ) {

												$start_date = date( 'j-n-Y', strtotime( $get_dates[2]->meta_value ) );
												$end_date   = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
											}
										}

										// consider orders from today onwards i.e. ignore back dated orders
										if ( $current_time <= strtotime( $end_date ) ) {

											// if both start and end date is set then get the between days
											if ( isset( $start_date ) && $start_date != '' && isset( $end_date ) && $end_date != '' ) {
												$dates      = bkap_common::bkap_get_betweendays( $start_date, $end_date );
												$first_date = $start_date;

												// if rental addon is active
												if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {
													$charge_per_day = false;
													// if charge per day is enabled, then the checkout date should also be disabled once lockout is reached
													if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
														$dates[]        = $end_date;
														$charge_per_day = true;
													}

													// add the prior and post dates in the list
													if ( isset( $booking_settings['booking_prior_days_to_book'] ) && $booking_settings['booking_prior_days_to_book'] ) {
														$days        = '-' . $booking_settings['booking_prior_days_to_book'] . ' days';
														$prior_date  = date( 'j-n-Y', strtotime( $days, strtotime( $start_date ) ) );
														$first_date  = $prior_date;
														$prior_block = bkap_common::bkap_get_betweendays( $prior_date, $start_date );

														foreach ( $prior_block as $block_key => $block_value ) {
															$dates[] = $block_value;
														}
													}

													if ( isset( $booking_settings['booking_later_days_to_book'] ) && $booking_settings['booking_later_days_to_book'] ) {

														if ( ! $charge_per_day ) {
															$end_date_new = $end_date;
														} else {
															$end_date_new = date( 'j-n-Y', strtotime( '+1 day', strtotime( $end_date ) ) );
														}
														$days      = '+' . $booking_settings['booking_later_days_to_book'] . ' days';
														$late_date = date( 'j-n-Y', strtotime( $days, strtotime( $end_date ) ) );

														$later_block = bkap_common::bkap_get_betweendays( $end_date_new, $late_date );

														foreach ( $later_block as $block_key => $block_value ) {
															$dates[] = $block_value;
														}
													}
												}

												// remove the first date (which is the start date so it can be enabled for the checkout calendar
												$checkout_dates = $dates;
												$key            = array_search( $first_date, $checkout_dates );

												if ( isset( $key ) && $key != false ) {
													unset( $checkout_dates[ $key ] );
												}
											}

											if ( is_numeric( $get_dates[0]->meta_value ) ) {
												if ( is_array( $dates ) && count( $dates ) > 0 ) {
													foreach ( $dates as $array_key => $array_value ) {
														$list_dates[ $array_value ] = $get_dates[0]->meta_value;
													}
													foreach ( $checkout_dates as $array_key => $array_value ) {
														$list_dates_checkout[ $array_value ] = $get_dates[0]->meta_value;
													}
												}
											}
										}
									}
								} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) {
									// get the booking details for the given order item ID
									$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
                                                            WHERE meta_key IN (%s,%s,%s)
                                                            AND order_item_id = %d';

									$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_wapbk_time_slot', '_qty', $item_value->order_item_id ) );

									// save the date in an array
									if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {

										$booked_date = '';
										$dates       = array();
										$list_dates  = array();

										if ( isset( $get_dates[1]->meta_value ) ) {
											$booked_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
										}

										if ( $current_time <= strtotime( $booked_date ) ) {

											// if rental addon is active
											if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {

												// add the prior and post dates in the list
												if ( isset( $booking_settings['booking_prior_days_to_book'] ) && $booking_settings['booking_prior_days_to_book'] ) {
													$days        = '-' . $booking_settings['booking_prior_days_to_book'] . ' days';
													$prior_date  = date( 'j-n-Y', strtotime( $days, strtotime( $booked_date ) ) );
													$first_date  = $prior_date;
													$prior_block = bkap_common::bkap_get_betweendays( $prior_date, $booked_date );

													foreach ( $prior_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}

												if ( isset( $booking_settings['booking_later_days_to_book'] ) && $booking_settings['booking_later_days_to_book'] ) {
													$days         = '+' . $booking_settings['booking_later_days_to_book'] . ' days';
													$late_date    = date( 'j-n-Y', strtotime( $days, strtotime( $booked_date ) ) );
													$end_date_new = date( 'j-n-Y', strtotime( '+1 day', strtotime( $booked_date ) ) );
													$later_block  = bkap_common::bkap_get_betweendays( $end_date_new, $late_date );

													foreach ( $later_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}
											}

											if ( is_numeric( $get_dates[0]->meta_value ) && isset( $get_dates[2] ) ) {

												if ( is_array( $dates ) && count( $dates ) > 0 ) {
													foreach ( $dates as $array_key => $array_value ) {
														$list_dates[ $array_value ][ $get_dates[2]->meta_value ] = $get_dates[0]->meta_value;
													}
												}

												$list_dates[ $booked_date ][ $get_dates[2]->meta_value ] = $get_dates[0]->meta_value;
											}
										}
									}
								} elseif ( isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) {

									// get the booking details for the given order item ID
									$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
                                                            WHERE meta_key IN (%s,%s)
                                                            AND order_item_id = %d';

									$get_dates = $wpdb->get_results( $wpdb->prepare( $query_get_dates, '_wapbk_booking_date', '_qty', $item_value->order_item_id ) );

									// save the date in an array
									if ( is_array( $get_dates ) && count( $get_dates ) > 0 ) {
										$booked_date = '';
										$dates       = array();
										$list_dates  = array();

										if ( isset( $get_dates[1]->meta_value ) ) {
											$booked_date = date( 'j-n-Y', strtotime( $get_dates[1]->meta_value ) );
										}

										if ( $current_time <= strtotime( $booked_date ) ) {

											// if rental addon is active
											if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {

												// add the prior and post dates in the list
												if ( isset( $booking_settings['booking_prior_days_to_book'] ) && $booking_settings['booking_prior_days_to_book'] ) {
													$days        = '-' . $booking_settings['booking_prior_days_to_book'] . ' days';
													$prior_date  = date( 'j-n-Y', strtotime( $days, strtotime( $booked_date ) ) );
													$first_date  = $prior_date;
													$prior_block = bkap_common::bkap_get_betweendays( $prior_date, $booked_date );

													foreach ( $prior_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}

												if ( isset( $booking_settings['booking_later_days_to_book'] ) && $booking_settings['booking_later_days_to_book'] ) {
													$days         = '+' . $booking_settings['booking_later_days_to_book'] . ' days';
													$late_date    = date( 'j-n-Y', strtotime( $days, strtotime( $booked_date ) ) );
													$end_date_new = date( 'j-n-Y', strtotime( '+1 day', strtotime( $booked_date ) ) );
													$later_block  = bkap_common::bkap_get_betweendays( $end_date_new, $late_date );

													foreach ( $later_block as $block_key => $block_value ) {
														$dates[] = $block_value;
													}
												}
											}

											if ( is_numeric( $get_dates[0]->meta_value ) ) {
												if ( is_array( $dates ) && count( $dates ) > 0 ) {
													foreach ( $dates as $array_key => $array_value ) {
														$list_dates[ $array_value ] = $get_dates[0]->meta_value;
													}
												}
												$list_dates[ $booked_date ] = $get_dates[0]->meta_value;
											}
										}
									}
								}
							}

							// create/edit a final array which contains each date once and the value is the qty for which the order has been placed for this date
							if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {

								foreach ( $list_dates as $date_key => $qty_value ) {

									$date_key = date( 'j-n-Y', strtotime( $date_key ) );

									if ( is_array( $qty_value ) && count( $qty_value ) > 0 ) {

										foreach ( $qty_value as $k => $v ) {
											// check if the date is already present in the array, if yes, then edit the qty
											if ( array_key_exists( $date_key, $total_bookings ) && array_key_exists( ( $k ), $total_bookings[ $date_key ] ) ) {
												$qty_present                       = $total_bookings[ $date_key ][ $k ];
												$new_qty                           = $qty_present + $v;
												$total_bookings[ $date_key ][ $k ] = $new_qty;
											} else {
												$total_bookings[ $date_key ][ $k ] = $v;
											}
										}
									} else {

										// check if the date is already present in the array, if yes, then edit the qty
										if ( array_key_exists( $date_key, $total_bookings ) ) {
											$qty_present                 = $total_bookings[ $date_key ];
											$new_qty                     = $qty_present + $qty_value;
											$total_bookings[ $date_key ] = $new_qty;
										} else { // else create a new entry in the array

											$date_key                    = date( 'j-n-Y', strtotime( $date_key ) );
											$total_bookings[ $date_key ] = $qty_value;
										}
									}
								}
							}

							// create/edit a final array for the checkout calendar which contains each date once and the value is the qty for which the order has been placed for this date
							if ( isset( $list_dates_checkout ) && is_array( $list_dates_checkout ) && count( $list_dates_checkout ) > 0 ) {

								foreach ( $list_dates_checkout as $date_key => $qty_value ) {
									$date_key = date( 'j-n-Y', strtotime( $date_key ) );
									// check if the date is already present in the array, if yes, then edit the qty
									if ( array_key_exists( $date_key, $total_bookings_checkout ) ) {
										$qty_present                          = $total_bookings_checkout[ $date_key ];
										$new_qty                              = $qty_present + $qty_value;
										$total_bookings_checkout[ $date_key ] = $new_qty;
									} else { // else create a new entry in the array

										$date_key                             = date( 'j-n-Y', strtotime( $date_key ) );
										$total_bookings_checkout[ $date_key ] = $qty_value;
									}
								}
							}
						}
					}
				}

				$lockout_reached_dates          = '';
				$bookings_placed                = '';
				$lockout_reached_dates_checkout = '';
				$lockout_reached_time_slots     = '';

				// create 2 fields one is the list of dates for which lockout is reached
				// second is the date and the number of bookings already placed
				if ( isset( $total_bookings ) && is_array( $total_bookings ) && count( $total_bookings ) > 0 ) {

					foreach ( $total_bookings as $date_key => $qty_value ) {

						if ( is_array( $qty_value ) && count( $qty_value ) > 0 ) {
							$time_slot_total_booked = 0;

							foreach ( $qty_value as $k => $v ) {

								$time_slot_total_booked += $v;
								$bookings_placed        .= '"' . $date_key . '"=>' . $k . '=>' . $v . ',';

								if ( $variation_lockout <= $v ) {
									// time slot should be blocked once lockout is reached
									$lockout_reached_time_slots .= $date_key . '=>' . $k . ',';
									// date should be blocked only when all the time slots are fully booked
									// run a loop through all the time slots created for that date/day and check if lockout is reached for that variation
									if ( isset( $booking_settings['booking_time_settings'] ) && is_array( $booking_settings['booking_time_settings'] ) && count( $booking_settings['booking_time_settings'] ) > 0 ) {
										$time_settings = $booking_settings['booking_time_settings'];

										if ( array_key_exists( $date_key, $time_settings ) ) {

											if ( array_key_exists( $date_key, $time_settings ) ) {
												$number_of_slots = count( $time_settings[ $date_key ] );
												// total time slot lockout for the variation is the number of slots * the lockout
												$total_lockout = $number_of_slots * $variation_lockout;

											}
										} else {
											// get the recurring weekday
											$day_number = date( 'w', strtotime( $date_key ) );
											$weekday    = 'booking_weekday_' . $day_number;

											if ( array_key_exists( $weekday, $time_settings ) ) {
												$number_of_slots = count( $time_settings[ $weekday ] );
												// total time slot lockout for the variation is the number of slots * the lockout
												$total_lockout = $number_of_slots * $variation_lockout;

											}
										}

										// if reached then add it to the list of dates to be locked
										if ( isset( $total_lockout ) && ( $total_lockout <= $time_slot_total_booked ) ) {
											$lockout_reached_dates .= '"' . $date_key . '",';
										}
									}
								}
							}
						} else {
							$bookings_placed .= '"' . $date_key . '"=>' . $qty_value . ',';
							if ( $variation_lockout <= $qty_value ) {
								$lockout_reached_dates .= '"' . $date_key . '",';
							}
						}
					}
				}
				if ( isset( $total_bookings_checkout ) && is_array( $total_bookings_checkout ) && count( $total_bookings_checkout ) > 0 ) {
					foreach ( $total_bookings_checkout as $date_key => $qty_value ) {
						if ( $variation_lockout <= $qty_value ) {
							$lockout_reached_dates_checkout .= '"' . $date_key . '",';
						}
					}
				}

				$return_variation_lockout = array();

				$return_variation_lockout['wapbk_lockout_']          = $lockout_reached_dates;
				$return_variation_lockout['wapbk_lockout_checkout_'] = $lockout_reached_dates_checkout;
				$return_variation_lockout['wapbk_timeslot_lockout_'] = $lockout_reached_time_slots;
				$return_variation_lockout['wapbk_bookings_placed_']  = $bookings_placed;

				return $return_variation_lockout;
			} else {
				$return_variation_lockout = array(
					'wapbk_lockout_'          => '',
					'wapbk_lockout_checkout_' => '',
					'wapbk_timeslot_lockout_' => '',
					'wapbk_bookings_placed_'  => '',
				);
				return $return_variation_lockout;
			}
		}

	}
}
$bkap_variations = new bkap_variations();
?>
