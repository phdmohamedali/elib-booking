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
		<button type="button" class="button-primary bkap-primary bkap-dokan-availability-btn" style="border-radius: 10px; padding-top: 0; padding-bottom: 0;" onclick="bkap_set_vendor_availability()" ><i class="fa fa-calendar-check-o" aria-hidden="true"></i></i>&nbsp;&nbsp;&nbsp;<?php esc_html_e( 'Availability', 'woocommerce-booking' ); ?></button>
	</span>
</div>
<div id="vendor-global-holiday" class="vendor-global-holiday popup-overlay">
	<div class="popup-content">
		<div>
			<input type="text" id="avail-title" class="title" placeholder="<?php esc_html_e( 'Add Title', 'woocommerce-booking' ); ?>" value=""/>
			<span class="availability-close"><i class="fa fa-close"></i></span>
		</div>
		<hr />
		<p>
			<i class="fa fa-calendar"></i>&nbsp;&nbsp;<?php esc_html_e( 'Date & Time', 'woocommerce-booking' ); ?>
			<input type="text" class="update-type" style="display: none;" value="" />
			<input type="text" class="update-id" style="display: none;" value="0" />
		</p>
		<br>
		<table id="vendor-dates">
			<tr>
				<td>
					<label for="availability-start"><?php esc_html_e( 'From', 'woocommerce-booking' ); ?></label>
					<br>
					<input id="availability-start" name="availability-start" type="text" readonly />
				</td>
				<td>
					<label for="availability-end"><?php esc_html_e( 'To', 'woocommerce-booking' ); ?></label>
					<br>
					<input id="availability-end" name="availability-end" type="text" readonly />
				</td>
			</tr>
		</table>
		<div class="data-links">
			<button type="button" class="bkap-dokan-avail-delete"><?php esc_html_e( 'Delete', 'woocommerce-booking' ); ?></button>
			<div class="close-buttons">
				<button type="button" class="button-secondary bkap-dokan-avail-cancel"><?php esc_html_e( 'Cancel', 'woocommerce-booking' ); ?></button>
				&nbsp;&nbsp;
				<button type="button" class="button-secondary bkap-dokan-avail-save"><?php esc_html_e( 'Save', 'woocommerce-booking' ); ?></button>
			</div>
		</div>
	</div>
</div>
