<?php
/**
 * It will Add all the Boilerplate component when we activate the plugin.
 *
 * @author  Tyche Softwares
 * @package BKAP/Admin/Component
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'BKAP_All_Component' ) ) {
	/**
	 * It will Add all the Boilerplate component when we activate the plugin.
	 */
	class BKAP_All_Component {

		/**
		 * It will Add all the Boilerplate component when we activate the plugin.
		 */
		public function __construct() {

			$bkap_plugin_name          = EDD_SL_ITEM_NAME_BOOK;
			$bkap_edd_license_option   = 'edd_sample_license_status';
			$bkap_license_path         = 'edit.php?post_type=bkap_booking&page=booking_license_page';
			$bkap_locale               = 'woocommerce-booking';
			$bkap_file_name            = 'woocommerce-booking/woocommerce-booking.php';
			$bkap_plugin_prefix        = 'bkap';
			$bkap_plugin_folder_name   = 'woocommerce-booking/';
			$bkap_plugin_dir_name      = BKAP_PLUGIN_PATH . '/woocommerce-booking.php';
			$bkap_get_previous_version = get_option( 'woocommerce_booking_db_version' );
			$bkap_plugins_page         = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';
			$bkap_plugin_slug          = 'edit.php?post_type=bkap_booking';
			$bkap_slug_for_faq_submenu = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';

			require_once 'component/plugin-tracking/class-tyche-plugin-tracking.php';
			new Tyche_Plugin_Tracking(
				array(
					'plugin_name'       => $bkap_plugin_name,
					'plugin_locale'     => $bkap_locale,
					'plugin_short_name' => $bkap_plugin_prefix,
					'version'           => BKAP_VERSION,
					'blog_link'         => 'https://www.tychesoftwares.com/booking-appointment-plugin-usage-tracking',
				)
			);

			add_filter( 'ts_tracker_data', array( 'bkap_common', 'bkap_ts_add_plugin_tracking_data' ), 10, 1 );

			if ( is_admin() ) {
				require_once 'component/license-active-notice/ts-active-license-notice.php';
				require_once 'component/WooCommerce-Check/ts-woo-active.php';
				require_once 'component/faq_support/ts-faq-support.php';
				require_once 'component/plugin-deactivation/class-tyche-plugin-deactivation.php';

				new Bkap_Active_License_Notice( $bkap_plugin_name, $bkap_edd_license_option, $bkap_license_path, $bkap_locale );
				new Bkap_TS_Woo_Active( $bkap_plugin_name, $bkap_file_name, $bkap_locale );

				add_action( 'admin_footer', array( __CLASS__, 'ts_admin_notices_scripts' ) );
				add_action( $bkap_plugin_prefix . '_add_new_settings', array( __CLASS__, 'ts_add_reset_tracking_setting' ) );
				add_action( 'admin_init', array( __CLASS__, 'ts_reset_tracking_setting' ) );
				add_action( $bkap_plugin_prefix . '_init_tracker_completed', array( __CLASS__, 'init_tracker_completed' ), 10, 2 );

				new Tyche_Plugin_Deactivation(
					array(
						'plugin_name'       => $bkap_plugin_name,
						'plugin_base'       => $bkap_file_name,
						'script_file'       => bkap_load_scripts_class::bkap_asset_url( '/assets/js/plugin-deactivation.js', BKAP_FILE ),
						'plugin_short_name' => $bkap_plugin_prefix,
						'version'           => BKAP_VERSION,
					)
				);

				$ts_pro_faq = self::bkap_get_faq();
				new Bkap_TS_Faq_Support( $bkap_plugin_name, $bkap_plugin_prefix, $bkap_plugins_page, $bkap_locale, $bkap_plugin_folder_name, $bkap_plugin_slug, $ts_pro_faq, $bkap_slug_for_faq_submenu );
			}
		}

		/**
		 * It will contain all the FAQ which need to be display on the FAQ page.
		 *
		 * @return array $ts_faq All questions and answers.
		 */
		public static function bkap_get_faq() {

			$ts_faq = array();

			$ts_faq = array(
				1  => array(
					'question' => 'What are different types of bookings I can setup with this plugin?',
					'answer'   => 'Four types of bookings can be setup with this plugin. 1. <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/types-of-bookings/recurring-weekdays-booking/">Single Day</a> 2. <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/types-of-bookings/setup-multiple-nights-booking-simple-product/" target="_blank">Multiple Nights</a> 3. <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/types-of-bookings/date-time-slot-booking/" target="_blank">Fixed Time</a> 4. <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/types-of-bookings/duration-based-booking/" target="_blank">Duration Based Time</a>.',
				),
				2  => array(
					'question' => 'With how many product types your Booking plugin is compatible?',
					'answer'   => 'Our Booking plugin is compatible with all default product types comes with WooCommerce. Also, we have made it compatible with <a href="https://woocommerce.com/products/product-bundles/" target="_blank">Bundle</a>, <a href="https://woocommerce.com/products/composite-products/" target="_blank">Composite</a>, and <a href="https://woocommerce.com/products/woocommerce-subscriptions/" target="_blank">Subscriptions</a> product type.',
				),
				3  => array(
					'question' => 'Can I restrict the number of bookings for each booking date?',
					'answer'   => 'Yes, by setting up the value in Max Bookings option you can restrict the number of bookings for each date. For Single Day and Date & Time booking type we have \'<a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/maximum-bookings-per-daydate-time-slot/">Max Bookings</a>\' option and for multiple nights we have \'<a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/types-of-bookings/setup-multiple-nights-booking-simple-product/" target="_blank">Maximum Bookings On Any Date</a>\' option in the Availability tab of Booking meta box.',
				),
				4  => array(
					'question' => 'Is it possible to change the booking details during the booking process?',
					'answer'   => 'Yes, we have Edit Bookings feature which allows editing the booking details on Cart and Checkout page. You can enable option from Booking-> Settings-> Global Booking Settings-> <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/edit-bookings/" target="_blank">Allow Bookings to be editable</a>.',
				),
				5  => array(
					'question' => 'Is it possible to view all the bookings from a single view?',
					'answer'   => 'Yes, we have <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/view-bookings-in-admin/" target="_blank">View Bookings</a> page where one can view, search and sort the bookings.',
				),
				6  => array(
					'question' => 'Do this plugin allows automatic sync the bookings with Google Calendar?',
					'answer'   => 'Yes. by setting up <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/synchronize-the-booking-dates-andor-time-with-google-calendar/" target="_blank">Google API for products, you can import and export the bookings automatically to the Google Calendar. Product-level settings are in \'<a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/synchronize-the-booking-dates-andor-time-with-google-calendar/product-level-export-automated/" target="_blank">Google Calendar Sync</a>\' tab of Booking meta box on Edit Product page.',
				),
				7  => array(
					'question' => 'How do I create a manual booking?',
					'answer'   => 'You can <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/addedit-orders-for-bookable-products-in-admin/" target="_blank">create manual booking</a> from Booking-> Create Booking page. While creating the booking, you can create new order for the booking or you can add the booking to already existing order.',
				),
				8  => array(
					'question' => 'Is it possible to allow the customer to make the booking without selecting the booking details?',
					'answer'   => 'Yes, we have \'<a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/allow-the-users-to-purchase-a-bookable-product-without-selecting-booking-details/" target="_blank">Purchase without choosing a date</a>\' option in the General tab of Booking meta box which allows the customer to purchase the product without selecting the booking details.',
				),
				9  => array(
					'question' => 'Can I translate the plugin string into my native language? If yes, then how?',
					'answer'   => 'You can use .po file of the plugin for translating the plugin strings. Or you can <a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/wpml-partners/" target="_blank">use WPML plugin for translating strings</a> as we have made our plugin compatible with <a href="https://wpml.org/" target="_blank">WPML plugin</a>.',
				),
				10 => array(
					'question' => 'Can I set bookable products that require confirmation?',
					'answer'   => 'Yes, by enabling \'<a href="https://www.tychesoftwares.com/docs/docs/booking-appointment-plugin-for-woocommerce/set-bookable-products-that-require-confirmation/" target="_blank">Requires Confirmation</a>\' option in the General tab of Booking meta box you can achieve it.',
				),
			);

			return $ts_faq;
		}

		/**
		 * It will add the settinig, which will allow store owner to reset the tracking data. Which will result into stop trakcing the data.
		 *
		 * @hook self::$plugin_prefix . '_add_new_settings'
		 */
		public static function ts_add_reset_tracking_setting() {

			add_settings_field(
				'ts_reset_tracking',
				__( 'Reset usage tracking', 'woocommerce-booking' ),
				array( __CLASS__, 'ts_rereset_tracking_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( 'This will reset your usage tracking settings, causing it to show the opt-in banner again and not sending any data.', 'woocommerce-booking' )
			);

			register_setting(
				'bkap_global_settings',
				'ts_reset_tracking'
			);
		}

		/**
		 * It will add the Reset button on the settings page.
		 *
		 * @param array $args
		 */
		public static function ts_rereset_tracking_callback( $args ) {
			$wcap_restrict_domain_address = get_option( 'wcap_restrict_domain_address' );
			$domain_value                 = isset( $wcap_restrict_domain_address ) ? esc_attr( $wcap_restrict_domain_address ) : '';
			// Next, we update the name attribute to access this element's ID in the context of the display options array.
			// We also access the show_header element of the options collection in the call to the checked() helper function.
			$ts_action = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page&ts_action=reset_tracking';
			printf( '<a href="' . $ts_action . '" class="button button-large reset_tracking">Reset</a>' );

			// Here, we'll take the first argument of the array and add it to a label next to the checkbox.
			echo '<label for="wcap_restrict_domain_address_label"> ' . $args[0] . '</label>';
		}

		/**
		 * Load the js file in the admin
		 *
		 * @since 6.8
		 * @access public
		 */
		public static function ts_admin_notices_scripts() {

			wp_enqueue_script(
				'bkap_ts_dismiss_notice',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/dismiss-tracking-notice.js', BKAP_FILE ),
				'',
				'',
				false
			);

			wp_localize_script(
				'bkap_ts_dismiss_notice',
				'bkap_ts_dismiss_notice',
				array(
					'ts_prefix_of_plugin' => 'bkap',
					'ts_admin_url'        => admin_url( 'admin-ajax.php' ),
				)
			);
		}

		/**
		 * It will delete the tracking option from the database.
		 */
		public static function ts_reset_tracking_setting() {
			if ( isset( $_GET ['ts_action'] ) && 'reset_tracking' == $_GET ['ts_action'] ) {
				Tyche_Plugin_Tracking::reset_tracker_setting( 'bkap' );
				$ts_url = remove_query_arg( 'ts_action' );
				wp_safe_redirect( $ts_url );
			}
		}

		/**
		 * Redirects after initializing the tracker.
		 */
		public static function init_tracker_completed() {
			header( 'Location: ' . admin_url( 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page' ) );
			exit;
		}
	}

	$BKAP_All_Component = new BKAP_All_Component();
}
