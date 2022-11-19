<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Generating ICS file of the booking on Order Receive page and Email notification
 *
 * @author      Tyche Softwares
 * @package     BKAP/ICS
 * @since       2.0
 * @category    Classes
 */

require_once 'bkap-common.php';
require_once 'bkap-lang.php';

if ( ! class_exists( 'Bkap_Ics' ) ) {

	/**
	 * Class for Generating ICS file of the booking on Order Receive page and Email notification
	 *
	 * @class Bkap_Ics
	 */
	class Bkap_Ics {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */
		public function __construct() {

			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			// Add order details as an attachment.
			if ( isset( $global_settings->booking_attachment ) && 'on' === $global_settings->booking_attachment ) {
				add_filter( 'woocommerce_email_attachments', array( 'bkap_ics', 'bkap_email_attachment' ), 10, 3 );
			}
		}

		/**
		 * This function attach the ICS file with the booking details in the email sent to user and admin.
		 *
		 * @since 1.7
		 * @param  array  $other Empty array.
		 * @param  object $email_id ID of email template.
		 * @param  object $order Order Object.
		 * @global object $wpdb Global wpdb object.
		 * @global object $woocommerce Global WooCommerce object.
		 *
		 * @return $file Returns the ICS file for the booking.
		 */
		public static function bkap_email_attachment( $other, $email_id, $order ) {
			global $wpdb;

			if ( isset( $order ) && ( 'WC_Order' === get_class( $order ) || 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) ) {
				$order_id = $order->get_id();
			} elseif ( isset( $order->order_id ) ) {
				$order_id = $order->order_id;
			} elseif ( isset( $order ) && ( 'WC_Order' === get_class( $order ) || 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) && isset( $order->id ) ) {
				$order_id = $order->get_id();
			}

			$file_path = bkap_temporary_directory();;
			$file      = array();
			$c         = 0;

			if ( isset( $order_id ) && 0 !== $order_id ) {

				$order_obj   = wc_get_order( $order_id );
				$order_items = $order_obj->get_items();

				$results_date = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT * FROM `' . $wpdb->prefix . 'booking_history` AS a1,`' . $wpdb->prefix . 'booking_order_history` AS a2 WHERE a1.id = a2.booking_id AND a2.order_id = %d',
						$order_id
					)
				);

				if ( $results_date ) {

					$results_date_count = count( $results_date );

					foreach ( $order_items as $item_key => $item_value ) {

						$duplicate_of = get_post_meta( $item_value['product_id'], '_icl_lang_duplicate_of', true );

						if ( '' === $duplicate_of || null === $duplicate_of ) {
							$post_time       = get_post( $item_value['product_id'] );
							$results_post_id = $wpdb->get_results(
								$wpdb->prepare(
									'SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE post_date = %s ORDER BY ID LIMIT 1',
									$post_time->post_date
								)
							);

							if ( isset( $results_post_id ) ) {
								$duplicate_of = $results_post_id[0]->ID;
							} else {
								$duplicate_of = $item_value['product_id'];
							}
						}

						$booking_settings = get_post_meta( $item_value['product_id'], 'woocommerce_booking_settings', true );
						$file_name        = get_option( 'book_ics-file-name' );

						if ( isset( $item_value['wapbk_booking_date'] ) && '' !== $item_value['wapbk_booking_date'] ) {

							$bkap_calendar_sync = new bkap_calendar_sync();
							$app                = $bkap_calendar_sync->bkap_create_gcal_obj( $item_value->get_id(), $item_value, $order_obj );

							for ( $c = 0; $c < $results_date_count; $c++ ) {

								if ( (int) $results_date[ $c ]->post_id === (int) $duplicate_of ) {

									$global_settings   = bkap_global_setting();
									$timezone_check    = bkap_timezone_check( $global_settings );
									$site_timezone     = bkap_booking_get_timezone_string();
									$customer_timezone = sanitize_text_field( wp_unslash( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) );

									$start_date = $results_date[ $c ]->start_date;
									$from_time  = $results_date[ $c ]->from_time;
									$to_time    = $results_date[ $c ]->to_time;

									if ( $timezone_check && '' !== $customer_timezone ) {

										$from_time = bkap_convert_date_from_timezone_to_timezone( $start_date . ' ' . $from_time, $site_timezone, $customer_timezone, 'H:i' );
										$to_time   = bkap_convert_date_from_timezone_to_timezone( $start_date . ' ' . $to_time, $site_timezone, $customer_timezone, 'H:i' );
									}

									if ( ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) && ( isset( $booking_settings['booking_enable_multiple_day'] ) && '' === $booking_settings['booking_enable_multiple_day'] ) ) {

										$booked_product    = array();
										$bkap_booking_date = $item_value['wapbk_booking_date'];
										$duration_time     = false;

										if ( strpos( $bkap_booking_date, ' - ' ) !== false ) {
											$duration_time  = true;
											$duration_dates = explode( ' - ', $item_value['wapbk_booking_date'] );
										}

										$dt               = strtotime( $start_date );
										$time_start_value = 0;
										$time_end_value   = 0;

										if ( 'on' === $booking_settings['booking_enable_time'] || 'duration_time' === $booking_settings['booking_enable_time'] ) {
											$time_start = explode( ':', $from_time );
											$time_end   = explode( ':', $to_time );

											if ( isset( $time_start[0] ) && isset( $time_start[1] ) ) {
												$time_start_value = $time_start[0] * 60 * 60 + $time_start[1] * 60;
											}

											if ( isset( $time_end[0] ) && isset( $time_end[1] ) ) {
												$time_end_value = $time_end[0] * 60 * 60 + $time_end[1] * 60;
											}
										}

										$start_timestamp = $dt + $time_start_value + ( time() - current_time( 'timestamp' ) );

										if ( $duration_time ) {
											$end_timestamp = strtotime( $duration_dates[1] ) + $time_end_value + ( time() - current_time( 'timestamp' ) );
											if ( $time_end > 0 ) {
												$end_timestamp = strtotime( $duration_dates[1] ) + $time_end_value + ( time() - current_time( 'timestamp' ) );
											}
										} else {
											$end_timestamp = $start_timestamp;
											if ( $time_end_value > 0 ) {
												$end_timestamp = $dt + $time_end_value + ( time() - current_time( 'timestamp' ) );
											}
										}

										$booked_product['start_timestamp'] = $start_timestamp;
										$booked_product['end_timestamp']   = $end_timestamp;
										$booked_product['name']            = $item_value['name'];
										$booked_product['summary']         = str_replace(
											array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
											array( get_bloginfo( 'name' ), $app->client_name, $app->product, $app->product_with_qty, $app->order_date_time, $app->order_date, $app->id, $app->order_total, $app->client_phone, $app->order_note, $app->client_address, $app->client_email, $app->resource, $app->persons, $app->zoom_meeting ),
											get_option( 'bkap_calendar_event_description' )
										);

										$file[ $c ] = $file_path . '/' . $file_name . '_' . $c . '.ics';
										$current    = self::bkap_ics_booking_details_email( $booked_product );
										file_put_contents( $file[ $c ], $current );
									} elseif ( ( isset( $booking_settings['booking_enable_date'] ) && 'on' === $booking_settings['booking_enable_date'] ) && ( isset( $booking_settings['booking_enable_multiple_day'] ) && 'on' === $booking_settings['booking_enable_multiple_day'] ) ) {
										$booked_product                    = array();
										$booked_product['start_timestamp'] = strtotime( $results_date[ $c ]->start_date );
										$booked_product['end_timestamp']   = strtotime( $results_date[ $c ]->start_date );
										$booked_product['name']            = $item_value['name'];
										$booked_product['summary']         = str_replace(
											array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
											array( get_bloginfo( 'name' ), $app->client_name, $app->product, $app->product_with_qty, $app->order_date_time, $app->order_date, $app->id, $app->order_total, $app->client_phone, $app->order_note, $app->client_address, $app->client_email, $app->resource, $app->persons, $app->zoom_meeting ),
											get_option( 'bkap_calendar_event_description' )
										);
										self::bkap_ics_booking_details_email( $booked_product );

										$file[ $c ] = $file_path . '/' . $file_name . '_' . $c . '.ics';
										$current    = self::bkap_ics_booking_details_email( $booked_product );
										file_put_contents( $file[ $c ], $current );
									}
								}
							}
						}
					}
				}
			}

			return $file;
		}

		/**
		 * This function create the string required to create the ICS file with the booking details.
		 *
		 * @since 1.7
		 * @param array $booked_product Array of booking details and product name.
		 *
		 * @return string $icsString Returns the string of the ICS file for the booking
		 */
		public static function bkap_ics_booking_details_email( $booked_product ) {

			$description = $booked_product['summary'];
			$description = str_replace( '\x0D', '', $description ); // lf - html break.
			$description = preg_replace( "/\r|\n/", '', $description );

			$ics_string = 'BEGIN:VCALENDAR
PRODID:-//Events Calendar//iCal4j 1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
DTSTART:' . date( 'Ymd\THis\Z', $booked_product['start_timestamp'] ) . '
DTEND:' . date( 'Ymd\THis\Z', $booked_product['end_timestamp'] ) . '
DTSTAMP:' . date( 'Ymd\THis\Z', current_time( 'timestamp' ) ) . '
UID:' . ( uniqid() ) . '
DESCRIPTION:' . $description . '
SUMMARY:' . $booked_product['name'] . '
END:VEVENT
END:VCALENDAR';

			return $ics_string;
		}

	}

	new Bkap_Ics();
}
