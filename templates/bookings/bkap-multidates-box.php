<?php
/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Template for Bookings Only Date Setting. This template shall be resued on Cart, Checkout and My Account Pages
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div id="bkap-multidate-box">
	<input type="hidden" id="bkap_multidate_data" name="bkap_multidate_data" data-multidate-data="" value="">
	<p><?php echo esc_html( $summary_heading ); ?></p>
	<table id="bkap-multidate-info">
		<tbody></tbody>
		<tfoot>
			<tr id="bkap-multidates-total-tr" data-total-price-charged=0>
				<td><?php echo esc_html( $total_booking_price ); ?></td>
				<td id="bkap-multidates-total"></td>
				<td></td>
			</tr>
			<?php if ( isset( $total_remaining_price ) ) { ?>
			<tr id="bkap-multidates-remaining-tr" data-total-remaining-charged=0>
				<td><?php echo esc_html( $total_remaining_price ); ?></td>
				<td id="bkap-multidates-remaining"></td>
				<td></td>
			</tr>
			<tr id="bkap-multidates-ftotal-tr" data-ftotal-price-charged=0>
				<td><?php echo esc_html( $final_total_price ); ?></td>
				<td id="bkap-multidates-ftotal"></td>
				<td></td>
			</tr>
			<?php } ?>
		</tfoot>
</table>
</div>
