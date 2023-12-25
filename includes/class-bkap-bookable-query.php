<?php
/**
 * Bookable Query
 *
 * @package  BKAP/bookable-query
 */

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class containing helpers to query data.
 *
 * @author   Tyche Softwares
 * @category Classes
 */
class BKAP_Bookable_Query {

	/**
	 * Posttype for bookable
	 *
	 * @var string
	 */
	private static $posttype = 'product';

	/**
	 * Number of bookable to show
	 *
	 * @var integer
	 */
	private static $numberpost = 10;

	/**
	 * Meta query to filter bookable
	 *
	 * @var array
	 */
	private static $meta_query = array(
		'relation' => 'AND',
		array(
			'key'     => '_bkap_enable_booking',
			'value'   => 'on',
			'compare' => '=',
		),
	);

	/**
	 * Valid post status for bookable
	 *
	 * @var array
	 */
	private static $bookable_status = array(
		'publish',
		'pending',
		'draft',
		'auto-draft',
		'future',
		'private',
		'inherit',
	);

	/**
	 * Get query args
	 *
	 * @param array $args query arguments.
	 * @return array
	 */
	public static function get_query_args( $args ) {

		$filter   = isset( $args['filter'] ) ? $args['filter'] : '';
		$filter   = bkap_common::get_attribute_value( $filter );
		$tax_args = self::get_tax_query_args( $args );

		$query_args = array(
			'post_type'   => self::$posttype,
			'status'      => self::$bookable_status,
			'numberposts' => ! empty( $args['numberposts'] ) ? $args['numberposts'] : self::$numberpost,
		);

		if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$current_language   = apply_filters( 'wpml_current_language', NULL );
			$query_args['lang'] = $current_language;
		}

		$query_args['suppress_filters'] = 0;
		$query_args['meta_query']       = self::get_meta_query_args( $args );

		if ( ! empty( $tax_args ) ) {
			$query_args['tax_query'] = $tax_args;
		}

		if ( 'products' === $filter && ! empty( $args['products'] ) ) {
			$products              = bkap_common::get_attribute_value( $args['products'] );
			$product_ids           = is_array( $products ) ? explode( ',', $products ) : $products;
			$query_args['include'] = $product_ids;
		}

		return $query_args;
	}

	/**
	 * Get meta query args
	 *
	 * @param  array $args arguments.
	 * @return array
	 */
	public static function get_meta_query_args( $args ) {
		$filter     = isset( $args['filter'] ) ? $args['filter'] : '';
		$filter     = bkap_common::get_attribute_value( $filter );
		$meta_query = self::$meta_query;

		// Get bookable by type (day/time).
		if ( 'type' === $filter && ! empty( $args['type'] ) ) {
				$type          = $args['type'];
				$bookable_type = $args[ $type . 'Type' ];
				$meta_query[]  = array(
					'key'   => '_bkap_booking_type',
					'value' => $bookable_type,
				);
		}

		// Get bookable products by resources.
		if ( 'resources' === $filter && ! empty( $args['resources'] ) ) {
			$resources = explode( ',', bkap_common::get_attribute_value( $args['resources'] ) );
			// Add each resource to meta query.
			foreach ( $resources as $id ) {
				$resource_args[] = array(
					'key'     => '_bkap_product_resources',
					'value'   => $id,
					'compare' => 'LIKE',
				);
			}

			$meta_query[] = array_merge( array( 'relation' => 'OR' ), $resource_args );
		}

		return $meta_query;
	}

	/**
	 * Get taxonomy args
	 *
	 * @param  array $args arguments.
	 * @return array
	 */
	public static function get_tax_query_args( $args ) {

		$filter    = isset( $args['filter'] ) ? $args['filter'] : '';
		$filter    = bkap_common::get_attribute_value( $filter );
		$tax_query = array();

		// Get bookables based on categories.
		if ( 'categories' === $filter && ! empty( $args['categories'] ) ) {
			$categories = bkap_common::get_attribute_value( $args['categories'] );
			$tax_query  = array(
				array(
					'taxonomy' => 'product_cat',
					'terms'    => $categories,
				),
			);
		}

		return $tax_query;
	}

	/**
	 * Get bookable data
	 *
	 * @param  array $args arguments.
	 * @return array|boolean
	 */
	public static function get_data( $args ) {
		return get_posts( self::get_query_args( $args ) );
	}

	/**
	 * Get bookable products with events rrule for calender.
	 *
	 * @param  array $args display args.
	 * @return array
	 */
	public static function get_events( $args ) {

		$show_times             = isset( $args['showTimes'] ) ? $args['showTimes'] : false;
		$sort_single_day_events = isset( $args['sortingSingleEvents'] ) ? $args['sortingSingleEvents'] : false;
		$events                 = array();
		$bookables              = self::get_data( $args );
		$show_times             = rest_sanitize_boolean( $show_times );
		$sort_single_day_events = rest_sanitize_boolean( $sort_single_day_events );
		$current_time           = current_time( 'timestamp' ); // WordPress Time.
		$current_date           = date( 'Y-m-d', $current_time );
		$phpversion             = version_compare( phpversion(), '5.3', '>' );
		$today                  = date( 'Y-m-d H:i', $current_time );
		$date1                  = new DateTime( $today );
		$global_settings        = bkap_global_setting();
		$global_holidays        = explode( ',', $global_settings->booking_global_holidays );
		$timezone               = bkap_timezone_check( $global_settings );
		$last_event_date        = array();

		foreach ( $bookables as $bookable ) {

			// Initial settings.
			$booking_type     = $bookable->_bkap_booking_type;
			$custom_range     = $bookable->_bkap_custom_ranges;
			$holidays         = is_array( $bookable->_bkap_product_holidays ) ? array_keys( $bookable->_bkap_product_holidays ) : array();
			$holidays         = array_merge( $holidays, $global_holidays );
			$product_id       = $bookable->ID;
			$_product         = wc_get_product( $product_id );
			$product_type     = $_product->get_type();
			$resources        = bkap_common::get_bookable_resources( $product_id );
			$price            = $_product->get_price();
			$booking_settings = $bookable->woocommerce_booking_settings;

			// Calculation for setting min and max date for product.
			if ( ! empty( $custom_range ) ) {
				$min_max_dates      = bkap_minmax_date_custom_range( $bookable->ID, $custom_range );
				$min_date           = $min_max_dates[0];
				$end_date           = $min_max_dates[1];
				$custom_range_dates = $min_max_dates[2];
			} else {
				$min_date = bkap_common::bkap_min_date_based_on_AdvanceBookingPeriod( $bookable->ID, $current_time );
				$end_date = calback_bkap_max_date( $current_date, $bookable->_bkap_max_bookable_days, $bookable->woocommerce_booking_settings );/* '2020-07-30'; */
				$end_date = date( 'Y-m-d H:i:s', strtotime( $end_date . '23.59' ) );
			}

			$last_event_date[] = $end_date;
			$start             = gmdate( 'Y-m-d', strtotime( $min_date ) );
			$frequency         = 'daily';
			$interval          = 1;
			$allday            = ( $show_times ? false : true );

			// Structure data.
			$bookable_item = array(
				'id'            => $bookable->ID,
				'title'         => $bookable->post_title,
				'url'           => esc_url( get_permalink( $bookable ) ),
				'allDay'        => $allday,
				'rrule'         => array(
					'dtstart'  => $start, // Booking Start.
					'freq'     => $frequency, // Daily/Weekly/hourly etc.
					'interval' => $interval, // Booking interval day/hours/min etc.
					'until'    => $end_date,
				),
				'extendedProps' => array(
					'productType'   => $product_type,
					'bookingType'   => $booking_type,
					'view'          => ( isset( $args['view'] ) ? $args['view'] : '' ),
					'greyOutBooked' => ( isset( $args['greyOutBooked'] ) ? $args['greyOutBooked'] : '' ),
					'showQuantity'  => ( isset( $args['showQuantity'] ) ? $args['showQuantity'] : '' ),
					'holidays'      => $holidays,
					'showTime'      => $show_times,
					'price'         => $price,
					'start'         => $start,
					'end'           => $end_date,
				),
			);

			if ( isset( $custom_range_dates ) ) {
				$bookable_item['extendedProps']['custom_range_dates'] = $custom_range_dates;
			}

			// Sort single day events.
			$bookable_item['extendedProps']['sort'] = in_array( $booking_type, array( 'only_day', 'multidates' ), true ) ? 0 : 1;

			// Set product type as variable when show time is false to display select options.
			if ( ! $show_times && in_array( $booking_type, array( 'duration_time', 'date_time', 'multidates_fixedtime' ), true ) ) {
				$bookable_item['extendedProps']['product_type'] = 'variable';
			}

			// Set all day for single day.
			if ( in_array( $booking_type, array( 'only_day', 'multiple_days', 'multidates' ), true ) ) {
				$bookable_item['allDay'] = true;
			}

			// Weekdays.
			$recurring_weekdays = $bookable->_bkap_recurring_weekdays;

			if ( ! empty( $recurring_weekdays ) ) {
				$weekdays_keys                       = array( 'su', 'mo', 'tu', 'we', 'th', 'fr', 'sa' );
				$weekdays_values                     = array_values( $recurring_weekdays );
				$weekdays_data                       = array_combine( $weekdays_keys, $weekdays_values );
				$weekdays                            = array_keys( array_filter( $weekdays_data ) );
				$bookable_item['rrule']['byweekday'] = $weekdays;
			}

			// check for Manage Availability Time.
			$mta_check = ( isset( $booking_settings['bkap_manage_time_availability'] ) && ! empty( $booking_settings['bkap_manage_time_availability'] ) );

			// Set duration based rules.
			if ( 'duration_time' === $booking_type && $show_times ) {
				$hourly            = '00';
				$minutely          = '00';
				$duration_settings = $bookable->_bkap_duration_settings;

				if ( isset( $duration_settings ) && is_array( $duration_settings ) ) {

					$frequency = '';
					$into      = 0;
					$hourly    = 0;

					if ( isset( $duration_settings['duration_type'] ) ) {

						switch ( $duration_settings['duration_type'] ) {
							case 'mins':
									$frequency = 'minutely';
									$into      = 60;
									$minutely  = $duration_settings['duration'];
								break;
							case 'hours':
									$frequency = 'hourly';
									$into      = 3600;
									$hourly    = $duration_settings['duration'];
								break;
						}
					}

					$first         = ( isset( $duration_settings['first_duration'] ) && '' !== $duration_settings['first_duration'] ) ? $duration_settings['first_duration'] : '00:10';
					$first_explode = explode( ':', $first );
					$last          = ( isset( $duration_settings['end_duration'] ) && '' !== $duration_settings['end_duration'] ) ? $duration_settings['end_duration'] : '23:59';
					$start         = gmdate( "Y-m-d\T{$first}" );
					$interval      = ( isset( $duration_settings['duration'] ) && '' !== $duration_settings['duration'] ) ? $duration_settings['duration'] : '';

					// Calculate number of hours and mins for end time.
					$start_date                        = new DateTime( $first );
					$end_date                          = new DateTime( $last );
					$int                               = $start_date->diff( $end_date );
					$hours                             = $int->format( '%H' );
					$minutes                           = $int->format( '%i' );
					$minutes                           = ( $minutes < 10 ) ? '0' . $minutes : $minutes;
					$bookable_item['rrule']['dtstart'] = date( 'Y-m-d H:i:s', ( $current_time + ( $bookable->_bkap_abp * 3600 ) ) );

					// Need some help in setting frequency and interval so look at it later.
					// $bookable_item['rrule']['freq']      = 'RRule.DAILY';
					// $bookable_item['rrule']['interval']  = 5;

					$bookable_item['rrule']['byhour']   = array( $first_explode[0] );
					$bookable_item['rrule']['byminute'] = array( $first_explode[1] );
					$bookable_item['duration']          = $hours . ':' . $minutes;

					if ( $mta_check ) {
						$manage_time_availability_data = $booking_settings['bkap_manage_time_availability'];
						usort( $manage_time_availability_data, 'bkap_sort_date_time_ranges_by_priority' );
						$bookable_item['extendedProps']['manage_time_availability'] = $manage_time_availability_data;
					}
				}
			}

			// Set slots.
			$push          = true;
			$resource_push = false;
			if ( in_array( $booking_type, array( 'date_time', 'multidates_fixedtime' ), true ) && $show_times && ! empty( $bookable->_bkap_time_settings ) ) {
				$time_settings      = $bookable->_bkap_time_settings;
				$time_bookable_item = $bookable_item;
				$i                  = 0;
				$newtimedata        = array();
				foreach ( $time_settings as $week => $time_setting ) {
					$weekday_cal = true;
					if ( strpos( $week, 'weekday' ) === false ) {
						$weekday_cal = false;
					}

					if ( ! $weekday_cal && strtotime( $week ) < strtotime( $current_date ) ) {
						continue;
					}

					$w = $weekdays_keys[ substr( $week, -1 ) ];
					foreach ( $time_setting as $key => $time_data ) {

						$push       = false;
						$open_ended = false;
						$from       = $time_data['from_slot_hrs'] . ':' . $time_data['from_slot_min'];
						$to         = $time_data['to_slot_hrs'] . ':' . $time_data['to_slot_min'];
						if ( '0:00' == $to ) {
							$open_ended = true;
							$to         = '23:59';
						}
						$newtimedata[ $i ]['extendedProps']['actual_timeslot_value'] = $from . ' - ' . $to;

						$booking_time = apply_filters( 'bkap_change_date_comparison_for_abp', $current_date . $from, $min_date, $from, $to, $product_id, $bookable->woocommerce_booking_settings );
						$date2        = new DateTime( $booking_time );
						$include      = bkap_dates_compare( $date1, $date2, $bookable->_bkap_abp, $phpversion );
						$start_date   = new DateTime( $from );
						$end_date     = new DateTime( $to );
						$interval     = $start_date->diff( $end_date );
						$hours        = $interval->format( '%H' );
						$minutes      = $interval->format( '%i' );
						$minutes      = ( $minutes < 10 ) ? '0' . $minutes : $minutes;

						$newtimedata[ $i ] = $time_bookable_item;
						if ( $weekday_cal ) {
							if ( ! $include ) {
								$newtimedata[ $i ]['rrule']['dtstart'] = date( 'Y-m-d H:i:s', ( $current_time + ( $bookable->_bkap_abp * 3600 ) ) );
							}
							$newtimedata[ $i ]['rrule']['byweekday'] = array( $w );
							$newtimedata[ $i ]['rrule']['byhour']    = array( $time_data['from_slot_hrs'] );/* $hours_from; */
							$newtimedata[ $i ]['rrule']['byminute']  = array( $time_data['from_slot_min'] );/* $hours_from; */
						} else {
							unset( $newtimedata[ $i ]['rrule'] );
							$newtimedata[ $i ]['start'] = date( 'Y-m-d H:i:s', strtotime( $week . ' ' . $from ) );
							if ( ! $open_ended ) {
								$newtimedata[ $i ]['end'] = date( 'Y-m-d H:i:s', strtotime( $week . ' ' . $to ) );
							}
						}

						if ( ! $open_ended ) {
							$newtimedata[ $i ]['duration'] = $hours . ':' . $minutes;
						}

						if ( $timezone && ! is_admin() ) {
							$store_timezone_string = bkap_booking_get_timezone_string();
							$offset                = bkap_get_offset_from_date( strtotime( $current_date ), $store_timezone_string );
							date_default_timezone_set( $store_timezone_string );

							$fromtime = strtotime( $from );
							$from     = date( 'H:i', $offset + $fromtime );
							$from_exp = explode( ':', $from );
							if ( $weekday_cal ) {
								$newtimedata[ $i ]['rrule']['byhour']   = array( $from_exp[0] );
								$newtimedata[ $i ]['rrule']['byminute'] = array( $from_exp[1] );
							} else {
								$newtimedata[ $i ]['start'] = date( 'Y-m-d H:i:s', $offset + strtotime( $newtimedata[ $i ]['start'] ) );
								if ( ! $open_ended ) {
									$newtimedata[ $i ]['end'] = date( 'Y-m-d H:i:s', $offset + strtotime( $newtimedata[ $i ]['end'] ) );
								}
							}

							if ( ! $open_ended ) {
								$totime = strtotime( $to );
								$to     = date( 'H:i', $offset + $totime );
							}
							date_default_timezone_set( 'UTC' );
						}

						$newtimedata[ $i ]['extendedProps']['timeslot_value'] = ( $open_ended ) ? $from : $from . ' - ' . $to;

						if ( $mta_check ) {
							$manage_time_availability_data = $booking_settings['bkap_manage_time_availability'];
							usort( $manage_time_availability_data, 'bkap_sort_date_time_ranges_by_priority' );
							$newtimedata[ $i ]['extendedProps']['manage_time_availability'] = $manage_time_availability_data;
						}
						if ( ! $resources ) {
							array_push( $events, $newtimedata[ $i ] );
						}

						$i++;
					}
				}

				$bookable_item = $newtimedata;
			}

			if ( isset( $resources ) && is_array( $resources ) && count( $resources ) > 0 ) { // saparating when product having resource.

				$_resources      = ( isset( $args['resources'] ) && is_array( $args['resources'] ) ) ? $args['resources'] : '';
				$arg_resource    = explode( ',', $_resources );
				$i               = 0;
				$newresourcedata = array();

				$filter = isset( $args['filter'] ) ? $args['filter'] : '';

				foreach ( $resources as $res => $resource ) {
					if ( in_array( $res, $arg_resource ) || 'resources' !== $filter ) {
						if ( $push ) { // date.
							$bookable_item['extendedProps']['resources'] = $res . '=>' . $resource;
							array_push( $events, $bookable_item );
							$resource_push = true;
						} else { // time.
							foreach ( $bookable_item as $b => $b_i ) {

								$consider = apply_filters( 'bkap_resource_event_based_on_availability_data', true, $res, $bookable->ID, $b_i );
								if ( $consider ) {
									$newresourcedata[ $i ]                               = $b_i;
									$newresourcedata[ $i ]['extendedProps']['resources'] = $res . '=>' . $resource;
									array_push( $events, $newresourcedata[ $i ] );
									$resource_push = true;
									$i++;
								}
							}
						}
					}
				}
				if ( $resource_push ) {
					$push = false;
				}
			}

			// Handle variations for Products.
			if ( 'only_day' === $booking_type && 'variable' === $product_type ) {
				$product_variations = $_product->get_available_variations( 'object' );

				$titles = array();

				foreach ( $_product->get_attributes() as $product_attribute ) {
					$attribute_name           = $product_attribute->get_name();
					$attribute_key            = 'attribute_' . sanitize_title( $attribute_name );
					$titles[ $attribute_key ] = $attribute_name;
				}

				foreach ( $product_variations as $variation ) {
					$price          = $variation->get_price();
					$attributes     = $variation->get_variation_attributes();
					$variation_data = array();

					foreach ( $attributes as $title => $attribute ) {
						$unsanitized_title = $titles[ $title ];
						$variation_data[]  = array(
							'id'               => $variation->get_id(),
							'price'            => $price,
							$unsanitized_title => $attribute,
						);
					}

					if ( count( $variation_data ) > 0 ) {
						$bookable_item['extendedProps']['variation_data'] = $variation_data;
						array_push( $events, $bookable_item );
						$push = false;
					}
				}
			}

			// Add to collection array.
			if ( $push ) {
				array_push( $events, $bookable_item );
			}
		}

		// Availability.
		if ( ! in_array( $booking_type, array( 'duration_time' ) ) ) {

			$is_date_based = in_array( $booking_type, array( 'only_day', 'multiple_days', 'multidates' ) );
			$is_time_based = in_array( $booking_type, array( 'date_time', 'multidates_fixedtime' ) );

			foreach ( $events as &$event ) {

				$availability = array();

				// Get array of dates between start_date and end_date.
				$interval   = new DateInterval( 'P1D' );
				$date_start = new DateTime( $event['extendedProps']['start'] );
				$date_end   = new DateTime( $event['extendedProps']['end'] );
				$date_end->add( $interval );
				$period = new DatePeriod( $date_start, $interval, $date_end );

				foreach ( $period as $_date ) {

					$date  = $_date->format( 'Y-m-d' );
					$date_ = $_date->format( 'j-n-Y' );
					$post  = array(
						'post_id'      => (int) $event['id'],
						'date'         => $date,
						'checkin_date' => $date,
						'bkap_page'    => 'product',
						'cal_price'    => true,
					);

					// Resources.
					if ( isset( $event['extendedProps']['resources'] ) && '' !== $event['extendedProps']['resources'] ) {
						$exp                 = explode( '=>', $event['extendedProps']['resources'] );
						$post['resource_id'] = $exp[0];
					}

					// Variation.
					if ( isset( $event['extendedProps']['variation_data'] ) && '' !== $event['extendedProps']['variation_data'] && is_array( $event['extendedProps']['variation_data'] ) ) {
						$post['variation_id'] = $event['extendedProps']['variation_data'][0]['id'];
					}

					if ( $is_date_based ) {
						$availability[ $date_ ] = bkap_booking_process::bkap_date_lockout( $post );
					} elseif ( $is_time_based ) {
						$post['timeslot_value'] = ( isset( $event['extendedProps'] ) && isset( $event['extendedProps']['timeslot_value'] ) ) ? $event['extendedProps']['timeslot_value'] : '';
						$post['date_time_type'] = 'on';
						$availability[ $date_ ] = bkap_booking_process::bkap_get_time_lockout( $post );
					}
				}

				$event['extendedProps']['availability_data'] = $availability;
			}
		}

		// For issue 5026.
		if ( count( $last_event_date ) > 0 ) {
			$l_event_date = max( $last_event_date );
			$events       = array_map(
				function( $arr ) use ( $l_event_date ) {
					return $arr + array( 'last_event_date' => $l_event_date );
				},
				$events
			);
		}

		return $events;
	}
}
