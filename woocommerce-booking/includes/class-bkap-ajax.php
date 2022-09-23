<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for AJAX
 *
 * @author   Tyche Softwares
 * @package  BKAP/Plugin-AJAX
 * @category Classes
 * @class    Bkap_Ajax
 */

if ( ! class_exists( 'Bkap_Ajax' ) ) {

	/**
	 * Class Bkap_Ajax.
	 *
	 * @since 5.3.0
	 */
	class Bkap_Ajax {

		/**
		 * Bkap_Ajax constructor.
		 */
		public function __construct() {
			/**
			 * Ajax calls
			 */
			add_action( 'init', array( &$this, 'bkap_book_load_ajax' ) );
			add_action( 'admin_init', array( &$this, 'bkap_book_load_ajax_admin' ) );
		}

		/**
		 * This function is used to load ajax functions required by plugin.
		 *
		 * @since 1.7.0
		 */
		public function bkap_book_load_ajax() {

			if ( ! is_user_logged_in() ) {
				add_action( 'wp_ajax_nopriv_bkap_get_per_night_price', array( 'bkap_booking_process', 'bkap_get_per_night_price' ) );
				add_action( 'wp_ajax_nopriv_bkap_check_for_time_slot', array( 'bkap_booking_process', 'bkap_check_for_time_slot' ) );
				add_action( 'wp_ajax_nopriv_bkap_insert_date', array( 'bkap_booking_process', 'bkap_insert_date' ) );
				add_action( 'wp_ajax_nopriv_bkap_date_datetime_price', array( 'bkap_booking_process', 'bkap_date_datetime_price' ) );
				add_action( 'wp_ajax_nopriv_bkap_js', array( 'bkap_booking_process', 'bkap_js' ) );
				add_action( 'wp_ajax_nopriv_bkap_date_lockout', array( 'bkap_booking_process', 'bkap_date_lockout' ) );
				add_action( 'wp_ajax_nopriv_bkap_get_time_lockout', array( 'bkap_booking_process', 'bkap_get_time_lockout' ) );
				add_action( 'wp_ajax_nopriv_save_widget_dates', array( 'Custom_WooCommerce_Widget_Product_Search', 'save_widget_dates' ) );
				add_action( 'wp_ajax_nopriv_clear_widget_dates', array( 'Custom_WooCommerce_Widget_Product_Search', 'clear_widget_dates' ) );
				add_action( 'wp_ajax_nopriv_bkap_booking_calender_content', array( 'Bkap_Calendar_View', 'bkap_booking_calender_content' ) );
				add_action( 'wp_ajax_nopriv_bkap_purchase_wo_date_price', array( 'bkap_booking_process', 'bkap_purchase_wo_date_price' ) );
				add_action( 'wp_ajax_nopriv_bkap_send_reminder_action', array( 'Bkap_Send_Reminder', 'bkap_send_reminder_action' ) );
				add_action( 'wp_ajax_nopriv_bkap_delete_reminder', array( 'Bkap_Send_Reminder', 'bkap_delete_reminder' ) );
				add_action( 'wp_ajax_nopriv_bkap_reminder_test', array( 'Bkap_Send_Reminder', 'bkap_reminder_test' ) );
				add_action( 'wp_ajax_nopriv_bkap_preview_reminder', array( 'Bkap_Send_Reminder', 'bkap_preview_reminder' ) );
				add_action( 'wp_ajax_nopriv_bkap_save_reminder_message', array( 'Bkap_Send_Reminder', 'bkap_save_reminder_message' ) );
			} else {
				add_action( 'wp_ajax_bkap_get_per_night_price', array( 'bkap_booking_process', 'bkap_get_per_night_price' ) );
				add_action( 'wp_ajax_bkap_check_for_time_slot', array( 'bkap_booking_process', 'bkap_check_for_time_slot' ) );
				add_action( 'wp_ajax_bkap_insert_date', array( 'bkap_booking_process', 'bkap_insert_date' ) );
				add_action( 'wp_ajax_bkap_date_datetime_price', array( 'bkap_booking_process', 'bkap_date_datetime_price' ) );
				add_action( 'wp_ajax_bkap_js', array( 'bkap_booking_process', 'bkap_js' ) );
				add_action( 'wp_ajax_bkap_date_lockout', array( 'bkap_booking_process', 'bkap_date_lockout' ) );
				add_action( 'wp_ajax_bkap_get_time_lockout', array( 'bkap_booking_process', 'bkap_get_time_lockout' ) );
				add_action( 'wp_ajax_save_widget_dates', array( 'Custom_WooCommerce_Widget_Product_Search', 'save_widget_dates' ) );
				add_action( 'wp_ajax_clear_widget_dates', array( 'Custom_WooCommerce_Widget_Product_Search', 'clear_widget_dates' ) );
				add_action( 'wp_ajax_bkap_booking_calender_content', array( 'Bkap_Calendar_View', 'bkap_booking_calender_content' ) );
				add_action( 'wp_ajax_bkap_purchase_wo_date_price', array( 'bkap_booking_process', 'bkap_purchase_wo_date_price' ) );
				add_action( 'wp_ajax_bkap_send_reminder_action', array( 'Bkap_Send_Reminder', 'bkap_send_reminder_action' ) );
				add_action( 'wp_ajax_bkap_reminder_test', array( 'Bkap_Send_Reminder', 'bkap_reminder_test' ) );
				add_action( 'wp_ajax_bkap_preview_reminder', array( 'Bkap_Send_Reminder', 'bkap_preview_reminder' ) );
				add_action( 'wp_ajax_bkap_delete_reminder', array( 'Bkap_Send_Reminder', 'bkap_delete_reminder' ) );
				add_action( 'wp_ajax_bkap_save_reminder_message', array( 'Bkap_Send_Reminder', 'bkap_save_reminder_message' ) );
			}

			add_action( 'wc_ajax_bkap_add_notice', array( 'bkap_common', 'bkap_add_notice' ) );
			add_action( 'wc_ajax_bkap_clear_notice', array( 'bkap_common', 'bkap_clear_notice' ) );
		}

		/**
		 * Load Admin Ajax used accross the plugin
		 *
		 * @since 1.7.0
		 */
		public function bkap_book_load_ajax_admin() {
			add_action( 'wp_ajax_bkap_save_attribute_data', array( 'bkap_attributes', 'bkap_save_attribute_data' ) );
			add_action( 'wp_ajax_bkap_discard_imported_event', array( 'import_bookings', 'bkap_discard_imported_event' ) );
			add_action( 'wp_ajax_bkap_map_imported_event', array( 'import_bookings', 'bkap_map_imported_event' ) );
			add_action( 'wp_ajax_bkap_save_settings', array( 'bkap_booking_box_class', 'bkap_save_settings' ) );
			add_action( 'wp_ajax_bkap_execute_data', array( 'Bkap_Bulk_Booking_Settings', 'bkap_execute_data' ) );
			add_action( 'wp_ajax_bkap_clear_defaults', array( 'Bkap_Bulk_Booking_Settings', 'bkap_clear_defaults' ) );
			add_action( 'wp_ajax_bkap_delete_date_time', array( 'bkap_booking_box_class', 'bkap_delete_date_time' ) );
			add_action( 'wp_ajax_bkap_update_date_time_slot', array( 'bkap_booking_box_class', 'bkap_update_date_time_slot' ) );
			add_action( 'wp_ajax_bkap_delete_all_date_time', array( 'bkap_booking_box_class', 'bkap_delete_all_date_time' ) );
			add_action( 'wp_ajax_bkap_delete_specific_range', array( 'bkap_booking_box_class', 'bkap_delete_specific_range' ) );
			add_action( 'wp_ajax_bkap_delete_booking', array( 'bkap_cancel_order', 'bkap_trash_booking' ) );
			add_action( 'wp_ajax_bkap_load_time_slots', array( 'bkap_booking_box_class', 'bkap_load_time_slots' ) );
			add_action( 'wp_ajax_bkap_test_sms', array( 'Bkap_SMS_settings', 'bkap_send_test_sms' ) );
		}
	}
	$bkap_ajax = new Bkap_Ajax();
}
