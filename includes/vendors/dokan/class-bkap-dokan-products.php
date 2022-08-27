<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan Products with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/Dokan
 * @version  4.8.0
 * @category Classes
 */

if ( ! class_exists( 'bkap_dokan_products_class' ) ) {

	/**
	 * Class for Integrating Products with Dokan
	 */
	class bkap_dokan_products_class {

		/**
		 * Default Constructor
		 */
		function __construct() {

			$dokan_settings = get_option( 'dokan_selling' );

			if ( defined( 'DOKAN_PRO_PLUGIN_VERSION' ) && DOKAN_PRO_PLUGIN_VERSION >= '2.7.3' ) {
				add_action( 'dokan_product_edit_after_main', array( &$this, 'bkap_add_booking_meta' ), 10, 1 );
			} else {
				if ( isset( $dokan_settings['product_style'] ) && $dokan_settings['product_style'] === 'old' ) {
					add_action( 'dokan_product_tab_content', array( &$this, 'bkap_add_booking_meta' ), 10, 1 );
				} elseif ( isset( $dokan_settings['product_style'] ) && $dokan_settings['product_style'] === 'new' ) {
					add_action( 'dokan_product_edit_after_main', array( &$this, 'bkap_add_booking_meta' ), 10, 1 );
				}
			}

			add_filter( 'dokan_product_data_tabs', array( &$this, 'bkap_dokan_add_tabs' ) );
		}

		/**
		 * Add Booking Meta Boxes to Booking Settings Tabs
		 *
		 * @param WP_Post $post Post object for the current Product
		 * @since 4.6.0
		 */
		public function bkap_add_booking_meta( $post ) {

			$plugin_version_number = get_option( 'woocommerce_booking_db_version' );
			$ajax_url              = get_admin_url() . 'admin-ajax.php';

			bkap_load_scripts_class::bkap_load_products_css( $plugin_version_number );
			bkap_load_scripts_class::bkap_load_bkap_tab_css( $plugin_version_number );
			bkap_load_scripts_class::bkap_load_dokan_css( $plugin_version_number );
			?>

			<div class="dokan-bkap-settings dokan-edit-row" id="product-bkap-bookings">
				<div class="dokan-section-heading" data-togglehandler="dokan_bkap_settings">
					<h2>
						<i class="wp-menu-image dashicons-before dashicons-calendar-alt" aria-hidden="true"></i> 
						<?php _e( 'Booking', 'woocommerce-booking' ); ?>
					</h2>
					<p><?php _e( 'Manage Booking Settings for this product.', 'woocommerce-booking' ); ?></p>
					<a href="#" class="dokan-section-toggle">
						<i class="fa fa-sort-desc fa-flip-vertical" aria-hidden="true"></i>
					</a>
					<div class="dokan-clearfix"></div>
				</div>

				<div class="dokan-section-content">
					<?php bkap_booking_box_class::bkap_meta_box(); ?>
				</div>
			</div>

			<?php

			bkap_load_scripts_class::bkap_common_admin_scripts_js( $plugin_version_number );
			bkap_load_scripts_class::bkap_load_product_scripts_js( $plugin_version_number, $ajax_url );
			bkap_load_scripts_class::bkap_load_dokan_product_scripts_js( $plugin_version_number, $ajax_url );
			bkap_load_scripts_class::bkap_load_resource_scripts_js( $plugin_version_number, $ajax_url );
			wp_register_script( 'jquery-tiptip', bkap_load_scripts_class::bkap_asset_url( '/assets/js/jquery.tipTip.minified.js', BKAP_FILE, false, false ), array( 'jquery' ), $plugin_version_number, false );
			wp_enqueue_script( 'jquery-tiptip' );
			wp_dequeue_script( 'bkap-jqueryui' );
			wp_deregister_script( 'bkap-jqueryui' );
		}

		/**
		 * Add Booking Tab to existing Product Settings Tabs
		 *
		 * @param array $tabs_array Array containing existing Tabs
		 * @return array Tabs Array
		 * @since 4.6.0
		 */
		public function bkap_dokan_add_tabs( $tabs_array ) {

			$tabs_array['bkap-bookings'] = array(
				'label'  => __( 'Booking', 'woocommerce-booking' ),
				'target' => 'product-bkap-bookings',
				'class'  => array(),
			);

			return $tabs_array;
		}
	}
}

return new bkap_dokan_products_class();
