<?php
/**
 * Booking Reminder Email
 *
 * An email sent to the custom for booking reminder
 *
 * @author   Tyche Softwares
 * @package  BKAP/Emails
 * @class    BKAP_Email_Booking_Reminder
 * @extends  WC_Email
 * @category Classes
 *
 * @since    2.5
 */

/**
 * Class BKAP_Email_Booking_Reminder.
 */
class BKAP_Email_Booking_Reminder extends WC_Email {

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

		$this->id          = 'bkap_booking_reminder';
		$this->title       = __( 'Booking Reminder', 'woocommerce-booking' );
		$this->description = __( 'Booking Reminder Emails', 'woocommerce-booking' );
		$this->heading     = __( 'Booking Reminder', 'woocommerce-booking' );
		$this->subject     = __( '[{blogname}] You have a booking for "{product_title}"', 'woocommerce-booking' );

		$this->template_html  = 'emails/customer-booking-reminder.php';
		$this->template_plain = 'emails/plain/customer-booking-reminder.php';

		// Call parent constructor.
		parent::__construct();

		// Other settings.
		$this->template_base = BKAP_BOOKINGS_TEMPLATE_PATH;
	}

	/**
	 * Get content html.
	 *
	 * @access public
	 * @param int    $item_id Order Item ID.
	 * @param string $subject Subject.
	 * @param string $message Message.
	 *
	 * @since 2.5
	 * @return string
	 */
	public function trigger( $item_id, $subject = '', $message = '' ) {

		$enabled = $this->is_enabled();

		if ( $item_id && $enabled ) {
			$this->booking_data = bkap_common::get_bkap_booking( $item_id );
			$this->object       = $this->booking_data;
		}

		if ( $this->booking_data->product_id ) {
			$key = array_search( '{product_title}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{product_title}';
			$this->replace[] = $this->booking_data->product_title;
		}

		if ( $this->booking_data->order_id ) {
			$key = array_search( '{order_date}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}

			$this->find[]    = '{order_date}';
			$this->replace[] = date_i18n( wc_date_format(), strtotime( $this->booking_data->order_date ) );

			$key = array_search( '{order_number}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{order_number}';
			$this->replace[] = $this->booking_data->order_id;

			$this->recipient = $this->booking_data->billing_email;
		} else {

			$this->find[]    = '{order_date}';
			$this->replace[] = date_i18n( wc_date_format(), strtotime( $this->booking_data->item_hidden_date ) );

			$this->find[]    = '{order_number}';
			$this->replace[] = __( 'N/A', 'woocommerce-booking' );

			if ( $this->booking_data->customer_id && ( $customer = get_user_by( 'id', $this->booking_data->customer_id ) ) ) {
				$this->recipient = $customer->user_email;
			}
		}

		if ( $this->booking_data->item_booking_date ) {
			$key = array_search( '{start_date}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{start_date}';
			$this->replace[] = $this->booking_data->item_booking_date;
		}

		if ( $this->booking_data->item_checkout_date ) {
			$key = array_search( '{end_date}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{end_date}';
			$this->replace[] = $this->booking_data->item_checkout_date;
		}

		if ( $this->booking_data->item_booking_time ) {
			$key = array_search( '{booking_time}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{booking_time}';
			$this->replace[] = $this->booking_data->item_booking_time;
		}

		if ( $this->objbooking_dataect->resource_title ) {
			$key = array_search( '{booking_resource}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{booking_resource}';
			$this->replace[] = $this->booking_data->resource_title;
		}

		if ( $this->booking_data->zoom_meeting ) {
			$key = array_search( '{zoom_link}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{zoom_link}';
			$this->replace[] = $this->booking_data->zoom_meeting;
		}

		if ( $this->booking_data->customer_id ) {
			$key = array_search( '{customer_name}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$customer = get_user_by( 'id', $this->booking_data->customer_id );

			if ( $customer ) {
				$display_name = $customer->display_name;
				$first_name   = $customer->first_name;
				$last_name    = $customer->last_name;
			} else {
				$order        = wc_get_order( $this->booking_data->order_id );
				$first_name   = $order->get_billing_first_name();
				$last_name    = $order->get_billing_last_name();
				$display_name = $first_name . ' ' . $last_name;
			}
			$this->find[]    = '{customer_name}';
			$this->replace[] = $display_name;

			$key = array_search( '{customer_first_name}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{customer_first_name}';
			$this->replace[] = $first_name;

			$key = array_search( '{customer_last_name}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{customer_last_name}';
			$this->replace[] = $last_name;
		}

		if ( $this->booking_data->booking_id ) {
			$key = array_search( '{booking_id}', $this->find );
			if ( false !== $key ) {
				unset( $this->find[ $key ] );
				unset( $this->replace[ $key ] );
			}
			$this->find[]    = '{booking_id}';
			$this->replace[] = $this->booking_data->booking_id;
		}

		if ( ! $this->get_recipient() ) {
			return;
		}

		if ( $subject !== '' || $message !== '' ) {
			$this->heading = str_replace( $this->find, $this->replace, $subject );
			$this->subject = str_replace( $this->find, $this->replace, $subject );
			$this->message = str_replace( $this->find, $this->replace, $message );
		} else {
			$this->message = '';
			$this->subject = $this->get_subject();
		}

		$this->send( $this->get_recipient(), $this->subject, stripslashes( $this->get_content() ), $this->get_headers(), $this->get_attachments() );
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
				'message'            => $this->message,
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
				'message'            => $this->message,
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

return new BKAP_Email_Booking_Reminder();
