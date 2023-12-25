<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/Dokan
 * @version  4.6.0
 * @category Classes
 */

if ( ! class_exists( 'bkap_dokan_class' ) ) {

	/**
	 * Class for Integrating Booking & Appointment plugin for WooCommerce with Dokan
	 *
	 * @since    4.6.0
	 */
	class bkap_dokan_class {


		private $vendor_type = 'dokan';

		/**
		 * Default Constructor. Include dependency files and hook functions to actions & filters
		 *
		 * @since 4.6.0
		 */
		public function __construct() {

			self::bkap_dokan_include_dependencies();

			add_filter( 'dokan_get_dashboard_nav', array( &$this, 'bkap_dokan_add_booking_nav' ) );

			add_filter( 'dokan_query_var_filter', array( &$this, 'bkap_dokan_query_var_filter' ) );

			add_action( 'dokan_rewrite_rules_loaded', array( $this, 'bkap_add_rewrite_rules' ) );

			add_action( 'dokan_load_custom_template', array( &$this, 'bkap_dokan_include_template' ), 10, 1 );

			add_action( 'admin_init', array( &$this, 'bkap_remove_menu' ) );

			add_action( 'wp_ajax_bkap_dokan_display_availability', array( &$this, 'bkap_dokan_display_availability' ), 5 );

			add_action( 'dokan_enqueue_scripts', array( $this, 'bkap_dokan_enqueue_scripts' ), 10 );

			add_filter( 'bkap_after_successful_manual_booking', array( $this, 'bkap_dokan_modify_manual_booking_redirect_url' ), 10, 2 );
			add_action( 'bkap_manual_booking_created_with_new_order', array( $this, 'bkap_dokan_manual_booking_created_with_new_order' ), 10, 1 );

			add_action( 'bkap_after_reminder_settings', array( &$this, 'bkap_after_reminder_settings' ) );
		}

		/**
		 * This function inserts the order data in Dokan table.
		 *
		 * @param int    $order_id ID of the order.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_dokan_manual_booking_created_with_new_order( $order_id ) {

			if ( function_exists( 'dokan_sync_insert_order' ) ) {
				dokan_sync_insert_order( $order_id );
			}
		}

		/**
		 * This function prepares the redirect url after manualy creating the booking.
		 *
		 * @param string $redirect_url Edit Order page URL.
		 * @param int    $order_id ID of the order.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_dokan_modify_manual_booking_redirect_url( $redirect_url, $order_id ) {

			global $wp;

			$base_url = isset( $_SERVER['REQUEST_URI'] ) && ! empty( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ( '/' . $wp->request . '/' );


			if ( false !== strpos( $base_url, 'bkap-create-booking' ) || false !== strpos( $base_url, 'bkap-calendar' ) ) {
				$redirect_url = add_query_arg( '_wpnonce', wp_create_nonce( 'dokan_view_order' ), add_query_arg( [ 'order_id' => $order_id ], dokan_get_navigation_url( 'orders' ) ) );
			}

			return $redirect_url;
		}

		/**
		 * This function will load styles and scripts.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_dokan_enqueue_scripts() {

			global $wp;

			$end_point = '';
			if ( isset( $wp->query_vars['bkap-dashboard'] ) ) {
				$end_point = 'bkap-dashboard';
			} else if ( isset( $wp->query_vars['bkap-create-booking'] ) ) {
				$end_point = 'bkap-create-booking';
			} else if ( isset( $wp->query_vars['bkap-manage-resource'] ) ) {
				$end_point = 'bkap-manage-resource';
			} else if ( isset( $wp->query_vars['bkap-list'] ) ) {
				$end_point = 'bkap-list';
			} else if ( isset( $wp->query_vars['bkap-calendar'] ) ) {
				$end_point = 'bkap-calendar';
			} else if ( isset( $wp->query_vars['bkap-send-reminders'] ) ) {
				$end_point = 'bkap-send-reminders';
			}
			if ( '' !== $end_point ) {
				BKAP_Vendors::bkap_vendor_load_scripts( $end_point );
				BKAP_Vendors::bkap_vendor_load_styles( $end_point );
			}
		}

		/**
		 * Remove Booking Menu for Vendors Admin Dashboard
		 *
		 * @since 4.6.0
		 */
		public function bkap_remove_menu() {

			if ( current_user_can( 'vendor' ) && ! is_multisite() ) {
				remove_menu_page( 'edit.php?post_type=bkap_booking' );
			}
		}

		/**
		 * Include dependent files
		 *
		 * @since 4.6.0
		 */
		public static function bkap_dokan_include_dependencies() {

			include_once BKAP_VENDORS_INCLUDES_PATH . 'dokan/class-bkap-dokan-products.php';
			include_once BKAP_VENDORS_INCLUDES_PATH . 'dokan/class-bkap-dokan-orders.php';
			include_once BKAP_VENDORS_INCLUDES_PATH . 'dokan/class-bkap-dokan-calendar.php';
		}

		/**
		 * Add Booking Menu to vendors Dashboard on Frontend
		 *
		 * @param array $urls Array containing existing Menu URLs.
		 * @return array URL Array.
		 * @since 4.6.0
		 */
		public function bkap_dokan_add_booking_nav( $urls ) {

			$urls['bkap-dashboard'] = array(
				'title'      => __( 'Booking', 'woocommerce-booking' ),
				'icon'       => '<i class="wp-menu-image dashicons-before dashicons-calendar-alt"></i>',
				'url'        => dokan_get_navigation_url( 'bkap-dashboard' ),
				'pos'        => '51',
			);

			return $urls;
		}

		/**
		 * Add Booking Query var to the existing query vars
		 *
		 * @param array $url_array Array of URL.
		 * @return array Array of URL after modification.
		 * @since 4.6.0
		 */
		public function bkap_dokan_query_var_filter( $url_array ) {

			$url_array[] = 'bkap-dashboard';
			$url_array[] = 'bkap-create-booking';
			$url_array[] = 'bkap-manage-resource';
			$url_array[] = 'bkap-list';
			$url_array[] = 'bkap-calendar';
			$url_array[] = 'bkap-send-reminders';

			return $url_array;
		}

		/**
		 * Add rewrite rules for Booking Links
		 *
		 * @since 4.6.0
		 */
		public function bkap_add_rewrite_rules() {
			flush_rewrite_rules( true );
		}

		/**
		 * Display the base template for Booking Menu
		 *
		 * @param array $query_vars Query Vars.
		 * @since 4.6.0
		 */
		public function bkap_dokan_include_template( $query_vars ) {

			$end_point = '';
			if ( isset( $query_vars['bkap-dashboard'] ) ) {
				$end_point = 'bkap-dashboard';
			} else if ( isset( $query_vars['bkap-create-booking'] ) ) {
				$end_point = 'bkap-create-booking';
			} else if ( isset( $query_vars['bkap-manage-resource'] ) ) {
				$end_point = 'bkap-manage-resource';
			} else if ( isset( $query_vars['bkap-list'] ) ) {
				$end_point = 'bkap-list';
			} else if ( isset( $query_vars['bkap-calendar'] ) ) {
				$end_point = 'bkap-calendar';
			} else if ( isset( $query_vars['bkap-send-reminders'] ) ) {
				$end_point = 'bkap-send-reminders';
			}

			$bkap_vendor = 'dokan';
			switch ( $end_point ) {
				case 'bkap-dashboard':
					$bkap_vendors          = new BKAP_Vendors();
					$bkap_vendor_endpoints = $bkap_vendors->bkap_get_vendor_endpoints( $bkap_vendor );
					array_shift( $bkap_vendor_endpoints ); // removing dashboard endpoint.
					$bkap_vendor_endpoints_group = array_chunk( $bkap_vendor_endpoints, 2 );

					include_once BKAP_VENDORS_TEMPLATE_PATH . 'dokan/bkap-dokan-main-navigation.php';
					break;
				case 'bkap-dashboard':
				case 'bkap-create-booking':
				case 'bkap-manage-resource':
				case 'bkap-list':
				case 'bkap-calendar':
				case 'bkap-send-reminders':
					include_once BKAP_VENDORS_TEMPLATE_PATH . 'dokan/bkap-dokan-feature-page.php';
					break;
			}
		}

		/**
		 * Display the Availability for Vendors in Booking->Calendar View.
		 *
		 * @since 5.0.0
		 */
		public function bkap_dokan_display_availability() {
			ob_start();
			wc_get_template(
				'dokan/bkap-dokan-vendor-global-availability.php',
				array(),
				'woocommerce-booking/',
				BKAP_VENDORS_TEMPLATE_PATH
			);
			ob_flush();
		}

		/**
		 * Allow the vendor to delete global holidays for their products
		 * from Booking->Calendar View.
		 *
		 * @since 5.0.0
		 */
		public function bkap_dokan_vendor_global_availability_delete() {

			$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

			if ( $id > 0 ) {

				global $wpdb;

				// Get the Vendor ID.
				$vendor_id = get_current_user_id();

				// update the User meta record.
				$existing_vendor_data = get_user_meta( $vendor_id, '_bkap_vendor_holidays', true );

				if ( is_array( $existing_vendor_data ) && count( $existing_vendor_data ) > 0 ) {
					foreach ( $existing_vendor_data as $availability_id => $vendor_availability ) {
						if ( $id == $vendor_availability['id'] ) {
							unset( $existing_vendor_data[ $availability_id ] );
							break;
						}
					}
				}
				update_user_meta( $vendor_id, '_bkap_vendor_holidays', $existing_vendor_data );

				// update the data for all the products.
				$get_product_ids = $wpdb->get_col( // phpcs:ignore
					$wpdb->prepare(
						'SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE post_author = %d AND post_type = %s',
						$vendor_id,
						'product'
					)
				);

				if ( is_array( $get_product_ids ) && count( $get_product_ids ) > 0 ) {
					foreach ( $get_product_ids as $product_id ) {

						// If the product is bookable.
						if ( bkap_common::bkap_get_bookable_status( $product_id ) ) {
							$get_existing = get_post_meta( $product_id, '_bkap_holiday_ranges', true );

							if ( is_array( $get_existing ) && count( $get_existing ) > 0 ) {
								foreach ( $get_existing as $existing_prd_id => $existing_prd_data ) {
									if ( $id == $existing_prd_data['id'] ) {
										unset( $get_existing[ $existing_prd_id ] );
										break;
									}
								}

								update_post_meta( $product_id, '_bkap_holiday_ranges', $get_existing );
							}
						}
					}
				}
			}
			die();
		}

		/**
		 * Add/Edit global availability for Vendors from Booking->Calendar View.
		 *
		 * @since 5.0.0
		 */
		public function bkap_dokan_vendor_global_availability() {

			global $wpdb;

			$id = 0;

			$start_date  = isset( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$end_date    = isset( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$update_type = isset( $_POST['update_type'] ) ? sanitize_text_field( wp_unslash( $_POST['update_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

			// Get the Vendor ID.
			$vendor_id = get_current_user_id();
			// update the User Meta record.
			$existing_vendor_data = get_user_meta( $vendor_id, '_bkap_vendor_holidays', true );

			if ( 'edit' === $update_type ) {
				$id = isset( $_POST['update_id'] ) ? sanitize_text_field( wp_unslash( $_POST['update_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
			} else {
				// get the new ID.
				if ( is_array( $existing_vendor_data ) && count( $existing_vendor_data ) > 0 ) {
					$id = count( $existing_vendor_data ) + 1;
				} else {
					$existing_vendor_data = array();
					$id                   = 1;
				}
			}

			// Verify that we have the start & end date & ID > 0.
			if ( '' === $start_date || '' === $end_date || 0 === $id ) {
				echo 'failed';
				die();
			}

			// Format the start & end.
			$new_range = array(
				'id'             => $id,
				'start'          => date( 'j-n-Y', strtotime( $start_date ) ), //phpcs:ignore
				'end'            => date( 'j-n-Y', strtotime( $end_date ) ), //phpcs:ignore
				'years_to_recur' => '',
				'range_type'     => 'custom_range',
				'range_name'     => $title,
			);

			$get_product_ids = $wpdb->get_col( // phpcs:ignore
				$wpdb->prepare(
					'SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE post_author = %d AND post_type = %s',
					$vendor_id,
					'product'
				)
			);

			$modified = false;
			if ( is_array( $get_product_ids ) && count( $get_product_ids ) > 0 ) {
				foreach ( $get_product_ids as $product_id ) {

					// If the product is bookable.
					if ( bkap_common::bkap_get_bookable_status( $product_id ) ) {
						$get_existing = get_post_meta( $product_id, '_bkap_holiday_ranges', true );

						if ( ! is_array( $get_existing ) ) {
							$get_existing = array();
						}

						// If we're editing, modify an existing entry.
						if ( 'edit' === $update_type ) {

							foreach ( $get_existing as $existing_prd_id => $existing_prd_data ) {
								if ( $id == $existing_prd_data['id'] ) {
									$get_existing[ $existing_prd_id ] = $new_range;
									break;
								}
							}
						} else { // else add a new entry.
							array_push( $get_existing, $new_range );
						}

						update_post_meta( $product_id, '_bkap_holiday_ranges', $get_existing );
						$modified = true;
					}
				}
			}

			// Update the User Meta record.
			if ( $modified ) {

				if ( 'edit' === $update_type ) { // update the existing entry.
					foreach ( $existing_vendor_data as $availability_id => $vendor_availability ) {
						if ( $id == $vendor_availability['id'] ) {
							$existing_vendor_data[ $availability_id ] = $new_range;
							break;
						}
					}
				} else { // create a new record.
					array_push( $existing_vendor_data, $new_range );
				}

				update_user_meta( $vendor_id, '_bkap_vendor_holidays', $existing_vendor_data );

				echo 'success';
			}
			die();
		}

		/**
		 * Loading BKAP Dokan Script on Send Reminders page.
		 *
		 * @since 5.14.0
		 */
		public function bkap_after_reminder_settings() {

			bkap_load_scripts_class::bkap_load_dokan_product_scripts_js( BKAP_VERSION, get_admin_url() . 'admin-ajax.php' );

			wp_register_script( 'jquery-tiptip', bkap_load_scripts_class::bkap_asset_url( '/assets/js/jquery.tipTip.minified.js', BKAP_FILE, false, false ), array( 'jquery' ), BKAP_VERSION, false );
			wp_enqueue_script( 'jquery-tiptip' );
			wp_dequeue_script( 'bkap-jqueryui' );
			wp_deregister_script( 'bkap-jqueryui' );
		}
	}
}

return new bkap_dokan_class();
