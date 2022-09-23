<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class containing common functions used all over the plugin.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Global-Function
 * @category Classes
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

/**
 * Contains common functions used all over the plugin.
 *
 * @class bkap_common
 */

class bkap_common {

	/**
	 * Get Global Booking Settings
	 *
	 * @return Object of the Global Booking Settings
	 */

	public static function bkap_global_setting() {
		return json_decode( get_option( 'woocommerce_booking_global_settings' ) );
	}

	/**
	 * Get Product Booking Settings
	 *
	 * @return Array of the product booking settings
	 */

	public static function bkap_product_setting( $pro_id ) {
		return get_post_meta( $pro_id, 'woocommerce_booking_settings', true );
	}

	/**
	 * Get count of Imported Events
	 *
	 * @return Int total Total Numbers of imported events
	 */

	public static function ts_get_event_counts() {

		$count_pages   = wp_count_posts( 'bkap_gcal_event' );
		$unm           = 'bkap-unmapped';
		$m             = 'bkap-mapped';
		$trash         = (int) $count_pages->trash;
		$bkap_unmapped = (int) $count_pages->$unm;
		$bkap_mapped   = (int) $count_pages->$m;

		$total = $trash + $bkap_unmapped + $bkap_mapped;

		return $total;
	}

	/**
	 * Get all bookable products
	 *
	 * @return array $products Array of all the bookable products.
	 */

	public static function ts_get_all_bookable_products( $additional_args = array() ) {

		$args = array(
			'post_type'      => array( 'product' ),
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' ),
			'meta_query'     => array(
				array(
					'key'     => '_bkap_enable_booking',
					'value'   => 'on',
					'compare' => '=',
				),
			),
		);

		if ( isset( $additional_args ) && ! empty( $additional_args ) ) {
			$args = array_merge( $args, $additional_args );
		}

		$products = get_posts( $args );

		return $products;
	}

	/**
	 * Get Global Booking Settings
	 *
	 * @return object $global_settings Object of the Global Booking Settings
	 */

	public static function ts_global_booking_setting() {

		$global_settings['global_settings']      = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		$global_settings['integration_settings'] = self::ts_get_integration_settings();
		$global_settings['addon_settings']       = self::ts_get_addon_settings();

		return $global_settings;
	}

	/**
	 * Get Addon Settings.
	 *
	 * @return object $addon_settings Object of the Addon Settings.
	 */
	public static function ts_get_addon_settings() {

		$addon_settings = array( 'bkap_hide_booking_options', array() );

		return $addon_settings;
	}

	/**
	 * Get Global Integration Settings
	 *
	 * @return object $integration_settings Object of the Integrations Settings.
	 */
	public static function ts_get_integration_settings() {

		$integration_settings = array();

		$zoom_meeting = array(
			'bkap_assign_meeting_scheduled' => get_option( 'bkap_assign_meeting_scheduled', false ),
			'bkap_zoom_api_key'             => get_option( 'bkap_zoom_api_key', false ),
			'bkap_zoom_api_secret'          => get_option( 'bkap_zoom_api_secret', false ),
		);

		$zapier = array(
			'bkap_api_zapier_integration' => get_option( 'bkap_api_zapier_integration', '' ),
			'trigger_create_booking'      => get_option( 'trigger_create_booking', '' ),
			'trigger_update_booking'      => get_option( 'trigger_update_booking', '' ),
			'trigger_delete_booking'      => get_option( 'trigger_delete_booking', '' ),
			'action_create_booking'       => get_option( 'action_create_booking', '' ),
			'action_update_booking'       => get_option( 'action_update_booking', '' ),
			'action_delete_booking'       => get_option( 'action_delete_booking', '' ),
			'bkap_api_zapier_log_enable'  => get_option( 'bkap_api_zapier_log_enable', '' ),
		);

		$gcal_settings = array(
			'bkap_calendar_event_location'             => get_option( 'bkap_calendar_event_location', '' ),
			'bkap_calendar_event_summary'              => get_option( 'bkap_calendar_event_summary', '' ),
			'bkap_calendar_event_description'          => get_option( 'bkap_calendar_event_description', '' ),
			'bkap_add_to_calendar_order_received_page' => get_option( 'bkap_add_to_calendar_order_received_page', '' ),
			'bkap_add_to_calendar_customer_email'      => get_option( 'bkap_add_to_calendar_customer_email', '' ),
			'bkap_add_to_calendar_my_account_page'     => get_option( 'bkap_add_to_calendar_my_account_page', '' ),
			'bkap_calendar_sync_integration_mode'      => get_option( 'bkap_calendar_sync_integration_mode', '' ),
			'bkap_cron_time_duration'                  => get_option( 'bkap_cron_time_duration', '' ),
			'bkap_ics_feed_urls'                       => get_option( 'bkap_ics_feed_urls', '' ),
		);

		$integration_settings['zoom_meeting']    = $zoom_meeting;
		$integration_settings['fluentcrm']       = get_option( 'bkap_fluentcrm_connection', array() );
		$integration_settings['zapier']          = $zapier;
		$integration_settings['google_calendar'] = $gcal_settings;

		return $integration_settings;
	}

	/**
	 * Get all booking products and its settings
	 *
	 * @return array $full_product_list Array of bookable product Id and its post meta.
	 */
	public static function ts_get_all_bookable_products_settings() {

		$full_product_list = array();

		$product = self::ts_get_all_bookable_products();

		foreach ( $product as $k => $value ) {

			$theid        = $value->ID;
			$duplicate_of = self::bkap_get_product_id( $theid );

			$product_meta        = get_post_meta( $duplicate_of );
			$full_product_list[] = array( $theid, $product_meta );
		}

		return $full_product_list;
	}
	/**
	 * Get booking count
	 *
	 * @return int $total Total Numbers of the Booking posts
	 */

	public static function ts_get_booking_counts() {

		$count_pages = wp_count_posts( 'bkap_booking' );
		$trash       = (int) $count_pages->trash;
		$paid        = (int) $count_pages->paid;
		$confirmed   = (int) $count_pages->confirmed;
		$cancelled   = (int) $count_pages->cancelled;

		$total = $trash + $paid + $confirmed + $cancelled;

		return $total;
	}
	/**
	 * Send the plugin data when the user has opted in
	 *
	 * @hook ts_tracker_data
	 * @param array $data All data to send to server
	 * @return array $plugin_data All data to send to server
	 */
	public static function bkap_ts_add_plugin_tracking_data( $data ) {
		if ( isset( $_GET['bkap_tracker_optin'] ) && isset( $_GET['bkap_tracker_nonce'] ) && wp_verify_nonce( $_GET['bkap_tracker_nonce'], 'bkap_tracker_optin' ) ) {

			global $booking_plugin_version;
			$plugin_data['ts_meta_data_table_name'] = 'ts_tracking_bkap_meta_data';
			$plugin_data['ts_plugin_name']          = 'Booking & Appointment Plugin for WooCommerce';

			/**
			 * Write your opt out plugin specific data below.
			 * $plugin_data [ 'total_bookable_products' ] = self::bkap_get_count_of_bookable_products();
			 */

			$plugin_data ['total_bookable_products']   = json_encode( self::ts_get_all_bookable_products() );
			$plugin_data ['total_gcal_count']          = self::ts_get_event_counts();
			$plugin_data ['total_global_setting']      = json_encode( self::ts_global_booking_setting() );
			$plugin_data ['bookable_products_setting'] = json_encode( self::ts_get_all_bookable_products_settings() );
			$plugin_data ['booking_counts']            = self::ts_get_booking_counts();

			// Get all plugin options info

			$plugin_data['plugin_version']      = $booking_plugin_version;
			$plugin_data['bkap_allow_tracking'] = get_option( 'bkap_allow_tracking' );
			$data['plugin_data']                = $plugin_data;
		}
		return $data;
	}

	/**
	 * This function used to send the data to the server. It is used for tracking the data when admin do not wish to share the tarcking informations.
	 *
	 * @hook ts_tracker_opt_out_data
	 * @param array $params Parameters
	 * @return array $params Parameters
	 */
	public static function bkap_get_data_for_opt_out( $params ) {
		$plugin_data['ts_meta_data_table_name'] = 'ts_tracking_bkap_meta_data';
		$plugin_data['ts_plugin_name']          = 'Booking & Appointment Plugin for WooCommerce';

		$params ['plugin_data'] = $plugin_data;

		return $params;
	}

	/**
	 * It will add the Questions while admin deactivate the plugin.
	 *
	 * @hook ts_deativate_plugin_questions
	 * @param array $bkap_add_questions Blank array
	 * @return array $bkap_add_questions List of all questions.
	 */
	public static function bkap_deactivate_add_questions( $bkap_add_questions ) {

		$bkap_add_questions = array(
			0 => array(
				'id'                => 4,
				'text'              => __( 'Facing some major issues on site due to this plugin.', 'woocommerce-booking' ),
				'input_type'        => '',
				'input_placeholder' => '',
			),
			1 => array(
				'id'                => 5,
				'text'              => __( 'Unable to setup the bookings as per my requirements.', 'woocommerce-booking' ),
				'input_type'        => '',
				'input_placeholder' => '',
			),
			2 => array(
				'id'                => 6,
				'text'              => __( 'Required feature is not working as per my expectation.', 'woocommerce-booking' ),
				'input_type'        => 'textfield',
				'input_placeholder' => 'Which Feature?',
			),
			3 => array(
				'id'                => 7,
				'text'              => __( 'The plugin is not compatible with another plugin.', 'woocommerce-booking' ),
				'input_type'        => 'textfield',
				'input_placeholder' => 'Which Plugin?',
			),

		);
		return $bkap_add_questions;
	}

	/**
	 * Return min date based on the Advance Booking Period.
	 *
	 * @param integer $product_id - Product ID for which calculations need to be done.
	 * @param string  $current_time - UNIX TimeStamp
	 * @return $min_date date - 'j-n-Y' format
	 * @since 4.1.0
	 */

	public static function bkap_min_date_based_on_AdvanceBookingPeriod( $product_id, $current_time ) {

		$bkap_abp      = get_post_meta( $product_id, '_bkap_abp', true );
		$bkap_abp      = ( isset( $bkap_abp ) && $bkap_abp != '' ) ? $bkap_abp : 0;
		$bkap_settings = bkap_setting( $product_id );
		$bkap_abp      = apply_filters( 'bkap_advance_booking_period', $bkap_abp, $bkap_settings, $product_id );

		// Convert the advance period to seconds and add it to the current time
		$advance_seconds   = $bkap_abp * 60 * 60;
		$cut_off_timestamp = $current_time + $advance_seconds;
		$cut_off_date      = date( 'd-m-Y', $cut_off_timestamp );
		$min_date          = date( 'j-n-Y', strtotime( $cut_off_date ) );

		return $min_date;
	}

	/**
	 * Return true/false based on the timeslot available for selected date.
	 *
	 * @param integer $product_id - Product ID
	 * @param string  $start_date - Date for which availability is being checked
	 * @return boolean
	 * @since 4.3.0
	 */

	public static function bkap_check_timeslot_for_weekday( $product_id, $start_date, $booking_settings ) {

		$start_weekday         = date( 'w', strtotime( $start_date ) );
		$start_booking_weekday = 'booking_weekday_' . $start_weekday;

		if ( ! isset( $booking_settings['booking_time_settings'] ) ) {
			return false;
		} elseif ( is_array( $booking_settings['booking_time_settings'] ) && isset( $booking_settings['booking_time_settings'][ $start_booking_weekday ] ) ) {
			return true;
		} elseif ( is_array( $booking_settings['booking_time_settings'] ) &&
		array_key_exists( date( 'j-n-Y', strtotime( $start_date ) ), $booking_settings['booking_time_settings'] ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Return all the timeslot available for given weekday/date.
	 *
	 * @param integer $product_id - Product ID.
	 * @param string  $start_date - Date for which availability is being checked.
	 * @param array   $booking_settings - Booking Settings.
	 *
	 * @return array
	 *
	 * @since 5.10.0
	 */
	public static function bkap_get_all_timeslots_for_weekday_date( $product_id, $start_date, $booking_settings ) {

		$time_slots = array();
		$timeslots  = array();

		if ( strpos( $start_date, 'booking_weekday_' ) !== false ) {
			$start_booking_weekday = $start_date;
			$date                  = false;
		} else {
			$start_weekday         = date( 'w', strtotime( $start_date ) );
			$start_booking_weekday = 'booking_weekday_' . $start_weekday;
			$date                  = true;
		}

		if ( isset( $booking_settings['booking_time_settings'] ) && is_array( $booking_settings['booking_time_settings'] ) ) {

			if ( isset( $booking_settings['booking_time_settings'][ $start_booking_weekday ] ) ) {
				$time_slots = $booking_settings['booking_time_settings'][ $start_booking_weekday ];
			} elseif ( $date ) {
				$start_date_jny = date( 'j-n-Y', strtotime( $start_date ) );
				if ( isset( $booking_settings['booking_time_settings'][ $start_date_jny ] ) ) {
					$time_slots = $booking_settings['booking_time_settings'][ $start_date_jny ];
				}
			}

			$i = 0;
			foreach ( $time_slots as $key => $timeslot ) {
				$from = $timeslot['from_slot_hrs'] . ':' . $timeslot['from_slot_min'];
				$to   = $timeslot['to_slot_hrs'] . ':' . $timeslot['to_slot_min'];
				if ( '0:00' === $to ) {
					$to = '23:59';
				}

				$timeslots[ $i ]['from'] = $from;
				$timeslots[ $i ]['to']   = $to;
				$i++;
			}
		}

		return $timeslots;
	}

	/**
	 * Return function name to be executed when multiple time slots are enabled.
	 *
	 * This function returns the function name to display the timeslots on the
	 * frontend if type of timeslot is Multiple for multiple time slots addon.
	 *
	 * @param integer $product_id
	 * @return string
	 * @since 2.0
	 */

	public static function bkap_ajax_on_select_date( $product_id ) {
		$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

		if ( isset( $booking_settings['booking_enable_multiple_time'] ) && $booking_settings['booking_enable_multiple_time'] == 'multiple' && function_exists( 'is_bkap_multi_time_active' ) && is_bkap_multi_time_active() ) {
			return 'multiple_time';
		}
	}

	/**
	 * Return an array of dates that fall in a date range
	 *
	 * This function returns an array of dates that falls
	 * in a date range in the d-n-Y format.
	 *
	 * @param string $start_date d-n-Y format.
	 * @param string $end_date d-n-Y format.
	 * @param string $format Date Format.
	 * @param array  $recurring_days Recurring Days Settings.
	 *
	 * @return array $Days - array of dates within the range
	 * @since 2.0
	 */

	public static function bkap_get_betweendays( $start_date, $end_date, $format = 'd-n-Y', $recurring_days = array() ) {

		$start_date_timestamp = strtotime( $start_date );
		$end_date_timestamp   = strtotime( $end_date );
		$recurring_passed     = empty( $recurring_days ) ? 0 : 1;

		if ( $start_date_timestamp !== $end_date_timestamp ) {
			while ( $start_date_timestamp < $end_date_timestamp ) {

				$consider_date = false;

				if ( $recurring_passed ) {
					$weekday         = date( 'w', strtotime( $start_date ) );
					$booking_weekday = 'booking_weekday_' . $weekday;
					if ( isset( $recurring_days[ $booking_weekday ] ) && 'on' === $recurring_days[ $booking_weekday ] ) {
						$consider_date = true;
					}
				}

				if ( $consider_date || empty( $recurring_days ) ) {
					$days[] = date( $format, strtotime( $start_date ) );
				}
				$start_date           = date( $format, strtotime( '+1 day', strtotime( $start_date ) ) );
				$start_date_timestamp = $start_date_timestamp + 86400;
			}
		}

		if ( ! isset( $days ) ) {
			$days[] = $start_date;
		}

		return $days;
	}

	/**
	 * Return an array of dates that fall in a date range
	 *
	 * This function returns an array of dates that falls in a date range
	 * in the d-n-Y format including the end date if the flat charge per day is enable.
	 *
	 * @param string $StartDate d-n-Y format
	 * @param string $EndDate d-n-Y format
	 * @return array $Days - array of dates within the range
	 *
	 * @since 4.7.0
	 */

	public static function bkap_get_betweendays_when_flat( $StartDate, $EndDate, $pro_id, $format = 'd-n-Y' ) {
		$Days[]      = $StartDate;
		$CurrentDate = $StartDate;

		$CurrentDate_timestamp = strtotime( $CurrentDate );
		$EndDate_timestamp     = strtotime( $EndDate );

		if ( $CurrentDate_timestamp != $EndDate_timestamp ) {

			while ( $CurrentDate_timestamp < $EndDate_timestamp ) {
				$CurrentDate           = date( $format, strtotime( '+1 day', strtotime( $CurrentDate ) ) );
				$CurrentDate_timestamp = $CurrentDate_timestamp + 86400;
				$Days[]                = $CurrentDate;
			}
		}
		return $Days;
	}

	/**
	 * Send the Base language product ID
	 *
	 * This function has been written as a part of making the Booking plugin
	 * compatible with WPML. It returns the base language Product ID when WPML
	 * is enabled.
	 *
	 * @param integer $product_id
	 * @return integer $base_product_id
	 * @since 2.0
	 */

	public static function bkap_get_product_id( $product_id ) {
		$base_product_id = $product_id;
		// If WPML is enabled, the make sure that the base language product ID is used to calculate the availability
		if ( function_exists( 'icl_object_id' ) ) {
			global $sitepress;
			global $polylang;

			if ( isset( $polylang ) ) {
				$default_lang = pll_current_language();
			} else {
				$default_lang = $sitepress->get_default_language();
			}

			$base_product_id = icl_object_id( $product_id, 'product', false, $default_lang );
			// The base product ID is blanks when the product is being created.
			if ( ! isset( $base_product_id ) || ( isset( $base_product_id ) && $base_product_id == '' ) ) {
				$base_product_id = $product_id;
			}
		}
		return $base_product_id;
	}

	/**
	 * Send the Base language Variation ID
	 *
	 * This function has been written as a part of making the Booking plugin
	 * compatible with WPML. It returns the base language Variation ID when WPML
	 * is enabled.
	 *
	 * @param int $variation_id - Variation ID
	 * @return int Variation ID
	 * @since 4.5.1
	 */

	public static function bkap_get_variation_id( $variation_id ) {
		$base_variation_id = $variation_id;
		// If WPML is enabled, the make sure that the base language product ID is used to calculate the availability
		if ( function_exists( 'icl_object_id' ) ) {
			global $sitepress;
			global $polylang;

			if ( isset( $polylang ) ) {
				$default_lang = pll_current_language();
			} else {
				$default_lang = $sitepress->get_default_language();
			}

			$base_variation_id = icl_object_id( $variation_id, 'product-variation', false, $default_lang );
			// The base variation_id is blanks when the variation is being created.
			if ( ! isset( $base_variation_id ) || ( isset( $base_variation_id ) && $base_variation_id == '' ) ) {
				$base_variation_id = $variation_id;
			}
		}
		return $base_variation_id;
	}

	/**
	 * Returns the selected setting of Multicurrency at product level from WPML plugin when it is active
	 *
	 * This function has been written as a part of making the Booking plugin
	 * compatible with WPML. It returns the selected setting of Multicurrency at product level from WPML plugin when it is active
	 *
	 * @param integer $product_id - Product ID
	 * @param integer $variation_id - Variation ID
	 * @param string  $product_type - Product Type
	 * @return integer $custom_post
	 * @since 4.3.0
	 */

	public static function bkap_get_custom_post( $product_id, $variation_id, $product_type ) {
		if ( $product_type == 'variable' ) {
			$custom_post = get_post_meta( $variation_id, '_wcml_custom_prices_status', true );
		} elseif ( $product_type == 'simple' || $product_type == 'grouped' ) {
			$custom_post = get_post_meta( $product_id, '_wcml_custom_prices_status', true );
		}
		if ( $custom_post == '' ) { // possible when the setting has been left to it's default value
			$custom_post = 0;
		}
		return $custom_post;
	}

	/**
	 * Return Woocommerce price
	 *
	 * This function returns the Woocommerce price applicable for a product.
	 * Different Product Types such as simple, variable, bundles etc. have been taken into account here.
	 *
	 * @param integer $product_id
	 * @param integer $variation_id
	 * @param string  $product_type - simple, bundled, composite, variation etc.
	 * @param string  $check_in - booking start date
	 * @param string  $check_out - booking end date
	 * @return integer $price
	 *
	 * @since 4.3.0
	 */

	public static function bkap_get_price( $product_id, $variation_id, $product_type, $check_in = '', $check_out = '' ) {

		global $wpdb;

		$adp_product_id            = $product_id;
		$price                     = 0;
		$wpml_multicurreny_enabled = 'no';
		if ( function_exists( 'icl_object_id' ) ) {
			global $woocommerce_wpml, $woocommerce;
			if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
				if ( $product_type == 'variable' ) {
					$custom_post = self::bkap_get_custom_post( $product_id, $variation_id, $product_type );
					if ( $custom_post == 1 ) {
						$client_currency = $woocommerce->session->get( 'client_currency' );
						if ( $client_currency != '' && $client_currency != get_option( 'woocommerce_currency' ) ) {
							$price                     = get_post_meta( $variation_id, '_price_' . $client_currency, true );
							$wpml_multicurreny_enabled = 'yes';
						}
					}
				} elseif ( $product_type == 'simple' || 'bundle' == $product_type ) {
					$custom_post = self::bkap_get_custom_post( $product_id, $variation_id, $product_type );
					if ( $custom_post == 1 ) {
						$client_currency = $woocommerce->session->get( 'client_currency' );
						if ( $client_currency != '' && $client_currency != get_option( 'woocommerce_currency' ) ) {
							$price                     = get_post_meta( $product_id, '_price_' . $client_currency, true );
							$wpml_multicurreny_enabled = 'yes';
						}
					}
				}
			}
		}

		if ( $wpml_multicurreny_enabled == 'no' ) {

			if ( $product_type == 'variable' ) {

				$adp_product_id = $variation_id;
				$sale_price     = get_post_meta( $variation_id, '_sale_price', true );

				$sale_price_dates_from = '';
				$sale_price_dates_to   = '';

				if ( ! isset( $sale_price ) || $sale_price == '' || $sale_price == 0 ) {
					$regular_price = get_post_meta( $variation_id, '_regular_price', true );
					$price         = $regular_price;
				} else {

					$sale_price_dates_from           = get_post_meta( $variation_id, '_sale_price_dates_from', true );
					$sale_price_dates_from_strtotime = ( isset( $sale_price_dates_from ) && '' != $sale_price_dates_from ) ? $sale_price_dates_from : '';
					$sale_price_dates_to             = get_post_meta( $variation_id, '_sale_price_dates_to', true );
					$sale_price_dates_to_strtotime   = ( isset( $sale_price_dates_to ) && '' != $sale_price_dates_to ) ? $sale_price_dates_to : '';

					if ( isset( $sale_price_dates_from_strtotime ) && '' != $sale_price_dates_from_strtotime && isset( $sale_price_dates_to_strtotime ) && '' != $sale_price_dates_to_strtotime ) {

						if ( ( strtotime( $check_in ) >= $sale_price_dates_from_strtotime )
						&& ( strtotime( $check_in ) <= $sale_price_dates_to_strtotime ) ) {

							 $price = $sale_price;

						} else {

							$regular_price = get_post_meta( $variation_id, '_regular_price', true );
							$price         = $regular_price;
						}
					} else {

							  $price = $sale_price;
					}
				}

				if ( isset( $_POST['wc_measurement'] ) ) {
					$price = $price * $_POST['wc_measurement'];
				}
			} elseif ( $product_type == 'simple' || 'bundle' == $product_type || 'composite' == $product_type ) {
				$product_obj = self::bkap_get_product( $product_id );
				if ( 'bundle' === $product_type ) {
					$sale_price = get_post_meta( $product_id, '_wc_pb_base_sale_price', true );
				} else {
					$sale_price = get_post_meta( $product_id, '_sale_price', true );
				}

				if ( $sale_price != '' ) {

					$sale_price_dates_from = '';
					$sale_price_dates_to   = '';

					$sale_price_dates_from           = get_post_meta( $product_id, '_sale_price_dates_from', true );
					$sale_price_dates_from_strtotime = ( isset( $sale_price_dates_from ) && '' != $sale_price_dates_from ) ? $sale_price_dates_from : '';

					$sale_price_dates_to           = get_post_meta( $product_id, '_sale_price_dates_to', true );
					$sale_price_dates_to_strtotime = ( isset( $sale_price_dates_to ) && '' != $sale_price_dates_to ) ? $sale_price_dates_to : '';

					if ( isset( $sale_price_dates_from_strtotime ) && '' != $sale_price_dates_from_strtotime && isset( $sale_price_dates_to_strtotime ) && '' != $sale_price_dates_to_strtotime ) {

						if ( ( strtotime( $check_in ) >= $sale_price_dates_from_strtotime )
						&& ( strtotime( $check_in ) <= $sale_price_dates_to_strtotime ) ) {

							$price = $product_obj->get_sale_price();

						} else {
							$regular_price = get_post_meta( $product_id, '_regular_price', true );
							$price         = $product_obj->get_regular_price();
						}
					} else {
						$regular_price = get_post_meta( $product_id, '_sale_price', true );
						$price         = $product_obj->get_sale_price();
					}
				} else {
					$price = $product_obj->get_price();
					if ( isset( $_POST['alg_lang'] ) || isset( $_POST['wc_membership'] ) ) {
						$price = get_post_meta( $product_id, '_regular_price', true );
					}
				}

				if ( $price == '' ) {
					$price = 0;
				}

				if ( isset( $_POST['wc_measurement'] ) ) {
					$price = $price * $_POST['wc_measurement'];
				}
			} else {
				if ( isset( $variation_id ) && $variation_id != '0' && $variation_id != '' ) {
					$product_obj = self::bkap_get_product( $variation_id );
				} else {
					$product_obj = self::bkap_get_product( $product_id );
				}

				$price = $product_obj->get_price();
			}

			// Fix #3959: Compatibility with Advanced Dynamic Pricing for WooCommerce Pro plugin.
			$price = self::bkap_get_price_adp( $price, $adp_product_id );

			// check if any of the products are individually priced
			// if yes then we need to add those to the bundle price
			/*
			if ( $price > 0 && 'bundle' == $product_type ) {
			$bundle_price = bkap_common::get_bundle_price( $price, $product_id, $variation_id );

			$price = $bundle_price;
			}*/
		}

		/**
		 * Routes the final price through a filter for external changes.
		 *
		 * @since 5.12.0
		 *
		 * @param float $price Product Price.
		 * @param integer $product_id Product ID.
		 */
		$price = apply_filters( 'bkap_get_price', $price, $product_id );

		return $price;
	}

	/**
	 * Returns Woocommerce price from the Advanced Dynamic Pricing for WooCommerce Pro plugin.
	 *
	 * @param float   $price Product Price.
	 * @param integer $product_id Product ID.
	 *
	 * @since 5.12.0
	 */
	public static function bkap_get_price_adp( $price, $product_id ) {

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// Check if ADP Plugin is activated. No need to continue if it isn't present.
		if ( ! is_plugin_active( 'advanced-dynamic-pricing-for-woocommerce-pro/advanced-dynamic-pricing-for-woocommerce-pro.php' ) ) {
			return $price;
		}

		$qty = isset( $_POST['quantity'] ) ? (int) $_POST['quantity'] : 0; // phpcs:ignore

		// ADP has some special functions to get discounted price. We need to take that into consideration.
		$context    = new \ADP\BaseVersion\Includes\Context();
		$customizer = \ADP\Factory::get( 'External_Customizer_Customizer', $context );

		$product = \ADP\BaseVersion\Includes\External\CacheHelper::getWcProduct( $product_id );

		// We're taking up simple and variable products  in this fix.
		// TODO: Implement other product types. ADP has some wierd way of handling product types. We need to get into that.
		if ( ! $product->is_type( 'simple' ) && 'product_variation' !== $product->post_type ) {
			return $price;
		}

		$range_record = \ADP\Factory::get( 'External_RangeDiscountTable_RangeDiscountTable', $context, $customizer );

		// Checking for ADP discount rules.
		$rule = $range_record->findRuleForProductTable( $product );
		if ( ! $rule ) {
			return $price;
		}

		// Checking for ADP Product Handlers.
		$handler = $rule->getProductRangeAdjustmentHandler();
		if ( ! $handler ) {
			return $price;
		}

		// Try to genereated discount price from available rules.
		$price_processor = $range_record->makePriceProcessor( $rule );
		if ( ! $price_processor ) {
			return $price;
		}

		// Check in Cart for product quantities and consider them.
		foreach ( WC()->cart->cart_contents as $cart_content ) {
			$facade = new \ADP\BaseVersion\Includes\External\WC\WcCartItemFacade( $context, $cart_content );

			if ( $facade->getProduct()->get_id() === $product->get_id() ) {
				$qty += $facade->getQty();
			}
		}

		// Get discount ranges.
		$ranges = array();
		foreach ( $handler->getRanges() as $range ) {

			$adp_product = $price_processor->calculateProduct( $product, $range->getFrom() );

			if ( ! $adp_product ) {
				return $price;
			}

			$range_from = $range->getFrom();
			$range_to   = $range->getTo();

			if ( ( $range_from <= $qty && $qty <= $range_to )
			|| ( $range_from <= $qty && '' === $range_to )
			|| ( '' === $range_from && $qty <= $range_to )
			) {
				$price = $adp_product->getPrice();
			}
		}

		return $price;
	}

	/**
	 * Calculates the Total Bundle Price
	 *
	 * The bundle price + the Individual child
	 * price based on the bundle settings
	 *
	 * @param $price - Bundle Price
	 * @param int                  $product_id - Product ID
	 * @param int                  $variation_id - Variation ID
	 * @return $price - Final Bundle Price
	 *
	 * @since 4.3.0
	 */

	static function get_bundle_price( $price, $product_id, $variation_id, $called = '', $component_key = '' ) {

		global $wpdb;

		// get all the IDs for the items in the bundle
		$bundle_items_query = 'SELECT bundled_item_id, product_id FROM `' . $wpdb->prefix . 'woocommerce_bundled_items`
                                 WHERE bundle_id = %d';
		$get_bundle_items   = $wpdb->get_results( $wpdb->prepare( $bundle_items_query, $product_id ) );

		if ( isset( $get_bundle_items ) && count( $get_bundle_items ) > 0 ) {

			// fetch the status of optional child products, whether they have been selected on the front end or no
			$explode_optional = array();
			if ( isset( $_POST['bundle_optional'] ) && '' != $_POST['bundle_optional'] ) {
				$explode_optional = json_decode( stripcslashes( $_POST['bundle_optional'] ), true );
				$bundle_qty       = json_decode( stripcslashes( $_POST['bundle_qty'] ), true );
			}

			$child_count = 0;
			foreach ( $get_bundle_items as $b_key => $b_value ) {
				$bundled_product_obj = wc_pb_get_bundled_item( $b_value->bundled_item_id );

				$quantity_min = $bundled_product_obj->get_quantity();
				$quantity_max = $bundled_product_obj->get_quantity( 'max' );

				$child_selected = 'off';

				$bundle_item_id = (int) $b_value->bundled_item_id;
				// get the pricing settings for each item
				$price_query   = 'SELECT meta_key, meta_value FROM `' . $wpdb->prefix . "woocommerce_bundled_itemmeta`
                                         WHERE bundled_item_id = %d
                                         AND meta_key IN ( 'priced_individually', 'discount', 'optional' )";
				$price_results = $wpdb->get_results( $wpdb->prepare( $price_query, $bundle_item_id ) );
				if ( isset( $price_results ) && count( $price_results ) > 0 ) {

					foreach ( $price_results as $key => $value ) {
						switch ( $value->meta_key ) {
							case 'priced_individually':
								$price_type = $value->meta_value;
								break;
							case 'discount':
								$price_discount = $value->meta_value;
								break;
							case 'optional':
								$optional = $value->meta_value;
								break;
							default:
								break;
						}
					}

					// if product is optional, see if it has been selected or no
					// on - selected, off - not selected
					if ( 'yes' == $optional ) {
						if ( isset( $explode_optional[ $bundle_item_id ] ) && '' != $explode_optional[ $bundle_item_id ] ) {
							$child_selected = $explode_optional[ $bundle_item_id ];
							$child_count++;
						}
					}

					$variation_array = array();
					// product is individually priced
					if ( 'yes' == $price_type ) {
						$bundle_child_id      = $b_value->product_id;
						$product_obj          = self::bkap_get_product( $bundle_child_id );
						$bundle_item_prd_type = $product_obj->get_type();
						$bundle_item_var      = 0;
						if ( 'variable' == $bundle_item_prd_type ) {
							$variation_list = $product_obj->get_available_variations();
							foreach ( $variation_list as $var_key => $var_value ) {
								array_push( $variation_array, $var_value['variation_id'] );
							}
							$variations_selected = explode( ',', $variation_id );

							// find the variation selected
							foreach ( $variations_selected as $v_key => $v_val ) {
								if ( in_array( $v_val, $variation_array ) ) {
									$bundle_item_var = $v_val;
									break;
								}
							}
						}

						$child_price = self::bkap_get_price( $bundle_child_id, $bundle_item_var, $bundle_item_prd_type );

						if ( '' != $price_discount && $price_discount > 0 && $called != 'composite' ) {
							// calculate the discounted price
							$discount     = ( $price_discount * $child_price ) / 100;
							$child_price -= $discount;
						}

						if ( $called == 'composite' ) {
							$bundle_item_id = 'component_' . $component_key . '_' . $bundle_item_id;
						}

						if ( $quantity_min == $quantity_max ) {
							$child_price = $child_price * $quantity_min;
						} elseif ( isset( $bundle_qty[ $bundle_item_id ] ) ) {
							$child_price = $child_price * $bundle_qty[ $bundle_item_id ];
						}

						if ( isset( $_POST['quantity'] ) && $_POST['quantity'] > 0 ) {
							$child_price = $child_price * $_POST['quantity'];
						}

						if ( isset( $_POST['diff_days'] ) && $_POST['diff_days'] > 0 ) {
							$is_bookable = self::bkap_get_bookable_status( $bundle_child_id );

							// Added filter hook to make non bookable product price repeatable for multiple days.
							$repeat_non_bookable_price = apply_filters( 'bkap_bundle_ignore_nonbookable_child_prices', true );

							// Don't multiply the product price with no of days if not bookable product.
							if ( $is_bookable || $repeat_non_bookable_price ) {
								$child_price = $child_price * $_POST['diff_days'];
							}
						}

						// if the product is optional, the child price should be added only if it's selected
						if ( 'yes' == $optional ) { // if the child product is optional
							if ( 'on' == $child_selected ) {
								$price += $child_price;
							}
						} else { // else product is not optional, so always add the child price
							$price += $child_price;
						}
					}
				}
			}
		}

		return $price;
	}

	/**
	 * Calculates the Total Composite Price
	 *
	 * The composite price + the Individual child
	 * price based on the product settings
	 *
	 * @param int $price
	 * @param int $product_id
	 * @param int $variation_id
	 * @return int $price
	 * @since 4.7.0
	 */

	public static function get_composite_price( $price, $product_id, $variation_id ) {

		$composite_data = array();
		if ( isset( $_POST['composite_data'] ) ) {
			$composite_data = $_POST['composite_data'];
		}

		if ( ! empty( $composite_data ) ) {

			$product_obj = wc_get_product( $product_id );
			// $price          = $product_obj->get_price();
			$component_ids   = $product_obj->get_component_ids();
			$variation_array = explode( ',', $variation_id );

			$quantity = 1;
			if ( isset( $_POST['quantity'] ) && $_POST['quantity'] > 0 ) {
				$quantity = $_POST['quantity'];
			}

			$diff_days = 1;
			if ( isset( $_POST['diff_days'] ) && $_POST['diff_days'] > 0 ) {
				$diff_days = $_POST['diff_days'];
			}

			if ( isset( $_POST['block_option_price'] ) && '' != $_POST['block_option_price'] ) {
				$price = $_POST['block_option_price'];
			}

			foreach ( $composite_data as $c_key => $c_value ) {
				$child_price = 0;
				if ( isset( $c_value['p_id'] ) && '' !== $c_value['p_id'] ) {

					$component_data = $product_obj->get_component_data( $c_key );

					$child_product = wc_get_product( $c_value['p_id'] );
					$child_type    = $child_product->get_type();
					$booking_type  = bkap_type( $c_value['p_id'] );

					$selected_variation = '';
					if ( $child_type == 'variable' && isset( $c_value['v_id'] ) ) {
						$selected_variation = $c_value['v_id'];
					}

					if ( isset( $component_data['priced_individually'] ) && 'yes' === $component_data['priced_individually'] ) {

						if ( $child_type == 'bundle' ) {
							$child_price = self::get_bundle_price( 0, $c_value['p_id'], $variation_id, 'composite', $c_key );
						} else {
							$child_price = self::bkap_get_price( $c_value['p_id'], $selected_variation, $child_type );
						}

						if ( 'multiple_days' === $booking_type ) {
							$param = array(
								date( 'Y-m-d', strtotime( $_POST['checkin_date'] ) ),
								date( 'Y-m-d', strtotime( $_POST['current_date'] ) ),
								$booking_type,
							);
						} else {
							$param = array(
								date( 'Y-m-d', strtotime( $_POST['bkap_date'] ) ),
								date( 'w', strtotime( $_POST['bkap_date'] ) ),
								$booking_type,
							);
						}

						$child_price = bkap_get_special_price( $c_value['p_id'], $param, $child_price );

						$child_discount = $product_obj->get_component_discount( $c_key );
						if ( isset( $child_discount ) && '' !== $child_discount ) {
							$child_price = $child_price - ( ( $child_price * $child_discount ) / 100 );
						}
					}

					if ( $quantity > 1 && $child_type != 'bundle' ) {
						$child_price = $child_price * $_POST['quantity'];
					}

					if ( $diff_days > 1 && $child_type != 'bundle' ) {
						$child_price = $child_price * $_POST['diff_days'];
					}

					if ( isset( $c_value['qty'] ) && '' !== $c_value['qty'] ) {
						$child_price = $child_price * $c_value['qty'];
					}

					$price += $child_price;
				}
			}
		}

		return $price;
	}

	/**
	 * Return product type
	 *
	 * Returns the Product type based on the ID received
	 *
	 * @params integer $product_id
	 * @return string $product_type
	 * @since 4.2.0
	 */

	public static function bkap_get_product_type( $product_id ) {
		$product = self::bkap_get_product( $product_id );

		if ( false == $product ) {
			return '';
		}

		$product_type = $product->get_type();

		return $product_type;
	}

	/**
	 * Returns the WooCommerce Product Addons Options total
	 *
	 * This function returns the WooCommerce Product Addons
	 * options total selected by a user for a given product.
	 *
	 * @param int   $diff_days Number of days between start and end. 1 in case of single days
	 * @param array $cart_item_meta Cart Item Meta array
	 * @param int   $product_quantity Product Quantity
	 * @return int Total Price after calculations
	 *
	 * @since 4.5.0 added $product_quantity variable
	 */

	public static function woo_product_addons_compatibility_cart( $diff_days, $cart_item_meta, $product_quantity ) {
		$addons_price = 0;
		if ( class_exists( 'WC_Product_Addons' ) ) {
			if ( isset( $cart_item_meta['addons'] ) && isset( $_POST['total_price_calculated'] ) ) {
				$product_addons = $cart_item_meta['addons'];
				$price          = $_POST['total_price_calculated'];
				$wpaprice       = array();
				foreach ( $product_addons as $key => $val ) {
					$price_type = $val['price_type'];
					switch ( $price_type ) {
						case 'percentage_based':
							$price_percentage = ( $price * $val['price'] / 100 );
							$wpaprice[ $key ] = $price_percentage * $product_quantity;
							break;
						case 'flat_fee':
							$wpaprice[ $key ] = $val['price'];
							break;
						case 'quantity_based':
							$wpaprice[ $key ] = $val['price'] * $product_quantity;
							break;
					}
				}

				if ( count( $wpaprice ) > 0 ) {
					$addons_price = array_sum( $wpaprice );
					if ( isset( $diff_days ) && $diff_days > 1 ) {
						$addons_price = $addons_price * $diff_days;
					}
				}
			}
		}
		return $addons_price;
	}

	/**
	 * Checks if the product requires booking confirmation from admin
	 *
	 * If the Product is a bookable product and requires confirmation,
	 * returns true else returns false
	 *
	 * @param int $product_id
	 * @return boolean
	 * @since 2.5
	 */
	public static function bkap_product_requires_confirmation( $product_id ) {
		$product = self::bkap_get_product( $product_id );

		// Booking Settings.
		$booking_settings      = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
		$requires_confirmation = get_post_meta( $product_id, '_bkap_requires_confirmation', true );
		if (
			is_object( $product )
			&& isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date']
			&& 'on' == $requires_confirmation
		) {
			return true;
		}

		return false;
	}

	/**
	 * Checks if Cart contains bookable products that require confirmation
	 *
	 * Returns true if Cart contains any bookable products that require
	 * confirmation, else returns false.
	 *
	 * @return boolean
	 * @since 2.5
	 */

	public static function bkap_cart_requires_confirmation() {

		$requires = false;

		if ( isset( WC()->cart ) ) {
			foreach ( WC()->cart->cart_contents as $item ) {

				$duplicate_of = self::bkap_get_product_id( $item['product_id'] );

				$requires_confirmation = self::bkap_product_requires_confirmation( $duplicate_of );

				if ( $requires_confirmation && isset( $item['bkap_booking'] ) ) {
					$requires = true;
					break;
				}
			}
		}
		return $requires;

	}

	/**
	 * Checks if an order contains products that require
	 * admin confirmation.
	 *
	 * @param WC_Order $order
	 * @return boolean
	 * @since 2.5
	 */

	public static function bkap_order_requires_confirmation( $order ) {
		$requires = false;

		if ( $order ) {
			foreach ( $order->get_items() as $item ) {
				if ( self::bkap_product_requires_confirmation( $item['product_id'] ) ) {
					$requires = true;
					break;
				}
			}
		}

		return $requires;
	}

	/**
	 * Returns a booking object containing booking details
	 * for a bookable item in an order.
	 * Used in Confirmation/Cancellation emails
	 *
	 * @param integer $item_id
	 * @return stdClass
	 * @since 2.5
	 */

	public static function get_bkap_booking( $item_id, $key = 0 ) {

		global $wpdb;
		global $polylang;

		$booking_object = new stdClass();

		$s_date = get_option( 'book_item-meta-date' );
		$e_date = get_option( 'checkout_item-meta-date' );
		$t_date = get_option( 'book_item-meta-time' );

		$start_date_label = ( '' == $s_date ) ? __( 'Start Date', 'woocommerce-booking' ) : $s_date;
		$end_date_label   = ( '' == $e_date ) ? __( 'End Date', 'woocommerce-booking' ) : $e_date;
		$time_label       = ( '' == $t_date ) ? __( 'Booking Time', 'woocommerce-booking' ) : $t_date;

		// order ID
		$query_order_id = 'SELECT order_id FROM `' . $wpdb->prefix . 'woocommerce_order_items`
                            WHERE order_item_id = %d';
		$get_order_id   = $wpdb->get_results( $wpdb->prepare( $query_order_id, $item_id ) );

		$order_id = 0;
		if ( isset( $get_order_id ) && is_array( $get_order_id ) && count( $get_order_id ) > 0 ) {
			$order_id = $get_order_id[0]->order_id;
		}

		$booking_object->order_id = $order_id;
		$order                    = new WC_order( $order_id );

		if ( isset( $polylang ) ) { // fetching the booking details if the order is placed in different language.
			$ord_lang         = pll_get_post_language( $order_id );
			$start_date_label = pll_translate_string( $start_date_label, $ord_lang );
			$end_date_label   = pll_translate_string( $end_date_label, $ord_lang );
			$time_label       = pll_translate_string( $time_label, $ord_lang );
		}

		// order date
		$post_data                  = get_post( $order_id );
		$booking_object->order_date = ( $post_data == null ) ? '' : $post_data->post_date;

		$product_id                 = wc_get_order_item_meta( $item_id, '_product_id' ); // product ID
		$booking_object->product_id = $product_id;

		$booking_settings = bkap_setting( $product_id );

		// Labels
		$start_date_label                 = apply_filters( 'bkap_change_start_date_label', $start_date_label, $booking_settings );
		$booking_object->start_date_label = $start_date_label;
		$booking_object->end_date_label   = $end_date_label;
		$booking_object->time_label       = $time_label;

		// product name
		$_product                      = self::bkap_get_product( $product_id );
		$booking_object->product_title = ( $_product ) ? $_product->get_title() : '';

		if ( $key > 0 ) {
			$item                      = new WC_Order_Item_Product( $item_id );
			$all_booking_details       = array();
			$date_meta                 = 0;
			$hidden_date_meta          = 0;
			$date_checkout_meta        = 0;
			$hidden_date_checkout_meta = 0;
			$time_slot_meta            = 0;
			$wapbk_time_slot_meta      = 0;
			$resource_id_meta          = 0;
			$person_id_meta            = 0;
			$booking_status_meta       = 0;
			$i                         = 0;

			foreach ( $item->get_meta_data() as $meta_index => $meta ) {

				switch ( $meta->key ) {
					case $start_date_label:
						$all_booking_details[ $date_meta ]['date'] = $meta->value;
						$date_meta                                 = $date_meta + 1;
						break;
					case '_wapbk_booking_date':
						$hidden_date = explode( '-', $meta->value );
						$all_booking_details[ $hidden_date_meta ]['hidden_date'] = $hidden_date[2] . '-' . $hidden_date[1] . '-' . $hidden_date[0];
						$hidden_date_meta                                        = $hidden_date_meta + 1;
						break;
					case $end_date_label:
						$all_booking_details[ $date_checkout_meta ]['date_checkout'] = $meta->value;
						$date_checkout_meta = $date_checkout_meta + 1;
						break;
					case '_wapbk_checkout_date':
						$hidden_date_checkout = explode( '-', $meta->value );
						$all_booking_details[ $hidden_date_checkout_meta ]['hidden_date_checkout'] = $hidden_date_checkout[2] . '-' . $hidden_date_checkout[1] . '-' . $hidden_date_checkout[0];
						$hidden_date_checkout_meta = $hidden_date_checkout_meta + 1;
						break;
					case $time_label:
						$all_booking_details[ $time_slot_meta ]['time_slot'] = $meta->value;
						$time_slot_meta                                      = $time_slot_meta + 1;
						break;
					case '_wapbk_time_slot':
						$all_booking_details[ $wapbk_time_slot_meta ]['wapbk_time_slot'] = $meta->value;
						$wapbk_time_slot_meta = $wapbk_time_slot_meta + 1;
						break;
					case '_resource_id':
						$all_booking_details[ $resource_id_meta ]['resource_id'] = $meta->value;
						$resource_id_meta                                        = $resource_id_meta + 1;
						break;
					case '_person_ids':
						$all_booking_details[ $person_id_meta ]['person_ids'] = $meta->value;
						$person_id_meta                                       = $person_id_meta + 1;
						break;
					case '_wapbk_booking_status':
						$all_booking_details[ $booking_status_meta ]['wapbk_booking_status'] = $meta->value;
						$booking_status_meta = $booking_status_meta + 1;
				}
			}

			$item_data = array(
				'item_booking_status'       => 'wapbk_booking_status',
				'item_hidden_date'          => 'hidden_date',
				'item_hidden_checkout_date' => 'hidden_date_checkout',
				'item_hidden_time'          => 'time_slot',
				'item_booking_date'         => 'date',
				'item_checkout_date'        => 'hidden_date_checkout',
				'item_booking_time'         => 'time_slot',
				'resource_id'               => 'resource_id',
				'person_ids'                => 'person_ids',
			);

			foreach ( $item_data as $item_k => $item_v ) {
				$booking_object->$item_k = '';
				if ( isset( $all_booking_details[ $key ][ $item_v ] ) ) {
					$booking_object->$item_k = $all_booking_details[ $key ][ $item_v ];
				}
			}
		} else {
			// get the booking status.
			$booking_object->item_booking_status = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );

			// get the hidden booking date and time.
			$booking_object->item_hidden_date          = wc_get_order_item_meta( $item_id, '_wapbk_booking_date' );
			$booking_object->item_hidden_checkout_date = wc_get_order_item_meta( $item_id, '_wapbk_checkout_date' );
			$booking_object->item_hidden_time          = wc_get_order_item_meta( $item_id, '_wapbk_time_slot' );

			// get the booking date and time to be displayed.
			$booking_object->item_booking_date  = wc_get_order_item_meta( $item_id, $start_date_label );
			$booking_object->item_checkout_date = wc_get_order_item_meta( $item_id, $end_date_label );
			$booking_object->item_booking_time  = wc_get_order_item_meta( $item_id, $time_label );

			// resource.
			$booking_object->resource_id = wc_get_order_item_meta( $item_id, '_resource_id' );
			$booking_object->person_ids  = wc_get_order_item_meta( $item_id, '_person_ids' );
		}

		// Booking ID.
		$booking_id = self::get_booking_id( $item_id );
		if ( is_array( $booking_id ) ) {
			$booking_object->booking_id = $booking_id[ $key ];
		} else {
			$booking_object->booking_id = $booking_id;
		}

		$booking = new BKAP_Booking( $booking_object->booking_id );

		$booking_object->resource_title = '';
		$booking_object->resource_label = '';

		if ( isset( $booking_object->resource_id ) && '' !== $booking_object->resource_id ) {

			$booking_object->resource_title = get_the_title( $booking_object->resource_id );
			$booking_object->resource_label = get_post_meta( $product_id, '_bkap_product_resource_lable', true );
			if ( '' === $booking_object->resource_label ) {
				$booking_object->resource_label = 'Resource Type';
			}
		}

		$booking_object->person_label = '';
		$booking_object->person_data = '';
		if ( isset( $booking_object->person_ids ) && '' !== $booking_object->person_ids ) {

			$booking_object->person_label = __( 'Persons', 'woocommerce-booking' );
			$booking_object->person_data  = $booking->get_persons_info();
		}

		if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
			$booking_object->billing_email = $order->billing_email;
			$booking_object->customer_id   = $order->user_id;
		} else {
			$booking_object->billing_email = $order->get_billing_email();
			$booking_object->customer_id   = $order->get_user_id();
		}

		$zoom_label                   = bkap_zoom_join_meeting_label( $product_id );
		$booking_object->zoom_meeting = wc_get_order_item_meta( $item_id, $zoom_label );

		$variation_id = $booking->get_variation_id();
		if ( $variation_id > 0 ) {
			$variation_obj                 = wc_get_product( $variation_id );
			$booking_object->product_title = $variation_obj->get_name();
		}

		return apply_filters( 'get_bkap_booking_item_meta_details', $booking_object, $item_id );
	}

	/**
	 * Returns the number of time slots present for a date.
	 * The date needs to be passed in the j-n-Y format
	 *
	 * @param integer $product_id
	 * @param string  $date_check_in
	 * @return integer $number_of_slots
	 * @since 2.6
	 */

	public static function bkap_get_number_of_slots( $product_id, $date_check_in ) {

		// Booking settings.
		$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

		$number_of_slots = 0;
		$timeslots       = array();
		// find the number of slots present for this date/day.
		if ( is_array( $booking_settings['booking_time_settings'] ) && count( $booking_settings['booking_time_settings'] ) > 0 ) {
			if ( array_key_exists( $date_check_in, $booking_settings['booking_time_settings'] ) ) {
				$timeslots = $booking_settings['booking_time_settings'][ $date_check_in ];
			} else { // it's a recurring weekday.
				$weekday         = date( 'w', strtotime( $date_check_in ) );
				$booking_weekday = 'booking_weekday_' . $weekday;
				if ( array_key_exists( $booking_weekday, $booking_settings['booking_time_settings'] ) ) {
					$timeslots = $booking_settings['booking_time_settings'][ $booking_weekday ];
				}
			}
		}

		$timeslots       = apply_filters( 'bkap_get_number_of_slots', $timeslots, $product_id, $date_check_in );
		$number_of_slots = count( $timeslots );

		return $number_of_slots;
	}

	/**
	 * Checks whether a product is bookable or no
	 *
	 * @param int $product_id
	 * @return bool $bookable
	 * @since 2.6
	 */

	public static function bkap_get_bookable_status( $product_id ) {

		$bookable = false;

		// Booking settings
		$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

		if ( isset( $booking_settings ) && isset( $booking_settings['booking_enable_date'] ) && 'on' == $booking_settings['booking_enable_date'] ) {
			$bookable = true;
		}

		return $bookable;
	}

	/**
	 * Get all products and variations and sort alphbetically, return in array (title, id)
	 *
	 * @param boolean $variations - True if variations for a product also need to be returned, else false
	 * @return array $full_product_list
	 * @since 2.6
	 */

	public static function get_woocommerce_product_list( $variations = true, $bkap_option = 'on', $ids = '', $post_status = array(), $meta_query = array() ) {

		$full_product_list = array();
		$all_product_ids   = array();

		if ( empty( $post_status ) ) {
			$post_status = array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit' );
		}

		$args = array(
			'post_type'      => array( 'product' ),
			'posts_per_page' => -1,
			'post_status'    => $post_status,
		);

		if ( empty( $meta_query ) ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_bkap_enable_booking',
					'value'   => $bkap_option,
					'compare' => '=',
				),
			);
		} else {
			$args['meta_query'] = $meta_query;
		}

		$product      = get_posts( $args );
		$parent_array = array();

		foreach ( $product as $k => $value ) {
			$theid        = $value->ID;
			$duplicate_of = self::bkap_get_product_id( $theid );
			$_product     = self::bkap_get_product( $duplicate_of );

			if ( 'variable' == $_product->get_type() && $variations ) {

				$productvariations = $_product->get_available_variations();

				if ( empty( $productvariations ) ) {
					continue;
				}

				$args               = array(
					'post_type'   => 'product_variation',
					'post_status' => array( 'private', 'publish' ),
					'numberposts' => -1,
					'orderby'     => 'menu_order',
					'order'       => 'asc',
					'post_parent' => $duplicate_of, // get parent post-ID.
				);
				$product_variations = get_posts( $args );

				foreach ( $product_variations as $variation ) {
					$thetitle            = '';
					$variation_id        = $variation->ID;
					$product_variation   = new WC_Product_Variation( $variation_id ); // get variations meta.
					$thetitle            = $product_variation->get_formatted_name();
					$full_product_list[] = array( $thetitle, $variation_id );
					$all_product_ids[]   = $variation_id;
				}
			} else {
				$thetitle            = $_product->get_formatted_name();
				$product_type        = $_product->get_type();
				$full_product_list[] = array( $thetitle, $theid );
				$all_product_ids[]   = $theid;
			}
		}

		// sort into alphabetical order, by title.
		sort( $full_product_list );

		if ( '' === $ids ) {
			return $full_product_list;
		} else {
			return $all_product_ids;
		}
	}

	/**
	 * Get all products and sort alphbetically.
	 * Return in array (title, id, fixed block option, price range option)
	 *
	 * @return array $full_product_list
	 * @since 4.1.0
	 */

	public static function get_woocommerce_product_list_f_p() {
		$full_product_list = array();

		$args    = array(
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => array( 'publish', 'pending', 'draft', 'auto-draft', 'future', 'private', 'inherit', 'trash' ),
		);
		$product = query_posts( $args );

		foreach ( $product as $k => $value ) {
			$theid        = $value->ID;
			$duplicate_of = self::bkap_get_product_id( $theid );

			$bkap_fixed_price = false;
			$bkap_range_price = false;

			// Booking settings
			$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

			$bkap_fixed = ( isset( $booking_settings['booking_fixed_block_enable'] ) && '' != $booking_settings['booking_fixed_block_enable'] ) ? $booking_settings['booking_fixed_block_enable'] : '';
			$bkap_range = ( isset( $booking_settings['booking_block_price_enable'] ) && '' != $booking_settings['booking_block_price_enable'] ) ? $booking_settings['booking_block_price_enable'] : '';

			if ( isset( $booking_settings ) && isset( $bkap_fixed ) && 'yes' == $bkap_fixed ) {
				$bkap_fixed_price = true;
			}

			if ( isset( $booking_settings ) && isset( $bkap_range ) && 'yes' == $bkap_range ) {
				$bkap_range_price = true;
			}

			if ( $bkap_fixed_price || $bkap_range_price ) {

				$_product = self::bkap_get_product( $theid );
				$thetitle = $_product->get_formatted_name();

				$full_product_list[] = array( $thetitle, $theid, $bkap_fixed_price, $bkap_range_price );
			}
		}
		wp_reset_query();
		// sort into alphabetical order, by title
		sort( $full_product_list );

		return $full_product_list;

	}

	/**
	 * Adds item meta for bookable products when an order is placed
	 *
	 * @param integer $item_id
	 * @param integer $product_id
	 * @param array   $booking_data
	 * @param bool    $gcal_import
	 * @since 4.1.0
	 */

	public static function bkap_update_order_item_meta( $item_id, $product_id, $booking_data, $gcal_import = false ) {

		global $wpdb;

		// Get Order ID from $item_id.
		$order_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT order_id FROM {$wpdb->prefix}woocommerce_order_items WHERE order_item_id = %d",
				$item_id
			)
		);

		$booking_settings = bkap_setting( $product_id );

		/**
		 * Storing Status Information
		 */
		if ( $gcal_import ) {
			$status = 'paid';
		} else {
			if ( isset( WC()->session ) && WC()->session !== null &&
				WC()->session->get( 'chosen_payment_method' ) === 'bkap-booking-gateway' ) {
				$status = 'pending-confirmation';
			} else {
				$status = 'confirmed';
			}
		}
		$status = apply_filters( 'bkap_booking_status_on_create_order', $status );
		wc_add_order_item_meta( $item_id, '_wapbk_booking_status', $status );

		/**
		 * Storing Start Date Information
		 */

		if ( $booking_data['date'] != '' ) {

			$name        = get_option( 'book_item-meta-date' );
			$name        = ( '' == $name ) ? __( 'Start Date', 'woocommerce-booking' ) : $name;
			$name        = apply_filters( 'bkap_change_checkout_start_date_label', $name, $booking_settings );
			$date_select = $booking_data['date'];

			wc_add_order_item_meta( $item_id, $name, sanitize_text_field( $date_select, true ) );

			// Save Start Date Information to Order Note.
			self::save_booking_information_to_order_note( $item_id, $order_id, sprintf( __( $name . ': %s', 'woocommerce-booking' ), sanitize_text_field( $date_select, true ) ) );
		}

		if ( isset( $booking_data['hidden_date'] ) && $booking_data['hidden_date'] != '' ) {
			$date_booking = bkap_date_as_format( $booking_data['hidden_date'], 'Y-m-d' );
			wc_add_order_item_meta( $item_id, '_wapbk_booking_date', sanitize_text_field( $date_booking, true ) );
		}

		/**
		 * Storing End Date Information
		 */

		if ( isset( $booking_data['date_checkout'] ) && $booking_data['date_checkout'] != '' ) {

			if ( $booking_settings['booking_enable_multiple_day'] == 'on' ) {
				$name_checkout        = get_option( 'checkout_item-meta-date' );
				$name_checkout        = ( '' == $name_checkout ) ? __( 'End Date', 'woocommerce-booking' ) : $name_checkout;
				$date_select_checkout = $booking_data['date_checkout'];

				wc_add_order_item_meta( $item_id, $name_checkout, sanitize_text_field( $date_select_checkout, true ) );
			}
		}

		if ( isset( $booking_data['hidden_date_checkout'] ) && $booking_data['hidden_date_checkout'] != '' ) {
			if ( $booking_settings['booking_enable_multiple_day'] == 'on' ) {
				$date_booking = bkap_date_as_format( $booking_data['hidden_date_checkout'], 'Y-m-d' );
				wc_add_order_item_meta( $item_id, '_wapbk_checkout_date', sanitize_text_field( $date_booking, true ) );
			}
		}

		/**
		 * Storing Fixed Time Information
		 */

		if ( isset( $booking_data['time_slot'] ) && $booking_data['time_slot'] != '' ) {

			$time_select          = $booking_data['time_slot'];
			$exploded_time        = explode( '<br>', $time_select );
			$global_settings      = bkap_global_setting();
			$time_format          = $global_settings->booking_time_format;
			$time_slot_to_display = $meta_data_format = '';

			$name_time_slot = get_option( 'book_item-meta-time' );
			$name_time_slot = ( '' == $name_time_slot ) ? __( 'Booking Time', 'woocommerce-booking' ) : $name_time_slot;

			foreach ( $exploded_time as $key => $value ) {

				if ( $value == '' ) {
					continue;
				}

				$time_exploded = explode( ' - ', $value );

				// Storing time details for display
				$from_time = trim( $time_exploded[0] );
				$to_time   = isset( $time_exploded[1] ) ? trim( $time_exploded[1] ) : '';
				if ( $time_format == '12' ) {
					$from_time = date( 'h:i A', strtotime( $time_exploded[0] ) );
					$to_time   = isset( $time_exploded[1] ) ? date( 'h:i A', strtotime( $time_exploded[1] ) ) : '';
				}
				if ( $to_time != '' ) { // preparing timeslot to display
					$time_slot_to_display .= $from_time . ' - ' . $to_time;
				} else {
					$time_slot_to_display .= $from_time;
				}

				// Storing time details for calculation
				$query_from_time   = date( 'H:i', strtotime( $time_exploded[0] ) );
				$meta_data_format .= $query_from_time;
				if ( isset( $time_exploded[1] ) ) {
					$query_to_time     = date( 'H:i', strtotime( $time_exploded[1] ) );
					$meta_data_format .= ' - ' . $query_to_time;
				}

				$time_slot_to_display .= ',';
				$meta_data_format     .= ',';
			}

			$time_slot_to_display = substr( $time_slot_to_display, 0, -1 );
			$meta_data_format     = substr( $meta_data_format, 0, -1 );

			$time_slot_to_display = apply_filters( 'bkap_update_order_item_meta_timeslot', $time_slot_to_display, $item_id, $product_id, $booking_data );
			wc_add_order_item_meta( $item_id, $name_time_slot, $time_slot_to_display );
			wc_add_order_item_meta( $item_id, '_wapbk_time_slot', $meta_data_format );

			// Save Booking Time Information to Order Note.
			self::save_booking_information_to_order_note( $item_id, $order_id, sprintf( __( $name_time_slot . ': %s', 'woocommerce-booking' ), $time_slot_to_display ) );
		}

		/**
		 * Storing Resource Information
		 */

		if ( isset( $booking_data['resource_id'] ) && $booking_data['resource_id'] != 0 ) {

			$resource_label = Class_Bkap_Product_Resource::bkap_get_resource_label( $product_id );

			if ( $resource_label == '' ) {
				$resource_label = __( 'Resource Type', 'wocommerce-booking' );
			}

			$resource_title = get_the_title( $booking_data['resource_id'] );
			$resource_title = apply_filters( 'bkap_change_resource_title_in_order_item_meta', $resource_title, $product_id );

			wc_add_order_item_meta( $item_id, $resource_label, $resource_title );
			wc_add_order_item_meta( $item_id, '_resource_id', $booking_data['resource_id'] );
		}

		/**
		 * Storing Resource Information
		 */
		if ( isset( $booking_data['persons'] ) && $booking_data['persons'] ) {
			if ( isset( $booking_data['persons'][0] ) ) {
				wc_add_order_item_meta( $item_id, Class_Bkap_Product_Person::bkap_get_person_label( $product_id ), $booking_data['persons'][0] );
			} else {
				foreach ( $booking_data['persons'] as $key => $value ) {
					wc_add_order_item_meta( $item_id, get_the_title( $key ), $value );
				}
			}
			wc_add_order_item_meta( $item_id, '_person_ids', $booking_data['persons'] );
		}

		/**
		 * Storing Duration Based Time Information
		 */

		if ( isset( $booking_data['selected_duration'] ) && $booking_data['selected_duration'] != 0 ) {

			$start_date = $booking_data['hidden_date'];
			$time       = $booking_data['duration_time_slot'];

			$selected_duration = explode( '-', $booking_data['selected_duration'] );

			$hour   = $selected_duration[0];
			$d_type = $selected_duration[1];

			$end_str  = self::bkap_add_hour_to_date( $start_date, $time, $hour, $product_id, $d_type ); // return end date timestamp
			$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta

			// updating end date
			if ( $start_date != $end_date ) {

				$name_checkout = ( '' == get_option( 'checkout_item-meta-date' ) ) ? __( 'End Date', 'woocommerce-booking' ) : get_option( 'checkout_item-meta-date' );

				$bkap_format  = self::bkap_get_date_format(); // get date format set at global
				$end_date_str = date( 'Y-m-d', strtotime( $end_date ) ); // conver date to Y-m-d format

				$end_date_str    = $date_booking . ' - ' . $end_date_str;
				$end_date_string = date( $bkap_format, strtotime( $end_date ) ); // Get date based on format at global level

				$end_date_string = $date_select . ' - ' . $end_date_string;

				// Updating end date field in order item meta
				wc_update_order_item_meta( $item_id, '_wapbk_booking_date', sanitize_text_field( $end_date_str, true ) );
				wc_update_order_item_meta( $item_id, $name, sanitize_text_field( $end_date_string, true ) );
			}

			$endtime        = date( 'H:i', $end_str );// getend time in H:i format
			$back_time_slot = $time . ' - ' . $endtime; // to store time sting in the _wapbk_time_slot key of order item meta

			$startime = self::bkap_get_formated_time( $time ); // return start time based on the time format at global
			$endtime  = self::bkap_get_formated_time( $endtime ); // return end time based on the time format at global

			$time_slot = $startime . ' - ' . $endtime; // to store time sting in the timeslot of order item meta

			// Updating timeslot
			$time_slot_label = get_option( 'book_item-meta-time' );
			$time_slot_label = ( '' == $time_slot_label ) ? __( 'Booking Time', 'woocommerce-booking' ) : $time_slot_label;

			wc_add_order_item_meta( $item_id, $time_slot_label, $time_slot, true );
			wc_add_order_item_meta( $item_id, '_wapbk_time_slot', $back_time_slot, true );

			// Save Booking Time Information to Order Note.
			self::save_booking_information_to_order_note( $item_id, $order_id, sprintf( __( $time_slot_label . ': %s', 'woocommerce-booking' ), $time_slot ) );
		}

		do_action( 'bkap_update_item_meta', $item_id, $product_id, $booking_data );
	}

	/**
	 * Fucntion to create end date timestamp based on the duration settings.
	 *
	 * @param string $start - Start date
	 * @param string $time start time
	 * @param string $hour no of hours
	 * @param int    $product_id Product ID
	 * @param string $d_type Type of duration hours/minutes optional
	 *
	 * @return int $hidden_end_with_time timestamp of end date
	 * @since 4.10.0
	 */

	public static function bkap_add_hour_to_date( $start, $time, $hour, $product_id, $d_type = '' ) {

		$today_midnight        = strtotime( 'today midnight' ); // timestamp of current date midnight
		$date_time_diff        = strtotime( $time ) - $today_midnight; // Differce of midnight and time
		$hidden_date_with_time = strtotime( $start ) + $date_time_diff; // Start date and time timestamp

		if ( $d_type == '' ) {
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$d_setting        = $booking_settings['bkap_duration_settings'];
			$d_type           = $d_setting['duration_type'];
		}

		if ( $d_type == 'hours' ) {

			$hidden_end_with_time = $hidden_date_with_time + ( (int) $hour * 3600 );
			// $hidden_end_with_time = $hidden_date_with_time + ( 2 * 3600 );
		} else {
			$hidden_end_with_time = $hidden_date_with_time + ( (int) $hour * 60 );
		}

		// $end_date = date( 'YmdHis', $hidden_end_with_time );

		return $hidden_end_with_time;

	}
	/**
	 * Creates a list of orders that are not yet exported to GCal
	 *
	 * @param integer $user_id - To check if it's being run for a tour operator
	 * @return array $total_orders_to_export
	 * @since 2.6
	 */

	public static function bkap_get_total_bookings_to_export( $user_id ) {

		// get the user role.
		$user_id = get_current_user_id();
		$user    = new WP_User( $user_id );
		if ( 'tour_operator' == $user->roles[0] ) {
			$event_items = get_the_author_meta( 'tours_event_item_ids', $user_id );
		} else {
			$event_items = get_option( 'bkap_event_item_ids' );
		}

		if ( $event_items == '' || $event_items == '{}' || $event_items == '[]' || $event_items == 'null' ) {
			$event_items = array();
		}

		// other logic.
		$current_time = current_time( 'timestamp' );
		$current_date = date( 'Y-m-d', $current_time );

		$args = array(
			'post_type'      => 'bkap_booking',
			'post_status'    => array( 'paid', 'pending-confirmation', 'confirmed' ),
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => '_bkap_start',
					'value'   => date( 'YmdHis', strtotime( $current_date ) ),
					'compare' => '>=',
				),
				array(
					'key'     => '_bkap_gcal_event_uid',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		$future_bookings = get_posts( $args );
		$item_ids        = array();
		foreach ( $future_bookings as $posts ) {
			$bkap_booking     = new BKAP_Booking( $posts->ID );
			$bookings_array[] = $bkap_booking;
			$item_id          = $bkap_booking->custom_fields['_bkap_order_item_id'][0];
			if ( ! in_array( $item_id, $event_items ) ) {
				$item_ids[] = $bkap_booking->custom_fields['_bkap_order_item_id'][0];
			}
		}

		return $item_ids;
	}

	/**
	 * Returns an array with currency data
	 *
	 * @return array $wc_price_args - array of currency data such as currency, separators etc.
	 * @since 2.6.3
	 */

	public static function get_currency_args() {
		if ( function_exists( 'icl_object_id' ) ) {
			global $woocommerce_wpml;

			if ( isset( $woocommerce_wpml->settings['enable_multi_currency'] ) && $woocommerce_wpml->settings['enable_multi_currency'] == '2' ) {
				if ( WCML_VERSION >= '3.8' ) {
					$currency = $woocommerce_wpml->multi_currency->get_client_currency();
				} else {
					$currency = $woocommerce_wpml->multi_currency_support->get_client_currency();
				}
			} else {
				$currency = get_woocommerce_currency();
			}
			$wc_price_args = array(
				'currency'           => $currency,
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimals'           => wc_get_price_decimals(),
				'price_format'       => get_woocommerce_price_format(),
			);
		} else {
			$wc_price_args = array(
				'currency'           => get_woocommerce_currency(),
				'decimal_separator'  => wc_get_price_decimal_separator(),
				'thousand_separator' => wc_get_price_thousand_separator(),
				'decimals'           => wc_get_price_decimals(),
				'price_format'       => get_woocommerce_price_format(),
			);
		}
		return $wc_price_args;
	}

	/**
	 * The below function adds notices to be displayed.
	 * It displays the notices as well using print notices function.
	 * This helps in displaying notices without having to reload the page.
	 *
	 * @since 2.9
	 */

	public static function bkap_add_notice() {
		$product_id = $_POST['post_id'];

		$message = '';
		if ( isset( $_POST['message'] ) ) {
			$message = $_POST['message'];
		}

		$notice_type = 'error';
		if ( isset( $_POST['notice_type'] ) ) {
			$notice_type = $_POST['notice_type'];
		}

		if ( ( isset( $message ) && '' != $message ) ) {
			wc_add_notice( __( $message, 'woocommerce-booking' ), $notice_type );
			wc_print_notices();
		}
		die;
	}

	/**
	 * This function clears any notices set in the session
	 *
	 * @since 2.9
	 */

	public static function bkap_clear_notice() {
		wc_clear_notices();
		die;
	}

	/**
	 * This function will return the differance days between two dates.
	 * An object similar to the one returned by the new DateTime() is returned.
	 *
	 * @param object $date1 - Date 1
	 * @param object $date2 - Date 2
	 * @return object $result - contains difference details between 2 dates
	 * @since 3.1
	 */

	public static function dateTimeDiff( $date1, $date2 ) {

		$one = $date1->format( 'U' );
		$two = $date2->format( 'U' );

		$invert = false;
		if ( $one > $two ) {
			list( $one, $two ) = array( $two, $one );
			$invert            = true;
		}

		$key          = array( 'y', 'm', 'd', 'h', 'i', 's' );
		$a            = array_combine( $key, array_map( 'intval', explode( ' ', date( 'Y m d H i s', $one ) ) ) );
		$b            = array_combine( $key, array_map( 'intval', explode( ' ', date( 'Y m d H i s', $two ) ) ) );
		$result       = new stdClass();
		$current_time = current_time( 'timestamp' );
		$date         = ( date( 'd', $current_time ) ) - 1;

		$result->y      = $b['y'] - $a['y'];
		$result->m      = $b['m'] - $a['m'];
		$result->d      = $date;
		$result->h      = $b['h'] - $a['h'];
		$result->i      = $b['i'] - $a['i'];
		$result->s      = $b['s'] - $a['s'];
		$result->invert = $invert ? 1 : 0;
		$result->days   = intval( abs( ( $one - $two ) / 86400 ) );

		if ( $invert ) {
			self::_date_normalize( $a, $result );
		} else {
			self::_date_normalize( $b, $result );
		}

		return $result;
	}

	/**
	 * Calculates the difference between the dates.
	 * Called  from dateTimeDiff()
	 *
	 * @param integer     $start
	 * @param integer     $end
	 * @param integer adj
	 * @param string      $a
	 * @param string      $b
	 * @param array       $result
	 * @return array $result
	 * @since 3.1
	 */

	public static function _date_range_limit( $start, $end, $adj, $a, $b, $result ) {
		$result = (array) $result;
		if ( $result[ $a ] < $start ) {
			$result[ $b ] -= intval( ( $start - $result[ $a ] - 1 ) / $adj ) + 1;
			$result[ $a ] += $adj * intval( ( $start - $result[ $a ] - 1 ) / $adj + 1 );
		}

		if ( $result[ $a ] >= $end ) {
			$result[ $b ] += intval( $result[ $a ] / $adj );
			$result[ $a ] -= $adj * intval( $result[ $a ] / $adj );
		}

		return $result;
	}

	/**
	 * Calculates the Ramge Limit.
	 * Called from dateTimeDiff()
	 *
	 * @param array  $base
	 * @param object $result
	 * @return object $result
	 * @since 3.1
	 */

	public static function _date_range_limit_days( $base, $result ) {
		$days_in_month_leap = array( 31, 31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$days_in_month      = array( 31, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );

		self::_date_range_limit( 1, 13, 12, 'm', 'y', $base );

		$year  = $base['y'];
		$month = $base['m'];

		if ( ! $result['invert'] ) {
			while ( $result['d'] < 0 ) {
				$month--;
				if ( $month < 1 ) {
					$month += 12;
					$year--;
				}

				$leapyear = $year % 400 == 0 || ( $year % 100 != 0 && $year % 4 == 0 );
				$days     = $leapyear ? $days_in_month_leap[ $month ] : $days_in_month[ $month ];

				$result['d'] += $days;
				$result['m']--;
			}
		} else {
			while ( $result['d'] < 0 ) {
				$leapyear = $year % 400 == 0 || ( $year % 100 != 0 && $year % 4 == 0 );
				$days     = $leapyear ? $days_in_month_leap[ $month ] : $days_in_month[ $month ];

				$result['d'] += $days;
				$result['m']--;

				$month++;
				if ( $month > 12 ) {
					$month -= 12;
					$year++;
				}
			}
		}

		return $result;
	}

	/**
	 * Normalize the Date. Called from dateTimeDiff()
	 *
	 * @param array  $base
	 * @param object $result
	 * @return array $result
	 * @since 3.1
	 */

	public static function _date_normalize( $base, $result ) {
		$result = self::_date_range_limit( 0, 60, 60, 's', 'i', $result );
		$result = self::_date_range_limit( 0, 60, 60, 'i', 'h', $result );
		$result = self::_date_range_limit( 0, 24, 24, 'h', 'd', $result );
		$result = self::_date_range_limit( 0, 12, 12, 'm', 'y', $result );

		$result = self::_date_range_limit_days( $base, $result );

		$result = self::_date_range_limit( 0, 12, 12, 'm', 'y', $result );

		return $result;
	}

	/**
	 * Ensures whether the date is a future date.
	 * Used when exporting bookings to Google Calendar
	 *
	 * @param string $date - Booking Date
	 * @return boolean $future_date_set
	 * @since 2.6
	 */

	public static function bkap_check_date_set( $date ) {
		$future_date_set = false;

		if ( isset( $date ) && '' != $date ) {

			if ( strtotime( $date ) > current_time( 'timestamp' ) ) {
				$future_date_set = true;
			}
		}
		return $future_date_set;
	}

	/**
	 * Returns TRUE if a cart contains bookable products
	 *
	 * @return boolean $contains_bookable
	 * @since 2.5
	 */

	public static function bkap_cart_contains_bookable() {

		$contains_bookable = false;

		$cart_items_count = WC()->cart->cart_contents_count;

		if ( $cart_items_count > 0 ) {
			foreach ( WC()->cart->cart_contents as $item ) {

				$is_bookable = self::bkap_get_bookable_status( $item['product_id'] );

				if ( $is_bookable ) {
					$contains_bookable = true;
					break;
				}
			}
		}
		return $contains_bookable;

	}

	/**
	 * Create and return an array of valid Booking statuses
	 *
	 * @return array $allowed_status - booking statuses
	 * @since 4.0.0
	 */

	static function get_bkap_booking_statuses() {

		$allowed_status = apply_filters(
			'bkap_get_bkap_booking_statuses',
			array(
				'pending-confirmation' => 'Pending Confirmation',
				'confirmed'            => 'Confirmed',
				'paid'                 => 'Paid',
				'cancelled'            => 'Cancelled',
			)
		);

		return $allowed_status;
	}

	/**
	 * Booking Status for showing on front end view booking page.
	 *
	 * @since 5.10.0
	 */
	public static function bkap_view_bookings_status() {

		$booking_status = array(
			'all'                  => __( 'All', 'woocommerce-booking' ),
			'complete'             => __( 'Complete', 'woocommerce-booking' ),
			'paid'                 => __( 'Paid & Confirmed', 'woocommerce-booking' ),
			'confirmed'            => __( 'Confirmed', 'woocommerce-booking' ),
			'pending-confirmation' => __( 'Pending Confirmation', 'woocommerce-booking' ),
			'cancelled'            => __( 'Cancelled', 'woocommerce-booking' ),
		);

		return $booking_status;
	}

	/**
	 * Returns translated status label.
	 *
	 * @param string $status_label The label to be translated.
	 * @return string The translated status label.
	 * @since 5.2.1
	 */
	public static function get_bkap_translated_status_label( $status_label ) {
		$translated_label = '';
		switch ( $status_label ) {
			case 'Pending Confirmation':
				$translated_label = __( 'Pending Confirmation', 'woocommerce-booking' );
				break;
			case 'Confirmed':
				$translated_label = __( 'Confirmed', 'woocommerce-booking' );
				break;
			case 'Paid':
				$translated_label = __( 'Paid', 'woocommerce-booking' );
				break;
			case 'Cancelled':
				$translated_label = __( 'Cancelled', 'woocommerce-booking' );
				break;
			default:
				$translated_label = __( $status_label, 'woocommerce-booking' );
				break;
		}

		return apply_filters( 'bkap_translated_status_label', $translated_label );
	}

	/**
	 * Create and return an array of valid event statuses
	 * - For Imported Events from Google Calendar
	 *
	 * @return array $allowed_status - event statuses
	 * @since 4.0.0
	 */

	static function get_bkap_event_statuses() {

		return $allowed_status = array(
			'bkap-unmapped' => 'Un-mapped',
			'bkap-mapped'   => 'Mapped',
			'bkap-deleted'  => 'Deleted',
		);

	}

	/**
	 * Fetches the Booking Post ID using the Item ID
	 *
	 * @param int $item_id - Item ID
	 * @return int booking ID from wp_post
	 * @since 4.0.0
	 */

	static function get_booking_id( $item_id ) {
		global $wpdb;

		$query_posts = 'SELECT post_id FROM `' . $wpdb->prefix . 'postmeta`
                   where meta_key = %s
                   AND meta_value = %d';

		$get_posts = $wpdb->get_results( $wpdb->prepare( $query_posts, '_bkap_order_item_id', $item_id ) );

		$count = count( $get_posts );

		if ( count( $get_posts ) > 1 ) {
			$bookingids = array();

			foreach ( $get_posts as $key => $value ) {
				array_push( $bookingids, $value->post_id );
			}

			return $bookingids;
		} elseif ( count( $get_posts ) > 0 ) {
			return $get_posts[0]->post_id;
		} else {
			return false;
		}
	}

	/**
	 * This function Checks if the Specific Date contains time slot or not.
	 *
	 * @param integer $product_id
	 * @return boolean $timeslots_present
	 * @since 4.2
	 */

	public static function bkap_check_specific_date_has_timeslot( $product_id ) {

		$booking_settings       = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
		$booking_specific_dates = ( isset( $booking_settings['booking_specific_date'] ) ) ? $booking_settings['booking_specific_date'] : array();

		$day = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );

		if ( '' != $booking_specific_dates && count( $booking_specific_dates ) > 0 ) {
			$booking_time_settings_key = array();
			if ( isset( $booking_settings['booking_time_settings'] ) && count( $booking_settings['booking_time_settings'] ) > 0 ) {
				$booking_time_settings_key = array_keys( $booking_settings['booking_time_settings'] );
			}

			foreach ( $booking_specific_dates as $booking_specific_dates_key => $booking_specific_dates_value ) {

				if ( strtotime( $booking_specific_dates_key ) <= $day ) {
					continue;
				}

				if ( is_array( $booking_time_settings_key ) && count( $booking_time_settings_key ) > 0 ) {
					if ( in_array( $booking_specific_dates_key, $booking_time_settings_key ) ) {

						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * The function checks if the passed product ID is listed
	 * as a child in _children postmeta.
	 *
	 * If yes, then it returns the parent product ID else it returns 0
	 *
	 * @param int $child_id
	 * @return int $parent_id
	 *
	 * @since 3.5.2
	 */

	public static function bkap_get_parent_id( $child_id ) {
		$parent_id = '';

		global $wpdb;

		$query_children = 'SELECT post_id, meta_value FROM `' . $wpdb->prefix . 'postmeta`
                           WHERE meta_key = %s';

		$results_children = $wpdb->get_results( $wpdb->prepare( $query_children, '_children' ) );

		if ( is_array( $results_children ) && count( $results_children ) > 0 ) {

			foreach ( $results_children as $r_value ) {
				// check if the meta value is non blanks
				if ( $r_value->meta_value != '' ) {
					// unserialize the data, create an array
					$child_array = maybe_unserialize( $r_value->meta_value );

					// if child ID is present in the array, we've found the parent
					if ( is_array( $child_array ) && ( in_array( $child_id, $child_array ) ) ) {
						$parent_id = $r_value->post_id;
						break;
					}
				}
			}
		}
		return $parent_id;
	}

	/**
	 * Get WooCommerce Product object
	 *
	 * @param int|string $product_id Product ID
	 * @return WC_Product Product Object
	 * @since 4.1.1
	 */

	public static function bkap_get_product( $product_id ) {
		return wc_get_product( $product_id );
	}

	/**
	 * Get Gravity Forms Addon Data for pricing purpose from cart item
	 *
	 * @param array $cart_item Cart Item Array
	 * @return array Addon Pricing array
	 * @since 4.2
	 */

	public static function bkap_get_cart_item_addon_data( $cart_item ) {

		$addon_pricing_data = array();

		// For compatibility with Gravity Forms
		if ( isset( $cart_item['_gform_total'] ) && $cart_item['_gform_total'] > 0 ) {
			$addon_pricing_data['gf_options'] = $cart_item['_gform_total'];
		}

		// For compatibility with WooCommerce Product Addons
		if ( isset( $cart_item['addons'] ) && count( $cart_item['addons'] ) > 0 ) {
			$addon_pricing_data['wpa_options'] = self::bkap_get_wpa_cart_totals( $cart_item );
		}

		// For compatibility with WooCommerce Product Addons
		if ( isset( $cart_item['pricing_item_meta_data'] ) && count( $cart_item['pricing_item_meta_data'] ) > 0 ) {
			$addon_pricing_data['_measurement_needed'] = $cart_item['pricing_item_meta_data']['_measurement_needed'];
		}

		return $addon_pricing_data;
	}

	/**
	 * Get WooCommerce Product addons prices
	 *
	 * @param array $cart_item Cart Item
	 * @return float Addon Total
	 * @since 4.2
	 */

	public static function bkap_get_wpa_cart_totals( $cart_item ) {

		$wpa_addons_total = 0;
		foreach ( $cart_item['addons'] as $addon_key => $addon_value ) {
			$wpa_addons_total = $wpa_addons_total + $addon_value['price'];
		}

		return $wpa_addons_total;
	}

	/**
	 * Get Addon data for pricing purpose from Order Item data
	 * Includes Gravity Form Addons as well as WooCommerce Product Addons
	 *
	 * @param WC_Order_Item $order_item Order Item object
	 * @return array Addon pricing array
	 * @since 4.2
	 */

	public static function bkap_get_order_item_addon_data( $order_item ) {

		$addon_pricing_data = array();

		// For compatibility with Gravity Forms
		$currency_symbol = get_woocommerce_currency_symbol();
		if ( isset( $order_item['Total'] ) && $order_item['Total'] !== '' ) {
			$addon_pricing_data['gf_options'] = str_replace( html_entity_decode( $currency_symbol ), '', $order_item['Total'] );
		}

		// For compatibility with WooCommerce Product Addons
		if ( isset( $order_item['_wapbk_wpa_prices'] ) && $order_item['_wapbk_wpa_prices'] !== '' ) {
			$addon_pricing_data['wpa_options'] = $order_item['_wapbk_wpa_prices'];
		}

		return $addon_pricing_data;
	}

	/**
	 * Check if cart item passed is composite in some parent product
	 *
	 * @param array $cart_item Cart Item Array
	 * @return bool
	 * @since 4.7.0
	 */

	public static function bkap_is_cartitem_composite( $cart_item ) {

		if ( isset( $cart_item['composite_parent'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if order item is composite in some parent product
	 *
	 * @param WC_Order_Item $item Order Item object
	 * @return bool
	 * @since 4.7.0
	 */

	public static function bkap_is_orderitem_composite( $item ) {

		if ( isset( $item['_composite_parent'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if cart item passed is bundled in some parent product
	 *
	 * @param array $cart_item Cart Item Array
	 * @return bool
	 * @since 4.2
	 */

	public static function bkap_is_cartitem_bundled( $cart_item ) {

		if ( isset( $cart_item['bundled_by'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Check if order item is bundled in some parent product
	 *
	 * @param WC_Order_Item $item Order Item object
	 * @return bool
	 * @since 4.2
	 */

	public static function bkap_is_orderitem_bundled( $item ) {

		if ( isset( $item['_bundled_by'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Get cart configuration for Bundled Products
	 *
	 * @param WC_Product $product Product Object
	 * @return array Cart Config
	 * @since 4.2
	 */

	public static function bkap_bundle_add_to_cart_config( $product ) {

		$posted_config = array();

		if ( is_object( $product ) && 'bundle' === $product->get_type() ) {

			$product_id    = WC_PB_Core_Compatibility::get_id( $product );
			$bundled_items = $product->get_bundled_items();

			if ( ! empty( $bundled_items ) ) {

				$posted_data = $_POST;

				if ( empty( $_POST['add-to-cart'] ) && ! empty( $_GET['add-to-cart'] ) ) {
					$posted_data = $_GET;
				}

				foreach ( $bundled_items as $bundled_item_id => $bundled_item ) {

					$posted_config[ $bundled_item_id ] = array();

					$bundled_product_id   = $bundled_item->product_id;
					$bundled_product_type = $bundled_item->product->get_type();
					$is_optional          = $bundled_item->is_optional();

					$bundled_item_quantity_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_quantity_' . $bundled_item_id;
					$bundled_product_qty               = isset( $posted_data[ $bundled_item_quantity_request_key ] ) ? absint( $posted_data[ $bundled_item_quantity_request_key ] ) : $bundled_item->get_quantity();

					$posted_config[ $bundled_item_id ]['product_id'] = $bundled_product_id;

					if ( $bundled_item->has_title_override() ) {
						$posted_config[ $bundled_item_id ]['title'] = $bundled_item->get_raw_title();
					}

					if ( $is_optional ) {

						/** Documented in method 'get_posted_bundle_configuration'. */
						$bundled_item_selected_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_selected_optional_' . $bundled_item_id;

						$posted_config[ $bundled_item_id ]['optional_selected'] = isset( $posted_data[ $bundled_item_selected_request_key ] ) ? 'yes' : 'no';

						if ( 'no' === $posted_config[ $bundled_item_id ]['optional_selected'] ) {
							$bundled_product_qty = 0;
						}
					}

					$posted_config[ $bundled_item_id ]['quantity'] = $bundled_product_qty;

					// Store variable product options in stamp to avoid generating the same bundle cart id.
					if ( 'variable' === $bundled_product_type || 'variable-subscription' === $bundled_product_type ) {

						$attr_stamp = array();
						$attributes = $bundled_item->product->get_attributes();

						foreach ( $attributes as $attribute ) {

							if ( ! $attribute['is_variation'] ) {
								continue;
							}

							$taxonomy = WC_PB_Core_Compatibility::wc_variation_attribute_name( $attribute['name'] );

							/** Documented in method 'get_posted_bundle_configuration'. */
							$bundled_item_taxonomy_request_key = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_' . $taxonomy . '_' . $bundled_item_id;

							if ( isset( $posted_data[ $bundled_item_taxonomy_request_key ] ) ) {

								// Get value from post data.
								if ( $attribute['is_taxonomy'] ) {
									$value = sanitize_title( stripslashes( $posted_data[ $bundled_item_taxonomy_request_key ] ) );
								} else {
									$value = wc_clean( stripslashes( $posted_data[ $bundled_item_taxonomy_request_key ] ) );
								}

								$attr_stamp[ $taxonomy ] = $value;
							}
						}

						$posted_config[ $bundled_item_id ]['attributes']   = $attr_stamp;
						$bundled_item_variation_id_request_key             = apply_filters( 'woocommerce_product_bundle_field_prefix', '', $product_id ) . 'bundle_variation_id_' . $bundled_item_id;
						$posted_config[ $bundled_item_id ]['variation_id'] = isset( $posted_data[ $bundled_item_variation_id_request_key ] ) ? $posted_data[ $bundled_item_variation_id_request_key ] : '';
					}
				}
			}
		}

		return $posted_config;
	}

	/**
	 * Returns an array of Booking IDs for the order ID sent.
	 *
	 * @param integer $order_id
	 * @since 4.2.0
	 */

	public static function get_booking_ids_from_order_id( $order_id ) {

		$booking_ids = array();
		if ( absint( $order_id ) > 0 ) {

			global $wpdb;
			if ( false !== get_post_status( $order_id ) ) {

				$order_query = 'SELECT ID from `' . $wpdb->prefix . 'posts`
	                           WHERE post_parent = %d && post_type = %s';

				$results = $wpdb->get_results( $wpdb->prepare( $order_query, $order_id, 'bkap_booking' ) );

				if ( isset( $results ) && count( $results ) > 0 ) {
					foreach ( $results as $r_value ) {
						$booking_ids[] = $r_value->ID;
					}
				}
			}
		}

		return $booking_ids;
	}

	/**
	 * Returns an array of all bookings for passed status.
	 *
	 * @param string|array $post_status - Valid Booking Statuses
	 * @param array        $additional_args - that can be added to the query.
	 * @return array $bookings_array - Bookings present in wp_posts
	 * @since 4.2.0
	 */

	public static function bkap_get_bookings( $post_status, $additional_args = array() ) {

		$bookings_array = array();
		$search         = ( isset( $_GET['s'] ) && '' !== $_GET['s'] ) ? $_GET['s'] : '';
		$date           = ( isset( $_GET['m'] ) && '' !== $_GET['m'] ) ? $_GET['m'] : '';

		// Check if we are on the CSV or Print page and that we have a valid meta query that has been saved in a transient.

		// phpcs:ignore WordPress.Security.NonceVerification
		$current_page = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : '';

		if ( ! empty( $current_page ) && ( strpos( $current_page, 'bkap-print' ) !== false || strpos( $current_page, 'bkap-csv' ) !== false ) ) {

			$saved_args = get_transient( 'bkap_vendors_view_bookings_meta_query' );

			if ( $saved_args ) {
				$additional_args = array_merge( $additional_args, $saved_args );
			}
		}

		$args = array(
			'post_status' => $post_status,
			's'           => $search,
			'meta_query'  => array(
				array(
					'key'     => '_bkap_start',
					'value'   => $date,
					'compare' => 'LIKE',
				),
				array(
					'key'     => '_bkap_end',
					'value'   => $date,
					'compare' => 'LIKE',
				),
			),
		);

		$wp_args = wp_parse_args(
			$args,
			array(
				'post_status'    => array( 'draft', 'cancelled', 'confirmed', 'paid', 'pending-confirmation' ),
				'post_type'      => 'bkap_booking',
				'parent'         => null,
				'posts_per_page' => -1,
				'meta_query'     => array(),
				'orderby'        => 'date',
				'order'          => 'DESC',
				'return'         => 'objects',
			)
		);

		if ( isset( $additional_args ) && ! empty( $additional_args ) ) {
			$wp_args = array_merge( $wp_args, $additional_args );
		}

		$booking = new WP_Query( $wp_args );

		foreach ( $booking->posts as $posts ) {
			$bookings_array[] = new BKAP_Booking( $posts );
		}

		wp_reset_query();

		return $bookings_array;
	}

	/**
	 * Returns the Status Label
	 *
	 * @param string $status - Booking status
	 * @return string
	 * @since 4.2.0
	 */

	public static function get_mapped_status( $status ) {

		switch ( $status ) {
			case 'paid':
				return __( 'Paid and Confirmed', 'woocommerce-booking' );
				break;

			case 'confirmed':
				return __( 'Confirmed', 'woocommerce-booking' );
				break;

			case 'pending-confirmation':
				return __( 'Pending Confirmation', 'woocommerce-booking' );
				break;

			case 'cancelled':
				return __( 'Cancelled', 'woocommerce-booking' );
				break;

			case 'draft':
				return __( 'Draft', 'woocommerce-booking' );
				break;

			default:
				return __( 'Paid and Confirmed', 'woocommerce-booking' );
				break;
		}
	}

	/**
	 * Return the date format set at Global Booking Settings page
	 *
	 * @since 4.9.0
	 */
	public static function bkap_get_date_format( $global_settings = array() ) {

		$date_formats = bkap_get_book_arrays( 'bkap_date_formats' );

		if ( empty( $global_settings ) ) {
			$global_settings = bkap_global_setting(); // get the global settings to find the date formats
		}
		$date_format_set = $date_formats[ $global_settings->booking_date_format ];

		return $date_format_set;
	}

	/**
	 * Return time in given format
	 *
	 * @since 4.9.0
	 */

	public static function bkap_time_by_format( $time, $format ) {

		return date( $format, strtotime( $time ) );
	}

	/**
	 * Return time format set in the Global Booking Settings-> Time Format
	 *
	 * @since 4.10.0
	 * @param array $g_setting Global Booking Setting Array. Default will be blank
	 */

	public static function bkap_get_time_format( $g_setting = array() ) {

		if ( empty( $g_setting ) ) {
			$g_setting = bkap_global_setting();
		}

		if ( $g_setting->booking_time_format == '12' ) {
			$time_format = 'h:i A';
		} else {
			$time_format = 'H:i';
		}

		return $time_format;
	}

	/**
	 * Display the time as per set in the Global Booking Settings-> Time Format
	 *
	 * @since 4.10.0
	 * @param string $time Time string
	 * @param array  $g_setting Global Booking Setting Array. Default will be blank
	 */

	public static function bkap_get_formated_time( $time, $g_setting = array() ) {
		$format          = self::bkap_get_time_format( $g_setting );
		$time_as_formate = self::bkap_time_by_format( $time, $format );

		return $time_as_formate;
	}

	/**
	 * Return the future bookings for a product
	 *
	 * @param int $product_id Product ID
	 * @return array
	 * @since 4.10.0
	 */

	public static function bkap_get_bookings_by_product( $product_id ) {

		$bookings            = self::bkap_get_bookings( array( 'paid', 'confirmed' ) );
		$bookings_by_product = array();
		$current_time        = current_time( 'timestamp' );
		$format              = self::bkap_get_date_format();
		$current_date        = date( $format );

		foreach ( $bookings as $booking ) {
			//$booking         = new BKAP_Booking( $value->get_id() );
			$booking_product = $booking->get_product_id();
			$start_date      = $booking->get_start_date();

			if ( $booking_product == $product_id && strtotime( $start_date ) > strtotime( $current_date ) ) {
				array_push( $bookings_by_product, $booking );
			}
		}

		return $bookings_by_product;
	}

	/**
	 * Return all orders which have bookable products
	 *
	 * @return array
	 * @since 4.10.0
	 */

	public static function bkap_get_orders_with_bookings( $additional_args ) {
		$bookings  = self::bkap_get_bookings( array( 'paid', 'confirmed' ), $additional_args );
		$order_ids = array();

		foreach ( $bookings as $key => $value ) {
			$booking  = new BKAP_Booking( $value->get_id() );
			$order_id = $booking->get_order_id();

			array_push( $order_ids, $order_id );
		}

		return $order_ids;
	}

	/**
	 * Return date if set in the session or cookie
	 *
	 * @return string
	 * @since 4.14.0
	 */

	public static function bkap_date_from_session_cookie( $date ) {

		if ( isset( WC()->session )
			&& ! is_null( WC()->session->get( $date ) )
			&& WC()->session->get( $date ) != ''
		) {
			$sdate = WC()->session->get( $date );
		} elseif ( isset( $_COOKIE[ $date ] ) && $_COOKIE[ $date ] != '' ) {
			$sdate = $_COOKIE[ $date ];
		} else {
			$sdate = false;
		}

		return $sdate;
	}

	/**
	 * Get filter value from react select option array.
	 *
	 * @param string $attribute Array of Attribute.
	 * @param string $array_glue Optional
	 * @return array
	 *
	 * @since 4.15.0
	 */
	public static function get_attribute_value( $attribute, $array_glue = ',' ) {

		if ( isset( $attribute ) && '' !== $attribute && isset( $attribute[0] ) && is_array( $attribute[0] ) ) {
			$values = wp_list_pluck( $attribute, 'value' );
			$value  = implode( $array_glue, $values );
			return $value;
		}

		return is_array( $attribute ) ? $attribute['value'] : $attribute;
	}

	/**
	 * Get resources comma seperated title list.
	 *
	 * @param in $id
	 * @return boolean|array
	 *
	 * @since 4.15.0
	 */
	public static function get_bookable_resources( $id ) {
		$resource_ids = get_post_meta( $id, '_bkap_product_resources', true );

		if ( empty( $resource_ids ) ) {
			return false;
		}

		$resource_list = array();

		foreach ( $resource_ids as $id ) {
			$resource_list[ $id ] = get_the_title( $id );
			// array_push( $resource_list, get_the_title( $id ) );
		}

		// $resources = implode( ', ' ,$resource_list );

		return $resource_list;
	}

	/**
	 * Save Booking Information to Order Note.
	 *
	 * This function checks if the setting is on to save the booking information to Order note and saves if the option is selected.
	 *
	 * @param integer $item_id Order Item ID
	 * @param integer $order_id Order ID.
	 * @param string  $note Note to be saved containing Booking Information.
	 * @since 5.0
	 */
	public static function save_booking_information_to_order_note( $item_id, $order_id, $note ) {

		global $wpdb;

		// Check for setting and stop if not set to on.
		$settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
		if ( ! isset( $settings->show_order_info_note ) || 'on' !== $settings->show_order_info_note ) {
			return;
		}

		// Get Order Object.
		$order = wc_get_order( $order_id );

		// Check if function is called with $item_id set. If $item_id has not been set, then get $item_id.
		if ( empty( $item_id ) ) {
			$order_items = $order->get_items();
			$order_item  = reset( $order_items ); // Get first index in array as we are not concerned with the rest since they would basically contain the same information.
			$item_id     = $order_item->get_id(); // Get only the first index.
		}

		// Check if Order Note ID has been saved. This is necessary to ensure that a single note is used to save booking information.
		$order_note_id = (int) wc_get_order_item_meta( $item_id, '_wapbk_order_note_id', true );

		if ( $order_note_id > 0 ) {
			// Order Note already exists for this Order. Now we update note instead.
			// But we need to fetch previous data already existing in the Order Note.
			$previous_note = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT comment_content FROM {$wpdb->prefix}comments WHERE comment_ID = %d",
					$order_note_id
				)
			);

			// Merge previous note with new note.
			// Check if Zoom Meeting Link already exists in Previous Note (in cases where Order Status is changed to processing more than once).
			if ( strpos( $previous_note, 'Zoom Meeting' ) !== false ) {
				// Append New text to ZOom Meeting label to differentitate
				$note = 'New ' . $note;
			}

			$note = $previous_note . "\n" . $note;

			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->prefix}comments SET comment_content = %s WHERE comment_ID = %d",
					$note,
					$order_note_id
				)
			);
		} else {
			// Order Note does not exist. Create new note and save the note ID to database.
			$order_note_id = $order->add_order_note( $note );
			wc_add_order_item_meta( $item_id, '_wapbk_order_note_id', $order_note_id );
		}
	}
}
