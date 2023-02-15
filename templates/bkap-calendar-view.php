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
?>

<div id="bkap_events_loader" style="font-size: medium;">
	<h4 style="text-align: center;">
		<?php esc_html_e( 'Loading Calendar Events....', 'woocommerce-booking' ); ?>
		<span><img src=<?php echo esc_attr( bkap_ajax_loader_gif() ); ?>></span>
	</h4>
</div>
<div id='bkap-calendar'></div>
