<?php
/**
 * The template adds Resource Form on Front End for Adding/Editing Resource.
 *
 * @package BKAP/Wcfm-Marketplace-Manage-Resource
 * @version 1.1.0
 */

?>
<a href="<?php echo $manage_resource_url; ?>" class="button" style="margin-bottom:16px;"><?php echo __( 'Back', 'woocommerce-booking' ); ?></a>

<form method="POST">
	<div id="titlewrap">
		<input type="text" id="bkap_resource_title" placeholder="<?php esc_attr_e( 'Add Resource Title', 'woocommerce-booking' ); ?>" name="bkap_resource_title" style="width:100%" value="<?php esc_attr_e( $resource_title ); // phpcs:ignore ?>" >
	</div>
	<br>
	<?php
	/* Resource Details */
	wc_get_template(
		'meta-boxes/html-bkap-resource-details.php',
		array(
			'resource_qty'          => $resource_qty,
			'resource_availability' => $resource_availability,
			'response'              => $response,
			'bkap_intervals'        => $bkap_intervals,
			'zoom_host_id'          => $zoom_host_id,
		),
		'woocommerce-booking/',
		BKAP_BOOKINGS_TEMPLATE_PATH
	);

	if ( $edit ) {
		?>
		<input type="hidden" id="bkap_resource_id" name="bkap_resource_id" value="<?php esc_attr_e( $resource_post ); // phpcs:ignore ?>">
		<?php
	}
	?>
	<input type="hidden" id="bkap_resource_url" name="bkap_resource_url" value="<?php esc_attr_e( $manage_resource_url ); // phpcs:ignore ?>">
	<div id="bkap_resource_manager_submit">
		<input type="submit" id="bkap_resource_manager" name="bkap_resource_manager" value="<?php esc_attr_e( 'Save Changes', 'woocommerce-booking' ); ?>" class="button">
	</div>
</form>
