<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for View Bookings
 *
 * @author   Tyche Softwares
 * @package  BKAP/View-Bookings
 * @category Classes
 */

if ( ! class_exists( 'BKAP_Bookings_View' ) ) {

	/**
	 * Class for View Bookings
	 *
	 * @since 4.1.0
	 */

	class BKAP_Bookings_View {

		/**
		 * Default constructor
		 *
		 * @since 4.1.0
		 */
		public function __construct() {
			$this->type = 'bkap_booking';

			add_action( 'admin_enqueue_scripts', array( &$this, 'bkap_post_enqueue' ) );
			// Admin Columns.
			add_filter( 'manage_edit-' . $this->type . '_columns', array( &$this, 'bkap_edit_columns' ) );
			add_action( 'manage_' . $this->type . '_posts_custom_column', array( &$this, 'bkap_custom_columns' ), 2 );
			add_filter( 'manage_edit-' . $this->type . '_sortable_columns', array( &$this, 'bkap_custom_columns_sort' ), 1 );

			// Setting class name for the primary column for view in mobile.
			add_filter( 'list_table_primary_column', array( $this, 'list_table_primary_column' ), 10, 2 );
			// Altering the actions in the mobile view.
			add_filter( 'post_row_actions', array( $this, 'row_actions' ), 100, 2 );

			// Filtering.
			add_action( 'restrict_manage_posts', array( $this, 'bkap_filters' ) );
			add_filter( 'parse_query', array( $this, 'bkap_filters_query' ) );
			add_filter( 'get_search_query', array( $this, 'bkap_search_label' ) );

			// Search.
			add_filter( 'parse_query', array( $this, 'bkap_search_custom_fields' ) );

			// Actions.
			add_filter( 'bulk_actions-edit-' . $this->type, array( $this, 'bkap_bulk_actions' ), 10, 1 );
			add_filter( 'handle_bulk_actions-edit-bkap_booking', array( $this, 'bkap_bulk_action' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'bkap_bulk_admin_notices' ) );

			// ajax action for confirming bookings.
			add_action( 'admin_init', array( $this, 'bkap_booking_confirmed' ) );

			add_action( 'admin_init', array( &$this, 'bkap_export_data' ) );
			add_filter( 'months_dropdown_results', array( &$this, 'bkap_custom_date_dropdown' ) );
			add_action( 'pre_get_posts', array( &$this, 'bkap_date_meta_query' ) );

			// Change Create Bookings link.
			add_filter( 'admin_url', array( &$this, 'bkap_change_create_booking_link' ), 10, 2 );

			add_action( 'wp_ajax_bkap_do_ajax_export', array( &$this, 'bkap_do_ajax_export' ) );
			add_action( 'init', array( &$this, 'bkap_download_csv' ) );
			add_action( 'wp_ajax_woocommerce_bkap_view_bookings_json_search_customers', array( &$this, 'bkap_view_bookings_json_search_customers' ) );
		}

		/**
		 * Modify the current create booking URL to point to our new Create Booking Page.
		 *
		 * @param string $url URL in admin dashboard.
		 * @param string $path Current Path to check.
		 * @return string Modified URL.
		 * @since 4.1.0
		 */
		public function bkap_change_create_booking_link( $url, $path ) {

			if ( 'post-new.php?post_type=bkap_booking' === $path ) {
				$url = esc_url( 'edit.php?post_type=bkap_booking&page=bkap_create_booking_page' );
			}
			return $url;
		}

		/**
		 * Modify the date filter dropdown to show Booking months & year.
		 *
		 * @param mixed $months Current Months present.
		 *
		 * @global mixed $wpdb Global wpdb Object.
		 *
		 * @since 4.1.0
		 *
		 * @return mixed Months having Booking Details
		 *
		 * @hook months_dropdown_results
		 */
		public function bkap_custom_date_dropdown( $months ) {
			global $wpdb;

			$months = $wpdb->get_results(
				"SELECT DISTINCT YEAR( meta_value ) AS year, MONTH(meta_value) AS month FROM {$wpdb->prefix}woocommerce_order_itemmeta
						WHERE meta_key = '_wapbk_booking_date' OR meta_key = '_wapbk_checkout_date' ORDER BY meta_value DESC"
			);

			return $months;
		}

		/**
		 * Modify the date filter query to filter by booking start & end dates
		 *
		 * @param string $wp_query Query String to set.
		 *
		 * @since 4.1.0
		 *
		 * @hook pre_get_posts
		 */
		public function bkap_date_meta_query( $wp_query ) {

			if ( is_admin() && $wp_query->is_main_query() && 'bkap_booking' === $wp_query->get( 'post_type' ) ) {
					$m = $wp_query->get( 'm' );

				if ( ! $meta_query = $wp_query->get( 'meta_query' ) ) { // Keep meta query if there currently is one.
					$meta_query = array();
				}

				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_bkap_start',
						'value'   => $m,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_bkap_end',
						'value'   => $m,
						'compare' => 'LIKE',
					),
				);

				$wp_query->set( 'meta_query', $meta_query );
				$wp_query->set( 'm', null );
			}
		}

		/**
		 * Enqueue Scripts on View Bookings Page.
		 *
		 * @since 4.1.0
		 *
		 * @hook admin_enqueue_scripts
		 */
		public function bkap_post_enqueue() {
			wp_enqueue_script( 'bkap-jquery-tip', bkap_load_scripts_class::bkap_asset_url( '/assets/js/jquery.tipTip.minified.js', BKAP_FILE, false, false ), '', BKAP_VERSION, false );
			wp_enqueue_style( 'bkap-edit-bookings-css', bkap_load_scripts_class::bkap_asset_url( '/assets/css/edit-booking.css', BKAP_FILE ), null, BKAP_VERSION );
		}

		/**
		 * Change title boxes in admin.
		 *
		 * @param  string  $text Text to be displayed.
		 * @param  WP_Post $post Post Object.
		 * @return string
		 *
		 * @since 4.1.0
		 *
		 * @hook enter_title_here
		 */
		public function bkap_title( $text, $post ) {
			if ( 'bkap_booking' === $post->post_type ) {
				return __( 'Booking Title', 'woocommerce-booking' );
			}
			return $text;
		}

		/**
		 * Set list table primary column for bookings.
		 *
		 * @param  string $default Default Column.
		 * @param  string $screen_id Screen ID.
		 *
		 * @return string Text to be displayed
		 *
		 * @since 4.1.0
		 *
		 * @hook list_table_primary_column
		 */
		public function list_table_primary_column( $default, $screen_id ) {

			if ( 'edit-bkap_booking' === $screen_id ) {
				return 'bkap_id';
			}

			return $default;
		}

		/**
		 * Set row actions for booking.
		 *
		 * @param  array   $actions Current Actions.
		 * @param  WP_Post $post Booking Post Object.
		 *
		 * @return array Actions
		 *
		 * @since 4.1.0
		 *
		 * @hook post_row_actions
		 */
		public function row_actions( $actions, $post ) {
			if ( 'bkap_booking' === $post->post_type ) {

				if ( isset( $actions['inline hide-if-no-js'] ) ) {
					unset( $actions['inline hide-if-no-js'] );
				}

				if ( isset( $actions['view'] ) ) {
					unset( $actions['view'] );
				}

				return array_merge( array( 'id' => 'ID: ' . $post->ID ), $actions );
			}

			return $actions;
		}

		/**
		 * Change the columns shown in admin.
		 *
		 * @param array $existing_columns Exiting Columns Array.
		 * @return array Columns Modified Array.
		 *
		 * @since 4.1.0
		 *
		 * @hook manage_edit-bkap_booking_columns
		 */
		public function bkap_edit_columns( $existing_columns ) {
			if ( empty( $existing_columns ) && ! is_array( $existing_columns ) ) {
				$existing_columns = array();
			}

			unset( $existing_columns['comments'], $existing_columns['title'], $existing_columns['date'] );

			$columns                      = array();
			$columns['bkap_status']       = '<span class="status_head tips help_tip" data-tip="' . esc_attr__( 'Status', 'woocommerce-booking' ) . '"></span>';
			$columns['bkap_id']           = __( 'Booking ID', 'woocommerce-booking' );
			$columns['bkap_product']      = __( 'Booked Product', 'woocommerce-booking' );
			$columns['bkap_customer']     = __( 'Booked By', 'woocommerce-booking' );
			$columns['bkap_order']        = __( 'Order', 'woocommerce-booking' );
			$columns['bkap_start_date']   = __( 'Start Date', 'woocommerce-booking' );
			$columns['bkap_end_date']     = __( 'End Date', 'woocommerce-booking' );
			$columns['bkap_persons']      = __( 'Persons', 'woocommerce-booking' );
			$columns['bkap_qty']          = __( 'Quantity', 'woocommerce-booking' );
			$columns['bkap_amt']          = __( 'Amount', 'woocommerce-booking' );
			$columns['bkap_order_date']   = __( 'Order Date', 'woocommerce-booking' );
			$columns['bkap_zoom_meeting'] = __( 'Zoom Meeting', 'woocommerce-booking' );
			$columns['bkap_actions']      = __( 'Actions', 'woocommerce-booking' );

			return apply_filters( 'bkap_view_booking_columns', array_merge( $existing_columns, $columns ) );
		}

		/**
		 * Define our custom columns shown in admin.
		 *
		 * @param  string $column Custom Columns Array.
		 * @global object $post WP_Post
		 *
		 * @since 4.1.0
		 *
		 * @hook manage_bkap_booking_posts_custom_column
		 */
		public function bkap_custom_columns( $column ) {
			global $post;

			if ( get_post_type( $post->ID ) === 'bkap_booking' ) {
				$booking = new BKAP_Booking( $post->ID );
			}

			switch ( $column ) {
				case 'bkap_status':
					$status           = $booking->status;
					$booking_statuses = bkap_common::get_bkap_booking_statuses();
					$status_label     = ( array_key_exists( $status, $booking_statuses ) ) ? $booking_statuses[ $status ] : ucwords( $status );
					echo '<span class="status-' . esc_attr( $status ) . ' help_tip" data-tip="' . esc_attr( $status_label ) . '">' . esc_html( $status_label ) . '</span>';
					break;
				case 'bkap_id':
					printf( '<a href="%s">' . __( '#%d', 'woocommerce-booking' ) . '</a>', admin_url( 'post.php?post=' . $post->ID . '&action=edit' ), $post->ID );
					break;
				case 'bkap_customer':

					$customer = self::customer_name_data( $booking );
					if ( '' != $customer ) {
						echo $customer;
					} else {
						echo '-';
					}
					break;
				case 'bkap_product':
					$product = $booking->get_product();

					if ( $product ) {
						$product_title = $product->get_title();
						$resource_id   = $booking->get_resource();
						$variation_id  = $booking->get_variation_id();

						if ( 0 < $variation_id ) {
							$variation_obj      = new WC_Product_Variation( $variation_id );
							$variation_attr_cnt = count( $variation_obj->get_variation_attributes() );
							$product_variations = implode( ", ", $variation_obj->get_variation_attributes() );
							$product_title      = $product_title . ' - ' . $product_variations;
						}

						echo '<a href="' . admin_url( 'post.php?post=' . ( is_callable( array( $product, 'get_id' ) ) ? $product->get_id() : $product->id ) . '&action=edit' ) . '">' . $product_title . '</a>';

						if ( $resource_id != '' ) {

							
							$show_resource = apply_filters( 'bkap_display_resource_info_on_view_booking', true, $product, $resource_id );

							if ( $show_resource ) {
								$resource_title = $booking->get_resource_title();
								echo '<br>( <a href="' . admin_url( 'post.php?post=' . $resource_id . '&action=edit' ) . '">' . esc_html( $resource_title ) . '</a> )';
							}
						}
					} else {
						echo '-';
					}
					break;
				case 'bkap_persons':
					$persons = $booking->persons;
					if ( count( $persons ) > 0 ) {
						if ( isset( $persons[0] ) ) {
							echo Class_Bkap_Product_Person::bkap_get_person_label( $booking->product_id ) . ' : ' . $persons[0] . '<br>';
						} else {
							foreach ( $persons as $key => $value ) {
								echo get_the_title( $key ) . ' : ' . $value . '<br>';
							}
						}
					}

					break;
				case 'bkap_qty':
					$quantity = $booking->qty;
					echo "$quantity";
					break;
				case 'bkap_amt':
					$amount    = $booking->cost;
					$final_amt = $amount * $booking->qty;
					$order_id  = $booking->order_id;

					echo wc_price( $final_amt );
					break;
				case 'bkap_order':
					$order = $booking->get_order();
					if ( $order ) {
						echo '<a href="' . admin_url( 'post.php?post=' . ( is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id ) . '&action=edit' ) . '">#' . $order->get_order_number() . '</a><br> ' . esc_html( wc_get_order_status_name( $order->get_status() ) );
					} else {
						echo '-';
					}
					break;
				case 'bkap_start_date':
					echo $booking->get_start_date() . '<br>' . $booking->get_start_time();
					break;
				case 'bkap_end_date':
					echo $booking->get_end_date() . '<br>' . $booking->get_end_time();
					break;
				case 'bkap_order_date':
					echo $booking->get_date_created();
					break;
				case 'bkap_zoom_meeting':
					$meeting_link = $booking->get_zoom_meeting_link();
					if ( '' !== $meeting_link ) {
						$meeting = sprintf( '<a href="%s" target="_blank"><span class="dashicons dashicons-video-alt2"></span></a>', $meeting_link );
						echo $meeting; // phpcs:ignore
					}
					break;
				case 'bkap_actions':
					$status = $booking->status;
					echo '<p>';
					$actions = array(
						'view' => array(
							'url'    => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
							'name'   => __( 'View', 'woocommerce-booking' ),
							'action' => 'view',
						),
					);

					if ( in_array( $status, array( 'pending-confirmation' ) ) ) {
						$actions['confirm'] = array(
							'url'    => wp_nonce_url( admin_url( '?action=bkap-booking-confirm&booking_id=' . $post->ID ), 'bkap-booking-confirm' ),
							'name'   => __( 'Confirm', 'woocommerce-booking' ),
							'action' => 'confirm',
						);
					}

					$actions = apply_filters( 'bkap_view_bookings_actions', $actions, $booking );

					foreach ( $actions as $action ) {
						printf( '<a class="button tips help_tip %s" href="%s" data-tip="%s">%s</a>', esc_attr( $action['action'] ), esc_url( $action['url'] ), esc_attr( $action['name'] ), esc_attr( $action['name'] ) );
					}
					echo '</p>';
					break;
			}
		}

		/**
		 * Sortable Columns List
		 *
		 * @access public
		 * @param mixed $columns Array of columns.
		 * @since 4.1.0
		 *
		 * @return array array of the columns keys to be displayed on the View Bookings page
		 */
		public function bkap_custom_columns_sort( $columns ) {

			$custom = array(
				'bkap_id'         => 'bkap_id',
				'bkap_status'     => 'bkap_status',
				'bkap_start_date' => 'bkap_start_date',
				'bkap_end_date'   => 'bkap_end_date',
				'bkap_order_date' => 'bkap_order_date',
			);

			return wp_parse_args( $custom, $columns );
		}

		/**
		 * Remove Edit link from the bulk actions
		 *
		 * @param array $actions Array of Action.
		 * @since 4.1.0
		 *
		 * @return array
		 */
		public function bkap_bulk_actions( $actions ) {

			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}

			$actions['confirm_booking'] = __( 'Confirm Booking', 'woocommerce-booking' );
			$actions['cancel_booking']  = __( 'Cancel Booking', 'woocommerce-booking' );

			return $actions;
		}

		/**
		 * This functions will filter the bookings on the View Bookings page
		 *
		 * @since 4.1.0
		 * @global string $typenow page string
		 * @global object $wp_query Query String to set
		 *
		 * @hook restrict_manage_posts
		 */
		public function bkap_filters() {
			global $typenow, $wp_query, $wpdb;

			if ( $typenow !== $this->type ) {
				return;
			}

			// Due to 2918 we have hard coded code to get all bookable products.
			$product  = $wpdb->get_results(
				"SELECT post_id FROM $wpdb->postmeta
                                        WHERE meta_key = '_bkap_enable_booking'
                                        AND meta_value = 'on'"
			);
			$products = array();

			if ( count( $product ) > 0 ) {
				foreach ( $product as $key => $value ) {
					$theid      = $value->post_id;
					$thetitle   = get_the_title( $theid );
					$products[] = array( $thetitle, $theid );
				}
				sort( $products );
			}

			// $products     = bkap_common::get_woocommerce_product_list( false );
			$products = apply_filters( 'bkap_all_bookable_products_dropdown', $products );
			$output   = '';

			if ( is_array( $products ) && count( $products ) > 0 ) {
				$output .= '<select name="filter_products">';
				$output .= '<option value="">' . __( 'All Bookable Products', 'woocommerce-booking' ) . '</option>';

				foreach ( $products as $filter_id => $filter ) {

					$output .= '<option value="' . absint( $filter[1] ) . '" ';

					if ( isset( $_REQUEST['filter_products'] ) ) {
						$output .= selected( $filter[1], $_REQUEST['filter_products'], false );
					}

					$output .= '>' . esc_html( $filter[0] ) . '</option>';
				}

				$output .= '</select>';
			}

			$views = apply_filters(
				'bkap_list_booking_by_dropdown',
				array(
					'today_onwards'  => 'Today Onwards',
					'today_checkin'  => 'Today\'s Check-ins',
					'today_checkout' => 'Today\'s Checkouts',
					'gcal'           => 'Imported Bookings',
					'custom_dates'   => 'Custom Dates',
				)
			);

			if ( isset( $_REQUEST['filter_customer'] ) ) {

				$id = absint( $_REQUEST['filter_customer'] );

				$customer = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT `{$wpdb->prefix}wc_customer_lookup`.first_name, `{$wpdb->prefix}wc_customer_lookup`.last_name FROM `{$wpdb->prefix}wc_customer_lookup` WHERE `{$wpdb->prefix}wc_customer_lookup`.customer_id = %d",
						$id
					)
				);

				if ( is_object( $customer ) ) {
					$display_name = $customer->first_name . ' ' . $customer->last_name;
				}

				$output .= '<select class="bkap-customer-search-select-box" name="filter_customer" data-placeholder="Search Customers" data-allow_clear="true"><option value="' . esc_attr( $id ) . '" selected="selected">' . htmlspecialchars( wp_kses_post( $display_name ) ) . '<option></select>';
			} else {
				$output .= '<select class="bkap-customer-search-select-box" name="filter_customer" data-placeholder="Search Customers" data-allow_clear="true"></select>';
			}

			if ( ! empty( $views ) ) {

				$output .= '<select name="filter_views" class="bkap_filter_view">';
				$output .= '<option value="">' . __( 'List bookings by', 'woocommerce-booking' ) . '</option>';

				foreach ( $views as $v_key => $v_value ) {

					$output .= '<option value="' . $v_key . '" ';

					if ( isset( $_REQUEST['filter_views'] ) ) {
						$output .= selected( $v_key, $_REQUEST['filter_views'], false );
					}

					$output .= '>' . esc_html( $v_value ) . '</option>';
				}

				$date_display = 'display:none;';
				if ( isset( $_REQUEST['filter_views'] ) && 'custom_dates' === $_REQUEST['filter_views'] ) {
					$date_display = '';
				}

				$startdate    = isset( $_GET[ 'bkap_custom_startdate' ] ) ? $_GET[ 'bkap_custom_startdate' ] : '';
				$enddate      = isset( $_GET[ 'bkap_custom_enddate' ] ) ? $_GET[ 'bkap_custom_enddate' ] : '';

				$output          .= '</select>';
				$start_date_label = __( 'Start Date', 'woocommerce-booking' );
				$end_date_label   = __( 'End Date', 'woocommerce-booking' );
				$output          .= '<input type="text" name="bkap_custom_startdate" id="bkap_custom_startdate" class="bkap_datepicker" value="' . $startdate . '" style="width:100px;' . $date_display . '" placeholder="' . $start_date_label . '" readonly><input type="text" name="bkap_custom_enddate" id="bkap_custom_enddate" class="bkap_datepicker" value="' . $enddate . '" style="width:100px;' . $date_display . '" placeholder="' . $end_date_label . '" readonly>';
			}

			echo apply_filters( 'bkap_filters_output', $output );
		}

		/**
		 * Custom filter queries
		 *
		 * @param $query
		 * @global string $typenow page string
		 * @global object $wp_query Query String to set
		 * @since 4.1.0
		 *
		 * @hook parse_query
		 */
		public function bkap_filters_query( $query ) {

			global $typenow, $wp_query, $wpdb;

			if ( 'bkap_booking' === $this->type ) {

				$current_timestamp = current_time( 'timestamp' );
				$current_time      = date( 'Ymd', $current_timestamp );
				$current_time      = $current_time . '000000';
				$current_date      = date( 'Ymd', $current_timestamp );
				$date              = ( isset( $_REQUEST['m'] ) && '' !== $_REQUEST['m'] ) ? $_REQUEST['m'] : '';
				$month_array       = array(
					'relation' => 'OR',
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

				if ( ! empty( $_REQUEST['filter_products'] ) && ! empty( $_REQUEST['filter_views'] ) && empty( $query->query_vars['suppress_filters'] ) ) {

					switch ( $_REQUEST['filter_views'] ) {
						case 'today_onwards':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_start',
									'value'   => $current_time,
									'compare' => '>=',
								),
								array(
									'key'   => '_bkap_product_id',
									'value' => absint( $_REQUEST['filter_products'] ),
								),
								$month_array,
							);
							break;

						case 'today_checkin':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_start',
									'value'   => $current_date,
									'compare' => 'LIKE',
								),
								array(
									'key'   => '_bkap_product_id',
									'value' => absint( $_REQUEST['filter_products'] ),
								),
								$month_array,
							);
							break;

						case 'today_checkout':
							$query->query_vars['meta_query'] = array(
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
									'value' => absint( $_REQUEST['filter_products'] ),
								),
								$month_array,
							);
							break;

						case 'custom_dates':
							$startdate = isset( $_REQUEST[ 'bkap_custom_startdate' ] ) && '' !== $_REQUEST[ 'bkap_custom_startdate' ] ? $_REQUEST[ 'bkap_custom_startdate' ] : $current_date;
							$enddate   = isset( $_REQUEST[ 'bkap_custom_enddate' ] ) && '' !== $_REQUEST[ 'bkap_custom_enddate' ] ? $_REQUEST[ 'bkap_custom_enddate' ] : $startdate;
							$from_date = date( 'YmdHis', strtotime( $startdate . '00:00:00' ) );
							$to_date   = date( 'YmdHis', strtotime( $enddate . '23:59:59' ) );

							$query->query_vars['meta_query'] = array(
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
									'value' => absint( $_REQUEST['filter_products'] ),
								),
							);
							break;

						case 'gcal':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_gcal_event_uid',
									'value'   => false,
									'compare' => '!=',
								),
								array(
									'key'   => '_bkap_product_id',
									'value' => absint( $_REQUEST['filter_products'] ),
								),
							);
							break;
					}
				} elseif ( ! empty( $_REQUEST['filter_products'] ) && empty( $query->query_vars['suppress_filters'] ) ) {

					$query->query_vars['meta_query'] = array(
						array(
							'key'   => '_bkap_product_id',
							'value' => absint( $_REQUEST['filter_products'] ),
						),
						$month_array,
					);
				} elseif ( ! empty( $_REQUEST['filter_views'] ) && empty( $query->query_vars['suppress_filters'] ) ) {

					switch ( $_REQUEST['filter_views'] ) {
						case 'today_onwards':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_start',
									'value'   => $current_time,
									'compare' => '>=',
								),
								$month_array,
							);
							break;
						case 'today_checkin':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_start',
									'value'   => $current_date,
									'compare' => 'LIKE',
								),
								$month_array,
							);
							break;
						case 'today_checkout':
							$query->query_vars['meta_query'] = array(
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
								$month_array,
							);
							break;
						case 'custom_dates':
							$startdate    = isset( $_REQUEST[ 'bkap_custom_startdate' ] ) && '' !== $_REQUEST[ 'bkap_custom_startdate' ] ? $_REQUEST[ 'bkap_custom_startdate' ] : $current_date;
							$enddate      = isset( $_REQUEST[ 'bkap_custom_enddate' ] ) && '' !== $_REQUEST[ 'bkap_custom_enddate' ] ? $_REQUEST[ 'bkap_custom_enddate' ] : $startdate;

							$from_date    = date( 'YmdHis', strtotime( $startdate . '00:00:00' ) );
							$to_date      = date( 'YmdHis', strtotime( $enddate . '23:59:59' ) );

							$query->query_vars['meta_query'] = array(
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
						case 'gcal':
							$query->query_vars['meta_query'] = array(
								array(
									'key'     => '_bkap_gcal_event_uid',
									'value'   => false,
									'compare' => '!=',
								),
							);
							break;
					}
				} elseif ( ! empty( $_REQUEST['filter_customer'] ) && empty( $query->query_vars['suppress_filters'] ) ) {

					$customer = $wpdb->get_row(
						'SELECT `' . $wpdb->prefix . 'wc_customer_lookup`.first_name, `' . $wpdb->prefix . 'wc_customer_lookup`.last_name FROM `' . $wpdb->prefix . 'wc_customer_lookup` WHERE `' . $wpdb->prefix . 'wc_customer_lookup`.customer_id = ' . absint( $_REQUEST['filter_customer'] )
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
							$query->query_vars['meta_query'] = array(
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
					$query->query_vars['orderby'] = 'ID';

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
					$query->query_vars['order'] = $_REQUEST['order'];
				}
			}
		}

		/**
		 * To get the search parameter when search is made on View booking page.
		 *
		 * @param array $query Query array.
		 * @global string $typenow page string
		 * @global string $pagenow current page
		 * @since 4.1.0
		 *
		 * @hook get_search_query
		 */
		public function bkap_search_label( $query ) {
			global $pagenow, $typenow;

			if ( 'edit.php' !== $pagenow ) {
				return $query;
			}

			if ( $typenow != $this->type ) {
				return $query;
			}

			if ( ! get_query_var( 'booking_search' ) ) {
				return $query;
			}

			return wc_clean( $_GET['s'] );
		}

		/**
		 * Search custom columns
		 *
		 * @param object $wp WP Object;
		 * @global string $pagenow Current page
		 * @global object $wpdb Global wpdb Object
		 * @since 4.1.0
		 *
		 * @hook parse_query
		 */
		public function bkap_search_custom_fields( $wp ) {
			global $pagenow, $wpdb;

			if ( ! in_array( $pagenow, array( 'edit.php', 'admin-ajax.php' ) ) || empty( $wp->query_vars['s'] ) || $wp->query_vars['post_type'] !== $this->type || 'bkap_gcal_event' === $this->type ) {
				return $wp;
			}
			$term = wc_clean( $wp->query_vars['s'] );

			$bkap_date_format = bkap_common::bkap_get_date_format();

			if ( is_numeric( $term ) ) {
				// check if a booking exists by this ID.
				if ( false !== get_post_status( $term ) && 'bkap_booking' === get_post_type( $term ) ) {
					$booking_ids = array( $term );
				} else { // else assume the numeric value is an order ID.
					if ( function_exists( 'wc_order_search' ) ) {
						$order_ids   = wc_order_search( wc_clean( $wp->query_vars['s'] ) );
						$booking_ids = $order_ids ? bkap_common::get_booking_ids_from_order_id( $order_ids ) : array( 0 );

						if ( is_array( $booking_ids ) && count( $booking_ids ) == 0 ) {
							$booking_ids = array( 0 );
						}
					}
				}
			} else {

				$search_string = esc_attr( $wp->query_vars['s'] );

				$white_space = strpos( $search_string, ' ' );

				if ( $white_space > 0 ) {

					$search_texts = explode( ' ', $search_string );

					$regex_text = implode( '|', $search_texts );
				} else {
					$regex_text = $search_string;
				}

				$search_fields = array_map(
					'wc_clean',
					array(
						'_billing_first_name',
						'_billing_last_name',
						'_billing_company',
						'_billing_address_1',
						'_billing_address_2',
						'_billing_city',
						'_billing_postcode',
						'_billing_country',
						'_billing_state',
						'_billing_email',
						'_billing_phone',
						'_shipping_first_name',
						'_shipping_last_name',
						'_shipping_address_1',
						'_shipping_address_2',
						'_shipping_city',
						'_shipping_postcode',
						'_shipping_country',
						'_shipping_state',
					)
				);

				// Search orders.
				$order_ids = $wpdb->get_col(
					"
						SELECT post_id
						FROM {$wpdb->postmeta}
						WHERE meta_key IN ('" . implode( "','", $search_fields ) . "')
						AND meta_value REGEXP '" . $regex_text . "'"
				);

				if ( empty( $order_ids ) ) {

					$date_from_format = DateTime::createFromFormat( $bkap_date_format, $term );
					if ( $date_from_format ) {
						$date_term = $date_from_format->format( 'Y-m-d' );
						$timestamp = strtotime( $date_term );
					} else {
						$timestamp = strtotime( $term );
					}

					if ( false !== $timestamp ) {

						$date      = date( 'Y-m-d', $timestamp );

						$order_ids = $wpdb->get_col(
							$wpdb->prepare(
								"SELECT post_id FROM {$wpdb->postmeta} WHERE ( meta_key = '%s' OR meta_key = '%s' ) AND meta_value LIKE '%%%s%%';",
								'_bkap_start',
								'_bkap_end',
								date( 'Ymd', $timestamp )
							)
						);

						$booking_ids = $order_ids;
					}
				}

				// If the search is not for date, search for product name.
				if ( empty( $order_ids ) ) {
					$order_ids = $wpdb->get_col(
						$wpdb->prepare(
							"SELECT post_id
							FROM {$wpdb->postmeta}
							WHERE ( meta_key = '_bkap_product_id' )
							AND meta_value IN ( SELECT ID from {$wpdb->posts} WHERE post_title LIKE '%%%s%%' ) ",
							esc_attr( $wp->query_vars['s'] )
						)
					);

					$booking_ids = $order_ids;
				}

				// ensure db query doesn't throw an error due to empty post_parent value.
				$order_ids = empty( $order_ids ) ? array( '-1' ) : $order_ids;

				// so we know we're doing this.
				if ( empty( $booking_ids ) ) {
					$booking_ids = array_merge(
						$wpdb->get_col(
							"SELECT ID FROM {$wpdb->posts}
								WHERE post_parent IN (" . implode( ',', $order_ids ) . ');'
						),
						$wpdb->get_col(
							$wpdb->prepare(
								"SELECT ID
									FROM {$wpdb->posts}
									WHERE post_title LIKE '%%%s%%'
									OR ID = %d
							;",
								esc_attr( $wp->query_vars['s'] ),
								absint( $wp->query_vars['s'] )
							)
						),
						array( 0 ) // so we don't get back all results for incorrect search.
					);
				}
			}

			$wp->query_vars['s']              = false;
			$wp->query_vars['post__in']       = $booking_ids;
			$wp->query_vars['booking_search'] = true;
		}

		/**
		 * Add Bulk Actions
		 *
		 * @global string $post_type type of post
		 * @since 4.1.0
		 *
		 * @hook admin_footer
		 */
		public function bkap_bulk_admin_footer() {
			global $post_type;

			if ( $this->type === $post_type ) {
				?>
					<script type="text/javascript">
						jQuery( document ).ready( function ( $ ) {
							$( '<option value="confirm_booking"><?php _e( 'Confirm bookings', 'woocommerce-booking' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
							$( '<option value="cancel_booking"><?php _e( 'Cancel bookings', 'woocommerce-booking' ); ?></option>' ).appendTo( 'select[name="action"], select[name="action2"]' );
						});
					</script>
				<?php
			}
		}

		/**
		 * Bulk Actions execution
		 *
		 * @param string $redirect_to Redirect URL.
		 * @param string $action Action.
		 * @param array  $ids Selected IDs.
		 *
		 * @global string $post_type type of post
		 * @since 4.1.0
		 *
		 * @hook load-edit.php
		 */
		public function bkap_bulk_action( $redirect_to, $action, $ids ) {

			global $post_type;

			if ( $this->type === $post_type ) {

				switch ( $action ) {
					case 'confirm_booking':
						$new_status    = 'confirmed';
						$report_action = 'bookings_confirmed';
						break;
					case 'cancel_booking':
						$new_status    = 'cancelled';
						$report_action = 'bookings_cancelled';
						break;
					case 'trash':
						$new_status    = 'trash';
						$report_action = 'bookings_trashed';
						break;
					default:
						return;
				}

				$changed = 0;

				$post_ids = $ids;

				foreach ( $post_ids as $post_id ) {

					if ( $new_status === 'trash' ) {
						bkap_cancel_order::bkap_delete_booking( $post_id );
					} else {
						$item_id = get_post_meta( $post_id, '_bkap_order_item_id', true );
						bkap_booking_confirmation::bkap_save_booking_status( $item_id, $new_status, $post_id );
					}
					$changed++;
				}

				$sendback = add_query_arg(
					array(
						'post_type'    => $this->type,
						$report_action => true,
						'changed'      => $changed,
						'ids'          => join(
							',',
							$post_ids
						),
					),
					''
				);
				return $sendback;
			}
		}

		/**
		 * Bulk Action messages
		 *
		 * @global string $post_type type of post
		 * @global string $pagenow current page
		 * @since 4.1.0
		 *
		 * @hook admin_notices
		 */
		public function bkap_bulk_admin_notices() {
			global $post_type, $pagenow;

			if ( isset( $_REQUEST['bookings_confirmed'] ) || isset( $_REQUEST['bookings_unconfirmed'] ) || isset( $_REQUEST['bookings_cancelled'] ) ) {
				$number = isset( $_REQUEST['changed'] ) ? absint( $_REQUEST['changed'] ) : 0;

				if ( 'edit.php' == $pagenow && $this->type == $post_type ) {
					$message = sprintf( _n( 'Booking status changed.', '%s booking statuses changed.', $number, 'woocommerce-booking' ), number_format_i18n( $number ) );
					echo '<div class="updated"><p>' . $message . '</p></div>';
				}
			}
		}

		/**
		 * Ajax for confirming bookings from Actions column
		 *
		 * @since 4.1.0
		 *
		 * @hook wp_ajax_bkap-booking-confirm
		 */
		public function bkap_booking_confirmed() {

			if ( isset( $_GET['action'] ) && 'bkap-booking-confirm' === $_GET['action'] ) {

				if ( ! check_admin_referer( 'bkap-booking-confirm' ) ) {
					wp_die( __( 'You have taken too long. Please go back and retry.', 'woocommerce-booking' ) );
				}
				$booking_id = isset( $_GET['booking_id'] ) && (int) $_GET['booking_id'] ? (int) $_GET['booking_id'] : '';
				if ( ! $booking_id ) {
					die;
				}

				$item_id = get_post_meta( $booking_id, '_bkap_order_item_id', true );

				do_action( 'bkap_booking_confirmed_using_icon' );
				bkap_booking_confirmation::bkap_save_booking_status( $item_id, 'confirmed', $booking_id );
				wp_safe_redirect( wp_get_referer() );
			}
		}

		/**
		 * Returns true if at least one booking has been received for the given product ID
		 *
		 * @param int $product_id Product ID.
		 *
		 * @since 4.1.0
		 *
		 * @return boolean
		 */
		public function bkap_check_booking_present( $product_id ) {

			global $wpdb;

			$query = 'SELECT post_id FROM `' . $wpdb->prefix . 'postmeta`
						WHERE meta_key = %s
						AND meta_value = %d
						ORDER BY post_id DESC LIMIT 1';

			$results_query = $wpdb->get_results( $wpdb->prepare( $query, '_bkap_product_id', $product_id ) );

			$bookings_present = false; // assume no bookings are present for this product.			
			if ( isset( $results_query ) && count( $results_query ) > 0 ) {
				$bookings_present = true;
			}

			return $bookings_present;
		}

		/**
		 * Preparing the data of bookings and Exporting that in the CSV file OR for Printing
		 *
		 * @since 4.1.0
		 *
		 * @hook admin_init
		 */
		public function bkap_export_data() {

			$post_status = isset( $_GET['post_status'] ) ? $_GET['post_status'] : '';

			if ( isset( $_GET['download'] ) && 'data.csv' === $_GET['download'] ) {
				$report = self::generate_data( $post_status );
				self::bkap_download_csv_file( $report );
			} elseif ( isset( $_GET['download'] ) && 'data.print' === $_GET['download'] ) {
				$report = self::generate_data( $post_status );
				self::bkap_download_print_file( $report );
			}
		}

		/**
		 * Download the CSV of the bookings.
		 *
		 * @param array $report array of bookings based on filter.
		 *
		 * @since 4.1.0
		 */
		public static function bkap_download_csv_file( $report ) {

			$csv = self::generate_csv( $report );

			header( 'Content-type: application/x-msdownload' );
			header( 'Content-Disposition: attachment; filename= ' . apply_filters( 'bkap_csv_file_name', 'Booking-Data-' . date( 'Y-m-d', current_time( 'timestamp' ) ) . '.csv' ) );
			header( 'Pragma: no-cache' );
			header( 'Expires: 0' );
			echo "\xEF\xBB\xBF";
			echo $csv; // phpcs:ignore
			exit;
		}

		/**
		 * Print of the bookings.
		 *
		 * @param array $report array of bookings based on filter.
		 *
		 * @since 4.1.0
		 */
		public static function bkap_download_print_file( $report, $table = false, $col_data = false, $row_data = false ) {

			$global_settings = bkap_global_setting();
			$cols            = self::bkap_get_csv_cols();

			$print_data_columns  = '<tr>';
			foreach ( $cols as $col ) {
				$print_data_columns .= '<th style="border:1px solid black;padding:5px;">' . $col . '</th>';
			}
			$print_data_columns  = apply_filters( 'bkap_view_bookings_print_columns', $print_data_columns );

			if ( $col_data ) {
				return $print_data_columns;
			}

			$print_data_row_data = '';
			$currency            = get_woocommerce_currency();
			$phpversion          = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );
			foreach ( $report as $key => $booking ) {

				$booking_id   = $booking->id;
				$order_id     = $booking->order_id;
				$status       = self::status_data( $booking ); // Status.
				$product_name = self::product_name_data( $booking ); // Booked Product.
				$booked_by    = self::customer_name_data( $booking ); // Booked By.
				$start_date   = self::start_date_data( $booking, $global_settings ); // Start Date.
				$end_date     = self::end_date_data( $booking, $global_settings ); // End Date.
				$order_date   = $booking->get_date_created(); // Order Date.
				$quantity     = $booking->get_quantity();
				$persons      = $booking->get_persons_info();
				$final_amt    = self::final_amount_data( $booking, $quantity, $currency, $phpversion );
				$meeting_link = $booking->get_zoom_meeting_link();

				$data = array(
					'booking_id'   => $booking_id,
					'order_id'     => $order_id,
					'status'       => $status,
					'product_name' => $product_name,
					'booked_by'    => $booked_by,
					'start_date'   => $start_date,
					'end_date'     => $end_date,
					'order_date'   => $order_date,
					'quantity'     => $quantity,
					'persons'      => $persons,
					'final_amt'    => $final_amt,
					'meeting_link' => $meeting_link,
				);

				$print_data_row_data_td  = '';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $status . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $booking->id . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $product_name . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $booked_by . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $booking->order_id . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $start_date . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $end_date . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $persons . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $quantity . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $order_date . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;">' . $final_amt . '</td>';
				$print_data_row_data_td .= '<td style="border:1px solid black;padding:5px;"><small>' . $meeting_link . '</small></td>';
				$print_data_row_data_td  = apply_filters( 'bkap_view_bookings_print_individual_row_data', $print_data_row_data_td, $booking, $booking_id, $data );

				$print_data_row_data .= '<tr>';
				$print_data_row_data .= $print_data_row_data_td;
				$print_data_row_data  = apply_filters( 'bkap_view_bookings_print_individual_row', $print_data_row_data, $booking, $booking_id );
				$print_data_row_data .= '</tr>';
			}

			$print_data_row_data = apply_filters( 'bkap_view_bookings_print_rows', $print_data_row_data, $report );
			if ( $row_data ) {
				return $print_data_row_data;
			}

			$print_data_title = apply_filters( 'bkap_view_bookings_print_title', __( 'Print Bookings', 'woocommerce-booking' ) );

			if ( $table ) {
				$print_data = "<table id='bkap_print_data' style='border:1px solid black;border-collapse:collapse;'>" . $print_data_columns . $print_data_row_data . '</table>';
				return $print_data; // phpcs:ignore
			} else {
				$print_data = '<html><head><title>' . $print_data_title . "</title><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body><table style='border:1px solid black;border-collapse:collapse;'>" . $print_data_columns . $print_data_row_data . '</table></body></html>';
				echo $print_data; // phpcs:ignore
				exit;
			}
		}

		/**
		 * Booking status.
		 *
		 * @param obj $booking Booking Object.
		 *
		 * @since 4.1.0
		 */
		public static function status_data( $booking ) {
			$status = bkap_common::get_mapped_status( $booking->get_status() );
			return $status;
		}

		/**
		 * Booked Product Name.
		 *
		 * @param obj $booking Booking Object.
		 *
		 * @since 4.1.0
		 */
		public static function product_name_data( $booking ) {
			$product      = $booking->get_product();
			if ( $product ) {

				$product_name = $product->get_title();
				$resource_id  = $booking->get_resource();
				$variation_id = $booking->get_variation_id();
				if ( $variation_id > 0 ) {
					$variation_obj = wc_get_product( $variation_id );
					$product_name  = false != $variation_obj ? $variation_obj->get_name() : '-';
				}

				if ( $resource_id != '' ) {

					$show_resource = apply_filters( 'bkap_display_resource_info_on_view_booking', true, $product, $resource_id );

					if ( $show_resource ) {
						$resource_title = $booking->get_resource_title();
						$product_name  .= '<br>( ' . esc_html( $resource_title ) . ' )';
					}
				}
			} else {
				$product_name = '-';
			}

			return $product_name;
		}

		/**
		 * Customer Name of Booking.
		 *
		 * @param obj $booking Booking Object.
		 *
		 * @since 4.1.0
		 */
		public static function customer_name_data( $booking ) {
			$customer = $booking->get_customer();
			return apply_filters( 'bkap_customer_name_on_view_booking', $customer->name, $customer, $booking );
		}

		/**
		 * Booking Start Date.
		 *
		 * @param obj $booking Booking Object.
		 *
		 * @since 4.1.0
		 */
		public static function start_date_data( $booking, $global_settings ) {
			$start_date     = $booking->get_start_date( $global_settings );
			$get_start_time = $booking->get_start_time( $global_settings );
			if ( '' !== $get_start_time ) {
				$start_date .= ' - ' . $get_start_time;
			}

			return $start_date;
		}

		/**
		 * Booking End Date.
		 *
		 * @param obj $booking Booking Object.
		 *
		 * @since 4.1.0
		 */
		public static function end_date_data( $booking, $global_settings ) {
			$end_date     = '';
			$get_end_date = $booking->get_end_date( $global_settings );
			if ( '' !== $get_end_date ) {
				$end_date     = $get_end_date;
				$get_end_time = $booking->get_end_time( $global_settings );

				if ( '' !== $get_end_time ) {
					$end_date .= ' - ' . $get_end_time;
				}
			}

			return $end_date;
		}

		/**
		 * Booking Amount.
		 *
		 * @param obj    $booking Booking Object.
		 * @param int    $quantity Booking Quantity.
		 * @param string $currency Currency Symbol.
		 * @param bool   $phpversion PHP Version.
		 *
		 * @since 4.1.0
		 */
		public static function final_amount_data( $booking, $quantity, $currency, $phpversion ) {
			// Amount.
			$amount    = $booking->get_cost();
			$final_amt = (float) $amount * (int) $quantity;

			if ( absint( $booking->order_id ) > 0 && false !== get_post_status( $booking->order_id ) ) {
				$the_order = wc_get_order( $booking->order_id );
				$currency  = ( $phpversion ) ? $the_order->get_order_currency() : $the_order->get_currency();
			}

			$final_amt = wc_price( $final_amt, array( 'currency' => $currency ) );

			return $final_amt;
		}

		/**
		 * Generate list of booking.
		 *
		 * @param string $post_status status of the booking.
		 *
		 * @since 4.1.0
		 * return array array contains all the booking details for the given status
		 */
		public function generate_data( $post_status, $args = array() ) {
			return bkap_common::bkap_get_bookings( $post_status, $args );
		}

		/**
		 * Generate string for CSV of booking.
		 *
		 * @param array $data array of booking information.
		 *
		 * @since 4.1.0
		 */
		public static function generate_csv( $data, $column = true ) {

			$global_settings = bkap_global_setting();

			$csv = '';
			if ( $column ) {
				$cols = self::bkap_get_csv_cols();
				foreach ( $cols as $col ) {
					$csv .= $col . ',';
				}
				$csv  = substr( $csv, 0, -1 );
				$csv  = apply_filters( 'bkap_bookings_csv_columns_data', $csv );
				$csv .= "\n";
			}

			$currency   = get_woocommerce_currency();
			$phpversion = ( version_compare( WOOCOMMERCE_VERSION, '3.0.0' ) < 0 );

			foreach ( $data as $key => $booking ) {

				$booking_id   = $booking->id; // ID.
				$order_id     = $booking->order_id; // Order ID.
				$status       = self::status_data( $booking ); // Status.
				$product_name = self::product_name_data( $booking ); // Booked Product.
				$product_name = str_replace( '<br>', ' - ', $product_name );
				$booked_by    = self::customer_name_data( $booking ); // Booked By.
				$start_date   = self::start_date_data( $booking, $global_settings ); // Start Date.
				$end_date     = self::end_date_data( $booking, $global_settings ); // End Date.
				$order_date   = $booking->get_date_created(); // Order Date.
				$persons      = $booking->get_persons_info();
				$quantity     = $booking->get_quantity();
				$final_amt    = self::final_amount_data( $booking, $quantity, $currency, $phpversion );
				$final_amt    = wp_strip_all_tags( html_entity_decode( $final_amt ) );
				$meeting_link = $booking->get_zoom_meeting_link();

				$data = array(
					'booking_id'   => $booking_id,
					'order_id'     => $order_id,
					'status'       => $status,
					'product_name' => $product_name,
					'booked_by'    => $booked_by,
					'start_date'   => $start_date,
					'end_date'     => $end_date,
					'order_date'   => $order_date,
					'persons'      => $persons,
					'quantity'     => $quantity,
					'final_amt'    => $final_amt,
					'meeting_link' => $meeting_link,
				);

				// Create the data row.
				$row = $status . ',' . $booking_id . ',"' . $product_name . '",' . $booked_by . ',' . $order_id . ',"' . $start_date . '","' . $end_date . '","' . $persons . '",' . $quantity . ',' . $order_date . ',"' . $final_amt . '",' . $meeting_link;
				$row = apply_filters( 'bkap_bookings_csv_individual_row_data', $row, $booking, $booking_id, $data );

				$csv .= $row;

				$csv  = apply_filters( 'bkap_bookings_csv_individual_data', $csv, $booking, $booking_id, $data, $row );
				$csv .= "\n";
			}
			$csv = apply_filters( 'bkap_bookings_csv_data', $csv, $data );
			return $csv;
		}

		/**
		 * Generate string for CSV of booking.
		 *
		 * @since 5.2.1
		 */
		public function bkap_do_ajax_export() {

			$csv_print = $_POST['csv_print'];
			if ( 'csv' === $csv_print ) {
				$upload_dir = wp_upload_dir();
				$filename   = 'bkap-csv.csv';
				$file       = trailingslashit( $upload_dir['basedir'] ) . $filename;

				if ( ! is_writeable( $upload_dir['basedir'] ) ) {
					wp_send_json( array( 'error' => true, 'message' => __( 'Export location or file not writable', 'woocommerce-booking' ) ) );
					wp_die();
				}
			}

			$step           = (int) $_POST['step'];
			$post_status    = $_POST['post_status'];
			$s              = $_POST['s'];
			$m              = $_POST['m'];
			$total_bookings = (int) $_POST['total_items'];
			$done_items     = (float) $_POST['done_items'];

			$args           = array(
				'posts_per_page' => 1000,
				'paged'          => $step,
				'meta_query'  => array(
					array(
						'key'     => '_bkap_start',
						'value'   => $m,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_bkap_end',
						'value'   => $m,
						'compare' => 'LIKE',
					),
				),
				's'              => $s,
			);

			$report = self::generate_data( $post_status, $args );

			if ( ! empty( $report ) ) {

				$exported_item = $done_items + count( $report );
				$saved_data    = $done_items + count( $report );				
				$percentage    = round( ( $saved_data / $total_bookings ) * 100 );

				if ( 'csv' === $csv_print ) {
					$rows = self::generate_csv( $report, $column = false );

					if ( $step < 2 ) {
						$done = false;
						// Make sure we start with a fresh file on step 1.
						@unlink( $file );
						self::bkap_print_csv_cols( $file );
					}

					$row = self::bkap_stash_step_data( $file, $rows );

					$json_data = array(
						'added'      => $exported_item,
						'percentage' => $percentage,
					);
				} else {
					$html_data = '';
					if ( $step < 2 ) {
						$html_data .= self::bkap_download_print_file( $report, true );
					} else {
						$html_data .= self::bkap_download_print_file( $report, false, false, true );
					}

					$json_data = array(
						'added'      => $exported_item,
						'percentage' => $percentage,
						'html_data'  => $html_data,
					);
				}
				$step++;
				$json_data['step'] = $step;
				wp_send_json( $json_data );
				wp_die();
			} elseif ( 1 === $step && empty( $report ) ) {
				wp_send_json(
					array(
						'error'   => true,
						'message' => __( 'No data found for export parameters.', 'woocommerce-booking' ),
					)
				);
				wp_die();
			} else {

				if ( 'csv' === $csv_print ) {
					$args = array(
						'step'        => $step,
						'nonce'       => wp_create_nonce( 'bkap-batch-export-csv' ),
						'bkap_action' => 'bkap_download_csv',
					);

					$download_url = add_query_arg( $args, admin_url() );
					$json_data    = array(
						'step' => 'done',
						'url'  => $download_url,
					);
				} else {
					$json_data = array( 'step' => 'done' );
				}
				wp_send_json( $json_data );
				wp_die();
			}
		}

		/**
		 * This function will write the Booking Column Data to CSV File.
		 *
		 * @param string $file Path of CSV File.
		 *
		 * @since 5.2.1
		 */
		public function bkap_print_csv_cols( $file ) {

			$cols      = self::bkap_get_csv_cols();
			$col_data  = implode( ',', $cols );
			$col_data .= "\r\n";

			self::bkap_stash_step_data( $file, $col_data );

			return $col_data;
		}

		/**
		 * Function will return the Columns of Booking Data..
		 *
		 * @since 5.2.1
		 */
		public static function bkap_get_csv_cols() {
			$cols = array(
				'status'         => __( 'Status', 'woocommerce-booking' ),
				'id'             => __( 'ID', 'woocommerce-booking' ),
				'booked_product' => __( 'Booked Product', 'woocommerce-booking' ),
				'booked_by'      => __( 'Booked By', 'woocommerce-booking' ),
				'order_id'       => __( 'Order ID', 'woocommerce-booking' ),
				'start_date'     => __( 'Start Date', 'woocommerce-booking' ),
				'end_date'       => __( 'End Date', 'woocommerce-booking' ),
				'persons'        => __( 'Persons', 'woocommerce-booking' ),
				'quantity'       => __( 'Quantity', 'woocommerce-booking' ),
				'order_date'     => __( 'Order Date', 'woocommerce-booking' ),
				'amount'         => __( 'Amount', 'woocommerce-booking' ),
				'zoom_meeting'   => __( 'Zoom Meeting', 'woocommerce-booking' ),
			);

			return apply_filters( 'bkap_bookings_csv_columns', $cols );
		}

		/**
		 * Function to write data to CSV file.
		 *
		 * @param string $file Path of the CSV file.
		 * @param string $data Data to be added to CSV file.
		 * @since 5.2.1
		 */
		public function bkap_stash_step_data( $file, $data ) {
			$file_content  = self::bkap_get_file( $file );
			$file_content .= $data;
			@file_put_contents( $file, $file_content );
		}

		/**
		 * Function to create CSV file OR get its content.
		 *
		 * @param string $file Path of the CSV file.
		 * @since 5.2.1
		 */
		public function bkap_get_file( $file ) {

			$f = '';
			if ( @file_exists( $file ) ) {
				if ( ! is_writeable( $file ) ) {
					$is_writable = false;
				}

				$f = @file_get_contents( $file );

			} else {
				@file_put_contents( $file, '' );
				@chmod( $file, 0664 );
			}

			return $f;
		}

		/**
		 * Function to download the CSV for Booking.
		 *
		 * @since 5.2.1
		 */
		public function bkap_download_csv() {

			if ( isset( $_GET['bkap_action'] ) && 'bkap_download_csv' === $_GET['bkap_action'] ) {

				if ( ! wp_verify_nonce( $_REQUEST['nonce'], 'bkap-batch-export-csv' ) ) {
					wp_die( __( 'Nonce verification failed', 'woocommerce-booking' ), __( 'Error', 'woocommerce-booking' ), array( 'response' => 403 ) );
				}

				$upload_dir = wp_upload_dir();
				$filename   = 'bkap-csv.csv';
				$file       = trailingslashit( $upload_dir['basedir'] ) . $filename;

				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename=' . apply_filters( 'bkap_csv_file_name', 'Booking-Data-' . date( 'Y-m-d', current_time( 'timestamp' ) ) . '.csv' ) );
				header( 'Expires: 0' );
				echo "\xEF\xBB\xBF";
				readfile( $file );
				unlink( $file );
				die();
			}
		}

		/**
		 * Search for customers.
		 */
		public static function bkap_view_bookings_json_search_customers() {
			global $wpdb;

			ob_start();

			check_ajax_referer( 'search-customers', 'security' );

			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				wp_die( -1 );
			}

			$term  = isset( $_GET['term'] ) ? (string) wc_clean( wp_unslash( $_GET['term'] ) ) : '';
			$limit = 0;

			if ( empty( $term ) ) {
				wp_die();
			}

			$ids = array();
			
			// Search by Customer ID if search string is numeric.
			if ( is_numeric( $term ) ) {

				$fetch = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT `{$wpdb->prefix}wc_customer_lookup`.customer_id FROM `{$wpdb->prefix}wc_customer_lookup` WHERE `{$wpdb->prefix}wc_customer_lookup`.customer_id = %s",
						$term
					)
				);

				if ( count( $fetch ) > 0 ) {
					$ids = $fetch;
				}
			}

			// Usernames can be numeric so we first check that no users was found by ID before searching for numeric username, this prevents performance issues with ID lookups.
			if ( empty( $ids ) ) {
				$limit = '';

				// If search is smaller than 3 characters, limit result set to avoid
				// too many rows being returned.
				if ( 3 > strlen( $term ) ) {
					$limit = ' LIMIT 20';
				}

				$ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT `{$wpdb->prefix}wc_customer_lookup`.customer_id FROM `{$wpdb->prefix}wc_customer_lookup` WHERE (`{$wpdb->prefix}wc_customer_lookup`.first_name LIKE %s OR `{$wpdb->prefix}wc_customer_lookup`.last_name LIKE %s) {$limit}",
						'%'.$term.'%',
						'%'.$term.'%'				
					)
				);
			}

			$found_customers = array();

			if ( ! empty( $_GET['exclude'] ) ) {
				$ids = array_diff( $ids, array_map( 'absint', (array) wp_unslash( $_GET['exclude'] ) ) );
			}

			foreach ( $ids as $id ) {
				$customer = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT `{$wpdb->prefix}wc_customer_lookup`.first_name, `{$wpdb->prefix}wc_customer_lookup`.last_name FROM `{$wpdb->prefix}wc_customer_lookup` WHERE `{$wpdb->prefix}wc_customer_lookup`.customer_id = %d",
						$id
					)
				);
				$found_customers[ $id ] = $customer->first_name . ' ' . $customer->last_name;
			}

			wp_send_json( $found_customers );
		}
	}
}
return new BKAP_Bookings_View();
