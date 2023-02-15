<?php
/**
 *  Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Plugin Licensing which restricts access to some BKAP Modules based on type of license.
 *
 * @author      Tyche Softwares
 * @package     BKAP/License
 * @category    Classes
 * @since       5.12.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_License' ) ) {

	/**
	 * BKAP License class.
	 *
	 * @since 5.12.0
	 */
	class BKAP_License {

		/**
		 * License Key.
		 *
		 * @var string
		 */
		public static $license_key = '';

		/**
		 * License Type.
		 *
		 * @var string
		 */
		public static $license_type = '';

		/**
		 * License Status.
		 *
		 * @var string
		 */
		public static $license_status = '';

		/**
		 * License Key.
		 *
		 * @var string
		 */
		public static $plugin_name = 'Booking & Appointment Plugin for WooCommerce';

		/**
		 * General License Error Message.
		 *
		 * @var string
		 */
		public static $license_error_message;

		/**
		 * Plugin License Activate Error Message.
		 *
		 * @var string
		 */
		public static $plugin_activate_error_message;

		/**
		 * Plugin License Error Message.
		 *
		 * @var string
		 */
		public static $plugin_license_error_message;

		/**
		 * Initializes the BKAP_License() class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 5.12.0
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new BKAP_License();
			}

			return $instance;
		}

		/**
		 * Default Constructor
		 *
		 * @since 5.12.0
		 */
		public function __construct() {
			self::load_license_data();
			add_action( 'admin_init', array( $this, 'save_license_key_and_maybe_reactivate' ) );
			add_action( 'admin_init', array( $this, 'deactivate_license' ) );
			add_action( 'admin_init', array( $this, 'activate_license' ) );
			add_action( 'admin_notices', array( &$this, 'vendor_plugin_license_error_notice' ), 15 );
			add_action( 'init', array( &$this, 'remove_wp_actions' ), 10 );
		}

		/**
		 * Load data.
		 *
		 * @since 5.12.0
		 */
		public static function load_license_data() {

			/* translators: %1$s: Current Plan, %2$s: Expected Plan */
			self::$license_error_message = __( 'You are on the %1$s License. This feature is available only on the %2$s License.', 'woocommerce-booking' );

			/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
			self::$plugin_license_error_message  = __( 'You have activated the %1$s Plugin. Your current license ( %2$s ) does not offer support for Vendor Plugins. Please upgrade to the %3$s License.', 'woocommerce-booking' ); //phpcs:ignore

			self::$plugin_activate_error_message = sprintf(
				/* translators: %1$s: Plugin name; %2$s: URL for License Page. */
				__( 'We have noticed that the license for <b>%1$s</b> plugin is not active. To receive automatic updates & support, please activate the license <a href= "%2$s"> here </a>.', 'woocommerce-booking' ),
				self::$plugin_name,
				'edit.php?post_type=bkap_booking&page=booking_license_page'
			);

			self::$license_key    = get_option( 'edd_sample_license_key', '' );
			self::$license_type   = get_option( 'edd_sample_license_type', '' );
			self::$license_status = get_option( 'edd_sample_license_status', '' );
		}

		/**
		 * This function add the license page in the Booking menu.
		 *
		 * @since 1.7
		 */
		public static function display_license_page() {
			?>

			<div class="wrap">
				<h2>
					<?php esc_html_e( 'Plugin License Options', 'woocommerce-booking' ); ?>
				</h2>

				<?php
				if ( isset( $_GET['bkap_license_notice'] ) && 'error' === $_GET['bkap_license_notice'] ) { // phpcs:ignore
					$notice = sprintf(
						/* translators: %s: License Key */
						__( 'An error has been encountered while trying to activate your license key: %s. Please check that you typed in the key correctly ( make sure to save ) and try again.', 'woocommerce-booking' ),
						self::$license_key
					);

					if ( isset( $_GET['bkap_license_error_code'] ) && '' !== $_GET['bkap_license_error_code'] ) { // phpcs:ignore

						switch( $_GET['bkap_license_error_code'] ) { // phpcs:ignore

							case '10b':
								$notice = __( 'A <strong>403 Forbidden</strong> error has been received from the Tyche Server. This isn\'t a problem with your license key but rather with the destination server as the activation request was flatly rejected. Please check your system configuration and ensure that your system is properly provisioned to send valid API requests.', 'woocommerce-booking' );
								break;

							case '10c':
								$domain_name = ( isset( $_SERVER['SERVER_NAME'] ) && '' !== $_SERVER['SERVER_NAME'] ? $_SERVER['SERVER_NAME'] : '[please provide your domain name here]' ); // phpcs:ignore
								$ip_address  = ( isset( $_SERVER['REMOTE_ADDR'] ) && '' !== $_SERVER['REMOTE_ADDR'] ? $_SERVER['REMOTE_ADDR'] : '[please provie your IP Address here]' ); // phpcs:ignore

								$notice = sprintf(
									/* translators: %1s: Create Ticket Link; %2s: Domain Name; %3s: IP address;%4s: License Key. */
									__(
										'Your License Activation Request was unsuccessful because API requests from your system to Tyche Softwares have been blocked by the Tyche Softwares Firewall.<br/>
										<br/>
										To resolve this issue, kindly create a ticket <a href="%1$s"> here </a> and provide the following details ( in the ticket ):<br/>
										<br/>
										<strong>Domain Name:</strong> %2$s<br/>
										<strong>IP Address:</strong> %3$s<br/>
										<strong>License Key:</strong> %4$s<br/>
										<strong>Temporary WP Admin Access:</strong> <em>[please provide username and password here for access to your WP site as it may be needed for debugging]</em><br/>
										<strong>Temporary FTP Access:</strong> <em>[please provide FTP details here as it may be needed if the Tyche Plugin files need to be updated]</em><br/>
										<br/>
										Please deactivate the temporary FTP and WP Admin access as soon as this issue has been resolved by the Tyche Softwares Support Team.',
										'woocommerce-booking'
									),
									'https://tychesoftwares.freshdesk.com/support/tickets/new',
									$domain_name,
									$ip_address,
									self::$license_key
								);
								break;
						}
					}

					self::display_error_notice( $notice );
				}
				?>

				<form method="post" action="options.php">

				<?php settings_fields( 'bkap_edd_sample_license' ); ?>

					<table class="form-table">
						<tbody>
							<tr valign="top">	
								<th scope="row" valign="top">
									<?php esc_html_e( 'License Key', 'woocommerce-booking' ); ?>
								</th>

								<td>
									<input id="edd_sample_license_key" name="edd_sample_license_key" type="text" class="regular-text" value="<?php esc_attr_e( self::$license_key ); // phpcs:ignore ?>" />
									<label class="description" for="edd_sample_license_key"><?php esc_html_e( 'Enter your license key', 'woocommerce-booking' ); ?></label>
								</td>
							</tr>

							<?php if ( false !== self::$license_key ) { ?>
								<tr valign="top">	
									<th scope="row" valign="top">
										<?php esc_html_e( 'Activate License', 'woocommerce-booking' ); ?>
									</th>

									<td>
									<?php if ( 'valid' === self::$license_status ) { ?>
										<span style="color:green;"><?php esc_html_e( 'active', 'woocommerce-booking' ); ?></span>
										<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
										<input type="submit" class="button-secondary" name="bkap_edd_license_deactivate" value="<?php esc_attr_e( 'Deactivate License', 'woocommerce-booking' ); ?>"/>
										<?php
									} else {
											wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' );
										?>
											<input type="submit" class="button-secondary" name="bkap_edd_license_activate" value="<?php esc_attr_e( 'Activate License', 'woocommerce-booking' ); ?>"/>
									<?php } ?>
									</td>
								</tr>
								<?php } ?>

								<input type="hidden" name="_wp_http_referer" value="<?php echo esc_url_raw( admin_url( 'edit.php?post_type=bkap_booking&page=booking_license_page' ) ); ?>" />
						</tbody>
					</table>	
					<?php submit_button(); ?>
				</form>
			<?php
		}

		/**
		 * This function stores the license key once the plugin is installed and the license key saved.
		 *
		 * @since 5.12.0
		 */
		public static function save_license_key_and_maybe_reactivate() {
			register_setting( 'bkap_edd_sample_license', 'edd_sample_license_key', array( 'BKAP_License', 'reactivate_if_new_license' ) );
		}

		/**
		 * Places a call to fetch license details.
		 *
		 * @param string $action Action to send to remote server while fetching license.
		 * @param bool   $return_whole_response Whether to return the whole response or just the body. Default action is to return only the body.
		 *
		 * @since 5.12.0
		 */
		public static function fetch_license( $action = 'check_license', $return_whole_response = false ) {

			$api_params = array(
				'edd_action' => $action,
				'license'    => self::$license_key,
				'item_name'  => rawurlencode( EDD_SL_ITEM_NAME_BOOK ),
			);

			// Call the Tyche API.
			$response = wp_remote_get(
				esc_url_raw( add_query_arg( $api_params, EDD_SL_STORE_URL_BOOK ) ),
				array(
					'timeout'   => 15,
					'sslverify' => false,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			return $return_whole_response ? $response : json_decode( wp_remote_retrieve_body( $response ) );
		}

		/**
		 * This function activates the stored license key.
		 * It sends an API call to the Tyche Server to check the validity of the license and thereafter activates the license if valid.
		 * 5.15.0 Update: Return useful information on the cause of failed activation - https://github.com/TycheSoftwares/woocommerce-booking/issues/5184
		 *
		 * @since 5.12.0
		 * @since Updated 5.15.0
		 */
		public static function activate_license() {

			if ( isset( $_POST['bkap_edd_license_activate'] ) ) { // phpcs:ignore

				if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return; // get out if we didn't click the Activate button.
				}

				$http_referrer 			= self::return_url_without_bkap_notice( $_POST['_wp_http_referer'] ); // phpcs:ignore
				$request_data           = self::fetch_license( 'activate_license', true );
				$response_code          = (int) $request_data['response']['code'];
				$response_body          = $request_data['body'];
				$response_message       = $request_data['response']['message'];
				$is_response_successful = ( 2 === (int) substr( $response_code, 0, 1 ) );
				$did_update             = false;

				if ( $is_response_successful ) {

					$license_data = json_decode( $response_body );

					if ( $license_data && isset( $license_data->license ) && '' !== $license_data->license && 'invalid' !== $license_data->license ) {

						$did_update           = true;
						self::$license_status = $license_data->license;
						self::$license_type   = self::get_license_type( strval( $license_data->price_id ), $license_data );

						update_option( 'edd_sample_license_status', self::$license_status );
						update_option( 'edd_sample_license_expires', $license_data->expires );
						update_option( 'edd_sample_license_type', self::$license_type );

						if ( false === strpos( $http_referrer, 'bkap_license_notice' ) ) {
							return;
						}
					}
				}

				if ( ! $did_update ) {

					// If we get here, then an error has occurred.
					$error_code = '10a';
					$error_code = ( 403 === $response_code ) ? '10b' : $error_code;
					$error_code = ( false !== strpos( $response_body, 'BlogVault Firewall' ) ) ? '10c' : $error_code; // Check if error has been caused by Tyche Firewall.

					// Append error variables to http_referer.
					if ( false === strpos( $http_referrer, 'bkap_license_notice' ) ) {
						$http_referrer .= ( '&bkap_license_notice=error&bkap_license_error_code=' . $error_code ); // phpcs:ignore
					}
				}

				$redirect = wp_validate_redirect( add_query_arg( 'settings-updated', 'true', $http_referrer ) );
				wp_redirect( $redirect ); // phpcs:ignore
				exit;
			}
		}

		/**
		 * This function will deactivate the license.
		 *
		 * @since 5.12.0
		 */
		public static function deactivate_license() {

			if ( isset( $_POST['bkap_edd_license_deactivate'] ) ) { //phpcs:ignore

				if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
					return;
				}

				$license_data = self::fetch_license( 'deactivate_license' );

				// $license_data->license will be either "deactivated" or "failed".
				if ( isset( $license_data->license ) && 'deactivated' === $license_data->license ) {
					delete_option( 'edd_sample_license_status' );
					delete_option( 'edd_sample_license_expires' );
					delete_option( 'edd_sample_license_type' );
				}
			}
		}

		/**
		 * This checks if a license key is valid.
		 *
		 * @since 5.12.0
		 */
		public static function check_license() {

			$license_data = self::fetch_license();

			$data = 'invalid';
			if ( isset( $license_data->license ) && 'valid' === $license_data->license ) {
				$data = 'valid';
			}

			self::$license_status = $license_data->license;
			self::$license_type   = self::get_license_type( strval( $license_data->price_id ), $license_data );

			update_option( 'edd_sample_license_status', self::$license_status );
			update_option( 'edd_sample_license_expires', $license_data->expires );
			update_option( 'edd_sample_license_type', self::$license_type );
			return $data;
		}

		/**
		 * This checks that the license type option is not empty. If it is, then we go a quick license key fetch.
		 *
		 * @since 5.12.0
		 */
		public static function check_license_type() {

			$license_type = get_option( 'edd_sample_license_type', '' );

			if ( '' !== $license_type ) {
				return;
			}

			$license_data = self::fetch_license();

			if ( $license_data && isset( $license_data->license ) && '' !== $license_data->license && 'invalid' !== $license_data->license ) {
				$license_type   = self::get_license_type( strval( $license_data->price_id ), $license_data );
				$license_status = get_option( 'edd_sample_license_status', '' );
				if ( '' !== $license_status ) {
					self::$license_type = $license_type;
					update_option( 'edd_sample_license_type', self::$license_type );
				}
			}
		}

		/**
		 * This function checks if a new license has been entered, if yes, then license must be reactivated.
		 *
		 * @param string $license License Key.
		 *
		 * @since Updated 5.12.0
		 */
		public static function reactivate_if_new_license( $license ) {
				$old_license = get_option( 'edd_sample_license_key' );

			if ( '' !== $license && isset( $old_license ) && '' !== $old_license && $old_license !== $license ) {
				delete_option( 'edd_sample_license_status' ); // A new license has been entered, so we must reactivate.
			}

			return $license;
		}

		/**
		 * This function gets the license type from the Price ID..
		 *
		 * @param string $price_id Price ID of the license.
		 * @param object $license_data License Data.
		 *
		 * @since Updated 5.12.0
		 */
		private static function get_license_type( $price_id, $license_data = null ) {

			$license_type = '';

			switch ( $price_id ) {

				case '1':
					$license_type = 'business';
					break;

				case '2':
					$license_type = 'enterprise';
					break;

				case '0':
				case '3':
				default:
					$license_type = 'starter';
					break;
			}

			// Consider starter licenses earlier purchased.
			$is_earlier_purchased = false;

			if ( is_null( $license_data ) ) {
				$license_data = self::fetch_license();
			}

			if ( isset( $license_data->payment_date ) && '' !== $license_data->payment_date ) {
				$is_earlier_purchased = 'lifetime' === $license_data->expires || ( strtotime( $license_data->payment_date ) <= strtotime( '2022-03-31' ) );
			}

			if ( $is_earlier_purchased ) {
				$license_type = 'enterprise';
			}

			return $license_type;
		}

		/**
		 * Plan Error Message.
		 *
		 * @param string $expected_plan Expected Plan that is valid for the restriced resouce.
		 *
		 * @since 5.12.0
		 */
		public static function license_error_message( $expected_plan ) {
			self::check_license_type();

			$message = self::$plugin_activate_error_message;

			if ( '' !== self::$license_status ) {
				$message = sprintf(
					/* translators: %1$s: Current Plan, %2$s: Expected Plan */
					__( self::$license_error_message, 'woocommerce-booking' ), //phpcs:ignore
					ucwords( self::$license_type ),
					ucwords( $expected_plan )
				);
			}

			return $message;
		}

		/**
		 * Checks if License is for Starter Plan.
		 *
		 * @since 5.12.0
		 */
		public static function starter_license() {
			self::check_license_type();
			return 'starter' === self::$license_type;
		}

		/**
		 * Starter Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function starter_license_error_message() {
			return self::license_error_message( 'starter' );
		}

		/**
		 * Checks if License is for Business Plan.
		 *
		 * @since 5.12.0
		 */
		public static function business_license() {
			self::check_license_type();
			return 'enterprise' === self::$license_type || 'business' === self::$license_type;
		}

		/**
		 * Business Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function business_license_error_message() {
			return self::license_error_message( 'business' );
		}

		/**
		 * Checks if License is for Enterprise Plan.
		 *
		 * @since 5.12.0
		 */
		public static function enterprise_license() {
			return 'enterprise' === self::$license_type;
		}

		/**
		 * Enterprise Plan Error Message.
		 *
		 * @since 5.12.0
		 */
		public static function enterprise_license_error_message() {
			return self::license_error_message( 'enterprise' );
		}

		/**
		 * Displays an error notice on the Admin page.
		 *
		 * @param string $notice Error notice to be displayed.
		 *
		 * @since 5.12.0
		 */
		public static function display_error_notice( $notice ) {
			printf( "<div class='notice notice-error'><p>%s</p></div>", $notice ); // phpcs:ignore
		}

		/**
		 * Displays an error notice if any of the Vendor Plugins are activated with an un-supported license.
		 *
		 * @since 5.12.0
		 */
		public static function vendor_plugin_license_error_notice() {

			global $current_screen;

			if ( 'page' !== $current_screen->post_type && 'post' !== $current_screen->post_type && 'update' !== $current_screen->base && ! self::business_license() ) {

				if ( class_exists( 'WeDevs_Dokan' ) ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'Dokan Multivendor',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}

				if ( function_exists( 'is_wcvendors_active' ) && is_wcvendors_active() ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'WC Vendors',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}

				if ( function_exists( 'is_wcfm_page' ) ) {
					$notice = sprintf(
						/* translators: %1$s: Vendor Plugin, %2$s: Current License, %3$s: Expected License */
						__( self::$plugin_license_error_message, 'woocommerce-booking' ), //phpcs:ignore
						'WCFM Marketplace',
						ucwords( self::$license_type ),
						'Business or Enterprise'
					);

					self::display_error_notice( $notice );
				}
			}
		}

		/**
		 * Removes WP Action Hooks that have been set in Add-on Plugins if License is not supported.
		 *
		 * @since 5.12.0
		 */
		public static function remove_wp_actions() {

			// Outlook Calendar Addon.
			if ( class_exists( 'Bkap_Outlook_Calendar' ) && ! self::enterprise_license() ) {

				$did_remove = self::remove_wp_action( 'bkap_global_integration_settings', 'bkap_outlook_calendar_options', 10 );

				if ( $did_remove ) {
					add_action( 'bkap_global_integration_settings', array( 'BKAP_License', 'show_enterprise_license_error_message' ), 10, 1 );
				}
			}
		}

		/**
		 * Displays the Enterprise License Error Message.
		 *
		 * @param string $screen Current Screen.
		 *
		 * @since 5.12.0
		 */
		public static function show_enterprise_license_error_message( $screen ) {
			if ( 'outlook_calendar' === $screen ) {
				?>
					<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::enterprise_license_error_message(); // phpcs:ignore; ?></div>
				<?php
			}
		}

		/**
		 * Removes WP Action Hook.
		 *
		 * @param string $action WP Action to be removed.
		 * @param string $function PHP function assigned to the hook.
		 * @param int    $priority Priority of WP Action.
		 *
		 * @since 5.12.0
		 */
		public static function remove_wp_action( $action, $function, $priority ) {

			global $wp_filter;

			$did_remove = false;

			$callbacks = $wp_filter[ $action ]->callbacks[ $priority ];

			foreach ( $callbacks as $callback_key => $callback ) {
				if ( false !== strpos( $callback_key, $function ) ) {
					unset( $wp_filter[ $action ]->callbacks[ $priority ][ $callback_key ] );
					$did_remove = true;
				}
			}

			return $did_remove;
		}

		/**
		 * Remove bkap_license_notice and error_code from URL.
		 *
		 * @param string $url URL.
		 *
		 * @since 5.15.0
		 */
		public static function return_url_without_bkap_notice( $url = '' ) {

			if ( '' === $url ) {
				$url = wp_get_referer();
			}

			if ( false !== strpos( $url, 'bkap_license_notice' ) ) {
				$url = remove_query_arg( 'bkap_license_notice', $url );
			}

			if ( false !== strpos( $url, 'bkap_license_error_code' ) ) {
				$url = remove_query_arg( 'bkap_license_error_code', $url );
			}

			return $url;
		}
	}
}

/**
 * Class Initilaization Function.
 *
 * @since 5.12.0
 */
function bkap_license() {
	return BKAP_License::init();
}

bkap_license();
