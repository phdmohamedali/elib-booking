<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Booking API that handles requests to the /products endpoint.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.9.1
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Products' ) ) {

	/**
	 * Product API endpoint.
	 *
	 * @since 5.9.1
	 */
	class BKAP_API_Products extends WC_API_Resource {

		/**
		 * Route base.
		 *
		 * @var string $base
		 */
		protected $base = 'bkap/products';

		/**
		 * Custom Post type for BKAP.
		 *
		 * @var string $post_type
		 */
		protected $post_type = 'product';

		/**
		 * Default Products Post Status.
		 *
		 * @var array $default_post_status
		 */
		protected $default_post_status = array(
			'draft',
			'publish',
			'pending',
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
		 * GET|POST /products
		 * GET /products/count
		 * GET|PUT|DELETE /products/<id>
		 *
		 * @since 5.9.1
		 * @param array $routes Routes.
		 * @return array
		 */
		public function register_routes( $routes ) {

			// GET|POST /products.
			$routes[ $this->get_products_endpoint() ] = array(
				array( array( $this, 'get_products' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'create_product' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /bookings/count.
			$routes[ $this->get_products_endpoint() . '/count' ] = array(
				array( array( $this, 'get_products_count' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET|PUT|DELETE /products/<id>.
			$routes[ $this->get_products_endpoint() . '/(?P<id>\d+)' ] = array(
				array( array( $this, 'get_product' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'edit_product' ), WC_API_Server::EDITABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'delete_product' ), WC_API_Server::DELETABLE ),
			);

			return $routes;
		}

		/**
		 * Returns the Product endpoint/base.
		 *
		 * @since 5.9.1
		 * @return string
		 */
		public function get_products_endpoint() {
			return '/' . apply_filters( 'bkap_api_product_endpoint', $this->base );
		}

		/**
		 * Get all Products.
		 *
		 * @since 5.9.1
		 * @param array $data Array of filter settings.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_products( $data = array() ) {

			try {
				$filter = $data;

				if ( $this->check_if_exists( 'limit', $filter ) ) {
					if ( ! is_numeric( $filter['limit'] ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Limit parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'status', $filter ) ) {

					if ( ! is_array( $filter['status'] ) ) {
						$filter['status'] = (array) $filter['status'];
					}

					if ( array_diff( $filter['status'], $this->default_post_status ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Post Status provided is invalid. Use -> draft, pending, publish', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'booking_type', $filter ) ) {

					if ( ! is_array( $filter['booking_type'] ) ) {
						$filter['booking_type'] = (array) $filter['booking_type'];
					}

					if ( ! class_exists( 'BKAP_API_Bookings' ) ) {
						throw new WC_API_Exception( 'bkap_api_product_booking_class_unavailable', __( 'Booking Class is required', 'woocommerce-booking' ), 400 );
					}

					if ( array_diff( $filter['booking_type'], BKAP_API_Bookings::get_bookings_types() ) ) {
						/* translators: %s: booking types */
						throw new WC_API_Exception( 'bkap_api_product_invalid_booking_type', sprintf( __( 'Booking Type provided is invalid.. Allowed Booking Types are: %s', 'woocommerce-booking' ), implode( ', ', array_keys( BKAP_API_Bookings::get_bookings_types() ) ) ), 400 );
					}
				}

				if ( $this->check_if_exists( 'start_date', $filter ) ) {
					// Check Start Date is in correct format.
					$date = DateTime::createFromFormat( 'Y-m-d', $filter['start_date'] );
					if ( false === $date ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the Start Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'end_date', $filter ) ) {
					// Check End Date is in correct format.
					$date = DateTime::createFromFormat( 'Y-m-d', $filter['end_date'] );
					if ( false === $date ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the End Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
					}
				}

				if ( $this->check_if_exists( 'price', $filter ) ) {
					if ( ! is_numeric( $filter['price'] ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Price parameter must be numeric', 'woocommerce-booking' ), 400 );
					}
				}

				// Set default return data in case the product record is empty.
				$return_data = array( 'message' => 'No Products' );

				$query_products = $this->query_products( $filter );

				if ( 0 !== $query_products->post_count ) {

					$products = array();

					foreach ( $query_products->posts as $product_id ) {

						// Conform to WooCommerce standard and check if the current user has got permission to read the Product instance.

						if ( ! $this->check_permission( $product_id, 'read' ) ) {
							continue;
						}

						$response = current( $this->get_product( $product_id, $filter ) );

						if ( '' !== $response ) {
							$products[] = $response;
						}
					}

					$this->server->add_pagination_headers( $query_products );

					$return_data = array(
						'products' => $products,
						'count'    => count( $products ),
					);
				}

				return $return_data;

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Function to get product objects.
		 *
		 * @since 5.9.1
		 * @param array $args Arguments for filtering query.
		 * @return WP_Query
		 */
		public function query_products( $args ) {

			// Default arguments.
			$default_args = array(
				'fields'         => 'ids',
				'post_status'    => 'publish',
				'post_type'      => 'product',
				'orderby'        => 'date',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'     => '_bkap_enable_booking',
						'value'   => 'on',
						'compare' => '=',
					),
				),
			);

			$filter_args = array();

			// Filter: Page count.
			if ( $this->check_if_exists( 'limit', $args ) ) {
				$filter_args['posts_per_page'] = $args['limit'];
			}

			// Filter: Product Status.
			if ( $this->check_if_exists( 'status', $args ) ) {
				$filter_args['post_status'] = $args['status'];
			}

			// Filter: Product Booking Type.
			if ( $this->check_if_exists( 'booking_type', $args ) ) {

				$filter_args['meta_query'][] = array(
					'key'     => '_bkap_booking_type',
					'value'   => $args['booking_type'],
					'compare' => '=',
				);
			}

			// Filter: Start Date.
			if ( $this->check_if_exists( 'start_date', $args ) ) {

				$filter_args['date_query'][] = array(
					'after' => strtotime( $args['start_date'] ),
				);
			}

			// Filter: End Date.
			if ( $this->check_if_exists( 'end_date', $args ) ) {

				$filter_args['date_query'][] = array(
					'before' => strtotime( $args['end_date'] ),
				);
			}

			$wp_args = wp_parse_args( $filter_args, $default_args );

			// Run $wp_args through a filter for exteral changes.
			$wp_args = apply_filters( 'bkap_api_product_query_products_args', $wp_args );

			return new WP_Query( $wp_args );
		}

		/**
		 * Get the Product object for the given ID.
		 *
		 * @since 5.9.1
		 * @param int   $id Product ID.
		 * @param array $filter Array of filter settings.
		 * @return array
		 */
		public function get_product( $id, $filter = array() ) {

			$fields = array();

			if ( $this->check_if_exists( 'fields', $filter ) ) {
				$fields = isset( $filter['fields'] ) ? $filter['fields'] : array();
			}

			// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
			$id = $this->validate_request( $id, 'product', 'read' );

			if ( is_wp_error( $id ) ) {
				return $id;
			}

			$product      = wc_get_product( $id );
			$product_data = array(
				'title'      => $product->get_name(),
				'id'         => $product->get_id(),
				'created_at' => $this->server->format_datetime( $product->get_date_created(), false, true ),
				'updated_at' => $this->server->format_datetime( $product->get_date_modified(), false, true ),
				'type'       => $product->get_type(),
				'status'     => $product->get_status(),
				'permalink'  => $product->get_permalink(),
				'price'      => $product->get_price(),
			);

			// Add constraint for price for variation. That is, get price when it is a variable product.

			// Filter for Price.
			if ( $this->check_if_exists( 'price', $filter ) ) {
				if ( $product_data['price'] < $filter['price'] ) {
					return;
				}
			}

			// Check if other properties exist and add them to array.
			$product_data = $this->add_other_product_properties( $product, $product_data );

			// Remove other meta_keys and return only the ones specified in $fields variable.
			if ( is_array( $fields ) && count( $fields ) > 0 ) {

				foreach ( $product_data as $key => $data ) {
					if ( ! in_array( $key, $fields, true ) ) {
						unset( $product_data[ $key ] );
					}
				}
			}

			// Run $product_data through filter hook to incorporate external changes.
			$product_data = apply_filters( 'bkap_api_product_product_response', $product_data, $product );

			return array( 'product' => $product_data );
		}

		/**
		 * Delete Product.
		 *
		 * @since 5.9.1
		 * @param int $id Product ID.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function delete_product( $id ) {

			try {

				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to delete.
				$id = $this->validate_request( $id, $this->post_type, 'delete' );

				if ( is_wp_error( $id ) ) {
					return $id;
				}

				// Check that the Product exists as a post in WordPress: We do this in case Product has already been deleted and request is repeated.
				if ( false === get_post_status( $id ) ) {
					throw new WC_API_Exception( 'bkap_api_product_post_not_found', __( 'Product cannot be found or does not exist', 'woocommerce-booking' ), 400 );
				}

				$product = wc_get_product( $id );
				$product->delete( true );

				return ( get_post_status( $id ) ) ? array( 'message' => __( 'Product could not be deleted due to some errors', 'woocommerce-booking' ) ) : array( 'message' => __( 'Product has been deleted', 'woocommerce-booking' ) );
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
		 * Gets all meta keys/values via get_post_meta and faltten array values.
		 *
		 * @since 5.9.1
		 * @param string $post_id  Post ID for Product, Booking etc..
		 * @return array
		 */
		public function get_all_post_meta( $post_id ) {
			$post_metas = get_post_meta( $post_id );

			// Flatten values.
			foreach ( $post_metas as $meta_key => $meta_value ) {
				if ( is_array( $meta_value ) ) {
					$value = $meta_value[0];
					// TODO: What happens when $meta_values are arrays of more than one item?
					// So we can have a field where we check for such items and return them as arrays then.
					$post_metas[ $meta_key ] = $value;
				}
			}

			return $post_metas;
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

				if ( ( is_array( $element ) && 0 !== count( $element ) ) || ( ! is_array( $element ) ) ) {
					$data[ $key ] = $element;
				}
			}

			return $data;
		}

		/**
		 * Get the total number of Products.
		 *
		 * @since 5.9.1
		 * @return array|WP_Error
		 */
		public function get_products_count() {

			try {
				$query_products = $this->query_products( array() );
				return array( 'count' => (int) $query_products->found_posts );

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
		 * @param object $product Product Object.
		 * @param array  $product_data Product array that will be returned as response.
		 * @return array
		 */
		public function add_other_product_properties( $product, $product_data ) {
			$product_data = $this->check_if_exists_and_set( 'parent_id', $product->get_parent_id(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'categories', wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ), $product_data );
			$product_data = $this->check_if_exists_and_set( 'tags', wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ), $product_data );
			$product_data = $this->check_if_exists_and_set( 'virtual', $product->is_virtual(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'regular_price', $product->get_regular_price(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'sale_price', $product->get_sale_price(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'taxable', $product->is_taxable(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'tax_status', $product->get_tax_status(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'tax_class', $product->get_tax_class(), $product_data );
			$product_data = $this->check_if_exists_and_set( 'description', wpautop( do_shortcode( $product->get_description() ) ), $product_data );
			$product_data = $this->check_if_exists_and_set( 'short_description', apply_filters( 'woocommerce_short_description', $product->get_short_description() ), $product_data );
			$product_data = $this->check_if_exists_and_set( 'product_url', ( $product->is_type( 'external' ) ? $product->get_product_url() : '' ), $product_data );

			$product_settings = $this->prepare_data_array(
				$this->get_all_post_meta(
					$product->get_id()
				),
				array(
					'woocommerce_booking_settings',
					'_product_attributes',
					'_bkap_max_bookable_days',
					'_bkap_attribute_settings',
					'_bkap_booking_type',
					'_bkap_special_price',
					'_bkap_enable_inline',
					'_bkap_requires_confirmation',
					'_bkap_can_be_cancelled',
					'_bkap_custom_ranges',
					'_bkap_holiday_ranges',
					'_bkap_month_ranges',
				)
			);

			$product_booking_settings = $this->prepare_data_array(
				maybe_unserialize( $product_settings['woocommerce_booking_settings'] ),
				array(
					'booking_recurring_booking',
					'booking_recurring',
					'booking_recurring_lockout',
					'booking_specific_booking',
					'booking_specific_date',
					'_bkap_product_resources',
					'_bkap_resource',
					'_bkap_product_resource_selection',
					'_bkap_resource_base_costs',
				)
			);

			// Check if product has attributes.
			$product_attributes = maybe_unserialize( $product_settings['_product_attributes'] );
			$booking_attributes = maybe_unserialize( $product_settings['_bkap_attribute_settings'] );

			if ( is_array( $product_attributes ) && 0 !== count( $product_attributes ) ) {

				$return_array = array();

				foreach ( $product_attributes as $id => $attribute ) {
					$return_data['attribute_slug']          = $id;
					$return_data['attribute_name']          = $attribute['name'];
					$return_data['attribute_value']         = $attribute['value'];
					$return_data['visible_on_product_page'] = ( 1 === $attribute['is_visible'] ? 'yes' : 'no' );
					$return_data['used_for_variations']     = ( 1 === $attribute['is_variation'] ? 'yes' : 'no' );

					if ( isset( $booking_attributes[ $id ] ) && is_array( $booking_attributes[ $id ] ) && 0 !== count( $booking_attributes[ $id ] ) ) {
						$return_data['equate_bookings'] = ( '' !== $booking_attributes[ $id ]['booking_lockout_as_value'] ? $booking_attributes[ $id ]['booking_lockout_as_value'] : 'off' );
						$return_data['booking_lockout'] = ( '' !== $booking_attributes[ $id ]['booking_lockout'] ? $booking_attributes[ $id ]['booking_lockout'] : 0 );
					}
				}

				$product_data = $this->check_if_exists_and_set( 'attributes', $return_array, $product_data );
			}

			// Enable Booking.
			$product_data = $this->check_if_exists_and_set( 'enable_booking', ( '' !== $product_settings['_bkap_enable_booking'] ? $product_settings['_bkap_enable_booking'] : 'off' ), $product_data );

			// Maximum Bookle Days.
			$product_data = $this->check_if_exists_and_set( 'maximum_booking_days', $product_settings['_bkap_max_bookable_days'], $product_data );

			// Special Price.
			$special_price = maybe_unserialize( $product_settings['_bkap_special_price'] );

			if ( is_array( $special_price ) && 0 !== count( $special_price ) ) {
				$product_data = $this->check_if_exists_and_set( 'special_price', $special_price, $product_data );
			}

			// Booking Type.
			$product_data = $this->check_if_exists_and_set( 'booking_type', $product_settings['_bkap_booking_type'], $product_data );

			// Calendar Type.
			$calendar_type = ( 'on' === $product_settings['_bkap_enable_inline'] ) ? 'inline' : 'default';
			$product_data  = $this->check_if_exists_and_set( 'booking_calendar_type', $calendar_type, $product_data );

			// Requires Confirmation.
			$requires_confirmation = ( 'on' === $product_settings['_bkap_requires_confirmation'] ) ? 'yes' : 'no';
			$product_data          = $this->check_if_exists_and_set( 'booking_requires_confirmation', $requires_confirmation, $product_data );

			// Can be cancelled?
			$booking_cancelled = $this->prepare_data_array(
				maybe_unserialize( $product_settings['_bkap_can_be_cancelled'] ),
				array(
					'status',
				)
			);

			$booking_cancelled_return_data = array(
				'status' => 'off',
			);

			if ( 'on' === $booking_cancelled['status'] ) {
				$booking_cancelled_return_data = $booking_cancelled;
			}

			$product_data = $this->check_if_exists_and_set( 'booking_cancellation', $booking_cancelled_return_data, $product_data );

			// Booking Availability by Weekday.
			$booking_availability_weekday_setting = $product_booking_settings['booking_recurring_booking'];
			$booking_availability_return_data     = '';

			$booking_days = array(
				'booking_weekday_0' => 'sunday',
				'booking_weekday_1' => 'monday',
				'booking_weekday_2' => 'tuesday',
				'booking_weekday_3' => 'wednesday',
				'booking_weekday_4' => 'thursday',
				'booking_weekday_5' => 'friday',
				'booking_weekday_6' => 'saturday',
			);

			if ( 'on' === $booking_availability_weekday_setting ) {
				$recurring_bookings = $product_booking_settings['booking_recurring'];
				$recurring_lockouts = $product_booking_settings['booking_recurring_lockout'];

				if ( is_array( $recurring_bookings ) && 0 !== count( $recurring_bookings ) && is_array( $recurring_lockouts ) && 0 !== count( $recurring_lockouts ) ) {

					$return_data = array();

					foreach ( $recurring_bookings as $setting => $value ) {

						if ( 'on' === $value ) {
							$return_data[] = array(
								'day'     => $booking_days[ $setting ],
								'lockout' => (int) ( '' === $recurring_lockouts[ $setting ] ? 0 : $recurring_lockouts[ $setting ] ),
							);
						}
					}

					$booking_availability_return_data = array(
						'status'          => 'on',
						'available_dates' => $return_data,
					);
				}
			}

			$product_data = $this->check_if_exists_and_set( 'booking_availability_weekday', $booking_availability_return_data, $product_data );

			// Booking Availability by Specific Dates.
			$booking_availability_specific_date_setting = $product_booking_settings['booking_specific_booking'];
			$booking_availability_return_data           = '';

			if ( 'on' === $booking_availability_specific_date_setting ) {
				$specific_dates = $product_booking_settings['booking_specific_date'];

				if ( is_array( $specific_dates ) && 0 !== count( $specific_dates ) ) {

					$return_data = array();

					foreach ( $specific_dates as $date => $value ) {

						$return_data[] = array(
							'date'    => $date,
							'lockout' => (int) ( '' === $value ? 0 : $value ),
						);
					}

					$booking_availability_return_data = array(
						'status'         => 'on',
						'specific_dates' => $return_data,
					);
				}
			}

			$product_data = $this->check_if_exists_and_set( 'booking_availability_specific_date', $booking_availability_return_data, $product_data );

			// Booking Availability by Custom Range.
			$booking_availability_custom_range = maybe_unserialize( $product_settings['_bkap_custom_ranges'] );

			if ( is_array( $booking_availability_custom_range ) && 0 !== count( $booking_availability_custom_range ) ) {
				$product_data = $this->check_if_exists_and_set( 'booking_custom_range', $booking_availability_custom_range, $product_data );
			}

			// Booking Availability by Holiday Range.
			$booking_availability_holiday_range = maybe_unserialize( $product_settings['_bkap_holiday_ranges'] );

			if ( is_array( $booking_availability_holiday_range ) && 0 !== count( $booking_availability_holiday_range ) ) {
				$product_data = $this->check_if_exists_and_set( 'booking_holiday_range', $booking_availability_holiday_range, $product_data );
			}

			// Booking Availability by Month Range.
			$booking_availability_month_range = maybe_unserialize( $product_settings['_bkap_month_ranges'] );

			if ( is_array( $booking_availability_month_range ) && 0 !== count( $booking_availability_month_range ) ) {
				$product_data = $this->check_if_exists_and_set( 'booking_month_range', $booking_availability_month_range, $product_data );
			}

			// Booking Resources.
			$resources = $product_booking_settings['_bkap_product_resources'];

			if ( is_array( $resources ) && 0 !== count( $resources ) ) {
				$booking_resource_return_data['resources'] = $resources;
			}

			$booking_resource_setting = $product_booking_settings['_bkap_resource'];

			if ( 'on' === $booking_resource_setting ) {

				if ( $this->check_if_exists( '_bkap_product_resource_lable', $product_booking_settings ) ) {
					$booking_resource_return_data['label'] = $product_booking_settings['_bkap_product_resource_lable'];
				}

				if ( $this->check_if_exists( '_bkap_product_resource_selection', $product_booking_settings ) ) {
					$booking_resource_return_data['selection'] = ( 'bkap_customer_resource' === $product_booking_settings['_bkap_product_resource_selection'] ? 'By Customer' : 'Automatically Assigned' );
				}

				$resource_base_cost = $product_booking_settings['_bkap_resource_base_costs'];

				if ( is_array( $resource_base_cost ) && 0 !== count( $resource_base_cost ) ) {

					$return_resource = array();

					foreach ( $resource_base_cost as $resource_id => $cost ) {
						$return_resource = array(
							'resource_id' => $resource_id,
							'cost'        => $cost,
						);
					}

					$booking_resource_return_data['resource_costs'] = $return_resource;
				}
			}

			return $product_data;
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
		public function edit_product( $id, $data ) {

			try {

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_product_missing_product_data', __( 'Product data has not been provided to edit Product', 'woocommerce-booking' ), 400 );
				}

				// Check if user is permitted to edit Product.
				if ( ! current_user_can( 'edit_posts' ) ) {
					throw new WC_API_Exception( 'bkap_api_product_user_cannot_edit_product', __( 'You do not have permission to edit this Product', 'woocommerce-booking' ), 400 );
				}

				$data = apply_filters( 'bkap_api_product_edit_product_data', $data, $this );

				$data['product_id'] = $id;

				if ( ! $this->is_data_set( 'product_id', $data ) ) {
					throw new WC_API_Exception( 'bkap_api_product_empty_booking_id', __( 'Please provide a Product ID', 'woocommerce-booking' ), 400 );
				}

				// Ensure Product ID is valid.
				$product = wc_get_product( $data['product_id'] );

				if ( 'product' !== $product->post_type ) {
					throw new WC_API_Exception( 'bkap_api_product_invalid_product_id', __( 'Product ID provided is not of type Product', 'woocommerce-booking' ), 400 );
				}

				// Validate Product Type.
				if ( $this->is_data_set( 'product_type', $data ) ) {
					if ( ! in_array( $data['product_type'], array_keys( wc_get_product_types() ), true ) ) {
						/* translators: %s: Product Types. */
						throw new WC_API_Exception( 'bkap_api_product_invalid_product_type', sprintf( __( 'Invalid Product type - the product type must be any of these: %s', 'woocommerce-booking' ), implode( ', ', array_keys( wc_get_product_types() ) ) ), 400 );
					}
				}

				$classname = WC_Product_Factory::get_classname_from_product_type( $data['product_type'] );
				if ( ! class_exists( $classname ) ) {
					$classname = 'WC_Product_Simple';
				}

				$product = new $classname();

				// Validate Product Status.
				if ( $this->is_data_set( 'product_status', $data ) ) {

					$product_status = $data['product_status'];

					if ( ! is_array( $data['product_status'] ) ) {
						$data['product_status'] = (array) $data['product_status'];
					}

					if ( array_diff( $data['product_status'], $this->default_post_status ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Product Status provided is invalid. Use -> draft, pending, publish', 'woocommerce-booking' ), 400 );
					}

					$product->set_status( wc_clean( $product_status ) );
				}

				// Update Product.
				if ( $this->is_data_set( 'product_description', $data ) ) {
					$product->set_description( wp_filter_post_kses( $data['product_description'] ) );
				}

				if ( $this->is_data_set( 'product_title', $data ) ) {
					$product->set_name( wc_clean( $data['product_title'] ) );
				}

				$product->save();

				// Clear cache or transients to remove stale data.
				wc_delete_product_transients( $data['product_id'] );

				// Save product meta for Booking.
				return $this->update_booking_product( $data['product_id'], $data['booking'] );

			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Do action: ( Create | Update ) Booking Product.
		 *
		 * @since 5.10.1
		 * @param int     $id Product ID.
		 * @param array   $data Booking data of items that will be used to create or update Booking Product.
		 * @param boolean $action Action to be implemented for this function. Ex: create - Create Booking Product, update - Update Booking Product.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function do_action_booking_product( $id, $data, $action ) {

			try {

				$return_data                   = array();
				$action_create_booking_product = ( 'create' === $action );
				$action_update_booking_product = ( 'update' === $action );

				if ( $action_create_booking_product || $action_update_booking_product ) {

					$obj        = new stdClass();
					$product_id = $id;

					// Get current data of booking product.
					$current_data = $this->get_all_post_meta( $product_id );

					// Let's organize the settings based on sections, starting with the General Settings section.

					// Enable Booking.
					if ( $this->is_data_set( 'enable_booking', $data ) ) {

						if ( 'on' === $data['enable_booking'] || 'off' === $data['enable_booking'] ) {
							$obj->booking_enable_date = ( 'on' === $data['enable_booking'] ? 'on' : '' );
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for enable_booking parameter', 'woocommerce-booking' ), 400 );
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->booking_enable_date = $current_data['_bkap_enable_booking'];
						}
					}

					// Booking Types.
					if ( $this->is_data_set( 'booking_type', $data ) ) {

						$booking_types = BKAP_API_Bookings::get_bookings_types();
						$booking_types = array_column( $booking_types, 'key' ); // Get array of keys of Booking types.

						if ( ! in_array( $data['booking_type'], $booking_types, true ) ) {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Invalid Booking Type', 'woocommerce-booking' ), 400 );
						}

						$obj->booking_options = $data['booking_type'];
					} else {
						if ( $action_update_booking_product ) {
							$obj->booking_options = $current_data['_bkap_booking_type'];
						}
					}

					// Checks for Multiple Dates.
					if ( $this->is_data_set( 'booking_type', $data ) && ( 'multidates' === $data['booking_type'] || 'multidates_fixedtime' === $data['booking_type'] ) ) {

						if ( ! $this->is_data_set( 'booking_date_fixed_time_selection', $data ) ) {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Specify Type of Selection for Dates & Fixed Time Booking Type', 'woocommerce-booking' ), 400 );
						}

						$obj->multidates_type = $data['booking_date_fixed_time_selection'];

						// Type of Selection: Fixed or Range.
						if ( 'fixed' === $data['booking_date_fixed_time_selection'] ) {
							if ( ! $this->is_data_set( 'booking_date_fixed_time_number_dates', $data ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Specify Number of Dates Fixed Type Selection for Dates & Fixed Time Booking Type', 'woocommerce-booking' ), 400 );
							}

							if ( ! is_numeric( $data['booking_date_fixed_time_number_dates'] ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Fixed Dates for Dates & Fixed Time Booking Type must be numeric', 'woocommerce-booking' ), 400 );
							}

							if ( (int) $data['booking_date_fixed_time_number_dates'] < 2 ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Fixed Dates for Dates & Fixed Time Booking Type must not be less than 2', 'woocommerce-booking' ), 400 );
							}

							$obj->multidates_fixed_number = (int) $data['booking_date_fixed_time_number_dates'];
						} else {
							if ( $action_update_booking_product ) {
								$obj->multidates_fixed_number = $current_data['_bkap_multidates_fixed_number'];
							}
						}

						if ( 'range' === $data['booking_date_fixed_time_selection'] ) {
							if ( ! $this->is_data_set( 'booking_date_fixed_time_min_dates', $data ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Specify the Minimum Dates for Range based Dates & Fixed Time Selection', 'woocommerce-booking' ), 400 );
							}

							if ( (int) $data['booking_date_fixed_time_min_dates'] < 1 ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Minimum Dates for Range based Dates & Fixed Time Selection must be greater than 0', 'woocommerce-booking' ), 400 );
							}

							if ( ! $this->is_data_set( 'booking_date_fixed_time_max_dates', $data ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Specify the Maximum Dates for Range based Dates & Fixed Time Selection', 'woocommerce-booking' ), 400 );
							}

							if ( (int) $data['booking_date_fixed_time_max_dates'] < 1 ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Maximum Dates for Range based Dates & Fixed Time Selection must be greater than 0', 'woocommerce-booking' ), 400 );
							}

							$obj->multidates_range_min = $data['booking_date_fixed_time_min_dates'];
							$obj->multidates_range_max = $data['booking_date_fixed_time_max_dates'];
						} else {
							if ( $action_update_booking_product ) {
								$obj->multidates_range_min = $current_data['_bkap_multidates_range_min'];
								$obj->multidates_range_max = $current_data['_bkap_multidates_range_max'];
							}
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->multidates_type         = $current_data['_bkap_multidates_type'];
							$obj->multidates_fixed_number = $current_data['_bkap_multidates_fixed_number'];
							$obj->multidates_range_min    = $current_data['_bkap_multidates_range_min'];
							$obj->multidates_range_max    = $current_data['_bkap_multidates_range_max'];
						}
					}

					// Inline Calendar.
					if ( $this->is_data_set( 'enable_inline_calendar', $data ) ) {

						if ( 'on' === $data['enable_inline_calendar'] || 'off' === $data['enable_inline_calendar'] ) {
							$obj->enable_inline = ( 'on' === $data['enable_inline_calendar'] ? 'on' : '' );
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for enable_inline_calendar parameter', 'woocommerce-booking' ), 400 );
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->enable_inline = $current_data['_bkap_enable_inline'];
						}
					}

					// Purchase without Date.
					if ( $this->is_data_set( 'purchase_without_date', $data ) ) {

						if ( 'on' === $data['purchase_without_date'] || 'off' === $data['purchase_without_date'] ) {
							$obj->purchase_wo_date = ( 'on' === $data['purchase_without_date'] ? 'on' : '' );
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for purchase_without_date parameter', 'woocommerce-booking' ), 400 );
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->purchase_wo_date = $current_data['_bkap_purchase_wo_date'];
						}
					}

					// Requires Confirmation.
					if ( $this->is_data_set( 'booking_requires_confirmation', $data ) ) {

						if ( 'on' === $data['booking_requires_confirmation'] || 'off' === $data['booking_requires_confirmation'] ) {
							$obj->requires_confirmation = ( 'on' === $data['booking_requires_confirmation'] ? 'on' : '' );
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for booking_requires_confirmation parameter', 'woocommerce-booking' ), 400 );
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->requires_confirmation = $current_data['_bkap_requires_confirmation'];
						}
					}

					// Cancel Booking.
					$obj->can_be_cancelled    = new stdClass();
					$current_can_be_cancelled = maybe_unserialize( $current_data['_bkap_can_be_cancelled'] );

					if ( $this->is_data_set( 'booking_can_be_cancelled', $data ) ) {

						if ( 'on' === $data['booking_can_be_cancelled'] || 'off' === $data['booking_can_be_cancelled'] ) {
							$obj->can_be_cancelled->status = ( 'on' === $data['booking_can_be_cancelled'] ? 'on' : '' );
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for booking_can_be_cancelled parameter', 'woocommerce-booking' ), 400 );
						}

						if ( $this->is_data_set( 'booking_can_be_cancelled_duration', $data ) || $this->is_data_set( 'booking_can_be_cancelled_period', $data ) ) {

							if ( ! $this->is_data_set( 'booking_can_be_cancelled_duration', $data ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Cancel Booking Duration Parameter must be set', 'woocommerce-booking' ), 400 );
							}

							if ( ! $this->is_data_set( 'booking_can_be_cancelled_period', $data ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Cancel Booking Period Parameter must be set', 'woocommerce-booking' ), 400 );
							}

							if ( ! is_numeric( $data['booking_can_be_cancelled_duration'] ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Cancellation Period must be numeric', 'woocommerce-booking' ), 400 );
							}

							if ( (int) $data['booking_can_be_cancelled_duration'] < 1 ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Cancellation Period must not be less than 1', 'woocommerce-booking' ), 400 );
							}

							$periods = array(
								'day',
								'hour',
								'minute',
							);

							if ( ! in_array( $data['booking_can_be_cancelled_period'], $periods, true ) ) {
								/* translators: %s: Period Types. */
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Invalid Cancel Booking Period type - Use: %s', 'woocommerce-booking' ), implode( ', ', array_keys( $periods ) ) ), 400 );
							}

							$obj->can_be_cancelled->duration = (int) $data['booking_can_be_cancelled_duration'];
							$obj->can_be_cancelled->period   = $data['booking_can_be_cancelled_period'];
						}
					} else {
						if ( $action_update_booking_product ) {
							if ( is_array( $current_can_be_cancelled ) ) {
								$obj->can_be_cancelled->status   = $current_can_be_cancelled['status'];
								$obj->can_be_cancelled->duration = $current_can_be_cancelled['duration'];
								$obj->can_be_cancelled->period   = $current_can_be_cancelled['period'];
							}
						}
					}

					$return_data['booking_general_settings'] = $obj;

					// Booking Settings for Availability.
					$obj = new stdClass();

					// Advance Booking Period.
					if ( $this->is_data_set( 'booking_advance_period', $data ) ) {

						if ( ! is_numeric( $data['booking_advance_period'] ) ) {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Booking Advance Period must be numeric', 'woocommerce-booking' ), 400 );
						}

						$obj->abp = $data['booking_advance_period'];
					} else {
						if ( $action_update_booking_product ) {
							$obj->abp = $current_data['_bkap_abp'];
						}
					}

					// Maximum Dates for Booking.
					if ( $this->is_data_set( 'booking_maximum_dates', $data ) ) {

						if ( ! is_numeric( $data['booking_maximum_dates'] ) ) {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Booking Maximum Dates must be numeric', 'woocommerce-booking' ), 400 );
						}

						$obj->max_bookable = $data['booking_maximum_dates'];
					} else {
						if ( $action_update_booking_product ) {
							$obj->max_bookable = $current_data['_bkap_max_bookable_days'];
						}
					}

					// Check for extra fields when Booking Type is set to Multiple Nights. Check if new Booking Type has been set in request or get booking type from database.

					if ( ( $this->is_data_set( 'booking_type', $data ) && 'multiple_days' === $data['booking_type'] ) || ( isset( $current_data['_bkap_booking_type'] ) && 'multiple_days' === $current_data['_bkap_booking_type'] ) ) {

						// Maximum Bookings on any Date.
						if ( $this->is_data_set( 'booking_maximum_bookings_on_date', $data ) ) {

							if ( ! is_numeric( $data['booking_maximum_bookings_on_date'] ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Maximum Bookings on Date must be numeric', 'woocommerce-booking' ), 400 );
							}

							$obj->date_lockout = $data['booking_maximum_bookings_on_date'];
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Maximum Bookings on Date must be set. Use: booking_maximum_bookings_on_date', 'woocommerce-booking' ), 400 );
						}

						// Minimum number of nights to book.
						if ( $this->is_data_set( 'booking_minimum_number_nights_book', $data ) ) {

							if ( ! is_numeric( $data['booking_minimum_number_nights_book'] ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Minimum Number of Nights must be numeric', 'woocommerce-booking' ), 400 );
							}

							$obj->min_days_multiple = $data['booking_minimum_number_nights_book'];
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Minimum Number of Nights must be set. Use: booking_minimum_nights_book', 'woocommerce-booking' ), 400 );
						}

						// Maximum number of nights to book.
						if ( $this->is_data_set( 'booking_maximum_number_nights_book', $data ) ) {

							if ( ! is_numeric( $data['booking_maximum_number_nights_book'] ) ) {
								throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Value for Maximum Number of Nights must be numeric', 'woocommerce-booking' ), 400 );
							}

							$obj->max_days_multiple = $data['booking_maximum_number_nights_book'];
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Maximum Number of Nights must be set. Use: booking_maximum_numer_nights_book', 'woocommerce-booking' ), 400 );
						}
					} else {
						if ( $action_update_booking_product ) {
							$obj->date_lockout      = $current_data['_bkap_date_lockout'];
							$obj->min_days_multiple = $current_data['_bkap_multiple_day_min'];
							$obj->max_days_multiple = $current_data['_bkap_multiple_day_max'];
						}
					}

					// Availability by Weekday.
					if ( $this->is_data_set( 'availability_weekday', $data ) ) {

						// Map Day API parameters to Booking parameters in DB.
						$weekdays = array(
							'sunday'    => 'booking_weekday_0',
							'monday'    => 'booking_weekday_1',
							'tuesday'   => 'booking_weekday_2',
							'wednesday' => 'booking_weekday_3',
							'thursday'  => 'booking_weekday_4',
							'friday'    => 'booking_weekday_5',
							'saturday'  => 'booking_weekday_6',
						);

						$lockouts = array(
							'sunday'    => 'weekday_lockout_0',
							'monday'    => 'weekday_lockout_1',
							'tuesday'   => 'weekday_lockout_2',
							'wednesday' => 'weekday_lockout_3',
							'thursday'  => 'weekday_lockout_4',
							'friday'    => 'weekday_lockout_5',
							'saturday'  => 'weekday_lockout_6',
						);

						$prices = array(
							'sunday'    => 'weekday_price_0',
							'monday'    => 'weekday_price_1',
							'tuesday'   => 'weekday_price_2',
							'wednesday' => 'weekday_price_3',
							'thursday'  => 'weekday_price_4',
							'friday'    => 'weekday_price_5',
							'saturday'  => 'weekday_price_6',
						);

						if ( is_array( $data['availability_weekday'] ) ) {

							// Availability is an array of dates with properties: weekday, lockout and price. We can loop through this array, but need to check it is in the right format.

							foreach ( $data['availability_weekday'] as $availability ) {

								if ( ! is_array( $availability ) ) {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Not An Array - Weekday Availability paameter must be an array of dates.', 'woocommerce-booking' ), 400 );
								}

								if ( ! isset( $availability['weekday'] ) && ! isset( $availability['status'] ) && ! isset( $availability['lockout'] ) && ! isset( $availability['price'] ) ) {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Weekday Availability parameter must have the following array properties: weekday, status, lockout, price', 'woocommerce-booking' ), 400 );
								}

								if ( ! isset( $weekdays[ $availability['weekday'] ] ) ) {
									/* translators: %s: Parameter. */
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Weekday value: %s for Weekday Availability is invalid. Use sunday, monday, tuesday, wednesday, thursday, friday, saturday as values for weekday', 'woocommerce-booking' ), $availability['weekday'] ), 400 );
								}

								if ( ! $this->is_data_set( 'status', $availability ) ) {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Status value for Weekday Availability must be set', 'woocommerce-booking' ), 400 );
								}

								if ( 'on' !== $availability['status'] && 'off' !== $availability['status'] ) {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Use either on or off for Status Weekday Availability parameter', 'woocommerce-booking' ), 400 );
								}

								$obj->{$weekdays[ $availability['weekday'] ]} = ( 'on' === $availability['status'] ? 'on' : '' );

								if ( $this->is_data_set( 'lockout', $availability ) ) {
									if ( ! is_numeric( $availability['lockout'] ) ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Lockout value for Weekday Availability must be numeric', 'woocommerce-booking' ), 400 );
									}

									$obj->{$lockouts[ $availability['weekday'] ]} = $availability['lockout'];
								}

								if ( $this->is_data_set( 'price', $availability ) ) {
									if ( ! is_numeric( $availability['price'] ) ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Price value for Weekday Availability must be numeric', 'woocommerce-booking' ), 400 );
									}

									$obj->{$prices[ $availability['weekday'] ]} = $availability['price'];
								}

								if ( 'off' === $availability['status'] ) {
									$obj->{$lockouts[ $availability['weekday'] ]} = '';
									$obj->{$prices[ $availability['weekday'] ]}   = '';
								}
							}
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Weekday Availability parameter must be of array type', 'woocommerce-booking' ), 400 );
						}
					} else {

						if ( $action_update_booking_product ) {

							// Set current data from DB.
							$current_booking_weekdays = maybe_unserialize( $current_data['_bkap_recurring_weekdays'] );

							if ( is_array( $current_booking_weekdays ) ) {
								foreach ( $current_booking_weekdays as $weekday => $status ) {
									$obj->{$weekday} = $status;
								}
							}

							$current_booking_lockouts = maybe_unserialize( $current_data['_bkap_recurring_lockout'] );

							if ( is_array( $current_booking_lockouts ) ) {
								foreach ( $current_booking_lockouts as $lockout => $status ) {
									$obj->{$lockout} = $status;
								}
							}
						}
					}

					// Availability by Dates/Months.
					if ( $this->is_data_set( 'availability_dates_months', $data ) ) {

						$availability_range_types = array(
							'custom_range',
							'specific_dates',
							'range_months',
							'holidays',
						);

						if ( is_array( $data['availability_dates_months'] ) ) {

							// Availability is an array of ranges: Specific Date, Range of Months, Holidays, Custom Rannge.

							foreach ( $data['availability_dates_months'] as $availability ) {

								if ( ! is_array( $availability ) ) {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Not An Array - Dates/Months Availability parameter must be an array of dates', 'woocommerce-booking' ), 400 );
								}

								if ( ! isset( $availability['type'] ) ) {
									/* translators: %s: Availability Range Types. */
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Range Type for Dates/Months Availability must be set - Use: %s', 'woocommerce-booking' ), implode( ', ', array_keys( $availability_range_types ) ) ), 400 );
								}

								if ( ! in_array( $availability['type'], $periods, true ) ) {
									/* translators: %s: Period Types. */
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Range Type for Dates/Months Availability is invalid - Use: %s', 'woocommerce-booking' ), implode( ', ', array_keys( $availability_range_types ) ) ), 400 );
								}

								$specific_dates = '';
								$custom_range   = '';
								$months_range   = '';
								$holidays       = '';

								if ( 'specific_dates' === $availability['type'] ) {

									// Check for Dates.
									$dates = $availability['date'];

									if ( ! is_array( $dates ) ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Not An Array - Dates for Specific Date must be an array of dates', 'woocommerce-booking' ), 400 );
									}

									// Check that all dates in the date array are valid.
									foreach ( $dates as $date ) {
										$status = DateTime::createFromFormat( 'Y-m-d', $date );
										if ( false === $status ) {
											throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the Date for Specific Date in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
										}

										// Ensure date is not in the past.
										$selected_date = strtotime( $date );
										$yesterday     = strtotime( '-1 days' );

										if ( $selected_date <= $yesterday ) {
											/* translators: %s: Selected Date. */
											throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Date: %s for Specific Date cannot be in the past', 'woocommerce-booking' ), $date ), 400 );
										}
									}

									// Separate dates with comma.
									$dates = implode( ',', $dates );

									// Check for Lockouts.
									$lockout = ( isset( $availability['lockout'] ) && '' !== $availability['lockout'] ) ? $availability['lockout'] : 0;

									// Check for Prices.
									$price = ( isset( $availability['price'] ) && '' !== $availability['price'] ) ? $availability['price'] : 0;

									$specific_dates .= $dates . '+' . $lockout . '+' . $price . ';';
								} elseif ( 'custom_range' === $availability['type'] ) {

									// From Date.
									$date_from = $availability['date_from'];
									$status    = DateTime::createFromFormat( 'Y-m-d', $date_from );
									if ( false === $status ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the From-Date for Custom Range in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
									}

									// Ensure From-Date is not in the past.
									$selected_date = strtotime( $date_from );
									$yesterday     = strtotime( '-1 days' );

									if ( $selected_date <= $yesterday ) {
										/* translators: %s: Selected Date. */
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Fom-Date: %s for Custom Range cannot be in the past', 'woocommerce-booking' ), $date_from ), 400 );
									}

									// To Date.
									$date_to = $availability['date_to'];
									$status  = DateTime::createFromFormat( 'Y-m-d', $date_to );
									if ( false === $status ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the To-Date for Custom Range in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
									}

									// Ensure date is not in the past.
									$selected_date = strtotime( $date_to );
									$yesterday     = strtotime( '-1 days' );

									if ( $selected_date <= $yesterday ) {
										/* translators: %s: Selected Date. */
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'To-Date: %s for Custom Range cannot be in the past', 'woocommerce-booking' ), $date_to ), 400 );
									}

									// Start Date must always be less than To Date.
									if ( strtotime( $date_from ) > strtotime( $date_to ) ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'From-Date must be less than To-Date for Custom Range', 'woocommerce-booking' ), 400 );
									}

									// Maximum Booking in Years.
									$maximum_bookiing = ( isset( $availability['maximum_booking'] ) && '' !== $availability['maximum_booking'] ) ? $availability['maximum_booking'] : 0;

									$custom_range .= $date_from . '+' . $date_to . '+' . $maximum_bookiing . ';';
								} elseif ( 'range_months' === $availability['type'] ) {

									$months = array(
										'january'   => 1,
										'february'  => 2,
										'march'     => 3,
										'april'     => 4,
										'may'       => 5,
										'june'      => 6,
										'july'      => 7,
										'august'    => 8,
										'september' => 9,
										'october'   => 10,
										'november'  => 11,
										'december'  => 12,
									);

									// From Month.
									$month_from = $availability['month_from'];

									if ( ! in_array( $availability['month_from'], $months, true ) ) {
										/* translators: %s: Months. */
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'From-Month for Range of Months Type Availability is invalid - Use: %s', 'woocommerce-booking' ), implode( ', ', array_keys( $months ) ) ), 400 );
									}

									// To Month.
									$month_to = $availability['month_to'];

									if ( ! in_array( $availability['month_to'], $months, true ) ) {
										/* translators: %s: Months. */
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'To-Month for Range of Months Type Availability is invalid - Use: %s', 'woocommerce-booking' ), implode( ', ', array_keys( $months ) ) ), 400 );
									}

									if ( $months[ $availability['month_from'] ] > $months[ $availability['month_to'] ] ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'From-Month must be a month not greater than To-Month for Range of Month Type Availability', 'woocommerce-booking' ), 400 );
									}

									// Maximum Booking in Years.
									$maximum_bookiing = ( isset( $availability['maximum_booking'] ) && '' !== $availability['maximum_booking'] ) ? $availability['maximum_booking'] : 0;

									$months_range .= $months[ $month_from ] . '+' . $months[ $month_to ] . '+' . $maximum_bookiing . ';';
								} elseif ( 'holidays' === $availability['type'] ) {

									// Check for Dates.
									$dates = $availability['date'];

									if ( ! is_array( $dates ) ) {
										throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Not An Array - Dates for Holidays must be an array of dates', 'woocommerce-booking' ), 400 );
									}

									// Check that all dates in the date array are valid.
									foreach ( $dates as $date ) {
										$status = DateTime::createFromFormat( 'Y-m-d', $date );
										if ( false === $status ) {
											throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Please provide the Date for Holidays in the Y-m-d format. Ex: 2021-09-11', 'woocommerce-booking' ), 400 );
										}

										// Ensure date is not in the past.
										$selected_date = strtotime( $date );
										$yesterday     = strtotime( '-1 days' );

										if ( $selected_date <= $yesterday ) {
											/* translators: %s: Selected Date. */
											throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', sprintf( __( 'Date: %s for Holidays cannot be in the past', 'woocommerce-booking' ), $date ), 400 );
										}
									}

									// Separate dates with comma.
									$dates = implode( ',', $dates );

									// Maximum Booking in Years.
									$maximum_bookiing = ( isset( $availability['maximum_booking'] ) && '' !== $availability['maximum_booking'] ) ? $availability['maximum_booking'] : 0;

									$specific_dates .= $dates . '+0;';
								} else {
									throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Invalid Range Type Selection', 'woocommerce-booking' ), 400 );
								}
							}
						} else {
							throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Invaid parameter for Availability Type', 'woocommerce-booking' ), 400 );
						}

						$obj->holidays_list = $holidays;
						$obj->specific_list = $specific_dates;
						$obj->month_range   = $months_range;
						$obj->custom_range  = $custom_range;
						// $obj->holiday_range.
					}

					$return_data['clean_settings_data'] = $obj;

					// TODO: Add logic for Duration Based Bookings.
					// Manage Time Availability.
					// Fixed Time Booking.
				}

				return $return_data;
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Updating Product-related Booking Information.
		 *
		 * @since 5.9.1
		 * @param int   $id Product ID.
		 * @param array $data Booking data of items to be updated.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function update_booking_product( $id, $data ) {

			try {

				$product_id = $id;

				$process_data = $this->do_action_booking_product( $id, $data, 'update' );

				if ( is_array( $process_data ) && count( $process_data ) > 0 ) {

					$booking_general_settings = $process_data['booking_general_settings'];
					$clean_settings_data      = $process_data['clean_settings_data'];

					$save_booking = new bkap_booking_box_class();
					$save_booking->setup_data(
						$product_id,
						$booking_general_settings,
						$clean_settings_data,
						array(),
						array(),
						array(),
						array(),
						array()
					);

					// TODO: Find a way to return error messages from the setup_data function above.

					// Return update Product object and properties.
					// TODO: Do we return all product data or just data of updated fields? Returning all product data for the meantime.
					$return_data = $this->get_product( $id );

					return apply_filters( 'bkap_api_product_edit_product_return_data', array_filter( $return_data ) );
				} else {
					throw new WC_API_Exception( 'bkap_api_product_error_do_action_booking_product', __( 'Invalid response gotten from do_action_booking_product function', 'woocommerce-booking' ), 400 );
				}
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}

		/**
		 * Checks for default properties in an array and sets them if not found. This is done to prevent undefined index notice warnings.
		 *
		 * @since 5.9.1
		 * @param array $data     Array of values.
		 * @param array $elements Array of elements that need to be checked in the array of values if they are set, and then be set if they are not.
		 * @return array $data     Returns array of values.
		 */
		public function prepare_data_array( $data, $elements ) {

			if ( ! is_array( $elements ) ) {
				$elem     = $elements;
				$elements = array();
				array_push( $elem, $elements );
			}

			foreach ( $elements as $element ) {
				if ( ! isset( $data[ $element ] ) ) {
					$data[ $element ] = null; // Temporaliy set with null to make it look like the element has been set and avoid notice errors :) .
				}
			}

			return $data;
		}

		/**
		 * Create Bookable Product.
		 *
		 * @since 5.10.1
		 * @param array $data Booking Product Data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function create_product( $data ) {

			try {

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_product_missing_booking_data', __( 'Product data has not been provided to create a new Booking Product', 'woocommerce-booking' ), 400 );
				}

				// Check if user is permitted to create Booking Products.
				if ( ! current_user_can( 'publish_products' ) ) {
					throw new WC_API_Exception( 'bkap_api_product_user_cannot_create_booking', __( 'You do not have permission to create this Booking Product', 'woocommerce-booking' ), 400 );
				}

				$data = apply_filters( 'bkap_api_product_create_booking_product_data', $data, $this );

				$product_data         = array();
				$booking_product_data = array();
				$request_booking_data = array();

				if ( $this->check_if_exists( 'booking', $data ) ) {
					$request_booking_data = $data['booking'];
				} else {
					throw new WC_API_Exception( 'bkap_api_product_booking_data_not_found', __( 'Booking Data not found. Please pass Booking data for this Product in an array with name: booking', 'woocommerce-booking' ), 400 );
				}

				// Perform checks to ensure Product data is provided to create Product via WooCommerce API.
				if ( $this->is_data_set( 'product_title', $data ) ) {
					$product_data['title'] = $data['product_title'];
				} else {
					throw new WC_API_Exception( 'bkap_api_product_missing_product_title', __( 'Product Title is required to create a Booking Product', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'product_price', $data ) ) {

					if ( ! is_numeric( $data['product_price'] ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Price parameter must be numeric', 'woocommerce-booking' ), 400 );
					}

					$product_data['regular_price'] = $data['product_price'];
				} else {
					throw new WC_API_Exception( 'bkap_api_product_missing_product_title', __( 'Product Price is required to create a Booking Product', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'product_type', $data ) ) {
					if ( ! in_array( wc_clean( $data['product_type'] ), array_keys( wc_get_product_types() ), true ) ) {
						/* translators: %s: product types */
						throw new WC_API_Exception( 'bkap_api_product_invalid_product_type', sprintf( __( 'Product Type provided is invalid. Allowed Product Types are: %s', 'woocommerce-booking' ), implode( ', ', array_keys( wc_get_product_types() ) ) ), 400 );
					}

					$product_data['type'] = $data['product_type'];
				} else {
					throw new WC_API_Exception( 'bkap_api_product_missing_product_type', __( 'Product Type is required to create a Booking Product', 'woocommerce-booking' ), 400 );
				}

				if ( $this->check_if_exists( 'product_status', $data ) ) {

					$product_status = $data['product_status'];

					if ( ! is_array( $data['product_status'] ) ) {
						$product_status = (array) $data['product_status'];
					}

					if ( array_diff( $product_status, $this->default_post_status ) ) {
						throw new WC_API_Exception( 'bkap_api_product_invalid_parameter', __( 'Product Status provided is invalid. Use -> draft, pending, publish', 'woocommerce-booking' ), 400 );
					}

					$product_data['status'] = $data['product_status'];
				}

				if ( $this->is_data_set( 'product_description', $data ) ) {
					$product_data['description']       = $data['product_description'];
					$product_data['short_description'] = $data['product_description'];
				}

				// Setup Booking Product data and perform some basic checks.
				$booking_product_data['enable_booking'] = 'on';

				if ( $this->check_if_exists( 'booking_type', $request_booking_data ) ) {

					$booking_type = $request_booking_data['booking_type'];

					if ( ! is_array( $request_booking_data['booking_type'] ) ) {
						$booking_type = (array) $request_booking_data['booking_type'];
					}

					if ( ! class_exists( 'BKAP_API_Bookings' ) ) {
						throw new WC_API_Exception( 'bkap_api_product_booking_class_unavailable', __( 'Booking Class is required', 'woocommerce-booking' ), 400 );
					}

					if ( array_diff( $booking_type, BKAP_API_Bookings::get_bookings_types( 'keys' ) ) ) {
						/* translators: %s: booking types */
						throw new WC_API_Exception( 'bkap_api_product_invalid_booking_type', sprintf( __( 'Booking Type provided is invalid. Allowed Booking Types are: %s', 'woocommerce-booking' ), implode( ', ', array_keys( BKAP_API_Bookings::get_bookings_types( 'keys' ) ) ) ), 400 );
					}

					$booking_product_data['booking_type'] = $request_booking_data['booking_type'];
				} else {
					throw new WC_API_Exception( 'bkap_api_product_missing_required_parameter', __( 'Booking Type is required', 'woocommerce-booking' ), 400 );
				}

				$booking_product_data = array_merge( $booking_product_data, $request_booking_data );

				// Create WooCommerce Product.
				$response = WC()->api->WC_API_Products->create_product( array( 'product' => $product_data ) );

				$product_id = $response['product']['id'];

				if ( ! $this->check_if_exists( $product_id ) ) {
					throw new WC_API_Exception( 'bkap_api_product_error_create_woocommerce_product', __( 'Error encountered while trying to create WooCommerce Product', 'woocommerce-booking' ), 400 );
				}

				$process_data = $this->do_action_booking_product( $product_id, $booking_product_data, 'create' );

				if ( is_array( $process_data ) && count( $process_data ) > 0 ) {

					$booking_general_settings = $process_data['booking_general_settings'];
					$clean_settings_data      = $process_data['clean_settings_data'];

					$save_booking = new bkap_booking_box_class();
					$save_booking->setup_data(
						$product_id,
						$booking_general_settings,
						$clean_settings_data,
						array(),
						array(),
						array(),
						array(),
						array()
					);

					// TODO: Find a way to return error messages from the setup_data function above.

					// Return update Product object and properties.
					// TODO: Do we return all product data or just data of updated fields? Returning all product data for the meantime.
					$return_data = $this->get_product( $product_id );

					return apply_filters( 'bkap_api_product_create_booking_product_return_data', array_filter( $return_data ) );
				} else {
					throw new WC_API_Exception( 'bkap_api_product_error_do_action_booking_product', __( 'Invalid response gotten from do_action_booking_product function', 'woocommerce-booking' ), 400 );
				}
			} catch ( WC_API_Exception $e ) {
				return new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) );
			}
		}
	}
}
