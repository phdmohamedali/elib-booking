<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for importing and exporting Bookable Products via the WoCommerce Product Import/Export buttons.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.14.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_Import_Export_Bookable_Products' ) ) {

	/**
	 * BKAP Background Actions.
	 *
	 * @since 5.14.0
	 */
	class BKAP_Import_Export_Bookable_Products {

		/**
		 * Booking Meta Data.
		 *
		 * @var array $booking_meta_data.
		 */
		private static $booking_meta_data = array();

		/**
		 * Booking Fields.
		 *
		 * @var array $booking_fields.
		 */
		private static $booking_fields = array();

		/**
		 * Data from Parse Functions.
		 *
		 * @var array $parsed_data.
		 */
		private $parsed_data = array();

		/**
		 * Initializes the BKAP_Import_Export_Bookable_Products class. Checks for an existing instance and if it doesn't find one, it then creates it.
		 *
		 * @since 9.28.3
		 */
		public static function init() {

			static $instance = false;

			if ( ! $instance ) {
				$instance = new BKAP_Import_Export_Bookable_Products();
			}

			return $instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 5.14.0
		 */
		public function __construct() {
			add_filter( 'woocommerce_product_export_product_default_columns', array( &$this, 'export_product_column_names' ), 10, 1 );
			add_filter( 'woocommerce_product_export_row_data', array( &$this, 'add_booking_data_to_export_row' ), 10, 3 );
			add_filter( 'woocommerce_csv_product_import_mapping_default_columns', array( &$this, 'import_product_column_names' ), 105 );
			add_filter( 'woocommerce_csv_product_import_mapping_options', array( &$this, 'export_product_column_names' ), 10 );
			add_filter( 'woocommerce_product_importer_formatting_callbacks', array( &$this, 'booking_formatting_callbacks' ), 10, 2 );
			add_filter( 'woocommerce_product_importer_parsed_data', array( &$this, 'format_parsed_data' ), 10, 2 );
			self::set_default_booking_fields();
		}

		/**
		 * Sets the default booking fields for import/export..
		 *
		 * @since 5.14.0
		 */
		public static function set_default_booking_fields() {
			self::$booking_fields = array(
				'enable_booking'            => __( 'Booking Enabled', 'woocommerce-booking' ),
				'booking_type'              => __( 'Booking Type', 'woocommerce-booking' ),
				'inline_calendar'           => __( 'Inline Calendar', 'woocommerce-booking' ),
				'purchase_without_date'     => __( 'Purchase w/o Date', 'woocommerce-booking' ),
				'requires_confirmation'     => __( 'Requires Confirmation', 'woocommerce-booking' ),
				'can_be_cancelled'          => __( 'Booking Cancellation', 'woocommerce-booking' ),
				'can_be_cancelled_duration' => __( 'Booking Cancellation Duration', 'woocommerce-booking' ),
				'can_be_cancelled_period'   => __( 'Booking Cancellation Period', 'woocommerce-booking' ),
				'abp'                       => __( 'Advance Booking Period', 'woocommerce-booking' ),
				'maximum_dates'             => __( 'Maximum No. of Dates', 'woocommerce-booking' ),
				'multidates_selecton_type'  => __( 'Multidates Selection Type', 'woocommerce-booking' ),
				'multidates_no_dates'       => __( 'Multidates No. Dates', 'woocommerce-booking' ),
				'maximum_bookings'          => __( 'Maximum Bookings', 'woocommerce-booking' ),
				'zoom_settings'             => __( 'Zoom Settings', 'woocommerce-booking' ),
				'zapier_settings'           => __( 'Zapier Settings', 'woocommerce-booking' ),
				'gcal_settings'             => __( 'Google Calendar Settings', 'woocommerce-booking' ),
				'fluentcrm_settings'        => __( 'FluentCRM Settings', 'woocommerce-booking' ),
				'person_settings'           => __( 'Person Settings', 'woocommerce-booking' ),
				'resource_settings'         => __( 'Resource Settings', 'woocommerce-booking' ),
				'weekday_lockouts'          => __( 'Weekday Lockouts', 'woocommerce-booking' ),
				'weekday_date_timeslots'    => __( 'Weekday Dates/Timeslots', 'woocommerce-booking' ),
				'dates_months_availability' => __( 'Dates/Months Availability', 'woocommerce-booking' ),
				'manage_time_availability'  => __( 'Manage Time Availability', 'woocommerce-booking' ),
				'booking_meta'              => __( 'Booking Meta', 'woocommerce-booking' ),
				'booking_settings_meta'     => __( 'Booking Settings Meta', 'woocommerce-booking' ),
			);
		}

		/**
		 * Adds Booking Fields to the Column Name export list.
		 *
		 * @param array $column_names Array of default Column Names.
		 * @since 5.14.0
		 */
		public static function export_product_column_names( $column_names ) {
			return array_merge( $column_names, self::$booking_fields );
		}

		/**
		 * Gets the Booking meta data for a product.
		 *
		 * @param integer $product_id Product ID.
		 * @since 5.14.0
		 */
		public static function get_booking_meta_data( $product_id ) {

			global $wpdb;

			$booking_meta_data = array();
			$query             = "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d AND meta_key LIKE '%bkap_%'";
			$data              = $wpdb->get_results(
				$wpdb->prepare( $query, $product_id ) //phpcs:ignore
			);

			if ( is_array( $data ) && count( $data ) > 0 ) {
				foreach ( $data as $key => $value ) {
					$booking_meta_data[ $value->meta_key ] = maybe_unserialize( $value->meta_value );
				}
			}

			return $booking_meta_data;
		}

		/**
		 * Checks for existing key in the row data and sets data for that key if found.
		 *
		 * @param array  $row Row data.
		 * @param string $key Row key - this will be checked to see if row data exists.
		 * @param mixed  $data Data to be set for Row key if it exists.
		 * @since 5.14.0
		 */
		public static function set_row_data( $row, $key, $data ) {

			if ( isset( $row[ $key ] ) && '' !== $data ) {

				if ( is_array( $data ) && 0 === count( $data ) ) {
					return $row;
				}

				$row[ $key ] = $data;
			}

			return $row;
		}

		/**
		 * Checks that booking meta key exists.
		 *
		 * @param array  $booking_meta Booking Meta data.
		 * @param string $key Booking Meta Key.
		 * @since 5.14.0
		 */
		public static function set_meta_data( $booking_meta, $key ) {

			if ( isset( $booking_meta[ $key ] ) ) {
				$booking_meta_data = $booking_meta[ $key ];
				unset( $booking_meta[ $key ] );
				self::$booking_meta_data = $booking_meta;
				return $booking_meta_data;
			}

			return '';
		}

		/**
		 * Adds Booking data to the row data that has been provisioned for export.
		 *
		 * @param array                   $row         An associative array containing the data of a single row in the CSV file.
		 * @param WC_Product              $product     The product object correspnding to the current row.
		 * @param WC_Product_CSV_Exporter $instance    The instance of the CSV exporter.
		 * @since 5.14.0
		 */
		public static function add_booking_data_to_export_row( $row, $product, $instance = null ) {

			$product_id              = $product->get_id();
			self::$booking_meta_data = self::get_booking_meta_data( $product_id );

			$row = self::set_row_data( $row, 'enable_booking', self::set_meta_data( self::$booking_meta_data, '_bkap_enable_booking' ) );
			$row = self::set_row_data( $row, 'booking_type', self::set_meta_data( self::$booking_meta_data, '_bkap_booking_type' ) );
			$row = self::set_row_data( $row, 'inline_calendar', self::set_meta_data( self::$booking_meta_data, '_bkap_enable_inline' ) );
			$row = self::set_row_data( $row, 'purchase_without_date', self::set_meta_data( self::$booking_meta_data, '_bkap_purchase_wo_date' ) );
			$row = self::set_row_data( $row, 'requires_confirmation', self::set_meta_data( self::$booking_meta_data, '_bkap_requires_confirmation' ) );

			// Can be cancelled.
			$can_be_cancelled = self::set_meta_data( self::$booking_meta_data, '_bkap_can_be_cancelled' );
			if ( '' !== $can_be_cancelled ) {

				if ( 0 !== count( $can_be_cancelled ) ) {
					$status   = $can_be_cancelled['status'];
					$duration = $can_be_cancelled['duration'];
					$period   = $can_be_cancelled['period'];

					$row = self::set_row_data( $row, 'can_be_cancelled', $status );
					$row = self::set_row_data( $row, 'can_be_cancelled_duration', $duration );
					$row = self::set_row_data( $row, 'can_be_cancelled_period', $period );
				}
			}

			$row = self::set_row_data( $row, 'abp', self::set_meta_data( self::$booking_meta_data, '_bkap_abp' ) );
			$row = self::set_row_data( $row, 'maximum_dates', self::set_meta_data( self::$booking_meta_data, '_bkap_max_bookable_days' ) );
			$row = self::set_row_data( $row, 'multidates_selecton_type', self::set_meta_data( self::$booking_meta_data, '_bkap_multidates_type' ) );
			$row = self::set_row_data( $row, 'multidates_no_dates', self::set_meta_data( self::$booking_meta_data, '_bkap_multidates_fixed_number' ) );
			$row = self::set_row_data( $row, 'maximum_bookings', self::set_meta_data( self::$booking_meta_data, '_bkap_date_lockout' ) );

			// Zoom.
			$zoom_settings = array(
				'_bkap_zoom_meeting'                   => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting' ),
				'_bkap_zoom_meeting_host'              => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_host' ),
				'_bkap_zoom_meeting_auth'              => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_auth' ),
				'_bkap_zoom_meeting_join_before_host'  => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_join_before_host' ),
				'_bkap_zoom_meeting_host_video'        => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_host_video' ),
				'_bkap_zoom_meeting_participant_video' => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_participant_video' ),
				'_bkap_zoom_meeting_mute_upon_entry'   => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_mute_upon_entry' ),
				'_bkap_zoom_meeting_auto_recording'    => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_auto_recording' ),
				'_bkap_zoom_meeting_alternative_host'  => self::set_meta_data( self::$booking_meta_data, '_bkap_zoom_meeting_alternative_host' ),
			);
			$row           = self::set_row_data( $row, 'zoom_settings', wp_json_encode( $zoom_settings ) );

			// Zapier.
			$zapier_settings = self::set_meta_data( self::$booking_meta_data, '_bkap_zapier' );
			if ( '' !== $zapier_settings ) {
				$zapier_settings = wp_json_encode( $zapier_settings );
			}
			$row = self::set_row_data( $row, 'zapier_settings', $zapier_settings );

			// Google Calendar.
			$gcal_settings = array(
				'_bkap_gcal_integration_mode'      => self::set_meta_data( self::$booking_meta_data, '_bkap_gcal_integration_mode' ),
				'_bkap_gcal_key_file_name'         => self::set_meta_data( self::$booking_meta_data, '_bkap_gcal_key_file_name' ),
				'_bkap_gcal_service_acc'           => self::set_meta_data( self::$booking_meta_data, '_bkap_gcal_service_acc' ),
				'_bkap_gcal_calendar_id'           => self::set_meta_data( self::$booking_meta_data, '_bkap_gcal_calendar_id' ),
				'_bkap_calendar_oauth_integration' => self::set_meta_data( self::$booking_meta_data, '_bkap_calendar_oauth_integration' ),
			);
			$row           = self::set_row_data( $row, 'gcal_settings', wp_json_encode( $gcal_settings ) );

			// Fluent CRM.
			$fluentcrm_settings = array(
				'_bkap_fluentcrm'      => self::set_meta_data( self::$booking_meta_data, '_bkap_fluentcrm' ),
				'_bkap_fluentcrm_list' => self::set_meta_data( self::$booking_meta_data, '_bkap_fluentcrm_list' ),
			);
			$row                = self::set_row_data( $row, 'fluentcrm_settings', wp_json_encode( $fluentcrm_settings ) );

			// Person Settings.
			$person_settings = array(
				'_bkap_person'              => self::set_meta_data( self::$booking_meta_data, '_bkap_person' ),
				'_bkap_min_person'          => self::set_meta_data( self::$booking_meta_data, '_bkap_min_person' ),
				'_bkap_max_person'          => self::set_meta_data( self::$booking_meta_data, '_bkap_max_person' ),
				'_bkap_price_per_person'    => self::set_meta_data( self::$booking_meta_data, '_bkap_price_per_person' ),
				'_bkap_each_person_booking' => self::set_meta_data( self::$booking_meta_data, '_bkap_each_person_booking' ),
				'_bkap_person_type'         => self::set_meta_data( self::$booking_meta_data, '_bkap_person_type' ),
				'_bkap_person_ids'          => self::set_meta_data( self::$booking_meta_data, '_bkap_person_ids' ),
				'_bkap_person_data'         => self::set_meta_data( self::$booking_meta_data, '_bkap_person_data' ),
			);

			$person_settings['person_titles'] = self::get_the_post_titles( $person_settings['_bkap_person_ids'] ); // Get Titles for Person IDs.
			$row                              = self::set_row_data( $row, 'person_settings', wp_json_encode( $person_settings ) );

			// Resource Settings.
			$resource_settings = array(
				'_bkap_resource'                     => self::set_meta_data( self::$booking_meta_data, '_bkap_resource' ),
				'_bkap_product_resource_lable'       => self::set_meta_data( self::$booking_meta_data, '_bkap_product_resource_lable' ),
				'_bkap_product_resource_selection'   => self::set_meta_data( self::$booking_meta_data, '_bkap_product_resource_selection' ),
				'_bkap_product_resource_max_booking' => self::set_meta_data( self::$booking_meta_data, '_bkap_product_resource_max_booking' ),
				'_bkap_product_resource_sorting'     => self::set_meta_data( self::$booking_meta_data, '_bkap_product_resource_sorting' ),
				'_bkap_product_resources'            => self::set_meta_data( self::$booking_meta_data, '_bkap_product_resources' ),
				'_bkap_resource_base_costs'          => self::set_meta_data( self::$booking_meta_data, '_bkap_resource_base_costs' ),
			);

			$resource_settings['resource_titles'] = self::get_the_post_titles( $resource_settings['_bkap_product_resources'] ); // Get Titles for Person IDs.
			$row                                  = self::set_row_data( $row, 'resource_settings', wp_json_encode( $resource_settings ) );

			// Weekdays.
			$weekday_lockouts = array(
				'_bkap_recurring_weekdays' => self::set_meta_data( self::$booking_meta_data, '_bkap_recurring_weekdays' ),
				'_bkap_recurring_lockout'  => self::set_meta_data( self::$booking_meta_data, '_bkap_recurring_lockout' ),
			);
			$row              = self::set_row_data( $row, 'weekday_lockouts', wp_json_encode( $weekday_lockouts ) );

			// Timeslots.
			$weekday_date_timeslots = self::set_meta_data( self::$booking_meta_data, '_bkap_time_settings' );
			if ( '' !== $weekday_date_timeslots ) {
				$weekday_date_timeslots = wp_json_encode( $weekday_date_timeslots );
			}
			$row = self::set_row_data( $row, 'weekday_date_timeslots', $weekday_date_timeslots );

			// Dates/Months Availability.
			$dates_months_availability = self::set_meta_data( self::$booking_meta_data, '_bkap_specific_dates' );
			if ( '' !== $dates_months_availability ) {
				$dates_months_availability = wp_json_encode( $dates_months_availability );
			}
			$row = self::set_row_data( $row, 'dates_months_availability', $dates_months_availability );

			// Manage Time Availability.
			$manage_time_availability = self::set_meta_data( self::$booking_meta_data, '_bkap_manage_time_availability' );
			if ( '' !== $manage_time_availability ) {
				$manage_time_availability = wp_json_encode( $manage_time_availability );
			}
			$row = self::set_row_data( $row, 'manage_time_availability', $manage_time_availability );

			// Woocommerce Boking Settings.
			$booking_settings_meta = get_post_meta( $product_id, 'woocommerce_booking_settings', true );
			$booking_settings_meta = wp_json_encode( $booking_settings_meta );
			$row                   = self::set_row_data( $row, 'booking_settings_meta', $booking_settings_meta );

			// Other Booking Meta not parsed.
			$booking_meta = wp_json_encode( self::$booking_meta_data );
			$row          = self::set_row_data( $row, 'booking_meta', $booking_meta );

			return $row;
		}

		/**
		 * Adds Booking Fields to the Column Name import list.
		 *
		 * @param array $column_names Array of default Column Names.
		 * @since 5.14.0
		 */
		public static function import_product_column_names( $column_names ) {

			// Reverse key-value pair for default booking fields.
			$fields = array_flip( self::$booking_fields );
			return array_merge( $column_names, $fields );
		}

		/**
		 * Adds callbacks needed for parse formatting functions.
		 *
		 * @param array                   $callbacks Array of callbacks of parse functions.
		 * @param WC_Product_CSV_Exporter $instance The instance of the CSV exporter.
		 * @since 5.14.0
		 */
		public function booking_formatting_callbacks( $callbacks, $instance ) {
			$mapped_fields = array_flip( $instance->get_mapped_keys() );

			foreach ( self::$booking_fields as $key => $field ) {
				$id = $mapped_fields[ $key ];
				if ( '' !== $id ) {
					$callbacks[ $id ] = array( &$this, 'parse_' . $key );
				}
			}

			return $callbacks;
		}

		/**
		 * Parse function for on/off values.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_function_on_off( $value ) {
			$allowed_values = array( 'on', 'off' );

			if ( ! in_array( $value, $allowed_values ) ) {
				$value = '';
			}

			if ( 'off' === $value ) {
				$value = '';
			}

			return $value;
		}

		/**
		 * Parse function for enable_booking.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_enable_booking( $value ) {
			return $this->parse_function_on_off( $value );
		}

		/**
		 * Parse function for booking_type.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_booking_type( $value ) {
			$allowed_booking_types = array( 'only_day', 'multiple_days', 'date_time', 'duration_time', 'multidates', 'multidates_fixedtime' );

			if ( ! in_array( $value, $allowed_booking_types ) ) {
				$value = '';
			}

			return $value;
		}

		/**
		 * Parse function for inline_calendar.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_inline_calendar( $value ) {
			return $this->parse_function_on_off( $value );
		}

		/**
		 * Parse function for purchase_without_date.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_purchase_without_date( $value ) {
			return $this->parse_function_on_off( $value );
		}

		/**
		 * Parse function for requires_confirmation.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_requires_confirmation( $value ) {
			return $this->parse_function_on_off( $value );
		}

		/**
		 * Parse function for can_be_cancelled.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_can_be_cancelled_duration( $value ) {
			$allowed_values = array( 'day', 'hour', 'minute' );

			if ( ! in_array( $value, $allowed_values ) ) {
				$value = '';
			}

			$this->parsed_data['can_be_cancelled_duration'] = $value;
			return $value;
		}

		/**
		 * Parse function for can_be_cancelled_period.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_can_be_cancelled_period( $value ) {
			$this->parsed_data['can_be_cancelled_period'] = $value;
			return (int) $value;
		}

		/**
		 * Parse function for can_be_cancelled.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_can_be_cancelled( $value ) {

			if ( ! isset( $this->parsed_data['can_be_cancelled_duration'] ) || '' === $this->parsed_data['can_be_cancelled_duration'] || 0 === $this->parsed_data['can_be_cancelled_period'] ) {
				return '';
			}

			return $this->parse_function_on_off( $value );
		}

		/**
		 * Parse function for abp.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_abp( $value ) {
			return (int) $value;
		}

		/**
		 * Parse function for maximum_dates.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_maximum_dates( $value ) {
			return (int) $value;
		}

		/**
		 * Parse function for multidates_selecton_type.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_multidates_selecton_type( $value ) {
			$allowed_values = array( 'fixed', 'range' );

			if ( ! in_array( $value, $allowed_values ) ) {
				$value = '';
			}

			return $value;
		}

		/**
		 * Parse function for multidates_no_dates.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_multidates_no_dates( $value ) {
			return (int) $value;
		}

		/**
		 * Parse function for maximum_bookings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_maximum_bookings( $value ) {
			return (int) $value;
		}

		/**
		 * Parse function for zoom_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_zoom_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for zapier_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_zapier_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for gcal_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_gcal_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for fluentcrm_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_fluentcrm_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for person_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_person_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for resource_settings.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_resource_settings( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for weekday_lockouts.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_weekday_lockouts( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for weekday_date_timeslots.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_weekday_date_timeslots( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for dates_months_availability.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_dates_months_availability( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for manage_time_availability.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_manage_time_availability( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for booking_meta.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_booking_meta( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Parse function for booking_settings_meta.
		 *
		 * @param string $value Field value.
		 * @since 5.14.0
		 */
		public function parse_booking_settings_meta( $value ) {
			return json_decode( $value, true );
		}

		/**
		 * Replace booking field with meta key.
		 *
		 * @param array  $data Parsed Data.
		 * @param string $booking_field_key Booking Field Key.
		 * @param string $booking_meta_field Booking Meta Field value.
		 * @since 5.14.0
		 */
		public static function replace_booking_field_with_meta_key( $data, $booking_field_key, $booking_meta_field ) {

			if ( isset( $data[ $booking_field_key ] ) ) {

				if ( is_array( $data[ $booking_field_key ] ) && '' === $booking_meta_field ) {

					// Person/Resource Settings.
					if ( 'person_settings' === $booking_field_key || 'resource_settings' === $booking_field_key ) {
						$is_person_settings   = 'person_settings' === $booking_field_key;
						$is_resource_settings = 'resource_settings' === $booking_field_key;
						$title                = 'person_settings' === $booking_field_key ? 'person_titles' : ( 'resource_settings' === $booking_field_key ? 'resource_titles' : '' );
						$titles               = $data[ $booking_field_key ][ $title ];

						if ( $is_person_settings ) {
							$_bkap_person_ids  = maybe_unserialize( $data[ $booking_field_key ]['_bkap_person_ids'] );
							$_bkap_person_data = maybe_unserialize( $data[ $booking_field_key ]['_bkap_person_data'] );
						}

						if ( $is_resource_settings ) {
							$_bkap_product_resources   = maybe_unserialize( $data[ $booking_field_key ]['_bkap_product_resources'] );
							$_bkap_resource_base_costs = maybe_unserialize( $data[ $booking_field_key ]['_bkap_resource_base_costs'] );
						}

						if ( '' !== $title && isset( $titles ) && is_array( $titles ) && count( $titles ) > 0 ) {
							foreach ( $titles as $id => $title ) {

								if ( $is_person_settings ) {
									$new_id            = BKAP_Person::bkap_create_person( $title );
									$_bkap_person_ids  = self::replace_array_index( $_bkap_person_ids, $id, $new_id, true );
									$_bkap_person_data = self::replace_array_index( $_bkap_person_data, $id, $new_id );
								}

								if ( $is_resource_settings ) {
									$new_id                    = BKAP_Product_Resource::bkap_create_resource( $title );
									$_bkap_product_resources   = self::replace_array_index( $_bkap_product_resources, $id, $new_id, true );
									$_bkap_resource_base_costs = self::replace_array_index( $_bkap_resource_base_costs, $id, $new_id );
								}
							}

							$booking_settings_meta = maybe_unserialize( $data['booking_settings_meta'] );

							if ( $is_person_settings ) {
								$data[ $booking_field_key ]['_bkap_person_ids'] = maybe_unserialize( $_bkap_person_ids );
								$booking_settings_meta['_bkap_person_ids']      = $data[ $booking_field_key ]['_bkap_person_ids'];
								$booking_settings_meta['bkap_person_ids']      = $data[ $booking_field_key ]['_bkap_person_ids'];

								$data[ $booking_field_key ]['_bkap_person_data'] = maybe_unserialize( $_bkap_person_data );
								$booking_settings_meta['_bkap_person_data']      = $data[ $booking_field_key ]['_bkap_person_data'];
								$booking_settings_meta['bkap_person_data']      = $data[ $booking_field_key ]['_bkap_person_data'];
								unset( $data[ $booking_field_key ]['person_titles'] );
							}

							if ( $is_resource_settings ) {
								$data[ $booking_field_key ]['_bkap_product_resources'] = maybe_unserialize( $_bkap_product_resources );
								$booking_settings_meta['_bkap_product_resources']      = $data[ $booking_field_key ]['_bkap_product_resources'];

								$data[ $booking_field_key ]['_bkap_resource_base_costs'] = maybe_unserialize( $_bkap_resource_base_costs );
								$booking_settings_meta['_bkap_resource_base_costs']      = $data[ $booking_field_key ]['_bkap_resource_base_costs'];
								unset( $data[ $booking_field_key ]['resource_titles'] );
							}

							$data['booking_settings_meta'] = $booking_settings_meta;
						}
					}

					foreach ( $data[ $booking_field_key ] as $key => $value ) {
						$data['meta_data'][] = array(
							'key'   => $key,
							'value' => $value,
						);
					}
				} else {
					$value               = $data[ $booking_field_key ];
					$data['meta_data'][] = array(
						'key'   => $booking_meta_field,
						'value' => $value,
					);
				}

				unset( $data[ $booking_field_key ] );
			}

			return $data;
		}

		/**
		 * Formats the parsed data and assign booking fields as meta key-values.
		 *
		 * @param array                   $data Parsed data.
		 * @param WC_Product_CSV_Exporter $instance The instance of the CSV exporter.
		 * @since 5.14.0
		 */
		public static function format_parsed_data( $data, $instance ) {

			// enable_booking.
			$data = self::replace_booking_field_with_meta_key( $data, 'enable_booking', '_bkap_enable_booking' );

			// booking_type.
			$data = self::replace_booking_field_with_meta_key( $data, 'booking_type', '_bkap_booking_type' );

			// inline_calendar.
			$data = self::replace_booking_field_with_meta_key( $data, 'inline_calendar', '_bkap_enable_inline' );

			// purchase_without_date.
			$data = self::replace_booking_field_with_meta_key( $data, 'purchase_without_date', '_bkap_purchase_wo_date' );

			// requires_confirmation.
			$data = self::replace_booking_field_with_meta_key( $data, 'requires_confirmation', '_bkap_requires_confirmation' );

			// can_be_cancelled.
			$can_be_cancelled          = '';
			$can_be_cancelled_duration = '';
			$can_be_cancelled_period   = '';

			$key = 'can_be_cancelled';
			if ( isset( $data[ $key ] ) ) {
				$can_be_cancelled = $data[ $key ];
				unset( $data[ $key ] );
			}

			$key = 'can_be_cancelled_duration';
			if ( isset( $data[ $key ] ) ) {
				$can_be_cancelled_duration = $data[ $key ];
				unset( $data[ $key ] );
			}

			$key = 'can_be_cancelled_period';
			if ( isset( $data[ $key ] ) ) {
				$can_be_cancelled_period = $data[ $key ];
				unset( $data[ $key ] );
			}

			$data['meta_data'][] = array(
				'key'   => '_bkap_can_be_cancelled',
				'value' => array(
					'status'   => $can_be_cancelled,
					'duration' => $can_be_cancelled_duration,
					'period'   => $can_be_cancelled_period,
				),
			);

			// abp.
			$data = self::replace_booking_field_with_meta_key( $data, 'abp', '_bkap_abp' );

			// maximum_dates.
			$data = self::replace_booking_field_with_meta_key( $data, 'maximum_dates', '_bkap_max_bookable_days' );

			// multidates_selecton_type.
			$data = self::replace_booking_field_with_meta_key( $data, 'multidates_selecton_type', '_bkap_multidates_type' );

			// multidates_selecton_type.
			$data = self::replace_booking_field_with_meta_key( $data, 'multidates_no_dates', '_bkap_multidates_fixed_number' );

			// maximum_bookings.
			$data = self::replace_booking_field_with_meta_key( $data, 'maximum_bookings', '_bkap_date_lockout' );

			// zoom_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'zoom_settings', '' );

			// zapier_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'zapier_settings', '_bkap_zapier' );

			// gcal_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'gcal_settings', '' );

			// fluentcrm_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'fluentcrm_settings', '' );

			// person_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'person_settings', '' );

			// resource_settings.
			$data = self::replace_booking_field_with_meta_key( $data, 'resource_settings', '' );

			// weekday_lockouts.
			$data = self::replace_booking_field_with_meta_key( $data, 'weekday_lockouts', '' );

			// weekday_date_timeslots.
			$data = self::replace_booking_field_with_meta_key( $data, 'weekday_date_timeslots', '_bkap_time_settings' );

			// dates_months_availability.
			$data = self::replace_booking_field_with_meta_key( $data, 'dates_months_availability', '_bkap_specific_dates' );

			// manage_time_availability.
			$data = self::replace_booking_field_with_meta_key( $data, 'manage_time_availability', '_bkap_manage_time_availability' );

			// booking_settings_meta.
			$data = self::replace_booking_field_with_meta_key( $data, 'booking_settings_meta', 'woocommerce_booking_settings' );

			// booking_meta.
			$data = self::replace_booking_field_with_meta_key( $data, 'booking_meta', '' );

			return $data;
		}

		/**
		 * Get Post Titles from Person/Resource IDs.
		 *
		 * @param string|array $post_ids Post IDs in serialized or array format.
		 * @since 5.14.0
		 */
		public static function get_the_post_titles( $post_ids ) {

			if ( ! is_array( $post_ids ) ) {
				$post_ids = maybe_unserialize( $post_ids );
			}

			$post_titles = array();
			if($post_ids)
			{
				if ( count( $post_ids ) > 0 ) {
					foreach ( $post_ids as $post_id ) {
						$post_titles[ $post_id ] = get_the_title( $post_id );
					}
				}
			}

			return $post_titles;
		}

		/**
		 * Replace Array Index.
		 *
		 * @param array   $data Data.
		 * @param int     $index Index of item in array.
		 * @param int     $new_index New index that should replace current index.
		 * @param boolean $replace_value_instead Boolean to rather replace value instead of the index.
		 * @since 5.14.0
		 */
		public static function replace_array_index( $data, $index, $new_index, $replace_value_instead = false ) {

			if ( ! is_array( $data ) ) {
				return $data;
			}

			if ( $replace_value_instead ) {
				foreach ( $data as $id => $value ) {
					if ( strval( $index ) === strval( $value ) ) {
						$data[ $id ] = $new_index;
					}
				}
				return $data;
			}

			if ( isset( $data[ $index ] ) ) {
				$value = $data[ $index ];
				unset( $data[ $index ] );
				$data[ $new_index ] = $value;
			}

			return $data;
		}
	}
}

/**
 * Returns a single instance of the class.
 *
 * @since 5.14.0
 * @return object
 */
function bkap_import_export_bookable_products() {
	return BKAP_Import_Export_Bookable_Products::init();
}

bkap_import_export_bookable_products();
