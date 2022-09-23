<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for approving bookings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Booking-Confirmation
 * @category Classes
 */

if ( ! class_exists( 'bkap_approve_booking' ) ) {

	/**
	 * Class for Approving Bookings
	 *
	 * @since 2.5.0
	 */
	class bkap_approve_booking {

		/**
		 *
 @var string slug */
		private $slug = null;

		/**
		 *
 @var string title */
		private $title = null;

		/**
		 *
 @var string content */
		private $content = null;

		/**
		 *
 @var string author */
		private $author = null;

		/**
		 *
 @var string date */
		private $date = null;

		/**
		 *
 @var string type */
		private $type = null;

		/**
		 * Initialize the variables with the values passed to create the class
		 *
		 * @param array $args Data for approving booking
		 * @since 2.5.0
		 */
		public function __construct( $args ) {

			if ( ! isset( $args['slug'] ) ) {
				throw new Exception( 'No slug given for page' );
			}

			$this->slug    = $args['slug'];
			$this->title   = isset( $args['title'] ) ? $args['title'] : '';
			$this->content = isset( $args['content'] ) ? $args['content'] : '';
			$this->author  = isset( $args['author'] ) ? $args['author'] : 1;
			$this->date    = isset( $args['date'] ) ? $args['date'] : current_time( 'mysql' );
			$this->dategmt = isset( $args['date'] ) ? $args['date'] : current_time( 'mysql', 1 );
			$this->type    = isset( $args['type'] ) ? $args['type'] : 'page';

			add_action( 'booking_page_woocommerce_history_page', array( &$this, 'create_virtual_page' ) );
			add_action( 'booking_page_operator_bookings', array( &$this, 'create_virtual_page' ) );
		}

		/**
		 * Filter to create virtual page content for Tell a Friend page
		 *
		 * @hook booking_page_woocommerce_history_page
		 * @hook booking_page_operator_bookings
		 * @since 2.5.0
		 */
		public function create_virtual_page() {
			echo $this->content;
		}
	}
}
