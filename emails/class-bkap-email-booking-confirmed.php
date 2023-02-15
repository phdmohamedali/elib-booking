<?php
/**
 * Booking ConfirmationEmail.
 *
 * An email sent to the user when a booking is confirmed.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Emails
 * @class    BKAP_Email_Booking_Confirmed
 * @category Classes
 * @extends  WC_Email
 * @since    2.5
 */

/**
 * Class BKAP_Email_Booking_Confirmed.
 */
class BKAP_Email_Booking_Confirmed extends WC_Email {

	/**
	 * Booking Data.
	 *
	 * @var String
	 */
	public $booking_data;

	/**
	 * Default constructor
	 *
	 * @since 2.5
	 */
	function __construct() {

		$this->id          = 'bkap_booking_confirmed';
		$this->title       = __( 'Booking Confirmed', 'woocommerce-booking' );
		$this->description = __( 'Booking confirmed emails are sent when the status of a booking goes to confirmed.', 'woocommerce-booking' );
		$this->heading     = __( 'Booking Confirmed', 'woocommerce-booking' );
		$this->subject     = __( '[{blogname}] Your booking of "{product_title}" has been confirmed (Order {order_number}) - {order_date}', 'woocommerce-booking' );

		$this->template_html  = 'emails/customer-booking-confirmed.php';
		$this->template_plain = 'emails/plain/customer-booking-confirmed.php';

		// Triggers for this email.
		add_action( 'bkap_booking_confirmed_notification', array( $this, 'trigger' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->template_base = BKAP_BOOKINGS_TEMPLATE_PATH;
		$this->recipient     = $this->get_option( 'recipient', '' );
	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int $item_id Order Item ID.
	 * @since 2.5
	 */
	public function trigger( $item_id, $booking_id ) {

		$enabled = $this->is_enabled();

		if ( $item_id && $enabled ) {

			$key         = 0;
			$booking_ids = bkap_common::get_booking_id( $item_id );
			if ( is_array( $booking_ids ) ) {
				foreach ( $booking_ids as $k => $id ) {

					if ( $booking_id == $id ) {
						$key = $k;
					}
				}
			}

			$this->booking_data = bkap_common::get_bkap_booking( $item_id, $key );
			$this->object       = $this->booking_data;

			$key = array_search( '{product_title}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}

			$this->find[]    = '{product_title}';
			$this->replace[] = $this->booking_data->product_title;

			if ( $this->booking_data->order_id ) {
				$this->find[]    = '{order_date}';
				$this->replace[] = date_i18n( wc_date_format(), strtotime( $this->booking_data->order_date ) );

				$this->find[]    = '{order_number}';
				$this->replace[] = $this->booking_data->order_id;

				$this->recipient .= ',' . $this->booking_data->billing_email;

			} else {
				$this->find[]    = '{order_date}';
				$this->replace[] = date_i18n( wc_date_format(), strtotime( $this->booking_data->item_hidden_date ) );

				$this->find[]    = '{order_number}';
				$this->replace[] = __( 'N/A', 'woocommerce-booking' );

				if ( $this->booking_data->customer_id && ( $customer = get_user_by( 'id', $this->booking_data->customer_id ) ) ) {
					$this->recipient .= ',' . $customer->user_email;
				}
			}
		}

		if ( ! $this->get_recipient() ) {
			return;
		}

		$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

	/**
	 * Get content html.
	 *
	 * @access public
	 * @since 2.5
	 * @return string
	 */
	public function get_content_html() {
		ob_start();
		wc_get_template(
			$this->template_html,
			array(
				'booking'            => $this->booking_data,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => false,
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
	 * @since 2.5
	 * @return string
	 */
	public function get_content_plain() {
		ob_start();
		wc_get_template(
			$this->template_plain,
			array(
				'booking'            => $this->booking_data,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'sent_to_admin'      => false,
				'plain_text'         => true,
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
	 * @since 2.5
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'    => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-booking' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable this email notification', 'woocommerce-booking' ),
				'default' => 'yes',
			),
			'subject'    => array(
				'title'       => __( 'Subject', 'woocommerce-booking' ),
				'type'        => 'text',
				'description' => sprintf( __( 'This controls the email subject line. Leave blank to use the default subject: <code>%s</code>.', 'woocommerce-booking' ), $this->subject ),
				'placeholder' => '',
				'default'     => '',
			),
			'recipient'  => array(
				'title'       => __( 'Recipient', 'woocommerce-booking' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Enter recipients (comma separated) for this email.', 'woocommerce-booking' ), get_option( 'admin_email' ) ),
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
return new BKAP_Email_Booking_Confirmed();
