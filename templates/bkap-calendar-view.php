<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Calendar View
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$product_ids = isset( $_REQUEST['bkap_clr_products'] ) ? implode( ',', $_REQUEST['bkap_clr_products'] ) : '';
?>

<div id="bkap_events_loader" style="font-size: medium;">
	<h4 style="text-align: center;">
		<?php esc_html_e( 'Loading Calendar Events....', 'woocommerce-booking' ); ?>
		<span><img src=<?php echo esc_attr( bkap_ajax_loader_gif() ); ?>></span>
	</h4>
</div>

<form id="calendar-filter" method="post" style="margin: 10px 0px;">
	<input type="hidden" name="post_type" id="post_type" value="bkap_booking">
	<input type="hidden" name="page" id="page" value="woocommerce_history_page">
	<input type="hidden" name="booking_view" id="booking_view" value="booking_calender">
	<div class="actions">
		<?php
		global $wpdb;

		// Due to 2918 we have hard coded code to get all bookable products.
		$product  = $wpdb->get_results(
			"SELECT post_id FROM $wpdb->postmeta
				WHERE meta_key = '_bkap_enable_booking'
				AND meta_value = 'on'"
		);
		$products = array();

		if ( count( $product ) > 0 ) {
			foreach ( $product as $key => $value ) {
				$theid      = $value->post_id;
				$thetitle   = get_the_title( $theid );
				$products[] = array( $thetitle, $theid );
			}
			sort( $products );
		}

		$products = apply_filters( 'bkap_all_bookable_products_dropdown', $products );
		$output   = '';

		if ( is_array( $products ) && count( $products ) > 0 ) {
			$output .= '<select id="bkap_clr_products" name="bkap_clr_products[]" multiple="multiple">';
			$output .= '<option value="">' . __( 'All Bookable Products', 'woocommerce-booking' ) . '</option>';

			foreach ( $products as $filter_id => $filter ) {
				$output .= '<option value="' . absint( $filter[1] ) . '" ';
				if ( isset( $_REQUEST['bkap_clr_products'] ) && in_array( $filter[1], $_REQUEST['bkap_clr_products'] ) ) {
					$output .= ' selected="selected" ';
				}
				$output .= '>' . esc_html( $filter[0] ) . '</option>';
			}
			$output .= '</select>';
			echo $output;
		}

		?>
		<input type="submit" name="filter_action" id="post-query-submit" class="button" value="Filter" fdprocessedid="0bycrl">
	</div>
</form>

<input type="hidden" name="product_ids" id="product_ids" value="<?php echo esc_html( $product_ids ); ?>">

<div id='bkap-calendar'></div>
