<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Adding Menus and Submenus and related setting fields in the Booking and Appointment Plugin for WooCommerce
 *
 * @author      Tyche Softwares
 * @package     BKAP/Menus
 * @since       2.0
 * @category    Classes
 */

if ( ! class_exists( 'Global_Menu' ) ) {

	/**
	 * Class for adding Menus, Registering Settings and Options
	 *
	 * @class Global_Menu
	 */
	class Global_Menu {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */
		public function __construct() {
			// WordPress Administration Menu.
			add_action( 'admin_menu', array( $this, 'bkap_woocommerce_booking_admin_menu' ) );
			// Gcal Settings tab.
			add_action( 'admin_init', array( $this, 'bkap_gcal_settings' ), 10 );
			// Zoom Meeting Settings tab.
			add_action( 'admin_init', array( $this, 'zoom_meeting_settings' ), 10 );

			add_action( 'admin_init', array( $this, 'bkap_global_settings' ), 10 );
			add_action( 'admin_init', array( $this, 'bkap_booking_labels' ), 10 );

			// remove the submit div.
			add_action( 'admin_menu', array( &$this, 'bkap_remove_submitdiv' ), 10 );
		}

		/**
		 * Remove Submit meta box
		 *
		 * @since 4.1.0
		 */
		public function bkap_remove_submitdiv() {
			remove_meta_box( 'submitdiv', 'bkap_booking', 'side' );
		}

		/**
		 * This function adds the Booking settings menu in the sidebar admin woocommerce.
		 *
		 * @since 1.7
		 * @global array $submenu Array of submenus
		 */
		public static function bkap_woocommerce_booking_admin_menu() {
			global $submenu;

			// Remove the additional Create Booking created on bkap_booking post registrations.
			unset( $submenu['edit.php?post_type=bkap_booking'][10] );

			$page = add_submenu_page(
				null,
				__( 'Calednar View', 'woocommerce-booking' ),
				__( 'Calendar View', 'woocommerce-booking' ),
				'manage_woocommerce',
				'woocommerce_history_page',
				array( 'Bkap_Calendar_View', 'bkap_calendar_view_page' )
			);

			$page = add_submenu_page(
				'edit.php?post_type=bkap_booking',
				__( 'Create Booking', 'woocommerce-booking' ),
				__( 'Create Booking', 'woocommerce-booking' ),
				'manage_woocommerce',
				'bkap_create_booking_page',
				array( 'bkap_admin_bookings', 'bkap_create_booking_page' )
			);

			$page = add_submenu_page(
				'edit.php?post_type=bkap_booking',
				__( 'Settings', 'woocommerce-booking' ),
				__( 'Settings', 'woocommerce-booking' ),
				'manage_woocommerce',
				'woocommerce_booking_page',
				array( 'Global_Menu', 'bkap_woocommerce_booking_page' )
			);

			$page = add_submenu_page(
				'edit.php?post_type=bkap_booking',
				__( 'Status', 'woocommerce-booking' ),
				__( 'Status', 'woocommerce-booking' ),
				'manage_woocommerce',
				'bkap_system_status',
				array( 'Bkap_System_Status', 'bkap_system_status' )
			);

			$page = add_submenu_page(
				'edit.php?post_type=bkap_booking',
				__( 'Activate License', 'woocommerce-booking' ),
				__( 'Activate License', 'woocommerce-booking' ),
				'manage_woocommerce',
				'booking_license_page',
				array( 'BKAP_License', 'display_license_page' )
			);

			do_action( 'bkap_add_submenu' );
		}

		/**
		 * This function displays the global settings for the booking products.
		 *
		 * @since 1.7
		 */
		public static function bkap_woocommerce_booking_page() {

			$action                = isset( $_GET['action'] ) ? $_GET['action'] : ''; //phpcs:ignore
			$active_settings       = '';
			$active_labels         = '';
			$addon_settings        = '';
			$integration_settings  = '';
			$bulk_booking_settings = '';

			switch ( $action ) {
				case 'settings':
					$active_settings = 'nav-tab-active';
					break;
				case 'labels':
					$active_labels = 'nav-tab-active';
					break;
				case 'addon_settings':
					$addon_settings = 'nav-tab-active';
					break;
				case 'calendar_sync_settings':
					$integration_settings = 'nav-tab-active';
					break;
				case 'bkap-update':
					$update_process = 'nav-tab-active';
					break;
				case 'faq_support_page':
					$active_settings = '';
					break;
				case 'bulk_booking_settings':
					$bulk_booking_settings = 'nav-tab-active';
					break;
				default:
					$active_settings = 'nav-tab-active';
					break;
			}

			?>
		<h2 class="nav-tab-wrapper woo-nav-tab-wrapper">
			<!-- Global Booking Settings -->
			<a  href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=settings" class="nav-tab <?php echo esc_attr( $active_settings ); ?>"><?php esc_html_e( 'Global Booking Settings', 'woocommerce-booking' ); ?></a>
			<!-- Labels & Messages -->
			<a  href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=labels" class="nav-tab <?php echo esc_attr( $active_labels ); ?>"><?php esc_html_e( 'Labels & Messages', 'woocommerce-booking' ); ?></a>
			<!-- Addon Settings -->
			<a  href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=addon_settings" class="nav-tab <?php echo esc_attr( $addon_settings ); ?>"><?php esc_html_e( 'Addon Settings', 'woocommerce-booking' ); ?></a>
			<!-- Google Calendar Sync -->
			<a  href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings" class="nav-tab <?php echo esc_attr( $integration_settings ); ?>"><?php esc_attr_e( 'Integrations', 'woocommerce-booking' ); ?></a>
			<!-- Bulk Booking Setting -->
			<a  href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=bulk_booking_settings" class="nav-tab <?php echo esc_attr( $bulk_booking_settings ); ?>"><?php esc_html_e( 'Bulk Booking Setting', 'woocommerce-booking' ); ?></a>

			<?php
			do_action( 'bkap_add_global_settings_tab' );
			do_action( 'bkap_add_settings_tab' );
			?>
		</h2>
			<?php
			do_action( 'bkap_add_tab_content' );

			switch ( $action ) {
				case 'settings':
					print( '<div id="content">
                    <form method="post" action="options.php">' );
						settings_errors();
						settings_fields( 'bkap_global_settings' );
						do_settings_sections( 'bkap_global_settings_page' );
						submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', true );
						print( '</form>
                </div>' );
					break;

				case '':
					print( '<div id="content">
                    <form method="post" action="options.php">' );
						settings_errors();
						settings_fields( 'bkap_global_settings' );
						do_settings_sections( 'bkap_global_settings_page' );
						submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', true );
						print( '</form>
                </div>' );
					break;

				case 'labels':
					print( '<div id="content">
                    <form method="post" action="options.php">' );
						settings_errors();
						settings_fields( 'bkap_booking_labels' );
						do_settings_sections( 'bkap_booking_labels_page' );
						submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', true );
						print( '</form>
                </div>' );
					break;

				case 'addon_settings':

					$external_settings = apply_filters( 'bkap_add_addon_settings_for_custom_addons', true );
					if ( $external_settings || ( function_exists( 'is_bkap_send_friend_active' ) && is_bkap_send_friend_active() ) || ( function_exists( 'is_bkap_tours_active' ) && is_bkap_tours_active() ) || ( function_exists( 'is_bkap_deposits_active' ) && is_bkap_deposits_active() ) || ( function_exists( 'is_bkap_tickets_active' ) && is_bkap_tickets_active() ) || is_plugin_active( 'bkap-recurring-bookings/bkap-recurring-bookings.php' ) ) {
						settings_errors();
						do_action( 'bkap_add_addon_settings' );
					} else {
						?>
					<p> <?php esc_html_e( 'No addons are currently active for the Booking & Appointment Plugin for WooCommerce.', 'woocommerce-booking' ); ?></p>
						<?php
					}
					break;

				case 'calendar_sync_settings':
					$section            = isset( $_GET['section'] ) ? sanitize_text_field( $_GET['section'] ) : ''; //phpcs:ignore
					$gcal_sync_class    = '';
					$zoom_meeting_class = '';
					$fluent_class       = '';

					switch ( $section ) {
						case 'gcal_sync':
							$gcal_sync_class = 'current';
							break;
						case 'zoom_meeting':
							$zoom_meeting_class = 'current';
							break;
						case '':
							$gcal_sync_class = 'current';
							break;
						default:
							$gcal_sync_class = '';
							break;
					}

					?>
					<ul class="subsubsub" id="bkap_integrations_settings_links">
						<li>
							<a href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=gcal_sync" class="<?php echo esc_attr( $gcal_sync_class ); ?>"><?php esc_html_e( 'Google Calendar Sync', 'woocommerce-booking' ); ?></a> |
						</li>
						<li>
							<a href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=zoom_meeting" class="<?php echo esc_attr( $zoom_meeting_class ); ?>"><?php esc_html_e( 'Zoom Meetings', 'woocommerce-booking' ); ?></a> | 
						</li>
						<?php do_action( 'bkap_integration_links', $section ); ?>
					</ul>
					<br class="clear">

					<?php
					if ( '' !== $gcal_sync_class ) {
						print( '<div id="content"><form method="post" action="options.php">' );
						settings_errors();
						settings_fields( 'bkap_gcal_sync_settings' );
						do_settings_sections( 'bkap_gcal_sync_settings_page' );
						submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', true );
						print( '</form></div>' );
					}

					if ( '' !== $zoom_meeting_class ) {

						if ( ! BKAP_License::enterprise_license() ) {
							?>
							<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::enterprise_license_error_message(); // phpcs:ignore; ?></div>
							<?php
						}

						if ( BKAP_License::enterprise_license() ) {

							print( '<div id="content"><form method="post" action="options.php">' );
							settings_errors();
							settings_fields( 'bkap_zoom_meeting_settings' );
							do_settings_sections( 'bkap_zoom_meeting_settings_page' );
							$key    = get_option( 'bkap_zoom_api_key', '' );
							$secret = get_option( 'bkap_zoom_api_secret', '' );
							if ( '' !== $key && '' !== $secret ) {
								?>
							<h4 class="description" style="color:red;"><?php esc_html_e( 'After you enter your keys. Do save settings before doing "Test Connection".', 'woocommerce-booking' ); ?></h4>
								<?php
							}
							?>
							<p class="submit">
							<?php
							submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', false );
							if ( '' !== $key && '' !== $secret ) {
								?>
							<button type="button" class="button bkap_zoom_test_connection"><?php esc_html_e( 'Test Connection', 'woocommerce-booking' ); ?></button> </p>
								<?php
							}
							print( '</form></div>' );
						}
					}

					do_action( 'bkap_global_integration_settings', $section );					
					break;
				case 'bulk_booking_settings':
					do_action( 'bulk_booking_settings_section' );
					break;

				default:
					break;
			}
			do_action( 'bkap_settings_tab_content', $action );
		}

		/**
		 * This function will add settings fields for the booking labels.
		 *
		 * @since 1.7
		 */
		public static function bkap_booking_labels() {
			add_settings_section(
				'bkap_booking_product_page_labels_section',     // ID used to identify this section and with which to register options.
				__( 'Labels on product page', 'woocommerce-booking' ),      // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_booking_product_page_labels_section_callback' ),       // Callback used to render the description of the section.
				'bkap_booking_labels_page'              // Page on which to add this section of options.
			);

			add_settings_field(
				'book_date-label',
				__( 'Check-in Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_date_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Check-in Date label on product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'checkout_date-label',
				__( 'Check-out Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'checkout_date_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Check-out Date label on product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_calendar_icon_file',
				__( 'Select Calendar Icon:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'bkap_calendar_icon_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Replace or Remove Calendar Icon label on product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_time-label',
				__( 'Booking Time:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_time_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Booking Time label on product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_time-select-option',
				__( 'Choose Time Text:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_time_select_option_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Text for the 1st option of Time Slot dropdown field that instructs the customer to select a time slot.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_fixed-block-label',
				__( 'Fixed Block Drop Down Label:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_fixed_block_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Fixed Block Drop Down label on product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_price-label',
				__( 'Label for Booking Price:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_price_label_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_product_page_labels_section',
				array( __( 'Label for Booking Price on product page.', 'woocommerce-booking' ) )
			);

			add_settings_section(
				'bkap_booking_order_received_and_email_labels_section',     // ID used to identify this section and with which to register options.
				__( 'Labels on order received page and in email notification', 'woocommerce-booking' ),     // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_booking_order_received_and_email_labels_section_callback' ),       // Callback used to render the description of the section.
				'bkap_booking_labels_page'
			);

			add_settings_field(
				'book_item-meta-date',
				__( 'Check-in Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_item_meta_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_order_received_and_email_labels_section',
				array( __( 'Check-in Date label on the order received page and email notification.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'checkout_item-meta-date',
				__( 'Check-out Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'checkout_item_meta_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_order_received_and_email_labels_section',
				array( __( 'Check-out Date label on the order received page and email notification.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_item-meta-time',
				__( 'Booking Time:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_item_meta_time_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_order_received_and_email_labels_section',
				array( __( 'Booking Time label on the order received page and email notification.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_ics-file-name',
				__( 'ICS File Name:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_ics_file_name_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_order_received_and_email_labels_section',
				array( __( 'ICS File name.', 'woocommerce-booking' ) )
			);

			add_settings_section(
				'bkap_booking_cart_and_checkout_page_labels_section',       // ID used to identify this section and with which to register options.
				__( 'Labels on Cart & Check-out Page', 'woocommerce-booking' ),     // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_booking_cart_and_checkout_page_labels_section_callback' ),     // Callback used to render the description of the section.
				'bkap_booking_labels_page'
			);

			add_settings_field(
				'book_item-cart-date',
				__( 'Check-in Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_item_cart_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_cart_and_checkout_page_labels_section',
				array( __( 'Check-in Date label on the cart and checkout page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'checkout_item-cart-date',
				__( 'Check-out Date:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'checkout_item_cart_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_cart_and_checkout_page_labels_section',
				array( __( 'Check-out Date label on the cart and checkout page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_item-cart-time',
				__( 'Booking Time:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_item_cart_time_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_cart_and_checkout_page_labels_section',
				array( __( 'Booking Time label on the cart and checkout page.', 'woocommerce-booking' ) )
			);

			add_settings_section(
				'bkap_add_to_cart_button_labels_section',       // ID used to identify this section and with which to register options.
				__( 'Text for Add to Cart button', 'woocommerce-booking' ),     // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_add_to_cart_button_labels_section_callback' ),     // Callback used to render the description of the section.
				'bkap_booking_labels_page'
			);

			add_settings_field(
				'bkap_add_to_cart',
				__( 'Text for Add to Cart Button:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'bkap_add_to_cart_button_text_callback' ),
				'bkap_booking_labels_page',
				'bkap_add_to_cart_button_labels_section',
				array( __( 'Change text for Add to Cart button on WooCommerce product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_check_availability',
				__( 'Text for Check Availability Button:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'bkap_check_availability_text_callback' ),
				'bkap_booking_labels_page',
				'bkap_add_to_cart_button_labels_section',
				array( __( 'Change text for Check Availability button on WooCommerce product page when product requires confirmation.', 'woocommerce-booking' ) )
			);

			do_action( 'bkap_after_add_to_cart_section' );

			add_settings_section(
				'bkap_booking_availability_messages_section',       // ID used to identify this section and with which to register options.
				__( 'Booking Availability Messages on Product Page', 'woocommerce-booking' ),       // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_booking_availability_messages_section_callback' ),     // Callback used to render the description of the section.
				'bkap_booking_labels_page'
			);

			add_settings_field(
				'book_stock-total',
				__( 'Total stock display message: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_stock_total_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The total stock message to be displayed when the product page loads.<br><i>Note: You can use AVAILABLE_SPOTS placeholder which will be replaced by it\'s real value.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_available-stock-date',
				__( 'Availability display message for a date: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_available_stock_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The availability message displayed when a date is selected in the calendar.<br><i>Note: You can use AVAILABLE_SPOTS, DATE placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_available-stock-time',
				__( 'Availability display message for a time slot: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_available_stock_time_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The availability message displayed when a time slot is selected for a date.<br><i>Note: You can use AVAILABLE_SPOTS, DATE, TIME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_available-stock-date-attr',
				__( 'Availability display message for a date when attribute level lockout is set: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_available_stock_date_attr_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The availability message displayed when a date is selected and attribute level lockout is set for the product.<br><i>Note: You can use AVAILABLE_SPOTS, DATE, ATTRIBUTE_NAME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_available-stock-time-attr',
				__( 'Availability display message for a time slot when attribute level lockout is set: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_available_stock_time_attr_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The availability message displayed when a time slot is selected for a date and attribute level lockout is set for the product.<br><i>Note: You can use AVAILABLE_SPOTS, DATE, TIME, ATTRIBUTE_NAME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_real-time-error-msg',
				__( 'Message to be displayed when the time slot is blocked in real time: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_real_time_error_msg_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'The message to be displayed when any time slot for the date selected by the user is fully blocked in real time bookings.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_multidates_min_max_selection_msg',
				__( 'Message to be displayed for Min & Max allowed dates selection for Multiple Dates: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_multidates_min_max_selection_msg_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_availability_messages_section',
				array( __( 'Min & Max Selection Multiple Dates', 'woocommerce-booking' ) )
			);

			add_settings_section(
				'bkap_booking_lockout_messages_section',        // ID used to identify this section and with which to register options.
				__( 'Booking Availability Error Messages on the Product, Cart & Checkout Pages', 'woocommerce-booking' ),       // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_booking_lockout_messages_section_callback' ),      // Callback used to render the description of the section.
				'bkap_booking_labels_page'
			);

			add_settings_field(
				'book_limited-booking-msg-date',
				__( 'Limited availability error message for a date: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_limited_booking_msg_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date booking when user tries to book more than the available quantity.<br><i>Note: You can use PRODUCT_NAME, AVAILABLE_SPOTS, DATE placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_no-booking-msg-date',
				__( 'No availability error message for a date: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_no_booking_msg_date_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date booking and bookings are no longer available for the selected date.<br><i>Note: You can use PRODUCT_NAME, DATE placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_limited-booking-msg-time',
				__( 'Limited availability error message for a time slot: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_limited_booking_msg_time_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date and time booking when user tries to book more than the available quantity.<br><i>Note: You can use PRODUCT_NAME, AVAILABLE_SPOTS, DATE, TIME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_no-booking-msg-time',
				__( 'No availability error message for a time slot: ', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_no_booking_msg_time_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date and time booking and bookings are no longer available for the selected time slot.<br><i>Note: You can use PRODUCT_NAME, DATE, TIME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_limited-booking-msg-date-attr',
				__( 'Limited Availability Error Message for a date when attribute level lockout is set:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_limited_booking_msg_date_attr_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date booking when user tries to book more than the available quantity setup at the attribute level.<br><i>Note: You can use PRODUCT_NAME, AVAILABLE_SPOTS, ATTRIBUTE_NAME, DATE placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'book_limited-booking-msg-time-attr',
				__( 'Limited Availability Error Message for a time slot when attribute level lockout is set:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'book_limited_booking_msg_time_attr_callback' ),
				'bkap_booking_labels_page',
				'bkap_booking_lockout_messages_section',
				array( __( 'The error message displayed for a date and time booking when user tries to book more than the available quantity setup at the attribute level.<br><i>Note: You can use PRODUCT_NAME, AVAILABLE_SPOTS, ATTRIBUTE_NAME, DATE, TIME placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			register_setting(
				'bkap_booking_labels',
				'book_date-label'
			);

			register_setting(
				'bkap_booking_labels',
				'bkap_add_to_cart'
			);

			register_setting(
				'bkap_booking_labels',
				'bkap_check_availability'
			);

			register_setting(
				'bkap_booking_labels',
				'checkout_date-label'
			);

			register_setting(
				'bkap_booking_labels',
				'bkap_calendar_icon_file'
			);

			register_setting(
				'bkap_booking_labels',
				'book_time-label'
			);

			register_setting(
				'bkap_booking_labels',
				'book_time-select-option'
			);

			register_setting(
				'bkap_booking_labels',
				'book_fixed-block-label'
			);

			register_setting(
				'bkap_booking_labels',
				'book_price-label'
			);

			register_setting(
				'bkap_booking_labels',
				'book_item-meta-date'
			);

			register_setting(
				'bkap_booking_labels',
				'checkout_item-meta-date'
			);

			register_setting(
				'bkap_booking_labels',
				'book_item-meta-time'
			);

			register_setting(
				'bkap_booking_labels',
				'book_ics-file-name'
			);

			register_setting(
				'bkap_booking_labels',
				'book_item-cart-date'
			);

			register_setting(
				'bkap_booking_labels',
				'checkout_item-cart-date'
			);

			register_setting(
				'bkap_booking_labels',
				'book_item-cart-time'
			);

			register_setting(
				'bkap_booking_labels',
				'book_stock-total'
			);

			register_setting(
				'bkap_booking_labels',
				'book_available-stock-date'
			);

			register_setting(
				'bkap_booking_labels',
				'book_available-stock-time'
			);

			register_setting(
				'bkap_booking_labels',
				'book_available-stock-date-attr'
			);

			register_setting(
				'bkap_booking_labels',
				'book_available-stock-time-attr'
			);

			register_setting(
				'bkap_booking_labels',
				'book_real-time-error-msg'
			);

			register_setting(
				'bkap_booking_labels',
				'book_multidates_min_max_selection_msg'
			);

			register_setting(
				'bkap_booking_labels',
				'book_limited-booking-msg-date'
			);

			register_setting(
				'bkap_booking_labels',
				'book_no-booking-msg-date'
			);

			register_setting(
				'bkap_booking_labels',
				'book_limited-booking-msg-time'
			);

			register_setting(
				'bkap_booking_labels',
				'book_no-booking-msg-time'
			);

			register_setting(
				'bkap_booking_labels',
				'book_limited-booking-msg-date-attr'
			);

			register_setting(
				'bkap_booking_labels',
				'book_limited-booking-msg-time-attr'
			);
		}

		/**
		 * This function will add settings fields for the Global Booking Settings.
		 *
		 * @since 1.7
		 */
		public static function bkap_global_settings() {

			add_settings_section(
				'bkap_global_settings_section',     // ID used to identify this section and with which to register options.
				__( 'General Settings', 'woocommerce-booking' ),        // Title to be displayed on the administration page.
				array( 'bkap_global_settings', 'bkap_global_settings_section_callback' ),       // Callback used to render the description of the section.
				'bkap_global_settings_page'             // Page on which to add this section of options.
			);

			add_settings_field(
				'booking_language',
				__( 'Language:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_language_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Choose the language for your booking calendar.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_date_format',
				__( 'Date Format:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_date_format_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'The format in which the booking date appears to the customers throughout the order cycle.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_time_format',
				__( 'Time Format:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_time_format_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'The format in which booking time appears to the customers throughout the order cycle.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_timeslot_display_mode',
				__( 'Display mode for Time Slots:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_timeslot_display_mode_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Choose the timeslot display mode ( <i>Not applicable for duration based booking</i> ).', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_timezone_conversion',
				__( 'Timezone Conversion:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_timezone_conversion_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'The time-slots will be automatically converted to the customer local time, making easier and friendlier to offer services to customers in different time-zones. <br><i><small> Note: Currently, this option will work only with the <b>Fixed Time</b> booking type. Soon we will make this option work with Duration Based Time booking type.</small></i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_months',
				__( 'Number of months to show in calendar:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_months_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'The number of months to be shown on the calendar. If the booking dates spans across 2 months, then dates of 2 months can be shown simultaneously without the need to press Next or Back buttons.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_calendar_day',
				__( 'First Day on Calendar:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_calendar_day_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Choose the first day to display on the booking calendar.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_attachment',
				__( 'Send bookings as attachments (ICS files) in email notifications:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_attachment_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Allow customers to export bookings as ICS file after placing an order. Sends ICS files as attachments in email notifications.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_theme',
				__( 'Preview Theme & Language:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_theme_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Select the theme for the calendar. You can choose a theme which blends with the design of your website.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_global_holidays',
				__( 'Select Holidays / Exclude Days / Black-out days:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_global_holidays_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Select dates for which the booking will be completely disabled for all the products in your WooCommerce store. <br> Please click on the date in calendar to add or delete the date from the holiday list.', 'woocommerce-booking' ) )
			);

			do_action( 'bkap_after_global_holiday_option' );

			add_settings_field(
				'booking_include_global_holidays',
				__( 'Allow holidays in the date range:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_include_global_holidays_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want the holidays to be included in the selected date range for multiple night bookings.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_global_timeslot',
				__( 'Global Time Slot Booking:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_global_timeslot_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want ALL time slots to be unavailable for booking in all products once the lockout for that time slot is reached for any product. <i><b>Note:</b> This option will work only with Fixed Time option of Date & Time booking type.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_overlapping_timeslot',
				__( 'Overlapping Time Slot Booking:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_overlapping_timeslot_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want overlapping time slots to be unavailable for booking in the product once the lockout for that time slot is reached for that product. <i><b>Note:</b> This option will work only with Fixed Time option of Date & Time booking type.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'hide_variation_price',
				__( 'Hide Variation Price on Product Page:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'hide_variation_price_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Select whether the WooCommerce Variation Price should be hidden on the front end Product Page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'hide_booking_price',
				__( 'Hide Booking Price on Product Page:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'hide_booking_price_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array(
					__(
						'
Select this if you want to hide the Booking Price on Product page until the time slot is selected for the bookable product with time slot. The prices will be shown only after booking date and timeslot is selected by the customer.',
						'woocommerce-booking'
					),
				)
			);

			add_settings_field(
				'display_disabled_buttons',
				__( 'Always display the Add to Cart and Quantity buttons:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'display_disabled_buttons_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Select whether the Add to Cart and Quantity buttons should always be displayed on the front end Product page.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_global_selection',
				__( 'Duplicate dates from first product in the cart to other products:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_global_selection_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want to select the date globally for All products once selected for a product and added to cart.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'booking_availability_display',
				__( 'Enable Availability Display on the Product page:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'booking_availability_display_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want to display the number of bookings available for a given product on a given date and time.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'resource_price_per_day',
				__( 'Charge Resource cost on a Per Day Basis:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'resource_price_per_day_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want to multiply the resource price with the number of booking days for Multiple Nights Booking.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'woo_product_addon_price',
				__( 'Charge WooCommerce Product Addons options on a Per Day Basis:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'woo_product_addon_price_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want to multiply the option price of WooCommerce Product Addons with the number of booking days for Multiple Day Booking.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'woo_gf_product_addon_option_price',
				__( 'Charge WooCommerce Gravity Forms Product Addons options on a Per Day Basis:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'woo_gf_product_addon_option_price_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want to multiply the option price of WooCommerce Gravity Forms Product Addons with the number of booking days for Multiple Day Booking.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'minimum_day_booking',
				__( 'Minimum Day Booking:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'minimum_day_booking_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Enter minimum days of booking for Multiple days booking.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'global_booking_minimum_number_days',
				__( 'Minimum number of days to choose:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'global_booking_minimum_number_days_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'The minimum number days you want to be booked for multiple day booking. For example, if you require minimum 2 days for booking, enter the value 2 in this field.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'show_order_info_note',
				__( 'Show Booking Information on Order Notes:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'show_order_info_note_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'Please select this checkbox if you want the Booking Information or Zoom Link to be added to Order Notes.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_auto_cancel_booking',
				__( 'Automatically cancel bookings that require confirmation:', 'woocommerce-booking' ),
				array( 'bkap_global_settings', 'bkap_auto_cancel_booking_callback' ),
				'bkap_global_settings_page',
				'bkap_global_settings_section',
				array( __( 'If you want to cancel the confirmation bookings (which require admin\'s approval) automatically after certain number of hours have passed, use this setting.', 'woocommerce-booking' ) )
			);

			register_setting(
				'bkap_global_settings',
				'woocommerce_booking_global_settings',
				array( 'bkap_global_settings', 'woocommerce_booking_global_settings_callback' )
			);

			do_action( 'bkap_after_global_holiday_field' );
		}

		/**
		 * This function is responsible for adding the settings for Zoom Meetings.
		 *
		 * @since 5.2.0
		 */
		public static function zoom_meeting_settings() {

			if ( isset( $_GET['action'] ) && 'calendar_sync_settings' === $_GET['action'] && isset( $_GET['section'] ) && 'zoom_meeting' === $_GET['section'] ) {

				// First, we register a section. This is necessary since all future options must belong to one.
				add_settings_section(
					'bkap_integrations_settings_section', // ID used to identify this section and with which to register options.
					__( 'Zoom Meetings', 'woocommerce-booking' ), // Title to be displayed on the administration page.
					array( 'Bkap_Zoom_Meeting_Settings', 'bkap_integrations_settings_callback' ), // Callback used to render the description of the section.
					'bkap_zoom_meeting_settings_page' // Page on which to add this section of options.
				);

				add_settings_field(
					'bkap_zoom_meeting_instructions',
					__( 'Instructions', 'woocommerce-booking' ),
					array( 'Bkap_Zoom_Meeting_Settings', 'bkap_zoom_meeting_instructions_callback' ),
					'bkap_zoom_meeting_settings_page',
					'bkap_integrations_settings_section',
					array( 'class' => 'bkap_zoom_meeting_instructions' )
				);

				add_settings_field(
					'bkap_zoom_api_key',
					__( 'API Key', 'woocommerce-booking' ),
					array( 'Bkap_Zoom_Meeting_Settings', 'bkap_zoom_api_key_callback' ),
					'bkap_zoom_meeting_settings_page',
					'bkap_integrations_settings_section'
				);

				add_settings_field(
					'bkap_zoom_api_secret',
					__( 'API Secret', 'woocommerce-booking' ),
					array( 'Bkap_Zoom_Meeting_Settings', 'bkap_zoom_api_secret_callback' ),
					'bkap_zoom_meeting_settings_page',
					'bkap_integrations_settings_section'
				);

				// Button for assigning to meeting to already placed bookings.
				// Show button if there is not background process running for this.
				$assign_meeting = get_option( 'bkap_assign_meeting_scheduled', false );
				if ( 'yes' !== $assign_meeting && 'done' !== $assign_meeting && bkap_zoom_meeting_enable() ) {

					add_settings_field(
						'bkap_zoom_adding_zoom_meetings',
						'',
						array( 'Bkap_Zoom_Meeting_Settings', 'bkap_zoom_adding_zoom_meetings_callback' ),
						'bkap_zoom_meeting_settings_page',
						'bkap_integrations_settings_section'
					);
				}
			}
		}

		/**
		 * This function will add settings fields for the Google Calendar Sync Settings.
		 *
		 * @since 3.6
		 */
		public static function bkap_gcal_settings() {

			// First, we register a section. This is necessary since all future options must belong to one.
			add_settings_section(
				'bkap_gcal_sync_general_settings_section',       // ID used to identify this section and with which to register options.
				__( 'General Settings', 'woocommerce-booking' ),     // Title to be displayed on the administration page.
				array( 'bkap_gcal_sync_settings', 'bkap_gcal_sync_general_settings_callback' ),      // Callback used to render the description of the section.
				'bkap_gcal_sync_settings_page'               // Page on which to add this section of options.
			);

			add_settings_field(
				'bkap_calendar_event_location',
				__( 'Event Location', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_event_location_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_gcal_sync_general_settings_section',
				array( __( '<br>Enter the text that will be used as location field in event of the Calendar. If left empty, website description is sent instead. <br><i>Note: You can use ADDRESS and CITY placeholders which will be replaced by their real values.</i>', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_calendar_event_summary',
				__( 'Event summary (name)', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_event_summary_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_gcal_sync_general_settings_section'
			);

			add_settings_field(
				'bkap_calendar_event_description',
				__( 'Event Description', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_event_description_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_gcal_sync_general_settings_section',
				array( '<br>For the above 2 fields, you can use the following placeholders which will be replaced by their real values:&nbsp;SITE_NAME, CLIENT, PRODUCT_NAME, PRODUCT_WITH_QTY, RESOURCE, PERSONS, ORDER_DATE_TIME, ORDER_DATE, ORDER_NUMBER, PRICE, PHONE, NOTE, ADDRESS, EMAIL (Client\'s email), ZOOM_MEETING', 'woocommerce-booking' )
			);

			add_settings_section(
				'bkap_calendar_sync_customer_settings_section',
				__( 'Customer Add to Calendar button Settings', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_sync_customer_settings_callback' ),
				'bkap_gcal_sync_settings_page'
			);

			add_settings_field(
				'bkap_add_to_calendar_order_received_page',
				__( 'Show Add to Calendar button on Order received page', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_add_to_calendar_order_received_page_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_customer_settings_section',
				array( __( 'Show Add to Calendar button on the Order Received page for the customers.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_add_to_calendar_customer_email',
				__( 'Show Add to Calendar button in the Customer notification email', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_add_to_calendar_customer_email_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_customer_settings_section',
				array( __( 'Show Add to Calendar button in the Customer notification email.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_add_to_calendar_my_account_page',
				__( 'Show Add to Calendar button on My account', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_add_to_calendar_my_account_page_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_customer_settings_section',
				array( __( 'Show Add to Calendar button on My account page for the customers.', 'woocommerce-booking' ) )
			);

			add_settings_section(
				'bkap_notice_for_use_product_gcalsync',
				'',
				array( 'bkap_gcal_sync_settings', 'bkap_notice_for_use_product_gcalsync_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section'
			);

			add_settings_section(
				'bkap_calendar_sync_admin_settings_section',
				__( 'Admin Calendar Sync Settings', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_sync_admin_settings_section_callback' ),
				'bkap_gcal_sync_settings_page'
			);

			add_settings_field(
				'bkap_calendar_sync_integration_mode',
				__( 'Integration Mode', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_sync_integration_mode_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( __( '<br><div class="bkap_sync_mode_info"><b>OAuth Sync</b> - Recommended method to sync events with Google Calendar with minimal steps.<br><b>Service Account Sync</b> - Traditional method to sync events with Google Calendar. Requires more steps to configure but the end result is same as OAuth Sync.<br><b>Sync Manually</b> - Will add an "Add to Calendar" button in emails received on new bookings and on Order Received page. Events will then be synced manually on click of the button.<br><b>Disabled</b> - Disables the integration with Google Calendar.', 'woocommerce-booking' ) )
			);

			add_settings_field(
				'bkap_sync_calendar_instructions',
				__( 'Instructions', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_sync_calendar_instructions_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_sync_instructions' )
			);

			add_settings_field(
				'bkap_calendar_key_file_name',
				__( 'Key file name', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_key_file_name_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_direct_sync' )
			);

			add_settings_field(
				'bkap_calendar_service_acc_email_address',
				__( 'Service account email address', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_service_acc_email_address_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_direct_sync' )
			);

			add_settings_field(
				'bkap_calendar_id',
				__( 'Calendar to be used', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_id_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_direct_sync' )
			);

			add_settings_field(
				'bkap_calendar_test_connection',
				'',
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_test_connection_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_direct_sync' )
			);

			add_settings_field(
				'bkap_calendar_oauth_integration',
				'',
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_oauth_integration_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_oauth_sync_first' )
			);

			add_settings_field(
				'bkap_admin_add_to_calendar_view_booking',
				__( 'Show Add to Calendar button on View Bookings page', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_admin_add_to_calendar_view_booking_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_add_to_calendar_view_booking' )
			);

			add_settings_field(
				'bkap_admin_add_to_calendar_email_notification',
				__( 'Show Add to Calendar button in New Order email notification', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_admin_add_to_calendar_email_notification_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_sync_admin_settings_section',
				array( 'class' => 'bkap_manual_sync' )
			);

			add_settings_section(
				'bkap_calendar_import_ics_feeds_section',
				__( 'Import Events', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_calendar_import_ics_feeds_section_callback' ),
				'bkap_gcal_sync_settings_page'
			);

			add_settings_field(
				'bkap_cron_time_duration',
				__( 'Run Automated Cron after X minutes', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_cron_time_duration_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_import_ics_feeds_section',
				array( '<br>The duration in minutes after which a cron job will be run automatically importing events from all the iCalendar/.ics Feed URLs.<br><i>Note: Setting it to a lower number can affect the site perfomance.</i>', 'woocommerce-booking' )
			);

			add_settings_field(
				'bkap_ics_feed_url_instructions',
				__( 'Instructions', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_ics_feed_url_instructions_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_import_ics_feeds_section'
			);

			add_settings_field(
				'bkap_ics_feed_url',
				__( 'iCalendar/.ics Feed URL', 'woocommerce-booking' ),
				array( 'bkap_gcal_sync_settings', 'bkap_ics_feed_url_callback' ),
				'bkap_gcal_sync_settings_page',
				'bkap_calendar_import_ics_feeds_section'
			);

			do_action( 'bkap_after_import_events_settings' );

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_event_location'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_event_summary',
				array( 'bkap_gcal_sync_settings', 'bkap_event_summary_validate_callback' )
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_event_description',
				array( 'bkap_gcal_sync_settings', 'bkap_event_description_validate_callback' )
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_add_to_calendar_order_received_page'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_add_to_calendar_customer_email'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_add_to_calendar_my_account_page'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_sync_integration_mode'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_details_1'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_calendar_oauth_integration'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_admin_add_to_calendar_view_booking'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_admin_add_to_calendar_email_notification'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_cron_time_duration'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_ics_feed_url_instructions'
			);

			register_setting(
				'bkap_gcal_sync_settings',
				'bkap_ics_feed_url'
			);

			register_setting(
				'bkap_zoom_meeting_settings',
				'bkap_zoom_api_key'
			);
			register_setting(
				'bkap_zoom_meeting_settings',
				'bkap_zoom_api_secret'
			);

			register_setting(
				'bkap_fluentcrm_settings',
				'bkap_fluentcrm_connection'
			);

			do_action( 'bkap_register_setting' );
		}

		/**
		 * This function adds review section in the footer of all the settings page.
		 *
		 * @since 3.6
		 */
		public static function bkap_add_review_note() {
			echo '<div class="tyche-info"><p>' . __( 'Happy with our Booking &amp; Appointment plugin? A review will help us immensely. You can review this plugin at <a href="https://www.facebook.com/TycheSoftwares/reviews/" target="_blank" class="button bkap_facebook_button"> Facebook</a> or submit your review <a href="https://www.tychesoftwares.com/submit-review/" target="_blank"><b> here.</b></a>', 'woocommerce-booking' ) . '</p></div>'; //phpcs:ignore
		}
	} // end class.
	$global_menu = new Global_Menu();
}
?>
