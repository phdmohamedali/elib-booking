<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Adding Payment Gateway for Requires Confirmation Products
 *
 * @author      Tyche Softwares
 * @package     BKAP/Booking-Confirmation
 * @category    Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( class_exists( 'WC_Payment_Gateway' ) ) {

	/**
	 * Class for adding Payment Gateways
	 *
	 * @since 2.6.0
	 */
	class BKAP_Payment_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 *
		 * @since 2.6.0
		 */
		public function __construct() {

			$check_booking_availability = apply_filters( 'bkap_booking_gateway_method_title_text', __( 'Check Booking Availability', 'woocommerce-booking' ) );

			$request_confirmation = apply_filters( 'bkap_request_confirmation_text', __( 'Request Confirmation', 'woocommerce-booking' ) );

			$this->id                = 'bkap-booking-gateway';
			$this->icon              = '';
			$this->has_fields        = false;
			$this->method_title      = $check_booking_availability;
			$this->title             = $this->method_title;
			$this->order_button_text = $request_confirmation;

			// Actions
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		}

		/**
		 * Admin Options for the payment gateway added.
		 *
		 * @since 2.6.0
		 */

		public function admin_options() {
			$title = ( ! empty( $this->method_title ) ) ? $this->method_title : __( 'Settings', 'woocommerce-booking' );

			echo '<h3>' . $title . '</h3>';

			echo '<p>' . __( 'This is fictitious payment method used for bookings that require confirmation.', 'woocommerce-booking' ) . '</p>';
			echo '<p>' . __( 'This gateway requires no configuration.', 'woocommerce-booking' ) . '</p>';

			// Hides the save button
			echo '<style>p.submit input[type="submit"] { display: none }</style>';
		}

		/**
		 * Process the payment when the payment method is selected as Requires Confirmation.
		 *
		 * @param string|int $order_id Order ID
		 * @return string|NULL When successfully completed creating order
		 *
		 * @since 2.6.0
		 */

		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			// Add meta
			update_post_meta( $order_id, '_bkap_pending_confirmation', '1' );

			// Add custom order note.
			$order->add_order_note( __( 'This order is awaiting confirmation from the shop manager', 'woocommerce-booking' ) );

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}

		/**
		 * Display details on Thank You page
		 *
		 * @param string|int $order_id Order ID
		 *
		 * @since 2.6.0
		 */

		public function thankyou_page( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'completed' == $order->get_status() ) {
				echo '<p>' . __( 'Your booking has been confirmed. Thank you.', 'woocommerce-booking' ) . '</p>';
			} else {
				echo '<p>' . __( 'Your booking is awaiting confirmation. You will be notified by email as soon as we\'ve confirmed availability.', 'woocommerce-booking' ) . '</p>';
			}
		}
	}//end class
}
