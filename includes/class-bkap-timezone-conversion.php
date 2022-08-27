<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Timezone Conversion
 *
 * @author   Tyche Softwares
 * @package  BKAP/Timezone-Conversion
 * @category Classes
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_Timezone_Conversion' ) ) {

	/**
	 * Class for Timezone Conversion
	 */
	class Bkap_Timezone_Conversion {

		/**
		 * Constructor
		 *
		 * @since 4.15.0
		 */
		public function __construct() {

			add_action( 'init', array( $this, 'start_session' ) );
			add_action( 'wp_footer', array( $this, 'bkap_wp_footer' ), 1 );
			add_action( 'bkap_before_booking_form', array( $this, 'bkap_display_user_timezone' ), 3, 3 );
			
			// add_filter( 'bkap_time_slot_filter', 			array( $this, 'bkap_time_slot_filter_callback'), 10, 1 );
			add_filter( 'bkap_addon_add_cart_item_data', array( $this, 'bkap_addon_add_cart_item_data_callback' ), 10, 6 );
			add_filter( 'bkap_get_item_data', array( $this, 'bkap_get_item_data_callback' ), 10, 2 );
			add_action( 'bkap_update_item_meta', array( $this, 'bkap_update_item_meta_callback' ), 10, 3 );
			
			// enabling appropriate specific dates based on timezone selected.
			add_filter( 'bkap_specific_dates', array( $this, 'bkap_specific_dates_callback' ), 10, 3 );
			add_action( 'wp_ajax_nopriv_set_timezone_session', array( $this, 'set_timezone_session' ) );
		}

		/**
		 * This function check for specific dates based on the timezone and return appropriate dates in date picker.
		 *
		 * @param string $specific_dates Lists of specific date.
		 * @param int    $product_id Product ID.
		 * @param array  $booking_settings Booking Settings.
		 * @since 4.19.2
		 *
		 * @return string
		 */
		public static function bkap_specific_dates_callback( $specific_dates, $product_id, $booking_settings ) {

			if ( isset( $booking_settings['booking_enable_time'] ) && 'on' === $booking_settings['booking_enable_time'] ) {

				$global_settings = bkap_global_setting();
				$timezone_check  = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled.

				if ( $timezone_check && '' !== self::get_timezone_var( 'bkap_timezone_name' ) ) {
					$time_format_to_show   = bkap_common::bkap_get_time_format( $global_settings );
					$s_dates               = explode( ',', $specific_dates );
					$final_dates           = array();
					$store_timezone_string = bkap_booking_get_timezone_string(); // fetching timezone string set for the store. E.g Asia/Calcutta.

					foreach ( $s_dates as $date ) { // "j-n-Y".
						$date = substr( $date, 1, -1 ); // j-n-Y.

						$dates   = array();
						$dates[] = bkap_convert_date_from_to_timezone( $date . ' 00:00', self::get_timezone_var( 'bkap_timezone_name' ), $store_timezone_string, 'j-n-Y' );
						$dates[] = bkap_convert_date_from_to_timezone( $date . ' 23:59', self::get_timezone_var( 'bkap_timezone_name' ), $store_timezone_string, 'j-n-Y' );

						// could be same date so removing duplicates.
						$dates = array_unique( $dates );

						$date_time_drop_down = array();
						foreach ( $dates as $key => $value ) {
							$date_time_drop_down[ $value ] = bkap_booking_process::get_time_slot( $value, $product_id );
						}

						if ( count( $date_time_drop_down ) > 0 ) {

							$current_date_str = strtotime( $date ); // timestamp of date in UTC timezone.
							$current_date_ymd = date( 'Y-m-d', $current_date_str ); // getting date in Y-m-d format.
							$offset           = bkap_get_offset_from_date( $current_date_str, self::get_timezone_var( 'bkap_timezone_name' ) ); // fetching the timezone str.

							foreach ( $date_time_drop_down as $key => $value ) {

								if ( '' === $value ) {
									continue;
								}

								date_default_timezone_set( $store_timezone_string );

								$time_drop_down_array = explode( '|', $value );
								$time_drop_down_array = bkap_sort_time_in_chronological( $time_drop_down_array );

								foreach ( $time_drop_down_array as $k => $v ) {

									if ( '' !== $v ) {
										$vexplode    = explode( ' - ', $v );
										$fromtime    = strtotime( $key . ' ' . $vexplode[0] );
										$from_time   = date( $time_format_to_show, $offset + $fromtime );
										$timedatejny = date( 'j-n-Y', $offset + $fromtime );
										$timedatejny = '"' . $timedatejny . '"';

										if ( ! in_array( $timedatejny, $final_dates ) ) {
											$final_dates[] = $timedatejny;
										}
									}
								}

								date_default_timezone_set( 'UTC' );
							}
						}
					}

					$specific_dates = implode( ',', $final_dates );
				}
			}

			return $specific_dates;
		}

		/**
		 * This function will reload the page if directly accessing to product page.
		 *
		 * @since 4.15.0
		 */

		public function bkap_wp_footer() {

			if ( is_admin() ) {
				return;
			}

			self::bkap_set_user_timezone();

			if ( 'product' === get_post_type() ) {

				/* 
				Link: http://www.onlineaspect.com/2007/06/08/auto-detect-a-time-zone-with-javascript/
				Link: https://stackoverflow.com/questions/9772955/how-can-i-get-the-timezone-name-in-javascript
				*/

				$global_settings = bkap_global_setting();
				$timezone_check  = bkap_timezone_check( $global_settings );

				if ( $timezone_check ) {
					if ( ! isset( $_COOKIE['bkap_timezone_name'] ) && ! isset( $_SESSION['bkap']['timezone']['bkap_timezone_name'] ) ) {
						?>
						<script type="text/javascript">
							setTimeout( function() {
								window.location.reload();
							}, 1500 );
						</script>
						<?php
					}
				}
			}
		}

		/**
		 * Adds item meta for bookable products when an order is placed.
		 *
		 * @param integer $item_id Item ID.
		 * @param integer $product_id Product ID.
		 * @param array   $booking_data Booking Data.
		 * @since 4.15.0
		 */
		public static function bkap_update_item_meta_callback( $item_id, $product_id, $booking_data ) {

			if ( isset( $booking_data['timezone_name'] ) && '' !== $booking_data['timezone_name'] ) {
				$timezone_str = __( 'Time Zone', 'woocommerce-booking' );
				wc_add_order_item_meta( $item_id, '_wapbk_timezone', sanitize_text_field( $booking_data['timezone_name'], true ) );
				wc_add_order_item_meta( $item_id, '_wapbk_timeoffset', sanitize_text_field( $booking_data['timezone_offset'], true ) );
				wc_add_order_item_meta( $item_id, $timezone_str, sanitize_text_field( $booking_data['timezone_name'], true ) );
			}
		}

		/**
		 * This function displays the Timezone information in the cart item.
		 *
		 * @param mixed $other_data Cart Meta Data Object.
		 * @param mixed $cart_item Session Cart Item Object.
		 *
		 * @return array other_data Name and value pair of item details.
		 *
		 * @since 4.15.0
		 *
		 * @hook bkap_get_item_data
		 */
		public static function bkap_get_item_data_callback( $other_data, $cart_item ) {

			$booking = $cart_item['bkap_booking'][0];

			if ( isset( $booking['timezone_name'] ) ) {
				$timezone_label = __( 'Timezone', 'woocommerce-booking' );
				$other_data[]   = array(
					'name'    => $timezone_label,
					'display' => $booking['timezone_name'],
				);
			}

			return $other_data;
		}

		/**
		 * This function adds the timezone infomration in the booking array in cart
		 *
		 * @param Array  $cart_arr Array of booking details
		 * @param Int    $product_id Product ID
		 * @param Int    $variation_id Variation ID
		 * @param Array  $cart_item_meta Cart Array
		 * @param Array  $booking_settings Booking Settings
		 * @param Object $global_settings Global Booking Settings
		 *
		 * @return array $cart_arr Array of booking details along with timezone information
		 *
		 * @since 4.15.0
		 *
		 * @hook bkap_addon_add_cart_item_data
		 */
		public static function bkap_addon_add_cart_item_data_callback( $cart_arr, $product_id, $variation_id, $cart_item_meta, $booking_settings, $global_settings ) {

			if ( isset( $cart_arr['time_slot'] ) && '' != $cart_arr['time_slot'] ) { // Add only when Date & Time Product

				$timezone_check = bkap_timezone_check( $global_settings ); // Check if the timezone setting is enabled

				if ( $timezone_check ) {
					if ( '' !== self::get_timezone_var( 'bkap_timezone_name' ) ) {
						$cart_arr['timezone_name']   = self::get_timezone_var( 'bkap_timezone_name' );
						$cart_arr['timezone_offset'] = self::get_timezone_var( 'bkap_offset' );
					}
				}
			}

			return $cart_arr;
		}

		/**
		 * This function display timezone of the client on Booking Form.
		 *
		 * @param Int   $product_id Product ID.
		 * @param Array $booking_settings Booking Settings.
		 * @param Array $hidden_dates Booking Dates.
		 *
		 * @since 4.15.0
		 *
		 * @hook bkap_before_booking_form
		 */
		public static function bkap_display_user_timezone( $product_id, $booking_settings, $hidden_dates ) {

			if ( is_admin() ) { // Return if user is at admin end.
				return;
			}

			/* Timezone will be applicable only for Fixed Time and Duration Based Time Booking Type */
			if ( isset( $booking_settings['booking_enable_time'] ) && $booking_settings['booking_enable_time'] == 'on' ) {

				$global_settings = bkap_global_setting();
				$timezone_check  = bkap_timezone_check( $global_settings );

				if ( $timezone_check ) { // Timezone offset is set in cookie or session.

					if ( '' !== self::get_timezone_var( 'bkap_offset' ) && '' !== self::get_timezone_var( 'bkap_offset' ) ) {

						$storetime    = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ); // store time +5:30.
						$current_time = time(); // utc0 timestamp.
						$utctime      = date( 'Y-m-d H:i:s', time() );

						$timezone_offset_minutes = self::get_timezone_var( 'bkap_offset' ) * 60;
						$client_timestamp        = $current_time + $timezone_offset_minutes;
						$client_time             = date( 'Y-m-d H:i:s', $client_timestamp );

						$timezone_offset_minutes = self::get_timezone_var( 'bkap_offset' );
						$timezone_name           = timezone_name_from_abbr( '', $timezone_offset_minutes * 60, false );

						?>
						<p class="bkap-timezone-block" align="center" data-bkap-timezone-name="<?php echo str_replace( '_', ' ', self::get_timezone_var( 'bkap_timezone_name' ) ); ?>">
							<?php esc_html_e( 'Times are in ', 'woocommerce-booking' ); ?>
							<span class="bkap-timezone"><?php echo str_replace( '_', ' ', self::get_timezone_var( 'bkap_timezone_name' ) ); ?></span>
						</p>
						<?php
					}
				}
			}
		}

		/**
		 * This function fetch the timezone and its offset via Javascript and add it Cookie
		 *
		 * @since 4.15.0
		 *
		 * @hook wp_head
		 */
		public static function bkap_set_user_timezone() {

			if ( is_admin() ) {
				return;
			}

			/**
			 * Link: http://www.onlineaspect.com/2007/06/08/auto-detect-a-time-zone-with-javascript/
			 * Link:  https://stackoverflow.com/questions/9772955/how-can-i-get-the-timezone-name-in-javascript
			 */

			$global_settings = bkap_global_setting();
			$timezone_check  = bkap_timezone_check( $global_settings );
			$nonce           = wp_create_nonce( 'bkap_set_user_timezone' );
			$ajaxurl         = admin_url( 'admin-ajax.php' );

			if ( $timezone_check ) {
				?>
				<script type="text/javascript">

					let timezone_offset_minutes = new Date().getTimezoneOffset();
					timezone_offset_minutes 	= timezone_offset_minutes == 0 ? 0 : -timezone_offset_minutes;

					let timezone_name = Intl.DateTimeFormat().resolvedOptions().timeZone;

					// Check if cookie is enabled on the site.

					if ( navigator.cookieEnabled ) {

						let cookie_timezone_name = bkapGetCookie( "bkap_timezone_name" );

						if ( '' === cookie_timezone_name || cookie_timezone_name !== timezone_name ) {
							document.cookie 			= "bkap_offset="+timezone_offset_minutes+"; path=/";
							document.cookie 			= "bkap_timezone_name="+timezone_name+"; path=/";
						}
					} else {
						sendTimeZoneToBackEnd( timezone_offset_minutes, timezone_name );
					}

					/**
					 * Function to get browser cookies
					 **/

					function bkapGetCookie( cname ) {
						var name          = cname + "=";
						var decodedCookie = decodeURIComponent(document.cookie);
						var ca            = decodedCookie.split(';');
						for( var i = 0; i < ca.length; i++ ) {
							var c = ca[i];
							while (c.charAt(0) == ' ') {
								c = c.substring(1);
							}
							if (c.indexOf(name) == 0) {
							return c.substring(name.length, c.length);
							}
						}
						return '';
					}

					/**
					 * Function to send detected timezone to back-end via AJAX ( in cases where cookies have been disabled ).
					 **/

					function sendTimeZoneToBackEnd( bkap_offset, bkap_timezone_name ) {

						jQuery( document ).ready( function() {

							let nonce = '<?php echo $nonce; ?>',
								ajaxurl = '<?php echo $ajaxurl; ?>';

							jQuery.post( ajaxurl, {
								action: 'set_timezone_session',
								bkap_offset,
								bkap_timezone_name,
								nonce
							});  
						})
					}
				</script>
				<?php
			}
		}

		/**
		 * This function ensures that the session is started. Sessions will be used as fallback incase cookies have been disabled.
		 *
		 * @since 5.12.0
		 */
		public static function start_session() {

			if ( 'BkapTimezoneSession' !== session_id() ) {
				session_id( 'BkapTimezoneSession' );
				session_start();
			}
		}

		/**
		 * This function sets the timezone using SESSION.
		 *
		 * @since 5.12.0
		 */
		public static function set_timezone_session() {

			if ( ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'bkap_set_user_timezone' ) ) { //phpcs:ignore
				return;
			}

			self::start_session();

			$bkap_offset        = isset( $_POST['bkap_offset'] ) ? wp_unslash( $_POST['bkap_offset'] ) : ''; //phpcs:ignore
			$bkap_timezone_name = isset( $_POST['bkap_timezone_name'] ) ? wp_unslash( $_POST['bkap_timezone_name'] ) : ''; //phpcs:ignore

			if ( '' !== $bkap_offset ) {
				$_SESSION['bkap']['timezone']['bkap_offset'] = $bkap_offset;
			}

			if ( '' !== $bkap_timezone_name ) {
				$_SESSION['bkap']['timezone']['bkap_timezone_name'] = $bkap_timezone_name;
			}

			die();
		}

		/**
		 * This function retrieves timezone related value from COOKIE first, and if COOKIE is disabled, then from the SESSION.
		 *
		 * @param String $timezone_var Timezone Variable to be retrieved.
		 *
		 * @since 5.12.0
		 */
		public static function get_timezone_var( $timezone_var ) {

			self::start_session();

			if ( isset( $_COOKIE[ $timezone_var ] ) && ! empty( $_COOKIE[ $timezone_var ] ) && '' !== $_COOKIE[ $timezone_var ] ) {
				return wp_unslash( $_COOKIE[ $timezone_var ] );
			}

			// Return variable from SESSION.
			return isset( $_SESSION['bkap']['timezone'][ $timezone_var ] )? wp_unslash( $_SESSION['bkap']['timezone'][ $timezone_var ] ) : '';
		}
	}

	$bkap_timezone_conversion = new Bkap_Timezone_Conversion();
}
