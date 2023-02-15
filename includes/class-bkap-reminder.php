<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Reminder Custom Post Type Data.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Reminder
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Reminder class.
 *
 * @class BKAP_Reminder
 * @since 4.6.0
 */
class BKAP_Reminder {

	private $reminder;
	private $id;
	private $prefix = 'bkap';
	private $type = 'bkap_reminder';

	/**
	 * Constructor
	 */
	public function __construct( $post ) {
		if ( is_numeric( $post ) ) {
			$this->reminder = get_post( $post );
			$this->id       = $post;
		} else {
			$this->reminder = $post;
			$this->id       = $post->ID;
		}
	}

	/**
	 * Return the ID
	 *
	 * @return int
	 * @since 4.6.0
	 */
	public function get_id() {
		return $this->reminder->ID;
	}

	/**
	 * Get the title of the resource
	 *
	 * @return string
	 * @since 4.6.0
	 */
	public function get_title() {
		return $this->reminder->post_title;
	}

	/**
	 * Get the title of the resource
	 *
	 * @return string
	 * @since 4.6.0
	 */
	public function get_status() {
		return $this->reminder->post_status;
	}

	/**
	 * Get Status Name.
	 *
	 * @return string Status Name.
	 * @since 5.14.0
	 */
	public function get_status_name() {

		$status            = str_replace( 'bkap-', '', $this->get_status() ); ;
		$reminder_statuses = array(
			'active'   => __( 'Active', 'woocommerce-booking' ),
			'inactive' => __( 'Inactive', 'woocommerce-booking' ),
			'trash'    => __( 'Trash', 'woocommerce-booking' ),
		);

		$status = isset( $reminder_statuses[ $status ] ) ? $reminder_statuses[ $status ] : $reminder_statuses['inactive'];

		return $status;
	}

	/**
	 * Updating Reminder Status.
	 *
	 * @return string Updating Reminder Status..
	 * @since 5.14.0
	 */
	public function update_status( $status ) {

		$id = wp_update_post(
			array(
				'ID'          => $this->id,
				'post_status' => $status,
			)
		);
	}

	/**
	 * Email Subject.
	 *
	 * @return string Email Subject.
	 * @since 5.14.0
	 */
	public function get_email_subject() {

		$email_subject = get_post_meta( $this->get_id(), $this->prefix . '_email_subject', true );

		return $email_subject;
	}

	/**
	 * Email Heading.
	 *
	 * @return string Email Heading.
	 * @since 5.14.0
	 */
	public function get_email_heading() {

		$email_subject = get_post_meta( $this->get_id(), $this->prefix . '_email_heading', true );

		return $email_subject;
	}

	/**
	 * Email Subject.
	 *
	 * @return string Email Subject.
	 * @since 5.14.0
	 */
	public function get_email_content() {

		$email_content = get_post_meta( $this->get_id(), $this->prefix . '_email_content', true );

		if ( '' === $email_content ) {
			$email_content = 'Hello {customer_first_name},

You have an upcoming booking. The details of your booking are shown below.

{booking_table}';
		}

		return $email_content;
	}

	/**
	 * Sending Delay.
	 *
	 * @return string Sending Delay.
	 * @since 5.14.0
	 */
	public function get_sending_delay() {

		$sending_delay = get_post_meta( $this->get_id(), $this->prefix . '_sending_delay', true );

		if ( '' === $sending_delay ) {
			$sending_delay = array(
				'delay_value' => 0,
				'delay_unit'  => 'hours', 
			);
		}

		return $sending_delay;
	}

	/**
	 * Trigger options.
	 *
	 * @return string Trigger options.
	 * @since 5.14.0
	 */
	public function get_trigger() {

		$trigger = get_post_meta( $this->get_id(), $this->prefix . '_trigger', true );

		if ( '' === $trigger ) {
			$trigger = 'before_booking_date';
		}

		return $trigger;
	}

	public function get_reminder_time_before_after_booking() {

		$sending_delay = $this->get_sending_delay();
		$sending_delay_text = $sending_delay['delay_value'] . ' ' . $sending_delay['delay_unit'];

		$trigger      = $this->get_trigger();
		$triggers     = array(
			'before_booking_date' => __( 'Before Booking Date', 'woocommerce-booking' ),
			'after_booking_date'  => __( 'After Booking Date', 'woocommerce-booking' ),
		);
		$trigger_text = ' - ' . $triggers[ $trigger ];

		$time_before_after_booking = $sending_delay_text . $trigger_text;

		return $time_before_after_booking;
	}

	/**
	 * Products.
	 *
	 * @return string Products.
	 * @since 5.14.0
	 */
	public function get_products() {

		$products = get_post_meta( $this->get_id(), $this->prefix . '_products', true );

		if (  '' === $products ) {
			$products = array();
		}

		return $products;
	}

	/**
	 * SMS Body.
	 *
	 * @return string SMS Body.
	 * @since 5.14.0
	 */
	public function get_sms_body() {

		$sms_body = get_post_meta( $this->get_id(), $this->prefix . '_sms_body', true );

		return $sms_body;
	}

	/**
	 * SMS Body.
	 *
	 * @return string SMS Body.
	 * @since 5.14.0
	 */
	public function get_enable_sms() {

		$enable_sms = get_post_meta( $this->get_id(), $this->prefix . '_enable_sms', true );

		return $enable_sms;
	}

	/**
	 * Return the title of resource
	 *
	 * @return string title of the resource
	 * @since 4.12.0
	 */

	public function get_resource_title() {

		return get_the_title( $this->get_id() );
	}

	/**
	 * Create Custom post for the Resource.
	 *
	 * @return integer ID of the resource created.
	 * @since 4.6.0
	 */
	public static function bkap_create_reminder( $data = array() ) {

		$subject = isset( $data['subject'] ) ? $data['subject'] : __( '[{blogname}] You have a booking for {product_title}', 'woocommerce-booking' );
		$heading = isset( $data['heading'] ) ? $data['heading'] : __( 'Booking Reminder', 'woocommerce-booking' );
		$content = isset( $data['content'] ) ? $data['content'] : 'Hello {customer_first_name},

You have an upcoming booking. The details of your booking are shown below.

{booking_table}';
		
		$sending_delay = isset( $data['sending_delay'] ) ? array( 'delay_value' => $data['sending_delay'], 'delay_unit' => 'hours' ) : array( 'delay_value' => 0, 'delay_unit' => 'hours' );
		$trigger       = 'before_booking_date';
		$products      = isset( $data['bkap_products'] ) ? $data['bkap_products'] : array( 'all' );
		$enable_sms    = isset( $data['enable_sms'] ) ? $data['enable_sms'] : '';
		$sms_body      = isset( $data['sms_body'] ) ? $data['sms_body'] : 'Hi {customer_first_name},

You have a booking of {product_title} on {start_date}. 

Your Order # : {order_number}
Order Date : {order_date}
Your booking id is: {booking_id}';
		$status        = isset( $data['status'] ) ? $data['status'] : 'bkap-inactive';
		$author        = $data['author'];
		$title         = isset( $data['title'] ) ?  ' - ' . $data['title'] : '';

		$parse_data = apply_filters(
			'bkap_reminder_adding_default_data',
			array(
				'bkap_email_subject' => $subject,
				'bkap_email_heading' => $heading,
				'bkap_email_content' => $content,
				'bkap_sending_delay' => $sending_delay,
				'bkap_delay_value'   => $sending_delay['delay_value'],
				'bkap_delay_unit'    => $sending_delay['delay_unit'],
				'bkap_trigger'       => $trigger,
				'bkap_products'      => $products,
				'bkap_enable_sms'    => $enable_sms,
				'bkap_sms_body'      => $sms_body,
			)
		);

		$id = wp_insert_post(
			array(
				'post_title'   => __( 'Booking Reminder', 'woocommerce-booking' ) . $title,
				'menu_order'   => 0,
				'post_content' => $content,
				'post_status'  => $status,
				'post_author'  => $author,
				'post_type'    => 'bkap_reminder',
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {

			foreach ( $parse_data as $key => $value ) {
				update_post_meta( $id, $key, $value );
			}

			return $id;
		}
	}
}
