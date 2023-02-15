<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Duration Based Bookings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Duration-time
 * @category Classes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_Duration_Time' ) ) {

	/**
	 * Class for Duration Based Booking
	 */
	class Bkap_Duration_Time {

		/**
		 * Construct
		 *
		 * @since 4.10.0
		 */
		public function __construct() {
			add_action( 'bkap_before_booking_form', array( &$this, 'bkap_duration_time_field' ), 5, 3 );
		}

		/**
		 * This function will get all the available durations and display.
		 *
		 * @param string         $current_date - Date for which booking is being placed.
		 * @param string|integer $post_id - Product ID
		 *
		 * @since 4.10.0
		 */

		public static function bkap_duration_time_field( $product_id, $bkap_setting, $hidden_dates ) {

			if ( isset( $bkap_setting['booking_enable_time'] ) && $bkap_setting['booking_enable_time'] == 'duration_time' ) {

				if ( ! empty( $bkap_setting['bkap_duration_settings'] ) && count( $bkap_setting['bkap_duration_settings'] ) > 0 ) {
					$d_sttg = $bkap_setting['bkap_duration_settings'];

					$d_label  = $d_sttg['duration_label'];
					$duration = $d_sttg['duration'];
					$d_min    = ( $d_sttg['duration_min'] == '' ) ? 1 : $d_sttg['duration_min'];

					$d_val = ( isset( $hidden_dates['duration_selected'] ) && $hidden_dates['duration_selected'] != '' ) ? $hidden_dates['duration_selected'] : $d_min;

					$d_max            = $d_sttg['duration_max'];
					$d_max_booking    = $d_sttg['duration_max_booking'];
					$d_price          = $d_sttg['duration_price'];
					$d_first_duration = $d_sttg['first_duration'];
					$d_type           = $d_sttg['duration_type'];
					$d_step           = apply_filters( 'bkap_step_attribute_for_duration_field', 1, $product_id, $bkap_setting );
					$d_extra_attr     = apply_filters( 'bkap_extra_attributes_for_duration_field', '', $product_id, $bkap_setting );

					$duration_type = ( 'hours' === $d_type ) ? __( 'Hour(s)', 'woocommerce-booking' ) : __( 'Min(s)', 'woocommerce-booking' );

					if ( '' === $d_label ) {
						$d_label = __( 'Duration', 'woocommerce-booking' );
					}
					/* translators: %s: Duration */
					$into_hr_min = sprintf( __( 'x %1$s %2$s', 'woocommerce-booking' ), $duration, $duration_type );
					$into_hr_min = apply_filters( 'bkap_hour_min_text_for_duration_field', $into_hr_min, $duration, $duration_type, $product_id, $bkap_setting );
					?>
					<p class="bkap_duration_section">
						<label for="bkap_duration_lable" style="display: block;"><?php echo sprintf( __( '%s :', 'woocommerce-booking' ), $d_label ); ?></label>
						<input 	type="number"
								id="bkap_duration_field"
								name="bkap_duration_field"
								<?php echo $d_extra_attr; ?>
								style="width: 90px;"
								min="<?php echo esc_attr( $d_min ); ?>"
								max="<?php echo esc_attr( $d_max ); ?>"
								step="<?php echo esc_attr( $d_step ); ?>"
								value="<?php echo esc_attr( $d_val ); ?>"/>
						<?php echo esc_html( $into_hr_min ); ?>
					</p>
					<?php

					do_action( 'bkap_after_duration_field_on_front', $product_id, $bkap_setting );
				}
			}
		}

		/**
		 * This function will get all the available durations and display.
		 *
		 * @param string         $current_date - Date for which booking is being placed.
		 * @param string|integer $post_id - Product ID
		 *
		 * @since 4.10.0
		 */

		public static function get_duration_time_slot( $current_date, $post_id ) {

			$bkap_setting = bkap_setting( $post_id );

			// Filter out the settings if required.
			$bkap_setting = apply_filters( 'bkap_product_duration_settings', $bkap_setting, $post_id, $current_date );

			if ( isset( $_POST['date_time_type'] ) && $_POST['date_time_type'] == 'duration_time' ) {

				$selected_date     = strtotime( $current_date ); // timestamp of selected date
				$d_setting         = $bkap_setting['bkap_duration_settings'];
				$base_interval     = (int) $d_setting['duration']; // 2 Hour set for product
				$duration_type     = $d_setting['duration_type']; // Type of Duration set for product Hours/mins
				$duration_gap      = (int) $d_setting['duration_gap']; // 2 Hour set for product
				$duration_gap_type = $d_setting['duration_gap_type']; // Type of Duration set for product Hours/mins
				$selected_duration = (int) $_POST['seleced_duration']; // Entered value on front end : 1
				$interval          = $selected_duration * $base_interval; // Total hours/mins based on selected duration and set duration : 2
				$resource_id       = isset( $_POST['resource_id'] ) ? $_POST['resource_id'] : ''; // Id of selected Resource

				if ( 'hours' === $duration_type ) {
					$interval      = $interval * 3600;
					$base_interval = $base_interval * 3600;
				} else {
					$interval      = $interval * 60;
					$base_interval = $base_interval * 60;
				}

				if ( 'hours' === $duration_gap_type ) {
					$duration_gap = $duration_gap * 3600;
				} else {
					$duration_gap = $duration_gap * 60;
				}

				$first_duration = $d_setting['first_duration'];
				$from           = strtotime( $first_duration ? $first_duration : 'midnight', $selected_date );
				$end_duration   = $d_setting['end_duration'];
				$to             = strtotime( $end_duration ? $end_duration : '+1 day', $selected_date );

				$blocks = self::bkap_display_availabile_blocks(
					$post_id,
					$selected_date,
					$from,
					$to,
					array( $interval, $base_interval, $duration_gap ),
					$resource_id,
					$duration_type
				);

				if ( isset( $bkap_setting['bkap_manage_time_availability'] ) && ! empty( $bkap_setting['bkap_manage_time_availability'] ) ) {
					$mta_availability_data = $bkap_setting['bkap_manage_time_availability'];
					if ( is_array( $mta_availability_data ) && count( $mta_availability_data ) > 0 ) {
						$blocks = bkap_filter_time_based_on_resource_availability(
							$current_date,
							$mta_availability_data,
							$blocks,
							array(
								'type'     => 'duration_time',
								'interval' => $interval,
							),
							0,
							$post_id,
							$bkap_setting
						);
					}
				}

				if ( '' !== $resource_id ) {

					$resource_id = explode( ',', $resource_id );
					$_blocks     = array();

					foreach ( $resource_id as $id ) {

						$resource                   = new BKAP_Product_Resource( $id, $post_id );
						$r_availability             = $resource->get_resource_qty();
						$resource_availability_data = $resource->get_resource_availability();

						if ( is_array( $resource_availability_data ) && count( $resource_availability_data ) > 0 ) {
							if ( isset( $bkap_setting['bkap_all_data_unavailable'] ) && 'on' === $bkap_setting['bkap_all_data_unavailable'] ) {
								$resource_availability_data = $mta_availability_data;
							}

							$_blocks[ $id ] = array_filter(
								bkap_filter_time_based_on_resource_availability(
									$current_date,
									$resource_availability_data,
									$blocks,
									array(
										'type'     => 'duration_time',
										'interval' => $interval,
									),
									$id,
									$post_id,
									$bkap_setting
								)
							);
						}
					}

					if ( count( $_blocks ) > 0 ) {
						$blocks = bkap_common::return_unique_array_values( $_blocks, count( $resource_id ) );
					}
				}

				$html_blocks = self::bkap_display_availabile_blocks_html(
					$post_id,
					$blocks,
					$from,
					$to,
					array( $interval, $base_interval ),
					$resource_id,
					$duration_type
				);

				$wp_send_json['bkap_time_count']    = 0;
				$wp_send_json['bkap_time_dropdown'] = $html_blocks;
				wp_send_json( $wp_send_json );
			}
		}

		/**
		 * This function will calculate the timmstamp for the durations based on the intervals set.
		 *
		 * @param int    $product_id - Product ID
		 * @param string $selected_date - selected date in the booking calendar
		 * @param int    $from timestamp of the start duration
		 * @param int    $to - end timestamp based on the duration settings
		 * @param array  $intervals - Contains value in seconds for selected interval and base interval
		 * @param int    $resource_id - Resource ID
		 * @param string $duration_type - Type of duration
		 *
		 * @return array $blocks Array of timestamps based on the duration for selected date.
		 *
		 * @since 4.10.0
		 */

		public static function bkap_display_availabile_blocks( $product_id, $selected_date, $from, $to, $intervals, $resource_id, $duration_type ) {

			$blocks           = array();
			$start_next_day   = $to;
			$current_time     = current_time( 'timestamp' );
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			$advance_booking_hrs = bkap_advance_booking_hrs( $booking_settings, $product_id );

			$advance_period = $advance_booking_hrs * 3600;
			$advance_period = $advance_period + $current_time;

			$consider_end_duration = apply_filters( 'bkap_consider_end_duration_at_value', true );

			if ( $booking_settings['bkap_duration_settings']['end_duration'] != '' && $consider_end_duration ) {
				$int            = $intervals[0] - $intervals[1];
				$start_next_day = $start_next_day - $int;
			}

			$intervals[1] = $intervals[1] + $intervals[2];

			for ( $i = $from; $i <= $start_next_day; $i += $intervals[1] ) {
				if ( $i > $advance_period ) {
					$blocks[] = $i;
				}
			}

			if ( date( 'Y-m-d', $selected_date ) != date( 'Y-m-d', $start_next_day ) ) {
				array_pop( $blocks );
			}

			return $blocks;
		}

		/**
		 * This function will calculate the timmstamp for the durations based on the intervals set.
		 *
		 * @param int    $product_id - Product ID
		 * @param array  $blocks - Array of timestamps based on the duration for selected date.
		 * @param int    $from timestamp of the start duration
		 * @param int    $to - end timestamp based on the duration settings
		 * @param array  $intervals - Contains value in seconds for selected interval and base interval
		 * @param int    $resource_id - Resource ID
		 * @param string $duration_type - Type of duration
		 * @param string $called_for - value will be display/backend - display is used for display purpose and backend is for calculations
		 *
		 * @return mixed $block_html if $called for is 'backend' then return array of timestamp which are available and if display then returns html of available duration for the selected date.
		 *
		 * @since 4.10.0
		 */

		public static function bkap_display_availabile_blocks_html( $product_id, $blocks, $from, $to, $intervals, $resource_id, $duration_type, $called_for = 'display' ) {

			$booking   = get_bookings_for_range( $product_id, $from, $to, true, $resource_id );
			$d_setting = get_post_meta( $product_id, '_bkap_duration_settings', true );

			// $booking_keys     = array_keys( $booking );
			$duration_booked = array(); // initializing array for timestamp and booking qty.
			$block_with_qty  = array();

			$check = true;
			if ( $called_for == 'display' && isset( $_POST['bkap_page'] ) && ( $_POST['bkap_page'] == 'cart' || $_POST['bkap_page'] == 'checkout' ) ) {
				$check = false;
			}

			if ( $check ) {
				$bkap_cart_check = bkap_cart_check_for_duration( $product_id, date( 'j-n-Y', $from ) );

				if ( count( $bkap_cart_check ) > 0 ) {

					foreach ( $bkap_cart_check as $k => $v ) {
						if ( array_key_exists( $k, $booking ) ) {
							$booking[ $k ] += $v;
						} else {
							$booking[ $k ] = $v;
						}
					}
				}
			}

			$d_max_booking = $d_setting['duration_max_booking'];

			if ( '' !== $resource_id && is_array( $resource_id ) ) {
				$d_max_booking = Class_Bkap_Product_Resource::compute_maximum_booking( $resource_id, date( 'Y-m-d', $from ), $product_id, bkap_setting( $product_id ) );
			}

			if ( count( $booking ) > 0 ) {

				foreach ( $blocks as $key => $value ) {

					$available            = true;
					$start_to_end_minutes = bkap_get_between_timestamp( $value + 60, $value + $intervals[0] - 60 );

					$bkap_unique = array_unique( array_intersect_key( $booking, array_flip( $start_to_end_minutes ) ) );

					if ( count( $bkap_unique ) > 0 ) {
						$qty = max( $bkap_unique );

						if ( $qty ) {

							if ( $d_max_booking != '' && $d_max_booking != 0 ) {
								$duration_booked[ $value ] = $d_max_booking - (int) $qty;
							}

							if ( $d_max_booking != '' && $d_max_booking != 0 && (int) $qty >= $d_max_booking ) {
								$available = false;
							}
						}
					}

					if ( ! $available ) {
						unset( $blocks[ $key ] );
					}
				}
			}

			if ( isset( $_POST['bkap_page'] ) && 'view-order' == $_POST['bkap_page'] ) {

				if ( isset( $_POST['view_item_id'] ) && '' != $_POST['view_item_id'] ) {
					$booking_id = bkap_common::get_booking_id( $_POST['view_item_id'] );

					if ( is_array( $booking_id ) ) {

						foreach ( $booking_id as $key => $id ) {
							$blocks = self::bkap_add_time_slot_on_bookingpage_or_vieworder( $blocks, $_POST['post_id'], $id, $_POST['current_date'] );
						}
					} else {
						$blocks = self::bkap_add_time_slot_on_bookingpage_or_vieworder( $blocks, $_POST['post_id'], $booking_id, $_POST['current_date'] );
					}
				}
			}

			if ( $called_for == 'backend' ) {
				return array(
					'blocks'          => $blocks,
					'duration_booked' => $duration_booked,
				);
			} else {
				$block_html = self::bkap_display_durations( $blocks, $duration_booked, $d_max_booking );
				return $block_html;
			}
		}

		/**
		 * This function will add block to the list if not available. This helps to see the booked duration incase if max booking is set to 1.
		 *
		 * @param array  $blocks - Array of available timestamps based on the duration for selected date.
		 * @param int    $product_id - Product ID.
		 * @param int    $booking_id - Booking ID.
		 * @param string $current_date - Selected Date.
		 *
		 * @since 5.15.0
		 */
		public static function bkap_add_time_slot_on_bookingpage_or_vieworder( $blocks, $product_id, $booking_id, $current_date ) {

			$booking        = new BKAP_Booking( $booking_id );
			$times_selected = explode( '-', $booking->get_time() );
			$strtotime      = strtotime( $current_date . ' ' . $times_selected[0] );

			if ( ! in_array( $strtotime, $blocks ) ) {
				array_push( $blocks, $strtotime );
				sort( $blocks );
			}

			return $blocks;
		}

		/**
		 * This function will prepare the html element based on the available duration for date
		 *
		 * @param array $blocks - Array of available timestamps based on the duration for selected date.
		 * @param array $duration_booked - Array of timestamps with its availablility
		 *
		 * @return mixed $block_html Returns html of available durations for the selected date.
		 *
		 * @since 4.10.0
		 */

		public static function bkap_display_durations( $blocks, $duration_booked, $max_booking ) {

			// Displaying duration time on the front end of the product.
			$time_lable  = apply_filters( 'bkap_change_book_time_label', bkap_option( 'time' ) );
			$block_html  = '<label id="bkap_book_time">' . $time_lable . ':</label>';
			$block_html  = apply_filters( 'bkap_change_book_time_label_section', $block_html );
			$block_html .= '<ul class="bkap-duration-block">';

			$global_settings   = bkap_global_setting();
			$show_availability = false;
			if ( isset( $global_settings->booking_availability_display ) && $global_settings->booking_availability_display == 'on' ) {
				$show_availability = true;
			}
			$show_availability = apply_filters( 'bkap_show_availability_in_duration_blocks', $show_availability );

			if ( count( $blocks ) > 0 ) {

				foreach ( $blocks as $block ) {

					if ( in_array( $block, array_keys( $duration_booked ) ) && $show_availability ) {

						$block_html .=
						'<li class="bkap_block" data-block="' . esc_attr( date( 'Hi', $block ) ) . '">
					 	<a href="#" data-value="' . date( 'H:i', $block ) . '">' . date_i18n( bkap_common::bkap_get_time_format(), $block ) . '<small style="display:block;" class="booking-spaces-left">(' . sprintf( _n( '%d left', '%d left', $duration_booked[ $block ], 'woocommerce-booking' ), absint( $duration_booked[ $block ] ) ) . ')</small>
					 	</a></li>';
					} elseif ( $max_booking > 0 && $show_availability ) {
						$block_html .= '<li class="bkap_block" data-block="' . esc_attr( date( 'Hi', $block ) ) . '"><a href="#" data-value="' . date( 'H:i', $block ) . '">' . date_i18n( bkap_common::bkap_get_time_format(), $block ) . '<small style="display:block;" class="booking-spaces-left">' . sprintf( _n( '%d Booking(s)', '%d Booking(s)', $max_booking, 'woocommerce-booking' ), absint( $max_booking ) ) . '</small></a></li>';
					} else {
						$block_html .= '<li class="bkap_block" data-block="' . esc_attr( date( 'Hi', $block ) ) . '"><a href="#" data-value="' . date( 'H:i', $block ) . '">' . date_i18n( bkap_common::bkap_get_time_format(), $block ) . '</a></li>';

					}
				}
			} else {
				$unavailable = __( 'No bookings available for selected date', 'woocommerce-booking' );
				$block_html .= '<li>' . $unavailable . '</li>';
			}

			$block_html .= '</ul>';

			return $block_html;
		}
	} // end of class
	$bkap_duration_time = new Bkap_Duration_Time();
} // end if
?>
