<?php
// Add header and delete this comment. 
?>
<div id="bkap-manual-booking-section">
	<h2><?php esc_html_e( 'Create Booking', 'woocommerce-booking' ); ?></h2>
	<form method="POST">
		<table id="bkap-manual-date-selection">
			<thead>
				<tr>
					<th scope="row">
						<label><?php esc_html_e( 'Booking Data:', 'woocommerce-booking' ); ?></label>
					</th>
				</tr>
			</thead>
			<tbody>
				<tr valign="top">
					<td>
					<?php
					bkap_include_booking_form( $duplicate_id, $_product );
					?>
					</td>
				</tr>
				<tr valign="top">
					<td>
						<div class="quantity">
						<input type="number" id="manual-booking-qty" class="input-text qty text" step="1" min="1" max="" name="quantity" value="1" title="Qty" size="4" inputmode="numeric" style="display: inline-block;">
						<input type="submit" name="bkap_create_booking_2" class="bkap_create_booking button-primary" value="<?php _e( 'Create Booking', 'woocommerce-booking' ); ?>" disabled="disabled"/>
						</div>

						<input type="hidden" name="bkap_customer_id" value="<?php echo esc_attr( $booking_data['customer_id'] ); ?>" />
						<input type="hidden" name="bkap_product_id" value="<?php echo esc_attr( $product_id ); ?>" />
						<input type="hidden" name="bkap_order" value="<?php echo esc_attr( $booking_data['bkap_order'] ); ?>" />
						<input type="hidden" name="bkap_order_id" value="<?php echo esc_attr( $booking_data['order_id'] ); ?>" />
						<?php if ( $parent_id > 0 ) { ?>
						<input type="hidden" class="variation_id" value="<?php echo $product_id; ?>" />
							<?php
							$variation_class = new WC_Product_Variation( $product_id );
							$get_attributes  = $variation_class->get_variation_attributes();

							if ( is_array( $get_attributes ) && count( $get_attributes ) > 0 ) {
								foreach ( $get_attributes as $attr_name => $attr_value ) {
									$attr_value = htmlspecialchars( $attr_value, ENT_QUOTES );
									// print a hidden field for each of these.
									print( "<input type='hidden' name='$attr_name' value='$attr_value' />" );
								}
							}
						}
						?>

						<?php wp_nonce_field( 'bkap_create_booking' ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
</div>
