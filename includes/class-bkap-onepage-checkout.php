<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Class for WooCommerce One Page Checkout
 *
 * @author      Tyche Softwares
 * @package     BKAP/Addons
 * @category    Classes
 */

if ( ! class_exists( 'BKAP_OPC_Addon' ) ) {

	/**
	 * Class for WooCommerce One Page Checkout
	 *
	 * @since 4.6.0
	 */
	class BKAP_OPC_Addon {

		/**
		 * Default constructor.
		 *
		 * @since 4.6.0
		 */

		function __construct() {

			add_action( 'wcopc_after_add_to_cart_button', array( &$this, 'bkap_opc_add_booking_button' ), 10, 1 );
			add_action( 'woocommerce_before_single_product_summary', array( &$this, 'bkap_opc_include_scripts_styles' ), 50, 1 );
		}

		/**
		 * Include JS and CSS files for Single Product Template of OPC
		 *
		 * @param string|int $page_id Page ID
		 * @globals WC_Product WooCommerce Product Object
		 * @since 4.6.0
		 *
		 * @hook woocommerce_before_single_product_summary
		 */

		public function bkap_opc_include_scripts_styles( $page_id ) {

			global $product;

			$product_id = $product->get_id();

			bkap_load_scripts_class::include_frontend_scripts_js( $product_id );
			bkap_load_scripts_class::inlcude_frontend_scripts_css( $product_id );

			self::bkap_opc_load_scripts( $product, $product_id );
		}

		/**
		 * Add Book Now button after quantity input.
		 *
		 * @param WC_Product $product Product Object
		 * @since 4.6.0
		 *
		 * @hook wcopc_after_add_to_cart_button
		 */

		public function bkap_opc_add_booking_button( $product ) {

			if ( 'simple' === $product->get_type() ) {
				$product_id   = $product->get_id();
				$variation_id = '';
				$modal_id     = $product_id;
			} elseif ( 'variation' === $product->get_type() ) {
				$product_id   = $product->get_parent_id();
				$variation_id = $product->get_id();
				$modal_id     = $variation_id;
			}

			$is_bookable = bkap_common::bkap_get_bookable_status( $product_id );

			if ( $is_bookable ) {

				printf( '<input type="button" onclick=bkap_edit_booking_class.bkap_edit_bookings(%d,"%s") value="%s" class="bkap-opc-button">', $product_id, $modal_id, __( 'Book Now', 'woocommerce-booking' ) );

				$page_type = '';
				if ( is_cart() ) {
					$page_type = 'cart';
				} elseif ( is_checkout() ) {
					$page_type = 'checkout';
				}

				$localized_array = array(
					'bkap_booking_params' => array(),
					'bkap_cart_item'      => '',
					'bkap_cart_item_key'  => $modal_id,
					'bkap_page_type'      => $page_type,
				);

				// Additional data for addons
				$additional_addon_data = '';// bkap_common::bkap_get_cart_item_addon_data( $cart_item );

				$booking_details = array(
					'date'                 => '',
					'hidden_date'          => '',
					'date_checkout'        => '',
					'hidden_date_checkout' => '',
					'price'                => '',
				);

				bkap_edit_bookings_class::bkap_load_template(
					$booking_details,
					$product,
					$product_id,
					$localized_array,
					$modal_id,
					$variation_id,
					$additional_addon_data
				);

				self::bkap_opc_load_scripts( $product, $product_id );
			}
		}

		/**
		 * Load JS files
		 *
		 * @param WC_Product $product Product Object
		 * @param int|string $product_id Product ID
		 * @since 4.6.0
		 */

		public static function bkap_opc_load_scripts( $product, $product_id ) {

			$localized_params = array( 'product_id' => $product_id );

			wp_register_script(
				'bkap-opc-add-booking',
				bkap_load_scripts_class::bkap_asset_url( '/assets/js/bkap-opc-add-booking.js', BKAP_FILE ),
				'',
				'',
				false
			);

			wp_localize_script( 'bkap-opc-add-booking', "bkap_opc_add_booking_$product_id", $localized_params );

			wp_enqueue_script( 'bkap-opc-add-booking' );
		}
	}
}

$bkap_opc_addon = new BKAP_OPC_Addon();
