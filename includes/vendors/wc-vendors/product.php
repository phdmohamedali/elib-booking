<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for integrating Dokan Products with Bookings & Appointment Plugin
 *
 * @author   Tyche Softwares
 * @package  BKAP/Vendors/WC-Vendors
 * @version  4.6.0
 * @category Classes
 */

/**
 * Class for WC Vendors Products integration with Booking plugin
 *
 * @since 4.6.0
 */
class bkap_wcv_product {

	/**
	 * Default constructor
	 *
	 * @since 4.6.0
	 */
	public function __construct() {

		// Add the booking tab
		add_filter( 'wcv_product_meta_tabs', array( &$this, 'bkap_add_booking_tab' ) );
		// Add the booking meta box in the Booking tab
		add_action( 'wcv_after_variations_tab', array( &$this, 'bkap_add_tab_data' ), 10, 1 );

		add_action( 'wcv_save_product', array( $this, 'bkap_wcv_save_product' ), 10, 1 );
	}

	/**
	 * Saving Booking Data for New Product.
	 *
	 * @param int $product_id Product ID.
	 * @since 5.10.0
	 */
	public function bkap_wcv_save_product( $product_id ) {

		if ( ! isset( $_POST['post_id'] ) && isset( $_POST['bkap_booking_options'] ) ) {

			$_POST['booking_options']  = $_POST['bkap_booking_options'];
			$_POST['settings_data']    = $_POST['bkap_settings_data'];
			$_POST['gcal_data']        = $_POST['bkap_gcal_data'];
			$_POST['ranges_enabled']   = $_POST['bkap_ranges_enabled'];
			$_POST['blocks_enabled']   = $_POST['bkap_blocks_enabled'];
			$_POST['fixed_block_data'] = $_POST['bkap_fixed_block_data'];
			$_POST['price_range_data'] = $_POST['bkap_price_range_data'];
			$_POST['resource_data']    = $_POST['bkap_resource_data'];

			bkap_booking_box_class::bkap_save_settingss( $product_id );
		}
	}

	/**
	 * Add our Bookings Tab to the existing tabs added by WC Vendors
	 *
	 * @param array $tabs_array Data containing the available options for tabs
	 * @returns array Array with Booking details added to it
	 * @since 4.6.0
	 */
	function bkap_add_booking_tab( $tabs_array ) {

		$tabs_array['bkap_booking'] = array(
			'label'  => __( 'Booking', 'woocommerce-booking' ),
			'target' => 'bkap_booking',
			'class'  => array( 'show_if_simple', 'show_if_variable', 'show_if_grouped' ),
		);

		return $tabs_array;
	}

	/**
	 * Add our Booking Meta Box on each product page
	 *
	 * @since 4.6.0
	 * @param string|int $product_id Current Product ID
	 * @global mixed $post Current Post Object
	 */
	public function bkap_add_tab_data( $product_id ) {

		$bkap_version = get_option( 'woocommerce_booking_db_version' );

		global $post;
		$post    = get_post( $product_id, OBJECT );
		$results = setup_postdata( $post );

		bkap_load_scripts_class::bkap_load_products_css( $bkap_version );
		bkap_load_scripts_class::bkap_load_bkap_tab_css( $bkap_version );

		?>
		<div class="wcv_bkap_booking tabs-content" id="bkap_booking">
			<?php
			bkap_booking_box_class::bkap_meta_box();
			?>
		</div>
		<?php

		$ajax_url = get_admin_url() . 'admin-ajax.php';

		bkap_load_scripts_class::bkap_common_admin_scripts_js( $bkap_version );
		bkap_load_scripts_class::bkap_load_product_scripts_js( $bkap_version, $ajax_url );
		bkap_load_scripts_class::bkap_load_resource_scripts_js( $bkap_version, $ajax_url );

		wp_register_script(
			'bkap-wcv',
			bkap_load_scripts_class::bkap_asset_url( '/assets/js/vendors/wc-vendors/product.js', BKAP_FILE ),
			'',
			$bkap_version,
			true
		);

		wp_localize_script(
			'bkap-wcv',
			'bkap_wcv_params',
			array(
				'ajax_url' => $ajax_url,
				'post_id'  => $product_id,
			)
		);

		wp_enqueue_script( 'bkap-wcv' );

		wp_enqueue_style(
			'bkap-wcv-products',
			bkap_load_scripts_class::bkap_asset_url( '/assets/css/vendors/wc-vendors/bkap-wcv-products.css', BKAP_FILE ),
			'',
			$bkap_version,
			false
		);

		wp_reset_postdata();
		?>

		<script type="text/javascript">
			jQuery(document).ready(function ( $ ) {
				$('.tstab-content').wrapInner('<div class="tstab-content-inner"></div>');
				$(document).on('click', '.tstab-tab', function(){
					data_link = $(this).data("link");
					cur_data_link = $('.tstab-tab.tstab-active').data("link");
					if ( cur_data_link !== data_link ) {
					$('.tstab-content').removeClass('tstab-active').hide();
					$("#"+data_link).addClass('tstab-active').css('position', 'relative').fadeIn('slow');
					$('.tstab-tab').removeClass('tstab-active');
					$(this).addClass('tstab-active');
					}
				});
			});
		</script>

		<?php

		if ( 0 == $product_id ) {
			?>
		<input type='hidden' id="bkap_booking_options" name="bkap_booking_options" value="" class="hidden">
		<input type='hidden' id="bkap_settings_data" name="bkap_settings_data" value="" class="hidden">
		<input type='hidden' id="bkap_gcal_data" name="bkap_gcal_data" value="" class="hidden">
		<input type='hidden' id="bkap_ranges_enabled" name="bkap_ranges_enabled" value="" class="hidden">
		<input type='hidden' id="bkap_blocks_enabled" name="bkap_blocks_enabled" value="" class="hidden">
		<input type='hidden' id="bkap_fixed_block_data" name="bkap_fixed_block_data" value="" class="hidden">
		<input type='hidden' id="bkap_price_range_data" name="bkap_price_range_data" value="" class="hidden">
		<input type='hidden' id="bkap_resource_data" name="bkap_resource_data" value="" class="hidden">
			<?php
		}
	}


} // end of class
$bkap_wcv_product = new bkap_wcv_product();
?>
