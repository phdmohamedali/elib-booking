<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Booking Endpoints
 *
 * @author   Tyche Softwares
 * @package  BKAP/Booking-Endpoints
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_Booking_Endpoints' ) ) :

	/**
	 * Booking & Appointment Plugin Booking Endpoints Core Class
	 *
	 * @class Bkap_Booking_Endpoints
	 */
	class Bkap_Booking_Endpoints {

		/**
		 * Default constructor
		 *
		 * @since 1.0
		 */
		public function __construct() {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}

		/**
		 * Register the routes for Booking posts.
		 *
		 * @since 1.0
		 *
		 * @hook rest_api_init
		 */
		public function register_routes() {

			// Get Bookings.
			$bookings_args = array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_bookings' ),
				'permission_callback' => array( $this, 'validate_request' ),
			);
			register_rest_route( 'wp/v2', 'bkap-bookings', $bookings_args );

			// Get Booking.
			$booking_args = array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking' ),
				'permission_callback' => array( $this, 'validate_request' ),
			);
			register_rest_route( 'wp/v2', 'bkap-bookings/(?P<id>\d+)', $booking_args );

			// Get Booking by order_id.
			$booking_order_args = array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_booking_by_orderid' ),
				'permission_callback' => array( $this, 'validate_request' ),
			);
			register_rest_route( 'wp/v2', 'bkap-bookings/order/(?P<id>\d+)', $booking_order_args );
		}

		/**
		 * Preparing to serve an API request for Booking posts.
		 *
		 * @since 1.0
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 *
		 * @hook rest_api_init
		 */
		public function get_bookings( WP_REST_Request $request ) {

			$date_regexp = '/^([0-9]{4})[\-]([0]?[1-9]|[1][0-2])[\-]([0]?[1-9]|[1|2][0-9]|[3][0|1])$/';
			$time_regexp = '/^([0|1][0-9]|[2][0|1|3|4])[:]([0-5][0-9]|[2|3|4|5|6][0])$/';

			$per_page    = $request->get_param( 'per_page' );
			$after       = $request->get_param( 'after' );
			$before      = $request->get_param( 'before' );
			$start_time  = $request->get_param( 'start_time' );
			$end_time    = $request->get_param( 'end_time' );
			$customer_id = $request->get_param( 'customer_id' );
			$resource_id = $request->get_param( 'resource_id' );
			$product_id  = $request->get_param( 'product_id' );
			$inclusive   = $request->get_param( 'inclusive' );

			if ( ! in_array( $inclusive, array('true', 'false' ) ) ) {
				$inclusive = false;
			}

			if ( ( ! empty( $after ) && ! preg_match( $date_regexp, $after ) ) || 
					( ! empty( $before ) && ! preg_match( $date_regexp, $before ) ) 
			) {
				return new WP_Error( 'invalid_dates', 'Please specify date in YYYY-MM-DD format!', array( 'status' => 200 ) );
			}

			if ( ! empty( $product_id ) && ! is_numeric( $product_id ) ) {
				return new WP_Error( 'invalid_product', 'Please specify valid numeric product id!', array( 'status' => 200 ) );
			}

			if ( ! empty( $resource_id ) && ! is_numeric( $resource_id ) ) {
				return new WP_Error( 'invalid_resource', 'Please specify valid numeric resource id!', array( 'status' => 200 ) );
			}

			if ( ! empty( $customer_id ) && ! is_numeric( $customer_id ) ) {
				return new WP_Error( 'invalid_customer', 'Please specify valid numeric customer id!', array( 'status' => 200 ) );
			}

			if ( empty( $start_time ) ) {
				$start_time = '000000';
			} else {
				$start_time = str_replace(':', '', $start_time ) . '00';
			}

			if ( empty( $end_time ) ) {
				$end_time = '000000';
			} else {
				$end_time = str_replace(':', '', $end_time ) . '00';
			}

			// Build a start date time for comparing against database table.
			if ( ! empty( $after ) ) {
				$bkap_start = str_replace('-', '', $after ) . $start_time;
			} else {
				$bkap_start = '';
			}

			// Build an end date time for comparing against database table.
			if ( ! empty( $before ) ) {
				$bkap_end = str_replace('-', '', $before ) . $end_time;
			} else {
				$bkap_end = '';
			}

			$query_args  = array(
				'post_type'   => 'bkap_booking',
				'post_status' => array( 'paid', 'pending-confirmation', 'confirmed' ),
			);

			$meta_query = array();

			if ( empty( $per_page ) ) {
				$per_page = 50;
			}

			// Build meta query.
			if ( isset( $customer_id ) ) {

				// If end user specified 0 as a customer id.
				if ( '0' == $customer_id ) {
					$meta_query[] = array(
						'key'   => '_bkap_customer_id',
						'value' => 0,
						'type'  => 'numeric',
					);
				} else if ( ! empty( $customer_id ) ) {
					$meta_query[] = array(
						'key'   => '_bkap_customer_id',
						'value' => $customer_id ,
						'type'  => 'numeric',
					);
				} 
			}

			if ( ! empty( $resource_id ) ) {
				$meta_query[] = array(
					'key'   => '_bkap_resource_id',
					'value' => $resource_id,
					'type'  => 'numeric',
				);
			}

			if ( ! empty( $product_id ) ) {
				$meta_query[] = array(
					'key'   => '_bkap_product_id',
					'value' => $product_id,
					'type'  => 'numeric',
				);
			}

			if ( ! empty( $bkap_start ) ) {
				$bkap_starts_meta =  array(
					'key'     => '_bkap_start',
					'value'   => $bkap_start,
					'compare' => '>',
				);
				if ( 'true' == $inclusive ) {
					$bkap_starts_meta[ 'compare' ] = '>=';
				}
				$meta_query[] = $bkap_starts_meta;
			}

			if ( ! empty( $bkap_end ) ) {

				$bkap_end_meta =  array(
					'key'          => '_bkap_start',
					'value'        => $bkap_end,
					'compare' => '<',
				);
				if ( 'true' == $inclusive ) {
					$bkap_end_meta[ 'compare' ] = '<=';
				}
				$meta_query[] = $bkap_end_meta;
			}

			$query_args['posts_per_page'] = $per_page;

			if ( ! empty( $meta_query ) ) {
				$query_args['meta_query'] = $meta_query;
				if ( 1 < count( $meta_query ) ) {
					$query_args['meta_query']['relation'] = 'AND';
				}
			}

			// Build Rest Response.
			$bkap_posts = get_posts( $query_args );

			foreach ( $bkap_posts as $post ) {
				$post->booking_post_meta = get_post_meta( $post->ID );
			}

			return $bkap_posts;
		}

		/**
		 * Preparing to serve an API request for Booking.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 *
		 * @hook rest_api_init
		 */
		public function get_booking( WP_REST_Request $request ) {
			$booking_id = $request['id'];
			$query_args = array(
				'p'           => $booking_id,
				'post_type'   => 'bkap_booking',
				'post_status' => array( 'paid', 'pending-confirmation', 'confirmed' ),
			);
			$bkap_posts = get_posts( $query_args );

			foreach ( $bkap_posts as $post ) {
				$post->booking_post_meta = get_post_meta( $post->ID );
			}

			return $bkap_posts;
		}

		/**
		 * Preparing to serve an API request for Booking by order id.
		 *
		 * @param WP_REST_Request $request Full data about the request.
		 * @return WP_Error|WP_REST_Request
		 *
		 * @hook rest_api_init
		 */
		public function get_booking_by_orderid( WP_REST_Request $request ) {
			$booking_order_id = $request['id'];

			$args = array(
				'post_type'   => 'bkap_booking',
				'post_status' => array( 'paid', 'pending-confirmation', 'confirmed' ),
				'meta_query'  => array(
					array(
						'key'   => '_bkap_parent_id',
						'type'  => 'numeric',
						'value' => $booking_order_id,
					),
				),
				'orderby'     => 'date',
				'order'       => 'DESC',
			);

			$bkap_posts = get_posts( $args );

			foreach ( $bkap_posts as $post ) {
				$post->booking_post_meta = get_post_meta( $post->ID );
			}

			return $bkap_posts;
		}

		/**
		 * Validate request for access key and secret.
		 *
		 * @return boolean|WP_Error
		 */
		public function validate_request() {
			$consumer_key    = '';
			$consumer_secret = '';

			// Get values.
			if ( ! empty( $_GET['consumer_key'] ) && ! empty( $_GET['consumer_secret'] ) ) { // WPCS: CSRF ok.
				$consumer_key    = $_GET['consumer_key']; // WPCS: CSRF ok, sanitization ok.
				$consumer_secret = $_GET['consumer_secret']; // WPCS: CSRF ok, sanitization ok.
			}

			// Stop if don't have any key.
			if ( ! $consumer_key || ! $consumer_secret ) {
				return false;
			}

			// Get user data.
			$user = $this->get_user_data_by_consumer_key( $consumer_key );
			if ( empty( $user ) ) {
				return false;
			}

			// Validate user secret.
			if ( ! hash_equals( $user->consumer_secret, $consumer_secret ) ) { // @codingStandardsIgnoreLine
				return new WP_Error(
					'bkap_rest_authentication_error',
					__( 'Consumer secret is invalid.', 'woocommerce-booking' ),
					array( 'status' => 401 )
				);
			}

			return true;
		}

		/**
		 * Return the user data for the given consumer_key.
		 *
		 * @param string $consumer_key Consumer key.
		 * @return array
		 */
		private function get_user_data_by_consumer_key( $consumer_key ) {
			global $wpdb;
			$consumer_key = wc_api_hash( sanitize_text_field( $consumer_key ) );
			$user         = $wpdb->get_row(
				$wpdb->prepare(
					"
				SELECT key_id, user_id, permissions, consumer_key, consumer_secret, nonces
				FROM {$wpdb->prefix}woocommerce_api_keys
				WHERE consumer_key = %s
			",
					$consumer_key
				)
			);
			return $user;
		}
	}

	$bkap_booking_endpoints = new Bkap_Booking_Endpoints();

endif;
