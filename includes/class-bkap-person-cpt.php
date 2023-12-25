<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for handling Resource Custom Post Type Data.
 *
 * @author   Tyche Softwares
 * @package  BKAP/Person
 * @category Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * BKAP_Person class.
 *
 * @class BKAP_Person
 * @since 5.11.0
 */
class BKAP_Person {

	private $person;
	private $product_id;
	private $id;

	/**
	 * Constructor
	 */
	public function __construct( $post, $product_id = 0 ) {
		if ( is_numeric( $post ) ) {
			$this->person = get_post( $post );
			$this->id     = $post;
		} else {
			$this->person = $post;
		}

		$this->product_id = $product_id;
	}

	/**
	 * Return the ID
	 *
	 * @return int
	 * @since 5.11.0
	 */
	public function get_id() {
		return $this->person->ID;
	}

	/**
	 * Set the ID
	 *
	 * @return int
	 * @since 5.11.0
	 */
	public function set_id( $id ) {
		$this->person->ID = $id;
	}

	/**
	 * Get the title of the resource
	 *
	 * @return string
	 * @since 5.11.0
	 */
	public function get_title() {
		return $this->person->post_title;
	}

	/**
	 * Create Custom post for the Resource.
	 *
	 * @return integer ID of the resource created.
	 * @since 5.11.0
	 */
	public static function bkap_create_person( $person_name ) {

		global $wpdb;
		
		$id = wp_insert_post(
			array(
				'post_title'   => $person_name,
				'menu_order'   => 0,
				'post_content' => '',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
				'post_type'    => 'bkap_person',
			),
			true
		);

		if ( $id && ! is_wp_error( $id ) ) {
			return $id;
		}
	}
}
