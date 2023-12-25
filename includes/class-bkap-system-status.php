<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling System Status
 *
 * @author   Tyche Softwares
 * @package  BKAP/BKAP-System-Status
 * @category Classes
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Bkap_System_Status' ) ) {
	/**
	 *
	 */
	class Bkap_System_Status {


		/**
		 * Construct
		 *
		 * @since 4.12.0
		 */

		public function __construct() {
			add_action( 'bkap_add_settings', array( &$this, 'bkap_export_link' ), 10, 1 );
		}

		/**
		 * Add content to Booking->Status menu page.
		 *
		 * @since 4.12.0
		 */

		public static function bkap_system_status() {

			$action = ( isset( $_GET['action'] ) ) ? $_GET['action'] : 'system_status';

			switch ( $action ) {
				case 'system_status':
					$active_tab = 'nav-tab-active';
					break;
			}
			$download_status = self::bkap_export_global_system_status();
			?>
			<h1><?php _e( 'Booking & Appointment Plugin for WooCommerce', 'woocommerce-booking' ); ?></h1>
			<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
				<a href="edit.php?post_type=bkap_booking&page=bkap_system_status&action=system_status" class="nav-tab <?php echo $active_tab; ?>"> <?php _e( 'System Status', 'woocommerce-booking' ); ?> </a>
			</h2>
			<br>
			<div id='bkap_system_status_download' class='notice notice-info'>
				<p>
					<?php _e( 'Please copy and paste this informtion in your ticket when contacting support:', 'woocommerce-booking' ); ?>
				</p>
				<p id='bkap_download_global'>
					<button id='bkap_global_status_download' class='button-primary'><?php _e( 'Get System Report', 'woocommerce-booking' ); ?></button>
				</p>
				<p id='bkap_display_status' hidden>
					<textarea id='bkap_status_txt' readonly style='width:100%; font-family:monospace; height:300px;'><?php echo $download_status; ?></textarea>
					<br><br>
					<button id='bkap_copy_status' class='button-primary' data-tip='Copied!'><?php _e( 'Copy for support', 'woocommerce-booking' ); ?></button>
					<span class="bkap_popuptext" id="bkap_myPopup"></span>
				</p>
			</div>
			<?php

			// gather data
			$settings = self::bkap_system_status_data();

			// display it
			self::bkap_system_status_display( $settings );
		}

		/**
		 * Returns the data for the system to be displayed in
		 * Booking->Status tab.
		 * Each entry in the array returned is displayed as a
		 * separate table.
		 *
		 * @return array $settings - Contains all the data to be displayed
		 *        2 entries - WordPress Environment & plugin Settings
		 * @since 4.12.0
		 */

		public static function bkap_system_status_data() {

			// fetch generic information
			$generic_settings = self::bkap_get_generic();

			$settings['WordPress_Environment'] = $generic_settings;

			// fetch the plugin data
			$prefix          = array( 'book', 'bkap_' );
			$plugin_settings = self::bkap_get_plugin_data( $prefix );

			$settings['Plugin_Settings'] = $plugin_settings;

			return $settings;
		}

		/**
		 * Displays the data sent in the array as separate
		 * tables for each entry in the array.
		 *
		 * @param array $settings - Contains all the data to be displayed
		 *        2 tables - WordPress Environment & plugin Settings
		 * @since 4.12.0
		 */

		public static function bkap_system_status_display( $settings ) {
			?>
			<div>
				<?php
				if ( is_array( $settings ) && count( $settings ) > 0 ) {

					foreach ( $settings as $name => $settings ) {
						$name = str_replace( '_', ' ', $name );
						?>
						<div>
							<p>
								<table id="bkap_status" style="table-layout:fixed;width:98%;" class="widefat fixed" cellspacing="0" >
									<thead>
										<tr>
											<th colspan="2"><b><?php _e( $name, 'woocommerce-booking' ); ?></b></th>
										</tr>
									</thead>
									<tbody>
										<?php
										foreach ( $settings as $s_name => $s_data ) {
											if ( $name == 'WordPress Environment' ) {
												$s_name = str_replace( '_', ' ', $s_name );
											}
											?>
											<tr>
												<td><?php echo "$s_name:"; ?></td>
												<td><?php echo $s_data; ?></td>
											</tr>
											<?php
										}
										?>
									</tbody>
								</table>
							</p>
						</div>
						<?php
					}
				}
				?>
			</div>
			<?php
		}

		/**
		 * Returns the plugin global settings
		 *
		 * @param array $prefix - contains a list of prefixes to be searched for in wp_options
		 * @return array $settings - contains the plugin settings
		 * @since 4.12.0
		 */

		public static function bkap_get_plugin_data( $prefix = '', $status = '' ) {
			// we can't fetch any data without the prefix
			if ( ! is_array( $prefix ) || count( $prefix ) === 0 ) {
				return;
			}

			global $wpdb;

			$settings = array();

			foreach ( $prefix as $prefix_data ) {
				$query_labels = 'SELECT option_id, option_name, option_value FROM `' . $wpdb->prefix . "options`
                                    WHERE option_name LIKE '%s'";

				$get_labels = $wpdb->get_results( $wpdb->prepare( $query_labels, "%$prefix_data%" ) );

				if ( is_array( $get_labels ) && count( $get_labels ) > 0 ) {
					foreach ( $get_labels as $results_data ) {
						if ( $results_data->option_name === 'woocommerce_booking_global_settings' ) {
							$global_settings = json_decode( $results_data->option_value );
							foreach ( $global_settings as $g_key => $g_data ) {
								$settings[ $g_key ] = $g_data;
							}
						} else {

							if ( $status == '' && strpos( $results_data->option_name, 'imported_event' ) !== false ) {
								continue;
							}

							if ( strpos( $results_data->option_name, 'orddd' ) !== false
								|| strpos( $results_data->option_name, 'acap' ) !== false
								|| strpos( $results_data->option_name, 'wbk_' ) !== false
								|| strpos( $results_data->option_name, 'birchschedule' ) !== false
								|| strpos( $results_data->option_name, 'BookingBug' ) !== false ) {

								continue;
							}
							$settings[ $results_data->option_name ] = $results_data->option_value;
						}
					}
				}
			}

			return $settings;
		}

		/**
		 * Returns the generic site information like
		 * WordPress version, WooCommerce version and so on.
		 *
		 * @return array $generic - basic site information
		 * @since 4.12.0
		 */
		public static function bkap_get_generic() {

			$generic = array();

			// Home URL
			$generic['Home_URL'] = get_option( 'home' );
			// Site URL
			$generic['Site_URL'] = get_option( 'siteurl' );
			// WordPress version
			$generic['WP_Version'] = get_bloginfo( 'version' );
			// WP Multisite
			$generic['WP_multisite'] = is_multisite() ? 'Yes' : 'No';
			// WP Debug Mode
			$generic['WP_debug_mode'] = ( WP_DEBUG === true ) ? 'On' : 'Off';
			// Language
			$generic['WP_Language'] = get_bloginfo( 'language' );
			// WooCommerce version
			$generic['WC_Version'] = get_option( 'woocommerce_version' );

			return $generic;
		}

		/**
		 * Formats and returns the data received in a
		 * readable format by replacing _ with spaces and adding
		 * End of lines for each entry in the array
		 *
		 * @param array   $data - Dates that needs to be prettied
		 * @param boolean $replace - TRUE: replace _ with space; FALSE: retains the _ as is.
		 * @param string  $line_break - What sort of Line break to add after each array entry
		 *
		 * @return string $pretty_data - All the data concatenated and formatted into readable lines.
		 * @since 4.12.0
		 */

		public static function bkap_pretty_data( $data = array(), $replace = false, $line_break = '<br><br>' ) {

			$pretty_data = '';
			if ( is_array( $data ) && count( $data ) > 0 ) {
				foreach ( $data as $d_label => $d_value ) {
					if ( $replace ) {
						$d_label = str_replace( '_', ' ', $d_label );
					}
					$pretty_data .= "$d_label: $d_value " . $line_break;
				}
			}

			return $pretty_data;
		}

		/**
		 * Returns the Global Settings for export
		 *
		 *  @since 4.12.0
		 */
		public static function bkap_export_global_system_status() {
			$export_data = '';
			if ( isset( $_GET['page'] ) && 'bkap_system_status' == $_GET['page'] ) { // global booking settings export

				// fetch generic information
				$generic_settings = self::bkap_get_generic();
				// Arrange it to make sense
				$text_generic = self::bkap_pretty_data( $generic_settings, true, PHP_EOL );
				$text_generic = __( '### WordPress Environment ###', 'woocommerce-booking' ) . PHP_EOL . '' . PHP_EOL . $text_generic;

				// fetch the plugin data
				$prefix      = array( 'book', 'bkap_' );
				$settings    = self::bkap_get_plugin_data( $prefix, 'global' );
				$text_plugin = self::bkap_pretty_data( $settings, false, PHP_EOL );
				$text_plugin = '' . PHP_EOL . __( '### Plugin Settings ###', 'woocommerce-booking' ) . PHP_EOL . '' . PHP_EOL . $text_plugin;

				$export_data = $text_generic . $text_plugin;

			}

			return $export_data;
		}

		/**
		 * Creates downloadable file for system status.
		 * The file can either be opened to copy paste the data directly into a third party app
		 * or can be downloaded for later use.
		 *
		 * This function works for both: global booking settings export
		 * as well as product level booking settings export.
		 *
		 * @since 4.12.0
		 */

		public static function bkap_export_system_status( $post_id ) {

			$duplicate_of = bkap_common::bkap_get_product_id( $post_id );

			// collect the data
			$booking_settings = self::bkap_get_post_data( $duplicate_of, 'bkap_' );

			if ( is_array( $booking_settings ) && count( $booking_settings ) > 0 ) {
				$display_data = self::bkap_pretty_data( $booking_settings, false, PHP_EOL );
			} else {
				$display_data = $booking_settings;
			}

			return $display_data;
		}

		/**
		 * Add 'Export Booking Settings' link in the
		 * Booking meta box in Add/Edit Product page.
		 *
		 * @param integer $post_id - Product ID
		 * @hook bkap_add_settings
		 * @since 4.12.0
		 */

		public static function bkap_export_link( $post_id ) {

			if ( isset( $_GET['action'] ) ) {

				$download_status = self::bkap_export_system_status( $post_id );

				?>
				<div class="bkap_popup" style="float: right;">
				<span class="bkap_popuptext" id="bkap_myPopup"></span>
				<b><a class="bkap_export_booking_link" title="Click to copy the Booking Settings of this product. Note: This option is only for Debugging purpose. Before copying the settings please make sure you have updated the product." >
					<?php _e( 'Copy booking settings', 'woocommerce-booking' ); ?>
				</a></b>
				</div>
				<input type="text" id="bkap_product_status_txt" value='<?php echo $download_status; ?>'>        
				<?php
			}
		}

		/**
		 * Collect the product level booking settings data and return the same.
		 *
		 * @param integer $post_id - Product ID
		 * @param string  $prefix - Prefix for post meta records
		 * @return array | string - Booking Settings | Msg indicating no settings are present.
		 * @since 4.12.0
		 */

		public static function bkap_get_post_data( $post_id = 0, $prefix = '' ) {

			if ( $post_id > 0 && $prefix != '' ) {

				global $wpdb;

				$bkap_settings = array();

				$query_meta = 'SELECT meta_key, meta_value FROM `' . $wpdb->prefix . 'postmeta`
                                WHERE post_id = %d
                                AND meta_key like %s';

				$get_data = $wpdb->get_results( $wpdb->prepare( $query_meta, $post_id, "%$prefix%" ) );

				if ( is_array( $get_data ) && count( $get_data ) > 0 ) {
					foreach ( $get_data as $key => $value ) {
						$bkap_settings[ $value->meta_key ] = $value->meta_value;
					}

					return $bkap_settings;
				}
			}
			return __( 'The product details maybe incorrect. Please try again.', 'woocommerce-booking' );
		}
	} // end of class

	$bkap_system_status = new Bkap_System_Status();
}
?>
