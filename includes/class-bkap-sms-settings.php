<?php
/**
 * It will display the email template listing.
 *
 * @author   Tyche Softwares
 * @package  BKAP/SMS-Reminder
 * @since 4.17.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get the PHP helper library from twilio.com/docs/php/install.
require_once BKAP_PLUGIN_PATH . '/includes/libraries/twilio-php/Twilio/autoload.php'; // Loads the library.
use Twilio\Rest\Client;

if ( ! class_exists( 'Bkap_SMS_Settings' ) ) {
	/**
	 * It will display the SMS settings for the plugin.
	 *
	 * @since 4.17.0
	 */
	class Bkap_SMS_Settings {

		/**
		 * Constructor
		 *
		 * @since 4.17.0
		 */
		public function __construct() {
			add_action( 'bkap_sms_reminder_settings', array( $this, 'bkap_send_sms_reminders' ) );
			add_action( 'admin_init', array( $this, 'bkap_save_sms_settings' ) );
			add_action( 'init', array( $this, 'bkap_save_sms_settings' ) );
		}

		/**
		 * This function will save the SMS settings to option
		 *
		 * @since 5.17.0
		 */
		public static function bkap_save_sms_settings() {

			if ( ! empty( $_POST ) && isset( $_POST[ 'bkap_sms_reminder' ] ) ) {

				$is_vendor = false;
				if ( ! is_admin() ) {
					$vendor_id = get_current_user_id();
					$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
				}

				if ( $is_vendor ) {
					$sms_option_name = 'bkap_vendor_sms_settings_' . $vendor_id;
				} else {
					$sms_option_name = 'bkap_sms_settings';
				}

				$bkap_sms_settings = array();
				if ( isset( $_POST['bkap_sms_settings'] ) && is_array( $_POST['bkap_sms_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$bkap_sms_settings         = array_map( 'sanitize_text_field', wp_unslash( $_POST['bkap_sms_settings'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					update_option( $sms_option_name, $bkap_sms_settings );
				}
			}
		}

		/**
		 * This function will send the SMS
		 *
		 * @param obj     $booking - Booking Object.
		 * @param array   $twilio_details - Array of Twilio Settings Details.
		 * @param integer $item_id - Item ID.
		 *
		 * @since 5.17.0
		 */
		public static function bkap_send_automatic_sms_reminder( $booking, $twilio_details, $item_id ) {

			$item_obj            = bkap_common::get_bkap_booking( $item_id );
			$from                = $twilio_details['from'];
			$sid                 = $twilio_details['sid'];
			$token               = $twilio_details['token'];
			$msg_body            = $twilio_details['body'];
			$product_title       = $item_obj->product_title;
			$order_date          = $item_obj->order_date;
			$order_number        = $item_obj->order_id;
			$start_date          = $item_obj->item_booking_date;
			$end_date            = $item_obj->item_checkout_date;
			$booking_time        = $item_obj->item_booking_time;
			$booking_id          = $item_obj->booking_id;
			$booking_resource    = $item_obj->resource_title;
			$booking_persons     = $item_obj->person_data;
			$zoom_link           = $item_obj->zoom_meeting;
			$customer_name       = '';
			$customer_first_name = '';
			$customer_last_name  = '';
			$order_obj           = wc_get_order( $item_obj->order_id );
			$user_id             = $item_obj->customer_id;
			if ( $user_id > 0 ) {
				$customer            = get_user_by( 'id', $item_obj->customer_id );
				$customer_name       = $customer->display_name;
				$customer_first_name = $customer->first_name;
				$customer_last_name  = $customer->last_name;
				$to_phone            = self::bkap_get_phone( $item_obj->customer_id );
			} else {
				$to_phone        = $order_obj->get_billing_phone();
				$country_map     = bkap_country_code_map();
				$billing_country = $order_obj->get_billing_country();
				$dial_code       = isset( $country_map[ $billing_country ] ) ? $country_map[ $billing_country ]['dial_code'] : '';
				if ( is_numeric( $to_phone ) ) {
					// if first character is not a +, add it.
					if ( substr( $to_phone, 0, 1 ) !== '+' ) {
						if ( '' !== $dial_code ) {
							$to_phone = $dial_code . $to_phone;
						} else {
							$to_phone = '+' . $to_phone;
						}
					}
				}
				$customer_name       = $order_obj->get_formatted_billing_full_name();
				$customer_first_name = $order_obj->get_billing_first_name();
				$customer_last_name  = $order_obj->get_billing_last_name();
			}

			$body = str_replace(
				array(
					'{product_title}',
					'{order_date}',
					'{order_number}',
					'{customer_name}',
					'{customer_first_name}',
					'{customer_last_name}',
					'{start_date}',
					'{end_date}',
					'{booking_time}',
					'{booking_id}',
					'{booking_resource}',
					'{booking_persons}',
					'{zoom_link}',
				),
				array(
					$product_title,
					$order_date,
					$order_number,
					$customer_name,
					$customer_first_name,
					$customer_last_name,
					$start_date,
					$end_date,
					$booking_time,
					$booking_id,
					$booking_resource,
					$booking_persons,
					$zoom_link,
				),
				$msg_body
			);

			// send the message.
			if ( $to_phone ) {

				try {
					$client  = new Client( $sid, $token );
					$message = $client->messages->create(
						$to_phone,
						array(
							'from' => $from,
							'body' => $body,
						)
					);

					if ( $message->sid ) {
						$message_sid     = $message->sid;
						$message_details = $client->messages( $message_sid )->fetch();
						$status          = $message_details->status;
						/* translators: %s: Booking ID */
						$sms_msg = sprintf( __( 'The reminder SMS for Booking #%1$s has been sent to %2$s.', 'woocommerce-booking' ), $booking_id, $to_phone );

						$order_obj->add_order_note( $sms_msg );
					}
				} catch ( Exception $e ) {
					$msg = $e->getMessage();
				}
			}
		}

		/**
		 * Returns the Phone number of the user
		 *
		 * @param integer $user_id - User ID.
		 * @return string`|boolean - Phone Number.
		 *
		 * @since 4.17.0
		 */
		public static function bkap_get_phone( $user_id ) {

			global $wpdb;

			$country_map     = bkap_country_code_map();
			$to_phone        = '';
			$user            = get_user_by( 'id', $user_id );
			$billing_country = $user->billing_country;
			$dial_code       = isset( $country_map[ $billing_country ] ) ? $country_map[ $billing_country ]['dial_code'] : '';
			$to_phone        = $user->billing_phone;

			// Verify the Phone number.
			if ( is_numeric( $to_phone ) ) {
				// if first character is not a +, add it.
				if ( substr( $to_phone, 0, 1 ) !== '+' ) {
					if ( '' !== $dial_code ) {
						$to_phone = $dial_code . $to_phone;
					} else {
						$to_phone = '+' . $to_phone;
					}
				}
				return $to_phone;
			} else {
				return false;
			}
		}

		/**
		 * Adds settings for SMS Notifications
		 *
		 * @since 7.9
		 */
		public static function bkap_send_sms_reminders() {
			
			$sms_settings = get_option( 'bkap_sms_settings' );
			$sms_settings = apply_filters( 'bkap_sms_settings', $sms_settings );

			wc_get_template(
				'reminders/bkap-reminder-sms-view.php',
				array( 'options' => $sms_settings ),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Sends a Test SMS
		 * Called via AJAX
		 *
		 * @since 4.17.0
		 */
		public static function bkap_send_test_sms() {

			$msg_array    = array();
			$phone_number = ( isset( $_POST['number'] ) ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			$msg          = ( isset( $_POST['msg'] ) && '' !== $_POST['msg'] ) ? sanitize_textarea_field( wp_unslash( $_POST['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			if ( '' !== $phone_number && '' !== $msg ) {

				// Verify the Phone number.
				if ( is_numeric( $phone_number ) ) {

					// if first character is not a +, add it.
					if ( substr( $phone_number, 0, 1 ) !== '+' ) {
						$phone_number = '+' . $phone_number;
					}

					$sms_settings = get_option( 'bkap_sms_settings' );
					$sms_settings = apply_filters( 'bkap_sms_settings', $sms_settings );
					$sid          = isset( $sms_settings['account_sid'] ) ? $sms_settings['account_sid'] : '';
					$token        = isset( $sms_settings['auth_token'] ) ? $sms_settings['auth_token'] : '';
					$from         = isset( $sms_settings['from'] ) ? $sms_settings['from'] : '';

					if ( '' !== $sid && '' !== $token ) {

						try {
							$client = new Client( $sid, $token );

							$message = $client->messages->create(
								$phone_number,
								array(
									'from' => $from,
									'body' => $msg,
								)
							);

							if ( $message->sid ) {
								$message_sid = $message->sid;

								$message_details = $client->messages( $message_sid )->fetch();
								$status          = $message_details->status;
								$errormsg        = $message_details->errorMessage; // phpcs:ignore
								/* translators: %s: Status of the message */
								$msg_array[] = sprintf( __( 'Message Status: %s', 'woocommerce-booking' ), $status );
							}
						} catch ( Exception $e ) {
							$msg_array[] = $e->getMessage();
						}
					} else { // Account Information is incomplete.
						$msg_array[] = __( 'Incomplete Twilio Account Details. Please provide an Account SID and Auth Token to send a test message.', 'woocommerce-booking' );
					}
				} else {
					$msg_array[] = __( 'Please enter the phone number in E.164 format', 'woocommerce-booking' );
				}
			} else { // Phone number/Msg has not been provided.
				$msg_array[] = __( 'Please make sure the Recipient Number and Message field are populated with valid details.', 'woocommerce-booking' );
			}

			echo wp_json_encode( $msg_array );
			die();
		}
	} // end of class
	$bkap_sms_settings = new Bkap_SMS_Settings();
}
