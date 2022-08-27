<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling 2 way Google Calendar Sync
 *
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @category Classes
 */

/**
 * Adds the Booking & Appointment Import Cron Job
 *
 * @param array $schedules - List of cron jobs to be run with their intervals.
 * @since 2.6.3
 */
function woocommerce_bkap_add_cron_schedule( $schedules ) {

	$duration            = get_option( 'bkap_cron_time_duration', 0 );
	$duration_in_seconds = ( $duration > 0 ) ? $duration * 60 : 86400;

	$schedules['bkap_gcal_import'] = array(
		'interval' => $duration_in_seconds, // import duration in seconds.
		'display'  => __( 'Booking & Appointment - GCal Import Events' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'woocommerce_bkap_add_cron_schedule' ); // Add a new interval for import cron schedule.

/**
 * Schedule an action if it's not already scheduled for GCal Import
 *
 * @since 2.6.3
 */
if ( ! wp_next_scheduled( 'woocommerce_bkap_import_events' ) ) {
	wp_schedule_event( time(), 'bkap_gcal_import', 'woocommerce_bkap_import_events' );
}

/**
 * Setup the Import of events from Google Calendar
 *
 * @since 2.6.3
 */
function bkap_import_events_cron() {
	$calendar_sync = new bkap_calendar_sync();
	$calendar_sync->bkap_setup_import();
}
add_action( 'woocommerce_bkap_import_events', 'bkap_import_events_cron' );

/**
 * Class for Google Calendar 2 way sync for bookings
 *
 * @class bkap_calendar_sync
 */
class bkap_calendar_sync {

	/**
	 * Default constructor
	 *
	 * @since 2.6.3
	 */
	public function __construct() {

		$this->gcal_api = false;
		$this->email_ID = '';

		add_action( 'wp_loaded', array( $this, 'bkap_setup_gcal_sync' ), 10 );
		add_action( 'admin_init', array( $this, 'bkap_setup_gcal_sync' ), 10 );

		$this->plugin_dir = plugin_dir_path( __FILE__ );
		$this->plugin_url = plugins_url( basename( dirname( __FILE__ ) ) );

		add_action( 'after_bkap_create_booking_post', array( &$this, 'bkap_google_calendar_update_order_meta' ), 10, 6 );
		add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_add_to_woo_pages' ), 11, 3 );

		// These 2 hooks have been added to figure out which email is being sent, the new order email (which goes to the admin)
		// or the customer processing order, on hold order or ccompleted order ( which goes to the customer).
		add_filter( 'woocommerce_email_subject_new_order', array( &$this, 'bkap_new_order_email' ), 10, 1 );
		add_filter( 'woocommerce_email_subject_customer_processing_order', array( &$this, 'bkap_customer_email' ), 10, 1 );
		add_filter( 'woocommerce_email_subject_customer_on_hold_order', array( &$this, 'bkap_customer_email' ), 10, 1 );
		add_filter( 'woocommerce_email_subject_customer_completed_order', array( &$this, 'bkap_customer_email' ), 10, 1 );

		if ( get_option( 'bkap_add_to_calendar_customer_email' ) == 'on' ) {
			add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_add_to_calendar_customer' ), 12, 3 );
		}

		if ( get_option( 'bkap_admin_add_to_calendar_email_notification' ) == 'on' && get_option( 'bkap_calendar_sync_integration_mode' ) == 'manually' ) {
			add_action( 'woocommerce_order_item_meta_end', array( &$this, 'bkap_add_to_calendar_admin' ), 13, 3 );
		}

		add_action( 'wp_ajax_bkap_save_ics_url_feed', array( &$this, 'bkap_save_ics_url_feed' ) );
		add_action( 'wp_ajax_bkap_delete_ics_url_feed', array( &$this, 'bkap_delete_ics_url_feed' ) );
		add_action( 'wp_ajax_bkap_import_events', array( &$this, 'bkap_setup_import' ) );
		add_action( 'wp_ajax_bkap_admin_booking_calendar_events', array( &$this, 'bkap_admin_booking_calendar_events' ) );

		require_once $this->plugin_dir . '/iCal/SG_iCal.php';

		add_filter( 'pre_update_option_bkap_cron_time_duration', array( &$this, 'pre_update_option_bkap_cron_time_duration_call' ), 10, 2 );

		add_action( 'admin_init', array( $this, 'bkap_gcal_oauth_redirect' ), 11 );
		add_action( 'init', array( $this, 'bkap_gcal_oauth_redirect' ), 11 );
		add_action( 'admin_notices', array( $this, 'bkap_gcal_success_fail_notice' ), 11 );
	}

	/**
	 * This function performs different actions based on the GET parameters.
	 *
	 * @since 5.1.0
	 */
	public function bkap_gcal_oauth_redirect() {

		if ( isset( $_GET['code'] ) && '' !== $_GET['code'] ) { // phpcs:ignore
			if ( isset( $_GET['bkap-google-oauth'] ) ) { // phpcs:ignore
				$user_id         = get_current_user_id();
				$product_id      = ( 1 != $_GET['bkap-google-oauth'] ) ? $_GET['bkap-google-oauth'] : 0; // phpcs:ignore
				$bkap_oauth_gcal = new BKAP_OAuth_Google_Calendar( $product_id, $user_id );
				$bkap_oauth_gcal->bkap_oauth_redirect();
			}
		}

		if ( isset( $_GET['bkap_logout'] ) ) { // phpcs:ignore
			if ( '' != $_GET['bkap_logout'] ) { // phpcs:ignore
				$user_id         = get_current_user_id();
				$product_id      = ( 0 != $_GET['bkap_logout'] ) ? $_GET['bkap_logout'] : 0; // phpcs:ignore
				$bkap_oauth_gcal = new BKAP_OAuth_Google_Calendar( $product_id, $user_id );
				$bkap_oauth_gcal->oauth_logout();
			}
		}
	}

	/**
	 * Try to create an encrypted key file folder
	 *
	 * @since 5.1.0
	 */
	public function bkap_gcal_success_fail_notice() {
		if ( isset( $_GET['bkap_con_status'] ) ) { // phpcs:ignore
			$status = $_GET['bkap_con_status']; // phpcs:ignore
			switch ( $status ) {
				case 'success':
					$message = __( 'Successfully connected.', 'woocommerce-booking' );
					$class   = 'notice notice-success';
					break;
				case 'fail':
					$uploads     = wp_upload_dir(); // Set log file location.
					$uploads_dir = isset( $uploads['basedir'] ) ? $uploads['basedir'] . '/' : WP_CONTENT_DIR . '/uploads/';
					$log_file    = $uploads_dir . 'bkap-log.txt';
					/* translators: %s: Bkap Log file url. */
					$message = sprintf( __( 'Failed to connect to your account, please try again, if the problem persists, please check the log in the %s file and see what is happening or please contact Support team.', 'woocommerce-booking' ), $log_file );
					$class   = 'notice notice-error';
					break;
			}

			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}

		if ( isset( $_GET['bkap_logout'] ) ) { // phpcs:ignore
			$message = __( 'Google Calendar Account disconnected successfully!', 'woocommerce-booking' );
			$class   = 'notice notice-success';
			printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}
	}

	/**
	 * Check the value of cron and if changed then clear the cron schedule and initiate with new timing.
	 *
	 * @param string $new_value New option value.
	 * @param string $old_value Old option value.
	 *
	 * @hook pre_update_option_bkap_cron_time_duration
	 * @since 4.11.0
	 */
	public function pre_update_option_bkap_cron_time_duration_call( $new_value, $old_value ) {

		if ( $new_value != $old_value ) {
			wp_clear_scheduled_hook( 'woocommerce_bkap_import_events' );
		}

		return $new_value;
	}

	/**
	 * Set the global parameter to email subject.
	 * Used to identify whether the WooCommerce email is being fired for the admin or the customer.
	 *
	 * @param string $subject Subject.
	 * @hook woocommerce_email_subject_new_order
	 * @since 2.6
	 */
	public function bkap_new_order_email( $subject ) {
		$this->email_ID = 'new_order';
		return $subject;
	}

	/**
	 * Set the global parameter to email subject
	 * Used to identify whether the WooCommerce email is being fired for the admin or the customer.
	 *
	 * @param string $subject Subject.
	 * @hook woocommerce_email_subject_customer_processing_order
	 *       woocommerce_email_subject_customer_on_hold_order
	 *       woocommerce_email_subject_customer_completed_order
	 *
	 * @since 2.6
	 */
	function bkap_customer_email( $subject ) {
		$this->email_ID = 'customer_order';
		return $subject;
	}

	/**
	 * Adds the GCal class file
	 *
	 * @hook admin_init - for admin side
	 *       wp_loaded - for front end
	 * @since 2.6
	 */
	public function bkap_setup_gcal_sync() {
		// GCal Integration.
		$this->gcal_api = false;
		// Allow forced disabling in case of emergency.
		require_once $this->plugin_dir . '/class.gcal.php';
		$this->gcal_api = new BKAP_Gcal();
	}

	/**
	 * This function adds booking to the Google Calendar if automated sync is enabled.
	 *
	 * @param integer $order_id - Order ID for which bookings need to be synced.
	 * @hook woocommerce_checkout_update_order_meta
	 * @since 2.6
	 */
	public function bkap_google_calendar_update_order_meta( $item_id, $post_id, $quantity, $booking_data, $variation_id, $order_id ) {
		global $wpdb;

		$gcal          = new BKAP_Gcal();
		$admin_id      = bkap_get_user_id();
		$tour_gcal_api = get_option( 'bkap_allow_tour_operator_gcal_api' );
		$gcal_mode     = $gcal->get_api_mode( $admin_id, $post_id );

		if ( in_array( $gcal_mode, array( 'directly', 'oauth' ), true ) ) {

			$booking_settings = bkap_setting( $post_id );
			$user_id          = $admin_id;

			// check if tour operators are allowed to setup GCal.
			if ( 'yes' === $tour_gcal_api ) {
				// if tour operator addon is active, pass the tour operator user Id else the admin ID.
				if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {
					if ( $is_bookable ) {
						if ( isset( $booking_settings['booking_tour_operator'] ) && $booking_settings['booking_tour_operator'] != 0 ) {
							$user_id = $booking_settings['booking_tour_operator'];
						}
					}
				}
			}

			// check if it's for the admin, else the tour operator addon will do the needful.
			if ( $user_id == $admin_id ) {

				$_data      = wc_get_product( $post_id );
				$item       = new WC_Order_Item_Product( $item_id );
				$post_title = $item->get_name();

				if ( 0 < $variation_id ) {
					$variation_obj      = new WC_Product_Variation( $variation_id );
					$variation_attr_cnt = count( $variation_obj->get_variation_attributes() );
					if ( 2 < $variation_attr_cnt ) {
						$product_variations = implode( ", ", $variation_obj->get_variation_attributes() );
						$post_title         = $post_title . ' - ' . $product_variations;
					}
				}

				// check the booking status, if pending confirmation, then do not insert event in the calendar.
				$booking_status = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );

				if ( ( isset( $booking_status ) && 'pending-confirmation' != $booking_status ) || ( ! isset( $booking_status ) ) ) {

					// ensure it's a future dated event
					$is_date_set = false;
					if ( isset( $booking_data['hidden_date'] ) ) {
						$day = date( 'Y-m-d', current_time( 'timestamp' ) );
						if ( strtotime( $booking_data['hidden_date'] ) >= strtotime( $day ) ) {
							$is_date_set = true;
						}
					}

					if ( $is_date_set ) {
						$global_settings                      = bkap_global_setting();
						$event_details                        = array();
						$event_details['hidden_booking_date'] = $booking_data['hidden_date'];

						if ( isset( $booking_data['hidden_date_checkout'] ) && '' !== $booking_data['hidden_date_checkout'] ) {
							$event_details['hidden_checkout_date'] = $booking_data['hidden_date_checkout'];
						}

						if ( is_plugin_active( 'bkap-rental/rental.php' ) ) {
							if ( isset( $booking_settings['booking_prior_days_to_book'] ) && '' !== $booking_settings['booking_prior_days_to_book'] && '0' !== $booking_settings['booking_prior_days_to_book'] ) {
								$prior_day                            = $booking_settings['booking_prior_days_to_book'];
								$event_details['hidden_booking_date'] = date( 'Y-m-d', strtotime( "-$prior_day day", strtotime( $booking_data['hidden_date'] ) ) );
							}

							if ( isset( $booking_settings['booking_charge_per_day'] ) && 'on' === $booking_settings['booking_charge_per_day'] ) {
								$event_details['hidden_checkout_date'] = date( 'Y-m-d', strtotime( '+1 day', strtotime( $booking_data['hidden_date_checkout'] ) ) );
							} elseif ( isset( $booking_settings['booking_later_days_to_book'] ) && '' !== $booking_settings['booking_later_days_to_book'] && '0' !== $booking_settings['booking_later_days_to_book'] ) {
								$later_day                             = $booking_settings['booking_later_days_to_book'];
								$event_details['hidden_checkout_date'] = date( 'Y-m-d', strtotime( "+$later_day day", strtotime( $booking_data['hidden_date_checkout'] ) ) );
							}
						}

						if ( isset( $booking_data['selected_duration'] ) && '' !== $booking_data['selected_duration'] ) {

							$start_date = $booking_data['hidden_date'];
							$time       = $booking_data['duration_time_slot'];

							$selected_duration = explode( '-', $booking_data['selected_duration'] );

							$hour   = $selected_duration[0];
							$d_type = $selected_duration[1];

							$end_str  = bkap_common::bkap_add_hour_to_date( $start_date, $time, $hour, $post_id, $d_type ); // return end date timestamp
							$end_date = date( 'j-n-Y', $end_str ); // Date in j-n-Y format to compate and store in end date order meta

							// updating end date
							if ( $start_date != $end_date ) {
								$event_details['hidden_checkout_date'] = $end_date;
							}

							$endtime        = date( 'H:i', $end_str );// getend time in H:i format
							$back_time_slot = $time . ' - ' . $endtime; // to store time sting in the _wapbk_time_slot key of order item meta

							$event_details['duration_time_slot'] = $back_time_slot;

						}

						if ( isset( $booking_data['resource_id'] ) && '' !== $booking_data['resource_id'] ) {
							$event_details['resource'] = get_the_title( $booking_data['resource_id'] );
						}

						if ( isset( $booking_data['persons'] ) && '' !== $booking_data['persons'] ) {
							if ( isset( $booking_data['persons'][0] ) ) {
								$person_info = Class_Bkap_Product_Person::bkap_get_person_label( $post_id ) . ' : ' . $booking_data['persons'][0];
							} else {
								$person_info = '';
								foreach ( $booking_data['persons'] as $p_key => $p_value ) {
									$person_info .= get_the_title( $p_key ) . ' : ' . $p_value . ',';
								}
							}
							$event_details['persons'] = $person_info;
						}

						$event_details['billing_email']      = sanitize_text_field( $_POST['billing_email'] );
						$event_details['billing_first_name'] = sanitize_text_field( $_POST['billing_first_name'] );
						$event_details['billing_last_name']  = sanitize_text_field( $_POST['billing_last_name'] );
						$event_details['billing_address_1']  = isset( $_POST['billing_address_1'] ) ? sanitize_text_field( $_POST['billing_address_1'] ) : '';
						$event_details['billing_address_2']  = isset( $_POST['billing_address_2'] ) ? sanitize_text_field( $_POST['billing_address_2'] ) : '';
						$event_details['billing_city']       = isset( $_POST['billing_city'] ) ? sanitize_text_field( $_POST['billing_city'] ) : '';
						$event_details['billing_postcode']   = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( $_POST['billing_postcode'] ) : '';
						$event_details['billing_phone']      = isset( $_POST['billing_phone'] ) ? sanitize_text_field( $_POST['billing_phone'] ) : '';
						$event_details['order_comments']     = isset( $_POST['order_comments'] ) ? sanitize_text_field( $_POST['order_comments'] ) : '';
						$event_details['order_id']           = $order_id;

						if ( isset( $_POST['shipping_first_name'] ) && $_POST['shipping_first_name'] != '' ) {
							$event_details['shipping_first_name'] = sanitize_text_field( $_POST['shipping_first_name'] );
						}
						if ( isset( $_POST['shipping_last_name'] ) && $_POST['shipping_last_name'] != '' ) {
							$event_details['shipping_last_name'] = sanitize_text_field( $_POST['shipping_last_name'] );
						}
						if ( isset( $_POST['shipping_address_1'] ) && $_POST['shipping_address_1'] != '' ) {
							$event_details['shipping_address_1'] = sanitize_text_field( $_POST['shipping_address_1'] );
						}
						if ( isset( $_POST['shipping_address_2'] ) && $_POST['shipping_address_2'] != '' ) {
							$event_details['shipping_address_2'] = sanitize_text_field( $_POST['shipping_address_2'] );
						}
						if ( isset( $_POST['shipping_city'] ) && $_POST['shipping_city'] != '' ) {
							$event_details['shipping_city'] = sanitize_text_field( $_POST['shipping_city'] );
						}
						if ( isset( $_POST['shipping_postcode'] ) && $_POST['shipping_postcode'] != '' ) {
							$event_details['shipping_postcode'] = sanitize_text_field( $_POST['shipping_postcode'] );
						}

						$event_details['product_name']  = $post_title;
						$event_details['product_qty']   = $quantity;
						$event_details['product_total'] = $quantity * $booking_data['price'];

						$zoom_label                    = bkap_zoom_join_meeting_label( $post_id );
						$zoom_meeting                  = wc_get_order_item_meta( $item_id, $zoom_label );
						$event_details['zoom_meeting'] = '';
						if ( '' != $zoom_meeting ) {
							$event_details['zoom_meeting'] = $zoom_label . ' - ' . $zoom_meeting;
						}

						// if sync is disabled at the product level, set post_id to 0 to ensure admin settings are taken into consideration.
						if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' == $booking_settings['product_sync_integration_mode'] ) ) {
							$post_id = 0;
						} elseif ( isset( $booking_settings['product_sync_integration_mode'] )
						&& in_array( $booking_settings['product_sync_integration_mode'], array( 'directly', 'oauth' ), true ) ) {
							$mode = $booking_settings['product_sync_integration_mode'];
							switch ( $mode ) {
								case 'directly':
									if ( '' == $booking_settings['product_sync_key_file_name'] || '' == $booking_settings['product_sync_service_acc_email_addr'] || '' == $booking_settings['product_sync_calendar_id'] ) {
										$post_id = 0;
									}
									break;
								case 'oauth':
									if ( ! isset( $booking_settings['bkap_calendar_oauth_integration'] ) ) {
										$post_id = 0;
									} else {
										$oauth_setting = $booking_settings['bkap_calendar_oauth_integration'];
										if ( '' === $oauth_setting['client_id'] || '' === $oauth_setting['client_secret'] ) {
											$post_id = 0;
										}
									}
									break;
							}
						}

						$time_exploded = array();

						// Checking for multiple timeslots.
						if ( isset( $booking_data['time_slot'] ) && $booking_data['time_slot'] != '' ) {

							if ( strpos( $booking_data['time_slot'], '<br>' ) !== false ) {
								$time_exploded = explode( '<br>', $booking_data['time_slot'] );
								array_shift( $time_exploded );
							} else {
								$time_exploded[] = $booking_data['time_slot'];
							}
						}

						if ( count( $time_exploded ) > 0 ) {
							$timezone_check = bkap_timezone_check( $global_settings );
							$offset         = ( $timezone_check ) ? bkap_get_offset( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_offset' ) ) : '';

							foreach ( $time_exploded as $key_time => $value_time ) {

								if ( $timezone_check ) {

									$site_timezone     = bkap_booking_get_timezone_string();
									$customer_timezone = $booking_data['timezone_name'];

									$from_time       = $to_time = '';
									$value_time_slot = explode( '-', $value_time );
									$from_time       = bkap_convert_date_from_timezone_to_timezone( $booking_data['hidden_date'] . ' ' . $value_time_slot[0], $customer_timezone, $site_timezone, 'H:i' );
									$value_time      = $from_time;
									if ( isset( $value_time_slot[1] ) ) {
										$to_time    = bkap_convert_date_from_timezone_to_timezone( $booking_data['hidden_date'] . ' ' . $value_time_slot[1], $customer_timezone, $site_timezone, 'H:i' );
										$value_time = $from_time . ' - ' . $to_time;
									}

									$event_details['time_slot'] = $value_time;
								} else {
									$event_details['time_slot'] = $value_time;
								}

								$gcal->insert_event( $event_details, $item_id, $user_id, $post_id, false );
							}
						} else {
							$gcal->insert_event( $event_details, $item_id, $user_id, $post_id, false );
						}

						// add an order note, mentioning an event has been created for the item.
						$order = new WC_Order( $order_id );

						$order_note = __( "Booking_details for $post_title have been exported to the Google Calendar", 'woocommerce-booking' );
						$order->add_order_note( $order_note );
					}
				}
			}
		}
	}

	/**
	 * Adds buttons in the WooCommerce customer emails
	 * using which customers can add bookings into their calendars.
	 *
	 * @param integer               $item_id - Item ID of the product
	 * @param WC_Order_Item_Product $item - Item Object
	 * @param WC_Order              $order - Order Object
	 *
	 * @hook woocommerce_order_item_meta_end
	 * @since 2.6
	 */

	function bkap_add_to_calendar_customer( $item_id, $item, $order ) {
		if ( ! is_account_page() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// check the email ID
			if ( 'customer_order' == $this->email_ID ) {

				// check if it's a bookable product
				$bookable   = bkap_common::bkap_get_bookable_status( $item['product_id'] );
				$valid_date = false;
				if ( isset( $item['wapbk_booking_date'] ) ) {
					$valid_date = bkap_common::bkap_check_date_set( $item['wapbk_booking_date'] );
				}
				if ( $bookable && $valid_date ) {
					$bkap = $this->bkap_create_gcal_obj( $item_id, $item, $order );
					$this->bkap_add_buttons_emails( $bkap, 'customer' );
				}
			}
		}
	}

	/**
	 * Adds buttons in the WooCommerce admin emails
	 * using which admin can add bookings into the calendar.
	 * Executed only when manual booking sync is enabled with
	 * appropriate settings.
	 *
	 * @param integer               $item_id - Item ID of the product
	 * @param WC_Order_Item_Product $item - Item Object
	 * @param WC_Order              $order - Order Object
	 *
	 * @hook woocommerce_order_item_meta_end
	 * @since 2.6
	 */

	function bkap_add_to_calendar_admin( $item_id, $item, $order ) {
		if ( ! is_account_page() && ! is_wc_endpoint_url( 'order-received' ) ) {

			if ( 'new_order' == $this->email_ID ) {
				// check if it's a bookable product
				$post_id = bkap_common::bkap_get_product_id( $item['product_id'] );

				$bookable = bkap_common::bkap_get_bookable_status( $post_id );

				$valid_date = false;
				if ( isset( $item['wapbk_booking_date'] ) ) {
					$valid_date = bkap_common::bkap_check_date_set( $item['wapbk_booking_date'] );
				}
				if ( $bookable && $valid_date ) {

					// check if tour operators are allowed to setup GCal.
					if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
						// if tour operator addon is active, return if an operator is assigned
						if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {

							$booking_settings = get_post_meta( $post_id, 'woocommerce_booking_settings', true );

							if ( isset( $booking_settings['booking_tour_operator'] ) && $booking_settings['booking_tour_operator'] != 0 ) {
								return;
							}
						}
					}
					$bkap = $this->bkap_create_gcal_obj( $item_id, $item, $order );
					$this->bkap_add_buttons_emails( $bkap, 'admin' );
				}
			}
		}
	}

	/**
	 * Adds buttons to WooCommerce pages such as My Account page and Thank you page
	 * based on settings which allow the ability to add bookings to the calendar.
	 *
	 * @param integer               $item_id - Item ID of the product
	 * @param WC_Order_Item_Product $item - Item Object
	 * @param WC_Order              $order - Order Object
	 *
	 * @hook woocommerce_order_item_meta_end
	 * @since 2.6
	 */

	function bkap_add_to_woo_pages( $item_id, $item, $order ) {

		if ( is_account_page() && 'on' == get_option( 'bkap_add_to_calendar_my_account_page' ) ) {

			// check if it's a bookable product
			$bookable = bkap_common::bkap_get_bookable_status( $item['product_id'] );

			$valid_date = false;
			if ( isset( $item['wapbk_booking_date'] ) ) {
				$valid_date = bkap_common::bkap_check_date_set( $item['wapbk_booking_date'] );
			}
			if ( $bookable && $valid_date ) {
				wp_enqueue_style( 'gcal_sync_style', bkap_load_scripts_class::bkap_asset_url( '/assets/css/calendar-sync.css', BKAP_FILE ), '', '', false );
				$bkap = $this->bkap_create_gcal_obj( $item_id, $item, $order );
				$this->bkap_add_buttons( $bkap );
			}
		}
		if ( is_wc_endpoint_url( 'order-received' ) && 'on' == get_option( 'bkap_add_to_calendar_order_received_page' ) ) {

			// check if it's a bookable product
			$bookable = bkap_common::bkap_get_bookable_status( $item['product_id'] );

			$valid_date = false;
			if ( isset( $item['wapbk_booking_date'] ) ) {
				$valid_date = bkap_common::bkap_check_date_set( $item['wapbk_booking_date'] );
			}
			if ( $bookable && $valid_date ) {
				wp_enqueue_style( 'gcal_sync_style', bkap_load_scripts_class::bkap_asset_url( '/assets/css/calendar-sync.css', BKAP_FILE ), '', '', false );
				$bkap = $this->bkap_create_gcal_obj( $item_id, $item, $order );
				$this->bkap_add_buttons( $bkap );
			}
		}

	}

	/**
	 * Returns an object which can be used to create 'Add to Calendar' buttons for Google Calendar Sync.
	 *
	 * @param integer               $item_id - Item ID of the product.
	 * @param WC_Order_Item_Product $item - Item Object.
	 * @param WC_Order              $order_details - Order Object.
	 * @return object $bkap - Contains booking details.
	 * @since 2.6
	 */
	public static function bkap_create_gcal_obj( $item_id, $item, $order_details ) {

		$order_id   = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order_details->id : $order_details->get_id();
		$order_data = get_post_meta( $order_id );
		$order      = new WC_Order( $order_id );

		$bkap = new stdClass();

		$bkap->item_id = $item_id;
		$valid_date    = bkap_common::bkap_check_date_set( $item['wapbk_booking_date'] );

		if ( $valid_date ) {
			$bkap->start          = $item['wapbk_booking_date'];
			$shipping_address_1   = isset( $order_data['_shipping_address_1'][0] ) ? $order_data['_shipping_address_1'][0] : '';
			$shipping_address_2   = isset( $order_data['_shipping_address_2'][0] ) ? $order_data['_shipping_address_2'][0] : '';
			$bkap->client_address = __( $shipping_address_1 . ' ' . $shipping_address_2, 'woocommerce-booking' );
			$bkap->client_city    = isset( $order_data['_shipping_city'][0] ) ? __( $order_data['_shipping_city'][0], 'woocommerce-booking' ) : '';

			if ( isset( $item['wapbk_checkout_date'] ) && $item['wapbk_checkout_date'] != '' ) {
				$bkap->end = $item['wapbk_checkout_date'];
			} else {
				$bkap->end = $item['wapbk_booking_date'];
			}

			if ( isset( $item['wapbk_time_slot'] ) && $item['wapbk_time_slot'] != '' ) {
				$timeslot  = explode( ' - ', $item['wapbk_time_slot'] );
				$from_time = date( 'H:i', strtotime( $timeslot[0] ) );

				if ( isset( $timeslot[1] ) && $timeslot[1] != '' ) {
					$to_time        = date( 'H:i', strtotime( $timeslot[1] ) );
					$bkap->end_time = $to_time;
					$time_end       = explode( ':', $to_time );
				} else {
					$bkap->end_time = $from_time;
					$time_end       = explode( ':', $from_time );
				}

				$bkap->start_time = $from_time;
			} else {
				$bkap->start_time = '';
				$bkap->end_time   = '';
			}

			$bkap->resource = '';
			if ( isset( $item['resource_id'] ) && '' !== $item['resource_id'] ) {
				$bkap->resource = get_the_title( $item['resource_id'] );
			}

			$bkap->persons = '';
			if ( isset( $item['person_ids'] ) && '' !== $item['person_ids'] ) {
				$person_info = '';				
				if ( isset( $item['person_ids'][0] ) ) {
					$person_info = Class_Bkap_Product_Person::bkap_get_person_label( $item['product_id'] ) . ' : ' . $item['person_ids'][0];
				} else {
					$person_info = '';
					foreach ( $item['person_ids'] as $p_key => $p_value ) {
						$person_info .= get_the_title( $p_key ) . ' : ' . $p_value . ',';
					}
				}

				$bkap->persons = $person_info;
			}

			$zoom_label         = bkap_zoom_join_meeting_label( $item['product_id'] );
			$zoom_meeting       = wc_get_order_item_meta( $item_id, $zoom_label );
			$bkap->zoom_meeting = '';
			if ( '' != $zoom_meeting ) {
				$bkap->zoom_meeting = $zoom_label . ' - ' . $zoom_meeting;
			}

			$bkap->client_email   = isset( $order_data['_billing_email'][0] ) ? $order_data['_billing_email'][0] : '';
			$billing_first_name   = isset( $order_data['_billing_first_name'][0] ) ? $order_data['_billing_first_name'][0] : '';
			$billing_last_name    = isset( $order_data['_billing_last_name'][0] ) ? $order_data['_billing_last_name'][0] : '';
			$bkap->client_name    = $billing_first_name . ' ' . $billing_last_name;
			$billing_address_1    = isset( $order_data['_billing_address_1'][0] ) ? $order_data['_billing_address_1'][0] : '';
			$billing_address_2    = isset( $order_data['_billing_address_2'][0] ) ? $order_data['_billing_address_2'][0] : '';
			$bkap->client_address = $billing_address_1 . ' ' . $billing_address_2;
			$bkap->client_phone   = isset( $order_data['_billing_phone'][0] ) ? $order_data['_billing_phone'][0] : '';
			$bkap->order_note     = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->customer_note : $order->get_customer_note();

			$product                = '';
			$product_with_qty       = '';
			$product                = $item['name'];
			$product_with_qty       = $item['name'] . '(QTY: ' . $item['qty'] . ') ';
			$bkap->order_total      = $item['line_total'];
			$bkap->product          = $product;
			$bkap->product_with_qty = $product_with_qty;

			if ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) {
				$bkap->order_date_time = $order->post->post_date;
				$order_date            = date( 'Y-m-d', strtotime( $order->post->post_date ) );
			} else {
				$order_post            = get_post( $order_id );
				$post_date             = strtotime( $order_post->post_date );
				$bkap->order_date_time = date( 'Y-m-d H:i:s', $post_date );
				$order_date            = date( 'Y-m-d', $post_date );
			}

			$bkap->order_date = $order_date;
			$bkap->id         = $order_id;

			return $bkap;
		}

	}

	/**
	 * Creates 'Add to Google Calendar' & 'Add to Other Calendar' buttons in WooCommerce emails.
	 *
	 * @param object $bkap - Contains Booking Details
	 * @since 2.6
	 */
	function bkap_add_buttons_emails( $bkap, $user_type ) {

		$gcal                = new BKAP_Gcal();
		$href                = $gcal->gcal( $bkap, $user_type );
		$other_calendar_href = $gcal->other_cal( $bkap, $user_type );

		$target = '_blank';

		if ( 'on' === get_option( 'bkap_calendar_in_same_window' ) ) {
			$target = '_self';
		}

		?>
		<p>
			<a style="padding: 4px;border: 1px solid #524c4c;text-decoration: none;background-color: #eee;border-radius: 2px;color: #000;" href="<?php echo $href; ?>" target= "<?php echo $target; ?>" id="add_to_google_calendar" ><?php _e( 'Add to Google Calendar', 'woocommerce-booking' ); ?></a>
		</p>
		<p>
			<a style="padding: 4px;border: 1px solid #524c4c;text-decoration: none;background-color: #eee;border-radius: 2px;color: #000;" href="<?php echo $other_calendar_href; ?>" target="<?php echo $target; ?>" id="add_to_other_calendar" ><?php _e( 'Add to other Calendar', 'woocommerce-booking' ); ?></a>
		</p>
		<?php
	}

	/**
	 * Creates 'Add to Google Calendar' & 'Add to Other Calendar' buttons on WooCommerce pages.
	 *
	 * @param object $bkap - Contains Booking Details
	 * @since 2.6
	 */
	function bkap_add_buttons( $bkap ) {

		$gcal                = new BKAP_Gcal();
		$href                = $gcal->gcal( $bkap, 'customer' );
		$other_calendar_href = $gcal->other_cal( $bkap, 'customer' );

		$target = '_blank';

		if ( get_option( 'bkap_calendar_in_same_window' ) == 'on' ) {
			$target = '_self';
		} else {
			$target = '_blank';
		}

		?>
		<div class="add_to_calendar">
			<button onclick="myFunction( <?php echo $bkap->item_id; ?> )" class="dropbtn"><?php _e( 'Add To Calendar', 'woocommerce-booking' ); ?><i class="claret"></i></button>
			<div id="add_to_calendar_menu_<?php echo $bkap->item_id; ?>" class="add_to_calendar-content">
				<a href="<?php echo $href; ?>" target= "<?php echo $target; ?>" id="add_to_google_calendar" ><img class="icon" src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/google-icon.jpg"><?php _e( 'Add to Google Calendar', 'woocommerce-booking' ); ?></a>
				<a href="<?php echo $other_calendar_href; ?>" target="<?php echo $target; ?>" id="add_to_other_calendar" ><img class="icon" src="<?php echo plugins_url(); ?>/woocommerce-booking/assets/images/calendar-icon.jpg"><?php _e( 'Add to other Calendar', 'woocommerce-booking' ); ?></a>
			</div>
		</div>

		<script type="text/javascript">
		/* When the user clicks on the button, 
		toggle between hiding and showing the dropdown content */

		function myFunction( chk ) {
			document.getElementById( "add_to_calendar_menu_"+ chk ).classList.toggle( "show" );
		}
		// Close the dropdown if the user clicks outside of it
		window.onclick = function(event) {
			if ( !event.target.matches( '.dropbtn' ) ) {
				var dropdowns = document.getElementsByClassName( "dropdown-add_to_calendar-content" );
				var i;
				for ( i = 0; i < dropdowns.length; i++ ) {
					var openDropdown = dropdowns[i];
					if ( openDropdown.classList.contains( 'show' ) ) {
						openDropdown.classList.remove( 'show' );
					}
				}
			}
		}
		</script>
		<?php
	}

	/**
	 * Save the Google Import URL Feeds from
	 * Booking->Settings->Google Calendar Sync Settings->Import Events
	 * Called via AJAX
	 *
	 * @since 2.6
	 */
	function bkap_save_ics_url_feed() {
		$ics_table_content = '';
		if ( isset( $_POST['ics_url'] ) ) {
			$ics_url = $_POST['ics_url'];
		} else {
			$ics_url = '';
		}

		if ( $ics_url != '' ) {
			$ics_feed_urls = get_option( 'bkap_ics_feed_urls' );
			if ( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
				$ics_feed_urls = array();
			}

			if ( ! in_array( $ics_url, $ics_feed_urls ) ) {
				array_push( $ics_feed_urls, $ics_url );
				update_option( 'bkap_ics_feed_urls', $ics_feed_urls );
				$ics_table_content = 'yes';
			}
		}

		echo $ics_table_content;
		die();
	}

	/**
	 * Delete the Google Import URL Feeds from
	 * Booking->Settings->Google Calendar Sync Settings->Import Events
	 * Called via AJAX
	 *
	 * @since 2.6
	 */

	function bkap_delete_ics_url_feed() {
		$ics_table_content = '';
		if ( isset( $_POST['ics_feed_key'] ) ) {
			$ics_url_key = $_POST['ics_feed_key'];
		} else {
			$ics_url_key = '';
		}

		$product_id = 0;
		if ( isset( $_POST['product_id'] ) ) {
			$product_id = $_POST['product_id'];
		}

		if ( $ics_url_key != '' ) {
			if ( isset( $product_id ) && $product_id > 0 ) {

				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( is_array( $booking_settings['ics_feed_url'] ) && count( $booking_settings['ics_feed_url'] ) > 0 ) {
					$ics_feed_urls = $booking_settings['ics_feed_url'];
					if ( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
						$ics_feed_urls = array();
					}

					unset( $ics_feed_urls[ $ics_url_key ] );
					$booking_settings['ics_feed_url'] = $ics_feed_urls;
					update_post_meta( $product_id, 'woocommerce_booking_settings', $booking_settings );

					// update the individual settings
					update_post_meta( $product_id, '_bkap_import_url', $ics_feed_urls );
					$ics_table_content = 'yes';
				}
			} else {

				$ics_feed_urls = get_option( 'bkap_ics_feed_urls' );
				if ( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
					$ics_feed_urls = array();
				}

				unset( $ics_feed_urls[ $ics_url_key ] );
				update_option( 'bkap_ics_feed_urls', $ics_feed_urls );
				$ics_table_content = 'yes';
			}
		}

		echo $ics_table_content;
		die();
	}

	/**
	 * Gets the ICAL Data from Google Calendar when Import is
	 * performed (manual and automated) for all the Google
	 * Calendars setup in the plugin.
	 *
	 * @since 2.6
	 */

	public function bkap_setup_import() {

		global $wpdb;

		if ( isset( $_POST['ics_feed_key'] ) ) {
			$ics_url_key = $_POST['ics_feed_key'];
		} else {
			$ics_url_key = '';
		}

		$product_id = 0;
		if ( isset( $_POST['product_id'] ) ) {
			$product_id = $_POST['product_id'];
		}

		if ( $product_id == 0 ) {
			$ics_feed_urls = get_option( 'bkap_ics_feed_urls' );
		} elseif ( $product_id > 0 ) {
			$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

			if ( is_array( $booking_settings['ics_feed_url'] ) && count( $booking_settings['ics_feed_url'] ) > 0 ) {
				$ics_feed_urls = $booking_settings['ics_feed_url'];
			}
		}
		if ( $ics_feed_urls == '' || $ics_feed_urls == '{}' || $ics_feed_urls == '[]' || $ics_feed_urls == 'null' ) {
			$ics_feed_urls = array();
		}

		if ( count( $ics_feed_urls ) > 0 && isset( $ics_feed_urls[ $ics_url_key ] ) ) {
			$ics_feed = $ics_feed_urls[ $ics_url_key ];
			$ics_feed = str_replace( 'https://', '', $ics_feed );
		} else {
			$ics_feed = '';
		}

		if ( $ics_feed == '' && count( $_POST ) <= 0 ) { // it means it was called using cron, so we need to auto import for all the calendars saved
			// run the import for all the calendars saved at the admin level
			if ( isset( $ics_feed_urls ) && count( $ics_feed_urls ) > 0 ) {

				foreach ( $ics_feed_urls as $ics_feed ) {
					$ics_feed   = str_replace( 'https://', '', $ics_feed );
					$ical       = new BKAP_iCalReader( $ics_feed );
					$ical_array = $ical->getEvents();

					// check if the import is on an AirBNB Cal
					if ( strpos( $ics_feed, 'airbnb' ) > 0 ) {
						$airbnb = true;
					} else {
						$airbnb = false;
					}
					$this->bkap_import_events( $ical_array, 0, $airbnb );
				}
			}

			$product_ids = array();
			// run the import for all the calendars saved at the product level
			$args    = array(
				'post_type'      => 'product',
				'posts_per_page' => -1,
			);
			$product = query_posts( $args );

			if ( count( $product ) > 0 ) {

				foreach ( $product as $k => $v ) {
					$product_ids[] = $v->ID;
				}

				foreach ( $product_ids as $k => $v ) {

					$duplicate_of = bkap_common::bkap_get_product_id( $v );
					$is_bookable  = bkap_common::bkap_get_bookable_status( $duplicate_of );

					if ( $is_bookable ) {

						$booking_settings = get_post_meta( $duplicate_of, 'woocommerce_booking_settings', true );

						if ( isset( $booking_settings['ics_feed_url'] ) && count( $booking_settings['ics_feed_url'] ) > 0 ) {

							foreach ( $booking_settings['ics_feed_url'] as $key => $value ) {

								$value      = str_replace( 'https://', '', $value );
								$ical       = new BKAP_iCalReader( $value );
								$ical_array = $ical->getEvents();

								// check if the import is on an AirBNB Cal
								if ( strpos( $value, 'airbnb' ) > 0 ) {
									$airbnb = true;
								} else {
									$airbnb = false;
								}
								$this->bkap_import_events( $ical_array, $duplicate_of, $airbnb );
							}
						}
					}
				}
			}
		} else {
			$ical       = new BKAP_iCalReader( $ics_feed );
			$ical_array = $ical->getEvents();

			// check if the import is on an AirBNB Cal
			if ( strpos( $ics_feed, 'airbnb' ) > 0 ) {
				$airbnb = true;
			} else {
				$airbnb = false;
			}
			$this->bkap_import_events( $ical_array, $product_id, $airbnb );

		}

		die();
	}

	/**
	 * Creates records for imported events and automatically maps the same
	 * (based on the settings) for the ICAL Data passed in.
	 *
	 * @param object  $ical_array - Contains all the events in the Google Calendar
	 * @param integer $product_id - Product ID for which the ICAl feed is setup. 0 indicates global
	 * @param boolean $airbnb - Set to true for AirBNB calendars, else false.
	 * @since 2.6
	 */

	public function bkap_import_events( $ical_array, $product_id = 0, $airbnb = false ) {

		global $wpdb;

		if ( isset( $product_id ) && 0 == $product_id ) {
			$event_uids = get_option( 'bkap_event_uids_ids' );
		} else {
			$event_uids = get_post_meta( $product_id, 'bkap_event_uids_ids', true );
		}

		if ( $event_uids == '' || $event_uids == '{}' || $event_uids == '[]' || $event_uids == 'null' ) {
			$event_uids = array();
		}

		if ( isset( $ical_array ) ) {

			// get the last stored event count.
			$options_query = "SELECT option_name FROM `" . $wpdb->prefix . "options` WHERE option_name like 'bkap_imported_events_%'";
			$results       = $wpdb->get_results( $options_query );

			if ( isset( $results ) && count( $results ) > 0 ) {
				$last_count = 0;
				foreach ( $results as $results_key => $option_name ) {
					$explode_array = explode( '_', $option_name->option_name );
					$current_id    = $explode_array[3];

					if ( $last_count < $current_id ) {
						$last_count = $current_id;
					}
				}

				$i = $last_count + 1;

			} else {
				$i = 0;
			}
			foreach ( $ical_array as $key_event => $value_event ) {

				$uid = '';
				if ( $airbnb ) {

					if ( isset( $value_event->uid ) ) {
						$uid = $value_event->uid;
					} else {
						$summary = $value_event->summary;
						if ( strpos( $summary, '(' ) > 0 ) {
							$start  = strpos( $summary, '(' );
							$start += 1;
							$end    = strpos( $summary, ')' );
							$length = $end - $start;
							$uid    = substr( $summary, $start, $length );
						}
					}
				} else {
					$uid = $value_event->uid;
				}

				if ( $uid != '' ) {
					// Do stuff with the event $event
					if ( ! in_array( $uid, $event_uids ) ) {
						// gmt time stamp as Google sends the UTC timestamp
						$current_time = current_time( 'timestamp', 1 );

						// Import future dated events.
						if ( $value_event->start >= $current_time || $value_event->end >= $current_time ) {

							$option_name = 'bkap_imported_events_' . $i;
							add_option( $option_name, json_encode( $value_event ) );

							array_push( $event_uids, $uid );
							if ( isset( $product_id ) && 0 == $product_id ) {
								update_option( 'bkap_event_uids_ids', $event_uids );

								$status = 'bkap-unmapped';
								self::bkap_create_gcal_event_post( $value_event, $product_id, $status, $option_name );

							} else {
								update_post_meta( $product_id, 'bkap_event_uids_ids', $event_uids );

								// get the product type.
								$_product          = wc_get_product( $product_id );
								$automated_mapping = 'NO';

								$status             = 'bkap-unmapped';
								$created_event_post = self::bkap_create_gcal_event_post( $value_event, $product_id, $status, $option_name );

								$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
								if ( isset( $booking_settings['enable_automated_mapping'] ) && 'on' == $booking_settings['enable_automated_mapping'] ) {
									$automated_mapping = 'YES';
									$product_id_to_map = $product_id;

									if ( 'variable' == $_product->get_type() ) {
										if ( isset( $booking_settings['gcal_default_variation'] ) && '' != $booking_settings['gcal_default_variation'] ) {
											$product_id_to_map = $booking_settings['gcal_default_variation'];
											$automated_mapping = 'YES';
										} else {
											$automated_mapping = 'NO';
										}
									}
								}

								if ( isset( $automated_mapping ) && 'YES' == $automated_mapping ) {

									$_POST['ID']         = $created_event_post->id;
									$_POST['product_id'] = $product_id_to_map;
									$_POST['automated']  = 1;
									$_POST['type']       = 'by_post';

									// all the events will be mapped to the product.
									$import_bookings = new import_bookings();
									$import_bookings->bkap_map_imported_event();
								} else {
									$user_id = 0;
									// if the tours addon is active, then the tour operator should receive the email
									if ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) {
										// check if tour operators are allowed to setup GCal
										if ( 'yes' == get_option( 'bkap_allow_tour_operator_gcal_api' ) ) {
											// fetch teh tour operators ID
											if ( isset( $booking_settings['booking_tour_operator'] ) && $booking_settings['booking_tour_operator'] != 0 ) {
												$user_id = $booking_settings['booking_tour_operator'];
											}
										}
									}

									do_action( 'bkap_gcal_events_imported', $option_name, $user_id );
								}
							}
						}
					}
				}
				$i++;
			}
			echo 'All the Events are Imported.';
		}

	}


	/**
	 * Creates & returns a booking post meta record
	 * array to be inserted in post meta.
	 *
	 * @param int   $item_id - Item ID of the Product
	 * @param int   $product_id - Product ID
	 * @param array $booking_details - Array containing booking details
	 *
	 * @since 4.2
	 */

	public static function bkap_create_gcal_event_post( $bkap_event, $product_id, $status, $option_name = '', $user_id = 1 ) {

		$new_event_data = array();

		// Merge booking data
		$defaults = array(
			'user_id'           => $user_id,
			'product_id'        => $product_id,
			'start_date'        => '',
			'end_date'          => '',
			'uid'               => '',
			'summary'           => '',
			'description'       => '',
			'location'          => '',
			'reason_of_fail'    => '',
			'resource_id'       => '',
			'persons'           => array(),
			'qty'               => 1,
			'variation_id'      => 0,
			'event_option_name' => '',
		);

		$new_event_data = wp_parse_args( $new_event_data, $defaults );

		$event_value_set = '';

		if ( is_object( $bkap_event ) && '' != $bkap_event ) {
			$event_uid         = $bkap_event->uid;
			$event_start_str   = $bkap_event->start;
			$event_end_str     = $bkap_event->end;
			$event_summary     = $bkap_event->summary;
			$event_description = $bkap_event->description;
			$event_location    = $bkap_event->location;
			$event_value_set   = 'value_set';
		}

		if ( '' != $event_value_set ) {
			$new_event_data['user_id']           = $user_id;
			$new_event_data['uid']               = $event_uid;
			$new_event_data['start_date']        = $event_start_str;
			$new_event_data['end_date']          = $event_end_str;
			$new_event_data['summary']           = $event_summary;
			$new_event_data['description']       = $event_description;
			$new_event_data['location']          = $event_location;
			$new_event_data['reason_of_fail']    = '';
			$new_event_data['event_option_name'] = $option_name;
		}

		// Create the Instance using the Event Data
		$new_booking_event = self::get_bkap_booking( $new_event_data );
		$new_booking_event->create( $status );

		return $new_booking_event;
	}

	/**
	 * Creating the instance fo the BKAP_Gcal_Event
	 *
	 * @param array $id - contains the event Details
	 * @return object BKAP_Booking
	 * @since 4.2
	 */

	static function get_bkap_booking( $id ) {
		return new BKAP_Gcal_Event( $id );
	}

	/**
	 * Export bookings to Google Calendar from the Booking->View Bookings->Add to Calendar button.
	 * This is especially used to export bookings for orders that were placed before
	 * Automated Google Calendar Sync is enabled.
	 *
	 * @since 2.6
	 */
	public function bkap_admin_booking_calendar_events() {

		$user_id                = $_POST['user_id'];
		$total_orders_to_export = bkap_common::bkap_get_total_bookings_to_export( $user_id );
		$gcal                   = new BKAP_Gcal();
		$current_time           = current_time( 'timestamp' );
		$user                   = new WP_User( $user_id );

		if ( 'tour_operator' == $user->roles[0] ) {
			$event_item_ids = get_the_author_meta( 'tours_event_item_ids', $user_id );
		} else {
			$event_item_ids = get_option( 'bkap_event_item_ids' );
		}

		if ( $event_item_ids == '' || $event_item_ids == '{}' || $event_item_ids == '[]' || $event_item_ids == 'null' ) {
			$event_item_ids = array();
		}

		$i = 0;

		if ( isset( $total_orders_to_export ) && count( $total_orders_to_export ) > 0 ) {
			foreach ( $total_orders_to_export as $item_id ) {

				$order_id = wc_get_order_id_by_order_item_id( $item_id );
				$data     = get_post_meta( $order_id );
				if ( ! in_array( $item_id, $event_item_ids ) ) {

					$event_details = array();
					$order         = wc_get_order( $order_id );
					$get_items     = $order->get_items();

					foreach ( $get_items as $get_items_key => $get_items_value ) {

						if ( $get_items_key == $item_id ) {

							$item_data = $get_items_value->get_data(); // Getting Item data.
							$item_name = $item_data['name'];

							$item_booking_date  = wc_get_order_item_meta( $item_id, '_wapbk_booking_date' );
							$item_checkout_date = wc_get_order_item_meta( $item_id, '_wapbk_checkout_date' );
							$item_booking_time  = wc_get_order_item_meta( $item_id, '_wapbk_time_slot' );
							$product_id         = wc_get_order_item_meta( $item_id, '_product_id' );
							$quantity           = wc_get_order_item_meta( $item_id, '_qty' );
							$booking_status     = wc_get_order_item_meta( $item_id, '_wapbk_booking_status' );
							$resource_id        = wc_get_order_item_meta( $item_id, '_resource_id' );
							$person_ids         = wc_get_order_item_meta( $item_id, '_person_ids' );

							if ( ( isset( $booking_status ) && 'pending-confirmation' != $booking_status ) || ( ! isset( $booking_status ) ) ) {

								if ( isset( $item_booking_date ) && $item_booking_date != '1970-01-01' ) {
									$event_details['hidden_booking_date'] = $item_booking_date;
								}

								if ( isset( $item_checkout_date ) && $item_checkout_date != '' ) {
									$event_details['hidden_checkout_date'] = $item_checkout_date;
								}

								if ( isset( $item_booking_time ) && $item_booking_time != '' ) {
									$event_details['time_slot'] = $item_booking_time;
								}

								if ( isset( $resource_id ) && '' != $resource_id ) {
									$event_details['resource'] = get_the_title( $resource_id );
								}

								if ( isset( $person_ids ) && '' != $person_ids ) {

									if ( isset( $person_ids[0] ) ) {
										$person_info = Class_Bkap_Product_Person::bkap_get_person_label( $product_id ) . ' : ' . $person_ids[0];
									} else {
										$person_info = '';
										foreach ( $person_ids as $p_key => $p_value ) {
											$person_info .= get_the_title( $p_key ) . ' : ' . $p_value . ',';
										}
									}
									$event_details['persons'] = $person_info;
								}

								$event_details['billing_email']     = $data['_billing_email'][0];
								$event_details['billing_address_1'] = $data['_billing_address_1'][0];
								$event_details['billing_address_2'] = $data['_billing_address_2'][0];
								$event_details['billing_city']      = $data['_billing_city'][0];
								$event_details['order_id']          = $order_id;

								$event_details['shipping_first_name'] = $data['_shipping_first_name'][0];
								$event_details['shipping_last_name']  = $data['_shipping_last_name'][0];
								$event_details['shipping_address_1']  = $data['_shipping_address_1'][0];
								$event_details['shipping_address_2']  = $data['_shipping_address_2'][0];
								$event_details['billing_phone']       = $data['_billing_phone'][0];
								$event_details['order_comments']      = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->customer_note : $order->get_customer_note();

								$_product   = wc_get_product( $product_id );
								$post_title = $_product->get_title();

								$event_details['product_name']  = $item_name;
								$event_details['product_qty']   = $quantity;
								$event_details['product_total'] = wc_get_order_item_meta( $item_id, '_line_total' );

								$post_id = $product_id;
								if ( ( ! isset( $booking_settings['product_sync_integration_mode'] ) ) || ( isset( $booking_settings['product_sync_integration_mode'] ) && 'disabled' == $booking_settings['product_sync_integration_mode'] ) ) {
									$post_id = 0;
								}

								$event_status = $gcal->insert_event( $event_details, $item_id, $user_id, $post_id, false );

								if ( $event_status ) {
									$i++;
									// add an order note, mentioning an event has been created for the item
									$order_note = __( "Booking_details for $post_title have been exported to the Google Calendar", 'woocommerce-booking' );
									$order->add_order_note( $order_note );
								}
							}
						}
					}
				}
			}
		}

		if ( $i ) {
			$bkap_view_booking = array(
				'total_bookings_to_exported_msg' => sprintf( __( '%s bookings have been exported to your Google Calendar. Please refresh your Google Calendar.', 'woocommerce-booking' ), $i ),
			);
		} else {
			$bkap_view_booking = array(
				'total_bookings_to_exported_msg' => __( 'No bookings have been exported to your Google Calendar. Please check the Google Calendar Sync Settings.', 'woocommerce-booking' ),
			);
		}

		wp_send_json( $bkap_view_booking );

		die();
	}
}//end class
$bkap_calendar_sync = new bkap_calendar_sync();
