<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Viewing Google Events
 *
 * @author      Tyche Softwares
 * @package     BKAP/Google-Calendar-Sync
 * @category    Classes
 */

if ( ! class_exists( 'BKAP_Google_Events_View' ) ) {

	/**
	 * Booking & Appointment Plugin for WooCommerce BKAP_Google_Events_View.
	 *
	 * Displaying the Google Event Posts.
	 *
	 * @class    BKAP_Google_Events_View
	 * @since    4.2.0
	 * @category Class
	 * @author   Tyche Softwares
	 */

	class BKAP_Google_Events_View {

		/**
		 * Default constructor.
		 *
		 * @since 4.2.0
		 */

		public function __construct() {

			$this->type = 'bkap_gcal_event';

			// Admin Columns
			add_filter( 'manage_edit-' . $this->type . '_columns', array( &$this, 'bkap_edit_columns' ) ); // adding column on post page
			add_action( 'manage_' . $this->type . '_posts_custom_column', array( &$this, 'bkap_custom_columns' ), 2 );
			add_filter( 'manage_edit-' . $this->type . '_sortable_columns', array( &$this, 'bkap_custom_columns_sort' ), 1 );
			add_filter( 'request', array( $this, 'bkap_custom_columns_orderby' ) );

			// Search
			add_filter( 'get_search_query', array( $this, 'bkap_search_label' ) );
			add_filter( 'parse_query', array( $this, 'bkap_search_custom_fields' ) );

			// Actions
			add_filter( 'bulk_actions-edit-' . $this->type, array( $this, 'bkap_bulk_actions' ), 10, 1 );
			add_action( 'admin_footer', array( $this, 'bkap_bulk_admin_footer' ), 10 );

			// All Dates filter
			add_action( 'admin_head', array( $this, 'bkap_remove_date_drop' ) );
		}

		/**
		 * Removing the All Dates filter from Import Booking page.
		 *
		 * @since 4.2.0
		 *
		 * @hook admin_head
		 */

		public function bkap_remove_date_drop() {

			$screen = get_current_screen();

			if ( ! is_null( $screen ) && 'bkap_gcal_event' == $screen->post_type ) {
				add_filter( 'months_dropdown_results', '__return_empty_array' );
			}
		}

		/**
		 * Change the columns shown in admin.
		 *
		 * @param array $existing_columns Exisiting Columns Array
		 *
		 * @return array Columns with new columns added
		 *
		 * @since 4.2.0
		 *
		 * @hook manage_edit-bkap_gcal_event_columns
		 */

		public function bkap_edit_columns( $existing_columns ) {

			if ( empty( $existing_columns ) && ! is_array( $existing_columns ) ) {
				$existing_columns = array();
			}

			unset( $existing_columns['comments'], $existing_columns['title'], $existing_columns['date'] );

			$columns = array();

			$columns['bkap_event_summary'] = __( 'Event Summary', 'woocommerce-booking' );
			$columns['bkap_description']   = __( 'Description', 'woocommerce-booking' );
			$columns['bkap_start_date']    = __( 'Start Date', 'woocommerce-booking' );
			$columns['bkap_end_date']      = __( 'End Date', 'woocommerce-booking' );
			$columns['bkap_timeslot']      = __( 'Timeslot', 'woocommerce-booking' );
			$columns['bkap_reason']        = __( 'Reason of Failure', 'woocommerce-booking' );
			$columns['bkap_product']       = __( 'Product', 'woocommerce-booking' );
			$columns['bkap_actions']       = __( 'Actions', 'woocommerce-booking' );

			return array_merge( $existing_columns, $columns );
		}

		/**
		 * Define our custom columns shown in admin.
		 *
		 * @param string $column Column Name
		 *
		 * @globals WP_Post $post
		 *
		 * @since 4.2.0
		 *
		 * @hook manage_bkap_gcal_event_posts_custom_column
		 */

		public function bkap_custom_columns( $column ) {
			global $post;

			$booking = (object) array();
			if ( get_post_type( $post->ID ) === 'bkap_gcal_event' ) {
				$booking = new BKAP_Gcal_Event( $post->ID );
			}

			$status = $booking->get_status();

			switch ( $column ) {

				case 'bkap_event_summary':
					echo esc_html( $booking->summary );
					break;

				case 'bkap_description':
					echo esc_html( $booking->description );
					break;

				case 'bkap_start_date':
					echo $booking->get_start_date();
					break;

				case 'bkap_end_date':
					echo $booking->get_end_date();
					break;

				case 'bkap_timeslot':
					if ( $booking->get_start_date() == $booking->get_end_date() || $booking->get_end_date() == '' ) {
						$start_time = $booking->get_start_time();
						$end_time   = $booking->get_end_time();

						if ( $end_time == '' ) {
							echo $start_time;
						} else {
							echo $start_time . '-' . $end_time;
						}
					}

					break;

				case 'bkap_reason':
					echo $booking->get_failed_reason();
					break;

				case 'bkap_product':
					if ( $status == 'bkap-unmapped' ) {
						$product_list = bkap_common::get_woocommerce_product_list();
						$user         = new WP_User( get_current_user_id() );

						$default_text = __( 'Select a Product', 'woocommerce-booking' );
						$value        = '<select style="max-width:180px;width:100%;" id="import_event_' . $post->ID . '">';
						$value       .= '<option value="" > ' . $default_text . '</option>';

						if ( $user->roles[0] == 'tour_operator' ) {

							foreach ( $product_list as $k => $v ) {
								$booking_setting = get_post_meta( $v[1], 'woocommerce_booking_settings', true );

								$tour_id = ( isset( $booking_setting['booking_tour_operator'] ) && '' != $booking_setting['booking_tour_operator'] ) ? $booking_setting['booking_tour_operator'] : '';

								if ( get_current_user_id() == $tour_id ) {
									$value .= '<option value="' . $v[1] . '" >' . $v[0] . '</option>';
								}
							}
						} else {
							foreach ( $product_list as $k => $v ) {
								$value .= '<option value="' . $v[1] . '" >' . $v[0] . '</option>';
							}
						}

						$value .= '</select>';
						echo $value;
					}
					break;

				case 'bkap_actions':
					if ( $status == 'bkap-mapped' ) {
						$mapped_text = __( 'This event is mapped.', 'woocommerce-booking' );
						echo '<p><b>' . $mapped_text . '</b></p>';
					} else {

						$default_text = __( 'Map Event', 'woocommerce-booking' );
						echo '<input type="button" class="save_button button-primary" id="map_event" name="map_event_' . $post->ID . '" value="' . $default_text . '" disabled="disabled">';
						echo '<img src="' . plugins_url() . '/woocommerce-booking/assets/images/ajax-loader.gif" id="event_ajax_loader_' . $post->ID . '" style="display:none">';
					}

					break;
			}

		}

		/**
		 * Sortable Columns List
		 *
		 * @param array $columns Columns Array
		 *
		 * @return array Modified Columns Array
		 *
		 * @since 4.2.0
		 *
		 * @hook manage_edit-bkap_gcal_event_sortable_columns
		 */

		public function bkap_custom_columns_sort( $columns ) {
			$custom = array(
				'bkap_event_summary' => 'bkap_event_summary',
				'bkap_start_date'    => 'bkap_start_date',
				'bkap_end_date'      => 'bkap_end_date',
			);

			return wp_parse_args( $custom, $columns );
		}

		/**
		 * Product column orderby.
		 *
		 * @access public
		 * @param mixed $vars Data
		 * @return array Modified Data to be displayed
		 *
		 * @since 4.2.0
		 *
		 * @hook request
		 */

		public function bkap_custom_columns_orderby( $vars ) {

			if ( isset( $vars['orderby'] ) ) {

				if ( 'bkap_start_date' == $vars['orderby'] ) {

					$vars = array_merge(
						$vars,
						array(
							'meta_key' => '_bkap_start',
							'orderby'  => 'meta_value_num',
						)
					);
				}

				if ( 'bkap_end_date' == $vars['orderby'] ) {
					$vars = array_merge(
						$vars,
						array(
							'meta_key' => '_bkap_end',
							'orderby'  => 'meta_value_num',
						)
					);
				}

				if ( 'bkap_event_summary' == $vars['orderby'] ) {
					$vars = array_merge(
						$vars,
						array(
							'meta_key' => '_bkap_summary',
							'orderby'  => 'meta_value',
						)
					);
				}
			}

			return $vars;
		}

		/**
		 * Adding JavaScript code in footer of the page for mapping the events with the product.
		 *
		 * @globals string $post_type
		 *
		 * @since 4.2.0
		 *
		 * @hook admin_footer
		 */

		public function bkap_bulk_admin_footer() {
			global $post_type;

			if ( $this->type === $post_type ) {
				?>
				<script type="text/javascript">
				jQuery( document ).on( "click", "#map_event", function () {

					var passed_id            = this.name;
					var exploded_id          = passed_id.split( '_' );
					var ID                   =  exploded_id[exploded_id.length - 1];
					var selectID             = "import_event_" + ID;
					var load_id              = "#event_ajax_loader_" + ID
					var product_id_selected  = document.getElementById( selectID ).value;
					
					jQuery( load_id ).show();
					
					var data = {
							ID:         ID,
							type:       'by_post',
							product_id: product_id_selected,
							action:     'bkap_map_imported_event'
					};

					jQuery.post('<?php echo get_admin_url(); ?>admin-ajax.php', data, function( response ) {
						if ( '' == response ) {
							var post_row = "#post-"+ID;

							var success_msg = '<div class="updated"><p>The event is successfully mapped.</p></div>';
							
							jQuery( "#bkap_display_notice" ).html( success_msg );
							jQuery( post_row ).remove();
							
						} else {
							jQuery( "#bkap_display_notice" ).html( response );
						}
						jQuery( load_id ).hide();
					});
				});

				// Enabling disabling the Map Event Button based on product selection.
				jQuery( document ).on( "change", "select[id^=import_event_]",  function() {    
					var id                  = this.id;

					var map_event_id_split  = id.split( '_' );
					var map_event_post      = map_event_id_split[ 2 ];

					var map_button_name = '[name="map_event_'+map_event_post+'"]';

					if( this.value == '' ){
						jQuery(map_button_name).prop("disabled", true);
					}else{                        
						jQuery(map_button_name).removeAttr('disabled');
					}
				});

				</script>
				<?php
			}
		}

		/**
		 * Adding search functionality on the Google Events.
		 *
		 * @param string $query Query to search
		 *
		 * @return string query
		 *
		 * @globals string $pagenow
		 * @globals string $typenow
		 *
		 * @since 4.2.0
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

			if ( ! get_query_var( 'event_search', true ) ) {
				return $query;
			}

			return wc_clean( $_GET['s'] );
		}

		/**
		 * Quering the posts based on the search.
		 *
		 * @param mixed $wp WP Variable
		 *
		 * @globals string $pagenow
		 * @globals mixed $wpdb
		 *
		 * @return mixed $wp
		 *
		 * @since 4.2.0
		 *
		 * @hook parse_query
		 */

		public function bkap_search_custom_fields( $wp ) {
			global $pagenow, $wpdb;

			if ( 'edit.php' != $pagenow || empty( $wp->query_vars['s'] ) || $wp->query_vars['post_type'] !== $this->type ) {
				return $wp;
			}
			 // strtotime does not support all date formats. hence it is suggested to use the "DateTime date_create_from_format" fn
			 $date_formats = bkap_get_book_arrays( 'bkap_date_formats' );
			 // get the global settings to find the date formats
			 $global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );
			 $date_format_set = $date_formats[ $global_settings->booking_date_format ];
			 $date_formatted  = date_create_from_format( $date_format_set, $_GET['s'] );

			 $date_strtotime = '';

			if ( isset( $date_formatted ) && $date_formatted != '' ) {
				$date           = date_format( $date_formatted, 'Y-m-d' );
				$date_strtotime = strtotime( $date );
			}

			 $query_args_meta = array(
				 'posts_per_page' => -1,
				 'post_type'      => 'bkap_gcal_event',
				 'meta_query'     => array(
					 'relation' => 'OR',
				 ),
			 );

			if ( $date_strtotime != '' ) {

				$start_date_search = array(
					'key'     => '_bkap_start',
					'value'   => sanitize_text_field( $date_strtotime ),
					'compare' => 'LIKE',
				);
				$end_date_search   = array(
					'key'     => '_bkap_end',
					'value'   => sanitize_text_field( $date_strtotime ),
					'compare' => 'LIKE',
				);

				array_push( $query_args_meta['meta_query'], $start_date_search );
				array_push( $query_args_meta['meta_query'], $end_date_search );

			} else {

				$summary_search = array(
					'key'     => '_bkap_summary',
					'value'   => sanitize_text_field( $_GET['s'] ),
					'compare' => 'LIKE',
				);
				array_push( $query_args_meta['meta_query'], $summary_search );
			}

			$result     = query_posts( $query_args_meta );
			$booking_id = array();

			if ( count( $result ) > 0 ) {

				foreach ( $result as $result_key => $result_value ) {
					array_push( $booking_id, $result_value->ID );
				}
			}

			$wp->query_vars['s']            = false;
			$wp->query_vars['post__in']     = $booking_id;
			$wp->query_vars['event_search'] = true;

		}

		/**
		 * Changing the Move to bin string to Delete Events.
		 *
		 * @param array $actions Actions Array
		 *
		 * @return array Actions Modified Array
		 * @since 4.2.0
		 */

		function bkap_bulk_actions( $actions ) {

			if ( isset( $actions['edit'] ) ) {
				unset( $actions['edit'] );
			}
			$actions['trash'] = __( 'Delete Events', 'woocommerce-booking' );

			return $actions;
		}
	}
	return new BKAP_Google_Events_View();
}
?>
