<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for Coupons that apply to Bookings.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.13.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_Coupons' ) ) {

	/**
	 * BKAP Coupon class.
	 *
	 * @since 5.13.0
	 */
	class BKAP_Coupons {

		/**
		 * Initializes the BKAP_Coupons() class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 5.13.0
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new BKAP_Coupons();
			}

			return $instance;
		}

		/**
		 * Default Constructor
		 *
		 * @since 5.13.0
		 */
		public function __construct() {
			add_filter( 'woocommerce_coupon_data_tabs', array( &$this, 'add_tab_item' ), 10 );
			add_action( 'woocommerce_coupon_data_panels', array( &$this, 'add_tab_content' ), 10 );
			add_action( 'woocommerce_coupon_options_save', array( &$this, 'save_bkap_coupon_option' ), 10, 2 );
			add_action( 'woocommerce_applied_coupon', array( &$this, 'validate_applied_coupon' ), 10, 1 );
			add_action( 'woocommerce_cart_item_removed', array( &$this, 'remove_applied_coupon' ), 10, 2 );
			add_action( 'bkap_updated_edited_bookings', array( &$this, 'remove_applied_coupon' ), 10, 2 );
		}

		/**
		 * Adds Booking Date Ranges menu item to the Coupon Tab.
		 *
		 * @since 5.13.0
		 *
		 * @param array $tabs Array of Coupon Menu Item Tabs.
		 */
		public function add_tab_item( $tabs ) {
			$tabs['bkap_date_range'] = array(
				'label'  => __( 'Booking Dates', 'woocommerce-booking' ),
				'target' => 'bkap_date_range_coupon_data',
				'class'  => 'bkap_date_range_coupon_data',
			);

			return $tabs;
		}

		/**
		 * Adds HTML content for the Booking Date Range menu item.
		 *
		 * @since 5.13.0
		 *
		 * @param int $coupon_id Coupon ID.
		 */
		public static function add_tab_content( $coupon_id ) {
			$bkap_coupon_start_date = self::fetch_coupon_data( $coupon_id, 'bkap_coupon_start_date' ) ? self::format_date( self::fetch_coupon_data( $coupon_id, 'bkap_coupon_start_date' ), false ) : '';
			$bkap_coupon_end_date   = self::fetch_coupon_data( $coupon_id, 'bkap_coupon_end_date' ) ? self::format_date( self::fetch_coupon_data( $coupon_id, 'bkap_coupon_end_date' ), false ) : '';
			?>

			<div id="bkap_date_range_coupon_data" class="panel woocommerce_options_panel">
				<h2>
					<?php esc_html_e( 'Specify Booking Start and End Dates for periods where the coupon can be used.', 'woocommerce-booking' ); ?>
				</h2>

			<?php

				woocommerce_wp_text_input(
					array(
						'id'                => 'bkap_coupon_start_date',
						'value'             => esc_attr( $bkap_coupon_start_date ),
						'label'             => __( 'Booking Start Date', 'woocommerce-booking' ),
						'placeholder'       => 'YYYY-MM-DD',
						'description'       => __( 'Coupon will not be applied to Bookings earlier than the Start Date.', 'woocommerce-booking' ),
						'desc_tip'          => true,
						'class'             => 'date-picker',
						'custom_attributes' => array(
							'pattern' => '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])',
						),
					)
				);

				woocommerce_wp_text_input(
					array(
						'id'                => 'bkap_coupon_end_date',
						'value'             => esc_attr( $bkap_coupon_end_date ),
						'label'             => __( 'Booking End Date', 'woocommerce-booking' ),
						'placeholder'       => 'YYYY-MM-DD',
						'description'       => __( 'Coupon will not be applied to Bookings later than End Date.', 'woocommerce-booking' ),
						'desc_tip'          => true,
						'class'             => 'date-picker',
						'custom_attributes' => array(
							'pattern' => '[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])',
						),
					)
				);
			?>
			</div>
				<?php
		}

		/**
		 * Saves Booking Start and End Date for the Coupon.
		 *
		 * @since 5.13.0
		 *
		 * @param int    $coupon_id ID of the saved coupon.
		 * @param string $coupon_code Coupon Code.
		 */
		public static function save_bkap_coupon_option( $coupon_id, $coupon_code ) {
			self::do_save_bkap_coupon_option( $coupon_id, 'bkap_coupon_start_date' );
			self::do_save_bkap_coupon_option( $coupon_id, 'bkap_coupon_end_date' );
		}

		/**
		 * Saves Booking Start and End Date for the Coupon.
		 *
		 * @since 5.13.0
		 *
		 * @param int    $coupon_id ID of the saved coupon.
		 * @param string $coupon_option_name Coupon Option Name.
		 */
		public static function do_save_bkap_coupon_option( $coupon_id, $coupon_option_name ) {

			$coupon_option_value = isset( $_POST[$coupon_option_name] ) ? wc_clean( $_POST[$coupon_option_name] ) : ''; //phpcs:ignore

			// If Option Name is empty, we make sure to remove from Coupon Data.
			if ( '' === $coupon_option_value ) {

				if ( self::fetch_coupon_data( $coupon_id, $coupon_option_name ) ) {
					delete_post_meta( $coupon_id, $coupon_option_name );
				}

				return;
			}

			// Check if meta name exists. If it does not, do an INSERT, else do an UPDATE.
			if ( ! self::fetch_coupon_data( $coupon_id, $coupon_option_name ) ) {
				add_post_meta( $coupon_id, $coupon_option_name, self::format_date( $coupon_option_value ) );
				return;
			}

			update_post_meta( $coupon_id, $coupon_option_name, self::format_date( $coupon_option_value ) );
		}

		/**
		 * Fetches Coupon data.
		 *
		 * @since 5.13.0
		 *
		 * @param int    $coupon_id ID of the saved coupon.
		 * @param string $coupon_option_name Option Name of propoerty to be retrieved from Coupon Data.
		 */
		public static function fetch_coupon_data( $coupon_id, $coupon_option_name ) {

			$coupon_data = get_post_meta( $coupon_id, $coupon_option_name, true );

			if ( ! $coupon_data || '' === $coupon_data ) {
				return false;
			}

			return $coupon_data;
		}

		/**
		 * Format date and converts it to the timestamp format or from timestamp back to date in string format.
		 *
		 * @since 5.13.0
		 *
		 * @param string $date Date.
		 * @param bool   $to_timestamp Boolean to either convert string date to timestamp format or vice versa..
		 */
		public static function format_date( $date, $to_timestamp = true ) {

			if ( $to_timestamp ) {

				// Convert date in string to timestamp.
				// We use WooCommerce functions to get timestamp string in UTC.
				$timestamp = wc_string_to_timestamp( get_gmt_from_date( gmdate( 'Y-m-d H:i:s', wc_string_to_timestamp( $date ) ) ) );
				$date_time = new WC_DateTime( "@{$timestamp}", new DateTimeZone( 'UTC' ) );

				// Set local timezone or offset.
				if ( get_option( 'timezone_string' ) ) {
					$date_time->setTimezone( new DateTimeZone( wc_timezone_string() ) );
				} else {
					$date_time->set_utc_offset( wc_timezone_offset() );
				}

				return $date_time->getTimestamp();
			}

			// Already in timestamp format.
			$date_time = new WC_DateTime( "@{$date}", new DateTimeZone( 'UTC' ) );
			return $date_time->date( 'Y-m-d' );
		}

		/**
		 * Validates applied code to ensure that it is within the Booking Date Range.
		 * There's no way we can stop the coupon from being applied. So we wait after it has been applied, and then remove it if it does not pass validation.
		 *
		 * @since 5.13.0
		 *
		 * @param string $coupon_code Coupon Code that has been applied.
		 */
		public static function validate_applied_coupon( $coupon_code ) {

			$coupon    = new WC_Coupon( $coupon_code );
			$coupon_id = $coupon->get_id();

			// Fetch Start and End Dates.
			$bkap_coupon_start_date = self::fetch_coupon_data( $coupon_id, 'bkap_coupon_start_date' ) ? self::format_date( self::fetch_coupon_data( $coupon_id, 'bkap_coupon_start_date' ), false ) : '';
			$bkap_coupon_end_date   = self::fetch_coupon_data( $coupon_id, 'bkap_coupon_end_date' ) ? self::format_date( self::fetch_coupon_data( $coupon_id, 'bkap_coupon_end_date' ), false ) : '';

			// Stop if both Start and End dates are empty.
			if ( '' === $bkap_coupon_start_date && '' === $bkap_coupon_end_date ) {
				return;
			}

			$invalid_products_for_coupon = array();

			// Get Booking Dates from the Cart.
			$booking_dates_from_cart = self::get_booking_dates_from_products_in_cart();

			if ( count( $booking_dates_from_cart ) > 0 ) {
				foreach ( $booking_dates_from_cart as $key => $value ) {
					$product_id   = ( '' === $value['variation_id'] ) ? $value['product_id'] : $value['variation_id'];
					$booking_date = $value['booking_date'];

					// Get Product Name.
					$product      = wc_get_product( $product_id );
					$product_name = $product->get_title();

					if ( '' !== $bkap_coupon_start_date ) {
						if ( strtotime( $bkap_coupon_start_date ) > strtotime( $booking_date ) ) {
							$invalid_products_for_coupon[] = $product_name;
						}
					}

					if ( '' !== $bkap_coupon_end_date && ! in_array( $product_name, $invalid_products_for_coupon ) ) {
						if ( strtotime( $booking_date ) > strtotime( $bkap_coupon_end_date ) ) {
							$invalid_products_for_coupon[] = $product_name;
						}
					}
				}
			}

			if ( count( $invalid_products_for_coupon ) > 0 ) {

				// Remove Applied Coupon.
				WC()->cart->remove_coupon( $coupon_code );

				// Notice message.
				$product_text = count( $invalid_products_for_coupon ) > 1 ? 'Some products in the cart have Booking Date(s) that are not supported by the Coupon for Bookings. Products: ' : 'A product in the cart has Booking Date(s) that are not supported by the Coupon for Bookings. Product: ';

				if ( 1 === count( $invalid_products_for_coupon ) ) {
					$product_list = $invalid_products_for_coupon[0];
				} else {
					$product_list   = array();
					$product_list[] = implode( ' and ', array_splice( $invalid_products_for_coupon, -2 ) );
					$product_list   = implode( ', ', $product_list );
				}

				$notice_message = sprintf(
					/* translators: &1$s: Product Text, %2$s: Product List - seperated with comma. */
					__( 'Coupon cannot be applied. %1$s %2$s', 'woocommerce-booking' ),
					$product_text,
					$product_list
				);

				wc_add_notice( $notice_message, 'error' );

				// Remove earlier coupon notice that was added when coupon was applied.
				// TODO: How do we remove this notice if the text has been changed via filter or appears in a different language?
				$coupon_sucess_text = 'Coupon code applied successfully.';
				$notices            = WC()->session->get( 'wc_notices', array() );
				$find               = array_search( $coupon_sucess_text, array_column( $notices['success'], 'notice' ) ); //phpcs:ignore

				if ( false !== $find ) {
					unset( $notices['success'][ $find ] );
				}

				WC()->session->set( 'wc_notices', $notices );
			}
		}

		/**
		 * Gets Booking Dates from Products added in the Cart.
		 *
		 * @since 5.13.0
		 */
		public static function get_booking_dates_from_products_in_cart() {

			$bookings_from_cart = array();

			if ( isset( WC()->cart ) ) {

				foreach ( WC()->cart->get_cart() as $key => $value ) {

					if ( isset( $value['bkap_booking'] ) && isset( $value['bkap_booking'][0] ) && is_array( $value['bkap_booking'][0] ) ) {
						$product_id   = $value['product_id'];
						$variation_id = isset( $value['variation_id'] ) && 0 !== $value['variation_id'] ? $value['variation_id'] : '';
						$booking_date = gmdate( 'Y-m-d', wc_string_to_timestamp( $value['bkap_booking'][0]['hidden_date'] ) );

						$bookings_from_cart[] = array(
							'product_id'   => $product_id,
							'variation_id' => $variation_id,
							'booking_date' => $booking_date,
						);
					}
				}
			}

			return $bookings_from_cart;
		}

		/**
		 * Removes all applied coupons when an item has been removed from the cart or when the Booking has been edited.
		 *
		 * @param string $cart_item_key Key of Cart Item that has been removed.
		 * @param string $instance Instance of the cart class.
		 *
		 * @since 5.13.1
		 */
		public static function remove_applied_coupon( $cart_item_key, $instance ) {

			// Remove all coupons that have been applied.
			foreach ( WC()->cart->get_coupons() as $coupon_code => $coupon ) {
				WC()->cart->remove_coupon( $coupon_code );
			}

			// Set session cart again when booking has been edited from the cart page. This is because the remove_coupon function sets a different value in the session cart.
			if ( 'bkap_updated_edited_bookings' === $cart_item_key ) {
				WC()->session->set( 'cart', $instance );
			}
		}
	}
}

/**
 * BKAP Coupons instance.
 *
 * @since 5.13.0
 */
function bkap_coupons() {
	return BKAP_Coupons::init();
}

bkap_coupons();
