<?php
/**
 * GCal Event Imported Email
 *
 * An email sent to the admin/tour operator when a new event is imported.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Emails
 * @class    BKAP_Email_Imported_Event
 * @extends  WC_Email
 * @category Classes
 *
 * @since    3.6
 */

/**
 * Class BKAP_Email_Imported_Event.
 */
class BKAP_Email_Imported_Event extends WC_Email {

	/**
	 * Booking Data.
	 *
	 * @var String
	 */
	public $booking_data;

	/**
	 * Default constructor
	 *
	 * @since 3.6
	 */
	function __construct() {

		$this->id          = 'bkap_event_import';
		$this->title       = __( 'New GCal Event Import', 'woocommerce-booking' );
		$this->description = __( 'New GCal Event Import. This email is received when an event is imported from the .ics/iCal feed.', 'woocommerce-booking' );
		$this->heading     = __( 'New GCal Event Imported', 'woocommerce-booking' );
		$this->subject     = __( '[{blogname}] New event imported from Google Calendar', 'woocommerce-booking' );

		$this->template_html  = 'emails/admin-gcal-import-event.php';
		$this->template_plain = 'emails/plain/admin-gcal-import-event.php';

		add_action( 'bkap_gcal_events_imported_notification', array( $this, 'trigger' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->template_base = BKAP_BOOKINGS_TEMPLATE_PATH;
		$this->recipient     = $this->get_option( 'recipient', get_option( 'admin_email' ) );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param object $option_name Event detail object.
	 * @param int    $user_id ID of the user.
	 * @since 3.6
	 */
	public function trigger( $option_name, $user_id = 0 ) {

		$enabled = $this->is_enabled();

		if ( isset( $option_name ) && '' != $option_name && $enabled ) {

			$bkap_date_formats = bkap_date_formats();

			$imported_event_details = json_decode( get_option( $option_name ) );

			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			$date_format_to_display = $global_settings->booking_date_format;
			$time_format_to_display = $global_settings->booking_time_format;

			if ( ! current_time( 'timestamp' ) ) {
				$tdif = 0;
			} else {
				$tdif = current_time( 'timestamp' ) - time();
			}

			// default the variables.
			$booking_date_to_display  = '';
			$checkout_date_to_display = '';
			$booking_from_time        = '';
			$booking_to_time          = '';

			$event_object = new stdClass();

			if ( $imported_event_details->end != '' && $imported_event_details->start != '' ) {
				$event_start = $imported_event_details->start + $tdif;
				$event_end   = $imported_event_details->end + $tdif;

				$booking_date_to_display  = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
				$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_end );

				if ( $event_end >= current_time( 'timestamp' ) && $event_start >= current_time( 'timestamp' ) ) {

					if ( $time_format_to_display == '12' ) {
						$booking_from_time = date( 'h:i A', $event_start );
						$booking_to_time   = date( 'h:i A', $event_end );
					} else {
						$booking_from_time = date( 'H:i', $event_start );
						$booking_to_time   = date( 'H:i', $event_end );
					}
				}
			} elseif ( $imported_event_details->start != '' && $imported_event_details->end == '' ) {

				$event_start             = $imported_event_details->start + $tdif;
				$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );

				if ( $event_start >= current_time( 'timestamp' ) ) {

					if ( $time_format_to_display == '12' ) {
						$booking_from_time = date( 'h:i A', $event_start );
					} else {
						$booking_from_time = date( 'H:i', $event_start );
					}
				}
			}

			$event_object->event_summary     = $imported_event_details->summary;
			$event_object->event_description = $imported_event_details->description;
			$event_object->booking_start     = $booking_date_to_display;

			if ( isset( $checkout_date_to_display ) && '' != $checkout_date_to_display ) {
				$event_object->booking_end = $checkout_date_to_display;
			}
			if ( isset( $booking_from_time ) && '' != $booking_from_time ) {
				$event_object->booking_time = $booking_from_time;
			}

			if ( isset( $booking_to_time ) && '' != $booking_to_time ) {
				$event_object->booking_time .= ' - ' . $booking_to_time;
			}

			$event_object->user_id = $user_id;
			$this->object          = $event_object;
			// if the user ID is set, then send the email to the given user ID.

			if ( isset( $user_id ) && 0 != $user_id ) {
				$user_info       = get_userdata( $user_id );
				$user_email      = $user_info->user_email;
				$this->recipient = $user_email;

			}

			if ( ! $this->get_recipient() ) {
				return;
			}

			$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );

		}
	}

	/**
	 * Get content html.
	 *
	 * @access public
	 * @since 3.6
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'event_details'      => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email'              => $this,
			),
			'woocommerce-booking/',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Get content plain.
	 *
	 * @access public
	 * @since 3.6
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'event_details'      => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'email'              => $this,
			),
			'woocommerce-booking/',
			$this->template_base
		);
		return ob_get_clean();
	}

	/**
	 * Initialise settings form fields.
	 *
	 * @since 3.6
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-booking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-booking' ),
				'default' => 'yes',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient', 'woocommerce-booking' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email. Defaults to %s', 'woocommerce-booking' ), get_option( 'admin_email' ) ),
				'default'     => get_option( 'admin_email' ),
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'woocommerce-booking' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce-booking' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'heading'    => array(
				'title'       => __( 'Email Heading', 'woocommerce-booking' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the main heading contained within the email notification. Leave blank to use the default heading: <code>%s</code>.', 'woocommerce-booking' ), $this->heading ),
				'placeholder' => '',
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Additional content', 'woocommerce-booking' ),
				'description' => __( 'Text to appear below the main email content.', 'woocommerce-booking' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'woocommerce-booking' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type' => array(
				'title'       => __( 'Email type', 'woocommerce-booking' ),
				'type'        => 'select',
				'description' => __( 'Choose which format of email to send.', 'woocommerce-booking' ),
				'default'     => 'html',
				'class'       => 'email_type',
				'options'     => array(
					'plain'     => __( 'Plain text', 'woocommerce-booking' ),
					'html'      => __( 'HTML', 'woocommerce-booking' ),
					'multipart' => __( 'Multipart', 'woocommerce-booking' ),
				),
			),
		);
	}

	/**
	 * Default content to show below main email content.
	 *
	 * @since 5.10.0
	 * @return string
	 */
	public function get_default_additional_content() {
		return '';
	}

}
return new BKAP_Email_Imported_Event();
