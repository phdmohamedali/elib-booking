<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for displaying Settings page for Zapier.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api/Zapier
 * @category    Classes
 * @since       5.11.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_API_Zapier_Settings' ) ) {

	/**
	 * Display Settings page on the WordPress Admin Page.
	 *
	 * @since 5.11.0
	 */
	class BKAP_API_Zapier_Settings {

		/**
		 * Key name for saving Zapier API Settings to the database.
		 *
		 * @var string
		 */
		public static $settings_key = 'bkap_api_zapier_settings';

		/**
		 * Key name for saving Zapier API Subscriptions to the database.
		 *
		 * @var string
		 */
		public static $subscription_key = 'bkap_api_zapier_subscription';

		/**
		 * Key name for saving Zapier API Product Settings to the database.
		 *
		 * @var string
		 */
		public static $product_settings_key = '_bkap_zapier';

		/**
		 * Construct
		 *
		 * @since 5.11.0
		 */
		public function __construct() {
			add_action( 'bkap_integration_links', array( &$this, 'bkap_api_zapier_settings_link' ), 10, 1 );
			add_action( 'bkap_global_integration_settings', array( &$this, 'bkap_api_zapier_settings_api' ), 10, 1 );

			if ( ! BKAP_License::enterprise_license() ) {
				return;
			}

			add_action( 'admin_init', array( &$this, 'bkap_api_zapier_settings' ) );
			add_action( 'bkap_register_setting', array( &$this, 'bkap_api_zapier_register_settings' ), 10 );
			add_action( 'bkap_after_zoom_meeting_settings_product', array( &$this, 'bkap_api_zapier_product_settings' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( &$this, 'bkap_api_zapier_enqueue_styles' ), 10, 1 );
			add_action( 'admin_enqueue_scripts', array( &$this, 'bkap_api_zapier_enqueue_scripts' ), 10, 1 );
			add_filter( 'bkap_product_integration_data', array( &$this, 'bkap_api_zapier_integration_data' ), 10, 3 );
			add_filter( 'bkap_update_serialized_post_meta_integration_data', array( &$this, 'bkap_api_zapier_serialized_data' ), 10, 2 );
			add_action( 'bkap_update_booking_post_meta', array( &$this, 'bkap_api_zapier_create_booking_trigger' ), 10, 2 );
			add_action( 'bkap_requires_confirmation_after_save_booking_status', array( &$this, 'bkap_api_zapier_create_booking_trigger' ), 10, 2 );
			add_action( 'bkap_after_update_booking_post', array( &$this, 'bkap_api_zapier_update_booking_trigger' ), 10, 2 );
			add_action( 'bkap_before_delete_booking_post', array( &$this, 'bkap_api_zapier_delete_booking_trigger' ), 10, 2 );
		}

		/**
		 * Adds Zapier Integration Link on the BKAP Integration Page.
		 *
		 * @param string $section Section.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_settings_link( $section ) {

			$class = ( 'zapier' === $section ) ? 'current' : '';
			?>
			<li>
			| <a href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=zapier" class="<?php echo esc_attr( $class ); ?>"><?php esc_html_e( 'Zapier Integration', 'woocommerce-booking' ); ?></a>
			</li>
			<?php
		}

		/**
		 * Adds Zapier Settings to the WP Settings API.
		 *
		 * @param string $section Section.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_settings_api( $section ) {

			if ( 'zapier' === $section ) {

				if ( ! BKAP_License::enterprise_license() ) {
					?>
					<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::enterprise_license_error_message(); // phpcs:ignore; ?></div>
					<?php
					return;
				}
				?>
					<div class="bkap_api_zapier_content">
						<form method="post" action="options.php">
				<?php
				settings_errors();
				settings_fields( 'bkap_zapier_settings' );
				?>
						<h2 class="header">General Settings</h2>
						<p>
						<?php
						/* translators: %s a link anchor tags */
						echo wp_kses_post( sprintf( __( 'Zapier is a service which you can use to create, update or delete bookings outside WooCommerce and integrate with other Zapier apps. If you do not have a Zapier account, you may %1$ssign up for one here%2$s.', 'woocommerce-booking' ), "<a href='https://zapier.com/app/signup' target='_blank'>", '</a>' ) );
						?>
						</p>
				<?php
				do_settings_sections( 'bkap_zapier_settings_page' );
				do_settings_sections( 'bkap_zapier_triggers_page' );
				do_settings_sections( 'bkap_zapier_actions_page' );
				submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save' );
				?>
						</form>
					</div>
				<?php

				if ( 'on' === self::bkap_api_zapier_get_settings( 'bkap_api_zapier_log_enable' ) ) {
					?>
						<h2 id="bkap_api_zapier_event_log">Event Log</h2>
						<p>Event Log page. Failed events ( error related ) are coloured in red.</p>
					<?php
					$table = new BKAP_API_Zapier_Log();
					$table->display_log_table();
				}
			}
		}

		/**
		 * Adds the Zapier Settings Section and Fields.
		 *
		 * @param string $section Section.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_settings( $section ) {

			if ( isset( $_GET['action'] ) && 'calendar_sync_settings' === $_GET['action'] && isset( $_GET['section'] ) && 'zapier' === $_GET['section'] ) { // phpcs:ignore

				add_settings_section(
					'bkap_api_zapier_general_settings_section',
					'',
					array( &$this, 'bkap_api_zapier_general_settings_instructions' ),
					'bkap_zapier_settings_page'
				);

				add_settings_field(
					'bkap_api_zapier_integration_activate',
					__( 'Zapier Integration', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_integration_callback' ),
					'bkap_zapier_settings_page',
					'bkap_api_zapier_general_settings_section',
					array( __( 'Activate', 'woocommerce-booking' ) )
				);

				add_settings_field(
					'bkap_api_zapier_log_enable',
					__( 'Log', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_log_enable_callback' ),
					'bkap_zapier_settings_page',
					'bkap_api_zapier_general_settings_section',
					array( __( 'Activate', 'woocommerce-booking' ) )
				);

				add_settings_section(
					'bkap_api_zapier_zapier_triggers_section',
					__( 'Zapier Triggers', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_zapier_triggers_instructions' ),
					'bkap_zapier_triggers_page'
				);

				add_settings_field(
					'bkap_api_zapier_triggers_activate',
					__( 'Triggers', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_triggers_activate_callback' ),
					'bkap_zapier_triggers_page',
					'bkap_api_zapier_zapier_triggers_section'
				);

				add_settings_section(
					'bkap_api_zapier_zapier_actions_section',
					__( 'Zapier Actions', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_zapier_actions_instructions' ),
					'bkap_zapier_actions_page'
				);

				add_settings_field(
					'bkap_api_zapier_actions_activate',
					__( 'Actions', 'woocommerce-booking' ),
					array( &$this, 'bkap_api_zapier_actions_activate_callback' ),
					'bkap_zapier_actions_page',
					'bkap_api_zapier_zapier_actions_section'
				);
			}
		}

		/**
		 * Registers Zapier Settings.
		 *
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_register_settings() {

			register_setting(
				'bkap_zapier_settings',
				self::$settings_key,
				array( &$this, 'bkap_api_zapier_prepare_records_for_saving_to_db' )
			);
		}

		/**
		 * Retrieve Zapier Settings from the database.
		 *
		 * @param string $option Property of Zapier Setting object that is stored in the database.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_settings( $option = '' ) {
			$settings = get_option( self::$settings_key );

			if ( '' !== $settings ) {
				$settings = json_decode( $settings );
			}

			return ( '' === $option ) ? $settings : ( ( '' !== $settings && isset( $settings->$option ) &&
			'' !== $settings->$option ) ? $settings->$option : '' );
		}

		/**
		 * Retrieve Zapier Product Settings from the database.
		 *
		 * @param string $product_id Product ID of Product where settings would be retrieved from.
		 * @param string $option Property of Zapier Product Setting object that is stored in the database.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_product_settings( $product_id, $option = '' ) {
			$settings = get_post_meta( $product_id, self::$product_settings_key, true );

			return ( '' === $option ) ? $settings : ( ( '' !== $settings && isset( $settings[ $option ] ) &&
			'' !== $settings[ $option ] ) ? $settings[ $option ] : array() );
		}

		/**
		 * Get Zapier Product Setting for a Trigger.
		 *
		 * @param int    $product_id Product ID.
		 * @param string $trigger Zapier Trigger.
		 * @param string $parameter Parameter that should be fetched and returned.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_product_trigger_setting( $product_id, $trigger, $parameter ) {

			$trigger_data = '';
			$setting      = self::bkap_api_zapier_get_product_settings( $product_id, $trigger );

			if ( isset( $setting[ $parameter ] ) && '' !== $setting[ $parameter ] ) {
				$trigger_data = $setting[ $parameter ];
			}

			return $trigger_data;
		}

		/**
		 * Get Trigger Hooks from Zapier.
		 *
		 * @param int    $user_id Product ID.
		 * @param string $type Zapier Trigger Type.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_trigger_hooks( $user_id, $type ) {

			$hooks = (array) self::bkap_api_zapier_get_subscriptions( $type );

			if ( '' !== $user_id && is_array( $hooks ) ) {
				foreach ( $hooks as $key => $hook ) {

					// Remove hooks not created/meant for the current user.
					if ( is_string( $hook ) ) {
						unset( $hooks[ $key ] );
						continue;
					}

					if ( (int) $user_id !== (int) $hook->created_by ) {
						unset( $hooks[ $key ] );
					}
				}
			} else {
				$hooks = array();
			}

			return $hooks;
		}

		/**
		 * Retrieve Zapier Subscriptions from the database.
		 *
		 * @param string $option Property of Zapier Subscriptions object that is stored in the database.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_subscriptions( $option = '' ) {
			$subscriptions = get_option( self::$subscription_key );

			if ( '' !== $subscriptions ) {
				$subscriptions = json_decode( $subscriptions );
			}

			return ( '' === $option ) ? $subscriptions : ( ( '' !== $subscriptions && isset( $subscriptions->$option ) &&
			'' !== $subscriptions->$option ) ? $subscriptions->$option : '' );
		}

		/**
		 * Checks if Zapier API has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_zapier_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'bkap_api_zapier_integration' );
		}

		/**
		 * Checks if Logging has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_logging_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'bkap_api_zapier_log_enable' );
		}


		/**
		 * Checks if Create Booking Trigger has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_create_booking_trigger_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'trigger_create_booking' );
		}

		/**
		 * Checks if Create Booking Trigger for a Product has been enabled.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_create_booking_trigger_enabled_for_product( $product_id ) {
			return 'on' === self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_create_booking', 'status' );
		}

		/**
		 * Gets Hook for Create Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_create_booking_trigger_product_hook( $product_id ) {
			return self::bkap_api_zapier_get_zapier_hook_url( 'booking_create', 'trigger_create_booking', $product_id );
		}

		/**
		 * Gets Label for Create Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.14.0
		 */
		public static function bkap_api_zapier_get_create_booking_trigger_product_label( $product_id ) {
			return self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_create_booking', 'label' );
		}

		/**
		 * Gets Hooks for Create Booking Triggers on Zapier.
		 *
		 * @param string $user_id User ID to return hooks asiigned/created by a User.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_create_booking_trigger_hooks( $user_id = '' ) {
			return self::bkap_api_zapier_get_trigger_hooks( $user_id, 'booking_create' );
		}

		/**
		 * Checks if Update Booking Trigger has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_update_booking_trigger_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'trigger_update_booking' );
		}

		/**
		 * Checks if Update Booking Trigger for a Product has been enabled.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_update_booking_trigger_enabled_for_product( $product_id ) {
			return 'on' === self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_update_booking', 'status' );
		}

		/**
		 * Gets Hook for Update Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_update_booking_trigger_product_hook( $product_id ) {
			return self::bkap_api_zapier_get_zapier_hook_url( 'booking_update', 'trigger_update_booking', $product_id );
		}

		/**
		 * Gets Label for Update Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_update_booking_trigger_product_label( $product_id ) {
			return self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_update_booking', 'label' );
		}

		/**
		 * Gets Hooks for Update Booking Triggers on Zapier.
		 *
		 * @param string $user_id User ID to return hooks asiigned/created by a User.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_update_booking_trigger_hooks( $user_id = '' ) {
			return self::bkap_api_zapier_get_trigger_hooks( $user_id, 'booking_update' );
		}

		/**
		 * Checks if Delete Booking Trigger has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_delete_booking_trigger_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'trigger_delete_booking' );
		}

		/**
		 * Checks if Delete Booking Trigger for a Product has been enabled.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_delete_booking_trigger_enabled_for_product( $product_id ) {
			return 'on' === self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_delete_booking', 'status' );
		}

		/**
		 * Gets Hook for Delete Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_delete_booking_trigger_product_hook( $product_id ) {
			return self::bkap_api_zapier_get_zapier_hook_url( 'booking_delete', 'trigger_delete_booking', $product_id );
		}

		/**
		 * Gets Label for Delete Booking Trigger for a Product.
		 *
		 * @param int $product_id Product ID.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_delete_booking_trigger_product_label( $product_id ) {
			return self::bkap_api_zapier_get_product_trigger_setting( $product_id, 'trigger_delete_booking', 'label' );
		}

		/**
		 * Gets Hooks for Delete Booking Triggers on Zapier.
		 *
		 * @param string $user_id User ID to return hooks asiigned/created by a User.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_get_delete_booking_trigger_hooks( $user_id = '' ) {
			return self::bkap_api_zapier_get_trigger_hooks( $user_id, 'booking_delete' );
		}

		/**
		 * Checks if Create Booking Action has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_create_booking_action_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'action_create_booking' );
		}

		/**
		 * Checks if Update Booking Action has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_update_booking_action_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'action_update_booking' );
		}

		/**
		 * Checks if Create Booking Action has been enabled.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_is_delete_booking_action_enabled() {
			return 'on' === self::bkap_api_zapier_get_settings( 'action_delete_booking' );
		}

		/**
		 * Callback for displaying instructions on General Settings section.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_general_settings_instructions() {}

		/**
		 * Callback for displaying instructions on Zapier Triggers section.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_zapier_triggers_instructions() {
			?>
				<p><?php esc_html_e( 'Zapier Triggers kickstart an event that starts a Zap, i.e Zapier -> WooCommerce. The options below control whether the various Triggers which are sent to Zapier are activated or not.', 'woocommerce-booking' ); ?></p>
			<?php
		}

		/**
		 * Callback for displaying instructions on Zapier Actions section.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_zapier_actions_instructions() {
			?>
				<p><?php esc_html_e( 'Zapier Actions creates, updates or deletes data in the WooCommerce Store from Zapier. For instance, once an update is received from any of the Zapier Apps, a Booking can be created, edited or updated here in this WooCommerce Store. ', 'woocommerce-booking' ); ?></p>
			<?php
		}

		/**
		 * Callback for displaying instructions on General Settings section.
		 *
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_event_log_instructions() {
			?>
				<p>Manage Zapier logs and monitor the events taking place on your WooCommerce Store.</p>
			<?php
		}

		/**
		 * Save the Zapier API Setting record to the database.
		 *
		 * @param string $option_key Option key for saving record to the database.
		 * @param string $key Identifier for the record.
		 * @param string $record Record to be saved to the database.
		 * @param bool   $overwrite Whether to overwrite existing data or append.
		 * @return bool True if save operation is successful.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_save_records_to_db( $option_key, $key, $record, $overwrite = true ) {

			$settings = array();

			if ( '_all' === $key ) {
				$settings = $record;
			} else {

				if ( ! $overwrite ) {
					$_settings = array();
					$settings  = get_option( $option_key );

					if ( '' !== $settings ) {

						$settings = json_decode( $settings, true );

						if ( is_array( $settings ) ) {

							if ( isset( $settings[ $key ] ) && '' !== $settings[ $key ] ) {

								if ( is_array( $settings[ $key ] ) ) {

									if ( $option_key === self::$subscription_key ) {

										// Remove all former values of hook action and label, thereby to allow for unique value.
										$__settings = $settings[ $key ];
										foreach ( $__settings as $index => $setting ) {

											if ( isset( $setting['action'] ) && isset( $record['action'] ) && isset( $record['label'] ) ) {
												if ( $setting['label'] === $record['label'] && $setting['action'] === $record['action'] ) {
													unset( $__settings[ $index ] );
												}
											} elseif ( ! isset( $setting['action'] ) && isset( $record['action'] ) && isset( $record['label'] ) ) {
												if ( $setting['label'] === $record['label'] ) {
													unset( $__settings[ $index ] );
												}
											}
										}

										$settings[ $key ] = $__settings;
									}

									$settings[ $key ][] = $record;
								} else {
									$_settings[ $key ][] = $settings[ $key ];
									$_settings[ $key ][] = $record;
									$settings            = $_settings;
								}
							} else {
								$settings[ $key ][] = $record;
							}
						} else {
							$_settings[]         = $settings;
							$_settings[ $key ][] = $record;
							$settings            = $_settings;
						}
					} else {
						$settings           = array();
						$settings[ $key ][] = $record;
					}
				} else {
					$settings         = array();
					$settings[ $key ] = $record;
				}
			}

			return update_option( $option_key, wp_json_encode( $settings ) );
		}

		/**
		 * Prepares the Zapier API Setting record that is to be saved to the database.
		 *
		 * @param array $input Zapier API Settings.
		 * @return string Zapier API Settings.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_prepare_records_for_saving_to_db( $input ) {

			// Create Database Table for Zapier Log if it does not exist.
			if ( isset( $input ) && isset( $input['bkap_api_zapier_log_enable'] ) && 'on' === $input['bkap_api_zapier_log_enable'] ) {
				BKAP_API_Zapier_Log::maybe_create_log_table();
			}

			return is_array( $input ) ? wp_json_encode( $input ) : $input;
		}

		/**
		 * Callback for displaying settings option for Zapier Integration.
		 *
		 * @param array $args Arguments.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_integration_callback( $args ) {
			$bkap_api_zapier_integration = self::bkap_api_zapier_get_settings( 'bkap_api_zapier_integration' );
			$checked                     = ( 'on' === $bkap_api_zapier_integration ) ? 'checked' : '';
			?>
				<input id="bkap_api_zapier_integration" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[bkap_api_zapier_integration]" value="on" <?php echo esc_attr( $checked ); ?> />
				<label for="bkap_api_zapier_integration"> <?php echo esc_html( $args[0] ); ?> </label>
				<p class="description"><?php esc_html_e( 'Activates Zapier Integration on this WooCommerce Store for Zapier requests.', 'woocommerce-booking' ); ?> <em><?php esc_html_e( 'Please enable Zapier triggers and actions to allow access to Zapier zaps.', 'woocommerce-booking' ); ?></em></p>
			<?php
		}

		/**
		 * Callback for displaying settings option for Zapier Triggers.
		 *
		 * @param array $args Arguments.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_triggers_activate_callback( $args ) {
			$_trigger_create_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'trigger_create_booking' ) ) ? 'checked' : '';
			$_trigger_update_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'trigger_update_booking' ) ) ? 'checked' : '';
			$_trigger_delete_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'trigger_delete_booking' ) ) ? 'checked' : '';
			?>
				<input id="trigger_create_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[trigger_create_booking]" value="on" <?php echo esc_attr( $_trigger_create_booking ); ?> />
				<label for="trigger_create_booking"> <?php esc_html_e( 'Create Booking Trigger', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This ensures Bookings which are created on this WooCommerce Store are sent to Zapier for onward trigger of other apps in the Zapier workspace.', 'woocommerce-booking' ); ?></p>
				<div class="bkap_api_zapier_divider"></div>

				<input id="trigger_update_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[trigger_update_booking]" value="on" <?php echo esc_attr( $_trigger_update_booking ); ?> />
				<label for="trigger_update_booking"> <?php esc_html_e( 'Update Booking Trigger', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This ensures Bookings which are updated on this WooCommerce Store are sent to Zapier for onward trigger of other apps in the Zapier workspace.', 'woocommerce-booking' ); ?></p>
				<div class="bkap_api_zapier_divider"></div>

				<input id="trigger_delete_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[trigger_delete_booking]" value="on" <?php echo esc_attr( $_trigger_delete_booking ); ?> />
				<label for="trigger_delete_booking"> <?php esc_html_e( 'Delete Booking Trigger', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This ensures Bookings which are deleted on this WooCommerce Store are sent to Zapier for onward trigger of other apps in the Zapier workspace.', 'woocommerce-booking' ); ?></p>
			<?php
		}

		/**
		 * Callback for displaying settings option for Zapier Actions.
		 *
		 * @param array $args Arguments.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_actions_activate_callback( $args ) {
			$_action_create_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'action_create_booking' ) ) ? 'checked' : '';
			$_action_update_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'action_update_booking' ) ) ? 'checked' : '';
			$_action_delete_booking = ( 'on' === self::bkap_api_zapier_get_settings( 'action_delete_booking' ) ) ? 'checked' : '';
			?>
				<input id="action_create_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[action_create_booking]" value="on" <?php echo esc_attr( $_action_create_booking ); ?> />
				<label for="action_create_booking"> <?php esc_html_e( 'Create Booking Action', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This enables Zapier Apps to create Bookings on this WooCommerce Store.', 'woocommerce-booking' ); ?></p>
				<div class="bkap_api_zapier_divider"></div>

				<input id="action_update_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[action_update_booking]" value="on" <?php echo esc_attr( $_action_update_booking ); ?> />
				<label for="action_update_booking"> <?php esc_html_e( 'Update Booking Action', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This enables Zapier Apps to update Bookings on this WooCommerce Store.', 'woocommerce-booking' ); ?></p>
				<div class="bkap_api_zapier_divider"></div>

				<input id="action_delete_booking" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[action_delete_booking]" value="on" <?php echo esc_attr( $_action_delete_booking ); ?> />
				<label for="action_delete_booking"> <?php esc_html_e( 'Delete Booking Action', 'woocommerce-booking' ); ?> </label>
				<p class="description"><?php esc_html_e( 'This enables Zapier Apps to delete Bookings on this WooCommerce Store.', 'woocommerce-booking' ); ?></p>
			<?php
		}

		/**
		 * Callback for displaying settings option for enabling Zapier Log.
		 *
		 * @param array $args Arguments.
		 * @since 5.11.0
		 */
		public static function bkap_api_zapier_log_enable_callback( $args ) {
			$bkap_api_zapier_log_enable = self::bkap_api_zapier_get_settings( 'bkap_api_zapier_log_enable' );
			$checked                    = ( 'on' === $bkap_api_zapier_log_enable ) ? 'checked' : '';
			?>
				<input id="bkap_api_zapier_log_enable" type="checkbox" name="<?php echo esc_attr( self::$settings_key ); ?>[bkap_api_zapier_log_enable]" value="on" <?php echo esc_attr( $checked ); ?> />
				<label for="bkap_api_zapier_log_enable"> <?php echo esc_html( $args[0] ); ?> </label>
				<p class="description"><?php esc_html_e( 'Logs all Zapier Requests such as Triggers, Actions and Subscriptions.', 'woocommerce-booking' ); ?></p>
				<div class="bkap_api_zapier_divider"></div>
			<?php
		}

		/**
		 * Returns WP_Error in the format Zapier can understand.
		 *
		 * @since 5.11.0
		 * @param WP_Error $error WP_Error class.
		 */
		public static function bkap_api_zapier_error( $error ) {
			$error_message = $error->get_error_message();
			status_header( 400 );
			die( $error_message ); // phpcs:ignore
		}

		/**
		 * Adds the Zapier Settings to the Booking Product Settings Metabox.
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Booking Settings.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_product_settings( $product_id, $booking_settings ) {

			if ( ! BKAP_License::enterprise_license() ) {
				return;
			}

			$checked_value_create_booking_trigger = ( self::bkap_api_zapier_is_create_booking_trigger_enabled_for_product( $product_id ) ) ? 'checked' : '';
			$checked_value_update_booking_trigger = ( self::bkap_api_zapier_is_update_booking_trigger_enabled_for_product( $product_id ) ) ? 'checked' : '';
			$checked_value_delete_booking_trigger = ( self::bkap_api_zapier_is_delete_booking_trigger_enabled_for_product( $product_id ) ) ? 'checked' : '';

			$display_value_create_booking_trigger = ( ! self::bkap_api_zapier_is_create_booking_trigger_enabled_for_product( $product_id ) || ! self::bkap_api_zapier_is_create_booking_trigger_enabled() ) ? ' class="bkap-zapier-hide"' : '';
			$display_value_update_booking_trigger = ( ! self::bkap_api_zapier_is_update_booking_trigger_enabled_for_product( $product_id ) || ! self::bkap_api_zapier_is_update_booking_trigger_enabled() ) ? ' class="bkap-zapier-hide"' : '';
			$display_value_delete_booking_trigger = ( ! self::bkap_api_zapier_is_delete_booking_trigger_enabled_for_product( $product_id ) || ! self::bkap_api_zapier_is_delete_booking_trigger_enabled() ) ? ' class="bkap-zapier-hide"' : '';

			$value_for_disabled_trigger = sprintf(
				/* translators: %1$s openeing tag, %2$s closing tag */
				__( '%1$s This Trigger is disabled. Please visit the Booking Settings page to enable it. %2$s', 'woocommerce-booking' ),
				'<span>',
				'</span'
			);

			$disabled_value_create_booking_trigger = ( ! self::bkap_api_zapier_is_create_booking_trigger_enabled() ) ? $value_for_disabled_trigger : '';
			$disabled_value_update_booking_trigger = ( ! self::bkap_api_zapier_is_update_booking_trigger_enabled() ) ? $value_for_disabled_trigger : '';
			$disabled_value_delete_booking_trigger = ( ! self::bkap_api_zapier_is_delete_booking_trigger_enabled() ) ? $value_for_disabled_trigger : '';

			$help_tip_url = plugins_url() . '/woocommerce/assets/images/help.png';
			?>
			<!-- Zapier Integration -->
			<button type="button" class="bkap-integrations-accordion bkap_integration_zapier_button"><b><?php esc_html_e( 'Zapier', 'woocommerce-booking' ); ?></b></button>
			<div class="bkap_google_sync_settings_content bkap_integrations_panel">
				<table class='form-table bkap-form-table bkap-zapier-integration'>
					<!-- Create Booking Trigger -->
					<tr>
						<td>
							<?php esc_html_e( 'Enable Create Booking Trigger', 'woocommerce-booking' ); ?>
							<?php echo $disabled_value_create_booking_trigger; // phpcs:ignore ?>
						</td>
						<td>
							<?php
							if ( self::bkap_api_zapier_is_create_booking_trigger_enabled() ) {
								?>
								<label class="bkap_switch">
									<input id="bkap_zapier_integration_create_booking_trigger_enable" name= "bkap_zapier_integration_create_booking_trigger_enable" type="checkbox" <?php echo esc_attr( $checked_value_create_booking_trigger ); ?> />
								<div class="bkap_slider round"></div>
								<?php
							}
							?>
						</td>
						<td>
							<?php
							if ( self::bkap_api_zapier_is_create_booking_trigger_enabled() ) {
								?>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable Create Booking Trigger so that newly created bookings can be sent to Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
								<?php
							}
							?>
						</td>
					</tr>

					<tr <?php echo $display_value_create_booking_trigger; // phpcs:ignore ?>>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_create_booking_trigger_enabled() ) {
							?>
							<label for="bkap_zapier_integration_create_booking_trigger"><?php esc_html_e( 'Select created Triggers', 'woocommerce-booking' ); ?></label>
							<?php
						}
						?>
						</td>
						<td>
							<?php
							$hooks = self::bkap_api_zapier_get_create_booking_trigger_hooks( get_current_user_id() );
							if ( self::bkap_api_zapier_is_create_booking_trigger_enabled() && count( $hooks ) > 0 ) {
								?>
								<label class="bkap-zapier-select">
									<select name="bkap_zapier_integration_create_booking_trigger_hook" id="bkap_zapier_integration_create_booking_trigger_hook">
										<option><?php esc_html_e( 'Select Trigger', 'woocommerce-booking' ); ?></option>
										<?php
										foreach ( $hooks as $hook ) {
											$selected_value = ( self::bkap_api_zapier_get_create_booking_trigger_product_label( $product_id ) === $hook->label ) ? ' selected' : '';
											?>
											<option value="<?php echo esc_attr( $hook->id ); ?>"<?php echo esc_attr( $selected_value ); ?> data-zapier-hook-label="<?php echo esc_attr( $hook->label ); ?>"><?php echo esc_attr( $hook->label ); ?></option>
											<?php
										}
										?>
									</select>
								</label>
								<?php
							} elseif ( self::bkap_api_zapier_is_create_booking_trigger_enabled() ) {
								?>
									<p class="bkap-zapier-no-trigger-found-label"><?php esc_attr_e( 'No Create Booking Triggers found!', 'woocommerce-booking' ); ?></p>
								<?php
							}
							?>
						</td>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_create_booking_trigger_enabled() ) {
							?>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Zapier Trigger Hook - Select Label used when creating a Trigger on Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
							<?php
						}
						?>
						</td>
					</tr>
				</table>
				<hr />
				<br>

				<table class='form-table bkap-form-table bkap-zapier-integration'>
					<!-- Update Booking Trigger -->
					<tr>
						<td>
							<?php esc_html_e( 'Enable Update Booking Trigger', 'woocommerce-booking' ); ?>
							<?php echo $disabled_value_update_booking_trigger; // phpcs:ignore ?>
						</td>
						<td>
							<?php
							if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
								?>
								<label class="bkap_switch">
									<input id="bkap_zapier_integration_update_booking_trigger_enable" name= "bkap_zapier_integration_update_booking_trigger_enable" type="checkbox" <?php echo esc_attr( $checked_value_update_booking_trigger ); ?> />
								<div class="bkap_slider round"></div>
								<?php
							}
							?>
						</td>
						<td>
							<?php
							if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
								?>
								<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable Update Booking Trigger so that updated bookings can be sent to Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
								<?php
							}
							?>
						</td>
					</tr>

					<tr <?php echo $display_value_update_booking_trigger; // phpcs:ignore ?>>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
							?>
							<label for="bkap_zapier_integration_update_booking_trigger"><?php esc_html_e( 'Select created Triggers', 'woocommerce-booking' ); ?></label>
							<?php
						}
						?>
						</td>
						<td>
							<?php
							$hooks = self::bkap_api_zapier_get_update_booking_trigger_hooks( get_current_user_id() );
							if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() && count( $hooks ) > 0 ) {
								?>
								<label class="bkap-zapier-select">
									<select name="bkap_zapier_integration_update_booking_trigger_hook" id="bkap_zapier_integration_update_booking_trigger_hook">
										<option><?php esc_html_e( 'Select Trigger', 'woocommerce-booking' ); ?></option>
										<?php
										foreach ( $hooks as $hook ) {
											$selected_value = ( self::bkap_api_zapier_get_update_booking_trigger_product_label( $product_id ) === $hook->label ) ? ' selected' : '';
											?>
											<option value="<?php echo esc_attr( $hook->id ); ?>"<?php echo esc_attr( $selected_value ); ?> data-zapier-hook-label="<?php echo esc_attr( $hook->label ); ?>"><?php echo esc_attr( $hook->label ); ?></option>
											<?php
										}
										?>
									</select>
								</label>
								<?php
							} elseif ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
								?>
									<p class="bkap-zapier-no-trigger-found-label"><?php esc_attr_e( 'No Update Booking Triggers found', 'woocommerce-booking' ); ?></p>
								<?php
							}
							?>
						</td>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
							?>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Zapier Trigger Hook - Select Label used when creating a Trigger on Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
							<?php
						}
						?>
						</td>
					</tr>
				</table>
				<hr />
				<br>

				<table class='form-table bkap-form-table bkap-zapier-integration'>
					<!-- Delete Booking Trigger -->
					<tr>
						<td>
							<?php esc_html_e( 'Enable Delete Booking Trigger', 'woocommerce-booking' ); ?>
							<?php echo $disabled_value_delete_booking_trigger; // phpcs:ignore ?>
						</td>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_delete_booking_trigger_enabled() ) {
							?>
							<label class="bkap_switch">
								<input id="bkap_zapier_integration_delete_booking_trigger_enable" name= "bkap_zapier_integration_delete_booking_trigger_enable" type="checkbox" <?php echo esc_attr( $checked_value_delete_booking_trigger ); ?> />
							<div class="bkap_slider round"></div>
							<?php
						}
						?>
						</td>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
							?>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable Delete Booking Trigger so that deleted bookings can be sent to Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
							<?php
						}
						?>
						</td>
					</tr>

					<tr <?php echo $display_value_delete_booking_trigger; // phpcs:ignore ?>>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_delete_booking_trigger_enabled() ) {
							?>
							<label for="bkap_zapier_integration_delete_booking_trigger"><?php esc_html_e( 'Select created Triggers', 'woocommerce-booking' ); ?></label>
							<?php
						}
						?>
						</td>
						<td>
							<?php
							$hooks = self::bkap_api_zapier_get_delete_booking_trigger_hooks( get_current_user_id() );
							if ( self::bkap_api_zapier_is_delete_booking_trigger_enabled() && count( $hooks ) > 0 ) {
								?>
								<label class="bkap-zapier-select">
									<select name="bkap_zapier_integration_delete_booking_trigger_hook" id="bkap_zapier_integration_delete_booking_trigger_hook">
										<option><?php esc_html_e( 'Select Trigger', 'woocommerce-booking' ); ?></option>
										<?php
										foreach ( $hooks as $hook ) {
											$selected_value = ( self::bkap_api_zapier_get_delete_booking_trigger_product_label( $product_id ) === $hook->label ) ? ' selected' : '';
											?>
											<option value="<?php echo esc_attr( $hook->id ); ?>"<?php echo esc_attr( $selected_value ); ?> data-zapier-hook-label="<?php echo esc_attr( $hook->label ); ?>"><?php echo esc_attr( $hook->label ); ?></option>
											<?php
										}
										?>
									</select>
								</label>
								<?php
							} elseif ( self::bkap_api_zapier_is_delete_booking_trigger_enabled() ) {
								?>
									<p class="bkap-zapier-no-trigger-found-label"><?php esc_attr_e( 'No Delete Booking Triggers found', 'woocommerce-booking' ); ?></p>
								<?php
							}
							?>
						</td>
						<td>
						<?php
						if ( self::bkap_api_zapier_is_update_booking_trigger_enabled() ) {
							?>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Zapier Trigger Hook - Select Label used when creating a Trigger on Zapier.', 'woocommerce-booking' ); ?>" src="<?php echo esc_url( $help_tip_url ); ?>" />
							<?php
						}
						?>
						</td>
					</tr>
				</table>
				<br>
			</div>
			<?php
		}

		/**
		 * Register the Zapier API stylesheet.
		 *
		 * @param string $version Plugin version number.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_enqueue_styles( $version ) {

			wp_enqueue_style(
				'bkap-zapier-integration',
				bkap_load_scripts_class::bkap_asset_url( '/assets/css/integrations/zapier/admin.css', BKAP_FILE ),
				array(),
				$version,
				false
			);
		}

		/**
		 * Register the Zapier API JavaScript file.
		 *
		 * @param string $version Plugin version number.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_enqueue_scripts( $version ) {

			wp_enqueue_script(
				'bkap-zapier-integration',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/integrations/zapier/admin.js', BKAP_FILE ),
				array( 'jquery' ),
				$version,
				false
			);

		}

		/**
		 * Adds Zapier API data to the integration data so that it can be saved.
		 *
		 * @param array $booking_data Booking Data.
		 * @param int   $product_id Product ID.
		 * @param obj   $integration_data Data returned from bkap_additional_integration_data JS function.
		 * @since 5.11.0
		 * @since Updated 5.14.0
		 */
		public function bkap_api_zapier_integration_data( $booking_data, $product_id, $integration_data ) {

			$key = self::$product_settings_key;

			$booking_data[ $key ]['trigger_create_booking'] = array(
				'status' => ( isset( $integration_data->bkap_zapier_create_booking_trigger ) && '' !== $integration_data->bkap_zapier_create_booking_trigger ) ? 'on' : 'off',
				'label'  => ( isset( $integration_data->bkap_zapier_create_booking_trigger ) && '' !== $integration_data->bkap_zapier_create_booking_trigger ) ? $integration_data->bkap_zapier_create_booking_trigger : '',
			);

			$booking_data[ $key ]['trigger_update_booking'] = array(
				'status' => ( isset( $integration_data->bkap_zapier_update_booking_trigger ) && '' !== $integration_data->bkap_zapier_update_booking_trigger ) ? 'on' : 'off',
				'label'  => ( isset( $integration_data->bkap_zapier_update_booking_trigger ) && '' !== $integration_data->bkap_zapier_update_booking_trigger ) ? $integration_data->bkap_zapier_update_booking_trigger : '',
			);

			$booking_data[ $key ]['trigger_delete_booking'] = array(
				'status' => ( isset( $integration_data->bkap_zapier_delete_booking_trigger ) && '' !== $integration_data->bkap_zapier_delete_booking_trigger ) ? 'on' : 'off',
				'label'  => ( isset( $integration_data->bkap_zapier_delete_booking_trigger ) && '' !== $integration_data->bkap_zapier_delete_booking_trigger ) ? $integration_data->bkap_zapier_delete_booking_trigger : '',
			);

			return $booking_data;
		}

		/**
		 * Adds Zapier API settings for serialization when Product Settings are saved.
		 *
		 * @param array $booking_settings Booking Settings.
		 * @param array $integration_data Booking Settings to be serialized.
		 * @since 5.11.0
		 */
		public function bkap_api_zapier_serialized_data( $booking_settings, $integration_data ) {

			$key     = self::$product_settings_key;
			$new_key = 'booking_' . str_replace( '_bkap_', '', self::$product_settings_key );

			$booking_settings[ $new_key ] = $integration_data[ $key ];

			return $booking_settings;
		}

		/**
		 * Triggers Zap when a booking triggger is executed.
		 *
		 * @since 5.11.0
		 * @param int    $booking_id Booking ID.
		 * @param string $trigger Trigger that has been executed for which this function should be called.
		 * @throws Exception If error encountered.
		 */
		public static function bkap_api_zapier_do_booking_trigger( $booking_id, $trigger ) {

			try {

				$booking_data = self::bkap_api_zapier_get_booking( $booking_id );
				$booking_id   = $booking_data['id'];
				$product_id   = $booking_data['product_id'];

				$is_booking_trigger_enabled             = false;
				$is_booking_trigger_enabled_for_product = false;
				$trigger_label                          = '';
				$hook                                   = '';

				switch ( $trigger ) {

					case 'create_booking_trigger':
						$trigger_label                          = 'Create Booking';
						$is_booking_trigger_enabled             = self::bkap_api_zapier_is_create_booking_trigger_enabled();
						$is_booking_trigger_enabled_for_product = self::bkap_api_zapier_is_create_booking_trigger_enabled_for_product( $product_id );
						$hook                                   = self::bkap_api_zapier_get_create_booking_trigger_product_hook( $product_id );
						break;

					case 'update_booking_trigger':
						$trigger_label                          = 'Update Booking';
						$is_booking_trigger_enabled             = self::bkap_api_zapier_is_update_booking_trigger_enabled();
						$is_booking_trigger_enabled_for_product = self::bkap_api_zapier_is_update_booking_trigger_enabled_for_product( $product_id );
						$hook                                   = self::bkap_api_zapier_get_update_booking_trigger_product_hook( $product_id );
						break;

					case 'delete_booking_trigger':
						$trigger_label                          = 'Delete Booking';
						$is_booking_trigger_enabled             = self::bkap_api_zapier_is_delete_booking_trigger_enabled();
						$is_booking_trigger_enabled_for_product = self::bkap_api_zapier_is_delete_booking_trigger_enabled_for_product( $product_id );
						$hook                                   = self::bkap_api_zapier_get_delete_booking_trigger_product_hook( $product_id );
						break;

					default:
						throw new Exception( __( 'Zapier API Trigger Request not understood.', 'woocommerce-booking' ), 400 );
				}

				if ( ! self::bkap_api_zapier_is_zapier_enabled() ) {
					throw new Exception( __( 'Zapier API is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ), 400 );
				}

				if ( ! $is_booking_trigger_enabled ) {
					throw new Exception(
						sprintf(
							/* translators: %s Trigger Label */
							__( 'Zapier API %s Trigger is disabled. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ),
							$trigger_label
						),
						400
					);
				}

				if ( ! $is_booking_trigger_enabled_for_product ) {
					throw new Exception(
						sprintf(
							/* translators: %d Booking ID */
							__( 'Zapier API %1$s Trigger is disabled for Product #%2$d. Please enable in WooCommerce Booking Settings.', 'woocommerce-booking' ),
							$trigger_label,
							$product_id
						),
						400
					);
				}

				if ( '' === $hook ) {
					throw new Exception(
						sprintf(
							/* translators: %s Trigger Label */
							__( 'Invalid or missing Zap Label for the Zapier API %s Trigger. Please check that the Zap is enabled and active on Zapier. Alternatively, you can switch the Zap off and back on.', 'woocommerce-booking' ),
							$trigger_label
						),
						400
					);
				}

				// Check if booking requires confirmation. If it does, do not proceed as booking status is not confirmed.
				if ( bkap_common::bkap_product_requires_confirmation( $product_id ) ) {

					$booking = new BKAP_Booking( $booking_id );

					if ( 'confirmed' !== $booking->get_status() ) {
						return;
					}
				}

				$response = wp_remote_post(
					$hook,
					array(
						'body' => wp_json_encode( $booking_data ),
					)
				);

				if ( $response ) {
					BKAP_API_Zapier_Log::add_log( "{$trigger_label} Trigger", "{$trigger_label} Trigger request for Booking #{$booking_id} has been successfully sent to Zapier", $booking_data );
				}
			} catch ( Exception $e ) {
				BKAP_API_Zapier_Log::add_log( "{$trigger_label} Trigger Error", $e->getMessage(), $booking_data );
			}
		}

		/**
		 * Create Booking Trigger request sent to Zapier.
		 *
		 * @since 5.11.0
		 * @param int   $booking_id Booking ID.
		 * @param array $data Booking data.
		 */
		public function bkap_api_zapier_create_booking_trigger( $booking_id, $data ) {

			$this->bkap_api_zapier_do_booking_trigger( $booking_id, 'create_booking_trigger' );
		}

		/**
		 * Update Booking Trigger request sent to Zapier.
		 *
		 * @since 5.11.0
		 * @param int   $booking_id Booking ID.
		 * @param array $data Booking data.
		 */
		public function bkap_api_zapier_update_booking_trigger( $booking_id, $data ) {

			$this->bkap_api_zapier_do_booking_trigger( $booking_id, 'update_booking_trigger' );
		}

		/**
		 * Delete Booking Trigger request sent to Zapier.
		 *
		 * @since 5.11.0
		 * @param int   $booking_id Booking ID.
		 * @param array $data Booking data.
		 */
		public function bkap_api_zapier_delete_booking_trigger( $booking_id, $data ) {

			$this->bkap_api_zapier_do_booking_trigger( $booking_id, 'delete_booking_trigger' );
		}

		/**
		 * Gets Booking data that will be returned to Zapier.
		 *
		 * @since 5.11.0
		 * @param int   $booking_id Booking ID.
		 * @param array $options Booking Options.
		 * @return array Booking data.
		 */
		public static function bkap_api_zapier_get_booking( $booking_id, $options = array() ) {

			// Check is request is for sample_data.
			$for_sample_data = false;
			if ( isset( $options['sample_data'] ) && true === $options['sample_data'] ) {
				$for_sample_data = true;
			} else {

				$booking = new BKAP_Booking( $booking_id );

				$booking_label = 'Booking #' . $booking->get_id();

				$product = wc_get_product( $booking->get_product_id() );
				if ( $product ) {
					$booking_label = $booking_label . ' for Product - ' . $product->get_name();
				}

				$customer       = $booking->get_customer();
				$customer_name  = $customer->name;
				$customer_email = $customer->email;

				$start_date = gmdate( 'Y-m-d', strtotime( strval( $booking->get_start() ) ) );
				$end_date   = gmdate( 'Y-m-d', strtotime( strval( $booking->get_end() ) ) );

				$amount = 0;
				$order  = wc_get_order( $booking->get_order_id() );
				if ( $order ) {
					$amount = $order->get_total();
				}
			}

			return array(
				'id'                     => ! $for_sample_data ? (int) $booking->get_id() : 0,
				'label'                  => ! $for_sample_data ? strval( $booking_label ) : 'Test Booking - This Booking data is for Testing Purposes',
				'order_id'               => ! $for_sample_data ? (int) $booking->get_order_id() : 0,
				'order_item_id'          => ! $for_sample_data ? (int) $booking->get_item_id() : 0,
				'booking_status'         => ! $for_sample_data ? strval( $booking->get_status() ) : 'test-booking',
				'customer_id'            => ! $for_sample_data ? (int) $booking->get_customer_id() : 0,
				'customer_name'          => ! $for_sample_data ? strval( $customer_name ) : 'James Macover',
				'customer_email'         => ! $for_sample_data ? strval( $customer_email ) : 'james@macover.com',
				'product_id'             => ! $for_sample_data ? (int) $booking->get_product_id() : 0,
				'start_date'             => ! $for_sample_data ? strval( $start_date ) : gmdate( 'Y-m-d ' ),
				'end_date'               => ! $for_sample_data ? strval( $end_date ) : gmdate( 'Y-m-d ' ),
				'start_time'             => ! $for_sample_data ? strval( $booking->get_start_time() ) : '09:00 AM',
				'end_time'               => ! $for_sample_data ? strval( $booking->get_end_time() ) : '10:00 AM',
				'resource_id'            => ! $for_sample_data ? (int) $booking->get_resource() : 0,
				'fixed_block'            => ! $for_sample_data ? strval( $booking->get_fixed_block() ) : '4-days',
				'unit_cost'              => ! $for_sample_data ? (float) $booking->get_cost() : 100,
				'quantity'               => ! $for_sample_data ? (int) $booking->get_quantity() : 5,
				'variation_id'           => ! $for_sample_data ? (int) $booking->get_variation_id() : 0,
				'resource_title'         => ! $for_sample_data ? strval( $booking->get_resource_title() ) : 'Number of Rooms',
				'duration'               => ! $for_sample_data ? strval( $booking->get_selected_duration() ) : '5 days',
				'duration_time'          => ! $for_sample_data ? strval( $booking->get_selected_duration_time() ) : '5',
				'client_timezone'        => ! $for_sample_data ? strval( $booking->get_timezone_name() ) : 'Asia/Kolkata',
				'client_timezone_offset' => ! $for_sample_data ? strval( $booking->get_timezone_offset() ) : '10',
				'zoom_meeting_url'       => ! $for_sample_data ? strval( $booking->get_zoom_meeting_link() ) : 'https://zoom.us/test-booking',
				'total_amount'           => ! $for_sample_data ? (float) $amount : 500,
			);
		}

		/**
		 * Gets the Zapier Hook URL.
		 *
		 * @since 5.14.0
		 * @param string $action Hook Action.
		 * @param string $hook Hook.
		 * @param int    $product_id Product ID.
		 * @param array  $data Data of information needed to retrieve Hook URL.
		 */
		public static function bkap_api_zapier_get_zapier_hook_url( $action, $hook, $product_id ) {

			$_hook = self::bkap_api_zapier_get_product_trigger_setting( $product_id, $hook, 'hook' );
			$label = self::bkap_api_zapier_get_product_trigger_setting( $product_id, $hook, 'label' );

			if ( '' === $label && '' !== $_hook ) {
				// Before we started using label as key.
				$hook_data = self::bkap_api_zapier_fetch_subscription_information( $action, 'url', $_hook );
				$label     = isset( $hook_data->label ) ? $hook_data->label : '';
			}

			if ( ! empty( $label ) ) {
				$label_data = self::bkap_api_zapier_fetch_subscription_information( $action, 'label', $label );
			}
			return isset( $label_data->url ) ? $label_data->url : '';
		}

		/**
		 * Fetch Zapier Subscription information.
		 *
		 * @since 5.14.0
		 * @param string $action Hook Action
		 * @param string $key Subscription Key.
		 * @param string $value Subscription Value.
		 */
		public static function bkap_api_zapier_fetch_subscription_information( $action, $key, $value ) {

			$subscriptions = (array) self::bkap_api_zapier_get_subscriptions( $action );

			if ( '' !== $subscriptions && is_array( $subscriptions ) && count( $subscriptions ) > 0 ) {

				// Reverse array to have most recent items at the top.
				$subscriptions = array_reverse( $subscriptions );

				foreach ( $subscriptions as $subscription ) {
					if ( $subscription->{$key} === $value && $action === $subscription->action ) {
						return $subscription;
					}
				}
			}

			return '';
		}
	}

	$bkap_api_zapier_settings = new BKAP_API_Zapier_Settings();
}
