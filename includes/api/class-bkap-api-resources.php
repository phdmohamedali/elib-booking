<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Booking API that handles requests to the /resources endpoint.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.9.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Resources' ) ) {

	/**
	 * Booking API endpoint.
	 *
	 * @since 5.9.1
	 */
	class BKAP_API_Resources extends WC_API_Resource {

		/**
		 * Route base.
		 *
		 * @var string $base
		 */
		protected $base = 'bkap/resources';

		/**
		 * Custom Post type for Resources.
		 *
		 * @var string $post_type
		 */
		protected $post_type = 'bkap_resource';

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
		 * GET /resources
		 * GET /resources/count
		 * GET|DELETE /products/<id>
		 *
		 * @since 5.9.1
		 * @param array $routes Routes.
		 * @return array
		 */
		public function register_routes( $routes ) {

			// GET /resources.
			$routes[ $this->get_resources_endpoint() ] = array(
				array( array( $this, 'get_resources' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /resources/count.
			$routes[ $this->get_resources_endpoint() . '/count' ] = array(
				array( array( $this, 'get_resources_count' ), WC_API_Server::READABLE ),
			);

			// GET|DELETE /resources/<id>.
			$routes[ $this->get_resources_endpoint() . '/(?P<id>\d+)' ] = array(
				array( array( $this, 'get_resource' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'delete_resource' ), WC_API_Server::DELETABLE ),
			);

			return $routes;
		}

		/**
		 * Returns the Resource endpoint/base.
		 *
		 * @since 5.9.1
		 * @return string
		 */
		public function get_resources_endpoint() {
			return '/' . apply_filters( 'bkap_api_resources_endpoint', $this->base );
		}

		/**
		 * Get all resources.
		 *
		 * @since 5.9.1
		 * @param array $data Array of filter settings.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_resources( $data = array() ) {

			try {
				$filter = $data;

				if ( $this->check_if_exists( 'limit', $filter ) ) {
					if ( ! is_numeric( $filter['limit'] ) ) {
						throw new WC_API_Exception( 'bkap_api_resource_invalid_parameter', __( 'Limit parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'product_id', $filter ) ) {
					$post = wc_get_product( $filter['product_id'] );
					if ( ! is_object( $post ) ) {
						throw new WC_API_Exception( 'bkap_api_resource_invalid_parameter', __( 'No valid Product has been found for the Product ID provided', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'customer_id', $filter ) ) {
					if ( false === get_user_by( 'id', $filter['customer_id'] ) ) {
						throw new WC_API_Exception( 'bkap_api_resource_invalid_parameter', __( 'Customer ID is invalid', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'quantity', $filter ) ) {
					if ( ! is_numeric( $filter['quantity'] ) ) {
						throw new WC_API_Exception( 'bkap_api_resource_invalid_parameter', __( 'Quantity parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				// Set default return data in case the resource record is empty.
				$return_data = array( 'message' => 'No Resources' );

				$query_resources = $this->query_resources( $filter );

				if ( '' !== $query_resources && 0 !== $query_resources->post_count ) {

					$resources = array();

					foreach ( $query_resources->posts as $resource_id ) {

						// Conform to WooCommerce standard and check if the current user has got permission to read the Resource instance.

						if ( ! $this->check_permission( $resource_id, 'read' ) ) {
							continue;
						}

						$response = current( $this->get_resource( $resource_id ) );

						// Add resource-related properties to response if Product ID has been set.
						if ( $this->check_if_exists( 'product_id', $filter ) ) {
							$resource_label = get_post_meta( $filter['product_id'], '_bkap_product_resource_lable', true );
							$response       = $this->check_if_exists_and_set( 'resource_label', $resource_label, $response );
							$selection      = get_post_meta( $filter['product_id'], '_bkap_product_resource_selection', true );
							$selection_type = get_post_meta( $filter['product_id'], '_bkap_product_resource_selection_type', true );

							$resource_selection = '';

							if ( 'bkap_customer_resource' === $selection ) {
								$resource_selection = 'Selected by Customer';
							} elseif ( 'bkap_automatic_resource' === $selection ) {
								$resource_selection = 'Automatically Assigned';
							}

							$resource_selection_type = '';

							if ( 'single' === $selection_type ) {
								$resource_selection_type = 'Single';
							} elseif ( 'multiple' === $selection_type ) {
								$resource_selection_type = 'Multiple';
							}

							$response = $this->check_if_exists_and_set( 'resource_selection', $resource_selection, $response );
							$response = $this->check_if_exists_and_set( 'resource_selection_type', $resource_selection_type, $response );

							$base_cost_resources = get_post_meta( $filter['product_id'], '_bkap_resource_base_costs', true );
							$base_cost_resources = maybe_unserialize( $base_costs );

							if ( is_array( $base_cost_resources ) && 0 !== count( $base_cost_resources ) ) {
								$resources = array();

								foreach ( $base_cost_resources as $resource_id => $resource_cost ) {
									$_response = array(
										'resource_id' => $resource_id,
										'resource_base_cost' => $resource_cost,
									);

									$resources[] = $_response;
								}
								$response['resource_base_cost'] = $resources;
							}
						}

						$resources[] = $response;
					}

					$this->server->add_pagination_headers( $query_resources );

					$return_data = array(
						'resources' => $resources,
						'count'     => count( $resources ),
					);
				}

				return $return_data;

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Function to get resource objects.
		 *
		 * @since 5.9.1
		 * @param array $args Arguments for filtering query.
		 * @return WP_Query
		 */
		public function query_resources( $args ) {

			// Default arguments.
			$default_args = array(
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'post_type'      => $this->post_type,
				'orderby'        => 'date',
				'posts_per_page' => -1,
			);

			$filter_args = array();

			// Filter: Page count.
			if ( $this->check_if_exists( 'limit', $args ) ) {
				$filter_args['posts_per_page'] = $args['limit'];
			}

			// Filter: Product.
			if ( $this->check_if_exists( 'product_id', $args ) ) {

				// Get list of all resources assigned to the Product.

				$resources = get_post_meta( $args['product_id'], '_bkap_product_resources', true );

				if ( ! empty( $resources ) && '' !== $resources ) {
					$resources = maybe_unserialize( $resources );

					if ( is_array( $resources ) && 0 !== count( $resources ) ) {
						$filter_args['post__in'] = ( 0 === count( $filter_args['post__in'] ) ) ? $resources : array_intersect( $filter_args['post__in'], $resources );
					}
				}
			}

			// Filter: Customer ID.
			if ( $this->check_if_exists( 'customer_id', $args ) ) {

				// Get list of all Resoruces that have been booked by Customer ID.
				$_args = array(
					'post_type'  => 'bkap_booking',
					'meta_query' => array(
						array(
							'key'     => '_bkap_customer_id',
							'value'   => $args['customer_id'],
							'compare' => '=',
						),
						array(
							'key'     => '_bkap_resource_id',
							'value'   => '',
							'compare' => '!=',
						),
					),
				);

				$query = new WP_Query( $_args );

				if ( 0 !== $query->post_count ) {

					$resource_ids = array();

					foreach ( $query->posts as $booking ) {
						$booking_id  = $booking->ID;
						$resource_id = get_post_meta( $booking_id, '_bkap_resource_id', true );

						if ( '' !== $resource_id ) {
							$resource_ids[] = $resource_id;
						}
					}

					$filter_args['post__in'] = ( 0 === count( $filter_args['post__in'] ) ) ? $resource_ids : array_intersect( $filter_args['post__in'], $resource_ids );
				}
			}

			// Filter: Quantity - Get resources greater than specified quantity.
			if ( $this->check_if_exists( 'quantity', $args ) ) {

				$filter_args['meta_query'][] = array(
					'key'     => '_bkap_resource_qty',
					'value'   => (int) $args['quantity'],
					'compare' => '>',
				);
			}

			$wp_args = wp_parse_args( $filter_args, $default_args );

			// Run $wp_args through a filter for exteral changes.
			$wp_args = apply_filters( 'bkap_api_booking_query_resources_args', $wp_args );

			return new WP_Query( $wp_args );
		}

		/**
		 * Get the Resource object for the given ID.
		 *
		 * @since 5.9.1
		 * @param int $id Resource ID.
		 * @return array
		 */
		public function get_resource( $id ) {

			// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
			$id = $this->validate_request( $id, $this->post_type, 'read' );

			if ( is_wp_error( $id ) ) {
				return $id;
			}

			$resource      = new BKAP_Product_Resource( $id );
			$resource_data = array(
				'resource_id'    => $resource->get_id(),
				'resource_title' => $resource->get_title(),
			);

			// Check if other properties exist and add them to array.
			$resource_data = $this->add_other_resource_properties( $resource, $resource_data );

			if ( isset( $resource_data['resource_availability'] ) ) {
				$resource_data['resource_availability'] = maybe_unserialize( $resource_data['resource_availability'] );
			}

			// Run $resource through filter hook to incorporate external changes.
			$resource_data = apply_filters( 'bkap_api_booking_resource_response', $resource_data, $resource );

			return array( 'resource' => $resource_data );
		}

		/**
		 * Delete Resource.
		 *
		 * @since 5.9.1
		 * @param int $id Resource ID.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function delete_resource( $id ) {

			try {

				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to delete.
				$id = $this->validate_request( $id, $this->post_type, 'delete' );

				if ( is_wp_error( $id ) ) {
					return $id;
				}

				// Check that Resource exists as a post in WordPress: We do this in case Resource has already been deleted and request is repeated.
				if ( false === get_post_status( $id ) ) {
					throw new WC_API_Exception( 'bkap_api_resource_post_not_found', __( 'Resource cannot be found or does not exist', 'woocommerce-booking' ), 400 );
				}

				// Remove Resource Post.
				$result = wp_delete_post( $id, true );

				return $result ? array( 'message' => __( 'Resource has been deleted', 'woocommerce-booking' ) ) : array( 'message' => __( 'Resource could not be deleted due to some errors', 'woocommerce-booking' ) );
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
		 * Get the total number of Resources.
		 *
		 * @since 5.9.1
		 * @return array|WP_Error
		 */
		public function get_resources_count() {

			try {
				$query_resources = $this->query_resources( null );
				return array( 'count' => (int) $query_resources->found_posts );

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Checks the permission for the user for a request.
		 *
		 * @since 5.9.1
		 * @param int    $post_id Can be Resource ID.
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
		 * @param object $resource Resource Object.
		 * @param array  $resource_data Resource array that will be returned as response.
		 * @return array
		 */
		public function add_other_resource_properties( $resource, $resource_data ) {
			$resource_data = $this->check_if_exists_and_set( 'quantity', $resource->get_qty(), $resource_data );
			$resource_data = $this->check_if_exists_and_set( 'base_cost', $resource->get_base_cost(), $resource_data );
			$resource_data = $this->check_if_exists_and_set( 'block_cost', $resource->get_block_cost(), $resource_data );
			$resource_data = $this->check_if_exists_and_set( 'resource_quantity', $resource->get_resource_qty(), $resource_data );
			$resource_data = $this->check_if_exists_and_set( 'resource_availability', $resource->get_resource_availability(), $resource_data );
			$resource_data = $this->check_if_exists_and_set( 'meeting_host', $resource->get_resource_host(), $resource_data );
			return $resource_data;
		}
	}
}
