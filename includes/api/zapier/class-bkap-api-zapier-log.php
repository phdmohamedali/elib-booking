<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for displaying Zapier API Log Table.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api/Zapier
 * @category    Classes
 * @since       5.11.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

if ( ! class_exists( 'BKAP_API_Zapier_Log' ) ) {

	/**
	 * Display Zapier API Log Table.
	 *
	 * @since 5.11.0
	 */
	class BKAP_API_Zapier_Log extends WP_List_Table {

		/**
		 * Items per page.
		 *
		 * @var int
		 * @since 5.11.0
		 */
		public $items_per_page = 20;

		/**
		 * Database table for Zapier Log.
		 *
		 * @var string
		 * @since 5.11.0
		 */
		public static $database_table = 'bkap_api_zapier_log';

		/**
		 * Initialize the log table list.
		 *
		 * @since 5.11.0
		 */
		public function __construct() {
			parent::__construct(
				array(
					'singular' => 'log',
					'plural'   => 'logs',
					'ajax'     => false,
				)
			);
		}

		/**
		 * Displays the Zapier Log Table.
		 *
		 * @since 5.11.0
		 */
		public function display_log_table() {
			if ( ! empty( $_REQUEST['flush-logs'] ) ) { // phpcs:ignore
				self::flush_zapier_logs();
			}

			if ( isset( $_REQUEST['action'] ) && isset( $_REQUEST['log'] ) ) { // phpcs:ignore
				self::log_bulk_actions();
			}

			$this->prepare_items();
			?>
			<form method="post" id="zapier_log_form" action="">
				<?php $this->search_box( __( 'Search logs', 'woocommerce-booking' ), 'log' ); ?>
				<?php $this->display(); ?>

				<input type="hidden" name="zapier_page" value="woocommerce_booking_page" />
				<input type="hidden" name="zapier_action" value="zapier_setting" />
				<input type="hidden" name="zapier_section" value="zapier" />				

				<?php submit_button( __( 'Flush all logs', 'woocommerce-booking' ), 'delete', 'flush-logs', true ); ?>
				<?php wp_nonce_field( 'bkap-api-zapier-log-status' ); ?>
			</form>
			<?php
			wc_enqueue_js(
				"jQuery( '#flush-logs' ).on( 'click', function() {
					if ( window.confirm('" . esc_js( __( 'Are you sure you want to clear all logs from the database?', 'woocommerce-booking' ) ) . "') ) {
						return true;
					}
					return false;
				});"
			);
		}

		/**
		 * Get list columns.
		 *
		 * @return array
		 * @since 5.11.0
		 */
		public function get_columns() {
			return array(
				'cb'        => '<input type="checkbox" />',
				'timestamp' => __( 'Timestamp', 'woocommerce-booking' ),
				'action'    => __( 'Action', 'woocommerce-booking' ),
				'details'   => __( 'Details', 'woocommerce-booking' ),
			);
		}

		/**
		 * Column cb.
		 *
		 * @param  array $log Log array.
		 * @return string
		 * @since 5.11.0
		 */
		public function column_cb( $log ) {
			return sprintf( '<input type="checkbox" name="log[]" value="%1$s" />', esc_attr( $log['log_id'] ) );
		}

		/**
		 * Timestamp column.
		 *
		 * @param  array $log Log array.
		 * @return string
		 * @since 5.11.0
		 */
		public function column_timestamp( $log ) {
			return esc_html(
				gmdate(
					'Y-m-d H:i:s',
					$log['timestamp']
				)
			);
		}

		/**
		 * Action column.
		 *
		 * @param  array $log Log array.
		 * @return string
		 * @since 5.11.0
		 */
		public function column_action( $log ) {

			$action = $log['action'];

			if ( strpos( strtolower( $action ), 'error' ) !== false ) {
				$action = '<span style="color:red;font-weight:bold">' . $action . '</span>';
			}

			return $action;
		}

		/**
		 * Details column.
		 *
		 * @param  array $log Log array.
		 * @return string
		 * @since 5.11.0
		 */
		public function column_details( $log ) {
			return esc_html( $log['message'] );
		}

		/**
		 * Get bulk actions.
		 *
		 * @return array
		 * @since 5.11.0
		 */
		protected function get_bulk_actions() {
			return array(
				'delete' => __( 'Delete', 'woocommerce-booking' ),
			);
		}

		/**
		 * Get a list of sortable columns.
		 *
		 * @return array
		 * @since 5.11.0
		 */
		protected function get_sortable_columns() {
			return array(
				'timestamp' => array( 'timestamp', true ),
			);
		}

		/**
		 * Prepare table list items.
		 *
		 * @global wpdb $wpdb
		 * @since 5.11.0
		 */
		public function prepare_items() {
			global $wpdb;

			$this->_column_headers = array(
				$this->get_columns(),
				array(),
				$this->get_sortable_columns(),
			);

			$where  = $this->get_items_query_where();
			$order  = $this->get_items_query_order();
			$limit  = $this->get_items_query_limit();
			$offset = $this->get_items_query_offset();

			$query_items = "
				SELECT log_id, timestamp, action, message
				FROM {$wpdb->prefix}" . self::$database_table . "
				{$where} {$order} {$limit} {$offset}
			";

			$this->items = $wpdb->get_results( $query_items, ARRAY_A ); // phpcs:ignore

			$query_count = "SELECT COUNT(log_id) FROM {$wpdb->prefix}" . self::$database_table . " {$where}";
			$total_items = $wpdb->get_var( $query_count ); // phpcs:ignore

			$this->set_pagination_args(
				array(
					'total_items' => $total_items,
					'per_page'    => $this->items_per_page,
					'total_pages' => ceil( $total_items / $this->items_per_page ),
				)
			);
		}

		/**
		 * Get prepared LIMIT clause for query.
		 *
		 * @global wpdb $wpdb
		 *
		 * @return string Prepared LIMIT clause for items query.
		 * @since 5.11.0
		 */
		protected function get_items_query_limit() {
			global $wpdb;
			return $wpdb->prepare( 'LIMIT %d', $this->items_per_page );
		}

		/**
		 * Get prepared OFFSET clause for query.
		 *
		 * @global wpdb $wpdb
		 *
		 * @return string Prepared OFFSET clause for query.
		 * @since 5.11.0
		 */
		protected function get_items_query_offset() {
			global $wpdb;

			$current_page = $this->get_pagenum();
			if ( 1 < $current_page ) {
				$offset = $this->items_per_page * ( $current_page - 1 );
			} else {
				$offset = 0;
			}

			return $wpdb->prepare( 'OFFSET %d', $offset );
		}

		/**
		 * Get prepared ORDER BY clause for query.
		 *
		 * @return string Prepared ORDER BY clause for query.
		 * @since 5.11.0
		 */
		protected function get_items_query_order() {

			$valid_orders = array( 'timestamp' );
			if ( ! empty( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], $valid_orders ) ) { // phpcs:ignore
				$by = wc_clean( $_REQUEST['orderby'] ); // phpcs:ignore
			} else {
				$by = 'timestamp';
			}

			$by = esc_sql( $by );

			if ( ! empty( $_REQUEST['order'] ) && 'asc' === strtolower( $_REQUEST['order'] ) ) { // phpcs:ignore
				$order = 'ASC';
			} else {
				$order = 'DESC';
			}

			return "ORDER BY {$by} {$order}, log_id {$order}";
		}

		/**
		 * Get prepared WHERE clause for query.
		 *
		 * @global wpdb $wpdb
		 *
		 * @return string Prepared WHERE clause for query.
		 * @since 5.11.0
		 */
		protected function get_items_query_where() {
			global $wpdb;

			$where_conditions = array();
			$where_values     = array();
			$action_request   = ( isset( $_REQUEST['action'] ) && '' !== $_REQUEST['action'] && 'calendar_sync_settings' !== $_REQUEST['action'] ) ? wc_clean( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore

			if ( ! empty( $action_request ) ) {
				$where_conditions[] = 'action like %s';
				$where_values[]     = '%' . $wpdb->esc_like( $action_request ) . '%'; // phpcs:ignore
			}

			if ( ! empty( $_REQUEST['s'] ) ) { // phpcs:ignore
				$where_conditions[] = 'message like %s';
				$where_values[]     = '%' . $wpdb->esc_like( wc_clean( wp_unslash( $_REQUEST['s'] ) ) ) . '%'; // phpcs:ignore
			}

			if ( empty( $where_conditions ) ) {
				return '';
			}

			return $wpdb->prepare( 'WHERE 1 = 1 AND ' . implode( ' AND ', $where_conditions ), $where_values ); // phpcs:ignore
		}

		/**
		 * Creates log table if it does not exist.
		 *
		 * @global wpdb $wpdb
		 *
		 * @return bool False on error, true if already exists or success.
		 * @since 5.11.0
		 */
		public static function maybe_create_log_table() {
			global $wpdb;

			$collate = '';

			if ( $wpdb->has_cap( 'collation' ) ) {
				$collate = $wpdb->get_charset_collate();
			}

			return self::maybe_create_table(
				$wpdb->prefix . self::$database_table,
				"
				CREATE TABLE {$wpdb->prefix}" . self::$database_table . " (
					log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					timestamp BIGINT UNSIGNED NOT NULL,
					action varchar(200) NOT NULL,
					message longtext NOT NULL,
					request longtext NULL,
					PRIMARY KEY (log_id)
				  ) $collate;
				"
			);
		}

		/**
		 * Creates a table in the database if it doesn't already exist.
		 *
		 * @since 5.11.0
		 *
		 * @global wpdb $wpdb.
		 *
		 * @param string $table_name Database table name.
		 * @param string $sql SQL statement to create table.
		 * @return bool True on success or if the table already exists. False on failure.
		 */
		public static function maybe_create_table( $table_name, $sql ) {
			global $wpdb;

			foreach ( $wpdb->get_col( 'SHOW TABLES', 0 ) as $table ) { // phpcs:ignore
				if ( $table === $table_name ) {
					return true;
				}
			}

			$wpdb->query( $sql ); // phpcs:ignore

			foreach ( $wpdb->get_col( 'SHOW TABLES', 0 ) as $table ) { // phpcs:ignore
				if ( $table === $table_name ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Adds log entry to the database.
		 *
		 * @param string $action Action.
		 * @param string $message Log message.
		 * @param array  $request Request parameter or any important information that will be serialized and stored in the database.
		 * @since 5.11.0
		 *
		 * @return bool True if log entry action was successful.
		 */
		public static function add_log( $action, $message, $request = '' ) {
			global $wpdb;

			// Don't log if Logging has not been enabled in Zapier API Settings.
			if ( ! BKAP_API_Zapier_Settings::bkap_api_zapier_is_logging_enabled() ) {
				return;
			}

			$insert = array(
				'timestamp' => strtotime( 'now' ),
				'action'    => $action,
				'message'   => $message,
				'request'   => $request,
			);

			$format = array(
				'%d',
				'%s',
				'%s',
				'%s',
			);

			if ( ! empty( $request ) ) {
				$insert['request'] = var_export( $request, true ); // phpcs:ignore
			}

			return false !== $wpdb->insert( "{$wpdb->prefix}" . self::$database_table, $insert, $format ); // phpcs:ignore
		}

		/**
		 * Clear all zapier logs from the DB.
		 *
		 * @return bool True if flush was successful.
		 * @since 5.11.0
		 */
		public static function flush() {
			global $wpdb;

			return $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}" . self::$database_table ); // phpcs:ignore
		}

		/**
		 * Delete selected logs from DB.
		 *
		 * @param int|string|array $log_ids Log ID or array of Log IDs to be deleted.
		 *
		 * @return bool
		 * @since 5.11.0
		 */
		public static function delete( $log_ids ) {
			global $wpdb;

			if ( ! is_array( $log_ids ) ) {
				$log_ids = array( $log_ids );
			}

			$format   = array_fill( 0, count( $log_ids ), '%d' );
			$query_in = '(' . implode( ',', $format ) . ')';
			return $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}" . self::$database_table . " WHERE log_id IN {$query_in}", $log_ids ) ); // phpcs:ignore
		}

		/**
		 * Clear DB log table.
		 *
		 * @since 5.11.0
		 */
		private static function flush_zapier_logs() {
			if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bkap-api-zapier-log-status' ) ) { // phpcs:ignore
				wp_die( esc_html__( 'Action failed. Please refresh the page and try again.', 'woocommerce-booking' ) );
			}

			self::flush();

			wp_safe_redirect( esc_url_raw( admin_url( 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=zapier#bkap_api_zapier_event_log' ) ) );
			exit();
		}

		/**
		 * Bulk delete Zapier log.
		 *
		 * @since 5.11.0
		 */
		private static function log_bulk_actions() {
			if ( empty( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'bkap-api-zapier-log-status' ) ) { // phpcs:ignore
				wp_die( esc_html__( 'Action failed. Please refresh the page and try and again.', 'woocommerce-booking' ) );
			}

			$log_ids = array_map( 'absint', (array) isset( $_REQUEST['log'] ) ? wp_unslash( $_REQUEST['log'] ) : array() ); // phpcs:ignore

			if ( ( isset( $_REQUEST['action'] ) && 'delete' === $_REQUEST['action'] ) ) { // phpcs:ignore
				self::delete( $log_ids );
				wp_safe_redirect( esc_url_raw( admin_url( 'edit.php?post_type=bkap_booking&page=woocommerce_booking_page&action=calendar_sync_settings&section=zapier#bkap_api_zapier_event_log' ) ) );
				exit();
			}
		}
	}
}
