<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling processing of bookings on the front end Product page.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Booking-Process
 * @category Classes
 */

require_once 'bkap-common.php';
require_once 'bkap-lang.php';

if ( ! class_exists( 'bkap_booking_process' ) ) {

	/**
	 * Class for handling processing of bookings on the front end Product page.
	 *
	 * @class bkap_booking_process
	 */
	class bkap_booking_process {

		/**
		 * Default constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {
			// Display on Products Page.
			add_action( 'woocommerce_before_add_to_cart_form', array( &$this, 'bkap_before_add_to_cart' ) );
			// Display Price Box after Booking Form.
			add_action( 'bkap_after_booking_box_form', array( &$this, 'bkap_price_display' ) );
			// Bind the booking form.
			add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'bkap_on_woocommerce_before_add_to_cart_form' ) );
			// Add locked time slot to dropdown if needed.
			add_filter( 'bkap_edit_display_timeslots', array( &$this, 'add_time_slot' ), 10, 1 );

			add_action( 'bkap_before_get_per_night_price', array( &$this, 'bkap_before_get_per_night_price_callback' ) );
			// Dequeuing the custom script of plugin or theme on single product page.
			add_action( 'wp_enqueue_scripts', array( &$this, 'bkap_wp_enqueue_scripts' ), 9999 );
			// Adding clear selection link on front end.
			add_action( 'bkap_before_add_to_cart_button', array( &$this, 'bkap_reset_booking_details_in_booking_form' ), 10, 1 );
		}

		/**
		 * Clear Selection link on the front end to clear the selected booking details.
		 *
		 * @param object $booking_settings Booking Setting.
		 *
		 * @since 5.14.0
		 * @hook bkap_before_add_to_cart_button
		 */
		public static function bkap_reset_booking_details_in_booking_form( $booking_settings ) {

			if ( get_post_type() == 'product' ) {
				$show = apply_filters( 'bkap_reset_bookings_link', true, $booking_settings );
				if ( isset( $booking_settings['booking_purchase_without_date'] ) && 'on' === $booking_settings['booking_purchase_without_date'] && $show ) {
					?>
					<div class="bkap_reset_dates_selection">
						<a href="" class="bkap_reset_dates"><?php echo __( 'Clear booking details', 'woocommerce-booking' ); ?></a>
					</div>
					<?php
				}
			}
		}

		/**
		 *  This fucntion is to use appropriate WooCommerce hook based on the WooCommerce Product type.
		 *
		 *  @hook woocommerce_before_add_to_cart_form
		 *  @since 4.10.1
		 */

		public function bkap_on_woocommerce_before_add_to_cart_form() {

			if ( get_post_type() == 'product' ) {
				$product = wc_get_product( get_the_ID() );
				if ( $product->get_type() == 'variable' ) {
					add_action( 'woocommerce_single_variation', array( $this, 'bkap_booking_after_add_to_cart' ), 8 );
				} else {
					add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'bkap_booking_after_add_to_cart' ), 8 );
				}
			}
		}

		/**
		 *  This function will disable the quantity and add to cart button on the frontend
		 *  for a bookable product based on the settings for 'Purchase without choosing a
		 *  Booking Date'.
		 *
		 *  @hook woocommerce_before_add_to_cart_form
		 *  @since 1.0
		 */

		public static function bkap_before_add_to_cart() {

			global $post;

			$duplicate_of     = bkap_common::bkap_get_product_id( $post->ID );
			$booking_settings = bkap_common::bkap_product_setting( $duplicate_of );

			if ( $booking_settings == '' || ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] != 'on' ) ) {
				return;
			}

			$multidates = false;
			if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'multidates' == $booking_settings['booking_enable_multiple_day'] ) {
				$multidates = true;
			}

			if ( $booking_settings != ''
			&& ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] == 'on' )
			&& ( isset( $booking_settings['booking_purchase_without_date'] ) && $booking_settings['booking_purchase_without_date'] != 'on' )
			) {

				// check the product type.
				$_product     = wc_get_product( $duplicate_of );
				$product_type = $_product->get_type();

				if ( 'bundle' == $product_type ) {
					?>
				<script type="text/javascript">
					jQuery( document ).ready( function () {
						jQuery( ".bundle_price" ).hide();
					});
				</script>
					<?php
				}

				// check the setting
				$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

				if ( isset( $global_settings->display_disabled_buttons ) && 'on' == $global_settings->display_disabled_buttons ) {
					?>
				<script type="text/javascript">
					jQuery(document).ready(function() {
						jQuery( ".single_add_to_cart_button" ).prop( "disabled", true );
						jQuery( '.quantity input[name="quantity"]' ).prop( "disabled", true );
					<?php
					if ( $multidates ) {
						?>
						jQuery( '.quantity input[name="quantity"]' ).hide();
						<?php } ?>
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
					jQuery( ".partial_message" ).hide();
				});
			</script>
				<?php
			}
		}

		/**
		 * Adds a span to display the bookable amount on
		 * the Product page
		 *
		 * @hook woocommerce_single_variation
		 *       woocommerce_before_add_to_cart_button
		 * @since 2.6.2
		 */

		public static function bkap_price_display() {

			do_action( 'bkap_before_price_display' );

			$display_price = get_option( 'book_price-label' );

			$display_html = '<div id="bkap-price-box" style="display:none">
							<div id="ajax_img" class="ajax_img" style="display:none"><img src="' . plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif"></div>
							<span id="bkap_no_of_days"></span>
							<span id="bkap_price" class="price">' . $display_price . '</span>
						</div>';

			$price_display = apply_filters( 'bkap_price_display_html', $display_html, $display_price );

			echo $price_display;

			do_action( 'bkap_after_price_display' );
		}

		/**
		 * Localizes and passes data to the JS scripts used on the
		 * front end product page as well as all the other places where
		 * booking fields are available for editing.
		 *
		 * @param integer $post_id - Product ID
		 * @param boolean $edit - True when called from Edit Booking page, else set to false.
		 * @return array $hidden_dates_array - Array containing data for the Booking Search Widget
		 *
		 * @since 4.1.0
		 */

		public static function bkap_localize_process_script( $post_id, $edit = false ) {

			global $wpdb, $bkap_months, $bkap_days;

			$product_id       = bkap_common::bkap_get_product_id( $post_id );
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true ); // booking settings
			$_product         = wc_get_product( $product_id );
			$product_type     = $_product->get_type();
			$global_settings  = bkap_global_setting();

			// WordPress Time
			$current_time   = current_time( 'timestamp' );
			$dateymd        = date( 'Y-m-d', $current_time );
			$today_midnight = strtotime( 'today midnight' );

			// Default settings
			$default = ( ( isset( $booking_settings['booking_recurring_booking'] ) && $booking_settings['booking_recurring_booking'] == 'on' ) || ( isset( $booking_settings['booking_specific_booking'] ) && $booking_settings['booking_specific_booking'] == 'on' ) ) ? 'N' : 'Y';

			$number_of_days    = 0;
			$check_time        = false;
			$timeslots_present = true;
			// setup the hidden date fields
			$hidden_dates_array = array(
				'min_search_checkout' => '',
				'hidden_checkout'     => '',
				'hidden_date'         => '',
				'widget_search'       => '',
			);

			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) {
				$timeslots_present = false; // assume no time slots are present
				$check_time        = true;
			}

			/* For Postcode addon */
			$postcode_weekdays = array();
			$postcode_weekdays = apply_filters( 'bkap_change_postcode_weekdays', $postcode_weekdays, $product_id, $default );

			if ( isset( $postcode_weekdays ) && is_array( $postcode_weekdays ) && ! empty( $postcode_weekdays ) ) {
				$booking_settings['booking_recurring'] = $postcode_weekdays;
			}

			$recurring_date_array = ( isset( $booking_settings['booking_recurring'] ) ) ? $booking_settings['booking_recurring'] : array();

			if ( empty( $postcode_weekdays ) ) {

				foreach ( $recurring_date_array as $wkey => $wval ) {

					if ( $default == 'Y' ) {
						$booking_settings['booking_recurring'][ $wkey ] = 'on';
					} else {

						if ( $booking_settings['booking_recurring_booking'] == 'on' ) {
							// for time slots, enable weekday only if 1 or more time slots are present
							if ( isset( $wval ) && $wval == 'on' && $check_time && array_key_exists( $wkey, $booking_settings['booking_time_settings'] ) && count( $booking_settings['booking_time_settings'][ $wkey ] ) > 0 ) {
								$booking_settings['booking_recurring'][ $wkey ] = $wval;
								$timeslots_present                              = true;
							} elseif ( ! $check_time ) { // when no time bookings are present, print as is
								$booking_settings['booking_recurring'][ $wkey ] = $wval;
							} else { // else set weekday to blanks
								$booking_settings['booking_recurring'][ $wkey ] = '';
							}
							if ( isset( $wval ) && $wval == 'on' ) {
								$number_of_days++;
							}
						} else {
							$booking_settings['booking_recurring'][ $wkey ] = '';
						}
					}
				}
			}

			if ( ! $timeslots_present ) {
				$timeslots_present = bkap_common::bkap_check_specific_date_has_timeslot( $product_id );

				if ( ! $timeslots_present ) {
					return $hidden_dates_array;
				}
			}

			$labels = bkap_get_disabled_date_labels();

			// Additional data
			$additional_data = array();

			$curr_lang = $global_settings->booking_language;
			$curr_lang = bkap_icl_lang_code( $curr_lang );

			$additional_data['bkap_lang']         = $curr_lang;
			$additional_data['gf_enabled']        = ( class_exists( 'woocommerce_gravityforms' ) || class_exists( 'WC_GFPA_Main' ) ) ? 'yes' : 'no';
			$additional_data['sold_individually'] = get_post_meta( $product_id, '_sold_individually', true );
			$additional_data['default_var_id']    = 0;

			/**
			 * Getting default variation id
			 */
			if ( $product_type == 'variable' ) {

				$default_attributes = $_product->get_default_attributes();

				if ( ! empty( $default_attributes ) ) {
					$default_var_id                    = bkap_find_matching_product_variation( $_product, $default_attributes );
					$additional_data['default_var_id'] = $default_var_id;
				}
			}

			$method_to_show = 'bkap_check_for_time_slot';
			$get_method     = bkap_common::bkap_ajax_on_select_date( $product_id );

			if ( isset( $get_method ) && $get_method == 'multiple_time' ) {
				$method_to_show = apply_filters( 'bkap_function_slot', '' );
			}
			$additional_data['method_timeslots'] = $method_to_show;
			$additional_data['product_type']     = $product_type;

			// Current Page
			$bkap_page                               = bkap_get_page();
			$additional_data['bkap_page']            = $bkap_page;
			$additional_data['bkap_update_cart_msg'] = __( 'Please update the cart before editing the Booking details.', 'woocommerce-booking' );

			if ( $bkap_page == 'view-order' ) {

				if ( isset( $booking_settings['booking_enable_multiple_time'] ) && $booking_settings['booking_enable_multiple_time'] == 'multiple' ) {
					$url           = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
					$template_name = strpos( $url, '/order-received/' ) === false ? '/view-order/' : '/order-received/';

					if ( strpos( $url, $template_name ) !== false ) {

						$start                            = strpos( $url, $template_name );
						$first_part                       = substr( $url, $start + strlen( $template_name ) );
						$ord_id                           = substr( $first_part, 0, strpos( $first_part, '/' ) );
						$additional_data['view_order_id'] = $ord_id;

						$view_order = wc_get_order( $ord_id );

						$view_order_item_timeslots = array();

						foreach ( $view_order->get_items() as $item ) {
							$itm_id              = $item->get_id();
							$_wapbk_time_slot    = wc_get_order_item_meta( $itm_id, get_option( 'book_item-meta-time' ) );
							$_wapbk_booking_date = date( 'j-n-Y', strtotime( wc_get_order_item_meta( $itm_id, '_wapbk_booking_date' ) ) );

							$view_order_item_timeslots[ $itm_id ][ $_wapbk_booking_date ] = $_wapbk_time_slot;
						}

						if ( ! empty( $view_order_item_timeslots ) ) {
							$additional_data['multiple_time_selected'] = $view_order_item_timeslots;
						}
					}
				}
			}

			/**
			 * Holidays - Global as well as Product level in one string
			 */
			$global_holidays = array();
			if ( isset( $global_settings->booking_global_holidays ) ) {

				$book_global_holidays = $global_settings->booking_global_holidays;

				if ( $book_global_holidays != '' ) {
					$global_holidays      = explode( ',', $global_settings->booking_global_holidays );
					$book_global_holidays = substr( $book_global_holidays, 0, strlen( $book_global_holidays ) );
					$book_global_holidays = '"' . str_replace( ',', '","', $book_global_holidays ) . '"';
				}
			} else {
				$book_global_holidays = '';
			}

			// the holidays are now an array @since 4.0.0
			$individual_holidays = ( isset( $booking_settings['booking_product_holiday'] ) && $booking_settings['booking_product_holiday'] !== '' ) ? $booking_settings['booking_product_holiday'] : array();

			$holiday_array = array();

			foreach ( $individual_holidays as $date => $years ) { // array format [date] => years to recur
				// add the date
				$holiday_array[] = $date;

				// if recurring is greater than 0
				if ( $years > 0 ) {
					for ( $i = 1; $i <= $years; $i++ ) {
						// add the dates for the future years
						$holiday_array[] = date( 'j-n-Y', strtotime( '+' . $i . 'years', strtotime( $date ) ) );
					}
				}
			}

			$max_days_in_years = 1;
			if ( isset( $booking_settings['booking_maximum_number_days'] ) ) {
				$max_days_in_years = ceil( $booking_settings['booking_maximum_number_days'] / 365 );
			}

			// get holiday ranges
			$holiday_ranges = get_post_meta( $product_id, '_bkap_holiday_ranges', true );

			if ( is_array( $holiday_ranges ) && count( $holiday_ranges ) > 0 ) {

				foreach ( $holiday_ranges as $ranges ) {

					// get the data
					$start_range = $ranges['start'];
					$end_range   = $ranges['end'];
					$recur       = $ranges['years_to_recur'];

					if ( $recur > $max_days_in_years ) {
						$recur = $max_days_in_years;
					}

					$days_in_between = bkap_common::bkap_get_betweendays( $start_range, $end_range ); // get the days in the range, this does not include the end date

					$days_in_between[] = $end_range; // add the end date

					foreach ( $days_in_between as $dates ) {

						$holiday_array[] = date( 'j-n-Y', strtotime( $dates ) ); // add each date

						// if recurring years is greater than 0
						if ( $recur > 0 ) {
							for ( $i = 1; $i <= $recur; $i++ ) {
								// add the date for the future years
								$holiday_array[] = date( 'j-n-Y', strtotime( '+' . $i . 'years', strtotime( $dates ) ) );
							}
						}
					}
				}
			}

			$booking_holidays_string = '';
			// create a string from the array
			foreach ( $holiday_array as $dates ) {
				$booking_holidays_string .= '"' . $dates . '",';
			}

			$holiday_list = $booking_holidays_string . $book_global_holidays;

			if ( $booking_holidays_string != '' && $book_global_holidays == '' ) {
				$holiday_list = substr( $holiday_list, 0, -1 );
			}

			$additional_data['holidays'] = $holiday_list;

			// fetch specific booking dates
			$booking_dates_arr = ( isset( $booking_settings['booking_specific_date'] ) ) ? $booking_settings['booking_specific_date'] : array();

			$booking_dates_str = '';

			if ( $booking_dates_arr != '' && count( $booking_dates_arr ) > 0 && count( $holiday_array ) > 0 ) {
				$booking_dates_arr = self::bkap_check_specificdate_in_global_holiday( $booking_dates_arr, $holiday_array );
			}

			$day_date_timeslots = get_post_meta( $product_id, '_bkap_time_settings', true );
			$booking_type       = get_post_meta( $product_id, '_bkap_booking_type', true );

			// When Date and Time is enabled that time removing the date
			// from the list of bookable date when date is added but no timeslot is created.
			if ( $booking_type == 'date_time' ) {

				$day_date_of_timeslots = array_keys( $day_date_timeslots );

				if ( ! empty( $booking_dates_arr ) ) {

					foreach ( $booking_dates_arr as $k => $v ) {
						if ( ! in_array( $k, $day_date_of_timeslots ) ) {
							unset( $booking_dates_arr[ $k ] );
						} elseif ( empty( $day_date_timeslots[ $k ] ) ) {
							unset( $booking_dates_arr[ $k ] );
						}

						if ( strtotime( $k ) < $today_midnight ) {
							unset( $booking_dates_arr[ $k ] );
						}
					}
				}
			} else {
				if ( ! empty( $booking_dates_arr ) ) {
					foreach ( $booking_dates_arr as $k => $v ) {
						if ( strtotime( $k ) < $today_midnight ) {
							unset( $booking_dates_arr[ $k ] );
						}
					}
				}
			}

			$specific_booking = false;

			if ( isset( $booking_settings['booking_specific_booking'] ) && $booking_settings['booking_specific_booking'] == 'on' ) {

				$specific_booking = true;

				if ( ! empty( $booking_dates_arr ) ) {
					// @since 4.0.0 they are now saved as date (key) and lockout (value)
					foreach ( $booking_dates_arr as $k => $v ) {
						$booking_dates_str .= '"' . $k . '",';
					}
				}

				$booking_dates_str = substr( $booking_dates_str, 0, strlen( $booking_dates_str ) - 1 );
			}

			$additional_data['specific_dates'] = apply_filters( 'bkap_specific_dates', $booking_dates_str, $product_id, $booking_settings );

			$ranges = array();

			// custom ranges
			$custom_ranges = isset( $booking_settings['booking_date_range'] ) ? $booking_settings['booking_date_range'] : array();

			if ( $specific_booking && is_array( $custom_ranges ) && count( $custom_ranges ) > 0 ) {

				foreach ( $custom_ranges as $range ) {
					$start = $range['start'];
					$end   = $range['end'];
					$recur = ( isset( $range['years_to_recur'] ) && $range['years_to_recur'] > 0 ) ? $range['years_to_recur'] : 0;

					for ( $i = 0; $i <= $recur; $i++ ) {
						// get the start & end dates
						$start_date = date( 'j-n-Y', strtotime( "+$i years", strtotime( $start ) ) );
						$end_date   = date( 'j-n-Y', strtotime( "+$i years", strtotime( $end ) ) );
						$ranges[]   = array(
							'start' => $start_date,
							'end'   => $end_date,
						);
					}
				}
			}

			// month ranges
			$month_ranges = get_post_meta( $product_id, '_bkap_month_ranges', true );

			if ( $specific_booking && is_array( $month_ranges ) && count( $month_ranges ) > 0 ) {

				foreach ( $month_ranges as $range ) {
					$start = $range['start'];
					$end   = $range['end'];
					$recur = ( isset( $range['years_to_recur'] ) && $range['years_to_recur'] > 0 ) ? $range['years_to_recur'] : 0;

					for ( $i = 0; $i <= $recur; $i++ ) {
						// get the start & end dates
						$start_date = date( 'j-n-Y', strtotime( "+$i years", strtotime( $start ) ) );
						$end_date   = date( 'j-n-Y', strtotime( "+$i years", strtotime( $end ) ) );
						$ranges[]   = array(
							'start' => $start_date,
							'end'   => $end_date,
						);
					}
				}
			}

			if ( is_array( $ranges ) && count( $ranges ) > 0 ) {
				// default the fields
				$min_date = '';
				$days     = '';

				$active_dates = array();
				$loop_count   = count( $ranges );

				for ( $i = 0; $i < $loop_count; $i++ ) {

					$key   = '';
					$first = true;

					foreach ( $ranges as $range_key => $range_data ) {

						if ( $first ) {
							$min_start = $range_data['start'];
							$min_end   = $range_data['end'];
							$key       = $range_key;
							$first     = false;
						}

						$new_start = strtotime( $range_data['start'] );

						if ( $new_start < strtotime( $min_start ) ) {
							$min_start = $range_data['start'];
							$min_end   = $range_data['end'];
							$key       = $range_key;
						}
					}

					$active_dates[] = array(
						'start' => $min_start,
						'end'   => $min_end,
					); // add the minimum data to the new array

					unset( $ranges[ $key ] ); // remove the minimum start & end record
				}

				// now get the first start date i.e. the min date

				foreach ( $active_dates as $dates ) {
					// very first active range
					$start = $dates['start'];

					// if it is a past date, check the end date to see if the entire range is past
					if ( strtotime( $start ) < $current_time ) {
						$end = $dates['end'];

						if ( strtotime( $end ) < $current_time ) {
							continue; // range is past, so check the next record
						} else { // few days left in the range
							$min_date = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time );  // so min date is today
							break;
						}
					} else { // this is a future date
						$min_date = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time );
						if ( strtotime( $start ) >= strtotime( $min_date ) ) {
							$min_date = $dates['start'];
						}
						break;
					}
				}

				// set the max date
				$active_dates_count  = count( $active_dates );
				$active_dates_count -= 1;
				$days                = $active_dates[ $active_dates_count ]['end'];

				// if min date is blanks, happens when all ranges are in the past
				if ( $min_date === '' ) {
					$min_date = $active_dates[ $active_dates_count ]['end'];
				}

				$fixed_date_range = '';
				// create the fixed date range record
				foreach ( $active_dates as $dates ) {
					$fixed_date_range .= '"' . $dates['start'] . '","' . $dates['end'] . '",';
				}

				if ( $fixed_date_range != '' ) {
					$fixed_date_range = substr( $fixed_date_range, 0, strlen( $fixed_date_range ) - 1 );
				}

				$additional_data['fixed_ranges'] = $fixed_date_range;

			} else { // follow ABP and Number of Dates.
				$min_date = '';
				$days     = '';
				$min_date = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time );

				if ( ! bkap_check_weekdays_status( $product_id, true ) ) {
					if ( ! empty( $booking_dates_arr ) ) {
						$min_date = bkap_closest_specific_date( $booking_dates_arr, strtotime( $min_date ) );
					}
				}

				if ( isset( $booking_settings['booking_maximum_number_days'] ) ) {
					$days = $booking_settings['booking_maximum_number_days'];
				}
			}
			// check mindate is today.. if yes, then check if all time slots are past, if yes, then set mindate to tomorrow.
			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {

				$last_slot_hrs    = 0;
				$current_slot_hrs = 0;
				$last_slot_min    = 0;

				if ( is_array( $booking_settings['booking_time_settings'] ) && array_key_exists( $min_date, $booking_settings['booking_time_settings'] ) ) {

					foreach ( $booking_settings['booking_time_settings'][ $min_date ] as $key => $value ) {
						$current_slot_hrs = $value['from_slot_hrs'];

						if ( $current_slot_hrs > $last_slot_hrs ) {
							$last_slot_hrs    = $current_slot_hrs;
							$last_slot_min    = $value['from_slot_min'];
							$last_slot_to_hrs = $value['to_slot_hrs'];
							$last_slot_to_min = $value['to_slot_min'];
						}
					}
				} else {
					// Get the weekday as it might be a recurring day setup.
					$weekday         = date( 'w', strtotime( $min_date ) );
					$booking_weekday = "booking_weekday_$weekday";

					if ( is_array( $booking_settings['booking_time_settings'] ) && array_key_exists( $booking_weekday, $booking_settings['booking_time_settings'] ) ) {

						foreach ( $booking_settings['booking_time_settings'][ $booking_weekday ] as $key => $value ) {
							$current_slot_hrs = $value['from_slot_hrs'];
							if ( $current_slot_hrs >= $last_slot_hrs ) {
								$last_slot_hrs    = $current_slot_hrs;
								$last_slot_min    = $value['from_slot_min'];
								$last_slot_to_hrs = $value['to_slot_hrs'];
								$last_slot_to_min = $value['to_slot_min'];
							}
						}
					}
				}

				if ( $last_slot_hrs == 0 && $last_slot_min == 0 ) {
				} else {
					$last_slot = $last_slot_hrs . ':' . $last_slot_min;

					$advance_booking_hrs = bkap_advance_booking_hrs( $booking_settings, $product_id );

					$booking_date2 = $min_date . ' ' . $last_slot;
					$booking_date2 = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_date2, $min_date, $last_slot, $last_slot_to_hrs . ':' . $last_slot_to_min, $product_id, $booking_settings );
					$booking_date2 = date( 'Y-m-d H:i', strtotime( $booking_date2 ) );

					$date2         = new DateTime( $booking_date2 );
					$booking_date1 = date( 'Y-m-d H:i', $current_time );
					$date1         = new DateTime( $booking_date1 );
					$phpversion    = version_compare( phpversion(), '5.3', '>' );
					$include       = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

					if ( ! $include ) {
						$min_date = date( 'j-n-Y', strtotime( $min_date . '+1 day' ) );
					}
				}
			} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' === $booking_settings['booking_enable_time'] ) {

				$duration_settings = $booking_settings['bkap_duration_settings'];
				$d_end_time        = $duration_settings['end_duration'];

				if ( '' !== $d_end_time ) {

					$advance_booking_hrs = bkap_advance_booking_hrs( $booking_settings, $product_id );
					$booking_date1       = date( 'Y-m-d H:i', $current_time );
					$date1               = new DateTime( $booking_date1 );

					$booking_date2 = $min_date . ' ' . $d_end_time;
					$booking_date2 = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_date2, $min_date, $d_end_time, $d_end_time, $product_id, $booking_settings );
					$booking_date2 = date( 'Y-m-d H:i', strtotime( $booking_date2 ) );
					$date2         = new DateTime( $booking_date2 );

					$phpversion = version_compare( phpversion(), '5.3', '>' );
					$include    = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

					if ( ! $include ) {
						$min_date = date( 'j-n-Y', strtotime( $min_date . '+1 day' ) );
					}
				}
			}

			// before setting the max date we need to make sure that at least 1 recurring day or a specific date is set.
			// This is necessary to ensure the datepicker doesnt go into an endless loop.
			if ( $check_time ) { // date & time bookings
				if ( ! $timeslots_present ) { // no time slots are present.
					$days = 0;
				}
			} else { // only day bookings.
				if ( $number_of_days == 0 && ! in_array( 'booking_specific_date', $booking_settings ) &&
				( in_array( 'booking_specific_date', $booking_settings ) && ( ! is_array( $booking_settings['booking_specific_date'] ) ) ||
				in_array( 'booking_specific_date', $booking_settings ) && is_array( $booking_settings['booking_specific_date'] ) &&
				count( $booking_settings['booking_specific_date'] ) == 0 ) ) {
					$days = 0;
				}
			}

			$additional_data['min_date']        = $min_date;
			$additional_data['number_of_dates'] = $days;

			$lockout_dates_str   = '';
			$lockout_dates_str_1 = '';

			if ( isset( $booking_settings['booking_enable_time'] ) && in_array( $booking_settings['booking_enable_time'], array( 'on', 'dates_time' ) ) || ( in_array( $booking_settings['booking_enable_time'], array( 'on', 'dates_time' ) ) && $booking_settings['booking_specific_booking'] == 'on' ) ) { // if date_time booking method

				// lockout dates that have a date/day lockout but no time lockout.
				$locked_dates = bkap_get_lockout( $product_id, $min_date, $days, $booking_settings ); // array of locked dates.
				$locked_dates = bkap_locked_dates_fixed_time( $locked_dates, $product_id, $booking_settings );
				if ( is_array( $locked_dates ) && count( $locked_dates ) > 0 ) {
					foreach ( $locked_dates as $k => $v ) {
						$lockout_dates_str_1 .= "$v,";
						$lockout_dates_str   .= '"' . $v . '",';
					}
				}
			} elseif ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' === $booking_settings['booking_enable_time'] ) {

				// lockout dates that have a date/day lockout but no time lockout
				$locked_dates = bkap_get_duration_lockout_fixing( $product_id, $min_date, $days ); // array of locked dates

				if ( is_array( $locked_dates ) && count( $locked_dates ) > 0 ) {
					foreach ( $locked_dates as $k => $v ) {
						$lockout_dates_str_1 .= "$v,";
						$lockout_dates_str   .= '"' . $v . '",';
					}
				}
			} elseif ( $booking_type != 'multiple_days' ) {

				$single_day_lockedout_dates = bkap_get_lockout( $product_id, $min_date, $days, $booking_settings, 'Y-m-d' ); // array of locked dates.

				$single_day_lockedout_dates = apply_filters( 'bkap_block_dates_single', $single_day_lockedout_dates, $booking_settings, $product_id );

				foreach ( $single_day_lockedout_dates as $k => $v ) {
					$lockout_temp         = date( 'j-n-Y', strtotime( $v ) );
					$lockout_dates_str_1 .= $lockout_temp . ',';
					$lockout_dates_str   .= '"' . $lockout_temp . '",';
					$lockout_temp         = '';
				}
			}

			$lockout_dates_str = ( '' !== $lockout_dates_str ) ? substr( $lockout_dates_str, 0, strlen( $lockout_dates_str ) - 1 ) : '';
			$lockout_dates     = $lockout_dates_str;

			$additional_data['wapbk_lockout_days'] = $lockout_dates;

			$lockout_dates_array = array();

			if ( $lockout_dates != '' ) {
				$lockout_dates_array = explode( ',', $lockout_dates_str_1 );
			}

			if ( $booking_type == 'multiple_days' ) {

				$todays_date = date( 'Y-m-d' );

				$query_date = "SELECT DATE_FORMAT(start_date,'%d-%c-%Y') as start_date,DATE_FORMAT(end_date,'%d-%c-%Y') as end_date
		    					FROM " . $wpdb->prefix . "booking_history
								WHERE ( start_date >='" . $todays_date . "'
								        OR end_date >='" . $todays_date . "'
								)
								AND post_id = '" . $product_id . "'";

				$results_date = $wpdb->get_results( $query_date );

				$dates_new    = array();
				$booked_dates = array();

				if ( isset( $results_date ) && count( $results_date ) > 0 && $results_date != false ) {

					foreach ( $results_date as $k => $v ) {
						$start_date = $v->start_date;
						$end_date   = $v->end_date;

						if ( isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' ) {
							$dates = bkap_common::bkap_get_betweendays( $start_date, $end_date );
						} else {
							$dates = bkap_common::bkap_get_betweendays( $start_date, $end_date );
						}

						$dates_new = array_merge( $dates, $dates_new );
					}
				}

				// Enable the start date for the booking period for checkout
				if ( isset( $results_date ) && count( $results_date ) > 0 && $results_date != false ) {

					foreach ( $results_date as $k => $v ) {
						$start_date = $v->start_date;
						$end_date   = $v->end_date;
						$new_start  = strtotime( '+1 day', strtotime( $start_date ) );
						$new_start  = date( 'd-n-Y', $new_start );
						if ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) {
							if ( $k > 0 && $start_date == $results_date[ $k - 1 ]->end_date ) {
								$new_start = strtotime( '-1 day', strtotime( $new_start ) );
								$new_start = date( 'd-n-Y', $new_start );
							}
						}

						if ( isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' ) {
							$dates = bkap_common::bkap_get_betweendays_when_flat( $new_start, $end_date, $product_id );
						} else {
							$dates = bkap_common::bkap_get_betweendays( $new_start, $end_date );
						}
						$booked_dates = array_merge( $dates, $booked_dates );
					}
				}

				$dates_new_arr    = array_count_values( $dates_new );
				$booked_dates_arr = array_count_values( $booked_dates );

				$lockout = bkap_get_maximum_booking( $product_id, $booking_settings );

				$new_arr_str = '';

				foreach ( $dates_new_arr as $k => $v ) {

					if ( isset( $booking_dates_arr[ $k ] ) ) {
						if ( $v >= $booking_dates_arr[ $k ] && $booking_dates_arr[ $k ] != 0 ) {
							$date_temp = $k;
							$date      = explode( '-', $date_temp );
							array_push( $lockout_dates_array, ( intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] ) );
							$new_arr_str .= '"' . intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] . '",';
							$date_temp    = '';
						}
					} else {
						if ( $v >= $lockout && $lockout != 0 ) {
							$date_temp = $k;
							$date      = explode( '-', $date_temp );
							array_push( $lockout_dates_array, ( intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] ) );
							$new_arr_str .= '"' . intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] . '",';
							$date_temp    = '';
						}
					}
				}

				if ( $new_arr_str != '' ) {
					$new_arr_str = substr( $new_arr_str, 0, strlen( $new_arr_str ) - 1 );
				}

				$additional_data['wapbk_hidden_booked_dates'] = $new_arr_str;

				// checkout calendar booked dates
				$blocked_dates    = array();
				$booked_dates_str = '';

				foreach ( $booked_dates_arr as $k => $v ) {

					if ( isset( $booking_dates_arr[ $k ] ) ) {
						if ( $v >= $booking_dates_arr[ $k ] && $booking_dates_arr[ $k ] != 0 ) {
							$date_temp                  = $k;
							$date                       = explode( '-', $date_temp );
							$date_without_zero_prefixed = intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2];
							$booked_dates_str          .= '"' . intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] . '",';
							$date_temp                  = '';
							$blocked_dates[]            = $date_without_zero_prefixed;
						}
					} else {
						if ( $v >= $lockout && $lockout != 0 ) {
							$date_temp                  = $k;
							$date                       = explode( '-', $date_temp );
							$date_without_zero_prefixed = intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2];
							$booked_dates_str          .= '"' . intval( $date[0] ) . '-' . intval( $date[1] ) . '-' . $date[2] . '",';
							$date_temp                  = '';
							$blocked_dates[]            = $date_without_zero_prefixed;
						}
					}
				}

				if ( $booked_dates_str != '' ) {
					$booked_dates_str = substr( $booked_dates_str, 0, strlen( $booked_dates_str ) - 1 );
				}

				$additional_data['wapbk_hidden_booked_dates_checkout'] = $booked_dates_str;
			}

			$current_time = strtotime( $min_date );

			$default_date = '';
			$fix_min_day  = date( 'w', strtotime( $min_date ) );
			$default_date = self::bkap_first_available( $product_id, $lockout_dates_array, $min_date );

			// if default date is blanks due to any reason
			$no_default_found = 1;
			if ( $default_date == '' ) {
				$no_default_found = 0;
				$default_date     = date( 'j-n-Y', current_time( 'timestamp' ) );
			}

			// if fixed date range is used, confirm that the default date falls in the range
			if ( $default_date != '' ) {
				if ( is_array( $ranges ) && count( $ranges ) > 0 ) {
					if ( strtotime( $default_date ) > strtotime( $days ) ) {
						$no_default_found = 0; // this will ensure the hidden date field is not populated and the product cannot be added to the cart
					}
				}
			}

			$additional_data['bkap_disabled_dates'] = '';
			if ( ( isset( $booking_settings['bkap_manage_time_availability'] ) && ! empty( $booking_settings['bkap_manage_time_availability'] ) )
			|| ( isset( $booking_settings['bkap_all_data_unavailable'] ) && 'on' === $booking_settings['bkap_all_data_unavailable'] )
			) {

				$manage_time_availability     = $booking_settings['bkap_manage_time_availability'];
				$availabile_dates_in_calendar = array();

				if ( strpos( $days, '-' ) == false ) {

					$start_booking_str            = strtotime( $default_date );
					$max_booking_date             = calback_bkap_max_date( $start_booking_str, $days, $booking_settings );
					$availabile_dates_in_calendar = bkap_common::bkap_get_betweendays( $default_date, $max_booking_date, 'j-n-Y', $booking_settings['booking_recurring'] );

				} else {
					$max_booking_date = $days;

					foreach ( $active_dates as $key => $value ) {

						$all_custom_dates             = array();
						$all_custom_dates             = bkap_common::bkap_get_betweendays( $value['start'], $value['end'], 'j-n-Y' );
						$availabile_dates_in_calendar = array_merge( $availabile_dates_in_calendar, $all_custom_dates );

					}
				}

				// Patching for additional dates getting enabled.
				$last_datein_adic             = end( $availabile_dates_in_calendar );
				$last_datein_adic_str         = strtotime( $last_datein_adic );
				$thirtyth_date                = date( 'j-n-Y', strtotime( '+30 day', $last_datein_adic_str ) );
				$more_thirty_dates            = bkap_common::bkap_get_betweendays( $last_datein_adic, $thirtyth_date, 'j-n-Y' );
				$availabile_dates_in_calendar = array_merge( $availabile_dates_in_calendar, $more_thirty_dates );

				usort( $manage_time_availability, 'bkap_sort_date_time_ranges_by_priority' );

				$mta_holiday_array = array();
				$mta_must_array    = array();

				foreach ( $availabile_dates_in_calendar as $a_key => $a_value ) {

					$holiday_check = false;
					$date_checked  = false;

					$bkap_availabile_date_str = strtotime( $a_value );

					foreach ( $manage_time_availability as $key => $value ) {
						$date_range_start = $date_range_end = '';

						switch ( $value['type'] ) {
							case 'custom':
								$date_range_start = $value['from'];
								$date_range_end   = $value['to'];

								break;
							case 'months':
								$month_range = bkap_get_month_range( $value['from'], $value['to'] );
								$date_month  = date( 'n', $bkap_availabile_date_str );

								if ( $date_month >= $value['from'] && $date_month <= $value['to'] ) {

									if ( $value['bookable'] == 1 && ! in_array( $a_value, $mta_holiday_array ) ) {
										array_push( $mta_must_array, $a_value );
									}

									if ( $value['bookable'] == 0 && ! in_array( $a_value, $mta_must_array ) ) {
										array_push( $mta_holiday_array, $a_value );
									}
									$date_checked = true;
								}

								$date_range_start = $month_range['start'];
								$date_range_end   = $month_range['end'];

								break;
							case 'weeks':
								$week_range = bkap_get_week_range( $value['from'], $value['to'] );

								$date_range_start = $week_range['start'];
								$date_range_end   = $week_range['end'];
								break;

							case 'days':
								$date_status = '';
								$date_day    = date( 'w', $bkap_availabile_date_str );

								$date_status = bkap_get_day_between_Week( $value['from'], $value['to'] );

								if ( strpos( $date_status, $date_day ) !== false && $value['bookable'] == 0 && ! in_array( $a_value, $mta_must_array ) ) {

									array_push( $mta_holiday_array, $a_value );
									// $holiday_check = true;
								}

								if ( strpos( $date_status, $date_day ) !== false && $value['bookable'] == 1 ) {
									$mta_must_array[] = $a_value;
								}
								break;
							case 'time':
							case 'time:range':
							case 'time:0':
							case 'time:1':
							case 'time:2':
							case 'time:3':
							case 'time:4':
							case 'time:5':
							case 'time:6':
								$check = false;
								if ( 'time' === $value['type'] ) {
									$check = true;
								} else {
									$rad_explode = explode( ':', $value['type'] );

									if ( 'range' === $rad_explode[1] ) {
										if ( $bkap_availabile_date_str >= strtotime( $value['from_date'] ) && $bkap_availabile_date_str <= strtotime( $value['to_date'] ) ) {
											$check = true;
										}
									} else {
										$weekday = date( 'w', $bkap_availabile_date_str );
										if ( $weekday == $rad_explode[1] ) {
											$check = true;
										}
									}
								}
								if ( $check ) {
									if ( $value['bookable'] == 1 && ! in_array( $a_value, $mta_holiday_array ) ) {
										$mta_must_array[] = $a_value;
									}
								}
								break;
						}

						if ( ! $date_checked && $bkap_availabile_date_str >= strtotime( $date_range_start ) && $bkap_availabile_date_str <= strtotime( $date_range_end ) ) {

							if ( $value['bookable'] == 1 && ! in_array( $a_value, $mta_holiday_array ) ) {
								array_push( $mta_must_array, $a_value );
							}
							if ( $value['bookable'] == 0 && ! in_array( $a_value, $mta_must_array ) ) {
								if ( $default_date == $a_value ) {
									$default_date = date( 'j-n-Y', strtotime( $default_date . ' +1 day' ) );
								}
								array_push( $mta_holiday_array, $a_value );
							}
							$holiday_check = true;
						}
					}
				}

				if ( isset( $booking_settings['bkap_all_data_unavailable'] ) && 'on' === $booking_settings['bkap_all_data_unavailable'] && is_array( $mta_must_array ) && count( $mta_must_array ) > 0 ) {
					$bkap_all_data_unavailable = true;
					$mta_holiday_array         = array_values( array_diff( $availabile_dates_in_calendar, $mta_must_array ) );

					if ( ! in_array( $default_date, $mta_must_array ) && isset( $mta_must_array[0] ) ) {
						$default_date = $mta_must_array[0];
					}
				}

				if ( ! empty( $mta_holiday_array ) && is_array( $mta_holiday_array ) && count( $mta_holiday_array ) > 0 ) {
					$mta_holiday_array = array_values( array_unique( $mta_holiday_array ) );
					/*
					 if ( isset( $additional_data['holidays'] ) && '' !== $additional_data['holidays'] ) {
					$mta_holiday_string = ',"' . implode( '","', $mta_holiday_array ) . '"';
					$additional_data['holidays'] = $additional_data['holidays'] . $mta_holiday_string;
					} else {
					$additional_data['holidays'] = '"' . implode( '","', $mta_holiday_array )  . '"';
					} */

					$additional_data['bkap_disabled_dates'] = '"' . implode( '","', $mta_holiday_array ) . '"';
				}
			}

			// Resource calculations.
			$additional_data['resource_disable_dates'] = array();
			$resource_array                            = Class_Bkap_Product_Resource::bkap_add_additional_resource_data( array(), $booking_settings, $product_id );

			$resource_holiday_array = array();
			$resource_must_array    = array();
			if ( ! empty( $resource_array ) ) {

				$resource_ids = Class_Bkap_Product_Resource::bkap_get_product_resources( $product_id );

				$default_date = self::bkap_first_available_resource_date( $product_id, $default_date );

				$availabile_dates_in_calendar = array();

				if ( strpos( $days, '-' ) == false ) {

					$start_booking_str            = strtotime( $default_date );
					$max_booking_date             = apply_filters( 'bkap_max_date', $start_booking_str, $days, $booking_settings );
					$availabile_dates_in_calendar = bkap_common::bkap_get_betweendays( $default_date, $max_booking_date, 'j-n-Y' );

				} else {
					$max_booking_date = $days;

					foreach ( $active_dates as $key => $value ) {

						$all_custom_dates             = array();
						$all_custom_dates             = bkap_common::bkap_get_betweendays( $value['start'], $value['end'], 'j-n-Y' );
						$availabile_dates_in_calendar = array_merge( $availabile_dates_in_calendar, $all_custom_dates );

					}
				}

				foreach ( $resource_ids as $resource_id ) {

					$resource_availability = $resource_array['bkap_resource_data'][ $resource_id ]['resource_availability'];

					if ( isset( $bkap_all_data_unavailable ) ) {
						$resource_availability = $manage_time_availability;
					}
					if ( ! isset( $bkap_all_data_unavailable ) && is_array( $resource_availability ) && count( $resource_availability ) > 0 ) {

						usort( $resource_availability, 'bkap_sort_date_time_ranges_by_priority' );

						$resource_holiday_array[ $resource_id ] = array();
						$resource_must_array[ $resource_id ]    = array();

						foreach ( $availabile_dates_in_calendar as $a_key => $a_value ) {

							$holiday_check = false;

							$bkap_availabile_date_str = strtotime( $a_value );
							$timeslots_for_date       = array();
							if ( 'date_time' == $booking_type ) {
								$timeslots_for_date       = bkap_common::bkap_get_all_timeslots_for_weekday_date( $product_id, $a_value, $booking_settings );
								$timeslots_for_date_count = count( $timeslots_for_date );
							}
							$rdisable_date_timeslots = array();
							$rdisable_date           = false;
							$rdisable_date_count     = 0;
							foreach ( $resource_availability as $key => $value ) {
								$date_range_start = $date_range_end = '';

								switch ( $value['type'] ) {
									case 'custom':
										$date_range_start = $value['from'];
										$date_range_end   = $value['to'];

										break;
									case 'months':
										$month_range = bkap_get_month_range( $value['from'], $value['to'] );

										$date_range_start = $month_range['start'];
										$date_range_end   = $month_range['end'];

										break;
									case 'weeks':
										$week_range = bkap_get_week_range( $value['from'], $value['to'] );

										$date_range_start = $week_range['start'];
										$date_range_end   = $week_range['end'];
										break;

									case 'days':
										$date_status = '';
										$date_day    = date( 'w', $bkap_availabile_date_str );

										$date_status = bkap_get_day_between_Week( $value['from'], $value['to'] );

										if ( strpos( $date_status, $date_day ) !== false && $value['bookable'] == 0 && ! in_array( $a_value, $resource_must_array[ $resource_id ] ) ) {

											array_push( $resource_holiday_array[ $resource_id ], $a_value );
											// $holiday_check = true;
										}

										if ( strpos( $date_status, $date_day ) !== false && $value['bookable'] == 1 ) {
											$resource_must_array[ $resource_id ][] = $a_value;
										}
										break;
									case 'time':
									case 'time:range':
									case 'time:0':
									case 'time:1':
									case 'time:2':
									case 'time:3':
									case 'time:4':
									case 'time:5':
									case 'time:6':
										$check = false;
										if ( 'time' === $value['type'] ) {
											$check = true;
										} else {
											$rad_explode = explode( ':', $value['type'] );

											if ( 'range' === $rad_explode[1] ) {
												if ( $bkap_availabile_date_str >= strtotime( $value['from_date'] ) && $bkap_availabile_date_str <= strtotime( $value['to_date'] ) ) {
													$check = true;
												}
											} else {
												$weekday = date( 'w', $bkap_availabile_date_str );
												if ( $weekday == $rad_explode[1] ) {
													$check = true;
												}
											}
										}
										if ( $check ) {

											// Issue #4538.
											if ( ! empty( $timeslots_for_date ) ) {

												if ( 'time' === $value['type'] ) {
													$r_selected_date_time = $a_value;
													$r_compare_fdate_time = $a_value;
													$r_compare_tdate_time = $a_value;
												} else {
													if ( 'range' === $rad_explode[1] ) {
														$r_selected_date_time = $a_value;
														$r_compare_fdate_time = $value['from_date'];
														$r_compare_tdate_time = $value['to_date'];
													} else {
														$r_selected_date_time = $a_value;
														$r_compare_fdate_time = $a_value;
														$r_compare_tdate_time = $a_value;
													}
												}

												$r_rfrom = strtotime( $r_compare_fdate_time . ' ' . $value['from'] );
												$r_rto   = strtotime( $r_compare_tdate_time . ' ' . $value['to'] );
												foreach ( $timeslots_for_date as $timeslot_for_date ) {
													$r_sfrom = strtotime( $r_selected_date_time . ' ' . $timeslot_for_date['from'] );
													$r_sto   = strtotime( $r_selected_date_time . ' ' . $timeslot_for_date['to'] );

													if ( ( $r_sfrom > $r_rfrom && $r_sfrom < $r_rto )
													|| ( $r_sto > $r_rfrom && $r_sto < $r_rto )
													|| ( $r_sfrom > $r_rfrom && $r_sto < $r_rto )
													|| ( $r_sfrom < $r_rfrom && $r_sto > $r_rto )

													) {
														$rdisable_date             = true;
														$rdisable_date_timeslots[] = $timeslot_for_date['from'] . ' - ' . $timeslot_for_date['to'];
														$rdisable_date_count++;
													}
												}
											}

											/*
											 if ( $value['bookable'] == 0 && ! in_array( $a_value, $resource_must_array[ $resource_id ] ) ) {
											$resource_holiday_array[ $resource_id ][] = $a_value;
											} */
										}
										break;
								}

								if ( '' === $date_range_start ) { // Time range.

									if ( $rdisable_date ) {
										if ( isset( $rdisable_date_timeslots ) && count( array_unique( $rdisable_date_timeslots ) ) == $timeslots_for_date_count ) {
											array_push( $resource_holiday_array[ $resource_id ], $a_value );
										}
									}
									if ( $value['bookable'] == 1 ) {
										$resource_must_array[ $resource_id ][] = $a_value;
									}
								}

								if ( $bkap_availabile_date_str >= strtotime( $date_range_start ) && $bkap_availabile_date_str <= strtotime( $date_range_end ) ) {

									if ( $value['bookable'] == 1 ) {
										array_push( $resource_must_array[ $resource_id ], $a_value );
									}
									if ( $value['bookable'] == 0 && ! in_array( $a_value, $resource_must_array[ $resource_id ] ) ) {
										array_push( $resource_holiday_array[ $resource_id ], $a_value );
									}
									$holiday_check = true;
								}

								/*
								 if ( $holiday_check ) {
								break;
								} */
							}
						}

						$resource_holiday_array[ $resource_id ] = array_values( array_unique( $resource_holiday_array[ $resource_id ] ) );
					} else {
						$resource_holiday_array[ $resource_id ] = '';
					}
				}
			}

			$additional_data['resource_disable_dates'] = $resource_holiday_array;
			$additional_data['default_date']           = $default_date;

			$admin_booking = ( isset( $_GET['page'] ) && $_GET['page'] === 'bkap_create_booking_page' ) ? true : false;

			$session_start_date = bkap_common::bkap_date_from_session_cookie( 'start_date' );
			$session_end_date   = bkap_common::bkap_date_from_session_cookie( 'end_date' );

			if ( isset( $booking_settings['enable_inline_calendar'] ) && $booking_settings['enable_inline_calendar'] == 'on' ) {

				// if there are no products in the cart, then the hidden field should be populated with the default date
				// hence defaulting it with the same.
				$hidden_date = ( isset( $no_default_found ) && $no_default_found ) ? $default_date : '';

				$hidden_date_checkout = '';
				$widget_search        = 0;
				// $bkap_block_booking = new bkap_block_booking();
				// $number_of_fixed_price_blocks  =   bkap_block_booking::bkap_get_fixed_blocks_count( $product_id );

				if ( $session_start_date ) {

					$session_start_strtotime = strtotime( $session_start_date );
					$hidden_date             = date( 'j-n-Y', $session_start_strtotime );
					$first_available_date    = self::bkap_first_available( $product_id, $lockout_dates_array, $hidden_date );
					$hidden_date             = $first_available_date;
					$hidden_date             = self::bkap_first_available_resource_date( $product_id, $hidden_date );
					$widget_search           = 1;
					if ( strtotime( $hidden_date ) < strtotime( $default_date ) ) {
						$hidden_date = $default_date;
					}
				}

				if ( $session_end_date ) {

					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
						$session_end_strtotime = strtotime( $session_end_date );

						$hidden_date_checkout = date( 'j-n-Y', $session_end_strtotime );
						if ( $hidden_date == $hidden_date_checkout ) {
							$hidden_date_checkout = bkap_add_days_to_date( $hidden_date_checkout, 1 );
						} elseif ( strtotime( $hidden_date ) > strtotime( $hidden_date_checkout ) ) {
							$hidden_date_checkout = bkap_add_days_to_date( $hidden_date, 1 );
						}
					}
				}

				/**
				 * Populating dates based on the bookable product available in the cart.
				 */

				if ( isset( $global_settings->booking_global_selection ) && $global_settings->booking_global_selection == 'on' ) {

					if ( ! $admin_booking && ! $edit && isset( WC()->cart ) ) {
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

							if ( array_key_exists( 'bkap_booking', $values ) ) {
								$booking         = $values['bkap_booking'];
								$duplicate_date  = $booking[0]['hidden_date'];
								$hidden_date_arr = explode( '-', $duplicate_date );
								$hidden_time     = mktime( 0, 0, 0, $hidden_date_arr[1], $hidden_date_arr[0], $hidden_date_arr[2] );

								$hidden_date = ( $hidden_time > $current_time ) ? $booking[0]['hidden_date'] : $default_date;

								$first_available_date = self::bkap_first_available( $product_id, $lockout_dates_array, $duplicate_date );
								if ( $duplicate_date !== $first_available_date ) {
									$hidden_date = $first_available_date;
								}

								if ( isset( $booking[0]['hidden_date_checkout'] ) ) {
									$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
									$daysbetween          = bkap_get_days_between_two_dates( $hidden_date, $hidden_date_checkout );

									if ( $daysbetween > $booking_settings['booking_maximum_number_days_multiple'] ) {
										$hidden_date_checkout = bkap_add_days_to_date( $hidden_date, $booking_settings['booking_maximum_number_days_multiple'] );
									}
								}
							}
							break;
						}
					}
				}

				$booking_date = '';

				if ( isset( $booking_settings['booking_fixed_block_enable'] ) && $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable' ) {

					if ( isset( $widget_search ) && 1 == $widget_search ) {
						self::bkap_prepopulate_fixed_block( $product_id );
					}

					$hidden_date = self::set_fixed_block_hidden_date(
						$hidden_date,
						$product_id,
						$holiday_array,
						$global_holidays,
						$lockout_dates_array
					);
				}
			} else {

				$hidden_date          = '';
				$hidden_date_checkout = '';
				$widget_search        = 0;

				if ( $session_start_date ) {
					$hidden_date               = date( 'j-n-Y', strtotime( $session_start_date ) );
					$first_available_date      = self::bkap_first_available( $product_id, $lockout_dates_array, $hidden_date );
					$numbers_of_days_to_choose = isset( $booking_settings['booking_maximum_number_days'] ) ? $booking_settings['booking_maximum_number_days'] - 1 : '';
					$max_booking_date          = apply_filters( 'bkap_max_date', $min_date, $numbers_of_days_to_choose, $booking_settings );
					$first_available_date_str  = strtotime( $first_available_date );
					$max_booking_date_str      = strtotime( $max_booking_date );
					$hidden_date               = $first_available_date;

					if ( $first_available_date_str > $max_booking_date_str ) { // see if date is greater then max date then use max date.
						$hidden_date = $max_booking_date;
					}

					$widget_search = 1;
				}

				if ( $session_end_date ) {
					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {
						$start_ts = strtotime( $session_start_date );
						$end_ts   = strtotime( $session_end_date );

						if ( $start_ts == $end_ts ) {

							if ( is_plugin_active( 'bkap-rental/rental.php' ) ) {

								if ( isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' && isset( $booking_settings['booking_same_day'] ) && $booking_settings['booking_same_day'] == 'on' ) {
									$hidden_date_checkout = date( 'j-n-Y', strtotime( $session_end_date ) );
								} else {
									$next_end_date        = strtotime( '+1 day', strtotime( $session_end_date ) );
									$hidden_date_checkout = date( 'j-n-Y', $next_end_date );
								}
							} else {
								$next_end_date        = strtotime( '+1 day', strtotime( $session_end_date ) );
								$hidden_date_checkout = date( 'j-n-Y', $next_end_date );
							}
						} else {

							$number_of_days = array();

							if ( isset( $booking_settings['enable_minimum_day_booking_multiple'] )
							&& 'on' == $booking_settings['enable_minimum_day_booking_multiple']
							&& $booking_settings['booking_minimum_number_days_multiple'] > 0
							) {
								$number_of_days = bkap_common::bkap_get_betweendays( $session_start_date, $session_end_date );

								if ( count( $number_of_days ) >= $booking_settings['booking_minimum_number_days_multiple'] ) {

									$hidden_date_checkout = date( 'j-n-Y', strtotime( $session_end_date ) );
									$min_search_checkout  = $hidden_date_checkout;
								} else {
									$minimum_number_of_days = $booking_settings['booking_minimum_number_days_multiple'];
									$end_ts                 = strtotime( '+' . $minimum_number_of_days . 'day', strtotime( $session_start_date ) );
									$hidden_date_checkout   = date( 'j-n-Y', $end_ts );
									$min_search_checkout    = $hidden_date_checkout;
								}
							} else {
								$hidden_date_checkout = date( 'j-n-Y', strtotime( $session_end_date ) );
							}
						}

						if ( isset( $widget_search )
						&& 1 == $widget_search
						&& isset( $booking_settings['booking_fixed_block_enable'] )
						&& $booking_settings['booking_fixed_block_enable'] == 'booking_fixed_block_enable'
						) {

							// fix of auto populating wrong dates when fixed block booking is enabled for the product
							self::bkap_prepopulate_fixed_block( $product_id );
							$hidden_date = self::set_fixed_block_hidden_date(
								$hidden_date,
								$product_id,
								$holiday_array,
								$global_holidays,
								$lockout_dates_array
							);
						}
					}
				}

				if ( isset( $global_settings->booking_global_selection ) && $global_settings->booking_global_selection == 'on' ) {

					if ( ! $admin_booking && ! $edit && isset( WC()->cart ) ) {

						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

							if ( array_key_exists( 'bkap_booking', $values ) ) {
								$booking         = $values['bkap_booking'];
								$duplicate_date  = $booking[0]['hidden_date'];
								$hidden_date_arr = explode( '-', $duplicate_date );
								$hidden_time     = mktime( 0, 0, 0, $hidden_date_arr[1], $hidden_date_arr[0], $hidden_date_arr[2] );

								$hidden_date = ( $hidden_time > $current_time ) ? $booking[0]['hidden_date'] : $default_date;

								$first_available_date = self::bkap_first_available( $product_id, $lockout_dates_array, $duplicate_date );

								if ( $duplicate_date !== $first_available_date ) {
									$hidden_date = $first_available_date;
								}

								$widget_search = 0;

								if ( isset( $booking[0]['hidden_date_checkout'] ) ) {

									$hidden_date_checkout = $booking[0]['hidden_date_checkout'];
									$daysbetween          = bkap_get_days_between_two_dates( $hidden_date, $hidden_date_checkout );

									if ( $daysbetween > $booking_settings['booking_maximum_number_days_multiple'] ) {
										$hidden_date_checkout = bkap_add_days_to_date( $hidden_date, $booking_settings['booking_maximum_number_days_multiple'] );
									}

									if ( strtotime( $hidden_date_checkout ) == strtotime( $hidden_date ) ) {
										if ( ! ( isset( $booking_settings['booking_charge_per_day'] ) && isset( $booking_settings['booking_same_day'] ) ) ) {
											$hidden_date_checkout = date( 'j-n-Y', strtotime( $hidden_date_checkout . ' +1 day' ) );
										}
									}
								}
							}
							break;
						}
					}
				}
			}

			$hidden_dates_array['widget_search']        = $widget_search;
			$hidden_dates_array['hidden_date']          = $hidden_date;
			$hidden_dates_array['hidden_checkout']      = ( isset( $hidden_date_checkout ) && $booking_type == 'multiple_days' ) ? $hidden_date_checkout : '';
			$hidden_dates_array['min_search_checkout']  = ( isset( $min_search_checkout ) ) ? $min_search_checkout : '';
			$additional_data['wapbk_grouped_child_ids'] = bkap_grouped_child_ids( $post_id, $_product );

			$disable_week_days = array();

			// @since 4.0.0 weekdays can be disabled for multiple day booking using the recurring weekday settings
			if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {
				$recurring_days = ( isset( $booking_settings['booking_recurring'] ) ) ? $booking_settings['booking_recurring'] : array();

				if ( is_array( $recurring_days ) && count( $recurring_days ) > 0 ) {
					$checkin_array  = array();
					$checkout_array = array();

					foreach ( $recurring_days as $day_name => $day_status ) {

						if ( '' == $day_status ) {
							$day              = substr( $day_name, -1 );
							$checkin_array[]  = $bkap_days[ $day ];
							$checkout_array[] = $bkap_days[ $day ];
						}
					}

					if ( is_array( $checkin_array ) && count( $checkin_array ) > 0 ) {
						$disable_week_days['checkin'] = $checkin_array;
					}

					if ( is_array( $checkout_array ) && count( $checkout_array ) > 0 ) {
						$disable_week_days['checkout'] = $checkout_array;
					}
				}

				$blocked_dates_hidden_var = '';
				$block_dates              = array();

				$block_dates = (array) apply_filters( 'bkap_block_dates', $product_id, $blocked_dates );

				if ( isset( $block_dates ) && count( $block_dates ) > 0 && $block_dates != false ) {
					$i          = 1;
					$bvalue     = array();
					$add_day    = '';
					$same_day   = '';
					$date_label = '';

					foreach ( $block_dates as $bkey => $bvalue ) {
						$blocked_dates_str = '';

						if ( is_array( $bvalue ) && isset( $bvalue['dates'] ) && count( $bvalue['dates'] ) > 0 ) {
							$blocked_dates_str = '"' . implode( '","', $bvalue['dates'] ) . '"';
						}

						$field_name = $i;

						if ( ( is_array( $bvalue ) && isset( $bvalue['field_name'] ) && $bvalue['field_name'] != '' ) ) {
							$field_name = $bvalue['field_name'];
						}

						$i++;

						if ( is_array( $bvalue ) && isset( $bvalue['add_days_to_charge_booking'] ) ) {
							$add_day = $bvalue['add_days_to_charge_booking'];
						}

						if ( $add_day == '' ) {
							$add_day = 0;
						}

						if ( is_array( $bvalue ) && isset( $bvalue['same_day_booking'] ) ) {
							$same_day = $bvalue['same_day_booking'];
						} else {
							$same_day = '';
						}
						$additional_data['wapbk_add_day']  = $add_day;
						$additional_data['wapbk_same_day'] = $same_day;

					}
					if ( isset( $bvalue['date_label'] ) && $bvalue['date_label'] != '' ) {
						$date_label = $bvalue['date_label'];
					} else {
						$date_label = 'Unavailable for Booking';
					}
				}
				$additional_data['bkap_rent'] = $blocked_dates_str;
			}
			$calendar          = '';
			$disable_week_days = apply_filters( 'bkap_block_weekdays', $disable_week_days );

			if ( isset( $disable_week_days ) && ! empty( $disable_week_days ) ) {

				foreach ( $disable_week_days as $calender_key => $calender_value ) {
					$calendar_name = strtolower( $calender_key );

					if ( 'checkin' == $calendar_name ) {
						$disable_weekdays_array  = array_map( 'trim', $calender_value );
						$disable_weekdays_array  = array_map( 'strtolower', $calender_value );
						$week_days_funcion       = bkap_get_book_arrays( 'bkap_days' );
						$week_days_numeric_value = '';

						foreach ( $week_days_funcion as $week_day_key => $week_day_value ) {

							if ( in_array( strtolower( $week_day_value ), $disable_weekdays_array ) ) {
								$week_days_numeric_value .= $week_day_key . ',';
							}
						}

						$week_days_numeric_value = rtrim( $week_days_numeric_value, ',' );

						$additional_data['wapbk_block_checkin_weekdays'] = $week_days_numeric_value;

					} elseif ( 'checkout' == $calendar_name ) {

						$disable_weekdays_array  = array_map( 'trim', $calender_value );
						$disable_weekdays_array  = array_map( 'strtolower', $calender_value );
						$week_days_funcion       = bkap_get_book_arrays( 'bkap_days' );
						$week_days_numeric_value = '';

						foreach ( $week_days_funcion as $week_day_key => $week_day_value ) {

							if ( in_array( strtolower( $week_day_value ), $disable_weekdays_array ) ) {
								$week_days_numeric_value .= $week_day_key . ',';
							}
						}

						$week_days_numeric_value = rtrim( $week_days_numeric_value, ',' );

						$additional_data['wapbk_block_checkout_weekdays'] = $week_days_numeric_value;
					}
				}
			}

			// POS Addon Block Weekdays
			$recurring_blocked_weekdays                      = '';
			$recurring_blocked_weekdays                      = apply_filters( 'wkpbk_block_recurring_weekdays', $recurring_blocked_weekdays, $product_id );
			$additional_data['bkap_block_selected_weekdays'] = $recurring_blocked_weekdays;

			$currency_symbol = get_woocommerce_currency_symbol();

			$additional_data['wapbk_currency']     = $currency_symbol;
			$additional_data['bkap_currency_args'] = wc_currency_arguments();

			$attribute_change_var     = '';
			$attribute_fields_str     = ',"tyche": 1';
			$on_change_attributes_str = '';
			$attribute_value          = '';
			$attribute_value_selected = '';

			if ( $product_type == 'variable' ) {
				$variations           = $_product->get_available_variations();
				$attributes           = $_product->get_variation_attributes();
				$attribute_fields_str = '';
				$attribute_name       = '';
				$attribute_fields     = array();
				$i                    = 0;

				// Product attributes - taxonomies and custom, ordered, with visibility and variation attributes set
				$bkap_attributes     = get_post_meta( $post_id, '_product_attributes', true );
				$attribute_name_list = '';
				foreach ( $bkap_attributes as $attr_key => $attr_value ) {
					$attribute_name_list .= urldecode( $attr_key ) . ',';
				}

				$variation_price_list = '';

				foreach ( $variations as $var_key => $var_val ) {

					$variation_price_list .= $var_val['variation_id'] . '=>' . $var_val['display_price'] . ',';
					foreach ( $var_val['attributes'] as $a_key => $a_val ) {

						if ( ! in_array( $a_key, $attribute_fields ) ) {
							$attribute_fields[]        = $a_key;
							$attribute_fields_str     .= ",\"$a_key\": jQuery(\"[name='$a_key']\").val() ";
							$attribute_value          .= "$a_key,";
							$attribute_value_selected .= "$a_key,";

							$a_key                  = esc_attr( sanitize_title( $a_key ) );
							$on_change_attributes[] = "[name='" . $a_key . "']";
						}
						$i++;
					}
				}

				if ( $attribute_value != '' ) {
					$attribute_value = substr( $attribute_value, 0, -1 );
				}
				if ( $attribute_value_selected != '' ) {
					$attribute_value_selected = substr( $attribute_value_selected, 0, -1 );
				}

				$on_change_attributes_str                = ( is_array( $on_change_attributes ) && count( $on_change_attributes ) > 0 ) ? implode( ',', $on_change_attributes ) : '';
				$attribute_change_var                    = ''; // moved to process.js
				$additional_data['wapbk_attribute_list'] = $attribute_name_list;
				$additional_data['wapbk_var_price_list'] = $variation_price_list;

			}

			$used_for_modal_display = apply_filters( 'bkap_display_multiple_modals', false );

			wp_register_script( 'bkap-init-datepicker', bkap_load_scripts_class::bkap_asset_url( '/assets/js/initialize-datepicker.js', BKAP_FILE ), '', BKAP_VERSION, false );

			$init_datepicker_param = 'bkap_init_params';
			if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'view-order' ) || $used_for_modal_display ) {
				$init_datepicker_param = "bkap_init_params_$product_id";
			}

			$additional_data['booking_post_id'] = 0; // default
			$additional_data['time_selected']   = ''; // default

			$additional_data = apply_filters( 'bkap_add_additional_data', $additional_data, $booking_settings, $product_id );

			if ( $edit ) { // if a booking post is being edited

				// set inline calendar to off
				$booking_settings['enable_inline_calendar'] = '';

				$booking_post_id = isset( $_GET['post'] ) ? $_GET['post'] : 0;

				if ( $booking_post_id > 0 ) {

					$booking_post_obj = new BKAP_Booking( $booking_post_id );
					// remove the booking date from the locked/holidays list if it matches
					$start_date                        = date( 'j-n-Y', strtotime( get_post_meta( $booking_post_id, '_bkap_start', true ) ) );
					$hidden_dates_array['hidden_date'] = $start_date;
					$resource_id                       = get_post_meta( $booking_post_id, '_bkap_resource_id', true );

					$post_pro_id = $booking_post_obj->get_product_id();

					// check if the date is locked
					if ( strpos( $additional_data['wapbk_lockout_days'], $start_date ) > 0 ) {
						$additional_data['wapbk_lockout_days'] = str_replace( $start_date, '', $additional_data['wapbk_lockout_days'] );
					}

					if ( $booking_type == 'only_day' || 'multidates' === $booking_type ) {
						if ( $resource_id != 0 && isset( $additional_data['bkap_booked_resource_data'] ) && isset( $additional_data['bkap_booked_resource_data'][ $resource_id ] ) && isset( $additional_data['bkap_booked_resource_data']['bkap_locked_dates'] ) ) {
							if ( strpos( $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'], $start_date ) > 0 ) {
								$additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] = str_replace( $start_date, '', $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] );
							}
						}
					}

					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) { // multiple days
						// get the range of dates
						$booking_end                           = date( 'j-n-Y', strtotime( get_post_meta( $booking_post_id, '_bkap_end', true ) ) );
						$hidden_dates_array['hidden_checkout'] = $booking_end;
						$booking_range                         = bkap_common::bkap_get_betweendays( $start_date, $booking_end );
						// loop through and enable all the locked dates in the range
						foreach ( $booking_range as $date ) {
							 $date_to_check = date( 'j-n-Y', strtotime( $date ) );
							 // remove the date if it exists in the list of blocked dates in the Checkin Calendar
							if ( strpos( $additional_data['wapbk_hidden_booked_dates'], $date_to_check ) > 0 ) {
								$additional_data['wapbk_hidden_booked_dates'] = str_replace( $date_to_check, '', $additional_data['wapbk_hidden_booked_dates'] );
							}
							 // remove the date if it exists in the list of blocked dates in the Checkout Calendar
							if ( strpos( $additional_data['wapbk_hidden_booked_dates_checkout'], $date_to_check ) > 0 ) {
								$additional_data['wapbk_hidden_booked_dates_checkout'] = str_replace( $date_to_check, '', $additional_data['wapbk_hidden_booked_dates_checkout'] );
							}

							if ( $resource_id != 0 && isset( $additional_data['bkap_booked_resource_data'] ) ) {
								if ( strpos( $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'], $date_to_check ) > 0 ) {
									  $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] = str_replace( $date_to_check, '', $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] );
								}
							}
						}
					}
					// check if the date is a holiday.
					if ( strpos( $additional_data['holidays'], $start_date ) > 0 ) {
						$additional_data['holidays'] = str_replace( $start_date, '', $additional_data['holidays'] );
					}
					// pass the booking post ID.
					$additional_data['booking_post_id'] = $booking_post_id;

					// #3710 - Show correct price in Booking Form on Edit Booking post page.
					$attribute_booking_data = get_post_meta( $post_id, '_bkap_attribute_settings', true ); // Product Attributes - Booking Settings.
					$is_attr_lockout        = false;

					if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {
						$variation  = new WC_Product_Variation( $booking_post_obj->get_variation_id() );
						$attributes = $variation->get_attributes();
						foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
							// check if the setting is on.
							if ( isset( $attr_settings['booking_lockout_as_value'] ) && 'on' == $attr_settings['booking_lockout_as_value'] ) {
								if ( array_key_exists( $attr_name, $attributes ) && $attributes[ $attr_name ] != 0 ) {
									$additional_data['booking_post_qty'] = 1;
								}
							}
						}
					}

					// if it's a date_time booking, we need to pass the already set timeslot.
					if ( isset( $booking_settings['booking_enable_time'] ) && ( 'on' === $booking_settings['booking_enable_time'] || 'dates_time' === $booking_settings['booking_enable_time'] ) ) {

						$time_format = $global_settings->booking_time_format;

						// get the time.
						if ( $time_format === '12' ) {
							$start_time = date( 'h:i A', strtotime( get_post_meta( $booking_post_id, '_bkap_start', true ) ) );
							$end_time   = date( 'h:i A', strtotime( get_post_meta( $booking_post_id, '_bkap_end', true ) ) );
						} else {
							$start_time = date( 'H:i', strtotime( get_post_meta( $booking_post_id, '_bkap_start', true ) ) );
							$end_time   = date( 'H:i', strtotime( get_post_meta( $booking_post_id, '_bkap_end', true ) ) );
						}

						$time_slot_selected = $start_time;
						if ( isset( $end_time ) && ( '' !== $end_time && '12:00 AM' !== $end_time && '00:00' !== $end_time ) ) {
							$time_slot_selected .= " - $end_time";
						}

						$additional_data['time_selected'] = $time_slot_selected;

						if ( $resource_id != 0 && isset( $additional_data['bkap_booked_resource_data'] ) ) {
							if ( strpos( $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'], $start_date ) > 0 ) {
								$additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] = str_replace( $start_date, '', $additional_data['bkap_booked_resource_data'][ $resource_id ]['bkap_locked_dates'] );
							}
						}
					}

					if ( isset( $booking_settings['booking_enable_time'] ) && 'duration_time' === $booking_settings['booking_enable_time'] ) {
						$duration_selected      = $booking_post_obj->get_selected_duration();
						$duration_time_selected = $booking_post_obj->get_selected_duration_time();

						$d_setting = get_post_meta( $post_pro_id, '_bkap_duration_settings', true );
						$d_hours   = (int) $d_setting['duration'];

						$additional_data['duration_selected']      = (int) $duration_selected / $d_hours;
						$hidden_dates_array['duration_selected']   = (int) $duration_selected / $d_hours;
						$additional_data['duration_time_selected'] = $duration_time_selected;
					}
				}
			}

			if ( is_product() && isset( $_POST['time_slot'] ) && '' !== $_POST['time_slot'] ) {
				$additional_data['time_selected'] = $_POST['time_slot'];
			}

			if ( isset( $_GET['bkap_date'] ) ) {
				$hidden_dates_array['hidden_date'] = date( 'j-n-Y', strtotime( $_GET['bkap_date'] ) );
			}

			$additional_data['partial_deposit_addon'] = ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() ) ? true : false;
			$additional_data['rental_system_addon']   = ( function_exists( 'is_bkap_rental_active' ) && is_bkap_rental_active() ) ? true : false;

			$additional_data['bkap_no_of_days'] = apply_filters( 'bkap_selected_days_label', __( 'Number of Days Selected: ', 'woocommerce-booking' ), $product_id, $booking_settings );

			$global_settings  = apply_filters( 'bkap_init_parameter_localize_script_global_settings', $global_settings );
			$booking_settings = apply_filters( 'bkap_init_parameter_localize_script_booking_settings', $booking_settings );
			$labels           = apply_filters( 'bkap_init_parameter_localize_script_labels', $labels );
			$additional_data  = apply_filters( 'bkap_init_parameter_localize_script_additional_data', $additional_data );

			wp_localize_script(
				'bkap-init-datepicker',
				$init_datepicker_param,
				apply_filters(
					'bkap_init_parameter_localize_script',
					array(
						'global_settings' => wp_json_encode( $global_settings ),
						'bkap_settings'   => wp_json_encode( $booking_settings ),
						'labels'          => wp_json_encode( $labels ),
						'additional_data' => wp_json_encode( $additional_data ),
					)
				)
			);

			wp_enqueue_script( 'bkap-init-datepicker' );

			wp_register_script(
				'bkap-process-functions',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/booking-process-functions.js', BKAP_FILE ),
				'',
				BKAP_VERSION,
				false
			);

			$process_param_name = 'bkap_process_params';
			if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'view-order' ) || $used_for_modal_display ) {
				$process_param_name = "bkap_process_params_$product_id";
			}

			// Passing attribute of product and its values in $attribute_name_and_values array.
			$attribute_name_and_values = array();
			if ( $attribute_fields_str != '' ) {

				$attribute_fields_str_array = explode( ',', $attribute_fields_str );

				foreach ( $attribute_fields_str_array as $attribute_fields_str_array_value ) {

					if ( $attribute_fields_str_array_value != '' ) {
						list( $k, $v )                   = explode( ':', $attribute_fields_str_array_value );
						$k                               = str_replace( '"', '', $k );
						$attribute_name_and_values[ $k ] = $v;
					}
				}
			}

			$additional_data['bkap_multidates_already_added_msg'] = __( 'This date is already added. Please select some other date.', 'woocommerce-booking' );

			$add_to_cart_labels = array(
				'bkap_add_to_cart'        => __( get_option( 'bkap_add_to_cart' ), 'woocommerce-booking' ),
				'bkap_check_availability' => __( get_option( 'bkap_check_availability' ), 'woocommerce-booking' ),
			);

			$wordpress_theme = '';

			if ( function_exists( 'wp_get_theme' ) ) {
				$wordpress_theme = wp_get_theme();
			}

			wp_localize_script(
				'bkap-process-functions',
				$process_param_name,
				array(
					'product_id'          => $product_id,
					'post_id'             => $post_id,
					'ajax_url'            => AJAX_URL,
					'bkap_permalink'      => get_permalink( $post_id ),
					'global_settings'     => wp_json_encode( $global_settings ),
					'bkap_settings'       => wp_json_encode( $booking_settings ),
					'labels'              => wp_json_encode( $labels ),
					'additional_data'     => wp_json_encode( $additional_data ),
					'on_change_attr_list' => $on_change_attributes_str,
					'attr_value'          => $attribute_value,
					'attr_selected'       => $attribute_value_selected,
					'attr_fields_str'     => $attribute_name_and_values,
					'selected_attribute'  => isset( $_GET['bkap_attribute'] ) && '' !== $_GET['bkap_attribute'] ? sanitize_text_field( $_GET['bkap_attribute'] ) : '',
					'add_to_cart_labels'  => $add_to_cart_labels,
					'wordpress_theme'     => strtolower( $wordpress_theme ),
					'is_admin'            => var_export( current_user_can( 'manage_options' ), true ),
				)
			);

			wp_localize_script(
				'bkap-process-functions',
				'product_id',
				array(
					'product_id' => $product_id,
				)
			);

			wp_enqueue_script( 'bkap-process-functions' );

			wp_register_script( 'booking-process', bkap_load_scripts_class::bkap_asset_url( '/assets/js/booking-process.js', BKAP_FILE ), '', BKAP_VERSION, false );

			wp_enqueue_script( 'booking-process' );

			$hidden_dates_array['additional_data'] = $additional_data;

			return $hidden_dates_array;
		}


		/**
		 * This function sets the hidden date variable for Fixed block bookings
		 *
		 * @param string     $hidden_date - Hidden Date variable previously set
		 * @param string|int $product_id - Product ID
		 * @param array      $holiday_array - Holiday Dates Array
		 * @param array      $global_holidays - Global Holidays Array
		 * @param array      $lockout_dates_array - Lockout Dates Array
		 * @return string $hidden_date - Hidden Date to be set
		 *
		 * @since v4.5.0
		 */

		public static function set_fixed_block_hidden_date( $hidden_date, $product_id, $holiday_array, $global_holidays, $lockout_dates_array ) {

			$results = bkap_block_booking::bkap_get_fixed_blocks( $product_id );

			if ( count( $results ) > 0 ) {

				foreach ( $results as $key => $value ) {
					$fix_min_day = $value['start_day'];
					break;
				}

				$min_day = date( 'w', strtotime( $hidden_date ) );

				$date_updated = 'NO';
				if ( $fix_min_day != 'any_days' ) {

					for ( $i = 0;; $i++ ) {

						if ( in_array( $hidden_date, $holiday_array ) || in_array( $hidden_date, $global_holidays ) || in_array( $hidden_date, $lockout_dates_array ) ) {
							$hidden_date  = date( 'j-n-Y', strtotime( '+1day', strtotime( $hidden_date ) ) );
							$date_updated = 'YES';

							$min_day = ( $min_day < 6 ) ? $min_day + 1 : $min_day - $min_day;

						} else {

							if ( $min_day == $fix_min_day ) {
								$hidden_date = date( 'j-n-Y', strtotime( $hidden_date ) );
								break;
							} else {
								$hidden_date  = date( 'j-n-Y', strtotime( '+1day', strtotime( $hidden_date ) ) );
								$date_updated = 'YES';

								$min_day = ( $min_day < 6 ) ? $min_day + 1 : $min_day - $min_day;
							}

							if ( $date_updated == 'NO' ) {
								break;
							}
						}
					}
				}
			}

			return $hidden_date;
		}

		/**
		 * This function add the Booking fields on the frontend product page
		 * for bookable products.
		 *
		 * @hook woocommerce_before_add_to_cart_button
		 * @since 1.0
		 */

		public static function bkap_booking_after_add_to_cart() {
			global $post;

			$duplicate_of = bkap_common::bkap_get_product_id( $post->ID );
			$bookable     = bkap_common::bkap_get_bookable_status( $duplicate_of );
			if ( ! $bookable ) {
				return;
			}

			/* Postcode Addon view */
			do_action( 'bkap_create_postcode_view' );
			$display_booking_fields = apply_filters( 'bkap_postcode_display_booking_field', '' );
			$booking_settings       = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );
			// $booking_settings_new     = bkap_get_post_meta( $duplicate_of );
			$global_settings = bkap_global_setting();
			$product         = wc_get_product( $post->ID );
			$product_type    = $product->get_type();

			// Postcode addon: Show delivery postcode to logged in users / modal to logged out users.
			if ( isset( $booking_settings['booking_enable_date'] ) && $booking_settings['booking_enable_date'] == 'on' && ( 'YES' == $display_booking_fields || '' == $display_booking_fields ) ) {
				do_action( 'bkap_create_postcode_field_before_field' );
				do_action( 'bkap_subscription_hooks' );
			} else {
				do_action( 'bkap_create_postcode_modal' );
				return;
			}

			$hidden_dates = self::bkap_localize_process_script( $post->ID );

			if ( is_product() && isset( $_POST['wapbk_hidden_date'] ) && '' !== $_POST['wapbk_hidden_date'] ) {
				$hidden_dates['hidden_date'] = $_POST['wapbk_hidden_date'];
				if ( isset( $_POST['wapbk_hidden_date_checkout'] ) && '' !== $_POST['wapbk_hidden_date_checkout'] ) {
					$hidden_dates['hidden_checkout'] = $_POST['wapbk_hidden_date_checkout'];
				}
				$hidden_dates['widget_search'] = 1;
			}

			/**
			 * Adding Template for Booking Fields on the front end of the Product Page.
			 */

			wc_get_template(
				'bookings/bkap-bookings-box.php',
				array(
					'product_id'       => $duplicate_of,
					'product_obj'      => $product,
					'booking_settings' => $booking_settings,
					'global_settings'  => $global_settings,
					'hidden_dates'     => $hidden_dates,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);

			do_action( 'bkap_booking_after_add_to_cart_end', $booking_settings );
		}

		/**
		 * This function displays the prices calculated for
		 * bookings (Single Day and Date & Time Bookings) on
		 * the front end product page.
		 *
		 * @since 2.0
		 */

		public static function bkap_date_datetime_price() {

			$product_id   = $_POST['id'];
			$product      = wc_get_product( $product_id );
			$product_type = $product->get_type();
			$variation_id = isset( $_POST['variation_id'] ) ? sanitize_text_field( $_POST['variation_id'] ) : '';

			if ( $product_type == 'variable' ) {
				if ( isset( $variation_id ) && ( $variation_id == '' || $variation_id == 0 ) ) {
					$error_message           = __( 'Please choose product options&hellip;', 'woocommerce' );
					$wp_send_json            = array( 'error' => true );
					$wp_send_json['message'] = addslashes( $error_message );
					wp_send_json( $wp_send_json );
				}
			}

			$booking_date_format = sanitize_text_field( $_POST['bkap_date'] );
			$booking_date        = date( 'Y-m-d', strtotime( $booking_date_format ) );
			$resource_id         = ( isset( $_POST['resource_id'] ) ) ? $_POST['resource_id'] : '';
			$gf_options          = ( isset( $_POST['gf_options'] ) && is_numeric( $_POST['gf_options'] ) ) ? $_POST['gf_options'] : 0;
			$booking_settings    = bkap_setting( $product_id );
			$person_data         = isset( $_POST['person_ids'] ) ? $_POST['person_ids'] : array();
			$total_person        = isset( $_POST['total_person'] ) ? sanitize_text_field( $_POST['total_person'] ) : 0;

			/* Person Calculations */
			$message = bkap_validate_person_selection( $person_data, $total_person, $booking_settings, $product_id );

			if ( '' !== $message ) {
				$message = bkap_woocommerce_error_div( $message );
				$data    = array(
					'message' => $message,
					'error'   => true,
				);
				wp_send_json( $data );
			}

			do_action(
				'bkap_display_updated_addon_price',
				$product_id,
				$booking_settings,
				$product,
				$booking_date,
				$variation_id,
				$gf_options,
				$resource_id,
				$person_data
			);
		}

		/**
		 * This function adds a hook where addons can execute js code
		 * that maybe needed on the front end product page during booking
		 * price calculation.
		 *
		 * @since 2.0
		 */

		public static function bkap_js() {
			$booking_date = $_POST['booking_date'];
			$post_id      = $_POST['post_id'];
			$addon_data   = '';

			if ( isset( $_POST['addon_data'] ) ) {
				$addon_data = $_POST['addon_data'];
			}

			do_action( 'bkap_js', $booking_date, $post_id, $addon_data );
			die();
		}

		/**
		 * This function returns the availability for a given date for all types of bookings.
		 * Called via AJAX
		 *
		 * @param array $post Array containing POST values sent as arguments.
		 * @return array containing the message and the availability.
		 *
		 * @since 2.1
		 * @since Updated 5.15.0
		 */
		public static function bkap_date_lockout( $post = array() ) {

			$product_id               = isset( $post['post_id'] ) ? $post['post_id'] : $_POST['post_id'];
			$product                  = wc_get_product( $product_id );
			$bkap_settings            = bkap_setting( $product_id );
			$date_format_set          = bkap_common::bkap_get_date_format();
			$checkin_date             = sanitize_text_field( isset( $post['date'] ) ? $post['date'] : $_POST['date'] );
			$date                     = strtotime( $checkin_date );
			$date_check_in            = date( 'Y-m-d', $date );
			$check_in_date            = date( $date_format_set, $date );
			$variation_id             = isset( $post['variation_id'] ) ? $post['variation_id'] : ( isset( $_POST['variation_id'] ) && '' != $_POST['variation_id'] ? sanitize_text_field( $_POST['variation_id'] ) : 0 );
			$bookings_placed          = isset( $_POST['bookings_placed'] ) && '' != $_POST['bookings_placed'] ? sanitize_text_field( $_POST['bookings_placed'] ) : '';
			$attr_bookings_placed     = isset( $_POST['attr_bookings_placed'] ) && '' != $_POST['attr_bookings_placed'] ? sanitize_text_field( $_POST['attr_bookings_placed'] ) : '';
			$resource_id              = isset( $post['resource_id'] ) ? $post['resource_id'] : ( isset( $_POST['resource_id'] ) ? $_POST['resource_id'] : '' );
			$resource_bookings_placed = isset( $_POST['resource_bookings_placed'] ) ? bkap_common::frontend_json_decode( $_POST['resource_bookings_placed'] ) : '';
			$date_fld_val             = isset( $_POST['date_fld_val'] ) ? $_POST['date_fld_val'] : $check_in_date;
			$selected_person_data     = isset( $_POST['person_ids'] ) ? sanitize_text_field( $_POST['person_ids'] ) : 0;
			$total_person             = isset( $_POST['total_person'] ) ? sanitize_text_field( $_POST['total_person'] ) : 0;
			$resource_ids             = '' !== $resource_id ? explode( ',', $resource_id ) : array();
			$availability_msg         = get_option( 'book_available-stock-date' );
			$message                  = __( 'Please select a date.', 'woocommerce-booking' );
			$available_tickets        = '';
			$cal_price                = ( isset( $_POST['cal_price'] ) && true === $_POST['cal_price'] ) || ( isset( $post['cal_price'] ) && true === $post['cal_price'] );

			if ( '' !== $checkin_date ) {

				if ( count( $resource_ids ) > 0 && ! $cal_price ) {
					$resource_id = $resource_ids;
				}

				$available_tickets = self::bkap_get_date_availability(
					$product_id,
					$variation_id,
					$date_check_in,
					$check_in_date,
					$bookings_placed,
					$attr_bookings_placed,
					'',
					true,
					$resource_id,
					$resource_bookings_placed,
					$cal_price
				);

				if (
					'' !== $resource_id &&
					is_array( $resource_ids ) &&
					count( $resource_ids ) > 0 &&
					is_array( $available_tickets ) &&
					count( $available_tickets ) > 0
				) {
					// Multiple availabilites for Resources.
					$message                   = '';
					$all_resource_availability = array();

					foreach ( $available_tickets as $_resource_id => $availability ) {
						$_message                    = $availability;
						$all_resource_availability[] = $availability;
						$_message                    = ( 'U' === $availability ) ? 'Unlimited' : $_message;
						$_message                    = ( 0 === $availability ) ? sprintf( 'Available bookings for date %s are either fully booked or already added in the cart', $date_fld_val ) : $_message;
						$_message                    = ( 0 !== $availability ) ? str_replace( array( 'AVAILABLE_SPOTS', 'DATE' ), array( $_message, $date_fld_val ), $availability_msg ) : $_message;
						$resource_name               = Class_Bkap_Product_Resource::get_resource_name( $_resource_id );
						$message                    .= '<label>' . $_message . ' for ' . $resource_name . '</label>';
					}

					// Since we have availabilities for a number of resources, we return the intersect of them all - the least availability and assign to the $available_tickets variable .
					$all_resource_availability = array_unique( $all_resource_availability );
					sort( $all_resource_availability );

					$available_tickets = $all_resource_availability[0];

					if ( 'U' === $all_resource_availability[0] ) {
						$available_tickets = 'Unlimited';
						if ( isset( $all_resource_availability[1] ) ) {
							$available_tickets = $all_resource_availability[1];
						}
					}
				} elseif ( 'FALSE' !== $available_tickets && 'TIME-FALSE' !== $available_tickets ) {
					if ( is_numeric( $available_tickets ) || 'Unlimited' === $available_tickets ) {
						$message = str_replace( array( 'AVAILABLE_SPOTS', 'DATE' ), array( $available_tickets, $date_fld_val ), $availability_msg );
					}
				} elseif ( 'FALSE' === $available_tickets ) {
					$message = __( 'Bookings are full.', 'woocommerce-booking' );
				} elseif ( 'TIME-FALSE' === $available_tickets ) {
					$message = sprintf(
						__( 'Available bookings for date %1$s are already added in the cart. You can add bookings for a different date or place the order that is currently in the <a href="%2$s">%3$s</a>.', 'woocommerce-booking' ),
						$check_in_date,
						esc_url( wc_get_page_permalink( 'cart' ) ),
						esc_html__( 'cart', 'woocommerce' )
					);

					$message = apply_filters( 'bkap_all_date_availability_present_in_cart', $message );
				}

				$message = apply_filters( 'bkap_date_lockout_message', $message );

				// Available Booking Based on Total Person.
				if ( isset( $bkap_settings['bkap_each_person_booking'] ) && 'on' === $bkap_settings['bkap_each_person_booking'] ) {
					if ( is_numeric( $available_tickets ) && $total_person > $available_tickets ) {
						$validation_msg = bkap_max_persons_available_msg( '', $product_id );
						$message        = sprintf( $validation_msg, $available_tickets, $date_fld_val );
						$message        = bkap_woocommerce_error_div( $message );
						$data           = array(
							'message' => $message,
							'error'   => true,
						);

						wp_send_json( $data );
					}
				}
			}

			$data = apply_filters(
				'bkap_date_lockout_data',
				array(
					'message' => $message,
					'max_qty' => $available_tickets,
				),
				$product,
				$product_id,
				$variation_id,
				$resource_id,
				$date_check_in
			);

			if ( isset( $post ) && is_array( $post ) && count( $post ) > 0 ) {
				return $data;
			}

			wp_send_json( $data );
		}


		/**
		 * This function calculates and returns the availability for a given
		 * date for all types of bookings
		 *
		 * @param integer $product_id - Product ID
		 * @param integer $variation_id - Variation ID, 0 for simple products.
		 * @param string  $hidden_date - Booking Start Date in j-n-Y format
		 * @param string  $check_in_date - Booking Start Date as per set in the booking field
		 * @param array   $bookings_placed - Number of bookings already present for the product.
		 * @param array   $attr_bookings_placed - Number of bookings already present for the attribute.
		 * @param string  $hidden_checkout_date - Booking End date in j-n-Y format. Blanks for single day and date & time bookings
		 * @param boolean $cart_check - True when availability is being checked on the front end. Checks for the product present in the Cart as well.
		 *                              False when availability is being checked for importing & mapping Google Events.
		 * @param integer $resource_id - Resource ID, 0 for when no resources are setup.
		 * @param array   $resource_bookings_placed - Bookings already present for the resource.
		 * @return string|integer $available_tickets - Available Bookings. Integer for a finite value and string for unlimited bookings.
		 *
		 * @since 2.1
		 */

		public static function bkap_get_date_availability(
		$product_id,
		$variation_id,
		$hidden_date,
		$check_in_date,
		$bookings_placed,
		$attr_bookings_placed,
		$hidden_checkout_date = '',
		$cart_check = true,
		$resource_id = '',
		$resource_bookings_placed = '',
		$cal_price = false
		) {

			global $wpdb;

			$booking_settings         = bkap_setting( $product_id );
			$available_tickets        = 0;
			$unlimited                = true;
			$_product                 = wc_get_product( $product_id );
			$product_type             = $_product->get_type();
			$selected_date            = date( 'j-n-Y', strtotime( $hidden_date ) );
			$check_availability       = 'YES'; // assuming that variation lockout is not set.
			$unlimited_plugin_lockout = false;

			// Inserting date records to database for Only Day based on booking settings.
			self::bkap_insert_date_record( $check_in_date, $product_id, $booking_settings, $_product );

			// Check if it is a variable product.
			if ( $product_type === 'variable' ) {

				if ( '' !== $variation_id && $variation_id === '0' ) {
					return __( 'Please select an option to display booking information.', 'woocommerce-booking' );
				}

				$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );

				if ( isset( $variation_lockout ) && $variation_lockout > 0 ) {

					$check_availability    = 'NO'; // set it to NO, so availability is not re calculated at the product level
					$variation_lockout_set = 'YES';
					$available_tickets     = $variation_lockout;
					$hidden_date           = $selected_date;

					if ( $variation_lockout > 0 ) {
						$unlimited = false;
					}

					// First we will check if lockout is set at the variation level
					if ( $booking_settings != '' && ( isset( $booking_settings['booking_enable_time'] ) && 'on' == $booking_settings['booking_enable_time'] ) ) {

						$number_of_slots = bkap_common::bkap_get_number_of_slots( $product_id, $hidden_date );

						if ( isset( $number_of_slots ) && $number_of_slots > 0 ) {
							$available_tickets *= $number_of_slots;
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

						$orders_placed = 0;
						if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {

							if ( array_key_exists( $hidden_date, $date_array ) ) {
								foreach ( $date_array[ $hidden_date ] as $date_key => $date_value ) {
									$orders_placed += $date_value;
								}
								$available_tickets = $available_tickets - $orders_placed;
							}
						}
					} else {

						if ( isset( $bookings_placed ) && $bookings_placed != '' ) {
							$list_dates = explode( ',', $bookings_placed );

							foreach ( $list_dates as $list_key => $list_value ) {

								$explode_date = explode( '=>', $list_value );

								if ( isset( $explode_date[1] ) && $explode_date[1] != '' ) {

									if ( strpos( $explode_date[0], '\\' ) !== false ) {
										$date = substr( $explode_date[0], 2, -2 );
									} else { // In the import process the string doesn't contain \ character
										$date = substr( $explode_date[0], 1, -1 );
									}

									$date_array[ $date ] = $explode_date[1];
								}
							}
						}

						if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
							if ( array_key_exists( $hidden_date, $date_array ) ) {
								$orders_placed     = $date_array[ $hidden_date ];
								$available_tickets = $available_tickets - $orders_placed;
							}
						}
					}
				} else { // if attribute lockout is set.

					$attributes = get_post_meta( $product_id, '_product_attributes', true );
					// Product Attributes - Booking Settings
					$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );
					$message                = '';

					if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

						foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {
							$attr_post_name = 'attribute_' . $attr_name;

							if ( isset( $attr_settings['booking_lockout_as_value'] )
							&& 'on' == $attr_settings['booking_lockout_as_value']
							&& isset( $attr_settings['booking_lockout'] )
							&& $attr_settings['booking_lockout'] > 0 ) {

								$attribute_lockout_set = 'YES';
								$bookings_placed       = $attr_bookings_placed;
								$check_availability    = 'NO';
								$available_tickets     = $attr_settings['booking_lockout'];

								$hidden_date = $selected_date;

								$number_of_slots = bkap_common::bkap_get_number_of_slots( $product_id, $hidden_date );

								if ( isset( $number_of_slots ) && $number_of_slots > 0 ) {
									$available_tickets *= $number_of_slots;
								}

								if ( isset( $bookings_placed ) && $bookings_placed != '' ) {

									$attribute_list = explode( ';', $bookings_placed );

									foreach ( $attribute_list as $attr_key => $attr_value ) {

										$attr_array = explode( ',', $attr_value );

										if ( $attr_name == $attr_array[0] ) {

											for ( $i = 1; $i < count( $attr_array ); $i++ ) {
												$explode_dates = explode( '=>', $attr_array[ $i ] );

												if ( isset( $explode_dates[0] ) && $explode_dates[0] != '' ) {

													$date = substr( $explode_dates[0], 2, -2 );

													if ( isset( $explode_dates[2] ) ) {
														$date_array[ $date ][ $explode_dates[1] ] = $explode_dates[2];
													} else {
														$date_array[ $date ] = $explode_dates[1];
													}
												}
											}
										}
									}
								}

								// check the availability for this attribute
								$orders_placed = 0;
								if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {

									if ( array_key_exists( $hidden_date, $date_array ) ) {

										if ( is_array( $date_array[ $hidden_date ] ) && count( $date_array[ $hidden_date ] ) > 0 ) {
											foreach ( $date_array[ $hidden_date ] as $date_key => $date_value ) {
												$orders_placed += $date_value;
											}
										} else {
											$orders_placed = $date_array[ $hidden_date ];
										}

										$available_tickets -= $orders_placed;
									}
								}

								$msg_format = get_option( 'book_available-stock-date-attr' );
								$attr_label = wc_attribute_label( $attr_name, $_product );

								$availability_msg = str_replace( array( 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE' ), array( $available_tickets, $attr_label, $check_in_date ), $msg_format );
								$message         .= $availability_msg . '<br>';
							}
						}
					}
					// This has been done specifically for Variable products with attribute level lockout
					$available_tickets = $message;
				}
			}

			if ( 'YES' === $check_availability ) {

				$unlimited_unavailability_text = __( 'Unlimited', 'woocommerce-booking' );
				$number_of_slots               = ( '' !== $booking_settings && isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) ? bkap_common::bkap_get_number_of_slots( $product_id, $hidden_date ) : '';

				// Calculating availability based on resources.
				if ( '' !== $resource_id ) {

					if ( ! is_array( $resource_id ) ) {

						$bkap_resource_availability = (int) bkap_resource_max_booking( $resource_id, $hidden_date, $product_id, $booking_settings );
						$bkap_resource_availability = ( '' !== $number_of_slots && $number_of_slots > 0 ) ? $bkap_resource_availability *= $number_of_slots : 0;

						if ( 0 === $bkap_resource_availability ) {
							return $unlimited_unavailability_text;
						}

						// Calculation for list view availability.
						if ( $cal_price ) { //phpcs:ignore

							$resource_booking_available        = $bkap_resource_availability;
							$resource_bookings[ $resource_id ] = bkap_calculate_bookings_for_resource( $resource_id, $product_id );

							if ( isset( $booking_settings['booking_enable_time'] ) && ( 'on' == $booking_settings['booking_enable_time'] || $booking_settings['booking_enable_time'] == 'duration_time' ) ) {
								$resource_bookings[ $resource_id ] = apply_filters( 'bkap_locked_dates_for_dateandtime', $resource_id, $product_id, $booking_settings, $resource_bookings[ $resource_id ] );
							}

							$placed_bookings        = ( isset( $resource_bookings[ $resource_id ]['bkap_booking_placed'] ) && '' != $resource_bookings[ $resource_id ]['bkap_booking_placed'] ) ? $resource_bookings[ $resource_id ]['bkap_booking_placed'] : '';
							$qty_in_placed_bookings = Class_Bkap_Product_Resource::return_quantity_in_placed_bookings( $placed_bookings, $selected_date );
							$qty_in_cart            = Class_Bkap_Product_Resource::return_quantity_in_cart( $resource_id, $hidden_date );

							$resource_booking_available = $bkap_resource_availability - $qty_in_placed_bookings - $qty_in_cart;

							return ( 0 === $resource_booking_available && 0 !== $bkap_resource_availability ) ? 'TIME-FALSE' : $resource_booking_available;
						}

						return;
					}

					// resource_id is an array.
					$all_resource_availability = array();

					foreach ( $resource_id as $id ) {

						$bkap_resource_availability = (int) bkap_resource_max_booking( $id, $hidden_date, $product_id, $booking_settings );
						$bkap_resource_availability = ( '' !== $number_of_slots && $number_of_slots > 0 ) ? $bkap_resource_availability *= $number_of_slots : $bkap_resource_availability;

						if ( 0 === $bkap_resource_availability ) {
							$all_resource_availability[ $id ] = 'U'; // Unlimited.
							continue;
						}

						$qty_in_placed_bookings           = is_array( $resource_bookings_placed ) && isset( $resource_bookings_placed[ $id ] ) ? Class_Bkap_Product_Resource::return_quantity_in_placed_bookings( $resource_bookings_placed[ $id ], $selected_date ) : 0;
						$qty_in_cart                      = Class_Bkap_Product_Resource::return_quantity_in_cart( $id, $hidden_date );
						$all_resource_availability[ $id ] = $bkap_resource_availability - $qty_in_placed_bookings - $qty_in_cart;
					}

					return $all_resource_availability;
				}

				// if multiple day booking is enabled then calculate the availability based on the total lockout in the settings
				if ( isset( $booking_settings['booking_enable_multiple_day'] ) && $booking_settings['booking_enable_multiple_day'] == 'on' ) {

					// Set the default availability to the total lockout value
					$available_tickets = bkap_get_maximum_booking( $product_id, $booking_settings );
					$available_tickets = bkap_get_specific_date_maximum_booking( $available_tickets, $selected_date, $product_id, $booking_settings );

					if ( $cart_check ) { // if cart_check = true it means this has been called from front end product page
						// Now fetch all the records for the product that have a date range which includes the start date
						$date_query   = 'SELECT available_booking FROM `' . $wpdb->prefix . 'booking_history`
        							         WHERE post_id = %d
        							         AND start_date <= %s
        							         AND end_date > %s
        							         OR ( post_id = %d AND start_date = %s AND end_date = %s )
        							         ';
						$results_date = $wpdb->get_results( $wpdb->prepare( $date_query, $product_id, $hidden_date, $hidden_date, $product_id, $hidden_date, $hidden_date ) );

						// If records are found then the availability needs to be subtracted from the total lockout value
						if ( $available_tickets > 0 ) {
							$unlimited         = false;
							$available_tickets = $available_tickets - count( $results_date );
						}
					} elseif ( false === $cart_check && $hidden_checkout_date != '' ) { // this means it's been called for importing hence we need to check for the entire range

						// Now fetch all the records for the product that have a date range which includes the start date
						$date_query   = 'SELECT available_booking FROM `' . $wpdb->prefix . 'booking_history`
        							         WHERE post_id = %d
        							         AND start_date <= %s
        							         AND end_date >= %s';
						$results_date = $wpdb->get_results( $wpdb->prepare( $date_query, $product_id, $hidden_date, $hidden_checkout_date ) );

						// If records are found then the availability needs to be subtracted from the total lockout value
						if ( $available_tickets > 0 ) {
							$unlimited         = false;
							$available_tickets = $available_tickets - count( $results_date );
						}
					}
				} else {

					$cut_off_timestamp = current_time( 'timestamp' );
					$advance_seconds   = bkap_advance_booking_hrs( $booking_settings, $product_id );
					$advance_seconds   = $advance_seconds * 3600;
					$cut_off_timestamp = $cut_off_timestamp + $advance_seconds;

					$fromtimequery = '';
					$select_clause = 'available_booking,total_booking';
					$bookings_done = 0;

					if ( isset( $booking_settings['booking_enable_time'] )
					&& 'on' == $booking_settings['booking_enable_time']
					) {
						$bookings_done = array();
						$fromtimequery = "AND from_time != ''";
						$select_clause = 'available_booking,total_booking,from_time,to_time';
					}

					$booking_available = apply_filters( 'bkap_check_booking_availability_done', false, $product_id );
					if ( ! $booking_available ) {
						$bookings_data = get_bookings_for_range( $product_id, $hidden_date, strtotime( $hidden_date ) );

						if ( count( $bookings_data ) > 0 ) {
							if ( isset( $bookings_data[ date( 'Ymd', strtotime( $hidden_date ) ) ] ) ) {
								$bookings_done = $bookings_data[ date( 'Ymd', strtotime( $hidden_date ) ) ];

								if ( $fromtimequery && ! empty( $bookings_done ) ) {
									  $bookings_done = array_sum( $bookings_done );
								}
							}
						}
					}

					$date_query   = 'SELECT ' . $select_clause . ' FROM `' . $wpdb->prefix . "booking_history`
            						WHERE post_id = %d
            						AND weekday = ''
            						AND start_date = %s
		    	    				" . $fromtimequery . "
            						AND status = ''";
					$results_date = $wpdb->get_results( $wpdb->prepare( $date_query, $product_id, $hidden_date ) );

					if ( count( $results_date ) == 0 ) {
						// Fetch the record for that date from the Booking history table
						$date_query   = 'SELECT ' . $select_clause . ' FROM `' . $wpdb->prefix . 'booking_history`
	            						 WHERE post_id = %d
	            						 AND start_date = %s
			    	    				 ' . $fromtimequery . "
	            						 AND status = ''";
						$results_date = $wpdb->get_results( $wpdb->prepare( $date_query, $product_id, $hidden_date ) );
					}

					if ( isset( $results_date ) && count( $results_date ) > 0 ) {

						foreach ( $results_date as $key => $value ) {

							if ( isset( $value->from_time ) && $value->from_time != '' ) { // Do not calc availablity of passed timeslot based on advance booking period

								if ( strtotime( $hidden_date . ' ' . $value->from_time ) < $cut_off_timestamp ) {
									continue;
								}
							}

							if ( $value->available_booking > 0 && $value->total_booking != 0 ) {
								$unlimited          = false;
								$available_tickets += (int) $value->total_booking;
							}

							if ( $value->available_booking > 0 || $value->total_booking != 0 ) {
								$unlimited = false;
							}

							if ( $value->available_booking == 0 && $value->total_booking == 0 ) {
								$unlimited_plugin_lockout = true;
							}
						}

						if ( ! $unlimited ) {
							$available_tickets -= (int) $bookings_done;
						}
					} else { // if no record found and multiple day bookings r not enabled then get the base record for that weekday

						$weekday         = date( 'w', strtotime( $hidden_date ) );
						$booking_weekday = 'booking_weekday_' . $weekday;
						$base_query      = 'SELECT ' . $select_clause . ' FROM `' . $wpdb->prefix . "booking_history`
            								WHERE post_id = %d
            								AND weekday = %s
            								AND start_date = '0000-00-00'
        							        " . $fromtimequery . "
            								AND status = ''";
						$results_base    = $wpdb->get_results( $wpdb->prepare( $base_query, $product_id, $booking_weekday ) );

						if ( isset( $results_base ) && count( $results_base ) > 0 ) {

							foreach ( $results_base as $key => $value ) {
								if ( isset( $value->from_time ) && $value->from_time != '' ) { // Do not calc availablity of passed timeslot based on advance booking period
									if ( strtotime( $hidden_date . ' ' . $value->from_time ) < $cut_off_timestamp ) {
										continue;
									}
								}
								if ( $value->available_booking > 0 ) {
									$unlimited = false;
									// $available_tickets = (int) $available_tickets + (int) $value->available_booking;
									$available_tickets = (int) $value->total_booking - (int) $bookings_done;
								}
							}
						} else {
							$unlimited = false; // this will ensure that availability is not displayed as 'Unlimited' when no record is found. This might happen when importing bookings
						}
					}
				}
			}

			$booking_time = '';

			/**
			 * Check if the same product is already present in the cart.
			 */

			if ( $cart_check && isset( WC()->cart ) ) {

				foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

					$product_id_cart = $values['product_id'];

					if ( $product_id_cart == $product_id && isset( $values['bkap_booking'] ) ) {

						$check_lockout               = 'YES';
						$is_max_booking_set_for_date = false;

						if ( isset( $variation_lockout_set ) && 'YES' == $variation_lockout_set ) {

							if ( $variation_id != $values['variation_id'] ) {
								$check_lockout = 'NO';
							}

							// Check if there is a record of maximum booking that has been set for the selected date. If there is, then check for lockout.
							$max_bookings = bkap_get_specific_date_maximum_booking( $available_tickets, $hidden_date, $product_id, $booking_settings );

							if ( $max_bookings > 0 ) {
								$check_lockout               = 'YES';
								$is_max_booking_set_for_date = true;
							}
						} elseif ( isset( $attribute_lockout_set ) && 'YES' == $attribute_lockout_set ) {
							$check_lockout = 'NO';
						}
						if ( 'YES' == $check_lockout ) {
							if ( isset( $values['bkap_booking'] ) ) {
								$booking = $values['bkap_booking'];

								if ( isset( $booking[0]['time_slot'] ) && $booking[0]['time_slot'] != '' ) {
									$booking_time = $booking[0]['time_slot'];
								}
							}

							/* Persons Calculations */
							$total_person = 1;
							if ( isset( $booking[0]['persons'] ) ) {
								if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
									$total_person = array_sum( $booking[0]['persons'] );
								}
							}

							$quantity = (int) $values['quantity'] * $total_person;

							// Check if variation data is set in the cart dataset and maximum booking set for the selected date.
							if ( isset( $values['variation'] ) && $is_max_booking_set_for_date ) {

								// Get variation value which will be used for quantity.
								foreach ( $values['variation'] as $variation_key => $variation_value ) {

									// Check if variation value is an integer and then set as quantity so that variation value can represent number for quantity.
									$variation_value = (int) $variation_value;

									if ( $variation_value > 0 ) {
										$quantity = $variation_value;
									}
								}
							}

							if ( strtotime( $booking[0]['hidden_date'] ) == strtotime( $hidden_date ) ) {

								if ( $available_tickets > 0 ) {
									$unlimited          = false;
									$available_tickets -= $quantity;
								}
							}
						}
					}
				}
			}

			$available_tickets = ( $available_tickets < 0 ) ? 0 : $available_tickets;

			if ( ( $available_tickets == 0 && $unlimited ) || ( $available_tickets == 0 && $unlimited_plugin_lockout ) ) {
				$available_tickets = __( 'Unlimited', 'woocommerce-booking' );
			}

			if ( ! $unlimited_plugin_lockout ) {
				if ( $available_tickets == 0 && ! $unlimited && $booking_time != '' ) {
					$available_tickets = 'TIME-FALSE';
				} elseif ( $available_tickets == 0 && ! $unlimited ) {
					$available_tickets = 'FALSE';
				}
			}

			return $available_tickets;
		}

		/**
		 * This functions will add the date records in db for only day booking type product
		 *
		 * @since 4.14.4
		 * @param string $date Date
		 * @param int    $product_id Product Id
		 * @param array  $booking_Setting Booking Setting of the product
		 * @param object $product Product Object
		 */

		public static function bkap_insert_date_record( $date, $product_id, $booking_settings, $product ) {

			$product_type = $product->get_type();

			if ( '' !== $date ) {
				$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );
				if ( in_array( $booking_type, array( 'only_day', 'multidates' ) ) ) {
					self::bkap_insert_date( $product_id, $product->get_type(), $date );
				}
			}

			if ( $product_type === 'bundle' ) {
				$cart_configs = bkap_common::bkap_bundle_add_to_cart_config( $product );

				foreach ( $cart_configs as $cart_key => $cart_value ) {
					$pro_id   = $cart_value['product_id'];
					$bookable = bkap_common::bkap_get_bookable_status( $pro_id );
					if ( $bookable ) {
						$booking_type = get_post_meta( $pro_id, '_bkap_booking_type', true );
						$_product     = wc_get_product( $pro_id );
						if ( $booking_type == 'only_day' ) {
							self::bkap_insert_date( $pro_id, $_product->get_type(), $date );
						}
					}
				}
			}
		}

		/**
		 * This function returns the availability for a given timeslot for all types of bookings.
		 * Called via AJAX
		 *
		 * @param array $post Array containing POST values sent as arguments.
		 * @return array containing the message and the availability.
		 *
		 * @since 2.1
		 * @since Updated 5.15.0
		 */
		public static function bkap_get_time_lockout( $post = array() ) {

			$available_tickets        = 0;
			$message                  = '';
			$timeslots                = isset( $post['timeslot_value'] ) ? $post['timeslot_value'] : ( isset( $_POST['timeslot_value'] ) ? $_POST['timeslot_value'] : '' );
			$product_id               = isset( $post['post_id'] ) ? $post['post_id'] : $_POST['post_id'];
			$variation_id             = isset( $post['variation_id'] ) ? $post['variation_id'] : ( isset( $_POST['variation_id'] ) ? $_POST['variation_id'] : '' );
			$bookings_placed          = isset( $_POST['bookings_placed'] ) ? $_POST['bookings_placed'] : '';
			$resource_id              = isset( $post['resource_id'] ) ? $post['resource_id'] : ( isset( $_POST['resource_id'] ) ? $_POST['resource_id'] : '' );
			$resource_bookings_placed = isset( $_POST['resource_bookings_placed'] ) ? bkap_common::frontend_json_decode( $_POST['resource_bookings_placed'] ) : array();
			$booking_date_in          = isset( $post['checkin_date'] ) ? $post['checkin_date'] : ( isset( $_POST['checkin_date'] ) ? $_POST['checkin_date'] : '' ); // Checkin/Booking Date
			$selected_person_data     = isset( $_POST['person_ids'] ) ? sanitize_text_field( $_POST['person_ids'] ) : 0;
			$total_person             = isset( $_POST['total_person'] ) ? absint( $_POST['total_person'] ) : 0;
			$booking_id               = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
			$cal_price                = ( isset( $_POST['cal_price'] ) && true === $_POST['cal_price'] ) || ( isset( $post['cal_price'] ) && true === $post['cal_price'] );
			$msg_format               = get_option( 'book_available-stock-time' );

			if ( '' !== $timeslots ) {

				$booking_settings    = bkap_setting( $product_id ); // Booking settings
				$global_settings     = bkap_global_setting();
				$date_format_set     = bkap_common::bkap_get_date_format();
				$date                = strtotime( $booking_date_in );
				$booking_date        = date( 'Y-m-d', $date ); // Checkin/Booking Date
				$booking_date_disply = date( $date_format_set, $date );
				$date_fld_val        = isset( $_POST['date_fld_val'] ) ? $_POST['date_fld_val'] : $booking_date_disply;
				$unlimited           = 'YES';

				// assuming that variation lockout is not set.
				$check_availability    = 'YES';
				$is_existing_ts        = 'NO';
				$attr_lockout_set      = 'NO';
				$variation_lockout_set = 'NO';
				$booking_in_cart       = 'NO';

				$timezone_check = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.

				// if it's a variable product and bookings placed field passed is non blanks.
				if ( isset( $variation_id ) && $variation_id > 0 ) {

					$variation_lockout = get_post_meta( $variation_id, '_booking_lockout_field', true );

					if ( isset( $variation_lockout ) && $variation_lockout > 0 ) {
						$check_availability    = 'NO'; // set it to NO, so availability is not re calculated at the product level.
						$variation_lockout_set = 'YES';

						$available_tickets = $variation_lockout;
						$date_check_in     = date( 'j-n-Y', $date );

						// create an array of dates for which orders have already been placed and the qty for each date.
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
					} else {

						$attributes = get_post_meta( $product_id, '_product_attributes', true );
						// Product Attributes - Booking Settings
						$attribute_booking_data = get_post_meta( $product_id, '_bkap_attribute_settings', true );

						if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

							foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {

								if ( isset( $attr_settings['booking_lockout_as_value'] )
								&& 'on' == $attr_settings['booking_lockout_as_value']
								&& isset( $attr_settings['booking_lockout'] )
								&& $attr_settings['booking_lockout'] > 0 ) {
									$attr_lockout_set   = 'YES';
									$bookings_placed    = $_POST['attr_bookings_placed'];
									$check_availability = 'NO';
									$available_tickets  = $attr_settings['booking_lockout'];

									$date_check_in = date( 'j-n-Y', $date );

									if ( isset( $bookings_placed ) && $bookings_placed != '' ) {

										$attribute_list = explode( ';', $bookings_placed );

										foreach ( $attribute_list as $attr_key => $attr_value ) {

											$attr_array = explode( ',', $attr_value );

											if ( $attr_name == $attr_array[0] ) {

												for ( $i = 1; $i < count( $attr_array ); $i++ ) {
													$explode_dates = explode( '=>', $attr_array[ $i ] );

													if ( isset( $explode_dates[0] ) && $explode_dates[0] != '' ) {

														$date = substr( $explode_dates[0], 2, -2 );

														if ( isset( $explode_dates[2] ) ) {
															$date_array[ $attr_name ][ $date ][ $explode_dates[1] ] = $explode_dates[2];
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

				// Calculaing availability based on resources.
				if ( '' !== $resource_id ) {

					$check_availability        = 'NO';
					$date_check_in             = date( 'j-n-Y', $date );
					$resource_date_array       = array();
					$all_resource_availability = array();
					$resource_ids              = explode( ',', $resource_id );

					if ( $cal_price ) {

						// Calculation for list view availability.
						// resource_id is a single item.
						$resource_bookings[ $resource_id ]        = bkap_calculate_bookings_for_resource( $resource_id, $product_id );
						$resource_bookings_placed[ $resource_id ] = ( isset( $resource_bookings[ $resource_id ]['bkap_time_booking_placed'] ) && '' !== $resource_bookings[ $resource_id ]['bkap_time_booking_placed'] ) ? $resource_bookings[ $resource_id ]['bkap_time_booking_placed'] : array();
					}

					if ( is_array( $resource_bookings_placed ) && count( $resource_bookings_placed ) > 0 ) {
						foreach ( $resource_bookings_placed as $id => $resource_bookings_placed_list_dates ) {
							$explode_date = explode( '=>', $resource_bookings_placed_list_dates ); // separate the qty for each date & time slot.

							if ( isset( $explode_date[2] ) && '' !== $explode_date[2] ) {
								$date = substr( $explode_date[0], 2, -2 );
								$resource_date_array[ $id ][ $date ][ $explode_date[1] ] = (int) $explode_date[2];
							}
						}
					}

					// Check if the same resource is already present in the cart.
					if ( isset( WC()->cart ) && count( WC()->cart->get_cart() ) > 0 ) {
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

							if ( isset( $values['bkap_booking'] ) ) {
								$cart_booking = $values['bkap_booking'][0];

								if ( isset( $cart_booking['resource_id'] ) && in_array( $cart_booking['resource_id'], $resource_ids ) ) {

									if ( isset( $cart_booking['time_slot'] ) && '' !== $cart_booking['time_slot'] ) {
										$time_slot = $cart_booking['time_slot'];
										$timeslot  = explode( ' - ', $time_slot );
										$frmtime   = date( 'H:i', strtotime( $timeslot[0] ) );
										$totime    = date( 'H:i', strtotime( $timeslot[1] ) );
										$time_slot = $frmtime . ' - ' . $totime;

										$_qty = isset( $resource_date_array[ $cart_booking['resource_id'] ][ $date_check_in ][ $time_slot ] ) ? $resource_date_array[ $cart_booking['resource_id'] ][ $date_check_in ][ $time_slot ] : 0;
										$resource_date_array[ $cart_booking['resource_id'] ][ $date_check_in ][ $time_slot ] = $_qty + $values['quantity'];
									}
								}
							}
						}
					}
				}

				// Check if multiple time slots are enabled.
				$seperator_pos = strpos( $timeslots, ',' );

				if ( isset( $seperator_pos ) && $seperator_pos != '' ) {
					$time_slot_array = explode( ',', $timeslots );
				} else {
					$time_slot_array   = array();
					$time_slot_array[] = $timeslots;
				}

				$time_slot_array_count = count( $time_slot_array );
				for ( $i = 0; $i < $time_slot_array_count; $i++ ) {
					// split the time slot into from and to time.

					$timeslot         = $time_slot_array[ $i ];
					$timeslot_explode = explode( '-', $timeslot );
					if ( $timezone_check ) {

						$gmt_offset = get_option( 'gmt_offset' );
						$gmt_offset = $gmt_offset * 60 * 60;

						$bkap_offset = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' );
						$bkap_offset = $bkap_offset * 60;
						$offset      = $bkap_offset - $gmt_offset;

						$customer_timezone = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );
						$site_timezone     = bkap_booking_get_timezone_string();

						$from_hrs     = bkap_convert_date_from_timezone_to_timezone( $booking_date . ' ' . $timeslot_explode[0], $customer_timezone, $site_timezone, 'H:i' );
						$to_hrs       = isset( $timeslot_explode[1] ) ? bkap_convert_date_from_timezone_to_timezone( $booking_date . ' ' . $timeslot_explode[1], $customer_timezone, $site_timezone, 'H:i' ) : '';
						$booking_date = bkap_convert_date_from_timezone_to_timezone( $booking_date . ' ' . $timeslot_explode[0], $customer_timezone, $site_timezone, 'Y-m-d' );
						// Converting booking date to store timezone for getting correct availability.

					} else {
						$from_hrs = bkap_date_as_format( $timeslot_explode[0], 'H:i' );
						$to_hrs   = isset( $timeslot_explode[1] ) ? bkap_date_as_format( $timeslot_explode[1], 'H:i' ) : '';
					}

					if ( 'YES' == $check_availability ) {
						$available_tickets = self::bkap_get_time_availability( $product_id, $booking_date, $from_hrs, $to_hrs, 'YES' );

						if ( $booking_id > 0 ) {
							$bkap           = new BKAP_Booking( $booking_id );
							$bkap_start     = date('H:i', $bkap->start);
							$bkap_end       = date('H:i', $bkap->end);
							/* if ( '00:00' == $bkap_end ) {
								$bkap_end = '';
							} */
							if ( ( $from_hrs == $bkap_start && empty( $to_hrs ) ) || ( $from_hrs == $bkap_start && ! empty( $to_hrs ) && $to_hrs == $bkap_end ) ) {
								$is_existing_ts = 'YES';
							}
						}

						if ( 'FALSE' == $available_tickets ) {
							$unlimited = 'NO';
						}
					} else {

						// HERE timezone is pending.

						if ( $timezone_check ) {
							$from_hrs     = bkap_date_as_format( $timeslot_explode[0], 'H:i' );
							$to_hrs       = isset( $timeslot_explode[1] ) ? bkap_date_as_format( $timeslot_explode[1], 'H:i' ) : '';
							$booking_time = $from_hrs . ' - ' . $to_hrs;
						} else {
							$booking_time = $from_hrs . ' - ' . $to_hrs;
						}

						if ( $attr_lockout_set == 'YES' ) {
							$orders_placed = 0;

							if ( is_array( $attribute_booking_data ) && count( $attribute_booking_data ) > 0 ) {

								foreach ( $attribute_booking_data as $attr_name => $attr_settings ) {

									$available_tickets = $attr_settings['booking_lockout'];

									if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
										if ( array_key_exists( $date_check_in, $date_array[ $attr_name ] ) ) {

											if ( array_key_exists( $booking_time, $date_array[ $attr_name ][ $date_check_in ] ) ) {
												$orders_placed = $date_array[ $attr_name ][ $date_check_in ][ $booking_time ];
											}

											$available_tickets -= $orders_placed;
										}
									}
									$msg_format = get_option( 'book_available-stock-time-attr' );
									$attr_label = wc_attribute_label( $attr_name, $_product );

									$avaiability_msg = str_replace( array( 'AVAILABLE_SPOTS', 'ATTRIBUTE_NAME', 'DATE', 'TIME' ), array( $available_tickets, $attr_label, $date_fld_val, $time_slot_array[ $i ] ), $msg_format );
									$message        .= $avaiability_msg . '<br>';
								}
							}
						} elseif ( $variation_lockout_set == 'YES' ) {

							$orders_placed = 0;

							if ( isset( $date_array ) && is_array( $date_array ) && count( $date_array ) > 0 ) {
								if ( array_key_exists( $date_check_in, $date_array ) ) {
									if ( array_key_exists( $booking_time, $date_array[ $date_check_in ] ) ) {
										$orders_placed = $date_array[ $date_check_in ][ $booking_time ];
									}
									$available_tickets = $variation_lockout - $orders_placed;
								}
							}
						}

						// Availability for resources.
						if ( '' !== $resource_id ) {

							$resource_orders_placed = 0;
							$all_available_tickets  = array();

							if ( isset( $resource_date_array ) && is_array( $resource_date_array ) && count( $resource_date_array ) > 0 ) {

								foreach ( $resource_date_array as $id => $date_array ) {

									if ( isset( $date_array[ $date_check_in ] ) /* array_key_exists( $date_check_in, $resource_date_array ) */ ) {
										if ( isset( $date_array[ $date_check_in ][ $booking_time ] ) /* array_key_exists( $booking_time, $resource_date_array[ $date_check_in ] ) */ ) {
											$resource_orders_placed = $date_array[ $date_check_in ][ $booking_time ];
										} else {
											// Condition for overlapping timeslot check in cart
											foreach ( $date_array[ $date_check_in ] as $k => $v ) {
												$ktimeslot = explode( ' - ', $k );
												$lf        = strtotime( $ktimeslot[0] ); // 07:00 100
												$lt        = strtotime( $ktimeslot[1] ); // 15:00 200

												$t_s_e = explode( ' - ', $booking_time );
												$f     = strtotime( $t_s_e[0] ); // 07:00 100
												$t     = strtotime( $t_s_e[1] ); // 11:00 150

												// 07:00 > 07:00 && 07:00 < 15:00  || 11:00 > 07:00 && 11:00 < 15:00
												if ( ( $f > $lf && $f < $lt ) || ( $t > $lf && $t < $lt ) ) {
													$resource_orders_placed = $v;
												}
											}
										}

										$all_resource_availability[ $id ] = 'U' === $all_resource_availability[ $id ] ? $all_resource_availability[ $id ] : ( $all_resource_availability[ $id ] - (int) $resource_orders_placed );
									}
								}
							}
						}
					}

					$overlapping = bkap_booking_overlapping_timeslot( $global_settings, $product_id );
					$s_timeslot  = explode( ' - ', $timeslot );
					$s_from      = strtotime( $s_timeslot[0] );
					$s_to        = isset( $s_timeslot[1] ) ? strtotime( $s_timeslot[1] ) : '';

					// Check if the same product is already present in the cart
					if ( isset( WC()->cart ) ) {
						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {
							$product_id_cart = $values['product_id'];
							if ( isset( $values['bkap_booking'] ) && $product_id_cart == $product_id ) {

								$check_lockout = 'YES';
								if ( isset( $variation_lockout_set ) && 'YES' == $variation_lockout_set ) {
									if ( $variation_id != $values['variation_id'] ) {
										$check_lockout = 'NO';
									}
								} elseif ( isset( $attr_lockout_set ) && 'YES' == $attr_lockout_set ) {
									$check_lockout = 'NO';
								}

								if ( 'YES' == $check_lockout && '' === $resource_id ) {

									$booking  = $values['bkap_booking'][0];
									$quantity = $values['quantity'];

									if ( $booking['hidden_date'] == $booking_date_in ) {
										if ( $booking['time_slot'] == $timeslot ) {

											/* Persons Calculations */
											if ( isset( $booking['persons'] ) ) {
												$carr_total_person = 1;
												if ( 'on' === $booking_settings['bkap_each_person_booking'] ) {
													$carr_total_person = array_sum( $booking['persons'] );
													$quantity          = $quantity * $carr_total_person;
												}
											}

											if ( 'Unlimited' == $available_tickets ) {
												$unlimited = 'YES';
											} else if ( (int) $available_tickets > 0 ) {
												$unlimited          = 'NO';
												$available_tickets -= $quantity;

												if ( $available_tickets == 0 ) {
													$booking_in_cart = 'YES';
												}
											}
										} else {

											// overlapping check.
											if ( $overlapping && '' != $s_to ) {
												if ( $available_tickets > 0 ) {
													$unlimited = 'NO';
													$c_explode = explode( ' - ', $booking['time_slot'] );
													$c_from    = strtotime( $c_explode[0] );
													$c_to      = strtotime( $c_explode[1] );

													if ( ( $s_from > $c_from && $s_from < $c_to ) || ( $s_to > $c_from && $s_to < $c_to ) || ( $s_from <= $c_from && $s_to >= $c_to ) ) {
														$available_tickets -= $quantity;

														if ( $available_tickets == 0 ) {
															$booking_in_cart = 'YES';
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

					if ( '' !== $resource_id ) {

						if ( 'YES' !== $attr_lockout_set && 'NO' === $booking_in_cart && 'FALSE' !== $available_tickets ) {
							$msg_format      = get_option( 'book_available-stock-time' );
							$avaiability_msg = str_replace( array( 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $available_tickets, $date_fld_val, $time_slot_array[ $i ] ), $msg_format );
							$avaiability_msg = apply_filters(
								'bkap_availability_message_display',
								$avaiability_msg,
								array(
									'available'    => $available_tickets,
									'date'         => $booking_date_in,
									'date_display' => $date_fld_val,
									'time_slot'    => $time_slot_array[ $i ],
								),
								$product_id,
								$msg_format
							);
							$message        .= $avaiability_msg . '<br>';

							foreach ( $all_resource_availability as $_resource_id => $_availability ) {
								$_all_resource_availability[] = $_availability;
								$_message                     = $_availability;
								$_message                     = ( 'U' === $_availability ) ? __( 'Unlimited', 'woocommerce-booking' ) : $_message;
								$_message                     = ( 0 === $_availability ) ? sprintf( __( 'Available bookings for timeslot %s are either fully booked or already added in the cart', 'woocommerce-booking' ), $timeslots ) : $_message;
								$availability_msg             = str_replace( array( 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $_message, $date_fld_val, $time_slot_array[ $i ] ), $msg_format );
								$_message                     = ( 0 !== $_availability ) ? $availability_msg : $_message;
								$resource_name                = Class_Bkap_Product_Resource::get_resource_name( $_resource_id );
								$message                     .= '<label>' . $_message . ' for ' . $resource_name . '</label>';
							}
						}

						if ( $booking_in_cart == 'YES' && $available_tickets == 0 ) {
							$available_tickets = 0;
							$message           = sprintf( __( 'Available bookings for this timeslot are already added in the cart. You can add bookings for a different timeslot or place the order that is currently in the <a href="%1$s">%2$s</a>.', 'woocommerce-booking' ), esc_url( wc_get_page_permalink( 'cart' ) ), esc_html__( 'cart', 'woocommerce' ) );
							$message           = apply_filters( 'bkap_all_datetime_availability_present_in_cart', $message );
						}

						if ( $available_tickets == 0 && $unlimited == 'YES' ) {
							$available_tickets = __( 'Unlimited', 'woocommerce-booking' );
						}
					} else {

						if ( $attr_lockout_set != 'YES' && $booking_in_cart == 'NO' && 'FALSE' != $available_tickets ) {
							$avaiability_msg = str_replace( array( 'AVAILABLE_SPOTS', 'DATE', 'TIME' ), array( $available_tickets, $date_fld_val, $time_slot_array[ $i ] ), $msg_format );
							$avaiability_msg = apply_filters(
								'bkap_availability_message_display',
								$avaiability_msg,
								array(
									'available'    => $available_tickets,
									'date'         => $_POST['checkin_date'],
									'date_display' => $date_fld_val,
									'time_slot'    => $time_slot_array[ $i ],
								),
								$product_id,
								$msg_format
							);
							$message        .= $avaiability_msg . '<br>';

						}

						if ( $booking_in_cart == 'YES' && $available_tickets == 0 ) {
							$available_tickets = 0;

							$message = sprintf( __( 'Available bookings for this timeslot are already added in the cart. You can add bookings for a different timeslot or place the order that is currently in the <a href="%1$s">%2$s</a>.', 'woocommerce-booking' ), esc_url( wc_get_page_permalink( 'cart' ) ), esc_html__( 'cart', 'woocommerce' ) );
							$message = apply_filters( 'bkap_all_datetime_availability_present_in_cart', $message );

						}

						// Available Booking Based on Total Person.
						if ( isset( $booking_settings['bkap_each_person_booking'] ) && 'on' === $booking_settings['bkap_each_person_booking'] ) {
							if ( is_numeric( $available_tickets ) && $total_person > $available_tickets ) {
								$validation_msg = bkap_max_persons_available_msg( 'time', $product_id );
								$message        = sprintf( $validation_msg, $available_tickets, $date_fld_val, $time_slot_array[ $i ] );
								$message        = bkap_woocommerce_error_div( $message );
								$data           = array(
									'message' => $message,
									'error'   => true,
								);
								wp_send_json( $data );
							}
						}
					}
				}
			}

			$data = apply_filters(
				'bkap_date_lockout_data',
				array(
					'message'        => $message,
					'max_qty'        => $available_tickets,
					'is_existing_ts' => $is_existing_ts,
				),
				wc_get_product( $product_id ),
				$product_id,
				$variation_id,
				$resource_id,
				$booking_date
			);

			if ( isset( $post ) && is_array( $post ) && count( $post ) > 0 ) {
				return $data;
			}

			wp_send_json( $data );
		}

		/**
		 * This function calculates and returns the availability for a given
		 * timslot for all types of bookings
		 *
		 * @param integer $product_id - Product ID
		 * @param string  $booking_date - Booking Start Date in j-n-Y format
		 * @param string  $from_hrs - Time Slot Start Hours (G:i)
		 * @param string  $to_hrs - Time Slot End Hours (G:i)
		 * @param string  $check_availability - YES when availability is to be calculated at Product level
		 *                                      NO when availability is to be calculated at variation/attribute/resource level.
		 * @return string|integer $available_tickets - Available Bookings. Integer for a finite value and string for unlimited bookings.
		 *
		 * @since 2.1
		 */

		public static function bkap_get_time_availability( $product_id, $booking_date, $from_hrs, $to_hrs, $check_availability ) {

			global $wpdb;

			$available_tickets = 0;
			$unlimited         = 'YES';

			if ( 'YES' == $check_availability ) {

				$from_hrs = date( 'H:i', strtotime( $from_hrs ) );

				if ( $to_hrs == '' ) {
					$time_query   = 'SELECT total_booking,available_booking FROM `' . $wpdb->prefix . "booking_history`
								WHERE post_id = %d
								AND start_date = %s
								AND TIME_FORMAT( from_time, '%H:%i' ) = %s
								AND to_time = %s
								AND status = ''";
					$results_time = $wpdb->get_results( $wpdb->prepare( $time_query, $product_id, $booking_date, $from_hrs, $to_hrs ) );
				} else {
					$to_hrs       = date( 'H:i', strtotime( $to_hrs ) );
					$time_query   = 'SELECT total_booking,available_booking FROM `' . $wpdb->prefix . "booking_history`
								WHERE post_id = %d
								AND weekday = ''
								AND start_date = %s
								AND TIME_FORMAT( from_time, '%H:%i' ) = %s
								AND TIME_FORMAT( to_time, '%H:%i' ) = %s
								AND status = ''";
					$results_time = $wpdb->get_results( $wpdb->prepare( $time_query, $product_id, $booking_date, $from_hrs, $to_hrs ) );

					if ( count( $results_time ) == 0 ) {
						$time_query   = 'SELECT total_booking,available_booking FROM `' . $wpdb->prefix . "booking_history`
								WHERE post_id = %d
								AND start_date = %s
								AND TIME_FORMAT( from_time, '%H:%i' ) = %s
								AND TIME_FORMAT( to_time, '%H:%i' ) = %s
								AND status = ''";
						$results_time = $wpdb->get_results( $wpdb->prepare( $time_query, $product_id, $booking_date, $from_hrs, $to_hrs ) );
					}
				}

				// If record is found then simply display the available bookings
				if ( isset( $results_time ) && count( $results_time ) > 0 ) {

					if ( $results_time[0]->available_booking > 0 ) {
						$unlimited = 'NO';

						/* Person Calculations */
						$bookings_data = get_bookings_for_range( $product_id, $booking_date, strtotime( $booking_date . ' 23:59' ) );

						$to_hrs = ( '' == $to_hrs ) ? '00:00' : $to_hrs;
						if ( count( $bookings_data ) > 0 ) {
							if ( isset( $bookings_data[ date( 'Ymd', strtotime( $booking_date ) ) ] ) ) {
								if ( isset( $bookings_data[ date( 'Ymd', strtotime( $booking_date ) ) ][ $from_hrs . ' - ' . $to_hrs ] ) ) {
									$available = $bookings_data[ date( 'Ymd', strtotime( $booking_date ) ) ][ $from_hrs . ' - ' . $to_hrs ];
									$available = $results_time[0]->total_booking - $available;
								}
							}
						}

						$available_tickets = isset( $available ) ? $available : (int) $results_time[0]->available_booking;
					} elseif ( $results_time[0]->available_booking == 0 && $results_time[0]->total_booking > 0 ) {
						$unlimited         = 'NO';
						$available_tickets = 'FALSE';
					}
				} else { // Else get the base record and the availability for that weekday
					$weekday         = date( 'w', strtotime( $booking_date ) );
					$booking_weekday = 'booking_weekday_' . $weekday;

					if ( $to_hrs == '' ) {
						$base_query = 'SELECT available_booking FROM `' . $wpdb->prefix . "booking_history`
									WHERE post_id = %d
									AND weekday = %s
									AND TIME_FORMAT( from_time, '%H:%i' ) = %s
									AND to_time = %s
									ANd status = ''";
					} else {
						$base_query = 'SELECT available_booking FROM `' . $wpdb->prefix . "booking_history`
									WHERE post_id = %d
									AND weekday = %s
									AND TIME_FORMAT( from_time, '%H:%i' ) = %s
									AND TIME_FORMAT( to_time, '%H:%i' ) = %s
									ANd status = ''";
					}

					$results_base = $wpdb->get_results( $wpdb->prepare( $base_query, $product_id, $booking_weekday, $from_hrs, $to_hrs ) );

					if ( isset( $results_base ) && count( $results_base ) > 0 ) {

						if ( $results_base[0]->available_booking > 0 ) {
							$unlimited         = 'NO';
							$available_tickets = (int) $results_base[0]->available_booking;
						}
					} else {
						$unlimited = 'NO'; // this will ensure that availability is not displayed as 'Unlimited' when no record is found. This might happen when importing bookings
					}
				}
			}

			if ( $available_tickets == 0 && $unlimited == 'YES' ) {
				$available_tickets = __( 'Unlimited', 'woocommerce-booking' );
			}

			return $available_tickets;
		}

		/**
		 * Sets up and displays the product price
		 *
		 * This function setups the bookable price in the hidden fields
		 * & displays the price for single day and/or time bookings when
		 * the purchase without date setting is on and the product is being
		 * purchased without a date.
		 * Called via AJAX
		 *
		 * @since 2.8.1
		 */

		public static function bkap_purchase_wo_date_price() {

			$product_id    = $_POST['post_id'];
			$variation_id  = ( isset( $_POST['variation_id'] ) && '' != $_POST['variation_id'] ) ? $_POST['variation_id'] : 0;
			$quantity      = ( isset( $_POST['quantity'] ) && '' != $_POST['quantity'] ) ? $_POST['quantity'] : 1;
			$_product      = wc_get_product( $product_id );
			$product_type  = $_product->get_type();
			$product_price = bkap_common::bkap_get_price( $product_id, $variation_id, $product_type );
			$price         = $product_price * $quantity;

			$wc_price_args   = bkap_common::get_currency_args();
			$formatted_price = wc_price( $price, $wc_price_args );
			$display_price   = get_option( 'book_price-label' ) . ' ' . $formatted_price;

			$wp_send_json                           = array();
			$wp_send_json['total_price_calculated'] = $price;
			$wp_send_json['bkap_price_charged']     = $price;
			$wp_send_json['bkap_price']             = addslashes( $display_price );

			wp_send_json( $wp_send_json );
		}

		/**
		 * This function displays the price calculated on the frontend
		 * product page for Multiple day booking feature.
		 * Called via AJAX
		 *
		 * @since 1.1
		 */

		public static function bkap_get_per_night_price() {

			do_action( 'bkap_before_get_per_night_price' );

			$product_type   = $_POST['product_type'];
			$variation_id   = $_POST['variation_id'];
			$product_id     = $_POST['post_id'];
			$check_in_date  = sanitize_text_field( $_POST['checkin_date'] );
			$check_out_date = sanitize_text_field( $_POST['current_date'] );
			$diff_days      = sanitize_text_field( $_POST['diff_days'] );

			$product_id       = bkap_common::bkap_get_product_id( $product_id );
			$booking_settings = bkap_setting( $product_id );
			$product_obj      = wc_get_product( $product_id );

			$quantity_grp_str  = isset( $_POST['quantity'] ) ? sanitize_text_field( $_POST['quantity'] ) : 1;
			$currency_selected = ( isset( $_POST['currency_selected'] ) && $_POST['currency_selected'] != '' ) ? sanitize_text_field( $_POST['currency_selected'] ) : '';
			$gf_options        = ( isset( $_POST['gf_options'] ) && is_numeric( $_POST['gf_options'] ) ) ? $_POST['gf_options'] : 0;
			$resource_id       = ( isset( $_POST['resource_id'] ) ) ? $_POST['resource_id'] : '';

			$selected_person_data = isset( $_POST['person_ids'] ) ? $_POST['person_ids'] : array();
			$total_person         = isset( $_POST['total_person'] ) ? sanitize_text_field( $_POST['total_person'] ) : 0;

			/* Person Calculations */
			$message = bkap_validate_person_selection( $selected_person_data, $total_person, $booking_settings, $product_id );

			if ( '' !== $message ) {
				$message = bkap_woocommerce_error_div( $message );
				$data    = array(
					'message' => $message,
					'error'   => true,
				);
				wp_send_json( $data );
			}

			$checkin_date_str  = strtotime( $check_in_date );
			$checkout_date_str = strtotime( $check_out_date );
			$checkin_date      = date( 'Y-m-d', $checkin_date_str );
			$checkout_date     = date( 'Y-m-d', $checkout_date_str );

			$number = 1;
			if ( (int) $diff_days > 0 ) {
				$number_of_days = $checkout_date_str - $checkin_date_str;
				$number         = floor( $number_of_days / 86400 );
			} else {
				$number = (int) $diff_days;
			}

			// Rental Active and Same Day Charge opton is enable then consider end date as well.
			if ( is_plugin_active( 'bkap-rental/rental.php' ) ) {
				if ( isset( $booking_settings['booking_charge_per_day'] ) && $booking_settings['booking_charge_per_day'] == 'on' ) {
					$number = $number + 1;
				}
			}
			$number = ( $number == 0 || $number <= 0 ) ? 1 : $number;

			if ( $product_type != 'grouped' ) {
				do_action(
					'bkap_display_multiple_day_updated_price',
					$product_id,
					$booking_settings,
					$product_obj,
					$variation_id,
					$checkin_date,
					$checkout_date,
					$number,
					$gf_options,
					$resource_id,
					$selected_person_data,
					$currency_selected
				);
			}

			if ( $product_type == 'grouped' ) {

				$currency_symbol = get_woocommerce_currency_symbol();
				$has_children    = '';
				$raw_price_str   = '';
				$price_str       = '';
				$price_arr       = array();

				if ( $product_obj->has_child() ) {
					$has_children = 'yes';
					$child_ids    = $product_obj->get_children();
				}

				$quantity_array = explode( ',', $quantity_grp_str );
				$i              = 0;

				foreach ( $child_ids as $k => $v ) {

					$price = get_post_meta( $v, '_sale_price', true );
					if ( $price == '' ) {
						$price = get_post_meta( $v, '_regular_price', true );
					}

					// check if it's a bookable product
					$bookable = bkap_common::bkap_get_bookable_status( $v );
					if ( $bookable ) {
						$final_price = $diff_days * $price * $quantity_array[ $i ];
					} else {
						$final_price = $price * $quantity_array[ $i ];
					}
					$raw_price = $final_price;

					$wc_price_args   = bkap_common::get_currency_args();
					$formatted_price = wc_price( $final_price, $wc_price_args );

					$child_product = wc_get_product( $v );

					if ( function_exists( 'icl_object_id' ) ) {

						global $woocommerce_wpml;
						// Multi currency is enabled
						if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
							$custom_post = bkap_common::bkap_get_custom_post( $v, 0, $product_type );
							if ( $custom_post == 1 ) {
								$raw_price       = $final_price;
								$wc_price_args   = bkap_common::get_currency_args();
								$formatted_price = wc_price( $final_price, $wc_price_args );
							} elseif ( $custom_post == 0 ) {
								$raw_price       = apply_filters( 'wcml_raw_price_amount', $final_price );
								$formatted_price = apply_filters( 'wcml_formatted_price', $final_price );
							}
						}
					}

					$raw_price_str .= $v . ':' . $raw_price . ',';
					$price_str     .= $child_product->get_title() . ': ' . $formatted_price . '<br>';
					$i++;
				}
			}

			if ( $raw_price_str > 0 && 'bundle' == $product_type ) {
				$bundle_price = bkap_common::get_bundle_price( $raw_price_str, $product_id, $variation_id_to_fetch );
				$price_str    = wc_price( $bundle_price, $wc_price_args );
			}

			$display_price = get_option( 'book.price-label' ) . ' ' . $price_str;

			$wp_send_json = array();

			// print( 'jQuery( "#bkap_price_charged" ).val( "'. addslashes( $raw_price_str ) . '");' );
			// print( 'jQuery( "#total_price_calculated" ).val( "'. addslashes( $raw_price_str ) . '");' );
			// print( 'jQuery( "#bkap_price" ).html( "' . addslashes( $display_price ) . '");' );

			$wp_send_json['bkap_price_charged']     = addslashes( $raw_price_str );
			$wp_send_json['total_price_calculated'] = addslashes( $raw_price_str );
			$wp_send_json['bkap_price']             = addslashes( $display_price );

			wp_send_json( $wp_send_json );
		}

		/**
		 * This function adds checks of product is variable and no option is choosen then show message instead of calculating the price.
		 *
		 * @since 4.15.0
		 */

		public static function bkap_before_get_per_night_price_callback() {

			$product_type          = $_POST['product_type'];
			$variation_id_to_fetch = $_POST['variation_id'];
			$error_message         = __( 'Please select an option.', 'woocommerce-booking' );
			$wp_send_json          = array();
			if ( $variation_id_to_fetch == 0 && $product_type == 'variable' ) {
				// print( 'jQuery( "#bkap_price" ).html( "' . addslashes( $error_message ) . '");' );
				$wp_send_json['bkap_price'] = addslashes( $error_message );
				// die();
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * This function adds the booking date selected on the frontend
		 * product page in the Booking History table for only day bookings
		 * using Weekdays when the date is selected.
		 *
		 * Called via AJAX
		 *
		 * @since 1.0
		 */

		public static function bkap_insert_date( $p_id = 0, $p_type = '', $date = '' ) {

			global $wpdb;

			$current_date  = ( $date != '' ) ? $date : $_POST['date'];
			$date_to_check = date( 'Y-m-d', strtotime( $current_date ) );
			$post_id       = ( $p_id != 0 ) ? $p_id : $_POST['id'];
			$product_type  = ( $p_type != '' ) ? $p_type : $_POST['p_type'];

			$check_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
						  	WHERE start_date= %s
						  	AND post_id= %d
						  	AND status = ''
						  	AND available_booking >= 0";
			$results_check = $wpdb->get_results( $wpdb->prepare( $check_query, $date_to_check, $post_id ) );

			if ( ! $results_check ) {

				$day_check         = 'booking_weekday_' . date( 'w', strtotime( $current_date ) );
				$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    								WHERE weekday= %s
    								AND post_id= %d
    								AND start_date='0000-00-00'
    								AND status = ''
    								AND available_booking > 0";
				$results_day_check = $wpdb->get_results( $wpdb->prepare( $check_day_query, $day_check, $post_id ) );

				if ( ! $results_day_check ) {
					$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    									WHERE weekday= %s
    									AND post_id= %d
    									AND start_date='0000-00-00'
    									AND status = ''
    									AND total_booking = 0
    									AND available_booking = 0";
					$results_day_check = $wpdb->get_results( $wpdb->prepare( $check_day_query, $day_check, $post_id ) );
				}

				// Grouped products compatibility
				$has_children = '';
				if ( $product_type == 'grouped' ) {
					$product = wc_get_product( $post_id );
					if ( $product->has_child() ) {
						$has_children = 'yes';
						$child_ids    = $product->get_children();
					}
				}

				foreach ( $results_day_check as $key => $value ) {

					$insert_date = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
									(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
									VALUES (
									'" . $post_id . "',
									'" . $day_check . "',
									'" . $date_to_check . "',
									'0000-00-00',
									'',
									'',
									'" . $value->total_booking . "',
									'" . $value->available_booking . "' )";
					$wpdb->query( $insert_date );

					// Grouped products compatibility
					if ( $product_type == 'grouped' ) {

						if ( $has_children == 'yes' ) {

							foreach ( $child_ids as $k => $v ) {

								$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    												WHERE weekday= %s
    												AND post_id= %d
    												AND start_date='0000-00-00'
    												AND status = ''
    												AND available_booking > 0";
								$results_day_check = $wpdb->get_results( $wpdb->prepare( $check_day_query, $day_check, $v ) );

								if ( ! $results_day_check ) {
									$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    													WHERE weekday= %s
    													AND post_id= %d
    													AND start_date='0000-00-00'
    													AND status = ''
    													AND total_booking = 0
    													AND available_booking = 0";
									$results_day_check = $wpdb->get_results( $wpdb->prepare( $check_day_query, $day_check, $v ) );
								}

								$insert_date = 'INSERT INTO `' . $wpdb->prefix . "booking_history`
    											(post_id,weekday,start_date,end_date,from_time,to_time,total_booking,available_booking)
    											VALUES (
    											'" . $v . "',
    											'" . $day_check . "',
    											'" . $date_to_check . "',
    											'0000-00-00',
    											'',
    											'',
    											'" . $results_day_check[0]->total_booking . "',
    											'" . $results_day_check[0]->available_booking . "' )";
								$wpdb->query( $insert_date );
							}
						}
					}
				}
			}
			if ( ! $p_id ) {
				die();
			}
		}

		/**
		 * This function displays the timeslots for the selected
		 * date for Date & Time Bookable products.
		 *
		 * Called via AJAX
		 *
		 * @since 1.0
		 */

		public static function bkap_check_for_time_slot() {

			$current_date    = sanitize_text_field( $_POST['current_date'] );
			$post_id         = $_POST['post_id'];
			$global_settings = bkap_global_setting();

			if ( isset( $_POST['date_time_type'] ) && $_POST['date_time_type'] == 'duration_time' ) {
				$duration_time_array = Bkap_Duration_Time::get_duration_time_slot( $current_date, $post_id );
			} else {

				$timezone_check                           = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled
				$time_format_to_show                      = bkap_common::bkap_get_time_format( $global_settings );
				$extra_information                        = array();
				$extra_information['time_format_to_show'] = $time_format_to_show;
				if ( $timezone_check ) {

					$store_timezone_string = bkap_booking_get_timezone_string(); // fetching timezone string set for the store. E.g Asia/Calcutta.

					if ( '' !== Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) {
						$bkap_timezone_name = Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' );
					} elseif ( isset( $_POST['bkap_timezone_name'] ) ) {
						$bkap_timezone_name = $_POST['bkap_timezone_name'];
					}

					$dates   = array();
					$dates[] = bkap_convert_date_from_to_timezone( $current_date . ' 00:00', $bkap_timezone_name, $store_timezone_string, 'j-n-Y' );
					$dates[] = bkap_convert_date_from_to_timezone( $current_date . ' 23:59', $bkap_timezone_name, $store_timezone_string, 'j-n-Y' );

					$date_time_drop_down = array();

					foreach ( $dates as $key => $value ) {
						$timeslots = self::get_time_slot( $value, $post_id );
						if ( '' !== $timeslots ) {
							$date_time_drop_down[ $value ] = $timeslots;
						}
					}

					$extra_information['timezone']        = true;
					$extra_information['store_time_zone'] = $store_timezone_string;

					if ( isset( $global_settings->booking_timeslot_display_mode ) &&
						'list-view' === $global_settings->booking_timeslot_display_mode
					) {
						self::bkap_display_timezoned_timeslots_lists( $current_date, $date_time_drop_down, $global_settings, $extra_information );
					} else {
						self::bkap_display_time_dropdown_timezone( $current_date, $date_time_drop_down, $global_settings, $extra_information );
					}
				} else {
					$time_drop_down = self::get_time_slot( $current_date, $post_id );

					if ( isset( $global_settings->booking_timeslot_display_mode ) &&
						'list-view' === $global_settings->booking_timeslot_display_mode
					) {
						self::bkap_display_timeslots_lists( $current_date, $time_drop_down, $global_settings, $extra_information );
					} else {
						self::bkap_display_time_dropdown( $current_date, $time_drop_down, $global_settings, $extra_information );
					}
				}
			}

			die();
		}

		/**
		 * Display available timeslot in the dropdown for timezone
		 *
		 * @param string $current_date Selected Date
		 * @param array  $date_time_drop_down Array of dates and its timeslots.
		 * @param obj    $global_settings Global Booking Settings
		 * @param array  $extra_information It contains timezone, store timezone & timeformat
		 *
		 * @since 4.15.0
		 */

		public static function bkap_display_time_dropdown_timezone( $current_date, $date_time_drop_down, $global_settings, $extra_information ) {

			if ( count( $date_time_drop_down ) > 0 ) { // Dates mate timeslots chhe ke nai

				$time_format_to_show = $extra_information['time_format_to_show'];
				$time_lable          = apply_filters( 'bkap_change_book_time_label', bkap_option( 'time' ) );
				$time_lable          = __( ( '' !== $time_lable ? $time_lable : 'Booking Time' ), 'woocommerce-booking' );
				$choose_time         = bkap_option( 'choose_time' );
				$timeslots_count     = 0;

				if ( function_exists( 'icl_t' ) ) {
					$choose_time = icl_t( 'woocommerce-booking', 'choose_a_time', $choose_time );
				}

				$drop_down  = '<label id="bkap_book_time">' . $time_lable . ':</label><br/>';
				$drop_down  = apply_filters( 'bkap_change_book_time_label_section', $drop_down );
				$drop_down .= "<select name='time_slot' id='time_slot' class='time_slot'>";
				$drop_down .= "<option value=''>" . $choose_time . '</option>';

				$current_date_str = strtotime( $current_date ); // timestamp of date in UTC timezone
				$current_date_ymd = date( 'Y-m-d', $current_date_str ); // getting date in Y-m-d format

				// $offset = bkap_get_offset_from_date( $current_date_str, Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ); // fetching the timezone str

				foreach ( $date_time_drop_down as $key => $value ) {
					$offset = bkap_get_offset_from_date( strtotime( $key ), Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ); // fetching the timezone str

					date_default_timezone_set( $extra_information['store_time_zone'] );
					$time_drop_down_array = explode( '|', $value );

					if ( trim( $time_drop_down_array[0] ) === 'ERROR' ) {
						$drop_down       = trim( $time_drop_down_array[1] );
						$drop_down      .= '<select name="time_slot" id="time_slot" class="time_slot" style="display:none;">';
						$timeslots_count = 2;
					} else {
						$time_drop_down_array = bkap_sort_time_in_chronological( $time_drop_down_array );

						foreach ( $time_drop_down_array as $k => $v ) {

							if ( $v != '' ) {
								$timeslots_count++;

								$store_time = sprintf( __( 'Store time is %1$s %2$s', 'woocommerce-booking' ), $key, $v );
								$vexplode   = explode( ' - ', $v );
								$fromtime   = strtotime( $key . ' ' . $vexplode[0] );

								$datetimeISO = date( DateTime::ISO8601, $fromtime );

								$from_time = date( $time_format_to_show, $offset + $fromtime );
								$to_time   = '';

								$timedateymd = date( 'Y-m-d', $offset + $fromtime );

								if ( $timedateymd != $current_date_ymd ) {
									continue;
								}

								if ( isset( $vexplode[1] ) ) {
									$totime  = strtotime( $vexplode[1] );
									$to_time = date( $time_format_to_show, $offset + $totime );
									$to_time = ' - ' . $to_time;
								}

								$v = $from_time . $to_time;

								$drop_down .= "<option data-value='" . $datetimeISO . "' title='" . $store_time . "' value='" . $v . "'>" . $v . '</option>';
							}
						}

						date_default_timezone_set( 'UTC' );
					}
				}

				$drop_down .= '</select>';

				$wp_send_json['bkap_time_count']    = $timeslots_count;
				$wp_send_json['bkap_time_dropdown'] = $drop_down;
				wp_send_json( $wp_send_json );
			} else {
				$wp_send_json['bkap_time_count']    = 0;
				$wp_send_json['bkap_time_dropdown'] = __( 'There are no timeslots available for the selected date.', 'woocommerce-booking' );
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * Display available timezoned timeslot in the list view.
		 *
		 * @param string $current_date Selected Date
		 * @param array  $datewise_timeslots Array of dates and its timeslots.
		 * @param obj    $global_settings Global Booking Settings
		 * @param array  $extra_information It contains timezone, store timezone & timeformat
		 *
		 * @since 5.5.0
		 */

		public static function bkap_display_timezoned_timeslots_lists(
		$current_date,
		$datewise_timeslots,
		$global_settings,
		$extra_information
		) {

			if ( count( $datewise_timeslots ) > 0 ) { // Dates mate timeslots chhe ke nai

				$time_format_to_show = $extra_information['time_format_to_show'];
				$time_lable          = apply_filters( 'bkap_change_book_time_label', bkap_option( 'time' ) );
				$time_lable          = __( ( '' !== $time_lable ? $time_lable : 'Booking Time' ), 'woocommerce-booking' );
				$choose_time         = bkap_option( 'choose_time' );
				$timeslots_count     = 0;

				if ( function_exists( 'icl_t' ) ) {
					$choose_time = icl_t( 'woocommerce-booking', 'choose_a_time', $choose_time );
				}

				$time_slot_lists  = '<label id="bkap_book_time">' . $time_lable . ':</label><br/>';
				$time_slot_lists  = apply_filters( 'bkap_change_book_time_label_section', $time_slot_lists );
				$time_slot_lists .= '<input type="hidden" name="time_slot" id="time_slot" value="" />';
				$time_slot_lists .= '<ul class="timeslot-lists ts-grid-container">';
				$current_date_str = strtotime( $current_date ); // timestamp of date in UTC timezone
				$current_date_ymd = date( 'Y-m-d', $current_date_str ); // getting date in Y-m-d format
				// $offset = bkap_get_offset_from_date( $current_date_str, Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ); // fetching the timezone str

				foreach ( $datewise_timeslots as $key => $value ) {
					$offset = bkap_get_offset_from_date( strtotime( $key ), Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ); // fetching the timezone str

					date_default_timezone_set( $extra_information['store_time_zone'] );
					$time_drop_down_array = explode( '|', $value );
					if ( trim( $time_drop_down_array[0] ) === 'ERROR' ) {
						$drop_down       = trim( $time_drop_down_array[1] );
						$time_slot_lists = '<ul class="timeslot-lists ts-grid-container"><li class="ts-grid-item">' . $drop_down . ' </li>';
						$timeslots_count = 2;
						break;
					} else {
						$time_drop_down_array = bkap_sort_time_in_chronological( $time_drop_down_array );

						foreach ( $time_drop_down_array as $k => $v ) {

							if ( $v != '' ) {
								$timeslots_count++;

								$store_time = sprintf( __( 'Store time is %1$s %2$s', 'woocommerce-booking' ), $key, $v );
								$vexplode   = explode( ' - ', $v );
								$fromtime   = strtotime( $key . ' ' . $vexplode[0] );

								$datetimeISO = date( DateTime::ISO8601, $fromtime );

								$from_time = date( $time_format_to_show, $offset + $fromtime );
								$to_time   = '';

								$timedateymd = date( 'Y-m-d', $offset + $fromtime );

								if ( $timedateymd != $current_date_ymd ) {
									continue;
								}

								if ( isset( $vexplode[1] ) ) {
									$totime  = strtotime( $vexplode[1] );
									$to_time = date( $time_format_to_show, $offset + $totime );
									$to_time = ' - ' . $to_time;
								}

								$v                = $from_time . $to_time;
								$time_slot_lists .= '<li class="ts-grid-item">' .
									'<a href="#" data-value="' . $v . '">' .
									'<input type="radio" name="time_slots" value="' . $v . '" class="time_slot" />' .
									$v .
									'</a>' .
								'</li>';

							}
						}
					}

					date_default_timezone_set( 'UTC' );
				}

				$time_slot_lists .= '</ul>';

				$wp_send_json['bkap_time_count']    = $timeslots_count;
				$wp_send_json['bkap_time_dropdown'] = $time_slot_lists;
				wp_send_json( $wp_send_json );
			} else {
				$wp_send_json['bkap_time_count']    = 0;
				$wp_send_json['bkap_time_dropdown'] = __( 'There are no timeslots available for the selected date.', 'woocommerce-booking' );
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * Display available timeslots in the list views.
		 *
		 * @param string $current_date Selected Date
		 * @param string $time_slots All Timeslots concated with | character.
		 * @param obj    $global_settings Global Booking Settings
		 * @param array  $extra_information It contains timezone, store timezone & timeformat
		 *
		 * @since 5.5.0
		 */
		public static function bkap_display_timeslots_lists(
		$current_date,
		$time_slots,
		$global_settings,
		$extra_information
		) {
			$wp_send_json = array();

			if ( $time_slots != '' ) {

				$time_format_to_show = $extra_information['time_format_to_show'];
				$time_slot_lists     = '';
				$ts_lists            = explode( '|', rtrim( $time_slots, '|' ) );
				$ts_lists_arr        = apply_filters( 'bkap_time_slot_filter', $ts_lists, $extra_information );
				$time_slots_cnt      = 0;

				if ( trim( $ts_lists_arr[0] ) === 'ERROR' ) {
					$time_slot_lists = trim( $ts_lists_arr[1] );
					$time_slots_cnt  = 2;
				} else {
					$time_lable      = apply_filters( 'bkap_change_book_time_label', bkap_option( 'time' ) );
					$time_lable      = __( ( '' !== $time_lable ? $time_lable : 'Booking Time' ), 'woocommerce-booking' );
					$time_slot_lists = '<label id="bkap_book_time">' . $time_lable . '</label>';
					$time_slot_lists = apply_filters( 'bkap_change_book_time_label_section', $time_slot_lists );
					$time_slots_cnt  = count( $ts_lists_arr );
					$ts_lists_arr    = bkap_sort_time_in_chronological( $ts_lists_arr );
					$ts_lists_arr    = apply_filters( 'bkap_time_slot_filter_after_chronological', $ts_lists_arr, $extra_information );

					$time_selected = $time_slots_cnt == 1 ? ' active_slot' : '';

					$time_slot_lists .= '<input type="hidden" name="time_slot" id="time_slot" value="" />';
					$time_slot_lists .= '<ul class="timeslot-lists ts-grid-container">';
					foreach ( $ts_lists_arr as $key => $ts_val ) {
						if ( $ts_val != '' ) {
							$time_slot_lists .= '<li class="ts-grid-item ' . $time_selected . '">' .
								'<a href="#" data-value="' . $ts_val . '">' .
								'<input type="radio" name="time_slots" value="' . $ts_val . '" class="time_slot" />' .
								$ts_val .
								'</a>' .
							'</li>';
						}
					}
					$time_slot_lists .= '</ul>';
				}

				$wp_send_json['bkap_time_count']    = $time_slots_cnt;
				$wp_send_json['bkap_time_dropdown'] = $time_slot_lists;
				wp_send_json( $wp_send_json );

			} else {
				$wp_send_json['bkap_time_count']    = 0;
				$wp_send_json['bkap_time_dropdown'] = __( 'There are no timeslots available for the selected date.', 'woocommerce-booking' );
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * Display available timeslot in the dropdown
		 *
		 * @param string $current_date Selected Date
		 * @param array  $date_time_drop_down Array of dates and its timeslots.
		 * @param obj    $global_settings Global Booking Settings
		 * @param array  $extra_information It contains timezone, store timezone & timeformat
		 *
		 * @since 4.10.0
		 */

		public static function bkap_display_time_dropdown( $current_date, $time_drop_down, $global_settings, $extra_information ) {

			if ( $time_drop_down != '' ) {

				$time_format_to_show  = $extra_information['time_format_to_show'];
				$drop_down            = '';
				$time_drop_down       = rtrim( $time_drop_down, '|' );
				$time_drop_down_array = explode( '|', $time_drop_down );

				$time_drop_down_array = apply_filters( 'bkap_time_slot_filter', $time_drop_down_array, $extra_information );

				$timeslots_count = 0;
				$wp_send_json    = array();

				if ( trim( $time_drop_down_array[0] ) === 'ERROR' ) {
					$drop_down       = trim( $time_drop_down_array[1] );
					$timeslots_count = 2;
				} else {

					$time_lable  = apply_filters( 'bkap_change_book_time_label', bkap_option( 'time' ) );
					$time_lable  = __( ( '' !== $time_lable ? $time_lable : 'Booking Time' ), 'woocommerce-booking' );
					$choose_time = bkap_option( 'choose_time' );

					if ( function_exists( 'icl_t' ) ) {
						$choose_time = icl_t( 'woocommerce-booking', 'choose_a_time', $choose_time );
					}

					$drop_down  = '<label id="bkap_book_time">' . $time_lable . ':</label><br/>';
					$drop_down  = apply_filters( 'bkap_change_book_time_label_section', $drop_down );
					$drop_down .= "<select name='time_slot' id='time_slot' class='time_slot'>";
					$drop_down .= "<option value=''>" . $choose_time . '</option>';

					$timeslots_count = count( $time_drop_down_array );
					$time_selected   = $timeslots_count == 1 ? 'selected' : '';

					$time_drop_down_array = bkap_sort_time_in_chronological( $time_drop_down_array );
					$time_drop_down_array = apply_filters( 'bkap_time_slot_filter_after_chronological', $time_drop_down_array, $extra_information );

					foreach ( $time_drop_down_array as $k => $v ) {
						$time_key = apply_filters( 'bkap_timeslot_key_or_value', $v, $k );
						if ( $v != '' ) {
							$drop_down .= "<option value='" . $time_key . "' " . $time_selected . '>' . $v . '</option>';
						}
					}

					$drop_down .= '</select>';
				}

				$wp_send_json['bkap_time_count']    = $timeslots_count;
				$wp_send_json['bkap_time_dropdown'] = $drop_down;
				wp_send_json( $wp_send_json );
			} else {
				$wp_send_json['bkap_time_count']    = 0;
				$wp_send_json['bkap_time_dropdown'] = __( 'There are no timeslots available for the selected date.', 'woocommerce-booking' );
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * This function returns the time slots available for booking
		 * for a Date & Time bookable product as a string.
		 *
		 * @param string         $current_date - Date for which booking is being placed.
		 * @param string|integer $post_id - Product ID
		 * @return string $drop_down - Time Slots string where | is the separator
		 *
		 * @since 2.0
		 */

		public static function get_time_slot( $current_date, $post_id ) {
			global $wpdb;

			$global_settings      = bkap_global_setting();
			$booking_settings     = bkap_setting( $post_id );
			$advance_booking_hrs  = bkap_advance_booking_hrs( $booking_settings, $post_id );
			$time_format_db_value = 'H:i';
			$time_format_to_show  = bkap_common::bkap_get_time_format( $global_settings );
			$current_time         = current_time( 'timestamp' );
			$jnyDate              = date( 'j-n-Y', $current_time );
			$today                = date( 'Y-m-d H:i', $current_time );
			$date1                = new DateTime( $today );
			$date_to_check        = date( 'Y-m-d', strtotime( $current_date ) );
			$day_check            = bkap_weekday_string( $current_date );
			$phpversion           = version_compare( phpversion(), '5.3', '>' );
			$abpcheck             = ( $advance_booking_hrs > 0 ) ? true : false;
			$from_time_db_value   = '';
			$from_time_show       = '';
			$timeslots_str        = '';
			$dropdownarray        = array();
			$time_slot            = '';

			$product      = wc_get_product( $post_id );
			$product_type = $product->get_type();

			if ( $product->has_child() ) { // Grouped products compatibility
				$has_children = 'yes';
				$child_ids    = $product->get_children();
			}

			// check if there's a record available for the given date and time with availability > 0.
			$check_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
            				WHERE start_date= '" . $date_to_check . "'
            				AND post_id = '" . $post_id . "'
            				AND status = ''
            				AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
			$results_check = $wpdb->get_results( $check_query );

			$extra_information = array(
				'ymddate'     => $date_to_check,
				'time_format' => $time_format_to_show,
				'abpcheck'    => $abpcheck,
				'abpvalue'    => $advance_booking_hrs,
			);

			// If date record found then it will go in if condition..
			if ( count( $results_check ) > 0 ) {

				$specific = false; // assume its a recurring weekday record
				foreach ( $results_check as $key => $value ) {
					if ( $value->weekday == '' ) {
						$specific = true;

						if ( $value->from_time != '' ) {
							$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
							$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
						}

						$to_time_show = $value->to_time;

						$booking_time = $current_date . $from_time_db_value;
						$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $current_date, $from_time_db_value, $to_time_show, $post_id, $booking_settings );
						$date2        = new DateTime( $booking_time );
						$include      = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

						if ( $include ) {

							if ( $to_time_show != '' ) {
								$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
								$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );
								$timeslots_str   .= $from_time_show . ' - ' . $to_time_show . '|';
								$dropdownarray[ $from_time_show . ' - ' . $to_time_show ] = $value->available_booking;
							} else {
								$timeslots_str                   .= $from_time_show . '|';
								$dropdownarray[ $from_time_show ] = $value->available_booking;
							}
						}
					}
				}

				if ( ! $specific ) { // if no records found based on specific date then it will go in this if..

					foreach ( $results_check as $key => $value ) {

						if ( $value->from_time != '' ) {
							$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
							$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
						}

						$to_time_show = $value->to_time;
						$booking_time = $current_date . $from_time_db_value;
						$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $current_date, $from_time_db_value, $to_time_show, $post_id, $booking_settings );
						$date2        = new DateTime( $booking_time );
						$include      = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

						if ( $include ) {
							// $to_time_show = $value->to_time;
							if ( $to_time_show != '' ) {
								$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
								$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );
								$timeslots_str   .= $from_time_show . ' - ' . $to_time_show . '|';
								$dropdownarray[ $from_time_show . ' - ' . $to_time_show ] = $value->available_booking;
							} else {

								if ( $value->from_time != '' ) {
									$timeslots_str                   .= $from_time_show . '|';
									$dropdownarray[ $from_time_show ] = $value->available_booking;
								}
							}
						}
					}

					/**
					 * Get all the records using the base record to ensure we include any time slots
					 * that might hv been added after the original date record was created
					 * This can happen only for recurring weekdays
					 */
					$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
										 WHERE weekday= '" . $day_check . "'
										 AND post_id= '" . $post_id . "'
										 AND start_date='0000-00-00'
										 AND status = ''
										 AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
					$results_day_check = $wpdb->get_results( $check_day_query );

					// remove duplicate time slots that have available booking set to 0
					foreach ( $results_day_check as $k => $v ) {

						$from_hi = date( 'H:i', strtotime( $v->from_time ) );
						$to_hi   = ( $v->to_time != '' ) ? date( 'H:i', strtotime( $v->to_time ) ) : '';

						$time_check_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
											WHERE start_date= '" . $date_to_check . "'
											AND post_id= '" . $post_id . "'
											AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "'
											AND TIME_FORMAT( to_time, '%H:%i' )= '" . $to_hi . "'
											AND status = '' ORDER BY STR_TO_DATE(from_time,'%H:%i')";
						$results_time_check = $wpdb->get_results( $time_check_query );

						if ( count( $results_time_check ) > 0 ) {
							unset( $results_day_check[ $k ] );
						} else {

							$time_check_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
												WHERE start_date= '" . $date_to_check . "'
												AND post_id= '" . $post_id . "'
												AND TIME_FORMAT( from_time, '%H:%i' ) = '" . $from_hi . "'
												AND to_time = '" . $to_hi . "'
												AND status = '' ORDER BY STR_TO_DATE(from_time,'%H:%i')";
							$results_time_check = $wpdb->get_results( $time_check_query );

							if ( count( $results_time_check ) > 0 ) {
								unset( $results_day_check[ $k ] );
							}
						}
					}

					// remove duplicate time slots that have available booking > 0
					foreach ( $results_day_check as $k => $v ) {

						foreach ( $results_check as $key => $value ) {

							if ( $v->from_time != '' && $v->to_time != '' ) {
								$from_time_chk = date( $time_format_db_value, strtotime( $v->from_time ) );
								if ( $value->from_time == $from_time_chk ) {

									if ( $v->to_time != '' ) {
										$to_time_chk = date( $time_format_db_value, strtotime( $v->to_time ) );
									}

									if ( $value->to_time == $to_time_chk ) {
										unset( $results_day_check[ $k ] );
									}
								}
							} else {
								if ( $v->from_time == $value->from_time ) {
									if ( $v->to_time == $value->to_time ) {
										unset( $results_day_check[ $k ] );
									}
								}
							}
						}
					}

					foreach ( $results_day_check as $key => $value ) {

						if ( $value->from_time != '' ) {
							$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
							$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
						}

						$to_time_show = $value->to_time;
						$booking_time = $current_date . $from_time_db_value;
						$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $current_date, $from_time_db_value, $to_time_show, $post_id, $booking_settings );
						$date2        = new DateTime( $booking_time );
						$include      = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

						if ( $to_time_show != '' ) {
							$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
							$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );

							if ( $include ) {
								$timeslots_str .= $from_time_show . ' - ' . $to_time_show . '|';
								$dropdownarray[ $from_time_show . ' - ' . $to_time_show ] = $value->available_booking;
							}
						} else {
							if ( $value->from_time != '' && $include ) {
								$timeslots_str                   .= $from_time_show . '|';
								$dropdownarray[ $from_time_show ] = $value->available_booking;
							}

							$to_time_db_value = '';
						}

						bkap_insert_record_booking_history( $post_id, $day_check, $date_to_check, '0000-00-00', $from_time_db_value, $to_time_db_value, $value->total_booking, $value->available_booking );

						// Grouped products compatibility
						if ( $product_type == 'grouped' ) {
							if ( $has_children == 'yes' ) {
								foreach ( $child_ids as $k => $v ) {
									$check_day_query_child   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    															WHERE weekday= '" . $day_check . "'
    															AND post_id= '" . $v . "'
    															AND start_date='0000-00-00'
    															AND status = ''
    															AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
									$results_day_check_child = $wpdb->get_results( $check_day_query_child );

									bkap_insert_record_booking_history( $v, $day_check, $date_to_check, '0000-00-00', $from_time_db_value, $to_time_db_value, $results_day_check_child[0]->total_booking, $results_day_check_child[0]->available_booking );
								}
							}
						}
					}
				}
			} else {

				// If no date records found then it will come here..

				// Getting all the base records with availability more than 0
				$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    								 WHERE weekday= '" . $day_check . "'
    								 AND post_id= '" . $post_id . "'
    								 AND start_date='0000-00-00'
    								 AND status = ''
    								 AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
				$results_day_check = $wpdb->get_results( $check_day_query );

				// No base record for availability > 0
				if ( ! $results_day_check ) {
					// check if there's a record for the date where unlimited bookings are allowed i.e. total and available = 0
					$check_query = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    								WHERE start_date= '" . $date_to_check . "'
    								AND post_id= '" . $post_id . "'
    								AND total_booking = 0
    								AND available_booking = 0
    								AND from_time != ''
    								AND status = '' ORDER BY STR_TO_DATE(from_time,'%H:%i')
    								";

					$results_check = $wpdb->get_results( $check_query );

					// if record found, then create the dropdown
					if ( isset( $results_check ) && count( $results_check ) > 0 ) {

						foreach ( $results_check as $key => $value ) {

							if ( $value->from_time != '' ) {
								$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
								$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
							} else {
								$from_time_show = $from_time_db_value = '';
							}

							$to_time_show = $value->to_time;
							$booking_time = $current_date . $from_time_db_value;
							$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $current_date, $from_time_db_value, $to_time_show, $post_id, $booking_settings );
							$date2        = new DateTime( $booking_time );
							$include      = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

							if ( $include ) {

								if ( $to_time_show != '' ) {
									$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
									$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );
									$timeslots_str   .= $from_time_show . ' - ' . $to_time_show . '|';
									$dropdownarray[ $from_time_show . ' - ' . $to_time_show ] = $value->available_booking;
								} else {
									$timeslots_str                   .= $from_time_show . '|';
									$dropdownarray[ $from_time_show ] = $value->available_booking;
									$to_time_show                     = $to_time_db_value = '';
								}
							}
						}
					} else {
						// else check if there's a base record with unlimited bookings i.e. total and available = 0
						$check_day_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    										WHERE weekday= '" . $day_check . "'
    										AND post_id= '" . $post_id . "'
    										AND start_date='0000-00-00'
    										AND status = ''
    										AND total_booking = 0
    										AND available_booking = 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
						$results_day_check = $wpdb->get_results( $check_day_query );
					}
				}

				if ( $results_day_check ) {

					$check_query   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
    								WHERE start_date= '" . $date_to_check . "'
    								AND post_id= '" . $post_id . "'
    								AND total_booking > 0
    								AND available_booking <= 0
    								AND status = '' ORDER BY STR_TO_DATE(from_time,'%H:%i')";
					$results_check = $wpdb->get_results( $check_query );

					if ( count( $results_check ) == count( $results_day_check ) ) {
						$timeslots_str          = 'ERROR | ' . __( get_option( 'book_real-time-error-msg' ), 'woocommerce-booking' );
						$dropdownarray['ERROR'] = __( get_option( 'book_real-time-error-msg' ), 'woocommerce-booking' );
						return apply_filters( 'bkap_edit_display_timeslots', $timeslots_str, $post_id, $booking_settings, $global_settings, $extra_information );
					} else {

						foreach ( $results_day_check as $key => $value ) {

							if ( $value->from_time != '' ) {
								$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
								$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
							} else {
								$from_time_show = $from_time_db_value = '';
							}

							$to_time_show = $value->to_time;

							$booking_time = $current_date . $from_time_db_value;
							$booking_time = apply_filters( 'bkap_modify_from_time_for_abp', $booking_time, $current_date, $from_time_db_value, $to_time_show, $post_id, $booking_settings );
							$date2        = new DateTime( $booking_time );
							$include      = bkap_dates_compare( $date1, $date2, $advance_booking_hrs, $phpversion );

							if ( $to_time_show != '' ) {
								$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
								$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );

								if ( $include ) {
									$timeslots_str .= $from_time_show . ' - ' . $to_time_show . '|';
									$dropdownarray[ $from_time_show . ' - ' . $to_time_show ] = $value->available_booking;
								}
							} else {
								if ( $include ) {
									$timeslots_str                   .= $from_time_show . '|';
									$dropdownarray[ $from_time_show ] = $value->available_booking;
								}

								$to_time_show = $to_time_db_value = '';
							}

							bkap_insert_record_booking_history( $post_id, $day_check, $date_to_check, '0000-00-00', $from_time_db_value, $to_time_db_value, $value->total_booking, $value->available_booking );

							// Grouped products compatibility
							if ( $product_type == 'grouped' ) {

								if ( isset( $has_children ) && $has_children == 'yes' ) {

									foreach ( $child_ids as $k => $v ) {
										$check_day_query_child   = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
        															WHERE weekday= '" . $day_check . "'
        															AND post_id= '" . $v . "'
        															AND start_date='0000-00-00'
        															AND status = ''
        															AND available_booking > 0 ORDER BY STR_TO_DATE(from_time,'%H:%i')";
										$results_day_check_child = $wpdb->get_results( $check_day_query_child );

										if ( isset( $results_day_check_child ) && count( $results_day_check_child ) > 0 ) {

											bkap_insert_record_booking_history( $v, $day_check, $date_to_check, '0000-00-00', $from_time_db_value, $to_time_db_value, $results_day_check_child[0]->total_booking, $results_day_check_child[0]->available_booking );
										}
									}
								}
							}
						}
					}
				}
			}

			// Add any unlimited booking slots that might be present for the date/day.
			$unlimited_drop_down = self::bkap_add_unlimited_slots( $timeslots_str, $post_id, $booking_settings, $global_settings, $extra_information );
			if ( $timeslots_str !== $unlimited_drop_down ) {
				$timeslots_str      = $unlimited_drop_down;
				$u_dropdown_explode = explode( '|', $unlimited_drop_down );
				foreach ( $u_dropdown_explode as $ukey => $uvalue ) {
					if ( '' !== $uvalue ) {
						$dropdownarray_keys = array_keys( $dropdownarray );
						if ( ! in_array( $uvalue, $dropdownarray_keys ) ) {
							$dropdownarray[ $uvalue ] = 'unlimited';
						}
					}
				}
			}

			// before returning check if any of the slots are to be blocked at the variation level.
			if ( isset( $_POST['variation_id'] ) && $_POST['variation_id'] != '' ) {

				if ( isset( $_POST['variation_timeslot_lockout'] ) && $_POST['variation_timeslot_lockout'] != '' ) {

					$dates_array = explode( ',', $_POST['variation_timeslot_lockout'] );

					foreach ( $dates_array as $date_key => $date_value ) {

						$list_dates = explode( '=>', $date_value );

						if ( stripslashes( $list_dates[0] ) == $_POST['current_date'] ) {

							// convert the time slot in the format in which it is being displayed
							$time_slot_explode = explode( '-', $list_dates[1] );
							$time_slot         = date( $time_format_to_show, strtotime( $time_slot_explode[0] ) );
							$time_slot        .= ' - ' . date( $time_format_to_show, strtotime( $time_slot_explode[1] ) );

							if ( strpos( $timeslots_str, $time_slot ) >= 0 ) {
								$pattern_to_be_removed = $time_slot . '|';
								$timeslots_str         = str_replace( $pattern_to_be_removed, '', $timeslots_str );
							}
						}
					}
				}

				if ( isset( $_POST['attribute_timeslot_lockout'] ) && $_POST['attribute_timeslot_lockout'] != '' ) {
					// before returning , also check if any slots needs to be blocked at the attribute level
					$attributes = get_post_meta( $post_id, '_product_attributes', true );

					if ( is_array( $attributes ) && count( $attributes ) > 0 ) {

						foreach ( $attributes as $attr_name => $attr_value ) {

							$attr_post_name = 'attribute_' . $attr_name;
							// check the attribute value
							if ( isset( $_POST[ $attr_post_name ] ) && $_POST[ $attr_post_name ] > 0 ) {
								// check if any dates/time slots are set to be locked out
								if ( isset( $_POST['attribute_timeslot_lockout'] ) && $_POST['attribute_timeslot_lockout'] != '' ) {

									$attribute_explode = explode( ';', $_POST['attribute_timeslot_lockout'] );

									foreach ( $attribute_explode as $attribute_name => $attribute_fields ) {

										$dates_array = explode( ',', $attribute_fields );

										foreach ( $dates_array as $date_key => $date_value ) {

											if ( $date_value != $attr_name ) {
												$list_dates = explode( '=>', $date_value );

												if ( stripslashes( $list_dates[0] ) == $_POST['current_date'] ) {

													// convert the time slot in the format in which it is being displayed
													$time_slot_explode = explode( '-', $list_dates[1] );
													$time_slot         = date( $time_format_to_show, strtotime( $time_slot_explode[0] ) );
													$time_slot        .= ' - ' . date( $time_format_to_show, strtotime( $time_slot_explode[1] ) );
													if ( strpos( $timeslots_str, $time_slot ) >= 0 ) {
														$pattern_to_be_removed = $time_slot . '|';
														$timeslots_str         = str_replace( $pattern_to_be_removed, '', $timeslots_str );
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

			if ( isset( $booking_settings['bkap_manage_time_availability'] ) && ! empty( $booking_settings['bkap_manage_time_availability'] ) ) {
				$mta_availability_data = $booking_settings['bkap_manage_time_availability'];

				if ( is_array( $mta_availability_data ) && count( $mta_availability_data ) > 0 ) {
					$timeslots_str = bkap_filter_time_based_on_resource_availability( $current_date, $mta_availability_data, $timeslots_str, array( 'type' => 'fixed_time' ), 0, $post_id, $booking_settings );
					if ( '' !== $timeslots_str ) {
						$mta_dropdownarray     = array();
						$mta_drop_down_explode = explode( '|', $timeslots_str );
						foreach ( $mta_drop_down_explode as $mta_key => $mta_value ) {
							if ( '' !== $mta_value ) {
								$mta_dropdownarray[ $mta_value ] = $dropdownarray[ $mta_value ];
							}
						}
						$dropdownarray = $mta_dropdownarray;
					} else {
						$dropdownarray = array();
					}
				}
			}

			$resource_id                     = ( isset( $_POST['resource_id'] ) ) ? $_POST['resource_id'] : '';
			$resource_lockoutdates           = ( isset( $_POST['resource_lockoutdates'] ) ) ? bkap_common::frontend_json_decode( $_POST['resource_lockoutdates'] ) : '';
			$check_for_overlapping_timeslots = true;

			if ( '' !== $resource_id ) {

				$resource_ids   = explode( ',', $resource_id );
				$availability   = array();
				$locked_dates   = array();
				$time_slots     = array();
				$_timeslots_str = $timeslots_str;
				$_time_slot     = $time_slot;

				foreach ( $resource_ids as $id ) {

					$resource                   = new BKAP_Product_Resource( $id, $post_id );
					$r_availability             = (int) bkap_resource_max_booking( $id, $date_to_check, $post_id, $booking_settings );
					$resource_availability_data = $resource->get_resource_availability();

					if ( is_array( $resource_availability_data ) && count( $resource_availability_data ) > 0 ) {
						$_timeslots_str = bkap_filter_time_based_on_resource_availability( $current_date, $resource_availability_data, $_timeslots_str, array( 'type' => 'fixed_time' ), $id, $post_id, $booking_settings );
					}

					$time_slots[ $id ] = array_filter( explode( '|', $_timeslots_str ) );

					if ( isset( $resource_lockoutdates[ $id ] ) ) {
						$locked_dates[ $id ] = array_filter( explode( ',', $resource_lockoutdates[ $id ] ) );
					}

					// Check if the resource is present in the cart.
					if ( isset( WC()->cart ) ) {

						$trc_qty = 0;

						foreach ( WC()->cart->get_cart() as $cart_item_key => $values ) {

							if ( isset( $values['bkap_booking'] ) ) {
								$cart_booking = $values['bkap_booking'][0];

								if ( isset( $cart_booking['resource_id'] ) && $cart_booking['resource_id'] === $id ) {

									if ( isset( $cart_booking['time_slot'] ) && '' !== $cart_booking['time_slot'] ) {
										$_time_slot = $cart_booking['time_slot'];
									}

									if ( isset( $cart_booking['hidden_date_checkout'] ) && $cart_booking['hidden_date_checkout'] != '' ) {
										if ( strtotime( $current_date ) >= strtotime( $cart_booking['hidden_date'] ) && strtotime( $hidden_date ) < strtotime( $cart_booking['hidden_date_checkout'] ) ) {
											$trc_qty += $values['quantity'];
										}
									} else {
										if ( strtotime( $current_date ) == strtotime( $cart_booking['hidden_date'] ) ) {
											$trc_qty += $values['quantity'];
										}
									}
								}
							}

							if ( 0 === $r_availability ) {
								// Unlimited Booking.
								$r_availability = 'U';
							} else {
								if ( $trc_qty >= $r_availability ) {

									// "18-3-2020"=>07:00 - 17:00
									$timeslotexplode       = explode( ' - ', $_time_slot );
									$_time_slot            = date( 'H:i', strtotime( $timeslotexplode[0] ) ) . ' - ' . date( 'H:i', strtotime( $timeslotexplode[1] ) );
									$locked_dates[ $id ][] = '\"' . $current_date . '\"=>' . $_time_slot;
									$time_slots[ $id ][]   = $_time_slot;
								}

								$locked_dates[ $id ] = array_filter( array_unique( $locked_dates[ $id ] ) );
								$time_slots[ $id ]   = array_filter( array_unique( $time_slots[ $id ] ) );
							}

							$availability[ $id ] = $r_availability;
						}
					}
				}

				if ( count( $locked_dates ) > 0 && count( $time_slots ) > 0 ) {

					foreach ( $resource_ids as $id ) {

						if ( isset( $locked_dates[ $id ] ) && is_array( $locked_dates[ $id ] ) && count( $locked_dates[ $id ] ) > 0 ) {

							foreach ( $locked_dates[ $id ] as $dates ) {

								$_date    = explode( '=>', $dates );
								$_date[0] = substr( stripslashes( $_date[0] ), 1, -1 );

								if ( $_date[0] === $_POST['current_date'] ) {

									// convert the time slot in the format in which it is being displayed
									$time_slot_explode = explode( ' - ', $_date[1] );
									$lf                = strtotime( $time_slot_explode[0] );
									$lt                = strtotime( $time_slot_explode[1] );

									$_time_slot  = date( $time_format_to_show, $lf );
									$_time_slot .= ' - ' . date( $time_format_to_show, $lt );

									/*
									foreach ( array_keys( $time_slots[ $id ], $_time_slot, true ) as $key ) {
									unset( $time_slots[ $id ][ $key ] );
									}
									*/

									foreach ( $time_slots[ $id ] as $key => $_timeslot ) {

										$remove_item = ( $_timeslot === $_time_slot );

										$t_s_e = explode( ' - ', $_timeslot );
										$f     = isset( $t_s_e[0] ) ? strtotime( $t_s_e[0] ) : 0;
										$t     = isset( $t_s_e[1] ) ? strtotime( $t_s_e[1] ) : 0;

										$remove_item = ( ( $f > $lf && $f < $lt ) || ( $t > $lf && $t < $lt ) || ( $f <= $lf && $t >= $lt ) ) ? true : $remove_item;

										if ( $remove_item ) {
											unset( $time_slots[ $id ][ $key ] );
										}
									}
								}
							}
						}
					}

					// Get timeslots that are available in all resources available.
					$uniform_time_slots   = bkap_common::return_unique_array_values( $time_slots, count( $resource_ids ) );
					$uniform_availability = bkap_common::return_unique_array_values( $availability, count( $resource_ids ) );

					if ( 0 === count( $uniform_time_slots ) ) {
						$_error_message         = ( 1 === count( $resource_ids ) ? __( 'The selected resource is not available for the selected date', 'woocommerce-booking' ) : __( 'One or more of the resources selected are not available for the selected date.', 'woocommerce-booking' ) );
						$timeslots_str          = 'ERROR | ' . $_error_message;
						$dropdownarray['ERROR'] = $_error_message;
					}

					if ( count( $uniform_time_slots ) > 0 || ! in_array( 'U', $uniform_availability ) ) {

						if ( count( $uniform_time_slots ) > 0 ) {
							$timeslots_str = implode( '|', $uniform_time_slots ) . '|';
						}

						$check_for_overlapping_timeslots = false;
					}
				}
			}

			if ( $check_for_overlapping_timeslots ) {
				// Normal check for overlapping

				if ( isset( WC()->cart ) && count( WC()->cart->cart_contents ) > 0 ) {
					$cart        = WC()->cart;
					$overlapping = bkap_booking_overlapping_timeslot( $global_settings, $post_id ); // Overlapping timeslots option

					foreach ( $cart->get_cart() as $cart_item_key => $values ) {

						if ( isset( $values['bkap_booking'] ) ) {
							$cart_booking = $values['bkap_booking'][0];

							if ( $values['product_id'] === $post_id && strtotime( $current_date ) === strtotime( $cart_booking['hidden_date'] ) ) {

								if ( isset( $cart_booking['time_slot'] ) && '' !== $cart_booking['time_slot'] ) {

									$time_slot         = $cart_booking['time_slot'];
									$time_slot_explode = explode( ' - ', $time_slot );
									$c_from            = strtotime( $time_slot_explode[0] );
									$c_to              = isset( $time_slot_explode[1] ) ? strtotime( $time_slot_explode[1] ) : '';

									foreach ( $dropdownarray as $d_key => $d_value ) {

										if ( $cart_booking['time_slot'] == $d_key ) {
											$dropdownarray[ $d_key ] = ( (int) $d_value ) - ( (int) $values['quantity'] );
											if ( 0 === $dropdownarray[ $d_key ] ) { // Unlimited bookings would have negative values, so this condition would not affect them.
												unset( $dropdownarray[ $d_key ] );
											}
										} else {
											// overlapping check.
											if ( $overlapping && '' != $c_to ) {
												$d_explode = explode( ' - ', $d_key );
												$d_from    = strtotime( $d_explode[0] );
												$d_to      = isset( $d_explode[1] ) ? strtotime( $d_explode[1] ) : '';
												if ( '' != $d_to ) {
													if ( ( $d_from > $c_from && $d_from < $c_to ) || ( $d_to > $c_from && $d_to < $c_to ) || ( $d_from <= $c_from && $d_to >= $c_to ) ) {
														$dropdownarray[ $d_key ] = $d_value - $values['quantity'];
														if ( 0 === $dropdownarray[ $d_key ] ) { // Unlimited bookings would have negative values, so this condition would not affect them.
															unset( $dropdownarray[ $d_key ] );
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

					$timeslots_str = ( count( $dropdownarray ) > 0 ) ? implode( '|', array_keys( $dropdownarray ) ) . '|' : '';
				}
			}

			return apply_filters( 'bkap_edit_display_timeslots', $timeslots_str, $post_id, $booking_settings, $global_settings, $extra_information );

		}

		/**
		 * Pre select the fixed block
		 *
		 * This function is called only when fixed blocks are enabled and
		 * a search is performed in the search widget. It pre populates the correct block
		 * based on the date selected.
		 *
		 * @param string|integer $duplicate_of - Product ID
		 *
		 * @since 2.9
		 */

		public static function bkap_prepopulate_fixed_block( $duplicate_of ) {

			$session_start_date = bkap_common::bkap_date_from_session_cookie( 'start_date' );
			$session_end_date   = bkap_common::bkap_date_from_session_cookie( 'end_date' );

			if ( $session_start_date ) {

				$date2               = new DateTime( $session_end_date );
				$date1               = new DateTime( $session_start_date );
				$diff_dates_selected = bkap_common::dateTimeDiff( $date2, $date1 );

				$diff_dates = $diff_dates_selected->days;
				$day_chosen = date( 'N', strtotime( $session_start_date ) );

				$block_value  = '';
				$fixed_blocks = bkap_block_booking::bkap_get_fixed_blocks( $duplicate_of );
				foreach ( $fixed_blocks as $key => $value ) {
					if ( is_numeric( $value['start_day'] ) ) {
						if ( $day_chosen == $value['start_day'] ) {
							$block_value     = $value['start_day'] . '&' . $value['number_of_days'] . '&' . $value['price'];
							$block_start_day = $value['start_day'];
							$block_days      = $value['number_of_days'];
							$block_price     = $value['price'];
							break;
						}
					}
				}

				if ( $block_value == '' ) { // no exact match found
					foreach ( $fixed_blocks as $key => $value ) {
						if ( $value['number_of_days'] == $diff_dates && $value['start_day'] == 'any_days' ) {
							$block_value     = $value['start_day'] . '&' . $value['number_of_days'] . '&' . $value['price'];
							$block_start_day = $value['start_day'];
							$block_days      = $value['number_of_days'];
							$block_price     = $value['price'];
							break;
						}
					}
				}

				if ( $block_value == '' ) { // no match found for the number of days either
					foreach ( $fixed_blocks as $key => $value ) {
						if ( $value['start_day'] == 'any_days' ) {
							$block_value     = $value['start_day'] . '&' . $value['number_of_days'] . '&' . $value['price'];
							$block_start_day = $value['start_day'];
							$block_days      = $value['number_of_days'];
							$block_price     = $value['price'];
							break;
						}
					}
				}

				if ( '' != $block_value ) {
					?>
				<script type="text/javascript">
				jQuery("#block_option").val("<?php echo $block_value; ?>");
				jQuery("#block_option_start_day").val("<?php echo $block_start_day; ?>");
				jQuery("#block_option_number_of_day").val("<?php echo $block_days; ?>");
				jQuery("#block_option_price").val("<?php echo $block_price; ?>");
				</script>
					<?php
				}
			} //end session check

		}

		/**
		 * Return the first available date from the given product.
		 *
		 * This function returns the first available booking date
		 * for the given product
		 *
		 * @param int   $duplicate_of - product ID
		 * @param array $lockout_dates_array - array containing locked dates
		 * @param str   $date j-n-Y format
		 * @return str $date j-n-Y format
		 *
		 * @since 3.0
		 */

		public static function bkap_first_available( $duplicate_of, $lockout_dates_array, $date ) {

			$global_holidays        = array();
			$holiday_array          = array();
			$bkap_fixed_blocks_data = array();
			$global_settings        = bkap_global_setting();
			$booking_settings       = bkap_setting( $duplicate_of );
			$custom_holiday_ranges  = get_post_meta( $duplicate_of, '_bkap_holiday_ranges', true );
			$booking_type           = get_post_meta( $duplicate_of, '_bkap_booking_type', true );
			$bkap_fixed_blocks      = get_post_meta( $duplicate_of, '_bkap_fixed_blocks', true );

			if ( isset( $booking_settings['bkap_all_data_unavailable'] ) && 'on' === $booking_settings['bkap_all_data_unavailable'] ) {
				if ( isset( $booking_settings['bkap_manage_time_availability'] ) ) {
					// if no ranges added in manage availability table then return $date.
					if ( empty( $booking_settings['bkap_manage_time_availability'] ) ) {
						return $date;
					} else {
						// if range is added and all added ranges are non-bookable then return $date.
						$bookable_range_available = false;
						foreach ( $booking_settings['bkap_manage_time_availability'] as $key => $value ) {
							if ( $value['bookable'] == 1 ) {
								$bookable_range_available = true;
								break;
							}
						}
						if ( ! $bookable_range_available ) {
							return $date;
						}
					}
				}
			}

			if ( isset( $global_settings->booking_global_holidays ) ) {
				$global_holidays = explode( ',', $global_settings->booking_global_holidays );
			}

			$recurring_date    = isset( $booking_settings['booking_recurring'] ) ? $booking_settings['booking_recurring'] : array();
			$booking_dates_arr = isset( $booking_settings['booking_specific_date'] ) ? $booking_settings['booking_specific_date'] : array();

			if ( isset( $booking_settings['booking_product_holiday'] ) && is_array( $booking_settings['booking_product_holiday'] ) && count( $booking_settings['booking_product_holiday'] ) > 0 ) {
				$holiday_array = $booking_settings['booking_product_holiday'];
			}

			// Removing specific date if the date is set as holiday at product level
			if ( $booking_dates_arr != ''
			&& count( $booking_dates_arr ) > 0
			&& ( count( $holiday_array ) > 0 || count( $global_holidays ) > 0 )
			) {
				$booking_dates_arr = self::bkap_check_specificdate_in_holiday( $booking_dates_arr, $holiday_array, $global_holidays );
			}

			$date_updated = '';
			$min_day      = date( 'w', strtotime( $date ) );

			$numbers_of_days_to_choose = isset( $booking_settings['booking_maximum_number_days'] ) ? $booking_settings['booking_maximum_number_days'] - 1 : '';

			$current_time     = strtotime( 'today midnight' );
			$current_time_ymd = date( 'Y-m-d', $current_time );
			$max_booking_date = apply_filters( 'bkap_max_date', $current_time_ymd, $numbers_of_days_to_choose, $booking_settings );
			$date_diff        = strtotime( $max_booking_date ) - $current_time;
			$diff_days        = absint( floor( $date_diff / 86400 ) );

			if ( $bkap_fixed_blocks == 'booking_fixed_block_enable' ) {
				$bkap_fixed_blocks_data = bkap_block_booking::bkap_get_fixed_blocks( $duplicate_of );
			}

			for ( $i = 0; $i <= $diff_days; $i++ ) {

				$custom_holiday_present = false;
				if ( is_array( $custom_holiday_ranges ) && count( $custom_holiday_ranges ) > 0 ) {
					foreach ( $custom_holiday_ranges as $custom_key => $custom_value ) {
						if ( strtotime( $custom_value['start'] ) <= strtotime( $date ) &&
						 strtotime( $custom_value['end'] ) >= strtotime( $date ) ) {
							$custom_holiday_present = true;
							break;
						}
					}
				}

				$time_slots_missing = false;
				if ( $booking_type === 'date_time' && ! $custom_holiday_present ) {
					$time_slots_missing = true;

					$day_has_timeslot = bkap_common::bkap_check_timeslot_for_weekday( $duplicate_of, $date, $booking_settings );
					if ( $day_has_timeslot ) {
						$time_slots = explode( '|', self::get_time_slot( $date, $duplicate_of ) );
						if ( sanitize_key( $time_slots[0] ) !== '' && sanitize_key( $time_slots[0] ) !== 'error' ) {
							$time_slots_missing = false;
						}
					}
				}

				if ( isset( $booking_settings['booking_recurring_booking'] ) && 'on' == $booking_settings['booking_recurring_booking'] && $booking_type != 'multiple_days' ) {

					if ( isset( $recurring_date[ 'booking_weekday_' . $min_day ] ) && $recurring_date[ 'booking_weekday_' . $min_day ] == 'on' ) {

						if ( isset( $holiday_array[ $date ] )
						|| in_array( $date, $global_holidays )
						|| in_array( $date, $lockout_dates_array )
						|| $custom_holiday_present
						|| $time_slots_missing
						) {
							$date_updated = 'YES';
						} else {
							$date_updated = 'NO';
						}
					}
				} elseif ( is_array( $booking_dates_arr ) && count( $booking_dates_arr ) > 0 && $booking_type != 'multiple_days' ) {
					if ( isset( $holiday_array[ $date ] )
					|| in_array( $date, $global_holidays )
					|| in_array( $date, $lockout_dates_array )
					|| ! array_key_exists( $date, $booking_dates_arr )
					|| $custom_holiday_present
					|| $time_slots_missing
					) {
						$date_updated = 'YES';
					} else {
						$date_updated = 'NO';
					}
				} else {
					if ( $booking_type == 'multiple_days' && $bkap_fixed_blocks == 'booking_fixed_block_enable' ) {

						if ( count( $bkap_fixed_blocks_data ) > 0 ) {
							$min_day     = date( 'w', strtotime( $date ) );
							$first_block = self::bkap_first_available_date_fixed_block( $bkap_fixed_blocks_data, $min_day );

							if ( $first_block['start_day'] == 'any_days' || $min_day == $first_block['start_day'] ) {
								// $date             = '';
								$date_updated = 'NO';
							} else {
								$fix_min_day   = $first_block['start_day'];
								$fixed_min_day = date( 'w', strtotime( $date ) );

								while ( $fix_min_day != $fixed_min_day ) {
									$date                  = date( 'j-n-Y', strtotime( '+1day', strtotime( $date ) ) );
									$fixed_min_day         = date( 'w', strtotime( $date ) );
									$date_updated          = 'NO';
									$custom_holiday_ranges = false;
									if ( is_array( $custom_holiday_ranges ) && count( $custom_holiday_ranges ) > 0 ) {
										foreach ( $custom_holiday_ranges as $custom_key => $custom_value ) {
											if ( strtotime( $custom_value['start'] ) <= strtotime( $date ) &&
											 strtotime( $custom_value['end'] ) >= strtotime( $date ) ) {
												$custom_holiday_present = true;
												break;
											}
										}
									}
								}

								if ( isset( $holiday_array[ $date ] )
									|| in_array( $date, $global_holidays )
									|| in_array( $date, $lockout_dates_array )
									|| $custom_holiday_present
									|| $time_slots_missing
								) {
									$date_updated = 'YES';
								}
							}
						}
					} else {
						if ( isset( $recurring_date[ 'booking_weekday_' . $min_day ] ) && $recurring_date[ 'booking_weekday_' . $min_day ] == 'on' ) {
							if ( isset( $holiday_array[ $date ] )
							|| in_array( $date, $global_holidays )
							|| in_array( $date, $lockout_dates_array )
							|| $custom_holiday_present
							|| $time_slots_missing
							) {
								$date_updated = 'YES';
							} else {
								$date_updated = 'NO';
							}
						}
					}
				}

				$updated = false;
				if ( 'YES' == $date_updated || '' == $date_updated ) {
					$updated = true;
					$date    = date( 'j-n-Y', strtotime( '+1day', strtotime( $date ) ) );
					if ( $min_day < 6 ) {
						$min_day = $min_day + 1;
					} else {
						$min_day = $min_day - $min_day;
					}
				}

				if ( 'NO' == $date_updated ) {
					break;
				} else {
					$date_enabled = 'NO';
					if ( is_array( $recurring_date ) && count( $recurring_date ) > 0 ) {
						if ( isset( $recurring_date[ 'booking_weekday_' . $min_day ] ) && $recurring_date[ 'booking_weekday_' . $min_day ] == 'on' ) {
							$date_enabled = 'YES';
						} else {
							$date_enabled = 'NO';
						}
					}
					if ( is_array( $booking_dates_arr ) && count( $booking_dates_arr ) > 0 && 'NO' == $date_enabled ) {
						// @since 4.0.0 they are now saved as date (key) and lockout (value)
						if ( array_key_exists( $date, $booking_dates_arr ) && ! in_array( $date, $lockout_dates_array ) ) {
							$date_enabled = 'YES';
							break;
						} else {
							$date_enabled = 'NO';
						}
					}
					if ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' == $booking_settings['booking_enable_multiple_day'] ) {

						if ( isset( $recurring_date[ 'booking_weekday_' . $min_day ] ) && $recurring_date[ 'booking_weekday_' . $min_day ] == 'on' ) {
							if ( isset( $holiday_array[ $date ] )
								|| in_array( $date, $global_holidays )
								|| in_array( $date, $lockout_dates_array )
								|| ! array_key_exists( $date, $booking_dates_arr )
								|| $custom_holiday_present
								|| $time_slots_missing
							) {
								continue;
							} else {
								$date_enabled = 'YES';
								break;
							}
						} else {
							$date_enabled = 'NO';
						}
					}
				}

				if ( 'NO' == $date_enabled && ! $updated ) {
					$date         = date( 'j-n-Y', strtotime( '+1day', strtotime( $date ) ) );
					$date_updated = 'YES';

					if ( $min_day < 6 ) {
						$min_day = $min_day + 1;
					} else {
						$min_day = $min_day - $min_day;
					}
				}
			}
			return $date;
		}

		/**
		 * Return the specific date array excluding product holidays
		 *
		 * This function returns the specific date array which will not have any holiday dates
		 *
		 * @param array $bkap_fixed_blocks_data - array contains fixed blocks data
		 * @param int   $min_day - Number of dthe weekday
		 * @return array $bkap_fixed_blocks_data array contains fixed block based on first available date
		 *
		 * @since 4.1.3
		 */

		public static function bkap_first_available_date_fixed_block( $bkap_fixed_blocks_data, $min_day ) {
			$block_array = array();

			foreach ( $bkap_fixed_blocks_data as $key => $value ) {

				$f_key               = '';
				$block_array[ $key ] = $value['start_day'];

				if ( $value['start_day'] == $min_day || $value['start_day'] == 'any_days' ) {
					$f_key = $key;
					break;
				}
			}

			if ( $f_key == '' ) {
				return reset( $bkap_fixed_blocks_data );
			} else {
				return $bkap_fixed_blocks_data[ $f_key ];
			}
		}

		/**
		 * Return the specific date array excluding product holidays
		 *
		 * This function returns the specific date array which will not have any holiday dates
		 *
		 * @param array $specific_date - array containing specific dates
		 * @param array $holiday_array - array containing product level holidays
		 * @return array $specific_date
		 *
		 * @since 4.1.3
		 */

		public static function bkap_check_specificdate_in_holiday( $specific_date, $holiday_array, $global_holidays ) {

			foreach ( $specific_date as $specific_date_key => $specific_date_value ) {
				if ( isset( $holiday_array[ $specific_date_key ] ) || in_array( $specific_date_key, $global_holidays ) ) {
					unset( $specific_date[ $specific_date_key ] );
				}
			}

			return $specific_date;

		}

		/**
		 * Return the specific date array excluding global holidays
		 *
		 * This function returns the specific date array which will not have any holiday dates added at global level
		 *
		 * @param array $specific_date - array containing specific dates
		 * @param array $global_holidays - array containing global level holidays
		 * @return array $specific_date
		 *
		 * @since 4.1.3
		 */

		public static function bkap_check_specificdate_in_global_holiday( $specific_date, $global_holidays ) {

			foreach ( $specific_date as $specific_date_key => $specific_date_value ) {
				if ( in_array( $specific_date_key, $global_holidays ) ) {
					unset( $specific_date[ $specific_date_key ] );
				}
			}

			return $specific_date;

		}

		/**
		 * Return the first available date for first resource.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $default_date - Date in j-n-Y format
		 * @return string $default_date - Date in j-n-Y formt
		 *
		 * @since 4.8.0
		 */

		public static function bkap_first_available_resource_date( $product_id, $default_date ) {

			$resource_ids     = Class_Bkap_Product_Resource::bkap_get_product_resources( $product_id );
			$booking_settings = bkap_setting( $product_id );
			$default_dates    = array();

			if ( is_array( $resource_ids ) && count( $resource_ids ) > 0 ) {

				foreach ( $resource_ids as $key => $value ) {

					$date                              = $default_date;
					$booking_placed_for_first_resource = bkap_calculate_bookings_for_resource( $value, $product_id );

					if ( isset( $booking_settings['booking_enable_time'] ) && ( 'on' == $booking_settings['booking_enable_time'] || $booking_settings['booking_enable_time'] == 'duration_time' ) ) {
						$booking_placed_for_first_resource = apply_filters( 'bkap_locked_dates_for_dateandtime', $value, $product_id, $booking_settings, $booking_placed_for_first_resource );
					}

					while ( strpos( $booking_placed_for_first_resource['bkap_locked_dates'], $date ) !== false ) {
						$date = bkap_add_days_to_date( $date, 1 );
					}
					$default_dates[] = $date;
				}

				usort(
					$default_dates,
					function( $a, $b ) {
						$dateTimestamp1 = strtotime( $a );
						$dateTimestamp2 = strtotime( $b );

						return $dateTimestamp1 < $dateTimestamp2 ? -1 : 1;
					}
				);

				$default_date = $default_dates[0];
			}

			return $default_date;
		}

		/**
		 * Add any unlimited booking slots that might be present
		 * for the date/day to be displayed for the date/day
		 * along with any timeslots that might have a finite
		 * availability.
		 *
		 * @param str $dropdown - String containing timeslots with a finite availability
		 * @return str $display - String containing timeslots with a finite availability as well as those with unlimited availability.
		 *
		 * @hook bkap_edit_display_timeslots
		 * @since 4.4.0
		 */

		public static function bkap_add_unlimited_slots( $dropdown, $post_id, $booking_settings, $global_settings, $extra_information ) {

			if ( isset( $_POST['resource_id'] ) && '' !== $_POST['resource_id'] ) {
				return $dropdown;
			}

			$display    = $dropdown;
			$product_id = $post_id;
			$date_ymd   = $extra_information['ymddate'];
			$phpversion = version_compare( phpversion(), '5.3', '>' );

			if ( $product_id > 0 ) {

				$times_array = explode( '|', $display );
				array_pop( $times_array );

				$time_format_db_value = 'H:i';
				$time_format_to_show  = $extra_information['time_format'];
				$advance_booking_hrs  = $extra_information['abpvalue'];

				$current_time = current_time( 'timestamp' );
				$date_today   = date( 'Y-m-d H:i', $current_time );
				$today        = new DateTime( $date_today );

				global $wpdb;

				$query_unlimited = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
		                           WHERE post_id = %d
		                           AND start_date = %s
		                           AND total_booking = 0
		                           AND available_booking = 0
		                           AND status != 'inactive'
		                           ORDER BY STR_TO_DATE(from_time,'%H:%i')";
				$set_unlimited   = $wpdb->get_results( $wpdb->prepare( $query_unlimited, $product_id, $date_ymd ) );
				$weekday         = bkap_weekday_string( $date_ymd );

				// check for the base records
				$base_unlimited     = 'SELECT * FROM `' . $wpdb->prefix . "booking_history`
		                           WHERE post_id = %d
		                           AND weekday = %s
			                       AND start_date = '0000-00-00'
		                           AND total_booking = 0
		                           AND available_booking = 0
		                           AND status != 'inactive'
		                           ORDER BY STR_TO_DATE(from_time,'%H:%i')";
				$base_set_unlimited = $wpdb->get_results( $wpdb->prepare( $base_unlimited, $product_id, $weekday ) );

				$specific = false;
				if ( is_array( $set_unlimited ) && count( $set_unlimited ) > 0 ) {

					if ( isset( $times_array[0] ) && $times_array[0] == 'ERROR ' ) {
						$display = '';
					}

					// check if it's a specific date record, if yes.. then no need to check the base list
					foreach ( $set_unlimited as $value ) {
						if ( $value->weekday === '' ) {
							$specific = true;
						}

						if ( $value->from_time != '' ) {
							$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
							$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
						} else {
							$from_time_show = $from_time_db_value = '';
						}

						$booking_time = $date_ymd . $from_time_db_value;
						$booking_time = apply_filters( 'bkap_modify_from_time_for_abp', $booking_time, $date_ymd, $from_time_db_value, $value->to_time, $product_id, bkap_setting( $product_id ) );
						$date2        = new DateTime( $booking_time );
						$include      = bkap_dates_compare( $today, $date2, $advance_booking_hrs, $phpversion );

						$to_time_show = '';
						if ( $value->to_time !== '' ) {
							$to_time_show = date( $time_format_to_show, strtotime( $value->to_time ) );
						}

						if ( $to_time_show != '' ) {
							$bkap_time_slot = "$from_time_show - $to_time_show";
						} else {
							$bkap_time_slot = "$from_time_show";
						}

						if ( $include && ! in_array( trim( $bkap_time_slot ), $times_array ) ) {

							if ( $to_time_show != '' ) {
								$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
								$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );
								$display         .= $from_time_show . ' - ' . $to_time_show . '|';
								$times_array[]    = "$from_time_show - $to_time_show";
							} else {
								$display      .= $from_time_show . '|';
								$to_time_show  = $to_time_db_value = '';
								$times_array[] = $from_time_show;
							}
						}
					}
				}

				if ( ! $specific ) {  // check the recurring base records if specific is false

					if ( is_array( $base_set_unlimited ) && count( $base_set_unlimited ) > 0 ) {
						// check if it's a specific date record, if yes.. then no need to check the base list
						foreach ( $base_set_unlimited as $value ) {

							if ( $value->from_time != '' ) {
								$from_time_show     = date( $time_format_to_show, strtotime( $value->from_time ) );
								$from_time_db_value = date( $time_format_db_value, strtotime( $value->from_time ) );
							} else {
								$from_time_show = $from_time_db_value = '';
							}

							$booking_time = $date_ymd . $from_time_db_value;
							$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $booking_time, $date_ymd, $from_time_db_value, $value->to_time, $product_id, bkap_setting( $product_id ) );
							$date2        = new DateTime( $booking_time );
							$include      = bkap_dates_compare( $today, $date2, $advance_booking_hrs, $phpversion );

							$to_time_show = '';
							if ( $value->to_time !== '' ) {
								$to_time_show = date( $time_format_to_show, strtotime( $value->to_time ) );
							}

							if ( $to_time_show != '' ) {
								$bkap_time_slot = "$from_time_show - $to_time_show";
							} else {
								$bkap_time_slot = "$from_time_show";
							}

							if ( $include && ! in_array( trim( $bkap_time_slot ), $times_array ) ) {

								if ( $to_time_show != '' ) {
									$to_time_show     = date( $time_format_to_show, strtotime( $value->to_time ) );
									$to_time_db_value = date( $time_format_db_value, strtotime( $value->to_time ) );
									$display         .= $from_time_show . ' - ' . $to_time_show . '|';
									$times_array[]    = "$from_time_show - $to_time_show";
								} else {
									$display      .= $from_time_show . '|';
									$to_time_show  = $to_time_db_value = '';
									$times_array[] = $from_time_show;
								}

								bkap_insert_record_booking_history( $product_id, $weekday, $date_ymd, '0000-00-00', $from_time_db_value, $to_time_db_value, $value->total_booking, $value->available_booking );
							}
						}
					}
				}
			}
			return $display;
		}

		/**
		 * If the time slot is locked out, it still needs to be
		 * displayed if the booking is being edited.
		 * So add the time slot to the dropdown list for
		 * Edit Booking Post page.
		 *
		 * @param string $dropdown Timeslots dropdown data
		 * @return string Timeslot dropdown data modified
		 * @since 4.3.0
		 */

		function add_time_slot( $dropdown ) {

			$display    = $dropdown;
			$booking_id = isset( $_REQUEST['booking_post_id'] ) ? $_REQUEST['booking_post_id'] : 0;

			if ( $booking_id > 0 && get_post_type( $booking_id ) === 'bkap_booking' ) {
				$display = self::bkap_add_time_slot_on_bookingpage_or_vieworder( $display, $_REQUEST['post_id'], $booking_id, $_REQUEST['current_date'] );
			} elseif ( isset( $_POST['bkap_page'] ) && 'view-order' == $_POST['bkap_page'] ) {

				if ( isset( $_POST['view_item_id'] ) && '' != $_POST['view_item_id'] ) {
					$booking_id = bkap_common::get_booking_id( $_POST['view_item_id'] );

					if ( is_array( $booking_id ) ) {

						foreach ( $booking_id as $key => $id ) {
							$display = self::bkap_add_time_slot_on_bookingpage_or_vieworder( $display, $_POST['post_id'], $id, $_POST['current_date'] );
						}
					} else {
						$display = self::bkap_add_time_slot_on_bookingpage_or_vieworder( $display, $_POST['post_id'], $booking_id, $_POST['current_date'] );
					}
				}
			}

			return $display;
		}

		public static function bkap_add_time_slot_on_bookingpage_or_vieworder( $display, $product_id, $booking_id, $current_date ) {

			$booking         = new BKAP_Booking( $booking_id );
			$times_selected  = explode( '-', $booking->get_time() );
			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$time_format     = $global_settings->booking_time_format;
			$time_format     = ( $time_format === '12' ) ? 'h:i A' : 'H:i';
			$time_display    = date( $time_format, strtotime( trim( $times_selected[0] ) ) );

			if ( isset( $times_selected[1] ) && '23:59' !== trim( $times_selected[1] ) ) {
				$time_display .= ' - ' . date( $time_format, strtotime( trim( $times_selected[1] ) ) );
			}

			$time_drop_down_array = explode( '|', $display );

			if ( ! in_array( $time_display, $time_drop_down_array ) ) {

				// check if any error messages are there
				if ( trim( $time_drop_down_array[0] ) === 'ERROR' ) {
					$display = '';
				}

				// check if the time slot is actually present for that day or no
				// this should be done only if the date in the datepicker is not the same as the one for which the booking was placed
				if ( $current_date !== date( 'j-n-Y', strtotime( $booking->get_start() ) ) ) {

					$found         = false;
					$booking_date  = date( 'j-n-Y', strtotime( $current_date ) );
					$booking_times = get_post_meta( $product_id, '_bkap_time_settings', true );

					if ( is_array( $booking_times ) && count( $booking_times ) > 0 && array_key_exists( $booking_date, $booking_times ) ) {
						$slots_list = $booking_times[ $booking_date ];
					} else {
						// check for the weekday
						$weekday         = date( 'w', strtotime( $booking_date ) );
						$booking_weekday = "booking_weekday_$weekday";

						if ( is_array( $booking_times ) && count( $booking_times ) > 0 && array_key_exists( $booking_weekday, $booking_times ) ) {
							$slots_list = $booking_times[ $booking_weekday ];
						}
					}

					if ( is_array( $slots_list ) && count( $slots_list ) > 0 ) {

						foreach ( $slots_list as $times ) {

							$from_time_check = date( $time_format, strtotime( $times['from_slot_hrs'] . ':' . $times['from_slot_min'] ) );
							$to_time_check   = date( $time_format, strtotime( $times['to_slot_hrs'] . ':' . $times['to_slot_min'] ) );

							if ( $to_time_check !== '' && $to_time_check !== '00:00' && $to_time_check !== '12:00 AM' ) {
								$time_check = "$from_time_check - $to_time_check";
							} else {
								$time_check = "$from_time_check";
							}

							if ( $time_check === $time_display ) {
								$found = true;
								break;
							}
						}
					}
				} else {
					$found = true;
				}

				if ( $found ) {
					$display .= $time_display . '|';
				}
			}

			return $display;
		}

		/**
		 * This function is for dequeuing the scripts added by custom theme and plugin on single  product page.
		 *
		 * @since 4.19.0
		 */
		public static function bkap_wp_enqueue_scripts() {
			if ( ! is_admin() && 'product' === get_post_type() && function_exists( 'oceanwp_is_woo_single' ) ) {
				if ( oceanwp_is_woo_single() && true === get_theme_mod( 'ocean_woo_product_ajax_add_to_cart', false ) ) {
					$product_id       = bkap_common::bkap_get_product_id( get_the_ID() );
					$booking_settings = bkap_common::bkap_product_setting( $product_id );
					$is_bookable      = bkap_common::bkap_get_bookable_status( $product_id );
					if ( $is_bookable ) {
						wp_dequeue_script( 'oceanwp-woo-ajax-addtocart' );
					}
				}
			}
		}
	}
	$bkap_booking_process = new bkap_booking_process();
}
