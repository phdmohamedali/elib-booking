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

			$is_admin = is_admin();

			if ( true === $is_admin ) {

				add_filter( 'ts_tracker_data', array( 'bkap_common', 'bkap_ts_add_plugin_tracking_data' ), 10, 1 );
				add_filter( 'ts_tracker_opt_out_data', array( 'bkap_common', 'bkap_get_data_for_opt_out' ), 10, 1 );
				add_filter( 'ts_deativate_plugin_questions', array( 'bkap_common', 'bkap_deactivate_add_questions' ), 10, 1 );

				require_once 'component/license-active-notice/ts-active-license-notice.php';
				require_once 'component/WooCommerce-Check/ts-woo-active.php';

				require_once 'component/tracking data/ts-tracking.php';
				require_once 'component/deactivate-survey-popup/class-ts-deactivation.php';

				require_once 'component/faq_support/ts-faq-support.php';

				$bkap_plugin_name        = self::ts_get_plugin_name();
				$bkap_edd_license_option = 'edd_sample_license_status';
				$bkap_license_path       = 'edit.php?post_type=bkap_booking&page=booking_license_page';
				$bkap_locale             = self::ts_get_plugin_locale();
				$bkap_file_name          = 'woocommerce-booking/woocommerce-booking.php';
				$bkap_plugin_prefix      = 'bkap';
				$bkap_plugin_folder_name = 'woocommerce-booking/';
				$bkap_plugin_dir_name    = BKAP_PLUGIN_PATH . '/woocommerce-booking.php';

				$bkap_blog_post_link = 'https://www.tychesoftwares.com/booking-appointment-plugin-usage-tracking/';

				$bkap_get_previous_version = get_option( 'woocommerce_booking_db_version' );

				$bkap_plugins_page         = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';
				$bkap_plugin_slug          = 'edit.php?post_type=bkap_booking';
				$bkap_slug_for_faq_submenu = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';

				$bkap_settings_page    = 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page';
				$bkap_setting_add_on   = 'bkap_global_settings_page';
				$bkap_setting_section  = 'bkap_global_settings_section';
				$bkap_register_setting = 'bkap_global_settings';

				new Bkap_Active_License_Notice( $bkap_plugin_name, $bkap_edd_license_option, $bkap_license_path, $bkap_locale );

				new Bkap_TS_Woo_Active( $bkap_plugin_name, $bkap_file_name, $bkap_locale );

				new Bkap_TS_tracking( $bkap_plugin_prefix, $bkap_plugin_name, $bkap_blog_post_link, $bkap_locale, BKAP_PLUGIN_URL, $bkap_settings_page, $bkap_setting_add_on, $bkap_setting_section, $bkap_register_setting );

				new Bkap_TS_Tracker( $bkap_plugin_prefix, $bkap_plugin_name );

				$wcap_deativate = new Bkap_TS_deactivate();
				$wcap_deativate->init( $bkap_file_name, $bkap_plugin_name );

				$user = wp_get_current_user();

				if ( in_array( 'administrator', (array) $user->roles ) ) {
					// new Bkap_TS_Welcome ( $bkap_plugin_name, $bkap_plugin_prefix, $bkap_locale, $bkap_plugin_folder_name, $bkap_plugin_dir_name, $bkap_get_previous_version );
				}
				$ts_pro_faq = self::bkap_get_faq();
				new Bkap_TS_Faq_Support( $bkap_plugin_name, $bkap_plugin_prefix, $bkap_plugins_page, $bkap_locale, $bkap_plugin_folder_name, $bkap_plugin_slug, $ts_pro_faq, $bkap_slug_for_faq_submenu );

			}
		}

		/**
		 * It will retrun the plguin name.
		 *
		 * @return string $ts_plugin_name Name of the plugin
		 */
		public static function ts_get_plugin_name() {
			$bkap_plugin_dir  = dirname( dirname( __FILE__ ) );
			$bkap_plugin_dir .= '/woocommerce-booking.php';

			$ts_plugin_name = '';
			$plugin_data    = get_file_data( $bkap_plugin_dir, array( 'name' => 'Plugin Name' ) );
			if ( ! empty( $plugin_data['name'] ) ) {
				$ts_plugin_name = $plugin_data['name'];
			}
			return $ts_plugin_name;
		}

		/**
		 * It will retrun the Plugin text Domain
		 *
		 * @return string $ts_plugin_domain Name of the Plugin domain
		 */
		public static function ts_get_plugin_locale() {
			$bkap_plugin_dir  = dirname( dirname( __FILE__ ) );
			$bkap_plugin_dir .= '/woocommerce-booking.php';

			$ts_plugin_domain = '';
			$plugin_data      = get_file_data( $bkap_plugin_dir, array( 'domain' => 'Text Domain' ) );
			if ( ! empty( $plugin_data['domain'] ) ) {
				$ts_plugin_domain = $plugin_data['domain'];
			}
			return $ts_plugin_domain;
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
	}
	$BKAP_All_Component = new BKAP_All_Component();
}
