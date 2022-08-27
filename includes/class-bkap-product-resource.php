<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Resources - Appearance and Calculations.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Resources
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class_Bkap_Product_Resource class.
 *
 * @class Class_Bkap_Product_Resource
 * @since 4.6.0
 */
class Class_Bkap_Product_Resource {

	/**
	 * Holds product id.
	 *
	 * @var int
	 */

	private $product_id;

	/**
	 * Constructor. Reference to the Resource.
	 *
	 * @since 4.6.0
	 * @param integer $product_id Product ID.
	 */
	public function __construct( $product_id = 0 ) {

		if ( $product_id != 0 ) {
			$this->$product_id = $product_id;
		}

		// Resource Post - Save the changes in resource details meta box.
		add_action( 'save_post', array( $this, 'bkap_meta_box_save_resource_details' ), 10, 2 );
		// add the Resource tab in the Booking meta box.
		add_action( 'bkap_add_tabs', array( &$this, 'bkap_resource_tab' ), 12, 2 );

		// add fields in the Resource tab in the Booking meta box.
		add_action( 'bkap_after_listing_enabled', array( &$this, 'bkap_resource_settings' ), 12, 4 );

		// Ajax.
		add_action( 'admin_init', array( &$this, 'bkap_load_resource_ajax_admin' ) );

		// adding resource option on Booking meta box header.
		add_action( 'bkap_add_resource_section', array( &$this, 'bkap_add_resource_section_callback' ), 10, 4 );

		// Adding dropdown for resource on front end product page.
		add_action( 'bkap_before_booking_form', array( &$this, 'bkap_front_end_resource_field' ), 6, 1 );

		// Adding data in the additional data being passed in the localized script.
		add_filter( 'bkap_add_additional_data', array( &$this, 'bkap_add_additional_resource_data' ), 10, 3 );

		// print hidden data for resource on the front end product page.
		add_action( 'bkap_add_additional_data', array( &$this, 'print_hidden_resource_data' ), 11, 3 );

		add_filter( 'bkap_locked_dates_for_dateandtime', array( &$this, 'bkap_locked_dates_for_dateandtime_callback' ), 10, 4 );

		add_action( 'admin_enqueue_scripts', array( &$this, 'bkap_resource_css_file' ), 100 );

		add_filter( 'bkap_resource_add_to_cart_validation', array( &$this, 'bkap_resource_add_to_cart_validation_callback' ), 10, 5 );
		// Changing href of add to cart button on Shop page if product having resources.
		add_filter( 'woocommerce_product_add_to_cart_url', array( &$this, 'woocommerce_product_add_to_cart_url_callback' ), 10, 2 );
		// Changing add to cart button text and class on Shop page if product having resources.
		add_filter( 'woocommerce_product_add_to_cart_text', array( &$this, 'woocommerce_product_add_to_cart_text_callback' ), 99, 3 );
		add_filter( 'woocommerce_product_supports', array( &$this, 'woocommerce_product_supports_callback' ), 99, 3 );

		add_filter( 'bkap_get_number_of_slots', array( &$this, 'bkap_get_slots_based_on_resource_availability' ), 19, 3 );

		add_filter( 'bkap_get_resource_costs', array( &$this, 'bkap_sorting_resource_data' ), 10, 2 );

	}

	public function bkap_sorting_resource_data( $resource_data, $product_id ) {

		$sorting_type = self::bkap_product_resource_sorting( $product_id );

		switch ( $sorting_type ) {
			case 'menu_order':
				$sorted_resources = array();
				$new_array              = array();
				foreach ( $resource_data as $key => $value ) {
					$menu_order                                    = get_post_field( 'menu_order', $key );
					$sorted_resources[ $key ]['resource_id'] = $key;
					$sorted_resources[ $key ]['menu_order']  = $menu_order;
				}

				$sorted_resources = bkap_array_orderby_array_key( $sorted_resources, 'menu_order', SORT_ASC );

				foreach ( $sorted_resources as $r_key => $r_value ) {
					$new_array[ $r_value['resource_id'] ] = $resource_data[ $r_value['resource_id'] ];
				}

				$resource_data = $new_array;
				break;
			case 'ascending':
				foreach ( $resource_data as $key => $value ) {
					$title                   = get_the_title( $key );
					$resource_titles[ $key ] = $title;
				}
			
				asort( $resource_titles );
			
				foreach ( $resource_titles as $r_key => $r_title ) {
					$resource_titles[ $r_key ] = $resource_data[ $r_key ];
				}
			
				$resource_data = $resource_titles;
				break;
			
			case 'price_low':
				asort( $resource_data );
				break;
			case 'price_high':
				arsort( $resource_data );
				break;
			default:
		}

		return $resource_data;
	}

	/**
	 * Filter the timeslots availability based on the Resource Time Avaialbility.
	 * This function is used for calculating the number of available timeslots.
	 *
	 * @param array      $timeslots Array of Timeslots available in the Booking Settings.
	 * @param string|int $product_id Product Post ID.
	 * @param string     $date_check_in Selected Date.	`
	 *
	 * @since 5.8.0
	 */
	public static function bkap_get_slots_based_on_resource_availability( $timeslots, $product_id, $date_check_in ) {

		$resource_id = isset( $_POST['resource_id'] ) ? (int) $_POST['resource_id'] : 0;

		if ( 0 !== $resource_id ) {
			$time       = array();
			$timestring = '';
			foreach ( $timeslots as $key => $value ) {
				$from_time = $value['from_slot_hrs'] . ':' . $value['from_slot_min'];
				$to_time   = $value['to_slot_hrs'] . ':' . $value['to_slot_min'];
				$time[]    = $from_time . ' - ' . $to_time;
			}

			if ( ! empty( $time ) ) {
				$timestring                 = implode( '|', $time );
				$timestring                .= '|';
				$resource                   = new BKAP_Product_Resource( $resource_id, $product_id );
				$resource_availability_data = $resource->get_resource_availability();
				$timestring                 = bkap_filter_time_based_on_resource_availability( $date_check_in, $resource_availability_data, $timestring, array( 'type' => 'fixed_time' ), $resource_id, $product_id, bkap_setting( $product_id ) );
				if ( '' !== $timestring ) {
					$timestring = substr( $timestring, 0, -1 );
					$timeslots  = explode( '|', $timestring );
				} else {
					$timeslots = array();
				}
			}
		}

		return $timeslots;
	}

	/**
	 * Save details of Resources availability
	 *
	 * @param string|int $post_id Resource Post ID
	 * @param WP_Post    $post Resource Post
	 * @since 4.6.0
	 *
	 * @todo Change the function name to meaningful
	 */
	public function bkap_meta_box_save_resource_details( $post_id, $post ) {

		if ( 'bkap_resource' == get_post_type() ) {

			$resource_data = bkap_save_resources( $post_id, $post );

			$meta_args = array(
				'_bkap_resource_qty'          => $resource_data['bkap_resource_qty'],
				'_bkap_resource_menu_order'   => $resource_data['bkap_resource_menu_order'],
				'_bkap_resource_availability' => $resource_data['bkap_resource_availability'],
				'_bkap_resource_meeting_host' => $resource_data['bkap_resource_meeting_host'],
			);

			// run a foreach and save the data.
			foreach ( $meta_args as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			// Reference : https://stackoverflow.com/questions/21717159/get-custom-fields-values-in-filter-on-wp-insert-post-data
			// unhook this function so it doesn't loop infinitely
			remove_action( 'save_post', array( $this, 'bkap_meta_box_save_resource_details' ) );

			// update the post, which calls save_post again
			wp_update_post( array( 'ID' => $post_id, 'menu_order' => $resource_data['bkap_resource_menu_order'] ) );

			// re-hook this function
			add_action( 'save_post', array( $this, 'bkap_meta_box_save_resource_details' ) );
		}
	}

	/**
	 * This function is for removing the Add to Cart Ajax class from class of Add to Cart button.
	 *
	 * @param bool   $status true if Add to Cart Ajax feature is supported.
	 * @param string $feature Feature name string.
	 * @param obj    $product Product Obj.
	 * @return bool
	 *
	 * @hook woocommerce_product_supports
	 * @since 5.0.0
	 */
	public function woocommerce_product_supports_callback( $status, $feature, $product ) {

		if ( 'ajax_add_to_cart' === $feature ) {
			if ( bkap_common::bkap_get_bookable_status( $product->get_id() ) ) {
				return false;
			}
		}

		return $status;
	}

	/**
	 * This function is to change the href link of Add to cart button on Shop page
	 *
	 * @param string $link - Href link of the Add to Cart Button.
	 * @param Object $product - WP Post Product.
	 * @return string $link - href attribute value.
	 *
	 * @hook woocommerce_product_add_to_cart_url
	 * @since 5.0.0
	 */
	public function woocommerce_product_add_to_cart_url_callback( $link, $product ) {

		$product_id = $product->get_id();
		if ( ! bkap_common::bkap_get_bookable_status( $product_id ) ) {
			return $link;
		}

		return get_permalink( $product_id );
	}

	/**
	 * This function is to change the text of Add to cart button on Shop page
	 *
	 * @param string $text - Add to Cart Text.
	 * @param Object $product - WP Post Product.
	 * @return string
	 *
	 * @hook woocommerce_product_add_to_cart_text
	 * @since 4.8.0
	 */
	public function woocommerce_product_add_to_cart_text_callback( $text, $product ) {

		$product_id = $product->get_id();
		$bookable   = bkap_common::bkap_get_bookable_status( $product_id );

		if ( ! $bookable ) {
			return $text;
		}

		$product_type = $product->get_type();
		if ( 'simple' !== $product_type ) {
			return $text;
		}

		$requires_confirmation = bkap_common::bkap_product_requires_confirmation( $product_id );
		if ( $requires_confirmation ) {
			$bkap_check_availability_text = get_option( 'bkap_check_availability' );
			$button_text                  = '' !== $bkap_check_availability_text ? __( $bkap_check_availability_text, 'woocommerce-booking' ) : __( 'Check Availability', 'woocommerce-booking' );
		} else {
			$bkap_add_to_cart = get_option( 'bkap_add_to_cart' );
			$button_text      = '' !== $bkap_add_to_cart ? __( $bkap_add_to_cart, 'woocommerce-booking' ) : __( 'Book Now!', 'woocommerce-booking' );
		}

		$resource_status = self::bkap_resource_status( $product_id );
		$person_status   = Class_Bkap_Product_Person::bkap_person( $product_id );
		if ( $resource_status && $bookable ) {
			$button_text = apply_filters( 'bkap_change_select_resource_text', __( 'Select Resource', 'woocommerce-booking' ), $product_id );
		} elseif ( $person_status && $bookable ) {
			$button_text = apply_filters( 'bkap_change_read_more_text', __( 'Read more', 'woocommerce-booking' ), $product_id );
		}

		return apply_filters( 'bkap_change_add_to_cart_text_on_shop', $button_text, $product_id );
	}

	/**
	 * This function is to validate the availability of resource on add to cart button action
	 *
	 * @param array   $post_data - DATA in $_POST.
	 * @param integer $post_id - Product ID.
	 * @param array   $booking_settings - Booking Settings.
	 * @param string  $quantity_check_pass - Valid values: yes, no.
	 * @param array   $resource_validation_result.
	 * @return array $resource_validation_result - Validation results.
	 *
	 * @hook bkap_resource_add_to_cart_validation
	 * @since 4.7.0
	 */
	public static function bkap_resource_add_to_cart_validation_callback( $post_data, $post_id, $booking_settings, $quantity_check_pass, $resource_validation_result ) {
		global $woocommerce;

		$item_quantity = isset( $post_data['quantity'] ) ? $post_data['quantity'] : 1;
		$selected_date = $_POST['wapbk_hidden_date'];

		$resource_id           = (int) $post_data['bkap_front_resource_selection'];
		$resource_booking_data = self::print_hidden_resource_data( array(), $booking_settings, $post_id );

		$bkap_resource_availability = bkap_resource_max_booking( $resource_id, $selected_date, $post_id, $booking_settings );

		if ( 0 == $bkap_resource_availability ) {

			$resource_validation_result['quantity_check_pass'] = 'yes';

			return $resource_validation_result;
		}

		$resource_booked_for_date   = 0;
		$booking_type_check         = '';

		if ( isset( $_POST['time_slot'] ) && $_POST['time_slot'] != '' ) {

			$time_slot                = $_POST['time_slot'];
			$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_date_time_array'];
			if ( isset( $resource_bookings_placed[ $selected_date ] ) /* array_key_exists( $selected_date, $resource_bookings_placed ) */ ) {
				if ( isset( $resource_bookings_placed[ $selected_date ][ $time_slot ] ) /* array_key_exists( $time_slot, $resource_bookings_placed[$selected_date] ) */ ) {
					$resource_booked_for_date = $resource_bookings_placed[ $selected_date ][ $time_slot ];
				}
			}
				$booking_type_check = 'date_time';
		} elseif ( isset( $_POST['duration_time_slot'] ) && $_POST['duration_time_slot'] != '' ) {

			$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_date_time_array'];
			if ( array_key_exists( $selected_date, $resource_bookings_placed ) ) {

				$duration_time         = $_POST['duration_time_slot'];
				$duration_time_str     = strtotime( $duration_time );
				$bkap_duration_field   = $_POST['bkap_duration_field'];
				$duration_end_time     = date( 'H:i', strtotime( "+$bkap_duration_field hour", $duration_time_str ) );
				$duration_end_time_str = strtotime( $duration_end_time );

				foreach ( $resource_bookings_placed[ $selected_date ] as $dkey => $dvalue ) {

					$explode = explode( ' - ', $dkey );

					if ( $duration_time_str >= strtotime( $explode[0] ) && $duration_end_time_str < strtotime( $explode[1] ) ) {
						$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
					} elseif ( $duration_time_str >= strtotime( $explode[0] ) && $duration_time_str < strtotime( $explode[1] ) ) {
						$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
					} elseif ( $duration_time_str < strtotime( $explode[0] ) && $duration_end_time_str > strtotime( $explode[1] ) ) {
						$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
					} elseif ( $duration_end_time_str > strtotime( $explode[0] ) && $duration_end_time_str < strtotime( $explode[1] ) ) {
						$resource_booked_for_date += $resource_bookings_placed[ $selected_date ][ $dkey ];
					}
				}
			}
			$booking_type_check = 'duration_time';
		} else {
			$resource_bookings_placed = $resource_booking_data['bkap_booked_resource_data'][ $resource_id ]['bkap_booking_placed'];

			$resource_bookings_placed_list_dates = explode( ',', $resource_bookings_placed );
			$resource_date_array                 = array();

			foreach ( $resource_bookings_placed_list_dates as $list_key => $list_value ) {
				// separate the qty for each date & time slot
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

			$resource_booking_available = $bkap_resource_availability - $resource_booked_for_date;

		if ( $item_quantity <= $resource_booking_available ) {
			$quantity_check_pass = 'yes';
		}

		if ( 'yes' === $quantity_check_pass ) {

			$resource_qty = 0;

			foreach ( $woocommerce->cart->cart_contents as $cart_check_key => $cart_check_value ) {

				if ( isset( $cart_check_value['bkap_booking'][0]['resource_id'] ) ) {

					if ( $resource_id == $cart_check_value['bkap_booking'][0]['resource_id'] ) {

						// Calculation for resource qty for product parent foreach product is single day.
						if ( isset( $post_data['wapbk_hidden_date_checkout'] ) && $post_data['wapbk_hidden_date_checkout'] == '' ) {

							$hidden_date_str          = '';
							$hidden_date_checkout_str = '';
							$val_hidden_date_str      = '';
							$hidden_date_str          = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date'] );
							$val_hidden_date_str      = strtotime( $post_data['wapbk_hidden_date'] );

							if ( isset( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] ) && $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] != '' ) {
								$hidden_date_checkout_str = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] );
							}

							switch ( $booking_type_check ) {
								case 'date_time':
									$time_slot = $_POST['time_slot'];
									if ( $hidden_date_checkout_str == '' ) {
										if ( $post_data['wapbk_hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {

											$time_check = false;
											if ( isset( $cart_check_value['bkap_booking'][0]['time_slot'] ) ) {
												$time_check = true;

												if ( $time_slot == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
													$resource_qty += $cart_check_value['quantity'];
												} else {
													$cart_time_slot_explode = explode( '-', $cart_check_value['bkap_booking'][0]['time_slot'] );
													$lf                     = strtotime( $cart_time_slot_explode[0] ); // 07:00 100
													$lt                     = strtotime( $cart_time_slot_explode[1] ); // 15:00 200

													$t_s_e = explode( ' - ', $time_slot );
													$f     = strtotime( $t_s_e[0] ); // 07:00 100
													$t     = strtotime( $t_s_e[1] ); // 11:00 150

													// 07:00 > 07:00 && 07:00 < 15:00  || 11:00 > 07:00 && 11:00 < 15:00
													if ( ( $f > $lf && $f < $lt ) || ( $t > $lf && $t < $lt ) ) {
														$resource_qty += $cart_check_value['quantity'];
													}
												}
											}
											if ( isset( $cart_check_value['bkap_booking'][0]['duration_time_slot'] ) ) {
												$time_check = true;

												$cartstart          = strtotime( $cart_check_value['bkap_booking'][0]['duration_time_slot'] );
												$d_explode          = explode( '-', $cart_check_value['bkap_booking'][0]['selected_duration'] );
												$d_explode_duration = $d_explode[0];
												$cartend            = strtotime( date( 'H:i', strtotime( "+$d_explode_duration hour", $cartstart ) ) );

												if ( $duration_time_str >= $cartstart && $duration_end_time_str < $cartend ) {
													// inside.
													$resource_qty += $cart_check_value['quantity'];

												} elseif ( $duration_time_str >= $cartstart && $duration_time_str < $cartend ) {
													// inside start time range.
													$resource_qty += $cart_check_value['quantity'];
												} elseif ( $duration_time_str < $cartstart && $duration_end_time_str > $cartend ) {
													// out side.
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
										if ( $post_data['wapbk_hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {

											$time_check = false;
											if ( isset( $cart_check_value['bkap_booking'][0]['time_slot'] ) ) {
												$time_check = true;
												if ( $time_slot == $cart_check_value['bkap_booking'][0]['time_slot'] ) {
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
													// inside.
													$resource_qty += $cart_check_value['quantity'];

												} elseif ( $duration_time_str >= $cartstart && $duration_time_str < $cartend ) {
													// inside start time range.
													$resource_qty += $cart_check_value['quantity'];
												} elseif ( $duration_time_str < $cartstart && $duration_end_time_str > $cartend ) {
													// out side.
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
										if ( $post_data['wapbk_hidden_date'] == $cart_check_value['bkap_booking'][0]['hidden_date'] ) {
											$resource_qty += $cart_check_value['quantity'];
										}
									} else {
										if ( $val_hidden_date_str >= $hidden_date_str && $val_hidden_date_str < $hidden_date_checkout_str ) {
											$resource_qty += $cart_check_value['quantity'];
										}
									}

									break;
							}
						} else { // Calculation for resource qty for product parent foreach product is multiple nights.

							$hidden_date_str            = '';
							$hidden_date_checkout_str   = '';
							$cart_check_hidden_date_str = '';
							$hidden_date_str            = strtotime( $post_data['wapbk_hidden_date'] );

							if ( $cart_check_value['bkap_booking'][0]['hidden_date_checkout'] != '' ) {
								$hidden_date_checkout_str = strtotime( $post_data['wapbk_hidden_date_checkout'] );
							}

							$cart_check_hidden_date_str = strtotime( $cart_check_value['bkap_booking'][0]['hidden_date'] );

							if ( $cart_check_hidden_date_str >= $hidden_date_str && $cart_check_hidden_date_str <= $hidden_date_checkout_str ) {
								$resource_qty += $cart_check_value['quantity'];
							}
						}
					}
				}
			}

			$resource_booking_available = $resource_booking_available - $resource_qty;

			if ( $resource_booking_available < $item_quantity ) {
				$quantity_check_pass = 'no';
			}
		}

			$resource_validation_result['quantity_check_pass']        = $quantity_check_pass;
			$resource_validation_result['resource_booking_available'] = $resource_booking_available;

			return $resource_validation_result;

	}

	/**
	 * This function is to dequeue CSS of WooCommerce Bookings to overcome conflict
	 * Later this will be removed with appropriate solution
	 *
	 * @hook admin_enqueue_scripts
	 * @since 4.6.0
	 */
	public static function bkap_resource_css_file() {

		if ( 'bkap_resource' === get_post_type() ) {
			wp_dequeue_style( 'wc_bookings_admin_styles' );
		}
	}

	/**
	 * This function is used to alter the lockout date for date and time product
	 *
	 * @param integer $resource_id - Resource ID.
	 * @param integer $product_id - Product ID.
	 * @param array   $booking_settings - Booking Settings.
	 * @param array   $resource_lockout_data - Lockout Data.
	 * @return array  $resource_lockout_data - Blocked Dates for the resource.
	 *
	 * @hook bkap_locked_dates_for_dateandtime
	 * @since 4.6.0
	 */
	public static function bkap_locked_dates_for_dateandtime_callback( $resource_id, $product_id, $booking_settings, $resource_lockout_data ) {

		$bkap_resource_availability = get_post_meta( $resource_id, '_bkap_resource_qty', true );
		$total_bookings             = $resource_lockout_data['bkap_date_time_array'];
		$bookings_placed            = '';
		$lockout_reached_time_slots = '';
		$lockout_reached_dates      = '';

		if ( isset( $total_bookings ) && is_array( $total_bookings ) && count( $total_bookings ) > 0 ) {

			foreach ( $total_bookings as $date_key => $qty_value ) {

				if ( is_array( $qty_value ) && count( $qty_value ) > 0 ) {
					$time_slot_total_booked = 0;

					foreach ( $qty_value as $k => $v ) {

						$time_slot_total_booked += $v;
						$bookings_placed        .= '"' . $date_key . '"=>' . $k . '=>' . $v . ',';
						$bkap_resource_availability = bkap_resource_max_booking( $resource_id, $date_key, $product_id, $booking_settings );
						if ( 0 != $bkap_resource_availability && $bkap_resource_availability <= $v ) {
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
										$total_lockout = $number_of_slots * $bkap_resource_availability;

									}
								} else {
									// get the recurring weekday.
									$day_number = date( 'w', strtotime( $date_key ) );
									$weekday    = 'booking_weekday_' . $day_number;

									if ( array_key_exists( $weekday, $time_settings ) ) {
										$number_of_slots = count( $time_settings[ $weekday ] );

										// total time slot lockout for the variation is the number of slots * the lockout.
										$total_lockout = $number_of_slots * $bkap_resource_availability;
									}
								}

								if ( ' - ' === $k ) { // this mean booking is done for single day or multiple nights.
									if ( $bkap_resource_availability <= $time_slot_total_booked ) {
										$lockout_reached_dates .= '"' . $date_key . '",';
									}
								}
								// if reached then add it to the list of dates to be locked.
								elseif ( isset( $total_lockout ) && ( $total_lockout <= $time_slot_total_booked ) ) {
									$lockout_reached_dates .= '"' . $date_key . '",';
								}
							}
						}
					}
				}
			}
		}

		$lockout_reached_dates = substr_replace( $lockout_reached_dates, '', -1 );

		$resource_lockout_data['bkap_locked_dates'] = $lockout_reached_dates;

		return $resource_lockout_data;
	}

	/**
	 * This function is used to add availability and quantity data in
	 * the additional data array being passed in the localized script. resource field.
	 *
	 * @param array   $additional_data - Additional Data.
	 * @param array   $booking_settings - Booking Settings.
	 * @param integer $product_id - Product ID.
	 * @return array $additional_data - Updated Additional Data.
	 *
	 * @hook bkap_add_additional_data
	 * @since 4.6.0
	 */
	public static function print_hidden_resource_data( $additional_data, $booking_settings, $product_id ) {

		global $wpdb, $post;

		if ( get_post_type( $post ) === 'product' ) {
			$product_id = $post->ID;
		}
		// get product type
		$product = wc_get_product( $product_id );
		$display = $product->is_type( array( 'simple', 'variable', 'subscription', 'variable-subscription' ) );

		if ( ! $display ) {
			return $additional_data;
		}
		$product_id      = bkap_common::bkap_get_product_id( $product_id );
		$resource_status = self::bkap_resource_status( $product_id );

		if ( ! $resource_status ) {
			return $additional_data;
		}

		// for a variable and bookable product
		if ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) {

			$resource_costs = self::bkap_get_resource_costs( $product_id );

			foreach ( $resource_costs as $key_resource_id => $value_resource_cost ) {

				$resource_bookings[ $key_resource_id ] = bkap_calculate_bookings_for_resource( $key_resource_id, $product_id );

				if ( isset( $booking_settings['booking_enable_time'] ) && ( 'on' === $booking_settings['booking_enable_time'] || $booking_settings['booking_enable_time'] == 'duration_time' ) ) {
					$resource_bookings[ $key_resource_id ] = apply_filters( 'bkap_locked_dates_for_dateandtime', $key_resource_id, $product_id, $booking_settings, $resource_bookings[ $key_resource_id ] );
				}
			}
		}

		$resource_bookings = apply_filters( 'bkap_merge_lockout_resource_automatically_assigned', $resource_bookings, $resource_costs, $product_id );

		$additional_data['bkap_booked_resource_data'] = $resource_bookings;

		return $additional_data;
	}

	/**
	 * This function is used to add availability and quantity data in
	 * the additional data array being passed in the localized script. resource field.
	 *
	 * @param array $additional_data Array Additional Data.
	 * @param array $booking_settings Array Booking Settings.
	 * @param int   $product_id Int Product ID.
	 * @since 4.6.0
	 */
	public static function bkap_add_additional_resource_data( $additional_data, $booking_settings, $product_id ) {

		$resource_status = self::bkap_resource_status( $product_id );

		if ( ! $resource_status ) {
			return $additional_data;
		}

		$resource_ids = self::bkap_get_product_resources( $product_id );

		$resource_additional_data = array();

		foreach ( $resource_ids as $resource_id ) {
			if ( get_post_status( $resource_id ) ) {
				$resource = new BKAP_Product_Resource( $resource_id, $product_id );

				$resource_additional_data[ $resource_id ]['resource_title']        = $resource->get_resource_title();
				$resource_additional_data[ $resource_id ]['resource_availability'] = $resource->get_resource_availability();
				$resource_additional_data[ $resource_id ]['resource_qty']          = $resource->get_resource_qty();
			}
		}

		$additional_data['resource_ids']           = $resource_ids;
		$additional_data['bkap_resource_data']     = $resource_additional_data;
		$additional_data['bkap_resource_assigned'] = BKAP_Product_Resource::bkap_resource_assigned( $product_id );

		return $additional_data;

	}

	/**
	 * This function is used to create resource field.
	 * in the Booking form.
	 *
	 * @param integer $product_id Product ID.
	 *
	 * @hook bkap_before_booking_form
	 * @since 4.6.0
	 */
	public static function bkap_front_end_resource_field( $product_id ) {

		$resource_status = self::bkap_resource_status( $product_id );

		if ( ! $resource_status ) {
			return;
		}

		$type                   = bkap_common::bkap_get_product_type( $product_id );
		$resource_compatibility = array( 'simple', 'subscription', 'variable', 'variable-subscription' );
		$display                = in_array( $type, $resource_compatibility ) ? true : false;
		if ( ! $display ) {
			return;
		}

		$resource_ids       = self::bkap_get_product_resources( $product_id );
		$resource_costs     = self::bkap_get_resource_costs( $product_id );
		$label              = self::bkap_get_resource_label( $product_id );
		$resource_selection = self::bkap_product_resource_selection( $product_id );

		if ( 'bkap_automatic_resource' === $resource_selection ) {
			if ( '' !== $label ) {
				?><label for="bkap_front_resource_lable"><?php echo $label . ': '; ?></label><?php
			}
			foreach ( $resource_ids as $key => $value ) {
				$resource_title = get_the_title( $value );
				?>
			<div id="bkap_resource_label" style="display: inline;" >
				<b><?php echo __( $resource_title, 'woocommerce-booking' ); ?></b>
			</div>
			<input type="hidden" id="bkap_front_resource_selection" name="bkap_front_resource_selection" value="<?php echo $value; ?>">
				<?php
				break;
			}
		} else {

			if ( $label == '' ) {
				$label = __( 'Type', 'woocommerce-booking' );
			}
			?>
			<div class="bkap_resource_container">
				<label for="bkap_front_resource_lable"><?php echo $label . ':'; ?></label>				

				<select id="bkap_front_resource_selection" name="bkap_front_resource_selection" style="width:100%;">
					<?php
					foreach ( $resource_costs as $key => $value ) {
						if ( get_post_status( $key ) ) {

							if ( function_exists( 'icl_object_id' ) ) {
								$key1 = icl_object_id( $key, 'bkap_resource', true, ICL_LANGUAGE_CODE );
							} else {
								$key1 = $key;
							}
							$formatted_price = ' - ( + ' . wc_price( $value ) . ' )';
							$resource_price  = apply_filters( 'bkap_resource_price_in_dropdown', $formatted_price, $value, $product_id );
							echo '<option value="' . esc_attr( $key ) . '">' . esc_html( get_the_title( $key1 ) ) . $resource_price . ' </option>';
						}
					}
					?>

				</select>
			</div>
			<?php
		}
	}

	/**
	 * This function is used to create resource field.
	 *
	 * @since 4.6.0
	 */
	public static function bkap_get_extra_options() {

		return apply_filters(
			'bkap_extra_options',
			array(
				'bkap_resource' => array(
					'id'            => '_bkap_resource',
					'wrapper_class' => 'hide_if_bundle bkap_resource',
					'label'         => __( 'Resources', 'woocommerce-booking' ),
					'description'   => __( 'Enable this if this bookable product has multiple bookable resources, for example room types or instructors.', 'woocommerce-booking' ),
					'default'       => 'no',
				),
				'bkap_person' => array(
					'id'            => '_bkap_person',
					'wrapper_class' => 'hide_if_bundle bkap_person',
					'label'         => __( 'Persons', 'woocommerce-booking' ),
					'description'   => __( 'Enable this if this bookable product can be booked by a customer defined number of persons.', 'woocommerce-booking' ),
					'default'       => 'no',
					'style'         => 'margin-left:16px;',
				),
			)
		);
	}

	/**
	 * This function is used to add resource option on Booking meta box header
	 * in Add/Edit Product page.
	 *
	 * @param integer $product_id Product ID.
	 * @since 4.6.0
	 */
	public static function bkap_add_resource_section_callback( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

		if ( is_admin() || function_exists( 'is_wcfm_page' ) ) {
			?>
			<span class="bkap_type_box" style="margin-left: 15%;">
			<?php
		} else {
			?>
			<div class="bkap_type_box">
			<?php
		}
		foreach ( self::bkap_get_extra_options() as $key => $option ) :
			if ( metadata_exists( 'post', $product_id, '_' . $key ) || $defaults ) {
				$bkap_resource_option = bkap_get_post_meta_data( $product_id, '_' . $key, $default_booking_settings, $defaults );
				$selected_value       = '';

				if ( $bkap_resource_option == 'on' ) {
					$selected_value = 'checked';
				}
			} else {
				$selected_value = 'yes' === ( isset( $option['default'] ) ? $option['default'] : 'no' );
			}

			?>
				<label for="<?php echo esc_attr( $option['id'] ); ?>" style="<?php echo isset( $option['style'] ) ? esc_attr( $option['style'] ) : ''; ?>" class="<?php echo esc_attr( $option['wrapper_class'] ); ?> tips" data-tip="<?php echo esc_attr( $option['description'] ); ?>">
					<?php echo esc_html( $option['label'] ); ?>:
					<input type="checkbox" name="<?php echo esc_attr( $option['id'] ); ?>" id="<?php echo esc_attr( $option['id'] ); ?>" <?php echo $selected_value; ?> />
				</label>
			<?php
			endforeach;
		do_action( 'bkap_add_settings', $product_id );
		if ( is_admin() || function_exists( 'is_wcfm_page' ) ) {
			?>
			</span>
			<?php
		} else {
			?>
			</div>
			<?php
		}
	}

	/**
	 * Ajax loads
	 *
	 * @hook admin_init
	 * @since 4.6.0
	 */
	public function bkap_load_resource_ajax_admin() {

		add_action( 'wp_ajax_bkap_add_resource', array( &$this, 'bkap_add_resource' ) );
		add_action( 'wp_ajax_bkap_delete_resource', array( &$this, 'bkap_delete_resource' ) );
	}

	/**
	 *  Deleting the resource
	 *
	 * @since 4.6.0
	 */
	public static function bkap_delete_resource() {

		$product_id  = intval( $_POST['post_id'] );
		$resource_id = intval( $_POST['delete_resource'] );

		if ( $resource_id ) {

			$bkap_resource_base_costs = get_post_meta( $product_id, '_bkap_resource_base_costs', true );
			$bkap_product_resources   = get_post_meta( $product_id, '_bkap_product_resources', true );

			if ( $bkap_resource_base_costs != '' ) {

				if ( array_key_exists( $resource_id, $bkap_resource_base_costs ) ) {
					unset( $bkap_resource_base_costs[ $resource_id ] );
					update_post_meta( $product_id, '_bkap_resource_base_costs', $bkap_resource_base_costs );
				}
			}

			if ( $bkap_product_resources != '' ) {

				if ( in_array( $resource_id, $bkap_product_resources ) ) {
					$key = array_search( $resource_id, $bkap_product_resources );
					unset( $bkap_product_resources[ $key ] );
					update_post_meta( $product_id, '_bkap_product_resources', $bkap_product_resources );
				}
			}
		} else {
			update_post_meta( $product_id, '_bkap_resource_base_costs', '' );
			update_post_meta( $product_id, '_bkap_product_resources', '' );
		}

		die();
	}

	/**
	 * Save the resource
	 *
	 * @since 4.6.0
	 */
	public static function bkap_add_resource() {

		$post_id           = intval( $_POST['post_id'] );
		$loop              = intval( $_POST['loop'] );
		$add_resource_id   = intval( $_POST['add_resource_id'] );
		$add_resource_name = wc_clean( $_POST['add_resource_name'] );

		if ( ! $add_resource_id ) {
			$add_resource_id = BKAP_Product_Resource::bkap_create_resource( $add_resource_name );
		}

		if ( $add_resource_id ) {

			$resource     = new BKAP_Product_Resource( $add_resource_id );
			$resource_id  = absint( $resource->get_id() );
			$resource_url = apply_filters( 'bkap_resource_link_booking_metabox', admin_url( 'post.php?post=' . $resource_id . '&action=edit' ), $resource_id );
			if ( isset( $_POST['bkap_resource_url'] ) && '' !== $_POST['bkap_resource_url'] ) {
				$resource_url = $_POST['bkap_resource_url'] . $resource_id;
			}

			ob_start();

			include BKAP_BOOKINGS_TEMPLATE_PATH . 'meta-boxes/html-bkap-resource.php';

			wp_send_json( array( 'html' => ob_get_clean() ) );
		}

		wp_send_json( array( 'error' => __( 'Unable to add resource', 'woocommerce-booking' ) ) );
	}

	/**
	 * Adds the resources tab in Add/Edit Product page
	 *
	 * @param integer $product_id - Product ID.
	 * @param array   $booking_settings Booking Settings.
	 * @hook bkap_add_tabs
	 * @since 4.6.0
	 */
	public static function bkap_resource_tab( $product_id, $booking_settings ) {

		$selected_value = 'display:none;';

		if ( 0 == $product_id ) {
			$type = 'simple';
		} else {
			$type = bkap_common::bkap_get_product_type( $product_id );
		}

		$resource_compatibility = array( 'simple', 'variable', 'subscription', 'variable-subscription' );
		$display                = in_array( $type, $resource_compatibility, true ) ? true : false;

		if ( ( isset( $booking_settings['_bkap_resource'] ) && $booking_settings['_bkap_resource'] == 'on' ) && $display ) {
			$selected_value = '';
		}

		?>

		<li class="tstab-tab" data-link="bkap_resource_settings_page">
			<a id="resource_tab_settings" style="<?php echo $selected_value; ?>" class="bkap_tab"><i class="fa fa-people-carry"></i><?php esc_html_e( 'Resource', 'woocommerce-booking' ); ?></a>
		</li>		
		<?php
	}

	/**
	 * Loads the content in the Resources tab in Add/Edit product page.
	 *
	 * @param integer $product_id - Product ID.
	 * @hook bkap_after_listing_enabled
	 * @since 4.6.0
	 */
	public static function bkap_resource_settings( $product_id, $booking_settings, $default_booking_settings, $defaults ) {

		$post_type = get_post_type( $product_id );

		?>
		<div id="bkap_resource_settings_page" class="tstab-content" style="position: relative; display: none;">

			<?php
			wc_get_template(
				'meta-boxes/html-bkap-resources.php',
				array(
					'product_id'               => $product_id,
					'default_booking_settings' => $default_booking_settings,
					'defaults'                 => $defaults,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
			?>
			<hr />
			<?php
			if ( isset( $post_type ) && 'product' === $post_type ) {
				bkap_booking_box_class::bkap_save_button( 'bkap_save_resources' );
			}
			?>
			<div id='resource_update_notification' style='display:none;'></div>
		</div>
		<?php

	}

	/**
	 * Get ids of bkap_resource post.
	 *
	 * @return array
	 * @since 4.6.0
	 */
	public static function bkap_get_resource_ids() {

		$all_resource_ids = array();
		$args             = array(
			'post_type'      => 'bkap_resource',
			'posts_per_page' => -1,
		);
		$resources        = get_posts( $args );

		if ( count( $resources ) > 0 ) {
			foreach ( $resources as $key => $value ) {
				$all_resource_ids[] = $value->ID;
			}
		}
		return $all_resource_ids;
	}

	/**
	 * Get all resource posts.
	 *
	 * @return array
	 * @since 4.6.0
	 */
	public static function bkap_get_all_resources() {

		$args = array(
			'post_type'      => 'bkap_resource',
			'posts_per_page' => -1,
		);

		/* Show vendors to their own resources */
		if ( ! is_admin() && apply_filters( 'bkap_show_resource_created_by_user', true ) ) {
			$args['author'] = get_current_user_id();
		}

		$resources = get_posts( $args );

		return $resources;
	}

	/**
	 * Get resources added for product.
	 *
	 * @param integer $product_id - Product ID.
	 * @return array
	 *
	 * @since 4.6.0
	 */
	public static function bkap_get_product_resources( $product_id ) {
		$product_resource_ids = get_post_meta( $product_id, '_bkap_product_resources', true );

		return $product_resource_ids;
	}

	/**
	 * Get resources costs for product.
	 *
	 * @param integer $product_id - Product ID.
	 * @return array
	 * @since 4.6.0
	 */
	public static function bkap_get_resource_costs( $product_id ) {
		$product_resource_ids = get_post_meta( $product_id, '_bkap_resource_base_costs', true );

		return apply_filters( 'bkap_get_resource_costs', $product_resource_ids, $product_id );
	}

	/**
	 * Get resource lable.
	 *
	 * @param integer $product_id - Product ID
	 * @return string
	 *
	 * @since 4.6.0
	 */
	public static function bkap_get_resource_label( $product_id ) {
		$product_resource_label = get_post_meta( $product_id, '_bkap_product_resource_lable', true );

		return $product_resource_label;
	}

	/**
	 * Get selected type of resource.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 4.6.0
	 */
	public static function bkap_product_resource_selection( $product_id ) {
		$bkap_product_resource_selection = get_post_meta( $product_id, '_bkap_product_resource_selection', true );

		return $bkap_product_resource_selection;
	}

	/**
	 * Get override product lockout setting for resource.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 4.6.0
	 */
	public static function bkap_product_resource_max_booking( $product_id ) {
		$bkap_product_resource_selection = get_post_meta( $product_id, '_bkap_product_resource_max_booking', true );

		return $bkap_product_resource_selection;
	}

	/**
	 * Get sorting method for resource on the front end.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 4.6.0
	 */
	public static function bkap_product_resource_sorting( $product_id ) {
		$bkap_product_resource_sorting = get_post_meta( $product_id, '_bkap_product_resource_sorting', true );

		return $bkap_product_resource_sorting;
	}

	/**
	 * Get resource option.
	 *
	 * @param integer $product_id - Product ID.
	 * @return string
	 * @since 4.6.0
	 */
	public static function bkap_resource( $product_id ) {
		$bkap_resource = get_post_meta( $product_id, '_bkap_resource', true );

		return $bkap_resource;
	}

	/**
	 * Get status of resource whether the product has resource enable and any resources are added to it or not.
	 *
	 * @param integer $product_id - Product ID.
	 * @return bool true if resource is enabled and have atleast one resource else false
	 * @since 4.8.0
	 */
	public static function bkap_resource_status( $product_id ) {

		$resource     = self::bkap_resource( $product_id );
		$resource_ids = self::bkap_get_product_resources( $product_id );
		$r_status     = false;

		if ( '' == $resource ) {
			return $r_status;
		} elseif ( '' != $resource && ! ( is_array( $resource_ids ) ) ) {
			return $r_status;
		} elseif ( '' != $resource && empty( $resource_ids ) ) {
			return $r_status;
		}

		return true;
	}
}

$class_bkap_product_resource = new Class_Bkap_Product_Resource();
?>
