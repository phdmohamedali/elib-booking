<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Zapier API that handles requests to the Bookings API.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api/Zapier
 * @category    Classes
 * @since       5.11.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Zapier_Bookings' ) ) {

	/**
	 * Zapier API endpoint.
	 *
	 * @since 5.11.0
	 */
	class BKAP_API_Zapier_Bookings extends BKAP_API_Bookings {

		/**
		 * Route base.
		 *
		 * @var string $base
		 */
		protected $base = 'bkap/zapier/bookings';

		/**
		 * Construct
		 *
		 * @since 5.11.0
		 * @param WC_API_Server $server Server.
		 */
		public function __construct( WC_API_Server $server ) {
			parent::__construct( $server );
		}

		/**
		 * Register routes.
		 *
		 * GET /bookings
		 *
		 * @since 5.11.0
		 * @param array $routes Routes.
		 * @return array
		 */
		public function register_routes( $routes ) {

			// POST /bookings.
			$routes[ $this->get_bookings_endpoint() ] = array(
				array( array( $this, 'get_bookings' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'create_booking' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET|PUT|DELETE /bookings/<id>.
			$routes[ $this->get_bookings_endpoint() . '/(?P<id>\d+)' ] = array(
				array( array( $this, 'get_booking' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'edit_booking' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'delete_booking' ), WC_API_Server::DELETABLE ),
			);

			return $routes;
		}

		/**
		 * Returns the endpoint/base.
		 *
		 * @since 5.11.0
		 * @return string
		 */
		public function get_bookings_endpoint() {
			return '/' . apply_filters( 'bkap_api_zapier_bookings_endpoint', $this->base );
		}

		/**
		 * Get all bookings.
		 *
		 * @since 5.11.0
		 * @param array $data Array of filters for the response data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_bookings( $data = array() ) {

			try {

				$filter = $data;

				// Zapier sends filter requests as $_GET parameters.
				if ( ! $this->is_data_set( 'limit', $filter ) ) {
					$var_limit = isset( $_GET['limit'] ) ? sanitize_text_field( wp_unslash( $_GET['limit'] ) ) : ''; // phpcs:ignore

					if ( '' !== $var_limit ) {
						$filter['limit'] = $var_limit;
					}
				}

				if ( ! $this->is_data_set( 'page', $filter ) ) {
					$var_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore

					if ( '' !== $var_limit ) {
						$filter['page'] = $var_page;
					}
				}

				// Check if request is for Sample Data.
				if ( ! $this->is_data_set( 'sample_data', $filter ) ) {
					$var_sample_data = isset( $_GET['sample_data'] ) ? sanitize_text_field( wp_unslash( $_GET['sample_data'] ) ) : ''; // phpcs:ignore

					if ( 'yes' === $var_sample_data ) {
						return array( BKAP_API_Zapier_Settings::bkap_api_zapier_get_booking( 0, array( 'sample_data' => true ) ) );
					}
				}

				$response = parent::get_bookings( $filter );

				if ( is_wp_error( $response ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $response );
				}

				if ( '' === $response || ! is_array( $response ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_booking_error', __( 'Error 1 occurred while trying to retrieve list of Bookings.', 'woocommerce-booking' ), 400 );
				}

				// For cases where there are no Bookings on the WooCommerce site, use sample data as booking return value.

				if ( is_array( $response ) && isset( $response['message'] ) && 'No Bookings' === $response['message'] ) {

					return array(
						BKAP_API_Zapier_Settings::bkap_api_zapier_get_booking(
							'',
							array(
								'sample_data' => true,
							)
						),
					);
				}

				if ( ! is_array( $response['bookings'] ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_booking_error', __( 'Error 2 occurred while trying to retrieve list of Bookings.', 'woocommerce-booking' ), 400 );
				}

				$bookings = array();

				// Add id property and other properties as Zapier strictly requires this.
				foreach ( $response['bookings'] as $booking ) {
					$bookings[] = BKAP_API_Zapier_Settings::bkap_api_zapier_get_booking( $booking['id'] );
				}

				return $bookings;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Get the Booking object for the given ID.
		 *
		 * @since 5.11.0
		 * @param int   $id Booking ID.
		 * @param array $fields Booking fields to return.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 * */
		public function get_booking( $id, $fields = array() ) {

			try {

				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
				$id = $this->validate_request( $id, $this->post_type, 'read' );

				if ( is_wp_error( $id ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $id );
				}

				return array( BKAP_API_Zapier_Settings::bkap_api_zapier_get_booking( $id ) );

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Deletes a Booking.
		 *
		 * @since 5.11.0
		 * @param int $id Booking ID.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 * */
		public function delete_booking( $id ) {

			try {

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_zapier_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_delete_booking_action_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API Delete Booking Action is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				$response = parent::delete_booking( $id );

				if ( is_wp_error( $response ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $response );
				}

				if ( '' === $response || ! is_array( $response ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_booking_error', __( 'Error occurred while trying to delete the  Booking.', 'woocommerce-booking' ), 400 );
				}

				BKAP_API_Zapier_Log::add_log( 'Delete Booking', "Booking #{$id} has been deleted via Zapier -> WooCommerce." );

				return $response;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Create Booking.
		 *
		 * @since 5.11.0
		 * @param array $data Booking Data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function create_booking( $data ) {

			try {

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_zapier_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_create_booking_action_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API Create Booking Action is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				$response = parent::create_booking( $data );

				if ( is_wp_error( $response ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $response );
				}

				if ( '' === $response || ! is_array( $response ) ) {
					BKAP_API_Zapier_Log::add_log( 'Create Booking', "New Booking #{$response['booking_id']} has been created via Zapier -> WooCommerce for Product ID: {$data['product_id']} ", $data );
				}

				// Discard response from Booking API and serve response that Zapier expects.
				$order_id   = $response['order_id'];
				$booking_id = $response['booking_id'];

				$response = BKAP_API_Zapier_Settings::bkap_api_zapier_get_booking( $booking_id );

				$order = wc_get_order( $order_id );

				// Add other properties to response data.
				$other_properties = array(
					'order_id'     => (int) $order_id,
					'order_number' => strval( $order->get_order_number() ),
					'order_status' => strval( $order->get_status() ),
					'currency'     => strval( $order->get_currency() ),
					'total'        => (float) wc_format_decimal( $order->get_total() ),
					'subtotal'     => (float) wc_format_decimal( $order->get_subtotal() ),
					'payment_url'  => strval( $order->get_checkout_payment_url() ),
				);

				$response = array_merge(
					$response,
					$other_properties
				);

				return $response;
			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Edit Booking.
		 *
		 * @since 5.11.0
		 * @param int   $id Booking ID.
		 * @param array $data Booking data of items to be changed.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function edit_booking( $id, $data ) {

			try {

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_zapier_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_update_booking_action_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API Update Booking Action is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				$response = parent::edit_booking( $id, $data );

				if ( is_wp_error( $response ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $response );
				}

				if ( '' === $response || ! is_array( $response ) ) {
					BKAP_API_Zapier_Log::add_log( 'Update Booking', "Booking #{$id} has been updated via Zapier -> WooCommerce", $data );
				}

				return $response;
			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}
	}
}
