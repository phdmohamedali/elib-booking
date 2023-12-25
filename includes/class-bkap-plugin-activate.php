<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Adding Plugin Meta
 *
 * @author   Tyche Softwares
 * @package  BKAP/Plugin-Activate
 * @category Classes
 * @class    Bkap_Plugin_Activate
 */

if ( ! class_exists( 'Bkap_Plugin_Activate' ) ) {

	/**
	 * Class Bkap_Plugin_Activate.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Plugin_Activate {

		/**
		 * Short Description. (use period)
		 *
		 * Long Description.
		 *
		 * @since    1.0.0
		 */
		public static function bkap_activate() {

			global $wpdb;

			$table_name = $wpdb->prefix . 'booking_history';

			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`post_id` int(11) NOT NULL,
									`weekday` varchar(50) NOT NULL,
									`start_date` date NOT NULL,
									`end_date` date NOT NULL,
									`from_time` varchar(50) NOT NULL,
									`to_time` varchar(50) NOT NULL,
									`total_booking` int(11) NOT NULL,
									`available_booking` int(11) NOT NULL,
									`status` varchar(20) NOT NULL,
									PRIMARY KEY (`id`)
							) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

			$order_table_name = $wpdb->prefix . 'booking_order_history';
			$order_sql        = "CREATE TABLE IF NOT EXISTS $order_table_name (
									`id` int(11) NOT NULL AUTO_INCREMENT,
									`order_id` int(11) NOT NULL,
									`booking_id` int(11) NOT NULL,
									PRIMARY KEY (`id`)
						)ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';

			dbDelta( $sql );
			dbDelta( $order_sql );
			update_option( 'woocommerce_booking_alter_queries', 'yes' );

			$wc_bkap_current_db_version = get_option( 'woocommerce_booking_db_version' );
			update_option( 'woocommerce_booking_db_version', BKAP_VERSION );

			// Adding new option to handle upgrade process smoothly.
			if ( ! empty( $wc_bkap_current_db_version ) ) {
				update_option( 'wc_bkap_prev_db_version',  $wc_bkap_current_db_version );
			} else {
				update_option( 'wc_bkap_prev_db_version', BKAP_VERSION );
			}

			update_option( 'woocommerce_booking_abp_hrs', 'HOURS' );
			$check_table_query = "SHOW COLUMNS FROM $table_name LIKE 'end_date'";

			$results = $wpdb->get_results( $check_table_query );

			if ( count( $results ) == 0 ) {
				$alter_table_query = "ALTER TABLE $table_name ADD `end_date` date AFTER  `start_date`";
				$wpdb->get_results( $alter_table_query );
			}

			if ( ( get_option( 'book_date-label' ) == false || get_option( 'book_date-label' ) == '' ) ) {
				add_option( 'bkap_add_to_cart', __( 'Book Now!', 'woocommerce-booking' ) );
				add_option( 'bkap_check_availability', __( 'Check Availability', 'woocommerce-booking' ) );
			}

			// Set default labels.
			add_option( 'book_date-label', __( 'Start Date', 'woocommerce-booking' ) );
			add_option( 'checkout_date-label', __( '<br>End Date', 'woocommerce-booking' ) );
			add_option( 'bkap_calendar_icon_file', 'calendar1.gif' );
			add_option( 'book_time-label', __( 'Booking Time', 'woocommerce-booking' ) );
			add_option( 'book_time-select-option', __( 'Choose a Time', 'woocommerce-booking' ) );
			add_option( 'book_fixed-block-label', __( 'Select Period', 'woocommerce-booking' ) );
			add_option( 'book_price-label', __( 'Total:', 'woocommerce-booking' ) );

			add_option( 'book_item-meta-date', __( 'Start Date', 'woocommerce-booking' ) );
			add_option( 'checkout_item-meta-date', __( 'End Date', 'woocommerce-booking' ) );
			add_option( 'book_item-meta-time', __( 'Booking Time', 'woocommerce-booking' ) );
			add_option( 'book_ics-file-name', __( 'Mycal', 'woocommerce-booking' ) );

			add_option( 'book_item-cart-date', __( 'Start Date', 'woocommerce-booking' ) );
			add_option( 'checkout_item-cart-date', __( 'End Date', 'woocommerce-booking' ) );
			add_option( 'book_item-cart-time', __( 'Booking Time', 'woocommerce-booking' ) );

			// add this option to ensure the labels above are retained in the future updates.
			add_option( 'bkap_update_booking_labels_settings', 'yes' );

			// add the new messages in the options table.
			add_option( 'book_stock-total', __( 'AVAILABLE_SPOTS stock total', 'woocommerce-booking' ) );
			add_option( 'book_available-stock-date', __( 'AVAILABLE_SPOTS booking(s) are available on DATE', 'woocommerce-booking' ) );
			add_option( 'book_available-stock-time', __( 'AVAILABLE_SPOTS booking(s) are available for TIME on DATE', 'woocommerce-booking' ) );
			add_option( 'book_available-stock-date-attr', __( 'AVAILABLE_SPOTS ATTRIBUTE_NAME booking(s) are available on DATE', 'woocommerce-booking' ) );
			add_option( 'book_available-stock-time-attr', __( 'AVAILABLE_SPOTS ATTRIBUTE_NAME booking(s) are available for TIME on DATE', 'woocommerce-booking' ) );

			add_option( 'book_limited-booking-msg-date', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS tickets available for the date DATE.', 'woocommerce-booking' ) );
			add_option( 'book_no-booking-msg-date', __( 'For PRODUCT_NAME, the date DATE has been fully booked. Please try another date.', 'woocommerce-booking' ) );
			add_option( 'book_limited-booking-msg-time', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS tickets available for TIME on DATE.', 'woocommerce-booking' ) );
			add_option( 'book_no-booking-msg-time', __( 'For PRODUCT_NAME, the time TIME on DATE has been fully booked. Please try another timeslot.', 'woocommerce-booking' ) );
			add_option( 'book_limited-booking-msg-date-attr', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS ATTRIBUTE_NAME tickets available for the date DATE.', 'woocommerce-booking' ) );
			add_option( 'book_limited-booking-msg-time-attr', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS ATTRIBUTE_NAME tickets available for TIME on DATE.', 'woocommerce-booking' ) );

			add_option( 'book_real-time-error-msg', __( 'That date just got booked. Please reload the page.', 'woocommerce-booking' ) );
			add_option( 'book_multidates_min_max_selection_msg', __( 'Select a minimum of MIN Days and maximum of MAX Days', 'woocommerce-booking' ) );

			// add GCal event summary & description.
			add_option( 'bkap_calendar_event_summary', 'SITE_NAME, ORDER_NUMBER' );
			add_option( 'bkap_calendar_event_description', __( 'PRODUCT_WITH_QTY,Name: CLIENT,Contact: EMAIL, PHONE', 'woocommerce-booking' ) );
			// add GCal event city.
			add_option( 'bkap_calendar_event_location', 'CITY' );

			// Set default global booking settings.
			$booking_settings                                     = new stdClass();
			$booking_settings->booking_language                   = 'en-GB';
			$booking_settings->booking_date_format                = 'mm/dd/y';
			$booking_settings->booking_time_format                = '12';
			$booking_settings->booking_months                     = '1';
			$booking_settings->booking_calendar_day               = '1';
			$booking_settings->global_booking_minimum_number_days = '0';
			$booking_settings->booking_availability_display       = '';
			$booking_settings->minimum_day_booking                = '';
			$booking_settings->booking_global_selection           = '';
			$booking_settings->booking_global_timeslot            = '';
			$booking_settings->woo_product_addon_price            = '';
			$booking_settings->booking_global_holidays            = '';
			$booking_settings->same_bookings_in_cart              = '';
			$booking_settings->resource_price_per_day             = '';
			$booking_settings->booking_themes                     = 'smoothness';
			$booking_settings->hide_variation_price               = 'on';
			$booking_settings->display_disabled_buttons           = 'on';
			$booking_settings->hide_booking_price                 = '';
			$booking_settings->booking_overlapping_timeslot       = 'on';
			$booking_settings->booking_timeslot_display_mode      = 'list-view';
			$booking_settings->bkap_auto_cancel_booking           = '0';

			$booking_settings = apply_filters( 'woocommerce_booking_global_settings', $booking_settings );

			$booking_global_settings = json_encode( $booking_settings );
			add_option( 'woocommerce_booking_global_settings', $booking_global_settings );
		}
	}
}
