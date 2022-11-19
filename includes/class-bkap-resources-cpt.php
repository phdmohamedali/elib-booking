<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Resource Custom Post Type Data.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Resources
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Product_Resource class.
 *
 * @class BKAP_Product_Resource
 * @since 4.6.0
 */
class BKAP_Product_Resource {

	private $resource;
	private $product_id;
	private $id;

	/**
	 * Constructor
	 */
	public function __construct( $post, $product_id = 0 ) {
		if ( is_numeric( $post ) ) {
			$this->resource = get_post( $post );
			$this->id       = $post;
		} else {
			$this->resource = $post;
		}

		$this->product_id = $product_id;
	}

	/**
	 * Return the ID
	 *
	 * @return int
	 * @since 4.6.0
	 */
	public function get_id() {
		return $this->resource->ID;
	}

	/**
	 * Set the ID
	 *
	 * @return int
	 * @since 4.6.0
	 */
	public function set_id( $id ) {
		$this->resource->ID = $id;
	}

	/**
	 * Get the title of the resource
	 *
	 * @return string
	 * @since 4.6.0
	 */
	public function get_title() {
		return $this->resource->post_title;
	}

	/**
	 * Return if we have qty set at resource level
	 *
	 * @return boolean
	 * @since 4.6.0
	 */
	public function has_qty() {
		return $this->get_qty() !== '';
	}

	/**
	 * Return the quantity set at resource level
	 *
	 * @return int
	 * @since 4.6.0
	 */
	public function get_qty() {
		return get_post_meta( $this->get_id(), 'qty', true );
	}

	/**
	 * Return the base cost of the Resource
	 *
	 * @return int|float $cost - Resource base cost
	 * @since 4.6.0
	 */
	public function get_base_cost() {
		$costs = get_post_meta( $this->product_id, '_bkap_resource_base_costs', true );
		$cost  = isset( $costs[ $this->get_id() ] ) ? $costs[ $this->get_id() ] : '';

		return (float) $cost;
	}

	/**
	 * Return the block cost for the resource
	 *
	 * @return int|float $cost - Resource block cost
	 * @since 4.6.0
	 */
	public function get_block_cost() {
		$costs = get_post_meta( $this->product_id, '_resource_block_costs', true );
		$cost  = isset( $costs[ $this->get_id() ] ) ? $costs[ $this->get_id() ] : '';

		return (float) $cost;
	}

	/**
	 * Return the availability of resource
	 *
	 * @return string|array - Qty for the resource available for booking.
	 * @since 4.6.0
	 */
	public function get_resource_availability() {

		$bkap_resource_availability = get_post_meta( $this->get_id(), '_bkap_resource_availability', true );

		return $bkap_resource_availability;
	}

	/**
	 * Return the quantity of resource
	 *
	 * @return integer Quantity of the resource available for booking
	 * @since 4.6.0
	 */
	public function get_resource_qty() {

		$bkap_resource_qty = get_post_meta( $this->get_id(), '_bkap_resource_qty', true );

		return $bkap_resource_qty;
	}

	/**
	 * Return the menu order of resource
	 *
	 * @return integer Menu order of the resource.
	 *
	 * @since 5.14.0
	 */
	public function get_resource_menu_order() {

		$bkap_resource_menu_order = get_post_meta( $this->get_id(), '_bkap_resource_menu_order', true );

		if ( '' === $bkap_resource_menu_order ) {
			$bkap_resource_menu_order = 0;
		}

		return $bkap_resource_menu_order;
	}

	/**
	 * Return the zoom host.
	 *
	 * @return string Zoom Host ID
	 * @since 5.2.0
	 */
	public function get_resource_host() {

		$bkap_resource_host = get_post_meta( $this->get_id(), '_bkap_resource_meeting_host', true );

		return $bkap_resource_host;
	}

	/**
	 * Return the title of resource
	 *
	 * @return string title of the resource
	 * @since 4.12.0
	 */

	public function get_resource_title() {
		return Class_Bkap_Product_Resource::get_resource_name( $this->get_id() );
	}

	/**
	 * Create Custom post for the Resource.
	 *
	 * @return integer ID of the resource created.
	 * @since 4.6.0
	 */
	public static function bkap_create_resource( $add_resource_name ) {

		$id = wp_insert_post(
			array(
				'post_title'   => $add_resource_name,
				'menu_order'   => 0,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_type'    => 'bkap_resource',
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {

			update_post_meta( $id, '_bkap_resource_qty', 1 );
			update_post_meta( $id, '_bkap_resource_menu_order', 0 );
			update_post_meta( $id, '_bkap_resource_availability', array() );

			return $id;
		}
	}

	/**
	 * Create Custom post for the Resource.
	 *
	 * @return integer ID of the resource created.
	 * @since 4.6.0
	 */
	public static function bkap_resource_assigned( $product_id ) {

		$bkap_resource_assigned = get_post_meta( $product_id, '_bkap_product_resource_selection', true );

		return $bkap_resource_assigned;

	}

	/**
	 * Returns resource selection type.
	 *
	 * @return integer $product_id Product ID.
	 * @since 5.15.0
	 */
	public static function get_resource_selection_type( $product_id ) {
		$resource_selection_type = get_post_meta( $product_id, '_bkap_product_resource_selection_type', true );
		return ( '' === $resource_selection_type ) ? 'single' : $resource_selection_type;
	}
}
