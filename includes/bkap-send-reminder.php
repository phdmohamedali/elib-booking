<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Menu page for sending manual reminder emails and setting automatic reminders for bookings
 *
 * @author      Tyche Softwares
 * @package     BKAP/Menus
 * @since       2.0
 * @category    Classes
 */

if ( ! class_exists( 'Bkap_Send_Reminder' ) ) {

	/**
	 * Class Bkap_Send_Reminder
	 */
	class Bkap_Send_Reminder {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'admin_init', array( $this, 'bkap_send_automatic_reminder' ), 10 );
			add_filter( 'woocommerce_screen_ids', array( $this, 'bkap_add_screen_id' ) );
			add_action( 'bkap_auto_reminder_emails', array( $this, 'bkap_send_auto_reminder_emails' ) );
			add_action( 'bkap_reminder_email_heading', array( $this, 'bkap_reminder_email_settings_page_heading' ), 10 );
			add_action( 'bkap_automatic_reminder_email_settings', array( $this, 'bkap_automatic_reminder_email_settings' ), 10 );
			add_action( 'bkap_manual_reminder_email_settings', array( $this, 'bkap_manual_reminder_email_settings' ), 10 );
		}

		/**
		 * Manual Reminder Email Settings.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_manual_reminder_email_settings() {

			$format       = bkap_common::bkap_get_date_format();
			$current_date = date( $format ); // phpcs:ignore

			if ( ! empty( $_POST ) ) {

				$product_ids = isset( $_POST['bkap_reminder_product_id'] ) && '' != $_POST['bkap_reminder_product_id'] ? $_POST['bkap_reminder_product_id'] : '';
				$booking_ids = isset( $_POST['bkap_reminder_booking_id'] ) && '' != $_POST['bkap_reminder_booking_id'] ? $_POST['bkap_reminder_booking_id'] : '';
				$order_ids   = isset( $_POST['bkap_reminder_order_id'] ) && '' != $_POST['bkap_reminder_order_id'] ? $_POST['bkap_reminder_order_id'] : '';

				$subject  = isset( $_POST['bkap_reminder_subject'] ) && '' != $_POST['bkap_reminder_subject'] ? $_POST['bkap_reminder_subject'] : 'Booking Reminder';
				$message  = isset( $_POST['bkap_reminder_message'] ) && '' != $_POST['bkap_reminder_message'] ? $_POST['bkap_reminder_message'] : '';
				$mailer   = WC()->mailer();
				$reminder = $mailer->emails['BKAP_Email_Booking_Reminder'];
				$success  = __( 'Reminder sent successfully', 'woocommerce-booking' );

				if ( is_array( $product_ids ) && ! empty( $product_ids ) ) {

					foreach ( $product_ids as $product_id ) {

						$bookings = bkap_common::bkap_get_bookings_by_product( $product_id );
						foreach ( $bookings as $id => $booking ) {
							$reminder->trigger( $booking->get_item_id(), $subject, $message );
							do_action( 'bkap_send_manual_reminder_emails', $booking, $item_id );
							echo '<div class="updated fade"><p>' . $success . '</p></div>'; // phpcs:ignore
						}
					}
				}

				if ( is_array( $booking_ids ) && ! empty( $booking_ids ) ) {

					foreach ( $booking_ids as $booking_id ) {
						$booking    = new BKAP_Booking( $booking_id );
						$start_date = $booking->get_start();

						if ( strtotime( $start_date ) > strtotime( $current_date ) ) {
							$reminder->trigger( $booking->get_item_id(), $subject, $message );
							do_action( 'bkap_send_manual_reminder_emails', $booking, $item_id );
							echo '<div class="updated fade"><p>' . $success . '</p></div>'; // phpcs:ignore
						}
					}
				}

				if ( is_array( $order_ids ) && ! empty( $order_ids ) ) {

					foreach ( $order_ids as $order_id ) {
						$order_bookings = bkap_common::get_booking_ids_from_order_id( $order_id );

						foreach ( $order_bookings as $booking_id ) {
							$booking    = new BKAP_Booking( $booking_id );
							$start_date = $booking->get_start();

							if ( strtotime( $start_date ) > strtotime( $current_date ) ) {
								$reminder->trigger( $booking->get_item_id(), $subject, $message );
								do_action( 'bkap_send_manual_reminder_emails', $booking, $item_id );
								echo '<div class="updated fade"><p>' . $success . '</p></div>'; // phpcs:ignore
							}
						}
					}
				}
			}

			$product_args      = apply_filters( 'bkap_get_product_args_for_manual_reminder', array() );
			$bookable_products = bkap_common::ts_get_all_bookable_products( $product_args );
			$additional_args   = apply_filters( 'bkap_get_bookings_args_for_manual_reminder', array() );
			$all_booking_ids   = array();
			$bookings          = bkap_common::bkap_get_bookings( array( 'paid', 'confirmed' ), $additional_args );

			foreach ( $bookings as $key => $value ) {
				array_push( $all_booking_ids, $value->get_id() );
			}

			$all_order_ids = bkap_common::bkap_get_orders_with_bookings( $additional_args );
			$saved_subject = get_option( 'reminder_subject' );
			if ( isset( $saved_subject ) && '' != $saved_subject ) {
				$email_subject = $saved_subject;
			} else {
				$email_subject = __( 'Booking Reminder', 'woocommerce-booking' );
			}

			$email_subject = apply_filters( 'bkap_manual_reminder_email_subject', $email_subject );

			$saved_message = get_option( 'reminder_message' );
			if ( isset( $saved_message ) && '' != $saved_message ) {
				$content = $saved_message;
			} else {
				$content = 'Hi {customer_first_name},

You have a booking of {product_title} on {start_date}. 

Your Order # : {order_number}
Order Date : {order_date}
Your booking id is: {booking_id}
';
			}
			$content = apply_filters( 'bkap_manual_reminder_email_content', $content );

			wc_get_template(
				'reminders/bkap-manual-reminder-email-settings.php',
				array(
					'bkap_heading'      => __( 'Manual Reminders', 'woocommerce-booking' ),
					'bookable_products' => $bookable_products,
					'booking_ids'       => $all_booking_ids,
					'order_ids'         => array_unique( $all_order_ids ),
					'email_subject'     => $email_subject,
					'content'           => $content,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Automatic Reminder Email Settings.
		 *
		 * @since 5.10.0
		 */
		public function bkap_automatic_reminder_email_settings() {

			if ( is_admin() ) {
				wc_get_template(
					'reminders/bkap-automatic-reminder-email-settings.php',
					array(
						'bkap_heading' => __( 'Automatic Reminders', 'woocommerce-booking' ),
					),
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);
			}
		}

		/**
		 * Reminder Email Page Heading.
		 *
		 * @since 5.10.0
		 */
		public function bkap_reminder_email_settings_page_heading() {

			if ( is_admin() ) {
				wc_get_template(
					'bkap-page-heading.php',
					array(
						'bkap_heading' => __( 'Reminder Settings', 'woocommerce-booking' ),
					),
					'woocommerce-booking/',
					BKAP_BOOKINGS_TEMPLATE_PATH
				);
			}
		}

		/**
		 * Settings screen id for Send reminder page.
		 *
		 * @param array $screen_ids Array of Screen Ids.
		 * @since 4.10.0
		 */
		public static function bkap_add_screen_id( $screen_ids ) {

			$screen_ids[] = 'bkap_booking_page_booking_reminder_page';
			return $screen_ids;
		}

		/**
		 * Callback for Reminder Settings section.
		 */
		public static function bkap_reminder_settings_section_callback() {}

		/**
		 * Add a page in the Bookings menu to send reminder emails
		 *
		 * @since 4.10.0
		 */
		public static function bkap_add_reminder_page() {

			/**
			 * Reminder Email Page Heading.
			 */
			do_action( 'bkap_reminder_email_heading' );

			/**
			 * Automatic Reminder Email Settings.
			 */
			do_action( 'bkap_automatic_reminder_email_settings' );

			/**
			 * Manual Reminder Email Settings.
			 */
			do_action( 'bkap_manual_reminder_email_settings' );

			/**
			 * SMS Reminder Settings.
			 */
			do_action( 'bkap_sms_reminder_settings' );
		}

		/**
		 * Add a setting for automatic reminders to set the number of hours
		 *
		 * @since 4.10.0
		 */
		public static function bkap_send_automatic_reminder() {

			add_settings_section(
				'bkap_reminder_section',
				'',
				array( 'Bkap_Send_Reminder', 'bkap_reminder_settings_section_callback' ),
				'booking_reminder_page'
			);

			add_settings_field(
				'reminder_email_before_hours',
				__( 'Number of hours for reminder before booking date', 'woocommerce-booking' ),
				array( 'Bkap_Send_Reminder', 'reminder_email_before_hours_callback' ),
				'booking_reminder_page',
				'bkap_reminder_section',
				array( __( 'Send the reminder email X number of hours before booking date', 'woocommerce-booking' ) )
			);

			register_setting(
				'bkap_reminder_settings',
				'bkap_reminder_settings',
				array( 'Bkap_Send_Reminder', 'bkap_reminder_settings_callback' )
			);
		}

		/**
		 * Callback function for the automatic reminder settings
		 *
		 * @param array $args Argument Array.
		 * @since 4.10.0
		 */
		public static function reminder_email_before_hours_callback( $args ) {

			$number_of_hours = self::bkap_update_reminder_email_day_to_hour();

			if ( is_admin() ) {
				if ( $number_of_hours > 0 ) {
					if ( ! wp_next_scheduled( 'bkap_auto_reminder_emails' ) ) {
						wp_schedule_event( time(), 'hourly', 'bkap_auto_reminder_emails' );
					}
				} else {
					if ( '' === get_option( 'bkap_vendor_enabled_automatic_reminders', '' ) ) {
						wp_clear_scheduled_hook( 'bkap_auto_reminder_emails' );
					}
				}
			}

			echo '<input type="number" name="bkap_reminder_settings[reminder_email_before_hours]" id="reminder_email_before_hours" value="' . esc_attr( $number_of_hours ) . '"/>';
			$html = '<label for="reminder_email_before_hours"> ' . $args[0] . '</label>';
			echo $html; // phpcs:ignore
		}

		public static function bkap_automatic_reminder_email_settings_html( $vendor_id, $vendor_type ) {

			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				$option_name = 'bkap_vendor_reminder_settings_' . $vendor_id;
			} else {
				$option_name = 'bkap_reminder_settings';
			}

			$saved_settings  = json_decode( get_option( $option_name ) );
			$number_of_hours = ( isset( $saved_settings->reminder_email_before_hours ) &&
			'' !== $saved_settings->reminder_email_before_hours ) ? $saved_settings->reminder_email_before_hours : 0;
			$save_button     = 'save_reminder_' . $vendor_type;

			// Saving the Automatic Reminder Email Settings.
			if ( ! empty( $_POST ) && isset( $_POST[ $save_button ] ) ) {
				if ( null === $saved_settings ) {
					$saved_settings = new stdClass();
				}
				$number_of_hours                             = (int) $_POST['bkap_reminder_settings']['reminder_email_before_hours'];
				$saved_settings->reminder_email_before_hours = $number_of_hours;
				update_option( $option_name, wp_json_encode( $saved_settings ) );
				$vendors = get_option( 'bkap_vendor_enabled_automatic_reminders', '' );
				if ( $is_vendor ) {
					if ( '' === $vendors ) {
						if ( $number_of_hours > 0 ) {
							update_option( 'bkap_vendor_enabled_automatic_reminders', $vendor_id );
							if ( ! wp_next_scheduled( 'bkap_auto_reminder_emails' ) ) {
								wp_schedule_event( time(), 'hourly', 'bkap_auto_reminder_emails' );
							}
						}
					} else {
						if ( $number_of_hours > 0 ) {
							update_option( 'bkap_vendor_enabled_automatic_reminders', $vendors . ',' . $vendor_id );
							if ( ! wp_next_scheduled( 'bkap_auto_reminder_emails' ) ) {
								wp_schedule_event( time(), 'hourly', 'bkap_auto_reminder_emails' );
							}
						} elseif ( 0 === $number_of_hours ) {
							$all_vendors = explode( ',', $vendors );
							if ( ( $key = array_search( $vendor_id, $all_vendors ) ) !== false) {
								unset( $all_vendors[$key] );
							}

							if ( empty( $all_vendors ) ) {
								update_option( 'bkap_vendor_enabled_automatic_reminders', '' );

								$number_of_hours = self::bkap_update_reminder_email_day_to_hour();
								if ( $number_of_hours <= 0 ) {
									wp_clear_scheduled_hook( 'bkap_auto_reminder_emails' );
								}
							} else {
								update_option( 'bkap_vendor_enabled_automatic_reminders', implode( ',', $all_vendors ) );
							}
						}
					}
				}
			}

			if ( ! empty( $_POST ) && isset( $_POST[ 'bkap_sms_reminder' ] ) ) {

				if ( $is_vendor ) {
					$sms_option_name = 'bkap_vendor_sms_settings_' . $vendor_id;
				} else {
					$sms_option_name = 'bkap_sms_settings';
				}

				$bkap_sms_settings = array();
				if ( isset( $_POST['bkap_sms_settings'] ) && is_array( $_POST['bkap_sms_settings'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
					$bkap_sms_settings         = array_map( 'sanitize_text_field', wp_unslash( $_POST['bkap_sms_settings'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					$bkap_sms_settings['body'] = sanitize_textarea_field( wp_unslash( $_POST['bkap_sms_settings']['body'] ) );
					update_option( $sms_option_name, $bkap_sms_settings );
				}
			}

			$heading     = __( 'Automatic Reminders', 'woocommerce-booking' );
			$row_heading = __( 'Number of hours for reminder before booking date', 'woocommerce-booking' );
			$label       = __( ' Send the reminder email X number of hours before booking date', 'woocommerce-booking' );

			// Including the template from core plugin.
			wc_get_template(
				'reminders/bkap-automatic-reminder-email-settings-html.php',
				array(
					'heading'         => $heading,
					'row_heading'     => $row_heading,
					'number_of_hours' => $number_of_hours,
					'label'           => $label,
					'save_button'     => $save_button,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Save the Automatic reminder Settings
		 *
		 * @param array $input - Settings on the page.
		 * @return string $bkap_reminder_settings - JSON.
		 * @since 4.10.0
		 */
		public static function bkap_reminder_settings_callback( $input ) {
			$bkap_reminder_settings_callback = ( is_array( $input) ) ? wp_json_encode( $input ) : $input;
			return $bkap_reminder_settings_callback;
		}

		/**
		 * Scheduled event for the automatic reminder emails
		 *
		 * @since 4.10.0
		 */
		public static function bkap_send_auto_reminder_emails() {

			$booking_posts     = bkap_get_future_bookings();
			$current_date      = date( 'Y-m-d H', current_time( 'timestamp' ) ); // phpcs:ignore
			$current_date      = $current_date . ':00';
			$current_date_time = strtotime( $current_date );
			$number_of_hours   = self::bkap_update_reminder_email_day_to_hour();
			$number_of_hours   = absint( $number_of_hours );
			$mailer            = WC()->mailer();
			$reminder          = $mailer->emails['BKAP_Email_Booking_Reminder'];
			$twilio_details    = bkap_get_sms_settings(); // Getting SMS settings.

			// Vendor Settings.
			$vendor_hours = BKAP_Vendors::bkap_vendor_reminder_hours(); // key - Vendor ID & Value - Hours.
			$vendor_sms   = BKAP_Vendors::bkap_vendor_sms_settings(); // key - Vendor ID & Value - Hours.

			foreach ( $booking_posts as $key => $value ) {
				$booking       = new BKAP_Booking( $value->ID );
				$booked_date   = date( 'Y-m-d H', strtotime( $booking->get_start() ) );
				$hours         = $number_of_hours;
				$twiliodetails = $twilio_details;
				
				// Consider the Hours set by vendor.
				$vendor_id = $booking->get_vendor_id();
				if ( isset( $vendor_hours[ $vendor_id ] ) ) {
					$hours = absint( $vendor_hours[ $vendor_id ] );
				}

				// Consider the sms settings set by vendor.
				if ( isset( $vendor_sms[ $vendor_id ] ) ) {
					$twiliodetails = $vendor_sms[ $vendor_id ];
				}

				$booked_date  = $booked_date . ':00';
				$booking_date = strtotime( $booked_date ); // phpcs:ignore
				$interval     = ( $booking_date - $current_date_time ); // booking date - current date time.
				if ( $interval === absint( $hours * 3600 ) ) { // phpcs:ignore
					$item_id = $booking->get_item_id();
					$reminder->trigger( $item_id );
					if ( is_array( $twilio_details ) ) {
						Bkap_SMS_settings::bkap_send_automatic_sms_reminder( $booking, $twiliodetails, $item_id );
					}

					// Sending remiders from other tools.
					do_action( 'bkap_send_auto_reminder_emails', $booking, $item_id );
				}
			}
		}

		/**
		 * Ajax call for the Send Reminder action on Edit Booking page
		 *
		 * @since 4.10.0
		 */
		public static function bkap_send_reminder_action() {

			$booking_id = $_POST['booking_id']; // phpcs:ignore
			$booking    = new BKAP_Booking( $booking_id );
			$item_id    = $booking->get_item_id();
			$mailer     = WC()->mailer();
			$reminder   = $mailer->emails['BKAP_Email_Booking_Reminder'];

			$reminder->trigger( $item_id );

			$twilio_details = bkap_get_sms_settings(); // Getting SMS settings.
			if ( is_array( $twilio_details ) ) {
				Bkap_SMS_settings::bkap_send_automatic_sms_reminder( $booking, $twilio_details, $item_id );
			}
			wp_die();
		}

		/**
		 * Ajax call for saving the email draft on Manual Reminder page
		 *
		 * @since 4.10.0
		 */
		public static function bkap_save_reminder_message() {

			$message = $_POST['message']; // phpcs:ignore
			$subject = $_POST['subject']; // phpcs:ignore

			$reminder_message = 'reminder_message';
			$reminder_subject = 'reminder_subject';

			if ( isset( $_POST['bkap_vendor_id'] ) && '' !== $_POST['bkap_vendor_id'] ) {

				$vendor_id = (int) $_POST['bkap_vendor_id'];
				$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );

				if ( $is_vendor ) {
					$reminder_message = 'bkap_vendor_reminder_message_' . $vendor_id;
					$reminder_subject = 'bkap_vendor_reminder_subject_' . $vendor_id;
				}
			}

			if ( isset( $message ) && '' !== $message ) {
				update_option( $reminder_message, $message );
			}

			if ( isset( $subject ) && '' !== $subject ) {
				update_option( $reminder_subject, $subject );
			}
		}

		/**
		 * Function to update 'day' values to 'hour' values.
		 *
		 * @return int hours for reminder email setting.
		 * @since 5.8.1
		 */
		public static function bkap_update_reminder_email_day_to_hour() {

			$saved_settings  = json_decode( get_option( 'bkap_reminder_settings' ) );
			$number_of_hours = ( isset( $saved_settings->reminder_email_before_hours ) &&
			'' !== $saved_settings->reminder_email_before_hours ) ? $saved_settings->reminder_email_before_hours : 0;

			// Check for previous records for days and convert to hours.
			if ( isset( $saved_settings->reminder_email_before_days ) ) {

				// Sometimes, reminder_email_before_days may still exist even when reminder_email_before_hours has been set. In that case, ignore reminder_email_before_days and use reminder_email_before_hours instead.

				if ( ! isset( $saved_settings->reminder_email_before_hours ) && ( ( (int) $saved_settings->reminder_email_before_days ) > 0 ) ) {
					$number_of_hours                             = ( (int) $saved_settings->reminder_email_before_days ) * 24;
					$saved_settings->reminder_email_before_hours = $number_of_hours;

					// Update scheduled event from day to hourly.
					if ( wp_next_scheduled( 'bkap_auto_reminder_emails' ) ) {
						wp_clear_scheduled_hook( 'bkap_auto_reminder_emails' );
						wp_schedule_event( time(), 'hourly', 'bkap_auto_reminder_emails' );
					}
				}

				// Delete bkap_booking_reschedule_days and update record.
				unset( $saved_settings->reminder_email_before_days );
				update_option( 'bkap_reminder_settings', wp_json_encode( $saved_settings ) );
			}

			return $number_of_hours;
		}
	}
	new Bkap_Send_Reminder();
}
