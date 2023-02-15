<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Zapier API that handles requests to the Booking API.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api/Zapier
 * @category    Classes
 * @since       5.11.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Zapier' ) ) {

	/**
	 * Zapier API endpoint.
	 *
	 * @since 5.11.0
	 */
	class BKAP_API_Zapier extends BKAP_API_Bookings {

		/**
		 * Route base.
		 *
		 * @var string $base
		 */
		protected $base = 'bkap/zapier';

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
		 * GET /
		 * GET /customers
		 *
		 * @since 5.11.0
		 * @param array $routes Routes.
		 * @return array
		 */
		public function register_routes( $routes ) {

			// GET root path.
			$routes[ $this->get_bkap_api_endpoint() ] = array(
				array( array( $this, 'get_sample_data' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /customers.
			$routes[ $this->get_bkap_api_endpoint() . '/customers' ] = array(
				array( array( $this, 'get_customers' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /users.
			$routes[ $this->get_bkap_api_endpoint() . '/users' ] = array(
				array( array( $this, 'get_users' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /products.
			$routes[ $this->get_bkap_api_endpoint() . '/products' ] = array(
				array( array( $this, 'get_products' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// GET /fields.
			$routes[ $this->get_bkap_api_endpoint() . '/fields' ] = array(
				array( array( $this, 'get_fields' ), WC_API_Server::READABLE | WC_API_Server::ACCEPT_DATA ),
			);

			// POST|DELETE /subscription.
			$routes[ $this->get_bookings_endpoint() . '/subscription' ] = array(
				array( array( $this, 'create_subscription' ), WC_API_SERVER::CREATABLE | WC_API_Server::ACCEPT_DATA ),
				array( array( $this, 'delete_subscription' ), WC_API_Server::DELETABLE | WC_API_Server::ACCEPT_DATA ),
			);

			return $routes;
		}

		/**
		 * Returns the endpoint/base.
		 *
		 * @since 5.11.0
		 * @return string
		 */
		public function get_bkap_api_endpoint() {
			return '/' . apply_filters( 'bkap_api_zapier_endpoint', $this->base );
		}

		/**
		 * Returns sample data to aid connection in Zapier.
		 *
		 * @since 5.11.0
		 * @return array
		 */
		public function get_sample_data() {
			return array(
				'bkap' => array(
					'connection' => 'success',
				),
			);
		}

		/**
		 * Get all customers.
		 *
		 * @since 5.11.0
		 * @param array $data Array of filters for the response data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_customers( $data = array() ) {

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

				$args = array(
					'fields'  => 'ID',
					'role'    => 'customer',
					'orderby' => 'registered',
					'order'   => 'DESC',
				);

				$customers_per_page = 20;

				if ( $this->is_data_set( 'limit', $filter ) ) {
					$limit = absint( $filter['limit'] );

					if ( $limit > 0 ) {
						$customers_per_page = $limit;
					}
				}

				$page = 1;

				if ( $this->is_data_set( 'page', $filter ) ) {
					$page = absint( $filter['page'] );
				}

				$args['page'] = $page;

				$args['number'] = $customers_per_page;

				$query = new WP_User_Query( $args );

				$customers = array();

				foreach ( $query->get_results() as $customer_id ) {

					if ( ! $this->is_readable( $customer_id ) ) {
						continue;
					}

					$customers[] = current( $this->get_customer( $customer_id, array( 'id', 'full_name' ) ) );
				}

				return $customers;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Get the Customer object for the given ID.
		 *
		 * @since 5.11.0
		 * @param int   $id Customer ID.
		 * @param array $fields Customer fields to return.
		 * @return array
		 */
		public function get_customer( $id, $fields = array() ) {

			// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
			$id = $this->validate_request( $id, 'customer', 'read' );

			if ( is_wp_error( $id ) ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $id );
			}

			$customer      = new WC_Customer( $id );
			$customer_data = array(
				'id'               => $customer->get_id(),
				'email'            => $customer->get_email(),
				'first_name'       => $customer->get_first_name(),
				'last_name'        => $customer->get_last_name(),
				'full_name'        => $customer->get_first_name() . ' ' . $customer->get_last_name(),
				'username'         => $customer->get_username(),
				'role'             => $customer->get_role(),
				'avatar_url'       => $customer->get_avatar_url(),
				'billing_address'  => array(
					'first_name' => $customer->get_billing_first_name(),
					'last_name'  => $customer->get_billing_last_name(),
					'company'    => $customer->get_billing_company(),
					'address_1'  => $customer->get_billing_address_1(),
					'address_2'  => $customer->get_billing_address_2(),
					'city'       => $customer->get_billing_city(),
					'state'      => $customer->get_billing_state(),
					'postcode'   => $customer->get_billing_postcode(),
					'country'    => $customer->get_billing_country(),
					'email'      => $customer->get_billing_email(),
					'phone'      => $customer->get_billing_phone(),
				),
				'shipping_address' => array(
					'first_name' => $customer->get_shipping_first_name(),
					'last_name'  => $customer->get_shipping_last_name(),
					'company'    => $customer->get_shipping_company(),
					'address_1'  => $customer->get_shipping_address_1(),
					'address_2'  => $customer->get_shipping_address_2(),
					'city'       => $customer->get_shipping_city(),
					'state'      => $customer->get_shipping_state(),
					'postcode'   => $customer->get_shipping_postcode(),
					'country'    => $customer->get_shipping_country(),
				),
			);

			// Remove other meta_keys and return only the ones specified in $fields variable.
			if ( is_array( $fields ) && count( $fields ) > 0 ) {

				foreach ( $customer_data as $key => $data ) {
					if ( ! in_array( $key, $fields, true ) ) {
						unset( $customer_data[ $key ] );
					}
				}
			}

			// Run $booking through filter hook to incorporate external changes.
			$customer_data = apply_filters( 'bkap_api_zapier_customer_response', $customer_data, $customer );

			return array( 'customer' => $customer_data );
		}

		/**
		 * Get all products.
		 *
		 * @since 5.11.0
		 * @param array $data Array of filters for the response data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_products( $data = array() ) {

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
						$filter['page'] = ( $var_page > 0 ) ? ( (int) $var_page - 1 ) : 0;
					}
				}

				$args = array(
					'fields'      => 'ids',
					'post_type'   => 'product',
					'post_status' => 'publish',
					'order'       => 'ASC',
					'orderby'     => 'title',
					'meta_query'  => array( // phpcs:ignore
						array(
							'key'     => '_bkap_enable_booking',
							'value'   => 'on',
							'compare' => '=',
						),
					),
				);

				$products_per_page = 20;

				if ( $this->is_data_set( 'limit', $filter ) ) {
					$limit = absint( $filter['limit'] );

					if ( $limit > 0 ) {
						$products_per_page = $limit;
					}
				}

				$page = 0;

				if ( $this->is_data_set( 'page', $filter ) ) {
					$page = absint( $filter['page'] );
				}

				$args['offset'] = $page * $products_per_page;

				$args['posts_per_page'] = $products_per_page;

				$query = new WP_Query( $args );

				$products = array();

				foreach ( $query->posts as $product_id ) {

					if ( ! $this->is_readable( $product_id ) ) {
						continue;
					}

					$products[] = current( $this->get_product( $product_id, array( 'id', 'title' ) ) );
				}

				return $products;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Get the Product object for the given ID.
		 *
		 * @since 5.11.0
		 * @param int   $id Product ID.
		 * @param array $fields Product fields to return.
		 * @return array
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_product( $id, $fields = array() ) {

			try {
				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
				$id = $this->validate_request( $id, 'product', 'read' );

				if ( is_wp_error( $id ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $id );
				}

				$product = wc_get_product( $id );

				if ( ! is_object( $product ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_invalid_product_id', __( 'Product ID provided is invalid.', 'woocommerce-booking' ), 400 );
				}

				$product_data = array(
					'title'             => $product->get_name(),
					'id'                => $product->get_id(),
					'type'              => $product->get_type(),
					'status'            => $product->get_status(),
					'permalink'         => $product->get_permalink(),
					'sku'               => $product->get_sku(),
					'price'             => $product->get_price(),
					'regular_price'     => $product->get_regular_price(),
					'sale_price'        => $product->get_sale_price() ? $product->get_sale_price() : null,
					'price_html'        => $product->get_price_html(),
					'stock_quantity'    => $product->get_stock_quantity(),
					'in_stock'          => $product->is_in_stock(),
					'visible'           => $product->is_visible(),
					'on_sale'           => $product->is_on_sale(),
					'product_url'       => $product->is_type( 'external' ) ? $product->get_product_url() : '',
					'shipping_required' => $product->needs_shipping(),
					'shipping_taxable'  => $product->is_shipping_taxable(),
					'shipping_class'    => $product->get_shipping_class(),
					'shipping_class_id' => ( 0 !== $product->get_shipping_class_id() ) ? $product->get_shipping_class_id() : null,
					'description'       => wpautop( do_shortcode( $product->get_description() ) ),
					'short_description' => apply_filters( 'woocommerce_short_description', $product->get_short_description() ),
					'parent_id'         => $product->get_parent_id(),
					'categories'        => wc_get_object_terms( $product->get_id(), 'product_cat', 'name' ),
					'tags'              => wc_get_object_terms( $product->get_id(), 'product_tag', 'name' ),
					'featured_src'      => wp_get_attachment_url( get_post_thumbnail_id( $product->get_id() ) ),
				);

				// Remove other meta_keys and return only the ones specified in $fields variable.
				if ( is_array( $fields ) && count( $fields ) > 0 ) {

					foreach ( $product_data as $key => $data ) {
						if ( ! in_array( $key, $fields, true ) ) {
							unset( $product_data[ $key ] );
						}
					}
				}

				// Run $booking through filter hook to incorporate external changes.
				$product_data = apply_filters( 'bkap_api_zapier_product_response', $product_data, $product );

				return array( 'product' => $product_data );
			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Get all users.
		 *
		 * @since 5.11.0
		 * @param array $data Array of filters for the response data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_users( $data = array() ) {

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
						$filter['page'] = ( $var_page > 0 ) ? ( (int) $var_page - 1 ) : 0;
					}
				}

				$args = array(
					'fields'  => array(
						'ID',
						'display_name',
						'user_email',
					),
					'orderby' => 'display_name',
					'order'   => 'ASC',
				);

				$users_per_page = 20;

				if ( $this->is_data_set( 'limit', $filter ) ) {
					$limit = absint( $filter['limit'] );

					if ( $limit > 0 ) {
						$users_per_page = $limit;
					}
				}

				$page = 0;

				if ( $this->is_data_set( 'page', $filter ) ) {
					$page = absint( $filter['page'] );
				}

				$args['offset'] = $page * $users_per_page;
				$args['number'] = $users_per_page;

				// Return all users for only administrators.
				if ( ! current_user_can( 'manage_options' ) ) {
					$args['include'] = get_current_user_id();
				}

				$users           = get_users( $args );
				$users_to_return = array();

				if ( '' !== $users && is_array( $users ) && count( $users ) > 0 ) {
					foreach ( $users as $user ) {
						$users_to_return[] = array(
							'id'    => (int) $user->ID,
							'name'  => $user->display_name,
							'email' => $user->user_email,
						);
					}
				}

				return $users_to_return;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Generate Zapier Dynamic Fields object.
		 *
		 * @since 5.11.0
		 * @param array $data Array of parameters required for Field Object generation, Ex. Product ID.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function get_fields( $data = array() ) {

			try {

				$fields           = array();
				$action           = '';
				$product_id       = '';
				$help_text_suffix = '';

				// Check for Action.
				if ( $this->is_data_set( 'action', $data ) ) {
					$action = $data['action'];
				}

				// Zapier sends filter requests as $_GET parameters.
				if ( ! $this->is_data_set( 'action', $data ) ) {
					$var_action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; // phpcs:ignore

					if ( '' !== $var_action ) {
						$action = $var_action;
					}
				}

				// Check for Product ID.
				if ( $this->is_data_set( 'product_id', $data ) ) {
					$product_id = $data['product_id'];
				}

				if ( 'create' === $action ) {
					// Zapier sends filter requests as $_GET parameters.
					if ( ! $this->is_data_set( 'product_id', $data ) ) {
						$var_product_id = isset( $_GET['product_id'] ) ? sanitize_text_field( wp_unslash( $_GET['product_id'] ) ) : ''; // phpcs:ignore

						if ( '' !== $var_product_id ) {
							$product_id = $var_product_id;
						}
					}
				} elseif ( 'update' === $action ) {
					// Zapier sends filter requests as $_GET parameters.
					if ( ! $this->is_data_set( 'booking_id', $data ) ) {
						$var_booking_id = isset( $_GET['booking_id'] ) ? sanitize_text_field( wp_unslash( $_GET['booking_id'] ) ) : ''; // phpcs:ignore

						if ( '' !== $var_booking_id ) {
							$booking_id = $var_booking_id;
							$booking    = new BKAP_Booking( $booking_id );
							$product_id = $booking->get_product_id();
						}
					}
					$help_text_suffix = ' Leave blank to retain current data.';
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_missing_action', __( 'Action parameter is missing.', 'woocommerce-booking' ), 400 );
				}

				if ( '' === $product_id ) {
					throw new WC_API_Exception( 'bkap_api_zapier_missing_product_id', __( 'Product ID is missing.', 'woocommerce-booking' ), 400 );
				}

				// Use WooCommerce's validate_request() function to ensure ID is valid and user has permission to read.
				$product_id = $this->validate_request( $product_id, 'product', 'read' );

				if ( is_wp_error( $product_id ) ) {
					return BKAP_API_Zapier_Settings::bkap_api_zapier_error( $product_id );
				}

				$product = wc_get_product( $product_id );

				if ( ! is_object( $product ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_invalid_product_id', __( 'Product ID provided is invalid.', 'woocommerce-booking' ), 400 );
				}

				$booking_type = get_post_meta( $product_id, '_bkap_booking_type', true );

				if ( '' === $booking_type ) {
					throw new WC_API_Exception( 'bkap_api_zapier_invalid_product_id', __( 'Booking Type cannot be retrieved from Product ID provided.', 'woocommerce-booking' ), 400 );
				}

				$fields = array(
					array(
						'key'      => 'order_id',
						'label'    => 'Order ID',
						'type'     => 'string',
						'required' => false,
						'helpText' => 'If Booking should be created for an existing Oder, enter the Order ID here. Leave blank if Booking is for a new order.' . $help_text_suffix,
					),
					array(
						'key'      => 'quantity',
						'label'    => 'Booking Quantity',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the Booking Quantity.' . $help_text_suffix,
					),
					array(
						'key'      => 'start_date',
						'label'    => 'Booking Start Date',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the Start Date for the Booking. Date format: YYYY-MM-DD. Ex. ' . gmdate( 'Y-m-d' ) . '.' . $help_text_suffix,
					),
					array(
						'key'      => 'end_date',
						'label'    => 'Booking End Date',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the End Date for the Booking. Date format: YYYY-MM-DD. Ex. ' . gmdate( 'Y-m-d' ) . '.' . $help_text_suffix,
					),
					array(
						'key'      => 'price',
						'label'    => 'Booking Price',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the Booking Price.' . $help_text_suffix,
					),
				);

				if ( 'multiple_days' === $booking_type ) {
					$fields[] = array(
						'key'      => 'fixed_block',
						'label'    => 'Fixed Block',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the Fixed BLock value for the Multiple Nights Booking Type Product' . $help_text_suffix,
					);
				} elseif ( 'date_time' === $booking_type || 'duration_time' === $booking_type ) {
					$fields[] = array(
						'key'      => 'start_time',
						'label'    => 'Booking Start Time',
						'type'     => 'string',
						'required' => 'create' === $action,
						'helpText' => 'Enter the Start Time for the Booking. Please provide the Start Time in the H:i format. Ex: 23:15' . $help_text_suffix,
					);

					if ( 'date_time' === $booking_type ) {
						$fields[] = array(
							'key'      => 'end_time',
							'label'    => 'Booking End Time',
							'type'     => 'string',
							'required' => 'create' === $action,
							'helpText' => 'Enter the End Time for the Booking. Please provide the End Time in the H:i format. Ex: 23:15' . $help_text_suffix,
						);
					}

					if ( 'duration_time' === $booking_type ) {
						$fields[] = array(
							'key'      => 'duration',
							'label'    => 'Booking Duration',
							'type'     => 'string',
							'required' => 'create' === $action,
							'helpText' => 'Enter the Booking Duration' . $help_text_suffix,
						);
					}
				}

				return $fields;

			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Checks if variable is available or if element exists in array.
		 *
		 * @since 5.11.0
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
		 * @since 5.11.0
		 * @param  string $key Index of array where variable is to be set.
		 * @param  string $element Variable to check whether it exists.
		 * @param  array  $data Array where element is to be added if it exists.
		 * @return array
		 */
		public function check_if_exists_and_set( $key, $element, $data ) {

			if ( $this->check_if_exists( $element ) && ! isset( $data[ $key ] ) ) {
				$data[ $key ] = $element;
			}

			return $data;
		}

		/**
		 * Checks the permission for the user for a request.
		 *
		 * @since 5.11.0
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
		 * @since 5.11.0
		 * @param string $key  The key int array where data is to be checked if it exists in the request.
		 * @param array  $data The request array containing list of request components.
		 * @return bool  true if the data in the request is available.
		 */
		public function is_data_set( $key, $data ) {
			return ( isset( $data[ $key ] ) && '' !== $data[ $key ] );
		}

		/**
		 * Create Zapier Subscription for Triggers.
		 *
		 * @since 5.11.0
		 * @param array $data Booking Data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function create_subscription( $data ) {

			try {

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_zapier_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_missing_subscription_data', __( 'Subscription data has not been provided to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				$data = apply_filters( 'bkap_api_zapier_subscription_create_subscription_data', $data, $this );

				$hook_url    = '';
				$hook_action = '';
				$hook_label  = '';
				$hook_user   = '';

				if ( $this->is_data_set( 'hookUrl', $data ) ) {

					$hook_url = $data['hookUrl'];
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_url', __( 'Hook URL is required to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				if ( $this->is_data_set( 'hookAction', $data ) ) {

					$hook_action = $data['hookAction'];
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_action', __( 'Hook Action is required to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				if ( $this->is_data_set( 'hookLabel', $data ) ) {

					$hook_label = $data['hookLabel'];
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_label', __( 'Hook Label is required to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				if ( $this->is_data_set( 'hookUser', $data ) ) {

					$hook_user = $data['hookUser'];

					$is_user_valid = (bool) get_users(
						array(
							'include' => $hook_user,
							'fields'  => 'ID',
						)
					);

					if ( ! $is_user_valid ) {
						throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_user', __( 'Hook User provided is invalid', 'woocommerce-booking' ), 400 );
					}
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_user', __( 'Hook User is required to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				// Update hook subscription in the database.
				$hook_id = 'z_' . $hook_action . '_' . strtotime( 'now' );

				$response = BKAP_API_Zapier_Settings::bkap_api_zapier_save_records_to_db(
					BKAP_API_Zapier_Settings::$subscription_key,
					$hook_action,
					array(
						'id'         => $hook_id,
						'url'        => $hook_url,
						'label'      => $hook_label,
						'created_by' => $hook_user,
						'action'     => $hook_action,
					),
					false
				);

				if ( $response ) {
					BKAP_API_Zapier_Log::add_log( 'Create Subscription', "Subscription has been created. ID: #{$hook_id}, Trigger: {$hook_action}, Label: {$hook_label}", $data );
					return array(
						'id'      => $hook_id,
						'message' => 'success',
					);
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_save_error', __( 'Error encountered while trying to save Subscription on WooCommerce Store', 'woocommerce-booking' ), 400 );
				}
			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}

		/**
		 * Delete Zapier Subscription for Triggers.
		 *
		 * @since 5.11.0
		 * @param array $data Booking Data.
		 * @return array|WP_Error
		 * @throws WC_API_Exception If error encountered.
		 */
		public function delete_subscription( $data ) {

			try {

				if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_zapier_enabled() ) {
					throw new WC_API_Exception( 'bkap_api_zapier_error', __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! isset( $data ) ) {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_missing_subscription_data', __( 'Subscription data has not been provided to create a Subscription', 'woocommerce-booking' ), 400 );
				}

				$data = apply_filters( 'bkap_api_zapier_subscription_delete_subscription_data', $data, $this );

				$hook_id     = '';
				$hook_action = '';

				if ( $this->is_data_set( 'hookId', $data ) ) {

					$hook_id = $data['hookId'];
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_url', __( 'Hook URL is required to delete a Subscription', 'woocommerce-booking' ), 400 );
				}

				if ( $this->is_data_set( 'hookAction', $data ) ) {

					$hook_action = $data['hookAction'];
				} else {
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_invalid_hook_action', __( 'Hook Action is required to delete a Subscription', 'woocommerce-booking' ), 400 );
				}

				$subscriptions                = (array) BKAP_API_Zapier_Settings::bkap_api_zapier_get_subscriptions();
				$subscription_for_hook_action = isset( $subscriptions->{$hook_action} ) ? $subscriptions->{$hook_action} : '';
				$is_subscription_key_found    = false;

				if ( '' !== $subscription_for_hook_action && is_array( $subscription_for_hook_action ) && count( $subscription_for_hook_action ) > 0 ) {
					foreach ( $subscription_for_hook_action as $index => $subscription ) {
						if ( $subscription->id === $hook_id ) {
							$is_subscription_key_found = true;
							unset( $subscription_for_hook_action[ $index ] );
						}
					}
				}

				if ( ! $is_subscription_key_found ) {
					/* translators: %s: Hook ID */
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_error', sprintf( __( 'Subscription #%s cannot be found', 'woocommerce-booking' ), $hook_id ), 400 );
				}

				$subscriptions->{$hook_action} = $subscription_for_hook_action;

				$delete_action = BKAP_API_Zapier_Settings::bkap_api_zapier_save_records_to_db(
					BKAP_API_Zapier_Settings::$subscription_key,
					'_all',
					$subscriptions
				);

				if ( ! $delete_action ) {
					/* translators: %s Hook ID */
					throw new WC_API_Exception( 'bkap_api_zapier_subscription_error', sprintf( __( 'Error encountered while trying to delete Subscription #%s', 'woocommerce-booking' ), $hook_id ), 400 );
				}

				BKAP_API_Zapier_Log::add_log( 'Delete Subscription', "Subscription #{$hook_id} has been deleted for {$hook_action} Trigger", $data );

				return array(
					'message' => 'success',
				);
			} catch ( WC_API_Exception $e ) {
				return BKAP_API_Zapier_Settings::bkap_api_zapier_error( new WP_Error( $e->getErrorCode(), $e->getMessage(), array( 'status' => $e->getCode() ) ) );
			}
		}
	}
}
