<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Booking API that handles requests to the /bookings endpoint.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.9.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Bookings' ) ) {

	/**
	 * Booking API endpoint.
	 *
	 * @since 5.9.1
	 */
	class BKAP_API_Bookings extends WC_API_Resource {

		/**
		 * Route base.
		 *
		 * @var string $base
		 */
		protected $base = 'bkap/bookings';

		/**
		 * Custom Post type for Bookings.
		 *
		 * @var string $post_type
		 */
		protected $post_type = 'bkap_booking';

		/**
		 * Default Booking Post Status.
		 *
		 * @var array $default_post_status
		 */
		protected $default_post_status = array(
			'draft',
			'cancelled',
			'confirmed',
			'paid',
			'pending-confirmation',
		);

		/**
		 * Construct
		 *
		 * @since 5.9.1
		 * @param WC_API_Server $server Server.
		 */
		public function __construct( WC_API_Server $server ) {
			parent::__construct( $server );
		}

		/**
		 * Register routes.
		 *
		 * GET|POST /bookings
		 * GET /bookings/count
		 * GET|PUT|DELETE /bookings/<id>
		 * GET /bookings/types
		 *
		 * @since 5.9.1
		 * @param array $routes Routes.
		 * @return array
		 */
		public function register_routes( $routes ) {

			// GET|POST /bookings.
			$routes[ $this->get_bookings_endpoint() ] = array(
				array( array( $this, 'get_bookings' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'create_booking' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET Update start date for products
			// Atif
			$routes[ $this->get_bookings_endpoint() . '/updatestartdate' ] = array(
				array( array( $this, 'update_start_date' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /bookings/count.
			$routes[ $this->get_bookings_endpoint() . '/count' ] = array(
				array( array( $this, 'get_bookings_count' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /bookings/availability.
			$routes[ $this->get_bookings_endpoint() . '/availability' ] = array(
				array( array( $this, 'get_booking_availability' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET|PUT|DELETE /bookings/<id>.
			$routes[ $this->get_bookings_endpoint() . '/(?P<id>\d+)' ] = array(
				array( array( $this, 'get_booking' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'edit_booking' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'delete_booking' ), WC_API_Server::DELETABLE ),
			);

			// GET /bookings/types.
			$routes[ $this->get_bookings_endpoint() . '/types' ] = array(
				array( array( $this, 'get_bookings_types' ), WC_API_Server::READABLE ),
			);

			// GET /bookings/price.
			$routes[ $this->get_bookings_endpoint() . '/price' ] = array(
				array( array( $this, 'get_booking_price' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			return $routes;
		}

		/**
		 * Returns the Booking endpoint/base.
		 *
		 * @since 5.9.1
		 * @return string
		 */
		public function get_bookings_endpoint() {
			return '/' . apply_filters( 'bkap_api_bookings_endpoint', $this->base );
		}

		/**
		 * Get all bookings.
		 *
		 * @since 5.9.1
		 * @param array $data Array of filter settings.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_bookings( $data = array() ) {

			try {
				if ( ! empty( $data ) ) {
					$filter = $data;
				} else {
					$filter = $_REQUEST;
				}

				if ( $this->check_if_exists( 'limit', $filter ) ) {
					if ( ! is_numeric( $filter['limit'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Limit parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'page', $filter ) ) {
					if ( ! is_numeric( $filter['page'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Page parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'product_id', $filter ) ) {
					$post = wc_get_product( $filter['product_id'] );
					if ( ! is_object( $post ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Product ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'product_id', $filter ) && $this->check_if_exists( 'variation_id', $filter ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Product ID and Variation ID cannot be set at the same time', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'variation_id', $filter ) ) {
					$post = wc_get_product( $filter['variation_id'] );
					if ( ! is_object( $post ) || 'product_variation' !== $post->post_type ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Variation ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'customer_id', $filter ) ) {
					if ( false === get_user_by( 'id', $filter['customer_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Customer ID is invalid', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'resource_id', $filter ) ) {
					if ( ! is_numeric( $filter['resource_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Resource ID must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'person_ids', $filter ) ) {
					if ( ! is_array( $filter['person_ids'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Person IDs must be an array', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'order_id', $filter ) ) {
					$order_id = absint( $filter['order_id'] );
					$order    = wc_get_order( $order_id );

					// Ensure Order exists and is valid.
					if ( ! $order ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Order can be found for the Order ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'status', $filter ) ) {

					if ( ! is_array( $filter['status'] ) ) {
						$filter['status'] = (array) $filter['status'];
					}

					if ( array_diff( $filter['status'], $this->default_post_status ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Booking Status provided is invalid. Use -> draft, cancelled, confirmed, paid, pending-confirmation', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'start_date', $filter ) ) {
					// Check Start Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) ) {
					// Check End Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the End Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'price', $filter ) ) {
					if ( ! is_numeric( $filter['price'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Price parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'quantity', $filter ) ) {
					if ( ! is_numeric( $filter['quantity'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Quantity parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				// Set default return data in case the booking record is empty.
				$return_data = array( 'message' => 'No Bookings' );

				$query_bookings = $this->query_bookings( $filter );

				if ( 0 !== $query_bookings->post_count ) {

					$bookings = array();

					foreach ( $query_bookings->posts as $booking_id ) {

						// Conform to WooCommerce standard and check if the current user has got permission to read the Booking instance.

						if ( ! $this->check_permission( $booking_id, 'read' ) ) {
							continue;
						}

						$bookings[] = current( $this->get_booking( $booking_id, ( isset( $filter['fields'] ) ? $filter['fields'] : array() ) ) );
					}

					$this->server->add_pagination_headers( $query_bookings );

					$return_data = array(
						'bookings' => $bookings,
						'count'    => count( $bookings ),
					);
				}

				return $return_data;

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Function to get booking objects.
		 *
		 * @since 5.9.1
		 * @param array $args Arguments for filtering query.
		 * @return WP_Query
		 */
		public function query_bookings( $args ) {

			// Default arguments.
			$default_args = array(
				'fields'         => 'ids',
				'post_status'    => $this->default_post_status,
				'post_type'      => $this->post_type,
				'orderby'        => 'date',
				'posts_per_page' => -1,
			);

			$filter_args = array();

			// Filter: Page count.
			if ( $this->check_if_exists( 'limit', $args ) ) {
				$filter_args['posts_per_page'] = (int) $args['limit'];
			}

			// Filter: Page offset.
			if ( $this->is_data_set( 'page', $args ) ) {
				$page                  = absint( $args['page'] );
				$filter_args['offset'] = $page * $filter_args['posts_per_page'];
			}

			// Filter: Product.
			if ( $this->check_if_exists( 'product_id', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_product_id',
						'value' => (int) $args['product_id'],
						'type'  => 'numeric',
					),
				);
			}

			// Filter: Variation.
			if ( $this->check_if_exists( 'variation_id', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_variation_id',
						'value' => $args['variation_id'],
						'type'  => 'numeric',
					),
				);
			}

			// Filter: Customer ID.
			if ( $this->check_if_exists( 'customer_id', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_customer_id',
						'value' => $args['customer_id'],
						'type'  => 'numeric',
					),
				);
			}

			// Filter: Resource.
			if ( $this->check_if_exists( 'resource_id', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_resource_id',
						'value' => $args['resource_id'],
						'type'  => 'numeric',
					),
				);
			}

			// Filter: Person.
			if ( $this->check_if_exists( 'person_ids', $args ) ) {

				foreach ( $args['person_ids'] as $key => $value ) {
					if ( (int) $value['person_id'] > 0 ) {
						$filter_args['meta_query'][] = array(
							'relation' => 'AND',
							array(
								'key'   => '_bkap_person_ids',
								'value' => $value['person_id'],
								'type'  => 'numeric',
							),
						);
					}
				}
			}

			// Filter: Order.
			if ( $this->check_if_exists( 'order_id', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_parent_id',
						'value' => (int) $args['order_id'],
						'type'  => 'numeric',
					),
				);
			}

			// Filter: Start Date.
			if ( $this->check_if_exists( 'start_date', $args ) ) {

				$start_date = gmdate( 'Ymd', strtotime( $args['start_date'] ) ) . '000000';

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'     => '_bkap_start',
						'value'   => $start_date,
						'compare' => '>=',
					),
				);
			}

			if ( $this->check_if_exists( 'end_date', $args ) ) {

				$end_date = gmdate( 'Ymd', strtotime( $args['end_date'] ) ) . '000000';

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'     => '_bkap_end',
						'value'   => $end_date,
						'compare' => '<=',
					),
				);
			}

			if ( $this->check_if_exists( 'price', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'     => '_bkap_cost',
						'value'   => floatval( $args['price'] ),
						'compare' => '>',
					),
				);
			}

			if ( $this->check_if_exists( 'quantity', $args ) ) {

				$filter_args['meta_query'][] = array(
					'relation' => 'AND',
					array(
						'key'   => '_bkap_qty',
						'value' => (int) $args['quantity'],
					),
				);
			}

			// Filter: Booking Status.
			if ( $this->check_if_exists( 'status', $args ) ) {
				$filter_args['post_status'] = $args['status'];
			}

			$wp_args = wp_parse_args( $filter_args, $default_args );

			// Run $wp_args through a filter for exteral changes.
			$wp_args = apply_filters( 'bkap_api_booking_query_bookings_args', $wp_args );

			return new WP_Query( $wp_args );
		}

		/**
		 * Get the Booking object for the given ID.
		 *
		 * @since 5.9.1
		 * @param int   $id Booking ID.
		 * @param array $fields Booking fields to return.
		 * @return array
		 */
		public function get_booking( $id, $fields = array() ) {

			// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
			$id = $this->validate_request( $id, $this->post_type, 'read' );

			if ( is_wp_error( $id ) ) {
				return $id;
			}

			$booking      = new BKAP_Booking( $id );
			$booking_data = array(
				'booking_id'     => $booking->get_id(),
				'order_id'       => $booking->get_order_id(),
				'order_item_id'  => $booking->get_item_id(),
				'booking_status' => $booking->get_status(),
				'customer_id'    => $booking->get_customer_id(),
				'customer'       => $booking->get_customer(),
				'product_id'     => $booking->get_product_id(),
			);

			// Check if other properties exist and add them to array.
			$booking_data = $this->add_other_booking_properties( $booking, $booking_data );

			// Remove other meta_keys and return only the ones specified in $fields variable.
			if ( is_array( $fields ) && count( $fields ) > 0 ) {

				foreach ( $booking_data as $key => $data ) {
					if ( ! in_array( $key, $fields, true ) ) {
						unset( $booking_data[ $key ] );
					}
				}
			}

			// TODO: Add important/required information from $booking class to $booking_data.

			// Run $booking through filter hook to incorporate external changes.
			$booking_data = apply_filters( 'bkap_api_booking_booking_response', $booking_data, $booking );

			return array( 'booking' => $booking_data );
		}

		/**
		 * Delete Booking.
		 *
		 * @since 5.9.1
		 * @param int $id Booking ID.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function delete_booking( $id ) {

			try {

				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to delete.
				$id = $this->validate_request( $id, $this->post_type, 'delete' );

				if ( is_wp_error( $id ) ) {
					return $id;
				}

				// Check that Booking exists as a post in WordPress: We do this in case Booking has already been deleted and request is repeated.
				if ( false === get_post_status( $id ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_post_not_found', __( 'Booking cannot be found or does not exist', 'woocommerce-booking' ), 400 );
				}

				// Retrieve Order ID.
				$booking  = new BKAP_Booking( $id );
				$order_id = $booking->get_order_id();

				bkap_cancel_order::bkap_delete_booking( $id );

				// Remove Booking Post.
				$result = wp_delete_post( $id, true );

				if ( ! $result ) {
					return array( 'message' => __( 'Booking has been deleted but the Booking Post may have not been deleted due to some errors', 'woocommerce-booking' ) );
				}

				// Remove Order Post, but we check if the order has other Bookings associated with it, and in that case, we do not delete Order Post.
				$all_bookings = bkap_common::get_booking_ids_from_order_id( $order_id );
				if ( count( $all_bookings ) === 1 ) {
					$result = wp_delete_post( $order_id, true );

					if ( ! $result ) {
						return array( 'message' => __( 'Booking has been deleted but the Parent Order for the Booking may have not been deleted due to some errors', 'woocommerce-booking' ) );
					}
				}

				return array(
					'message' => sprintf(
						/* translators: %s: Booking ID */
						__( 'Booking #%s has been deleted', 'woocommerce-booking' ),
						$id
					),
				);
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Checks if variable is available or if element exists in array.
		 *
		 * @since 5.9.1
		 * @param string $element Variable to check whether it exists.
		 * @param array  $data Array where Variable is checked if it exists.
		 * @return bool
		 */
		public function check_if_exists( $element, $data = '' ) {

			if ( '' !== $data && is_array( $data ) ) {
				$element = isset( $data[ $element ] );
			} else {
				$element = isset( $element );
			}

			return $element && '' !== $element && '0' !== $element;
		}

		/**
		 * Checks if variable is available and sets it in an array if it is.
		 *
		 * @since 5.9.1
		 * @param string $key Index of array where variable is to be set.
		 * @param string $element Variable to check whether it exists.
		 * @param array  $data Array where element is to be added if it exists.
		 * @return array
		 */
		public function check_if_exists_and_set( $key, $element, $data ) {

			if ( $this->check_if_exists( $element ) && ! isset( $data[ $key ] ) ) {
				$data[ $key ] = $element;
			}

			return $data;
		}

		/**
		 * Get the total number of Bookings.
		 *
		 * @since 5.9.1
		 * @return array|WP_Error
		 */
		public function get_bookings_count() {

			try {
				$query_bookings = $this->query_bookings( array() );
				return array( 'count' => (int) $query_bookings->found_posts );

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Get the Booking Price.
		 *
		 * @since 5.20.0
		 * @return array|WP_Error
		 */
		public function get_booking_price( $data = array() ) {

			try {
				$filter = $data;

				if ( $this->check_if_exists( 'limit', $filter ) ) {
					if ( ! is_numeric( $filter['limit'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Limit parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'product_id', $filter ) ) {
					$post = wc_get_product( $filter['product_id'] );
					if ( ! is_object( $post ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Product ID provided', 'woocommerce-booking' ), 400 );
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_required_parameter', __( 'Please provide a Product ID to continue.', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'product_id', $filter ) && $this->check_if_exists( 'variation_id', $filter ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Product ID and Variation ID cannot be set at the same time', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'variation_id', $filter ) ) {
					$post = wc_get_product( $filter['variation_id'] );
					if ( ! is_object( $post ) || 'product_variation' !== $post->post_type ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Variation ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'resource_id', $filter ) ) {
					if ( ! is_numeric( $filter['resource_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Resource ID must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'person_ids', $filter ) ) {
					if ( ! is_array( $filter['person_ids'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Person IDs must be an array', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'start_date', $filter ) ) {
					// Check Start Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) ) {
					// Check End Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the End Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) || ( $this->check_if_exists( 'start_date', $filter ) && $this->check_if_exists( 'end_date', $filter ) ) ) {
					if ( is_array( $filter['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'Start Date cannot be in an array. Please provide a single date for ranges to apply.', 'woocommerce-booking' ), 400 );
					} elseif ( is_array( $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'End Date cannot be in an array. Please provide a single date. Please provide a single date for ranges to apply.', 'woocommerce-booking' ), 400 );
					} elseif ( strtotime( $filter['start_date'] ) > strtotime( $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'Start Date must not be greater than End Date. Please provide a valid date.', 'woocommerce-booking' ), 400 );
					}
				}

				$price_data = array();

				if ( $this->check_if_exists( 'start_date', $filter ) ) {

					$product_id       = $filter['product_id'];
					$_product         = wc_get_product( $product_id );
					$booking_type     = get_post_meta( $product_id, '_bkap_booking_type', true );
					$booking_settings = bkap_setting( $product_id );
					$variation_id     = $this->check_if_exists( 'variation_id', $filter ) ? $filter['variation_id'] : 0;
					$resource_id      = $this->check_if_exists( 'resource_id', $filter ) ? $filter['resource_id'] : 0;
					$person_data      = $this->check_if_exists( 'person_ids', $filter ) ? $filter['person_ids'] : array();

					$booking_date                = date( 'j-n-Y', strtotime( $filter['start_date'] ) );
					$bkap_post_data['bkap_date'] = $booking_date;
					$bkap_post_data['id']        = $product_id;
					$quantity                    = $this->check_if_exists( 'quantity', $filter ) ? $filter['quantity'] : 1;
					$bkap_post_data['quantity']  = $quantity;

					switch ( $booking_type ) {
						case 'only_day':
						case 'multidates':
							break;
						case 'multiple_days':
							if ( $this->check_if_exists( 'end_date', $filter ) ) {

								$bkap_post_data['checkin_date']       = $booking_date;
								$bkap_post_data['current_date']       = date( 'j-n-Y', strtotime( $filter['end_date'] ) );
								$bkap_post_data['diff_days']          = 1;
								$bkap_post_data['post_id']            = $product_id;
								$bkap_post_data['variation_id']       = $variation_id;
								$bkap_post_data['gf_options']         = 0;
								$bkap_post_data['product_type']       = $_product->get_type();
								$bkap_post_data['attribute_selected'] = '';
								$bkap_post_data['resource_id']        = $resource_id;
								$bkap_post_data['person_ids']         = $person_data;

								bkap_booking_process::bkap_get_per_night_price( $bkap_post_data );
							}
							break;
						case 'date_time':
						case 'multidates_fixedtime':
							if ( $this->check_if_exists( 'start_time', $filter ) ) {
								$time_slot = $filter['start_time'];
								if ( $this->check_if_exists( 'end_time', $filter ) ) {
									$time_slot .= ' - ' . $filter['end_time'];
								}
								$bkap_post_data['timeslot_value'] = $time_slot;
								$bkap_post_data['date_time_type'] = 'on';
							}
							break;
						case 'duration_time':
							if ( $this->check_if_exists( 'start_time', $filter ) ) {
								$time_slot = $filter['start_time'];
								$bkap_post_data['timeslot_value'] = $time_slot;
								$bkap_post_data['date_time_type'] = 'duration_time';
								$bkap_post_data['bkap_duration']  = $this->check_if_exists( 'duration', $filter ) ? $filter['duration'] : 1;
								$bkap_post_data['bkap_page']      = 'product';
							}
							break;
						default:
							throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'Something went wrong. Please check data.', 'woocommerce-booking' ), 400 );
							break;
					}

					if ( 'multiple_days' != $booking_type ) {
						$price_data = bkap_timeslot_price::timeslot_display_updated_price(
							$product_id,
							$booking_settings,
							$_product,
							$booking_date,
							$variation_id,
							0,
							$resource_id,
							$person_data,
							$bkap_post_data
						);
					}
				} else {
					return array( 'price' => $_product->get_price() );
				}

				return $price_data;
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Get the list of Booking types.
		 *
		 * @since 5.9.1
		 * @param string $return_condition Return only the neede parameter for the booking type.
		 * @return array|WP_Error
		 */
		public static function get_bookings_types( $return_condition = '' ) {

			try {
				$booking_types  = bkap_get_booking_types();
				$_booking_types = array();
				$_booking_keys  = array();

				// Clean data.
				foreach ( $booking_types as $key => $type ) {
					$_booking_types[] = array(
						'key'   => $type['key'],
						'label' => $type['label'],
					);

					$_booking_keys[] = $type['key'];
				}

				$return_data = $_booking_types;

				if ( 'keys' === $return_condition ) {
					$return_data = $_booking_keys;
				}

				return $return_data;
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Checks the permission for the user for a request.
		 *
		 * @since 5.9.1
		 * @param int    $post_id Can be Booking ID, Order ID etc.
		 * @param string $context the type of permission to check.
		 * @return bool  true if the current user has the permissions to perform the context on the post.
		 */
		public function check_permission( $post_id, $context ) {

			$post = get_post( $post_id );

			$post_type = get_post_type_object( $post->post_type );

			if ( 'read' === $context ) {
				return current_user_can( $post_type->cap->read_private_posts, $post->ID );
			} elseif ( 'edit' === $context ) {
				return current_user_can( $post_type->cap->edit_post, $post->ID );
			} elseif ( 'delete' === $context ) {
				return current_user_can( $post_type->cap->delete_post, $post->ID );
			} else {
				return false;
			}
		}

		/**
		 * Checks that the data has been set in the request.
		 *
		 * @since 5.9.1
		 * @param string $key  The key int array where data is to be checked if it exists in the request.
		 * @param array  $data The request array containing list of request components.
		 * @return bool  true if the data in the request is available.
		 */
		public function is_data_set( $key, $data ) {
			return ( isset( $data[ $key ] ) && '' !== $data[ $key ] );
		}

		/**
		 * Checks for other properties and adds to the response object if those properties have been set.
		 *
		 * @since 5.9.1
		 * @param object $booking Booking Object.
		 * @param array  $booking_data Booking array that will be returned as response.
		 * @return array
		 */
		public function add_other_booking_properties( $booking, $booking_data ) {
			$booking_data = $this->check_if_exists_and_set( 'start_date', $booking->get_start(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'end_date', $booking->get_end(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'start_time', $booking->get_start_time(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'end_time', $booking->get_end_time(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'start_date_formatted', $booking->get_start_date(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'end_date_formatted', $booking->get_end_date(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'resource_id', $booking->get_resource(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'persons', $booking->get_persons(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'fixed_block', $booking->get_fixed_block(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'cost', $booking->get_cost(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'quantity', $booking->get_quantity(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'variation_id', $booking->get_variation_id(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'resource', $booking->get_resource_title(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'duration', $booking->get_selected_duration(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'duration_time', $booking->get_selected_duration_time(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'timezone', $booking->get_timezone_name(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'timezone_offset', $booking->get_timezone_offset(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'zoom_meeting_url', $booking->get_zoom_meeting_link(), $booking_data );
			$booking_data = $this->check_if_exists_and_set( 'zoom_meeting_date', $booking->get_zoom_meeting_data(), $booking_data );
			return $booking_data;
		}

		/**
		 * Create Booking.
		 *
		 * @since 5.9.1
		 * @param array $data Booking Data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function create_booking( $data ) {

			try {

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_missing_booking_data', __( 'Booking data has not been provided to create a new Booking', 'woocommerce-booking' ), 400 );
				}

				// Check if user is permitted to create Booking. We check if user can create orders, and if user can, then definently the user can be allowed to create Booking.
				if ( ! current_user_can( 'publish_shop_orders' ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_user_cannot_create_booking', __( 'You do not have permission to create this Booking', 'woocommerce-booking' ), 401 );
				}

				$data = apply_filters( 'bkap_api_booking_create_booking_data', $data, $this );

				$booking_data = array();

				// Check for variation_id. If it exists in request, then do not bother checking for product.
				if ( $this->is_data_set( 'variation_id', $data ) || $this->is_data_set( 'product_id', $data ) ) {

					$post_id    = $this->is_data_set( 'variation_id', $data ) ? $data['variation_id'] : $data['product_id'];
					$post_label = $this->is_data_set( 'variation_id', $data ) ? 'Variation' : 'Product';

					$post = wc_get_product( $post_id );
					if ( ! is_object( $post ) ) {
						/* translators: %s: product type label: variation or product */
						throw new WC_API_Exception( 'bkap_api_booking_user_invalid_product', sprintf( __( '%1$s is invalid for the %2$s ID that has been provided', 'woocommerce-booking' ), ucwords( $post_label ), ucwords( $post_label ) ), 400 );
					}

					$booking_data['product_id'] = $post_id;

					if ( 'product_variation' === $post->post_type ) {
						$booking_data['product_id'] = $post->get_parent_id();
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_invalid_product_id', __( 'Product ID is required to create a Booking', 'woocommerce-booking' ), 400 );
				}

				$product_id = $booking_data['product_id'];

				// Quantity check.
				if ( $this->is_data_set( 'quantity', $data ) ) {
					$booking_data['quantity'] = (int) $data['quantity'];
				} else {
					$booking_data['quantity'] = 1;
				}

				// Booking Price is required.
				if ( $this->is_data_set( 'price', $data ) ) {

					$price                 = floatval( $data['price'] );
					$booking_data['price'] = $price;

					if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
						$product_price              = wc_get_price_excluding_tax(
							wc_get_product( $booking_data['product_id'] ),
							array( 'price' => $price )
						);
						$booking_data['price']      = $product_price;
						$booking_data['price_incl'] = $price;
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_empty_booking_price', __( 'Please provide the Price for the Booking', 'woocommerce-booking' ), 400 );
				}

				// Check if Order is sent. If it is sent, then attach booking to sent order or create a new order. Either ways, we check that Order ID is valid.
				if ( $this->is_data_set( 'order_id', $data ) ) {

					$order_id = absint( $data['order_id'] );
					$order    = wc_get_order( $order_id );

					// Ensure Order exists and is valid.
					if ( ! $order ) {
						throw new WC_API_Exception( 'bkap_api_booking_user_invalid_order', __( 'The provided Order ID is invalid', 'woocommerce-booking' ), 400 );
					}

					$booking_data['order_id'] = $order_id;
				}

				$booking_type                                = get_post_meta( $product_id, '_bkap_booking_type', true );
				$booking_type_is_multiple_dates              = ( 'multidates' === $booking_type || 'multidates_fixedtime' === $booking_type );
				$is_booking_resource_selection_multiple_type = 'multiple' === BKAP_Product_Resource::get_resource_selection_type( $product_id );

				if ( $booking_type_is_multiple_dates ) {
					// Add fake data for start_date and end_date in order to pass validation checks. Don't worry, we'll remove them later :).
					$data['start_date'] = gmdate( 'Y-m-d' );
					$data['end_date']   = gmdate( 'Y-m-d' );
				}

				// Booking Start Date is required for all Booking Types.
				if ( $this->is_data_set( 'start_date', $data ) ) {

					// Check Start Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $data['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_start_date', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_empty_booking_start_date', __( 'Please provide the Start Date', 'woocommerce-booking' ), 400 );
				}

				$booking_data['start'] = strtotime( $data['start_date'] );

				// End Date is required for all Booking Types.
				if ( $this->is_data_set( 'end_date', $data ) ) {

					// Check End Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $data['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_end_date', __( 'Please provide the End Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_empty_booking_end_date', __( 'Please provide the End Date', 'woocommerce-booking' ), 400 );
				}

				$booking_data['end'] = strtotime( $data['end_date'] );

				// Start Time is required for both date_time Booking Type and duration_time Booking Type.
				if ( 'date_time' === $booking_type || 'duration_time' === $booking_type ) {

					// Booking Start Time.
					if ( $this->is_data_set( 'start_time', $data ) ) {

						// Check Start Time is in correct format.
						if ( false === DateTime::createFromFormat( 'H:i', $data['start_time'] ) ) {
							throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_start_time', __( 'Please provide the Start Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
						}
					} else {
						throw new WC_API_Exception( 'bkap_api_booking_empty_booking_start_time', __( 'Please provide the Start Time', 'woocommerce-booking' ), 400 );
					}

					$start_date_time       = $data['start_date'] . ' ' . $data['start_time'];
					$booking_data['start'] = strtotime( $start_date_time );
				}

				// End Time is required only for date_time Booking Type.
				if ( 'date_time' === $booking_type ) {

					// Booking End Time.
					if ( $this->is_data_set( 'end_time', $data ) ) {

						// Check Start Time is in correct format.
						if ( false === DateTime::createFromFormat( 'H:i', $data['end_time'] ) ) {
							throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_end_time', __( 'Please provide the End Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
						}
					} else {
						throw new WC_API_Exception( 'bkap_api_booking_empty_booking_end_time', __( 'Please provide the End Time', 'woocommerce-booking' ), 400 );
					}

					$end_date_time       = $data['end_date'] . ' ' . $data['end_time'];
					$booking_data['end'] = strtotime( $end_date_time );
				}

				// Get End Time for duration_time Booking Type.
				if ( 'duration_time' === $booking_type ) {

					if ( $this->is_data_set( 'duration', $data ) ) {

						$duration_setting            = get_post_meta( $product_id, '_bkap_duration_settings', true );
						$hour                        = ( (int) $data['duration'] ) * ( (int) $duration_setting['duration'] );
						$booking_data['end']         = bkap_common::bkap_add_hour_to_date( $data['start_date'], $data['start_time'], $hour, $product_id, $duration_setting['duration_type'] );
						$booking_details['duration'] = $hour . '-' . $duration_setting['duration_type'];
					} else {
						throw new WC_API_Exception( 'bkap_api_booking_empty_booking_duration', __( 'Please provide the Duration for the Booking', 'woocommerce-booking' ), 400 );
					}
				}

				// Resources.
				if ( $this->is_data_set( 'resource_id', $data ) ) {

					if ( $is_booking_resource_selection_multiple_type && ! is_array( $data['resource_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_resource_data_format', __( 'Please provide an array of Resource ID(s)', 'woocommerce-booking' ), 400 );
					}

					if ( ! $is_booking_resource_selection_multiple_type && is_array( $data['resource_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_resource_data_format', __( 'Please provide the Resource ID as a single item and not as an array', 'woocommerce-booking' ), 400 );
					}

					// Check that Resource ID or IDs provided are valid.
					$resource_id            = is_array( $data['resource_id'] ) ? $data['resource_id'] : array( $data['resource_id'] );
					$resource_ids_not_found = array();

					foreach ( $resource_id as $id ) {
						if ( false === get_post_status( $id ) ) {
							$resource_ids_not_found[] = $id;
						}
					}

					if ( count( $resource_ids_not_found ) > 0 ) {
						throw new WC_API_Exception(
							'bkap_api_booking_invalid_resource_id',
							sprint_f(
								/* translators: %s: Resource IDs. */
								__( 'The following Resource ID(s) provided are invalid: %s ', 'woocommerce-booking' ),
								implode( ', ', $resource_ids_not_found )
							),
							400
						);
					}

					$booking_data['bkap_resource_id'] = $data['resource_id'];
				}

				if ( $this->is_data_set( 'customer_id', $data ) ) {
					$booking_data['customer_id'] = $data['customer_id'];
				} else {
					$booking_data['customer_id'] = '';
				}

				// Persons.
				if ( $this->is_data_set( 'person_ids', $data ) ) {

					if ( ! is_array( $data['person_ids'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_person_data_format', __( 'Please provide an array of Person ID(s)', 'woocommerce-booking' ), 400 );
					}

					// Check that Resource ID or IDs provided are valid.
					$person_id            = is_array( $data['person_ids'] ) ? $data['person_ids'] : array( $data['person_ids'] );
					$person_ids_not_found = array();

					$persons = array();
					foreach ( $person_id as $key => $val ) {
						if ( ! isset( $data['person_ids'][$key]['person_id'] ) ) {
							throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'person_id is missing in person_ids array', 'woocommerce-booking' ), 400 );
						}
						if ( ! isset( $data['person_ids'][$key]['person_val'] ) ) {
							throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'person_val is missing in person_ids array', 'woocommerce-booking' ), 400 );
						}
						if ( false === get_post_status( $val['person_id'] ) ) {
							$person_ids_not_found[] = $val['person_id'];
						} else {
							$persons[$val['person_id']] = (int) $val['person_val'];
						}
					}

					if ( count( $person_ids_not_found ) > 0 ) {
						throw new WC_API_Exception(
							'bkap_api_booking_invalid_person_id',
							sprint_f(
								/* translators: %s: Person IDs. */
								__( 'The following Person ID(s) provided are invalid: %s ', 'woocommerce-booking' ),
								implode( ', ', $person_ids_not_found )
							),
							400
						);
					}

					$booking_data['persons'] = $persons;
				}

				if ( $booking_type_is_multiple_dates ) {

					$selection_type = get_post_meta( $product_id, '_bkap_multidates_type', true );
					if ( 'fixed' !== $selection_type && 'range' !== $selection_type ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_selection_type', __( 'Could not retrieve a valid Selecion Type for the provided product.', 'woocommerce-booking' ), 400 );
					}

					unset( $data['start_date'] );
					unset( $data['end_date'] );

					$booking_data['start']          = '';
					$booking_data['end']            = '';
					$booking_data['quantity']       = 1;
					$booking_data['has_multidates'] = true;
					$multiple_dates_has_time        = ( 'multidates_fixedtime' === $booking_type );
					$multidates_booking             = array();

					// We will be expecting an array of dates.
					if ( $this->is_data_set( 'dates', $data ) ) {
						if ( ! is_array( $data['dates'] ) ) {
							throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_dates', __( 'Please provide the Multiple Booking Dates in an array.', 'woocommerce-booking' ), 400 );
						}
					} else {
						throw new WC_API_Exception( 'bkap_api_booking_empty_booking_dates', __( 'Please provide the Booking Dates.', 'woocommerce-booking' ), 400 );
					}

					$booking_dates   = $data['dates'];
					$number_of_dates = count( $booking_dates );
					$min_dates       = 0;
					$max_dates       = 0;

					if ( 'fixed' === $selection_type ) {
						$min_dates = (int) get_post_meta( $product_id, '_bkap_multidates_fixed_number', true );
						$max_dates = $min_dates;
					}

					if ( 'range' === $selection_type ) {
						$min_dates = (int) get_post_meta( $product_id, '_bkap_multidates_range_min', true );
						$max_dates = (int) get_post_meta( $product_id, '_bkap_multidates_range_max', true );
					}

					if ( $number_of_dates < $min_dates ) {
						throw new WC_API_Exception(
							'bkap_api_booking_invalid_date_count',
							sprintf(
							/* translators: %d Minimum Dates */
								__( 'Booking Dates Invalid. A minimum of %d must be provided.', 'woocommerce-booking' ),
								$min_dates
							),
							400
						);
					}

					if ( $number_of_dates > $max_dates ) {
						throw new WC_API_Exception(
							'bkap_api_booking_invalid_date_count',
							sprintf(
							/* translators: %d Maximum Dates */
								__( 'Booking Dates Invalid. A maximum of %d must be provided.', 'woocommerce-booking' ),
								$max_dates
							),
							400
						);
					}

					// We loop through each date and check that they are in the correct format.
					foreach ( $booking_dates as $_data ) {

						if ( $multiple_dates_has_time ) {

							// $_data must be an array.
							if ( ! is_array( $_data ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_dates', __( 'Please provide the Booking date and timeslots in an array.', 'woocommerce-booking' ), 400 );
							}

							// We expect an array of date and timeslots.
							if ( ! $this->is_data_set( 'date', $_data ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_missing_date_parameter', __( 'Date Parameter must be set for Multiple Dates Product having Timeslots.', 'woocommerce-booking' ), 400 );
							}

							if ( false === DateTime::createFromFormat( 'Y-m-d', $_data['date'] ) ) {
								throw new WC_API_Exception(
									'bkap_api_booking_invalid_booking_date',
									sprintf(
									/* translators: %s Date */
										__( 'Invalid Date: %s. Please provide this Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ),
										$_data['date']
									),
									400
								);
							}

							if ( ! $this->is_data_set( 'start_time', $_data ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_missing_time_parameter', __( 'Start Time Parameter must be set for Multiple Dates Product having Timeslots.', 'woocommerce-booking' ), 400 );
							}

							// Check Start Time is in correct format.
							if ( false === DateTime::createFromFormat( 'H:i', $_data['start_time'] ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_start_time', __( 'Please provide the Start Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
							}

							if ( ! $this->is_data_set( 'end_time', $_data ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_missing_time_parameter', __( 'End Time Parameter must be set for Multiple Dates Product having Timeslots.', 'woocommerce-booking' ), 400 );
							}

							// Check End Time is in correct format.
							if ( false === DateTime::createFromFormat( 'H:i', $_data['end_time'] ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_end_time', __( 'Please provide the End Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
							}

							$multidates_booking[] = array(
								'date'                 => gmdate( 'd/m/y', strtotime( $_data['date'] ) ),
								'hidden_date'          => gmdate( 'j-n-Y', strtotime( $_data['date'] ) ),
								'hidden_date_checkout' => '',
								'time_slot'            => gmdate( 'h:i A', strtotime( $_data['start_time'] ) ) . ' - ' . gmdate( 'h:i A', strtotime( $_data['end_time'] ) ),
								'price_charged'        => $booking_data['price'],
							);
						}

						if ( ! $multiple_dates_has_time ) {

							// $_data must NOT be an array.
							if ( is_array( $_data ) ) {
								throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_dates', __( 'Please provide the Booking date as a single value.', 'woocommerce-booking' ), 400 );
							}

							if ( false === DateTime::createFromFormat( 'Y-m-d', $_data ) ) {
								throw new WC_API_Exception(
									'bkap_api_booking_invalid_booking_date',
									sprintf(
									/* translators: %s Date */
										__( 'Invalid Date: %s. Please provide this Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ),
										$_data
									),
									400
								);
							}

							$multidates_booking[] = array(
								'date'                 => gmdate( 'd/m/y', strtotime( $_data ) ),
								'hidden_date'          => gmdate( 'j-n-Y', strtotime( $_data ) ),
								'hidden_date_checkout' => '',
								'price_charged'        => $booking_data['price'],
							);
						}
					}

					$booking_data['multidates_booking'] = $multidates_booking;
				}

				if ( $this->is_data_set( 'fixed_block', $data ) ) {
					$booking_data['fixed_block'] = $data['fixed_block'];
				}

				$booking_settings = bkap_setting( $product_id );
				$_product         = wc_get_product( $product_id );
				$variation_id     = $this->check_if_exists( 'variation_id', $data ) ? $data['variation_id'] : 0;
				$person_data      = $this->check_if_exists( 'person_ids', $data ) ? $data['person_ids'] : array();

				$total_price_calculated = bkap_timeslot_price::timeslot_display_updated_price(
					$product_id,
					$booking_settings,
					$_product,
					$booking_data['start'],
					$variation_id,
					0,
					$data['resource_id'],
					$person_data,
					$booking_data,
					true
				);
				$booking_data['price']  = ! empty( $total_price_calculated ) ? $total_price_calculated : $booking_data['price'];

				// TODO: Check for required parameters for the different types of booking.

				if ( $this->is_data_set( 'order_id', $booking_data ) ) {
					$status = import_bookings::bkap_create_booking( $booking_data );
				} else {
					$status = import_bookings::bkap_create_order( $booking_data );
				}

				$is_booking_created_is_new_order = ( isset( $status['new_order'] ) && '' !== $status['new_order'] && isset( $status['order_id'] ) && '' !== $status['order_id'] );

				$is_booking_created_existing_order = ( isset( $status['item_added'] ) && '' !== $status['item_added'] && isset( $status['order_id'] ) && '' !== $status['order_id'] );

				if ( $is_booking_created_is_new_order || $is_booking_created_existing_order ) {

					$order_id = $status['order_id'];
					$order    = wc_get_order( $order_id );

					// Fetch all Bookings associated with Order ID and retrieve the last element as newly inserted item.
					// TODO: Find a better way to retrieve the newly created booking besides assuming the last item in the array is the newest.

					$all_bookings = bkap_common::get_booking_ids_from_order_id( $order_id );

					$new_booking_index = count( $all_bookings ) - 1;

					if ( $new_booking_index === -1 ) {
						throw new WC_API_Exception( 'bkap_api_booking_full_booking_error', __( 'Max limit are reached for booking.', 'woocommerce-booking' ), 400 );
					}

					$created_booking_id = $all_bookings[ $new_booking_index ];

					$booking = new BKAP_Booking( $created_booking_id );

					// When new order has been created, status is set to paid for booking and processing for order. We need to change this to unpaid.

					if ( $is_booking_created_is_new_order ) {
						$order->update_status( 'pending' );
						$booking->update_status( 'pending-confirmation' );
					}

					// TODO: What happens in the case when booking has been added to already existing order, what happens to the booking and order status? Do we change?.

					// Re-load order and booking objects.
					$order   = wc_get_order( $order_id );
					$booking = new BKAP_Booking( $created_booking_id );

					// Default Return Data properties.
					$return_data = array(
						'created_booking_type' => ( $is_booking_created_is_new_order ? 'created_booking_in_new_order' : ( $is_booking_created_existing_order ? 'created_booking_in_existing_order' : 'unknown' ) ),
						'booking_id'           => (int) $created_booking_id,
						'order_id'             => $order_id,
						'order_key'            => $order->get_order_key(),
						'order_number'         => $order->get_order_number(),
						'date_created'         => $this->server->format_datetime( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0, false, false ), // UTC time.
						'date_updated'         => $this->server->format_datetime( $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0, false, false ), // UTC time.
						'order_status'         => $order->get_status(),
						'booking_status'       => $booking->get_status(),
						'order_status'         => $order->get_status(),
						'customer_id'          => (int) $booking->get_customer_id(),
						'product_id'           => (int) $booking->get_product_id(),
						'start_date'           => $booking->get_start(),
						'end_date'             => $booking->get_end(),
						'start_date_formatted' => $booking->get_start_date(),
						'end_date_formatted'   => $booking->get_end_date(),
						'currency'             => $order->get_currency(),
						'total'                => wc_format_decimal( $order->get_total() ),
						'subtotal'             => wc_format_decimal( $order->get_subtotal() ),
						'order_url'            => $order->get_view_order_url(),
						'payment_url'          => $order->get_checkout_payment_url(),
					);

					$return_data = $this->add_other_booking_properties( $booking, $return_data );

					// Run $return_data through filter hook to incorporate external changes and return.
					return apply_filters( 'bkap_api_booking_create_booking_return_data', array_filter( $return_data ), $this );
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_create_booking_error', __( 'Error occured while trying to create booking', 'woocommerce-booking' ), 400 );
				}
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * ATIF Update startdate
		 */
		public function update_start_date( $data = array() ) {
			$products_long_rent =  self::get_products_from_category_by_ID('rent-area-7-days-and-more');
			//return $products_long_rent;	
			foreach ( $products_long_rent as $id ) {
				self::bkap_update_metadata_next_available_date($id,7);
			}
			//return $products_long_rent;
			$products_events = self::get_products_from_category_by_ID('events');
			foreach ( $products_events as $id ) {
				self::bkap_update_metadata_next_available_date($id, 3);
			 }
			 return 'done';
		}
		

		/**
		 * Edit Booking.
		 *
		 * @since 5.9.1
		 * @param int   $id Booking ID.
		 * @param array $data Booking relateddata of items to be changed.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function edit_booking( $id, $data ) {

			try {

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_missing_booking_data', __( 'Booking data has not been provided to edit Booking', 'woocommerce-booking' ), 400 );
				}

				// Check if user is permitted to edit booking posts.
				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_user_cannot_edit_booking', __( 'You do not have permission to edit this Booking', 'woocommerce-booking' ), 401 );
				}

				$data['booking_id'] = $id;

				$data = apply_filters( 'bkap_api_booking_edit_booking_data', $data, $this );

				$booking_data = array();

				if ( $this->is_data_set( 'booking_id', $data ) ) {

					// Ensure Booking ID is valid and is of type booking.
					$post = get_post( $data['booking_id'] );

					if ( $this->post_type !== $post->post_type ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_id', __( 'Booking ID provided is not of type Booking', 'woocommerce-booking' ), 400 );
					}

					$booking_data['booking_id'] = absint( $data['booking_id'] );
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_empty_booking_id', __( 'Please provide a Booking ID', 'woocommerce-booking' ), 400 );
				}

				// Get details of the existing Booking.
				$booking    = new BKAP_Booking( $booking_data['booking_id'] );
				$product_id = $booking->get_product_id();

				// Booking Start Date is required for all Booking Types.
				if ( $this->is_data_set( 'start_date', $data ) ) {

					// Check Start Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $data['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_start_date', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
					$booking_data['start_date'] = gmdate( 'd-n-Y', strtotime( $data['start_date'] ) );
				}

				if ( $this->is_data_set( 'start_time', $data ) ) {

					// Check Start Time is in correct format.
					if ( false === DateTime::createFromFormat( 'H:i', $data['start_time'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_start_time', __( 'Please provide the Start Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
					}
					$booking_data['start_time'] = $data['start_time'];
				}

				if ( $this->is_data_set( 'end_time', $data ) ) {

					// Check Start Time is in correct format.
					if ( false === DateTime::createFromFormat( 'H:i', $data['end_time'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_end_time', __( 'Please provide the End Time in the H:i format. Ex: 23:15', 'woocommerce-booking' ), 400 );
					}
					$booking_data['end_time'] = $data['end_time'];
				}

				if ( $this->is_data_set( 'start_time', $booking_data ) && $this->is_data_set( 'end_time', $booking_data ) ) {
					$booking_data['time_slot'] = $booking_data['start_time'] . ' - ' . $booking_data['end_time'];
				}

				if ( $this->is_data_set( 'quantity', $data ) ) {
					$booking_data['quantity'] = (int) $data['quantity'];
				}

				if ( $this->is_data_set( 'status', $data ) ) {
					$allowed_statuses = bkap_common::get_bkap_booking_statuses();

					if ( array_key_exists( $data['status'], $allowed_statuses ) ) {
						$booking_data['status'] = $data['status'];
					} else {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_booking_status', __( 'Booking Status provided is invalid', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->is_data_set( 'duration', $data ) ) {

					$duration_setting              = get_post_meta( $product_id, '_bkap_duration_settings', true );
					$hour                          = ( (int) $data['duration'] ) * ( (int) $duration_setting['duration'] );
					$booking_data['end']           = bkap_common::bkap_add_hour_to_date( $data['start_date'], $data['start_time'], $hour, $product_id, $duration_setting['duration_type'] );
					$booking_data['duration_time'] = $hour . '-' . $duration_setting['duration_type'];
					$booking_data['duration']      = $data['duration'];
				}

				if ( $this->is_data_set( 'price', $data ) ) {

					$price                 = floatval( $data['price'] );
					$booking_data['price'] = $price;

					if ( 'yes' === get_option( 'woocommerce_prices_include_tax' ) ) {
						$product_price              = wc_get_price_excluding_tax(
							wc_get_product( $booking_data['product_id'] ),
							array( 'price' => $price )
						);
						$booking_data['price']      = $product_price;
						$booking_data['price_incl'] = $price;
					}
				}

				// Create wp_once variable.
				$booking_data['nonce'] = wp_create_nonce( 'bkap_details_meta_box' );

				$post = (array) get_post( $booking_data['booking_id'] );

				// Set default values for some required parameters.
				if ( ! $this->is_data_set( 'start_date', $booking_data ) ) {
					$booking_data['start_date'] = gmdate( 'd-n-Y', strtotime( strval( $booking->get_start() ) ) );
				}

				if ( ! $this->is_data_set( 'start_time', $booking_data ) || ! $this->is_data_set( 'end_time', $booking_data ) ) {
					$booking_data['time_slot'] = $booking->get_time();
				}

				if ( ! $this->is_data_set( 'duration', $booking_data ) ) {
					$booking_data['duration'] = $booking->get_selected_duration();
				}

				if ( ! $this->is_data_set( 'duration_time', $booking_data ) ) {
					$booking_data['duration_time'] = $booking->get_selected_duration_time();
				}

				if ( ! $this->is_data_set( 'quantity', $booking_data ) ) {
					$booking_data['quantity'] = $booking->get_quantity();
				}

				if ( ! $this->is_data_set( 'status', $booking_data ) ) {
					$booking_data['status'] = $booking->get_status();
				}

				if ( ! $this->is_data_set( 'price', $booking_data ) ) {
					$booking_data['price'] = floatval( $booking->get_cost() ) * (int) $booking->get_quantity();
				}

				// Simulate post request so that we can use the bkap_meta_box_save_booking_details function on Bkap_Edit_booking_post class to update booking.
				$send_data = array(
					'ID'                          => $booking_data['booking_id'],
					'post_ID'                     => $booking_data['booking_id'],
					'post_type'                   => $this->post_type,
					'wapbk_hidden_date'           => $booking_data['start_date'],
					'wapbk_hidden_date_checkout'  => $booking_data['start_date'],
					'bkap_details_meta_box_nonce' => $booking_data['nonce'],
					'bkap_product_id'             => $product_id,
					'time_slot'                   => $booking_data['time_slot'],
					'duration_time_slot'          => $booking_data['duration_time'],
					'bkap_duration_field'         => $booking_data['duration'],
					'bkap_qty'                    => $booking_data['quantity'],
					'_bkap_status'                => $booking_data['status'],
					'bkap_price_charged'          => $booking_data['price'],
				);

				$bkap_edit_booking_post = new Bkap_Edit_Booking_Post();
				$bkap_edit_booking_post->bkap_meta_box_save_booking_details( $post, $send_data );

				$return_data = $booking_data;

				// TODO: Tweak bkap_meta_box_save_booking_details() function to return error messages to know the status of saved bookings - on success or failure.

				// Remove nonce data from return response.
				unset( $return_data['nonce'] );

				// Return start_date to the format they were earlier - this is done so as not to confuse the user making the request.
				if ( $this->is_data_set( 'start_date', $return_data ) ) {
					$return_data['start_date'] = gmdate( 'Y-m-d', strtotime( $return_data['start_date'] ) );
				}

				$return_data = $this->add_other_booking_properties( $booking, $return_data );

				return apply_filters( 'bkap_api_booking_edit_booking_return_data', array_filter( $return_data ) );

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Get availability for bookings.
		 *
		 * @since 5.10.1
		 * @param array $data Array of filter settings.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_booking_availability( $data = array() ) {

			try {
				$filter = $data;

				if ( $this->check_if_exists( 'limit', $filter ) ) {
					if ( ! is_numeric( $filter['limit'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Limit parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'product_id', $filter ) ) {
					$post = wc_get_product( $filter['product_id'] );
					if ( ! is_object( $post ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Product ID provided', 'woocommerce-booking' ), 400 );
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_booking_required_parameter', __( 'Please provide a Product ID to continue.', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'product_id', $filter ) && $this->check_if_exists( 'variation_id', $filter ) ) {
					throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Product ID and Variation ID cannot be set at the same time', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'variation_id', $filter ) ) {
					$post = wc_get_product( $filter['variation_id'] );
					if ( ! is_object( $post ) || 'product_variation' !== $post->post_type ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'No valid Product has been found for the Variation ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'resource_id', $filter ) ) {
					if ( ! is_numeric( $filter['resource_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Resource ID must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'person_ids', $filter ) ) {
					if ( ! is_array( $filter['person_ids'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Person IDs must be an array', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'start_date', $filter ) ) {
					// Check Start Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) ) {
					// Check End Date is in correct format.
					if ( false === DateTime::createFromFormat( 'Y-m-d', $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_invalid_parameter', __( 'Please provide the End Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) || ( $this->check_if_exists( 'start_date', $filter ) && $this->check_if_exists( 'end_date', $filter ) ) ) {
					if ( is_array( $filter['start_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'Start Date cannot be in an array. Please provide a single date for ranges to apply.', 'woocommerce-booking' ), 400 );
					} elseif ( is_array( $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'End Date cannot be in an array. Please provide a single date. Please provide a single date for ranges to apply.', 'woocommerce-booking' ), 400 );
					} elseif ( strtotime( $filter['start_date'] ) > strtotime( $filter['end_date'] ) ) {
						throw new WC_API_Exception( 'bkap_api_booking_date_error', __( 'Start Date must not be greater than End Date. Please provide a valid date.', 'woocommerce-booking' ), 400 );
					}
				}

				$return_data  = array();
				$product_id   = $filter['product_id'];
				$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );
				$variation_id = $this->check_if_exists( 'variation_id', $filter ) ? $filter['variation_id'] : 0;
				$resource_id  = $this->check_if_exists( 'resource_id', $filter ) ? $filter['resource_id'] : 0;

				// Array of dates to check for Booking Availability.
				$dates = array();

				if ( $this->check_if_exists( 'start_date', $filter ) && $this->check_if_exists( 'end_date', $filter ) ) {

					// Get dates between start_date and end_date.
					$interval = new DateInterval( 'P1D' );

					$date_start = new DateTime( $filter['start_date'] );

					$date_end = new DateTime( $filter['end_date'] );
					$date_end->add( $interval );

					$period = new DatePeriod( $date_start, $interval, $date_end );

					foreach ( $period as $date ) {
						$dates[] = $date->format( 'Y-m-d' );
					}
				} elseif ( $this->check_if_exists( 'start_date', $filter ) ) {
					if ( ! is_array( $filter['start_date'] ) ) {
						$dates[] = $filter['start_date'];
					} elseif ( is_array( $filter['start_date'] ) && count( $filter['start_date'] ) > 0 ) {
						$dates[] = $filter['start_date'];
					}
				} else {
					$dates[] = gmdate( 'Y-m-d' );
				}

				if ( is_array( $dates ) && count( $dates ) > 0 ) {

					foreach ( $dates as $date ) {
						$_return          = array();
						$get_availability = bkap_booking_process::bkap_get_date_availability(
							$product_id,
							$variation_id,
							$date,
							$date,
							'',
							'',
							'',
							false,
							$resource_id,
							''
						);

						if ( 'FALSE' !== $get_availability && 'TIME-FALSE' !== $get_availability ) {
							$return = array(
								'date'         => $date,
								'availability' => $get_availability,
							);

							if ( in_array( $booking_type, array( 'date_time', 'multidates_fixedtime' ) ) ) {

								// Get timeslots.
								$time_slots = bkap_booking_process::bkap_check_for_time_slot(
									array(
										'current_date' => $date,
										'post_id'      => $product_id,
										'called_from'  => 'bkap-api',
									)
								);

								if ( '' !== $time_slots && ! is_array( $time_slots ) ) {
									$time_slots = explode( '|', $time_slots );
								} elseif ( is_array( $time_slots ) ) {

									$_time_slots = array();

									if ( isset( $time_slots['unavailable_slots'] ) || isset( $time_slots['time_slots'] ) ) {

										if ( isset( $time_slots['unavailable_slots'] ) && is_array( $time_slots['unavailable_slots'] ) && count( $time_slots['unavailable_slots'] ) > 0 ) {

											foreach( $time_slots['unavailable_slots'] as $item ) {

												$from_time = $item['from_time'];
												$to_time   = $item['to_time'];

												if ( '' !== $from_time ) {
													$from_time = date( 'H:i', strtotime( $from_time ) );
												}

												if ( '' !== $to_time ) {
													$to_time = date( 'H:i', strtotime( $to_time ) );
												}

												$_time_slots[] = array(
													'is_unavailable' => true,
													'start_time'     => $from_time,
													'end_time'       => $to_time,
												);
											}
										}

										if ( isset( $time_slots['time_slots'] ) && is_array( $time_slots['time_slots'] ) && count( $time_slots['time_slots'] ) > 0 ) {

											foreach( $time_slots['time_slots'] as $item ) {
												$_time_slots[] = $item;
											}
										}
									}

									$time_slots = $_time_slots;
								}

								if ( is_array( $time_slots ) && count( $time_slots ) > 0 ) {
									foreach ( $time_slots as $slot ) {

										if ( '' === $slot ) {
											continue;
										}

										if ( is_array( $slot ) && isset( $slot['is_unavailable'] ) && true === $slot['is_unavailable'] ) {

											$return_data[] = array(
												'date'         => $date,
												'availability' => 0,
												'start_time'   => $slot['start_time'],
												'end_time'     => $slot['end_time'],
											);

										} else {

											$_availability = bkap_booking_process::bkap_get_time_lockout(
												array(
													'post_id'        => $product_id,
													'date'           => $date,
													'checkin_date'   => $date,
													'timeslot_value' => $slot,
													'date_time_type' => 'on'
												)
											);

											$split      = explode( '-', $slot );
											$start_time = date( 'H:i', strtotime( trim( $split[0] ) ) );
											$end_time   = date( 'H:i', strtotime( trim( $split[1] ) ) );

											$return_data[] = array(
												'date'         => $date,
												'availability' => 'FALSE' === $_availability[ 'max_qty' ] ? 0 : $_availability[ 'max_qty' ],
												'start_time'   => $start_time,
												'end_time'     => $end_time,
											);
										}
									}

									$return_data = array_values( array_unique( $return_data, SORT_REGULAR ) );
								}
							} else {
								$return_data[] = $return;
							}
						} elseif ( 'FALSE' === $get_availability ) {
							$return_data[] = array(
								'date'    => $date,
								'message' => 'Bookings are full.',
							);

						} elseif ( 'TIME-FALSE' === $get_availability ) {
							$return_data[] = array(
								'date'    => $date,
								'message' => 'Bookings are in cart.',
							);
						}
					}
				}

				return $return_data;

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}
	}
}
