<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for Resource Listing
 *
 * @author   Tyche Softwares
 * @package  BKAP/Resource_Listing
 * @category Classes
 */

if ( ! class_exists( 'BKAP_Resource_Listing' ) ) {

	/**
	 * Class for Resource Listing
	 *
	 * @since 4.1.0
	 */

	class BKAP_Resource_Listing {

		/**
		 * Post Type.
		 *
		 * @var string $type Post Type.
		 */
		public $type;

		/**
		 * Default constructor
		 *
		 * @since 5.13.0
		 */
		public function __construct() {
			$this->type = 'bkap_resource';

			add_filter( 'manage_edit-' . $this->type . '_columns', array( &$this, 'bkap_edit_columns' ) );
			add_action( 'manage_' . $this->type . '_posts_custom_column', array( &$this, 'bkap_custom_columns' ), 2 );
		}

		/**
		 * Change the columns shown in admin.
		 *
		 * @param array $existing_columns Exiting Columns Array.
		 * @return array Columns Modified Array.
		 *
		 * @since 5.13.0
		 * @hook manage_edit-bkap_resource_columns
		 */
		public function bkap_edit_columns( $existing_columns ) {

			if ( empty( $existing_columns ) && ! is_array( $existing_columns ) ) {
				$existing_columns = array();
			}

			$columns                       = array();
			$columns['title']              = __( 'Resource Title', 'woocommerce-booking' ); // Resource Title
			$columns['bkap_product_count'] = __( 'No. of Products', 'woocommerce-booking' ); 
			$columns['date']               = $existing_columns['date'];

			unset( $existing_columns['comments'], $existing_columns['title'], $existing_columns['date'] );

			return apply_filters( 'bkap_resource_listing_columns', array_merge( $existing_columns, $columns ) );
		}

		/**
		 * Define our custom columns shown in admin.
		 *
		 * @param  string $column Custom Columns Array.
		 * @global object $post WP_Post
		 *
		 * @since 5.13.0
		 *
		 * @hook manage_bkap_resource_posts_custom_column
		 */
		public function bkap_custom_columns( $column ) {
			
			global $post;

			if ( get_post_type( $post->ID ) === 'bkap_resource' ) {

				switch ( $column ) {
					case 'bkap_product_count':
						$product_ids = bkap_product_ids_from_resource_id( $post->ID );

						$count = count( $product_ids );
						if ( $count > 0 ) {
							$url   = admin_url() . 'edit.php?post_type=product&bkap_resource_id=' .$post->ID;
							echo sprintf( '<a href="%s" title="">%d</a>', $url, $count );
						} else {
							echo '0';
						}
						break;
				}
			}
		}
	}
}
return new BKAP_Resource_Listing();
