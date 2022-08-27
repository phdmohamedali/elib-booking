<?php

/**
 * Bookings and Appointment Plugin for WooCommerce
 *
 * Modal Popup template for allowing to edit Booking
 *
 * @author      Tyche Softwares
 * @package     Bookings and Appointment Plugin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<style type="text/css">
#modal-body{
	height: 400px;
	overflow-y: auto;
}
.bkap-modal{
	padding-top: 50px !important;
}
</style>

<div id="bkap_preview_reminder_modal" class="bkap-modal">

	<!-- Save Progress Loader -->
	<div id="bkap_save" class="bkap_save"></div>

	<!-- Modal content -->
	<div class="bkap-booking-contents">

		<div class="bkap-booking-header">
			<div class="bkap-header-title">
				<h1 class="product_title entry-title">
				<?php $preview_reminder_label = apply_filters( 'bkap_preview_reminder_label', __ ( 'Preview Reminder', 'woocommerce-booking' ) ); ?>
				<?php echo esc_html( $preview_reminder_label ); ?>
				</h1>
			</div>
			<div class="bkap-header-close">&times;</div>
		</div>

		<div style="clear: both;"></div>

		<div id="modal-body" class="modal-body">

			<div class="modal-body-content"></div>
			<div class="bkap-error"></div>
		</div>

		<div class="modal-footer">
		</div>

	</div>

</div>
