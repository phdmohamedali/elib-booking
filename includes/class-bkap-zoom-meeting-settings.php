<?php
/**
 * Bookings & Appointment Plugin for WooCommerce
 *
 * Class for Booking->Settings->Integration->Zoom Meetings settings
 *
 * @author   Tyche Softwares
 * @package  BKAP/Zoom-Meetings
 * @category Classes
 * @class    Bkap_Zoom_Meeting_Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Not Allowed Here !' ); // If this file is called directly, abort.
}

if ( ! class_exists( 'Bkap_Zoom_Meeting_Settings' ) ) {
	/**
	 * Class Bkap_Zoom_Meeting_Settings.
	 */
	class Bkap_Zoom_Meeting_Settings {

		/**
		 * Class instance.
		 *
		 * @var $_instance Class Instance.
		 * @since 5.2.0
		 */
		private static $_instance = null; // phpcs:ignore

		/**
		 * Create only one instance so that it may not Repeat
		 *
		 * @since 5.2.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Default Constructor
		 *
		 * @since 5.2.0
		 */
		public function __construct() {
			$this->bkap_zoom_autoloader();
			$this->bkap_zoom_load_dependencies();
			$this->bkap_zoom_init_api();

			add_action( 'wp_ajax_bkap_zoom_meeting_test_connection', array( &$this, 'bkap_zoom_meeting_test_connection' ) );
			//add_action( 'bkap_update_booking_post_meta', array( &$this, 'bkap_create_zoom_meeting' ), 10, 2 );

			add_action( 'woocommerce_order_status_completed', array( $this, 'bkap_create_zoom_meeting_on_order_confirmed_or_processing' ), 5, 2 );

			add_action( 'woocommerce_order_status_processing', array( $this, 'bkap_create_zoom_meeting_on_order_confirmed_or_processing' ), 5, 2 );

			add_filter( 'woocommerce_email_order_items_args', array( $this, 'bkap_add_zoom_link_to_order_item' ), 8, 1 );

			add_action( 'bkap_admin_booking_data_after_booking_details', array( &$this, 'bkap_display_zoom_meeting_info_booking_details' ), 10, 2 );
			add_action( 'bkap_after_delete_booking', array( &$this, 'bkap_delete_zoom_meeting' ), 10, 2 );

			add_action( 'admin_init', array( &$this, 'bkap_assign_meetings' ), 9 );
			add_action( 'bkap_assign_meetings', array( &$this, 'bkap_assign_meetings_to_booking' ) );

			add_action( 'admin_notices', array( &$this, 'bkap_assign_meetings_to_booking_notice' ) );
		}

		/**
		 * This function will display notice for meeting assignment.
		 *
		 * @since 5.2.0
		 */
		public function bkap_assign_meetings_to_booking_notice() {

			if ( ! BKAP_License::enterprise_license() ) {
				return false;
			}

			if ( 'page' !== get_post_type() && 'post' !== get_post_type() ) {

				if ( isset( $_GET['section'] ) && 'zoom_meeting' === $_GET['section'] ) { // phpcs:ignore

					if ( isset( $_GET['success'] ) && wp_unslash( $_GET['success'] ) ) {  // phpcs:ignore
						$message = __( 'Connected to Zoom successfully.', 'woocommerce-booking' );
						$class   = 'notice notice-success';
						printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
					}

					if ( isset( $_GET['logout'] ) && wp_unslash( $_GET['logout'] ) ) { // phpcs:ignore

						$message = __( 'Successfully logged out from Zoom.', 'woocommerce-booking' );
						$class   = 'notice notice-success';
						printf( '<div class="%s"><p>%s</p></div>', esc_attr( $class ), esc_html( $message ) );
					}
				}

				$key    = get_option( 'bkap_zoom_api_key', '' );
				$secret = get_option( 'bkap_zoom_api_secret', '' );

				if ( '' !== $key && '' !== $secret ) {
					$client_id      = get_option( 'bkap_zoom_client_id', '' );
					$client_secret  = get_option( 'bkap_zoom_client_secret', '' );
					$migrate_notice = get_option( 'bkap_zoom_access_token', '' );
					if ( '' === $client_id || '' === $client_secret ) {
						$class          = 'notice notice-info';
						$zoom_page_link = bkap_zoom_redirect_url();

						/* translators: %s: URL Zoom Meeting page */
						$message = sprintf( __( 'Urgent Notification: Attention, please! By September 8, 2023, Zoom will no longer support JWT app type authorization. Your site is currently benefiting from the Zoom integration offered by the Booking & Appointment Plugin for WooCommerce. To continue enjoying uninterrupted service, we recommend transitioning from JWT app type to OAuth app type <a href="%s">here.</a>', 'woocommerce-booking' ), $zoom_page_link );

						printf( '<div class="%s"><p>%s</p></div>', $class, $message ); // phpcs:ignore	
					}
				}

				$option = get_option( 'bkap_assign_meeting_scheduled', false );
				if ( $option ) {
					switch ( $option ) {
						case 'yes':
							/* translators: %s: URL of Google Calendar Sync page */
							$message = __( 'Zoom meeting links are getting generated and assigned in the background for future bookings. This process may take a little while, so please be patient.', 'woocommerce-booking' );
							$class   = 'notice notice-info';
							printf( '<div class="%s"><p><b>%s</b></p></div>', $class, $message ); // phpcs:ignore
							break;
						case 'done':
							/* translators: %s: URL of Google Calendar Sync page */
							$message = __( 'Zoom meeting links have been generated and assigned to future bookings.', 'woocommerce-booking' );
							$class   = 'notice notice-success bkap-meeting-notice is-dismissible';
							printf( '<div class="%s"><p><b>%s</b></p></div>', $class, $message ); // phpcs:ignore
							break;
					}
				}

				if ( isset( $_GET['section'] ) && 'zoom_meeting' === $_GET['section'] && ! bkap_zoom_meeting_enable() ) { // phpcs:ignore

					if ( '' !== $key && '' !== $secret ) {
						$message = __( 'Zoom Meetings - Connection Failed.', 'woocommerce-booking' );
						$class   = 'notice notice-error';
						printf( '<div class="%s"><p>%s</p></div>', $class, $message ); // phpcs:ignore
					}
				}
			}
		}

		/**
		 * This function will schedule an action to create/assign the meeting for future bookings.
		 *
		 * @since 5.2.0
		 */
		public function bkap_assign_meetings() {

			if ( isset( $_GET['section'] ) && 'zoom_meeting' === $_GET['section'] ) { // phpcs:ignore

				if ( isset( $_GET['code'] ) && '' !== $_GET['code'] ) { // phpcs:ignore
					$zoom_connection = bkap_zoom_connection();
					$data            = $zoom_connection->bkap_exchange_code_for_token( sanitize_text_field( wp_unslash( $_GET['code'] ) ) ); // phpcs:ignore

					if ( $data ) {
						if ( isset( $data['access_token'] ) && '' !== $data['access_token'] ) {
							update_option( 'bkap_zoom_access_token', $data['access_token'] );
							update_option( 'bkap_zoom_token_expiry', time() + $data['expires_in'] );
							update_option( 'bkap_zoom_access_data', $data );

							delete_option( 'bkap_zoom_api_key' );
							delete_option( 'bkap_zoom_api_secret' );

							$zoom_meeting_page = bkap_zoom_redirect_url();
							$query_args        = array(
								'success' => '1',
							);
							$zoom_page_link    = add_query_arg( $query_args, $zoom_meeting_page );

							wp_safe_redirect( $zoom_page_link );
							exit();
						}
					}
				}

				if ( isset( $_GET['logout'] ) && $_GET['logout'] ) { // phpcs:ignore

					delete_option( 'bkap_zoom_access_token' );
					delete_option( 'bkap_zoom_token_expiry' );
					delete_option( 'bkap_zoom_access_data' );
				}
			}

			if ( isset( $_POST['bkap_assign_meeting_to_booking'] ) && '' != $_POST['bkap_assign_meeting_to_booking'] ) { // phpcs:ignore
				if ( bkap_zoom_meeting_enable() ) {
					as_schedule_single_action( time() + 10, 'bkap_assign_meetings', array( 'test' => 20 ) );
					add_option( 'bkap_assign_meeting_scheduled', 'yes' );
				}
			}
		}

		/**
		 * This function will be executed for creating/assigning the meeting for future bookings.
		 *
		 * @since 5.2.0
		 */
		public function bkap_assign_meetings_to_booking() {

			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => '_bkap_enable_booking',
					'value'   => 'on',
					'compare' => '=',
				),
				array(
					'key'     => '_bkap_zoom_meeting',
					'value'   => 'on',
					'compare' => '=',
				),
			);

			$product_ids   = bkap_common::get_woocommerce_product_list( false, 'on', 'yes', $post_status = array(), $meta_query );
			$bookings      = bkap_get_bookings_to_assign_zoom_meeting( $product_ids );
			$meeting_query = array(
				'key'     => '_bkap_zoom_meeting_link',
				'compare' => 'NOT EXISTS',
			);

			foreach ( $bookings as $booking ) {

				$start_date    = $booking->_bkap_start;
				$end_date      = $booking->_bkap_end;
				$product_id    = $booking->_bkap_product_id;
				$variation_id  = $booking->_bkap_variation_id;
				$resource_id   = $booking->_bkap_resource_id;
				$order_item_id = $booking->_bkap_order_item_id;
				$order_id      = $booking->_bkap_parent_id;
				$booking_id    = $booking->ID;

				$booking_data = array(
					'start'         => $start_date,
					'end'           => $end_date,
					'product_id'    => $product_id,
					'resource_id'   => $resource_id,
					'variation_id'  => $variation_id,
					'order_item_id' => $order_item_id,
					'parent_id'     => $order_id,
				);

				$meeting_data = self::bkap_create_zoom_meeting( $booking_id, $booking_data );

				if ( count( $meeting_data ) > 0 ) {
					/* translators: %s: Booking ID and Meeting link. */
					$meeting_msg = sprintf( __( 'Updated Zoom Meeting Link for Booking #%1$s - %2$s', 'woocommerce-booking' ), $booking_id, $meeting_data['meeting_link'] );
					$order_obj   = wc_get_order( $order_id );
					$order_obj->add_order_note( $meeting_msg, 1, false );
				}
			}

			update_option( 'bkap_assign_meeting_scheduled', 'done' );
		}

		/**
		 * Autoloader.
		 *
		 * @since 5.2.0
		 */
		public function bkap_zoom_autoloader() {
			require_once BKAP_VENDORS_LIBRARIES_PATH . 'firebase-jwt/vendor/autoload.php';
		}

		/**
		 * INitialize the hooks
		 *
		 * @since 5.2.0
		 */
		protected function bkap_zoom_init_api() {
			// Load the Credentials.
			$bkap_zoom_connection                     = bkap_zoom_connection();
			$bkap_zoom_connection->zoom_api_key       = get_option( 'bkap_zoom_api_key' );
			$bkap_zoom_connection->zoom_api_secret    = get_option( 'bkap_zoom_api_secret' );
			$bkap_zoom_connection->zoom_client_id     = get_option( 'bkap_zoom_client_id' );
			$bkap_zoom_connection->zoom_client_secret = get_option( 'bkap_zoom_client_secret' );
		}

		/**
		 * Load the other class dependencies
		 *
		 * @since 5.2.0
		 */
		protected function bkap_zoom_load_dependencies() {
			// Include the Main Class.
			require_once BKAP_BOOKINGS_INCLUDE_PATH . 'class-bkap-zoom-meeting-connection.php';
		}

		/**
		 * Zoom Meeting Test Connection.
		 *
		 * @since 5.2.0
		 */
		public function bkap_zoom_meeting_test_connection() {

			$zoom_connection = bkap_zoom_connection();

			$result = json_decode( $zoom_connection->bkap_list_users() );
			if ( ! empty( $result ) ) {
				if ( ! empty( $result->code ) ) {
					wp_send_json( $result->message );
				}

				if ( http_response_code() === 200 ) {
					$conn_string = __( 'API Connection is good.', 'woocommerce-booking' );
					wp_send_json( $conn_string );
				} else {
					wp_send_json( $result );
				}
			}
			wp_die();
		}

		/**
		 * Creating assigning the meeting link to the booking.
		 *
		 * @param int    $booking_id Booking ID.
		 * @param obj    $booking_data Booking Object.
		 * @param string $action default is blank - 'update' can be passed for update operation.
		 * @since 5.2.0
		 */
		public static function bkap_create_zoom_meeting( $booking_id, $booking_data, $action = '', $paid_check = true ) {

			$booking_obj = new BKAP_Booking( $booking_id );

			// Don't create Zoom Meeting if payment for the Order has not been made.
			// For now, we check for payment from the Order.
			// TODO: Find a way of checking for payment for the Booking itself since Zoom Meetings are actually created for the Bookings and not orders.
			$order_obj = $booking_obj->get_order();

			if ( false === $order_obj ) {

				// Stop if order information can't be retrieved. This could be as a result of the order not being created yet or custom post type not being available.
				return;
			}

			if ( ! $order_obj->get_date_paid() && $paid_check ) {
				return;
			}

			$booking_data = (array) $booking_data;
			extract( $booking_data ); // phpcs:ignore

			$meeting_info = array();
			if ( bkap_zoom_meeting_enable( $product_id, $resource_id ) ) {

				$zoom_booking_id = bkap_check_same_booking_info( $start, $end, $product_id, $variation_id, $resource_id, $booking_id );
				$meeting_label   = bkap_zoom_join_meeting_label( $product_id );
				$meeting_text    = bkap_zoom_join_meeting_text( $product_id );

				if ( 0 !== $zoom_booking_id ) {
					$meeting_link = get_post_meta( $zoom_booking_id, '_bkap_zoom_meeting_link', true );
					$meeting_data = get_post_meta( $zoom_booking_id, '_bkap_zoom_meeting_data', true );
					update_post_meta( $booking_id, '_bkap_zoom_meeting_link', $meeting_link );
					update_post_meta( $booking_id, '_bkap_zoom_meeting_data', $meeting_data );
					$meeting_link = sprintf( '<a href="%s">%s</a>', $meeting_link, $meeting_text );
					wc_add_order_item_meta( $order_item_id, $meeting_label, $meeting_link );

					$meeting_info['meeting_link'] = $meeting_link;
					$meeting_info['meeting_data'] = $meeting_data;

					// Save Zoom Meeting Information to Order Note.
					$order_meeting_link = get_post_meta( $zoom_booking_id, '_bkap_zoom_meeting_link', true );
					$order_meeting_link = sprintf( '<a href="%s">%s</a>', $order_meeting_link, $order_meeting_link );
					$order_meeting_link = sprintf( __( $meeting_label . ': %s', 'woocommerce-booking' ), $order_meeting_link );
					bkap_common::save_booking_information_to_order_note( '', $parent_id, $order_meeting_link );
				} else {
					// Creating meeting.

					$duration = 24 * 60;
					if ( $start != $end ) { // phpcs:ignore
						$t1       = strtotime( $end );
						$t2       = strtotime( $start );
						$diff     = $t1 - $t2;
						$duration = round( $diff / 60 ); // to minutes.
					}

					$product_title      = BKAP_Bookings_View::product_name_data( $booking_obj );
					$product_title      = str_replace( '<br>', ' - ', $product_title );
					$meeting_start_date = date( 'Y-m-d H:i:s', strtotime( $start ) ); // phpcs:ignore
					$topic              = $product_title . ' - ' . get_bloginfo();
					$timezone_string    = bkap_booking_get_timezone_string();
					$booking_type       = get_post_meta( $product_id, '_bkap_booking_type', true );
					$zooom_host         = get_post_meta( $product_id, '_bkap_zoom_meeting_host', true );
					$bkap_settings      = get_post_meta( $product_id, 'woocommerce_booking_settings', true );

					if ( $resource_id > 0 ) {
						$zooom_host = get_post_meta( $resource_id, '_bkap_resource_meeting_host', true );
					}

					$meeting_authentication = false;
					if ( isset( $bkap_settings['zoom_meeting_auth'] ) && 'on' === $bkap_settings['zoom_meeting_auth'] ) {
						$meeting_authentication = true;
					}
					$join_before_host = false;
					if ( isset( $bkap_settings['zoom_meeting_join_before_host'] ) && 'on' === $bkap_settings['zoom_meeting_join_before_host'] ) {
						$join_before_host = true;
					}
					$participant_video = false;
					if ( isset( $bkap_settings['zoom_meeting_participant_video'] ) && 'on' === $bkap_settings['zoom_meeting_participant_video'] ) {
						$participant_video = true;
					}
					$host_video = false;
					if ( isset( $bkap_settings['zoom_meeting_host_video'] ) && 'on' === $bkap_settings['zoom_meeting_host_video'] ) {
						$host_video = true;
					}
					$mute_upon_entry = false;
					if ( isset( $bkap_settings['zoom_meeting_mute_upon_entry'] ) && 'on' === $bkap_settings['zoom_meeting_mute_upon_entry'] ) {
						$mute_upon_entry = true;
					}
					$auto_recording = 'none';
					if ( isset( $bkap_settings['zoom_meeting_auto_recording'] ) && '' !== $bkap_settings['zoom_meeting_auth'] ) {
						$auto_recording = $bkap_settings['zoom_meeting_auto_recording'];
					}
					$alternative_hosts = array();
					if ( isset( $bkap_settings['zoom_meeting_alternative_host'] ) ) {
						$alternative_hosts = $bkap_settings['zoom_meeting_alternative_host'];
					}

					$meeting_data = array(
						'start_date'             => $meeting_start_date,
						'agenda'                 => $topic, // unable to find.
						'meetingTopic'           => $topic, // front par dekhase.
						'timezone'               => $timezone_string,
						'userId'                 => $zooom_host,
						'duration'               => $duration,
						'meeting_authentication' => $meeting_authentication,
						'join_before_host'       => $join_before_host,
						'host_video'             => $host_video,
						'participant_video'      => $participant_video,
						'mute_upon_entry'        => $mute_upon_entry,
						'auto_recording'         => $auto_recording,
						'alternative_hosts'      => $alternative_hosts,
					);

					if ( 'multiple_days' === $booking_type ) {
						$numberofdays               = bkap_get_days_between_two_dates( $start, $end );
						$meeting_data['type']       = 8;
						$meeting_data['duration']   = 24 * 60;
						$meeting_data['recurrence'] = array(
							'type'            => 1,
							'repeat_interval' => 1,
							'end_times'       => $numberofdays,
						);
					}

					$meeting = json_decode( bkap_zoom_connection()->bkap_create_meeting( $meeting_data ) );

					if ( isset( $meeting->join_url ) ) {
						$meeting_link       = $meeting->join_url;
						$order_meeting_link = $meeting_link;
						$meeting_data       = $meeting;

						update_post_meta( $booking_id, '_bkap_zoom_meeting_link', $meeting_link );
						update_post_meta( $booking_id, '_bkap_zoom_meeting_data', $meeting_data );
						$meeting_link = sprintf( '<a href="%s">%s</a>', $meeting->join_url, $meeting_text );
						wc_add_order_item_meta( $order_item_id, $meeting_label, $meeting_link );

						$meeting_info['meeting_link'] = $meeting_link;
						$meeting_info['meeting_data'] = $meeting_data;

						// Save Zoom Meeting Information to Order Note.
						$order_meeting_link = sprintf( '<a href="%s">%s</a>', $order_meeting_link, $order_meeting_link );
						$order_meeting_link = sprintf( __( $meeting_label . ': %s', 'woocommerce-booking' ), $order_meeting_link );
						bkap_common::save_booking_information_to_order_note( '', $parent_id, $order_meeting_link );
					} else {
						$meeting_info['meeting_error'] = $meeting->message;
						$order_obj                     = wc_get_order( $parent_id );
						/* translators: %s: Booking ID and Meeting link. */
						$meeting_msg = sprintf( __( 'Zoom Meeting Error for Booking #%1$s - %2$s', 'woocommerce-booking' ), $booking_id, $meeting->message );
						$order_obj->add_order_note( $meeting_msg );
					}
				}

				do_action( 'bkap_zoom_meeting_created', $booking_id, $booking_data, $meeting_info );
			}

			return $meeting_info;
		}

		/**
		 * Deleting Zoom Meeting.
		 *
		 * @param int $booking_id Booking ID.
		 * @param obj $booking Booking Object.
		 *
		 * @since 5.2.0
		 */
		public static function bkap_delete_zoom_meeting( $booking_id, $booking ) {

			global $wpdb;

			$meeting_link = $booking->get_zoom_meeting_link();
			$product_id   = $booking->get_product_id();

			if ( '' !== $meeting_link && bkap_zoom_meeting_enable( $product_id ) ) {
				$start_date      = $booking->get_start();
				$end_date        = $booking->get_end();
				$resource_id     = $booking->get_resource();
				$order_item_id   = $booking->get_item_id();
				$variation_id    = $booking->get_variation_id();
				$zoom_booking_id = bkap_check_same_booking_info( $start_date, $end_date, $product_id, $variation_id, $resource_id, $booking_id );

				if ( 0 === $zoom_booking_id ) {
					$meeting_data = $booking->get_zoom_meeting_data();
					$meeting_id   = $meeting_data->id;
					$meeting      = bkap_zoom_connection()->bkap_delete_meeting( $meeting_id );
				}

				update_post_meta( $booking_id, '_bkap_zoom_meeting_link', '' );
				update_post_meta( $booking_id, '_bkap_zoom_meeting_data', '' );
				$meeting_label = bkap_zoom_join_meeting_label( $product_id );

				wc_delete_order_item_meta( $order_item_id, $meeting_label );
			}
		}

		/**
		 * Displaying Zoom Meeting information in Edit Booking-> Booking Details.
		 *
		 * @param int $booking_id Booking ID.
		 * @param obj $booking Booking Object.
		 *
		 * @since 5.2.0
		 */
		public function bkap_display_zoom_meeting_info_booking_details( $booking_id, $booking ) {

			$meeting_link = $booking->get_zoom_meeting_link();
			if ( '' !== $meeting_link ) {
				$product_id    = $booking->get_product_id();
				$meeting_label = bkap_zoom_join_meeting_label( $product_id );
				$meeting_text  = bkap_zoom_join_meeting_text( $product_id );
				?>

			<p class="form-field form-field-wide">
				<label for="_bkap_zoom_meeting"><?php echo $meeting_label; ?> - <a href="<?php echo $meeting_link; ?>" target="_blank"><?php echo $meeting_text; // phpcs:ignore ?></a></label>
			</p>
				<?php
			} else {
				$product_id    = $booking->get_product_id();
				$zoom_enabled  = bkap_zoom_meeting_enable( $product_id );
				$meeting_label = bkap_zoom_join_meeting_label( $product_id );

				if ( $zoom_enabled ) {
					?>
					<p class="form-field form-field-wide">
						<label for="bkap_add_zoom_meeting"><?php echo $meeting_label; ?> - <a href="javascript:void(0)" id="bkap_add_zoom_meeting" ><?php echo __( 'Add zoom meeting', 'woocommerce-booking' ); // phpcs:ignore ?></a></label>
						<input type="text" name="bkap_manual_zoom_meeting" id="bkap_manual_zoom_meeting" placeholder="<?php echo __( 'Add meeting link here', 'woocommerce-booking' ); ?>">
						<i id="bkap_manual_zoom_meeting_info"><?php echo __( 'Keeping above field blank will generate new meeting link.', 'woocommerce-booking' ); ?></i>
					</p>
					<?php
				}
			}
		}

		/**
		 * Callback for the gcal general settings section.
		 *
		 * @since 5.2.0
		 */
		public static function bkap_integrations_settings_callback() {}

		/**
		 * Callback for the gcal general settings section.
		 *
		 * @since 5.2.0
		 */
		public static function bkap_zoom_meeting_instructions_callback() {
			?>
			<div id="bkap_zoom_meeting_steps"><?php echo wp_kses( __( 'To find your <b>Client ID</b> and <b>Client Secret</b> do the following:', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
				<div class="description zoom-instructions">
					<ul style="list-style-type:decimal;">
						<li>
						<?php
						printf(
							wp_kses(
								/* translators: %s: Zoom Meeting Settings page link */
								__( 'Sign in to your Zoom account and visit the %s.', 'woocommerce-booking' ),
								array(
									'b' => array(),
									'a' => array(),
								)
							),
							'<b><a href="https://marketplace.zoom.us/" target="_blank">Zoom App Marketplace</a></b>'
						);
						?>
						</li>
						<li><?php echo wp_kses( __( 'Click on the <b>Develop</b> option on the top-right corner and select <b>Build App</b>.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
						<li><?php echo wp_kses( __( 'A page with various app types will be displayed. Select <b>OAuth</b> as the app type and click on <b>Create</b>.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
						<li><?php echo wp_kses( __( 'Fill out the <b>App Name</b> and choose the app type as <b>Account-level</b> app and switch off the option for publishing Zoom App to Marketplace and click on the <b>Create</b> button.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
						<li><?php echo wp_kses( __( 'The <b>Client ID</b> and <b>Client Secret</b> information will be available in the <b>App Credentials</b> tab. Use them in the form below on this page and click on the Save Settings button.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
						<li><?php echo wp_kses( __( 'Once you\'ve copied over your Client ID and Client Secret, copy the <b>Redirect URL</b> and set it to <b>Redirect URL for the OAuth</b> option in the App Credentials section and <b>Add Allow List</b> option in the OAuth Allow List section. Click on Continue for the next step.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?>
						<li><?php echo wp_kses( __( 'In the Information tab, fill data in the required fields like Short description, Long description, Company name, and Developer Contact Information and click on the Continue button.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
						<li><?php echo wp_kses( __( 'In the Features tab, enable the required features and click on the Continue button.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
						<li><?php echo wp_kses( __( 'You will now be on the <b>Scopes</b> tab. Click on the <b>Add Scopes</b> button select all the scopes in <b>Meeting</b> and <b>User</b> tabs and click on the <b>Done</b> button.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
						<li><?php echo wp_kses( __( 'Click on the Continue button and if there are no error messages for missing fields on the Activation tab then your app is ready.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
						<li><?php echo wp_kses( __( 'Now you can connect the store with the created Zoom App by clicking on the <b>Connect to Zoom</b> button below.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
						<li><?php echo wp_kses( __( 'On click of <b>Connect to Zoom</b> button, you will be redirected to the permission page of Zoom for using the app. Click on <b>Allow</b> button to give the permission and you will be redirected back to store upon the successful connection.', 'woocommerce-booking' ), array( 'b' => array() ) ); ?></li>
					</ul>
				</div>
			</div>
			<?php
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->Client ID.
		 *
		 * @param Array $args Array of Additional Data.
		 * @since 5.2.0
		 */
		public static function bkap_zoom_client_id_callback( $args ) {

			$readonly            = 'oauth' === $args['type'] ? 'readonly' : '';
			$bkap_zoom_client_id = get_option( 'bkap_zoom_client_id' );
			echo '<input type="text" name="bkap_zoom_client_id" id="bkap_zoom_client_id" value="' . esc_attr( $bkap_zoom_client_id ) . '" size="60" ' . esc_attr( $readonly ) . '/>';
			$html = sprintf( '<br><label for="bkap_zoom_client_id">%s</label>', __( 'The Client ID obtained from your OAuth app.', 'woocommerce-booking' ) );
			echo $html; // phpcs:ignore
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->Client Secret.
		 *
		 * @param Array $args Array of Additional Data.
		 * @since 5.2.0
		 */
		public static function bkap_zoom_client_secret_callback( $args ) {

			$readonly                = 'oauth' === $args['type'] ? 'readonly' : '';
			$bkap_zoom_client_secret = get_option( 'bkap_zoom_client_secret' );
			echo '<input type="text" id="bkap_zoom_client_secret" name="bkap_zoom_client_secret" value="' . esc_attr( $bkap_zoom_client_secret ) . '" size="60" ' . esc_attr( $readonly ) . ' />';
			$html = sprintf( '<br><label for="bkap_zoom_client_secret">%s</label>', __( 'The Client Secret obtained from your OAuth app.', 'woocommerce-booking' ) );
			echo $html; // phpcs:ignore
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->Redirect URL.
		 *
		 * @since 5.23.0
		 */
		public static function bkap_zoom_redirect_url_callback() {

			$copy_clipboard_str = __( 'Copied!', 'woocommerce-booking' );
			$redirect_uri       = bkap_zoom_redirect_url();

			printf( '<input type="text" class="bkap-auth-redirect-uri" id="bkap_zoom_redirect_uri" name="bkap_zoom_redirect_uri" value="%s" size="60" readonly />', esc_attr( $redirect_uri ) );
			printf( '<a href="javascript:void(0)" style="border: 1px solid #eee;padding: 4px;" id="bkap_copy_redirect_uri" data-selector-to-copy="#bkap-zoom-redirect-uri" data-tip="%s" class="dashicons dashicons-admin-page bkap-zoom-url-copy-to-clipboard"></a><span id="bkap_redirect_uri_copied"></span>', esc_html( $copy_clipboard_str ) );
		}

		/**
		 * Connect to Zoom Link.
		 *
		 * @since 5.23.0
		 */
		public static function bkap_zoom_connect_callback() {

			$zoom_connection     = bkap_zoom_connection();
			$connect_to_zoom     = $zoom_connection->bkap_redirect_url();
			$connect_to_zoom_str = __( 'Connect to Zoom', 'woocommerce-booking' );

			printf( '<a class="button button-primary" href="%s">%s</a>', esc_url( $connect_to_zoom ), esc_html( $connect_to_zoom_str ) );
		}

		/**
		 * Zoom logout link.
		 *
		 * @since 5.23.0
		 */
		public static function bkap_zoom_logout_callback() {

			$logout_url = bkap_zoom_redirect_url() . '&logout=1';
			$logout_str = __( 'Logout', 'woocommerce-booking' );
			$connected  = __( 'Connected to Zoom.', 'woocommerce-booking' );

			printf( '<span style="color:green;font-weight:500;margin-right: 8px;">%s</span><a class="button button-secondary" href="%s">%s</a>', esc_html( $connected ), esc_url( $logout_url ), esc_html( $logout_str ) );
		}

		/**
		 * Callback function for Booking->Settings->Zoom Meetings->Assign Meeting to Booking.
		 *
		 * @since 5.2.0
		 */
		public static function bkap_zoom_adding_zoom_meetings_callback() {
			?>
			<input type="submit" name="bkap_assign_meeting_to_booking" class="button-secondary" id="bkap_assign_meeting_to_booking" value="<?php esc_attr_e( 'Assign Meeting to Bookings', 'woocommerce-booking' ); ?>">
			<br><?php esc_html_e( 'Click on Assign Meeting to Booking button to Create/Add meeting links for the Bookings which doesn\'t have the meetings.', 'woocommerce-booking' ); ?>
			<?php
		}

		/**
		 * This function will create a zoom meeting link when order status has been updated to confirmed or processing.
		 *
		 * @param int    $order_id Order ID.
		 * @param object $instance WC_Order.
		 * @since 5.8.0
		 */
		public function bkap_create_zoom_meeting_on_order_confirmed_or_processing( $order_id, $instance ) {
			global $bkap_temp_order_object;

			// $bkap_temp_order_object has been introduced to temporarily save the $order information to be used later on in a filter function where the $order object will need to be updated to incorporate the Zoom Meeting Link information.

			$order       = wc_get_order( $order_id );
			$item_values = $order->get_items();

			foreach ( $item_values as $item_id => $item_value ) {
				$booking_id = bkap_common::get_booking_id( $item_id );

				if ( $booking_id ) {

					// Get Meeting Label and use that to search if Zoom Meeting already exists for $item_id.
					$booking_data  = bkap_get_meta_data( $booking_id );

					foreach ( $booking_data as $data ) {
						$product_id    = $data['product_id'];
						$meeting_label = bkap_zoom_join_meeting_label( $product_id );

						$zoom_meeting_link = wc_get_order_item_meta( $item_id, $meeting_label, true );

						if ( ! empty( $zoom_meeting_link ) ) {
							return;
						}

						self::bkap_create_zoom_meeting( $booking_id, $data );
						$bkap_temp_order_object = wc_get_order( $order_id ); // Reload the $order object to get Zoom Link elements and save to $bkap_temp_order_object.

						do_action( 'bkap_after_zoom_meeting_created', $booking_id, $data );
					}
				}
			}
		}

		/**
		 * This function will update the Order object to reflect the Zoom Meeting link information.
		 *
		 * @param array $order_array Array of Order data.
		 * @since 5.8.0
		 */
		public function bkap_add_zoom_link_to_order_item( $order_array ) {
			global $bkap_temp_order_object;

			if ( isset( $bkap_temp_order_object ) && is_object( $bkap_temp_order_object ) ) {

				// $bkap_temp_order_object is a valid object, so we proceed.
				// Ensure that the ID of both orders (1. order from the global variable. 2. order from the filter function) are the same to be sure that we do not tamper with unrelated data.

				// $order_array['order']->get_id() - Stale data without Zoom Meeting Link.
				// $bkap_temp_order_object->get_id() - Update data with Zoom Meeting Link.

				if ( $order_array['order']->get_id() === $bkap_temp_order_object->get_id() ) {

					// Orders are similar.
					$order_array['order'] = $bkap_temp_order_object;
					$order_array['items'] = $bkap_temp_order_object->get_items();
					unset( $bkap_temp_order_object );
				}
			}
			return $order_array;
		}

		/**
		 * Manually adding the meeting link to the booking.
		 *
		 * @param array $order_array Array of Order data.
		 * @since 5.15.0
		 */
		public static function bkap_add_zoom_meeting() {

			$booking_id   = $_POST['booking_id'];
			$meeting_link = $_POST['meeting_link'];

			if ( $meeting_link == '' ) {
				$booking_data = bkap_get_meta_data( $booking_id );

				foreach ( $booking_data as $data ) {
					$meeting_info = Bkap_Zoom_Meeting_Settings::bkap_create_zoom_meeting( $booking_id, $data, 'add', false );
					$meeting_link = $meeting_info['meeting_link'];
				}
			} else {

				$meeting_link_data  = wp_parse_url( $meeting_link );
				$meeting_explode    = explode( '/', $meeting_link_data['path'] );
				$meeting_id         = $meeting_explode[2];
				$meeting_id_explode = explode( '?', $meeting_id );
				$meeting_id         = $meeting_id_explode[0];

				if ( $meeting_id != '' ) {
					$meeting      = json_decode( bkap_zoom_connection()->bkap_get_meeting_info( $meeting_id ) );
					$booking_data = bkap_get_meta_data( $booking_id );

					extract( $booking_data[0] );
					if ( isset( $meeting->join_url ) ) {

						$meeting_label      = bkap_zoom_join_meeting_label( $product_id );
						$meeting_text       = bkap_zoom_join_meeting_text( $product_id );
						$meeting_link       = $meeting->join_url;
						$order_meeting_link = $meeting_link;
						$meeting_data       = $meeting;
	
						update_post_meta( $booking_id, '_bkap_zoom_meeting_link', $meeting_link );
						update_post_meta( $booking_id, '_bkap_zoom_meeting_data', $meeting_data );
						$meeting_link = sprintf( '<a href="%s">%s</a>', $meeting->join_url, $meeting_text );
						wc_add_order_item_meta( $order_item_id, $meeting_label, $meeting_link );
	
						$meeting_info['meeting_link'] = $meeting_link;
						$meeting_info['meeting_data'] = $meeting_data;
	
						// Save Zoom Meeting Information to Order Note.
						$order_meeting_link = sprintf( '<a href="%s">%s</a>', $order_meeting_link, $order_meeting_link );
						$order_meeting_link = sprintf( __( $meeting_label . ': %s', 'woocommerce-booking' ), $order_meeting_link );
						bkap_common::save_booking_information_to_order_note( '', $parent_id, $order_meeting_link );
					} else {
						$meeting_link = $meeting->message;
						$order_obj                     = wc_get_order( $parent_id );
						/* translators: %s: Booking ID and Meeting link. */
						$meeting_msg = sprintf( __( 'Zoom Meeting Error for Booking #%1$s - %2$s', 'woocommerce-booking' ), $booking_id, $meeting->message );
						$order_obj->add_order_note( $meeting_msg );
					}
				} else {
					$meeting_link = __( 'Invalid meeting link.', 'woocommerce-booking' );
				}
			}

			wp_send_json( array( 'meeting_link' => $meeting_link ) );
		}
	}
	Bkap_Zoom_Meeting_Settings::instance();
}
?>
