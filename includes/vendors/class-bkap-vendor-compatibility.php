<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for making Booking and Appointment compatible with Vendor plugins
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendor-Compatiblity
 * @category Classes
 */

if ( ! class_exists( 'Bkap_Vendor_Compatibility' ) ) {

	/**
	 * Class for Vendor Compatibility.
	 *
	 * @since 5.10.0
	 */
	class Bkap_Vendor_Compatibility {

		/**
		 * Initialize and attach functions to hooks
		 *
		 * @since 5.10.0
		 */
		function __construct() {

			add_action( 'bkap_vendor_feature_header', array( $this, 'bkap_vendor_navigation_icons_in_header' ), 10, 3 );
            add_action( 'bkap_vendor_feature_content', array( $this, 'bkap_vendor_feature_content' ), 10, 3 );

			add_action( 'bkap_automatic_reminder_email_settings', array( &$this, 'bkap_vendor_automatic_reminder_email_settings_html' ), 1 );
			add_action( 'bkap_reminder_email_heading', array( $this, 'bkap_vendor_feature_content_hidden_data' ), 10 );
			
			add_filter( 'bkap_get_bookings_args_for_manual_reminder', array( $this, 'bkap_vendor_bookings_args_for_manual_reminder' ), 10, 1 );
			add_filter( 'bkap_get_product_args_for_manual_reminder', array( $this, 'bkap_vendor_product_args_for_manual_reminder' ), 10, 1 );

			add_filter( 'bkap_manual_reminder_email_subject', array( $this, 'bkap_vendor_manual_reminder_email_subject' ), 10, 1 );
			add_filter( 'bkap_manual_reminder_email_content', array( $this, 'bkap_vendor_manual_reminder_email_content' ), 10, 1 );

			add_filter( 'bkap_sms_settings', array( $this, 'bkap_vendor_sms_settings' ), 10, 1 );

			add_filter( 'bkap_resource_link_on_front_end', array( $this, 'bkap_vendor_resource_link_on_front_end' ), 10, 3 );

			add_filter( 'bkap_all_resources_link', array( $this, 'bkap_all_resources_link' ), 10, 1 );
			add_filter( 'bkap_resource_link_booking_metabox', array( $this, 'bkap_resource_link_booking_metabox' ), 10, 2 );

		}

		/**
		 * Modify Resource URL for front end.
		 * 
		 * @param string $edit_resource_url Resource URL.
		 * @param int $resource_id Resource ID.
		 *
		 * @since 5.10.0
		 */
		public function bkap_resource_link_booking_metabox( $edit_resource_url, $resource_id ) {
			global $wp;
			
			if ( ! is_admin() ) {
				$all_resource_url  = $this->bkap_all_resources_link( '' );
				$edit_resource_url = $this->bkap_vendor_resource_link_on_front_end( $edit_resource_url, $resource_id, $all_resource_url );
			}

			return $edit_resource_url; 
		}

		/**
		 * Modify All Resources URL for front end.
		 * 
		 * @param string $all_resource_url All Resources URL.
		 *
		 * @since 5.10.0
		 */
		public function bkap_all_resources_link( $all_resource_url ) {
			global $wp;

			if ( ! is_admin() ) {
				$vendor = '';

				if ( isset( $wp->query_vars['wcfm-products-manage'] ) ) {
					$vendor = 'wcfm';
				}

				if ( isset( $wp->query_vars['products'] ) ) {
					$vendor = 'dokan';
				}

				if ( isset( $wp->query_vars['object'] ) && 'product' == $wp->query_vars['object'] ) {
					$vendor = 'wc-vendor';
				}

				switch ( $vendor ) {
					case 'wcfm':
						$all_resource_url = wcfm_get_bkap_manage_resource_url();
						break;
					case 'dokan':
						$all_resource_url = dokan_get_navigation_url( 'bkap-manage-resource' );
						break;
					case 'wc-vendor':
						$all_resource_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'bkap-booking/bkap-manage-resource' );
						break;
				}
			}

			return $all_resource_url;
		}

		/**
		 * Modifying the Edit Resource Link on the front end.
		 *
		 * @param string $resource_edit_link Resource Edit Link.
		 * @param int    $resource_id Resource ID.
		 * @param string $manage_resource_link Manage Resource URL.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_resource_link_on_front_end( $resource_edit_link, $resource_id, $manage_resource_link ) {
			
			$resource_edit_link  = $manage_resource_link;
			$resource_edit_link .= '?bkap-resource=' . $resource_id;

			return $resource_edit_link;
		}

		/**
		 * Getting the SMS Settings according to the Vendor.
		 *
		 * @param array $sms_settings SMS Reminder Settings Data.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_sms_settings( $sms_settings ) {

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				$sms_settings = get_option( 'bkap_vendor_sms_settings_' . $vendor_id );
			}

			return $sms_settings;
		}

		/**
		 * Manual Reminder Content option Based on the Vendor.
		 *
		 * @param string $content Email Content String.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_manual_reminder_email_content( $content ) {

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );

			if ( $is_vendor ) {
				$saved_message = get_option( 'bkap_vendor_reminder_message_' . $vendor_id, '' );
				if ( isset( $saved_message ) && '' != $saved_message ) { // phpcs:ignore
					$content = $saved_message;
				} else {
					$content = 'Hi {customer_first_name},
	
You have a booking of {product_title} on {start_date}. 

Your Order # : {order_number}
Order Date : {order_date}
Your booking id is: {booking_id}';
				}
			}
			return $content;
		}

		/**
		 * Manual Reminder Subject option Based on the Vendor.
		 *
		 * @param string $email_subject Email Subject String.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_manual_reminder_email_subject( $email_subject ) {

			$vendor_id = get_current_user_id();
			$is_vendor = BKAP_Vendors::bkap_is_vendor( $vendor_id );
			if ( $is_vendor ) {
				$saved_subject = get_option( 'bkap_vendor_reminder_subject_' . $vendor_id, '' );
				if ( isset( $saved_subject ) && '' != $saved_subject ) { // phpcs:ignore
					$email_subject = $saved_subject;
				} else {
					$email_subject = __( 'Booking Reminder', 'woocommerce-booking' );
				}
			}

			return $email_subject;
		}

		/**
		 * This function will add args to fetch products created by the current user.
		 *
		 * @param string $args Additional Arguments.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_product_args_for_manual_reminder( $args ) {

			if ( ! is_admin() ) {
				$args = array(
					'author' => get_current_user_id(), // phpcs:ignore
				);
			}

			return $args;
		}

		/**
		 * This function will add args to fetch bookings of products that are created by the current Vendor.
		 *
		 * @param string $args Additional Arguments.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_bookings_args_for_manual_reminder( $args ) {

			if ( ! is_admin() ) {
				$args = array(
					'meta_key'   => '_bkap_vendor_id', // phpcs:ignore
					'meta_value' => get_current_user_id(), // phpcs:ignore
				);
			}

			return $args;
		}

		/**
		 * Adding Hidden Field to get the infomration about the current user.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_feature_content_hidden_data() {
			global $wp;

			if (
				false !== strpos( $wp->request, 'products-manage' ) ||
				false !== strpos( $wp->request, 'bkap-list' ) ||
				false !== strpos( $wp->request, 'bkap-calendar' ) ||
				false !== strpos( $wp->request, 'bkap-send-reminders' )
			) {

				$vendor_id = get_current_user_id();
				?>
				<input type="hidden" name="bkap_vendor_id" id="bkap_vendor_id" value="<?php echo esc_attr( $vendor_id ); ?>">
				<?php
			}
		}

		/**
		 * This function will add form for Automatic Reminder Settings on front end.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_automatic_reminder_email_settings_html() {
			if ( ! is_admin() ) {
				$vendor_id = get_current_user_id();
				Bkap_Send_Reminder::bkap_automatic_reminder_email_settings_html( $vendor_id, 'vendor' );
			}
		}

        /**
		 * Displaying Navigation Icons to the Right Side of Header on Feature page.
		 *
		 * @param string $end_point Current Endpoint.
		 * @param array  $bkap_vendor_endpoints Array of Endpoints.
		 * @param string $bkap_vendor Vendor Type.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_navigation_icons_in_header( $end_point, $bkap_vendor_endpoints, $bkap_vendor ) {

			foreach ( $bkap_vendor_endpoints as $key => $value ) {
				if ( $end_point === $value['slug'] ) {
					unset( $bkap_vendor_endpoints[ $key ] );
					break;
				}
			}

			// Loading Dashboard Page from Booking Plugin.
			wc_get_template(
				'bkap-booking-feature-navigation-links.php',
				array(
					'bkap_vendor_endpoints' => $bkap_vendor_endpoints,
					'bkap_vendor'           => $bkap_vendor,
					'end_point'             => $end_point,
				),
				'woocommerce-booking/',
				BKAP_VENDORS_TEMPLATE_PATH
			);
		}

        /**
		 * This function will load the view according to the selected module.
		 *
		 * @param string $end_point Current Endpoint.
		 * @param array  $bkap_vendor_endpoints Array of Endpoints.
		 * @param string $bkap_vendor Vendor Type.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_feature_content( $end_point, $bkap_vendor_endpoints, $bkap_vendor ) {

			switch ( $end_point ) {
				case 'bkap-list':
					
					switch ( $bkap_vendor ) {
						case 'wc-vendor':
							bkap_load_scripts_class::bkap_wcv_dashboard_css( BKAP_VERSION );
						break;
					}
					include_once BKAP_VENDORS_TEMPLATE_PATH . 'bkap-view-bookings.php';
					break;
				case 'bkap-calendar':
					wc_get_template(
						'dokan/bkap-dokan-vendor-global-availability.php',
						array(),
						'woocommerce-booking/',
						BKAP_VENDORS_TEMPLATE_PATH
					);
					// Including the template from core plugin.
					wc_get_template(
						'bkap-calendar-view.php',
						array(),
						'woocommerce-booking/',
						BKAP_BOOKINGS_TEMPLATE_PATH
					);
					break;
				case 'bkap-create-booking':
					bkap_admin_bookings::bkap_create_booking_page();
					break;
				case 'bkap-manage-resource':
					$bkap_resource       = '';
					$manage_resource_url = '';
					switch ( $bkap_vendor ) {
						case 'dokan':
							$manage_resource_url = dokan_get_navigation_url( 'bkap-manage-resource' );
							break;
						case 'wcfm':
							$manage_resource_url = wcfm_get_bkap_manage_resource_url();
							break;
						case 'wc-vendor':
							$manage_resource_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'bkap-booking/bkap-manage-resource' );
							break;
						default:
							# code...
							break;
					}

					if ( isset( $_GET['bkap-resource'] ) && '' !== $_GET['bkap-resource'] ) { // phpcs:ignore WordPress.Security.NonceVerification
						$bkap_resource = sanitize_text_field( wp_unslash( $_GET['bkap-resource'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
						if ( 'new' === $bkap_resource ) {
							$resource_post = '';
						} else {
							$resource_post = (int) $bkap_resource;
						}

						$bkap_intervals      = bkap_intervals();
						$zoom_api_key        = get_option( 'bkap_zoom_api_key', '' );
						$zoom_api_secret     = get_option( 'bkap_zoom_api_secret', '' );
						$response            = new stdClass();
						if ( '' !== $zoom_api_key && '' !== $zoom_api_secret && BKAP_License::enterprise_license() ) {
							$zoom_connection = bkap_zoom_connection();
							$response        = json_decode( $zoom_connection->bkap_list_users() );
						}
						$edit = false;
						if ( '' === $resource_post ) {
							$resource_qty          = 1;
							$resource_availability = array();
							$zoom_host_id          = '';
							$resource_title        = '';
						} else {
							$edit                  = true;
							$resource              = new BKAP_Product_Resource( $resource_post );
							$resource_qty          = $resource->get_resource_qty();
							$resource_availability = $resource->get_resource_availability();
							$zoom_host_id          = $resource->get_resource_host();
							$resource_title        = get_the_title( $resource_post );
						}

						include_once BKAP_VENDORS_TEMPLATE_PATH . 'bkap-manage-resources.php';
					} else {
						include_once BKAP_VENDORS_TEMPLATE_PATH . 'bkap-resources.php';
					}

					if ( isset( $_GET['bkap-add-resource'] ) && '' !== $_GET['bkap-add-resource'] ) { // phpcs:ignore WordPress.Security.NonceVerification
						$bkap_resource = sanitize_text_field( wp_unslash( $_GET['bkap-add-resource'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
					}
					break;
				case 'bkap-send-reminders':
					Bkap_Send_Reminder::bkap_add_reminder_page();
					break;
				default:
					break;
			}
		}
	}
}
$bkap_vendor_compatibility = new Bkap_Vendor_Compatibility();
