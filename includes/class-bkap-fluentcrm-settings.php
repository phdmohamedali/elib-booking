<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Booking->Settings->Integration->FluentCRM settings
 *
 * @author   Tyche Softwares
 * @package  BKAP/FluentCRM
 * @category Classes
 * @class    Bkap_Fluentcrm_Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Not Allowed Here !' ); // If this file is called directly, abort.
}

if ( ! class_exists( 'Bkap_Fluentcrm_Settings' ) ) {

	/**
	 * Class Bkap_Fluentcrm_Settings.
	 */
	class Bkap_Fluentcrm_Settings {

		/**
		 * Class instance.
		 *
		 * @var $_instance Class Instance.
		 * @since 5.12.0
		 */
		private static $_instance = null; // phpcs:ignore

		/**
		 * Default Constructor
		 *
		 * @since 5.12.0
		 */
		public function __construct() {

			$this->bkap_fluentcrm_load_dependencies();
			$this->bkap_fluentcrm_init_api();

			add_action( 'bkap_integration_links', array( $this, 'bkap_fluentcrm_link' ), 10, 1 );
			add_action( 'bkap_global_integration_settings', array( $this, 'bkap_fluentcrm_settings_page' ), 10, 1 );

			// Product Settings.
			add_action( 'bkap_after_zoom_meeting_settings_product', array( $this, 'bkap_fluentcrm_product_settings' ), 10, 2 );

			if ( ( ! bkap_fluentcrm_lite_active() || ! bkap_fluentcrm_pro_active() ) || ! BKAP_License::enterprise_license() ) {
				return;
			}

			// Global Settings.
			add_action( 'admin_init', array( $this, 'bkap_fluentcrm_settings' ), 11 );

			// Adding Booking data to FluentCRM contact.
			add_action( 'bkap_update_booking_post_meta', array( $this, 'bkap_fluentcrm_booking_created' ), 20, 2 );
			add_action( 'bkap_after_zoom_meeting_created', array( $this, 'bkap_fluentcrm_booking_created' ), 10, 2 );

			add_action( 'bkap_after_update_booking_post', array( $this, 'bkap_fluentcrm_booking_updated' ), 20, 3 );
			add_action( 'bkap_after_rescheduling_booking', array( $this, 'bkap_fluentcrm_booking_updated' ), 20, 3 );

			// Booking Confirmed.
			add_action( 'bkap_booking_confirmed_using_icon', array( $this, 'bkap_fluentcrm_booking_confirmed' ), 10, 1 );
			add_action( 'bkap_booking_is_confirmed', array( $this, 'bkap_fluentcrm_booking_confirmed' ), 10, 1 );

			// Booking Cancelled.
			add_action( 'bkap_booking_is_cancelled', array( $this, 'bkap_fluentcrm_booking_cancelled' ), 20, 1 );
			add_action( 'bkap_reallocation_of_booking', array( $this, 'bkap_fluentcrm_booking_reallocated' ), 20, 4 );
		}

		/**
		 * Cancel Booking when cancelling order.
		 *
		 * @param int   $item_id Item ID.
		 * @param array $item_value Item Data.
		 * @param int   $product_id Product ID.
		 * @param int   $order_id Order ID.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_booking_reallocated( $item_id, $item_value, $product_id, $order_id ) {
			$booking_id = bkap_common::get_booking_id( $item_id );
			$this->bkap_fluentcrm_booking_cancelled( $booking_id );
		}

		/**
		 * Sending data to FluentCRM Based on the Booking Actions.
		 *
		 * @param int $booking_id Booking ID.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_booking_cancelled( $booking_id ) {

			$booking_data = bkap_get_meta_data( $booking_id );
			$this->bkap_fluntcrm_booking_actions( $booking_id, $booking_data[0], 'cancel' );
		}

		/**
		 * Sending data to FluentCRM Based on the Booking Actions.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param array  $booking_data Array of Booking data.
		 * @param string $action Booking Action that is being performed.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluntcrm_booking_actions( $booking_id, $booking_data, $action = '' ) {

			$product_id = $booking_data['product_id'];

			if ( bkap_fluentcrm_enable( $product_id ) ) {
				/* Fetching Connection */
				$fluentcrm_connection   = bkap_fluentcrm_connection();
				$data                   = array();
				$contact_data           = bkap_fluentcrm_get_contact_data( $booking_id, $booking_data );
				$data                   = array_merge( $data, $contact_data );
				$data['status']         = 'subscribed';
				$data['__force_update'] = 'yes';
				$data['lists']          = array();
				$f_list_id              = bkap_fluentcrm_list_id( $product_id );

				if ( $f_list_id['status'] ) {
					$data['lists'] = array( $f_list_id['status'] );
				}

				$tags = bkap_fluentcrm_get_available_tags( $fluentcrm_connection );

				switch ( $action ) {
					case 'update':
						$event = $fluentcrm_connection->events[1];
						break;
					case 'confirm':
						$event = $fluentcrm_connection->events[3];
						break;
					case 'cancel':
						$event = $fluentcrm_connection->events[2];
						break;
					default:
						$event = $fluentcrm_connection->events[0];
						break;
				}

				$additional_data       = bkap_fluentcrm_prepare_custom_fields_data( $booking_id, $booking_data );
				$data['custom_values'] = $additional_data['custom_values'];

				$contact_exists = $fluentcrm_connection->bkap_create_contact( $data );

				if ( isset( $contact_exists['contact'] ) ) {
					$contact_id = $contact_exists['contact']['id'];
					// Removing Tags.
					$remove_tags                = array();
					$remove_tags['remove_tags'] = array( 'Booking Created', 'Booking Updated', 'Booking Deleted', 'Booking Confirmed' );
					$remove_tags['subscribers'] = array( $contact_id );
					$fluentcrm_connection->bkap_remove_tags( $remove_tags );

					// Adding Tags.
					$add_tags                = array();
					$add_tags['add_tags']    = array( $event );
					$add_tags['subscribers'] = array( $contact_id );
					$fluentcrm_connection->bkap_add_tags_to_contact( $add_tags );

					// Adding Note to Contact.
					if ( '' === $action || 'update' === $action ) {
						$note_data         = array();
						$note_data['id']   = $contact_id;
						$note_data['note'] = $additional_data['note'];
						$note_response     = $fluentcrm_connection->bkap_add_note( $note_data );
					}
				}
			}
		}

		/**
		 * Sending data to FluentCRM when Booking is created.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param array $booking_data Array of Booking data.
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_booking_created( $booking_id, $booking_data ) {
			$this->bkap_fluntcrm_booking_actions( $booking_id, $booking_data );
		}

		/**
		 * Sending data to FluentCRM when Booking is update via Booking Post.
		 *
		 * @param int   $booking_id Booking ID.
		 * @param obj   $booking Booking Object.
		 * @param array $booking_data Array of Booking data.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_booking_updated( $booking_id, $booking, $booking_data ) {
			$this->bkap_fluntcrm_booking_actions( $booking_id, $booking_data[0], 'update' );
		}

		/**
		 * Sending data to FluentCRM when Booking is confirmed.
		 *
		 * @param int $booking_id Booking ID.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_booking_confirmed( $booking_id ) {

			$booking_data = bkap_get_meta_data( $booking_id );
			$this->bkap_fluntcrm_booking_actions( $booking_id, $booking_data[0], 'confirm' );
		}

		/**
		 * Create only one instance so that it may not Repeat
		 *
		 * @since 5.12.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Load the other class dependencies
		 *
		 * @since 5.12.0
		 */
		protected function bkap_fluentcrm_load_dependencies() {
			// Include the Main Class.
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class-bkap-fluentcrm-connection.php';
		}

		/**
		 * Initialize the integration.
		 *
		 * @since 5.12.0
		 */
		protected function bkap_fluentcrm_init_api() {

			// Load the Credentials.
			$fluentcrm_connection      = bkap_fluentcrm_connection();
			$bkap_fluentcrm_connection = bkap_fluentcrm_global_settings();

			$bkap_fluentcrm_api_name = $bkap_fluentcrm_connection['bkap_fluentcrm_api_name'];
			$bkap_fluentcrm_api_key  = $bkap_fluentcrm_connection['bkap_fluentcrm_api_key'];

			$fluentcrm_connection->fluentcrm_api_name = $bkap_fluentcrm_api_name;
			$fluentcrm_connection->fluentcrm_api_key  = $bkap_fluentcrm_api_key;
			$fluentcrm_connection->fluentcrm_api_url  = home_url() . '/wp-json/fluent-crm/v2/';
		}

		/**
		 * FluentCRM Settings in Booking Meta Box-> Integrations-> FluentCRM
		 *
		 * @param int   $product_id Product ID.
		 * @param array $booking_settings Booking Settings.
		 *
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_product_settings( $product_id, $booking_settings ) {

			if ( ( ! bkap_fluentcrm_lite_active() || ! bkap_fluentcrm_pro_active() ) || ! BKAP_License::enterprise_license() ) {
				return;
			}

			$enable_fluentcrm = '';
			if ( isset( $booking_settings['bkap_fluentcrm'] ) && 'on' === $booking_settings['bkap_fluentcrm'] ) {
				$enable_fluentcrm = 'checked';
			}

			$bkap_fluentcrm_connection = bkap_fluentcrm_global_settings();
			$bkap_fluentcrm_api_name   = $bkap_fluentcrm_connection['bkap_fluentcrm_api_name'];
			$bkap_fluentcrm_api_key    = $bkap_fluentcrm_connection['bkap_fluentcrm_api_key'];

			$data_set = true;
			if ( '' !== $bkap_fluentcrm_api_name && '' !== $bkap_fluentcrm_api_key ) {
				$data_set             = true;
				$fluentcrm_connection = bkap_fluentcrm_connection();
				$response             = $fluentcrm_connection->bkap_get_lists();
			}
			?>
			<button type="button" class="bkap-integrations-accordion bkap_integration_fluentcrm_button"><b><?php esc_html_e( 'FluentCRM', 'woocommerce-booking' ); ?></b></button>
			<div class="bkap_google_sync_settings_content bkap_integrations_panel bkap_integration_fluentcrm_panel">
				<?php
				if ( ! bkap_fluentcrm_lite_active() || ! bkap_fluentcrm_pro_active() ) {
					$class   = 'notice notice-info';
					$message = __( 'FluentCRM plugin is not active. Please install and activate it.', 'woocommerce-booking' );
					printf( '<p class="%1$s">%2$s</p>', $class, $message );
				} else {
					?>
				<table class='form-table bkap-form-table'>
					<tr>
						<th>
							<?php esc_html_e( 'Enable FluentCRM', 'woocommerce-booking' ); ?>
						</th>
						<td>
							<label class="bkap_switch">
								<input id="bkap_enable_fluentcrm" name= "bkap_enable_fluentcrm" type="checkbox" <?php esc_attr_e( $enable_fluentcrm ); ?>/>
							<div class="bkap_slider round"></div>
						</td>
						<td>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Enable FluentCRM.', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
						</td>
					</tr>

					<?php

					if ( ! is_wp_error( $response ) && isset( $response['lists'] ) ) {
						$bkap_fluentcrm_list = '';
						if ( isset( $booking_settings['bkap_fluentcrm_list'] ) && '' !== $booking_settings['bkap_fluentcrm_list'] ) {
							$bkap_fluentcrm_list = $booking_settings['bkap_fluentcrm_list'];
						}
						?>
					<tr>
						<th>
							<?php esc_html_e( 'Select List', 'woocommerce-booking' ); ?>
						</th>
						<td>
							<select name="bkap_fluentcrm_list" id="bkap_fluentcrm_list">
							<option value=''><?php esc_html_e( 'Select List', 'woocommerce-booking' ); ?></option>
							<?php
							foreach ( $response['lists'] as $list ) {
								$selected_list         = ( $list['id'] == $bkap_fluentcrm_list ) ? 'selected' : '';
								$fluent_crm_list_title = $list['title'];
								printf( "<option value='%s' %s>%s</option>", esc_attr( $list['id'] ), esc_attr( $selected_list ), esc_html( $fluent_crm_list_title ) );
							}
							?>
							</select>
						</td>
						<td>
							<img class="help_tip" width="16" height="16" data-tip="<?php esc_attr_e( 'Contact will be added to selected list..', 'woocommerce-booking' ); ?>" src="<?php echo plugins_url(); ?>/woocommerce/assets/images/help.png"/>
						</td>
					</tr>
					<?php } ?>
					<tr>
						<th></th>
						<td colspan="2">
							<p><i>
								<?php
								if ( ! $data_set ) {
									$redirect_args = array(
										'page'      => 'woocommerce_booking_page',
										'action'    => 'calendar_sync_settings',
										'section'   => 'fluentcrm',
										'post_type' => 'bkap_booking',
									);
									$url           = add_query_arg( $redirect_args, admin_url( '/edit.php?' ) );
									/* translators: %s: FluentCRM Settings page link */
									$api_msg = sprintf( __( 'Set App API Name and API Key for FluentCRM <a href="%s" target="_blank">here.</a>', 'woocommerce-booking' ), $url );
									echo $api_msg; // phpcs:ignore
								}
								?>
								</i>
							</p>
						</td>
					</tr>
				</table>
				<?php } ?>
			</div>
			<?php
		}

		/**
		 * This function will add FluentCRM link in Integrations tab.
		 *
		 * @param string $section Section of Integration.
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_link( $section ) {

			$fluent_class = '';
			if ( 'fluentcrm' === $section ) {
				$fluent_class = 'current';
			}
			?>
			<li>
				<a href="edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=fluentcrm" class="<?php echo esc_attr( $fluent_class ); ?>"><?php esc_html_e( 'FluentCRM', 'woocommerce-booking' ); ?></a>
			</li>
			<?php
		}

		/**
		 * This function will add FluentCRM Settings page.
		 *
		 * @param string $section Section of Integration.
		 * @since 5.12.0
		 */
		public function bkap_fluentcrm_settings_page( $section ) {

			if ( 'fluentcrm' === $section ) {

				if ( ! BKAP_License::enterprise_license() ) {
					?>
					<div class="bkap-plugin-error-notice-admin"><?php echo BKAP_License::enterprise_license_error_message(); // phpcs:ignore; ?></div>
					<?php
					return;
				}

				if ( bkap_fluentcrm_lite_active() || bkap_fluentcrm_pro_active() ) {
					print( '<div id="content"><form method="post" action="options.php">' );
					settings_errors();
					settings_fields( 'bkap_fluentcrm_settings' );
					do_settings_sections( 'bkap_fluentcrm_settings_page' );

					print( '<p class="submit">' );
					submit_button( __( 'Save Settings', 'woocommerce-booking' ), 'primary', 'save', false );
					print( '</p></form></div>' );
				} else {
					bkap_fluentcrm_inactive_notice();
				}
			}
		}

		/**
		 * This function will add FluentCRM Settings sections.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_settings() {

			if ( isset( $_GET['action'] ) && 'calendar_sync_settings' === $_GET['action'] && isset( $_GET['section'] ) && 'fluentcrm' === $_GET['section'] ) {

				// First, we register a section. This is necessary since all future options must belong to one.
				add_settings_section(
					'bkap_fluentcrm_settings_section', // ID used to identify this section and with which to register options.
					__( 'FluentCRM', 'woocommerce-booking' ), // Title to be displayed on the administration page.
					array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_settings_section_callback' ), // Callback used to render the description of the section.
					'bkap_fluentcrm_settings_page' // Page on which to add this section of options.
				);

				$bkap_fluentcrm_connection = bkap_fluentcrm_global_settings();
				$bkap_fluentcrm_api_name   = $bkap_fluentcrm_connection['bkap_fluentcrm_api_name'];
				$bkap_fluentcrm_api_key    = $bkap_fluentcrm_connection['bkap_fluentcrm_api_key'];
				$bkap_fluentcrm_list       = isset( $bkap_fluentcrm_connection['bkap_fluentcrm_list'] ) ? $bkap_fluentcrm_connection['bkap_fluentcrm_list'] : '';

				add_settings_field(
					'bkap_fluentcrm_instructions',
					__( 'Instructions', 'woocommerce-booking' ),
					array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_instructions_callback' ),
					'bkap_fluentcrm_settings_page',
					'bkap_fluentcrm_settings_section',
					array(
						'class'      => 'bkap_fluentcrm_instructions',
						'connection' => $bkap_fluentcrm_connection,
					)
				);

				add_settings_field(
					'bkap_fluentcrm_api_name',
					__( 'API Name', 'woocommerce-booking' ),
					array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_api_name_callback' ),
					'bkap_fluentcrm_settings_page',
					'bkap_fluentcrm_settings_section',
					array( 'bkap_fluentcrm_api_name' => $bkap_fluentcrm_api_name )
				);

				add_settings_field(
					'bkap_fluentcrm_api_key',
					__( 'API Key', 'woocommerce-booking' ),
					array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_api_key_callback' ),
					'bkap_fluentcrm_settings_page',
					'bkap_fluentcrm_settings_section',
					array( 'bkap_fluentcrm_api_key' => $bkap_fluentcrm_api_key )
				);

				if ( '' !== $bkap_fluentcrm_api_name && '' !== $bkap_fluentcrm_api_key ) {

					$fluentcrm_connection = bkap_fluentcrm_connection();
					$response             = $fluentcrm_connection->bkap_get_lists();

					if ( ! is_wp_error( $response ) && isset( $response['lists'] ) ) {

						if ( ! count( $response['lists'] ) ) {
							$added_list          = $fluentcrm_connection->bkap_add_default_list();
							$response['lists'][] = $added_list['lists'];
						}

						add_settings_field(
							'bkap_fluentcrm_lists',
							__( 'Select List', 'woocommerce-booking' ),
							array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_lists_callback' ),
							'bkap_fluentcrm_settings_page',
							'bkap_fluentcrm_settings_section',
							array(
								'bkap_fluentcrm_list' => $bkap_fluentcrm_list,
								'lists'               => $response['lists'],
							)
						);

						// @todo - need to check why custom fields are not getting added.
						if ( '' === $bkap_fluentcrm_list ) {
							self::bkap_add_custom_fields( $bkap_fluentcrm_api_name, $bkap_fluentcrm_api_key );
							self::bkap_add_default_events( $bkap_fluentcrm_api_name, $bkap_fluentcrm_api_key );
						}
					} else {

						update_option(
							'bkap_fluentcrm_connection',
							array(
								'bkap_fluentcrm_api_name' => $bkap_fluentcrm_api_name,
								'bkap_fluentcrm_api_key'  => $bkap_fluentcrm_api_key,
								'bkap_fluentcrm_list'     => '',
							)
						);

						$message = ( ! is_wp_error( $response ) && isset( $response['message'] ) ) ? $response['message'] : '';
						add_settings_field(
							'bkap_fluentcrm_error',
							'',
							array( 'Bkap_Fluentcrm_Settings', 'bkap_fluentcrm_error_callback' ),
							'bkap_fluentcrm_settings_page',
							'bkap_fluentcrm_settings_section',
							array( 'message' => $message )
						);
					}
				}
			}
		}

		/**
		 * Add Custom Fields for Contact on FluentCRM connection.
		 *
		 * @param string $bkap_fluentcrm_api_name API Name.
		 * @param string $bkap_fluentcrm_api_key API Key.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_add_custom_fields( $bkap_fluentcrm_api_name, $bkap_fluentcrm_api_key ) {
			$bkap_fluentcrm_connection = bkap_fluentcrm_connection();
			$bkap_fluentcrm_connection->bkap_custom_fields();
		}

		/**
		 * Add default Tags on FluentCRM connection.
		 *
		 * @param string $bkap_fluentcrm_api_name API Name.
		 * @param string $bkap_fluentcrm_api_key API Key.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_add_default_events( $bkap_fluentcrm_api_name, $bkap_fluentcrm_api_key ) {
			$bkap_fluentcrm_connection = bkap_fluentcrm_connection();
			$bkap_fluentcrm_connection->bkap_add_tags();
		}

		/**
		 * Callback for the gcal general settings section.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_settings_section_callback() {}

		/**
		 * Callback for the gcal general settings section.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_instructions_callback() {
			?>
			<div id="bkap_fluentcrm_steps"><?php _e( 'To find your <b>API Name</b> and <b>API Key</b> do the following:', 'woocommerce-booking' ); // phpcs:ignore ?>
				<div class="description fluentcrm-instructions">
					<ul style="list-style-type:decimal;">
					<li><?php _e( 'Go to FluentCRM -> Settings -> Managers for Adding New Manager.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'After clicking on Add New Manager button, a popup will appear. Enter the email address and enable the required Permissions and click on Create button.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Go to the REST API tab and click on Add New Key button. Give the name and select the Manager and click on Create button.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					<li><?php _e( 'Copy API Username and API Password and set them to API Name and API Key fields respectively.', 'woocommerce-booking' ); // phpcs:ignore?></li>
					</ul>
				</div>
			</div>
			<?php
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->API Key.
		 *
		 * @param array $args - Setting Label Array.
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_api_name_callback( $args ) {

			$bkap_fluentcrm_api_name = $args['bkap_fluentcrm_api_name'];
			echo '<input type="text" name="bkap_fluentcrm_connection[bkap_fluentcrm_api_name]" id="bkap_fluentcrm_api_name" value="' . esc_attr( $bkap_fluentcrm_api_name ) . '" size="60" />';
			$html = sprintf( '<br><label for="bkap_fluentcrm_api_name">%s</label>', __( 'The API Name obtained from FluentCRM-> Settings-> REST API.', 'woocommerce-booking' ) );
			echo $html; // phpcs:ignore
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->API Secret.
		 *
		 * @param array $args - Setting Label Array.
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_api_key_callback( $args ) {

			$bkap_fluentcrm_api_key = $args['bkap_fluentcrm_api_key'];

			echo '<input type="text" id="bkap_fluentcrm_connection" name="bkap_fluentcrm_connection[bkap_fluentcrm_api_key]" value="' . esc_attr( $bkap_fluentcrm_api_key ) . '" size="60" />';
			$html = sprintf( '<br><label for="bkap_fluentcrm_connection">%s</label>', __( 'The API Key obtained from FluentCRM-> Settings-> REST API.', 'woocommerce-booking' ) );
			echo $html; // phpcs:ignore
		}

		/**
		 * This function will display error notice for invalid connection.
		 *
		 * @param array $args - Arguments.
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_error_callback( $args ) {
			if ( '' !== $args['message'] ) {
				$class = 'notice notice-error';
				printf( '<div class="%s"><p>%s</p></div>', $class, $args['message'] ); // phpcs:ignore
			} else {
				esc_html_e( 'Invalid API Name or API Key. Please enter correct API Name and API Key for successful connection.', 'woocommerce-booking' );
			}
		}

		/**
		 * This function will display dropdown for selecting the list.
		 *
		 * @param array $args Arguments.
		 *
		 * @since 5.12.0
		 */
		public static function bkap_fluentcrm_lists_callback( $args ) {

			$lists         = $args['lists'];
			$selected_list = $args['bkap_fluentcrm_list'];

			$drop_down  = "<select name='bkap_fluentcrm_connection[bkap_fluentcrm_list]' id='bkap_fluentcrm_list'>";
			$drop_down .= '<option value="">' . __( 'Select List', 'woocommerce-booking' ) . '</option>';
			foreach ( $lists as $key => $value ) {
				$selected   = ( $value['id'] == $selected_list ) ? 'selected' : '';
				$drop_down .= '<option value="' . $value['id'] . '" ' . $selected . '>' . $value['title'] . '</option>';
			}
			$drop_down .= '</select>';

			echo $drop_down;
		}
	}
	Bkap_Fluentcrm_Settings::instance();
}
?>
