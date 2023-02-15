<?php
/**
 *  Dokan Dashboard Bookings Calendar Template
 *
 *  Load Availability Popup - Allows vendors to add global holidays.
 *
 *  @since 5.0.0
 *
 *  @package woocommerce-booking
 */

?>
<div class="dokan-dashboard-header">
	<span class="entry-title bkap-dokan-tab-title"><?php _e( 'Booking Calendar', 'woocommerce-booking' ); ?></span>
	<span class="bkap-dokan-availability">
		<button type="button" class="button-primary bkap-dokan-availability-btn" onclick="bkap_set_vendor_availability()" ><i class="fa fa-calendar"></i>&nbsp;&nbsp;&nbsp;<?php esc_html_e( 'Set Holidays', 'woocommerce-booking' ); ?></button>
	</span>
</div>
<div style="clear:both;"></div>
<hr>
<div id="vendor-global-holiday" class="vendor-global-holiday popup-overlay">
	<div class="popup-content">
		<div>
			<h2><?php esc_html_e( 'Set Holidays', 'woocommerce-booking' ); ?></h2>
			<input type="text" class="update-type" style="display: none;" value="" />
			<input type="text" class="update-id" style="display: none;" value="0" />
			<span class="availability-close">X</span>
		</div>
		<hr>
		<div>
			<label for="avail-title"><?php esc_html_e( 'Holiday Title: ', 'woocommerce-booking' ); ?></label><input type="text" id="avail-title" class="title" value=""/>
		</div>
		<br>
		<table id="vendor-dates">
			<tr>
				<td>
					<label for="availability-start"><?php esc_html_e( 'From Date:', 'woocommerce-booking' ); ?></label>
					<br>
					<input id="availability-start" name="availability-start" type="text" readonly/>
				</td>
				<td>
					<label for="availability-end"><?php esc_html_e( 'To Date:', 'woocommerce-booking' ); ?></label>
					<br>
					<input id="availability-end" name="availability-end" type="text" readonly />
				</td>
			</tr>
		</table>
		<hr>
		<div class="data-links">
			<button type="button" class="bkap-dokan-avail-delete"><?php esc_html_e( 'Delete', 'woocommerce-booking' ); ?></button>
			<div class="close-buttons">
				<button type="button" class="bkap-dokan-avail-cancel"><?php esc_html_e( 'Cancel', 'woocommerce-booking' ); ?></button>
				&nbsp;&nbsp;
				<button type="button" class="bkap-dokan-avail-save button alt"><?php esc_html_e( 'Save', 'woocommerce-booking' ); ?></button>
			</div>
		</div>
	</div>
</div>
