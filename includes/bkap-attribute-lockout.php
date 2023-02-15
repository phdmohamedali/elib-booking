<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Attribute Level Lockout for Variable Products
 *
 * @author   Tyche Softwares
 * @package  BKAP/Attribute-Lockout
 * @category Classes
 */

if ( ! class_exists( 'bkap_attributes' ) ) {

	/**
	 * Class for Attribute Level lockout
	 *
	 * @class bkap_attributes
	 */
	class bkap_attributes {

		/**
		 * Default constructor
		 *
		 * @since 2.5.3
		 */

		public function __construct() {

			// add Attribute level fields in the Product data, Attributes Tab
			add_action( 'woocommerce_product_options_attributes', array( &$this, 'add_attribute_fields' ) );

			// print hidden fields on the front end product page
			add_action( 'bkap_print_hidden_fields', array( &$this, 'print_attribute_lockout' ), 12, 1 );

			// validations on the product page
			add_action( 'bkap_multiple_days_product_validation', array( &$this, 'validate_attribute_multiple_days_product_page' ), 12 );
			add_action( 'bkap_single_days_product_validation', array( &$this, 'validate_attribute_single_days_product_page' ), 12 );
			add_action( 'bkap_date_time_product_validation', array( &$this, 'validate_attribute_date_time_product_page' ), 12 );

			// validation on the cart page
			add_action( 'bkap_multiple_days_cart_validation', array( &$this, 'validate_attribute_multiple_days_cart_page' ), 12 );
			add_action( 'bkap_single_days_cart_validation', array( &$this, 'validate_attribute_single_days_cart_page' ), 12 );
			add_action( 'bkap_date_time_cart_validation', array( &$this, 'validate_attribute_date_time_cart_page' ), 12 );
		}

		/**
		 * Adds the fields from the Booking plugin to be displayed in
		 * Product Data->Attributes
		 *
		 * @hook woocommerce_product_options_attributes
		 * @since 2.5.3
		 */

		function add_attribute_fields() {
			global $post;
			$duplicate_of = bkap_common::bkap_get_product_id( $post->ID );
			// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
			$attributes = get_post_meta( $duplicate_of, '_product_attributes', true );
			// Product Attributes - Booking Settings
			$attribute_booking_data = get_post_meta( $duplicate_of, '_bkap_attribute_settings', true );

			?>
			<script type="text/javascript">
					// add a field using jQuery
					jQuery(document).ready(function() {
						<?php
						// Output All Set Attributes
						if ( ! empty( $attributes ) ) {
							$attribute_keys  = array_keys( $attributes );
							$attribute_total = sizeof( $attribute_keys );

							for ( $i = 0; $i < $attribute_total; $i ++ ) {
								$attribute_name = $attribute_keys[ $i ];
								$attribute      = $attributes[ $attribute_keys[ $i ] ];

								$position = empty( $attribute['position'] ) ? 0 : absint( $attribute['position'] );
								$lockout  = 0;

								if ( isset( $attribute_booking_data[ $attribute_name ]['booking_lockout_as_value'] ) && 'on' == $attribute_booking_data[ $attribute_name ]['booking_lockout_as_value'] ) {
									$enable_booking_as_qty = 'checked';
								} else {
									$enable_booking_as_qty = '';
								}

								if ( isset( $attribute_booking_data[ $attribute_name ]['booking_lockout'] ) ) {
									$lockout = $attribute_booking_data[ $attribute_name ]['booking_lockout'];
								}
								?>
								var field_name = "attribute_variation[<?php echo $position; ?>]";

								jQuery("<br><label><input type='checkbox' class='checkbox' name='attribute_enable_qty[<?php echo $position; ?>]' <?php echo $enable_booking_as_qty; ?> onClick='bkap_attrchk(this)' /> <?php _e( 'Equate Booking Lockout with Attribute value(s)', 'woocommerce-booking' ); ?></label><br><label><input type='number' name='attribute_bkap_lockout[<?php echo $position; ?>]' value='<?php echo $lockout; ?>' /> <?php _e( 'Booking Lockout for Attribute', 'woocommerce-booking' ); ?></label>").insertAfter(jQuery('[name="'+field_name+'"]').closest("label"));
								<?php
								// Enable/Disable the lockout text field based on the checkbox value.
								if ( '' == $enable_booking_as_qty ) {
									?>
									jQuery( "[name='attribute_bkap_lockout[<?php echo $position; ?>]']" ).prop( "disabled", true );
									<?php
								}
							}
						}
						?>
						jQuery( ".save_attributes" ).on( "click", function () {
							var enable_qty = '';
							var attr_lockout = '';
							<?php
							if ( ! empty( $attributes ) ) {
								$attribute_keys  = array_keys( $attributes );
								$attribute_total = sizeof( $attribute_keys );

								for ( $i = 0; $i < $attribute_total; $i ++ ) {
									$attribute = $attributes[ $attribute_keys[ $i ] ];
									$position  = empty( $attribute['position'] ) ? 0 : absint( $attribute['position'] );
									?>
									if ( jQuery( "[name='attribute_enable_qty[<?php echo $position; ?>]']" ).prop( 'checked' ) ) {
										enable_qty += 'checked' + ',';
									} else {
										enable_qty += 'undefined' + ',';
									}									
									attr_lockout += jQuery( "[name='attribute_bkap_lockout[<?php echo $position; ?>]']" ).val() + ',';

									<?php
								}

								?>
								var data = {
										enable_qty: enable_qty,
										attr_lockout: attr_lockout,
										post_id: <?php echo $duplicate_of; ?>, 
										action: "bkap_save_attribute_data"
										};
								jQuery.post("<?php get_admin_url(); ?>admin-ajax.php", data, function(response) {
								});
								<?php
							}
							?>
						});
		
					});

					// Enable/Disable the lockout text field based on the checkbox value
					function bkap_attrchk(chk) {
						<?php
						if ( ! empty( $attributes ) ) {
							$attribute_keys  = array_keys( $attributes );
							$attribute_total = sizeof( $attribute_keys );

							for ( $i = 0; $i < $attribute_total; $i ++ ) {
								$attribute = $attributes[ $attribute_keys[ $i ] ];
								$position  = empty( $attribute['position'] ) ? 0 : absint( $attribute['position'] );
								?>

									if ( !jQuery( "input[name='attribute_enable_qty[<?php echo $position; ?>]']" ).prop( "checked" ) ) {
										jQuery( "[name='attribute_bkap_lockout[<?php echo $position; ?>]']" ).prop( "disabled", true );
									} else if ( jQuery( "input[name='attribute_enable_qty[<?php echo $position; ?>]']" ).prop( "checked" ) ) {
										jQuery( "[name='attribute_bkap_lockout[<?php echo $position; ?>]']" ).removeAttr( "disabled" );
									}
									<?php
							}
						}
						?>
					}
				</script>
				<?php
		}


		/**
		 * Save the booking data when attributes are being saved
		 *
		 * @since 2.5.3
		 */

		public static function bkap_save_attribute_data() {
			$attribute_value_as_qty = explode( ',', $_POST['enable_qty'] );
			$attr_lockout           = explode( ',', $_POST['attr_lockout'] );

			$product_id = $_POST['post_id'];

			$attributes = get_post_meta( $product_id, '_product_attributes', true );

			// Output All Set Attributes
			if ( ! empty( $attributes ) ) {
				$attribute_keys  = array_keys( $attributes );
				$attribute_total = sizeof( $attribute_keys );

				$booking_data = array();
				for ( $i = 0; $i < $attribute_total; $i ++ ) {
					$attribute_name = $attribute_keys[ $i ];
					$attribute      = $attributes[ $attribute_keys[ $i ] ];

					if ( 'undefined' == $attribute_value_as_qty[ $i ] ) {
						$attribute_value_as_qty[ $i ] = 'off';
					} elseif ( 'checked' == $attribute_value_as_qty[ $i ] ) {
						$attribute_value_as_qty[ $i ] = 'on';
					}
					$booking_data[ $attribute_name ]['booking_lockout_as_value'] = $attribute_value_as_qty[ $i ];

					if ( ! is_numeric( $attr_lockout[ $i ] ) ) {
						$attr_lockout[ $i ] = 0;
					}

					$booking_data[ $attribute_name ]['booking_lockout'] = $attr_lockout[ $i ];
				}
			}

			update_post_meta( $product_id, '_bkap_attribute_settings', $booking_data );

			die();
		}

		/**
		 * Add hidden fields on the product page with attribute booking & lockout details
		 *
		 * @hook bkap_print_hidden_fields
		 * @since 2.5.3
		 */

		function print_attribute_lockout( $product_id ) {
			global $wpdb;
			// get product type
			$product      = wc_get_product( $product_id );
			$product_type = $product->get_type();

			// Booking settings
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			// for a variable and bookable product
			if ( 'variable' == $product_type && isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) {

				// check if lockout is set at the attribute level
				// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
				$attributes = get_post_meta( $product_id, '_product_attributes', true );
				// Product Attributes - Booking Settings
				$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

				if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

					foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
						$attr_name = urldecode( $attr_name );
						// if yes, then create hidden fields for each attribute, which will contain the number of bookings received for each date
						if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] && isset( $attr_settings['booking_lockout'] ) && $attr_settings['booking_lockout'] > 0 ) {
							$attr_lockout = $attr_settings['booking_lockout'];

							// fetch the total placed orders for the attribute

							// get all the dates for which bookings have been made for this variation ID
							$query_get_order_item_ids = 'SELECT order_item_id, meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
    												WHERE meta_key = %s';
							$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $attr_name ) );

							$total_bookings = $total_bookings_checkout = array();

							// once u hv a list of all the orders placed for a given attribute, create a list of dates and compare it with lockout
							if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {

								foreach ( $get_order_item_ids as $item_key => $item_value ) {

									$item = new WC_Order_Item_Product( $item_value->order_item_id );

									// The product ID.
									$item_product_id = $item->get_product_id();

									if ( $item_product_id != $product_id ) {
										continue;
									}

									// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
									$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
    													WHERE order_item_id = %d';
									$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

									// check the booking post status
									$booking_id = bkap_common::get_booking_id( $item_value->order_item_id );

									$booking_status = get_post_status( $booking_id );

									// check if it's a valid ID
									$order = wc_get_order( $get_order_id[0]->order_id );
									if ( $order && false !== $booking_status ) {

										if ( $order ) {
											$order_status = $order->get_status();
											$order_status = "wc-$order_status";
										}
										if ( isset( $order_status ) && ( $order_status != '' ) && ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

											// get the booking status
											$booking_status = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );

											if ( isset( $booking_status ) && 'cancelled' != $booking_status ) {

												$current_time = current_time( 'timestamp' );
												// check booking type and calculate lockout accordingly
												// multiple days
												if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
													// get the booking details for the given order item ID
													$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
            														WHERE meta_key IN (%s,%s,%s)
            														AND order_item_id = %d';

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

														// consider orders from today onwards i.e. ignore back dated orders
														if ( $current_time <= strtotime( $end_date ) ) {

															// if both start and end date is set then get the between days
															if ( isset( $start_date ) && $start_date != '' && isset( $end_date ) && $end_date != '' ) {
																$dates      = bkap_common::bkap_get_betweendays( $start_date, $end_date );
																$first_date = $start_date;

																// if rental addon is active
																if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {
																	// if charge per day is enabled, then the checkout date should also be disabled once lockout is reached
																	if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
																		$dates[] = $end_date;
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
																		$days         = '+' . $booking_settings['booking_later_days_to_book'] . ' days';
																		$late_date    = date( 'j-n-Y', strtotime( $days, strtotime( $end_date ) ) );
																		$end_date_new = date( 'j-n-Y', strtotime( '+1 day', strtotime( $end_date ) ) );
																		$later_block  = bkap_common::bkap_get_betweendays( $end_date_new, $late_date );
																		foreach ( $later_block as $block_key => $block_value ) {
																			$dates[] = $block_value;
																		}
																	}
																}

																// remove the first date (which is the start date so it can be enabled for the checkout calendar
																$checkout_dates = $dates;
																$key            = array_search( $first_date, $checkout_dates );

																if ( isset( $key ) && is_numeric( $key ) ) {
																	unset( $checkout_dates[ $key ] );
																}
															}

															if ( is_numeric( $item_value->meta_value ) ) {

																$total_qty = $item_value->meta_value;
																// if qty is greater than 1
																if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																	$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
																}
																if ( is_array( $dates ) && count( $dates ) > 0 ) {
																	foreach ( $dates as $array_key => $array_value ) {
																		$list_dates[ $array_value ] = $total_qty;
																	}
																	foreach ( $checkout_dates as $array_key => $array_value ) {
																		$list_dates_checkout[ $array_value ] = $total_qty;
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

														if ( strtotime( date( 'j-n-Y', $current_time ) ) <= strtotime( $booked_date ) ) {

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

															if ( is_numeric( $item_value->meta_value ) ) {

																if ( isset( $get_dates[2]->meta_value ) && $get_dates[2]->meta_value != '' ) {
																	$total_qty = $item_value->meta_value;
																	// if qty is greater than 1
																	if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																		$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
																	}

																	if ( is_array( $dates ) && count( $dates ) > 0 ) {
																		foreach ( $dates as $array_key => $array_value ) {
																			$list_dates[ $array_value ][ $get_dates[2]->meta_value ] = $total_qty;
																		}
																	}

																	$list_dates[ $booked_date ][ $get_dates[2]->meta_value ] = $total_qty;
																}
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

															if ( is_numeric( $item_value->meta_value ) ) {

																$total_qty = $item_value->meta_value;
																// if qty is greater than 1
																if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																	$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
																}
																if ( is_array( $dates ) && count( $dates ) > 0 ) {
																	foreach ( $dates as $array_key => $array_value ) {
																		$list_dates[ $array_value ] = $total_qty;
																	}
																}
																$list_dates[ $booked_date ] = $total_qty;
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

														$total_bookings_checkout[ $date_key ] = $qty_value;
													}
												}
											}
										}
									}
								}

								$dates_list = array();
								if ( isset( $_REQUEST['post'] ) && absint( $_REQUEST['post'] ) > 0 && get_post_type( $_REQUEST['post'] ) == 'bkap_booking' ) {
									$booking_post  = new BKAP_Booking( $_REQUEST['post'] );
									$booking_start = $booking_post->get_start();
									$booking_end   = $booking_post->get_end();

									$booking_start = date( 'j-n-Y', strtotime( $booking_start ) );
									$booking_end   = date( 'j-n-Y', strtotime( $booking_end ) );

									$dates_list = bkap_common::bkap_get_betweendays( $booking_start, $booking_end, 'j-n-Y' );

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
												if ( $attr_lockout <= $v ) {
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
																$total_lockout = $number_of_slots * $attr_lockout;

															}
														} else {
															// get the recurring weekday
															$day_number = date( 'w', strtotime( $date_key ) );
															$weekday    = 'booking_weekday_' . $day_number;
															if ( array_key_exists( $weekday, $time_settings ) ) {
																$number_of_slots = count( $time_settings[ $weekday ] );
																// total time slot lockout for the variation is the number of slots * the lockout
																$total_lockout = $number_of_slots * $attr_lockout;

															}
														}

														// if reached then add it to the list of dates to be locked
														if ( isset( $total_lockout ) && ( $total_lockout <= $time_slot_total_booked ) && ! in_array( $date_key, $dates_list ) ) {
															$lockout_reached_dates .= '"' . $date_key . '",';
														}
													}
												}
											}
										} else {
											$bookings_placed .= '"' . $date_key . '"=>' . $qty_value . ',';
											if ( $attr_lockout <= $qty_value && ! in_array( $date_key, $dates_list ) ) {
												$lockout_reached_dates .= '"' . $date_key . '",';
											}
										}
									}
								}

								if ( isset( $total_bookings_checkout ) && is_array( $total_bookings_checkout ) && count( $total_bookings_checkout ) > 0 ) {
									foreach ( $total_bookings_checkout as $date_key => $qty_value ) {
										if ( $attr_lockout <= $qty_value && ! in_array( $date_key, $dates_list ) ) {
											$lockout_reached_dates_checkout .= '"' . $date_key . '",';
										}
									}
								}

								print( "<input type='hidden' id='wapbk_lockout_" . $attr_name . "' name='wapbk_lockout_" . $attr_name . "' value='" . $lockout_reached_dates . "' />" );

								if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
									print( "<input type='hidden' id='wapbk_lockout_checkout_" . $attr_name . "' name='wapbk_lockout_checkout_" . $attr_name . "' value='" . $lockout_reached_dates_checkout . "' />" );
								}

								if ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) {
									print( "<input type='hidden' id='wapbk_timeslot_lockout_" . $attr_name . "' name='wapbk_timeslot_lockout_" . $attr_name . "' value='" . $lockout_reached_time_slots . "' />" );
								}
								print( "<input type='hidden' id='wapbk_bookings_placed_" . $attr_name . "' name='wapbk_bookings_placed_" . $attr_name . "' value='" . $bookings_placed . "' />" );
							}
						}
					}
				}
			}
		}

		/**
		 * Validate attribute availability when product is added to the cart
		 * for Multiple Day Booking Products
		 *
		 * @hook bkap_multiple_days_product_validation
		 * @since 2.5.3
		 */

		function validate_attribute_multiple_days_product_page() {

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

			if ( ! isset( $_POST['validated'] ) ) {
				$_POST['validated'] = 'NO';
			}
			$_product = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;

			// if variable product
			if ( isset( $product_type ) && $product_type == 'variable' ) {
				// get the attribute names and their lockout values if set

				// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
				$attributes = get_post_meta( $_POST['product_id'], '_product_attributes', true );
				// Product Attributes - Booking Settings
				$attribute_booking_data = get_post_meta( $_POST['product_id'], '_bkap_attribute_settings', true );

				if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

					foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
						$attr_name      = urldecode( $attr_name );
						$attr_post_name = 'attribute_' . $attr_name;

						if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] && isset( $attr_settings['booking_lockout'] ) && $attr_settings['booking_lockout'] > 0 ) {
							$attr_lockout = $attr_settings['booking_lockout'];

							if ( isset( $attr_lockout ) && $attr_lockout > 0 ) { // lockout is set at the attribute level

								$_POST['validated'] = 'YES';

								$field_name = 'wapbk_bookings_placed_' . $attr_name;

								$bookings_placed = ( isset( $_POST[ $field_name ] ) ) ? $_POST[ $field_name ] : '';
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

								$date_availablity = array();

								// create an array of the current dates selected by the user
								$bookings_array = bkap_common::bkap_get_betweendays( $_POST['wapbk_hidden_date'], $_POST['wapbk_hidden_date_checkout'] );
								$check          = 'pass';
								foreach ( $bookings_array as $date_key => $date_value ) {

									$date_value                      = date( 'j-n-Y', strtotime( $date_value ) );
									$date_availablity[ $date_value ] = $attr_lockout;
									// qty
									$final_qty = 0;
									if ( isset( $_POST['quantity'] ) ) {
										$final_qty = $_POST['quantity'];
									}

									if ( isset( $_POST[ $attr_post_name ] ) && $_POST[ $attr_post_name ] > 0 && $final_qty > 0 ) {
										$final_qty = $final_qty * $_POST[ $attr_post_name ];
									}

									// add the number of already placed orders for that date
									if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
										if ( array_key_exists( $date_value, $date_array ) ) {
											$qty        = $date_array[ $date_value ];
											$final_qty += $qty;
										}
									}

									if ( $final_qty > $attr_lockout ) {
										if ( isset( $qty ) && $qty > 0 ) {
											$availability = $attr_lockout - $qty;
										} else {
											$availability = $attr_lockout;
										}
										$date_availablity[ $date_value ] = $availability;
										$quantity_check_pass             = 'no';
										$check                           = 'failed';
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
										$check_in_to_display  = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date'] ) );
										$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date_checkout'] ) );
										$date_range           = "$check_in_to_display to $check_out_to_display";

										$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
										$attr_label = wc_attribute_label( $attr_name, $_product );

										$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $least_availability, $attr_label, $date_range ), $msg_text );
										wc_add_notice( $message, $notice_type = 'error' );
									}
								}
								// check if the same product has been added to the cart for the same dates
								if ( $quantity_check_pass == 'yes' ) {
									foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
										if ( isset( $values['bkap_booking'] ) ) {
											$booking = $values['bkap_booking'];
										}
										if ( is_array( $values['variation'] ) && count( $values['variation'] > 0 ) ) {
											if ( isset( $values['variation'][ $attr_post_name ] ) && $values['variation'][ $attr_post_name ] > 0 ) {
												$quantity = $values['quantity'] * $values['variation'][ $attr_post_name ];
											} else {
												$quantity = $values['quantity'];
											}
										}

										$product_id_added = $values['product_id'];

										if ( isset( $booking[0]['hidden_date'] ) && isset( $booking[0]['hidden_date_checkout'] ) ) {
											$hidden_date          = $booking[0]['hidden_date'];
											$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
											$dates                = bkap_common::bkap_get_betweendays( $booking[0]['hidden_date'], $booking[0]['hidden_date_checkout'] );

											if ( $_POST['product_id'] == $product_id_added ) {
												$date_availablity = array();
												$check            = 'pass';

												foreach ( $bookings_array as $date_key => $date_value ) {
													$date_availablity[ $date_value ] = $attr_lockout;
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
													if ( $final_qty > $attr_lockout ) {
														if ( isset( $qty ) && $qty > 0 ) {
															$availability = $attr_lockout - $qty;
														} else {
															$availability = $attr_lockout;
														}
														$date_availablity[ $date_value ] = $availability;
														$quantity_check_pass             = 'no';
														$check                           = 'failed';
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
														$check_in_to_display  = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date'] ) );
														$check_out_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $_POST['wapbk_hidden_date_checkout'] ) );
														$date_range           = "$check_in_to_display to $check_out_to_display";

														$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
														$attr_label = wc_attribute_label( $attr_name, $_product );

														$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $least_availability, $attr_label, $date_range ), $msg_text );
														wc_add_notice( $message, $notice_type = 'error' );
													}
												}
											}
										}
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
		 * Validate the availability of the attrbiutes when the product is added to the Cart
		 * for Single Day Booking Products
		 *
		 * @hook bkap_single_days_product_validation
		 * @since 2.5.3
		 */

		function validate_attribute_single_days_product_page() {

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

			if ( ! isset( $_POST['validated'] ) ) {
				$_POST['validated'] = 'NO';
			}
			$_product = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;

			// if variable product
			if ( isset( $product_type ) && $product_type == 'variable' ) {
				// get the attribute names and their lockout values if set

				// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
				$attributes = get_post_meta( $_POST['product_id'], '_product_attributes', true );
				// Product Attributes - Booking Settings
				$attribute_booking_data = get_post_meta( $_POST['product_id'], '_bkap_attribute_settings', true );

				if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

					foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
						$attr_name      = urldecode( $attr_name );
						$attr_post_name = 'attribute_' . $attr_name;

						if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] && isset( $attr_settings['booking_lockout'] ) && $attr_settings['booking_lockout'] > 0 ) {
							$attr_lockout = $attr_settings['booking_lockout'];

							if ( isset( $attr_lockout ) && $attr_lockout > 0 ) { // lockout is set at the attribute level

								$_POST['validated'] = 'YES';

								$field_name = 'wapbk_bookings_placed_' . $attr_name;

								$bookings_placed = '';
								if ( isset( $_POST[ $field_name ] ) ) {
									$bookings_placed = $_POST[ $field_name ];
								}

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

								// booking date
								$booking_date = $_POST['wapbk_hidden_date'];
								// qty
								$final_qty = 0;
								if ( isset( $_POST['quantity'] ) ) {
									$final_qty = $_POST['quantity'];
								}

								if ( isset( $_POST[ $attr_post_name ] ) && $_POST[ $attr_post_name ] > 0 && $final_qty > 0 ) {
									$final_qty = $final_qty * $_POST[ $attr_post_name ];
								}

								// add the number of already placed orders for that date
								if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
									if ( array_key_exists( $booking_date, $date_array ) ) {
										$qty        = $date_array[ $booking_date ];
										$final_qty += $qty;
									}
								}

								// now check if the final qty exceeds the lockout value
								if ( $final_qty > $attr_lockout ) {
									if ( isset( $qty ) && $qty > 0 ) {
										$availability = $attr_lockout - $qty;
									} else {
										$availability = $attr_lockout;
									}

									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $availability, $attr_label, $date_to_display ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
									$quantity_check_pass                  = 'no';
								}

								// check if the same product has been added to the cart for the same dates
								if ( 'yes' == $quantity_check_pass ) {

									foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

										if ( isset( $values['bkap_booking'] ) ) {
											$booking = $values['bkap_booking'];
										}

										if ( is_array( $values['variation'] ) && count( $values['variation'] ) > 0 ) {
											if ( isset( $values['variation'][ $attr_post_name ] ) && $values['variation'][ $attr_post_name ] > 0 ) {
												$quantity = $values['quantity'] * $values['variation'][ $attr_post_name ];
											} else {
												$quantity = $values['quantity'];
											}
										}

										$product_id_added = $values['product_id'];

										if ( isset( $booking[0]['hidden_date'] ) ) {

											$hidden_date = $booking[0]['hidden_date'];

											if ( $_POST['product_id'] == $product_id_added ) {

												// add the number of already placed orders for that date
												if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
													if ( array_key_exists( $booking_date, $date_array ) ) {
														$qty        = $date_array[ $booking_date ];
														$final_qty += $qty; // add the already placed orders to the qty the customer selected
													}
												}

												// add the qty from the item in the cart
												if ( isset( $hidden_date ) && $hidden_date != '' ) {
													if ( $hidden_date == $booking_date ) {
														$final_qty += $quantity; // add the qty in the cart
													}
												}

												if ( $final_qty > $attr_lockout ) {
													if ( isset( $qty ) && $qty > 0 ) {
														$availability = $attr_lockout - $qty;
													} else {
														$availability = $attr_lockout;
													}
													$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

													$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
													$attr_label = wc_attribute_label( $attr_name, $_product );

													$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $availability, $attr_label, $date_to_display ), $msg_text );
													wc_add_notice( $message, $notice_type = 'error' );
													$quantity_check_pass                  = 'no';
												}
											}
										}
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
		 * Validate the attribute availability when product is added to the Cart
		 * for Date & Time Booking Products
		 *
		 * @hook bkap_date_time_product_validation
		 * @since 2.5.3
		 */

		function validate_attribute_date_time_product_page() {

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

			if ( ! isset( $_POST['validated'] ) ) {
				$_POST['validated'] = 'NO';
			}
			$_product = wc_get_product( $_POST['product_id'] );

			$post_title = get_post( $_POST['product_id'] );
			// get the product type
			$product_type = $_product->get_type();
			$variation_id = 0;

			// if variable product
			if ( isset( $product_type ) && $product_type == 'variable' ) {
				// get the attribute names and their lockout values if set

				// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
				$attributes = get_post_meta( $_POST['product_id'], '_product_attributes', true );
				// Product Attributes - Booking Settings
				$attribute_booking_data = get_post_meta( $_POST['product_id'], '_bkap_attribute_settings', true );

				if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

					foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
						$attr_name      = urldecode( $attr_name );
						$attr_post_name = 'attribute_' . $attr_name;

						if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] && isset( $attr_settings['booking_lockout'] ) && $attr_settings['booking_lockout'] > 0 ) {
							$attr_lockout = $attr_settings['booking_lockout'];

							if ( isset( $attr_lockout ) && $attr_lockout > 0 ) { // lockout is set at the attribute level.

								$_POST['validated'] = 'YES';

								$field_name = 'wapbk_bookings_placed_' . $attr_name;
								if ( isset( $_POST[ $field_name ] ) ) {
									$bookings_placed = $_POST[ $field_name ];
								}
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

								$booking_date = $_POST['wapbk_hidden_date']; // booking date.
								$booking_time = ''; // booking time.

								if ( isset( $_POST['time_slot'] ) ) {
									$booking_time  = $_POST['time_slot'];
									$exploded_time = explode( '-', $booking_time );
									$booking_time  = bkap_date_as_format( trim( $exploded_time[0] ), 'H:i' );

									if ( isset( $exploded_time[1] ) && '' !== $exploded_time[1] ) {
										$booking_time .= ' - ' . bkap_date_as_format( trim( $exploded_time[1] ), 'H:i' );
									}
								}

								$final_qty = isset( $_POST['quantity'] ) ? $_POST['quantity'] : 0;

								if ( isset( $_POST[ $attr_post_name ] ) && $_POST[ $attr_post_name ] > 0 ) {
									$final_qty = $final_qty * $_POST[ $attr_post_name ];
								}

								// add the number of already placed orders for that date.
								if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
									if ( array_key_exists( $booking_date, $date_array ) ) {
										if ( array_key_exists( $booking_time, $date_array[ $booking_date ] ) ) {
											$qty        = $date_array[ $booking_date ][ $booking_time ];
											$final_qty += $qty;
										}
									}
								}

								// now check if the final qty exceeds the lockout value
								if ( $final_qty > $attr_lockout ) {
									if ( isset( $qty ) && $qty > 0 ) {
										$availability = $attr_lockout - $qty;
									} else {
										$availability = $attr_lockout;
									}
									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-time-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE', 'TIME' ), array( $post_title->post_title, $availability, $attr_label, $date_to_display, $_POST['time_slot'] ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
									$quantity_check_pass                  = 'no';
								}

								// check if the same product has been added to the cart for the same date and time
								if ( 'yes' == $quantity_check_pass ) {
									foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
										if ( isset( $values['bkap_booking'] ) ) {
											$booking = $values['bkap_booking'];
										}

										if ( is_array( $values['variation'] ) && count( $values['variation'] ) > 0 ) {
											if ( isset( $values['variation'][ $attr_post_name ] ) && $values['variation'][ $attr_post_name ] > 0 ) {
												$quantity = $values['quantity'] * $values['variation'][ $attr_post_name ];
											} else {
												$quantity = $values['quantity'];
											}
										}

										$prodouct_id_added = $values['product_id'];

										if ( isset( $booking[0]['hidden_date'] ) && isset( $booking[0]['time_slot'] ) ) {
											$hidden_date = $booking[0]['hidden_date'];
											$hidden_time = $booking[0]['time_slot'];

											if ( $_POST['product_id'] == $prodouct_id_added ) {
												// $final_qty = $_POST[ 'quantity' ]; // change here

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

												if ( $final_qty > $attr_lockout ) {
													if ( isset( $qty ) && $qty > 0 ) {
														$availability = $attr_lockout - $qty;
													} else {
														$availability = $attr_lockout;
													}
													$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

													$msg_text   = __( get_option( 'book_limited-booking-msg-time-attr' ), 'woocommerce-booking' );
													$attr_label = wc_attribute_label( $attr_name, $_product );

													$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE', 'TIME' ), array( $post_title->post_title, $availability, $attr_label, $date_to_display, $_POST['time_slot'] ), $msg_text );
													wc_add_notice( $message, $notice_type = 'error' );
													$quantity_check_pass                  = 'no';
												}
											}
										}
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
		 * Validate attribute availability on the Cart and Checkout pages
		 * for Multiple Day Booking products
		 *
		 * @hook bkap_multiple_days_cart_validation
		 * @since 2.5.3
		 */

		function validate_attribute_multiple_days_cart_page() {

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

			$cart_details = WC()->cart->cart_contents[ $cart_item_key ];

			// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
			$attributes = get_post_meta( $product_id, '_product_attributes', true );

			$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

			if ( is_array( $cart_details['variation'] ) && count( $cart_details['variation'] ) > 0 ) {
				foreach ( $cart_details['variation'] as $attr_name => $attr_value ) {
					$attr_name = str_replace( 'attribute_', '', $attr_name );

					if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
						if ( isset( $attribute_booking_data[ $attr_name ] ) ) {
							$attr_lockout      = $attribute_booking_data[ $attr_name ]['booking_lockout'];
							$attr_value_as_qty = $attribute_booking_data[ $attr_name ]['booking_lockout_as_value'];
						}
					}

					if ( isset( $attr_value_as_qty ) && 'on' == $attr_value_as_qty && isset( $attr_lockout ) && $attr_lockout > 0 ) {

						if ( $attr_value > 0 ) {
							$attr_qty_booked = $attr_value * $_POST['quantity'];
						} else {
							$attr_qty_booked = $attr_value;
						}

						if ( is_numeric( $attr_qty_booked ) ) {
							$validation_completed = 'YES';

							// Booking settings
							$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

							$post_title = get_post( $product_id );

							// get all the dates for which bookings have been made for this variation ID
							$query_get_order_item_ids = 'SELECT order_item_id, meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
    												WHERE meta_key = %s';
							$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $attr_name ) );

							$total_bookings = $total_bookings_checkout = array();

							// once u hv a list of all the orders placed for a given attribute, create a list of dates and compare it with lockout
							if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {

								foreach ( $get_order_item_ids as $item_key => $item_value ) {

									// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
									$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
    													WHERE order_item_id = %d';
									$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

									// check the booking post status
									$booking_id = bkap_common::get_booking_id( $item_value->order_item_id );

									$booking_status = get_post_status( $booking_id );

									// check if it's a valid ID
									$order = wc_get_order( $get_order_id[0]->order_id );
									if ( $order ) {

										$order_status = $order->get_status();
										$order_status = "wc-$order_status";
										
										if ( ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

											// get the booking status
											$booking_status = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );

											if ( isset( $booking_status ) && 'cancelled' != $booking_status ) {

												// get the booking details for the given order item ID
												$query_get_dates = 'SELECT meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
                														WHERE meta_key IN (%s,%s,%s)
                														AND order_item_id = %d';

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
													$current_time = current_time( 'timestamp' );

													// consider orders from today onwards i.e. ignore back dated orders
													if ( $current_time <= strtotime( $end_date ) ) {

														// if both start and end date is set then get the between days
														if ( isset( $start_date ) && $start_date != '' && isset( $end_date ) && $end_date != '' ) {
															$dates      = bkap_common::bkap_get_betweendays( $start_date, $end_date );
															$first_date = $start_date;

															// if rental addon is active
															if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {
																// if charge per day is enabled, then the checkout date should also be disabled once lockout is reached
																if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' == $booking_settings['booking_charge_per_day'] ) {
																	$dates[] = $end_date;
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

														if ( is_numeric( $item_value->meta_value ) ) {

															$total_qty = $item_value->meta_value;
															// if qty is greater than 1
															if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
															}
															if ( is_array( $dates ) && count( $dates ) > 0 ) {
																foreach ( $dates as $array_key => $array_value ) {
																	$list_dates[ $array_value ] = $total_qty;
																}
															}
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
															$date_key                    = date( 'j-n-Y', strtotime( $date_key ) );
															$total_bookings[ $date_key ] = $new_qty;
														}
														// else create a new entry in the array
														else {
															$date_key                    = date( 'j-n-Y', strtotime( $date_key ) );
															$total_bookings[ $date_key ] = $qty_value;
														}
													}
												}
											}
										}
									}
								}

								$date_availablity = array();
								$check            = 'pass';

								foreach ( $order_dates as $k => $v ) {
									$date_availablity[ $v ] = $attr_lockout;
									$final_qty              = $attr_qty_booked;

									$v = date( 'j-n-Y', strtotime( $v ) );
									if ( array_key_exists( $v, $total_bookings ) ) {
										$qty_ordered = $total_bookings[ $v ];
										$final_qty  += $total_bookings[ $v ];

									}

									if ( $attr_lockout > 0 && $attr_lockout < $final_qty ) {
										if ( isset( $qty_ordered ) && $qty_ordered > 0 ) {
											$available_tickets = $attr_lockout - $qty_ordered;
										} else {
											$available_tickets = $attr_lockout;
										}
										$date_availablity[ $v ] = $available_tickets;
										$check                  = 'failed';
									}
								}
							} else {
								$date_availablity = array();
								$check            = 'pass';

								foreach ( $order_dates as $k => $v ) {
									$date_availablity[ $v ] = $attr_lockout;
									if ( $attr_lockout > 0 && $attr_lockout < $attr_qty_booked ) {
										$available_tickets      = $attr_lockout;
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

									$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $least_availability, $attr_label, $date_range ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
								}
							}
						}
					}
				}
			}
			$_POST['validation_status'] = $validation_completed;
		}

		/**
		 * Validate attribute availability on Cart & checkout pages
		 * for Single Day Booking Product
		 *
		 * @hook bkap_single_days_cart_validation
		 * @since 2.5.3
		 */

		function validate_attribute_single_days_cart_page() {

			global $wpdb;

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$cart_item_key = $_POST['cart_item_key'];
			$booking_date  = $_POST['booking_date'];
			$product_id    = $_POST['product_id'];

			$cart_details = WC()->cart->cart_contents[ $cart_item_key ];

			// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
			$attributes = get_post_meta( $product_id, '_product_attributes', true );

			$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

			if ( ! isset( $_POST['validation_status'] ) ) {
				$validation_completed = 'NO';
			} else {
				$validation_completed = $_POST['validation_status'];
			}

			$current_time = current_time( 'timestamp' );

			if ( is_array( $cart_details['variation'] ) && count( $cart_details['variation'] ) > 0 ) {
				foreach ( $cart_details['variation'] as $attr_name => $attr_value ) {
					$attr_name = str_replace( 'attribute_', '', $attr_name );

					if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
						if ( isset( $attribute_booking_data[ $attr_name ] ) ) {
							$attr_lockout      = $attribute_booking_data[ $attr_name ]['booking_lockout'];
							$attr_value_as_qty = $attribute_booking_data[ $attr_name ]['booking_lockout_as_value'];
						}
					}

					if ( isset( $attr_value_as_qty ) && 'on' == $attr_value_as_qty && isset( $attr_lockout ) && $attr_lockout > 0 ) {

						if ( $attr_value > 0 ) {
							$attr_qty_booked = $attr_value * $_POST['quantity'];
						} else {
							$attr_qty_booked = $attr_value;
						}

						if ( is_numeric( $attr_qty_booked ) ) {
							$validation_completed = 'YES';

							// Booking settings
							$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

							$post_title = get_post( $product_id );
							// get all the dates for which bookings have been made for this variation ID
							$query_get_order_item_ids = 'SELECT order_item_id, meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
        												WHERE meta_key = %s';
							$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $attr_name ) );

							$total_bookings = array();

							// once u hv a list of all the orders placed for a given attribute, create a list of dates and compare it with lockout
							if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {

								foreach ( $get_order_item_ids as $item_key => $item_value ) {

									// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
									$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
        													WHERE order_item_id = %d';
									$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

									// check the booking post status
									$booking_id = bkap_common::get_booking_id( $item_value->order_item_id );

									$booking_status = get_post_status( $booking_id );

									// check if it's a valid ID
									$order = wc_get_order( $get_order_id[0]->order_id );
									if ( $order && false !== $booking_status ) {
										$order = wc_get_order( $get_order_id[0]->order_id );

										if ( $order ) {
											$order_status = $order->get_status();
											$order_status = "wc-$order_status";
										}
										
										if ( isset( $order_status ) && ( $order_status != '' ) && ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

											// get the booking status
											$booking_status = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );

											if ( isset( $booking_status ) && 'cancelled' != $booking_status ) {

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

														if ( is_numeric( $item_value->meta_value ) ) {

															$total_qty = $item_value->meta_value;
															// if qty is greater than 1
															if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
															}
															if ( is_array( $dates ) && count( $dates ) > 0 ) {
																foreach ( $dates as $array_key => $array_value ) {
																	$list_dates[ $array_value ] = $total_qty;
																}
															}
															$list_dates[ $booked_date ] = $total_qty;
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
								}
								$final_qty = $attr_qty_booked;

								if ( array_key_exists( $booking_date, $total_bookings ) ) {
									$qty_ordered = $total_bookings[ $booking_date ];

									$final_qty += $total_bookings[ $booking_date ];
								}

								if ( $attr_lockout > 0 && $attr_lockout < $final_qty ) {
									if ( isset( $qty_ordered ) && $qty_ordered > 0 ) {
										$available_tickets = $attr_lockout - $qty_ordered;
									} else {
										$available_tickets = $attr_lockout;
									}
									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $available_tickets, $attr_label, $date_to_display ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
								}
							} else {

								if ( $attr_lockout > 0 && $attr_lockout < $attr_qty_booked ) {
									$available_tickets = $attr_lockout;
									$date_to_display   = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-date-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $post_title->post_title, $available_tickets, $attr_label, $date_to_display ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
								}
							}
						}
					}
				}
			}
			$_POST['validation_status'] = $validation_completed;
		}

		/**
		 * Validate attribute availability on Cart & Checkout pages
		 * for Date & Time Booking products
		 *
		 * @hook bkap_date_time_cart_validation
		 * @since 2.5.3
		 */

		function validate_attribute_date_time_cart_page() {

			global $wpdb;

			global $bkap_date_formats;

			$saved_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			if ( isset( $saved_settings ) ) {
				$date_format_to_display = $saved_settings->booking_date_format;
			} else {
				$date_format_to_display = 'mm/dd/y';
			}

			$cart_item_key = $_POST['cart_item_key'];
			$booking_date  = $_POST['booking_date'];
			$booking_time  = $_POST['time_slot'];
			$product_id    = $_POST['product_id'];

			$cart_details = WC()->cart->cart_contents[ $cart_item_key ];

			// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
			$attributes = get_post_meta( $product_id, '_product_attributes', true );

			$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

			if ( ! isset( $_POST['validation_status'] ) ) {
				$validation_completed = 'NO';
			} else {
				$validation_completed = $_POST['validation_status'];
			}

			$current_time = current_time( 'timestamp' );

			$booking_date = date( 'j-n-Y', strtotime( $booking_date ) );

			$exploded_time = explode( '-', $booking_time );
			$booking_time  = bkap_date_as_format( trim( $exploded_time[0] ), 'H:i' );
			if ( isset( $exploded_time[1] ) && $exploded_time[1] != '' ) {
				$booking_time .= ' - ' . bkap_date_as_format( trim( $exploded_time[1] ), 'H:i' );
			}

			if ( is_array( $cart_details['variation'] ) && count( $cart_details['variation'] ) > 0 ) {
				foreach ( $cart_details['variation'] as $attr_name => $attr_value ) {
					$attr_name = str_replace( 'attribute_', '', $attr_name );

					if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
						if ( isset( $attribute_booking_data[ $attr_name ] ) ) {
							$attr_lockout      = $attribute_booking_data[ $attr_name ]['booking_lockout'];
							$attr_value_as_qty = $attribute_booking_data[ $attr_name ]['booking_lockout_as_value'];
						}
					}

					if ( isset( $attr_value_as_qty ) && 'on' == $attr_value_as_qty && isset( $attr_lockout ) && $attr_lockout > 0 ) {

						if ( $attr_value > 0 ) {
							$attr_qty_booked = $attr_value * $_POST['quantity'];
						} else {
							$attr_qty_booked = $attr_value;
						}

						if ( is_numeric( $attr_qty_booked ) ) {
							$validation_completed = 'YES';

							// Booking settings
							$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

							$post_title = get_post( $product_id );
							// get all the dates for which bookings have been made for this variation ID
							$query_get_order_item_ids = 'SELECT order_item_id, meta_value FROM `' . $wpdb->prefix . 'woocommerce_order_itemmeta`
        												WHERE meta_key = %s';
							$get_order_item_ids       = $wpdb->get_results( $wpdb->prepare( $query_get_order_item_ids, $attr_name ) );

							$total_bookings = array();

							// once u hv a list of all the orders placed for a given attribute, create a list of dates and compare it with lockout
							if ( is_array( $get_order_item_ids ) && count( $get_order_item_ids ) > 0 ) {

								foreach ( $get_order_item_ids as $item_key => $item_value ) {

									// check if the order status is refunded, cancelled, failed or trashed, if yes, then ignore the order
									$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
    													WHERE order_item_id = %d';
									$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_value->order_item_id ) );

									// check the booking post status
									$booking_id = bkap_common::get_booking_id( $item_value->order_item_id );

									$booking_status = get_post_status( $booking_id );

									// check if it's a valid ID
									$order = wc_get_order( $get_order_id[0]->order_id );
									if ( $order && false !== $booking_status ) {
										$order = wc_get_order( $get_order_id[0]->order_id );

										if ( $order ) {
											$order_status = $order->get_status();
											$order_status = "wc-$order_status";
										}
										
										if ( isset( $order_status ) && ( $order_status != '' ) && ( $order_status != 'wc-cancelled' ) && ( $order_status != 'wc-refunded' ) && ( $order_status != 'wc-trash' ) && ( $order_status != 'wc-failed' ) && 'trash' !== $booking_status && 'cancelled' !== $booking_status ) {

											// get the booking status
											$booking_status = wc_get_order_item_meta( $item_key, '_wapbk_booking_status' );

											if ( isset( $booking_status ) && 'cancelled' != $booking_status ) {

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

														if ( is_numeric( $item_value->meta_value ) ) {

															if ( isset( $get_dates[2]->meta_value ) && $get_dates[2]->meta_value != '' ) {
																$total_qty = $item_value->meta_value;
																// if qty is greater than 1
																if ( is_numeric( $get_dates[0]->meta_value ) && $get_dates[0]->meta_value > 1 ) {
																	$total_qty = $get_dates[0]->meta_value * $item_value->meta_value;
																}

																if ( is_array( $dates ) && count( $dates ) > 0 ) {
																	foreach ( $dates as $array_key => $array_value ) {
																		$list_dates[ $array_value ][ $get_dates[2]->meta_value ] = $total_qty;
																	}
																}

																$list_dates[ $booked_date ][ $get_dates[2]->meta_value ] = $total_qty;
															}
														}
													}

													// create/edit a final array which contains each date once and the value is the qty for which the order has been placed for this date
													if ( isset( $list_dates ) && is_array( $list_dates ) && count( $list_dates ) > 0 ) {
														foreach ( $list_dates as $date_key => $qty_value ) {

															if ( is_array( $qty_value ) && count( $qty_value ) > 0 ) {

																foreach ( $qty_value as $k => $v ) {
																	// check if the date is already present in the array, if yes, then edit the qty
																	if ( array_key_exists( $date_key, $total_bookings ) && array_key_exists( ( $k ), $total_bookings[ $date_key ] ) ) {
																		$qty_present                       = $total_bookings[ $date_key ][ $k ];
																		$new_qty                           = $qty_present + $v;
																		$total_bookings[ $date_key ][ $k ] = $new_qty;
																	} else {
																		$date_key                          = date( 'j-n-Y', strtotime( $date_key ) );
																		$total_bookings[ $date_key ][ $k ] = $v;
																	}
																}
															}
														}
													}
												}
											}
										}
									}
								}

								$final_qty = $attr_qty_booked;

								if ( array_key_exists( $booking_date, $total_bookings ) && array_key_exists( $booking_time, $total_bookings[ $booking_date ] ) ) {
									$qty_ordered = $total_bookings[ $booking_date ][ $booking_time ];
									$final_qty  += $total_bookings[ $booking_date ][ $booking_time ];
								}

								if ( $attr_lockout > 0 && $attr_lockout < $final_qty ) {
									if ( isset( $qty_ordered ) && $qty_ordered > 0 ) {
										$available_tickets = $attr_lockout - $qty_ordered;
									} else {
										$available_tickets = $attr_lockout;
									}
									$date_to_display = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-time-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE', 'TIME' ), array( $post_title->post_title, $available_tickets, $attr_label, $date_to_display, $_POST['time_slot'] ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
								}
							} else {
								if ( $attr_lockout > 0 && $attr_lockout < $attr_qty_booked ) {
									$available_tickets = $attr_lockout;
									$date_to_display   = date( $bkap_date_formats[ $date_format_to_display ], strtotime( $booking_date ) );

									$msg_text   = __( get_option( 'book_limited-booking-msg-time-attr' ), 'woocommerce-booking' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$message                              = str_replace( array( 'PRODUCT_NAME', 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE', 'TIME' ), array( $post_title->post_title, $available_tickets, $attr_label, $date_to_display, $_POST['time_slot'] ), $msg_text );
									wc_add_notice( $message, $notice_type = 'error' );
								}
							}
						}
					}
				}
			}

			$_POST['validation_status'] = $validation_completed;
		}

	} // end of class
} // end of if class
$bkap_attributes = new bkap_attributes();
?>
