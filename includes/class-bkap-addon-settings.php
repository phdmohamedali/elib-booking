<?php
/**
 * Hide Booking Options for Vendors
 *
 * Additional settings for the plugin
 *
 * @author  Tyche Softwares
 * @package Hide Booking Options for Vendors
 */

if ( ! class_exists( 'BKAP_Addon_Settings' ) ) {

	/**
	 * BKAP_Addon_Settings
	 */
	class BKAP_Addon_Settings {

		public function __construct() {

			add_action( 'bkap_add_addon_settings', array( &$this, 'bkap_settings_view' ), 9 );
			add_action( 'admin_init', array( &$this, 'bkap_settings_init' ), 10 );

			add_action( 'bkap_after_load_products_css', array( &$this, 'bkap_hide_booking_options' ) );
			add_action( 'admin_footer', array( &$this, 'bkap_hide_booking_options' ) );

			add_filter( 'bkap_extra_options', array( &$this, 'bkap_hide_resource_persons_options' ), 10, 1 );

			add_filter( 'bkap_get_booking_types', array( &$this, 'bkap_hide_booking_types' ), 10, 1 );
		}

		/**
		 *  For Vendors, remove Booking Type from the Booking Type dropdown.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_booking_types( $booking_types ) {

			$path = untrailingslashit( WP_CONTENT_DIR . '/plugins/woocommerce-booking/' );
			include_once $path . '/includes/vendors/vendors-common.php';

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options', array() );

				if ( isset( $bkap_hide_booking_options['booking_type'] ) ) {

					$bkap_types = $bkap_hide_booking_options['booking_type'];
					foreach ( $bkap_types as $bkap_type ) {
						if ( isset( $booking_types[ $bkap_type ] ) ) {
							unset( $booking_types[ $bkap_type ] );
						}
					}
				}
			}

			return $booking_types;
		}

		/**
		 * Hide Resource and Persons from the Vendor Dashboard.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_resource_persons_options( $data ) {

			$path = untrailingslashit( WP_CONTENT_DIR . '/plugins/woocommerce-booking/' );
			include_once $path . '/includes/vendors/vendors-common.php';

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options', array() );

				if ( isset( $bkap_hide_booking_options['resource'] ) && 'on' === $bkap_hide_booking_options['resource'] ) {
					// $data['bkap_resource']['style'] = 'display: none !important;';
					unset( $data['bkap_resource'] );
				}

				if ( isset( $bkap_hide_booking_options['persons'] ) && 'on' === $bkap_hide_booking_options['persons'] ) {
					// $data['bkap_person']['style'] = $data['bkap_person']['style'] . 'display: none !important;';
					unset( $data['bkap_person'] );
				}
			}

			return $data;
		}

		/**
		 * Adding CSS to hide the options for the Vendor.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_booking_options() {

			$path = untrailingslashit( WP_CONTENT_DIR . '/plugins/woocommerce-booking/' );
			include_once $path . '/includes/vendors/vendors-common.php';

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				self::bkap_hide_booking_options_script();
			}
		}

		/**
		 * Preparing the CSS to hide the options.
		 *
		 * @since 5.13.0
		 */
		public static function bkap_hide_booking_options_script() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options', array() );

			$id_class_data = array(
				'enable_booking'         => '#enable_booking_options_section',
				'booking_type_section'   => '#enable_booking_types_section',
				'inline_calendar'        => '#enable_inline_calendar_section',
				'purchase_without_date'  => '#purchase_wo_date_section',
				'requires_confirmation'  => '#requires_confirmation_section',
				'can_be_cancelled'       => '#can_be_cancelled_section',
				'advance_booking_period' => '#booking_minimum_number_days_row',
				'nod_to_choose'          => '#booking_maximum_number_days_row',
				'max_booking_on_date'    => '#booking_lockout_date_row',
				'min_no_of_nights'       => '#booking_minimum_number_days_multiple_row',
				'max_no_of_nights'       => '#booking_maximum_number_days_multiple_row',
				'google_calendar_export' => '#bkap_gcal_export_section',
				'google_calendar_import' => '#bkap_gcal_import_section',
				'zoom_meetings'          => '.bkap_integration_zoom_button',
				'fluentcrm'              => '.bkap_integration_fluentcrm_button',
				'zapier'                 => '.bkap_integration_zapier_button',
			);

			$css = '';
			foreach ( $bkap_hide_booking_options as $key => $value ) {
				if ( 'on' === $value && isset( $id_class_data[ $key ] ) ) {
					$css .= $id_class_data[ $key ] . ', ';
				}
			}

			if ( '' !== $css ) {
				$css = substr( $css, 0, -2 );
				?>
				<style type="text/css">
					<?php echo $css; ?> { display: none !important; }
				</style>
				<?php
			}
		}

		/**
		 * Registers settings of the plugin and attached to hook to display on Addon Settings Tab
		 */
		public function bkap_settings_view() {

			$action = isset( $_GET['action'] ) ? $_GET['action'] : '';

			if ( 'addon_settings' === $action ) {

				if ( ! BKAP_License::business_license() ) {
					?>
					<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::business_license_error_message(); // phpcs:ignore; ?></div>
					<?php
					return;
				}

				bkap_include_select2_scripts();
				?>
				<div id="content">
					<form method="post" action="options.php">
						<?php settings_fields( 'bkap_hide_booking_options_settings' ); ?>
						<?php do_settings_sections( 'bkap_hide_booking_options_settings' ); ?> 
						<?php submit_button(); ?>
					</form>
				</div>
				<?php
			}
		}

		/**
		 * Settings API Initialization
		 */
		public function bkap_settings_init() {

			register_setting( 'bkap_hide_booking_options_settings', 'bkap_hide_booking_options' );

			add_settings_section(
				'bkap_hide_booking_options_section',
				__( 'Hide Booking Options on Vendor Dashboard', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_booking_options_callback' ),
				'bkap_hide_booking_options_settings'
			);

			add_settings_field(
				'general_tab',
				__( 'General Tab Options', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_general_tab_callback' ),
				'bkap_hide_booking_options_settings',
				'bkap_hide_booking_options_section'
			);

			add_settings_field(
				'availability_tab',
				__( 'Availability Tab Options', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_availability_tab_callback' ),
				'bkap_hide_booking_options_settings',
				'bkap_hide_booking_options_section'
			);

			add_settings_field(
				'resource_tab',
				__( 'Resource Tab Options', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_resource_tab_callback' ),
				'bkap_hide_booking_options_settings',
				'bkap_hide_booking_options_section'
			);

			add_settings_field(
				'persons_tab',
				__( 'Persons Tab Options', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_persons_tab_callback' ),
				'bkap_hide_booking_options_settings',
				'bkap_hide_booking_options_section'
			);

			add_settings_field(
				'integration_tab',
				__( 'Integrations Tab Options', 'woocommerce-booking' ),
				array( $this, 'bkap_hide_integration_tab_callback' ),
				'bkap_hide_booking_options_settings',
				'bkap_hide_booking_options_section'
			);
		}

		/**
		 * General Tab Options.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_general_tab_callback() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$descriptions              = array(
				'enable_booking'        => __( 'Hide General -> Enable Booking option from Booking Meta Box.', 'woocommerce-booking' ),
				'booking_type'          => array(
					__( 'Selected booking type will be removed from General -> Booking Type dropdown.', 'woocommerce-booking' ),
					__( 'Hide General -> Booking Type dropdown.', 'woocommerce-booking' ),
				),
				'inline_calendar'       => __( 'Hide General -> Enable Inline Calendar option from Booking Meta Box.', 'woocommerce-booking' ),
				'purchase_without_date' => __( 'Hide General -> Purchase without choosing date option from Booking Meta Box.', 'woocommerce-booking' ),
				'requires_confirmation' => __( 'Hide General -> Requires Confirmation option from Booking Meta Box.', 'woocommerce-booking' ),
				'can_be_cancelled'      => __( 'Hide General -> Can be cancelled? option from Booking Meta Box.', 'woocommerce-booking' ),
			);

			foreach ( $descriptions as $key => $value ) {
				$function_name = 'bkap_hide_' . $key . '_callback';
				self::$function_name( array( $value, $key, $bkap_hide_booking_options ) );
				echo '<br /><br />';
			}
		}

		/**
		 * Availability Tab Options.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_availability_tab_callback() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$descriptions              = array(
				'advance_booking_period' => __( 'Hide Availability -> Advance Booking Period (in hours) option from Booking Meta Box.', 'woocommerce-booking' ),
				'nod_to_choose'          => __( 'Hide Availability -> Number of dates to choose option from Booking Meta Box.', 'woocommerce-booking' ),
				'max_booking_on_date'    => __( 'Hide Availability -> Maximum Bookings On Any Date option from Booking Meta Box.', 'woocommerce-booking' ),
				'min_no_of_nights'       => __( 'Hide Availability -> Minimum number of nights to book option from Booking Meta Box.', 'woocommerce-booking' ),
				'max_no_of_nights'       => __( 'Hide Availability -> Maximum number of nights to book option from Booking Meta Box.', 'woocommerce-booking' ),
			);

			foreach ( $descriptions as $key => $value ) {
				$function_name = 'bkap_hide_' . $key . '_callback';
				self::$function_name( array( $value, $key, $bkap_hide_booking_options ) );
				echo '<br /><br />';
			}
		}

		/**
		 * Resource Tab Options.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_resource_tab_callback() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$descriptions              = array(
				'resource' => __( 'Hide Resource option from Booking Meta Box.', 'woocommerce-booking' ),
			);

			foreach ( $descriptions as $key => $value ) {
				$function_name = 'bkap_hide_' . $key . '_callback';
				self::$function_name( array( $value, $key, $bkap_hide_booking_options ) );
				echo '<br /><br />';
			}
		}

		/**
		 * Persons Tab Options.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_persons_tab_callback() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$descriptions              = array(
				'persons' => __( 'Hide Persons options from Booking Meta Box.', 'woocommerce-booking' ),
			);

			foreach ( $descriptions as $key => $value ) {
				$function_name = 'bkap_hide_' . $key . '_callback';
				self::$function_name( array( $value, $key, $bkap_hide_booking_options ) );
				echo '<br /><br />';
			}
		}

		/**
		 * Integrations Tab Options.
		 *
		 * @since 5.13.0
		 */
		public function bkap_hide_integration_tab_callback() {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$descriptions              = array(
				'google_calendar_export' => __( 'Hide Integrations -> Google Calendar Export option from Booking Meta Box.', 'woocommerce-booking' ),
				'google_calendar_import' => __( 'Hide Integrations -> Google Calendar Import option from Booking Meta Box.', 'woocommerce-booking' ),
				'zoom_meetings'          => __( 'Hide Integrations -> Zoom Meetings option from Booking Meta Box.', 'woocommerce-booking' ),
				'fluentcrm'              => __( 'Hide Integrations -> FluentCRM option from Booking Meta Box.', 'woocommerce-booking' ),
				'zapier'                 => __( 'Hide Integrations -> Zapier option from Booking Meta Box.', 'woocommerce-booking' ),
			);

			foreach ( $descriptions as $key => $value ) {
				$function_name = 'bkap_hide_' . $key . '_callback';
				self::$function_name( array( $value, $key, $bkap_hide_booking_options ) );
				echo '<br /><br />';
			}
		}

		/**
		 * Zapier.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_zapier_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$zapier = '';
			if ( isset( $bkap_hide_booking_options['zapier'] ) && 'on' === $bkap_hide_booking_options['zapier'] ) {
				$zapier = 'checked';
			}

			echo '<input type="checkbox" id="zapier" name="bkap_hide_booking_options[zapier]" ' . $zapier . '/>';
			$html = '<label for="zapier"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * FluentCRM.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_fluentcrm_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$fluentcrm = '';
			if ( isset( $bkap_hide_booking_options['fluentcrm'] ) && 'on' === $bkap_hide_booking_options['fluentcrm'] ) {
				$fluentcrm = 'checked';
			}

			echo '<input type="checkbox" id="fluentcrm" name="bkap_hide_booking_options[fluentcrm]" ' . $fluentcrm . '/>';
			$html = '<label for="fluentcrm"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Zoom Meeting.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_zoom_meetings_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$zoom_meetings = '';
			if ( isset( $bkap_hide_booking_options['zoom_meetings'] ) && 'on' === $bkap_hide_booking_options['zoom_meetings'] ) {
				$zoom_meetings = 'checked';
			}

			echo '<input type="checkbox" id="zoom_meetings" name="bkap_hide_booking_options[zoom_meetings]" ' . $zoom_meetings . '/>';
			$html = '<label for="zoom_meetings"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Google Calendar Import.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_google_calendar_import_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$google_calendar_import = '';
			if ( isset( $bkap_hide_booking_options['google_calendar_import'] ) && 'on' === $bkap_hide_booking_options['google_calendar_import'] ) {
				$google_calendar_import = 'checked';
			}

			echo '<input type="checkbox" id="google_calendar_import" name="bkap_hide_booking_options[google_calendar_import]" ' . $google_calendar_import . '/>';
			$html = '<label for="google_calendar_import"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Google Calendar Export.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_google_calendar_export_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$google_calendar_export = '';
			if ( isset( $bkap_hide_booking_options['google_calendar_export'] ) && 'on' === $bkap_hide_booking_options['google_calendar_export'] ) {
				$google_calendar_export = 'checked';
			}

			echo '<input type="checkbox" id="google_calendar_export" name="bkap_hide_booking_options[google_calendar_export]" ' . $google_calendar_export . '/>';
			$html = '<label for="google_calendar_export"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Persons.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_persons_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$persons = '';
			if ( isset( $bkap_hide_booking_options['persons'] ) && 'on' === $bkap_hide_booking_options['persons'] ) {
				$persons = 'checked';
			}

			echo '<input type="checkbox" id="persons" name="bkap_hide_booking_options[persons]" ' . $persons . '/>';
			$html = '<label for="persons"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Resource.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_resource_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$resource = '';
			if ( isset( $bkap_hide_booking_options['resource'] ) && 'on' === $bkap_hide_booking_options['resource'] ) {
				$resource = 'checked';
			}

			echo '<input type="checkbox" id="resource" name="bkap_hide_booking_options[resource]" ' . $resource . '/>';
			$html = '<label for="resource"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Maximum numbers of nights to book.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_max_no_of_nights_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$max_no_of_nights = '';
			if ( isset( $bkap_hide_booking_options['max_no_of_nights'] ) && 'on' === $bkap_hide_booking_options['max_no_of_nights'] ) {
				$max_no_of_nights = 'checked';
			}

			echo '<input type="checkbox" id="max_no_of_nights" name="bkap_hide_booking_options[max_no_of_nights]" ' . $max_no_of_nights . '/>';
			$html = '<label for="max_no_of_nights"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Minimum numbers of nights to book.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_min_no_of_nights_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$min_no_of_nights = '';
			if ( isset( $bkap_hide_booking_options['min_no_of_nights'] ) && 'on' === $bkap_hide_booking_options['min_no_of_nights'] ) {
				$min_no_of_nights = 'checked';
			}

			echo '<input type="checkbox" id="min_no_of_nights" name="bkap_hide_booking_options[min_no_of_nights]" ' . $min_no_of_nights . '/>';
			$html = '<label for="min_no_of_nights"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Maximum Booking on a Date.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_max_booking_on_date_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$max_booking_on_date = '';
			if ( isset( $bkap_hide_booking_options['max_booking_on_date'] ) && $bkap_hide_booking_options['max_booking_on_date'] == 'on' ) {
				$max_booking_on_date = 'checked';
			}

			echo '<input type="checkbox" id="max_booking_on_date" name="bkap_hide_booking_options[max_booking_on_date]" ' . $max_booking_on_date . '/>';
			$html = '<label for="max_booking_on_date"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Number of Dates to choose.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_nod_to_choose_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$nod_to_choose = '';
			if ( isset( $bkap_hide_booking_options['nod_to_choose'] ) && 'on' === $bkap_hide_booking_options['nod_to_choose'] ) {
				$nod_to_choose = 'checked';
			}

			echo '<input type="checkbox" id="nod_to_choose" name="bkap_hide_booking_options[nod_to_choose]" ' . $nod_to_choose . '/>';
			$html = '<label for="nod_to_choose"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Advance Booking Period.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_advance_booking_period_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$advance_booking_period = '';
			if ( isset( $bkap_hide_booking_options['advance_booking_period'] ) && $bkap_hide_booking_options['advance_booking_period'] == 'on' ) {
				$advance_booking_period = 'checked';
			}

			echo '<input type="checkbox" id="advance_booking_period" name="bkap_hide_booking_options[advance_booking_period]" ' . $advance_booking_period . '/>';
			$html = '<label for="advance_booking_period"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Can be cancelled.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_can_be_cancelled_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$can_be_cancelled = '';
			if ( isset( $bkap_hide_booking_options['can_be_cancelled'] ) && 'on' === $bkap_hide_booking_options['can_be_cancelled'] ) {
				$can_be_cancelled = 'checked';
			}

			echo '<input type="checkbox" id="can_be_cancelled" name="bkap_hide_booking_options[can_be_cancelled]" ' . $can_be_cancelled . '/>';
			$html = '<label for="can_be_cancelled"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Requires Confirmation.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_requires_confirmation_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$requires_confirmation = '';
			if ( isset( $bkap_hide_booking_options['requires_confirmation'] ) && 'on' === $bkap_hide_booking_options['requires_confirmation'] ) {
				$requires_confirmation = 'checked';
			}

			echo '<input type="checkbox" id="requires_confirmation" name="bkap_hide_booking_options[requires_confirmation]" ' . $requires_confirmation . '/>';
			$html = '<label for="requires_confirmation"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Purchase without choosing date.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_purchase_without_date_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$purchase_without_date = '';
			if ( isset( $bkap_hide_booking_options['purchase_without_date'] ) && 'on' === $bkap_hide_booking_options['purchase_without_date'] ) {
				$purchase_without_date = 'checked';
			}

			echo '<input type="checkbox" id="purchase_without_date" name="bkap_hide_booking_options[purchase_without_date]" ' . $purchase_without_date . '/>';
			$html = '<label for="purchase_without_date"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Inline Calendar.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_inline_calendar_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$inline_calendar = '';
			if ( isset( $bkap_hide_booking_options['inline_calendar'] ) && 'on' === $bkap_hide_booking_options['inline_calendar'] ) {
				$inline_calendar = 'checked';
			}

			echo '<input type="checkbox" id="inline_calendar" name="bkap_hide_booking_options[inline_calendar]" ' . $inline_calendar . '/>';
			$html = '<label for="inline_calendar"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Booking Type.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_booking_type_callback( $args ) {

			$argument                  = $args[0];
			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );
			$selected_booking_type     = isset( $bkap_hide_booking_options['booking_type'] ) ? $bkap_hide_booking_options['booking_type'] : array();
			$booking_types             = bkap_get_booking_types();

			$booking_type_section = '';
			if ( isset( $bkap_hide_booking_options['booking_type_section'] ) && $bkap_hide_booking_options['booking_type_section'] == 'on' ) {
				$booking_type_section = 'checked';
			}

			?>
			<select id="booking_type" class="booking_type"
					name="bkap_hide_booking_options[booking_type][]"
					placehoder="Select Booking Type"
					multiple="multiple">
				<?php
				foreach ( $booking_types as $key => $booking_type ) {
					$option_value = $key;
					$selected     = '';
					if ( in_array( $key, $selected_booking_type ) ) {
						$selected = 'selected="selected"';
					}
					?>
					<option value="<?php echo esc_attr( $option_value ); ?>" <?php echo $selected; ?>><?php echo esc_html( $booking_type['label'] ); ?></option>
					<?php
				}
				?>
			</select>
			<br>
			<label for="booking_type"><?php echo $argument[0]; ?></label>
			<br>
			<br>
			<input type="checkbox" id="booking_type_section" name="bkap_hide_booking_options[booking_type_section]" <?php echo $booking_type_section; ?>/>
			<label for="booking_type_section"><?php echo $argument[1]; ?></label>
			<?php
		}

		/**
		 * Enable Booking Option.
		 *
		 * @param array $args Arguments.
		 * @since 5.13.0
		 */
		public static function bkap_hide_enable_booking_callback( $args ) {

			$bkap_hide_booking_options = get_option( 'bkap_hide_booking_options' );

			$booking_option = '';
			if ( isset( $bkap_hide_booking_options['enable_booking'] ) && $bkap_hide_booking_options['enable_booking'] == 'on' ) {
				$booking_option = 'checked';
			}

			echo '<input type="checkbox" id="enable_booking" name="bkap_hide_booking_options[enable_booking]" ' . $booking_option . '/>';
			$html = '<label for="enable_booking"> ' . $args[0] . '</label>';
			echo $html;
		}

		/**
		 * Settings Section callback (Add any additional information messages here)
		 */
		public function bkap_hide_booking_options_callback() {
			?>
			<p><?php echo __( 'Hide following Booking options of the Booking Meta Box for the Vendors.', 'woocommerce-booking' ); ?></p>
			<?php
		}
	}
	new BKAP_Addon_Settings();
}
