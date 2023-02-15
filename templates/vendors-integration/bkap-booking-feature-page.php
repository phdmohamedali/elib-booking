<?php
/**
 * Layout for Features.
 *
 * @package BKAP/Vendor-Booking-Feature
 */

?>
<div class="bkap-vendor-container" id="">
	<div class="bkap-vendor-content">
		<!-- Dashboard Page -->
		<?php do_action( 'bkap_before_feature_container', $bkap_vendor ); ?>
		<div class='bkap-feature-container'>
			<div id="bkap-feature-header" class="bkap-feature-content">
				<h2><?php echo esc_html( $booking_heading ); ?> </h2>
				<?php do_action( 'bkap_vendor_feature_header', $end_point, $bkap_vendor_endpoints, $bkap_vendor ); ?>
			</div>

			<div id="bkap-feature-body" class="bkap-feature-content">
				<?php do_action( 'bkap_vendor_feature_content', $end_point, $bkap_vendor_endpoints, $bkap_vendor ); ?>
			</div>

		</div><!-- .bkap-feature-container -->
	</div><!-- .bkap-vendor-content -->
</div> <!-- .bkap-vendor-container -->
