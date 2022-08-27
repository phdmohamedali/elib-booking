<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Google Calendar API for WooCommerce Booking and Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @category Classes
 * @since 2.6
 */

if ( ! class_exists( 'BKAP_Gcal' ) ) {

	/**
	 * Class for Google Calendar API for WooCommerce Booking and Appointment Plugin
	 *
	 * @class BKAP_Gcal
	 */
	class BKAP_Gcal {

		/**
		 * Constructor
		 *
		 * @param string $key_file Key File Name.
		 * @param string $service_account Service Account Value.
		 * @param string $calendar Calendar ID.
		 */
		public function __construct( $key_file = '', $service_account = '', $calendar = '' ) {

			global $wpdb;

			$this->plugin_dir      = plugin_dir_path( __FILE__ );
			$this->plugin_url      = plugins_url( basename( dirname( __FILE__ ) ) );
			$this->local_time      = current_time( 'timestamp' ); // phpcs:ignore
			$this->key_file        = $key_file;
			$this->service_account = $service_account;
			$this->calendar        = $calendar;

			$global_settings       = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			$this->time_format     = ( isset( $global_settings->booking_time_format ) ) ? $global_settings->booking_time_format : 'H:i';
			$this->date_format     = ( isset( $global_settings->booking_date_format ) ) ? $global_settings->booking_date_format : 'Y-m-d';
			$this->datetime_format = $this->date_format . ' ' . $this->time_format;
			$uploads               = wp_upload_dir(); // Set log file location.
			$this->uploads_dir     = isset( $uploads['basedir'] ) ? $uploads['basedir'] . '/' : WP_CONTENT_DIR . '/uploads/';
			$this->log_file        = $this->uploads_dir . 'bkap-log.txt';

			require_once $this->plugin_dir . 'external/google/Client.php';

			add_action( 'admin_init', array( &$this, 'bkap_init' ), 12 );
			// Prevent exceptions to kill the page.
			if ( ( isset( $_POST['gcal_api_test'] ) && 1 == $_POST['gcal_api_test'] ) || ( isset( $_POST['gcal_import_now'] ) && $_POST['gcal_import_now'] ) ) { // phpcs:ignore
				set_exception_handler( array( &$this, 'exception_error_handler' ) );
			}

			add_action( 'wp_ajax_display_nag', array( &$this, 'display_nag' ) );
		}

		/**
		 * Refresh the page with the exception as GET parameter, so that page is not killed
		 *
		 * @param string $exception Exception.
		 * @since 2.6
		 */
		public function exception_error_handler( $exception ) {
			// If we don't remove these GETs there will be an infinite loop.
			if ( ! headers_sent() ) {
				wp_redirect(
					esc_url(
						add_query_arg(
							array(
								'gcal_api_test_result' => urlencode( $exception ),
								'gcal_import_now'      => false,
								'gcal_api_test'        => false,
								'gcal_api_pre_test'    => false,
							)
						)
					)
				);
			} else {
				$this->log( $exception );
			}
		}

		/**
		 * Displays Messages
		 *
		 * @since 2.6
		 */
		public function display_nag() {
			$error      = false;
			$message    = '';
			$user_id    = $_POST['user_id'];
			$product_id = 0;
			$product_id = $_POST['product_id'];

			if ( isset( $_POST['gcal_api_test'] ) && 1 == $_POST['gcal_api_test'] ) {
				$result = $this->is_not_suitable( $user_id, $product_id );
				if ( '' != $result ) {
					$message .= $result;
				} else {
					// Insert a test event.
					$result = $this->insert_event( array(), 0, $user_id, $product_id, true );
					if ( $result ) {
						$message .= __( '<b>Test is successful</b>. Please REFRESH your Google Calendar and check that test appointment has been saved.', 'woocommerce-booking' );
					} else {
						$log_path = $this->uploads_dir . 'bkap-log.txt';
						$message .= __( "<b>Test failed</b>. Please inspect your log located at {$log_path} for more info.", 'woocommerce-booking' );
					}
				}
			}

			if ( isset( $_POST['gcal_api_test_result'] ) && '' != $_POST['gcal_api_test_result'] ) {
				$m = stripslashes( urldecode( $_POST['gcal_api_test_result'] ) );
				// Get rid of unnecessary information
				if ( strpos( $m, 'Stack trace' ) !== false ) {
					$temp = explode( 'Stack trace', $m );
					$m    = $temp[0];
				}
				if ( strpos( $this->get_selected_calendar( $user_id, $product_id ), 'group.calendar.google.com' ) === false ) {
					$add = '<br />' . __( 'Do NOT use your primary Google calendar, but create a new one.', 'woocommerce-booking' );
				} else {
					$add = '';
				}
				$message = __( 'The following error has been reported by Google Calendar API:<br />', 'woocommerce-booking' ) . $m . '<br />' . __( '<b>Recommendation:</b> Please double check your settings.' . $add, 'woocommerce-booking' );
			}

			echo $message;
			die();
		}

		/**
		 * Set some default settings related to GCal
		 *
		 * @since 2.6
		 */
		public function bkap_init() {

			$product_id = 0;
			$user_id    = get_current_user_id();
			$gcal_mode  = $this->get_api_mode( $user_id, $product_id );

			if ( 'disabled' != $gcal_mode && '' != $gcal_mode ) {
				// Try to create key file folder if it doesn't exist.
				$this->create_key_file_folder();
				$kff = $this->key_file_folder();

				// Copy index.php to this folder and to uploads folder.
				if ( is_dir( $kff ) && ! file_exists( $kff . 'index.php' ) ) {
					echo 'copying index file <br>';
					@copy( $this->plugin_dir . 'gcal/key/index.php', $kff . 'index.php' );
				}
				if ( is_dir( $this->uploads_dir ) && ! file_exists( $this->uploads_dir . 'index.php' ) ) {
					@copy( $this->plugin_dir . 'gcal/key/index.php', $this->uploads_dir . 'index.php' );
				}

				// Copy key file to uploads folder.
				$kfn = $this->get_key_file( $user_id, $product_id ) . '.p12';
				if ( $kfn && is_dir( $kff ) && ! file_exists( $kff . $kfn ) && file_exists( $this->plugin_dir . 'gcal/key/' . $kfn ) ) {
					@copy( $this->plugin_dir . 'gcal/key/' . $kfn, $kff . $kfn );
				}
			}
		}

		/**
		 * Try to create an encrypted key file folder
		 *
		 * @return string
		 * @since 2.6
		 */
		function create_key_file_folder() {
			if ( ! is_dir( $this->uploads_dir . 'bkap_uploads/' ) ) {
				@mkdir( $this->uploads_dir . 'bkap_uploads/' );
			}
		}


		/**
		 * Return GCal API mode (oauth, directly, manual, disabled )
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 2.6
		 */
		public function get_api_mode( $user_id, $product_id = 0 ) {
			$integration_mode = 'disabled';
			if ( isset( $product_id ) && 0 != $product_id ) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['product_sync_integration_mode'] ) ) {
					$integration_mode = $booking_settings['product_sync_integration_mode'];
				}
			}
			// in a scenario where sync is disabled at the product level, the admin/tour operator settings need to be used.
			if ( isset( $integration_mode ) && 'disabled' == $integration_mode || ( ! isset( $integration_mode ) ) ) {
				// get the user role.
				$user = new WP_User( $user_id );
				if ( isset( $user->roles[0] ) && 'tour_operator' == $user->roles[0] ) {
					$integration_mode = get_the_author_meta( 'tours_calendar_sync_integration_mode', $user_id );
				} else {
					$integration_mode = get_option( 'bkap_calendar_sync_integration_mode' );
				}
			}

			if ( '' != $this->key_file && '' != $this->service_account && '' != $this->calendar ){
				$integration_mode = 'directly';
			}
			return $integration_mode; // returns fine for oauth as well.
		}

		/**
		 * Return GCal service account
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 2.6
		 */
		function get_service_account( $user_id, $product_id ) {
			$gcal_service_account = '';

			if ( isset( $product_id ) && 0 != $product_id ) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['product_sync_service_acc_email_addr'] ) && '' !== $booking_settings['product_sync_service_acc_email_addr'] ) {
					$gcal_service_account = $booking_settings['product_sync_service_acc_email_addr'];
				} else {
					$gcal_service_account_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_service_account_arr['bkap_calendar_service_acc_email_address'] ) ) {
						$gcal_service_account = $gcal_service_account_arr['bkap_calendar_service_acc_email_address'];
					}
				}
			} else {
				// get the user role
				$user = new WP_User( $user_id );
				if ( isset( $user->roles[0] ) && 'tour_operator' == $user->roles[0] ) {
					$gcal_service_account_arr = get_the_author_meta( 'tours_calendar_details_1', $user_id );
					if ( isset( $gcal_service_account_arr['tours_calendar_service_acc_email_address'] ) ) {
						$gcal_service_account = $gcal_service_account_arr['tours_calendar_service_acc_email_address'];
					}
				} else {
					$gcal_service_account_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_service_account_arr['bkap_calendar_service_acc_email_address'] ) ) {
						$gcal_service_account = $gcal_service_account_arr['bkap_calendar_service_acc_email_address'];
					}
				}
			}

			if ( '' !== $this->service_account ) {
				$gcal_service_account = $this->service_account;
			}
			return $gcal_service_account;
		}

		/**
		 * Return GCal key file name without the extension
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 2.6
		 */
		function get_key_file( $user_id, $product_id ) {
			$gcal_key_file = '';

			if ( isset( $product_id ) && 0 != $product_id ) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['product_sync_key_file_name'] ) && '' !== $booking_settings['product_sync_key_file_name'] ) {
					$gcal_key_file = $booking_settings['product_sync_key_file_name'];
				} else {
					$gcal_key_file_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_key_file_arr['bkap_calendar_key_file_name'] ) ) {
						$gcal_key_file = $gcal_key_file_arr['bkap_calendar_key_file_name'];
					}
				}
			} else {
				// get the user role
				$user = new WP_User( $user_id );
				if ( isset( $user->roles[0] ) && 'tour_operator' == $user->roles[0] ) {
					$gcal_key_file_arr = get_the_author_meta( 'tours_calendar_details_1', $user_id );

					if ( isset( $gcal_key_file_arr['tours_calendar_key_file_name'] ) ) {
						$gcal_key_file = $gcal_key_file_arr['tours_calendar_key_file_name'];
					}
				} else {
					$gcal_key_file_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_key_file_arr['bkap_calendar_key_file_name'] ) ) {
						$gcal_key_file = $gcal_key_file_arr['bkap_calendar_key_file_name'];
					}
				}
			}

			if ( '' !== $this->key_file ) {
				$gcal_key_file = $this->key_file;
			}

			return $gcal_key_file;
		}

		/**
		 * Return GCal selected calendar ID
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 2.6
		 */
		function get_selected_calendar( $user_id, $product_id ) {

			$gcal_selected_calendar = '';
			if ( isset( $product_id ) && 0 != $product_id ) {

				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['product_sync_calendar_id'] ) && '' !== $booking_settings['product_sync_calendar_id'] ) {
					$gcal_selected_calendar = $booking_settings['product_sync_calendar_id'];
				} else {
					$gcal_selected_calendar_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_selected_calendar_arr['bkap_calendar_id'] ) ) {
						$gcal_selected_calendar = $gcal_selected_calendar_arr['bkap_calendar_id'];
					}
				}
			} else {
				// get the user role
				$user = new WP_User( $user_id );
				if ( isset( $user->roles[0] ) && 'tour_operator' == $user->roles[0] ) {
					$gcal_selected_calendar_arr = get_the_author_meta( 'tours_calendar_details_1', $user_id );
					if ( isset( $gcal_selected_calendar_arr['tours_calendar_id'] ) ) {
						$gcal_selected_calendar = $gcal_selected_calendar_arr['tours_calendar_id'];
					}
				} else {
					$gcal_selected_calendar_arr = get_option( 'bkap_calendar_details_1' );
					if ( isset( $gcal_selected_calendar_arr['bkap_calendar_id'] ) ) {
						$gcal_selected_calendar = $gcal_selected_calendar_arr['bkap_calendar_id'];
					}
				}
			}

			if ( '' !== $this->calendar ) {
				$gcal_selected_calendar = $this->calendar;
			}

			return $gcal_selected_calendar;
		}


		/**
		 * Return GCal selected calendar ID
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 5.1.0
		 */
		function get_client_id( $user_id, $product_id ) {

			$data = $this->id_secret_calendar( $user_id, $product_id, 'client_id' );
			return $data;
		}

		function get_client_secret( $user_id, $product_id ) {

			$data = $this->id_secret_calendar( $user_id, $product_id, 'client_secret' );
			return $data;
		}

		function get_calendar_id( $user_id, $product_id ) {

			$data = $this->id_secret_calendar( $user_id, $product_id, 'calendar_id' );
			return $data;
		}

		function id_secret_calendar( $user_id, $product_id, $key ) {

			$data = '';
			if ( isset( $product_id ) && 0 != $product_id ) {

				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

				if ( isset( $booking_settings['bkap_calendar_oauth_integration'] ) && '' !== $booking_settings['bkap_calendar_oauth_integration'] ) {
					$oauth_setting = $booking_settings['bkap_calendar_oauth_integration'];
					$data          = $oauth_setting[ $key ];
				} else {
					$oauth_setting = get_option( 'bkap_calendar_oauth_integration' );
					if ( isset( $oauth_setting[ $key ] ) ) {
						$data = $oauth_setting[ $key ];
					}
				}
			} else {
				// get the user role.
				$user = new WP_User( $user_id );
				if ( isset( $user->roles[0] ) && 'tour_operator' == $user->roles[0] ) {
					$oauth_setting = get_the_author_meta( 'tour_calendar_oauth_integration', $user_id );
					if ( isset( $oauth_setting[ $key ] ) ) {
						$data = $oauth_setting[ $key ];
					}
				} else {
					$oauth_setting = get_option( 'bkap_calendar_oauth_integration' );
					if ( isset( $oauth_setting[ $key ] ) ) {
						$data = $oauth_setting[ $key ];
					}
				}
			}

			return $data;
		}

		/**
		 * Return GCal Summary (name of Event)
		 *
		 * @return string
		 * @since 2.6
		 */
		function get_summary() {
			return get_option( 'bkap_calendar_event_summary' );
		}

		/**
		 * Return GCal description
		 *
		 * @return string
		 * @since 2.6
		 */
		function get_description() {
			return get_option( 'bkap_calendar_event_description' );
		}

		/**
		 * Checks if php version and extentions are correct
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string (Empty string means suitable)
		 *
		 * @since 2.6
		 */
		function is_not_suitable( $user_id, $product_id ) {

			if ( version_compare( PHP_VERSION, '5.3.0', '<' ) ) {
				return __( 'Google PHP API Client <b>requires at least PHP 5.3</b>', 'woocommerce-booking' );
			}

			// Disabled for now
			if ( false && memory_get_usage() < 31000000 ) {
				return sprintf( __( 'Google PHP API Client <b>requires at least 32 MByte Server RAM</b>. Please check this link how to increase it: %s', 'woocommerce-booking' ), '<a href="http://codex.wordpress.org/Editing_wp-config.php#Increasing_memory_allocated_to_PHP" target="_blank">' . __( 'Increasing_memory_allocated_to_PHP', 'woocommerce-booking' ) . '</a>' );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'Google PHP API Client <b>requires the CURL PHP extension</b>', 'woocommerce-booking' );
			}

			if ( ! function_exists( 'json_decode' ) ) {
				return __( 'Google PHP API Client <b>requires the JSON PHP extension</b>', 'woocommerce-booking' );
			}

			if ( ! function_exists( 'http_build_query' ) ) {
				return __( 'Google PHP API Client <b>requires http_build_query()</b>', 'woocommerce-booking' );
			}

			// Dont continue further if this is pre check
			if ( isset( $_POST['gcal_api_pre_test'] ) && 1 == $_POST['gcal_api_pre_test'] ) {
				return __( 'Your server installation meets requirements.', 'woocommerce-booking' );
			}

			if ( ! $this->_file_exists( $user_id, $product_id ) ) {
				return __( '<b>Key file does not exist</b>', 'woocommerce-booking' );
			}

			return '';
		}

		/**
		 * Checks if key file exists
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return boolean
		 *
		 * @since 2.6
		 */
		function _file_exists( $user_id, $product_id ) {
			if ( file_exists( $this->key_file_folder() . $this->get_key_file( $user_id, $product_id ) . '.p12' ) ) {
				return true;
			} elseif ( file_exists( $this->plugin_dir . 'gcal/key/' . $this->get_key_file( $user_id, $product_id ) . '.p12' ) ) {
				return true;
			} else {
				return false;
			}
		}

		/**
		 * Get contents of the key file
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return string
		 *
		 * @since 2.6
		 */
		function _file_get_contents( $user_id, $product_id ) {
			if ( file_exists( $this->key_file_folder() . $this->get_key_file( $user_id, $product_id ) . '.p12' ) ) {
				return @file_get_contents( $this->key_file_folder() . $this->get_key_file( $user_id, $product_id ) . '.p12' );
			} elseif ( file_exists( $this->plugin_dir . 'gcal/key/' . $this->get_key_file( $user_id, $product_id ) . '.p12' ) ) {
				return @file_get_contents( $this->plugin_dir . 'gcal/key/' . $this->get_key_file( $user_id, $product_id ) . '.p12' );
			} else {
				return '';
			}
		}

		/**
		 * Return key file folder name
		 *
		 * @return string
		 * @since 2.6
		 */
		function key_file_folder() {
			return $this->uploads_dir . 'bkap_uploads/';
		}

		/**
		 * Checks for settings and prerequisites
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return boolean
		 *
		 * @since 2.6
		 */
		public function is_active( $user_id, $product_id ) {
			// If integration is disabled, nothing to do.
			$gcal_mode = $this->get_api_mode( $user_id, $product_id );

			if ( 'disabled' == $gcal_mode || '' == $gcal_mode || ! $gcal_mode ) {
				return false;
			}

			switch ( $gcal_mode ) {
				case 'oauth':
					if ( $this->get_client_id( $user_id, $product_id )
					&& $this->get_client_secret( $user_id, $product_id )
					&& $this->get_calendar_id( $user_id, $product_id ) ) {
						if ( $product_id ) {
							$refresh_token = get_post_meta( $product_id, '_bkap_gcal_refresh_token', true );
						} else {
							$refresh_token = get_option( 'bkap_gcal_refresh_token' );
						}

						if ( $refresh_token ) {
							return 'oauth';
						}
					}
					// code for oauth to see if its active.
					break;
				case 'directly': // global level nu.
					if ( $this->is_not_suitable( $user_id, $product_id ) ) {
						return false;
					}

					if ( $this->get_key_file( $user_id, $product_id )
					&& $this->get_service_account( $user_id, $product_id )
					&& $this->get_selected_calendar( $user_id, $product_id ) ) {
						return 'directly';
					}
					break;
			}

			// None of the other cases are allowed.
			return false;
		}

		/**
		 * Connects to GCal API
		 *
		 * @param integer $user_id - User ID. Greater than 0 for Tor Operator calendars.
		 * @param integer $product_id - Product ID. Greater than 0 for product level calendars.
		 * @return boolean
		 *
		 * @since 2.6
		 */
		public function connect( $user_id, $product_id ) {
			// Disallow faultly plugins to ruin what we are trying to do here.
			@ob_start();

			$is_active = $this->is_active( $user_id, $product_id );
			if ( ! $is_active ) {
				return false;
			}
			switch ( $is_active ) {
				case 'oauth':
					$bkap_oauth_gcal = new BKAP_OAuth_Google_Calendar( $product_id, $user_id );
					$bkap_oauth_gcal->bkap_init_service();
					$this->service   = $bkap_oauth_gcal->service;
					$this->sync_mode = 'oauth';
					break;
				case 'directly':
					$this->bkap_google_client( $user_id, $product_id );
					$this->sync_mode = 'directly';
					break;
			}

			return true;
		}

		/**
		 * This function connects to Google Service for direct mode.
		 *
		 * @param int $user_id User ID.
		 * @param int $product_id Product ID.
		 * @since 2.6
		 */
		public function bkap_google_client( $user_id, $product_id ) {

			require_once $this->plugin_dir . 'external/google/Client.php';

			$config = new BKAP_Google_BKAPGoogleConfig(
				apply_filters(
					'bkap-gcal-client_parameters',
					array(
					// 'cache_class' => 'BKAP_Google_Cache_Null', // For an example.
					)
				)
			);

			$this->client = new BKAP_Google_Client( $config );
			$this->client->setApplicationName( 'WooCommerce Booking and Appointment' );

			$key = $this->_file_get_contents( $user_id, $product_id );
			$this->client->setAssertionCredentials(
				new BKAP_Google_Auth_AssertionCredentials(
					$this->get_service_account( $user_id, $product_id ),
					array( 'https://www.googleapis.com/auth/calendar' ),
					$key
				)
			);

			$this->service = new BKAP_Google_Service_Calendar( $this->client );
		}

		/**
		 * Creates a Google Event object and set its parameters
		 *
		 * @param obj $app Booking object to be set as event.
		 * @since 2.6
		 */
		public function set_event_parameters( $app ) {
			if ( get_option( 'bkap_calendar_event_location' ) != '' ) {
				$location = str_replace( array( 'ADDRESS', 'CITY' ), array( $app->client_address, $app->client_city ), get_option( 'bkap_calendar_event_location' ) );
			} else {
				$location = get_bloginfo( 'description' );
			}

			$summary = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $app->client_name, $app->product, $app->product_with_qty, $app->order_date_time, $app->order_date, $app->id, $app->order_total, $app->client_phone, $app->order_note, $app->client_address, $app->client_email, $app->resource, $app->persons, $app->zoom_meeting ),
				$this->get_summary()
			);

			$description = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $app->client_name, $app->product, $app->product_with_qty, $app->order_date_time, $app->order_date, $app->id, $app->order_total, $app->client_phone, $app->order_note, $app->client_address, $app->client_email, $app->resource, $app->persons, $app->zoom_meeting ),
				$this->get_description()
			);

			$location    = apply_filters( 'bkap_google_event_location', $location, $app );
			$summary     = apply_filters( 'bkap_google_event_summary', $summary, $app );
			$description = apply_filters( 'bkap_google_event_description', $description, $app );

			// Find time difference from Greenwich as GCal asks UTC.
			if ( ! current_time( 'timestamp' ) ) {
				$tdif = 0;
			} else {
				$tdif = current_time( 'timestamp' ) - time();
			}

			$timezone_string = bkap_booking_get_timezone_string();

			if ( 'oauth' === $this->sync_mode ) {
				$start       = new Google_Service_Calendar_EventDateTime();
				$end         = new Google_Service_Calendar_EventDateTime();
				$attendee1   = new Google_Service_Calendar_EventAttendee();
				$this->event = new Google_Service_Calendar_Event();
			} else {
				$start       = new BKAP_Google_Service_Calendar_EventDateTime();
				$end         = new BKAP_Google_Service_Calendar_EventDateTime();
				$attendee1   = new BKAP_Google_Service_Calendar_EventAttendee();
				$this->event = new BKAP_Google_Service_Calendar_Event();
			}

			if ( $app->start_time == '' && $app->end_time == '' ) {
				$start->setDate( date( 'Y-m-d', strtotime( $app->start ) ) );
				$end->setDate( date( 'Y-m-d', strtotime( $app->end ) ) );
			} elseif ( $app->end_time == '' ) {
				$start->setDateTime( bkap_get_date_as_per_utc_timezone( $app->start . ' ' . $app->start_time, $timezone_string ) );
				$end->setDateTime( bkap_get_date_as_per_utc_timezone( $app->start . ' ' . $app->start_time, $timezone_string ) );
			} else {
				$start->setDateTime( bkap_get_date_as_per_utc_timezone( $app->start . ' ' . $app->start_time, $timezone_string ) );
				$end->setDateTime( bkap_get_date_as_per_utc_timezone( $app->end . ' ' . $app->end_time, $timezone_string ) );
			}

			$email = $app->client_email;
			$attendee1->setEmail( $email );
			$attendees = array( $attendee1 );

			$this->event->setLocation( $location );
			$this->event->setStart( $start );
			$this->event->setEnd( $end );
			$this->event->setSummary( apply_filters( 'bkap-gcal-set_summary', $summary ) );
			$this->event->setDescription( apply_filters( 'bkap-gcal-set_description', $description ) );
		}

		/**
		 * Delete event from Gcal when an order is cancelled.
		 *
		 * @param integer $item_id - Item ID.
		 * @param integer $user_id - User ID - to be passed for tour operator calendars.
		 * @param integer $product_id - Product ID, Greater than 0 for product level calendars.
		 *
		 * @since 2.6.3
		 */
		public function delete_event( $item_id, $user_id, $product_id, $event_uid = '', $item_number = -1 ) {

			if ( ! $this->connect( $user_id, $product_id ) ) {
				return false;
			}

			$user = new WP_User( $user_id );
			if ( 'oauth' === $this->sync_mode ) {
				$calendar_id = $this->get_calendar_id( $user_id, $product_id );
			} else {
				$calendar_id = $this->get_selected_calendar( $user_id, $product_id );
			}
			$event_id = ''; // get the event UID.

			if ( isset( $product_id ) && $product_id != 0 ) {
				$booking_settings = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
				$event_uids       = get_post_meta( $product_id, 'bkap_event_uids_ids', true );
			} elseif ( isset( $user->roles[0] ) && 'tour_operator' === $user->roles[0] ) {
				$event_uids = get_the_author_meta( 'tours_event_uids_ids', $user_id );
			} else {
				$event_uids = get_option( 'bkap_event_uids_ids' );
			}

			if ( '' !== $event_uid ) {
				$event_uid = str_replace( '@google.com', '', $event_uid );
				$event_uid = array( $event_uid );
			} else {
				if ( is_array( $event_uids ) && count( $event_uids ) > 0 ) {
					if ( isset( $item_id ) && array_key_exists( $item_id, $event_uids ) ) {
						$event_id  = $event_uids[ $item_id ];
						$ids       = array();
						$event_ids = explode( ',', $event_id );
						foreach ( $event_ids as $event_key => $event_value ) {
							if ( $item_number < 0 ) {
								$ids[] = str_replace( '@google.com', '', $event_value );
							} else {
								if ( $item_number == $event_key ) {
									$ids[] = str_replace( '@google.com', '', $event_value );
									break;
								}
							}
						}
						$event_uid = $ids;
					} else {
						$event_uid = array();
					}
				}
			}

			if ( ! empty( $event_uid ) && $calendar_id != '' ) {
				try {
					foreach ( $event_uid as $id ) {
						$event        = $this->service->events->get( $calendar_id, $id );
						$event_status = $event->status;
						if ( 'cancelled' !== $event_status ) {
							$deleted_event = $this->service->events->delete( $calendar_id, $id );
						}
					}
				} catch ( Exception $e ) {
					$this->log( 'Event does\'t found in selected calendar: ' . $e->getMessage() );
				}
			}
		}

		/**
		 * Inserts a booking to the selected calendar as event
		 *
		 * @param array   $event_details - Details such as start & end dates, time, product, qty etc.
		 * @param integer $event_id - Item ID.
		 * @param integer $user_id - Passed for tour operators.
		 * @param integer $product_id - Passed for product level calendars.
		 * @param boolean $test: True if a Test booking is being created, else false.
		 *
		 * @return boolean
		 *
		 * @since 2.6
		 */
		public function insert_event( $event_details, $event_id, $user_id, $product_id = 0, $test = false, $item_number = -1 ) {
			if ( ! $this->connect( $user_id, $product_id ) ) {
				return false;
			}
			global $wpdb;

			if ( isset( $user_id ) ) {
				$address_1  = get_user_meta( $user_id, 'shipping_address_1' );
				$address_2  = get_user_meta( $user_id, 'shipping_address_2' );
				$first_name = get_user_meta( $user_id, 'shipping_first_name' );
				$last_name  = get_user_meta( $user_id, 'shipping_last_name' );
				$phone      = get_user_meta( $user_id, 'billing_phone' );
				$city       = get_user_meta( $user_id, 'shipping_city' );
			} else {
				$address_1  = '';
				$address_2  = '';
				$first_name = '';
				$last_name  = '';
				$phone      = '';
				$city       = '';
			}

			if ( $test ) {
				$bkap             = new stdClass();
				$bkap->start      = date( 'Y-m-d', $this->local_time );
				$bkap->end        = date( 'Y-m-d', $this->local_time );
				$bkap->start_time = date( 'H:i:s', $this->local_time + 600 );
				$bkap->end_time   = date( 'H:i:s', $this->local_time + 2400 );
				$client_email     = get_user_meta( $user_id, 'billing_email' );

				if ( isset( $client_email[0] ) ) {
					$bkap->client_email = $client_email[0];
				} else {
					$bkap->client_email = '';
				}
				if ( isset( $first_name[0] ) && isset( $last_name[0] ) ) {
					$bkap->client_name = $first_name[0] . ' ' . $last_name[0];
				} else {
					$bkap->client_name = '';
				}
				if ( isset( $address_1[0] ) && isset( $address_2[0] ) ) {
					$bkap->client_address = $address_1[0] . ' ' . $address_2[0];
				} else {
					$bkap->client_address = '';
				}

				if ( isset( $city[0] ) ) {
					$bkap->client_city = __( $city[0], 'woocommerce-booking' );
				} else {
					$bkap->client_city = '';
				}

				if ( isset( $phone[0] ) ) {
					$bkap->client_phone = $phone[0];
				} else {
					$bkap->client_phone = '';
				}
				$bkap->resource         = '';
				$bkap->persons          = '';
				$bkap->zoom_meeting     = '';
				$bkap->order_note       = '';
				$bkap->order_total      = '';
				$bkap->product          = '';
				$bkap->product_with_qty = '';
				$bkap->order_date_time  = '';
				$bkap->order_date       = '';
				$bkap->id               = '';
			} else {
				$bkap = bkap_event_data( $event_details, $event_id );
			}

			if ( ! isset( $bkap ) ) {
				return false;
			}

			// Create Event object and set parameters.
			$this->set_event_parameters( $bkap );
			// Insert event.
			try {

				if ( 'oauth' === $this->sync_mode ) {
					$calendar_id = $this->get_calendar_id( $user_id, $product_id );
				} else {
					$calendar_id = $this->get_selected_calendar( $user_id, $product_id );
				}

				$created_event = $this->service->events->insert( $calendar_id, $this->event );
				$uid           = $created_event->iCalUID;

				bkap_update_event_item_uid_data( $uid, $product_id, $user_id, $event_id, $item_number, 'gcal' );

				return true;
			} catch ( Exception $e ) {
				$this->log( 'Insert went wrong: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Used to log messages in the bkap-log file.
		 *
		 * @param string $message Message to be added in log file.
		 * @since 2.6
		 */
		function log( $message = '' ) {
			if ( $message ) {
				$to_put = '<b>[' . date_i18n( $this->datetime_format, $this->local_time ) . ']</b> ' . $message;
				// Prevent multiple messages with same text and same timestamp.
				if ( ! file_exists( $this->log_file ) || strpos( @file_get_contents( $this->log_file ), $to_put ) === false ) {
					@file_put_contents( $this->log_file, $to_put . chr( 10 ) . chr( 13 ), FILE_APPEND );
				}
			}
		}

		/**
		 * Email Admin regarding the Google Calendar Sync Error when its failed.
		 *
		 * @param string $body Message to be added in email.
		 * @param int    $product_id Product ID.
		 *
		 * @since 5.1.0
		 */
		public function bkap_log_mail( $body, $product_id ) {
			$to        = get_option( 'admin_email' );
			$p_g       = ( $product_id ) ? 'For #' . $product_id . ' ' : 'Global ';
			$subject   = $p_g . 'Error - Google Calendar Sync';
			$site_name = get_bloginfo( 'name' );
			$from      = 'From: ' . $site_name . ' &lt;' . $to;
			$headers   = array( 'Content-Type: text/html; charset=UTF-8', $from );

			wp_mail( $to, $subject, $body, $headers );
		}

		/**
		 * Build GCal url for GCal Button. It requires UTC time.
		 *
		 * @param object $bkap - Contains booking details like start date, end date, product, qty etc.
		 * @return string
		 * @since 2.6
		 */
		function gcal( $bkap, $user_type ) {
			// Find time difference from Greenwich as GCal asks UTC.
			$summary = str_replace(
				array(
					'SITE_NAME',
					'CLIENT',
					'PRODUCT_NAME',
					'PRODUCT_WITH_QTY',
					'ORDER_DATE_TIME',
					'ORDER_DATE',
					'ORDER_NUMBER',
					'PRICE',
					'PHONE',
					'NOTE',
					'ADDRESS',
					'EMAIL',
					'RESOURCE',
					'PERSONS',
					'ZOOM_MEETING',
				),
				array(
					get_bloginfo( 'name' ),
					$bkap->client_name,
					$bkap->product,
					$bkap->product_with_qty,
					$bkap->order_date_time,
					$bkap->order_date,
					$bkap->id,
					$bkap->order_total,
					$bkap->client_phone,
					$bkap->order_note,
					$bkap->client_address,
					$bkap->client_email,
					$bkap->resource,
					$bkap->persons,
					$bkap->zoom_meeting,
				),
				$this->get_summary()
			);

			$description = str_replace(
				array(
					'SITE_NAME',
					'CLIENT',
					'PRODUCT_NAME',
					'PRODUCT_WITH_QTY',
					'ORDER_DATE_TIME',
					'ORDER_DATE',
					'ORDER_NUMBER',
					'PRICE',
					'PHONE',
					'NOTE',
					'ADDRESS',
					'EMAIL',
					'RESOURCE',
					'PERSONS',
					'ZOOM_MEETING',
				),
				array(
					get_bloginfo( 'name' ),
					$bkap->client_name,
					$bkap->product,
					$bkap->product_with_qty,
					$bkap->order_date_time,
					$bkap->order_date,
					$bkap->id,
					$bkap->order_total,
					$bkap->client_phone,
					$bkap->order_note,
					$bkap->client_address,
					$bkap->client_email,
					$bkap->resource,
					$bkap->persons,
					$bkap->zoom_meeting,
				),
				$this->get_description()
			);

			if ( $bkap->start_time == '' && $bkap->end_time == '' ) {
				$start = strtotime( $bkap->start );
				$end   = strtotime( $bkap->end . '+1 day' );

				// Using gmdate instead of get_gmt_from_date as the latter is not working correctly with Timezone Strings
				$gmt_start = gmdate( 'Ymd', $start );
				$gmt_end   = gmdate( 'Ymd', $end );

			} elseif ( $bkap->end_time == '' ) {
				$start     = strtotime( $bkap->start . ' ' . $bkap->start_time );
				$end       = strtotime( $bkap->end . ' ' . $bkap->start_time );
				$gmt_start = get_gmt_from_date( date( 'Y-m-d H:i:s', $start ), 'Ymd\THis\Z' );
				$gmt_end   = get_gmt_from_date( date( 'Y-m-d H:i:s', $end ), 'Ymd\THis\Z' );
			} else {
				$start = strtotime( $bkap->start . ' ' . $bkap->start_time );
				$end   = strtotime( $bkap->end . ' ' . $bkap->end_time );
				if ( $user_type == 'admin' ) {
					$gmt_start = get_gmt_from_date( date( 'Y-m-d H:i:s', $start ), 'Ymd\THis\Z' );
					$gmt_end   = get_gmt_from_date( date( 'Y-m-d H:i:s', $end ), 'Ymd\THis\Z' );
				} else {

					if ( '' !== Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) {
						// This will be the case when order is placed with timezone settings

						$sstart = new DateTime( date( 'Y-m-d H:i:s', $start ), new DateTimeZone( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) );
						$sstart->setTimezone( new DateTimeZone( 'UTC' ) );
						$sstart->format( 'Ymd\THis\Z' );
						$gmt_start = $sstart->format( 'Ymd\THis\Z' );

						$eend = new DateTime( date( 'Y-m-d H:i:s', $end ), new DateTimeZone( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) );
						$eend->setTimezone( new DateTimeZone( 'UTC' ) );
						$eend->format( 'Ymd\THis\Z' );
						$gmt_end = $eend->format( 'Ymd\THis\Z' );
					} else {
						$gmt_start = get_gmt_from_date( date( 'Y-m-d H:i:s', $start ), 'Ymd\THis\Z' );
						$gmt_end   = get_gmt_from_date( date( 'Y-m-d H:i:s', $end ), 'Ymd\THis\Z' );
					}
				}
			}

			if ( get_option( 'bkap_calendar_event_location' ) != '' ) {
				$location = str_replace( array( 'ADDRESS', 'CITY' ), array( $bkap->client_address, $bkap->client_city ), get_option( 'bkap_calendar_event_location' ) );
			} else {
				$location = get_bloginfo( 'description' );
			}

			$param = array(
				'action'   => 'TEMPLATE',
				'text'     => $summary,
				'dates'    => $gmt_start . '/' . $gmt_end,
				'location' => $location,
				'details'  => rawurlencode( $description ),
			);

			return esc_url(
				add_query_arg(
					array( $param, $start, $end ),
					'http://www.google.com/calendar/event'
				)
			);
		}

		/**
		 * Build URL for Other Calendar button. It requires UTC time.
		 *
		 * @param object $bkap - Contains booking details like start date, end date, product, qty etc.
		 * @return string
		 * @since 2.6
		 */
		function other_cal( $bkap, $user_type ) {
			// Find time difference from Greenwich as GCal asks UTC
			$summary = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $bkap->client_name, $bkap->product, $bkap->product_with_qty, $bkap->order_date_time, $bkap->order_date, $bkap->id, $bkap->order_total, $bkap->client_phone, $bkap->order_note, $bkap->client_address, $bkap->client_email, $bkap->resource, $bkap->persons, rawurlencode( $bkap->zoom_meeting ) ),
				$this->get_summary()
			);

			$description = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $bkap->client_name, $bkap->product, $bkap->product_with_qty, $bkap->order_date_time, $bkap->order_date, $bkap->id, $bkap->order_total, $bkap->client_phone, $bkap->order_note, $bkap->client_address, $bkap->client_email, $bkap->resource, $bkap->persons, rawurlencode( $bkap->zoom_meeting ) ),
				$this->get_description()
			);

			if ( $bkap->start_time == '' && $bkap->end_time == '' ) {
				$gmt_start = strtotime( $bkap->start );
				$gmt_end   = strtotime( '+1 day', strtotime( $bkap->end ) );
				$gmt_start  = $this->bkap_get_date_to_cal( $gmt_start + ( time() - current_time( 'timestamp' ) ) );
				$gmt_end    = $this->bkap_get_date_to_cal( $gmt_end + ( time() - current_time( 'timestamp' ) ) );
			} elseif ( $bkap->end_time == '' ) {
				$time_start = explode( ':', $bkap->start_time );
				$gmt_start  = strtotime( $bkap->start ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) );
				$gmt_end    = strtotime( $bkap->end ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) );
				$gmt_start  = $this->bkap_get_date_to_cal( $gmt_start );
				$gmt_end    = $this->bkap_get_date_to_cal( $gmt_end );
			} else {

				if ( $user_type == 'admin' ) {
					$time_start = explode( ':', $bkap->start_time );
					$time_end   = explode( ':', $bkap->end_time );
					$gmt_start  = $this->bkap_get_date_to_cal( strtotime( $bkap->start ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) ) );
					$gmt_end    = $this->bkap_get_date_to_cal( strtotime( $bkap->end ) + $time_end[0] * 60 * 60 + $time_end[1] * 60 + ( time() - current_time( 'timestamp' ) ) );
				} else {
					$global_settings = bkap_global_setting();
					$timezone        = bkap_timezone_check( $global_settings );
					if ( $timezone && '' !== Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) {
						// This will be the case when order is placed with timezone settings.
						$start  = strtotime( $bkap->start . ' ' . $bkap->start_time );
						$end    = strtotime( $bkap->end . ' ' . $bkap->end_time );
						$sstart = new DateTime( date( 'Y-m-d H:i:s', $start ), new DateTimeZone( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) );
						$sstart->setTimezone( new DateTimeZone( 'UTC' ) );
						$sstart->format( 'Ymd\THis\Z' );
						$gmt_start = $sstart->format( 'Ymd\THis\Z' );

						$eend = new DateTime( date( 'Y-m-d H:i:s', $end ), new DateTimeZone( Bkap_Timezone_Conversion::get_timezone_var( 'bkap_timezone_name' ) ) );
						$eend->setTimezone( new DateTimeZone( 'UTC' ) );
						$eend->format( 'Ymd\THis\Z' );
						$gmt_end = $eend->format( 'Ymd\THis\Z' );
					} else {
						$time_start = explode( ':', $bkap->start_time );
						$time_end   = explode( ':', $bkap->end_time );
						$gmt_start  = $this->bkap_get_date_to_cal( strtotime( $bkap->start ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) ) );
						$gmt_end    = $this->bkap_get_date_to_cal( strtotime( $bkap->end ) + $time_end[0] * 60 * 60 + $time_end[1] * 60 + ( time() - current_time( 'timestamp' ) ) );
					}
				}
			}

			if ( get_option( 'bkap_calendar_event_location' ) != '' ) {
				$location = str_replace( array( 'ADDRESS', 'CITY' ), array( $bkap->client_address, $bkap->client_city ), get_option( 'bkap_calendar_event_location' ) );
			} else {
				$location = get_bloginfo( 'description' );
			}

			$current_time = current_time( 'timestamp' );

			return plugins_url( "woocommerce-booking/includes/ical.php?event_date_start=$gmt_start&amp;event_date_end=$gmt_end&amp;current_time=$current_time&amp;summary=$summary&amp;description=$description&amp;event_location=$location" );
		}

		/**
		 * Convert timestamp to Ymd\THis\Z based on UTC timezone.
		 *
		 * @param int $timestamp Timestamp.
		 * @return string
		 * @since 5.6
		 */
		function bkap_get_date_to_cal( $timestamp ) {
			date_default_timezone_set( 'UTC' );
			$time = date( 'H:i', $timestamp );
			if ( $time != '00:00' && $time != '00:01' ) {
				return date( 'Ymd\THis\Z', $timestamp );
			} else {
				return date( 'Ymd', $timestamp );
			}
		}

		/**
		 * Build URL for Outlook Calendar. It requires UTC time.
		 *
		 * @param object $bkap - Contains booking details like start date, end date, product, qty etc.
		 * @return string
		 * @since 2.6
		 */
		function outlook_cal( $bkap ) {

			// Find time difference from Greenwich as GCal asks UTC.
			$summary = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $bkap->client_name, $bkap->product, $bkap->product_with_qty, $bkap->order_date_time, $bkap->order_date, $bkap->id, $bkap->order_total, $bkap->client_phone, $bkap->order_note, $bkap->client_address, $bkap->client_email, $bkap->resource, $bkap->persons, $bkap->zoom_meeting ),
				$this->get_summary()
			);

			$description = str_replace(
				array( 'SITE_NAME', 'CLIENT', 'PRODUCT_NAME', 'PRODUCT_WITH_QTY', 'ORDER_DATE_TIME', 'ORDER_DATE', 'ORDER_NUMBER', 'PRICE', 'PHONE', 'NOTE', 'ADDRESS', 'EMAIL', 'RESOURCE', 'PERSONS', 'ZOOM_MEETING' ),
				array( get_bloginfo( 'name' ), $bkap->client_name, $bkap->product, $bkap->product_with_qty, $bkap->order_date_time, $bkap->order_date, $bkap->id, $bkap->order_total, $bkap->client_phone, $bkap->order_note, $bkap->client_address, $bkap->client_email, $bkap->resource, $bkap->persons, $bkap->zoom_meeting ),
				$this->get_description()
			);

			if ( $bkap->start_time == '' && $bkap->end_time == '' ) {
				$gmt_start = strtotime( $bkap->start );
				$gmt_end   = strtotime( '+1 day', strtotime( $bkap->end ) );
			} elseif ( $bkap->end_time == '' ) {
				$time_start = explode( ':', $bkap->start_time );
				$gmt_start  = strtotime( $bkap->start ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) );
				$gmt_end    = strtotime( $bkap->end ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) );
			} else {
				$time_start = explode( ':', $bkap->start_time );
				$time_end   = explode( ':', $bkap->end_time );
				$gmt_start  = strtotime( $bkap->start ) + $time_start[0] * 60 * 60 + $time_start[1] * 60 + ( time() - current_time( 'timestamp' ) );
				$gmt_end    = strtotime( $bkap->end ) + $time_end[0] * 60 * 60 + $time_end[1] * 60 + ( time() - current_time( 'timestamp' ) );
			}

			if ( get_option( 'bkap_calendar_event_location' ) != '' ) {
				$location = str_replace( array( 'ADDRESS', 'CITY' ), array( $bkap->client_address, $bkap->client_city ), get_option( 'bkap_calendar_event_location' ) );
			} else {
				$location = get_bloginfo( 'description' );
			}

			$current_time = current_time( 'timestamp' );

			$param = array(
				'DTSTART   '  => $gmt_start,
				'DTEND'       => $gmt_end,
				'SUMMARY'     => $summary,
				'LOCATION'    => $location,
				'DESCRIPTION' => $description,
			);

			return str_replace( 'http://', 'webcal://', plugins_url( 'woocommerce-booking/Calendar-event.ics' ) );
		}

	} // end of class

}// if not class exists
