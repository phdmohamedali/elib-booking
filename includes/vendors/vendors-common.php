<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan Products with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors
 * @version  4.8.0
 * @category Common Functions
 */

if ( ! class_exists( 'BKAP_Vendors' ) ) {

	/**
	 * Class containing common functions
	 *
	 * @since 4.6.0
	 */
	class BKAP_Vendors {

		/**
		 * Default constructor to add Vendor ID when Booking is placed
		 *
		 * @since 4.6.0
		 */
		public function __construct() {
			// Add Vendor ID as booking post meta.
			add_action( 'bkap_update_booking_post_meta', array( &$this, 'bkap_update_vendor_id' ), 10, 1 );
			add_action( 'init', array( &$this, 'bkap_save_front_end_resource_data' ) );

			add_action( 'wp_ajax_bkap_vendor_global_availability', array( &$this, 'bkap_vendor_global_availability' ), 5 );
		
			add_action( 'wp_ajax_bkap_vendor_global_availability_delete', array( &$this, 'bkap_vendor_global_availability_delete' ), 5 );

		}

		/**
		 * This function returns the URL for View Booking page.
		 *
		 * @param string $status Booking Status.
		 * @param string $vendor_type Type of Vendor Solution.
		 *
		 * @since 5.10.0
		 */
		public function bkap_vendor_bkap_list_url( $status, $vendor_type ) {

			switch ( $vendor_type ) {
				case 'dokan':
					$url = dokan_get_navigation_url( 'bkap-list' );
					if ( $status ) {
						$url = add_query_arg( 'booking_status', $status, $url );
					}
					break;
				case 'wcfm':
					$url = wcfm_get_bkap_list_url( $status );
					break;
				case 'wc-vendor':
					$url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'bkap-booking/bkap-list' );
					if ( $status ) {
						$url = add_query_arg( 'booking_status', $status, $url );
					}
					break;
			}

			return $url;
		}

		/**
		 * Loads the JS files for Vendor Pages.
		 *
		 * @param string $end_point Vendor Page Endpoint.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_vendor_load_scripts( $end_point ) {

			$bkap_version = get_option( 'woocommerce_booking_db_version' );
			$ajax_url     = get_admin_url() . 'admin-ajax.php';
			switch ( $end_point ) {
				case 'bkap-calendar':

					// Include the JS & CSS files that we need.
					bkap_load_scripts_class::bkap_load_dokan_calendar_view_scripts_css();
					bkap_load_scripts_class::bkap_load_dokan_calendar_view_scripts_js( $bkap_version, $ajax_url );
					$vendor_id = get_current_user_id();
					bkap_load_scripts_class::bkap_load_calendar_scripts( $bkap_version, $vendor_id );
					break;
				case 'bkap-send-reminders':
					
					wp_enqueue_script(
						'bkap-booking-reminder',
						bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-send-reminder.js', BKAP_FILE ),
						'',
						$bkap_version,
						false
					);
					wp_localize_script(
						'bkap-booking-reminder',
						'bkap_reminder_params',
						array(
							'ajax_url' => $ajax_url,
							'moved_to_trash' => __( 'Moved to trash', 'woocommerce-booking' ),
						)
					);
					break;
				case 'bkap-list':
					$_GET['post_type'] = 'bkap_booking';
					bkap_load_scripts_class::bkap_load_view_booking_scripts();
					break;
			}
		}

		/**
		 * Loads the CSs files for Vendor Pages.
		 *
		 * @param string $end_point Vendor Page Endpoint.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_vendor_load_styles( $end_point ) {

			$ajax_url     = get_admin_url() . 'admin-ajax.php';
			$bkap_version = get_option( 'woocommerce_booking_db_version' );
			$load_css     = false;

			switch ( $end_point ) {
				case 'bkap-calendar':
					bkap_load_scripts_class::bkap_load_calendar_styles( $bkap_version );
					$load_css = true;
					break;
				case 'bkap-send-reminders':
					wp_dequeue_script( 'wcv-frontend-product' );
					bkap_include_select2_scripts();
					$load_css = true;
					break;
				case 'bkap-manage-resource':
					bkap_load_scripts_class::bkap_load_resource_scripts_js( $bkap_version, $ajax_url );
					$load_css = true;
					break;
				case 'bkap-list':
					$load_css = true;
					break;
				case 'bkap-create-booking':
					$load_css = true;
					break;
				case 'bkap-dashboard':
					$load_css = true;
					break;
			}

			if ( $load_css ) {
				wp_register_style(
					'bkap-vendor-compatiblity',
					bkap_load_scripts_class::bkap_asset_url( '/assets/css/bkap-vendor-compatiblity.css', BKAP_FILE ),
					array(),
					'1.0'
				);
				wp_enqueue_style( 'bkap-vendor-compatiblity' );

				do_action( 'bkap_vendor_load_styles', $end_point );
			}
		}

		/**
		 * Adds the vendor ID as booking post meta
		 *
		 * @param string|int $booking_id Booking ID
		 * @since 4.6.0
		 */
		function bkap_update_vendor_id( $booking_id ) {

			// Booking object
			$booking = new BKAP_booking( $booking_id );

			// Product ID
			$product_id = $booking->get_product_id();

			// get the post record
			$post = get_post( $product_id );

			// get the post author
			$vendor_id = $post->post_author;

			update_post_meta( $booking_id, '_bkap_vendor_id', $vendor_id );
		}

		/**
		 * Return the count of bookings present for the given vendor
		 *
		 * @param string|int $user_id Vendor ID
		 * @return int Post Count
		 * @since 4.6.0
		 */
		public static function get_resources_count( $user_id, $additional_args = array() ) {

			$args = array(
				'post_type'   => 'bkap_resource',
				'numberposts' => -1,
				'post_status' => array( 'publish' ),
				/* 'meta_key'    => '_bkap_vendor_id',
				'meta_value'  => $user_id, */
			);

			$args = array_merge( $args, $additional_args );

			$posts_count = count( get_posts( $args ) );

			return $posts_count;
		}

		/**
		 * Return the count of reminders present for the given vendor
		 *
		 * @param string|int $user_id Vendor ID
		 * @return int Post Count
		 * @since 5.14.0
		 */
		public static function get_reminder_count( $user_id, $additional_args = array() ) {

			$args = array(
				'post_type'   => 'bkap_reminder',
				'numberposts' => -1,
				'post_status' => array( 'bkap-active', 'bkap-inactive' ),
				/* 'meta_key'    => '_bkap_vendor_id',
				'meta_value'  => $user_id, */
			);

			$args = array_merge( $args, $additional_args );

			$posts_count = count( get_posts( $args ) );

			return $posts_count;
		}

		/**
		 * This function is to fetch all Resources.
		 *
		 * @todo Fetching the resource data based on the vendor is still pending.
		 *
		 * @since 5.10.0
		 */
		public static function get_resource_data( $user_id, $start, $limit, $additional_args = array() ) {
			$args = array(
				'post_type'   => 'bkap_resource',
				'numberposts' => $limit,
				'post_status' => array( 'publish' ),
				/* 'meta_key'    => '_bkap_vendor_id',
				'meta_value'  => $user_id, */
				'author'      => $user_id,
				'paged'       => $start,
			);

			$args = array_merge( $args, $additional_args );

			$args = apply_filters( 'bkap_vendor_resource_data_args', $args, $user_id, $start, $limit );

			return get_posts( $args );
		}

		/**
		 * This function is to fetch all Reminders.
		 *
		 * @todo Fetching the resource data based on the vendor is still pending.
		 *
		 * @since 5.10.0
		 */
		public static function get_reminder_data( $user_id, $start, $limit, $additional_args = array() ) {
			$args = array(
				'post_type'   => 'bkap_reminder',
				'numberposts' => $limit,
				'post_status' => array( 'bkap-active', 'bkap-inactive' ),
				/* 'meta_key'    => '_bkap_vendor_id',
				'meta_value'  => $user_id, */
				'author'      => $user_id,
				'paged'       => $start,
			);

			$args = array_merge( $args, $additional_args );

			$args = apply_filters( 'bkap_vendor_reminder_data_args', $args, $user_id, $start, $limit );

			return get_posts( $args );
		}

		/**
		 * Creating Resource and Saving Resource Data from front end.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_save_front_end_resource_data() {

			if ( isset( $_POST['bkap_resource_manager'] ) && '' !== $_POST['bkap_resource_manager'] ) {

				$resource_title = sanitize_text_field( $_POST['bkap_resource_title'] );

				if ( isset( $_POST['bkap_resource_id'] ) && '' !== $_POST['bkap_resource_id'] ) {

					$resource_id = (int) $_POST['bkap_resource_id'];
					wp_update_post(
						array(
							'ID'         => $resource_id,
							'post_title' => $resource_title,
						)
					);

				} else {
					$resource_title = sanitize_text_field( $_POST['bkap_resource_title'] );
					$resource_id    = wp_insert_post(
						array(
							'post_title'   => $resource_title,
							'menu_order'   => wc_clean( $_POST['_bkap_resource_menu_order'] ),
							'post_content' => '',
							'post_status'  => 'publish',
							'post_author'  => get_current_user_id(),
							'post_type'    => 'bkap_resource',
						),
						true
					);
				}

				if ( $resource_id && ! is_wp_error( $resource_id ) ) {

					$meta_args = array(
						'_bkap_resource_qty'          => wc_clean( $_POST['_bkap_booking_qty'] ),
						'_bkap_resource_menu_order'   => wc_clean( $_POST['_bkap_resource_menu_order'] ),
						'_bkap_resource_availability' => bkap_get_posted_availability(),
						'_bkap_resource_meeting_host' => bkap_get_posted_meeting_host(),
					);

					// run a foreach and save the data.
					foreach ( $meta_args as $key => $value ) {
						update_post_meta( $resource_id, $key, $value );
					}

					$resource_created_url = $_POST['bkap_resource_url'] . '?bkap-resource=' . $resource_id;
					wp_safe_redirect( $resource_created_url );
					exit();
				}
			}

			if ( isset( $_POST['bkap_reminder_manager'] ) && '' !== $_POST['bkap_reminder_manager'] ) {
				$reminder_title = sanitize_text_field( $_POST['bkap_reminder_title'] );

				if ( isset( $_POST['bkap_reminder_id'] ) && '' !== $_POST['bkap_reminder_id'] ) {

					$reminder_id = (int) $_POST['bkap_reminder_id'];
					wp_update_post(
						array(
							'ID'          => $reminder_id,
							'post_title'  => $reminder_title,
							'post_status' => wc_clean( $_POST['bkap_reminder_action'] ),
						)
					);

				} else {
					$reminder_title = sanitize_text_field( $_POST['bkap_reminder_title'] );
					$reminder_id    = wp_insert_post(
						array(
							'post_title'   => $reminder_title,
							'post_content' => wc_clean( $_POST['bkap_email_content'] ),
							'post_status'  => wc_clean( $_POST['bkap_reminder_action'] ),
							'post_author'  => get_current_user_id(),
							'post_type'    => 'bkap_reminder',
						),
						true
					);
				}

				if ( $reminder_id && ! is_wp_error( $reminder_id ) ) {

					bkap_reminder_save_data( $reminder_id );

					$reminder_created_url = $_POST['bkap_reminder_url'] . '?bkap-reminder=' . $reminder_id;
					wp_safe_redirect( $reminder_created_url );
					exit();
				}
			}
		}

		/**
		 * Return the count of bookings present for the given vendor
		 *
		 * @param string|int $user_id Vendor ID
		 * @return int Post Count
		 * @since 4.6.0
		 */
		public static function get_bookings_count( $user_id, $additional_args = array() ) {

			$user_meta  = get_userdata( $user_id );
			$user_roles = $user_meta->roles;

			$args = array(
				'post_type'   => 'bkap_booking',
				'numberposts' => -1,
				'post_status' => array( 'all' )
			);

			if ( empty( array_intersect( $user_roles, array( 'administrator', 'shop_manager' ) ) ) ) {
				$args['meta_key']   = '_bkap_vendor_id';
				$args['meta_value'] = $user_id;
			}

			$args = array_merge( $args, $additional_args );

			$posts_count = count( get_posts( $args ) );

			return $posts_count;

		}

		/**
		 * Calculate the number of pages
		 *
		 * @param string|int $user_id Vendor ID
		 * @param int        $per_page Number of records per page
		 * @return int Number of Pages
		 * @since 4.6.0
		 */
		function get_number_of_pages( $user_id, $per_page, $custom_post, $args = array() ) {

			switch ( $custom_post ) {
				case 'bkap_booking':
					$total_count = $this->get_bookings_count( $user_id, $args );
					break;
				case 'bkap_resource':
					$total_count = $this->get_resources_count( $user_id, $args );
					break;
				case 'bkap_reminder':
					$total_count = $this->get_reminder_count( $user_id, $args );
					break;
			}

			$number_of_pages = 0;
			if ( $total_count > 0 ) {
				$number_of_pages = ceil( $total_count / $per_page );
			}

			return $number_of_pages;

		}

		/**
		 * Preparing Meta Query Data based on the available filters
		 *
		 * @since 5.10.0
		 */
		public static function bkap_filtered_data_meta_query() {

			global $wpdb;

			$current_timestamp = current_time( 'timestamp' );
			$current_time      = date( 'YmdHis', $current_timestamp );
			$current_date      = date( 'Ymd', $current_timestamp );
			$date              = ( isset( $_REQUEST['m'] ) && '' !== $_REQUEST['m'] ) ? $_REQUEST['m'] : '';
			$meta_arguments    = array();

			if ( ! empty( $_REQUEST['bkap_filter_products'] ) && ! empty( $_REQUEST['bkap_filter_views'] ) ) {

				switch ( $_REQUEST['bkap_filter_views'] ) {
					case 'today_onwards':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_start',
								'value'   => $current_time,
								'compare' => '>=',
							),
							array(
								'key'   => '_bkap_product_id',
								'value' => absint( $_REQUEST['bkap_filter_products'] ),
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'today_checkin':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_start',
								'value'   => $current_date,
								'compare' => 'LIKE',
							),
							array(
								'key'   => '_bkap_product_id',
								'value' => absint( $_REQUEST['bkap_filter_products'] ),
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'today_checkout':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_end',
								'value'   => $current_date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $current_date,
								'compare' => 'NOT LIKE',
							),
							array(
								'key'   => '_bkap_product_id',
								'value' => absint( $_REQUEST['bkap_filter_products'] ),
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'custom_dates':
						$startdate    = isset( $_REQUEST[ 'bkap_custom_startdate' ] ) && '' !== $_REQUEST[ 'bkap_custom_startdate' ] ? $_REQUEST[ 'bkap_custom_startdate' ] : $current_date;
						$enddate      = isset( $_REQUEST[ 'bkap_custom_enddate' ] ) && '' !== $_REQUEST[ 'bkap_custom_enddate' ] ? $_REQUEST[ 'bkap_custom_enddate' ] : $startdate;
						$from_date    = date( 'YmdHis', strtotime( $startdate . '00:00:00' ) );
						$to_date      = date( 'YmdHis', strtotime( $enddate . '23:59:59' ) );
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_end',
								'value'   => array( $from_date, $to_date ),
								'type'    => 'NUMERIC',
								'compare' => 'BETWEEN'
							),
							array(
								'key'     => '_bkap_start',
								'value'   => array( $from_date, $to_date ),
								'type'    => 'NUMERIC',
								'compare' => 'BETWEEN'
							),
							array(
								'key'   => '_bkap_product_id',
								'value' => absint( $_REQUEST['bkap_filter_products'] ),
							),
						);
						break;
				}
			} elseif ( ! empty( $_REQUEST['bkap_filter_products'] ) ) {
				$meta_arguments['meta_query'] = array(
					array(
						'key'   => '_bkap_product_id',
						'value' => absint( $_REQUEST['bkap_filter_products'] ),
					),
					array(
						'key'     => '_bkap_start',
						'value'   => $date,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_bkap_end',
						'value'   => $date,
						'compare' => 'LIKE',
					),
				);
			} elseif ( ! empty( $_REQUEST['bkap_filter_views'] ) ) {
		
				switch ( $_REQUEST['bkap_filter_views'] ) {
					case 'today_onwards':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_start',
								'value'   => $current_time,
								'compare' => '>=',
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'today_checkin':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_start',
								'value'   => $current_date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'today_checkout':
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_end',
								'value'   => $current_date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $current_date,
								'compare' => 'NOT LIKE',
							),
							array(
								'key'     => '_bkap_start',
								'value'   => $date,
								'compare' => 'LIKE',
							),
							array(
								'key'     => '_bkap_end',
								'value'   => $date,
								'compare' => 'LIKE',
							),
						);
						break;
					case 'custom_dates':
						$startdate    = isset( $_REQUEST[ 'bkap_custom_startdate' ] ) && '' !== $_REQUEST[ 'bkap_custom_startdate' ] ? $_REQUEST[ 'bkap_custom_startdate' ] : $current_date;
						$enddate      = isset( $_REQUEST[ 'bkap_custom_enddate' ] ) && '' !== $_REQUEST[ 'bkap_custom_enddate' ] ? $_REQUEST[ 'bkap_custom_enddate' ] : $startdate;
		
						$from_date    = date( 'YmdHis', strtotime( $startdate . '00:00:00' ) );
						$to_date      = date( 'YmdHis', strtotime( $enddate . '23:59:59' ) );
		
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_end',
								'value'   => array( $from_date, $to_date ),
								'type'    => 'NUMERIC',
								'compare' => 'BETWEEN'
							),
							array(
								'key'     => '_bkap_start',
								'value'   => array( $from_date, $to_date ),
								'type'    => 'NUMERIC',
								'compare' => 'BETWEEN'
							),
						);
						break;
				}
			} elseif ( ! empty( $_REQUEST['bkap_filter_customer'] ) ) {
		
				$customer = $wpdb->get_row(
					'SELECT `' . $wpdb->prefix . 'wc_customer_lookup`.first_name, `' . $wpdb->prefix . 'wc_customer_lookup`.last_name FROM `' . $wpdb->prefix . 'wc_customer_lookup` WHERE `' . $wpdb->prefix . 'wc_customer_lookup`.customer_id = ' . absint( $_REQUEST['bkap_filter_customer'] )
				);
		
				if ( is_object( $customer ) && count( (array) $customer ) > 0 ) {
		
					// To cater for instances where more than one customer may have the same last name or first name, we then get an array of ALL Post IDs that have a combination with either First Name or Last Name as Customer Name.
		
					$all_post_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT post_id FROM {$wpdb->postmeta} WHERE ( meta_key = '_billing_first_name' AND meta_value = %s ) OR ( meta_key = '_billing_last_name' AND meta_value = %s )",
							$customer->first_name,
							$customer->last_name
						)
					);
		
					// Sort array values by count. Post ID of the customer to be searched for would have the count as 2, i.e. Post ID appears once for the First Name and then appears a second time for the last name. So we check for a count of 2.
		
					$sorted_post_ids = array_count_values( $all_post_ids );
					$post_ids        = array();
		
					foreach ( $sorted_post_ids as $_post_id => $count ) {
						if ( 2 === $count ) {
							$post_ids[] = $_post_id;
						}
					}
		
					if ( is_array( $post_ids ) && count( $post_ids ) > 0 ) {
						$meta_arguments['meta_query'] = array(
							array(
								'key'     => '_bkap_parent_id',
								'value'   => $post_ids,
								'compare' => 'IN',
							),
						);
					}
				}
			}
		
			if ( isset( $_REQUEST['orderby'] ) ) {
				$meta_arguments['orderby'] = 'ID';
		
				switch ($_REQUEST['orderby']) {
					case 'bkap_id':
						$query->query_vars['orderby'] = 'ID';
						break;
					case 'status':
						$query->query_vars['orderby'] = 'post_status';
						break;
					case 'bkap_start_date':
						$query->query_vars['orderby'] = 'meta_value_num';
						$query->query_vars['meta_key'] = '_bkap_start';
						break;
					case 'bkap_end_date':
						$query->query_vars['orderby'] = 'meta_value_num';
						$query->query_vars['meta_key'] = '_bkap_end';
						break;
					case 'bkap_order_date':
						$query->query_vars['orderby'] = 'post_date';
						break;
				}
			}
			if ( isset( $_REQUEST['order'] ) ) {
				$meta_arguments['order'] = $_REQUEST['order'];
			}

			return $meta_arguments;
		}

		/**
		 * Return the booking posts for a given vendor per page
		 *
		 * @param string|int $user_id Vendor ID
		 * @param int        $start Page Index from where to fetch data
		 * @param int        $limit Number of records to limit
		 * @return array Booking Data
		 * @since 4.6.0
		 * @access public
		 */
		public static function get_booking_data( $user_id, $start, $limit, $status = array( 'all' ), $additional_args = array() ) {

			$user_meta  = get_userdata( $user_id );
			$user_roles = $user_meta->roles;

			$args = array(
				'post_type'   => 'bkap_booking',
				'numberposts' => $limit,
				'post_status' => $status,
				'paged'       => $start,
			);

			if ( empty( array_intersect( $user_roles, array( 'administrator', 'shop_manager' ) ) ) ) {
				$args['meta_key']   = '_bkap_vendor_id';
				$args['meta_value'] = $user_id;
			}

			$args['meta_query'] = $additional_args;

			$args = apply_filters( 'bkap_vendor_booking_data_args', $args, $user_id, $start, $limit );

			$posts_data = get_posts( $args );

			$bookings_data = array();

			foreach ( $posts_data as $k => $value ) {

				// Booking ID
				$booking_id = $value->ID;
				// $bookings_data[ 'id' ] = $booking_id;

				// Booking Object
				$booking = new BKAP_Booking( $booking_id );

				// Booking Status
				$bookings_data[ $booking_id ]['status'] = $value->post_status;

				// Product Booked
				$product = $booking->get_product();

				if ( $product ) {
					$bookings_data[ $booking_id ]['product_id']   = $product->get_id();
					$bookings_data[ $booking_id ]['product_name'] = $product->get_title();
					$bookings_data[ $booking_id ]['variation_id'] = $booking->get_variation_id();
				} else {
					$bookings_data[ $booking_id ]['product_id']   = '-';
					$bookings_data[ $booking_id ]['product_name'] = '-';
					$bookings_data[ $booking_id ]['variation_id'] = '-';
				}

				// Qty
				$bookings_data[ $booking_id ]['qty'] = $booking->get_quantity();

				// Customer Name
				$customer = $booking->get_customer();

				if ( $customer->email && $customer->name ) {
					$bookings_data[ $booking_id ]['customer_name'] = esc_html( $customer->name );
				} else {
					$bookings_data[ $booking_id ]['customer_name'] = '-';
				}

				// Booking Start Date & Time
				$bookings_data[ $booking_id ]['start']        = $booking->get_start_date() . '<br>' . $booking->get_start_time();
				$bookings_data[ $booking_id ]['hidden_start'] = date( 'd-m-Y', strtotime( $booking->get_start() ) );

				// Booking End Date & Time
				$date_end = $booking->get_end_date();
				if ( '' !== $date_end ) {
					$bookings_data[ $booking_id ]['end']        = $date_end . '<br>' . $booking->get_end_time();
					$bookings_data[ $booking_id ]['hidden_end'] = date( 'd-m-Y', strtotime( $booking->get_end() ) );
				}

				// Booking Time
				$time_start = $booking->get_start_time();
				$time_end   = $booking->get_end_time();

				if ( $booking->get_selected_duration() ) {

					$bookings_data[ $booking_id ]['duration_time_slot'] = $booking->get_selected_duration_time();
					$bookings_data[ $booking_id ]['selected_duration']  = get_post_meta( $booking_id, '_bkap_duration', true );

				} elseif ( $time_start !== '' ) {
					$bookings_data[ $booking_id ]['time_slot'] = "$time_start - $time_end";
				}

				// Persons

				$bookings_data[ $booking_id ]['persons'] = $booking->get_persons_info();

				// Order ID & Status
				$order = $booking->get_order();
				if ( $order ) {
					$bookings_data[ $booking_id ]['order_id']      = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
					$bookings_data[ $booking_id ]['order_status']  = esc_html( wc_get_order_status_name( $order->get_status() ) );
					$bookings_data[ $booking_id ]['order_item_id'] = $booking->get_item_id();
				} else {
					$bookings_data[ $booking_id ]['order_id']      = 0;
					$bookings_data[ $booking_id ]['order_status']  = '-';
					$bookings_data[ $booking_id ]['order_item_id'] = 0;
				}

				// Order Date
				if ( $bookings_data[ $booking_id ]['order_id'] > 0 ) {
					$bookings_data[ $booking_id ]['order_date'] = $booking->get_date_created();
				} else {
					$bookings_data[ $booking_id ]['order_date'] = '-';
				}

				// Amount
				$amount    = $booking->get_cost();
				$final_amt = $amount * $booking->get_quantity();
				$order_id  = $booking->get_order_id();

				if ( absint( $order_id ) > 0 ) {
					$order = wc_get_order( $order_id );
					if ( $order ) {
						$currency  = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 ) ? $order->get_order_currency() : $order->get_currency();
					}
				} else {
					// get default woocommerce currency
					$currency = get_woocommerce_currency();
				}
				$currency_symbol = get_woocommerce_currency_symbol( $currency );

				$bookings_data[ $booking_id ]['amount'] = wc_price( $final_amt, array( 'currency' => $currency ) );

			}

			return $bookings_data;
		}

		/**
		 * Return Vendor Endpoints Data.
		 *
		 * @param string $vendor Vendor Type.
		 * @since 5.10.0
		 */
		public static function bkap_get_vendor_endpoints( $vendor ) {

			$endpoints = apply_filters( 'bkap_vendor_capability_options',
				array(
					array(
						'slug'   => 'bkap-dashboard',
						'name'   => __( 'Booking Dashboard', 'woocommerce-booking' ),
						'icon'   => 'fa-calendar-plus',
						'status' => apply_filters( 'bkap_dashboard_allow', true, 'bkap-dashboard', $vendor ),
					),
					array(
						'slug'   => 'bkap-create-booking',
						'name'   => __( 'Create Booking', 'woocommerce-booking' ),
						'icon'   => 'fa-calendar-plus',
						'status' => apply_filters( 'bkap_create_booking_allow', true, 'bkap-create-booking', $vendor ),
					),
					array(
						'slug'   => 'bkap-manage-resource',
						'name'   => __( 'Manage Resource', 'woocommerce-booking' ),
						'icon'   => 'fa-users',
						'status' => apply_filters( 'bkap_manage_resource_allow', true, 'bkap-manage-resource', $vendor ),
					),
					array(
						'slug'   => 'bkap-list',
						'name'   => __( 'View Bookings', 'woocommerce-booking' ),
						'icon'   => 'fa-list',
						'status' => apply_filters( 'bkap_list_allow', true, 'bkap-list', $vendor ),
					),
					array(
						'slug'   => 'bkap-calendar',
						'name'   => __( 'Calendar View', 'woocommerce-booking' ),
						'icon'   => 'fa-calendar',
						'status' => apply_filters( 'bkap_calendar_allow', true, 'bkap-calendar', $vendor ),
					),
					array(
						'slug'   => 'bkap-send-reminders',
						'name'   => __( 'Send Reminders', 'woocommerce-booking' ),
						'icon'   => 'fa-envelope',
						'status' => apply_filters( 'bkap_send_reminders_allow', true, 'bkap-send-reminders', $vendor ),
					),
				)
			);
			switch ( $vendor ) {
				case 'wcfm':
					$wcfm_page = get_wcfm_page();
					foreach ( $endpoints as $key => $value ) {
						if ( $value['status'] ) {
							$endpoints[ $key ]['url'] = wcfm_get_endpoint_url( $value['slug'], '', $wcfm_page );
						} else {
							unset( $endpoints[ $key ] );
						}
					}

					$endpoints[] = array(
						'name'   => __( 'Create Bookable', 'woocommerce-booking' ),
						'slug'   => 'bkap-create-bookable',
						'url'    => get_wcfm_edit_product_url(),
						'icon'   => 'fa-edit',
						'status' => apply_filters( 'bkap_bookable_allow', true, 'bkap-create-bookable', $vendor ),
					);
					break;
				case 'dokan':
					foreach ( $endpoints as $key => $value ) {
						if ( $value['status'] ) {
							$endpoints[ $key ]['url'] = dokan_get_navigation_url( $value['slug'] );
						} else {
							unset( $endpoints[ $key ] );
						}
					}
					$endpoints[] = array(
						'name'   => __( 'Create Bookable', 'woocommerce-booking' ),
						'slug'   => 'bkap-create-bookable',
						'url'    => dokan_get_navigation_url( 'new-product' ),
						'icon'   => 'fa-edit',
						'status' => apply_filters( 'bkap_bookable_allow', true, 'bkap-create-bookable', $vendor ),
					);
					
					break;
				case 'wc-vendor':
					$wc_vendor_url = WCVendors_Pro_Dashboard::get_dashboard_page_url( 'bkap-booking' );
					foreach ( $endpoints as $key => $value ) {
						if ( $value['status'] ) {
							$endpoints[ $key ]['url'] = $wc_vendor_url . '/' . $value['slug'];
						} else {
							unset( $endpoints[ $key ] );
						}
					}
					$endpoints[] = array(
						'name'   => __( 'Create Bookable', 'woocommerce-booking' ),
						'slug'   => 'bkap-create-bookable',
						'url'    => WCVendors_Pro_Dashboard::get_dashboard_page_url( 'product' ) . '/edit/',
						'icon'   => 'fa-edit',
						'status' => apply_filters( 'bkap_bookable_allow', true, 'bkap-create-bookable', $vendor ),
					);
					
					break;
			}

			return apply_filters( 'bkap_get_vendor_endpoints', $endpoints, $vendor );
		}

		/**
		 * Return true if the user is vendor.
		 *
		 * @param int $vendor_id Vendor ID.
		 * @since 5.10.0
		 */
		public static function bkap_is_vendor( $vendor_id ) {
			
			$user_meta  = get_userdata( $vendor_id );
			$user_roles = $user_meta->roles;
			$vendors    = array( 'seller', 'wcfm_vendor', 'vendor' );
			$is_vendor  = false;
			foreach ( $user_roles as $key => $value ) {
				if ( in_array( $value, $vendors ) ) {
					$is_vendor = true;
					break;
				}
			}

			return $is_vendor;
		}

		/**
		 * This function fetch the data for getting automatic reminder hours based on Vendor.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_vendor_reminder_hours() {

			global $wpdb;

			$option_names = $wpdb->get_results(
				"SELECT * FROM $wpdb->options WHERE option_name LIKE 'bkap_vendor_reminder_settings_%'",
				ARRAY_A
			);

			$vendor_hours = array();
			foreach ( $option_names as $key => $value ) {
				$value_option_explode       = explode( '_', $value['option_name'] );
				$unserialized               = json_decode( $value['option_value'] );
				$vendor_id                  = $value_option_explode[4];
				$vendor_hours[ $vendor_id ] = (int) $unserialized->reminder_email_before_hours;
			}

			return $vendor_hours;
		}

		/**
		 * This function fetch the data for getting SMS reminder setting data based on Vendor.
		 *
		 * @since 5.10.0
		 */
		public static function bkap_vendor_sms_settings() {

			global $wpdb;

			$option_names = $wpdb->get_results(
				"SELECT * FROM $wpdb->options WHERE option_name LIKE 'bkap_vendor_sms_settings_%'",
				ARRAY_A
			);

			$vendor_sms_settings = array();
			foreach ( $option_names as $key => $value ) {
				$value_option_explode              = explode( '_', $value['option_name'] );
				$unserialized                      = unserialize( $value['option_value'] );
				$from       = '';
				$acc_id     = '';
				$auth_token = '';
				$body       = '';

				if ( isset( $unserialized['from'] ) && '' !== $unserialized['from'] ) {
					$from = $unserialized['from'];
				}
				if ( isset( $unserialized['account_sid'] ) && '' !== $unserialized['account_sid'] ) {
					$acc_id = $unserialized['account_sid'];
				}
				if ( isset( $unserialized['auth_token'] ) && '' !== $unserialized['auth_token'] ) {
					$auth_token = $unserialized['auth_token'];
				}

				if ( isset( $unserialized['body'] ) && '' !== $unserialized['body'] ) {
					$body = $unserialized['body'];
				}

				$twilio_details = array(
					'sid'        => $acc_id,
					'token'      => $auth_token,
					'from'       => $from,
					'body'       => $body,
				);
				$vendor_id                         = $value_option_explode[4];
				$vendor_sms_settings[ $vendor_id ] = $twilio_details;
			}

			return $vendor_sms_settings;
		}

		/**
		 * Allow the vendor to delete global holidays for their products
		 * from Booking->Calendar View.
		 *
		 * @since 5.0.0
		 */
		public function bkap_vendor_global_availability_delete() {

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
		public function bkap_vendor_global_availability() {

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
			} else {
				_e( 'Your request could be processed because no bookable products were found', 'woocommerce-booking' );
				die();
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

	} // end of class
	$bkap_vendors = new BKAP_Vendors();
}
