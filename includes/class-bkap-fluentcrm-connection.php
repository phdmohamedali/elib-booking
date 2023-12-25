<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Connecting to FluentCRM
 *
 * @author   Tyche Softwares
 * @package  BKAP/FluentCRM-Connection
 * @category Classes
 * @class    Bkap_Fluentcrm_Connection
 */

if ( ! class_exists( 'Bkap_Fluentcrm_Connection' ) ) {
	/**
	 * Class Bkap_Zoom_Meeting_Connection.
	 *
	 * @since   5.2.0
	 */
	class Bkap_Fluentcrm_Connection {

		/**
		 * Zoom API KEY
		 *
		 * @var string $zoom_api_key API Key.
		 */
		public $fluentcrm_api_name;

		/**
		 * Zoom API Secret
		 *
		 * @var string $zoom_api_secret API Secret.
		 */
		public $fluentcrm_api_key;

		/**
		 * Hold my instance
		 *
		 * @var $_instance Instance of the class.
		 */
		protected static $_instance; // phpcs:ignore

		/**
		 * API endpoint base
		 *
		 * @var string
		 */
		public $fluentcrm_api_url = '';

		/**
		 * Default Tags
		 *
		 * @var array
		 */
		public $events = array( 'Booking Created', 'Booking Updated', 'Booking Deleted', 'Booking Confirmed' );


		/**
		 * Create only one instance so that it may not Repeat
		 *
		 * @since 5.2.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Bkap_Zoom_Meeting_Connection constructor.
		 *
		 * @param string $zoom_api_key Zoom API Key.
		 * @param string $zoom_api_secret Zoom API Secret.
		 */
		public function __construct( $fluentcrm_api_name = '', $fluentcrm_api_key = '', $fluentcrm_api_url = '' ) {

			$this->fluentcrm_api_name = $fluentcrm_api_name;
			$this->fluentcrm_api_key  = $fluentcrm_api_key;
			$this->fluentcrm_api_url  = $fluentcrm_api_url;
		}

		/**
		 * Send request to API
		 *
		 * @param string $called_function Slug.
		 * @param array  $data Data.
		 * @param string $request Request Type.
		 *
		 * @return array|bool|string|WP_Error
		 */
		protected function bkap_send_request( $called_function, $data, $request = 'GET' ) {

			$request_url = $this->fluentcrm_api_url . $called_function;

			$args = array(
				'timeout'     => 45,
				'httpversion' => '1.0',
				'blocking'    => true,
				'body'        => $data,
				'method'      => $request,
			);

			$headers = $this->bkap_get_headers();

			if ( is_array( $headers ) && count( $headers ) > 0 ) {
				$args['headers'] = $headers;
			}

			if ( 'GET' === $request ) {
				$args['body'] = ! empty( $data ) ? $data : '';
				$response     = wp_remote_get( $request_url, $args );
			} elseif ( 'DELETE' === $request || 'PATCH' === $request || 'PUT' === $request ) {
				$args['body']   = ! empty( $data ) ? wp_json_encode( $data ) : array();
				$args['method'] = $request;
				$response       = wp_remote_request( $request_url, $args );
			} else {
				$args['body']   = ! empty( $data ) ? wp_json_encode( $data ) : array();
				$args['method'] = 'POST';
				$response       = wp_remote_post( $request_url, $args );
			}

			if ( ! is_wp_error( $response ) ) {
				$body = wp_remote_retrieve_body( $response );

				if ( $this->is_json( $body ) ) {
					$body = json_decode( $body, true );
				}

				return $body;
			}

			$body['body'] = [ $response->get_error_message() ];

			return $response;
		}

		/**
		 * Function to generate headers.
		 *
		 * @return string
		 */
		public function bkap_get_headers() {

			$name = $this->fluentcrm_api_name;
			$key  = $this->fluentcrm_api_key;

			return apply_filters(
				'bkap_fluentcrm_headers',
				array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . base64_encode( $name . ':' . $key ),
				)
			);
		}

		/**
		 * Get Lists.
		 *
		 * @return bool
		 */
		public function bkap_get_lists( $data = array() ) {

			$endpoint = 'lists';

			if ( isset( $data['id'] ) ) {
				$endpoint .= '/' . $data['id'];
			}
			$params = array();

			return $this->bkap_send_request( $endpoint, $params, 'GET' );
		}

		/**
		 * Adding default list when enabling booking and fluentcrm integration.
		 *
		 * @return bool
		 */
		public function bkap_add_default_list() {

			$endpoint        = 'lists';
			$params['title'] = 'Bookings';
			$params['slug']  = 'bkap-bookings';

			return $this->bkap_send_request( $endpoint, $params, 'POST' );
		}

		/**
		 * Get Tags.
		 *
		 * @return bool
		 */
		public function bkap_get_tags() {

			$endpoint = 'tags';
			$params   = array();

			return $this->bkap_send_request( $endpoint, $params, 'GET' );
		}

		/**
		 * Add Tags.
		 *
		 * @return bool
		 */
		public function bkap_add_tags() {

			$endpoint = 'tags';
			$params   = array();

			$events = $this->events;
			foreach ( $events as $event ) {
				$params['title'] = $event;
				$params['slug']  = $this->bkap_get_slug( $event );
				$this->bkap_send_request( $endpoint, $params, 'POST' );
			}
		}

		/**
		 * Adding tags to contact.
		 *
		 * @param array $data Data.
		 *
		 * @return bool
		 */
		public function bkap_add_tags_to_contact( $data ) {
			$endpoint     = 'subscribers/sync-segments';
			$data['type'] = 'tags';
			$add_tags     = $data['add_tags'];

			foreach ( $this->events as $tag ) {
				if ( in_array( $tag, $add_tags ) ) {
					$data['attach'][] = $this->bkap_get_slug( $tag );
				}
			}

			$this->bkap_send_request( $endpoint, $data, 'POST' );
		}

		/**
		 * Check if contact is already exits
		 *
		 * @param array $data Data.
		 *
		 * @return bool
		 */
		public function bkap_remove_tags( $data ) {

			$endpoint     = 'subscribers/sync-segments';
			$data['type'] = 'tags';
			$remove_tags  = $data['remove_tags'];

			foreach ( $this->events as $tag ) {
				if ( in_array( $tag, $remove_tags ) ) {
					$data['detach'][] = $this->bkap_get_slug( $tag );
				}
			}

			$this->bkap_send_request( $endpoint, $data, 'POST' );
		}

		/**
		 * Check if contact is already exits
		 *
		 * @return bool
		 */
		public function bkap_create_contact( $data = array() ) {

			$endpoint = 'subscribers';

			return $this->bkap_send_request( $endpoint, $data, 'POST' );
		}

		/**
		 * Create Custom Field for Contact.
		 *
		 * @return bool
		 */
		public function bkap_custom_fields() {

			$endpoint = 'custom-fields/contacts';
			$params   = array();
			$response = $this->bkap_send_request( $endpoint, $params, 'GET' );

			$bkap_custom_field = bkap_fluentcrm_custom_fields();

			if ( isset( $response['fields'] ) ) {
				if ( count( $response['fields'] ) > 0 ) {
					$params['fields'] = array_merge( $response['fields'], $bkap_custom_field );
				} else {
					$params['fields'] = $bkap_custom_field;
				}

				$this->bkap_send_request( $endpoint, $params, 'PUT' );
			}
		}

		/**
		 * Adding Note to contact.
		 *
		 * @param array $data Data.
		 *
		 * @return bool 
		 */
		public function bkap_add_note( $data ) {

			$endpoint = 'subscribers/' . $data['id'] . '/notes';

			unset( $data['id'] );

			return $this->bkap_send_request( $endpoint, $data, 'POST' );
		}

		/**
		 * Check if a string is json or not.
		 *
		 * @param mixed $string
		 *
		 * @return bool
		 */
		public function is_json( $string ) {
			json_decode( $string );

			return ( json_last_error() === JSON_ERROR_NONE );
		}

		/**
		 * Get slug for an event
		 *
		 *  @param string $event - event.
		 */
		public function bkap_get_slug( $event ) {
			return strtolower( str_replace( ' ', '-', $event ) );
		}
	}

	/**
	 * Initiating.
	 */
	function bkap_fluentcrm_connection() {
		return Bkap_Fluentcrm_Connection::instance();
	}

	bkap_fluentcrm_connection();
}
