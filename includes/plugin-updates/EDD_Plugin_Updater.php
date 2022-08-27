<?php
/**
 * Booking & Appointment for WooCommerce - EDD License
 *
 * @since   5.2.0
 * @author  Tyche Softwares
 *
 * @package BKAP/Plugin-EDD-License
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_BOOK_Plugin_Updater' ) ) {
	// load our custom updater if it doesn't already exist.
	include dirname( __FILE__ ) . '/EDD_BOOK_Plugin_Updater.php';
}

// retrieve our license key from the DB.
$license_key = trim( get_option( 'edd_sample_license_key' ) );

// setup the updater.
$edd_updater = new EDD_BOOK_Plugin_Updater(
	EDD_SL_STORE_URL_BOOK,
	BKAP_FILE,
	array(
		'version'   => BKAP_VERSION,          // current version number.
		'license'   => $license_key,          // license key (used get_option above to retrieve from DB).
		'item_name' => EDD_SL_ITEM_NAME_BOOK, // name of this plugin.
		'author'    => 'Ashok Rane',          // author of this plugin.
	)
);
