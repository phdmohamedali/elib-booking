<?php

/**
 * Booking & Appointment Plugin for WooCommerce
 *
 * This file contains functions to register the custom post types for booking plugin
 *
 * @author      Tyche Softwares
 * @category    Core
 * @package     BKAP/Custom-Post-Type
 * @version     4.10.0
 */


/**
 * Booking Post Types
 *
 * @since 4.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'Bkap_Custom_Post_Type' ) ) {

	/**
	 * Booking Custom Post Types
	 */

	class Bkap_Custom_Post_Type {

		/**
		 * Construct
		 *
		 * @since 4.10.0
		 */
		public function __construct() {
			// Init post type
			add_action( 'init', array( &$this, 'bkap_init_post_types' ) );

			// Remove yoast seo links and filters and columns from admin booking pages.
			add_action( 'admin_init', array( $this, 'bkap_booking_remove_yoast_seo_admin_filter' ) );
			add_action( 'views_edit-bkap_booking', array( $this, 'bkap_remove_yoast_subsubmenu_booking_pages' ), 11 );
			add_filter( 'manage_edit-bkap_booking_columns', array( $this, 'bkap_booking_remove_yoast_seo_columns' ) );
		}

		/**
		 * Register custom post types needed for the plugins.
		 *
		 * @since 4.1.0
		 */

		public static function bkap_init_post_types() {

			/**
			 * Booking Custom Post Type.
			 */
			register_post_type(
				'bkap_booking',
				apply_filters(
					'bkap_register_post_type_bkap_booking',
					array(
						'label'               => __( 'Booking', 'woocommerce-booking' ),
						'labels'              => array(
							'name'               => __( 'Booking', 'woocommerce-booking' ),
							'singular_name'      => __( 'Booking', 'woocommerce-booking' ),
							'add_new'            => __( 'Create Booking', 'woocommerce-booking' ),
							'add_new_item'       => __( 'Add New Booking', 'woocommerce-booking' ),
							'edit'               => __( 'Edit', 'woocommerce-booking' ),
							'edit_item'          => __( 'Edit Booking', 'woocommerce-booking' ),
							'new_item'           => __( 'New Booking', 'woocommerce-booking' ),
							'view'               => __( 'View Booking', 'woocommerce-booking' ),
							'view_item'          => __( 'View Booking', 'woocommerce-booking' ),
							'search_items'       => __( 'Search Bookings', 'woocommerce-booking' ),
							'not_found'          => __( 'No Bookings found', 'woocommerce-booking' ),
							'not_found_in_trash' => __( 'No Bookings found in trash', 'woocommerce-booking' ),
							'parent'             => __( 'Parent Bookings', 'woocommerce-booking' ),
							'menu_name'          => _x( 'Booking', 'Admin menu name', 'woocommerce-booking' ),
							'all_items'          => __( 'View Bookings', 'woocommerce-booking' ),
						),
						'description'         => __( 'This is where bookings are stored.', 'woocommerce-booking' ),
						'public'              => false,
						'show_ui'             => true,
						'capability_type'     => 'post',
						'map_meta_cap'        => true,
						'supports'            => array( '' ),
						'menu_icon'           => 'dashicons-calendar-alt',
						'show_in_nav_menus'   => true,
						'publicly_queryable'  => false,
						'has_archive'         => true,
						'query_var'           => true,
						'can_export'          => true,
						'rewrite'             => false,
						'show_in_menu'        => true,
						'hierarchical'        => false,
						'show_in_rest'        => true,
						'exclude_from_search' => true,
					)
				)
			);

			/**
			 * Post status for Booking Custom Post Type.
			 */

			/**
			 * Post status Paid.
			 */
			register_post_status(
				'paid',
				array(
					'label'                     => '<span class="status-paid tips" data-tip="' . _x( 'Paid &amp; Confirmed', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Paid &amp; Confirmed', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'Paid &amp; Confirmed <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Post status confirmed.
			 */
			register_post_status(
				'confirmed',
				array(
					'label'                     => '<span class="status-confirmed tips" data-tip="' . _x( 'Confirmed', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Confirmed', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Confirmed <span class="count">(%s)</span>', 'Confirmed <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Post status pending confirmation.
			 */
			register_post_status(
				'pending-confirmation',
				array(
					'label'                     => '<span class="status-pending tips" data-tip="' . _x( 'Pending Confirmation', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Pending Confirmation', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Pending Confirmation <span class="count">(%s)</span>', 'Pending Confirmation <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Post status cancelled.
			 */
			register_post_status(
				'cancelled',
				array(
					'label'                     => '<span class="status-cancelled tips" data-tip="' . _x( 'Cancelled', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Cancelled', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Cancelled <span class="count">(%s)</span>', 'Cancelled <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			do_action( 'bkap_registering_custom_booking_status' );

			/**
			 * Registering post type for Google Calendar Events.
			 */

			register_post_type(
				'bkap_gcal_event',
				apply_filters(
					'bkap_register_post_type_bkap_gcal_event',
					array(
						'label'               => __( 'Import Bookings', 'woocommerce-booking' ),
						'labels'              => array(
							'name'               => __( 'Google Event', 'woocommerce-booking' ),
							'singular_name'      => __( 'Google Event', 'woocommerce-booking' ),
							'add_new'            => __( 'Add Google Event', 'woocommerce-booking' ),
							'add_new_item'       => __( 'Add New Google Event', 'woocommerce-booking' ),
							'edit'               => __( 'Edit', 'woocommerce-booking' ),
							'edit_item'          => __( 'Edit Google Event', 'woocommerce-booking' ),
							'new_item'           => __( 'New Google Event', 'woocommerce-booking' ),
							'view'               => __( 'Import Bookings', 'woocommerce-booking' ),
							'view_item'          => __( 'View Google Event', 'woocommerce-booking' ),
							'search_items'       => __( 'Search Google Event', 'woocommerce-booking' ),
							'not_found'          => __( 'No Google Event found', 'woocommerce-booking' ),
							'not_found_in_trash' => __( 'No Google Event found in trash', 'woocommerce-booking' ),
							'parent'             => __( 'Parent Google Events', 'woocommerce-booking' ),
							'menu_name'          => _x( 'Google Event', 'Admin menu name', 'woocommerce-booking' ),
							'all_items'          => __( 'Import Booking', 'woocommerce-booking' ),
						),
						'description'         => __( 'This is where bookings are stored.', 'woocommerce-booking' ),
						'public'              => false,
						'show_ui'             => true,
						'capability_type'     => 'post',
						'capabilities'        => array(
							'create_posts' => 'do_not_allow', // will have to be removed oncce we show the custom post type
						),
						'map_meta_cap'        => true,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'show_in_menu'        => 'edit.php?post_type=bkap_booking',
						'hierarchical'        => false,
						'show_in_nav_menus'   => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => array( '' ),
						'has_archive'         => false,
						'menu_icon'           => 'dashicons-calendar-alt',
					)
				)
			);

			/**
			 * Registering the status of the Google Calendar Events
			 */

			/**
			 * Post status unmapped
			 */

			register_post_status(
				'bkap-unmapped',
				array(
					'label'                     => '<span class="status-un-mapped tips" data-tip="' . _x( 'Un-mapped', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Un-mapped', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Un-mapped <span class="count">(%s)</span>', 'Un-mapped <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Post status mapped
			 */

			register_post_status(
				'bkap-mapped',
				array(
					'label'                     => '<span class="status-mapped tips" data-tip="' . _x( 'Mapped', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Mapped', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Mapped <span class="count">(%s)</span>', 'Mapped <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Post status deleted
			 */

			register_post_status(
				'bkap-deleted',
				array(
					'label'                     => '<span class="status-deleted tips" data-tip="' . _x( 'Deleted', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Deleted', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Deleted <span class="count">(%s)</span>', 'Mapped <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			/**
			 * Booking Resources Post Type
			 */

			register_post_type(
				'bkap_resource',
				apply_filters(
					'bkap_register_post_type_resource',
					array(
						'label'               => __( 'Booking Resources', 'woocommerce-booking' ),
						'labels'              => array(
							'name'               => __( 'Bookable resource', 'woocommerce-booking' ),
							'singular_name'      => __( 'Bookable resource', 'woocommerce-booking' ),
							'add_new'            => __( 'Add Resource', 'woocommerce-booking' ),
							'add_new_item'       => __( 'Add New Resource', 'woocommerce-booking' ),
							'edit'               => __( 'Edit', 'woocommerce-booking' ),
							'edit_item'          => __( 'Edit Resource', 'woocommerce-booking' ),
							'new_item'           => __( 'New Resource', 'woocommerce-booking' ),
							'view'               => __( 'View Resource', 'woocommerce-booking' ),
							'view_item'          => __( 'View Resource', 'woocommerce-booking' ),
							'search_items'       => __( 'Search Resource', 'woocommerce-booking' ),
							'not_found'          => __( 'No Resource found', 'woocommerce-booking' ),
							'not_found_in_trash' => __( 'No Resource found in trash', 'woocommerce-booking' ),
							'parent'             => __( 'Parent Resources', 'woocommerce-booking' ),
							'menu_name'          => _x( 'Resources', 'Admin menu name', 'woocommerce-booking' ),
							'all_items'          => __( 'Resources', 'woocommerce-booking' ),
						),
						'description'         => __( 'Bookable resources are bookable within a bookings product.', 'woocommerce-booking' ),
						'public'              => false,
						'show_ui'             => true,
						'capability_type'     => 'product',
						'map_meta_cap'        => true,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'show_in_menu'        => true,
						'hierarchical'        => false,
						'show_in_nav_menus'   => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => array( 'title' ),
						'has_archive'         => false,
						'show_in_rest'        => true,
						'show_in_menu'        => 'edit.php?post_type=bkap_booking',
					)
				)
			);

			/**
			 * Booking Person Post Type
			 */
			register_post_type(
				'bkap_person',
				apply_filters(
					'bkap_register_post_type_person',
					array(
						'label'               => __( 'Booking Persons', 'woocommerce-booking' ),
						'labels'              => array(
							'name'               => __( 'Bookable person', 'woocommerce-booking' ),
							'singular_name'      => __( 'Bookable person', 'woocommerce-booking' ),
							'add_new'            => __( 'Add Person', 'woocommerce-booking' ),
							'add_new_item'       => __( 'Add New Person', 'woocommerce-booking' ),
							'edit'               => __( 'Edit', 'woocommerce-booking' ),
							'edit_item'          => __( 'Edit Person', 'woocommerce-booking' ),
							'new_item'           => __( 'New Person', 'woocommerce-booking' ),
							'view'               => __( 'View Person', 'woocommerce-booking' ),
							'view_item'          => __( 'View Person', 'woocommerce-booking' ),
							'search_items'       => __( 'Search Person', 'woocommerce-booking' ),
							'not_found'          => __( 'No Person found', 'woocommerce-booking' ),
							'not_found_in_trash' => __( 'No Person found in trash', 'woocommerce-booking' ),
							'parent'             => __( 'Parent Persons', 'woocommerce-booking' ),
							'menu_name'          => _x( 'Persons', 'Admin menu name', 'woocommerce-booking' ),
							'all_items'          => __( 'Persons', 'woocommerce-booking' ),
						),
						'public'              => false,
						'show_ui'             => true,
						'capability_type'     => 'product',
						'map_meta_cap'        => true,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'show_in_menu'        => false,
						'hierarchical'        => false,
						'show_in_nav_menus'   => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => array( 'title' ),
						'has_archive'         => false,
						'show_in_rest'        => true,
					)
				)
			);

			/**
			 * Post Type: Send Reminder.
			 */
			register_post_type(
				'bkap_reminder',
				apply_filters(
					'bkap_register_post_type_reminder',
					array(
						'label'               => __( 'Send Reminder', 'woocommerce-booking' ),
						'labels'              => array(
							'name'               => __( 'Send Reminder', 'woocommerce-booking' ),
							'singular_name'      => __( 'Send Reminder', 'woocommerce-booking' ),
							'add_new'            => __( 'Add new Reminder', 'woocommerce-booking' ),
							'add_new_item'       => __( 'Add New Reminder', 'woocommerce-booking' ),
							'edit'               => __( 'Edit', 'woocommerce-booking' ),
							'edit_item'          => __( 'Edit Reminder', 'woocommerce-booking' ),
							'new_item'           => __( 'New Reminder', 'woocommerce-booking' ),
							'view'               => __( 'View Reminder', 'woocommerce-booking' ),
							'view_item'          => __( 'View Reminder', 'woocommerce-booking' ),
							'search_items'       => __( 'Search Resource', 'woocommerce-booking' ),
							'not_found'          => __( 'No Resource found', 'woocommerce-booking' ),
							'not_found_in_trash' => __( 'No Resource found in trash', 'woocommerce-booking' ),
							'parent'             => __( 'Parent Reminders', 'woocommerce-booking' ),
							'menu_name'          => _x( 'Send Reminders', 'Admin menu name', 'woocommerce-booking' ),
							'all_items'          => __( 'Send Reminders', 'woocommerce-booking' ),
						),
						//'description'         => __( 'Bookable resources are bookable within a bookings product.', 'woocommerce-booking' ),
						'public'              => false,
						'show_ui'             => true,
						'capability_type'     => 'product',
						'map_meta_cap'        => true,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'show_in_menu'        => true,
						'hierarchical'        => false,
						'show_in_nav_menus'   => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => array( 'title' ),
						'has_archive'         => false,
						'show_in_rest'        => true,
						'show_in_menu'        => 'edit.php?post_type=bkap_booking',
					)
				)
			);

			/**
			 * Post status cancelled.
			 */
			register_post_status(
				'bkap-active',
				array(
					'label'                     => '<span class="status-active tips" data-tip="' . _x( 'Active', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Active', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);

			register_post_status(
				'bkap-inactive',
				array(
					'label'                     => '<span class="status-inactive tips" data-tip="' . _x( 'Inactive', 'woocommerce-booking', 'woocommerce-booking' ) . '">' . _x( 'Inactive', 'woocommerce-booking', 'woocommerce-booking' ) . '</span>',
					'public'                    => true,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => _n_noop( 'Inactive <span class="count">(%s)</span>', 'Inactive <span class="count">(%s)</span>', 'woocommerce-booking' ),
				)
			);
		}
		
		/**
		 * Remove Yoast Seo Admin filters from booking post types.
		 */
		public function bkap_booking_remove_yoast_seo_admin_filter() {
			global $pagenow, $typenow;
			if ( 'edit.php' == $pagenow && ( 'bkap_booking' == $typenow ) ) {
				if ( has_action( 'restrict_manage_posts' ) ) {
					global $wpseo_meta_columns ;
					remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown' ) );
					remove_action( 'restrict_manage_posts', array( $wpseo_meta_columns , 'posts_filter_dropdown_readability' ) );
				}
			}
		}

		/**
		 * Remove yoast seo Cornerstone content quick link from admin 
		 * booking pages.
		 *
		 * @param array $views An array of quick links.
		 */
		public function bkap_remove_yoast_subsubmenu_booking_pages( $views ) {
			global $pagenow, $typenow;
			if ( 'edit.php' == $pagenow && 'bkap_booking' == $typenow ) {
				unset( $views['yoast_cornerstone'] );
			}
			return $views;
		}

		/**
		 * Function to remove yoast seo columns from booking posts pages.
		 *
		 * @param array $columns An array of all columns going to listed out.
 		 */
		public function bkap_booking_remove_yoast_seo_columns( $columns ) {
			unset($columns['wpseo-score']);
			unset($columns['wpseo-score-readability']);
			unset($columns['wpseo-title']);
			unset($columns['wpseo-metadesc']);
			unset($columns['wpseo-focuskw']);
			unset($columns['wpseo-links']);
			return $columns;
		}

	} // end of class
	$bkap_custom_post_type = new Bkap_Custom_Post_Type();
} // end if

