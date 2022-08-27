<?php
/**
 * New Customer Email Notification for unconfirmed booking.
 *
 * An email sent to the customer when a new booking is created and is not confirmed.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Emails
 * @class    BKAP_Email_New_Booking
 * @extends  WC_Email
 * @category Classes
 *
 * @since    5.2.2
 */

/**
 * Class BKAP_Email_Booking_Pending.
 */
class BKAP_Email_Booking_Pending extends WC_Email {

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

		$this->id          = 'bkap_customer_pending_booking';
		$this->title       = __( 'Booking Confirmation Pending', 'woocommerce-booking' );
		$this->description = __( 'Confirmation pending emails are sent when the booking availability is not confirmed yet.', 'woocommerce-booking' );
		$this->heading     = __( 'Booking Confirmation Pending', 'woocommerce-booking' );
		$this->subject     = __( '[{blogname}] Confirmation pending for {product_title} (Order {order_number}) - {order_date}', 'woocommerce-booking' );

		$this->template_html  = 'emails/customer-pending-confirmation.php';
		$this->template_plain = 'emails/plain/customer-pending-confirmation.php';

		// Triggers for this email.
		add_action( 'bkap_pending_booking_notification', array( $this, 'bkap_customer_pending_notification' ), 11, 1 );
		add_action( 'bkap_customer_new_booking_notification', array( $this, 'bkap_customer_unconfirmed_notification' ), 10, 2 );

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->template_base = BKAP_BOOKINGS_TEMPLATE_PATH;

	}

	/**
	 * Trigger the sending of this email.
	 *
	 * @param int $order_id The Order ID.
	 * @since 2.5
	 */
	public function bkap_customer_pending_notification( $order_id ) {

		$order          = new WC_order( $order_id );
		$customer_email = $order->get_billing_email(); 
		$items          = $order->get_items();
		foreach ( $items as $item_key => $item_value ) {
			do_action( 'bkap_customer_new_booking_notification', $item_key, $customer_email );
		}
	}

	/**
	 * Send an unconfirmed booking notification to a customer.
	 *
	 * @param int    $item_id The Order Item ID.
	 * @param string $customer_email The Customer email ID.
	 *
	 * @since 5.2.2
	 */
	public function bkap_customer_unconfirmed_notification( $item_id, $customer_email ) {

		$this->booking_data = bkap_common::get_bkap_booking( $item_id );
		$this->object       = $this->booking_data;
		if ( 'pending-confirmation' === $this->booking_data->item_booking_status ) {
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
			} else {

				$this->find[]    = '{order_date}';
				$this->replace[] = date_i18n( wc_date_format(), strtotime( $this->booking_data->item_hidden_date ) );
				$this->find[]    = '{order_number}';
				$this->replace[] = __( 'N/A', 'woocommerce-booking' );
			}
			$this->send( $customer_email, $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
		}

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
				'message'            => __( 'We have received your request for a booking. The details of the booking are as follows:', 'woocommerce-booking' ),
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

return new BKAP_Email_Booking_Pending();
