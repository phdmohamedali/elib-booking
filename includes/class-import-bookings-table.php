<?php
/**
 * Load WP_List_Table if not loaded
 *
 * @version  4.2.0
 * @category Class
 * @author   Tyche Softwares
 * @package  BKAP/Google-Calendar-Sync
 * @class    WAPBK_Import_Bookings_Table
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Load WP_List_Table if not loaded
 *
 * @class WAPBK_Import_Bookings_Table
 */

class WAPBK_Import_Bookings_Table extends WP_List_Table {

	/**
	 * Number of results to show per page
	 *
	 * @var string
	 */
	public $per_page = 30;

	/**
	 * URL of this page
	 *
	 * @var string
	 */
	public $base_url;

	public $total_count;
	/**
	 * Get things started
	 *
	 * @see WP_List_Table::__construct()
	 */
	public function __construct() {

		global $status, $page;

		// Set parent defaults
		parent::__construct(
			array(
				'ajax' => true,                         // Does this table support ajax?
			)
		);

		$this->get_import_booking_counts();
		$this->base_url = admin_url( 'edit.php?post_type=bkap_booking&page=woocommerce_import_page' );
	}

	public function bkap_prepare_items() {

		global $wpdb;

		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();
		$data     = $this->import_bookings_data();
		$status   = isset( $_GET['status'] ) ? $_GET['status'] : 'any';

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$options_query = 'SELECT * FROM `' . $wpdb->prefix . "options`
                            WHERE option_name like 'bkap_imported_events_%'";

		$results = $wpdb->get_results( $options_query );

		if ( isset( $results ) && count( $results ) > 0 ) {
			$total_items = count( $results );
		} else {
			$total_items = 0;
		}
		$total_items = apply_filters( 'bkap_import_bookings_count', $total_items );

		$this->items = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,                      // WE have to calculate the total number of items
				'per_page'    => $this->per_page,                       // WE have to determine how many items to show on a page
				'total_pages' => ceil( $total_items / $this->per_page ),   // WE have to calculate the total number of pages
			)
		);

	}

	public function get_columns() {
		$columns = array(
			'import_event_id' => __( 'Import Event ID', 'woocommerce-booking' ),
			'summary'         => __( 'Event Summary', 'woocommerce-booking' ),
			'description'     => __( 'Event Description', 'woocommerce-booking' ),
			'booking_details' => __( 'Booking Details', 'woocommerce-booking' ),
			'product_list'    => __( 'Product', 'woocommerce-booking' ),
			'actions'         => __( 'Actions', 'woocommerce-booking' ),
		);

		return apply_filters( 'bkap_import_bookings_table_columns', $columns );
	}

	public function get_hidden_columns() {

		$columns = array( 'import_event_id' );
		return apply_filters( 'bkap_import_bookings_hidden_columns', $columns );
	}

	public function get_sortable_columns() {
		$columns = array();
		return apply_filters( 'bkap_import_bookings_sortable_columns', $columns );
	}

	public function get_import_booking_counts() {

		global $wpdb;

		$options_query = 'SELECT * FROM `' . $wpdb->prefix . "options`
                            WHERE option_name like 'bkap_imported_events_%'";

		$results = $wpdb->get_results( $options_query );

		if ( isset( $results ) && count( $results ) > 0 ) {
			$total_items = count( $results );
		} else {
			$total_items = 0;
		}

		$total_items       = apply_filters( 'bkap_import_bookings_count', $total_items );
		$this->total_count = $total_items;

	}
	public function get_views() {

		$current     = isset( $_GET['status'] ) ? $_GET['status'] : '';
		$total_count = '&nbsp;<span class="count">(' . $this->total_count . ')</span>';

		$views = array(
			'all' => sprintf( '<a href="%s"%s>%s</a>', remove_query_arg( array( 'status', 'paged' ) ), $current === 'all' || $current == '' ? ' class="current"' : '', __( 'All', 'woocommerce-booking' ) . $total_count ),
		);
		return apply_filters( 'bkap_import_bookings_table_views', $views );

	}

	public function import_bookings_data() {

		global $wpdb;
		$per_page      = $this->per_page;
		$options_query = 'SELECT option_name, option_value FROM `' . $wpdb->prefix . "options`
                            WHERE option_name like 'bkap_imported_events_%'";
		$results       = $wpdb->get_results( $options_query );

		if ( isset( $_GET['paged'] ) && $_GET['paged'] > 1 ) {
			$page_number = $_GET['paged'] - 1;
		} else {
			$page_number = 0;
		}

		if ( count( $results ) > $per_page ) {
			$results = array_chunk( $results, $per_page );
			if ( isset( $results[ $page_number ] ) ) {
				$results = $results[ $page_number ];
			} else {
				$results = array();
			}
		}
		$return_import_bookings = $this->bkap_create_data( $results );

		return apply_filters( 'bkap_import_bookings_table_data', $return_import_bookings );
	}

	public function bkap_create_data( $results ) {

		global $bkap_date_formats;

		$i = 0;

		$return_import_bookings = array();

		if ( isset( $results ) && count( $results ) > 0 ) {

			if ( ! current_time( 'timestamp' ) ) {
				$tdif = 0;
			} else {
				$tdif = current_time( 'timestamp' ) - time();
			}

			$global_settings = json_decode( get_option( 'woocommerce_booking_global_settings' ) );

			$date_format_to_display = $global_settings->booking_date_format;
			$time_format_to_display = $global_settings->booking_time_format;

			foreach ( $results as $key => $value ) {
				// reset the variables
				$booking_date_to_display  = '';
				$checkout_date_to_display = '';
				$booking_from_time        = '';
				$booking_to_time          = '';

				$event_details                = json_decode( $value->option_value );
				$return_import_bookings[ $i ] = new stdClass();

				$return_import_bookings[ $i ]->import_event_id = $value->option_name;
				$return_import_bookings[ $i ]->summary         = $event_details->summary;

				$return_import_bookings[ $i ]->description = $event_details->description;

				if ( $event_details->end != '' && $event_details->start != '' ) {

					$event_start = $event_details->start;
					$event_end   = $event_details->end;

					$diff = $event_end - $event_start;

					$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );

					if ( $diff == 86400 ) {
						$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );
					} else {
						$checkout_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_end );
					}

					if ( $time_format_to_display == '12' ) {
						$booking_from_time = date( 'h:i A', $event_start + $tdif );
						$booking_to_time   = date( 'h:i A', $event_end + $tdif );
					} else {
						$booking_from_time = date( 'H:i', $event_start + $tdif );
						$booking_to_time   = date( 'H:i', $event_end + $tdif );
					}

					if ( $checkout_date_to_display == $booking_date_to_display ) {
						$booking_details = $booking_date_to_display . '<br>';
					} else {
						$booking_details = $booking_date_to_display . ' - ' . $checkout_date_to_display . '<br>';
					}

					if ( $booking_from_time != '' && $booking_to_time != '' && $booking_from_time != $booking_to_time ) {
						$booking_details .= $booking_from_time . ' - ' . $booking_to_time;
					} elseif ( $booking_from_time != '' && $booking_from_time != $booking_to_time ) {
						$booking_details .= $booking_from_time;
					}
				} elseif ( $event_details->start != '' && $event_details->end == '' ) {

					$event_start             = $event_details->start;
					$booking_date_to_display = date( $bkap_date_formats[ $date_format_to_display ], $event_start );

					if ( $event_start >= current_time( 'timestamp' ) ) {

						if ( $time_format_to_display == '12' ) {
							$booking_from_time = date( 'h:i A', $event_start + $tdif );
						} else {
							$booking_from_time = date( 'H:i', $event_start + $tdif );
						}
					}

					$booking_details = $booking_date_to_display . '<br>';

					if ( $booking_from_time != '' ) {
						$booking_details .= $booking_from_time;
					}
				}

				$return_import_bookings[ $i ]->booking_details = $booking_details;

				$i++;
			}
		}

		return $return_import_bookings;
	}

	public function column_default( $import_booking, $column_name ) {

		switch ( $column_name ) {

			case 'product_list':
				$product_list = bkap_common::get_woocommerce_product_list();

				$default_text = __( 'Select a Product', 'woocommerce-booking' );

				$value  = '<select style="max-width:180px;width:100%;" id="import_event_' . $import_booking->import_event_id . '">';
				$value .= '<option value="" > ' . $default_text . '</option>';

				foreach ( $product_list as $k => $v ) {
					$value .= '<option value="' . $v[1] . '" >' . $v[0] . '</option>';
				}
				$value .= '</select>';
				break;
			case 'actions':
				$value = '<input type="button" class="save_button" id="map_event" name="map_event_' . $import_booking->import_event_id . '" value="Map Event"> 
                          <input type="button" class="save_button" id="discard_event" name="discard_event_' . $import_booking->import_event_id . '" value="Discard Event">';
				break;
			default:
				$value = isset( $import_booking->$column_name ) ? $import_booking->$column_name : '';
		}

		return apply_filters( 'bkap_import_booking_table_column_default', $value, $import_booking, $column_name );

	}
}

