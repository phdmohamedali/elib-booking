<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Bulk Booking Settings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Bulk-Booking-Settings
 * @category Classes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_Bulk_Booking_Settings' ) ) {

	/**
	 * Class for Bulk Booking Settings.
	 */
	class Bkap_Bulk_Booking_Settings {

		/**
		 * Constructor.
		 *
		 * @since 4.16.0
		 */
		public function __construct() {
			add_action( 'bulk_booking_settings_section', array( $this, 'bulk_booking_settings_section_callback' ) );
			add_action( 'bkap_meta_box_bulk_booking', array( $this, 'bkap_meta_box_bulk_booking_callback' ) );
		}

		/**
		 * This function will delete the saved default booking options.
		 *
		 * @since 5.13.0
		 */
		public static function bkap_clear_defaults() {

			delete_option( 'bkap_default_booking_settings' );
			delete_option( 'bkap_default_individual_booking_settings' );
		}

		/**
		 * This function will execute the code for action added on the Bulk booking page.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_execute_data() {

			if ( isset( $_POST['bulk_execution_data'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification

				$bulk_execution_data = array();
				$post_execution_data = $_POST['bulk_execution_data']; // phpcs:ignore
				$temp_data           = str_replace( '\\', '', $post_execution_data );
				$bulk_execution_data = (array) json_decode( $temp_data );

				$all_bookable_products = isset( $_POST['bkap_all_bookable_products'] ) ? sanitize_text_field( wp_unslash( $_POST['bkap_all_bookable_products'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
				$selected_products     = isset( $_POST['bkap_selected_product'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['bkap_selected_product'] ) ) : array(); // phpcs:ignore WordPress.Security.NonceVerification
				if ( in_array( 'all_bookable_products', $selected_products, true ) ) { // If all product is selected then get all product ids.
					$all_product_ids   = $all_bookable_products;
					$selected_products = explode( ',', $all_product_ids );
				}

				$selected_product_count = count( $selected_products );
				$count                  = 0;

				foreach ( $selected_products as $pkey => $product_id ) { // looping through all the selected products.

					$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );

					foreach ( $bulk_execution_data as $ekey => $evalue ) {

						$booking_settings = bkap_setting( $product_id ); // Booking settings.

						switch ( $evalue->bulk_action ) {
							case 'add':
								self::bkap_bulk_add_execution( $product_id, $booking_settings, $evalue, $booking_type );
								break;
							case 'update':
								self::bkap_bulk_update_execution( $product_id, $booking_settings, $evalue, $booking_type );
								break;
							case 'delete':
								self::bkap_bulk_delete_execution( $product_id, $booking_settings, $evalue, $booking_type );
								break;
						}
					}
					$count++;
				}

				if ( $count === $selected_product_count ) {
					echo 'Added actions are executed successfully for the selected product.';
				}
			}

			die();
		}

		/**
		 * Updating already available availability data of the product.
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of Booking.
		 * @since 4.16.0
		 */
		public static function bkap_bulk_update_execution( $product_id, $booking_settings, $execution_data, $booking_type ) {

			switch ( $booking_type ) {
				case 'only_day':
					self::bkap_bulk_update_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'date_time':
					if ( '' !== $execution_data->bulk_from_time ) {
						self::bkap_bulk_update_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type );
					} else {
						self::bkap_bulk_update_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					}
					break;
				case 'duration_time':
					self::bkap_bulk_update_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'multiple_days':
					self::bkap_bulk_update_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
			}
		}

		/**
		 * This function is for update action of date & time booking type.
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of Booking.
		 * @since 4.16.0
		 */
		public static function bkap_bulk_update_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type ) {

			if ( '' !== $execution_data->bulk_from_time ) {

				$bulk_day_date_value   = $execution_data->bulk_day_date_value;
				$booking_time_settings = $booking_settings['booking_time_settings'];
				$settings_data         = array( '_bkap_time_settings' => array() );

				$from_time_explode = explode( ':', $execution_data->bulk_from_time );
				if ( '' === $execution_data->bulk_to_time ) {
					$to_time_explode = explode( ':', '0:00' );
				} else {
					$to_time_explode = explode( ':', $execution_data->bulk_to_time );
				}

				switch ( $execution_data->bulk_day_date ) {

					case 'day':
						$booking_recurring = array();
						$recurring_lockout = array();
						$recurring_prices  = array();

						if ( in_array( 'all', $bulk_day_date_value, true ) ) { // if user selected all.

							/* Enabling the weekday if its not enable */
							for ( $i = 0; $i <= 6; $i++ ) {
								$weekday_name = "booking_weekday_$i";

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {

									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {

										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											$booking_time_settings[ $weekday_name ][ $key ]['slot_price']   = $execution_data->bulk_slot_price;
											$booking_time_settings[ $weekday_name ][ $key ]['lockout_slot'] = $execution_data->bulk_lockout_slot;

											$settings_data['_bkap_time_settings'][ $weekday_name ] = array();
											array_push( $settings_data['_bkap_time_settings'][ $weekday_name ], $booking_time_settings[ $weekday_name ][ $key ] );
											break;
										}
									}
								}
							}
						} else {

							foreach ( $bulk_day_date_value as $key => $value ) {
								$weekday_name = "booking_weekday_$value";

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {
									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {
										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											$booking_time_settings[ $weekday_name ][ $key ]['slot_price']   = $execution_data->bulk_slot_price;
											$booking_time_settings[ $weekday_name ][ $key ]['lockout_slot'] = $execution_data->bulk_lockout_slot;
											$settings_data['_bkap_time_settings'][ $weekday_name ]          = array();
											array_push( $settings_data['_bkap_time_settings'][ $weekday_name ], $booking_time_settings[ $weekday_name ][ $key ] );
											break;
										}
									}
								}
							}
						}

						break;
					case 'date':
						$specific_dates        = explode( ',', $bulk_day_date_value );
						$booking_specific_date = $booking_settings['booking_specific_date'];
						$date_price            = array();

						foreach ( $specific_dates as $key => $value ) {
							if ( isset( $booking_specific_date[ $value ] ) ) {
								$booking_specific_date[ $value ] = $execution_data->bulk_lockout_slot;
								$date_price[ $value ]            = $execution_data->bulk_slot_price;
							}

							if ( isset( $booking_time_settings[ $value ] ) ) { // product already having the date settings.
								foreach ( $booking_time_settings[ $value ] as $k => $v ) {
									if ( $v['from_slot_hrs'] === $from_time_explode[0]
										&& $v['from_slot_min'] === $from_time_explode[1]
										&& $v['to_slot_hrs'] === $to_time_explode[0]
										&& $v['to_slot_min'] === $to_time_explode[1]
									) {
										$booking_time_settings[ $value ][ $k ]['lockout_slot'] = $execution_data->bulk_lockout_slot;
										$booking_time_settings[ $value ][ $k ]['slot_price']   = $execution_data->bulk_slot_price;

										$settings_data['_bkap_time_settings'][ $value ] = array();
										array_push( $settings_data['_bkap_time_settings'][ $value ], $booking_time_settings[ $value ][ $k ] );
										break;
									}
								}
							}
						}
						break;
				}

				if ( count( $settings_data['_bkap_time_settings'] ) > 0 ) {
					$booking_settings['booking_time_settings'] = $booking_time_settings;
					$settings_data['_bkap_time_settings']      = $booking_time_settings;
					$booking_box_class                         = new bkap_booking_box_class();
					$booking_box_class->update_bkap_history_date_time( $product_id, $settings_data );
					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
					update_post_meta( $product_id, '_bkap_time_settings', $booking_time_settings );
				}
			}
		}

		/**
		 * Updating the data for the selected day date ; Single Day
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of Booking.
		 */
		public static function bkap_bulk_update_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type ) {

			$bulk_day_date_value = $execution_data->bulk_day_date_value;

			switch ( $execution_data->bulk_day_date ) {
				case 'day':
					$booking_recurring = array();
					$recurring_lockout = array();
					$recurring_prices  = array();
					$settings_data     = array();

					$old_lockout = get_post_meta( $product_id, '_bkap_recurring_lockout', true );

					if ( in_array( 'all', $bulk_day_date_value, true ) ) {

						/* Enabling the weekday if its not enable */
						for ( $i = 0; $i <= 6; $i++ ) {
							$weekday_name = "booking_weekday_$i";

							if ( 'on' === $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$recurring_lockout[ $weekday_name ] = $execution_data->bulk_lockout_slot;
								$recurring_prices[ $weekday_name ]  = $execution_data->bulk_slot_price;
							}
						}
					} else {

						foreach ( $bulk_day_date_value as $key => $value ) {
							$weekday_name = "booking_weekday_$value";
							if ( 'on' === $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$recurring_lockout[ $weekday_name ] = $execution_data->bulk_lockout_slot;
								$recurring_prices[ $weekday_name ]  = $execution_data->bulk_slot_price;
							}
						}
					}

					$new_special_price = self::bkap_bulk_special_price( $product_id, $booking_settings, $recurring_prices, 'day' );

					$settings_data['_bkap_recurring_weekdays'] = $booking_settings['booking_recurring'];
					$settings_data['_bkap_specific_dates']     = array();

					if ( 'only_day' === $booking_type || 'date_time' === $booking_type ) {
						$new_lockout = array_merge( $old_lockout, $recurring_lockout );

						$booking_settings['booking_recurring_lockout'] = $new_lockout;
						$settings_data['_bkap_recurring_lockout']      = $new_lockout;
						update_post_meta( $product_id, '_bkap_recurring_lockout', $new_lockout );
						if ( 'only_day' === $booking_type ) {
							$booking_box_class = new bkap_booking_box_class();
							$booking_box_class->update_bkap_history_only_days( $product_id, $settings_data ); // Updating records..
						}
					}

					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
					update_post_meta( $product_id, '_bkap_special_price', $new_special_price );

					break;
				case 'date':
					$specific_dates        = explode( ',', $bulk_day_date_value );
					$booking_specific_date = $booking_settings['booking_specific_date'];
					$date_price            = array();
					$settings_data         = array();

					foreach ( $specific_dates as $key => $value ) {
						if ( isset( $booking_specific_date[ $value ] ) ) {
							$booking_specific_date[ $value ] = $execution_data->bulk_lockout_slot;
							$date_price[ $value ]            = $execution_data->bulk_slot_price;
						}
					}

					if ( 'multiple_days' !== $booking_type ) { // do not add specific date in db when product type is multiple nights.
						$booking_settings['booking_specific_date'] = $booking_specific_date;
					}

					$settings_data['_bkap_specific_dates']     = $booking_specific_date; // got old plus new specifc date with lockout.
					$settings_data['_bkap_recurring_lockout']  = array();
					$settings_data['_bkap_recurring_weekdays'] = array();

					/* Genrating the special price data based on new specific date price*/
					$new_special_price = self::bkap_bulk_special_price( $product_id, $booking_settings, $date_price, 'date' );

					if ( 'only_day' === $booking_type ) {
						$booking_box_class = new bkap_booking_box_class();
						$booking_box_class->update_bkap_history_only_days( $product_id, $settings_data ); // Updating records..
					}
					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );

					if ( 'multiple_days' !== $booking_type ) {
						update_post_meta( $product_id, '_bkap_specific_dates', $booking_specific_date );
					}

					update_post_meta( $product_id, '_bkap_special_price', $new_special_price );

					break;
			}
		}

		/**
		 * Adding availability to selected product.
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_add_execution( $product_id, $booking_settings, $execution_data, $booking_type ) {

			switch ( $booking_type ) {
				case 'only_day':
					self::bkap_bulk_add_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'date_time':
					if ( '' !== $execution_data->bulk_from_time ) {
						self::bkap_bulk_add_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type );
					} else {
						self::bkap_bulk_add_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					}
					break;
				case 'duration_time':
					self::bkap_bulk_add_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'multiple_days':
					self::bkap_bulk_add_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
			}
		}

		/**
		 * Adding the availability for Single Day booking type.
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_add_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type ) {

			$bulk_day_date_value = $execution_data->bulk_day_date_value;

			switch ( $execution_data->bulk_day_date ) {
				case 'day':
					$booking_recurring = array();
					$recurring_lockout = array();
					$recurring_prices  = array();
					$settings_data     = array();

					$old_lockout = get_post_meta( $product_id, '_bkap_recurring_lockout', true );

					if ( in_array( 'all', $bulk_day_date_value, true ) ) {

						/* Enabling the weekday if its not enable */
						for ( $i = 0; $i <= 6; $i++ ) {
							$weekday_name = "booking_weekday_$i";

							if ( 'on' !== $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$booking_recurring[ $weekday_name ] = 'on';
								$recurring_lockout[ $weekday_name ] = $execution_data->bulk_lockout_slot;
								$recurring_prices[ $weekday_name ]  = $execution_data->bulk_slot_price;
							}
						}
					} else {

						foreach ( $bulk_day_date_value as $key => $value ) {
							$weekday_name = "booking_weekday_$value";
							if ( 'on' !== $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$booking_recurring[ $weekday_name ] = 'on';
								$recurring_lockout[ $weekday_name ] = $execution_data->bulk_lockout_slot;
								$recurring_prices[ $weekday_name ]  = $execution_data->bulk_slot_price;
							}
						}
					}

					$new_recurring     = array_merge( $booking_settings['booking_recurring'], $booking_recurring );
					$new_special_price = self::bkap_bulk_special_price( $product_id, $booking_settings, $recurring_prices, 'day' );

					if ( isset( $booking_settings['booking_recurring_booking'] ) && '' === $booking_settings['booking_recurring_booking'] ) {
						if ( in_array( 'on', $new_recurring, true ) ) {
							$booking_settings['booking_recurring_booking'] = 'on';
							update_post_meta( $product_id, '_bkap_enable_recurring', 'on' );
						}
					}

					// Changing the infomration in booking settings.
					$booking_settings['booking_recurring']     = $new_recurring;
					$settings_data['_bkap_recurring_weekdays'] = $new_recurring;
					$settings_data['_bkap_specific_dates']     = array();

					if ( 'only_day' === $booking_type || 'date_time' === $booking_type ) {
						$new_lockout                                   = array_merge( $old_lockout, $recurring_lockout );
						$booking_settings['booking_recurring_lockout'] = $new_lockout;
						$settings_data['_bkap_recurring_lockout']      = $new_lockout;
						update_post_meta( $product_id, '_bkap_recurring_lockout', $new_lockout );

						if ( 'only_day' === $booking_type ) {
							$booking_box_class = new bkap_booking_box_class();
							$booking_box_class->update_bkap_history_only_days( $product_id, $settings_data ); // Updating records..
						}
					}

					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
					update_post_meta( $product_id, '_bkap_recurring_weekdays', $new_recurring );
					update_post_meta( $product_id, '_bkap_special_price', $new_special_price );

					break;
				case 'date':
					$specific_dates        = explode( ',', $bulk_day_date_value );
					$booking_specific_date = $booking_settings['booking_specific_date'];
					$date_price            = array();
					$settings_data         = array();

					foreach ( $specific_dates as $key => $value ) {
						if ( ! isset( $booking_specific_date[ $value ] ) ) {
							$booking_specific_date[ $value ] = $execution_data->bulk_lockout_slot;
							$date_price[ $value ]            = $execution_data->bulk_slot_price;
						}
					}

					if ( 'multiple_days' !== $booking_type ) { // do not add specific date in db when product type is multiple nights.
						$booking_settings['booking_specific_date'] = $booking_specific_date;
					}

					$settings_data['_bkap_specific_dates']     = $booking_specific_date; // got old plus new specifc date with lockout.
					$settings_data['_bkap_recurring_lockout']  = array();
					$settings_data['_bkap_recurring_weekdays'] = array();

					if ( '' === $booking_settings['booking_specific_booking'] && ( ! empty( $booking_specific_date ) ) ) {
						$booking_settings['booking_specific_booking'] = 'on'; // enabled the specific date option is specific date are available.
						update_post_meta( $product_id, '_bkap_enable_specific', 'on' );
					}

					/* Genrating the special price data based on new specific date price*/
					$new_special_price = self::bkap_bulk_special_price( $product_id, $booking_settings, $date_price, 'date' );

					if ( 'only_day' === $booking_type ) {
						$booking_box_class = new bkap_booking_box_class();
						$booking_box_class->update_bkap_history_only_days( $product_id, $settings_data ); // Updating records..
					}
					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );

					if ( 'multiple_days' !== $booking_type ) {
						update_post_meta( $product_id, '_bkap_specific_dates', $booking_specific_date );
					}

					update_post_meta( $product_id, '_bkap_special_price', $new_special_price );

					break;
			}
		}

		/**
		 * Getting and adding new special price infomration in the post meta.
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param array  $recurring_prices Array of recurring prices.
		 * @param string $daydate Date or Day.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_special_price( $product_id, $booking_settings, $recurring_prices, $daydate ) {
			// Get the existing record.
			$booking_special_prices = get_post_meta( $product_id, '_bkap_special_price', true );

			switch ( $daydate ) {
				case 'day':
					foreach ( $recurring_prices as $k => $v ) {
						$found = false;
						foreach ( $booking_special_prices as $key => $value ) {
							if ( $value['booking_special_weekday'] === $k ) {
								$booking_special_prices[ $key ]['booking_special_price'] = $v;
								$found = true;
								break;
							}
						}
						if ( ! $found ) {

							if ( empty( $booking_special_prices ) ) {
								$cnt = 0;
							} else {
								$cnt = max( array_keys( $booking_special_prices ) ) + 1; // calculation new key.
							}
							$booking_special_prices[ $cnt ]['booking_special_weekday'] = $k;
							$booking_special_prices[ $cnt ]['booking_special_date']    = '';
							$booking_special_prices[ $cnt ]['booking_special_price']   = $v;
						}
					}
					break;
				case 'date':
					foreach ( $recurring_prices as $k => $v ) {
						$found = false;
						foreach ( $booking_special_prices as $key => $value ) {
							if ( strtotime( $value['booking_special_date'] ) === strtotime( $k ) ) {
								$booking_special_prices[ $key ]['booking_special_price'] = $v;
								$found = true;
								break;
							}
						}

						if ( ! $found ) {

							if ( empty( $booking_special_prices ) ) {
								$cnt = 0;
							} else {
								$cnt = max( array_keys( $booking_special_prices ) ) + 1; // calculation new key.
							}
							$booking_special_prices[ $cnt ]['booking_special_weekday'] = '';
							$booking_special_prices[ $cnt ]['booking_special_date']    = date( 'Y-m-d', strtotime( $k ) );
							$booking_special_prices[ $cnt ]['booking_special_price']   = $v;
						}
					}
					break;
			}

			return $booking_special_prices;
		}

		/**
		 * Adding availability to date and time product
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_add_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type ) {

			if ( '' !== $execution_data->bulk_from_time ) {

				$bulk_day_date_value   = $execution_data->bulk_day_date_value;
				$booking_time_settings = $booking_settings['booking_time_settings'];

				$from_time_explode = explode( ':', $execution_data->bulk_from_time );
				if ( '' === $execution_data->bulk_to_time ) {
					$to_time_explode = explode( ':', '0:00' );
				} else {
					$to_time_explode = explode( ':', $execution_data->bulk_to_time );
				}

				switch ( $execution_data->bulk_day_date ) {
					case 'day':
						$booking_recurring = array();
						$recurring_lockout = array();
						$recurring_prices  = array();
						$settings_data     = array();

						if ( in_array( 'all', $bulk_day_date_value, true ) ) { // if user selected all.

							/* Enabling the weekday if its not enable */
							for ( $i = 0; $i <= 6; $i++ ) {
								$weekday_name = "booking_weekday_$i";

								if ( 'on' !== $booking_settings['booking_recurring'][ $weekday_name ] ) {
									$booking_recurring[ $weekday_name ] = 'on';
								}

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {

									// data available in time setting is sunday and monday 10:00 12:00 12:00 to 14:00
									// Adding new data is sunday 14:00 to 16:00.
									$found = false;
									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {
										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											$found = true;
											break;
										}
									}

									if ( ! $found ) { // This mean the timeslot is not present in the booking time settings.
										$time['from_slot_hrs']     = $from_time_explode[0];
										$time['from_slot_min']     = $from_time_explode[1];
										$time['to_slot_hrs']       = $to_time_explode[0];
										$time['to_slot_min']       = $to_time_explode[1];
										$time['booking_notes']     = $execution_data->bulk_note;
										$time['slot_price']        = $execution_data->bulk_slot_price;
										$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
										$time['global_time_check'] = false;
										array_push( $booking_time_settings[ $weekday_name ], $time );
									}
								} else {
									// This mean any data for the weekday is not present.
									// add key as weekday and timeslot array to booking_time_settings.

									$time['from_slot_hrs']     = $from_time_explode[0];
									$time['from_slot_min']     = $from_time_explode[1];
									$time['to_slot_hrs']       = $to_time_explode[0];
									$time['to_slot_min']       = $to_time_explode[1];
									$time['booking_notes']     = $execution_data->bulk_note;
									$time['slot_price']        = $execution_data->bulk_slot_price;
									$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
									$time['global_time_check'] = false;

									$booking_time_settings[ $weekday_name ] = array( $time );
								}
							}
						} else {

							foreach ( $bulk_day_date_value as $key => $value ) {

								$weekday_name = "booking_weekday_$value";

								if ( 'on' !== $booking_settings['booking_recurring'][ $weekday_name ] ) {
									$booking_recurring[ $weekday_name ] = 'on';
								}

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {

									// data available in time setting is sunday and monday 10:00 12:00 12:00 to 14:00
									// Adding new data is sunday 14:00 to 16:00.
									$found = false;
									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {
										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											$found = true;
											break;
										}
									}

									if ( ! $found ) { // This mean the timeslot is not present in the booking time settings.
										$time['from_slot_hrs']     = $from_time_explode[0];
										$time['from_slot_min']     = $from_time_explode[1];
										$time['to_slot_hrs']       = $to_time_explode[0];
										$time['to_slot_min']       = $to_time_explode[1];
										$time['booking_notes']     = $execution_data->bulk_note;
										$time['slot_price']        = $execution_data->bulk_slot_price;
										$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
										$time['global_time_check'] = false;
										array_push( $booking_time_settings[ $weekday_name ], $time );
									}
								} else {
									// This mean any data for the weekday is not present
									// add key as weekday and timeslot array to booking_time_settings.

									$time['from_slot_hrs']     = $from_time_explode[0];
									$time['from_slot_min']     = $from_time_explode[1];
									$time['to_slot_hrs']       = $to_time_explode[0];
									$time['to_slot_min']       = $to_time_explode[1];
									$time['booking_notes']     = $execution_data->bulk_note;
									$time['slot_price']        = $execution_data->bulk_slot_price;
									$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
									$time['global_time_check'] = false;

									$booking_time_settings[ $weekday_name ] = array( $time );
								}
							}
						}

						$new_recurring                             = array_merge( $booking_settings['booking_recurring'], $booking_recurring );
						$booking_settings['booking_recurring']     = $new_recurring; // assigning weekdays to booking settings.
						$booking_settings['booking_time_settings'] = $booking_time_settings;
						$settings_data['_bkap_time_settings']      = $booking_time_settings;
						$booking_box_class                         = new bkap_booking_box_class();
						$booking_box_class->update_bkap_history_date_time( $product_id, $settings_data );

						update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
						update_post_meta( $product_id, '_bkap_recurring_weekdays', $new_recurring );
						update_post_meta( $product_id, '_bkap_time_settings', $booking_time_settings );

						break;
					case 'date':
						$specific_dates        = explode( ',', $bulk_day_date_value );
						$booking_specific_date = $booking_settings['booking_specific_date'];
						$date_price            = array();
						$settings_data         = array();

						foreach ( $specific_dates as $key => $value ) {
							if ( ! isset( $booking_specific_date[ $value ] ) ) {
								$booking_specific_date[ $value ] = $execution_data->bulk_lockout_slot;
								$date_price[ $value ]            = $execution_data->bulk_slot_price;
							}

							if ( isset( $booking_time_settings[ $value ] ) ) { // product already having the date settings.
								$found = false;
								foreach ( $booking_time_settings[ $value ] as $k => $v ) {
									if ( $v['from_slot_hrs'] === $from_time_explode[0]
										&& $v['from_slot_min'] === $from_time_explode[1]
										&& $v['to_slot_hrs'] === $to_time_explode[0]
										&& $v['to_slot_min'] === $to_time_explode[1]
									) {
										$found = true;
										break;
									}
								}

								$time = array();

								if ( ! $found ) { // This mean the timeslot is not present in the booking time settings.
									$time['from_slot_hrs']     = $from_time_explode[0];
									$time['from_slot_min']     = $from_time_explode[1];
									$time['to_slot_hrs']       = $to_time_explode[0];
									$time['to_slot_min']       = $to_time_explode[1];
									$time['booking_notes']     = $execution_data->bulk_note;
									$time['slot_price']        = $execution_data->bulk_slot_price;
									$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
									$time['global_time_check'] = false;
									array_push( $booking_time_settings[ $value ], $time );
								}
							} else {
								// Do not have any date setting for the product so add new to it.
								$time['from_slot_hrs']     = $from_time_explode[0];
								$time['from_slot_min']     = $from_time_explode[1];
								$time['to_slot_hrs']       = $to_time_explode[0];
								$time['to_slot_min']       = $to_time_explode[1];
								$time['booking_notes']     = $execution_data->bulk_note;
								$time['slot_price']        = $execution_data->bulk_slot_price;
								$time['lockout_slot']      = $execution_data->bulk_lockout_slot;
								$time['global_time_check'] = false;

								$booking_time_settings[ $value ] = array( $time );
							}
						}

						// booking_time_settings : this array is ready.

						$booking_settings['booking_specific_date'] = $booking_specific_date;
						$settings_data['_bkap_specific_dates']     = $booking_specific_date; // got old plus new specifc date with lockout.
						if ( '' === $booking_settings['booking_specific_booking'] && ( ! empty( $booking_specific_date ) ) ) {
							$booking_settings['booking_specific_booking'] = 'on'; // enabled the specific date option is specific date are available.
							update_post_meta( $product_id, '_bkap_enable_specific', 'on' );
						}

						$booking_settings['booking_time_settings'] = $booking_time_settings;
						$settings_data['_bkap_time_settings']      = $booking_time_settings;

						// The data we are passing to this function is for weekdays as well as for dates but this will need only dates so later please try to have only date data so that it will save the time by excluding the check of weekdays.
						$booking_box_class = new bkap_booking_box_class();
						$booking_box_class->update_bkap_history_date_time( $product_id, $settings_data );

						update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
						update_post_meta( $product_id, '_bkap_specific_dates', $booking_specific_date );
						update_post_meta( $product_id, '_bkap_time_settings', $booking_time_settings );

						break;
				}
			}
		}

		/**
		 * Deleting availability from selected product
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_delete_execution( $product_id, $booking_settings, $execution_data, $booking_type ) {

			switch ( $booking_type ) {
				case 'only_day':
					self::bkap_bulk_delete_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'date_time':
					if ( '' !== $execution_data->bulk_from_time ) {
						self::bkap_bulk_delete_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type );
					} else {
						self::bkap_bulk_delete_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					}
					break;
				case 'duration_time':
					self::bkap_bulk_delete_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
				case 'multiple_days':
					self::bkap_bulk_delete_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type );
					break;
			}
		}

		/**
		 * Deleting availability from selected product of date and time type product
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_delete_execution_date_time( $product_id, $booking_settings, $execution_data, $booking_type ) {

			$from_time = $execution_data->bulk_from_time;

			if ( '' !== $from_time ) {

				$to_time               = $execution_data->bulk_to_time;
				$bulk_day_date_value   = $execution_data->bulk_day_date_value;
				$booking_time_settings = $booking_settings['booking_time_settings'];

				$from_time_explode = explode( ':', $from_time );
				if ( '' === $to_time ) {
					$to_time_explode = explode( ':', '0:00' );
				} else {
					$to_time_explode = explode( ':', $to_time );
				}

				switch ( $execution_data->bulk_day_date ) {
					case 'day':
						$booking_recurring = array();
						$recurring_lockout = array();
						$recurring_prices  = array();
						$settings_data     = array();

						if ( in_array( 'all', $bulk_day_date_value, true ) ) { // if user selected all.

							/* Enabling the weekday if its not enable */
							for ( $i = 0; $i <= 6; $i++ ) {
								$weekday_name = "booking_weekday_$i";

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {

									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {
										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											unset( $booking_time_settings[ $weekday_name ][ $key ] );
											// deleting the record from database.
											bkap_booking_box_class::delete_booking_history( $product_id, $weekday_name, $from_time, $to_time );
											break;
										}
									}
								}
							}
						} else {

							foreach ( $bulk_day_date_value as $key => $value ) {

								$weekday_name = "booking_weekday_$value";

								if ( isset( $booking_time_settings[ $weekday_name ] ) ) {
									foreach ( $booking_time_settings[ $weekday_name ] as $key => $value ) {
										if ( $value['from_slot_hrs'] === $from_time_explode[0]
											&& $value['from_slot_min'] === $from_time_explode[1]
											&& $value['to_slot_hrs'] === $to_time_explode[0]
											&& $value['to_slot_min'] === $to_time_explode[1]
										) {
											unset( $booking_time_settings[ $weekday_name ][ $key ] );
											// deleting the record from database.
											bkap_booking_box_class::delete_booking_history( $product_id, $weekday_name, $from_time, $to_time );
											break;
										}
									}
								}
							}
						}

						$booking_settings['booking_time_settings'] = $booking_time_settings;
						$settings_data['_bkap_time_settings']      = $booking_time_settings;
						update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
						update_post_meta( $product_id, '_bkap_time_settings', $booking_time_settings );

						break;
					case 'date':
						$specific_dates        = explode( ',', $bulk_day_date_value );
						$booking_specific_date = $booking_settings['booking_specific_date'];
						$date_price            = array();
						$settings_data         = array();

						foreach ( $specific_dates as $key => $value ) { // array of dates to be deleted from the product.

							if ( isset( $booking_time_settings[ $value ] ) ) { // product already having the date settings.
								foreach ( $booking_time_settings[ $value ] as $k => $v ) {
									if ( $v['from_slot_hrs'] === $from_time_explode[0]
										&& $v['from_slot_min'] === $from_time_explode[1]
										&& $v['to_slot_hrs'] === $to_time_explode[0]
										&& $v['to_slot_min'] === $to_time_explode[1]
									) {
										// add code to delete the specific date from db and also unset the data from array.
										unset( $booking_time_settings[ $value ][ $k ] );
										bkap_booking_box_class::delete_booking_history( $product_id, $value, $from_time, $to_time );
										break;
									}
								}
							}
						}

						$booking_settings['booking_time_settings'] = $booking_time_settings;
						update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
						update_post_meta( $product_id, '_bkap_time_settings', $booking_time_settings );

						break;
				}
			}
		}

		/**
		 * Deleting the availability from product for only day, duration time and multiple nights
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param obj    $execution_data Object of data to be added.
		 * @param string $booking_type Type of booking.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_delete_execution_only_day( $product_id, $booking_settings, $execution_data, $booking_type ) {

			$bulk_day_date_value = $execution_data->bulk_day_date_value;

			switch ( $execution_data->bulk_day_date ) {

				case 'day':
					$booking_recurring = array();
					$settings_data     = array();
					$old_lockout       = get_post_meta( $product_id, '_bkap_recurring_lockout', true );

					if ( in_array( 'all', $bulk_day_date_value, true ) ) {

						for ( $i = 0; $i <= 6; $i++ ) { /* Disabling the weekday if its not enable */
							$weekday_name = "booking_weekday_$i";
							if ( 'on' === $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$booking_recurring[ $weekday_name ]                         = '';
								$settings_data['_bkap_recurring_weekdays'][ $weekday_name ] = ''; // preparing this so that it will help deleting the records.
								$settings_data['_bkap_recurring_lockout'][ $weekday_name ]  = ''; // preparing this so that it will help deleting the records.
							}
						}
					} else {
						$detele_recurring = array();
						foreach ( $bulk_day_date_value as $key => $value ) {
							$weekday_name = "booking_weekday_$value";
							if ( 'on' === $booking_settings['booking_recurring'][ $weekday_name ] ) {
								$booking_recurring[ $weekday_name ]                         = '';
								$settings_data['_bkap_recurring_weekdays'][ $weekday_name ] = '';
								$settings_data['_bkap_recurring_lockout'][ $weekday_name ]  = '';
							}
						}
					}

					$new_recurring                         = array_merge( $booking_settings['booking_recurring'], $booking_recurring );
					$booking_settings['booking_recurring'] = $new_recurring; // Changing the infomration in booking settings.

					if ( isset( $booking_settings['booking_recurring_booking'] ) && 'on' === $booking_settings['booking_recurring_booking'] ) {
						if ( ! in_array( 'on', $new_recurring, true ) ) {
							$booking_settings['booking_recurring_booking'] = '';
							update_post_meta( $product_id, '_bkap_enable_recurring', '' );
						}
					}

					if ( 'only_day' === $booking_type ) {
						$booking_box_class = new bkap_booking_box_class();
						$booking_box_class->update_bkap_history_only_days( $product_id, $settings_data ); // Updating records..
					}

					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );
					update_post_meta( $product_id, '_bkap_recurring_weekdays', $new_recurring );

					break;
				case 'date':
					$specific_dates        = explode( ',', $bulk_day_date_value );
					$booking_specific_date = $booking_settings['booking_specific_date'];
					$date_price            = array();
					$settings_data         = array();
					$delete_date_price     = array();
					$booking_box_class     = new bkap_booking_box_class();

					foreach ( $specific_dates as $key => $value ) {
						if ( isset( $booking_specific_date[ $value ] ) ) {

							unset( $booking_specific_date[ $value ] );
							$booking_box_class->delete_specific_date( $product_id, $value );
							$delete_date_price[] = $value;
						}
					}

					if ( 'multiple_days' !== $booking_type ) { // do not add specific date in db when product type is multiple nights.
						$booking_settings['booking_specific_date'] = $booking_specific_date;
					}

					/* Genrating the special price data based on delete specific date price*/
					$new_special_price = self::bkap_bulk_special_price_delete( $product_id, $booking_settings, $delete_date_price, 'date' );

					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );

					if ( 'multiple_days' !== $booking_type ) {
						update_post_meta( $product_id, '_bkap_specific_dates', $booking_specific_date );
					}

					update_post_meta( $product_id, '_bkap_special_price', $new_special_price );

					break;
			}
		}

		/**
		 * Deleting the price for the specific date from special price post meta
		 *
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @param array  $delete_date_price Array of date price information to be deleted from special price data.
		 * @param string $daydate Date or Day string.
		 *
		 * @since 4.16.0
		 */
		public static function bkap_bulk_special_price_delete( $product_id, $booking_settings, $delete_date_price, $daydate ) {
			// Get the existing record.
			$booking_special_prices = get_post_meta( $product_id, '_bkap_special_price', true );

			switch ( $daydate ) {
				case 'day':
					break;
				case 'date':
					foreach ( $delete_date_price as $k => $v ) {
						foreach ( $booking_special_prices as $key => $value ) {
							if ( strtotime( $value['booking_special_date'] ) === strtotime( $v ) ) {
								unset( $booking_special_prices[ $key ] );
								continue;
							}
						}
					}
					break;
			}

			return $booking_special_prices;
		}

		/**
		 * Showing Settings for Bulk Booking Settings.
		 *
		 * @since 4.16.0
		 */
		public static function bulk_booking_settings_section_callback() {

			bkap_include_select2_scripts();

			$args = array(
				'taxonomy'       => 'product_cat',
				'order'          => 'ASC',
				'pad_counts'     => 0,
				'hierarchical'   => 1,
				'posts_per_page' => -1,
			);

			$categories = get_categories( $args );

			$args = array(
				'post_type'      => array( 'product' ),
				'posts_per_page' => -1,
				'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
			);

			$products = get_posts( $args );

			$description     = __( 'Add booking settings for multiple products together. Selected settings will be shown on the selected edit product place.', 'woocommerce-booking' );
			$pro_desc        = __( 'Select the product for which you want to add the booking settings. You can also select a Product Category to add booking settings to all the products in the selected category.', 'woocommerce-booking' );
			$pro_placeholder = __( 'Select a Product', 'woocommerce-booking' );

			$default_booking_options = get_option( 'bkap_default_booking_settings', false );

			?>
			<div class="content">
				<h2><?php esc_html_e( 'Bulk Booking Settings', 'woocommerce-booking' ); ?></h2>
				<p><?php echo esc_html( $description ); ?></p>
				<form method="post" action="<?php echo esc_url( get_admin_url() ); ?>edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=bulk_booking_settings&action_type=save" >
					<div id="bkap_product_list">
						<table class="bkap-form-table form-table">
							<tr>
								<th>
									<h3><?php esc_html_e( 'Select Products and/or Categories:', 'woocommerce-booking' ); ?></h3>
								</th>
								<td>
									<select id="bkap_products"
											name="bkap_products[]"
											placehoder="Select Products"
											class="bkap_bulk_products"
											multiple="multiple"
											placeholder="<?php echo esc_html( $description ); ?>">
										<optgroup label="<?php esc_attr_e( 'Select All Products', 'woocommerce-booking' ); ?>">
										<option value="all_products"><?php esc_html_e( 'All Products', 'woocommerce-booking' ); ?></option>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'Select by Product Category', 'woocommerce-booking' ); ?>">
										<?php
										// Display all prodct categories.
										foreach ( $categories as $key => $category ) { 
											$category_text = ( 'uncategorized' === $category->slug ) ? 'Uncategorized Products' : 'Products in ' . $category->cat_name . ' Category';
											$option_value  = 'cat_' . $category->slug;
											?>
											<option value="<?php echo esc_attr( $option_value ); ?>"><?php echo esc_html( $category_text ); ?></option>
											<?php
										}
										?>
										</optgroup>
										<optgroup label="<?php esc_attr_e( 'Select by Product Name', 'woocommerce-booking' ); ?>">
										<?php
										// Looping through all the products.
										$productss = '';
										foreach ( $products as $bkey => $bval ) {
											$productss .= $bval->ID . ',';
											?>
											<option value="<?php echo esc_attr( $bval->ID ); ?>"><?php echo esc_html( $bval->post_title ); ?></option>
											<?php
										}
										if ( '' !== $productss ) {
											$productss = substr( $productss, 0, -1 );
										}
										?>
										</optgroup>
									</select>
									<div>
										<label for="bkap_products"><?php echo esc_html( $pro_desc ); ?></label>
									</div>
									<input type="hidden" id="bkap_all_products" name="custId" value="<?php echo esc_attr( $productss ); ?>">
								</td>
							</tr>
						</table>
					</div>
					<br />
					<div id="woocommerce-booking" class="postbox">
						<h3 class="bkap-bulk-meta-box-heading hndle ui-sortable-handle"><span>Booking</span></h3>
						<div class="inside">
						<?php self::bkap_add_tab_data(); ?> 	
						</div>
					</div>
					<div style="width:100%;margin-bottom: 20px;">
						<p>
							<input type="checkbox" name="bkap_default_booking_option" id="bkap_default_booking_option">
							<label for="bkap_default_booking_option"><?php echo esc_html( __( 'Enable this to save selected options as default options.', 'woocommerce-booking' )); ?></label>
							<?php if ( $default_booking_options ) : ?>
							<a id="bkap_clear_defaults" class="button" title="<?php echo __( 'Clicking on this button will clear the default booking options that were saved earlier.', 'woocommerce-booking' ); ?>"><?php echo __( 'Clear defaults', 'woocommerce-booking' ); ?></a>
							<?php endif; ?>
						</p>
					</div>
					<div style="width:100%;margin-bottom: 20px;">
						<button type="button" class="button-primary bkap-primary bkap-bulk-setting">
							<img id="ajax_img" class="ajax_img" src="<?php echo esc_url( plugins_url() ) . '/woocommerce-booking/assets/images/ajax-loader.gif'; ?>"><?php esc_html_e( 'Save Settings', 'woocommerce-booking' ); ?>
						</button>
						<div id='bulk_booking_update_notification' style='display:none;'></div>
					</div>
				</form>
				<?php do_action( 'bkap_after_bulk_booking_settings' ); ?>
				<h2><?php esc_html_e( 'Manage availability of products', 'woocommerce-booking' ); ?></h2>
				<p>
				<?php
				esc_html_e( 'Add, update and delete the availability of mulitple bookable products using below table. ', 'woocommerce-booking' );
				/* translators: %s: Link to documentation of Manage Availability */
				echo sprintf( wp_kses_post( __( 'We highly recommend to read <strong><a href="%s" target="_blank">documentation</a></strong> before using this functionality.', 'woocommerce-booking' ) ), esc_url( 'https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/manage-availability-in-bulk/' ) );
				?>
				</p>
				<?php self::bkap_bulk_availability_table( $products ); ?>
			</div>
			<?php
		}

		/**
		 * Create/Delete Availability for products
		 *
		 * @param array $products Array of product ids.
		 * @since 4.16.0
		 */
		public static function bkap_bulk_availability_table( $products ) {

			// The parameter is for temporary.. try to optimize it by getting only bookable products i think function is already available
			// once above thing is done then remove the comment.

			$pro_desc = __( 'Below added actions will be excecuted for the products selected in this field.', 'woocommerce-booking' );
			?>
		<div class="panel-wrap" id="bulk_execution_table">
			<div class="options_group">
				<table class="bkap-form-table form-table">
					<tr>
						<th>
							<h3><?php esc_html_e( 'Bookable products:', 'woocommerce-booking' ); ?></h3>
						</th>
						<td>
							<select id="bkap_executable_products"
									name="bkap_executable_products[]"
									placehoder="Select Products"
									class="bkap_bulk_products"
									style="width: 300px"
									multiple="multiple">
								<option value="all_bookable_products"><?php esc_html_e( 'All Products', 'woocommerce-booking' ); ?></option>
								<?php
								$productss = '';
								foreach ( $products as $bkey => $bval ) {
									$non_bookable = bkap_common::bkap_get_bookable_status( $bval->ID );
									if ( $non_bookable ) {
										$productss .= $bval->ID . ',';
										?>
										<option value="<?php echo esc_attr( $bval->ID ); ?>"><?php echo esc_html( $bval->post_title ); ?></option>
										<?php
									}
								}
								if ( '' !== $productss ) {
									$productss = substr( $productss, 0, -1 );
								}
								?>
							</select>
							<div>
								<label for="bkap_executable_products"><?php echo esc_html( $pro_desc ); ?></label>
							</div>
							<input type="hidden" id="bkap_all_bookable_products" name="custId" value="<?php echo esc_attr( $productss ); ?>">
						</td>
					</tr>
				</table>
			</div>
			<br/>
			<div>
				<table class="bkap-executable-table">
					<thead>
						<tr>
							<th style="width: 10%;"><b><?php esc_html_e( 'Day/Date', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'Select day or date option to for which you want to manage the availability.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 28%;"><b><?php esc_html_e( 'Which Days/Dates?', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'For which day or date you want to manage the availability.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 10%;"><b><?php esc_html_e( 'Action', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'Select the action you want to perform on the selected day/date and product. You can add, update and delete the availability of the product.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 15%;"><b><?php esc_html_e( 'From & To time', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'Select from and to time slot value for which you want to manage the availability. This field will be applicable only if you want to manage the availability of the product which is set up with Fixed Time booking type.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 10%;"><b><?php esc_html_e( 'Max Booking', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'Set this field if you want to place a limit on maximum bookings on any given date. If you can manage up to 15 bookings in a day, set this value to 15. Once 15 orders have been booked, then that date will not be available for further bookings. This field will be used only when availability is being added or updated.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 8%;"><b><?php esc_html_e( 'Price', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'This field is for adding/updating the special price for selected day/date.', 'woocommerce-booking' ) ); ?>
							</th>
							<th style="width: 15%;"><b><?php esc_html_e( 'Note', 'woocommerce-booking' ); ?></b>
								<?php echo wc_help_tip( __( 'This field is for adding/updating the note for selected day/date and time slot. This field will be applicable only for Fixed Time booking type.', 'woocommerce-booking' ) ); ?>
							</th>
							<th class="remove_bulk" width="1%">&nbsp;</th>
						</tr>
					</thead>					
					<tfoot>
						<tr>
							<th colspan="5" style="text-align: left;font-size: 11px;font-style: italic;">
								<?php esc_html_e( 'You can add, update and delete the day/date availability from here.', 'woocommerce-booking' ); ?>
							</th>   
							<th colspan="3" style="text-align: right;">
								<a href="#" class="button button-primary bkap_add_row_bulk" style="text-align: right;" data-row="
								<?php
									ob_start();
									include BKAP_BOOKINGS_TEMPLATE_PATH . 'html-bkap-bulk-booking-rules.php';
									$html = ob_get_clean();
									echo esc_attr( $html );
								?>
								"><?php esc_html_e( 'Add Action', 'woocommerce-booking' ); ?></a>
							</th>
						</tr>
						<tr>
							<th colspan="5">
								<div id='execute_booking_update_notification' style='display:none;'></div>
							</th>
							<th colspan="3" style="text-align: right;">
								<a class="button button-primary bkap_execute_row_bulk" style="text-align: right;">
									<img id="ajax_img" class="ajax_img" src="<?php echo plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif'; // phpcs:ignore ?>">
									<?php esc_html_e( 'Execute Added Action', 'woocommerce-booking' ); ?>
								</a>
							</th>
						</tr>
					</tfoot>                    
					<tbody id="bulk_setting_availability_rows">                        
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Add our Booking Meta Box on each product page
		 *
		 * @since 4.16.0
		 */
		public static function bkap_add_tab_data() {

			$bkap_version = BKAP_VERSION;

			bkap_load_scripts_class::bkap_load_products_css( $bkap_version );
			bkap_load_scripts_class::bkap_load_bkap_tab_css( $bkap_version );

			$product_id   = 0;
			$has_defaults = false;

			if ( isset( $_GET['bkap_product_id'] ) && '' !== $_GET['bkap_product_id'] ) { // to load the booking settings of provided bookable product.
				$product_id                  = intval( $_GET['bkap_product_id'] );
				$booking_settings            = bkap_setting( $product_id );
				$individual_booking_settings = array();
			} else {
				$booking_settings            = get_option( 'bkap_default_booking_settings', array() );
				$individual_booking_settings = get_option( 'bkap_default_individual_booking_settings', array() );
				$has_defaults                = ( ! empty( $individual_booking_settings ) );
			}

			$product_info = array(
				'duplicate_of'                => $product_id,
				'booking_settings'            => $booking_settings,
				'individual_booking_settings' => $individual_booking_settings,
				'has_defaults'                => $has_defaults,
				'post_type'                   => '',
			);

			bkap_booking_box_class::bkap_meta_box_template( $product_info );

			$ajax_url = get_admin_url() . 'admin-ajax.php';
			bkap_load_scripts_class::bkap_common_admin_scripts_js( $bkap_version );
			bkap_load_scripts_class::bkap_load_product_scripts_js( $bkap_version, $ajax_url, 'bulk' );
		}
	} // end of class
	$bkap_bulk_booking_settings = new Bkap_Bulk_Booking_Settings();
} // end if
?>
