<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Resource Details Meta Box
 *
 * @author   Tyche Softwares
 * @package  BKAP/Meta-Boxes
 * @category Classes
 * @class    BKAP_Email_Reminder_Settings_Meta_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'BKAP_Send_Reminder_Meta_Box' ) ) {

	/**
	 * BKAP_Send_Reminder_Meta_Box.
	 */
	class BKAP_Send_Reminder_Meta_Box {

		/**
		 * Meta box ID.
		 *
		 * @var string
		 */
		public $id;

		/**
		 * Meta box title.
		 *
		 * @var string
		 */
		public $title;

		/**
		 * Meta box title.
		 *
		 * @var string
		 */
		public $prefix = 'bkap';

		/**
		 * Meta box context.
		 *
		 * @var string
		 */
		public $context;

		/**
		 * Meta box priority.
		 *
		 * @var string
		 */
		public $priority;

		/**
		 * Meta box post types.
		 *
		 * @var array
		 */
		public $post_types;

		/**
		 * Meta box post type.
		 *
		 * @var array
		 */
		public $post_type = 'bkap_reminder';

		/**
		 * Is meta boxes saved once?
		 *
		 * @var boolean
		 */
		private static $saved_meta_box = false;

		/**
		 * Constructor.
		 */
		public function __construct() {

			add_action( 'add_meta_boxes', array( $this, 'add_reminder_meta_boxes' ), 10, 1 );

			$this->id         = $this->prefix . '-email-reminder-settings';
			$this->title      = __( 'Email Reminder Settings', 'woocommerce-booking' );
			$this->context    = 'normal';
			$this->priority   = 'high';
			$this->post_types = array( $this->post_type );

			add_action( 'save_post', array( $this, $this->prefix . '_save_email_reminder' ), 10, 2 );

			add_filter( 'woocommerce_screen_ids', array( $this, 'set_wc_screen_ids' ), 10, 1 );

			// remove the submit div.
			add_action( 'admin_menu', array( $this, 'remove_publish_metabox' ), 10 );
			add_filter( 'post_updated_messages', array( $this, 'reminder_updated_messages' ), 10 );

			//wp_enqueue_style( 'bkap-booking', bkap_load_scripts_class::bkap_asset_url( '/assets/css/booking.css', BKAP_FILE ), '', '1.0', false );
		}

		/**
		 * Change reminder update messages.
		 *
		 * @param array $messages Messages for post.
		 * @since 5.14.0
		 */
		public function reminder_updated_messages( $messages ) {

			if ( get_post_type() === $this->post_type ) {
				$messages['post'][1] = __( 'Reminder updated.', 'woocommerce-booking' );
				$messages['post'][4] = __( 'Reminder updated.', 'woocommerce-booking' );
			}

			return $messages;
		}

		/**
		 * Save Reminder Settings.
		 *
		 * @param int $post_id Reminder ID.
		 * @param obj $post Reminder Object.
		 * @since 5.14.0
		 */
		public function bkap_save_email_reminder( $post_id, $post ) {

			if ( 'bkap_reminder' == $post->post_type ) {

				if ( ! isset( $_POST['bkap_email_reminder_settings_nonce'] ) || ! wp_verify_nonce( $_POST['bkap_email_reminder_settings_nonce'], 'bkap_email_reminder_settings' ) ) {
					return;
				}

				bkap_reminder_save_data( $post_id );

				// Reference : https://stackoverflow.com/questions/21717159/get-custom-fields-values-in-filter-on-wp-insert-post-data.
				// unhook this function so it doesn't loop infinitely.
				remove_action( 'save_post', array( $this, 'bkap_save_email_reminder' ) );

				// update the post, which calls save_post again.
				$status = 'bkap-active';
				if ( isset( $_POST[ 'bkap_reminder_action' ] ) ) {
					$status = sanitize_text_field( $_POST[ 'bkap_reminder_action' ] );
				}
				wp_update_post( array( 'ID' => $post_id, 'post_status' => $status ) );

				// re-hook this function.
				add_action( 'save_post', array( $this, 'bkap_save_email_reminder' ) );
			}
		}

		/**
		 * Remove Publish Meta Box.
		 *
		 * @since 5.14.0
		 */
		public function remove_publish_metabox() {
			remove_meta_box( 'submitdiv', 'bkap_reminder', 'side' );
		}

		/**
		 * Setting screen id to load the help tip from WooCommerce,
		 *
		 * @param array $screen Screens Data.
		 * @since 5.14.0
		 */
		public function set_wc_screen_ids( $screen ) {

			$current_screen = get_current_screen();

			if ( $current_screen->post_type ) {
				$screen[] = $this->post_type;
			}
			
			return $screen;
		}

		/**
		 * Adding Meta Boxes on the Reminders Post Page.
		 *
		 * @since 5.14.0
		 */
		public function add_reminder_meta_boxes() {

			$meta_box_data = array(
				'email-reminder-settings' => array(
					'id'    => 'email-reminder-settings',
					'title' => __( 'Email Settings', 'woocommerce-booking' ),
					'context' => 'normal',
					'priority' => 'high',
				),
				'reminder-settings' => array(
					'id'    => 'reminder-settings',
					'title' => __( 'Reminder Settings', 'woocommerce-booking' ),
					'context' => 'normal',
					'priority' => 'high',
				),
				'merge-codes' => array(
					'id' => 'merge-codes',
					'title' => __( 'Merge Codes', 'woocommerce-booking' ),
					'context' => 'side',
					'priority' => 'default',
				),
				'send-test' => array(
					'id' => 'send-test',
					'title' => __( 'Send Test', 'woocommerce-booking' ),
					'context' => 'side',
					'priority' => 'default',
				),
				'reminder-actions' => array(
					'id' => 'reminder-actions',
					'title' => __( 'Reminder Actions', 'woocommerce-booking' ),
					'context' => 'side',
					'priority' => 'high',
				),
			);

			foreach ( $meta_box_data as $data ) {
				add_meta_box(
					$data['id'],
					$data['title'],
					array( $this, str_replace('-', '_', $data['id'] ) ),
					$this->post_types,
					$data['context'],
					$data['priority']
				);
			}
		}

		/**
		 * Reminder Actions Meta Box.
		 *
		 * @since 5.14.0
		 */
		public function reminder_actions( $post = '' ) {

			$reminder_actions = array(
				'bkap-active'   => __( 'Active', 'woocommerce-booking' ),
				'bkap-inactive' => __( 'Inactive', 'woocommerce-booking' ),
			);

			/* Reminder Actions */
			wc_get_template(
				'reminders/html-bkap-reminder-actions.php',
				array(
					'post'             => $post,
					'post_id'          => $post->ID,
					'prefix'           => $this->prefix,
					'reminder_status'  => $post->post_status,
					'reminder_actions' => $reminder_actions,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Email Reminder Settings Meta Box.
		 *
		 * @since 5.14.0
		 */
		public function email_reminder_settings( $post = '' ) {

			wp_nonce_field( 'bkap_email_reminder_settings', 'bkap_email_reminder_settings_nonce' );

			$reminder_id = $post->ID;
			$reminder    = new BKAP_Reminder( $post );

			$email_subject = $reminder->get_email_subject();
			$email_heading = $reminder->get_email_heading();
			$email_content = $reminder->get_email_content();

			/* Email Reminder Settings */
			wc_get_template(
				'reminders/html-bkap-email-reminder-settings.php',
				array(
					'post'          => $post,
					'prefix'        => $this->prefix,
					'email_subject' => $email_subject,
					'email_heading' => $email_heading,
					'email_content' => $email_content,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Trigger Settings Meta Box.
		 * 
		 * @since 5.14.0
		 */
		public function reminder_settings( $post = '' ) {
			
			$bkap_version = BKAP_VERSION;
			$prefix       = $this->prefix;
			$reminder     = new BKAP_Reminder( $post );

			/* Reminder Settings */
			wc_get_template(
				'reminders/html-bkap-reminder-settings.php',
				array(
					'post'          => $post,
					'prefix'        => $this->prefix,
					'bkap_version'  => BKAP_VERSION,
					'reminder'      => new BKAP_Reminder( $post ),
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}

		/**
		 * Merge Codes Meta Box.
		 * 
		 * @since 5.14.0
		 */
		public function merge_codes( $post = '' ) {

			$merge_codes = bkap_reminder_merge_codes();

			foreach ( $merge_codes as $key => $value ) {
				echo '<p>' . $key; echo bkap_help_tip_html( $value ) . '</p>';
			}
		}

		/**
		 * Send Test Meta Box.
		 * 
		 * @since 5.14.0
		 */
		public function send_test( $post = '' ) {

			$prefix = $this->prefix;

			/* Send Test */
			wc_get_template(
				'reminders/html-bkap-send-test.php',
				array(
					'prefix' => $this->prefix,
				),
				'woocommerce-booking/',
				BKAP_BOOKINGS_TEMPLATE_PATH
			);
		}
	}
	return new BKAP_Send_Reminder_Meta_Box();
}
