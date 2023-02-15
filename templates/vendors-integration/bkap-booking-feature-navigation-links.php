<?php
/**
 * Layout for Feature Navigation Links in Feature Header.
 *
 * @package BKAP/Vendor-Booking-Feature-Navigation
 */

?>

<?php foreach ( $bkap_vendor_endpoints as $key => $value ) : ?>
	<a href="<?php echo esc_attr( $value['url'] ); ?>" title="<?php echo esc_attr( $value['name'] ); ?>" class="bkap-feature-navigation">
		<i class="fas <?php echo esc_attr( $value['icon'] ); ?>"></i>
	</a>
<?php endforeach; ?>
