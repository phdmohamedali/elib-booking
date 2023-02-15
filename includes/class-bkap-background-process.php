<?php
/**
 * Bookings and Appointment Plugin for WooCommerce.
 *
 * Class for processing Background Actions using the WordPress Cron Job Service.
 *
 * @author      Tyche Softwares
 * @package     BKAP/Api
 * @category    Classes
 * @since       5.13.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BKAP_Background_Process' ) ) {

	/**
	 * BKAP Background Actions.
	 *
	 * @since 5.13.0
	 */
	abstract class BKAP_Background_Process {

		/**
		 * Action.
		 *
		 * @var string $action.
		 * @access protected
		 */
		protected $action = 'bkap_background_process';

		/**
		 * Background process start time.
		 *
		 * @var int $start_time
		 * @access protected
		 */
		protected $start_time = 0;

		/**
		 * Hook Identifier.
		 *
		 * @var mixed $hook_identifier
		 * @access protected
		 */
		protected $hook_identifier;

		/**
		 * Interval Identifier.
		 *
		 * @var mixed $interval_identifier
		 * @access protected
		 */
		protected $interval_identifier;

		/**
		 * Data.
		 *
		 * @var array $data
		 * @access protected
		 */
		protected $data = array();

		/**
		 * Constructor.
		 *
		 * @since 5.13.0
		 * @param string $hook_identifier Hook Identifier.
		 */
		public function __construct( $hook_identifier ) {
			$this->hook_identifier     = 'bkap_' . $hook_identifier;
			$this->interval_identifier = 'bkap_' . $hook_identifier . '_interval';

			add_filter( 'cron_schedules', array( $this, 'schedule_actions' ) );
			add_action( $this->hook_identifier, array( $this, 'handle_actions' ) );
		}

		/**
		 * Specify intervals for BKAP Cron Schedules.
		 *
		 * @since 5.13.0
		 * @param mixed $schedules Schedules.
		 * @return mixed
		 */
		public function schedule_actions( $schedules ) {
			$interval = apply_filters( $this->hook_identifier . '_cron_interval', 15 );

			// Adds 15 seconds to existing schedules.
			$schedules[ $this->interval_identifier ] = array(
				'interval' => $interval,
				/* translators: %d: seconds */
				'display'  => sprintf( __( 'Every %d Second(s)', 'woocommerce-booking' ), $interval ),
			);

			return $schedules;
		}

		/**
		 * Handle BKAP Background Actions.
		 *
		 * @since 5.13.0
		 */
		public function handle_actions() {

			if ( $this->is_process_running() ) {
				// Background process already running.
				exit;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				$this->clear_scheduled_action();
				exit;
			}

			$this->start_process();
			exit;
		}

		/**
		 * Checks if current process is running in a background process.
		 *
		 * @since 5.13.0
		 */
		protected function is_process_running() {
			if ( get_transient( $this->hook_identifier . '_process_lock' ) ) {
				// Process already running.
				return true;
			}

			return false;
		}

		/**
		 * Checks if batch in process queue is empty.
		 *
		 * @since 5.13.0
		 */
		protected function is_queue_empty() {
			global $wpdb;

			$key = $wpdb->esc_like( $this->hook_identifier . '_batch_' ) . '%';

			$count = $wpdb->get_var(
				$wpdb->prepare(
					"
				SELECT COUNT(*)
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
			",
					$key
				)
			);

			return ( $count > 0 ) ? false : true;
		}

		/**
		 * Clears all scheduled background actions.
		 *
		 * @since 5.13.0
		 */
		protected function clear_scheduled_action() {
			$timestamp = wp_next_scheduled( $this->hook_identifier );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $this->hook_identifier );
			}
		}

		/**
		 * Starts Background Process and passes queue item to the process task handler.
		 *
		 * @since 5.13.0
		 */
		protected function start_process() {
			$this->lock_process();

			do {
				$batch = $this->get_batch();

				foreach ( $batch->data as $key => $value ) {
					$task = $this->process( $value );

					if ( $task ) {
						unset( $batch->data[ $key ] );
					} else { // phpcs:ignore
						// Error occured.
						// Leave batch in queue to be retried afterwards.
						// TODO: Need to condider repetitive failures and remove from batch queue eventually.
					}

					if ( $this->time_exceeded() || $this->memory_exceeded() || $this->is_process_cancelled() ) {
						// Batch limits reached. So we take a break and sip some coffe :) .
						break;
					}
				}

				// Update or delete current batch.
				if ( 0 !== count( $batch->data ) && ! $this->is_process_cancelled() ) {
					$this->update( $batch->key, $batch->data );
				} else {
					$this->complete_batch();
					$this->delete( $batch->key );
				}
			} while ( ! $this->time_exceeded() && ! $this->memory_exceeded() && ! $this->is_queue_empty() && ! $this->is_process_cancelled() );

			$this->unlock_process();

			// Start next batch or complete process.
			if ( ! $this->is_queue_empty() ) {
				$this->dispatch();
			} else {
				$this->complete();
			}

			wp_die();
		}

		/**
		 * Locks the process to prevent multiple instances from running simulataneously.
		 *
		 * @since 5.13.0
		 */
		protected function lock_process() {
			$this->start_time = time();

			$lock_duration = 60; // 1 minute.
			$lock_duration = apply_filters( $this->hook_identifier . '_queue_lock_time', $lock_duration );

			set_transient( $this->hook_identifier . '_process_lock', microtime(), $lock_duration );
		}

		/**
		 * Unlocks the process.
		 *
		 * @since 5.13.0
		 */
		protected function unlock_process() {
			delete_transient( $this->hook_identifier . '_process_lock' );

			return $this;
		}

		/**
		 * Gets the batch data for a background process.
		 *
		 * @since 5.13.0
		 */
		protected function get_batch() {
			global $wpdb;

			$key = $wpdb->esc_like( $this->hook_identifier . '_batch_' ) . '%';

			$query = $wpdb->get_row(
				$wpdb->prepare(
					"
				SELECT *
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				ORDER BY option_id ASC
				LIMIT 1
			",
					$key
				)
			);

			$batch       = new stdClass();
			$batch->key  = $query->option_name;
			$batch->data = maybe_unserialize( $query->option_value );

			return $batch;
		}

		/**
		 * Checks if the batch process has exceeded a specified time limit.
		 *
		 * @since 5.13.0
		 */
		protected function time_exceeded() {
			$finish = $this->start_time + apply_filters( $this->hook_identifier . '_default_time_limit', 20 ); // 20 seconds.
			$return = false;

			if ( time() >= $finish ) {
				$return = true;
			}

			return apply_filters( $this->hook_identifier . '_time_exceeded', $return );
		}

		/**
		 * Ensure that the batch process does not exceed the WordPress memomry limit.
		 *
		 * @since 5.13.0
		 */
		protected function memory_exceeded() {
			$memory_limit   = $this->get_memory_limit() * 0.7; // 70% of maximum memory - safe bet.
			$current_memory = memory_get_usage( true );
			$return         = false;

			if ( $current_memory >= $memory_limit ) {
				$return = true;
			}

			return apply_filters( $this->hook_identifier . '_memory_exceeded', $return );
		}

		/**
		 * Get the PHP memory limit.
		 *
		 * @since 5.13.0
		 */
		protected function get_memory_limit() {

			$memory_limit = '128M';

			if ( function_exists( 'ini_get' ) ) {
				$memory_limit = ini_get( 'memory_limit' );
			}

			if ( ! $memory_limit || -1 === intval( $memory_limit ) ) {
				// Unlimited, set to 32GB.
				$memory_limit = '32000M';
			}

			return wp_convert_hr_to_bytes( $memory_limit );
		}

		/**
		 * Checks if the current process is cancelled.
		 *
		 * @since 5.13.0
		 */
		protected function is_process_cancelled() {
			return false !== get_transient( $this->hook_identifier . '_process_cancelled' );
		}

		/**
		 * Pushes a new batch process to the batch queue.
		 *
		 * @since 5.13.0
		 * @param array $data Batch Data.
		 */
		public function push_to_queue( $data ) {
			$this->data[] = $data;

			return $this;
		}

		/**
		 * Saves batch queue.
		 *
		 * @since 5.13.0
		 */
		public function save() {
			$key = $this->generate_key();

			if ( ! empty( $this->data ) ) {
				update_option( $key, $this->data );
			}

			return $this;
		}

		/**
		 * Generates a unique key that will be used to save or identify a batch queue.
		 *
		 * @since 5.13.0
		 * @param int $length Length.
		 * @return string
		 */
		public function generate_key( $length = 64 ) {
			$unique  = md5( microtime() . wp_rand() );
			$prepend = $this->hook_identifier . '_batch_';

			return substr( $prepend . $unique, 0, $length );
		}

		/**
		 * Updates the batch queue.
		 *
		 * @since 5.13.0
		 * @param string $key Key.
		 * @param array  $data Data.
		 *
		 * @return $this
		 */
		public function update( $key, $data ) {
			if ( ! empty( $data ) ) {
				update_option( $key, $data );
			}

			return $this;
		}

		/**
		 * Deletes queue.
		 *
		 * @since 5.13.0
		 * @param string $key Key.
		 * @return string
		 */
		public function delete( $key ) {
			delete_option( $key );

			return $this;
		}

		/**
		 * Called when current batch is completed.
		 *
		 * @since 5.13.0
		 */
		protected function complete_batch() {
			// Perform some actions when current batch is completed.
		}

		/**
		 * Called when the batch job is complete.
		 *
		 * @since 5.13.0
		 */
		protected function complete() {
			// $this->clear_scheduled_event();
			// No data to process.
			$this->clear_scheduled_action();

			delete_transient( $this->hook_identifier . '_process_cancelled' );
		}

		/**
		 * Dispatches batch job.
		 *
		 * @since 5.13.0
		 */
		protected function dispatch() {
			$this->schedule_batch_event();

			delete_transient( $this->hook_identifier . '_process_cancelled' );
		}

		/**
		 * Schedules the batch event.
		 *
		 * @since 5.13.0
		 */
		protected function schedule_batch_event() {
			if ( ! wp_next_scheduled( $this->hook_identifier ) ) {
				wp_schedule_event( time(), $this->interval_identifier, $this->hook_identifier );
			}
		}

		/**
		 * Clears the scheduled batch event.
		 *
		 * @since 5.13.0
		 */
		protected function clear_scheduled_batch_event() {
			$timestamp = wp_next_scheduled( $this->cron_hook_identifier );

			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $this->cron_hook_identifier );
			}
		}

		/**
		 * This stops the batch process processing and deletes the batch job.
		 *
		 * @since 5.13.0
		 */
		public function cancel_process() {

			if ( ! $this->is_queue_empty() ) {
				$batch = $this->get_batch();
				$this->delete( $batch->key );
				$this->unlock_process();
				wp_clear_scheduled_hook( $this->hook_identifier );

				set_transient( $this->hook_identifier . '_process_cancelled', $this->hook_identifier . '_process_cancelled' );
			}
		}

		/**
		 * Displays notice.
		 *
		 * @since 5.13.0
		 * @param array $args Array of arguments used in generating the HTML for the notice.
		 */
		public static function display_notice( $args ) {

			$defaults = array(
				'status'           => 'success',
				'dismissible'      => 'is-dismissible',
				'message'          => '',
				'notice'           => '',
				'dismiss_button'   => false,
				'readonly_content' => '',
				'id'               => '',
				'action'           => '',
			);

			$args      = wp_parse_args( $args, $defaults );
			$notice_id = '';

			if ( ! empty( $args['id'] ) ) {
				$notice_id = ' id="' . esc_attr( $args['id'] ) . '"';
			}
			?>

			<div class="notice notice-<?php echo esc_attr( $args['status'] ); ?> <?php echo esc_attr( $args['dismissible'] ); ?>"<?php echo $notice_id; // phpcs:ignore ?>>
				<p>
					<?php echo $args['message']; // phpcs:ignore ?>
				</p>

				<?php
				if ( $args['action'] ) :
					?>
				<p>
					<?php echo $args['action']; // phpcs:ignore ?>
				</p>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * This method is meant to be overriden to perform a process on a task.
		 *
		 * @since 5.13.0
		 * @param mixed $task Task to be processed.
		 * @return mixed
		 */
		abstract protected function process( $task );
	}
}
