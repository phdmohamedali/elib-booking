<?php
/**
 * Layout for Vendor Dashboard Page.
 *
 * @package BKAP/Vendor-Booking-Dashboard
 */

?>
<!-- Dashboard Page -->
<div class="bkap-vendor-container">
	<div class="bkap-vendor-content">
		<?php do_action( 'bkap_before_booking_dashboard_container', $bkap_vendor ); ?>
		<div class='bkap-dashboard-container'>
			<?php foreach ( $bkap_vendor_endpoints_group as $key => $bkap_vendor_endpoint ) : ?>
				<!-- Rows for Feature Box -->
				<div class="bkap-container-box">
				<!-- Feature Box -->
				<?php foreach ( $bkap_vendor_endpoint as $k => $v ) : ?>
					<div class="bkap-content <?php echo ( 1 === $key ) ? 'bkap-content-middle' : ''; ?> ">
						<a href="<?php esc_attr_e( $v['url'] ); ?>">
							<div class="bkap-feature-box">
								<div class="bkap-feature-icon">
									<i class="fas <?php esc_attr_e( $v['icon'] ); ?>"></i>
								</div>
								<div class="bkap-feature-heading">
									<h2><?php esc_attr_e( $v['name'] ); ?></h2>
								</div>
							</div>
						</a>
					</div>
				<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div><!-- .bkap-dashboard-container -->
	</div>
</div>
