<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for managing the emails sent from Booking & Appointment plugin
 *
 * @author  Tyche Softwares
 * @package BKAP/Emails
 * @category Classes
 */

class BKAP_Email_Manager {

	/**
	 * Constructor sets up actions
	 *
	 * @since 2.6.0
	 */

	public function __construct() {

		add_action( 'woocommerce_checkout_order_processed', array( &$this, 'init_confirmation_emails' ), 10, 2 );
		add_filter( 'woocommerce_email_classes', array( &$this, 'bkap_init_emails' ) );

		// Email Actions
		$email_actions = array(
			// New & Pending Confirmation
			'bkap_pending_booking',
			'bkap_admin_new_booking',

			// Confirmed
			'bkap_booking_confirmed',

			// Cancelled
			'bkap_booking_pending-confirmation_to_cancelled',

			// Events Imported from GCal
			'bkap_gcal_events_imported',

			// Rescheduled Event
			'bkap_booking_rescheduled_admin',

			'bkap_email_booking_reminder',

			// Customer booking pending. 
			'bkap_customer_booking_pending',

		);

		foreach ( $email_actions as $action ) {
			/*
			if ( version_compare( WC_VERSION, '2.3', '<' ) ) {
				add_action( $action, array( $GLOBALS['woocommerce'], 'send_transactional_email' ), 10, 10 );
			} else {*/
				add_action( $action, array( 'WC_Emails', 'send_transactional_email' ), 10, 10 );
			// }
		}

		// add_filter( 'woocommerce_email_attachments', array( $this, 'attach_ics_file' ), 10, 3 );

		add_filter( 'woocommerce_template_directory', array( $this, 'bkap_template_directory' ), 10, 2 );

	}

	/**
	 * Initialize the confirmation emails to be sent
	 *
	 * @param string|int $order_id Order ID
	 * @param string     $posted Posted
	 *
	 * @since 2.6.0
	 *
	 * @hook woocommerce_checkout_order_processed
	 * @hooked bkap_pending_booking_notification
	 */

	function init_confirmation_emails( $order_id, $posted ) {

		if ( isset( $order_id ) && 0 != $order_id ) {
			$order    = wc_get_order( $order_id );
			$requires = bkap_common::bkap_order_requires_confirmation( $order );

			if ( $requires ) {
				new WC_Emails();
				do_action( 'bkap_pending_booking_notification', $order_id );
			}
		}
	}

	/**
	 * Initialize the Booking Emails
	 *
	 * @param array $emails Emails Array containing all the email types
	 *
	 * @return array Array with the files included against each type
	 *
	 * @hook woocommerce_email_classes
	 *
	 * @since 2.6.0
	 */

	public function bkap_init_emails( $emails ) {

		if ( ! isset( $emails['BKAP_Email_New_Booking'] ) ) {
			$emails['BKAP_Email_New_Booking'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-new-booking.php';
		}

		if ( ! isset( $emails['BKAP_Email_Booking_Confirmed'] ) ) {
			$emails['BKAP_Email_Booking_Confirmed'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-booking-confirmed.php';
		}

		if ( ! isset( $emails['BKAP_Email_Booking_Cancelled'] ) ) {
			$emails['BKAP_Email_Booking_Cancelled'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-booking-cancelled.php';
		}

		if ( ! isset( $emails['BKAP_Email_Event_Imported'] ) ) {
			$emails['BKAP_Email_Event_Imported'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-imported-events.php';
		}

		if ( ! isset( $emails['BKAP_Email_Booking_Rescheduled_Admin'] ) ) {
			$emails['BKAP_Email_Booking_Rescheduled_Admin'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-booking-rescheduled-admin.php';
		}

		if ( ! isset( $emails['BKAP_Email_Booking_Reminder'] ) ) {
			$emails['BKAP_Email_Booking_Reminder'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-booking-reminder.php';
		}
		
		// Added to send email notification to customer for unconfirmed bookings.
		if ( ! isset( $emails['BKAP_Email_Booking_Pending'] ) ) {
			$emails['BKAP_Email_Booking_Pending'] = include_once BKAP_PLUGIN_PATH . '/emails/class-bkap-email-booking-pending.php';
		}

		return $emails;
	}

	/**
	 * Get the Email Templates Directory
	 *
	 * @param string $directory Current Directory Set
	 * @param string $template Current Template
	 *
	 * @return string Template folder
	 *
	 * @since 2.6.0
	 *
	 * @hook woocommerce_template_directory
	 */

	public function bkap_template_directory( $directory, $template ) {
		if ( false !== strpos( $template, '-booking' ) ) {
			return 'woocommerce-booking';
		}

		return $directory;
	}
}//end class
new BKAP_Email_Manager();

