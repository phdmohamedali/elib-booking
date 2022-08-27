<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Adding Plugin Meta
 *
 * @author   Tyche Softwares
 * @package  BKAP/Plugin-Update
 * @category Classes
 * @class    Bkap_Plugin_Update
 */

if ( ! class_exists( 'Bkap_Plugin_Update' ) ) {

	/**
	 * Class Bkap_Plugin_Update.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Plugin_Update {

		/**
		 * Bkap_Plugin_Update constructor.
		 */
		public function __construct() {
			add_action( 'admin_notices', array( &$this, 'bkap_display_timeslot_notices' ), 10 );
			add_action( 'admin_init', array( &$this, 'bkap_bookings_update_db_check' ) );
			
			// Add a transients to handle display of notices based on it.
			add_action( 'admin_init', array( &$this, 'bkap_add_transients' ) );
		}

		/**
		 * Add transients to track if the plugin is installed first time or it is
		 * updated.
		 */
		public function bkap_add_transients() {

			$_bkap_timeslot_notice  = get_transient( 'bkap_timeslot_notice' );

			if ( ! get_option( 'wc_bkap_prev_db_version' ) && 
				! $_bkap_timeslot_notice 
			) {

				// Set transient to check if our plugin is updated.
				set_transient( 'bkap_timeslot_notice', 1 );
			}
		}
		
		/**
		 * Display admin notification to users about new time slot list view
		 * only if user is upgraded the plugin.
		 */
		public function bkap_display_timeslot_notices() {

			global $current_screen;

			// Fix #4313. Ensure this notice is never displayed in the plugin activation window.

			if ( 'update' === $current_screen->base ) {
				return;
			}

			$bkap_timeslot_notice = get_transient( 'bkap_timeslot_notice' );

			if ( ( false !== $bkap_timeslot_notice ) && ( '1' === $bkap_timeslot_notice ) ) {

				$redirect_args = array(
					'page'      => 'woocommerce_booking_page',
					'action'    => 'settings',
					'post_type' => 'bkap_booking',
				);

				$url     = add_query_arg( $redirect_args, admin_url( '/edit.php?' ) );
				$message = sprintf( __( 'Introducing List View for Time Slots for Bookable products in the Booking & Appointment plugin for WooCommerce. Configure from <a href="%s">here</a>.', 'woocommerce-booking' ), $url );
				$class   = 'notice notice-info bkap-timeslot-notice is-dismissible';
				$notice  = sprintf( '<div class="%s"><p>%s</p></div>', $class, $message );
				echo $notice;
			}
		}

		/**
		 * This function is executed when the plugin is updated using
		 * the Automatic Updater. It calls the bookings_activate function
		 * which will check the table structures for the plugin and
		 * make any changes if necessary.
		 *
		 * @since 2.4.4
		 *
		 * @globals string $booking_plugin_version Live Booking Plugin version
		 * @globals mixed $wpdb Global wpdb object
		 */
		public function bkap_bookings_update_db_check() {

			global $booking_plugin_version;
			global $wpdb;

			$booking_plugin_version = get_option( 'woocommerce_booking_db_version' );
			$current_plugin_version = get_booking_version();

			if ( $booking_plugin_version != $current_plugin_version ) {

				do_action( 'bkap_bookings_update_db_check', $booking_plugin_version, $current_plugin_version );

				update_option( 'woocommerce_booking_db_version', BKAP_VERSION );
				
				// Add an option to change the "Choose a Time" text in the time slot dropdown.
				add_option( 'book_time-select-option', 'Choose a Time' );
				// Add an option to change ICS file name.
				add_option( 'book_ics-file-name', 'Mycal' );
				// add an option to set the label for fixed block drop down.
				add_option( 'book_fixed-block-label', 'Select Period' );

				// add an option to add a label for the front end price display.
				add_option( 'book_price-label', '' );

				if ( $booking_plugin_version <= '4.19.2' ) {

					$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
					if ( ! isset( $global_settings->booking_overlapping_timeslot ) ) {
						$global_settings->booking_overlapping_timeslot = 'on';
						update_option( 'woocommerce_booking_global_settings', json_encode( $global_settings ) );
					}
				}

				if ( $booking_plugin_version <= '5.2.1' ) {
					as_enqueue_async_action( 'bkap_update_time_gi_to_hi' );
				}

				// add the new messages in the options table.
				add_option( 'book_stock-total', __( 'AVAILABLE_SPOTS stock total', 'woocommerce-booking' ) );
				add_option( 'book_available-stock-date', __( 'AVAILABLE_SPOTS bookings are available on DATE', 'woocommerce-booking' ) );
				add_option( 'book_available-stock-time', __( 'AVAILABLE_SPOTS bookings are available for TIME on DATE', 'woocommerce-booking' ) );
				add_option( 'book_available-stock-date-attr', __( 'AVAILABLE_SPOTS ATTRIBUTE_NAME bookings are available on DATE', 'woocommerce-booking' ) );
				add_option( 'book_available-stock-time-attr', __( 'AVAILABLE_SPOTS ATTRIBUTE_NAME bookings are available for TIME on DATE', 'woocommerce-booking' ) );

				add_option( 'book_limited-booking-msg-date', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS tickets available for the date DATE.', 'woocommerce-booking' ) );
				add_option( 'book_no-booking-msg-date', __( 'For PRODUCT_NAME, the date DATE has been fully booked. Please try another date.', 'woocommerce-booking' ) );
				add_option( 'book_limited-booking-msg-time', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS tickets available for TIME on DATE.', 'woocommerce-booking' ) );
				add_option( 'book_no-booking-msg-time', __( 'For PRODUCT_NAME, the time TIME on DATE has been fully booked. Please try another timeslot.', 'woocommerce-booking' ) );
				add_option( 'book_limited-booking-msg-date-attr', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS ATTRIBUTE_NAME tickets available for the date DATE.', 'woocommerce-booking' ) );
				add_option( 'book_limited-booking-msg-time-attr', __( 'PRODUCT_NAME has only AVAILABLE_SPOTS ATTRIBUTE_NAME tickets available for TIME on DATE.', 'woocommerce-booking' ) );

				add_option( 'book_real-time-error-msg', __( 'That date just got booked. Please reload the page.', 'woocommerce-booking' ) );

				add_option( 'book_multidates_min_max_selection_msg', __( 'Select a minimum of MIN Days and maximum of MAX Days', 'woocommerce-booking' ) );
				//add_option( 'book_multidates_fixed_dates_selection_msg', 'Select a minimum of MIN Days and maximum of MAX Days' );
			}
		}
	}
	$bkap_plugin_update = new Bkap_Plugin_Update();
}
