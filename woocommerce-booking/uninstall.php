<?php
/**
 * Booking & Appointment Plugin for WooCommerce
 *
 * Uninstalling Booking & Appointment Plugin deletes tables and options.
 *
 * @author      Tyche Softwares
 * @category    Core
 * @package     BKAP/Uninstall
 * @version     4.1.4
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/**
 * Delete the data for the WordPress Multisite.
 */
if ( is_multisite() ) {

	$bkap_blog_list = get_sites();

	foreach ( $bkap_blog_list as $bkap_blog_list_key => $bkap_blog_list_value ) {


		$bkap_blog_id = $bkap_blog_list_value->blog_id;

		/**
		 * It indicates the sub site id.
		 */
		if ( $bkap_blog_id > 1 ) {
			$bkap_multisite_prefix = $wpdb->prefix . $bkap_blog_id . '_';
		} else {
			$bkap_multisite_prefix = $wpdb->prefix;
		}

		bkap_delete_tables( $bkap_multisite_prefix );

		bkap_delete_options( $bkap_multisite_prefix );

		// Delete the global booking settings
		delete_blog_option( $bkap_blog_id, 'woocommerce_booking_global_settings' );

		// Add delete option for all the gcal settings, import events and so on
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_event_location' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_event_summary' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_event_description' );
		delete_blog_option( $bkap_blog_id, 'bkap_add_to_calendar_order_received_page' );
		delete_blog_option( $bkap_blog_id, 'bkap_add_to_calendar_customer_email' );
		delete_blog_option( $bkap_blog_id, 'bkap_add_to_calendar_my_account_page' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_in_same_window' );
		delete_blog_option( $bkap_blog_id, 'bkap_allow_tour_operator_gcal_api' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_sync_integration_mode' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_details_1' );
		delete_blog_option( $bkap_blog_id, 'bkap_admin_add_to_calendar_view_booking' );
		delete_blog_option( $bkap_blog_id, 'bkap_admin_add_to_calendar_email_notification' );
		delete_blog_option( $bkap_blog_id, 'bkap_event_item_ids' );
		delete_blog_option( $bkap_blog_id, 'bkap_event_uids_ids' );
		delete_blog_option( $bkap_blog_id, 'bkap_ics_feed_urls' );
		delete_blog_option( $bkap_blog_id, 'bkap_cron_time_duration' );

		// Deleting all the labels set
		delete_blog_option( $bkap_blog_id, 'book_date-label' );
		delete_blog_option( $bkap_blog_id, 'checkout_date-label' );
		delete_blog_option( $bkap_blog_id, 'bkap_calendar_icon_file' );
		delete_blog_option( $bkap_blog_id, 'book_time-label' );
		delete_blog_option( $bkap_blog_id, 'book_time-select-option' );
		delete_blog_option( $bkap_blog_id, 'book_fixed-block-label' );
		delete_blog_option( $bkap_blog_id, 'book_price-label' );
		delete_blog_option( $bkap_blog_id, 'book_item-meta-date' );
		delete_blog_option( $bkap_blog_id, 'checkout_item-meta-date' );
		delete_blog_option( $bkap_blog_id, 'book_item-meta-time' );
		delete_blog_option( $bkap_blog_id, 'book_ics-file-name' );
		delete_blog_option( $bkap_blog_id, 'book_item-cart-date' );
		delete_blog_option( $bkap_blog_id, 'checkout_item-cart-date' );
		delete_blog_option( $bkap_blog_id, 'book_item-cart-time' );

		// Deleting all the availability messages
		delete_blog_option( $bkap_blog_id, 'book_stock-total' );
		delete_blog_option( $bkap_blog_id, 'book_available-stock-date' );
		delete_blog_option( $bkap_blog_id, 'book_available-stock-time' );
		delete_blog_option( $bkap_blog_id, 'book_available-stock-date-attr' );
		delete_blog_option( $bkap_blog_id, 'book_available-stock-time-attr' );

		delete_blog_option( $bkap_blog_id, 'book_limited-booking-msg-date' );
		delete_blog_option( $bkap_blog_id, 'book_no-booking-msg-date' );
		delete_blog_option( $bkap_blog_id, 'book_limited-booking-msg-time' );
		delete_blog_option( $bkap_blog_id, 'book_no-booking-msg-time' );
		delete_blog_option( $bkap_blog_id, 'book_limited-booking-msg-date-attr' );
		delete_blog_option( $bkap_blog_id, 'book_limited-booking-msg-time-attr' );

		delete_blog_option( $bkap_blog_id, 'woocommerce_booking_alter_queries' );
		delete_blog_option( $bkap_blog_id, 'bkap_update_booking_labels_settings' );
		delete_blog_option( $bkap_blog_id, 'woocommerce_booking_db_version' );
		delete_blog_option( $bkap_blog_id, 'wc_bkap_prev_db_version' );
		delete_blog_option( $bkap_blog_id, 'book_real-time-error-msg' );

		// Delete the option records for DB update
		delete_blog_option( $bkap_blog_id, 'bkap_400_manual_update_count' );
		delete_blog_option( $bkap_blog_id, 'bkap_400_update_db_status' );

		delete_blog_option( $bkap_blog_id, 'bkap_410_manual_update_count' );
		delete_blog_option( $bkap_blog_id, 'bkap_410_update_db_status' );

		delete_blog_option( $bkap_blog_id, 'bkap_420_update_gcal_meta' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_update_stats' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_update_db_status' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_gcal_update_stats' );

		delete_blog_option( $bkap_blog_id, 'bkap_420_update_gcal_status' );
		delete_blog_option( $bkap_blog_id, 'bkap_420_manual_update_count' );

		delete_blog_option( $bkap_blog_id, 'bkap_420_gcal_update_tour_stats' );

		delete_blog_option( $bkap_blog_id, 'bkap_block_notice' );

		delete_blog_option( $bkap_blog_id, 'bkap_zoom_api_key' );
		delete_blog_option( $bkap_blog_id, 'bkap_zoom_api_secret' );
	}
} else {

	bkap_delete_tables( $wpdb->prefix );

	bkap_delete_options( $wpdb->prefix );

	// Delete the booking settings at product level for all the products
	delete_post_meta_by_key( 'woocommerce_booking_settings' );

	// Delete the global booking settings
	delete_option( 'woocommerce_booking_global_settings' );

	// Add delete option for all the gcal settings, import events and so on
	delete_option( 'bkap_calendar_event_location' );
	delete_option( 'bkap_calendar_event_summary' );
	delete_option( 'bkap_calendar_event_description' );
	delete_option( 'bkap_add_to_calendar_order_received_page' );
	delete_option( 'bkap_add_to_calendar_customer_email' );
	delete_option( 'bkap_add_to_calendar_my_account_page' );
	delete_option( 'bkap_calendar_in_same_window' );
	delete_option( 'bkap_allow_tour_operator_gcal_api' );
	delete_option( 'bkap_calendar_sync_integration_mode' );
	delete_option( 'bkap_calendar_details_1' );
	delete_option( 'bkap_admin_add_to_calendar_view_booking' );
	delete_option( 'bkap_admin_add_to_calendar_email_notification' );
	delete_option( 'bkap_event_item_ids' );
	delete_option( 'bkap_event_uids_ids' );
	delete_option( 'bkap_ics_feed_urls' );
	delete_option( 'bkap_cron_time_duration' );

	// Deleting all the labels set
	delete_option( 'book_date-label' );
	delete_option( 'checkout_date-label' );
	delete_option( 'bkap_calendar_icon_file' );
	delete_option( 'book_time-label' );
	delete_option( 'book_time-select-option' );
	delete_option( 'book_fixed-block-label' );
	delete_option( 'book_price-label' );
	delete_option( 'book_item-meta-date' );
	delete_option( 'checkout_item-meta-date' );
	delete_option( 'book_item-meta-time' );
	delete_option( 'book_ics-file-name' );
	delete_option( 'book_item-cart-date' );
	delete_option( 'checkout_item-cart-date' );
	delete_option( 'book_item-cart-time' );

	// Deleting all the availability messages
	delete_option( 'book_stock-total' );
	delete_option( 'book_available-stock-date' );
	delete_option( 'book_available-stock-time' );
	delete_option( 'book_available-stock-date-attr' );
	delete_option( 'book_available-stock-time-attr' );

	delete_option( 'book_limited-booking-msg-date' );
	delete_option( 'book_no-booking-msg-date' );
	delete_option( 'book_limited-booking-msg-time' );
	delete_option( 'book_no-booking-msg-time' );
	delete_option( 'book_limited-booking-msg-date-attr' );
	delete_option( 'book_limited-booking-msg-time-attr' );

	delete_option( 'woocommerce_booking_alter_queries' );
	delete_option( 'bkap_update_booking_labels_settings' );
	delete_option( 'woocommerce_booking_db_version' );
	delete_option( 'wc_bkap_prev_db_version' );
	delete_option( 'book_real-time-error-msg' );

	// Delete the option records for DB update
	delete_option( 'bkap_400_manual_update_count' );
	delete_option( 'bkap_400_update_db_status' );

	delete_option( 'bkap_410_manual_update_count' );
	delete_option( 'bkap_410_update_db_status' );

	delete_option( 'bkap_420_update_gcal_meta' );
	delete_option( 'bkap_420_update_stats' );
	delete_option( 'bkap_420_update_db_status' );
	delete_option( 'bkap_420_gcal_update_stats' );

	delete_option( 'bkap_420_update_gcal_status' );
	delete_option( 'bkap_420_manual_update_count' );

	delete_option( 'bkap_420_gcal_update_tour_stats' );
	delete_option( 'bkap_block_notice' );

	delete_option( 'bkap_zoom_api_key' );
	delete_option( 'bkap_zoom_api_secret' );
}

/**
 * This function will delete the custom table added by the Booking & Appointment Plugin for WooCommerce
 *
 * @since 4.5.0
 * @global object $wpdb Global wpdb Object
 * @todo Simplify this code by creating array of table and do foreach to remove tables
 */

function bkap_delete_tables( $bkap_table_prefix ) {

	global $wpdb;
	// All custom tables.
	$table_name_booking_history         = $bkap_table_prefix . 'booking_history';
	$table_name_order_history           = $bkap_table_prefix . 'booking_order_history';
	$table_name_booking_block_price     = $bkap_table_prefix . 'booking_block_price_meta';
	$table_name_booking_block_attribute = $bkap_table_prefix . 'booking_block_price_attribute_meta';
	$table_name_block_booking           = $bkap_table_prefix . 'booking_fixed_blocks';

	// Dropping all the custom tables.
	$sql_table_name_booking_history         = 'DROP TABLE IF EXISTS ' . $table_name_booking_history;
	$sql_table_name_order_history           = 'DROP TABLE IF EXISTS ' . $table_name_order_history;
	$sql_table_name_booking_block_price     = 'DROP TABLE IF EXISTS ' . $table_name_booking_block_price;
	$sql_table_name_booking_block_attribute = 'DROP TABLE IF EXISTS ' . $table_name_booking_block_attribute;
	$sql_table_name_block_booking           = 'DROP TABLE IF EXISTS ' . $table_name_block_booking;

	$wpdb->query( $sql_table_name_booking_history );
	$wpdb->query( $sql_table_name_order_history );
	$wpdb->query( $sql_table_name_booking_block_price );
	$wpdb->query( $sql_table_name_booking_block_attribute );
	$wpdb->query( $sql_table_name_block_booking );
}

/**
 * This function will delete the options added by the Booking & Appointment Plugin for WooCommerce
 *
 * @since 4.5.0
 * @global object $wpdb Global wpdb Object
 * @param string $bkap_table_prefix Prefix of the tables
 */

function bkap_delete_options( $bkap_table_prefix ) {

	global $wpdb;
	// Delete all the option records which are present for imported GCal events and are not yet mapped
	$delete_imported_events = 'DELETE FROM `' . $bkap_table_prefix . "options`
	                          WHERE option_name like 'bkap_imported_events_%'";
	$wpdb->query( $delete_imported_events );

	// Delete the backup settings
	$delete_backup = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
	                 WHERE meta_key like 'woocommerce_booking_settings_%'";
	$wpdb->query( $delete_backup );

	$delete_backup = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
	                 WHERE meta_key like 'woocommerce_booking_settings_f_p_%'";
	$wpdb->query( $delete_backup );

	// Delete the individual settings
	$delete_individual = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
	                     WHERE meta_key like '_bkap_%'";
	$wpdb->query( $delete_individual );

	// Delete the individual settings
	$delete_individual = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
	                     WHERE meta_key = 'woocommerce_booking_settings'";
	$wpdb->query( $delete_individual );

	$delete_bkap_400_update_status = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
		                      WHERE meta_key = '_bkap_400_update_status'";
	$wpdb->query( $delete_bkap_400_update_status );

	$delete_bkap_410_update_status = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
     					  WHERE meta_key = '_bkap_410_update_status'";
	$wpdb->query( $delete_bkap_410_update_status );

	$delete_bkap_special_price = 'DELETE FROM `' . $bkap_table_prefix . "postmeta`
     					  WHERE meta_key = '_bkap_special_price'";
	$wpdb->query( $delete_bkap_special_price );

	/**
	 * Deleting posts from post table.
	 */

	$delete_bkap_booking = 'DELETE FROM `' . $bkap_table_prefix . "posts`
     					  WHERE post_type = 'bkap_booking'";
	$wpdb->query( $delete_bkap_booking );

	$delete_bkap_gcal_event = 'DELETE FROM `' . $bkap_table_prefix . "posts`
     					  WHERE post_type = 'bkap_gcal_event'";
	$wpdb->query( $delete_bkap_gcal_event );

	$delete_bkap_resource = 'DELETE FROM `' . $bkap_table_prefix . "posts`
     					  WHERE post_type = 'bkap_resource'";
	$wpdb->query( $delete_bkap_resource );
}

$bkap_uploads_path = WP_CONTENT_DIR . '/uploads/';
/**
 * delete if any gcal sync log file exits
 */
if ( file_exists( $bkap_uploads_path . 'bkap-log.txt' ) ) {
	unlink( $bkap_uploads_path . 'bkap-log.txt' );
}

/**
 * Delete the folder created for the ics file.
 */
if ( is_dir( $bkap_uploads_path . 'wbkap_tmp' ) ) {
	bkap_delete_folder_and_files( $bkap_uploads_path . 'wbkap_tmp' );
}

/**
 * Delete the gcal files.
 */
if ( is_dir( $bkap_uploads_path . 'bkap_uploads' ) ) {
	bkap_delete_folder_and_files( $bkap_uploads_path . 'bkap_uploads' );
}


/**
 * This function will delete the Files and Folders added by the Booking & Appointment Plugin for WooCommerce
 *
 * @since 4.5.0
 * @param string $bkap_dir_name Name of the directory
 * @return true|false
 */

function bkap_delete_folder_and_files( $bkap_dir_name ) {

	$bkap_dir_handle = '';

	if ( is_dir( $bkap_dir_name ) ) {
		$bkap_dir_handle = opendir( $bkap_dir_name );
	}

	if ( ! $bkap_dir_handle ) {
		return false;
	}

	while ( $bkap_file = readdir( $bkap_dir_handle ) ) {

		if ( $bkap_file != '.' && $bkap_file != '..' ) {

			if ( ! is_dir( $bkap_dir_name . '/' . $bkap_file ) ) {
				unlink( $bkap_dir_name . '/' . $bkap_file );
			} else {
				bkap_delete_folder_and_files( $bkap_dir_name . '/' . $bkap_file );
			}
		}
	}

	closedir( $bkap_dir_handle );
	rmdir( $bkap_dir_name );
	return true;
}

// Delete the orphaned transients.
delete_transient( 'bkap_timeslot_notice' );

// Clear any cached data that has been removed
wp_cache_flush();


