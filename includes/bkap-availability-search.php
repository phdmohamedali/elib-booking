<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Calculations and display of the available products based on the search dates
 *
 * @author      Tyche Softwares
 * @package     BKAP/Search-Widget
 * @since       1.7
 * @category    Classes
 */

if ( ! class_exists( 'Bkap_Availability_Search' ) ) {
	/**
	 * Class Bkap_Plugin_Meta.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Availability_Search {

		/**
		 * Bkap_Availability_Search constructor.
		 */
		public function __construct() {
			add_action( 'widgets_init', array( $this, 'bkap_widgets_init' ) ); // Registering Booking & Appointment Availability Search Widget.
			add_filter( 'bkap_max_date', 'calback_bkap_max_date', 10, 3 );
			add_action( 'pre_get_posts', array( $this, 'bkap_generate_bookable_data' ), 20 );
			add_shortcode( 'bkap_search_widget', array( $this, 'bkap_search_widget_shortcode' ) );
			add_action( 'init', array( $this, 'bkap_set_searched_dates_in_cookies' ) );
		}

		/**
		 * This function initialize the wideget and register the same.
		 *
		 * @since 4.3
		 * @hook widgets_init
		 */
		public function bkap_widgets_init() {
			include_once 'bkap-widget-product-search.php';
			register_widget( 'Custom_WooCommerce_Widget_Product_Search' );
		}

		/**
		 * This function calculate the maximum available date in the booking calendar
		 *
		 * @since 4.4.0
		 * @hook bkap_generate_bookable_data
		 * @param object $query WP_Query Object
		 *
		 * @return object $query Return modified WP_Query Object
		 */
		public function bkap_generate_bookable_data( $query ) {

			if ( ! empty( $_GET['w_checkin'] ) ) {

				if ( 'product' !== $query->get( 'post_type' ) ) {
					return $query;
				}

				$start_date = $_GET['w_checkin'];
				if ( ! empty( $_GET['w_checkout'] ) ) {
					$end_date = $_GET['w_checkout'];
				} else {
					$end_date = $_GET['w_checkin'];
				}

				if ( isset( WC()->session ) ) {
					WC()->session->set( 'start_date', $start_date );
					WC()->session->set( 'end_date', $end_date );
					if ( ! empty( $_GET['w_allow_category'] ) && $_GET['w_allow_category'] == 'on' ) {
						WC()->session->set( 'selected_category', $_GET['w_category'] );
					} else {
						WC()->session->set( 'selected_category', 'disable' );
					}

					if ( ! empty( $_GET['w_allow_resource'] ) && $_GET['w_allow_resource'] == 'on' ) {
						WC()->session->set( 'selected_resource', $_GET['w_resource'] );
					} else {
						WC()->session->set( 'selected_resource', 'disable' );
					}
				}
			}

			if ( ! empty( $start_date ) &&
				! empty( $end_date ) &&
				$query->is_main_query()
			) {

				$query->set( 'suppress_filters', false );
				$filtered_products = array();

				// If widget has only start date then filter out all the products if its an holiday.
				if ( $start_date === $end_date ) {
					$is_global_holiday = bkap_check_holiday( $start_date, $end_date );

					if ( $is_global_holiday ) {
						$query->set( 'post__in', array( '' ) );
						return $query;
					}
				}

				if ( ! empty( $_GET['select_cat'] ) && $_GET['select_cat'] != 0 ) {

					$tax_query[] = array(
						'taxonomy' => 'product_cat',
						'field'    => 'id',
						'terms'    => array( $_GET['select_cat'] ),
						'operator' => 'IN',
					);

					$query->set( 'tax_query', $tax_query );
				}

				/* Retrive products only if it contains selected resource. */
				$meta_query = array();
				if ( ! empty( $_GET['select_res'] ) && $_GET['select_res'] != 0 ) {

					$resource_id = $_GET['select_res'];
					$meta_query  = array(
						array(
							'key'     => '_bkap_resource_base_costs',
							'value'   => $resource_id,
							'compare' => 'LIKE',
						),
						array(
							'key'     => '_bkap_enable_booking',
							'value'   => 'on',
							'compare' => '=',
						),
					);

					$resource                   = new BKAP_Product_Resource( $resource_id );
					$resource_availability_data = $resource->get_resource_availability();

					$resource_availability = false;
					if ( is_array( $resource_availability_data ) && count( $resource_availability_data ) > 0 ) {
						$date_range = bkap_array_of_given_date_range( $start_date, $end_date, 'Y-m-d' );

						foreach ( $date_range as $d_range ) {
							$is_resource_available = bkap_filter_time_based_on_resource_availability( $d_range, $resource_availability_data, '00:00 - 23:59|', array( 'type' => 'fixed_time' ), $resource_id, 0, array() );
							if ( '' != $is_resource_available ) {
								$resource_availability = true;
							}
						}

						if ( ! $resource_availability ) {
							$query->set( 'post__in', array( '' ) );
							return $query;
						}
					}
				}

				$bookable_products = bkap_common::get_woocommerce_product_list( false, 'on', '', array(), $meta_query );

				foreach ( $bookable_products as $pro_key => $pro_value ) {

					$product_id   = $pro_value['1'];
					$view_product = bkap_check_booking_available( $product_id, $start_date, $end_date );

					if ( $view_product ) {
						array_push( $filtered_products, $product_id );
					}
				}

				$filtered_products = apply_filters( 'bkap_additional_products_search_result', $filtered_products );

				if ( count( $filtered_products ) === 0 ) {
					$filtered_products = array( '' );
				}

				$query->set( 'post__in', $filtered_products );
			} elseif ( ! empty( $_GET['select_cat'] ) && $_GET['select_cat'] != 0 ) {

				if ( 'product' !== $query->get( 'post_type' ) ) {
					return $query;
				}

				if ( isset( WC()->session ) ) {
					if ( ! empty( $_GET['w_allow_category'] ) && $_GET['w_allow_category'] == 'on' ) {
						WC()->session->set( 'selected_category', $_GET['w_category'] );
					} else {
						WC()->session->set( 'selected_category', 'disable' );
					}

					if ( ! empty( $_GET['w_allow_resource'] ) && $_GET['w_allow_resource'] == 'on' ) {
						WC()->session->set( 'selected_resource', $_GET['w_resource'] );
					} else {
						WC()->session->set( 'selected_resource', 'disable' );
					}
				}

				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'id',
					'terms'    => array( $_GET['select_cat'] ),
					'operator' => 'IN',
				);

				$query->set( 'tax_query', $tax_query );
			} elseif ( ! empty( $_GET['select_res'] ) && $_GET['select_res'] != 0 ) {

				if ( 'product' !== $query->get( 'post_type' ) ) {
					return $query;
				}

				if ( isset( WC()->session ) ) {
					if ( ! empty( $_GET['w_allow_category'] ) && $_GET['w_allow_category'] == 'on' ) {
						WC()->session->set( 'selected_category', $_GET['w_category'] );
					} else {
						WC()->session->set( 'selected_category', 'disable' );
					}

					if ( ! empty( $_GET['w_allow_resource'] ) && $_GET['w_allow_resource'] == 'on' ) {
						WC()->session->set( 'selected_resource', $_GET['w_resource'] );
					} else {
						WC()->session->set( 'selected_resource', 'disable' );
					}
				}

				/* Retrive products only if it contains selected resource. */
				$meta_query = array();
				if ( ! empty( $_GET['select_res'] ) && $_GET['select_res'] != 0 ) {

					$resource_id = $_GET['select_res'];
					$meta_query  = array(
						array(
							'key'     => '_bkap_resource_base_costs',
							'value'   => $resource_id,
							'compare' => 'LIKE',
						),
						array(
							'key'     => '_bkap_enable_booking',
							'value'   => 'on',
							'compare' => '=',
						),
					);
				}

				$bookable_products = bkap_common::get_woocommerce_product_list( false, 'on', '', array(), $meta_query );
				$filtered_products = array();
				foreach ( $bookable_products as $pro_key => $pro_value ) {
					$product_id          = $pro_value['1'];
					$filtered_products[] = $product_id;
				}

				$filtered_products = apply_filters( 'bkap_additional_products_search_result', $filtered_products );

				if ( count( $filtered_products ) === 0 ) {
					$filtered_products = array( '' );
				}

				$query->set( 'post__in', $filtered_products );
			}
			return $query;
		}

		/**
		 * This function initialize the wideget and register the same.
		 *
		 * @param array $atts Attribute Data.
		 * @since 4.3
		 * @hook bkap_search_widget
		 */
		public function bkap_search_widget_shortcode( $atts ) {

			$html = '';

			$shortcode_instance = array(
				'enable_day_search_label' => '',
				'category'                => 'no',
			);

			$shortcode_instance['start_date_label'] = isset( $atts['start_date_label'] ) ? $atts['start_date_label'] : __( 'Start Date', 'woocommerce-booking' );
			$shortcode_instance['end_date_label']   = isset( $atts['end_date_label'] ) ? __( $atts['end_date_label'], 'woocommerce-booking' ) : __( 'End Date', 'woocommerce-booking' );
			$shortcode_instance['search_label']     = isset( $atts['search_label'] ) ? __( $atts['search_label'], 'woocommerce-booking' ) : __( 'Search', 'woocommerce-booking' );
			$shortcode_instance['clear_label']      = isset( $atts['clear_label'] ) ? __( $atts['clear_label'], 'woocommerce-booking' ) : __( 'Clear', 'woocommerce-booking' );
			$shortcode_instance['text_label']       = isset( $atts['text_label'] ) ? __( $atts['text_label'], 'woocommerce-booking' ) : '';
			$shortcode_instance['category_title']   = isset( $atts['category_label'] ) ? __( $atts['category_label'], 'woocommerce-booking' ) : __( 'Select Category', 'woocommerce-booking' );

			if ( isset( $atts['search_by_category'] ) && 'yes' == $atts['search_by_category'] ) {
				$shortcode_instance['category'] = 'on';
			}

			if ( isset( $atts['hide_end_date'] ) && 'yes' === $atts['hide_end_date'] ) {
				$shortcode_instance['enable_day_search_label'] = 'on';
			}

			$html = Custom_WooCommerce_Widget_Product_Search::bkap_search_widget_form( $shortcode_instance, 'shortcode' );

			return $html;
		}

		/**
		 * When searched for available date by guest then storing the searched dates in cookie
		 *
		 * @since 4.14.0
		 * @hook init
		 */
		public function bkap_set_searched_dates_in_cookies() {

			if ( ! empty( $_GET['w_checkin'] ) ) {
				unset( $_COOKIE['start_date'] );
				unset( $_COOKIE['end_date'] );

				setcookie( 'start_date', $_GET['w_checkin'], 0, '/' );

				if ( ! empty( $_GET['w_checkout'] ) ) {
					setcookie( 'end_date', $_GET['w_checkout'], 0, '/' );
				} else {
					setcookie( 'end_date', $_GET['w_checkin'], 0, '/' );
				}
			}
		}
	}

	$bkap_availability_search = new Bkap_Availability_Search();
}

/**
 * This function calculate the maximum available date in the booking calendar
 *
 * @since 1.7
 * @hook bkap_max_date
 * @param string                      $m_d Minimum Date
 * @param int                         $max_dates Numbers of date to choose
 * @param $booking_set Booking Setting
 *
 * @return string $m_d Return Max date
 */

function calback_bkap_max_date( $m_d, $max_dates, $booking_set ) {

	$next_date      = $m_d;
	$max_loop_count = apply_filters( 'bkap_max_date_loop_count', 1000, $m_d, $max_dates, $booking_set );

	$recurring = true;
	foreach ( $booking_set['booking_recurring'] as $recur_key => $recur_value ) {
		if ( isset( $recur_value ) && $recur_value != 'on' ) {
			$recurring = false;
		} elseif ( isset( $recur_value ) && $recur_value == 'on' ) {
			$recurring = true;
			break;
		}
	}

	if ( isset( $booking_set['booking_specific_date'] )
	&& is_array( $booking_set['booking_specific_date'] )
	&& count( $booking_set['booking_specific_date'] ) > 0 ) {
		$specific_dates = array_keys( $booking_set['booking_specific_date'] );
		$today_midnight = strtotime( 'today midnight' );
		foreach ( $specific_dates as $k => $v ) {
			if ( strtotime( $v ) < $today_midnight ) {
				unset( $specific_dates[ $k ] );
			}
		}
	}

	if ( ! $recurring && isset( $specific_dates ) ) {

		if ( count( $specific_dates ) > 0 ) {
			sort( $specific_dates );
			$next_date = $specific_dates[ 0 ];
		}
	}

	for ( $i = 0; $i < $max_loop_count; $i++ ) {

		$stt = '';
		$stt = date( 'w', strtotime( $next_date ) );
		$stt = 'booking_weekday_' . $stt;

		if ( $max_dates >= 0 ) {

			if ( isset( $booking_set['booking_recurring'] ) && $booking_set['booking_recurring'][ $stt ] == 'on' ) {

				if ( isset( $booking_set['booking_date_range'] ) && count( $booking_set['booking_date_range'] ) > 0 ) {

					foreach ( $booking_set['booking_date_range'] as $range_value ) {
						if ( strtotime( $range_value['start'] ) < strtotime( $next_date ) && strtotime( $range_value['end'] ) > strtotime( $next_date ) ) {
							$m_d = $next_date;
							$max_dates--;
						}
					}
				} else {
					$m_d = $next_date;
					$max_dates--;
				}
			} elseif ( isset( $specific_dates ) ) {
				if ( in_array( $next_date, $specific_dates ) ) {
					$m_d = $next_date;
				}
				$max_dates--;
			}
			$next_date = addDayswithdate( $next_date, 1 );
		} else {
			break;
		}
	}

	return $m_d;
}

/**
 * Check if Booking is not locked out for a particular date
 *
 * @since 4.3.0
 * @param string|int $product_id Product ID
 * @param string     $start_date Start Date
 * @param string     $end_date End Date
 * @return bool True for available else false
 */

function bkap_check_booking_available( $product_id, $start_date, $end_date ) {

	$product_id                  = bkap_common::bkap_get_product_id( $product_id );
	$booking_settings            = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
	$booking_type                = get_post_meta( $product_id, '_bkap_booking_type', true );
	$recurring_selected_weekdays = isset( $booking_settings['booking_recurring'] ) ? $booking_settings['booking_recurring'] : array();
	$booking_specific_booking    = isset( $booking_settings['booking_specific_booking'] ) ? $booking_settings['booking_specific_booking'] : '';
	$month_range                 = get_post_meta( $product_id, '_bkap_month_ranges', true );
	$current_time                = current_time( 'timestamp' );

	// Check if the product having resource and that any resource are available on selected date range or not.
	if ( isset( $booking_settings['_bkap_resource'] ) && $booking_settings['_bkap_resource'] == 'on' ) {
		$is_resource_available = bkap_check_resource_available( $product_id, $start_date, $end_date );

		if ( $is_resource_available ) {
			return false;
		}
	}

	// Check if the product is avaialble based on advance booking period or not.
	if ( $start_date === $end_date ) {
		$is_min_date_available = bkap_check_for_min_date( $product_id, $start_date, $current_time );

		if ( ! $is_min_date_available ) {
			return false;
		}
	}

	// Check if the date range having the weekdays enabled in the booking settings
	$return_value_recurring = check_in_range_weekdays( $start_date, $end_date, $recurring_selected_weekdays );
	if ( ! in_array( true, $return_value_recurring, true ) ) {
		if ( $booking_specific_booking != 'on' ) {
			return false;
		}
	}

	// Check if the date range falls in month range or not.
	if ( is_array( $month_range ) && ! empty( $month_range ) ) {
		$return_value = bkap_check_in_range_months( $start_date, $end_date, $month_range[0]['start'], $month_range[0]['end'] );

		if ( ! in_array( true, $return_value, true ) ) {
			return false;
		}
	}

	// Check if the start date is less than the maximum date available for product or not.
	$is_in_max_range = bkap_check_for_max_date( $product_id, $booking_settings, $start_date, $current_time );
	if ( ! $is_in_max_range ) {
		return false;
	}

	switch ( $booking_type ) {
		case 'only_day':
			do {
				$availability_result = bkap_check_day_booking_available( $product_id, $start_date );
				$range_has_holiday   = bkap_check_holiday( $start_date, $start_date );

				if ( $availability_result && ! $range_has_holiday ) {
					return true;
				}
				$start_date = date( 'Y-m-d', strtotime( $start_date . ' +1 day' ) );
			} while ( strtotime( $start_date ) <= strtotime( $end_date ) );

			return false;
			break;

		case 'multiple_days':
			$range_has_holiday = bkap_check_holiday( $start_date, $end_date );
			if ( $range_has_holiday ) {
				return false;
			}

			if ( isset( $booking_settings['booking_fixed_block_enable'] )
				&& $booking_settings['booking_fixed_block_enable'] === 'booking_fixed_block_enable'
			) {

				$block_max_days = 0;
				if ( isset( $booking_settings['bkap_fixed_blocks_data'] ) ) {
					foreach ( $booking_settings['bkap_fixed_blocks_data'] as $block_key => $block_value ) {
						if ( isset( $block_value['number_of_days'] ) && $block_value['number_of_days'] > $block_max_days ) {
							$block_max_days = $block_value['number_of_days'];
						}
					}
				}

				if ( $block_max_days > 0 ) {
					$end_date = date( 'Y-m-d', strtotime( $end_date . " +$block_max_days day" ) );
				}
			}

			do {
				$availability_result = bkap_check_day_booking_available( $product_id, $start_date );
				if ( ! $availability_result ) {
					return false;
				}
				$start_date = date( 'Y-m-d', strtotime( $start_date . ' +1 day' ) );
			} while ( strtotime( $start_date ) < strtotime( $end_date ) );

			return true;
			break;

		case 'date_time':
			do {
				$availability_result = bkap_check_day_booking_available( $product_id, $start_date );
				$range_has_holiday   = bkap_check_holiday( $start_date, $start_date );
				$day_has_timeslot    = bkap_common::bkap_check_timeslot_for_weekday( $product_id, $start_date, $booking_settings );

				if ( $availability_result && ! $range_has_holiday ) {
					$time_slots = explode( '|', bkap_booking_process::get_time_slot( $start_date, $product_id ) );

					if ( sanitize_key( $time_slots[0] ) !== 'error' &&
						( sanitize_key( $time_slots[0] ) !== '' && $day_has_timeslot ) ) {

						return true;
					}
				}
				$start_date = date( 'Y-m-d', strtotime( $start_date . ' +1 day' ) );
			} while ( strtotime( $start_date ) <= strtotime( $end_date ) );
			return false;
			break;

		case 'duration_time':
			return true;
			break;

		default:
			return false;
			break;
	}
}

/**
 * Check if min booking date is available for booking when compared to start date.
 * Return true if date available else return false
 *
 * @since 4.3.0
 * @param string|int $product_id Product ID
 * @param string     $start_date Start Date
 * @param string     $current_time Current WordPress Time
 * @return bool True if date available else return false
 */

function bkap_check_for_min_date( $product_id, $start_date, $current_time ) {

	$min_date = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time );
	if ( strtotime( $min_date ) > strtotime( $start_date ) ) {
		return false;
	} else {
		return true;
	}
}

/**
 * Check if resource is available on the given start and end date range or not.
 *
 * @since 4.8.0
 * @param string|int $product_id Product ID
 * @param string     $start_date Start Date
 * @param string     $end_date End Date
 *
 * @return boolean True if resource has lockout date for searched date range.
 */

function bkap_check_resource_available( $product_id, $start_date, $end_date ) {

	$date_range = bkap_array_of_given_date_range( $start_date, $end_date, 'j-n-Y' );
	$rstatus    = false;

	if ( ! empty( $_GET['select_res'] ) && $_GET['select_res'] != 0 ) {
		$resource_id = (int) $_GET['select_res'];
		$rstatus     = bkap_check_resource_booked_in_date_range( $product_id, $resource_id, $date_range );
	} else {
		$bkap_product_resources = get_post_meta( $product_id, '_bkap_product_resources', true );

		if ( '' != $bkap_product_resources && is_array( $bkap_product_resources ) ) {

			$resource_selection = Class_Bkap_Product_Resource::bkap_product_resource_selection( $product_id );
			if ( 'bkap_automatic_resource' === $resource_selection ) {
				$rstatus = bkap_check_resource_booked_in_date_range( $product_id, $bkap_product_resources[0], $date_range );
			} else {
				foreach ( $bkap_product_resources as $rkey => $rvalue ) {
					$rstatus = bkap_check_resource_booked_in_date_range( $product_id, $rvalue, $date_range );
				}
			}
		}
	}

	return $rstatus;
}

/**
 * Check if start date is out of the max date range (i.e. maximum number of dates to choose).
 * Return true if in range else return false
 *
 * @since 4.3.0
 * @param string|int $product_id Product ID
 * @param array      $booking_settings Booking Settings for the product to check
 * @param string     $start_date Start Date
 * @return bool true if not in range else return false
 */

function bkap_check_for_max_date( $product_id, $booking_settings, $start_date, $current_time ) {

	$numbers_of_days_to_choose = isset( $booking_settings['booking_maximum_number_days'] ) ? $booking_settings['booking_maximum_number_days'] - 1 : '';
	$custom_ranges             = isset( $booking_settings['booking_date_range'] ) ? $booking_settings['booking_date_range'] : array();

	$month_ranges = get_post_meta( $product_id, '_bkap_month_ranges', true );
	$min_date     = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time );

	if ( ( isset( $numbers_of_days_to_choose )
			&& '' != $numbers_of_days_to_choose
			&& empty( $custom_ranges )
			&& empty( $month_ranges ) )
		||
		 ( isset( $numbers_of_days_to_choose )
			&& 0 === $numbers_of_days_to_choose )
	 ) {

		if ( isset( $booking_settings['booking_recurring_booking'] )
			&& $booking_settings['booking_recurring_booking'] == 'on'
		) {

			$max_date = apply_filters( 'bkap_max_date', $min_date, $numbers_of_days_to_choose, $booking_settings );

			if ( strtotime( $max_date ) < strtotime( $start_date ) ) {
				return false;
			}
		}
	}

	return true;
}

/**
 * Check if bookings are available for that day for single day bookings
 *
 * @since 4.3.0
 * @param string|int $product_id Product ID
 * @param string     $start_date Start Date
 * @return bool True if booking available else false
 */

function bkap_check_day_booking_available( $product_id, $start_date ) {

	$result = get_bookings_for_date( $product_id, $start_date );
	$res    = get_availability_for_date( $product_id, $start_date, $result );

	if ( count( $res ) > 0 &&
		( $res['unlimited'] === 'YES' || ( $res['unlimited'] === 'NO' && $res['available'] > 0 ) ) ) {

		return true;
	}
	return false;
}

/**
 * Check if the date passed is a part of global holidays
 *
 * @since 4.3.0
 * @param string $start_date Date (start date from widget)
 * @param string $end_date Date (end date from widget)
 * @return bool true if part of global holiday else false
 */

function bkap_check_holiday( $start_date, $end_date ) {

	$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
	if ( isset( $global_settings->booking_include_global_holidays ) && $global_settings->booking_include_global_holidays == 'on' ) {
		return false;
	}
	$global_holidays      = array();
	$formatted_start_date = date( 'j-n-Y', strtotime( $start_date ) );
	$formatted_end_date   = date( 'j-n-Y', strtotime( $end_date ) );

	if ( isset( $global_settings->booking_global_holidays ) ) {
		$global_holidays = explode( ',', $global_settings->booking_global_holidays );
	}

	if ( in_array( $formatted_start_date, $global_holidays ) ) {
		return true;
	} elseif ( $formatted_end_date !== $formatted_start_date ) {
		while ( strtotime( $formatted_start_date ) < strtotime( $formatted_end_date ) ) {
			if ( in_array( $formatted_start_date, $global_holidays ) ) {
				return true;
			}
			$formatted_start_date = date( 'j-n-Y', strtotime( $formatted_start_date . ' +1 day' ) );
		}
	}
	return false;
}

/**
 * Check in custom dates which are non-bookable
 *
 * @since 4.3.0
 * @param string $start_date Date (start date from widget)
 * @param string $end_date Date (end date from widget)
 * @param string $custom_start_date Date (start date from holiday range)
 * @param string $custom_end_date Date (end date from holiday range)
 * @return bool true if part of global holiday else false
 */

function bkap_check_in_custom_holiday_range( $start_date, $end_date, $custom_start_date, $custom_end_date ) {

	$start_ts            = strtotime( $start_date );
	$end_ts              = strtotime( $end_date );
	$new_custom_array    = array();
	$custom_return_value = array();

	while ( $start_ts <= $end_ts ) {
		$new_custom_array[] = $start_date;
		$start_ts           = strtotime( '+1 day', $start_ts );
		$start_date         = date( 'j-n-Y', $start_ts );
	}

	foreach ( $new_custom_array as $key => $value ) {

		$custom_values = strtotime( $value );
		if ( $custom_values >= strtotime( $custom_start_date ) && $custom_values <= strtotime( $custom_end_date ) ) {
			$custom_return_value [ $value ] = true;
		} else {
			$custom_return_value [ $value ] = false;
		}
	}

	return $custom_return_value;
}

/**
 * This function will add days to the passed date and return the date
 *
 * @since 1.7
 * @param mixed $date It can be Date(string) or UNIXTIME(int)
 * @param int   $days Numbers of days to be added to the date
 *
 * @return string $m_d Return new date after the days added
 */

function addDayswithdate( $date, $days ) {

	if ( is_numeric( $date ) ) {
		$date = strtotime( '+' . $days . ' days', $date );
	} else {
		$date = strtotime( '+' . $days . ' days', strtotime( $date ) );
	}
	return date( 'j-n-Y', $date );
}

/**
 * This function will check if the date is between give date range or not.
 *
 * @since 2.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $date_from_user selected date by user on front end
 * @return true|false This will return true if user date is in between date range else false
 */

function check_in_range( $start_date, $end_date, $date_from_user ) {
	$start_ts = strtotime( $start_date );
	$end_ts   = strtotime( $end_date );
	$user_ts  = strtotime( $date_from_user );

	// Check that user date is between start & end
	return ( ( $user_ts >= $start_ts ) && ( $user_ts <= $end_ts ) );
}

/**
 * This function will return array of dates with true if date is current/future date and false if date is past date.
 *
 * @since 2.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $date_from_user selected date by user on front end
 *
 * @return array $return_value This will return array of dates with true if date is current/future date and false if date is past date.
 */

function check_in_range_abp( $start_date, $end_date, $date_from_user ) {

	$start_ts          = strtotime( $start_date );
	$end_ts            = strtotime( $end_date );
	$user_ts           = strtotime( $date_from_user );
	$return_value      = array();
	$new_week_days_arr = array();

	while ( $start_ts <= $end_ts ) {
		$new_week_days_arr [] = $start_date;
		$start_ts             = strtotime( '+1 day', $start_ts );
		$start_date           = date( 'j-n-Y', $start_ts );
	}

	foreach ( $new_week_days_arr as $weekday_key => $weekday_value ) {

		$week_day_value = strtotime( $weekday_value );

		if ( $week_day_value == $user_ts ) {
			$return_value [ $weekday_value ] = true;
		} elseif ( $week_day_value >= $user_ts ) {
			$return_value [ $weekday_value ] = true;
		} else {
			$return_value [ $weekday_value ] = false;
		}
	}
	return $return_value;
}

/**
 * This function will return date range with true false based on the enabled weekdays.
 *
 * @since 2.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $recurring_selected_weekdays Weekday setting of the product
 *
 * @return array $return_value This will array of dates with true if date range having weekday enabled else date with false value.
 */

function check_in_range_weekdays( $start_date, $end_date, $recurring_selected_weekdays ) {

	$start_ts          = strtotime( $start_date );
	$end_ts            = strtotime( $end_date );
	$return_value      = array();
	$new_week_days_arr = array();

	while ( $start_ts <= $end_ts ) {

		if ( ! in_array( date( 'w', $start_ts ), $new_week_days_arr ) ) {
			$new_week_days_arr [] = date( 'w', $start_ts );
		} elseif ( ! in_array( date( 'w', $end_ts ), $new_week_days_arr ) ) {
			$new_week_days_arr [] = date( 'w', $end_ts );
		}
		$start_ts = strtotime( '+1 day', $start_ts );
	}

	foreach ( $recurring_selected_weekdays as $weekday_key => $weekday_value ) {

		$week_day_value = substr( $weekday_key, -1 );

		if ( $weekday_value == 'on' && in_array( $week_day_value, $new_week_days_arr ) ) {
			$return_value [] = true;
		} else {
			$return_value [] = false;
		}
	}
	return $return_value;
}

/**
 * This function will return date range with true false based on the added month range.
 *
 * @since 4.12.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $custom_start_date date of the start month
 * @param string $custom_end_date date of the end month
 *
 * @return array $return_value This will array of dates with true if date range falling under month range else date with false value.
 */

function bkap_check_in_range_months( $start_date, $end_date, $custom_start_date, $custom_end_date ) {

	$start_ts            = strtotime( $start_date );
	$end_ts              = strtotime( $end_date );
	$new_custom_array    = array();
	$custom_return_value = array();

	while ( $start_ts <= $end_ts ) {
		$new_custom_array[] = $start_date;
		$start_ts           = strtotime( '+1 day', $start_ts );
		$start_date         = date( 'Y-m-d', $start_ts );
	}
	foreach ( $new_custom_array as $key => $value ) {

		$custom_values = strtotime( $value );

		if ( $custom_values >= strtotime( $custom_start_date ) && $custom_values <= strtotime( $custom_end_date ) ) {
			$custom_return_value [ $value ] = true;
		} else {
			$custom_return_value [ $value ] = false;
		}
	}

	return $custom_return_value;
}

/**
 * This function will return date with true or false value based on the holidays date
 *
 * @since 2.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $recurring_selected_weekdays Array of holiday dates
 *
 * @return array $return_value This will array of dates with true if date holiday else date with false value.
 */

function check_in_range_holidays( $start_date, $end_date, $recurring_selected_weekdays ) {

	$start_ts          = strtotime( $start_date );
	$end_ts            = strtotime( $end_date );
	$return_value      = array();
	$new_week_days_arr = array();

	while ( $start_ts <= $end_ts ) {

		$new_week_days_arr [] = $start_date;
		$start_ts             = strtotime( '+1 day', $start_ts );
		$start_date           = date( 'j-n-Y', $start_ts );
	}

	foreach ( $new_week_days_arr as $weekday_key => $weekday_value ) {

		$week_day_value = strtotime( $weekday_value );

		if ( is_array( $recurring_selected_weekdays ) && in_array( $weekday_value, $recurring_selected_weekdays ) ) {
			$return_value [ $weekday_value ] = true;
		} else {

			$return_value [ $weekday_value ] = false;
		}
	}
	return $return_value;
}

/**
 * This function will return array of dates with true or false value based on the weekday of fixed block
 *
 * @since 2.0
 * @param string $start_date Start Date
 * @param string $end_date End Date
 * @param string $days Array of added days in all fixed blocks
 *
 * @return array $return_value Return array of dates with true or false value based on the weekday of fixed block
 */

function check_in_fixed_block_booking( $start_date, $end_date, $days ) {

	$start_ts          = strtotime( $start_date );
	$end_ts            = strtotime( $end_date );
	$return_value      = array();
	$new_week_days_arr = array();
	$weekdays_array    = array(
		'Sunday'    => '0',
		'Monday'    => '1',
		'Tuesday'   => '2',
		'Wednesday' => '3',
		'Thursday'  => '4',
		'Friday'    => '5',
		'Saturday'  => '6',
	);

	$flag      = false;
	$min_day   = date( 'l', $start_ts );
	$min_value = $weekdays_array[ $min_day ];

	if ( in_array( $min_value, $days ) || in_array( 'any_days', $days ) ) {
		$flag = true;
	}

	if ( $flag ) {
		$return_value [ $start_date ] = true;
	} else {
		$return_value [ $start_date ] = false;
	}

	return $return_value;
}

