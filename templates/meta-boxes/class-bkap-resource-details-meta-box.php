<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Resource Details Meta Box
 *
 * @author   Tyche Softwares
 * @package  BKAP/Meta-Boxes
 * @category Classes
 * @class    BKAP_Resource_Details_Meta_Box
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Resource_Details_Meta_Box.
 */
class BKAP_Resource_Details_Meta_Box {

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
	 * Is meta boxes saved once?
	 *
	 * @var boolean
	 */
	private static $saved_meta_box = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id         = 'bkap-resource-data';
		$this->title      = __( 'Resource details', 'woocommerce-booking' );
		$this->context    = 'normal';
		$this->priority   = 'high';
		$this->post_types = array( 'bkap_resource' );

		add_action( 'save_post', 'bkap_save_resources', 10, 2 );

		wp_enqueue_style( 'bkap-booking', bkap_load_scripts_class::bkap_asset_url( '/assets/css/booking.css', BKAP_FILE ), '', '1.0', false );
	}

	/**
	 * Show meta box.
	 */
	public static function meta_box_inner( $post = '' ) {

		$bkap_intervals  = bkap_intervals();
		$zoom_api_key    = get_option( 'bkap_zoom_api_key', '' );
		$zoom_api_secret = get_option( 'bkap_zoom_api_secret', '' );
		$response        = new stdClass();
		if ( '' !== $zoom_api_key && '' !== $zoom_api_secret && BKAP_License::enterprise_license() ) {
			$zoom_connection = bkap_zoom_connection();
			$response        = json_decode( $zoom_connection->bkap_list_users() );
		}

		if ( '' === $post ) {
			$resource_qty          = 1;
			$resource_menu_order   = 0;
			$resource_availability = array();
			$zoom_host_id          = '';
		} else {
			$post_id               = $post->ID;
			$resource              = new BKAP_Product_Resource( $post_id );
			$resource_qty          = $resource->get_resource_qty();
			if ( '' === $resource_qty ) {
				$resource_qty = 1;
			}
			$resource_menu_order   = $resource->get_resource_menu_order();
			$resource_availability = $resource->get_resource_availability();
			$zoom_host_id          = $resource->get_resource_host();
		}

		/* Resource Details */
		wc_get_template(
			'meta-boxes/html-bkap-resource-details.php',
			array(
				'post'                  => $post,
				'resource_qty'          => $resource_qty,
				'resource_menu_order'   => $resource_menu_order,
				'resource_availability' => $resource_availability,
				'response'              => $response,
				'bkap_intervals'        => $bkap_intervals,
				'zoom_host_id'          => $zoom_host_id,
			),
			'woocommerce-booking/',
			BKAP_BOOKINGS_TEMPLATE_PATH
		);
	}
}

return new BKAP_Resource_Details_Meta_Box();

?>
