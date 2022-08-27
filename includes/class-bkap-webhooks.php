<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for bookable webhooks.
 *
 * @author   Tyche Softwares
 * @package  BKAP/webhooks
 * @category Classes
 */
class Bkap_Webhooks {

	/**
	 * Init webhooks
	 *
	 * @return void
	 */
	public static function init() {
		// Setup webhooks.
		add_filter( 'woocommerce_webhook_deliver_async', array( __CLASS__, 'deliver_sync' ), 10, 3 );
		add_filter( 'woocommerce_valid_webhook_resources', array( __CLASS__, 'add_bookable_resources' ), 10 );
		add_filter( 'woocommerce_webhook_topics', array( __CLASS__, 'add_new_webhook_topics' ) );
		add_filter( 'woocommerce_webhook_topic_hooks', array( __CLASS__, 'add_bookable_topic_hooks' ), 10 );
		add_filter( 'woocommerce_webhook_payload', array( __CLASS__, 'generate_payload' ), 10, 4 );
		// Process webhook actions.
		add_action( 'save_post', array( __CLASS__, 'process_created' ), 100, 2 );
		add_action( 'post_updated', array( __CLASS__, 'process_updated' ), 100, 3 );
		add_action( 'bkap_new_booking', array( __CLASS__, 'process_created' ), 100, 2 ); // Handle booking creation.
		add_action( 'wp_trash_post', array( __CLASS__, 'process_delete' ), 100 );
	}

	/**
	 * Deliver webhook instantly
	 *
	 * @param string $value value.
	 * @param object $webhook webhook object.
	 * @param array  $arg args.
	 * @return boolean
	 */
	public static function deliver_sync( $value, $webhook, $arg ) {
		$bookable_webhook_topics = array(
			// Bookable Product.
			'bookable.created',
			'bookable.updated',
			'bookable.deleted',
			// Bookable Resource.
			'bookable_resource.created',
			'bookable_resource.updated',
			'bookable_resource.deleted',
			// Booking.
			'booking.created',
			'booking.updated',
			'booking.deleted',
		);

		if ( in_array( $webhook->topic, $bookable_webhook_topics, true ) ) {
			return false;
		}

		$value;
	}

	/**
	 * Add new resources for topic.
	 *
	 * @param  array $topic_resources Existing valid resources.
	 * @return array
	 */
	public static function add_bookable_resources( $topic_resources ) {
		// Webhook resources for bookable.
		$new_events = array(
			'bookable',
			'bookable_resource',
			'booking',
		);

		return array_merge( $topic_resources, $new_events );
	}

	/**
	 * Add new webhooks to the dropdown list on the Webhook page.
	 *
	 * @param array $topics Array of topics with the i18n proper name.
	 * @return array
	 */
	public static function add_new_webhook_topics( $topics ) {
		// New topic array to add to the list, must match hooks being created.
		$new_topics = array(
			// Bookable.
			'bookable.created'          => __( 'Bookable Product created', 'woocommerce-booking' ),
			'bookable.updated'          => __( 'Bookable Product updated', 'woocommerce-booking' ),
			'bookable.deleted'          => __( 'Bookable Product deleted', 'woocommerce-booking' ),
			// Bookable Resource.
			'bookable_resource.created' => __( 'Bookable Resource created', 'woocommerce-booking' ),
			'bookable_resource.updated' => __( 'Bookable Resource updated', 'woocommerce-booking' ),
			'bookable_resource.deleted' => __( 'Bookable Resource deleted', 'woocommerce-booking' ),
			// Booking.
			'booking.created'           => __( 'Booking created', 'woocommerce-booking' ),
			'booking.updated'           => __( 'Booking updated', 'woocommerce-booking' ),
			'booking.deleted'           => __( 'Booking deleted', 'woocommerce-booking' ),
		);

		return array_merge( $topics, $new_topics );
	}

	/**
	 * Add a new webhook topic hook.
	 *
	 * @param array $topic_hooks Existing topic hooks.
	 * @return array
	 */
	public static function add_bookable_topic_hooks( $topic_hooks ) {
		// Array that has the topic as resource.event with arrays of actions that call that topic.
		$new_hooks = array(
			// Bookable Product.
			'bookable.created'          => array( 'bkap_create_bookable' ),
			'bookable.updated'          => array( 'bkap_update_bookable' ),
			'bookable.deleted'          => array( 'bkap_delete_bookable' ),
			// Bookable Resource.
			'bookable_resource.created' => array( 'bkap_create_bookable_resource' ),
			'bookable_resource.updated' => array( 'bkap_update_bookable_resource' ),
			'bookable_resource.deleted' => array( 'bkap_delete_bookable_resource' ),
			// Booking.
			'booking.created'           => array( 'bkap_create_booking' ),
			'booking.updated'           => array( 'bkap_update_booking' ),
			'booking.deleted'           => array( 'bkap_delete_booking' ),
		);

		return array_merge( $topic_hooks, $new_hooks );
	}

	/**
	 * Generate payload for webhook
	 *
	 * @param array  $payload payload.
	 * @param string $resource resource name.
	 * @param array  $resource_data resource data.
	 * @param int    $id webhook id.
	 * @return array
	 */
	public static function generate_payload( $payload, $resource, $resource_data, $id ) {

		switch ( $resource_data['action'] ) {
			case 'created':
			case 'updated':
					$webhook_meta = array(
						'webhook_id'          => $id,
						'webhook_action'      => $resource_data['action'],
						'webhook_resource'    => $resource,
						'webhook_resource_id' => $resource_data['id'],
					);
					$post_meta    = get_post_meta( $resource_data['id'], '', true );

					$post_metadata = array();
				foreach ( $post_meta as $key => $value ) {
					$post_metadata[ $key ] = maybe_unserialize( $value[0] );
				}

					$payload = array_merge( $webhook_meta, $resource_data['data'], $post_metadata );
				break;

			case 'deleted':
					$payload = array(
						'webhook_id'          => $id,
						'webhook_action'      => 'deleted',
						'webhook_resource'    => $resource,
						'webhook_resource_id' => $resource_data['id'],
					);
				break;
		}

		return $payload;
	}

	/**
	 * Process created action for resources
	 *
	 * @param int     $post_ID post id.
	 * @param WP_Post $post post object.
	 * @return void
	 */
	public static function process_created( $post_ID, $post = null ) {

		// Return if post is null.
		if ( ! isset( $post ) ) {
			return;
		}

		// Bail out if autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bailout if its revision.
		if ( wp_is_post_revision( $post ) ) {
			return;
		}

		// Bailout if post status not in allowed.
		$allowed_status = array( 'publish', 'paid' );
		if ( ! in_array( $post->post_status, $allowed_status, true ) ) {
			return;
		}

		if ( $post->post_date !== $post->post_modified ) {
			return;
		}

		if ( empty( $post ) ) {
			$post = get_post( $post_ID );
		}

		// Payload meta.
		$data = array(
			'id'     => $post_ID,
			'data'   => $post->to_array(),
			'action' => 'created',
		);

		switch ( $post->post_type ) {
			case 'product':
				if ( 'on' === $post->_bkap_enable_booking ) {
					do_action( 'bkap_create_bookable', $data );
				}
				break;
			case 'bkap_booking':
					do_action( 'bkap_create_booking', $data );
				break;
			case 'bkap_resource':
					do_action( 'bkap_create_bookable_resource', $data, '' );
				break;
		}
	}

	/**
	 * Process updated action for webhooks resources
	 *
	 * @param int     $post_ID post id.
	 * @param WP_Post $post post object.
	 * @param WP_Post $post_before post object.
	 * @return void
	 */
	public static function process_updated( $post_ID, $post, $post_before = null ) {
		// Bailout if post status not in allowed.
		$allowed_status = array( 'publish', 'paid' );
		if ( ! in_array( $post->post_status, $allowed_status, true ) ) {
			return;
		}

		// Bailout if new post (auto-draft published).
		if ( $post->post_date === $post->post_modified ) {
			return;
		}

		// Bailout for status change (trash restored).
		if ( $post->post_status !== $post_before->post_status ) {
			return;
		}

		if ( empty( $post_before ) ) {
			$post_before = $post;
		}

		// Payload meta.
		$data = array(
			'id'     => $post_ID,
			'data'   => $post->to_array(),
			'action' => 'updated',
		);

		switch ( $post->post_type ) {
			case 'product':
				if ( 'on' === $post->_bkap_enable_booking ) {
					do_action( 'bkap_update_bookable', $data );
				}
				break;
			case 'bkap_booking':
					do_action( 'bkap_update_booking', $data );
				break;
			case 'bkap_resource':
					do_action( 'bkap_update_bookable_resource', $data, '' );
				break;
		}
	}

	/**
	 * Process delete action for webhook resources
	 *
	 * @param int $post_ID post id.
	 * @return void
	 */
	public static function process_delete( $post_ID ) {
		$data = array(
			'id'     => $post_ID,
			'action' => 'deleted',
		);

		switch ( get_post_type( $post_ID ) ) {
			case 'product':
				if ( 'on' === get_post_meta( $post_ID, '_bkap_enable_booking', true ) ) {
					do_action( 'bkap_delete_bookable', $data );
				}
				break;
			case 'bkap_booking':
					do_action( 'bkap_delete_booking', $data );
				break;
			case 'bkap_resource':
					do_action( 'bkap_delete_bookable_resource', $data, '' );
				break;
		}
	}
}

Bkap_Webhooks::init();
