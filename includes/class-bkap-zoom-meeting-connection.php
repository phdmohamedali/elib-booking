<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Connecting to Zoom
 *
 * @author   Tyche Softwares
 * @package  BKAP/Zoom-Connection
 * @category Classes
 * @class    Bkap_Zoom_Meeting_Connection
 */

use \Firebase\JWT\JWT;

if ( ! class_exists( 'Bkap_Zoom_Meeting_Connection' ) ) {
	/**
	 * Class Bkap_Zoom_Meeting_Connection.
	 *
	 * @since   5.2.0
	 */
	class Bkap_Zoom_Meeting_Connection {

		/**
		 * Zoom API KEY
		 *
		 * @var string $zoom_api_key API Key.
		 */
		public $zoom_api_key;

		/**
		 * Zoom API Secret
		 *
		 * @var string $zoom_api_secret API Secret.
		 */
		public $zoom_api_secret;

		/**
		 * Zoom Client ID
		 *
		 * @var string $zoom_client_id Client ID.
		 */
		public $zoom_client_id;

		/**
		 * Zoom Client Secret
		 *
		 * @var string $zoom_client_secret Client Secret.
		 */
		public $zoom_client_secret;

		/**
		 * Zoom Type
		 *
		 * @var $zoom_type Zoom connection type.
		 */
		private $zoom_type; // phpcs:ignore

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
		private $api_url = 'https://api.zoom.us/v2/';

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
		 * @param string $zoom_client_id Zoom Client ID.
		 * @param string $zoom_client_secret Zoom Client Secret.
		 */
		public function __construct( $zoom_api_key = '', $zoom_api_secret = '', $zoom_client_id = '', $zoom_client_secret = '' ) {
			$this->zoom_api_key       = $zoom_api_key;
			$this->zoom_api_secret    = $zoom_api_secret;
			$this->zoom_client_id     = $zoom_client_id;
			$this->zoom_client_secret = $zoom_client_secret;
			$this->zoom_type          = bkap_zoom_connection_type();
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

			$request_url = $this->api_url . $called_function;
			$code        = '';
			switch ( $this->zoom_type ) {
				case 'oauth':
					$code = $this->bkap_get_access_token();
					break;
				case 'jwt':
					$code = $this->generate_jwt_key();
					break;
			}

			$args = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $code,
					'Content-Type'  => 'application/json',
				),
			);

			if ( 'GET' === $request ) {
				$args['body'] = ! empty( $data ) ? $data : array();
				$response     = wp_remote_get( $request_url, $args );
			} elseif ( 'DELETE' === $request ) {
				$args['body']   = ! empty( $data ) ? wp_json_encode( $data ) : array();
				$args['method'] = 'DELETE';
				$response       = wp_remote_request( $request_url, $args );
			} elseif ( 'PATCH' === $request ) {
				$args['body']   = ! empty( $data ) ? wp_json_encode( $data ) : array();
				$args['method'] = 'PATCH';
				$response       = wp_remote_request( $request_url, $args );
			} else {
				$args['body']   = ! empty( $data ) ? wp_json_encode( $data ) : array();
				$args['method'] = 'POST';
				$response       = wp_remote_post( $request_url, $args );
			}

			$response = wp_remote_retrieve_body( $response );

			if ( ! $response ) {
				return false;
			}

			return $response;
		}

		/**
		 * Function to generate JWT.
		 *
		 * @return string
		 */
		private function generate_jwt_key() {
			$key    = $this->zoom_api_key;
			$secret = $this->zoom_api_secret;

			$token = array(
				'iss' => $key,
				'exp' => time() + 3600 // 60 seconds as suggested.
			);

			return JWT::encode( $token, $secret, 'HS256' );
		}

		/**
		 * Function to generate JWT.
		 *
		 * @return string
		 */
		public function bkap_redirect_url() {

			$args = array(
				'response_type' => 'code',
				'client_id'     => $this->zoom_client_id,
				'redirect_uri'  => rawurlencode( bkap_zoom_redirect_url() ),
			);

			$zoom_url = add_query_arg( $args, 'https://zoom.us/oauth/authorize' );

			return $zoom_url;
		}

		/**
		 * Generate access token code from the code.
		 *
		 * @param string $code Code from Zoom.
		 * @return string
		 */
		public function bkap_exchange_code_for_token( $code ) {

			$response = wp_remote_post(
				'https://zoom.us/oauth/token',
				array(
					'body' => array(
						'grant_type'    => 'authorization_code',
						'code'          => $code,
						'client_id'     => $this->zoom_client_id,
						'client_secret' => $this->zoom_client_secret,
						'redirect_uri'  => bkap_zoom_redirect_url(),
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$data = json_decode( wp_remote_retrieve_body( $response ), true );

			return isset( $data['access_token'] ) ? $data : false;
		}

		/**
		 * Function to get access token.
		 *
		 * @return $access_token Access token.
		 */
		public function bkap_get_access_token() {

			$token_expiry = get_option( 'bkap_zoom_token_expiry', 0 );

			if ( $token_expiry > time() ) {
				$access_token = get_option( 'bkap_zoom_access_token', '' );
				return $access_token;
			} else {

				$access_data  = get_option( 'bkap_zoom_access_data' );
				$refresh_data = array(
					'grant_type'    => 'refresh_token',
					'refresh_token' => $access_data['refresh_token'],
					'client_id'     => $this->zoom_client_id,
					'client_secret' => $this->zoom_client_secret,
				);

				$token_url = 'https://zoom.us/oauth/token';

				$response = wp_remote_post(
					$token_url,
					array(
						'body' => $refresh_data,
					)
				);

				if ( ! is_wp_error( $response ) ) {
					$body             = wp_remote_retrieve_body( $response );
					$new_token_data   = json_decode( $body, true );
					$new_access_token = $new_token_data['access_token'];
					update_option( 'bkap_zoom_access_token', $new_access_token );
					update_option( 'bkap_zoom_token_expiry', time() + $new_token_data['expires_in'] );

					return $new_access_token;
				} else {
					// Handle refresh token error.
					return false;
				}
			}
		}

		/**
		 * User Function to List.
		 *
		 * @param int $page Page.
		 *
		 * @return array
		 */
		public function bkap_list_users( $page = 1 ) {
			$list_users_array                = array();
			$list_users_array['page_size']   = 300;
			$list_users_array['page_number'] = absint( $page );
			$list_users_array                = apply_filters( 'bkap_zoom_list_users', $list_users_array );

			return $this->bkap_send_request( 'users', $list_users_array, 'GET' );
		}

		/**
		 * Get A users info by user Id
		 *
		 * @param int $user_id Zoom User Id.
		 *
		 * @return array|bool|string
		 */
		public function bkap_get_user_info( $user_id ) {
			$get_user_info_array = array();
			$get_user_info_array = apply_filters( 'bkap_zoom_get_user_info', $get_user_info_array );

			return $this->bkap_send_request( 'users/' . $user_id, $get_user_info_array );
		}

		/**
		 * Get Meetings
		 *
		 * @param int $host_id Host ID.
		 *
		 * @return array
		 */
		public function bkap_list_meetings( $host_id ) {
			$list_meetings_array              = array();
			$list_meetings_array['page_size'] = 300;
			$list_meetings_array              = apply_filters( 'bkap_zoom_list_meetings', $list_meetings_array );

			return $this->bkap_send_request( 'users/' . $host_id . '/meetings', $list_meetings_array, 'GET' );
		}

		/**
		 * Create A meeting API
		 *
		 * @param array $data Meeting Data.
		 *
		 * @return array|bool|string|void|WP_Error
		 */
		public function bkap_create_meeting( $data = array() ) {

			$post_time  = $data['start_date'];
			$start_time = gmdate( 'Y-m-d\TH:i:s', strtotime( $post_time ) );

			$create_a_meeting_array = array();

			if ( ! empty( $data['alternative_hosts'] ) ) {
				if ( count( $data['alternative_hosts'] ) > 1 ) {
					$alternative_host_ids = implode( ',', $data['alternative_hosts'] );
				} else {
					$alternative_host_ids = $data['alternative_hosts'][0];
				}
			}

			if ( ! empty( $data['recurrence'] ) ) {
				$create_a_meeting_array['recurrence'] = $data['recurrence'];
			}

			$create_a_meeting_array['topic']      = $data['meetingTopic'];
			$create_a_meeting_array['agenda']     = ! empty( $data['agenda'] ) ? $data['agenda'] : '';
			$create_a_meeting_array['type']       = ! empty( $data['type'] ) ? $data['type'] : 2; // Scheduled.
			$create_a_meeting_array['start_time'] = $start_time;
			$create_a_meeting_array['timezone']   = $data['timezone'];
			$create_a_meeting_array['password']   = ! empty( $data['password'] ) ? $data['password'] : '';
			$create_a_meeting_array['duration']   = ! empty( $data['duration'] ) ? $data['duration'] : 60;
			$create_a_meeting_array['settings']   = array(
				'meeting_authentication' => ! empty( $data['meeting_authentication'] ) ? true : false,
				'join_before_host'       => ! empty( $data['join_before_host'] ) ? true : false,
				'host_video'             => ! empty( $data['host_video'] ) ? true : false,
				'participant_video'      => ! empty( $data['participant_video'] ) ? true : false,
				'mute_upon_entry'        => ! empty( $data['mute_upon_entry'] ) ? true : false,
				'auto_recording'         => ! empty( $data['auto_recording'] ) ? $data['auto_recording'] : 'none',
				'alternative_hosts'      => isset( $alternative_host_ids ) ? $alternative_host_ids : '',
			);

			$create_a_meeting_array = apply_filters( 'bkap_zoom_create_meeting', $create_a_meeting_array );
			if ( ! empty( $create_a_meeting_array ) ) {
				return $this->bkap_send_request( 'users/' . $data['userId'] . '/meetings', $create_a_meeting_array, 'POST' );
			} else {
				return;
			}
		}

		/**
		 * Delete A Meeting.
		 *
		 * @param int|string $meeting_id Meeting ID.
		 *
		 * @return array|bool|string|WP_Error
		 */
		public function bkap_delete_meeting( $meeting_id ) {
			return $this->bkap_send_request( 'meetings/' . $meeting_id, false, 'DELETE' );
		}

		/**
		 * Get a Meeting Info
		 *
		 * @param int|string $id Meeting ID.
		 *
		 * @return array
		 */
		public function bkap_get_meeting_info( $id ) {
			$get_meeting_info_array = array();
			$get_meeting_info_array = apply_filters( 'bkap_zoom_get_meeting_info', $get_meeting_info_array );

			return $this->bkap_send_request( 'meetings/' . $id, $get_meeting_info_array, 'GET' );
		}
	}

	/**
	 * Initiating.
	 */
	function bkap_zoom_connection() {
		return Bkap_Zoom_Meeting_Connection::instance();
	}

	bkap_zoom_connection();
}
