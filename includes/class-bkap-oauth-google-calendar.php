<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Google Calendar API for WooCommerce Booking and Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @category Classes
 * @since 5.1.0
 */

if ( ! class_exists( 'BKAP_OAuth_Google_Calendar' ) ) {

	/**
	 * Class for Google Calendar API for WooCommerce Booking and Appointment Plugin
	 * BKAP_OAuth_Gcal
	 *
	 * @class BKAP_OAuth_Google_Calendar
	 */
	class BKAP_OAuth_Google_Calendar {

		/**
		 * Construct
		 *
		 * @param int $product_id Product ID.
		 * @param int $user_id User ID.
		 */
		public function __construct( $product_id = 0, $user_id = 1 ) {

			$this->product_id = $product_id;
			$this->user_id    = $user_id;
			$this->is_product = ( $product_id ) ? true : false; // If called for product the true else false.
			$this->gcal       = new BKAP_Gcal(); // For using log function of BKAP_Gcal class.

			// Adding Client ID and Client Secret data to Connection.
			add_action( 'bkap_update_google_client_id_secret', array( $this, 'bkap_update_google_client_callback' ), 10, 1 );
		}

		/**
		 * This will return the uri to which the user will be redirected after consent screen.
		 *
		 * @since 5.1.0
		 */
		public function bkap_get_redirect_uri() {

			$redirect_uri = '';
			if ( $this->is_product ) {
				$query_args   = array(
					'post'              => $this->product_id,
					'action'            => 'edit',
					'bkap-google-oauth' => $this->product_id,
				);
				$redirect_uri = add_query_arg( $query_args, admin_url( 'post.php' ) );

				if ( ! is_admin() && current_user_can( 'dokan_edit_product' ) && function_exists( 'dokan_edit_product_url' ) ) {
					$query_args   = array( 'bkap-google-oauth' => $this->product_id );
					$redirect_uri = add_query_arg( $query_args, dokan_edit_product_url( $this->product_id ) );
				}
			} else {
				$query_args   = array(
					'page'              => 'woocommerce_booking_page',
					'action'            => 'calendar_sync_settings',
					'bkap-google-oauth' => '1',
					'post_type'         => 'bkap_booking',
				);
				$redirect_uri = add_query_arg( $query_args, admin_url( 'edit.php' ) );
			}

			return $redirect_uri;
		}

		/**
		 * This will set the Access Token information in transient.
		 *
		 * @since 5.1.0
		 */
		public function bkap_set_access_token( $access_token ) {

			$global_product = ( $this->is_product ) ? '_' . $this->product_id : '';
			set_transient( 'bkap_gcal_access_token' . $global_product, $access_token, 3600 );
		}

		/**
		 * This will return the the Access Token information.
		 *
		 * @since 5.1.0
		 */
		public function bkap_get_access_token() {

			$product_global = ( $this->is_product ) ? '_' . $this->product_id : '';
			$access_token   = get_transient( 'bkap_gcal_access_token' . $product_global );

			return $access_token;
		}

		/**
		 * This will return the Refresh Token information.
		 *
		 * @since 5.1.0
		 */
		public function bkap_get_refresh_token() {

			if ( $this->is_product ) {
				$refresh_token = get_post_meta( $this->product_id, '_bkap_gcal_refresh_token', true );
			} else {
				$refresh_token = get_option( 'bkap_gcal_refresh_token' );
			}

			return $refresh_token;
		}

		/**
		 * This will return the Calendar options.
		 *
		 * @since 5.1.0
		 */
		public function bkap_get_calendar_list_options() {

			$this->bkap_init_service();

			$options = array( '' => __( 'Select a calendar from the list', 'woocommerce-booking' ) );

			if ( $this->bkap_is_integration_active() ) {
				try {
					$calendar_list = $this->service->calendarList->listCalendarList();

					while ( true ) {
						foreach ( $calendar_list->getItems() as $calendar_list_entry ) {
							$summary        = $calendar_list_entry->getSummary();
							$id             = $calendar_list_entry->getId();
							$options[ $id ] = $summary;
						}
						$page_token = $calendar_list->getNextPageToken();
						if ( $page_token ) {
							$opt_params    = array( 'pageToken' => $page_token );
							$calendar_list = $service->calendarList->listCalendarList( $opt_params ); // phpcs:ignore
						} else {
							break;
						}
					}
				} catch ( Exception $e ) {
					$this->gcal->log( 'Error while getting the list of calendars: ' . $e->getMessage() );
				}
			}

			return $options;
		}

		/**
		 * This function is responsible for the connecting to the google and it returns an authorized API client.
		 *
		 * @since 5.1.0
		 */
		public function bkap_init_service() {

			if ( empty( $this->service ) ) {
				$this->service = new Google_Service_Calendar( $this->get_client() );
			}
		}

		/**
		 * This function is responsible for the connecting to the google and it returns an authorized API client.
		 *
		 * @since 5.1.0
		 */
		public function get_client() {

			if ( ! $this->bkap_check_gcal_vendor_files() ) {
				return;
			}

			$client = new Google_Client();
			$client->addScope( Google_Service_Calendar::CALENDAR );
			$client->setAccessType( 'offline' );
			$client->setRedirectUri( $this->bkap_get_redirect_uri() /*'http://localhost/hotel/wp-admin/edit.php?post_type=bkap_booking&page=woocommerce_booking_page'*/ );

			do_action( 'bkap_update_google_client_id_secret', $client ); // Adding client and secret data to $client.

			$access_token  = $this->bkap_get_access_token();
			$refresh_token = $this->bkap_get_refresh_token();

			// https://github.com/googleapis/google-api-php-client/issues/1475#issuecomment-492378984 .
			$client->setApprovalPrompt( 'force' );
			$client->setIncludeGrantedScopes( true );

			if ( $refresh_token && empty( $access_token ) ) {

				$access_token = $this->bkap_renew_access_token( $refresh_token, $client );

				if ( $access_token && isset( $access_token['access_token'] ) ) {

					unset( $access_token['refresh_token'] ); // unset this since we store it in an option.
					$this->bkap_set_access_token( $access_token );
				} else {
					$unable_to_fetch = __( 'Unable to fetch access token with refresh token. Google sync disabled until re-authenticated. ', 'woocommerce-booking' );

					$error            = isset( $access_token['error'] ) ? $access_token['error'] . ' - ' : '';
					$error           .= isset( $access_token['error_description'] ) ? $access_token['error_description'] : '';
					$error            = ( '' !== $error ) ? 'Error : ' . $error : '';
					$unable_to_fetch .= $error;

					$this->gcal->bkap_log_mail( $unable_to_fetch, $this->product_id );
					$this->gcal->log( $unable_to_fetch );
					$client->setClientId( null );
					$client->setClientSecret( null );
					if ( $this->is_product ) {
						delete_post_meta( $this->product_id, '_bkap_gcal_refresh_token' );
						delete_transient( 'bkap_gcal_access_token_' . $this->product_id );
					} else {
						delete_option( 'bkap_gcal_refresh_token' );
						delete_transient( 'bkap_gcal_access_token' );
					}
				}
			}
			// It may be empty, e.g. in case refresh token is empty.
			if ( ! empty( $access_token ) ) {
				$access_token['refresh_token'] = $refresh_token;
				try {
					$client->setAccessToken( $access_token );
				} catch ( InvalidArgumentException $e ) {
					$this->gcal->log( 'Invalid access token. Reconnect with Google necessary.' );
				}
			}

			return $client;
		}

		/**
		 * This function is responsible for the connecting to the google and it returns an authorized API client.
		 *
		 * @param obj $client Client Object.
		 * @since 5.1.0
		 */
		public function bkap_update_google_client_callback( $client ) {

			if ( $this->is_product ) {
				$oauth_settings = get_post_meta( $this->product_id, '_bkap_calendar_oauth_integration', true );
			} else {
				$oauth_settings = get_option( 'bkap_calendar_oauth_integration', null );
			}

			if ( $oauth_settings && ! empty( $oauth_settings['client_id'] ) && ! empty( $oauth_settings['client_secret'] ) ) {
				$client->setClientId( $oauth_settings['client_id'] );
				$client->setClientSecret( $oauth_settings['client_secret'] );
			}
		}

		/**
		 * This function is responsible for the connecting to the google and it returns an authorized API client.
		 *
		 * @since 5.1.0
		 */
		public function bkap_is_integration_active() {

			if ( $this->is_product ) {
				$integration_mode = get_post_meta( $this->product_id, '_bkap_gcal_integration_mode', true );
				if ( 'oauth' !== $integration_mode ) {
					return false;
				}
				$refresh_token = get_post_meta( $this->product_id, '_bkap_gcal_refresh_token', true );
			} else {
				$integration_mode = get_option( 'bkap_calendar_sync_integration_mode' );
				if ( 'oauth' !== $integration_mode ) {
					return false;
				}
				$refresh_token = get_option( 'bkap_gcal_refresh_token' );
			}

			return ! empty( $refresh_token );
		}

		/**
		 * Renew access token with refresh token. Must pass through connect.woocommerce.com middleware.
		 *
		 * @param string        $refresh_token Refresh Token.
		 * @param Google_Client $client Google Client Object.
		 *
		 * @return array
		 */
		private function bkap_renew_access_token( $refresh_token, $client ) {

			if ( $client->getClientId() ) {
				return $client->fetchAccessTokenWithRefreshToken( $refresh_token );
			}
		}

		/**
		 * Get google login url from connect.woocommerce.com.
		 *
		 * @return string
		 */
		public function bkap_get_google_auth_url() {

			$client = $this->get_client();
			if ( $client->getClientId() ) {
				return $client->createAuthUrl();
			}

			return add_query_arg(
				array(
					'redirect' => $this->bkap_get_redirect_uri(),
				),
				$client->createAuthUrl()
			);
		}

		/**
		 * Process the oauth redirect.
		 *
		 * @return void
		 */
		public function bkap_oauth_redirect() {

			$client = $this->get_client();
			$client->authenticate( $_GET['code'] ); // phpcs:ignore
			$access_token = $client->getAccessToken();

			try {
				$client->setAccessToken( $access_token ); // $access_token is an array of access_token, expires_in, scope, token_type, created, refresh_token.

				if ( isset( $_GET['bkap-google-oauth'] ) ) { // phpcs:ignore
					$user_id    = get_current_user_id();
					$product_id = ( 1 != $_GET['bkap-google-oauth'] ) ? $_GET['bkap-google-oauth'] : 0; // phpcs:ignore

					if ( $this->is_product ) {
						set_transient( 'bkap_gcal_access_token_' . $this->product_id, $access_token, 3500 );
						$refresh = $client->getRefreshToken();
						update_post_meta( $this->product_id, '_bkap_gcal_refresh_token', $refresh );
					} else {
						set_transient( 'bkap_gcal_access_token', $access_token, 3500 );
						$refresh = $client->getRefreshToken();
						update_option( 'bkap_gcal_refresh_token', $refresh );
					}
				}
			} catch ( Exception $e ) {
				$this->gcal->log( 'Error while doing OAuth: ' . $e->getMessage() );
			}

			if ( ! empty( $access_token ) ) {
				$status = 'success';
			} else {
				$status        = 'fail';
				$error_message = sprintf( __( 'Google OAuth failed with "%1$s", "%2$s"', 'woocommerce-booking' ), isset( $_GET['error'] ) ? $_GET['error'] : '', isset( $_GET['error_description'] ) ? $_GET['error_description'] : '' ); // phpcs:ignore
				$this->gcal->log( $error_message );
			}

			// Redirecting user to appropriate page.
			if ( $this->product_id ) {
				$redirect_args = array(
					'post'            => $this->product_id,
					'action'          => 'edit',
					'bkap_con_status' => $status,
				);
				$url           = add_query_arg( $redirect_args, admin_url( '/post.php?' ) );

				if ( ! is_admin() && current_user_can( 'dokan_edit_product' ) && function_exists( 'dokan_edit_product_url' ) ) {
					$query_args = array( 'bkap_con_status' =>  $status );
					$url        = add_query_arg( $query_args, dokan_edit_product_url( $this->product_id ) );
				}
			} else {
				$redirect_args = array(
					'page'            => 'woocommerce_booking_page',
					'action'          => 'calendar_sync_settings',
					'bkap_con_status' => $status,
					'post_type'       => 'bkap_booking',
				);
				$url           = add_query_arg( $redirect_args, admin_url( '/edit.php?' ) );
			}

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * OAuth Logout.
		 *
		 * @since 5.1.0
		 */
		public function oauth_logout() {

			$client       = $this->get_client();
			$access_token = $client->getAccessToken();

			if ( ! empty( $access_token['access_token'] ) ) {
				if ( $client->getClientId() ) {
					$body = $client->revokeToken( $access_token );
				}
			}

			if ( $this->is_product ) {
				delete_post_meta( $this->product_id, '_bkap_gcal_refresh_token' );
				delete_transient( 'bkap_gcal_access_token_' . $this->product_id );
				$this->gcal->log( 'Product : Left the Google Calendar App. successfully - ' . $this->product_id );
			} else {
				delete_option( 'bkap_gcal_refresh_token' );
				delete_transient( 'bkap_gcal_access_token' );
				$this->gcal->log( 'Global : Left the Google Calendar App. successfully' );
			}

			return true;
		}

		/**
		 * Function to check if Google Calendar and Google Vendor Files are loaded.
		 *
		 * @since 5.9.1
		 */
		public function bkap_check_gcal_vendor_files() {
			return class_exists( Google_Client::class ) && class_exists( Google_Service_Calendar::class );
		}

		/**
		 * Function to load Google Calendar Vendor Files from Composer.
		 *
		 * @since 5.9.1
		 */
		public static function bkap_load_gcal_vendor_files() {
			$autoloader = BKAP_PLUGIN_PATH . '/includes/libraries/oauth-gcal/vendor/autoload.php';
			if ( file_exists( $autoloader ) ) {
				require $autoloader;
			}
		}
	}

	// Load Vendor Files.
	return BKAP_OAuth_Google_Calendar::bkap_load_gcal_vendor_files();
}
