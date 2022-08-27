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
		 */
		public function __construct( $zoom_api_key = '', $zoom_api_secret = '' ) {
			$this->zoom_api_key    = $zoom_api_key;
			$this->zoom_api_secret = $zoom_api_secret;
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
			$args        = array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->generate_jwt_key(),
					'Content-Type'  => 'application/json'
				)
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

			return JWT::encode( $token, $secret );
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
